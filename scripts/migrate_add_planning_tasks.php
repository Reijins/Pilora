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

CREATE TABLE IF NOT EXISTS Task (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  projectId BIGINT UNSIGNED NULL,
  assignedUserId BIGINT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  status ENUM('todo','in_progress','done') NOT NULL DEFAULT 'todo',
  dueAt DATETIME NULL,
  createdByUserId BIGINT UNSIGNED NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_task_companyId (companyId),
  KEY idx_task_projectId (companyId, projectId),
  CONSTRAINT fk_task_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_task_project
    FOREIGN KEY (projectId) REFERENCES Project (id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_task_assigned_user
    FOREIGN KEY (assignedUserId) REFERENCES `User` (id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_task_created_by
    FOREIGN KEY (createdByUserId) REFERENCES `User` (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS PlanningEntry (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  projectId BIGINT UNSIGNED NULL,
  taskId BIGINT UNSIGNED NULL,
  userId BIGINT UNSIGNED NULL,
  entryType ENUM('task','absence','meeting','other') NOT NULL DEFAULT 'task',
  title VARCHAR(255) NOT NULL,
  notes TEXT NULL,
  startAt DATETIME NOT NULL,
  endAt DATETIME NOT NULL,
  createdByUserId BIGINT UNSIGNED NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_planning_companyId (companyId),
  KEY idx_planning_projectId (companyId, projectId),
  KEY idx_planning_userId (companyId, userId),
  KEY idx_planning_range (companyId, startAt, endAt),
  CONSTRAINT fk_planning_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_planning_project
    FOREIGN KEY (projectId) REFERENCES Project (id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_planning_task
    FOREIGN KEY (taskId) REFERENCES Task (id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_planning_user
    FOREIGN KEY (userId) REFERENCES `User` (id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_planning_created_by
    FOREIGN KEY (createdByUserId) REFERENCES `User` (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

$pdo->exec($sql);
echo "Migration: Planning Task OK\n";

