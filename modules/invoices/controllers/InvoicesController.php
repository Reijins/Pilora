<?php
declare(strict_types=1);

namespace Modules\Invoices\Controllers;

use App\Controllers\BaseController;
use Core\Context\UserContext;
use Core\Http\Request;
use Core\Http\Response;
use Core\Database\Connection;
use Core\Security\Csrf;
use Core\Support\DateFormatter;
use Core\View\View;
use Modules\Clients\Repositories\ClientRepository;
use Modules\Companies\Repositories\CompanyRepository;
use Modules\Contacts\Repositories\ContactRepository;
use Modules\Invoices\Repositories\InvoiceItemRepository;
use Modules\Invoices\Repositories\InvoiceRepository;
use Modules\Invoices\Services\InvoiceAccountingExportService;
use Modules\Invoices\Services\InvoiceAmountsService;
use Modules\Invoices\Services\LineAmountCalculator;
use Modules\PriceLibrary\Repositories\PriceLibraryRepository;
use Modules\Invoices\Services\InvoicePaidReceiptEmailService;
use Modules\Projects\Repositories\ProjectRepository;
use Modules\Quotes\Repositories\QuoteRepository;
use Modules\Quotes\Services\QuoteDeliveryService;
use Modules\Settings\Repositories\SmtpSettingsRepository;

final class InvoicesController extends BaseController
{
    /** Contenu modifiable (hors paiement partiel / total / annulé). */
    private static function invoiceStatusAllowsLineEdit(string $status): bool
    {
        return in_array($status, ['brouillon', 'envoyee'], true);
    }

    private static function canDeleteManualDraftInvoice(array $invoice, UserContext $userContext): bool
    {
        if ((string) ($invoice['status'] ?? '') !== 'brouillon') {
            return false;
        }
        if ((int) ($invoice['quoteId'] ?? 0) !== 0) {
            return false;
        }

        return in_array('invoice.update', $userContext->permissions, true)
            || in_array('invoice.create', $userContext->permissions, true);
    }

    /**
     * @param array<string, mixed>|null $pi Ligne bibliothèque sélectionnée (ou null).
     * @return array{vatRate:float, revenueAccount:?string}
     */
    private static function resolveLineVatAndAccount(?array $pi, mixed $postedVatRaw, mixed $postedAccRaw, float $companyDefaultVat): array
    {
        $vat = $companyDefaultVat;
        if (is_numeric($postedVatRaw)) {
            $vat = (float) $postedVatRaw;
        } elseif (is_array($pi) && isset($pi['defaultVatRate']) && is_numeric($pi['defaultVatRate'])) {
            $vat = (float) $pi['defaultVatRate'];
        }
        $vat = max(0.0, min(100.0, $vat));

        $acc = trim((string) $postedAccRaw);
        if ($acc === '' && is_array($pi) && isset($pi['defaultRevenueAccount'])) {
            $acc = trim((string) $pi['defaultRevenueAccount']);
        }

        return ['vatRate' => $vat, 'revenueAccount' => $acc !== '' ? $acc : null];
    }

    public function exportAccounting(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('invoice.export', $userContext->permissions, true)) {
            return Response::redirect('invoices');
        }

        $repo = new InvoiceRepository();

        if (strtoupper($request->getMethod()) === 'POST') {
            $csrf = $request->getBodyParam('csrf_token', null);
            if (!Csrf::verify(is_string($csrf) ? $csrf : null)) {
                return Response::redirect('invoices/export-accounting?err=Requ%C3%AAte%20invalide');
            }
            $idsRaw = $request->getBodyParam('invoice_id', []);
            if (!is_array($idsRaw)) {
                $idsRaw = [];
            }
            if ($idsRaw === []) {
                return Response::redirect('invoices/export-accounting?err=Aucune%20facture%20s%C3%A9lectionn%C3%A9e');
            }
            $mark = (string) $request->getBodyParam('mark_exported', '') === '1';
            $onlyPending = (string) $request->getBodyParam('only_pending', '') === '1';

            $smtp = (new SmtpSettingsRepository())->getByCompanyId($userContext->companyId);
            $svc = new InvoiceAccountingExportService();
            $result = $svc->buildExportCsv(
                $userContext->companyId,
                $smtp,
                $onlyPending ? true : null,
                $mark,
                $idsRaw,
            );

            if ($result['errors'] !== []) {
                $first = reset($result['errors']);
                $msg = is_string($first) ? $first : 'Erreur export';

                return Response::redirect('invoices/export-accounting?err=' . rawurlencode($msg));
            }

            Csrf::rotate();
            $fileName = 'ecritures_comptables_' . date('Ymd_His') . '.csv';

            return new Response(
                body: $result['csv'],
                status: 200,
                headers: [
                    'Content-Type' => 'text/csv; charset=utf-8',
                    'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                ],
            );
        }

        $onlyPending = (string) $request->getQueryParam('only_pending', '1') !== '0';
        $exportable = [];
        try {
            $exportable = $repo->listForAccountingLines($userContext->companyId, $onlyPending ? true : null, 500);
        } catch (\Throwable) {
            $exportable = [];
        }

        return $this->renderPage('invoices/export_accounting.php', [
            'pageTitle' => 'Export comptable',
            'permissionDenied' => false,
            'exportableInvoices' => $exportable,
            'onlyPending' => $onlyPending,
            'csrfToken' => Csrf::token(),
            'flashError' => $request->getQueryParam('err', null),
        ]);
    }

    public function show(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('invoice.read', $userContext->permissions, true)) {
            return Response::redirect('invoices');
        }

        $idRaw = $request->getQueryParam('invoiceId', 0);
        $invoiceId = is_numeric($idRaw) ? (int) $idRaw : 0;
        if ($invoiceId <= 0) {
            return Response::redirect('invoices?err=Facture%20invalide');
        }

        $repo = new InvoiceRepository();
        $invoice = $repo->findByCompanyIdAndId($userContext->companyId, $invoiceId);
        if (!is_array($invoice)) {
            return Response::redirect('invoices?err=Facture%20introuvable');
        }

        $itemRepo = new InvoiceItemRepository();
        $items = [];
        try {
            $items = $itemRepo->listByCompanyIdAndInvoiceId($userContext->companyId, $invoiceId);
        } catch (\Throwable) {
            $items = [];
        }
        $quoteId = (int) ($invoice['quoteId'] ?? 0);
        if ($items === [] && $quoteId > 0) {
            try {
                $items = (new QuoteRepository())->listItemsByCompanyIdAndQuoteId($userContext->companyId, $quoteId);
            } catch (\Throwable) {
                $items = [];
            }
        }

        $totals = InvoiceAmountsService::displayTotalsForInvoice($userContext->companyId, $invoice);
        $projectId = (int) ($invoice['projectId'] ?? 0);
        if ($projectId <= 0 && $quoteId > 0) {
            $q = (new QuoteRepository())->findByCompanyIdAndId($userContext->companyId, $quoteId);
            if (is_array($q)) {
                $projectId = (int) ($q['projectId'] ?? 0);
            }
        }

        $canEditDraft = self::invoiceStatusAllowsLineEdit((string) ($invoice['status'] ?? ''))
            && in_array('invoice.update', $userContext->permissions, true);

        $invSt = (string) ($invoice['status'] ?? '');
        $canSendInvoice = in_array('invoice.read', $userContext->permissions, true)
            && $projectId > 0
            && $invSt === 'brouillon';
        $canResendInvoice = in_array('invoice.read', $userContext->permissions, true)
            && $projectId > 0
            && in_array($invSt, ['envoyee', 'partiellement_payee', 'echue', 'payee'], true);

        $remainingTtc = InvoiceAmountsService::remainingTtc($userContext->companyId, $invoice);
        $canAddPaymentFromShow = in_array('invoice.mark_paid', $userContext->permissions, true)
            && $invSt !== 'annulee'
            && $invSt !== 'payee'
            && $remainingTtc > 0.009;

        $canDeleteManualDraft = self::canDeleteManualDraftInvoice($invoice, $userContext);

        return $this->renderPage('invoices/show.php', [
            'pageTitle' => 'Facture ' . (string) ($invoice['invoiceNumber'] ?? ('#' . $invoiceId)),
            'invoice' => $invoice,
            'items' => $items,
            'displayTotals' => $totals,
            'projectId' => $projectId,
            'canEditDraft' => $canEditDraft,
            'canSendInvoice' => $canSendInvoice,
            'canResendInvoice' => $canResendInvoice,
            'canAddPaymentFromShow' => $canAddPaymentFromShow,
            'canDeleteManualDraft' => $canDeleteManualDraft,
            'amountRemainingTtc' => round(max(0.0, $remainingTtc), 2),
            'csrfToken' => Csrf::token(),
            'flashMessage' => $request->getQueryParam('msg', null),
            'flashError' => $request->getQueryParam('err', null),
        ]);
    }

    public function edit(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('invoice.update', $userContext->permissions, true)) {
            return Response::redirect('invoices');
        }

        $idRaw = $request->getQueryParam('invoiceId', 0);
        $invoiceId = is_numeric($idRaw) ? (int) $idRaw : 0;
        if ($invoiceId <= 0) {
            return Response::redirect('invoices?err=Facture%20invalide');
        }

        $repo = new InvoiceRepository();
        $invoice = $repo->findByCompanyIdAndId($userContext->companyId, $invoiceId);
        if (!is_array($invoice)) {
            return Response::redirect('invoices?err=Facture%20introuvable');
        }
        if (!self::invoiceStatusAllowsLineEdit((string) ($invoice['status'] ?? ''))) {
            return Response::redirect('invoices/show?invoiceId=' . $invoiceId . '&err=Modification%20non%20autoris%C3%A9e%20pour%20ce%20statut');
        }

        $itemRepo = new InvoiceItemRepository();
        $items = [];
        try {
            $items = $itemRepo->listByCompanyIdAndInvoiceId($userContext->companyId, $invoiceId);
        } catch (\Throwable) {
            $items = [];
        }
        $quoteId = (int) ($invoice['quoteId'] ?? 0);
        if ($items === [] && $quoteId > 0) {
            try {
                $items = (new QuoteRepository())->listItemsByCompanyIdAndQuoteId($userContext->companyId, $quoteId);
            } catch (\Throwable) {
                $items = [];
            }
        }

        $projectId = (int) ($invoice['projectId'] ?? 0);
        if ($projectId <= 0 && $quoteId > 0) {
            $q = (new QuoteRepository())->findByCompanyIdAndId($userContext->companyId, $quoteId);
            if (is_array($q)) {
                $projectId = (int) ($q['projectId'] ?? 0);
            }
        }

        $smtp = (new SmtpSettingsRepository())->getByCompanyId($userContext->companyId);
        $vatDefault = (float) ($smtp['vat_rate'] ?? 20);
        if ($vatDefault < 0.0) {
            $vatDefault = 0.0;
        }
        if ($vatDefault > 100.0) {
            $vatDefault = 100.0;
        }

        $priceItems = [];
        try {
            $priceItems = (new PriceLibraryRepository())->listByCompanyId($userContext->companyId, false, 400);
        } catch (\Throwable) {
            $priceItems = [];
        }

        return $this->renderPage('invoices/edit.php', [
            'pageTitle' => 'Modifier la facture ' . (string) ($invoice['invoiceNumber'] ?? ('#' . $invoiceId)),
            'invoice' => $invoice,
            'items' => $items,
            'projectId' => $projectId,
            'quoteVatRate' => $vatDefault,
            'priceItems' => $priceItems,
            'csrfToken' => Csrf::token(),
            'canDeleteManualDraft' => self::canDeleteManualDraftInvoice($invoice, $userContext),
            'flashMessage' => $request->getQueryParam('msg', null),
            'flashError' => $request->getQueryParam('err', null),
        ]);
    }

    public function saveDraft(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('invoice.update', $userContext->permissions, true)) {
            return Response::redirect('invoices');
        }
        $csrf = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrf) ? $csrf : null)) {
            return Response::redirect('invoices?err=CSRF%20invalide');
        }
        $invoiceId = (int) $request->getBodyParam('invoice_id', 0);
        if ($invoiceId <= 0) {
            return Response::redirect('invoices');
        }
        $title = trim((string) $request->getBodyParam('title', ''));
        $due = trim((string) $request->getBodyParam('due_date', ''));
        $notes = trim((string) $request->getBodyParam('notes', ''));
        if ($title === '' || $due === '') {
            return Response::redirect('invoices/edit?invoiceId=' . $invoiceId . '&err=Champs%20requis');
        }

        $repo = new InvoiceRepository();
        $invoice = $repo->findByCompanyIdAndId($userContext->companyId, $invoiceId);
        if (!is_array($invoice) || !self::invoiceStatusAllowsLineEdit((string) ($invoice['status'] ?? ''))) {
            return Response::redirect('invoices/edit?invoiceId=' . $invoiceId . '&err=Modification%20non%20autoris%C3%A9e%20pour%20ce%20statut');
        }

        $namesRaw = $request->getBodyParam('item_name', []);
        $qtyRaw = $request->getBodyParam('item_quantity', []);
        $priceRaw = $request->getBodyParam('item_unit_price', []);
        $vatRaw = $request->getBodyParam('item_vat_rate', []);
        $accRaw = $request->getBodyParam('item_revenue_account', []);
        $libIdRaw = $request->getBodyParam('item_price_item_id', []);
        if (!is_array($namesRaw)) {
            $namesRaw = [];
        }
        if (!is_array($qtyRaw)) {
            $qtyRaw = [];
        }
        if (!is_array($priceRaw)) {
            $priceRaw = [];
        }
        if (!is_array($vatRaw)) {
            $vatRaw = [];
        }
        if (!is_array($accRaw)) {
            $accRaw = [];
        }
        if (!is_array($libIdRaw)) {
            $libIdRaw = [];
        }

        $smtp = (new SmtpSettingsRepository())->getByCompanyId($userContext->companyId);
        $vatDefault = (float) ($smtp['vat_rate'] ?? 20);
        if ($vatDefault < 0.0) {
            $vatDefault = 0.0;
        }
        if ($vatDefault > 100.0) {
            $vatDefault = 100.0;
        }

        $priceLibRepo = new PriceLibraryRepository();
        $maxLen = max(count($namesRaw), count($qtyRaw), count($priceRaw), count($vatRaw), count($accRaw), count($libIdRaw));
        $linesToInsert = [];
        for ($i = 0; $i < $maxLen; $i++) {
            $desc = trim((string) ($namesRaw[$i] ?? ''));
            if ($desc === '') {
                continue;
            }
            $qtyStr = str_replace(',', '.', trim((string) ($qtyRaw[$i] ?? '0')));
            $puStr = str_replace(',', '.', trim((string) ($priceRaw[$i] ?? '0')));
            $quantity = is_numeric($qtyStr) ? (float) $qtyStr : 0.0;
            $unitPrice = is_numeric($puStr) ? (float) $puStr : 0.0;
            if ($quantity < 0.0) {
                $quantity = 0.0;
            }
            $libId = (int) ($libIdRaw[$i] ?? 0);
            $pi = null;
            if ($libId > 0) {
                $pi = $priceLibRepo->findByCompanyAndId($userContext->companyId, $libId);
            }
            $comm = self::resolveLineVatAndAccount(
                $pi,
                $vatRaw[$i] ?? null,
                $accRaw[$i] ?? null,
                $vatDefault,
            );
            $amt = LineAmountCalculator::fromQtyUnitVat($quantity, $unitPrice, $comm['vatRate']);
            $linesToInsert[] = [
                'priceLibraryItemId' => $pi !== null ? $libId : null,
                'description' => $desc,
                'quantity' => $quantity,
                'unitPrice' => $unitPrice,
                'vatRate' => $comm['vatRate'],
                'revenueAccount' => $comm['revenueAccount'],
                'lineTotal' => $amt['lineTotal'],
                'lineVat' => $amt['lineVat'],
                'lineTtc' => $amt['lineTtc'],
            ];
        }

        if ($linesToInsert === []) {
            return Response::redirect('invoices/edit?invoiceId=' . $invoiceId . '&err=Ajoutez%20au%20moins%20une%20prestation');
        }

        $pdo = Connection::pdo();
        try {
            $pdo->beginTransaction();
            $okMeta = $repo->updateDraftInvoiceMeta(
                $userContext->companyId,
                $invoiceId,
                $title,
                $due,
                $notes !== '' ? $notes : null,
            );
            if (!$okMeta) {
                $pdo->rollBack();

                return Response::redirect('invoices/edit?invoiceId=' . $invoiceId . '&err=Mise%20%C3%A0%20jour%20impossible');
            }
            $itemRepo = new InvoiceItemRepository();
            $itemRepo->deleteAllForInvoice($userContext->companyId, $invoiceId);
            $sort = 0;
            foreach ($linesToInsert as $line) {
                $itemRepo->insertLine(
                    $userContext->companyId,
                    $invoiceId,
                    $line['priceLibraryItemId'],
                    $line['description'],
                    $line['quantity'],
                    $line['unitPrice'],
                    $line['vatRate'],
                    $line['revenueAccount'],
                    $line['lineTotal'],
                    $line['lineVat'],
                    $line['lineTtc'],
                    $sort,
                );
                $sort++;
            }
            $repo->syncDraftAmountTotalFromItems($userContext->companyId, $invoiceId);
            $pdo->commit();
        } catch (\Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return Response::redirect('invoices/edit?invoiceId=' . $invoiceId . '&err=Enregistrement%20impossible');
        }

        Csrf::rotate();

        return Response::redirect('invoices/show?invoiceId=' . $invoiceId . '&msg=Enregistr%C3%A9');
    }

    public function index(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        if (!in_array('invoice.read', $userContext->permissions, true)) {
            return $this->renderPage('invoices/index.php', [
                'pageTitle' => 'Factures',
                'permissionDenied' => true,
            ]);
        }

        $canMarkPaid = in_array('invoice.mark_paid', $userContext->permissions, true);
        $canExport = in_array('invoice.export', $userContext->permissions, true);

        $statusFilterRaw = $request->getQueryParam('status', '');
        $statusFilter = is_string($statusFilterRaw) ? trim($statusFilterRaw) : '';

        $statusLabels = [
            'brouillon' => 'Brouillon',
            'envoyee' => 'Envoyée',
            'partiellement_payee' => 'Partiellement payée',
            'payee' => 'Payée',
            'echue' => 'Échue',
            'annulee' => 'Annulée',
        ];

        $repo = new InvoiceRepository();
        $invoices = [];
        try {
            $invoices = $repo->listByCompanyId(
                companyId: $userContext->companyId,
                status: $statusFilter !== '' ? $statusFilter : null,
            );
        } catch (\Throwable) {
            $invoices = [];
        }

        $canInvoiceUpdate = in_array('invoice.update', $userContext->permissions, true);
        $canInvoiceCreate = in_array('invoice.create', $userContext->permissions, true);

        return $this->renderPage('invoices/index.php', [
            'pageTitle' => 'Factures',
            'permissionDenied' => false,
            'invoices' => $invoices,
            'statusLabels' => $statusLabels,
            'statusFilter' => $statusFilter,
            'canMarkPaid' => $canMarkPaid,
            'canExport' => $canExport,
            'canInvoiceUpdate' => $canInvoiceUpdate,
            'canInvoiceCreate' => $canInvoiceCreate,
            'csrfToken' => Csrf::token(),
            'flashMessage' => $request->getQueryParam('msg', null),
            'flashError' => $request->getQueryParam('err', null),
        ]);
    }

    public function export(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        if (!in_array('invoice.export', $userContext->permissions, true)) {
            return Response::redirect('invoices');
        }

        $statusFilterRaw = $request->getQueryParam('status', '');
        $statusFilter = is_string($statusFilterRaw) ? trim($statusFilterRaw) : '';

        $repo = new InvoiceRepository();
        $invoices = [];
        try {
            $invoices = $repo->listByCompanyId(
                companyId: $userContext->companyId,
                status: $statusFilter !== '' ? $statusFilter : null,
                limit: 500
            );
        } catch (\Throwable) {
            $invoices = [];
        }

        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, [
            'invoiceNumber',
            'title',
            'dueDate',
            'status',
            'amountTotal',
            'amountPaid',
            'amountRemaining',
            'clientId',
        ]);

        foreach ($invoices as $inv) {
            fputcsv($fh, [
                $inv['invoiceNumber'] ?? '',
                $inv['title'] ?? '',
                $inv['dueDate'] ?? '',
                $inv['status'] ?? '',
                $inv['amountTotal'] ?? 0,
                $inv['amountPaid'] ?? 0,
                $inv['amountRemaining'] ?? 0,
                $inv['clientId'] ?? '',
            ]);
        }

        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        $fileName = 'factures_' . date('Ymd_His') . '.csv';

        return new Response(
            body: $csv ?: '',
            status: 200,
            headers: [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ],
        );
    }

    public function new(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        if (!in_array('invoice.create', $userContext->permissions, true)) {
            return $this->renderPage('invoices/new.php', [
                'pageTitle' => 'Créer une facture',
                'permissionDenied' => true,
            ]);
        }

        $quoteIdRaw = $request->getQueryParam('quoteId', null);
        $quoteId = is_numeric($quoteIdRaw) ? (int) $quoteIdRaw : 0;
        if ($quoteId <= 0) {
            return Response::redirect('invoices');
        }

        $repoQuotes = new QuoteRepository();
        $quote = null;
        $totals = ['ht' => 0.0, 'vat_rate' => 20.0, 'vat_amount' => 0.0, 'ttc' => 0.0];
        try {
            $quote = $repoQuotes->findByCompanyIdAndId($userContext->companyId, $quoteId);
            if ($quote) {
                $totals = InvoiceAmountsService::fromQuote($userContext->companyId, $quoteId);
            }
        } catch (\Throwable) {
            $quote = null;
        }

        if ($quote === null) {
            return Response::redirect('invoices?err=Devis%20introuvable');
        }

        $dueDateYmd = '';
        $dueDateRaw = $request->getQueryParam('dueDate', '');
        if (is_string($dueDateRaw) && trim($dueDateRaw) !== '') {
            $dueDateYmd = trim($dueDateRaw);
        } else {
            $dueDateYmd = (new \DateTimeImmutable('now'))->modify('+30 days')->format('Y-m-d');
        }

        return $this->renderPage('invoices/new.php', [
            'pageTitle' => 'Créer une facture',
            'permissionDenied' => false,
            'csrfToken' => Csrf::token(),
            'quoteId' => $quoteId,
            'quoteTitle' => (string) ($quote['title'] ?? ''),
            'quoteNumber' => (string) ($quote['quoteNumber'] ?? ''),
            'clientId' => (int) ($quote['clientId'] ?? 0),
            'invoiceTotals' => $totals,
            'dueDateYmd' => $dueDateYmd,
        ]);
    }

    public function create(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('invoice.create', $userContext->permissions, true)) {
            return Response::redirect('invoices');
        }

        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('invoices?err=CSRF%20invalide');
        }

        $quoteIdRaw = $request->getBodyParam('quote_id', null);
        $quoteId = is_numeric($quoteIdRaw) ? (int) $quoteIdRaw : 0;
        $dueDateYmd = trim((string) $request->getBodyParam('due_date', ''));
        $notes = trim((string) $request->getBodyParam('notes', ''));

        if ($quoteId <= 0) {
            return Response::redirect('invoices?err=Devis%20invalide');
        }
        if ($dueDateYmd === '') {
            return Response::redirect('invoices?err=Date%20d’échéance%20requise');
        }

        $repoQuotes = new QuoteRepository();
        $repoInvoices = new InvoiceRepository();

        $quote = $repoQuotes->findByCompanyIdAndId($userContext->companyId, $quoteId);
        if ($quote === null) {
            return Response::redirect('invoices?err=Devis%20introuvable');
        }

        $totals = InvoiceAmountsService::fromQuote($userContext->companyId, $quoteId);

        $title = (string) ($quote['title'] ?? 'Facture');
        $clientId = (int) ($quote['clientId'] ?? 0);
        $createdByUserId = (int) $userContext->userId;

        try {
            $repoInvoices->createInvoiceFromQuote(
                companyId: $userContext->companyId,
                quoteId: $quoteId,
                clientId: $clientId,
                invoiceNumber: null,
                title: $title,
                dueDateYmd: $dueDateYmd,
                status: 'brouillon',
                amountTotal: $totals['ttc'],
                createdByUserId: $createdByUserId,
                notes: $notes !== '' ? $notes : null,
            );

            $repoQuotes->markQuoteAsAccepted($userContext->companyId, $quoteId, new \DateTimeImmutable('now'));
        } catch (\Throwable) {
            return Response::redirect('invoices?err=Impossible%20de%20cr%C3%A9er%20la%20facture');
        }

        Csrf::rotate();
        return Response::redirect('invoices?msg=Facture%20cr%C3%A9%C3%A9e');
    }

    public function newManualFromProject(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('invoice.create', $userContext->permissions, true)) {
            return Response::redirect('projects?err=Permissions%20insuffisantes');
        }
        $pidRaw = $request->getQueryParam('projectId', 0);
        $projectId = is_numeric($pidRaw) ? (int) $pidRaw : 0;
        if ($projectId <= 0) {
            return Response::redirect('projects?err=Affaire%20invalide');
        }
        $proj = (new ProjectRepository())->findByCompanyIdAndId($userContext->companyId, $projectId);
        if (!is_array($proj)) {
            return Response::redirect('projects?err=Affaire%20introuvable');
        }
        $due = (new \DateTimeImmutable('now'))->modify('+30 days')->format('Y-m-d');

        return $this->renderPage('invoices/manual_new.php', [
            'pageTitle' => 'Nouvelle facture (affaire)',
            'project' => $proj,
            'projectId' => $projectId,
            'defaultDueYmd' => $due,
            'csrfToken' => Csrf::token(),
            'flashError' => $request->getQueryParam('err', null),
        ]);
    }

    public function createManualFromProject(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('invoice.create', $userContext->permissions, true)) {
            return Response::redirect('projects?err=Permissions%20insuffisantes');
        }
        $csrf = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrf) ? $csrf : null)) {
            return Response::redirect('projects?err=CSRF%20invalide');
        }
        $projectId = (int) $request->getBodyParam('project_id', 0);
        $title = trim((string) $request->getBodyParam('title', ''));
        $due = trim((string) $request->getBodyParam('due_date', ''));
        $notes = trim((string) $request->getBodyParam('notes', ''));
        if ($projectId <= 0 || $title === '' || $due === '') {
            return Response::redirect('invoices/new-manual?projectId=' . max(0, $projectId) . '&err=Champs%20requis');
        }
        $proj = (new ProjectRepository())->findByCompanyIdAndId($userContext->companyId, $projectId);
        if (!is_array($proj)) {
            return Response::redirect('projects?err=Affaire%20introuvable');
        }
        $clientId = (int) ($proj['clientId'] ?? 0);
        if ($clientId <= 0) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Client%20affaire%20invalide');
        }
        $repo = new InvoiceRepository();
        try {
            $invoiceId = $repo->createManualInvoiceForProject(
                $userContext->companyId,
                $projectId,
                $clientId,
                (int) $userContext->userId,
                $title,
                $due,
                $notes !== '' ? $notes : null,
            );
        } catch (\InvalidArgumentException) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Cr%C3%A9ation%20facture%20impossible');
        } catch (\Throwable) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Cr%C3%A9ation%20facture%20impossible');
        }

        Csrf::rotate();

        return Response::redirect('invoices/edit?invoiceId=' . $invoiceId . '&msg=' . rawurlencode('Facture créée — complétez les lignes.'));
    }

    public function deleteManualDraft(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        $csrf = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrf) ? $csrf : null)) {
            return Response::redirect('invoices?err=CSRF%20invalide');
        }
        $invoiceId = (int) $request->getBodyParam('invoice_id', 0);
        $projectId = (int) $request->getBodyParam('project_id', 0);
        if ($invoiceId <= 0) {
            return Response::redirect('invoices');
        }
        $repo = new InvoiceRepository();
        $invoice = $repo->findByCompanyIdAndId($userContext->companyId, $invoiceId);
        if (!is_array($invoice) || !self::canDeleteManualDraftInvoice($invoice, $userContext)) {
            return Response::redirect('invoices/show?invoiceId=' . $invoiceId . '&err=Suppression%20non%20autoris%C3%A9e');
        }
        if (!$repo->deleteManualDraftInvoice($userContext->companyId, $invoiceId)) {
            return Response::redirect('invoices/show?invoiceId=' . $invoiceId . '&err=Suppression%20impossible');
        }
        Csrf::rotate();
        if ($projectId > 0) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&msg=' . rawurlencode('Facture brouillon supprimée.'));
        }

        return Response::redirect('invoices?msg=' . rawurlencode('Facture brouillon supprimée.'));
    }

    public function sendFromProject(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('invoice.read', $userContext->permissions, true)) {
            return Response::redirect('clients?err=Permissions%20insuffisantes');
        }

        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('clients?err=CSRF%20invalide');
        }

        $projectId = (int) $request->getBodyParam('project_id', 0);
        $invoiceId = (int) $request->getBodyParam('invoice_id', 0);
        if ($projectId <= 0 || $invoiceId <= 0) {
            return Response::redirect('projects/show?projectId=' . max(0, $projectId) . '&err=Facture%20invalide');
        }

        $repoInvoices = new InvoiceRepository();
        $invoice = $repoInvoices->findByCompanyIdAndId($userContext->companyId, $invoiceId);
        if (!is_array($invoice)) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Facture%20introuvable');
        }
        if ((string) ($invoice['status'] ?? '') === 'annulee') {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Facture%20annul%C3%A9e');
        }
        if ((string) ($invoice['status'] ?? '') !== 'brouillon') {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Envoi%20r%C3%A9serv%C3%A9%20aux%20brouillons');
        }
        if (!$repoInvoices->invoiceBelongsToProject($userContext->companyId, $invoiceId, $projectId)) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Facture%20invalide%20pour%20cette%20affaire');
        }

        $quoteId = (int) ($invoice['quoteId'] ?? 0);

        try {
            $quoteRepo = new QuoteRepository();
            $quote = null;
            if ($quoteId > 0) {
                $quote = $quoteRepo->findByCompanyIdAndId($userContext->companyId, $quoteId);
                if (!is_array($quote)) {
                    return Response::redirect('projects/show?projectId=' . $projectId . '&err=Devis%20introuvable');
                }
            } else {
                $quote = ['title' => '', 'quoteNumber' => '', 'id' => 0];
            }
            $project = (new ProjectRepository())->findByCompanyIdAndId($userContext->companyId, $projectId);
            $client = (new ClientRepository())->findByCompanyIdAndId($userContext->companyId, (int) ($invoice['clientId'] ?? 0));
            if (!is_array($project) || !is_array($client)) {
                return Response::redirect('projects/show?projectId=' . $projectId . '&err=Donn%C3%A9es%20de%20facture%20incompl%C3%A8tes');
            }
            $toEmail = trim((string) ($client['email'] ?? ''));
            if ($toEmail === '' || filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
                return Response::redirect('projects/show?projectId=' . $projectId . '&err=Email%20client%20invalide');
            }

            $itemRepo = new InvoiceItemRepository();
            $items = $itemRepo->listByCompanyIdAndInvoiceId($userContext->companyId, $invoiceId);
            if ($items === [] && $quoteId > 0) {
                $items = $quoteRepo->listItemsByCompanyIdAndQuoteId($userContext->companyId, $quoteId);
            }
            $smtp = (new SmtpSettingsRepository())->getByCompanyId($userContext->companyId);
            $companyIdentity = (new CompanyRepository())->getDocumentIdentity($userContext->companyId, $smtp);
            $companyName = (string) $companyIdentity['name'];
            $totals = InvoiceAmountsService::displayTotalsForInvoice($userContext->companyId, $invoice);
            $contacts = (new ContactRepository())->listByCompanyIdAndClientId(
                $userContext->companyId,
                (int) ($invoice['clientId'] ?? 0)
            );
            $viewsRoot = dirname(__DIR__, 3) . '/app/views';
            $pdfHtml = View::render($viewsRoot . '/invoices/pdf.php', [
                'invoice' => $invoice,
                'quote' => $quote,
                'project' => $project,
                'client' => $client,
                'company' => $companyIdentity,
                'items' => $items,
                'totals' => $totals,
                'contacts' => $contacts,
            ]);

            $delivery = new QuoteDeliveryService();
            $pdfContent = $delivery->buildPdf($pdfHtml);
            $invoiceNumber = (string) ($invoice['invoiceNumber'] ?? ('FA-' . $invoiceId));
            $invoiceTitle = (string) ($invoice['title'] ?? '');
            $projectName = (string) (($project['name'] ?? '') !== '' ? $project['name'] : '—');
            $clientName = (string) (($client['name'] ?? '') !== '' ? $client['name'] : 'Client');
            $amountTtc = $totals['ttc'];
            $dueFr = DateFormatter::frDate(isset($invoice['dueDate']) ? (string) $invoice['dueDate'] : null);

            $payToken = $repoInvoices->ensurePaymentToken($userContext->companyId, $invoiceId);
            $invoicePayUrl = self::invoicePayAbsoluteUrl($payToken);

            $subjectTpl = (string) ($smtp['invoice_email_subject'] ?? 'Votre facture {{invoice_number}}');
            $bodyTpl = (string) ($smtp['invoice_email_body'] ?? "Bonjour,\n\nVeuillez trouver votre facture en pièce jointe (PDF).\n\nConsultez ou payez en ligne : {{invoice_link}}\n\nCordialement,\n{{company_name}}");
            $repl = [
                '{{company_name}}' => $companyName,
                '{{client_name}}' => $clientName,
                '{{invoice_number}}' => $invoiceNumber,
                '{{invoice_title}}' => $invoiceTitle,
                '{{project_name}}' => $projectName,
                '{{amount_total_ttc}}' => number_format($amountTtc, 2, ',', ' ') . ' €',
                '{{due_date}}' => $dueFr,
                '{{invoice_link}}' => $invoicePayUrl,
            ];
            $subject = strtr($subjectTpl, $repl);
            $body = strtr($bodyTpl, $repl);

            $delivery->sendQuoteEmail(
                companyId: $userContext->companyId,
                toEmail: $toEmail,
                subject: $subject,
                bodyText: $body,
                pdfContent: $pdfContent,
                pdfFileName: 'facture-' . $invoiceId . '.pdf'
            );
            $repoInvoices->markAsSent($userContext->companyId, $invoiceId);
        } catch (\Throwable) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Envoi%20facture%20impossible');
        }

        Csrf::rotate();
        return Response::redirect('projects/show?projectId=' . $projectId . '&msg=Facture%20envoy%C3%A9e%20au%20client');
    }

    /**
     * Renvoi email + PDF (facture déjà envoyée ou soldée — pas de changement de statut brouillon).
     */
    public function resendFromProject(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('invoice.read', $userContext->permissions, true)) {
            return Response::redirect('clients?err=Permissions%20insuffisantes');
        }

        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('clients?err=CSRF%20invalide');
        }

        $projectId = (int) $request->getBodyParam('project_id', 0);
        $invoiceId = (int) $request->getBodyParam('invoice_id', 0);
        if ($projectId <= 0 || $invoiceId <= 0) {
            return Response::redirect('projects/show?projectId=' . max(0, $projectId) . '&err=Facture%20invalide');
        }

        $repoInvoices = new InvoiceRepository();
        $invoice = $repoInvoices->findByCompanyIdAndId($userContext->companyId, $invoiceId);
        if (!is_array($invoice)) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Facture%20introuvable');
        }

        $invStatus = (string) ($invoice['status'] ?? '');
        if ($invStatus === 'annulee' || $invStatus === 'brouillon') {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Renvoi%20non%20autoris%C3%A9%20pour%20ce%20statut');
        }

        if (!in_array($invStatus, ['envoyee', 'partiellement_payee', 'echue', 'payee'], true)) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Renvoi%20non%20autoris%C3%A9');
        }

        if (!$repoInvoices->invoiceBelongsToProject($userContext->companyId, $invoiceId, $projectId)) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Facture%20invalide%20pour%20cette%20affaire');
        }

        $quoteId = (int) ($invoice['quoteId'] ?? 0);

        try {
            $quoteRepo = new QuoteRepository();
            $quote = null;
            if ($quoteId > 0) {
                $quote = $quoteRepo->findByCompanyIdAndId($userContext->companyId, $quoteId);
                if (!is_array($quote)) {
                    return Response::redirect('projects/show?projectId=' . $projectId . '&err=Devis%20introuvable');
                }
            } else {
                $quote = ['title' => '', 'quoteNumber' => '', 'id' => 0];
            }
            $project = (new ProjectRepository())->findByCompanyIdAndId($userContext->companyId, $projectId);
            $client = (new ClientRepository())->findByCompanyIdAndId($userContext->companyId, (int) ($invoice['clientId'] ?? 0));
            if (!is_array($project) || !is_array($client)) {
                return Response::redirect('projects/show?projectId=' . $projectId . '&err=Donn%C3%A9es%20de%20facture%20incompl%C3%A8tes');
            }
            $toEmail = trim((string) ($client['email'] ?? ''));
            if ($toEmail === '' || filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
                return Response::redirect('projects/show?projectId=' . $projectId . '&err=Email%20client%20invalide');
            }

            $itemRepo = new InvoiceItemRepository();
            $items = $itemRepo->listByCompanyIdAndInvoiceId($userContext->companyId, $invoiceId);
            if ($items === [] && $quoteId > 0) {
                $items = $quoteRepo->listItemsByCompanyIdAndQuoteId($userContext->companyId, $quoteId);
            }
            $smtp = (new SmtpSettingsRepository())->getByCompanyId($userContext->companyId);
            $companyIdentity = (new CompanyRepository())->getDocumentIdentity($userContext->companyId, $smtp);
            $companyName = (string) $companyIdentity['name'];
            $totals = InvoiceAmountsService::displayTotalsForInvoice($userContext->companyId, $invoice);
            $contacts = (new ContactRepository())->listByCompanyIdAndClientId(
                $userContext->companyId,
                (int) ($invoice['clientId'] ?? 0)
            );
            $viewsRoot = dirname(__DIR__, 3) . '/app/views';
            $pdfHtml = View::render($viewsRoot . '/invoices/pdf.php', [
                'invoice' => $invoice,
                'quote' => $quote,
                'project' => $project,
                'client' => $client,
                'company' => $companyIdentity,
                'items' => $items,
                'totals' => $totals,
                'contacts' => $contacts,
            ]);

            $delivery = new QuoteDeliveryService();
            $pdfContent = $delivery->buildPdf($pdfHtml);
            $invoiceNumber = (string) ($invoice['invoiceNumber'] ?? ('FA-' . $invoiceId));
            $invoiceTitle = (string) ($invoice['title'] ?? '');
            $projectName = (string) (($project['name'] ?? '') !== '' ? $project['name'] : '—');
            $clientName = (string) (($client['name'] ?? '') !== '' ? $client['name'] : 'Client');
            $amountTtc = $totals['ttc'];
            $dueFr = DateFormatter::frDate(isset($invoice['dueDate']) ? (string) $invoice['dueDate'] : null);

            $payToken = $repoInvoices->ensurePaymentToken($userContext->companyId, $invoiceId);
            $invoicePayUrl = self::invoicePayAbsoluteUrl($payToken);

            $subjectTpl = (string) ($smtp['invoice_email_subject'] ?? 'Votre facture {{invoice_number}}');
            $bodyTpl = (string) ($smtp['invoice_email_body'] ?? "Bonjour,\n\nVeuillez trouver votre facture en pièce jointe (PDF).\n\nConsultez ou payez en ligne : {{invoice_link}}\n\nCordialement,\n{{company_name}}");
            $repl = [
                '{{company_name}}' => $companyName,
                '{{client_name}}' => $clientName,
                '{{invoice_number}}' => $invoiceNumber,
                '{{invoice_title}}' => $invoiceTitle,
                '{{project_name}}' => $projectName,
                '{{amount_total_ttc}}' => number_format($amountTtc, 2, ',', ' ') . ' €',
                '{{due_date}}' => $dueFr,
                '{{invoice_link}}' => $invoicePayUrl,
            ];
            $subject = strtr($subjectTpl, $repl);
            $body = strtr($bodyTpl, $repl);

            $delivery->sendQuoteEmail(
                companyId: $userContext->companyId,
                toEmail: $toEmail,
                subject: $subject,
                bodyText: $body,
                pdfContent: $pdfContent,
                pdfFileName: 'facture-' . $invoiceId . '.pdf'
            );
        } catch (\Throwable) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Renvoi%20facture%20impossible');
        }

        Csrf::rotate();
        return Response::redirect('projects/show?projectId=' . $projectId . '&msg=Facture%20renvoy%C3%A9e%20au%20client');
    }

    /**
     * Paiement manuel (virement, espèces) depuis la fiche affaire.
     */
    public function recordManualPaymentFromProject(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('invoice.mark_paid', $userContext->permissions, true)) {
            return Response::redirect('clients?err=Permissions%20insuffisantes');
        }
        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('clients?err=CSRF%20invalide');
        }
        $projectId = (int) $request->getBodyParam('project_id', 0);
        $invoiceId = (int) $request->getBodyParam('invoice_id', 0);
        $amountRaw = str_replace(',', '.', trim((string) $request->getBodyParam('amount', '0')));
        $amount = is_numeric($amountRaw) ? (float) $amountRaw : 0.0;

        if ($invoiceId <= 0) {
            return Response::redirect($projectId > 0 ? ('projects/show?projectId=' . $projectId . '&err=Paiement%20invalide') : 'invoices?err=Paiement%20invalide');
        }

        $repoInvoices = new InvoiceRepository();
        $invoice = $repoInvoices->findByCompanyIdAndId($userContext->companyId, $invoiceId);
        if (!is_array($invoice)) {
            return Response::redirect($projectId > 0 ? ('projects/show?projectId=' . $projectId . '&err=Facture%20introuvable') : 'invoices?err=Facture%20introuvable');
        }
        if ($projectId > 0 && !$repoInvoices->invoiceBelongsToProject($userContext->companyId, $invoiceId, $projectId)) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Facture%20invalide%20pour%20cette%20affaire');
        }

        $result = $repoInvoices->recordManualPayment($userContext->companyId, $invoiceId, $amount);
        if (empty($result['ok'])) {
            $err = match ($result['error'] ?? '') {
                'invalid_amount' => 'Montant%20invalide%20ou%20sup%C3%A9rieur%20au%20reste%20%C3%A0%20payer',
                'already_paid' => 'Facture%20d%C3%A9j%C3%A0%20sold%C3%A9e',
                'cancelled' => 'Facture%20annul%C3%A9e',
                default => 'Paiement%20impossible',
            };

            return Response::redirect($projectId > 0 ? ('projects/show?projectId=' . $projectId . '&err=' . $err) : ('invoices?err=' . $err));
        }

        InvoicePaidReceiptEmailService::notifyIfManualPaymentBecamePaid(
            $userContext->companyId,
            $invoiceId,
            $result
        );

        Csrf::rotate();

        if ($projectId > 0) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&msg=Paiement%20enregistr%C3%A9');
        }

        return Response::redirect('invoices?msg=Paiement%20enregistr%C3%A9');
    }

    private static function invoicePayAbsoluteUrl(string $token): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        $basePath = ($basePath === '.' || $basePath === '\\') ? '' : $basePath;

        return $scheme . '://' . $host . $basePath . '/invoice/pay?token=' . urlencode($token);
    }
}

