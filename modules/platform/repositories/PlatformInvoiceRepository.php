<?php
declare(strict_types=1);

namespace Modules\Platform\Repositories;

use Core\Database\Connection;
use PDO;

final class PlatformInvoiceRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listCompanyInvoiceTracking(int $limit = 500): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT
                c.id AS companyId,
                c.name AS companyName,
                c.billingEmail,
                COUNT(i.id) AS invoicesCount,
                SUM(CASE
                    WHEN i.status NOT IN ("payee","annulee")
                    THEN GREATEST(i.amountTotal - i.amountPaid, 0)
                    ELSE 0
                END) AS unpaidAmount,
                SUM(CASE
                    WHEN i.status NOT IN ("payee","annulee")
                     AND i.dueDate < CURDATE()
                     AND GREATEST(i.amountTotal - i.amountPaid, 0) > 0
                    THEN 1 ELSE 0
                END) AS overdueCount,
                SUM(CASE
                    WHEN i.status NOT IN ("payee","annulee")
                     AND i.dueDate < CURDATE()
                    THEN GREATEST(i.amountTotal - i.amountPaid, 0)
                    ELSE 0
                END) AS overdueAmount,
                MIN(CASE
                    WHEN i.status NOT IN ("payee","annulee")
                     AND i.dueDate < CURDATE()
                     AND GREATEST(i.amountTotal - i.amountPaid, 0) > 0
                    THEN i.dueDate
                    ELSE NULL
                END) AS oldestOverdueDate
            FROM Company c
            LEFT JOIN Invoice i ON i.companyId = c.id
            WHERE c.companyKind = "tenant"
            GROUP BY c.id, c.name, c.billingEmail
            ORDER BY overdueCount DESC, overdueAmount DESC, c.id ASC
            LIMIT :limit
        ');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

