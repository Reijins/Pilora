<?php
declare(strict_types=1);

namespace Modules\Rbac\Repositories;

use Core\Database\Connection;
use PDO;

final class RbacRepository
{
    public function getUserRoles(int $userId, int $companyId): array
    {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            SELECT DISTINCT r.code, r.name
            FROM UserRole ur
            INNER JOIN Role r
                ON r.id = ur.roleId
               AND r.scope = "tenant"
               AND r.companyId = ?
            WHERE ur.userId = ?
              AND ur.companyId = ?
            ORDER BY r.name
        ');
        $stmt->execute([
            $companyId,
            $userId,
            $companyId,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $roles = [];
        foreach ($rows as $row) {
            $roles[] = $row['code'] ?: $row['name'];
        }

        $stmtPlat = $pdo->prepare('
            SELECT DISTINCT r.code, r.name
            FROM UserRole ur
            INNER JOIN Role r
                ON r.id = ur.roleId
               AND r.scope = "platform"
            WHERE ur.userId = ?
              AND ur.companyId = ?
            ORDER BY r.name
        ');
        $stmtPlat->execute([$userId, $companyId]);
        foreach ($stmtPlat->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $roles[] = $row['code'] ?: $row['name'];
        }

        return array_values(array_unique($roles));
    }

    public function getUserPermissions(int $userId, int $companyId): array
    {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            SELECT DISTINCT p.code
            FROM UserRole ur
            INNER JOIN Role r
                ON r.id = ur.roleId
               AND r.scope = "tenant"
               AND r.companyId = ?
            INNER JOIN RolePermission rp
                ON rp.roleId = r.id
               AND rp.companyId = ?
            INNER JOIN Permission p
                ON p.id = rp.permissionId
               AND p.scope = "tenant"
               AND p.companyId = ?
            WHERE ur.userId = ?
              AND ur.companyId = ?
            ORDER BY p.code
        ');
        $stmt->execute([
            $companyId,
            $companyId,
            $companyId,
            $userId,
            $companyId,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $codes = array_map(static fn ($r) => $r['code'], $rows);

        $stmtPlat = $pdo->prepare('
            SELECT DISTINCT p.code
            FROM UserRole ur
            INNER JOIN Role r
                ON r.id = ur.roleId
               AND r.scope = "platform"
            INNER JOIN RolePermission rp
                ON rp.roleId = r.id
               AND rp.companyId = ur.companyId
            INNER JOIN Permission p
                ON p.id = rp.permissionId
               AND p.scope = "platform"
            WHERE ur.userId = ?
              AND ur.companyId = ?
            ORDER BY p.code
        ');
        $stmtPlat->execute([$userId, $companyId]);
        foreach ($stmtPlat->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $codes[] = $row['code'];
        }

        $codes = array_values(array_unique($codes));
        sort($codes);

        return $codes;
    }
}

