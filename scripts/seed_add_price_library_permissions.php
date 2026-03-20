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
    'price.library.read' => 'Lire la bibliothèque de prix',
    'price.library.create' => 'Créer une prestation dans la bibliothèque de prix',
];

$stmt = $pdo->prepare('SELECT id FROM Role WHERE scope="tenant" AND companyId=? AND name="Admin" LIMIT 1');
$stmt->execute([$companyId]);
$roleId = (int) ($stmt->fetchColumn() ?: 0);
if ($roleId <= 0) {
    echo "seed_add_price_library_permissions: rôle Admin introuvable.\n";
    exit(1);
}

$pdo->beginTransaction();
try {
    foreach ($permissionCodes as $code => $desc) {
        $permStmt = $pdo->prepare('SELECT id FROM Permission WHERE scope="tenant" AND companyId=? AND code=? LIMIT 1');
        $permStmt->execute([$companyId, $code]);
        $permId = (int) ($permStmt->fetchColumn() ?: 0);
        if ($permId <= 0) {
            $pdo->prepare('INSERT INTO Permission (scope, companyId, code, description) VALUES ("tenant", ?, ?, ?)')
                ->execute([$companyId, $code, $desc]);
            $permId = (int) $pdo->lastInsertId();
        }

        $exists = $pdo->prepare('SELECT COUNT(*) AS c FROM RolePermission WHERE companyId=? AND roleId=? AND permissionId=?');
        $exists->execute([$companyId, $roleId, $permId]);
        $c = (int) ($exists->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
        if ($c <= 0) {
            $pdo->prepare('INSERT INTO RolePermission (companyId, roleId, permissionId, createdAt) VALUES (?, ?, ?, NOW())')
                ->execute([$companyId, $roleId, $permId]);
        }
    }
    $pdo->commit();
    echo "seed_add_price_library_permissions: OK\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "seed_add_price_library_permissions: erreur: " . $e->getMessage() . "\n";
    throw $e;
}

