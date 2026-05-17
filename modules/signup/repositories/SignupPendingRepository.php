<?php
declare(strict_types=1);

namespace Modules\Signup\Repositories;

use Core\Database\Connection;
use PDO;

final class SignupPendingRepository
{
    /**
     * @param array<string, mixed> $payload
     */
    public function create(string $token, array $payload): int
    {
        $pdo = Connection::pdo();
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new \RuntimeException('Payload inscription invalide.');
        }
        $stmt = $pdo->prepare('
            INSERT INTO SignupPending (token, status, payload)
            VALUES (:token, "pending", :payload)
        ');
        $stmt->execute(['token' => $token, 'payload' => $json]);

        return (int) $pdo->lastInsertId();
    }

    public function findByToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id, token, status, stripeCheckoutSessionId, payload, companyId, userId, createdAt
            FROM SignupPending
            WHERE token = :token
            LIMIT 1
        ');
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByStripeSessionId(string $sessionId): ?array
    {
        if ($sessionId === '') {
            return null;
        }
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id, token, status, stripeCheckoutSessionId, payload, companyId, userId, createdAt
            FROM SignupPending
            WHERE stripeCheckoutSessionId = :sid
            LIMIT 1
        ');
        $stmt->execute(['sid' => $sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function saveStripeSessionId(string $token, string $sessionId): void
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE SignupPending
            SET stripeCheckoutSessionId = :sid, updatedAt = NOW()
            WHERE token = :token
        ');
        $stmt->execute(['sid' => $sessionId, 'token' => $token]);
    }

    public function markPaid(string $token): void
    {
        $pdo = Connection::pdo();
        $pdo->prepare('
            UPDATE SignupPending SET status = "paid", updatedAt = NOW()
            WHERE token = :token AND status = "pending"
        ')->execute(['token' => $token]);
    }

    public function markCancelled(string $token): void
    {
        $pdo = Connection::pdo();
        $pdo->prepare('
            UPDATE SignupPending SET status = "cancelled", updatedAt = NOW()
            WHERE token = :token AND status IN ("pending","paid")
        ')->execute(['token' => $token]);
    }

    public function markProvisioned(string $token, int $companyId, int $userId): void
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE SignupPending
            SET status = "provisioned",
                companyId = :cid,
                userId = :uid,
                updatedAt = NOW()
            WHERE token = :token
        ');
        $stmt->execute([
            'token' => $token,
            'cid' => $companyId,
            'uid' => $userId,
        ]);
    }

    public function markFailed(string $token): void
    {
        $pdo = Connection::pdo();
        $pdo->prepare('
            UPDATE SignupPending SET status = "failed", updatedAt = NOW() WHERE token = :token
        ')->execute(['token' => $token]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $payload = [];
        $raw = (string) ($row['payload'] ?? '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
        $row['payload'] = $payload;

        return $row;
    }
}
