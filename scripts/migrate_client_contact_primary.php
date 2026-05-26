<?php
declare(strict_types=1);

/**
 * - Ajoute `isPrimaryContact` sur Contact
 * - Ajoute `status` et `isBillable` sur Client
 * - Le premier contact de chaque client devient contact principal
 *
 * Usage : php scripts/migrate_client_contact_primary.php
 */

use Core\Autoloader;
use Core\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Autoloader())->register();

$pdo = Connection::pdo();

$cols = $pdo->query("SHOW COLUMNS FROM Contact LIKE 'isPrimaryContact'")->fetchAll();
if ($cols === []) {
    $pdo->exec('ALTER TABLE Contact ADD COLUMN isPrimaryContact TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER notes');
    $pdo->exec('ALTER TABLE Contact ADD KEY idx_contact_primary (companyId, clientId, isPrimaryContact)');
    echo "Migration: colonne Contact.isPrimaryContact OK\n";

    $pdo->exec('
        UPDATE Contact c
        INNER JOIN (
            SELECT MIN(id) AS minId, clientId, companyId
            FROM Contact
            GROUP BY clientId, companyId
        ) m ON m.minId = c.id AND m.clientId = c.clientId AND m.companyId = c.companyId
        SET c.isPrimaryContact = 1
    ');
    echo "Migration: contacts existants — premier contact = principal\n";
} else {
    echo "Migration: colonne Contact.isPrimaryContact déjà présente\n";
}

$colsClient = $pdo->query("SHOW COLUMNS FROM Client LIKE 'status'")->fetchAll();
if ($colsClient === []) {
    $pdo->exec("ALTER TABLE Client ADD COLUMN status ENUM('active','deleted') NOT NULL DEFAULT 'active' AFTER accountingCustomerAccount");
    $pdo->exec("ALTER TABLE Client ADD KEY idx_client_status (companyId, status)");
    echo "Migration: colonne Client.status OK\n";
} else {
    echo "Migration: colonne Client.status déjà présente\n";
}

$colsBillable = $pdo->query("SHOW COLUMNS FROM Client LIKE 'isBillable'")->fetchAll();
if ($colsBillable === []) {
    $pdo->exec("ALTER TABLE Client ADD COLUMN isBillable TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER status");
    echo "Migration: colonne Client.isBillable OK\n";
} else {
    echo "Migration: colonne Client.isBillable déjà présente\n";
}

echo "Terminé.\n";
