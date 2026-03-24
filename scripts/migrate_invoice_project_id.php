<?php
/**
 * Migration : Invoice.projectId (factures manuelles / avoirs liés à une affaire sans devis)
 * Exécuter : php scripts/migrate_invoice_project_id.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use Core\Database\Connection;

$pdo = Connection::pdo();

try {
    $pdo->exec('
        ALTER TABLE Invoice
        ADD COLUMN projectId BIGINT UNSIGNED NULL
        AFTER quoteId
    ');
    echo "OK: ADD COLUMN projectId\n";
} catch (Throwable $e) {
    echo "SKIP ADD COLUMN: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec('
        ALTER TABLE Invoice
        ADD CONSTRAINT fk_invoice_project
        FOREIGN KEY (projectId) REFERENCES Project (id)
        ON DELETE SET NULL ON UPDATE CASCADE
    ');
    echo "OK: fk_invoice_project\n";
} catch (Throwable $e) {
    echo "SKIP FK: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec('
        CREATE INDEX idx_invoice_projectId ON Invoice (companyId, projectId)
    ');
    echo "OK: idx_invoice_projectId\n";
} catch (Throwable $e) {
    echo "SKIP INDEX: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec('
        UPDATE Invoice i
        INNER JOIN Quote q ON q.id = i.quoteId AND q.companyId = i.companyId
        SET i.projectId = q.projectId
        WHERE i.quoteId IS NOT NULL
          AND q.projectId IS NOT NULL
          AND (i.projectId IS NULL OR i.projectId <> q.projectId)
    ');
    echo "OK: backfill projectId depuis Quote\n";
} catch (Throwable $e) {
    echo "SKIP backfill: " . $e->getMessage() . "\n";
}

echo "Terminé.\n";
