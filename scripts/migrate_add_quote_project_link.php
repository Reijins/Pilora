<?php
declare(strict_types=1);

use Core\Config;
use Core\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

$loader = new \Core\Autoloader();
$loader->register();

$pdo = Connection::pdo();

echo "Migration: ajout liaison Quote.projectId...\n";

$columnExistsStmt = $pdo->query("
    SELECT COUNT(*) AS c
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'Quote'
      AND COLUMN_NAME = 'projectId'
");
$columnExists = ((int) ($columnExistsStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0)) > 0;

if (!$columnExists) {
    $pdo->exec("ALTER TABLE Quote ADD COLUMN projectId BIGINT UNSIGNED NULL AFTER clientId");
    echo "- Colonne Quote.projectId ajoutée.\n";
} else {
    echo "- Colonne Quote.projectId déjà présente.\n";
}

$indexExistsStmt = $pdo->query("
    SELECT COUNT(*) AS c
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'Quote'
      AND INDEX_NAME = 'idx_quote_projectId'
");
$indexExists = ((int) ($indexExistsStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0)) > 0;

if (!$indexExists) {
    $pdo->exec("ALTER TABLE Quote ADD KEY idx_quote_projectId (companyId, projectId)");
    echo "- Index idx_quote_projectId ajouté.\n";
} else {
    echo "- Index idx_quote_projectId déjà présent.\n";
}

echo "OK\n";

