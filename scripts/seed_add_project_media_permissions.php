<?php
declare(strict_types=1);

use Core\Autoloader;
use Core\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Autoloader())->register();

$pdo = Connection::pdo();
$companyId = 1;

$permissionCodes = [
    'project.report.read' => 'Lire les rapports de chantier',
    'project.report.create' => 'Créer des rapports de chantier',
    'project.photo.read' => 'Lire les photos de chantier',
    'project.photo.upload' => 'Téléverser des photos de chantier',
];

$stmt = $pdo->prepare('
    SELECT id FROM Role
    WHERE scope="tenant" AND companyId=? AND name="Admin" LIMIT 1
');
$stmt->execute([$companyId]);
$roleId = (int) ($stmt->fetchColumn() ?: 0);

if ($roleId <= 0) {
    echo "seed_add_project_media_permissions: rôle Admin introuvable.\n";
    exit(1);
}

$pdo->beginTransaction();
try {
    foreach ($permissionCodes as $code => $desc) {
        $permStmt = $pdo->prepare('
            SELECT id FROM Permission
            WHERE scope="tenant" AND companyId=? AND code=? LIMIT 1
        ');
        $permStmt->execute([$companyId, $code]);
        $permissionId = (int) ($permStmt->fetchColumn() ?: 0);

        if ($permissionId <= 0) {
            $ins = $pdo->prepare('
                INSERT INTO Permission (scope, companyId, code, description)
                VALUES ("tenant", ?, ?, ?)
            ');
            $ins->execute([$companyId, $code, $desc]);
            $permissionId = (int) $pdo->lastInsertId();
        }

        $existsStmt = $pdo->prepare('
            SELECT COUNT(*) AS c FROM RolePermission
            WHERE companyId=? AND roleId=? AND permissionId=?
        ');
        $existsStmt->execute([$companyId, $roleId, $permissionId]);
        $c = (int) ($existsStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

        if ($c <= 0) {
            $pdo->prepare('
                INSERT INTO RolePermission (companyId, roleId, permissionId, createdAt)
                VALUES (?, ?, ?, NOW())
            ')->execute([$companyId, $roleId, $permissionId]);
        }
    }

    $pdo->commit();
    echo "seed_add_project_media_permissions: OK\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "seed_add_project_media_permissions: erreur: " . $e->getMessage() . "\n";
    throw $e;
}

