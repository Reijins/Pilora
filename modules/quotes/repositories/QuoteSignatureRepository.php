<?php
declare(strict_types=1);

namespace Modules\Quotes\Repositories;

use Core\Database\Connection;
use PDO;

final class QuoteSignatureRepository
{
    private function ensureTable(): void
    {
        $pdo = Connection::pdo();
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS QuoteSignatureOtp (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                companyId BIGINT UNSIGNED NOT NULL,
                quoteId BIGINT UNSIGNED NOT NULL,
                contactEmail VARCHAR(255) NOT NULL,
                otpHash VARCHAR(255) NOT NULL,
                expiresAt DATETIME NOT NULL,
                consumedAt DATETIME NULL,
                createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_qs_company_quote (companyId, quoteId),
                KEY idx_qs_email (contactEmail)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function createCode(int $companyId, int $quoteId, string $contactEmail, string $otp, \DateTimeImmutable $expiresAt): void
    {
        $this->ensureTable();
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            INSERT INTO QuoteSignatureOtp (companyId, quoteId, contactEmail, otpHash, expiresAt, consumedAt)
            VALUES (:companyId, :quoteId, :contactEmail, :otpHash, :expiresAt, NULL)
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'quoteId' => $quoteId,
            'contactEmail' => $contactEmail,
            'otpHash' => password_hash($otp, PASSWORD_DEFAULT),
            'expiresAt' => $expiresAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function verifyAndConsumeCode(int $companyId, int $quoteId, string $contactEmail, string $otp, \DateTimeImmutable $now): bool
    {
        $this->ensureTable();
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id, otpHash
            FROM QuoteSignatureOtp
            WHERE companyId = :companyId
              AND quoteId = :quoteId
              AND contactEmail = :contactEmail
              AND consumedAt IS NULL
              AND expiresAt >= :nowAt
            ORDER BY id DESC
            LIMIT 1
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'quoteId' => $quoteId,
            'contactEmail' => $contactEmail,
            'nowAt' => $now->format('Y-m-d H:i:s'),
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return false;
        }
        if (!password_verify($otp, (string) ($row['otpHash'] ?? ''))) {
            return false;
        }
        $upd = $pdo->prepare('UPDATE QuoteSignatureOtp SET consumedAt = :consumedAt WHERE id = :id');
        $upd->execute([
            'consumedAt' => $now->format('Y-m-d H:i:s'),
            'id' => (int) ($row['id'] ?? 0),
        ]);
        return true;
    }
}

