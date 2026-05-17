<?php
declare(strict_types=1);

namespace Modules\Platform\Repositories;

/**
 * SMTP et modèles d’emails émis par Pilora vers les sociétés clientes (facturation, bienvenue).
 */
final class PlatformSmtpSettingsRepository
{
    private function path(): string
    {
        return dirname(__DIR__, 3) . '/storage/settings/platform_smtp.json';
    }

    /**
     * @return array<string, string|int>
     */
    public function get(): array
    {
        $defaults = [
            'host' => '',
            'port' => 587,
            'auth_enabled' => '1',
            'username' => '',
            'password' => '',
            'encryption' => 'tls',
            'from_email' => '',
            'from_name' => 'Pilora',
            'billing_email_subject' => 'Facture Pilora — {{pack_name}}',
            'billing_email_body' => "Bonjour,\n\nVotre abonnement {{pack_name}} ({{billing_cycle}}) a été renouvelé.\nMontant : {{amount}} EUR.\nProchaine échéance : {{renew_date}}.\n\nCordialement,\nPilora",
            'company_welcome_subject' => 'Votre espace Pilora est prêt — {{company_name}}',
            'company_welcome_body' => "Bonjour,\n\nVotre espace Pilora « {{company_name}} » a été créé.\n\nConnectez-vous : {{login_url}}\nIdentifiant : {{login_email}}\n\nCordialement,\nL’équipe Pilora",
            'demo_notify_email' => '',
            'demo_notify_subject' => '[Pilora] Nouvelle demande de démo #{{request_id}}',
            'demo_notify_body' => "Nouvelle demande de démonstration Pilora.\n\nNom : {{contact_name}}\nEmail : {{contact_email}}\nEntreprise : {{company_name}}\nMessage :\n{{message}}\n\nRéférence : #{{request_id}}",
            'demo_ack_subject' => 'Demande de démo Pilora reçue',
            'demo_ack_body' => "Bonjour {{contact_name}},\n\nNous avons bien reçu votre demande de démonstration Pilora"
                . " pour {{company_name}}.\nUn membre de notre équipe vous recontacte sous 48 h ouvrées.\n\nCordialement,\nL’équipe Pilora",
        ];
        $p = $this->path();
        if (!is_file($p)) {
            return $defaults;
        }
        $raw = @file_get_contents($p);
        if (!is_string($raw) || trim($raw) === '') {
            return $defaults;
        }
        $d = json_decode($raw, true);

        return is_array($d) ? array_merge($defaults, $d) : $defaults;
    }

    public function save(array $data): void
    {
        $dir = dirname($this->path());
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $existing = $this->get();
        $passwordInput = trim((string) ($data['password'] ?? ''));
        if ($passwordInput === '') {
            $data['password'] = (string) ($existing['password'] ?? '');
        }
        $merged = array_merge($existing, $data);
        $payload = json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (!is_string($payload)) {
            throw new \RuntimeException('Sérialisation impossible.');
        }
        if (@file_put_contents($this->path(), $payload) === false) {
            throw new \RuntimeException('Écriture impossible.');
        }
    }
}
