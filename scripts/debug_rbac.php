<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Core\Autoloader())->register();

use Core\Database\Connection;
use Modules\Rbac\Repositories\RbacRepository;

$adminEmail = 'admin@pilora.demo';

$pdo = Connection::pdo();

$stmt = $pdo->prepare('SELECT id, companyId, email FROM `User` WHERE email = :email LIMIT 1');
$stmt->execute(['email' => $adminEmail]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "Admin introuvable: {$adminEmail}\n";
    exit(1);
}

$userId = (int) $user['id'];
$companyId = (int) $user['companyId'];

echo "Admin userId={$userId} companyId={$companyId}\n\n";

$repo = new RbacRepository();
$roles = $repo->getUserRoles($userId, $companyId);
$perms = $repo->getUserPermissions($userId, $companyId);

echo "Roles:\n";
foreach ($roles as $r) {
    echo "- {$r}\n";
}

echo "\nPermissions:\n";
foreach ($perms as $p) {
    echo "- {$p}\n";
}

echo "\n";

