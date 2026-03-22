<?php
declare(strict_types=1);

namespace Modules\Invoices\Controllers;

use App\Controllers\BaseController;
use Core\Config;
use Core\Context\UserContext;
use Core\Http\Request;
use Core\Http\Response;
use Core\View\View;
use Modules\Clients\Repositories\ClientRepository;
use Modules\Companies\Repositories\CompanyRepository;
use Modules\Contacts\Repositories\ContactRepository;
use Modules\Invoices\Repositories\InvoiceRepository;
use Modules\Invoices\Services\InvoiceAmountsService;
use Modules\Invoices\Services\InvoicePaidReceiptEmailService;
use Modules\Projects\Repositories\ProjectRepository;
use Modules\Quotes\Repositories\QuoteRepository;
use Modules\Settings\Repositories\SmtpSettingsRepository;

final class PublicInvoiceController extends BaseController
{
    public function pay(Request $request, UserContext $userContext): Response
    {
        $token = trim((string) $request->getQueryParam('token', ''));
        $sessionId = trim((string) $request->getQueryParam('session_id', ''));
        if ($sessionId === '' && isset($_GET['session_id'])) {
            $sessionId = trim((string) $_GET['session_id']);
        }
        $cancelled = (string) $request->getQueryParam('cancelled', '') === '1';

        if ($token === '') {
            return new Response('Lien invalide', 404);
        }

        $repo = new InvoiceRepository();
        $invoice = $repo->findByPaymentToken($token);
        if (!is_array($invoice)) {
            return new Response('Facture introuvable', 404);
        }

        $companyId = (int) ($invoice['companyId'] ?? 0);
        $invoiceId = (int) ($invoice['id'] ?? 0);
        $quoteId = (int) ($invoice['quoteId'] ?? 0);

        $smtpRepo = new SmtpSettingsRepository();
        $smtp = $smtpRepo->getByCompanyId($companyId);
        $stripeSecretForSession = $smtpRepo->getStripeSecretKey($companyId);

        if ($sessionId !== '' && $stripeSecretForSession !== '') {
            try {
                \Stripe\Stripe::setApiKey($stripeSecretForSession);
                $session = \Stripe\Checkout\Session::retrieve(
                    $sessionId,
                    ['expand' => ['payment_intent']]
                );
                $marked = self::markInvoicePaidFromStripeCheckoutSession(
                    session: $session,
                    companyId: $companyId,
                    invoice: $invoice,
                    repo: $repo
                );
                if ($marked) {
                    $invoice = $repo->findByPaymentToken($token) ?? $invoice;
                } elseif (Config::isDebug()) {
                    self::logStripePayDebug(
                        'Synchronisation Stripe sans mise à jour (voir client_reference_id / session id / metadata)',
                        $session
                    );
                }
            } catch (\Throwable $e) {
                self::logStripePayFailure('Stripe Session::retrieve ou sync: ' . $e->getMessage());
            }
        } elseif ($sessionId !== '' && $stripeSecretForSession === '') {
            self::logStripePayFailure('session_id présent mais clé secrète Stripe indisponible (société ' . $companyId . ', paiement en ligne désactivé ou clé vide).');
        }

        $quoteRepo = new QuoteRepository();
        $items = $quoteId > 0
            ? $quoteRepo->listItemsByCompanyIdAndQuoteId($companyId, $quoteId)
            : [];
        $client = (new ClientRepository())->findByCompanyIdAndId($companyId, (int) ($invoice['clientId'] ?? 0));
        $contacts = (new ContactRepository())->listByCompanyIdAndClientId($companyId, (int) ($invoice['clientId'] ?? 0));
        $totals = $quoteId > 0
            ? InvoiceAmountsService::fromQuote($companyId, $quoteId)
            : ['ht' => 0.0, 'vat_rate' => 20.0, 'vat_amount' => 0.0, 'ttc' => (float) ($invoice['amountTotal'] ?? 0)];

        $project = null;
        if ($quoteId > 0) {
            $q = $quoteRepo->findByCompanyIdAndId($companyId, $quoteId);
            $pid = is_array($q) && is_numeric($q['projectId'] ?? null) ? (int) $q['projectId'] : 0;
            if ($pid > 0) {
                $project = (new ProjectRepository())->findByCompanyIdAndId($companyId, $pid);
            }
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        $basePath = ($basePath === '.' || $basePath === '\\') ? '' : $basePath;
        $publicBase = $scheme . '://' . $host . $basePath;

        $stripeOk = $smtpRepo->isStripeOnlinePaymentReady($companyId);
        $amountRemaining = InvoiceAmountsService::remainingTtc($companyId, $invoice);
        $invStatus = (string) ($invoice['status'] ?? '');
        $canPay = $stripeOk
            && in_array($invStatus, ['brouillon', 'envoyee', 'echue', 'partiellement_payee'], true)
            && $amountRemaining > 0.009;

        $viewsRoot = dirname(__DIR__, 3) . '/app/views';
        $companyIdentity = (new CompanyRepository())->getDocumentIdentity($companyId, $smtp);
        $html = View::render($viewsRoot . '/invoices/public_pay.php', [
            'invoice' => $invoice,
            'items' => $items,
            'client' => $client ?? [],
            'contacts' => $contacts,
            'project' => $project,
            'company' => $companyIdentity,
            'smtp' => $smtp,
            'totals' => $totals,
            'token' => $token,
            'publicBase' => $publicBase,
            'stripeOk' => $stripeOk,
            'canPay' => $canPay,
            'amountRemaining' => $amountRemaining,
            'cancelled' => $cancelled,
            'paidJustNow' => $sessionId !== '' && (string) ($invoice['status'] ?? '') === 'payee',
        ]);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function stripeCheckout(Request $request, UserContext $userContext): Response
    {
        $token = trim((string) $request->getBodyParam('token', ''));
        if ($token === '') {
            return Response::redirect('invoice/pay?err=token');
        }

        $repo = new InvoiceRepository();
        $invoice = $repo->findByPaymentToken($token);
        if (!is_array($invoice)) {
            return Response::redirect('invoice/pay?err=facture');
        }

        $companyId = (int) ($invoice['companyId'] ?? 0);
        $invoiceId = (int) ($invoice['id'] ?? 0);

        $smtpRepo = new SmtpSettingsRepository();
        $secret = $smtpRepo->getStripeSecretKey($companyId);
        if ($secret === '') {
            return Response::redirect('invoice/pay?token=' . urlencode($token) . '&err=stripe');
        }

        $status = (string) ($invoice['status'] ?? '');
        if (in_array($status, ['payee', 'annulee'], true)) {
            return Response::redirect('invoice/pay?token=' . urlencode($token));
        }

        $amountRemaining = InvoiceAmountsService::remainingTtc($companyId, $invoice);
        if ($amountRemaining < 0.01) {
            return Response::redirect('invoice/pay?token=' . urlencode($token));
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        $basePath = ($basePath === '.' || $basePath === '\\') ? '' : $basePath;
        $publicBase = $scheme . '://' . $host . $basePath;

        try {
            \Stripe\Stripe::setApiKey($secret);
            $invNo = (string) ($invoice['invoiceNumber'] ?? ('FA-' . $invoiceId));
            $quoteId = (int) ($invoice['quoteId'] ?? 0);
            $quoteItems = [];
            if ($quoteId > 0) {
                try {
                    $quoteItems = (new QuoteRepository())->listItemsByCompanyIdAndQuoteId($companyId, $quoteId);
                } catch (\Throwable) {
                    $quoteItems = [];
                }
            }

            $lineItems = self::buildStripeCheckoutLineItems(
                invoiceNumberLabel: $invNo,
                amountRemaining: $amountRemaining,
                companyId: $companyId,
                quoteId: $quoteId,
                quoteItems: $quoteItems,
            );

            $sessionParams = [
                'mode' => 'payment',
                'client_reference_id' => (string) $invoiceId,
                'metadata' => [
                    'invoice_id' => (string) $invoiceId,
                    'company_id' => (string) $companyId,
                ],
                'line_items' => $lineItems,
                'success_url' => $publicBase . '/invoice/pay?token=' . rawurlencode($token) . '&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $publicBase . '/invoice/pay?token=' . rawurlencode($token) . '&cancelled=1',
            ];

            $customMsg = self::stripeCheckoutCustomSubmitMessage(
                companyId: $companyId,
                quoteId: $quoteId,
                amountRemaining: $amountRemaining,
            );
            if ($customMsg !== '') {
                $sessionParams['custom_text'] = [
                    'submit' => [
                        'message' => $customMsg,
                    ],
                ];
            }

            $client = (new ClientRepository())->findByCompanyIdAndId($companyId, (int) ($invoice['clientId'] ?? 0));
            if (is_array($client)) {
                $custEmail = trim((string) ($client['email'] ?? ''));
                if ($custEmail !== '' && filter_var($custEmail, FILTER_VALIDATE_EMAIL)) {
                    $sessionParams['customer_email'] = $custEmail;
                }
            }

            $session = \Stripe\Checkout\Session::create($sessionParams);
            $sid = (string) ($session->id ?? '');
            if ($sid !== '') {
                $repo->saveStripeCheckoutSessionId($companyId, $invoiceId, $sid);
            }
            $url = (string) ($session->url ?? '');
            if ($url !== '') {
                return Response::redirect($url);
            }
        } catch (\Throwable) {
            return Response::redirect('invoice/pay?token=' . urlencode($token) . '&err=stripe_session');
        }

        return Response::redirect('invoice/pay?token=' . urlencode($token) . '&err=stripe_session');
    }

    /**
     * Lignes Stripe Checkout : une ligne par prestation (HT au prorata) + ligne TVA ; somme = montant TTC débité.
     * Si trop de lignes (>99 prestations), repli sur total HT + TVA.
     *
     * @param array<int, array<string, mixed>> $quoteItems
     * @return array<int, array<string, mixed>>
     */
    private static function buildStripeCheckoutLineItems(
        string $invoiceNumberLabel,
        float $amountRemaining,
        int $companyId,
        int $quoteId,
        array $quoteItems,
    ): array {
        $targetCents = (int) round($amountRemaining * 100);
        if ($targetCents < 1) {
            return [];
        }

        $fallback = [[
            'price_data' => [
                'currency' => 'eur',
                'product_data' => [
                    'name' => 'Montant TTC — Facture ' . $invoiceNumberLabel,
                    'description' => 'Total à régler (toutes taxes comprises)',
                ],
                'unit_amount' => $targetCents,
            ],
            'quantity' => 1,
        ]];

        if ($quoteId <= 0 || $quoteItems === []) {
            return $fallback;
        }

        try {
            $totals = InvoiceAmountsService::fromQuote($companyId, $quoteId);
        } catch (\Throwable) {
            return $fallback;
        }

        $ttc = (float) $totals['ttc'];
        if ($ttc < 0.0001) {
            return $fallback;
        }

        $ratio = $amountRemaining / $ttc;
        if ($ratio > 1.0001) {
            $ratio = 1.0;
        }

        $vatRateStr = number_format((float) $totals['vat_rate'], 2, ',', ' ');
        $prorataSuffix = $ratio < 0.999 ? ' (au prorata du solde)' : '';

        // Trop de lignes pour Stripe Checkout (max 100)
        if (count($quoteItems) > 99) {
            return self::buildStripeLineItemsHtVatSummary(
                invoiceNumberLabel: $invoiceNumberLabel,
                targetCents: $targetCents,
                totals: $totals,
                ratio: $ratio,
                vatRateStr: $vatRateStr,
                prorataSuffix: $prorataSuffix,
                fallback: $fallback,
            );
        }

        /** @var array<int, array{name:string, cents:int}> $htRows */
        $htRows = [];
        foreach ($quoteItems as $it) {
            $desc = trim((string) ($it['description'] ?? ''));
            if ($desc === '') {
                $desc = 'Prestation';
            }
            if (function_exists('mb_substr')) {
                $desc = (string) mb_substr($desc, 0, 120);
            } else {
                $desc = substr($desc, 0, 120);
            }
            $lineHt = (float) ($it['lineTotal'] ?? 0);
            $htPortion = round($lineHt * $ratio, 2);
            $cents = (int) round($htPortion * 100);
            if ($cents >= 1) {
                $htRows[] = ['name' => $desc, 'cents' => $cents];
            }
        }

        if ($htRows === []) {
            return $fallback;
        }

        $sumHt = 0;
        foreach ($htRows as $r) {
            $sumHt += $r['cents'];
        }
        $vatCents = $targetCents - $sumHt;

        // Ajustement si arrondis HT > cible
        while ($vatCents < 0 && $htRows !== []) {
            $last = count($htRows) - 1;
            $dec = min($htRows[$last]['cents'], -$vatCents);
            if ($dec < 1) {
                break;
            }
            $htRows[$last]['cents'] -= $dec;
            $vatCents += $dec;
            if ($htRows[$last]['cents'] < 1) {
                array_pop($htRows);
            }
        }

        if ($vatCents < 0) {
            return $fallback;
        }

        $sumHt = 0;
        foreach ($htRows as $r) {
            $sumHt += $r['cents'];
        }
        $vatCents = $targetCents - $sumHt;
        if ($vatCents < 0) {
            return $fallback;
        }

        $out = [];
        foreach ($htRows as $r) {
            if ($r['cents'] < 1) {
                continue;
            }
            $out[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => $r['name'],
                        'description' => 'Montant HT' . $prorataSuffix,
                    ],
                    'unit_amount' => $r['cents'],
                ],
                'quantity' => 1,
            ];
        }

        if ($vatCents >= 1) {
            $out[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => 'TVA (' . $vatRateStr . ' %)',
                        'description' => 'Montant TVA' . $prorataSuffix,
                    ],
                    'unit_amount' => $vatCents,
                ],
                'quantity' => 1,
            ];
        }

        $sum = 0;
        foreach ($out as $row) {
            $sum += (int) ($row['price_data']['unit_amount'] ?? 0);
        }
        if ($sum !== $targetCents || count($out) > 100) {
            return $fallback;
        }

        return $out !== [] ? $out : $fallback;
    }

    /**
     * @param array{ht:float,vat_rate:float,vat_amount:float,ttc:float} $totals
     * @param array<int, array<string, mixed>> $fallback
     * @return array<int, array<string, mixed>>
     */
    private static function buildStripeLineItemsHtVatSummary(
        string $invoiceNumberLabel,
        int $targetCents,
        array $totals,
        float $ratio,
        string $vatRateStr,
        string $prorataSuffix,
        array $fallback,
    ): array {
        $htPart = round((float) $totals['ht'] * $ratio, 2);
        $vatPart = round((float) $totals['vat_amount'] * $ratio, 2);
        $htCents = (int) round($htPart * 100);
        $vatCents = (int) round($vatPart * 100);
        $diff = $targetCents - $htCents - $vatCents;
        $vatCents += $diff;
        if ($vatCents < 0) {
            $htCents += $vatCents;
            $vatCents = 0;
        }
        if ($htCents < 0) {
            $vatCents += $htCents;
            $htCents = 0;
        }
        if ($htCents + $vatCents !== $targetCents) {
            return $fallback;
        }

        $lines = [];
        if ($htCents >= 1) {
            $lines[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => 'Total HT' . $prorataSuffix,
                        'description' => 'Somme des prestations (hors taxes)',
                    ],
                    'unit_amount' => $htCents,
                ],
                'quantity' => 1,
            ];
        }
        if ($vatCents >= 1) {
            $lines[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => 'TVA (' . $vatRateStr . ' %)' . $prorataSuffix,
                    ],
                    'unit_amount' => $vatCents,
                ],
                'quantity' => 1,
            ];
        }

        return count($lines) >= 1 ? $lines : $fallback;
    }

    /** Texte d’aide sur la page Stripe Checkout (récap HT / TVA / TTC). */
    private static function stripeCheckoutCustomSubmitMessage(
        int $companyId,
        int $quoteId,
        float $amountRemaining,
    ): string {
        if ($quoteId <= 0) {
            return 'Le montant débité correspond au total TTC (somme des lignes).';
        }
        try {
            $totals = InvoiceAmountsService::fromQuote($companyId, $quoteId);
        } catch (\Throwable) {
            return '';
        }
        $ttc = (float) $totals['ttc'];
        if ($ttc < 0.0001) {
            return '';
        }
        $ratio = min(1.0, $amountRemaining / $ttc);
        $ht = number_format(round((float) $totals['ht'] * $ratio, 2), 2, ',', ' ');
        $vat = number_format(round((float) $totals['vat_amount'] * $ratio, 2), 2, ',', ' ');
        $ttcStr = number_format(round($amountRemaining, 2), 2, ',', ' ');

        return 'Total HT ' . $ht . ' € + TVA ' . $vat . ' € = TTC ' . $ttcStr . ' €. Ce montant TTC est débité.';
    }

    /**
     * Webhook Stripe : confirme le paiement même si le client ne revient pas sur la page de succès.
     * URL : POST /webhooks/stripe?company_id=VOTRE_ID_SOCIÉTÉ
     */
    public function stripeWebhook(Request $request, UserContext $userContext): Response
    {
        $companyId = (int) $request->getQueryParam('company_id', 0);
        if ($companyId <= 0) {
            return new Response('Bad Request', 400);
        }

        $whSecret = trim((string) ((new SmtpSettingsRepository())->getByCompanyId($companyId)['stripe_webhook_secret'] ?? ''));
        if ($whSecret === '') {
            return new Response('Webhook non configuré', 400);
        }

        $payload = $request->getRawBody();
        $sigHeader = (string) $request->getHeader('Stripe-Signature');
        if ($sigHeader === '' || $payload === '') {
            return new Response('Bad Request', 400);
        }

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $whSecret);
        } catch (\Throwable) {
            return new Response('Invalid signature', 400);
        }

        if ($event->type !== 'checkout.session.completed') {
            return new Response('', 200);
        }

        $session = $event->data->object;
        if (!is_object($session)) {
            return new Response('', 200);
        }

        if (!self::isStripeCheckoutSessionPaid($session)) {
            return new Response('', 200);
        }

        $repo = new InvoiceRepository();
        $stripeSid = (string) ($session->id ?? '');

        if ($stripeSid !== '') {
            $invBySid = $repo->findByCompanyIdAndStripeCheckoutSessionId($companyId, $stripeSid);
            if (is_array($invBySid) && (int) ($invBySid['id'] ?? 0) > 0) {
                $iid = (int) $invBySid['id'];
                $res = $repo->markAsPaidInFull($companyId, $iid);
                InvoicePaidReceiptEmailService::notifyIfBecamePaid($companyId, $iid, $res);

                return new Response('OK', 200);
            }
        }

        $refInv = (int) ($session->client_reference_id ?? 0);
        if ($refInv > 0) {
            $res = $repo->markAsPaidInFull($companyId, $refInv);
            InvoicePaidReceiptEmailService::notifyIfBecamePaid($companyId, $refInv, $res);

            return new Response('OK', 200);
        }

        $md = self::stripeSessionMetadataToArray($session);

        $invId = (int) ($md['invoice_id'] ?? 0);
        if ($invId <= 0 && isset($session->client_reference_id) && (string) $session->client_reference_id !== '') {
            $invId = (int) $session->client_reference_id;
        }
        if ($invId <= 0 && isset($session->metadata['invoice_id'])) {
            $invId = (int) $session->metadata['invoice_id'];
        }

        $metaCompany = (int) ($md['company_id'] ?? 0);
        if ($invId <= 0 || ($metaCompany > 0 && $metaCompany !== $companyId)) {
            return new Response('', 200);
        }

        $res = $repo->markAsPaidInFull($companyId, $invId);
        InvoicePaidReceiptEmailService::notifyIfBecamePaid($companyId, $invId, $res);

        return new Response('OK', 200);
    }

    /**
     * Marque la facture payée si la session Checkout est bien payée et correspond à la facture (lien session enregistré ou métadonnées).
     */
    private static function markInvoicePaidFromStripeCheckoutSession(
        object $session,
        int $companyId,
        array $invoice,
        InvoiceRepository $repo,
    ): bool {
        if (!self::isStripeCheckoutSessionPaid($session)) {
            return false;
        }

        $expectedInvoiceId = (int) ($invoice['id'] ?? 0);
        if ($expectedInvoiceId <= 0) {
            return false;
        }

        // 1) Référence posée à la création de la session (prioritaire — ne dépend pas des métadonnées ni du UPDATE en base).
        $ref = trim((string) ($session->client_reference_id ?? ''));
        if ($ref !== '' && (int) $ref === $expectedInvoiceId) {
            $res = $repo->markAsPaidInFull($companyId, $expectedInvoiceId);
            InvoicePaidReceiptEmailService::notifyIfBecamePaid($companyId, $expectedInvoiceId, $res);

            return $res['updated'];
        }

        // 2) ID de session enregistré sur la facture au moment du Session::create.
        $retrievedSid = (string) ($session->id ?? '');
        $savedSid = trim((string) ($invoice['stripeCheckoutSessionId'] ?? ''));
        if ($savedSid !== '' && $retrievedSid !== '' && $savedSid === $retrievedSid) {
            $res = $repo->markAsPaidInFull($companyId, $expectedInvoiceId);
            InvoicePaidReceiptEmailService::notifyIfBecamePaid($companyId, $expectedInvoiceId, $res);

            return $res['updated'];
        }

        // 3) Métadonnées (si invoice_id est renseigné mais ne correspond pas au token, on refuse : évite une fausse concordance).
        $md = self::stripeSessionMetadataToArray($session);
        $invFromMeta = (int) ($md['invoice_id'] ?? 0);
        if ($invFromMeta > 0 && $invFromMeta !== $expectedInvoiceId) {
            return false;
        }

        $invId = $invFromMeta;
        if ($invId <= 0 && $ref !== '') {
            $invId = (int) $ref;
        }
        if ($invId <= 0 && isset($session->metadata['invoice_id'])) {
            $invId = (int) $session->metadata['invoice_id'];
        }

        if ($invId <= 0 || $invId !== $expectedInvoiceId) {
            return false;
        }

        $metaCompany = (int) ($md['company_id'] ?? 0);
        if ($metaCompany > 0 && $metaCompany !== $companyId) {
            return false;
        }

        $res = $repo->markAsPaidInFull($companyId, $invId);
        InvoicePaidReceiptEmailService::notifyIfBecamePaid($companyId, $invId, $res);

        return $res['updated'];
    }

    private static function logStripePayFailure(string $message): void
    {
        $dir = dirname(__DIR__, 3) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir . '/stripe_invoice_sync.log';
        @file_put_contents($path, date('c') . ' ' . $message . "\n", FILE_APPEND);
    }

    private static function logStripePayDebug(string $message, ?object $session = null): void
    {
        if (!Config::isDebug()) {
            return;
        }
        $suffix = '';
        if ($session !== null) {
            $suffix = sprintf(
                ' [payment_status=%s client_reference_id=%s session.id=%s]',
                (string) ($session->payment_status ?? ''),
                (string) ($session->client_reference_id ?? ''),
                (string) ($session->id ?? '')
            );
        }
        self::logStripePayFailure($message . $suffix);
    }

    /** Paiement considéré comme réussi côté Checkout (statut session ou PaymentIntent). */
    private static function isStripeCheckoutSessionPaid(object $session): bool
    {
        $ps = (string) ($session->payment_status ?? '');
        if ($ps === 'paid' || $ps === 'no_payment_required') {
            return true;
        }

        $pi = $session->payment_intent ?? null;
        if (is_object($pi) && (string) ($pi->status ?? '') === 'succeeded') {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    private static function stripeSessionMetadataToArray(object $session): array
    {
        if (!isset($session->metadata)) {
            return [];
        }
        $meta = $session->metadata;
        if (is_object($meta) && method_exists($meta, 'toArray')) {
            $md = $meta->toArray();
        } elseif (is_array($meta)) {
            $md = $meta;
        } else {
            return [];
        }

        $out = [];
        foreach ($md as $k => $v) {
            $out[(string) $k] = (string) $v;
        }

        return $out;
    }
}
