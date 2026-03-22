<?php
declare(strict_types=1);

/**
 * Remet la base à un état minimal : une société back-office (platform) et un seul utilisateur admin plateforme.
 * Supprime toutes les sociétés clientes (tenants), données métier, autres utilisateurs et sessions.
 *
 * Les rôles / permissions globaux (scope platform, companyId NULL) sont conservés.
 *
 * Usage :
 *   php scripts/clean_database_platform_admin.php --yes
 *   php scripts/clean_database_platform_admin.php --yes --email=admin@example.com
 *   php scripts/clean_database_platform_admin.php --dry-run
 *
 * Prérequis : colonne Company.companyKind (voir migrate_company_platform_kind.php).
 */

use Core\Autoloader;
use Core\Database\Connection;
use Modules\Companies\Repositories\CompanyRepository;
use Modules\Users\Repositories\UserAdminRepository;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Autoloader())->register();

function out(string $msg): void
{
    fwrite(STDOUT, $msg . PHP_EOL);
}

function tableExists(\PDO $pdo, string $name): bool
{
    // SHOW TABLES + placeholders : incompatible avec PDO en requêtes préparées natives (MariaDB/MySQL).
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        return false;
    }
    $stmt = $pdo->prepare('
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = :name
        LIMIT 1
    ');
    $stmt->execute(['name' => $name]);
    return (bool) $stmt->fetchColumn();
}

function parseArgs(array $argv): array
{
    $yes = false;
    $dry = false;
    $email = '';
    foreach ($argv as $i => $arg) {
        if ($i === 0) {
            continue;
        }
        if ($arg === '--yes') {
            $yes = true;
        } elseif ($arg === '--dry-run') {
            $dry = true;
        } elseif (str_starts_with($arg, '--email=')) {
            $email = trim(substr($arg, 8), " \t\"'");
        }
    }
    return ['yes' => $yes, 'dry' => $dry, 'email' => $email];
}

/** @return array{0:int,1:string}|null [userId, email] */
function resolveKeepPlatformUser(\PDO $pdo, string $emailFilter): ?array
{
    $sql = '
        SELECT u.id AS userId, u.email AS email
        FROM `User` u
        INNER JOIN UserRole ur ON ur.userId = u.id AND ur.companyId = u.companyId
        INNER JOIN Role r ON r.id = ur.roleId AND r.scope = "platform"
    ';
    $params = [];
    if ($emailFilter !== '') {
        $sql .= ' WHERE u.email = :email ';
        $params['email'] = $emailFilter;
    }
    $sql .= ' ORDER BY u.id ASC LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    return [(int) $row['userId'], (string) $row['email']];
}

$args = parseArgs($argv);
$dryRun = $args['dry'];
$confirmed = $args['yes'];
$emailArg = $args['email'];

if (!$dryRun && !$confirmed) {
    out('ATTENTION : ce script supprime presque toutes les données de la base.');
    out('Ajoutez --yes pour confirmer, ou --dry-run pour simuler.');
    exit(1);
}

$pdo = Connection::pdo();

$col = $pdo->query("SHOW COLUMNS FROM Company LIKE 'companyKind'");
if ($col === false || $col->rowCount() === 0) {
    out('Erreur : colonne Company.companyKind absente. Exécutez : php scripts/migrate_company_platform_kind.php');
    exit(1);
}

$keep = resolveKeepPlatformUser($pdo, $emailArg);
if ($keep === null) {
    out('Aucun utilisateur avec un rôle plateforme (scope=platform) trouvé' . ($emailArg !== '' ? ' pour l’email : ' . $emailArg : '') . '.');
    out('Créez un compte back-office ou lancez les scripts de seed appropriés.');
    exit(1);
}

[$keepUserId, $keepEmail] = $keep;

$platformCompanyId = (new CompanyRepository())->ensurePlatformOperatorCompany();

$userStmt = $pdo->prepare('SELECT id, companyId, email, fullName FROM `User` WHERE id = :id LIMIT 1');
$userStmt->execute(['id' => $keepUserId]);
$userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
if (!$userRow) {
    out('Utilisateur à conserver introuvable (id=' . $keepUserId . ').');
    exit(1);
}

out('--- Résumé ---');
out('Utilisateur conservé : id=' . $keepUserId . ', email=' . $keepEmail);
out('Société plateforme (back-office) : id=' . $platformCompanyId);
out('Données tenants / projets / devis / factures / audit : seront supprimées.');

if ($dryRun) {
    out('[dry-run] Aucune modification effectuée.');
    exit(0);
}

$pdo->beginTransaction();
try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

    // Un seul rôle plateforme + société interne back-office
    $oldCompanyId = (int) ($userRow['companyId'] ?? 0);
    $pdo->prepare('DELETE FROM UserRole WHERE userId = :uid')->execute(['uid' => $keepUserId]);
    $pdo->prepare('UPDATE `User` SET companyId = :cid WHERE id = :uid')
        ->execute(['cid' => $platformCompanyId, 'uid' => $keepUserId]);
    (new UserAdminRepository())->assignPlatformOperatorRole($platformCompanyId, $keepUserId);
    if ($oldCompanyId !== $platformCompanyId) {
        out('Utilisateur rattaché à la société plateforme (ancien companyId=' . $oldCompanyId . ').');
    }

    $tablesDeleteAll = [
        'AuditLog',
        'UserSession',
        'LeaveRequest',
        'ProjectPhoto',
        'ProjectReport',
        'PlanningEntry',
        'Task',
        'ProjectAssignment',
        'Project',
        'Payment',
        'Invoice',
        'QuoteItem',
        'Quote',
        'Contact',
        'Client',
        'PriceLibraryItem',
    ];

    foreach ($tablesDeleteAll as $t) {
        if (!tableExists($pdo, $t)) {
            continue;
        }
        $pdo->exec('DELETE FROM `' . str_replace('`', '``', $t) . '`');
        out('Vidage : ' . $t);
    }

    $pdo->prepare('DELETE FROM UserRole WHERE userId <> :uid')->execute(['uid' => $keepUserId]);
    out('UserRole : conservé pour user id=' . $keepUserId);

    $pdo->prepare('DELETE FROM `User` WHERE id <> :uid')->execute(['uid' => $keepUserId]);
    out('User : conservé id=' . $keepUserId);

    $pdo->prepare('DELETE FROM RolePermission WHERE companyId <> :cid')->execute(['cid' => $platformCompanyId]);
    out('RolePermission : conservé pour companyId plateforme uniquement.');

    $pdo->prepare('
        DELETE FROM Role
        WHERE companyId IS NOT NULL AND companyId <> :cid
    ')->execute(['cid' => $platformCompanyId]);
    out('Role : sociétés clients supprimées.');

    $pdo->prepare('
        DELETE FROM Permission
        WHERE companyId IS NOT NULL AND companyId <> :cid
    ')->execute(['cid' => $platformCompanyId]);
    out('Permission : permissions tenant supprimées.');

    $pdo->prepare('DELETE FROM Company WHERE id <> :cid')->execute(['cid' => $platformCompanyId]);
    out('Company : conservée id=' . $platformCompanyId . ' (Back-office interne).');

    (new CompanyRepository())->syncPlatformRolePermissionsForCompany($platformCompanyId);

    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    out('Erreur : ' . $e->getMessage());
    exit(1);
}

out('');
out('Terminé. Connexion possible avec : ' . $keepEmail);
out('Les packs plateforme sont dans storage/settings/platform_packs.json (non modifiés ici).');
