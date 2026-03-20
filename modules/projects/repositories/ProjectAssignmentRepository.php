<?php
declare(strict_types=1);

namespace Modules\Projects\Repositories;

use Core\Database\Connection;
use PDO;

final class ProjectAssignmentRepository
{
    /**
     * @return array<int>
     */
    public function listAssignedUserIds(int $companyId, int $projectId): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT userId
            FROM ProjectAssignment
            WHERE companyId = :companyId AND projectId = :projectId
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'projectId' => $projectId,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_values(array_map(static fn ($r) => (int) $r['userId'], $rows));
    }

    /**
     * Synchronise les affectations (remplace la liste).
     *
     * @param array<int> $userIds
     */
    public function syncAssignments(int $companyId, int $projectId, array $userIds): void
    {
        $userIds = array_values(array_unique(array_filter(array_map(static function ($id) {
            return is_numeric($id) ? (int) $id : 0;
        }, $userIds), static fn (int $id) => $id > 0)));

        $pdo = Connection::pdo();
        $pdo->beginTransaction();

        try {
            $pdo->prepare('
                DELETE FROM ProjectAssignment
                WHERE companyId = :companyId AND projectId = :projectId
            ')->execute([
                'companyId' => $companyId,
                'projectId' => $projectId,
            ]);

            if ($userIds === []) {
                $pdo->commit();
                return;
            }

            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $stmtAllowed = $pdo->prepare('
                SELECT id
                FROM `User`
                WHERE companyId = ?
                  AND id IN (' . $placeholders . ')
            ');
            $stmtAllowed->execute(array_merge([$companyId], $userIds));
            $allowedUserIds = array_values(array_map(static fn ($r) => (int) $r['id'], $stmtAllowed->fetchAll(PDO::FETCH_ASSOC)));

            if ($allowedUserIds === []) {
                $pdo->commit();
                return;
            }

            $stmtInsert = $pdo->prepare('
                INSERT INTO ProjectAssignment (companyId, projectId, userId, createdAt)
                VALUES (:companyId, :projectId, :userId, NOW())
            ');
            foreach ($allowedUserIds as $uid) {
                $stmtInsert->execute([
                    'companyId' => $companyId,
                    'projectId' => $projectId,
                    'userId' => $uid,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

