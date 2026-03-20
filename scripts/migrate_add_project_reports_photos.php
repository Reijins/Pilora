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

CREATE TABLE IF NOT EXISTS ProjectReport (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  projectId BIGINT UNSIGNED NOT NULL,
  authorUserId BIGINT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL,
  content TEXT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_projectReport_companyId (companyId),
  KEY idx_projectReport_projectId (companyId, projectId),
  CONSTRAINT fk_projectReport_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_projectReport_project
    FOREIGN KEY (projectId) REFERENCES Project (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_projectReport_author
    FOREIGN KEY (authorUserId) REFERENCES `User` (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ProjectPhoto (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  projectId BIGINT UNSIGNED NOT NULL,
  uploaderUserId BIGINT UNSIGNED NULL,
  filePath VARCHAR(500) NOT NULL,
  caption VARCHAR(255) NULL,
  takenAt DATETIME NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_projectPhoto_companyId (companyId),
  KEY idx_projectPhoto_projectId (companyId, projectId),
  CONSTRAINT fk_projectPhoto_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_projectPhoto_project
    FOREIGN KEY (projectId) REFERENCES Project (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_projectPhoto_uploader
    FOREIGN KEY (uploaderUserId) REFERENCES `User` (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

$pdo->exec($sql);
echo "Migration: ProjectReport + ProjectPhoto OK\n";

