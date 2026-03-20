<?php
declare(strict_types=1);

namespace Modules\Quotes\Repositories;

use Core\Database\Connection;
use PDO;

final class QuoteShareRepository
{
    private function ensureTable(PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS QuoteShareToken (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                companyId BIGINT UNSIGNED NOT NULL,
                quoteId BIGINT UNSIGNED NOT NULL,
                token VARCHAR(128) NOT NULL,
                expiresAt DATETIME NULL,
                createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_quote_share_token (token),
                KEY idx_quote_share_quote (companyId, quoteId),
                CONSTRAINT fk_quoteShare_company FOREIGN KEY (companyId) REFERENCES Company (id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_quoteShare_quote FOREIGN KEY (quoteId) REFERENCES Quote (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function createOrRefresh(int $companyId, int $quoteId, ?\DateTimeImmutable $expiresAt = null): string
    {
        $pdo = Connection::pdo();
        $this->ensureTable($pdo);

        $token = bin2hex(random_bytes(24));
        $stmt = $pdo->prepare('
            INSERT INTO QuoteShareToken (companyId, quoteId, token, expiresAt)
            VALUES (:companyId, :quoteId, :token, :expiresAt)
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'quoteId' => $quoteId,
            'token' => $token,
            'expiresAt' => $expiresAt?->format('Y-m-d H:i:s'),
        ]);
        return $token;
    }

    public function findByToken(string $token): ?array
    {
        $pdo = Connection::pdo();
        $this->ensureTable($pdo);
        $stmt = $pdo->prepare('
            SELECT companyId, quoteId, expiresAt
            FROM QuoteShareToken
            WHERE token = :token
            LIMIT 1
        ');
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if (!empty($row['expiresAt'])) {
            $now = new \DateTimeImmutable('now');
            $exp = new \DateTimeImmutable((string) $row['expiresAt']);
            if ($exp < $now) {
                return null;
            }
        }
        return $row;
    }
}

