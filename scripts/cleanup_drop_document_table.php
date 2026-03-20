<?php
declare(strict_types=1);

use Core\Autoloader;
use Core\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Autoloader())->register();

$pdo = Connection::pdo();

$pdo->beginTransaction();
try {
    // 1) Supprimer les associations RBAC liées aux permissions document.*
    $stmtPermIds = $pdo->prepare('
        SELECT id
        FROM Permission
        WHERE scope = "tenant"
          AND code IN ("document.read", "document.upload")
    ');
    $stmtPermIds->execute();
    $permIds = array_values(array_map(static fn ($r) => (int) $r['id'], $stmtPermIds->fetchAll(PDO::FETCH_ASSOC) ?: []));

    if (!empty($permIds)) {
        $ph = implode(',', array_fill(0, count($permIds), '?'));
        $stmtDelRp = $pdo->prepare('DELETE FROM RolePermission WHERE permissionId IN (' . $ph . ')');
        $stmtDelRp->execute($permIds);

        $stmtDelPerm = $pdo->prepare('DELETE FROM Permission WHERE id IN (' . $ph . ')');
        $stmtDelPerm->execute($permIds);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Erreur nettoyage permissions document.* : " . $e->getMessage() . PHP_EOL);
    throw $e;
}

// 2) DROP TABLE Document (DDL hors transaction MySQL)
// Désactivation temporaire FK checks pour garantir le drop propre.
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('DROP TABLE IF EXISTS Document');
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "Cleanup: table Document supprimée + permissions document.* retirées." . PHP_EOL;

