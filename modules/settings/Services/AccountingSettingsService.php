<?php
declare(strict_types=1);

namespace Modules\Settings\Services;

final class AccountingSettingsService
{
    private const RATE_EPS = 0.005;

    /**
     * @return array<int, array{rate:float, account:string}>
     */
    public static function parseVatRateAccounts(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $r = $row['rate'] ?? null;
            $acc = trim((string) ($row['account'] ?? ''));
            if (!is_numeric($r) || $acc === '') {
                continue;
            }
            $out[] = ['rate' => round((float) $r, 2), 'account' => $acc];
        }

        return $out;
    }

    /**
     * @param array<int, array{rate:float, account:string}> $pairs
     */
    public static function vatAccountForRate(array $pairs, float $rate): ?string
    {
        $rate = round($rate, 2);
        foreach ($pairs as $p) {
            if (abs($p['rate'] - $rate) < self::RATE_EPS) {
                return $p['account'];
            }
        }

        return null;
    }

    public static function defaultClientAccount(array $settings): string
    {
        return trim((string) ($settings['default_client_account'] ?? ''));
    }
}
