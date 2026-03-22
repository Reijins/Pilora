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
            'id', 'companyId', 'quoteId', 'clientId', 'invoiceNumber', 'title', 'dueDate',
            'status', 'amountTotal', 'amountPaid', 'paidAt', 'paymentToken', 'stripeCheckoutSessionId',
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
                (COALESCE(i.amountTotal,0) - COALESCE(i.amountPaid,0)) AS amountRemaining,
                q.projectId AS quoteProjectId
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
     * Même affaire : si une facture a déjà quitté le brouillon, ne pas lister d’autres brouillons
     * parallèles (même facture logique, autre ligne de devis).
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
            if ($pid > 0 && ($projectHasNonDraft[$pid] ?? false) && (string) ($row['status'] ?? '') === 'brouillon') {
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
                (COALESCE(i.amountTotal,0) - COALESCE(i.amountPaid,0)) AS amountRemaining,
                q.projectId AS quoteProjectId
            FROM Invoice i
            INNER JOIN Quote q
                ON q.id = i.quoteId
               AND q.companyId = i.companyId
            INNER JOIN Project p
                ON p.id = :projectId
               AND p.companyId = i.companyId
            WHERE i.companyId = :companyId
              AND (
                  q.projectId = p.id
                  OR (
                      q.projectId IS NULL
                      AND q.clientId = p.clientId
                      AND q.title IN (p.name, CONCAT(\'Devis - \', p.name))
                  )
              )
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
                clientId,
                invoiceNumber,
                title,
                dueDate,
                status,
                amountTotal,
                amountPaid,
                paidAt,
                paymentToken,
                stripeCheckoutSessionId
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
                    paymentToken,
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
                    :paymentToken,
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
                'paymentToken' => $paymentToken,
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
                stripeCheckoutSessionId
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
                stripeCheckoutSessionId
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

