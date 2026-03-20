<?php
declare(strict_types=1);

namespace Modules\Platform\Repositories;

use Core\Database\Connection;
use PDO;

final class AuditLogRepository
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function insert(
        int $companyId,
        int $actorUserId,
        string $action,
        ?int $targetCompanyId,
        ?array $metadata,
        string $ipAddress,
        ?string $userAgent
    ): void {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            INSERT INTO AuditLog (companyId, actorUserId, action, targetCompanyId, metadata, ipAddress, userAgent)
            VALUES (:companyId, :actorUserId, :action, :targetCompanyId, :metadata, :ipAddress, :userAgent)
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'actorUserId' => $actorUserId,
            'action' => $action,
            'targetCompanyId' => $targetCompanyId,
            'metadata' => $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent !== null && $userAgent !== '' ? substr($userAgent, 0, 255) : null,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRecent(int $limit = 200): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT a.id, a.companyId, a.actorUserId, a.action, a.targetCompanyId, a.metadata,
                   a.ipAddress, a.createdAt,
                   u.email AS actorEmail,
                   c.name AS companyName,
                   tc.name AS targetCompanyName
            FROM AuditLog a
            INNER JOIN `User` u ON u.id = a.actorUserId
            INNER JOIN Company c ON c.id = a.companyId
            LEFT JOIN Company tc ON tc.id = a.targetCompanyId
            ORDER BY a.id DESC
            LIMIT :limit
        ');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
