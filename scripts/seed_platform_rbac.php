<?php
declare(strict_types=1);

use Core\Autoloader;
use Core\Config;
use Core\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Autoloader())->register();

function out(string $msg): void
{
    fwrite(STDOUT, $msg . PHP_EOL);
}

$pdo = Connection::pdo();

$companyName = (string) Config::env('SEED_COMPANY_NAME', 'Pilora Demo');
$adminEmail = (string) Config::env('SEED_ADMIN_EMAIL', 'admin@pilora.demo');

$stmt = $pdo->prepare('SELECT id FROM Company WHERE name = :name LIMIT 1');
$stmt->execute(['name' => $companyName]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    out('seed_platform_rbac: company introuvable (nom=' . $companyName . '). Lancez seed_dev.php d’abord.');
    exit(1);
}
$companyId = (int) $row['id'];

$permissions = [
    'platform.company.manage' => 'Gérer les sociétés (liste, création, édition)',
    'platform.billing.manage' => 'Gérer la facturation des sociétés',
    'platform.audit.read' => 'Consulter le journal d’audit plateforme',
    'platform.impersonate.start' => 'Démarrer / arrêter l’impersonation tenant',
];

$pdo->beginTransaction();
try {
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
        $roleId = (int) $r['id'];
    } else {
        $pdo->prepare('
            INSERT INTO Role (scope, companyId, name, code)
            VALUES ("platform", NULL, "Opérateur plateforme", "platform_operator")
        ')->execute();
        $roleId = (int) $pdo->lastInsertId();
    }

    $pdo->prepare('DELETE FROM RolePermission WHERE companyId = :cid AND roleId = :rid')
        ->execute(['cid' => $companyId, 'rid' => $roleId]);

    foreach ($permIds as $pid) {
        $pdo->prepare('
            INSERT INTO RolePermission (companyId, roleId, permissionId, createdAt)
            VALUES (:companyId, :roleId, :permissionId, NOW())
        ')->execute([
            'companyId' => $companyId,
            'roleId' => $roleId,
            'permissionId' => $pid,
        ]);
    }

    $stmt = $pdo->prepare('SELECT id FROM `User` WHERE companyId = :cid AND email = :email LIMIT 1');
    $stmt->execute(['cid' => $companyId, 'email' => $adminEmail]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        out('seed_platform_rbac: utilisateur admin introuvable (' . $adminEmail . ').');
        $pdo->rollBack();
        exit(1);
    }
    $userId = (int) $u['id'];

    $pdo->prepare('
        INSERT IGNORE INTO UserRole (companyId, userId, roleId, createdAt)
        VALUES (:companyId, :userId, :roleId, NOW())
    ')->execute([
        'companyId' => $companyId,
        'userId' => $userId,
        'roleId' => $roleId,
    ]);

    $pdo->commit();
    out('seed_platform_rbac: OK (companyId=' . $companyId . ', user=' . $adminEmail . ').');
} catch (Throwable $e) {
    $pdo->rollBack();
    out('seed_platform_rbac: erreur — ' . $e->getMessage());
    throw $e;
}
