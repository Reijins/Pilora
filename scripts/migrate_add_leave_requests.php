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

CREATE TABLE IF NOT EXISTS LeaveRequest (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  userId BIGINT UNSIGNED NOT NULL,
  type ENUM('conges','absence') NOT NULL DEFAULT 'conges',
  startDate DATE NOT NULL,
  endDate DATE NOT NULL,
  reason TEXT NULL,
  status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  approvedByUserId BIGINT UNSIGNED NULL,
  approvedAt DATETIME NULL,
  rejectionReason VARCHAR(255) NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_leaveRequest_companyId (companyId),
  KEY idx_leaveRequest_userId (companyId, userId),
  KEY idx_leaveRequest_status (companyId, status),
  CONSTRAINT fk_leaveRequest_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_leaveRequest_user
    FOREIGN KEY (userId) REFERENCES `User` (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_leaveRequest_approvedBy
    FOREIGN KEY (approvedByUserId) REFERENCES `User` (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

$pdo->exec($sql);
echo "Migration: LeaveRequest OK\n";

