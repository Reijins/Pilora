<?php
/**
 * Migration : Client.siret, Invoice.paymentToken, Invoice.stripeCheckoutSessionId
 * Exécuter : php scripts/migrate_invoice_pay_client_siret.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use Core\Database\Connection;

$pdo = Connection::pdo();

$alters = [
    'ALTER TABLE Client ADD COLUMN siret VARCHAR(32) NULL',
    'ALTER TABLE Invoice ADD COLUMN paymentToken VARCHAR(64) NULL',
    'ALTER TABLE Invoice ADD COLUMN stripeCheckoutSessionId VARCHAR(255) NULL',
];

foreach ($alters as $sql) {
    try {
        $pdo->exec($sql);
        echo "OK: $sql\n";
    } catch (Throwable $e) {
        echo "SKIP ou erreur: " . $e->getMessage() . "\n";
    }
}

try {
    $pdo->exec('CREATE UNIQUE INDEX uq_invoice_paymentToken ON Invoice (paymentToken)');
    echo "OK: index paymentToken\n";
} catch (Throwable $e) {
    echo "SKIP index: " . $e->getMessage() . "\n";
}

echo "Terminé.\n";
