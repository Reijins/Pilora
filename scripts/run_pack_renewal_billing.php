<?php
declare(strict_types=1);

use Core\Autoloader;
use Modules\Companies\Repositories\CompanyRepository;
use Modules\Platform\Repositories\PackRepository;
use Modules\Quotes\Services\QuoteDeliveryService;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Autoloader())->register();

$packRepo = new PackRepository();
$companyRepo = new CompanyRepository();
$mailer = new QuoteDeliveryService();

$today = new DateTimeImmutable('today');
$packs = $packRepo->listAll();
$companies = $companyRepo->listTenantCompanies(5000);
$packByName = [];
foreach ($packs as $p) {
    $nm = trim((string) ($p['name'] ?? ''));
    if ($nm !== '') {
        $packByName[$nm] = $p;
    }
}

$updated = 0;
$sent = 0;
foreach ($companies as $c) {
    $companyId = (int) ($c['id'] ?? 0);
    if ($companyId <= 0) {
        continue;
    }
    $companyPack = trim((string) ($c['billingPlan'] ?? ''));
    $renewsAt = trim((string) ($c['subscriptionRenewsAt'] ?? ''));
    $billingEmail = trim((string) ($c['billingEmail'] ?? ''));
    if ($companyPack === '' || $renewsAt === '') {
        continue;
    }
    $renewDate = DateTimeImmutable::createFromFormat('Y-m-d', substr($renewsAt, 0, 10));
    if (!$renewDate || $renewDate > $today) {
        continue;
    }
    $pack = $packByName[$companyPack] ?? null;
    if (!is_array($pack)) {
        continue;
    }

    $cycle = (string) ($c['billingCycle'] ?? 'monthly');
    if ($cycle !== 'annual') {
        $cycle = 'monthly';
    }
    $price = (float) ($pack['price'] ?? 0);
    $nextDate = $cycle === 'annual' ? $renewDate->modify('+1 year') : $renewDate->modify('+1 month');
    try {
        $companyRepo->updateBilling($companyId, [
            'billingPlan' => $companyPack,
            'billingStatus' => 'active',
            'maxSeats' => (int) ($pack['maxUsers'] ?? 0),
            'subscriptionRenewsAt' => $nextDate->format('Y-m-d'),
            'externalBillingRef' => (string) ($c['externalBillingRef'] ?? ''),
        ]);
        $updated++;
    } catch (Throwable) {
        continue;
    }

    if ($billingEmail !== '' && filter_var($billingEmail, FILTER_VALIDATE_EMAIL)) {
        try {
            $mailer->sendTestEmail(
                companyId: $companyId,
                toEmail: $billingEmail,
                subject: 'Facture de renouvellement - ' . $companyPack,
                bodyText: "Votre abonnement {$companyPack} ({$cycle}) a été renouvelé.\nMontant: " . number_format($price, 2, ',', ' ') . " EUR.\nProchaine échéance: " . $nextDate->format('Y-m-d') . "."
            );
            $sent++;
        } catch (Throwable) {
            // Ne bloque pas la mise à jour de date si l'email échoue.
        }
    }
}

echo "Renouvellements traités: {$updated}\n";
echo "Emails envoyés: {$sent}\n";

