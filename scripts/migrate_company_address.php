<?php
declare(strict_types=1);

use Core\Autoloader;
use Core\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Autoloader())->register();

$pdo = Connection::pdo();

$cols = $pdo->query("SHOW COLUMNS FROM Company LIKE 'siret'")->fetchAll();
if ($cols === []) {
    $pdo->exec('ALTER TABLE Company ADD COLUMN siret VARCHAR(14) NULL AFTER name');
    echo "Migration: colonne Company.siret OK\n";
} else {
    echo "Migration: colonne Company.siret déjà présente\n";
}

$cols2 = $pdo->query("SHOW COLUMNS FROM Company LIKE 'address'")->fetchAll();
if ($cols2 === []) {
    $pdo->exec('ALTER TABLE Company ADD COLUMN address TEXT NULL AFTER siret');
    echo "Migration: colonne Company.address OK\n";
} else {
    echo "Migration: colonne Company.address déjà présente\n";
}

$cols3 = $pdo->query("SHOW COLUMNS FROM Company LIKE 'billingAddress'")->fetchAll();
if ($cols3 === []) {
    $pdo->exec('ALTER TABLE Company ADD COLUMN billingAddress TEXT NULL AFTER address');
    echo "Migration: colonne Company.billingAddress OK\n";
} else {
    echo "Migration: colonne Company.billingAddress déjà présente\n";
}

echo "Terminé.\n";
