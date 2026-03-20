<?php
declare(strict_types=1);

use Core\Autoloader;
use Core\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Autoloader())->register();

$pdo = Connection::pdo();

$stmt = $pdo->query("SHOW COLUMNS FROM Quote LIKE 'proofFilePath'");
if ($stmt === false) {
    fwrite(STDERR, "Erreur SHOW COLUMNS Quote\n");
    exit(1);
}
if ($stmt->rowCount() === 0) {
    $pdo->exec('ALTER TABLE Quote ADD COLUMN proofFilePath VARCHAR(512) NULL AFTER notes');
    echo "Migration: Quote.proofFilePath ajoutée.\n";
} else {
    echo "Migration: Quote.proofFilePath déjà présente.\n";
}
