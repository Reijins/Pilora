<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Core\Autoloader())->register();

use Modules\Invoices\Repositories\InvoiceRepository;

$repo = new InvoiceRepository();
$rows = $repo->listByCompanyId(companyId: 1, status: null, limit: 1);

if (empty($rows)) {
    echo "Aucune facture.\n";
    exit(0);
}

$row = $rows[0];
echo "Colonnes: " . implode(', ', array_keys($row)) . "\n";
echo "clientId=" . (string) ($row['clientId'] ?? '') . "\n";

