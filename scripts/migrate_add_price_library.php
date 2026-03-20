<?php
declare(strict_types=1);

use Core\Autoloader;
use Core\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Autoloader())->register();

$pdo = Connection::pdo();

$sql = <<<SQL
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS PriceLibraryItem (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  code VARCHAR(100) NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  unitLabel VARCHAR(50) NULL,
  unitPrice DECIMAL(15,2) NOT NULL DEFAULT 0,
  estimatedTimeMinutes INT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_priceLibrary_companyId (companyId),
  KEY idx_priceLibrary_status (companyId, status),
  KEY idx_priceLibrary_name (companyId, name),
  CONSTRAINT fk_priceLibrary_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

$pdo->exec($sql);
echo "Migration: PriceLibraryItem OK\n";

