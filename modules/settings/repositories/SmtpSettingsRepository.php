<?php
declare(strict_types=1);

namespace Modules\Settings\Repositories;

use Modules\Platform\Repositories\PlatformBillingSettingsRepository;

final class SmtpSettingsRepository
{
    private function settingsFilePath(int $companyId): string
    {
        $root = dirname(__DIR__, 3);
        return $root . '/storage/settings/smtp_company_' . $companyId . '.json';
    }

    public function getByCompanyId(int $companyId): array
    {
        $defaults = [
            'host' => '',
            'port' => 587,
            'auth_enabled' => '1',
            'username' => '',
            'password' => '',
            'encryption' => 'tls',
            'from_email' => '',
            'from_name' => '',
            'vat_rate' => 20,
            'vat_rate_accounts' => '[]',
            'default_client_account' => '',
            'default_revenue_account' => '',
            'proof_required' => '0',
            'quote_email_subject' => 'Votre devis {{quote_number}}',
            'quote_email_body' => "Bonjour,\n\nVeuillez trouver votre devis en pièce jointe (PDF).\nVous pouvez aussi le consulter en ligne : {{quote_link}}\n\nCordialement,\n{{company_name}}",
            'invoice_email_subject' => 'Votre facture {{invoice_number}}',
            'invoice_email_body' => "Bonjour,\n\nVeuillez trouver votre facture en pièce jointe (PDF).\n\nConsultez ou payez en ligne : {{invoice_link}}\n\nCordialement,\n{{company_name}}",
            'invoice_paid_email_subject' => 'Réception de votre paiement — {{invoice_number}}',
            'invoice_paid_email_body' => "Bonjour {{client_name}},\n\nNous accusons réception de votre paiement pour la facture {{invoice_number}} ({{amount_paid}}).\n\nMerci pour votre confiance.\n\nCordialement,\n{{company_name}}",
            'company_logo_path' => '',
            'stripe_online_payment_enabled' => '0',
            'stripe_secret_key' => '',
            'stripe_webhook_secret' => '',
        ];
        $path = $this->settingsFilePath($companyId);
        if (!is_file($path)) {
            return $defaults;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return $defaults;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $defaults;
        }
        return array_merge($defaults, $decoded);
    }

    public function saveByCompanyId(int $companyId, array $data): void
    {
        $path = $this->settingsFilePath($companyId);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (!is_string($payload)) {
            throw new \RuntimeException('Impossible de sérialiser les paramètres SMTP.');
        }
        if (@file_put_contents($path, $payload) === false) {
            throw new \RuntimeException('Impossible d\'écrire les paramètres SMTP.');
        }
    }

    /** Paiement en ligne Stripe activé et clé secrète renseignée (société ou secours Pilora). */
    public function isStripeOnlinePaymentReady(int $companyId): bool
    {
        $s = $this->getByCompanyId($companyId);
        if ((string) ($s['stripe_online_payment_enabled'] ?? '0') !== '1') {
            return false;
        }

        return $this->getStripeSecretKey($companyId) !== '';
    }

    /**
     * Clé secrète Stripe (vide si désactivé).
     * Si la clé société est vide, utilise la clé enregistrée dans Paramètres Pilora (plateforme).
     */
    public function getStripeSecretKey(int $companyId): string
    {
        $s = $this->getByCompanyId($companyId);
        if ((string) ($s['stripe_online_payment_enabled'] ?? '0') !== '1') {
            return '';
        }

        $key = trim((string) ($s['stripe_secret_key'] ?? ''));
        if ($key !== '') {
            return $key;
        }
        try {
            $p = (new PlatformBillingSettingsRepository())->get();

            return trim((string) ($p['stripe_secret_key'] ?? ''));
        } catch (\Throwable) {
            return '';
        }
    }
}

