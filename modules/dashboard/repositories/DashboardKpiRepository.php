<?php
declare(strict_types=1);

namespace Modules\Dashboard\Repositories;

use Core\Database\Connection;
use PDO;

final class DashboardKpiRepository
{
    /** Devis envoyés à relancer (pas de date de relance ou date passée). */
    public function countQuotesToFollowUp(int $companyId): int
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT COUNT(*) AS c
            FROM Quote
            WHERE companyId = :companyId
              AND status IN ("envoye", "a_relancer")
              AND (followUpAt IS NULL OR followUpAt < NOW())
        ');
        $stmt->execute(['companyId' => $companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['c'] ?? 0);
    }

    /** Factures avec échéance dépassée et reste à payer. */
    public function countOverdueUnpaidInvoices(int $companyId): int
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT COUNT(*) AS c
            FROM Invoice
            WHERE companyId = :companyId
              AND dueDate < CURDATE()
              AND (COALESCE(amountTotal, 0) - COALESCE(amountPaid, 0)) > 0.01
              AND status NOT IN ("payee", "annulee")
        ');
        $stmt->execute(['companyId' => $companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['c'] ?? 0);
    }

    /** Chantiers en cours dont la fin planifiée est dépassée. */
    public function countLateActiveProjects(int $companyId): int
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT COUNT(*) AS c
            FROM Project
            WHERE companyId = :companyId
              AND status != "completed"
              AND actualEndDate IS NULL
              AND plannedEndDate IS NOT NULL
              AND plannedEndDate < CURDATE()
              AND (notes IS NULL OR (notes NOT LIKE :cancelled AND notes NOT LIKE :refused))
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'cancelled' => '%[STATUS:CANCELLED]%',
            'refused' => '%[STATUS:REFUSED_CLIENT]%',
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Chantiers en cours sans rapport de chantier sur les 14 derniers jours.
     */
    public function countProjectsMissingRecentReports(int $companyId, int $days = 14): int
    {
        $days = max(1, min(365, $days));
        $pdo = Connection::pdo();
        $sql = '
            SELECT COUNT(*) AS c
            FROM Project p
            WHERE p.companyId = :companyId
              AND p.status = "in_progress"
              AND NOT EXISTS (
                  SELECT 1
                  FROM ProjectReport pr
                  WHERE pr.companyId = p.companyId
                    AND pr.projectId = p.id
                    AND pr.createdAt >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)
              )
        ';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['companyId' => $companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['c'] ?? 0);
    }
}
