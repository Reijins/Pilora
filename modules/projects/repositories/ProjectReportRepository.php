<?php
declare(strict_types=1);

namespace Modules\Projects\Repositories;

use Core\Database\Connection;
use PDO;

final class ProjectReportRepository
{
    /**
     * @return array<int, array{ id:int, title:string, content:?string, authorName:?string, createdAt:string }>
     */
    public function listByCompanyIdAndProjectId(int $companyId, int $projectId, int $limit = 200): array
    {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            SELECT
                pr.id,
                pr.title,
                pr.content,
                COALESCE(u.fullName, u.email) AS authorName,
                pr.createdAt
            FROM ProjectReport pr
            LEFT JOIN `User` u
                ON u.id = pr.authorUserId
               AND u.companyId = pr.companyId
            WHERE pr.companyId = :companyId
              AND pr.projectId = :projectId
            ORDER BY pr.id DESC
            LIMIT :limit
        ');

        $stmt->bindValue('companyId', $companyId, PDO::PARAM_INT);
        $stmt->bindValue('projectId', $projectId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(
        int $companyId,
        int $projectId,
        ?int $authorUserId,
        string $title,
        ?string $content
    ): int {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            INSERT INTO ProjectReport (
                companyId,
                projectId,
                authorUserId,
                title,
                content,
                createdAt,
                updatedAt
            ) VALUES (
                :companyId,
                :projectId,
                :authorUserId,
                :title,
                :content,
                NOW(),
                NOW()
            )
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'projectId' => $projectId,
            'authorUserId' => $authorUserId,
            'title' => $title,
            'content' => $content !== '' ? $content : null,
        ]);

        return (int) $pdo->lastInsertId();
    }
}

