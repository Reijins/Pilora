<?php
declare(strict_types=1);

/**
 * TVA par ligne, comptes, lignes de facture figées, export comptable.
 * Idempotent : ignore les colonnes/tables déjà présentes (erreur 1060 / 1050).
 */

use Core\Autoloader;
use Core\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Autoloader())->register();

$pdo = Connection::pdo();
$pdo->exec('SET NAMES utf8mb4');

function migrateColumn(PDO $pdo, string $table, string $column, string $ddl): void
{
    try {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN {$ddl}");
        echo "OK: {$table}.{$column}\n";
    } catch (PDOException $e) {
        if ((int) $e->errorInfo[1] === 1060) {
            echo "Skip (exists): {$table}.{$column}\n";
            return;
        }
        throw $e;
    }
}

function migrateTable(PDO $pdo, string $sql): void
{
    try {
        $pdo->exec($sql);
        echo "OK: table créée\n";
    } catch (PDOException $e) {
        if ((int) $e->errorInfo[1] === 1050) {
            echo "Skip (exists): table\n";
            return;
        }
        throw $e;
    }
}

// --- PriceLibraryItem ---
migrateColumn($pdo, 'PriceLibraryItem', 'defaultVatRate', 'defaultVatRate DECIMAL(5,2) NULL DEFAULT NULL COMMENT \'% TVA suggérée pour nouvelles lignes\' AFTER unitPrice');
migrateColumn($pdo, 'PriceLibraryItem', 'defaultRevenueAccount', 'defaultRevenueAccount VARCHAR(32) NULL DEFAULT NULL AFTER defaultVatRate');

// --- QuoteItem ---
migrateColumn($pdo, 'QuoteItem', 'vatRate', 'vatRate DECIMAL(5,2) NOT NULL DEFAULT 20.00 AFTER lineTotal');
migrateColumn($pdo, 'QuoteItem', 'revenueAccount', 'revenueAccount VARCHAR(32) NULL DEFAULT NULL AFTER vatRate');
migrateColumn($pdo, 'QuoteItem', 'lineVat', 'lineVat DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER revenueAccount');
migrateColumn($pdo, 'QuoteItem', 'lineTtc', 'lineTtc DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER lineVat');

// --- Client ---
migrateColumn($pdo, 'Client', 'accountingCustomerAccount', 'accountingCustomerAccount VARCHAR(32) NULL DEFAULT NULL AFTER siret');

// --- Invoice ---
migrateColumn($pdo, 'Invoice', 'accountingExportedAt', 'accountingExportedAt DATETIME NULL DEFAULT NULL AFTER notes');

migrateTable($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS InvoiceItem (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  invoiceId BIGINT UNSIGNED NOT NULL,
  priceLibraryItemId BIGINT UNSIGNED NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
  unitPrice DECIMAL(15,2) NOT NULL DEFAULT 0,
  lineTotal DECIMAL(15,2) NOT NULL DEFAULT 0,
  vatRate DECIMAL(5,2) NOT NULL DEFAULT 20.00,
  revenueAccount VARCHAR(32) NULL,
  lineVat DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  lineTtc DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  lineSort INT NOT NULL DEFAULT 0,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_invoiceItem_company (companyId),
  KEY idx_invoiceItem_invoice (companyId, invoiceId),
  CONSTRAINT fk_invoiceItem_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_invoiceItem_invoice
    FOREIGN KEY (invoiceId) REFERENCES Invoice (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
);

// --- Backfill QuoteItem : taux depuis param société (fichier JSON), puis montants ---
$stmtCompanies = $pdo->query('SELECT DISTINCT companyId FROM QuoteItem');
$companyIds = $stmtCompanies ? $stmtCompanies->fetchAll(PDO::FETCH_COLUMN) : [];
$settingsRoot = dirname(__DIR__) . '/storage/settings';

foreach ($companyIds as $cid) {
    $cid = (int) $cid;
    $vat = 20.0;
    $path = $settingsRoot . '/smtp_company_' . $cid . '.json';
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        if (is_string($raw) && $raw !== '') {
            $j = json_decode($raw, true);
            if (is_array($j) && isset($j['vat_rate']) && is_numeric($j['vat_rate'])) {
                $vat = (float) $j['vat_rate'];
            }
        }
    }
    $vat = max(0.0, min(100.0, $vat));
    $upd = $pdo->prepare('
        UPDATE QuoteItem
        SET
            vatRate = :vat,
            lineVat = ROUND(lineTotal * (:vatPct / 100), 2),
            lineTtc = ROUND(lineTotal + ROUND(lineTotal * (:vatPct2 / 100), 2), 2)
        WHERE companyId = :cid
    ');
    $upd->execute(['vat' => $vat, 'vatPct' => $vat, 'vatPct2' => $vat, 'cid' => $cid]);
}

// --- Backfill InvoiceItem depuis QuoteItem ---
$sqlFillInvoiceItems = '
    INSERT INTO InvoiceItem (
        companyId, invoiceId, priceLibraryItemId, description, quantity, unitPrice, lineTotal,
        vatRate, revenueAccount, lineVat, lineTtc, lineSort
    )
    SELECT
        i.companyId,
        i.id,
        qi.priceLibraryItemId,
        qi.description,
        qi.quantity,
        qi.unitPrice,
        qi.lineTotal,
        qi.vatRate,
        qi.revenueAccount,
        qi.lineVat,
        qi.lineTtc,
        qi.id
    FROM Invoice i
    INNER JOIN QuoteItem qi ON qi.quoteId = i.quoteId AND qi.companyId = i.companyId
    WHERE i.quoteId IS NOT NULL
      AND NOT EXISTS (
          SELECT 1 FROM InvoiceItem x WHERE x.invoiceId = i.id AND x.companyId = i.companyId
      )
';
try {
    $n = $pdo->exec($sqlFillInvoiceItems);
    echo 'OK: InvoiceItem backfill rows=' . (string) $n . "\n";
} catch (PDOException $e) {
    echo 'Backfill InvoiceItem: ' . $e->getMessage() . "\n";
}

echo "Migration terminée.\n";
