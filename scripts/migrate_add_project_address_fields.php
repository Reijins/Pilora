<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$loader = new \Core\Autoloader();
$loader->register();

$pdo = \Core\Database\Connection::pdo();

$columns = [
    'siteAddress' => 'ALTER TABLE Project ADD COLUMN siteAddress VARCHAR(255) NULL AFTER plannedEndDate',
    'siteCity' => 'ALTER TABLE Project ADD COLUMN siteCity VARCHAR(150) NULL AFTER siteAddress',
    'sitePostalCode' => 'ALTER TABLE Project ADD COLUMN sitePostalCode VARCHAR(20) NULL AFTER siteCity',
];

foreach ($columns as $name => $sql) {
    $exists = $pdo->query("SHOW COLUMNS FROM Project LIKE '{$name}'")->fetch();
    if (!$exists) {
        $pdo->exec($sql);
        echo "Ajout colonne {$name}\n";
    } else {
        echo "Colonne {$name} deja presente\n";
    }
}

echo "Migration terminee.\n";

