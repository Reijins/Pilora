<?php
declare(strict_types=1);

namespace Modules\Platform\Repositories;

/**
 * Paramètres légaux Pilora affichés sur les factures (back-office plateforme).
 */
final class PlatformBillingSettingsRepository
{
    private function path(): string
    {
        return dirname(__DIR__, 3) . '/storage/settings/platform_billing.json';
    }

    /**
     * @return array{
     *   legal_name:string,
     *   address:string,
     *   siret:string,
     *   rib:string,
     *   phone:string,
     *   email:string,
     *   website:string,
     *   stripe_secret_key:string
     * }
     */
    public function get(): array
    {
        $defaults = [
            'legal_name' => 'Pilora',
            'address' => '',
            'siret' => '',
            'rib' => '',
            'phone' => '',
            'email' => '',
            'website' => '',
            'stripe_secret_key' => '',
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
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (!is_string($payload)) {
            throw new \RuntimeException('Sérialisation impossible.');
        }
        if (@file_put_contents($this->path(), $payload) === false) {
            throw new \RuntimeException('Écriture impossible.');
        }
    }
}
