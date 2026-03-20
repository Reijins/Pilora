<?php
declare(strict_types=1);

namespace Modules\Settings\Repositories;

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
            'proof_required' => '0',
            'quote_email_subject' => 'Votre devis {{quote_number}}',
            'quote_email_body' => "Bonjour,\n\nVeuillez trouver votre devis en pièce jointe (PDF).\nVous pouvez aussi le consulter en ligne : {{quote_link}}\n\nCordialement,\n{{company_name}}",
            'company_logo_path' => '',
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
}

