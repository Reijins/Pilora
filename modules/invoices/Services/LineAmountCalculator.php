<?php
declare(strict_types=1);

namespace Modules\Invoices\Services;

final class LineAmountCalculator
{
    /**
     * @return array{lineTotal:float,lineVat:float,lineTtc:float}
     */
    public static function fromQtyUnitVat(float $quantity, float $unitPriceHt, float $vatRatePercent): array
    {
        $lineHt = round($quantity * $unitPriceHt, 2);
        $lineVat = round($lineHt * max(0.0, $vatRatePercent) / 100.0, 2);
        $lineTtc = round($lineHt + $lineVat, 2);

        return [
            'lineTotal' => $lineHt,
            'lineVat' => $lineVat,
            'lineTtc' => $lineTtc,
        ];
    }
}
