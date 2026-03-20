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

CREATE TABLE IF NOT EXISTS Project (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  clientId BIGINT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  status ENUM('planned','in_progress','completed','paused') NOT NULL DEFAULT 'planned',
  plannedStartDate DATE NULL,
  plannedEndDate DATE NULL,
  actualStartDate DATE NULL,
  actualEndDate DATE NULL,
  notes TEXT NULL,
  createdByUserId BIGINT UNSIGNED NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_project_companyId (companyId),
  KEY idx_project_clientId (companyId, clientId),
  KEY idx_project_status (companyId, status),
  CONSTRAINT fk_project_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_project_client
    FOREIGN KEY (clientId) REFERENCES Client (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ProjectAssignment (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  projectId BIGINT UNSIGNED NOT NULL,
  userId BIGINT UNSIGNED NOT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_projectAssignment (companyId, projectId, userId),
  KEY idx_projectAssignment_companyId (companyId),
  KEY idx_projectAssignment_projectId (companyId, projectId),
  KEY idx_projectAssignment_userId (companyId, userId),
  CONSTRAINT fk_projectAssignment_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_projectAssignment_project
    FOREIGN KEY (projectId) REFERENCES Project (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_projectAssignment_user
    FOREIGN KEY (userId) REFERENCES `User` (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

$pdo->exec($sql);
echo "Migration: Project + ProjectAssignment OK\n";

