<?php
declare(strict_types=1);

use Core\Autoloader;
use Core\Database\Connection;
use Modules\Projects\Repositories\ProjectRepository;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

$loader = new Autoloader();
$loader->register();

$pdo = Connection::pdo();

$row = $pdo->query("
    SELECT companyId, id AS clientId
    FROM Client
    ORDER BY id DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo "Aucun client trouvé.\n";
    exit(0);
}

$companyId = (int) ($row['companyId'] ?? 0);
$clientId = (int) ($row['clientId'] ?? 0);

echo "Diagnostic: companyId={$companyId}, clientId={$clientId}\n";

$affaires = (new ProjectRepository())->listAffairesByCompanyIdAndClientId(
    companyId: $companyId,
    clientId: $clientId,
    limit: 10
);

echo "Affaires trouvées: " . count($affaires) . "\n";
foreach ($affaires as $a) {
    echo "- projectId=" . (int) ($a['projectId'] ?? 0)
        . " name=" . (string) ($a['projectName'] ?? '')
        . " quotesCount=" . (int) ($a['quotesCount'] ?? 0)
        . " quoteAmount=" . (string) ($a['quoteAmount'] ?? 0)
        . " invoicesCount=" . (int) ($a['invoicesCount'] ?? 0)
        . " invoiceAmount=" . (string) ($a['invoiceAmount'] ?? 0)
        . " paidAmount=" . (string) ($a['paidAmount'] ?? 0)
        . " remaining=" . (string) ($a['remainingAmount'] ?? 0)
        . "\n";
}

