<?php
declare(strict_types=1);

namespace Modules\Projects\Repositories;

use Core\Database\Connection;
use PDO;

final class ProjectPhotoRepository
{
    /**
     * @return array<int, array{id:int, filePath:string, caption:?string, takenAt:?string, uploaderName:?string}>
     */
    public function listByCompanyIdAndProjectId(int $companyId, int $projectId, int $limit = 200): array
    {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            SELECT
                pp.id,
                pp.filePath,
                pp.caption,
                pp.takenAt,
                COALESCE(u.fullName, u.email) AS uploaderName
            FROM ProjectPhoto pp
            LEFT JOIN `User` u
                ON u.id = pp.uploaderUserId
               AND u.companyId = pp.companyId
            WHERE pp.companyId = :companyId
              AND pp.projectId = :projectId
            ORDER BY pp.id DESC
            LIMIT :limit
        ');
        $stmt->bindValue('companyId', $companyId, PDO::PARAM_INT);
        $stmt->bindValue('projectId', $projectId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

