<?php
declare(strict_types=1);

namespace Modules\Projects\Repositories;

use Core\Database\Connection;
use PDO;

final class ProjectTimeEntryRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByCompanyIdAndProjectId(int $companyId, int $projectId): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT
                pte.id,
                pte.userId,
                pte.assignmentDate,
                pte.durationMinutes,
                COALESCE(u.fullName, u.email) AS userLabel
            FROM ProjectTimeEntry pte
            INNER JOIN `User` u
                ON u.id = pte.userId
               AND u.companyId = pte.companyId
            WHERE pte.companyId = :companyId
              AND pte.projectId = :projectId
            ORDER BY pte.assignmentDate ASC, pte.id ASC
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'projectId' => $projectId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function deleteByCompanyIdAndProjectId(int $companyId, int $projectId): void
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            DELETE FROM ProjectTimeEntry
            WHERE companyId = :companyId AND projectId = :projectId
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'projectId' => $projectId,
        ]);
    }

    /**
     * @param array<int, array{userId:int, assignmentDate:string, durationMinutes:int}> $rows
     */
    public function insertMany(int $companyId, int $projectId, array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            INSERT INTO ProjectTimeEntry (companyId, projectId, userId, assignmentDate, durationMinutes, createdAt, updatedAt)
            VALUES (:companyId, :projectId, :userId, :assignmentDate, :durationMinutes, NOW(), NOW())
        ');
        foreach ($rows as $r) {
            $uid = (int) ($r['userId'] ?? 0);
            $min = (int) ($r['durationMinutes'] ?? 0);
            $d = (string) ($r['assignmentDate'] ?? '');
            if ($uid <= 0 || $min <= 0 || $d === '') {
                continue;
            }
            $stmt->execute([
                'companyId' => $companyId,
                'projectId' => $projectId,
                'userId' => $uid,
                'assignmentDate' => $d,
                'durationMinutes' => $min,
            ]);
        }
    }

    public function sumLaborCostAmount(int $companyId, int $projectId): float
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT COALESCE(SUM((pte.durationMinutes / 60.0) * COALESCE(u.coutHoraire, 0)), 0) AS labor
            FROM ProjectTimeEntry pte
            INNER JOIN `User` u
                ON u.id = pte.userId
               AND u.companyId = pte.companyId
            WHERE pte.companyId = :companyId
              AND pte.projectId = :projectId
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'projectId' => $projectId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return round((float) ($row['labor'] ?? 0), 2);
    }
}
