<?php
declare(strict_types=1);

namespace Modules\Quotes\Repositories;

use Core\Database\Connection;
use PDO;

final class QuoteRepository
{
    private function isUnknownProjectIdColumnError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'unknown column')
            && str_contains($message, 'projectid');
    }

    private function insertQuoteRow(
        PDO $pdo,
        int $companyId,
        int $clientId,
        ?int $projectId,
        string $quoteNumber,
        string $title,
        string $status,
        int $createdByUserId
    ): int {
        try {
            $stmt = $pdo->prepare('
                INSERT INTO Quote (
                    companyId,
                    clientId,
                    projectId,
                    quoteNumber,
                    title,
                    status,
                    createdByUserId
                ) VALUES (
                    :companyId,
                    :clientId,
                    :projectId,
                    :quoteNumber,
                    :title,
                    :status,
                    :createdByUserId
                )
            ');
            $stmt->execute([
                'companyId' => $companyId,
                'clientId' => $clientId,
                'projectId' => $projectId !== null && $projectId > 0 ? $projectId : null,
                'quoteNumber' => $quoteNumber,
                'title' => $title,
                'status' => $status,
                'createdByUserId' => $createdByUserId,
            ]);
        } catch (\Throwable $e) {
            if (!$this->isUnknownProjectIdColumnError($e)) {
                throw $e;
            }

            // Fallback schéma legacy: table Quote sans projectId.
            $stmt = $pdo->prepare('
                INSERT INTO Quote (
                    companyId,
                    clientId,
                    quoteNumber,
                    title,
                    status,
                    createdByUserId
                ) VALUES (
                    :companyId,
                    :clientId,
                    :quoteNumber,
                    :title,
                    :status,
                    :createdByUserId
                )
            ');
            $stmt->execute([
                'companyId' => $companyId,
                'clientId' => $clientId,
                'quoteNumber' => $quoteNumber,
                'title' => $title,
                'status' => $status,
                'createdByUserId' => $createdByUserId,
            ]);
        }

        return (int) $pdo->lastInsertId();
    }

    /**
     * @return array<int, array{
     *   id:int,
     *   quoteNumber:?string,
     *   title:?string,
     *   status:string,
     *   followUpAt:?string
     * }>
     */
    public function listByCompanyId(int $companyId, ?string $status = null, int $limit = 50): array
    {
        $pdo = Connection::pdo();

        $sql = '
            SELECT id, clientId, projectId, quoteNumber, title, status, followUpAt, proofFilePath
            FROM Quote
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

    /**
     * @return array<int, array{
     *   id:int,
     *   quoteNumber:?string,
     *   title:?string,
     *   status:string,
     *   followUpAt:?string
     * }>
     */
    public function listByCompanyIdAndClientId(int $companyId, int $clientId, ?string $status = null, int $limit = 50): array
    {
        $pdo = Connection::pdo();

        $sql = '
            SELECT id, clientId, projectId, quoteNumber, title, status, followUpAt, proofFilePath
            FROM Quote
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
            SELECT id, clientId, projectId, quoteNumber, title, status, followUpAt, proofFilePath
            FROM Quote
            WHERE companyId = :companyId
              AND projectId = :projectId
        ';

        $params = ['companyId' => $companyId, 'projectId' => $projectId];

        if ($status !== null && $status !== '') {
            $sql .= ' AND status = :status ';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY id DESC LIMIT :limit';

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

    public function createQuoteWithFirstItem(
        int $companyId,
        int $clientId,
        ?int $projectId,
        string $title,
        string $status,
        ?string $quoteNumber,
        int $createdByUserId,
        string $itemDescription,
        float $quantity,
        float $unitPrice,
        ?int $estimatedTimeMinutes
    ): int {
        $pdo = Connection::pdo();
        $pdo->beginTransaction();

        try {
            $lineTotal = $quantity * $unitPrice;
            $lineTotal = (float) round($lineTotal, 2);

            if ($quoteNumber === null || $quoteNumber === '') {
                $quoteNumber = 'DV-' . date('YmdHis') . '-' . random_int(100, 999);
            }

            $quoteId = $this->insertQuoteRow(
                pdo: $pdo,
                companyId: $companyId,
                clientId: $clientId,
                projectId: $projectId,
                quoteNumber: $quoteNumber,
                title: $title,
                status: $status,
                createdByUserId: $createdByUserId
            );

            $stmtItem = $pdo->prepare('
                INSERT INTO QuoteItem (
                    companyId,
                    quoteId,
                    priceLibraryItemId,
                    description,
                    quantity,
                    unitPrice,
                    lineTotal,
                    estimatedTimeMinutes
                ) VALUES (
                    :companyId,
                    :quoteId,
                    NULL,
                    :description,
                    :quantity,
                    :unitPrice,
                    :lineTotal,
                    :estimatedTimeMinutes
                )
            ');

            $stmtItem->execute([
                'companyId' => $companyId,
                'quoteId' => $quoteId,
                'description' => $itemDescription,
                'quantity' => $quantity,
                'unitPrice' => $unitPrice,
                'lineTotal' => $lineTotal,
                'estimatedTimeMinutes' => $estimatedTimeMinutes,
            ]);

            $pdo->commit();
            return $quoteId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<int, array{
     *   priceLibraryItemId:?int,
     *   description:string,
     *   quantity:float,
     *   unitPrice:float,
     *   estimatedTimeMinutes:?int
     * }> $items
     */
    public function createQuoteWithItems(
        int $companyId,
        int $clientId,
        ?int $projectId,
        string $title,
        string $status,
        ?string $quoteNumber,
        int $createdByUserId,
        array $items
    ): int {
        $pdo = Connection::pdo();
        $pdo->beginTransaction();

        try {
            if ($quoteNumber === null || $quoteNumber === '') {
                $quoteNumber = 'DV-' . date('YmdHis') . '-' . random_int(100, 999);
            }

            $quoteId = $this->insertQuoteRow(
                pdo: $pdo,
                companyId: $companyId,
                clientId: $clientId,
                projectId: $projectId,
                quoteNumber: $quoteNumber,
                title: $title,
                status: $status,
                createdByUserId: $createdByUserId
            );

            $stmtItem = $pdo->prepare('
                INSERT INTO QuoteItem (
                    companyId,
                    quoteId,
                    priceLibraryItemId,
                    description,
                    quantity,
                    unitPrice,
                    lineTotal,
                    estimatedTimeMinutes
                ) VALUES (
                    :companyId,
                    :quoteId,
                    :priceLibraryItemId,
                    :description,
                    :quantity,
                    :unitPrice,
                    :lineTotal,
                    :estimatedTimeMinutes
                )
            ');

            foreach ($items as $item) {
                $quantity = (float) $item['quantity'];
                $unitPrice = (float) $item['unitPrice'];
                $lineTotal = (float) round($quantity * $unitPrice, 2);

                $stmtItem->execute([
                    'companyId' => $companyId,
                    'quoteId' => $quoteId,
                    'priceLibraryItemId' => $item['priceLibraryItemId'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $quantity,
                    'unitPrice' => $unitPrice,
                    'lineTotal' => $lineTotal,
                    'estimatedTimeMinutes' => $item['estimatedTimeMinutes'] ?? null,
                ]);
            }

            $pdo->commit();
            return $quoteId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function findByCompanyIdAndId(int $companyId, int $quoteId): ?array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id, clientId, projectId, title, quoteNumber, status, acceptedAt, notes, proofFilePath
            FROM Quote
            WHERE companyId = :companyId AND id = :quoteId
            LIMIT 1
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'quoteId' => $quoteId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<int, array{
     *   id:int,
     *   description:string,
     *   quantity:float,
     *   unitPrice:float,
     *   lineTotal:float,
     *   estimatedTimeMinutes:?int
     * }>
     */
    public function listItemsByCompanyIdAndQuoteId(int $companyId, int $quoteId): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id, description, quantity, unitPrice, lineTotal, estimatedTimeMinutes
            FROM QuoteItem
            WHERE companyId = :companyId
              AND quoteId = :quoteId
            ORDER BY id ASC
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'quoteId' => $quoteId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function computeQuoteTotalAmount(int $companyId, int $quoteId): float
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT COALESCE(SUM(quantity * unitPrice), 0) AS total
            FROM QuoteItem
            WHERE companyId = :companyId AND quoteId = :quoteId
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'quoteId' => $quoteId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float) ($row['total'] ?? 0);
    }

    public function markQuoteAsAccepted(int $companyId, int $quoteId, \DateTimeImmutable $now): void
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE Quote
            SET status = "accepte",
                acceptedAt = :acceptedAt
            WHERE companyId = :companyId AND id = :quoteId
        ');
        $stmt->execute([
            'acceptedAt' => $now->format('Y-m-d H:i:s'),
            'companyId' => $companyId,
            'quoteId' => $quoteId,
        ]);
    }

    /**
     * Accepte le devis et enregistre le chemin relatif de la preuve de commande (fichier uploadé).
     */
    public function markQuoteAsAcceptedWithProofPath(
        int $companyId,
        int $quoteId,
        \DateTimeImmutable $now,
        ?string $proofFilePathRelative
    ): void {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE Quote
            SET status = "accepte",
                acceptedAt = :acceptedAt,
                proofFilePath = :proofFilePath
            WHERE companyId = :companyId AND id = :quoteId
        ');
        $stmt->execute([
            'acceptedAt' => $now->format('Y-m-d H:i:s'),
            'proofFilePath' => $proofFilePathRelative,
            'companyId' => $companyId,
            'quoteId' => $quoteId,
        ]);
    }

    public function markQuoteAsSent(int $companyId, int $quoteId, \DateTimeImmutable $now): void
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE Quote
            SET status = "envoye",
                sentAt = :sentAt
            WHERE companyId = :companyId
              AND id = :quoteId
              AND status <> "accepte"
        ');
        $stmt->execute([
            'sentAt' => $now->format('Y-m-d H:i:s'),
            'companyId' => $companyId,
            'quoteId' => $quoteId,
        ]);
    }

    public function setSignatureMetadata(
        int $companyId,
        int $quoteId,
        string $signedFirstName,
        string $signedLastName,
        string $signedEmail,
        \DateTimeImmutable $signedAt
    ): void {
        $pdo = Connection::pdo();
        $existing = $this->findByCompanyIdAndId($companyId, $quoteId);
        $notes = trim((string) ($existing['notes'] ?? ''));
        $notes = preg_replace('/\[SIGNED_[^\]]+\]/', '', $notes) ?? '';
        $notes = trim($notes);
        $sig = '[SIGNED_FIRST_NAME:' . trim($signedFirstName) . ']'
            . '[SIGNED_LAST_NAME:' . trim($signedLastName) . ']'
            . '[SIGNED_EMAIL:' . trim($signedEmail) . ']'
            . '[SIGNED_AT:' . $signedAt->format('Y-m-d H:i:s') . ']';
        $newNotes = trim($sig . ' ' . $notes);
        $stmt = $pdo->prepare('
            UPDATE Quote
            SET notes = :notes,
                updatedAt = NOW()
            WHERE companyId = :companyId AND id = :quoteId
        ');
        $stmt->execute([
            'notes' => $newNotes,
            'companyId' => $companyId,
            'quoteId' => $quoteId,
        ]);
    }
}

