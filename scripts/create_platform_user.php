<?php
declare(strict_types=1);

/**
 * Crée un utilisateur back-office (rôle plateforme « Opérateur plateforme »).
 *
 * Usage :
 *   php scripts/create_platform_user.php
 *   php scripts/create_platform_user.php --email=admin@example.com --password="Secret123!" --name="Admin BO"
 *
 * Variables d'environnement (fallback) :
 *   PLATFORM_USER_EMAIL, PLATFORM_USER_PASSWORD, PLATFORM_USER_FULL_NAME
 */

use Core\Autoloader;
use Core\Config;
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

function parseArgs(array $argv): array
{
    $email = (string) Config::env('PLATFORM_USER_EMAIL', 'backoffice@pilora.demo');
    $password = (string) Config::env('PLATFORM_USER_PASSWORD', 'BackOffice123!');
    $fullName = (string) Config::env('PLATFORM_USER_FULL_NAME', 'Opérateur Back-office');

    foreach ($argv as $i => $arg) {
        if ($i === 0) {
            continue;
        }
        if (str_starts_with($arg, '--email=')) {
            $email = trim(substr($arg, 8), " \t\"'");
        } elseif (str_starts_with($arg, '--password=')) {
            $password = substr($arg, 11);
        } elseif (str_starts_with($arg, '--name=')) {
            $fullName = trim(substr($arg, 7), " \t\"'");
        }
    }

    return ['email' => $email, 'password' => $password, 'fullName' => $fullName];
}

function ensurePlatformRbac(\PDO $pdo): int
{
    $permissions = [
        'platform.company.manage' => 'Gérer les sociétés (liste, création, édition)',
        'platform.billing.manage' => 'Gérer la facturation des sociétés',
        'platform.audit.read' => 'Consulter le journal d’audit plateforme',
        'platform.impersonate.start' => 'Démarrer / arrêter l’impersonation tenant',
    ];

    $permIds = [];
    foreach ($permissions as $code => $desc) {
        $stmt = $pdo->prepare('
            SELECT id FROM Permission
            WHERE scope = "platform" AND companyId IS NULL AND code = :code
            LIMIT 1
        ');
        $stmt->execute(['code' => $code]);
        $ex = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($ex) {
            $permIds[$code] = (int) $ex['id'];
            continue;
        }
        $pdo->prepare('
            INSERT INTO Permission (scope, companyId, code, description)
            VALUES ("platform", NULL, :code, :description)
        ')->execute(['code' => $code, 'description' => $desc]);
        $permIds[$code] = (int) $pdo->lastInsertId();
    }

    $stmt = $pdo->prepare('
        SELECT id FROM Role WHERE scope = "platform" AND companyId IS NULL AND code = "platform_operator" LIMIT 1
    ');
    $stmt->execute();
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($r) {
        return (int) $r['id'];
    }

    $pdo->prepare('
        INSERT INTO Role (scope, companyId, name, code)
        VALUES ("platform", NULL, "Opérateur plateforme", "platform_operator")
    ')->execute();

    return (int) $pdo->lastInsertId();
}

$args = parseArgs($argv);
$email = trim($args['email']);
$password = $args['password'];
$fullName = trim($args['fullName']) !== '' ? trim($args['fullName']) : 'Opérateur Back-office';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    out('Erreur : email invalide.');
    exit(1);
}
if (mb_strlen($password) < 8) {
    out('Erreur : le mot de passe doit contenir au moins 8 caractères.');
    exit(1);
}

$pdo = Connection::pdo();
$col = $pdo->query("SHOW COLUMNS FROM Company LIKE 'companyKind'");
if ($col === false || $col->rowCount() === 0) {
    out('Erreur : colonne Company.companyKind absente. Exécutez : php scripts/migrate_company_platform_kind.php');
    exit(1);
}

$pdo->beginTransaction();
try {
    ensurePlatformRbac($pdo);
    $platformCompanyId = (new CompanyRepository())->ensurePlatformOperatorCompany();

    $stmt = $pdo->prepare('SELECT id FROM `User` WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    $repo = new UserAdminRepository();
    if ($existing) {
        $userId = (int) $existing['id'];
        $pdo->prepare('UPDATE `User` SET companyId = :cid, fullName = :name, status = "active" WHERE id = :id')
            ->execute(['cid' => $platformCompanyId, 'name' => $fullName, 'id' => $userId]);
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare('UPDATE `User` SET passwordHash = :hash WHERE id = :id')
            ->execute(['hash' => $hash, 'id' => $userId]);
        $repo->assignPlatformOperatorRole($platformCompanyId, $userId);
        out('Utilisateur existant mis à jour et rôle plateforme assigné.');
    } else {
        $userId = $repo->createBasicUser($platformCompanyId, $email, $password, $fullName);
        $repo->assignPlatformOperatorRole($platformCompanyId, $userId);
        out('Utilisateur back-office créé.');
    }

    $pdo->commit();
    out('');
    out('--- Identifiants ---');
    out('Email    : ' . $email);
    out('Mot de passe : ' . $password);
    out('Nom      : ' . $fullName);
    out('UserId   : ' . $userId);
    out('CompanyId (plateforme) : ' . $platformCompanyId);
    out('');
    out('Connexion : utilisez l’URL de login habituelle avec ces identifiants.');
} catch (Throwable $e) {
    $pdo->rollBack();
    out('Erreur : ' . $e->getMessage());
    exit(1);
}
