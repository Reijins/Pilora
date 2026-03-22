<?php
declare(strict_types=1);

namespace Modules\Invoices\Controllers;

use App\Controllers\BaseController;
use Core\Context\UserContext;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\Csrf;
use Core\Support\DateFormatter;
use Core\View\View;
use Modules\Clients\Repositories\ClientRepository;
use Modules\Companies\Repositories\CompanyRepository;
use Modules\Contacts\Repositories\ContactRepository;
use Modules\Invoices\Repositories\InvoiceRepository;
use Modules\Invoices\Services\InvoiceAmountsService;
use Modules\Invoices\Services\InvoicePaidReceiptEmailService;
use Modules\Projects\Repositories\ProjectRepository;
use Modules\Quotes\Repositories\QuoteRepository;
use Modules\Quotes\Services\QuoteDeliveryService;
use Modules\Settings\Repositories\SmtpSettingsRepository;

final class InvoicesController extends BaseController
{
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

        return $this->renderPage('invoices/index.php', [
            'pageTitle' => 'Factures',
            'permissionDenied' => false,
            'invoices' => $invoices,
            'statusLabels' => $statusLabels,
            'statusFilter' => $statusFilter,
            'canMarkPaid' => $canMarkPaid,
            'canExport' => $canExport,
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
        $quoteId = (int) ($invoice['quoteId'] ?? 0);
        if ($quoteId <= 0) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Devis%20de%20facture%20introuvable');
        }

        try {
            $quoteRepo = new QuoteRepository();
            $quote = $quoteRepo->findByCompanyIdAndId($userContext->companyId, $quoteId);
            $project = (new ProjectRepository())->findByCompanyIdAndId($userContext->companyId, $projectId);
            $client = (new ClientRepository())->findByCompanyIdAndId($userContext->companyId, (int) ($invoice['clientId'] ?? 0));
            if (!is_array($quote) || !is_array($project) || !is_array($client)) {
                return Response::redirect('projects/show?projectId=' . $projectId . '&err=Donn%C3%A9es%20de%20facture%20incompl%C3%A8tes');
            }
            $toEmail = trim((string) ($client['email'] ?? ''));
            if ($toEmail === '' || filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
                return Response::redirect('projects/show?projectId=' . $projectId . '&err=Email%20client%20invalide');
            }

            $items = $quoteRepo->listItemsByCompanyIdAndQuoteId($userContext->companyId, $quoteId);
            $smtp = (new SmtpSettingsRepository())->getByCompanyId($userContext->companyId);
            $companyIdentity = (new CompanyRepository())->getDocumentIdentity($userContext->companyId, $smtp);
            $companyName = (string) $companyIdentity['name'];
            $totals = InvoiceAmountsService::fromQuote($userContext->companyId, $quoteId);
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

        if (!in_array($invStatus, ['envoyee', 'partiellement_payee', 'echue'], true)) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Renvoi%20non%20autoris%C3%A9');
        }

        $quoteId = (int) ($invoice['quoteId'] ?? 0);
        if ($quoteId <= 0) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Devis%20de%20facture%20introuvable');
        }

        try {
            $quoteRepo = new QuoteRepository();
            $quote = $quoteRepo->findByCompanyIdAndId($userContext->companyId, $quoteId);
            $project = (new ProjectRepository())->findByCompanyIdAndId($userContext->companyId, $projectId);
            $client = (new ClientRepository())->findByCompanyIdAndId($userContext->companyId, (int) ($invoice['clientId'] ?? 0));
            if (!is_array($quote) || !is_array($project) || !is_array($client)) {
                return Response::redirect('projects/show?projectId=' . $projectId . '&err=Donn%C3%A9es%20de%20facture%20incompl%C3%A8tes');
            }
            $toEmail = trim((string) ($client['email'] ?? ''));
            if ($toEmail === '' || filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
                return Response::redirect('projects/show?projectId=' . $projectId . '&err=Email%20client%20invalide');
            }

            $items = $quoteRepo->listItemsByCompanyIdAndQuoteId($userContext->companyId, $quoteId);
            $smtp = (new SmtpSettingsRepository())->getByCompanyId($userContext->companyId);
            $companyIdentity = (new CompanyRepository())->getDocumentIdentity($userContext->companyId, $smtp);
            $companyName = (string) $companyIdentity['name'];
            $totals = InvoiceAmountsService::fromQuote($userContext->companyId, $quoteId);
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

        if ($projectId <= 0 || $invoiceId <= 0) {
            return Response::redirect('projects/show?projectId=' . max(0, $projectId) . '&err=Paiement%20invalide');
        }

        $repoInvoices = new InvoiceRepository();
        $invoice = $repoInvoices->findByCompanyIdAndId($userContext->companyId, $invoiceId);
        if (!is_array($invoice)) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Facture%20introuvable');
        }
        $quoteId = (int) ($invoice['quoteId'] ?? 0);
        if ($quoteId <= 0) {
            return Response::redirect('projects/show?projectId=' . $projectId . '&err=Facture%20sans%20devis');
        }
        $quote = (new QuoteRepository())->findByCompanyIdAndId($userContext->companyId, $quoteId);
        if (!is_array($quote) || (int) ($quote['projectId'] ?? 0) !== $projectId) {
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

            return Response::redirect('projects/show?projectId=' . $projectId . '&err=' . $err);
        }

        InvoicePaidReceiptEmailService::notifyIfManualPaymentBecamePaid(
            $userContext->companyId,
            $invoiceId,
            $result
        );

        Csrf::rotate();

        return Response::redirect('projects/show?projectId=' . $projectId . '&msg=Paiement%20enregistr%C3%A9');
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

