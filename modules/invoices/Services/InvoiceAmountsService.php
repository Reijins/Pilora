<?php
declare(strict_types=1);

namespace Modules\Invoices\Services;

use Modules\Quotes\Repositories\QuoteRepository;
use Modules\Settings\Repositories\SmtpSettingsRepository;

final class InvoiceAmountsService
{
    /**
     * @return array{ht:float,vat_rate:float,vat_amount:float,ttc:float}
     */
    public static function fromQuote(int $companyId, int $quoteId): array
    {
        $ht = (new QuoteRepository())->computeQuoteTotalAmount($companyId, $quoteId);
        $smtp = (new SmtpSettingsRepository())->getByCompanyId($companyId);
        $vatRate = is_numeric($smtp['vat_rate'] ?? null) ? (float) $smtp['vat_rate'] : 20.0;
        $ht = round($ht, 2);
        $ttc = round($ht * (1 + $vatRate / 100.0), 2);
        $vatAmount = round($ttc - $ht, 2);

        return [
            'ht' => $ht,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'ttc' => $ttc,
        ];
    }

    /**
     * Montant total TTC de référence : recalculé depuis le devis (HT + TVA société) si lié,
     * sinon montant en base (factures sans devis).
     */
    public static function canonicalTotalTtc(int $companyId, array $invoice): float
    {
        $quoteId = (int) ($invoice['quoteId'] ?? 0);
        if ($quoteId > 0) {
            try {
                return self::fromQuote($companyId, $quoteId)['ttc'];
            } catch (\Throwable) {
                // devis manquant, etc.
            }
        }

        return round((float) ($invoice['amountTotal'] ?? 0), 2);
    }

    /** Reste à payer TTC (montant total TTC − déjà payé). */
    public static function remainingTtc(int $companyId, array $invoice): float
    {
        $total = self::canonicalTotalTtc($companyId, $invoice);
        $paid = round((float) ($invoice['amountPaid'] ?? 0), 2);
        $r = round($total - $paid, 2);

        return $r > 0 ? $r : 0.0;
    }

    /**
     * Harmonise amountTotal (TTC) et amountRemaining pour affichage / paiement.
     *
     * @param array<string, mixed> $invoice
     * @return array<string, mixed>
     */
    public static function enrichInvoiceRow(int $companyId, array $invoice): array
    {
        $ttc = self::canonicalTotalTtc($companyId, $invoice);
        $paid = round((float) ($invoice['amountPaid'] ?? 0), 2);
        $rem = max(0.0, round($ttc - $paid, 2));
        $invoice['amountTotal'] = $ttc;
        $invoice['amountRemaining'] = $rem;

        return $invoice;
    }
}
