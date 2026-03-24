<?php
declare(strict_types=1);

namespace Modules\Invoices\Services;

use Core\Support\DateFormatter;
use Modules\Clients\Repositories\ClientRepository;
use Modules\Companies\Repositories\CompanyRepository;
use Modules\Invoices\Repositories\InvoiceRepository;
use Modules\Projects\Repositories\ProjectRepository;
use Modules\Quotes\Repositories\QuoteRepository;
use Modules\Quotes\Services\QuoteDeliveryService;
use Modules\Settings\Repositories\SmtpSettingsRepository;

/**
 * Email de confirmation de réception du paiement (facture passée en « payée »).
 */
final class InvoicePaidReceiptEmailService
{
    /**
     * @param array{updated?: bool, becamePaid?: bool} $markResult
     */
    public static function notifyIfBecamePaid(int $companyId, int $invoiceId, array $markResult): void
    {
        if (empty($markResult['becamePaid'])) {
            return;
        }
        self::send($companyId, $invoiceId);
    }

    public static function notifyIfManualPaymentBecamePaid(int $companyId, int $invoiceId, array $result): void
    {
        if (empty($result['ok']) || empty($result['becamePaid'])) {
            return;
        }
        self::send($companyId, $invoiceId);
    }

    public static function send(int $companyId, int $invoiceId): void
    {
        $repo = new InvoiceRepository();
        $invoice = $repo->findByCompanyIdAndId($companyId, $invoiceId);
        if (!is_array($invoice) || (string) ($invoice['status'] ?? '') !== 'payee') {
            return;
        }

        $smtp = (new SmtpSettingsRepository())->getByCompanyId($companyId);
        $subjectTpl = trim((string) ($smtp['invoice_paid_email_subject'] ?? ''));
        $bodyTpl = trim((string) ($smtp['invoice_paid_email_body'] ?? ''));
        if ($subjectTpl === '' || $bodyTpl === '') {
            return;
        }

        $client = (new ClientRepository())->findByCompanyIdAndId($companyId, (int) ($invoice['clientId'] ?? 0));
        if (!is_array($client)) {
            return;
        }
        $toEmail = trim((string) ($client['email'] ?? ''));
        if ($toEmail === '' || filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        $identity = (new CompanyRepository())->getDocumentIdentity($companyId, $smtp);
        $companyName = (string) $identity['name'];
        $clientName = (string) (($client['name'] ?? '') !== '' ? $client['name'] : 'Client');
        $invoiceNumber = (string) ($invoice['invoiceNumber'] ?? ('FA-' . $invoiceId));
        $invoiceTitle = (string) ($invoice['title'] ?? '');
        $quoteId = (int) ($invoice['quoteId'] ?? 0);
        $totals = InvoiceAmountsService::displayTotalsForInvoice($companyId, $invoice);
        $amountTtc = round((float) $totals['ttc'], 2);
        $amountPaid = round((float) ($invoice['amountPaid'] ?? 0), 2);
        $remaining = InvoiceAmountsService::remainingTtc($companyId, $invoice);
        $paidAt = (string) ($invoice['paidAt'] ?? '');
        $paidFr = $paidAt !== '' ? DateFormatter::frDateTime($paidAt) : DateFormatter::frDateTime((new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'));

        $projectName = '—';
        if ($quoteId > 0) {
            $q = (new QuoteRepository())->findByCompanyIdAndId($companyId, $quoteId);
            if (is_array($q)) {
                $pid = (int) ($q['projectId'] ?? 0);
                if ($pid > 0) {
                    $p = (new ProjectRepository())->findByCompanyIdAndId($companyId, $pid);
                    if (is_array($p)) {
                        $projectName = (string) (($p['name'] ?? '') !== '' ? $p['name'] : '—');
                    }
                }
            }
        }

        $legal = $companyName;
        $payToken = $repo->ensurePaymentToken($companyId, $invoiceId);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        $basePath = ($basePath === '.' || $basePath === '\\') ? '' : $basePath;
        $invoiceLink = $scheme . '://' . $host . $basePath . '/invoice/pay?token=' . urlencode($payToken);

        $repl = [
            '{{company_name}}' => $companyName,
            '{{legal_name}}' => $legal,
            '{{client_name}}' => $clientName,
            '{{invoice_number}}' => $invoiceNumber,
            '{{invoice_title}}' => $invoiceTitle,
            '{{project_name}}' => $projectName,
            '{{amount_total_ttc}}' => number_format($amountTtc, 2, ',', ' ') . ' €',
            '{{amount_paid}}' => number_format($amountPaid, 2, ',', ' ') . ' €',
            '{{remaining}}' => number_format(max(0, $remaining), 2, ',', ' ') . ' €',
            '{{payment_date}}' => $paidFr,
            '{{invoice_link}}' => $invoiceLink,
        ];
        $subject = strtr($subjectTpl, $repl);
        $body = strtr($bodyTpl, $repl);

        try {
            (new QuoteDeliveryService())->sendTestEmail($companyId, $toEmail, $subject, $body);
        } catch (\Throwable) {
            // ne bloque pas le flux métier
        }
    }
}
