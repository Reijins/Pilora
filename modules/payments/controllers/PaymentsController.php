<?php
declare(strict_types=1);

namespace Modules\Payments\Controllers;

use App\Controllers\BaseController;
use Core\Context\UserContext;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\Csrf;
use Modules\Invoices\Repositories\InvoiceRepository;
use Modules\Payments\Services\PaymentService;
use Modules\Payments\Repositories\PaymentRepository;

final class PaymentsController extends BaseController
{
    public function new(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('invoice.mark_paid', $userContext->permissions, true)) {
            return Response::redirect('invoices');
        }

        $invoiceIdRaw = $request->getQueryParam('invoiceId', null);
        $invoiceId = is_numeric($invoiceIdRaw) ? (int) $invoiceIdRaw : 0;
        if ($invoiceId <= 0) {
            return Response::redirect('invoices');
        }

        $repoInvoices = new InvoiceRepository();
        $invoice = null;
        try {
            $invoice = $repoInvoices->findByCompanyIdAndId($userContext->companyId, $invoiceId);
        } catch (\Throwable) {
            $invoice = null;
        }

        if ($invoice === null) {
            return Response::redirect('invoices?err=Facture%20introuvable');
        }
        if ((string) ($invoice['status'] ?? '') === 'annulee') {
            return Response::redirect('invoices?err=Facture%20annul%C3%A9e');
        }

        $total = (float) ($invoice['amountTotal'] ?? 0);
        $paid = (float) ($invoice['amountPaid'] ?? 0);
        $remaining = (float) round(max(0, $total - $paid), 2);

        return $this->renderPage('payments/new.php', [
            'pageTitle' => 'Paiement',
            'permissionDenied' => false,
            'csrfToken' => Csrf::token(),
            'invoiceId' => $invoiceId,
            'invoiceNumber' => (string) ($invoice['invoiceNumber'] ?? ''),
            'remaining' => $remaining,
            'amountTotal' => $total,
        ]);
    }

    public function create(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('invoice.mark_paid', $userContext->permissions, true)) {
            return Response::redirect('invoices');
        }

        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('invoices?err=CSRF%20invalide');
        }

        $invoiceIdRaw = $request->getBodyParam('invoice_id', null);
        $invoiceId = is_numeric($invoiceIdRaw) ? (int) $invoiceIdRaw : 0;
        if ($invoiceId <= 0) {
            return Response::redirect('invoices?err=Facture%20invalide');
        }

        $amountRaw = $request->getBodyParam('amount', null);
        $amount = is_numeric($amountRaw) ? (float) $amountRaw : 0.0;

        $provider = trim((string) $request->getBodyParam('provider', 'Manuel'));
        $reference = trim((string) $request->getBodyParam('reference', ''));
        $reference = $reference !== '' ? $reference : null;

        if ($amount <= 0) {
            return Response::redirect('payments/new?invoiceId=' . $invoiceId . '&err=Montant%20invalide');
        }

        $invCheck = (new InvoiceRepository())->findByCompanyIdAndId($userContext->companyId, $invoiceId);
        if (!is_array($invCheck) || (string) ($invCheck['status'] ?? '') === 'annulee') {
            return Response::redirect('invoices?err=Facture%20annul%C3%A9e');
        }

        $service = new PaymentService(
            invoiceRepository: new InvoiceRepository(),
            paymentRepository: new PaymentRepository(),
        );

        try {
            $service->registerSucceededPaymentAndUpdateInvoice(
                companyId: $userContext->companyId,
                invoiceId: $invoiceId,
                amount: $amount,
                provider: $provider !== '' ? $provider : 'Manuel',
                reference: $reference,
            );
        } catch (\Throwable) {
            return Response::redirect('payments/new?invoiceId=' . $invoiceId . '&err=Paiement%20impossible');
        }

        Csrf::rotate();
        return Response::redirect('invoices?msg=Paiement%20enregistr%C3%A9');
    }
}

