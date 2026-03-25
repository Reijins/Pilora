<?php
declare(strict_types=1);

namespace Modules\Users\Repositories;

use Core\Database\Connection;
use PDO;

final class UserAdminRepository
{
    public function assignPlatformOperatorRole(int $companyId, int $userId): void
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id
            FROM Role
            WHERE scope = "platform" AND companyId IS NULL AND code = "platform_operator"
            LIMIT 1
        ');
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $roleId = (int) ($row['id'] ?? 0);
        if ($roleId <= 0) {
            throw new \RuntimeException('Role backoffice plateforme introuvable. Lancez seed_platform_rbac.php.');
        }

        $insert = $pdo->prepare('
            INSERT IGNORE INTO UserRole (companyId, userId, roleId, createdAt)
            VALUES (:companyId, :userId, :roleId, NOW())
        ');
        $insert->execute([
            'companyId' => $companyId,
            'userId' => $userId,
            'roleId' => $roleId,
        ]);
    }

    public function countActiveUsersByCompanyId(int $companyId): int
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT COUNT(*) AS c
            FROM `User`
            WHERE companyId = :companyId
              AND status IN ("active", "pending", "invited")
        ');
        $stmt->execute(['companyId' => $companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['c'] ?? 0);
    }

    public function getCompanyMaxSeats(int $companyId): ?int
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('SELECT maxSeats FROM Company WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['maxSeats'] === null) {
            return null;
        }
        return max(0, (int) $row['maxSeats']);
    }

    public function listUsersWithRoles(int $companyId): array
    {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            SELECT
                u.id,
                u.email,
                u.fullName,
                u.coutHoraire,
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
                    'coutHoraire' => $row['coutHoraire'] ?? null,
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

        $maxSeats = $this->getCompanyMaxSeats($companyId);
        if ($maxSeats !== null && $maxSeats > 0) {
            $activeUsers = $this->countActiveUsersByCompanyId($companyId);
            if ($activeUsers >= $maxSeats) {
                throw new \RuntimeException('Limite utilisateurs atteinte pour le pack.');
            }
        }

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

    public function createBasicUser(
        int $companyId,
        string $email,
        string $password,
        string $fullName
    ): int {
        $pdo = Connection::pdo();

        $maxSeats = $this->getCompanyMaxSeats($companyId);
        if ($maxSeats !== null && $maxSeats > 0) {
            $activeUsers = $this->countActiveUsersByCompanyId($companyId);
            if ($activeUsers >= $maxSeats) {
                throw new \RuntimeException('Limite utilisateurs atteinte pour le pack.');
            }
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
        return (int) $pdo->lastInsertId();
    }

    public function listBasicByCompanyId(int $companyId): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id, email, fullName, coutHoraire, status, createdAt
            FROM `User`
            WHERE companyId = :companyId
            ORDER BY id DESC
        ');
        $stmt->execute(['companyId' => $companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Tous les utilisateurs ayant un rôle plateforme (back-office), quelle que soit leur société d’attache.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listUsersWithPlatformRole(int $limit = 500): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT DISTINCT u.id, u.email, u.fullName, u.status, u.createdAt
            FROM `User` u
            INNER JOIN UserRole ur
                ON ur.userId = u.id
               AND ur.companyId = u.companyId
            INNER JOIN Role r
                ON r.id = ur.roleId
               AND r.scope = "platform"
            ORDER BY u.id DESC
            LIMIT :limit
        ');
        $stmt->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id, companyId, email, fullName, coutHoraire, status, createdAt
            FROM `User`
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function userHasPlatformRole(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT 1
            FROM UserRole ur
            INNER JOIN Role r ON r.id = ur.roleId AND r.scope = "platform"
            INNER JOIN `User` u ON u.id = ur.userId AND u.companyId = ur.companyId
            WHERE ur.userId = :userId
            LIMIT 1
        ');
        $stmt->execute(['userId' => $userId]);
        return (bool) $stmt->fetchColumn();
    }

    public function deleteByCompanyAndUserId(int $companyId, int $userId): bool
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('DELETE FROM `User` WHERE companyId = :companyId AND id = :id');
        $stmt->execute(['companyId' => $companyId, 'id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public function updateBasicByCompanyAndUserId(
        int $companyId,
        int $userId,
        string $fullName,
        string $email,
        string $status
    ): bool {
        $allowed = ['active', 'inactive', 'pending', 'invited', 'disabled'];
        $status = in_array($status, $allowed, true) ? $status : 'active';
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE `User`
            SET fullName = :fullName, email = :email, status = :status, updatedAt = NOW()
            WHERE companyId = :companyId AND id = :id
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'id' => $userId,
            'fullName' => $fullName,
            'email' => $email,
            'status' => $status,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function updateCoutHoraireForCompanyUser(int $companyId, int $userId, ?float $coutHoraire): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE `User`
            SET coutHoraire = :cout,
                updatedAt = NOW()
            WHERE companyId = :companyId AND id = :id
        ');
        $stmt->execute([
            'cout' => $coutHoraire !== null ? round(max(0.0, $coutHoraire), 2) : null,
            'companyId' => $companyId,
            'id' => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }
}

