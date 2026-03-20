<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Core\Autoloader())->register();

use Core\Database\Connection;
use Modules\Invoices\Repositories\InvoiceRepository;
use Modules\Payments\Repositories\PaymentRepository;
use Modules\Payments\Services\PaymentService;

$companyId = 1;

$pdo = Connection::pdo();
$stmt = $pdo->prepare('SELECT id FROM Invoice WHERE companyId = ? ORDER BY id DESC LIMIT 1');
$stmt->execute([$companyId]);
$invoiceId = (int) ($stmt->fetchColumn() ?: 0);

if ($invoiceId <= 0) {
    echo "Aucune facture trouvée.\n";
    exit(0);
}

$service = new PaymentService(
    invoiceRepository: new InvoiceRepository(),
    paymentRepository: new PaymentRepository(),
);

echo "Création paiement de 1.00€ sur invoiceId={$invoiceId}\n";
$service->registerSucceededPaymentAndUpdateInvoice(
    companyId: $companyId,
    invoiceId: $invoiceId,
    amount: 1.0,
    provider: 'Manuel',
    reference: 'TEST-CLI',
);

$stmt2 = $pdo->prepare('
    SELECT amountPaid, amountTotal, (amountTotal-amountPaid) AS remaining, status
    FROM Invoice WHERE companyId = ? AND id = ?
');
$stmt2->execute([$companyId, $invoiceId]);
$inv = $stmt2->fetch(PDO::FETCH_ASSOC);

echo sprintf(
    "Invoice après: paid=%s total=%s remaining=%s status=%s\n",
    (string) ($inv['amountPaid'] ?? 0),
    (string) ($inv['amountTotal'] ?? 0),
    (string) ($inv['remaining'] ?? 0),
    (string) ($inv['status'] ?? '')
);

