<?php
declare(strict_types=1);

namespace Modules\Rbac\Repositories;

use Core\Database\Connection;
use PDO;

final class RbacAdminRepository
{
    public function listRolesByCompanyId(int $companyId): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id, name
            FROM Role
            WHERE companyId = :companyId AND scope = "tenant"
            ORDER BY name
        ');
        $stmt->execute(['companyId' => $companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listPermissionsByCompanyId(int $companyId): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id, code, description
            FROM Permission
            WHERE companyId = :companyId AND scope = "tenant"
            ORDER BY code
        ');
        $stmt->execute(['companyId' => $companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listPermissionIdsForRole(int $companyId, int $roleId): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT permissionId
            FROM RolePermission
            WHERE companyId = :companyId AND roleId = :roleId
        ');
        $stmt->execute(['companyId' => $companyId, 'roleId' => $roleId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_values(array_map(static fn ($r) => (int) $r['permissionId'], $rows));
    }

    /**
     * @param array<int> $permissionIds
     */
    public function setPermissionsForRole(int $companyId, int $roleId, array $permissionIds): void
    {
        $pdo = Connection::pdo();

        $permissionIds = array_values(array_unique(array_map(static function ($id) {
            return is_numeric($id) ? (int) $id : 0;
        }, $permissionIds)));
        $permissionIds = array_values(array_filter($permissionIds, static fn (int $id) => $id > 0));

        // Allowed permissions for this tenant role.
        if ($permissionIds === []) {
            // On demande vide => on supprime tout.
            $pdo->prepare('DELETE FROM RolePermission WHERE companyId = :companyId AND roleId = :roleId')
                ->execute(['companyId' => $companyId, 'roleId' => $roleId]);
            return;
        }

        $placeholders = implode(',', array_fill(0, count($permissionIds), '?'));
        $stmt = $pdo->prepare('
            SELECT id
            FROM Permission
            WHERE companyId = ?
              AND scope = "tenant"
              AND id IN (' . $placeholders . ')
        ');
        $stmt->execute(array_merge([$companyId], $permissionIds));
        $allowed = array_map(static fn ($r) => (int) $r['id'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        $permissionIds = array_values(array_intersect($permissionIds, $allowed));

        $pdo->prepare('DELETE FROM RolePermission WHERE companyId = :companyId AND roleId = :roleId')
            ->execute(['companyId' => $companyId, 'roleId' => $roleId]);

        $insert = $pdo->prepare('
            INSERT INTO RolePermission (companyId, roleId, permissionId, createdAt)
            VALUES (:companyId, :roleId, :permissionId, NOW())
        ');
        foreach ($permissionIds as $permissionId) {
            $insert->execute([
                'companyId' => $companyId,
                'roleId' => $roleId,
                'permissionId' => $permissionId,
            ]);
        }
    }
}

