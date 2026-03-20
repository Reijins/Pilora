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

CREATE TABLE IF NOT EXISTS Invoice (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  quoteId BIGINT UNSIGNED NULL,
  clientId BIGINT UNSIGNED NOT NULL,
  invoiceNumber VARCHAR(50) NULL,
  title VARCHAR(255) NULL,
  dueDate DATE NOT NULL,
  status ENUM('brouillon','envoyee','partiellement_payee','payee','echue') NOT NULL DEFAULT 'brouillon',
  amountTotal DECIMAL(15,2) NOT NULL DEFAULT 0,
  amountPaid DECIMAL(15,2) NOT NULL DEFAULT 0,
  createdByUserId BIGINT UNSIGNED NULL,
  sentAt DATETIME NULL,
  paidAt DATETIME NULL,
  notes TEXT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_invoice_companyId (companyId),
  KEY idx_invoice_clientId (companyId, clientId),
  KEY idx_invoice_quoteId (companyId, quoteId),
  KEY idx_invoice_status (companyId, status),
  CONSTRAINT fk_invoice_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_invoice_client
    FOREIGN KEY (clientId) REFERENCES Client (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_invoice_quote
    FOREIGN KEY (quoteId) REFERENCES Quote (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Payment (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  invoiceId BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(100) NULL,
  reference VARCHAR(150) NULL,
  amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
  status ENUM('pending','succeeded','failed','refunded') NOT NULL DEFAULT 'pending',
  paidAt DATETIME NULL,
  metadata TEXT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_payment_companyId (companyId),
  KEY idx_payment_invoiceId (companyId, invoiceId),
  KEY idx_payment_status (companyId, status),
  CONSTRAINT fk_payment_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_payment_invoice
    FOREIGN KEY (invoiceId) REFERENCES Invoice (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

$pdo->exec($sql);
echo "Migration: Invoice + Payment OK\n";

