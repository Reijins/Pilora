<?php
declare(strict_types=1);

namespace Modules\Invoices\Services;

/**
 * Agrège des lignes document (devis/facture) avec TVA par ligne.
 *
 * @phpstan-type LineRow array{
 *   lineTotal?:float|numeric-string,
 *   lineVat?:float|numeric-string,
 *   lineTtc?:float|numeric-string,
 *   vatRate?:float|numeric-string
 * }
 */
final class DocumentTotalsService
{
    /**
     * @param array<int, LineRow> $lines
     * @return array{
     *   ht:float,
     *   vat_amount:float,
     *   ttc:float,
     *   vat_by_rate:array<int, array{rate:float, ht:float, vat:float}>,
     *   vat_rate:float
     * }
     */
    public static function aggregate(array $lines): array
    {
        $byRate = [];
        $htSum = 0.0;
        $vatSum = 0.0;
        $ttcSum = 0.0;

        foreach ($lines as $row) {
            $ht = round((float) ($row['lineTotal'] ?? 0), 2);
            $vat = round((float) ($row['lineVat'] ?? 0), 2);
            $ttc = round((float) ($row['lineTtc'] ?? 0), 2);
            $rate = round((float) ($row['vatRate'] ?? 0), 2);

            if ($ttc <= 0 && $ht > 0) {
                $vat = round($ht * $rate / 100.0, 2);
                $ttc = round($ht + $vat, 2);
            }

            $htSum += $ht;
            $vatSum += $vat;
            $ttcSum += $ttc;

            $key = (string) $rate;
            if (!isset($byRate[$key])) {
                $byRate[$key] = ['rate' => $rate, 'ht' => 0.0, 'vat' => 0.0];
            }
            $byRate[$key]['ht'] = round($byRate[$key]['ht'] + $ht, 2);
            $byRate[$key]['vat'] = round($byRate[$key]['vat'] + $vat, 2);
        }

        $htSum = round($htSum, 2);
        $vatSum = round($vatSum, 2);
        $ttcSum = round($ttcSum, 2);

        $vatByRate = array_values($byRate);
        usort($vatByRate, static fn (array $a, array $b): int => ($b['rate'] <=> $a['rate']));

        $singleRate = 20.0;
        if (count($vatByRate) === 1) {
            $singleRate = (float) $vatByRate[0]['rate'];
        }

        return [
            'ht' => $htSum,
            'vat_amount' => $vatSum,
            'ttc' => $ttcSum,
            'vat_by_rate' => $vatByRate,
            'vat_rate' => $singleRate,
        ];
    }
}
