<?php
declare(strict_types=1);

/**
 * Demandes de démo depuis le site marketing public.
 *
 * Usage : php scripts/migrate_demo_requests.php
 */

use Core\Autoloader;
use Core\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Autoloader())->register();

$pdo = Connection::pdo();

$pdo->exec('
    CREATE TABLE IF NOT EXISTS DemoRequest (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      name VARCHAR(120) NOT NULL,
      email VARCHAR(255) NOT NULL,
      companyName VARCHAR(255) NULL,
      message TEXT NULL,
      status ENUM("new","contacted","closed","spam") NOT NULL DEFAULT "new",
      notes TEXT NULL,
      ipAddress VARCHAR(45) NOT NULL,
      userAgent VARCHAR(255) NULL,
      notifySentAt DATETIME NULL,
      ackSentAt DATETIME NULL,
      createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_demo_status (status),
      KEY idx_demo_created (createdAt),
      KEY idx_demo_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
');

echo "Migration: table DemoRequest OK\n";
