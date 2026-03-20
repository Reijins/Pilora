<?php
declare(strict_types=1);

use Core\Autoloader;
use Core\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Autoloader())->register();

$pdo = Connection::pdo();

$check = $pdo->query("SHOW COLUMNS FROM Company LIKE 'billingPlan'");
if ($check !== false && $check->rowCount() === 0) {
    $pdo->exec('
        ALTER TABLE Company
            ADD COLUMN billingPlan VARCHAR(80) NULL AFTER status,
            ADD COLUMN billingStatus ENUM(\'trial\',\'active\',\'past_due\',\'cancelled\') NULL AFTER billingPlan,
            ADD COLUMN maxSeats INT UNSIGNED NULL AFTER billingStatus,
            ADD COLUMN subscriptionRenewsAt DATE NULL AFTER maxSeats,
            ADD COLUMN externalBillingRef VARCHAR(120) NULL AFTER subscriptionRenewsAt
    ');
    echo "Migration: colonnes billing Company OK\n";
} else {
    echo "Migration: colonnes billing Company déjà présentes\n";
}

$stmt = $pdo->query("SHOW TABLES LIKE 'AuditLog'");
if ($stmt !== false && $stmt->rowCount() === 0) {
    $pdo->exec('
        CREATE TABLE AuditLog (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            companyId BIGINT UNSIGNED NOT NULL,
            actorUserId BIGINT UNSIGNED NOT NULL,
            action VARCHAR(120) NOT NULL,
            targetCompanyId BIGINT UNSIGNED NULL,
            metadata TEXT NULL,
            ipAddress VARCHAR(45) NOT NULL,
            userAgent VARCHAR(255) NULL,
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_audit_company (companyId),
            KEY idx_audit_actor (actorUserId),
            KEY idx_audit_created (createdAt),
            CONSTRAINT fk_audit_company FOREIGN KEY (companyId) REFERENCES Company (id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_audit_actor FOREIGN KEY (actorUserId) REFERENCES `User` (id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_audit_target FOREIGN KEY (targetCompanyId) REFERENCES Company (id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');
    echo "Migration: table AuditLog OK\n";
} else {
    echo "Migration: table AuditLog déjà présente\n";
}
