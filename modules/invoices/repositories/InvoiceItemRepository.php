<?php
declare(strict_types=1);

namespace Modules\Invoices\Repositories;

use Core\Database\Connection;
use PDO;

final class InvoiceItemRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByCompanyIdAndInvoiceId(int $companyId, int $invoiceId): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT
                ii.id,
                ii.description,
                ii.quantity,
                ii.unitPrice,
                ii.lineTotal,
                ii.vatRate,
                ii.revenueAccount,
                ii.lineVat,
                ii.lineTtc,
                ii.priceLibraryItemId,
                pl.unitLabel AS unitLabel
            FROM InvoiceItem ii
            LEFT JOIN PriceLibraryItem pl
                ON pl.companyId = ii.companyId
               AND pl.id = ii.priceLibraryItemId
            WHERE ii.companyId = :companyId
              AND ii.invoiceId = :invoiceId
            ORDER BY ii.lineSort ASC, ii.id ASC
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'invoiceId' => $invoiceId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countByCompanyIdAndInvoiceId(int $companyId, int $invoiceId): int
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT COUNT(*) AS c
            FROM InvoiceItem
            WHERE companyId = :companyId AND invoiceId = :invoiceId
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'invoiceId' => $invoiceId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['c'] ?? 0);
    }

    public function deleteAllForInvoice(int $companyId, int $invoiceId): void
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            DELETE FROM InvoiceItem
            WHERE companyId = :companyId AND invoiceId = :invoiceId
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'invoiceId' => $invoiceId,
        ]);
    }

    public function insertLine(
        int $companyId,
        int $invoiceId,
        ?int $priceLibraryItemId,
        string $description,
        float $quantity,
        float $unitPrice,
        float $vatRate,
        ?string $revenueAccount,
        float $lineTotal,
        float $lineVat,
        float $lineTtc,
        int $lineSort
    ): void {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            INSERT INTO InvoiceItem (
                companyId, invoiceId, priceLibraryItemId, description, quantity, unitPrice, lineTotal,
                vatRate, revenueAccount, lineVat, lineTtc, lineSort, createdAt, updatedAt
            ) VALUES (
                :companyId, :invoiceId, :priceLibraryItemId, :description, :quantity, :unitPrice, :lineTotal,
                :vatRate, :revenueAccount, :lineVat, :lineTtc, :lineSort, NOW(), NOW()
            )
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'invoiceId' => $invoiceId,
            'priceLibraryItemId' => $priceLibraryItemId,
            'description' => $description,
            'quantity' => round($quantity, 2),
            'unitPrice' => round($unitPrice, 2),
            'lineTotal' => round($lineTotal, 2),
            'vatRate' => round($vatRate, 2),
            'revenueAccount' => $revenueAccount !== null && $revenueAccount !== '' ? $revenueAccount : null,
            'lineVat' => round($lineVat, 2),
            'lineTtc' => round($lineTtc, 2),
            'lineSort' => $lineSort,
        ]);
    }
}
