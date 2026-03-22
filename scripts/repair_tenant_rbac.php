<?php
declare(strict_types=1);

/**
 * Répare le RBAC tenant pour une société existante (permissions + rôles + liens).
 *
 * Usage (recommandé sous Windows / PowerShell : email en argument séparé) :
 *   php scripts/repair_tenant_rbac.php <companyId>
 *   php scripts/repair_tenant_rbac.php <companyId> --assign-all-roles j.charpente@renovcharpente.fr
 *   php scripts/repair_tenant_rbac.php <companyId> --assign-admin admin@client.fr
 *
 * Forme avec = (souvent à mettre entre guillemets si l’email pose problème) :
 *   php scripts/repair_tenant_rbac.php 5 "--assign-all-roles=j.charpente@renovcharpente.fr"
 */

use Core\Autoloader;
use Core\Database\Connection;
use Modules\Rbac\Services\TenantRbacBootstrapService;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Autoloader())->register();

$cid = isset($argv[1]) ? (int) $argv[1] : 0;
$assignAdminEmail = '';
$assignAllRolesEmail = '';
$argc = count($argv);
for ($i = 2; $i < $argc; $i++) {
    $arg = $argv[$i];
    if (!is_string($arg)) {
        continue;
    }
    if ($arg === '--assign-admin' && isset($argv[$i + 1])) {
        $assignAdminEmail = trim((string) $argv[++$i], " \t\"'");
        continue;
    }
    if ($arg === '--assign-all-roles' && isset($argv[$i + 1])) {
        $assignAllRolesEmail = trim((string) $argv[++$i], " \t\"'");
        continue;
    }
    $pAdmin = '--assign-admin=';
    if (str_starts_with($arg, $pAdmin)) {
        $assignAdminEmail = trim(substr($arg, strlen($pAdmin)), " \t\"'");
        continue;
    }
    $pAll = '--assign-all-roles=';
    if (str_starts_with($arg, $pAll)) {
        $assignAllRolesEmail = trim(substr($arg, strlen($pAll)), " \t\"'");
        continue;
    }
}

if ($cid <= 0) {
    fwrite(STDERR, "Usage: php scripts/repair_tenant_rbac.php <companyId> [--assign-admin EMAIL] [--assign-all-roles EMAIL]\n");
    fwrite(STDERR, "Exemple : php scripts/repair_tenant_rbac.php 3 --assign-all-roles j.charpente@renovcharpente.fr\n");
    exit(1);
}

$pdo = Connection::pdo();
$stmt = $pdo->prepare('SELECT id, name, companyKind FROM Company WHERE id = ? LIMIT 1');
$stmt->execute([$cid]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    fwrite(STDERR, "Société introuvable.\n");
    exit(1);
}
if (($row['companyKind'] ?? '') === 'platform') {
    fwrite(STDERR, "Refus : société interne plateforme (pas un tenant).\n");
    exit(1);
}

$bootstrap = new TenantRbacBootstrapService();
$bootstrap->bootstrapCompany($cid);
echo "RBAC tenant OK pour companyId={$cid} (" . ($row['name'] ?? '') . ").\n";

$resolveUser = static function (string $email) use ($pdo, $cid): int {
    if ($email === '') {
        return 0;
    }
    $u = $pdo->prepare('SELECT id FROM `User` WHERE companyId = ? AND email = ? LIMIT 1');
    $u->execute([$cid, $email]);
    $ur = $u->fetch(PDO::FETCH_ASSOC);

    return $ur ? (int) $ur['id'] : 0;
};

if ($assignAllRolesEmail !== '') {
    $uid = $resolveUser($assignAllRolesEmail);
    if ($uid <= 0) {
        fwrite(STDERR, "Utilisateur introuvable pour cette société : {$assignAllRolesEmail}\n");
        exit(1);
    }
    $bootstrap->assignUserAllTenantRoles($cid, $uid);
    echo "Tous les rôles tenant assignés à user id={$uid} ({$assignAllRolesEmail}).\n";
} elseif ($assignAdminEmail !== '') {
    $uid = $resolveUser($assignAdminEmail);
    if ($uid <= 0) {
        fwrite(STDERR, "Utilisateur introuvable pour cette société : {$assignAdminEmail}\n");
        exit(1);
    }
    $bootstrap->assignUserToRole($cid, $uid, 'Admin');
    echo "Rôle Admin assigné à user id={$uid} ({$assignAdminEmail}).\n";
} else {
    echo "Optionnel : --assign-admin EMAIL ou --assign-all-roles EMAIL (email après un espace, sans =).\n";
}
