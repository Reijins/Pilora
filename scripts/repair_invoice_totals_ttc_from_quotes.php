<?php
/**
 * Recalcule amountTotal (TTC) depuis les devis pour les factures liées à un devis.
 * À exécuter une fois si d'anciennes factures avaient un total HT au lieu de TTC.
 *
 * Usage (CLI) : php scripts/repair_invoice_totals_ttc_from_quotes.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/core/Autoloader.php';

(new \Core\Autoloader())->register();

use Core\Database\Connection;
use Modules\Invoices\Services\InvoiceAmountsService;

$pdo = Connection::pdo();
$stmt = $pdo->query('SELECT id, companyId, quoteId, amountTotal, amountPaid FROM Invoice WHERE quoteId IS NOT NULL AND quoteId > 0');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$updated = 0;
foreach ($rows as $row) {
    $companyId = (int) ($row['companyId'] ?? 0);
    $id = (int) ($row['id'] ?? 0);
    if ($companyId <= 0 || $id <= 0) {
        continue;
    }
    try {
        $ttc = InvoiceAmountsService::canonicalTotalTtc($companyId, $row);
    } catch (\Throwable) {
        continue;
    }
    $old = round((float) ($row['amountTotal'] ?? 0), 2);
    if (abs($old - $ttc) < 0.01) {
        continue;
    }
    $paid = round((float) ($row['amountPaid'] ?? 0), 2);
    $u = $pdo->prepare('UPDATE Invoice SET amountTotal = :ttc, updatedAt = NOW() WHERE companyId = :c AND id = :id');
    $u->execute(['ttc' => $ttc, 'c' => $companyId, 'id' => $id]);
    echo "Invoice #{$id} : amountTotal {$old} -> {$ttc} (payé {$paid})\n";
    $updated++;
}

echo "Terminé. Factures mises à jour : {$updated}\n";
