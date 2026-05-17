<?php
declare(strict_types=1);

namespace Modules\Marketing\Repositories;

use Core\Database\Connection;
use PDO;

final class DemoRequestRepository
{
    public function create(
        string $name,
        string $email,
        ?string $companyName,
        ?string $message,
        string $ipAddress,
        ?string $userAgent,
    ): int {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            INSERT INTO DemoRequest (name, email, companyName, message, ipAddress, userAgent)
            VALUES (:name, :email, :companyName, :message, :ipAddress, :userAgent)
        ');
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'companyName' => $companyName !== null && $companyName !== '' ? $companyName : null,
            'message' => $message !== null && $message !== '' ? $message : null,
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent !== null && $userAgent !== '' ? substr($userAgent, 0, 255) : null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function markNotifySent(int $id): void
    {
        $pdo = Connection::pdo();
        $pdo->prepare('UPDATE DemoRequest SET notifySentAt = NOW() WHERE id = :id')->execute(['id' => $id]);
    }

    public function markAckSent(int $id): void
    {
        $pdo = Connection::pdo();
        $pdo->prepare('UPDATE DemoRequest SET ackSentAt = NOW() WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRecent(int $limit = 200): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id, name, email, companyName, message, status, notes,
                   ipAddress, notifySentAt, ackSentAt, createdAt
            FROM DemoRequest
            ORDER BY id DESC
            LIMIT :limit
        ');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function updateStatus(int $id, string $status, ?string $notes): bool
    {
        if (!in_array($status, ['new', 'contacted', 'closed', 'spam'], true)) {
            return false;
        }
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE DemoRequest
            SET status = :status,
                notes = :notes,
                updatedAt = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            'id' => $id,
            'status' => $status,
            'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
        ]);

        return $stmt->rowCount() > 0;
    }
}
