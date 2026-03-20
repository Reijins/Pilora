<?php
declare(strict_types=1);

namespace Modules\Hr\Repositories;

use Core\Database\Connection;
use PDO;

final class LeaveRequestRepository
{
    public function create(
        int $companyId,
        int $userId,
        string $type,
        string $startDateYmd,
        string $endDateYmd,
        ?string $reason
    ): int {
        $pdo = Connection::pdo();

        $allowedTypes = ['conges', 'absence'];
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'conges';
        }

        $stmt = $pdo->prepare('
            INSERT INTO LeaveRequest (
                companyId,
                userId,
                type,
                startDate,
                endDate,
                reason,
                status,
                createdAt,
                updatedAt
            ) VALUES (
                :companyId,
                :userId,
                :type,
                :startDate,
                :endDate,
                :reason,
                "pending",
                NOW(),
                NOW()
            )
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'userId' => $userId,
            'type' => $type,
            'startDate' => $startDateYmd,
            'endDate' => $endDateYmd,
            'reason' => $reason !== '' ? $reason : null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function listByCompany(int $companyId, ?int $onlyUserId = null, int $limit = 200): array
    {
        $pdo = Connection::pdo();

        $sql = '
            SELECT
                lr.id,
                lr.type,
                lr.startDate,
                lr.endDate,
                lr.reason,
                lr.status,
                lr.createdAt,
                COALESCE(u.fullName, u.email) AS userName,
                COALESCE(au.fullName, au.email) AS approverName,
                lr.approvedAt,
                lr.rejectionReason
            FROM LeaveRequest lr
            INNER JOIN `User` u
                ON u.id = lr.userId
               AND u.companyId = lr.companyId
            LEFT JOIN `User` au
                ON au.id = lr.approvedByUserId
               AND au.companyId = lr.companyId
            WHERE lr.companyId = :companyId
        ';
        $params = ['companyId' => $companyId];

        if ($onlyUserId !== null && $onlyUserId > 0) {
            $sql .= ' AND lr.userId = :onlyUserId ';
            $params['onlyUserId'] = $onlyUserId;
        }

        $sql .= ' ORDER BY lr.id DESC LIMIT :limit';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('companyId', $params['companyId'], PDO::PARAM_INT);
        if (isset($params['onlyUserId'])) {
            $stmt->bindValue('onlyUserId', $params['onlyUserId'], PDO::PARAM_INT);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function setStatus(
        int $companyId,
        int $leaveRequestId,
        string $status,
        int $approvedByUserId,
        ?string $rejectionReason
    ): void {
        $pdo = Connection::pdo();

        $allowedStatuses = ['approved', 'rejected', 'cancelled'];
        if (!in_array($status, $allowedStatuses, true)) {
            throw new \InvalidArgumentException('Statut invalide.');
        }

        $stmt = $pdo->prepare('
            UPDATE LeaveRequest
            SET
                status = :status,
                approvedByUserId = :approvedByUserId,
                approvedAt = NOW(),
                rejectionReason = :rejectionReason,
                updatedAt = NOW()
            WHERE companyId = :companyId
              AND id = :leaveRequestId
              AND status = "pending"
        ');
        $stmt->execute([
            'status' => $status,
            'approvedByUserId' => $approvedByUserId,
            'rejectionReason' => $status === 'rejected' ? ($rejectionReason !== '' ? $rejectionReason : null) : null,
            'companyId' => $companyId,
            'leaveRequestId' => $leaveRequestId,
        ]);
    }
}

