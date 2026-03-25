<?php
declare(strict_types=1);

use Core\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';

$pdo = Connection::pdo();

try {
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS PriceCategory (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            companyId BIGINT UNSIGNED NOT NULL,
            name VARCHAR(120) NOT NULL,
            defaultVatRate DECIMAL(5,2) NULL,
            defaultRevenueAccount VARCHAR(32) NULL,
            status ENUM("active","inactive") NOT NULL DEFAULT "active",
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_priceCategory_company (companyId),
            KEY idx_priceCategory_status (companyId, status),
            KEY idx_priceCategory_name (companyId, name),
            CONSTRAINT fk_priceCategory_company
                FOREIGN KEY (companyId) REFERENCES Company (id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');
    echo "OK: CREATE TABLE PriceCategory\n";
} catch (Throwable $e) {
    echo "SKIP CREATE TABLE PriceCategory: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec('
        ALTER TABLE PriceLibraryItem
        ADD COLUMN categoryId BIGINT UNSIGNED NULL
        AFTER unitPrice
    ');
    echo "OK: ADD COLUMN PriceLibraryItem.categoryId\n";
} catch (Throwable $e) {
    echo "SKIP ADD COLUMN categoryId: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec('
        CREATE INDEX idx_priceLibrary_category
        ON PriceLibraryItem (companyId, categoryId)
    ');
    echo "OK: CREATE INDEX idx_priceLibrary_category\n";
} catch (Throwable $e) {
    echo "SKIP INDEX idx_priceLibrary_category: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec('
        ALTER TABLE PriceLibraryItem
        ADD CONSTRAINT fk_priceLibrary_category
        FOREIGN KEY (categoryId) REFERENCES PriceCategory (id)
        ON DELETE SET NULL ON UPDATE CASCADE
    ');
    echo "OK: ADD FK fk_priceLibrary_category\n";
} catch (Throwable $e) {
    echo "SKIP FK fk_priceLibrary_category: " . $e->getMessage() . "\n";
}

echo "Termine.\n";

