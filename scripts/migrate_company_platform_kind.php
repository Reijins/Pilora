<?php
declare(strict_types=1);

/**
 * Ajoute Company.companyKind (tenant | platform), initialise les lignes existantes en tenant,
 * puis crée la société interne back-office et synchronise les RolePermission plateforme.
 *
 * Usage : php scripts/migrate_company_platform_kind.php
 */

use Core\Autoloader;
use Core\Database\Connection;
use Modules\Companies\Repositories\CompanyRepository;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Autoloader())->register();

$pdo = Connection::pdo();

$check = $pdo->query("SHOW COLUMNS FROM Company LIKE 'companyKind'");
if ($check !== false && $check->rowCount() === 0) {
    $pdo->exec("
        ALTER TABLE Company
            ADD COLUMN companyKind ENUM('tenant','platform') NOT NULL DEFAULT 'tenant' AFTER name
    ");
    echo "Migration: colonne companyKind ajoutée\n";
    try {
        $pdo->exec('CREATE INDEX idx_company_kind ON Company (companyKind)');
        echo "Migration: index idx_company_kind OK\n";
    } catch (\Throwable $e) {
        echo "Migration: index idx_company_kind ignoré (" . $e->getMessage() . ")\n";
    }
} else {
    echo "Migration: colonne companyKind déjà présente\n";
}

$upd = $pdo->exec("UPDATE Company SET companyKind = 'tenant' WHERE companyKind IS NULL OR companyKind = ''");
if ($upd !== false && $upd > 0) {
    echo "Migration: {$upd} ligne(s) Company normalisée(s) en tenant\n";
}

try {
    $id = (new CompanyRepository())->ensurePlatformOperatorCompany();
    echo "Migration: société plateforme back-office id={$id}\n";
} catch (\Throwable $e) {
    echo "Migration: ensurePlatformOperatorCompany erreur — " . $e->getMessage() . "\n";
    exit(1);
}

echo "Migration terminée.\n";
