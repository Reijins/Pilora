<?php
declare(strict_types=1);

namespace Modules\Auth\Repositories;

use Core\Database\Connection;
use PDO;

final class UserSessionRepository
{
    public function revokeActiveSessionsByUserIp(int $userId, int $companyId, string $ipAddress): void
    {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            UPDATE UserSession
            SET isActive = 0,
                revokedAt = NOW()
            WHERE userId = :userId
              AND companyId = :companyId
              AND ipAddress = :ipAddress
              AND isActive = 1
              AND revokedAt IS NULL
        ');
        $stmt->execute([
            'userId' => $userId,
            'companyId' => $companyId,
            'ipAddress' => $ipAddress,
        ]);
    }

    public function createSession(array $data): void
    {
        // $data keys:
        // userId, companyId, ipAddress, userAgent, sessionId, sessionToken, expiresAt, lastActivityAt
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            INSERT INTO UserSession (
                userId,
                companyId,
                ipAddress,
                userAgent,
                sessionId,
                sessionToken,
                isActive,
                lastActivityAt,
                expiresAt,
                createdAt,
                revokedAt
            ) VALUES (
                :userId,
                :companyId,
                :ipAddress,
                :userAgent,
                :sessionId,
                :sessionToken,
                1,
                :lastActivityAt,
                :expiresAt,
                NOW(),
                NULL
            )
        ');

        $stmt->execute([
            'userId' => $data['userId'],
            'companyId' => $data['companyId'],
            'ipAddress' => $data['ipAddress'],
            'userAgent' => $data['userAgent'],
            'sessionId' => $data['sessionId'],
            'sessionToken' => $data['sessionToken'],
            'lastActivityAt' => $data['lastActivityAt'],
            'expiresAt' => $data['expiresAt'],
        ]);
    }

    public function findValidSessionIdentity(
        int $userId,
        int $companyId,
        string $ipAddress,
        string $sessionId,
        string $sessionToken,
        \DateTimeImmutable $now,
        \DateTimeImmutable $minLastActivityAt,
    ): ?array {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            SELECT
                id,
                userId,
                companyId,
                ipAddress,
                userAgent,
                sessionId,
                sessionToken,
                lastActivityAt,
                expiresAt,
                createdAt,
                revokedAt
            FROM UserSession
            WHERE userId = :userId
              AND companyId = :companyId
              AND ipAddress = :ipAddress
              AND sessionId = :sessionId
              AND sessionToken = :sessionToken
              AND isActive = 1
              AND revokedAt IS NULL
              AND lastActivityAt >= :minLastActivityAt
              AND (expiresAt IS NULL OR expiresAt >= :now)
            LIMIT 1
        ');

        $stmt->execute([
            'userId' => $userId,
            'companyId' => $companyId,
            'ipAddress' => $ipAddress,
            'sessionId' => $sessionId,
            'sessionToken' => $sessionToken,
            'now' => $now->format('Y-m-d H:i:s'),
            'minLastActivityAt' => $minLastActivityAt->format('Y-m-d H:i:s'),
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function touchSession(int $userId, int $companyId, string $sessionId, string $sessionToken, string $ipAddress): void
    {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            UPDATE UserSession
            SET lastActivityAt = NOW()
            WHERE userId = :userId
              AND companyId = :companyId
              AND ipAddress = :ipAddress
              AND sessionId = :sessionId
              AND sessionToken = :sessionToken
              AND isActive = 1
              AND revokedAt IS NULL
        ');
        $stmt->execute([
            'userId' => $userId,
            'companyId' => $companyId,
            'ipAddress' => $ipAddress,
            'sessionId' => $sessionId,
            'sessionToken' => $sessionToken,
        ]);
    }

    public function revokeCurrentSession(int $userId, int $companyId, string $sessionId, string $sessionToken, string $ipAddress): void
    {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            UPDATE UserSession
            SET isActive = 0,
                revokedAt = NOW()
            WHERE userId = :userId
              AND companyId = :companyId
              AND ipAddress = :ipAddress
              AND sessionId = :sessionId
              AND sessionToken = :sessionToken
              AND isActive = 1
              AND revokedAt IS NULL
        ');
        $stmt->execute([
            'userId' => $userId,
            'companyId' => $companyId,
            'ipAddress' => $ipAddress,
            'sessionId' => $sessionId,
            'sessionToken' => $sessionToken,
        ]);
    }
}

