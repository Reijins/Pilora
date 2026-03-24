<?php
declare(strict_types=1);

namespace Modules\Invoices\Repositories;

use Core\Database\Connection;
use Modules\Invoices\Services\InvoiceAmountsService;
use PDO;

final class InvoiceRepository
{
    /**
     * PDO / MySQL peuvent renvoyer des clés en casse différente ; on expose les clés camelCase attendues par l’app.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function normalizeInvoiceSelectRow(array $row): array
    {
        $canonical = [
            'id', 'companyId', 'quoteId', 'projectId', 'clientId', 'invoiceNumber', 'title', 'dueDate',
            'status', 'amountTotal', 'amountPaid', 'paidAt', 'paymentToken', 'stripeCheckoutSessionId',
            'accountingExportedAt', 'notes',
        ];
        $lower = [];
        foreach ($row as $k => $v) {
            $lower[strtolower((string) $k)] = $v;
        }
        $out = $row;
        foreach ($canonical as $key) {
            $l = strtolower($key);
            if (!\array_key_exists($key, $out) && \array_key_exists($l, $lower)) {
                $out[$key] = $lower[$l];
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function enrichRow(int $companyId, array $row): array
    {
        return InvoiceAmountsService::enrichInvoiceRow($companyId, $row);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function enrichRows(int $companyId, array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->enrichRow($companyId, $row);
        }

        return $out;
    }

    public function listByCompanyId(int $companyId, ?string $status = null, int $limit = 50): array
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
                i.accountingExportedAt,
                (COALESCE(i.amountTotal,0) - COALESCE(i.amountPaid,0)) AS amountRemaining,
                COALESCE(q.projectId, i.projectId) AS quoteProjectId
            FROM Invoice i
            LEFT JOIN Quote q ON q.id = i.quoteId AND q.companyId = i.companyId
            WHERE i.companyId = :companyId
        ';
        $params = ['companyId' => $companyId];

        if ($status !== null && $status !== '') {
            $sql .= ' AND i.status = :status ';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY i.id DESC LIMIT :limit';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('companyId', $params['companyId'], PDO::PARAM_INT);
        if (isset($params['status'])) {
            $stmt->bindValue('status', $params['status'], PDO::PARAM_STR);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $this->enrichRows($companyId, $rows);
    }

    public function listByCompanyIdAndClientId(int $companyId, int $clientId, ?string $status = null, int $limit = 50): array
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
                i.accountingExportedAt,
                (COALESCE(i.amountTotal,0) - COALESCE(i.amountPaid,0)) AS amountRemaining,
                COALESCE(q.projectId, i.projectId) AS quoteProjectId
            FROM Invoice i
            LEFT JOIN Quote q
                ON q.id = i.quoteId
               AND q.companyId = i.companyId
            WHERE i.companyId = :companyId
              AND i.clientId = :clientId
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

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $rows = self::finalizeInvoiceListRows(
            self::stripDraftWhenProjectHasNonDraft(self::dedupeByQuoteIdKeepPreferred($rows))
        );

        return $this->enrichRows($companyId, $rows);
    }

    /**
     * Une facture = une ligne : le statut évolue (brouillon → envoyé, etc.).
     * Si plusieurs lignes existent pour le même devis, on garde celle qui n’est plus brouillon
     * si l’une l’est (c’est la même facture après envoi), sinon la plus récente.
     */
    private static function preferSameLogicalInvoice(array $a, array $b): array
    {
        $da = ((string) ($a['status'] ?? '')) === 'brouillon';
        $db = ((string) ($b['status'] ?? '')) === 'brouillon';
        if ($da && !$db) {
            return $b;
        }
        if (!$da && $db) {
            return $a;
        }

        return ((int) ($b['id'] ?? 0)) > ((int) ($a['id'] ?? 0)) ? $b : $a;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private static function dedupeByQuoteIdKeepPreferred(array $rows): array
    {
        $byQuoteId = [];
        $noQuote = [];
        foreach ($rows as $row) {
            $qid = (int) ($row['quoteId'] ?? 0);
            if ($qid <= 0) {
                $noQuote[] = $row;
                continue;
            }
            $prev = $byQuoteId[$qid] ?? null;
            if ($prev === null) {
                $byQuoteId[$qid] = $row;
                continue;
            }
            $byQuoteId[$qid] = self::preferSameLogicalInvoice($prev, $row);
        }
        $merged = array_merge(array_values($byQuoteId), $noQuote);
        usort($merged, static function (array $a, array $b): int {
            return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
        });

        return $merged;
    }

    /**
     * Même affaire : si une facture liée à un devis a déjà quitté le brouillon, ne pas lister les
     * brouillons encore associés à un devis (doublons / ancienne ligne). Les brouillons sans devis
     * (factures manuelles) ne sont pas masqués.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private static function stripDraftWhenProjectHasNonDraft(array $rows): array
    {
        $projectHasNonDraft = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['quoteProjectId'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            if ((string) ($row['status'] ?? '') !== 'brouillon') {
                $projectHasNonDraft[$pid] = true;
            }
        }
        $out = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['quoteProjectId'] ?? 0);
            $quoteId = (int) ($row['quoteId'] ?? 0);
            // Ne masquer que les brouillons liés à un devis : les factures manuelles (sans quoteId)
            // restent visibles même si une autre facture de l’affaire n’est plus en brouillon.
            if (
                $quoteId > 0
                && $pid > 0
                && ($projectHasNonDraft[$pid] ?? false)
                && (string) ($row['status'] ?? '') === 'brouillon'
            ) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Retire la colonne technique utilisée pour le filtrage.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private static function finalizeInvoiceListRows(array $rows): array
    {
        foreach ($rows as $i => $row) {
            if (isset($row['quoteProjectId'])) {
                unset($rows[$i]['quoteProjectId']);
            }
        }

        return $rows;
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
                i.paymentToken,
                i.accountingExportedAt,
                (COALESCE(i.amountTotal,0) - COALESCE(i.amountPaid,0)) AS amountRemaining,
                COALESCE(q.projectId, i.projectId) AS quoteProjectId
            FROM Invoice i
            LEFT JOIN Quote q
                ON q.id = i.quoteId
               AND q.companyId = i.companyId
            WHERE i.companyId = :companyId
              AND (
                  i.projectId = :projectId1
                  OR q.projectId = :projectId2
              )
        ';

        $params = ['companyId' => $companyId, 'projectId1' => $projectId, 'projectId2' => $projectId];

        if ($status !== null && $status !== '') {
            $sql .= ' AND i.status = :status ';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY i.id DESC LIMIT :limit';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('companyId', $params['companyId'], PDO::PARAM_INT);
        $stmt->bindValue('projectId1', $params['projectId1'], PDO::PARAM_INT);
        $stmt->bindValue('projectId2', $params['projectId2'], PDO::PARAM_INT);
        if (isset($params['status'])) {
            $stmt->bindValue('status', $params['status'], PDO::PARAM_STR);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $rows = self::finalizeInvoiceListRows(
            self::stripDraftWhenProjectHasNonDraft(self::dedupeByQuoteIdKeepPreferred($rows))
        );

        return $this->enrichRows($companyId, $rows);
    }

    public function findByCompanyIdAndId(int $companyId, int $invoiceId): ?array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT
                id,
                quoteId,
                projectId,
                clientId,
                invoiceNumber,
                title,
                dueDate,
                status,
                amountTotal,
                amountPaid,
                paidAt,
                paymentToken,
                stripeCheckoutSessionId,
                accountingExportedAt,
                notes
            FROM Invoice
            WHERE companyId = :companyId AND id = :invoiceId
            LIMIT 1
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'invoiceId' => $invoiceId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $row = self::normalizeInvoiceSelectRow($row);

        return $this->enrichRow($companyId, $row);
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
        ?string $notes,
        ?string $paymentToken = null
    ): int {
        $pdo = Connection::pdo();
        $pdo->beginTransaction();

        try {
            if ($invoiceNumber === null || $invoiceNumber === '') {
                $invoiceNumber = 'FA-' . date('YmdHis') . '-' . random_int(100, 999);
            }
            if ($paymentToken === null || trim($paymentToken) === '') {
                $paymentToken = bin2hex(random_bytes(24));
            }

            $stmtQ = $pdo->prepare('SELECT projectId FROM Quote WHERE companyId = :cid AND id = :qid LIMIT 1');
            $stmtQ->execute(['cid' => $companyId, 'qid' => $quoteId]);
            $rowQ = $stmtQ->fetch(PDO::FETCH_ASSOC);
            $projectIdIns = null;
            if (is_array($rowQ) && isset($rowQ['projectId'])) {
                $p = (int) $rowQ['projectId'];
                $projectIdIns = $p > 0 ? $p : null;
            }

            $stmt = $pdo->prepare('
                INSERT INTO Invoice (
                    companyId,
                    quoteId,
                    projectId,
                    clientId,
                    invoiceNumber,
                    title,
                    dueDate,
                    status,
                    amountTotal,
                    amountPaid,
                    createdByUserId,
                    notes,
                    paymentToken,
                    createdAt,
                    updatedAt
                ) VALUES (
                    :companyId,
                    :quoteId,
                    :projectId,
                    :clientId,
                    :invoiceNumber,
                    :title,
                    :dueDate,
                    :status,
                    :amountTotal,
                    0,
                    :createdByUserId,
                    :notes,
                    :paymentToken,
                    NOW(),
                    NOW()
                )
            ');
            $stmtSum = $pdo->prepare('
                SELECT COALESCE(SUM(lineTtc), 0) AS ttc
                FROM QuoteItem
                WHERE companyId = :companyId AND quoteId = :quoteId
            ');
            $stmtSum->execute(['companyId' => $companyId, 'quoteId' => $quoteId]);
            $sumRow = $stmtSum->fetch(PDO::FETCH_ASSOC);
            $ttcFromLines = round((float) ($sumRow['ttc'] ?? 0), 2);
            $amountStored = $ttcFromLines > 0.0001 ? $ttcFromLines : (float) round($amountTotal, 2);

            $stmt->execute([
                'companyId' => $companyId,
                'quoteId' => $quoteId,
                'projectId' => $projectIdIns,
                'clientId' => $clientId,
                'invoiceNumber' => $invoiceNumber,
                'title' => $title,
                'dueDate' => $dueDateYmd,
                'status' => $status,
                'amountTotal' => $amountStored,
                'createdByUserId' => $createdByUserId,
                'notes' => $notes,
                'paymentToken' => $paymentToken,
            ]);

            $invoiceId = (int) $pdo->lastInsertId();

            $stmtCopy = $pdo->prepare('
                INSERT INTO InvoiceItem (
                    companyId,
                    invoiceId,
                    priceLibraryItemId,
                    description,
                    quantity,
                    unitPrice,
                    lineTotal,
                    vatRate,
                    revenueAccount,
                    lineVat,
                    lineTtc,
                    lineSort
                )
                SELECT
                    qi.companyId,
                    :invoiceId,
                    qi.priceLibraryItemId,
                    qi.description,
                    qi.quantity,
                    qi.unitPrice,
                    qi.lineTotal,
                    qi.vatRate,
                    qi.revenueAccount,
                    qi.lineVat,
                    qi.lineTtc,
                    qi.id
                FROM QuoteItem qi
                WHERE qi.companyId = :cid
                  AND qi.quoteId = :qid
            ');
            $stmtCopy->execute([
                'invoiceId' => $invoiceId,
                'cid' => $companyId,
                'qid' => $quoteId,
            ]);

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
              AND status <> "annulee"
            LIMIT 1
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'quoteId' => $quoteId,
        ]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Annulation d’affaire : factures liées aux devis concernés.
     *
     * @param array<int, int> $quoteIds
     */
    public function cancelInvoicesByQuoteIds(int $companyId, array $quoteIds): void
    {
        $quoteIds = array_values(array_unique(array_filter(
            array_map(static fn ($v): int => (int) $v, $quoteIds),
            static fn (int $id): bool => $id > 0
        )));
        if ($quoteIds === []) {
            return;
        }

        $pdo = Connection::pdo();
        $placeholders = implode(',', array_fill(0, count($quoteIds), '?'));
        $sql = '
            UPDATE Invoice
            SET status = "annulee",
                updatedAt = NOW()
            WHERE companyId = ?
              AND quoteId IN (' . $placeholders . ')
              AND status <> "annulee"
        ';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$companyId], $quoteIds));
    }

    public function markAsSent(int $companyId, int $invoiceId): void
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE Invoice
            SET status = "envoyee",
                sentAt = COALESCE(sentAt, NOW()),
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

    /**
     * Marque la facture comme exportée en comptabilité (écritures générées).
     */
    public function markAccountingExported(int $companyId, int $invoiceId): void
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE Invoice
            SET accountingExportedAt = NOW(),
                updatedAt = NOW()
            WHERE companyId = :companyId
              AND id = :invoiceId
              AND status IN ("envoyee", "partiellement_payee", "payee", "echue")
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'invoiceId' => $invoiceId,
        ]);
    }

    /**
     * Factures non brouillon, non annulées (base export écritures).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForAccountingLines(int $companyId, ?bool $onlyNotExported = null, int $limit = 500): array
    {
        $pdo = Connection::pdo();
        $sql = '
            SELECT
                id,
                quoteId,
                clientId,
                invoiceNumber,
                title,
                dueDate,
                status,
                amountTotal,
                amountPaid,
                accountingExportedAt
            FROM Invoice
            WHERE companyId = :companyId
              AND status IN ("envoyee", "partiellement_payee", "payee", "echue")
        ';
        if ($onlyNotExported === true) {
            $sql .= ' AND accountingExportedAt IS NULL ';
        }
        $sql .= ' ORDER BY id ASC LIMIT :limit';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('companyId', $companyId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Factures éligibles export comptable parmi une liste d’IDs (même filtres statut que listForAccountingLines).
     *
     * @param array<int, int|string> $invoiceIds
     * @return array<int, array<string, mixed>>
     */
    public function listForAccountingByIds(int $companyId, array $invoiceIds, ?bool $onlyNotExported = null): array
    {
        $ids = [];
        foreach ($invoiceIds as $raw) {
            $id = is_numeric($raw) ? (int) $raw : 0;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "
            SELECT
                id,
                quoteId,
                clientId,
                invoiceNumber,
                title,
                dueDate,
                status,
                amountTotal,
                amountPaid,
                accountingExportedAt
            FROM Invoice
            WHERE companyId = ?
              AND id IN ($placeholders)
              AND status IN ('envoyee', 'partiellement_payee', 'payee', 'echue')
        ";
        if ($onlyNotExported === true) {
            $sql .= ' AND accountingExportedAt IS NULL ';
        }
        $sql .= ' ORDER BY id ASC';
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare($sql);
        $bind = array_merge([$companyId], array_values($ids));
        $stmt->execute($bind);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function updateDraftInvoiceMeta(int $companyId, int $invoiceId, string $title, string $dueDateYmd, ?string $notes): bool
    {
        $cur = $this->findByCompanyIdAndId($companyId, $invoiceId);
        if (!is_array($cur)) {
            return false;
        }
        $st = (string) ($cur['status'] ?? '');
        if (!in_array($st, ['brouillon', 'envoyee'], true)) {
            return false;
        }
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE Invoice
            SET title = :title,
                dueDate = :dueDate,
                notes = :notes,
                updatedAt = NOW()
            WHERE companyId = :companyId
              AND id = :invoiceId
              AND status IN (\'brouillon\', \'envoyee\')
        ');
        $stmt->execute([
            'title' => $title,
            'dueDate' => $dueDateYmd,
            'notes' => $notes !== null && $notes !== '' ? $notes : null,
            'companyId' => $companyId,
            'invoiceId' => $invoiceId,
        ]);

        return true;
    }

    /**
     * Recalcule amountTotal (TTC) depuis les lignes InvoiceItem (facture brouillon).
     */
    public function syncDraftAmountTotalFromItems(int $companyId, int $invoiceId): void
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE Invoice i
            SET i.amountTotal = (
                SELECT COALESCE(SUM(ii.lineTtc), 0)
                FROM InvoiceItem ii
                WHERE ii.companyId = i.companyId AND ii.invoiceId = i.id
            ),
            i.updatedAt = NOW()
            WHERE i.companyId = :companyId
              AND i.id = :invoiceId
              AND i.status IN (\'brouillon\', \'envoyee\')
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'invoiceId' => $invoiceId,
        ]);
    }

    /**
     * Facture rattachée à une affaire (directement ou via le devis).
     */
    public function invoiceBelongsToProject(int $companyId, int $invoiceId, int $projectId): bool
    {
        if ($projectId <= 0) {
            return false;
        }
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT 1
            FROM Invoice i
            LEFT JOIN Quote q ON q.id = i.quoteId AND q.companyId = i.companyId
            WHERE i.companyId = :companyId
              AND i.id = :invoiceId
              AND (
                  i.projectId = :projectId1
                  OR q.projectId = :projectId2
              )
            LIMIT 1
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'invoiceId' => $invoiceId,
            'projectId1' => $projectId,
            'projectId2' => $projectId,
        ]);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Facture manuelle (sans devis) pour une affaire (avoir, régularisation, etc.).
     */
    public function createManualInvoiceForProject(
        int $companyId,
        int $projectId,
        int $clientId,
        int $createdByUserId,
        string $title,
        string $dueDateYmd,
        ?string $notes
    ): int {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id, clientId FROM Project
            WHERE companyId = :companyId AND id = :projectId
            LIMIT 1
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'projectId' => $projectId,
        ]);
        $pr = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($pr) || (int) ($pr['clientId'] ?? 0) !== $clientId) {
            throw new \InvalidArgumentException('project_client_mismatch');
        }

        $invoiceNumber = 'FA-' . date('YmdHis') . '-' . random_int(100, 999);
        $paymentToken = bin2hex(random_bytes(24));
        $stmtIns = $pdo->prepare('
            INSERT INTO Invoice (
                companyId,
                quoteId,
                projectId,
                clientId,
                invoiceNumber,
                title,
                dueDate,
                status,
                amountTotal,
                amountPaid,
                createdByUserId,
                notes,
                paymentToken,
                createdAt,
                updatedAt
            ) VALUES (
                :companyId,
                NULL,
                :projectId,
                :clientId,
                :invoiceNumber,
                :title,
                :dueDate,
                \'brouillon\',
                0,
                0,
                :createdByUserId,
                :notes,
                :paymentToken,
                NOW(),
                NOW()
            )
        ');
        $stmtIns->execute([
            'companyId' => $companyId,
            'projectId' => $projectId,
            'clientId' => $clientId,
            'invoiceNumber' => $invoiceNumber,
            'title' => $title,
            'dueDate' => $dueDateYmd,
            'createdByUserId' => $createdByUserId,
            'notes' => $notes !== null && $notes !== '' ? $notes : null,
            'paymentToken' => $paymentToken,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Supprime une facture brouillon sans devis (création manuelle). Pas de paiement attendu sur un brouillon.
     */
    public function deleteManualDraftInvoice(int $companyId, int $invoiceId): bool
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            DELETE FROM Invoice
            WHERE companyId = :companyId
              AND id = :invoiceId
              AND status = \'brouillon\'
              AND quoteId IS NULL
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'invoiceId' => $invoiceId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Retrouve une facture par l’ID de session Stripe Checkout enregistré lors du paiement.
     */
    public function findByCompanyIdAndStripeCheckoutSessionId(int $companyId, string $sessionId): ?array
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            return null;
        }
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT
                id,
                companyId,
                quoteId,
                clientId,
                invoiceNumber,
                title,
                dueDate,
                status,
                amountTotal,
                amountPaid,
                paymentToken,
                stripeCheckoutSessionId,
                accountingExportedAt
            FROM Invoice
            WHERE companyId = :companyId
              AND stripeCheckoutSessionId = :sid
            LIMIT 1
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'sid' => $sessionId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return self::normalizeInvoiceSelectRow($row);
    }

    public function findByPaymentToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT
                id,
                companyId,
                quoteId,
                clientId,
                invoiceNumber,
                title,
                dueDate,
                status,
                amountTotal,
                amountPaid,
                paymentToken,
                stripeCheckoutSessionId,
                accountingExportedAt
            FROM Invoice
            WHERE paymentToken = :token
            LIMIT 1
        ');
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return self::normalizeInvoiceSelectRow($row);
    }

    public function saveStripeCheckoutSessionId(int $companyId, int $invoiceId, string $sessionId): void
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE Invoice
            SET stripeCheckoutSessionId = :sid,
                updatedAt = NOW()
            WHERE companyId = :companyId AND id = :invoiceId
        ');
        $stmt->execute([
            'sid' => $sessionId,
            'companyId' => $companyId,
            'invoiceId' => $invoiceId,
        ]);
    }

    /**
     * @return array{updated: bool, becamePaid: bool}
     */
    public function markAsPaidInFull(int $companyId, int $invoiceId): array
    {
        $inv = $this->findByCompanyIdAndId($companyId, $invoiceId);
        if (!is_array($inv)) {
            return ['updated' => false, 'becamePaid' => false];
        }
        $prevStatus = (string) ($inv['status'] ?? '');
        $ttc = round((float) ($inv['amountTotal'] ?? 0), 2);

        $pdo = Connection::pdo();
        // PDO natif MySQL : un même nom de paramètre ne peut pas être réutilisé deux fois (HY093).
        $stmt = $pdo->prepare('
            UPDATE Invoice
            SET status = \'payee\',
                amountTotal = :amountTotal,
                amountPaid = :amountPaid,
                paidAt = NOW(),
                updatedAt = NOW()
            WHERE companyId = :companyId
              AND id = :invoiceId
              AND status <> \'annulee\'
        ');
        $stmt->execute([
            'amountTotal' => $ttc,
            'amountPaid' => $ttc,
            'companyId' => $companyId,
            'invoiceId' => $invoiceId,
        ]);

        $updated = $stmt->rowCount() > 0;
        $becamePaid = $updated && $prevStatus !== 'payee';

        return ['updated' => $updated, 'becamePaid' => $becamePaid];
    }

    /**
     * Enregistre un paiement manuel (virement, espèces, etc.).
     *
     * @return array{ok: bool, becamePaid?: bool, error?: string}
     */
    public function recordManualPayment(int $companyId, int $invoiceId, float $amount): array
    {
        $inv = $this->findByCompanyIdAndId($companyId, $invoiceId);
        if (!is_array($inv)) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        $status = (string) ($inv['status'] ?? '');
        if ($status === 'annulee') {
            return ['ok' => false, 'error' => 'cancelled'];
        }
        if ($status === 'payee') {
            return ['ok' => false, 'error' => 'already_paid'];
        }

        $remaining = InvoiceAmountsService::remainingTtc($companyId, $inv);
        $amount = round($amount, 2);
        if ($amount <= 0 || $amount > $remaining + 0.009) {
            return ['ok' => false, 'error' => 'invalid_amount'];
        }

        $totalTtc = InvoiceAmountsService::canonicalTotalTtc($companyId, $inv);
        $currentPaid = round((float) ($inv['amountPaid'] ?? 0), 2);
        $newPaid = round($currentPaid + $amount, 2);
        $full = $newPaid >= $totalTtc - 0.01;
        if ($full) {
            $newPaid = $totalTtc;
        }
        $newStatus = $full ? 'payee' : 'partiellement_payee';
        $becamePaid = $full && (string) ($inv['status'] ?? '') !== 'payee';

        $pdo = Connection::pdo();
        $isFull = $full ? 1 : 0;
        $stmt = $pdo->prepare('
            UPDATE Invoice
            SET amountPaid = :amountPaid,
                amountTotal = :amountTotal,
                status = :newStatus,
                paidAt = IF(:isFull > 0, NOW(), paidAt),
                updatedAt = NOW()
            WHERE companyId = :companyId
              AND id = :invoiceId
              AND status <> \'annulee\'
        ');
        $stmt->execute([
            'amountPaid' => $newPaid,
            'amountTotal' => $totalTtc,
            'newStatus' => $newStatus,
            'isFull' => $isFull,
            'companyId' => $companyId,
            'invoiceId' => $invoiceId,
        ]);

        if ($stmt->rowCount() <= 0) {
            return ['ok' => false, 'error' => 'update_failed'];
        }

        try {
            (new \Modules\Payments\Repositories\PaymentRepository())->createPaymentSucceeded(
                companyId: $companyId,
                invoiceId: $invoiceId,
                amount: $amount,
                provider: 'manual',
                reference: null,
                paidAt: new \DateTimeImmutable('now'),
                metadata: null
            );
        } catch (\Throwable) {
            // traçabilité optionnelle
        }

        return ['ok' => true, 'becamePaid' => $becamePaid];
    }

    public function ensurePaymentToken(int $companyId, int $invoiceId): string
    {
        $inv = $this->findByCompanyIdAndId($companyId, $invoiceId);
        if (!is_array($inv)) {
            throw new \InvalidArgumentException('Facture introuvable');
        }
        $t = trim((string) ($inv['paymentToken'] ?? ''));
        if ($t !== '') {
            return $t;
        }
        $new = bin2hex(random_bytes(24));
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE Invoice SET paymentToken = :tok, updatedAt = NOW()
            WHERE companyId = :companyId AND id = :invoiceId
        ');
        $stmt->execute([
            'tok' => $new,
            'companyId' => $companyId,
            'invoiceId' => $invoiceId,
        ]);

        return $new;
    }
}

