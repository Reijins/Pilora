<?php
declare(strict_types=1);

namespace Modules\Invoices\Repositories;

use Core\Database\Connection;
use PDO;

final class InvoiceRepository
{
    public function listByCompanyId(int $companyId, ?string $status = null, int $limit = 50): array
    {
        $pdo = Connection::pdo();

        $sql = '
            SELECT
                id,
                quoteId,
                invoiceNumber,
                title,
                dueDate,
                status,
                amountTotal,
                amountPaid,
                clientId,
                (COALESCE(amountTotal,0) - COALESCE(amountPaid,0)) AS amountRemaining
            FROM Invoice
            WHERE companyId = :companyId
        ';
        $params = ['companyId' => $companyId];

        if ($status !== null && $status !== '') {
            $sql .= ' AND status = :status ';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY id DESC LIMIT :limit';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('companyId', $params['companyId'], PDO::PARAM_INT);
        if (isset($params['status'])) {
            $stmt->bindValue('status', $params['status'], PDO::PARAM_STR);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listByCompanyIdAndClientId(int $companyId, int $clientId, ?string $status = null, int $limit = 50): array
    {
        $pdo = Connection::pdo();

        $sql = '
            SELECT
                id,
                quoteId,
                invoiceNumber,
                title,
                dueDate,
                status,
                amountTotal,
                amountPaid,
                clientId,
                (COALESCE(amountTotal,0) - COALESCE(amountPaid,0)) AS amountRemaining
            FROM Invoice
            WHERE companyId = :companyId
              AND clientId = :clientId
        ';

        $params = ['companyId' => $companyId, 'clientId' => $clientId];

        if ($status !== null && $status !== '') {
            $sql .= ' AND status = :status ';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY id DESC LIMIT :limit';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('companyId', $params['companyId'], PDO::PARAM_INT);
        $stmt->bindValue('clientId', $params['clientId'], PDO::PARAM_INT);
        if (isset($params['status'])) {
            $stmt->bindValue('status', $params['status'], PDO::PARAM_STR);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listByCompanyIdAndProjectId(int $companyId, int $projectId, ?string $status = null, int $limit = 50): array
    {
        $pdo = Connection::pdo();

        $sql = '
            SELECT
                i.id,
                i.quoteId,
                i.invoiceNumber,
                i.title,
                i.dueDate,
                i.status,
                i.amountTotal,
                i.amountPaid,
                i.clientId,
                (COALESCE(i.amountTotal,0) - COALESCE(i.amountPaid,0)) AS amountRemaining
            FROM Invoice i
            INNER JOIN Quote q
                ON q.id = i.quoteId
               AND q.companyId = i.companyId
            WHERE i.companyId = :companyId
              AND q.projectId = :projectId
        ';

        $params = ['companyId' => $companyId, 'projectId' => $projectId];

        if ($status !== null && $status !== '') {
            $sql .= ' AND i.status = :status ';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY i.id DESC LIMIT :limit';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('companyId', $params['companyId'], PDO::PARAM_INT);
        $stmt->bindValue('projectId', $params['projectId'], PDO::PARAM_INT);
        if (isset($params['status'])) {
            $stmt->bindValue('status', $params['status'], PDO::PARAM_STR);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByCompanyIdAndId(int $companyId, int $invoiceId): ?array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT
                id,
                quoteId,
                clientId,
                invoiceNumber,
                title,
                dueDate,
                status,
                amountTotal,
                amountPaid
            FROM Invoice
            WHERE companyId = :companyId AND id = :invoiceId
            LIMIT 1
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'invoiceId' => $invoiceId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createInvoiceFromQuote(
        int $companyId,
        int $quoteId,
        int $clientId,
        ?string $invoiceNumber,
        string $title,
        string $dueDateYmd,
        string $status,
        float $amountTotal,
        int $createdByUserId,
        ?string $notes
    ): int {
        $pdo = Connection::pdo();
        $pdo->beginTransaction();

        try {
            if ($invoiceNumber === null || $invoiceNumber === '') {
                $invoiceNumber = 'FA-' . date('YmdHis') . '-' . random_int(100, 999);
            }

            $stmt = $pdo->prepare('
                INSERT INTO Invoice (
                    companyId,
                    quoteId,
                    clientId,
                    invoiceNumber,
                    title,
                    dueDate,
                    status,
                    amountTotal,
                    amountPaid,
                    createdByUserId,
                    notes,
                    createdAt,
                    updatedAt
                ) VALUES (
                    :companyId,
                    :quoteId,
                    :clientId,
                    :invoiceNumber,
                    :title,
                    :dueDate,
                    :status,
                    :amountTotal,
                    0,
                    :createdByUserId,
                    :notes,
                    NOW(),
                    NOW()
                )
            ');
            $stmt->execute([
                'companyId' => $companyId,
                'quoteId' => $quoteId,
                'clientId' => $clientId,
                'invoiceNumber' => $invoiceNumber,
                'title' => $title,
                'dueDate' => $dueDateYmd,
                'status' => $status,
                'amountTotal' => (float) round($amountTotal, 2),
                'createdByUserId' => $createdByUserId,
                'notes' => $notes,
            ]);

            $invoiceId = (int) $pdo->lastInsertId();
            $pdo->commit();
            return $invoiceId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function existsByCompanyIdAndQuoteId(int $companyId, int $quoteId): bool
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id
            FROM Invoice
            WHERE companyId = :companyId
              AND quoteId = :quoteId
            LIMIT 1
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'quoteId' => $quoteId,
        ]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function markAsSent(int $companyId, int $invoiceId): void
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE Invoice
            SET status = "envoyee",
                updatedAt = NOW()
            WHERE companyId = :companyId
              AND id = :invoiceId
              AND status IN ("brouillon", "echue")
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'invoiceId' => $invoiceId,
        ]);
    }
}

