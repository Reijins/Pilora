<?php
declare(strict_types=1);

namespace Modules\Payments\Repositories;

use Core\Database\Connection;
use PDO;

final class PaymentRepository
{
    public function createPaymentSucceeded(
        int $companyId,
        int $invoiceId,
        float $amount,
        string $provider,
        ?string $reference,
        \DateTimeImmutable $paidAt,
        ?string $metadata = null
    ): int {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            INSERT INTO Payment (
                companyId,
                invoiceId,
                provider,
                reference,
                amount,
                currency,
                status,
                paidAt,
                metadata,
                createdAt,
                updatedAt
            ) VALUES (
                :companyId,
                :invoiceId,
                :provider,
                :reference,
                :amount,
                "EUR",
                "succeeded",
                :paidAt,
                :metadata,
                NOW(),
                NOW()
            )
        ');

        $stmt->execute([
            'companyId' => $companyId,
            'invoiceId' => $invoiceId,
            'provider' => $provider,
            'reference' => $reference,
            'amount' => (float) round($amount, 2),
            'paidAt' => $paidAt->format('Y-m-d H:i:s'),
            'metadata' => $metadata,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function sumSucceededAmountByCompanyAndClientId(int $companyId, int $clientId): float
    {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            SELECT COALESCE(SUM(p.amount), 0) AS total
            FROM Payment p
            INNER JOIN Invoice i
                ON i.id = p.invoiceId
               AND i.companyId = p.companyId
            WHERE p.companyId = :companyId
              AND i.clientId = :clientId
              AND p.status = "succeeded"
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'clientId' => $clientId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float) ($row['total'] ?? 0);
    }
}

