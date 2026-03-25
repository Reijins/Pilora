<?php
declare(strict_types=1);

namespace Modules\PriceLibrary\Services;

final class PriceLineDefaultsResolver
{
    /**
     * @param array<string,mixed>|null $priceLibraryItem
     * @return array{vatRate:float,revenueAccount:?string}
     */
    public static function resolve(?array $priceLibraryItem, mixed $postedVatRaw, mixed $postedAccRaw, float $companyDefaultVat): array
    {
        $vat = $companyDefaultVat;
        if (is_numeric($postedVatRaw)) {
            $vat = (float) $postedVatRaw;
        } elseif (
            is_array($priceLibraryItem)
            && isset($priceLibraryItem['defaultVatRate'])
            && is_numeric($priceLibraryItem['defaultVatRate'])
        ) {
            $vat = (float) $priceLibraryItem['defaultVatRate'];
        } elseif (
            is_array($priceLibraryItem)
            && isset($priceLibraryItem['categoryDefaultVatRate'])
            && is_numeric($priceLibraryItem['categoryDefaultVatRate'])
        ) {
            $vat = (float) $priceLibraryItem['categoryDefaultVatRate'];
        }
        $vat = max(0.0, min(100.0, $vat));

        $acc = trim((string) $postedAccRaw);
        if ($acc === '' && is_array($priceLibraryItem) && isset($priceLibraryItem['defaultRevenueAccount'])) {
            $acc = trim((string) $priceLibraryItem['defaultRevenueAccount']);
        }
        if ($acc === '' && is_array($priceLibraryItem) && isset($priceLibraryItem['categoryDefaultRevenueAccount'])) {
            $acc = trim((string) $priceLibraryItem['categoryDefaultRevenueAccount']);
        }

        return ['vatRate' => $vat, 'revenueAccount' => $acc !== '' ? $acc : null];
    }
}

