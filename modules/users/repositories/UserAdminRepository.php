<?php
declare(strict_types=1);

namespace Modules\Users\Repositories;

use Core\Database\Connection;
use PDO;

final class UserAdminRepository
{
    public function listUsersWithRoles(int $companyId): array
    {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            SELECT
                u.id,
                u.email,
                u.fullName,
                u.status,
                r.id AS roleId,
                r.name AS roleName
            FROM `User` u
            LEFT JOIN UserRole ur
                ON ur.userId = u.id
               AND ur.companyId = :companyId_join_ur
            LEFT JOIN Role r
                ON r.id = ur.roleId
               AND r.companyId = :companyId_join_r
               AND r.scope = "tenant"
            WHERE u.companyId = :companyId
            ORDER BY u.id DESC
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'companyId_join_ur' => $companyId,
            'companyId_join_r' => $companyId,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $byUser = [];
        foreach ($rows as $row) {
            $userId = (int) $row['id'];
            if (!isset($byUser[$userId])) {
                $byUser[$userId] = [
                    'id' => $userId,
                    'email' => $row['email'],
                    'fullName' => $row['fullName'] ?? null,
                    'status' => $row['status'],
                    'roles' => [],
                ];
            }
            if (!empty($row['roleId'])) {
                $byUser[$userId]['roles'][] = [
                    'id' => (int) $row['roleId'],
                    'name' => $row['roleName'],
                ];
            }
        }

        return array_values($byUser);
    }

    public function listRoleIdsByCompanyId(int $companyId): array
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

    /**
     * @param array<int> $roleIds
     */
    public function createUserWithRoles(
        int $companyId,
        string $email,
        string $password,
        string $fullName,
        array $roleIds
    ): int {
        $pdo = Connection::pdo();

        $roleIds = array_values(array_unique(array_map(static function ($id) {
            return is_numeric($id) ? (int) $id : 0;
        }, $roleIds)));
        $roleIds = array_values(array_filter($roleIds, static fn (int $id) => $id > 0));

        if ($roleIds === []) {
            throw new \InvalidArgumentException('Au moins un rôle est requis.');
        }

        // Validation: ne garder que les rôles qui appartiennent à cette entreprise.
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $pdo->prepare('
            SELECT id
            FROM Role
            WHERE companyId = ?
              AND scope = "tenant"
              AND id IN (' . $placeholders . ')
        ');
        $stmt->execute(array_merge([$companyId], $roleIds));
        $allowedRoleIds = array_map(static fn ($r) => (int) $r['id'], $stmt->fetchAll(PDO::FETCH_ASSOC));

        $roleIds = array_values(array_intersect($roleIds, $allowedRoleIds));
        if ($roleIds === []) {
            throw new \InvalidArgumentException('Rôles invalides pour cette entreprise.');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare('
            INSERT INTO `User` (companyId, email, passwordHash, fullName, status)
            VALUES (:companyId, :email, :passwordHash, :fullName, "active")
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'email' => $email,
            'passwordHash' => $hash,
            'fullName' => $fullName,
        ]);
        $userId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare('
            INSERT INTO UserRole (companyId, userId, roleId, createdAt)
            VALUES (:companyId, :userId, :roleId, NOW())
        ');
        foreach ($roleIds as $roleId) {
            $stmt->execute([
                'companyId' => $companyId,
                'userId' => $userId,
                'roleId' => $roleId,
            ]);
        }

        return $userId;
    }
}

