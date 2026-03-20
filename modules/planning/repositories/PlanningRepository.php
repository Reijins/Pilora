<?php
declare(strict_types=1);

namespace Modules\Planning\Repositories;

use Core\Database\Connection;
use PDO;

final class PlanningRepository
{
    public function findDuplicateEntryId(
        int $companyId,
        ?int $projectId,
        ?int $taskId,
        ?int $userId,
        string $entryType,
        string $title,
        \DateTimeImmutable $startAt,
        \DateTimeImmutable $endAt
    ): ?int {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            SELECT id
            FROM PlanningEntry
            WHERE companyId = :companyId
              AND projectId <=> :projectId
              AND taskId <=> :taskId
              AND userId <=> :userId
              AND entryType = :entryType
              AND title = :title
              AND startAt = :startAt
              AND endAt = :endAt
            LIMIT 1
        ');

        $stmt->execute([
            'companyId' => $companyId,
            'projectId' => $projectId !== null && $projectId > 0 ? $projectId : null,
            'taskId' => $taskId !== null && $taskId > 0 ? $taskId : null,
            'userId' => $userId !== null && $userId > 0 ? $userId : null,
            'entryType' => $entryType,
            'title' => $title,
            'startAt' => $startAt->format('Y-m-d H:i:s'),
            'endAt' => $endAt->format('Y-m-d H:i:s'),
        ]);

        $id = $stmt->fetchColumn();
        if ($id === false || $id === null) {
            return null;
        }
        return (int) $id;
    }

    /**
     * @return array<int, array{
     *   id:int,
     *   title:string,
     *   entryType:string,
     *   startAt:string,
     *   endAt:string,
     *   projectName:?string,
     *   userName:?string
     * }>
     */
    public function listByCompanyAndRange(
        int $companyId,
        ?int $projectId,
        ?int $userId,
        \DateTimeImmutable $rangeStart,
        \DateTimeImmutable $rangeEnd
    ): array {
        $pdo = Connection::pdo();

        $sql = '
            SELECT
                pe.id,
                pe.title,
                pe.entryType,
                pe.startAt,
                pe.endAt,
                p.name AS projectName,
                COALESCE(u.fullName, u.email) AS userName
            FROM PlanningEntry pe
            LEFT JOIN Project p
                ON p.id = pe.projectId
               AND p.companyId = pe.companyId
            LEFT JOIN `User` u
                ON u.id = pe.userId
               AND u.companyId = pe.companyId
            WHERE pe.companyId = :companyId
              AND pe.startAt >= :rangeStart
              AND pe.endAt <= :rangeEnd
        ';

        if ($projectId !== null && $projectId > 0) {
            $sql .= ' AND pe.projectId = :projectId ';
        }

        if ($userId !== null && $userId > 0) {
            $sql .= ' AND pe.userId = :userId ';
        }

        $sql .= ' ORDER BY pe.startAt ASC LIMIT 500';

        $params = [
            'companyId' => $companyId,
            'rangeStart' => $rangeStart->format('Y-m-d H:i:s'),
            'rangeEnd' => $rangeEnd->format('Y-m-d H:i:s'),
        ];
        if ($projectId !== null && $projectId > 0) {
            $params['projectId'] = $projectId;
        }
        if ($userId !== null && $userId > 0) {
            $params['userId'] = $userId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createPlanningEntry(
        int $companyId,
        ?int $projectId,
        ?int $taskId,
        ?int $userId,
        string $entryType,
        string $title,
        ?string $notes,
        \DateTimeImmutable $startAt,
        \DateTimeImmutable $endAt,
        ?int $createdByUserId
    ): int {
        $pdo = Connection::pdo();

        if ($endAt <= $startAt) {
            throw new \InvalidArgumentException('La fin doit être après le début.');
        }

        $entryTypeAllowed = ['task', 'absence', 'meeting', 'other'];
        if (!in_array($entryType, $entryTypeAllowed, true)) {
            $entryType = 'task';
        }

        $stmt = $pdo->prepare('
            INSERT INTO PlanningEntry (
                companyId,
                projectId,
                taskId,
                userId,
                entryType,
                title,
                notes,
                startAt,
                endAt,
                createdByUserId,
                createdAt,
                updatedAt
            ) VALUES (
                :companyId,
                :projectId,
                :taskId,
                :userId,
                :entryType,
                :title,
                :notes,
                :startAt,
                :endAt,
                :createdByUserId,
                NOW(),
                NOW()
            )
        ');

        $stmt->execute([
            'companyId' => $companyId,
            'projectId' => $projectId !== null && $projectId > 0 ? $projectId : null,
            'taskId' => $taskId !== null && $taskId > 0 ? $taskId : null,
            'userId' => $userId !== null && $userId > 0 ? $userId : null,
            'entryType' => $entryType,
            'title' => $title,
            'notes' => $notes !== '' ? $notes : null,
            'startAt' => $startAt->format('Y-m-d H:i:s'),
            'endAt' => $endAt->format('Y-m-d H:i:s'),
            'createdByUserId' => $createdByUserId,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @return array<int, array{
     *   id:int,
     *   title:string,
     *   entryType:string,
     *   startAt:string,
     *   endAt:string,
     *   userName:?string
     * }>
     */
    public function listByCompanyIdAndProjectId(int $companyId, int $projectId, int $limit = 50): array
    {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            SELECT
                pe.id,
                pe.title,
                pe.entryType,
                pe.startAt,
                pe.endAt,
                COALESCE(u.fullName, u.email) AS userName
            FROM PlanningEntry pe
            LEFT JOIN `User` u
                ON u.id = pe.userId
               AND u.companyId = pe.companyId
            WHERE pe.companyId = :companyId
              AND pe.projectId = :projectId
            ORDER BY pe.startAt DESC
            LIMIT :limit
        ');
        $stmt->bindValue('companyId', $companyId, PDO::PARAM_INT);
        $stmt->bindValue('projectId', $projectId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

