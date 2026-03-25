<?php
declare(strict_types=1);

namespace Modules\Projects\Repositories;

use Core\Database\Connection;
use Modules\Invoices\Repositories\InvoiceRepository;
use Modules\Quotes\Repositories\QuoteRepository;
use PDO;

final class ProjectRepository
{
    /**
     * @return array<int, array{
     *   id:int,
     *   name:string,
     *   status:string,
     *   plannedStartDate:?string,
     *   plannedEndDate:?string,
     *   clientName:string
     * }>
     */
    public function listByCompanyId(int $companyId, int $limit = 50): array
    {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            SELECT
                p.id,
                p.name,
                p.status,
                p.plannedStartDate,
                p.plannedEndDate,
                c.name AS clientName
            FROM Project p
            INNER JOIN Client c
                ON c.id = p.clientId
               AND c.companyId = p.companyId
            WHERE p.companyId = :companyId
            ORDER BY p.id DESC
            LIMIT :limit
        ');
        $stmt->bindValue('companyId', $companyId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Liste des affaires avec filtre par onglet (GET ?tab=).
     *
     * @param 'all'|'active'|'planned'|'waiting'|'done' $tab
     * @return array<int, array{
     *   id:int,
     *   name:string,
     *   status:string,
     *   plannedStartDate:?string,
     *   plannedEndDate:?string,
     *   notes:?string,
     *   clientName:string
     * }>
     */
    public function listByCompanyIdWithTab(int $companyId, string $tab, int $limit = 300): array
    {
        $allowed = ['all', 'active', 'planned', 'waiting', 'done'];
        if (!in_array($tab, $allowed, true)) {
            $tab = 'all';
        }

        $pdo = Connection::pdo();

        $sql = '
            SELECT
                p.id,
                p.clientId,
                p.name,
                p.status,
                p.plannedStartDate,
                p.plannedEndDate,
                p.notes,
                c.name AS clientName
            FROM Project p
            INNER JOIN Client c
                ON c.id = p.clientId
               AND c.companyId = p.companyId
            WHERE p.companyId = :companyId
        ';

        if ($tab === 'active') {
            $sql .= ' AND p.status IN ("in_progress", "paused")
              AND (p.notes IS NULL OR p.notes NOT LIKE :cancelMarker)
              AND (p.notes IS NULL OR p.notes NOT LIKE :refusedMarker)';
        } elseif ($tab === 'planned') {
            $sql .= ' AND p.status = "planned"
              AND (p.notes IS NULL OR p.notes NOT LIKE :waitingMarker)';
        } elseif ($tab === 'waiting') {
            $sql .= ' AND p.notes LIKE :waitingMarker';
        } elseif ($tab === 'done') {
            $sql .= ' AND (p.status = "completed" OR p.actualEndDate IS NOT NULL)';
        }

        $sql .= ' ORDER BY p.id DESC LIMIT :limit';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('companyId', $companyId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        if ($tab === 'planned' || $tab === 'waiting') {
            $stmt->bindValue('waitingMarker', '%[STATUS:WAITING_PLANNING]%', PDO::PARAM_STR);
        }
        if ($tab === 'active') {
            $stmt->bindValue('cancelMarker', '%[STATUS:CANCELLED]%', PDO::PARAM_STR);
            $stmt->bindValue('refusedMarker', '%[STATUS:REFUSED_CLIENT]%', PDO::PARAM_STR);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByCompanyIdAndId(int $companyId, int $projectId): ?array
    {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            SELECT
                p.id,
                p.clientId,
                p.name,
                p.status,
                p.plannedStartDate,
                p.plannedEndDate,
                p.actualStartDate,
                p.actualEndDate,
                p.montantFactureHt,
                p.coutMateriauxTotal,
                p.rentabiliteStatut,
                p.rentabiliteRenseigneeAt,
                p.beneficeTotal,
                p.margePercent,
                p.siteAddress,
                p.siteCity,
                p.sitePostalCode,
                p.notes,
                c.name AS clientName,
                c.email AS clientEmail
            FROM Project p
            INNER JOIN Client c
                ON c.id = p.clientId
               AND c.companyId = p.companyId
            WHERE p.companyId = :companyId
              AND p.id = :projectId
            LIMIT 1
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'projectId' => $projectId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<int, array{
     *   id:int,
     *   name:string,
     *   status:string,
     *   plannedStartDate:?string,
     *   plannedEndDate:?string,
     *   clientName:string
     * }>
     */
    public function listByCompanyIdAndClientId(int $companyId, int $clientId, int $limit = 50): array
    {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            SELECT
                p.id,
                p.name,
                p.status,
                p.plannedStartDate,
                p.plannedEndDate,
                c.name AS clientName
            FROM Project p
            INNER JOIN Client c
                ON c.id = p.clientId
               AND c.companyId = p.companyId
            WHERE p.companyId = :companyId
              AND p.clientId = :clientId
            ORDER BY p.id DESC
            LIMIT :limit
        ');
        $stmt->bindValue('companyId', $companyId, PDO::PARAM_INT);
        $stmt->bindValue('clientId', $clientId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createProject(
        int $companyId,
        int $clientId,
        string $name,
        string $status,
        ?string $plannedStartDateYmd,
        ?string $plannedEndDateYmd,
        ?string $siteAddress,
        ?string $siteCity,
        ?string $sitePostalCode,
        ?string $notes,
        ?int $createdByUserId
    ): int {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            INSERT INTO Project (
                companyId,
                clientId,
                name,
                status,
                plannedStartDate,
                plannedEndDate,
                siteAddress,
                siteCity,
                sitePostalCode,
                notes,
                createdByUserId,
                createdAt,
                updatedAt
            ) VALUES (
                :companyId,
                :clientId,
                :name,
                :status,
                :plannedStartDate,
                :plannedEndDate,
                :siteAddress,
                :siteCity,
                :sitePostalCode,
                :notes,
                :createdByUserId,
                NOW(),
                NOW()
            )
        ');

        $stmt->execute([
            'companyId' => $companyId,
            'clientId' => $clientId,
            'name' => $name,
            'status' => $status,
            'plannedStartDate' => $plannedStartDateYmd !== null && $plannedStartDateYmd !== '' ? $plannedStartDateYmd : null,
            'plannedEndDate' => $plannedEndDateYmd !== null && $plannedEndDateYmd !== '' ? $plannedEndDateYmd : null,
            'siteAddress' => $siteAddress !== '' ? $siteAddress : null,
            'siteCity' => $siteCity !== '' ? $siteCity : null,
            'sitePostalCode' => $sitePostalCode !== '' ? $sitePostalCode : null,
            'notes' => $notes !== '' ? $notes : null,
            'createdByUserId' => $createdByUserId,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function updateStatusAndReason(
        int $companyId,
        int $projectId,
        string $status,
        ?string $reason
    ): bool {
        $pdo = Connection::pdo();

        $stmtLoad = $pdo->prepare('
            SELECT id, clientId, name, notes
            FROM Project
            WHERE companyId = :companyId
              AND id = :projectId
            LIMIT 1
        ');
        $stmtLoad->execute([
            'companyId' => $companyId,
            'projectId' => $projectId,
        ]);
        $projectRow = $stmtLoad->fetch(PDO::FETCH_ASSOC);
        if (!is_array($projectRow)) {
            return false;
        }

        $reasonInput = $reason !== null ? trim($reason) : '';
        $reasonInput = $reasonInput !== '' ? $reasonInput : null;

        // Compatibilité avec l'enum actuel de Project.status:
        // on persiste cancelled/refused_client via status=paused + marqueur en notes.
        $dbStatus = $status;
        $notesValue = $reasonInput; /* utilisé si jamais d'autres statuts passent par cette méthode */
        if ($status === 'cancelled' || $status === 'refused_client') {
            $dbStatus = 'paused';
            $prefix = $status === 'cancelled' ? '[STATUS:CANCELLED]' : '[STATUS:REFUSED_CLIENT]';
            $line = $prefix . ($reasonInput !== null && $reasonInput !== '' ? ' ' . $reasonInput : '');
            $existingNotes = trim((string) ($projectRow['notes'] ?? ''));
            if ($existingNotes !== '') {
                $notesValue = $line !== '' ? ($line . "\n\n" . $existingNotes) : $existingNotes;
            } else {
                $notesValue = $line;
            }
        }

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('
                UPDATE Project
                SET status = :status,
                    notes = :notes,
                    updatedAt = NOW()
                WHERE companyId = :companyId
                  AND id = :projectId
            ');

            $stmt->execute([
                'status' => $dbStatus,
                'notes' => $notesValue,
                'companyId' => $companyId,
                'projectId' => $projectId,
            ]);

            $updated = $stmt->rowCount() > 0;

            if ($updated && ($status === 'cancelled' || $status === 'refused_client')) {
                $quoteRepo = new QuoteRepository();
                $quoteIds = $quoteRepo->listQuoteIdsLinkedToProject(
                    $companyId,
                    $projectId,
                    (int) ($projectRow['clientId'] ?? 0),
                    (string) ($projectRow['name'] ?? '')
                );
                if ($quoteIds !== []) {
                    if ($status === 'cancelled') {
                        $quoteRepo->annulerQuotesByIds($companyId, $quoteIds);
                    } else {
                        $quoteRepo->refuseQuotesByIds($companyId, $quoteIds);
                    }
                    (new InvoiceRepository())->cancelInvoicesByQuoteIds($companyId, $quoteIds);
                }
            }

            $pdo->commit();

            return $updated;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function markWaitingPlanning(int $companyId, int $projectId): bool
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE Project
            SET status = "paused",
                notes = :notes,
                updatedAt = NOW()
            WHERE companyId = :companyId
              AND id = :projectId
        ');
        $stmt->execute([
            'notes' => '[STATUS:WAITING_PLANNING] En attente de planification',
            'companyId' => $companyId,
            'projectId' => $projectId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function planProject(
        int $companyId,
        int $projectId,
        string $startDateYmd,
        string $endDateYmd,
        ?string $siteAddress,
        ?string $siteCity,
        ?string $sitePostalCode
    ): bool {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE Project
            SET status = "planned",
                plannedStartDate = :plannedStartDate,
                plannedEndDate = :plannedEndDate,
                siteAddress = :siteAddress,
                siteCity = :siteCity,
                sitePostalCode = :sitePostalCode,
                notes = :notes,
                updatedAt = NOW()
            WHERE companyId = :companyId
              AND id = :projectId
        ');
        $stmt->execute([
            'plannedStartDate' => $startDateYmd,
            'plannedEndDate' => $endDateYmd,
            'siteAddress' => $siteAddress !== '' ? $siteAddress : null,
            'siteCity' => $siteCity !== '' ? $siteCity : null,
            'sitePostalCode' => $sitePostalCode !== '' ? $sitePostalCode : null,
            'notes' => '[STATUS:PLANNED] Planifie',
            'companyId' => $companyId,
            'projectId' => $projectId,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * @return array<int, array{
     *   projectId:int,
     *   projectName:string,
     *   projectStatus:string,
     *   quotesCount:int,
     *   quoteAmount:float,
     *   invoicesCount:int,
     *   invoiceAmount:float,
     *   paidAmount:float,
     *   remainingAmount:float
     * }>
     */
    public function listAffairesByCompanyIdAndClientId(int $companyId, int $clientId, int $limit = 200): array
    {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            SELECT
                p.id AS projectId,
                p.name AS projectName,
                p.status AS projectStatus,
                p.notes AS projectNotes,
                (
                    SELECT COUNT(*)
                    FROM Quote q
                    WHERE q.companyId = p.companyId
                      AND (
                          q.projectId = p.id
                          OR (
                              q.projectId IS NULL
                              AND q.clientId = p.clientId
                              AND q.title IN (p.name, CONCAT(\'Devis - \', p.name))
                          )
                      )
                ) AS quotesCount,
                (
                    SELECT COALESCE(SUM(qi.lineTotal), 0)
                    FROM Quote q
                    INNER JOIN QuoteItem qi
                        ON qi.companyId = q.companyId
                       AND qi.quoteId = q.id
                    WHERE q.companyId = p.companyId
                      AND q.status = \'accepte\'
                      AND (
                          q.projectId = p.id
                          OR (
                              q.projectId IS NULL
                              AND q.clientId = p.clientId
                              AND q.title IN (p.name, CONCAT(\'Devis - \', p.name))
                          )
                      )
                ) AS quoteAmount,
                (
                    SELECT COUNT(*)
                    FROM Invoice i
                    INNER JOIN Quote q
                        ON q.companyId = i.companyId
                       AND q.id = i.quoteId
                    WHERE i.companyId = p.companyId
                      AND i.status <> \'annulee\'
                      AND (
                          q.projectId = p.id
                          OR (
                              q.projectId IS NULL
                              AND q.clientId = p.clientId
                              AND q.title IN (p.name, CONCAT(\'Devis - \', p.name))
                          )
                      )
                ) AS invoicesCount,
                (
                    SELECT COALESCE(SUM(i.amountTotal), 0)
                    FROM Invoice i
                    INNER JOIN Quote q
                        ON q.companyId = i.companyId
                       AND q.id = i.quoteId
                    WHERE i.companyId = p.companyId
                      AND i.status <> \'annulee\'
                      AND (
                          q.projectId = p.id
                          OR (
                              q.projectId IS NULL
                              AND q.clientId = p.clientId
                              AND q.title IN (p.name, CONCAT(\'Devis - \', p.name))
                          )
                      )
                ) AS invoiceAmount,
                (
                    SELECT COALESCE(SUM(i.amountPaid), 0)
                    FROM Invoice i
                    INNER JOIN Quote q
                        ON q.companyId = i.companyId
                       AND q.id = i.quoteId
                    WHERE i.companyId = p.companyId
                      AND i.status <> \'annulee\'
                      AND (
                          q.projectId = p.id
                          OR (
                              q.projectId IS NULL
                              AND q.clientId = p.clientId
                              AND q.title IN (p.name, CONCAT(\'Devis - \', p.name))
                          )
                      )
                ) AS paidAmount,
                (
                    SELECT COALESCE(SUM(i.amountTotal - i.amountPaid), 0)
                    FROM Invoice i
                    INNER JOIN Quote q
                        ON q.companyId = i.companyId
                       AND q.id = i.quoteId
                    WHERE i.companyId = p.companyId
                      AND i.status <> \'annulee\'
                      AND (
                          q.projectId = p.id
                          OR (
                              q.projectId IS NULL
                              AND q.clientId = p.clientId
                              AND q.title IN (p.name, CONCAT(\'Devis - \', p.name))
                          )
                      )
                ) AS remainingAmount
            FROM Project p
            WHERE p.companyId = :companyId
              AND p.clientId = :clientId
            ORDER BY p.id DESC
            LIMIT :limit
        ');
        $stmt->bindValue('companyId', $companyId, PDO::PARAM_INT);
        $stmt->bindValue('clientId', $clientId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Affaires planifiées dont la période chevauche l’intervalle (vue planning hebdo).
     *
     * @return array<int, array{
     *   id:int,
     *   name:string,
     *   status:string,
     *   plannedStartDate:?string,
     *   plannedEndDate:?string,
     *   siteAddress:?string,
     *   siteCity:?string,
     *   sitePostalCode:?string,
     *   clientName:string
     * }>
     */
    public function listScheduledForRange(int $companyId, string $rangeStartYmd, string $rangeEndYmd): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT
                p.id,
                p.name,
                p.status,
                p.plannedStartDate,
                p.plannedEndDate,
                p.siteAddress,
                p.siteCity,
                p.sitePostalCode,
                c.name AS clientName
            FROM Project p
            INNER JOIN Client c
                ON c.id = p.clientId
               AND c.companyId = p.companyId
            WHERE p.companyId = :companyId
              AND p.status = \'planned\'
              AND p.plannedStartDate IS NOT NULL
              AND p.plannedEndDate IS NOT NULL
              AND LOCATE(\'[STATUS:PLANNED]\', COALESCE(p.notes, \'\')) > 0
              AND p.plannedStartDate <= :rangeEnd
              AND p.plannedEndDate >= :rangeStart
            ORDER BY p.plannedStartDate ASC, p.id ASC
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'rangeStart' => $rangeStartYmd,
            'rangeEnd' => $rangeEndYmd,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function completeProject(int $companyId, int $projectId, string $actualEndDateYmd): bool
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE Project
            SET status = "completed",
                actualEndDate = :actualEndDate,
                updatedAt = NOW()
            WHERE companyId = :companyId
              AND id = :projectId
              AND (notes IS NULL OR (notes NOT LIKE :cancelled AND notes NOT LIKE :refused))
        ');
        $stmt->execute([
            'actualEndDate' => $actualEndDateYmd,
            'companyId' => $companyId,
            'projectId' => $projectId,
            'cancelled' => '%[STATUS:CANCELLED]%',
            'refused' => '%[STATUS:REFUSED_CLIENT]%',
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param array{
     *   montantFactureHt:?float,
     *   coutMateriauxTotal:float,
     *   beneficeTotal:float,
     *   margePercent:?float
     * } $figures
     */
    public function saveRentabilityFigures(int $companyId, int $projectId, array $figures): void
    {
        $pdo = Connection::pdo();
        $marge = $figures['margePercent'];
        $stmt = $pdo->prepare('
            UPDATE Project
            SET montantFactureHt = :montantHt,
                coutMateriauxTotal = :coutMat,
                beneficeTotal = :benef,
                margePercent = :marge,
                rentabiliteStatut = "renseignee",
                rentabiliteRenseigneeAt = COALESCE(rentabiliteRenseigneeAt, NOW()),
                updatedAt = NOW()
            WHERE companyId = :companyId
              AND id = :projectId
        ');
        $stmt->execute([
            'montantHt' => $figures['montantFactureHt'],
            'coutMat' => $figures['coutMateriauxTotal'],
            'benef' => $figures['beneficeTotal'],
            'marge' => $marge,
            'companyId' => $companyId,
            'projectId' => $projectId,
        ]);
    }

    public function countRentabilityToFill(int $companyId): int
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT COUNT(*) AS c
            FROM Project p
            WHERE p.companyId = :companyId
              AND (p.status = "completed" OR p.actualEndDate IS NOT NULL)
              AND p.rentabiliteStatut = "a_renseigner"
              AND (p.notes IS NULL OR (p.notes NOT LIKE :cancelled AND p.notes NOT LIKE :refused))
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
     * @return array<int, array<string, mixed>>
     */
    public function listRentabilityToFill(int $companyId, int $limit = 200): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT
                p.id,
                p.name,
                p.actualEndDate,
                p.montantFactureHt,
                p.coutMateriauxTotal,
                c.name AS clientName
            FROM Project p
            INNER JOIN Client c
                ON c.id = p.clientId
               AND c.companyId = p.companyId
            WHERE p.companyId = :companyId
              AND (p.status = "completed" OR p.actualEndDate IS NOT NULL)
              AND p.rentabiliteStatut = "a_renseigner"
              AND (p.notes IS NULL OR (p.notes NOT LIKE :cancelled AND p.notes NOT LIKE :refused))
            ORDER BY (p.actualEndDate IS NULL) ASC, p.actualEndDate DESC, p.id DESC
            LIMIT :limit
        ');
        $stmt->bindValue('companyId', $companyId, PDO::PARAM_INT);
        $stmt->bindValue('cancelled', '%[STATUS:CANCELLED]%', PDO::PARAM_STR);
        $stmt->bindValue('refused', '%[STATUS:REFUSED_CLIENT]%', PDO::PARAM_STR);
        $stmt->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRentabilityHistory(int $companyId, int $limit = 200): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT
                p.id,
                p.name,
                p.actualEndDate,
                p.montantFactureHt,
                p.beneficeTotal,
                p.margePercent,
                p.rentabiliteRenseigneeAt,
                c.name AS clientName
            FROM Project p
            INNER JOIN Client c
                ON c.id = p.clientId
               AND c.companyId = p.companyId
            WHERE p.companyId = :companyId
              AND (p.status = "completed" OR p.actualEndDate IS NOT NULL)
              AND p.rentabiliteStatut = "renseignee"
              AND (p.notes IS NULL OR (p.notes NOT LIKE :cancelled AND p.notes NOT LIKE :refused))
            ORDER BY p.rentabiliteRenseigneeAt DESC, p.id DESC
            LIMIT :limit
        ');
        $stmt->bindValue('companyId', $companyId, PDO::PARAM_INT);
        $stmt->bindValue('cancelled', '%[STATUS:CANCELLED]%', PDO::PARAM_STR);
        $stmt->bindValue('refused', '%[STATUS:REFUSED_CLIENT]%', PDO::PARAM_STR);
        $stmt->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * CA = somme montantFactureHt ; bénéfice = somme beneficeTotal (rentabilité renseignée).
     *
     * @return array{ca: float, benefice: float}
     */
    public function aggregateRentabilityKpi(int $companyId, string $periodStartYmd, string $periodEndYmd): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT
                COALESCE(SUM(CASE WHEN montantFactureHt IS NOT NULL THEN montantFactureHt ELSE 0 END), 0) AS ca,
                COALESCE(SUM(
                    CASE
                        WHEN rentabiliteStatut = "renseignee" AND beneficeTotal IS NOT NULL THEN beneficeTotal
                        ELSE 0
                    END
                ), 0) AS benefice
            FROM Project p
            WHERE p.companyId = :companyId
              AND p.actualEndDate IS NOT NULL
              AND p.actualEndDate >= :d1
              AND p.actualEndDate <= :d2
              AND (p.notes IS NULL OR (p.notes NOT LIKE :cancelled AND p.notes NOT LIKE :refused))
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'd1' => $periodStartYmd,
            'd2' => $periodEndYmd,
            'cancelled' => '%[STATUS:CANCELLED]%',
            'refused' => '%[STATUS:REFUSED_CLIENT]%',
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'ca' => round((float) ($row['ca'] ?? 0), 2),
            'benefice' => round((float) ($row['benefice'] ?? 0), 2),
        ];
    }
}

