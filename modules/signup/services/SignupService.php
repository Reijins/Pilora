<?php
declare(strict_types=1);

namespace Modules\Signup\Services;

use Core\Config;
use Core\Database\Connection;
use Modules\Auth\Repositories\UserRepository;
use Modules\Platform\Repositories\PackRepository;
use Modules\Platform\Repositories\PlatformBillingSettingsRepository;
use Modules\Signup\Repositories\SignupPendingRepository;
use PDO;

final class SignupService
{
    private SignupPendingRepository $pendingRepo;

    public function __construct()
    {
        $this->pendingRepo = new SignupPendingRepository();
    }

    /**
     * @return array{token:string, requires_payment:bool, checkout_url:?string}
     */
    public function startSignup(array $input): array
    {
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $fullName = trim((string) ($input['full_name'] ?? ''));
        $companyName = trim((string) ($input['company_name'] ?? ''));
        $packId = (int) ($input['pack_id'] ?? 0);
        $billingCycle = trim((string) ($input['billing_cycle'] ?? 'monthly'));
        if (!in_array($billingCycle, ['monthly', 'annual'], true)) {
            $billingCycle = 'monthly';
        }

        if ($companyName === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Informations incomplètes.');
        }
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Le mot de passe doit contenir au moins 8 caractères.');
        }
        if ((new UserRepository())->findActiveByEmail($email) !== null) {
            throw new \InvalidArgumentException('Un compte actif existe déjà avec cet email.');
        }

        $pack = (new PackRepository())->findById($packId);
        if ($pack === null) {
            throw new \InvalidArgumentException('Pack invalide.');
        }

        $packPrice = (float) ($pack['price'] ?? 0);
        $token = bin2hex(random_bytes(24));
        $payload = [
            'company_name' => $companyName,
            'billing_email' => $email,
            'user_email' => $email,
            'full_name' => $fullName !== '' ? $fullName : 'Administrateur',
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'pack' => $pack,
            'pack_id' => $packId,
            'billing_cycle' => $billingCycle,
        ];

        $this->pendingRepo->create($token, $payload);

        if ($packPrice <= 0) {
            $this->ensureProvisioned($token, null);

            return ['token' => $token, 'requires_payment' => false, 'checkout_url' => null];
        }

        $checkoutUrl = $this->createStripeCheckoutSession($token, $payload, $pack, $billingCycle);
        if ($checkoutUrl === '') {
            throw new \RuntimeException('Paiement en ligne indisponible (Stripe non configuré).');
        }

        return ['token' => $token, 'requires_payment' => true, 'checkout_url' => $checkoutUrl];
    }

    /**
     * @return array{companyId:int, userId:int}
     */
    public function ensureProvisioned(string $token, ?string $externalBillingRef): array
    {
        $token = trim($token);
        if ($token === '') {
            throw new \InvalidArgumentException('Jeton invalide.');
        }

        $pdo = Connection::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('
                SELECT id, token, status, companyId, userId, payload
                FROM SignupPending
                WHERE token = :token
                LIMIT 1
                FOR UPDATE
            ');
            $stmt->execute(['token' => $token]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                throw new \RuntimeException('Inscription introuvable.');
            }

            $companyId = (int) ($row['companyId'] ?? 0);
            $userId = (int) ($row['userId'] ?? 0);
            if ($companyId > 0 && $userId > 0) {
                $pdo->commit();

                return ['companyId' => $companyId, 'userId' => $userId];
            }

            $payload = $this->decodePayload((string) ($row['payload'] ?? ''));
            $pack = $payload['pack'] ?? null;
            if (!is_array($pack)) {
                throw new \RuntimeException('Données d\'inscription invalides.');
            }

            $existingUser = (new UserRepository())->findActiveByEmail((string) ($payload['user_email'] ?? ''));
            if (is_array($existingUser)) {
                $companyId = (int) ($existingUser['companyId'] ?? 0);
                $userId = (int) ($existingUser['id'] ?? 0);
                if ($companyId > 0 && $userId > 0) {
                    $pdo->prepare('
                        UPDATE SignupPending
                        SET status = "provisioned", companyId = :cid, userId = :uid, updatedAt = NOW()
                        WHERE token = :token
                    ')->execute(['cid' => $companyId, 'uid' => $userId, 'token' => $token]);
                    $pdo->commit();

                    return ['companyId' => $companyId, 'userId' => $userId];
                }
            }

            $ref = $externalBillingRef;
            if ($ref === null || $ref === '') {
                $ref = isset($payload['external_billing_ref']) ? (string) $payload['external_billing_ref'] : null;
            }

            $result = (new TenantProvisioningService())->provision([
                'company_name' => (string) ($payload['company_name'] ?? ''),
                'billing_email' => (string) ($payload['billing_email'] ?? ''),
                'user_email' => (string) ($payload['user_email'] ?? ''),
                'password_hash' => (string) ($payload['password_hash'] ?? ''),
                'full_name' => (string) ($payload['full_name'] ?? ''),
                'pack' => $pack,
                'billing_cycle' => (string) ($payload['billing_cycle'] ?? 'monthly'),
                'external_billing_ref' => $ref !== '' ? $ref : null,
            ]);

            $companyId = (int) $result['companyId'];
            $userId = (int) $result['userId'];
            $upd = $pdo->prepare('
                UPDATE SignupPending
                SET status = "provisioned",
                    companyId = :cid,
                    userId = :uid,
                    updatedAt = NOW()
                WHERE token = :token
            ');
            $upd->execute(['cid' => $companyId, 'uid' => $userId, 'token' => $token]);
            $pdo->commit();

            return ['companyId' => $companyId, 'userId' => $userId];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            try {
                $this->pendingRepo->markFailed($token);
            } catch (\Throwable) {
            }
            throw $e;
        }
    }

    public function syncStripeSessionAndProvision(string $token, string $sessionId): array
    {
        $token = trim($token);
        $sessionId = trim($sessionId);
        if ($token === '' || $sessionId === '') {
            throw new \InvalidArgumentException('Paramètres invalides.');
        }

        $row = $this->pendingRepo->findByToken($token);
        if ($row === null) {
            throw new \RuntimeException('Inscription introuvable.');
        }

        $pack = $row['payload']['pack'] ?? null;
        if (!is_array($pack) || (float) ($pack['price'] ?? 0) <= 0) {
            return $this->ensureProvisioned($token, null);
        }

        $secret = trim((string) ((new PlatformBillingSettingsRepository())->get()['stripe_secret_key'] ?? ''));
        if ($secret === '') {
            throw new \RuntimeException('Stripe non configuré.');
        }

        \Stripe\Stripe::setApiKey($secret);
        $session = \Stripe\Checkout\Session::retrieve($sessionId, ['expand' => ['subscription']]);
        if (!$this->isCheckoutSessionPaid($session)) {
            throw new \RuntimeException('Paiement non confirmé.');
        }

        $md = $this->sessionMetadata($session);
        $mdToken = trim((string) ($md['signup_token'] ?? ''));
        if ($mdToken !== '' && $mdToken !== $token) {
            throw new \RuntimeException('Session de paiement incorrecte.');
        }

        $this->pendingRepo->markPaid($token);
        $this->pendingRepo->saveStripeSessionId($token, $sessionId);

        $subId = '';
        if (isset($session->subscription)) {
            $subId = is_object($session->subscription)
                ? (string) ($session->subscription->id ?? '')
                : (string) $session->subscription;
        }

        return $this->ensureProvisioned($token, $subId !== '' ? $subId : null);
    }

    public function handleStripeWebhook(string $payload, string $sigHeader): void
    {
        $whSecret = trim((string) ((new PlatformBillingSettingsRepository())->get()['stripe_webhook_secret'] ?? ''));
        if ($whSecret === '') {
            throw new \RuntimeException('Webhook non configuré');
        }

        $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $whSecret);
        if ($event->type !== 'checkout.session.completed') {
            return;
        }

        $session = $event->data->object ?? null;
        if (!is_object($session) || !$this->isCheckoutSessionPaid($session)) {
            return;
        }

        $md = $this->sessionMetadata($session);
        if (($md['type'] ?? '') !== 'signup') {
            return;
        }

        $token = trim((string) ($md['signup_token'] ?? ''));
        if ($token === '') {
            return;
        }

        $sid = (string) ($session->id ?? '');
        if ($sid !== '') {
            $this->pendingRepo->saveStripeSessionId($token, $sid);
        }
        $this->pendingRepo->markPaid($token);

        $subId = isset($session->subscription) ? (string) $session->subscription : '';
        $this->ensureProvisioned($token, $subId !== '' ? $subId : null);
    }

    public function publicBaseUrl(): string
    {
        $appUrl = rtrim((string) (Config::env('APP_URL', '') ?? ''), '/');
        if ($appUrl !== '') {
            return $appUrl;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        $basePath = ($basePath === '.' || $basePath === '\\') ? '' : $basePath;

        return $scheme . '://' . $host . $basePath;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $pack
     */
    private function createStripeCheckoutSession(
        string $token,
        array $payload,
        array $pack,
        string $billingCycle,
    ): string {
        $secret = trim((string) ((new PlatformBillingSettingsRepository())->get()['stripe_secret_key'] ?? ''));
        if ($secret === '') {
            return '';
        }

        $price = (float) ($pack['price'] ?? 0);
        if ($price <= 0) {
            return '';
        }

        $packName = trim((string) ($pack['name'] ?? 'Abonnement Pilora'));
        $amountCents = (int) round($price * 100);
        $interval = 'month';
        if ($billingCycle === 'annual') {
            $amountCents = (int) round($price * 12 * 100);
            $interval = 'year';
        }

        $publicBase = $this->publicBaseUrl();
        $email = trim((string) ($payload['user_email'] ?? ''));

        \Stripe\Stripe::setApiKey($secret);
        $params = [
            'mode' => 'subscription',
            'client_reference_id' => $token,
            'metadata' => [
                'type' => 'signup',
                'signup_token' => $token,
            ],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => $amountCents,
                    'recurring' => ['interval' => $interval],
                    'product_data' => ['name' => 'Pilora — ' . $packName],
                ],
                'quantity' => 1,
            ]],
            'success_url' => $publicBase . '/inscription/succes?token=' . rawurlencode($token) . '&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $publicBase . '/inscription/annule?token=' . rawurlencode($token),
        ];
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $params['customer_email'] = $email;
        }

        $session = \Stripe\Checkout\Session::create($params);
        $sid = (string) ($session->id ?? '');
        if ($sid !== '') {
            $this->pendingRepo->saveStripeSessionId($token, $sid);
        }

        return (string) ($session->url ?? '');
    }

    /**
     * @return array<string, string>
     */
    private function sessionMetadata(object $session): array
    {
        $out = [];
        $md = $session->metadata ?? null;
        if (is_object($md)) {
            foreach (get_object_vars($md) as $k => $v) {
                $out[(string) $k] = (string) $v;
            }
        }

        return $out;
    }

    private function isCheckoutSessionPaid(object $session): bool
    {
        if ((string) ($session->status ?? '') === 'complete') {
            return true;
        }
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
     * @return array<string, mixed>
     */
    private function decodePayload(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
