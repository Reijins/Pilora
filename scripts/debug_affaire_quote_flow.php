<?php
declare(strict_types=1);

use Core\Config;
use Core\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

$loader = new \Core\Autoloader();
$loader->register();

try {
    $pdo = Connection::pdo();
} catch (\Throwable $e) {
    fwrite(STDERR, "Connexion BDD impossible: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "=== DIAGNOSTIC AFFAIRE/DEVIS ===" . PHP_EOL;

// 1) Vérifier schéma Quote.projectId
$colStmt = $pdo->query("
    SELECT COUNT(*) AS c
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'Quote'
      AND COLUMN_NAME = 'projectId'
");
$hasProjectId = ((int) ($colStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0)) > 0;
echo "Quote.projectId présent: " . ($hasProjectId ? "OUI" : "NON") . PHP_EOL;

// 2) Dernières affaires
$projects = $pdo->query("
    SELECT id, companyId, clientId, name, createdAt
    FROM Project
    ORDER BY id DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

echo "Dernières affaires (max 5): " . count($projects) . PHP_EOL;
foreach ($projects as $p) {
    $projectId = (int) ($p['id'] ?? 0);
    $companyId = (int) ($p['companyId'] ?? 0);
    $clientId = (int) ($p['clientId'] ?? 0);
    $name = (string) ($p['name'] ?? '');

    $quotesByProject = 0;
    if ($hasProjectId) {
        $q1 = $pdo->prepare("SELECT COUNT(*) AS c FROM Quote WHERE companyId = ? AND projectId = ?");
        $q1->execute([$companyId, $projectId]);
        $quotesByProject = (int) ($q1->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    }

    $q2 = $pdo->prepare("SELECT COUNT(*) AS c FROM Quote WHERE companyId = ? AND clientId = ? AND title IN (?, ?)");
    $q2->execute([$companyId, $clientId, $name, 'Devis - ' . $name]);
    $quotesByTitle = (int) ($q2->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

    echo "- Affaire #{$projectId} | {$name} | devis(projectId)={$quotesByProject} | devis(titre)={$quotesByTitle}" . PHP_EOL;
}

// 3) Permissions quote.create par utilisateur
$users = $pdo->query("
    SELECT u.id, u.email, u.companyId
    FROM User u
    ORDER BY u.id DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$permStmt = $pdo->prepare("
    SELECT COUNT(*) AS c
    FROM UserRole ur
    INNER JOIN RolePermission rp
        ON rp.companyId = ur.companyId
       AND rp.roleId = ur.roleId
    INNER JOIN Permission p
        ON p.id = rp.permissionId
    WHERE ur.companyId = ?
      AND ur.userId = ?
      AND p.code = 'quote.create'
");

echo "Utilisateurs récents et droit quote.create:" . PHP_EOL;
foreach ($users as $u) {
    $uid = (int) ($u['id'] ?? 0);
    $cid = (int) ($u['companyId'] ?? 0);
    $permStmt->execute([$cid, $uid]);
    $has = ((int) ($permStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0)) > 0;
    echo "- User #{$uid} (" . (string) ($u['email'] ?? 'n/a') . "): " . ($has ? "OUI" : "NON") . PHP_EOL;
}

echo "=== FIN DIAGNOSTIC ===" . PHP_EOL;

