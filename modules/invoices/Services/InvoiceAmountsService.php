<?php
declare(strict_types=1);

namespace Modules\Invoices\Services;

use Modules\Invoices\Repositories\InvoiceItemRepository;
use Modules\Quotes\Repositories\QuoteRepository;
use Modules\Settings\Repositories\SmtpSettingsRepository;

final class InvoiceAmountsService
{
    /**
     * Totaux depuis les lignes de devis (TVA par ligne).
     *
     * @return array{ht:float,vat_rate:float,vat_amount:float,ttc:float,vat_by_rate:array<int, array{rate:float, ht:float, vat:float}>}
     */
    public static function fromQuote(int $companyId, int $quoteId): array
    {
        $items = (new QuoteRepository())->listItemsByCompanyIdAndQuoteId($companyId, $quoteId);
        $agg = DocumentTotalsService::aggregate($items);

        return [
            'ht' => $agg['ht'],
            'vat_rate' => $agg['vat_rate'],
            'vat_amount' => $agg['vat_amount'],
            'ttc' => $agg['ttc'],
            'vat_by_rate' => $agg['vat_by_rate'],
        ];
    }

    /**
     * Totaux depuis les lignes de facture figées (prioritaires).
     *
     * @return array{ht:float,vat_rate:float,vat_amount:float,ttc:float,vat_by_rate:array<int, array{rate:float, ht:float, vat:float}>}|null
     */
    public static function fromInvoiceLines(int $companyId, int $invoiceId): ?array
    {
        $items = (new InvoiceItemRepository())->listByCompanyIdAndInvoiceId($companyId, $invoiceId);
        if ($items === []) {
            return null;
        }
        $agg = DocumentTotalsService::aggregate($items);

        return [
            'ht' => $agg['ht'],
            'vat_rate' => $agg['vat_rate'],
            'vat_amount' => $agg['vat_amount'],
            'ttc' => $agg['ttc'],
            'vat_by_rate' => $agg['vat_by_rate'],
        ];
    }

    /**
     * Montant total TTC de référence : lignes facture si présentes, sinon recalcul devis, sinon base.
     *
     * @param array<string, mixed> $invoice
     */
    public static function canonicalTotalTtc(int $companyId, array $invoice): float
    {
        $invoiceId = (int) ($invoice['id'] ?? 0);
        if ($invoiceId > 0) {
            $fromLines = self::fromInvoiceLines($companyId, $invoiceId);
            if ($fromLines !== null) {
                return round($fromLines['ttc'], 2);
            }
        }

        $quoteId = (int) ($invoice['quoteId'] ?? 0);
        if ($quoteId > 0) {
            try {
                return self::fromQuote($companyId, $quoteId)['ttc'];
            } catch (\Throwable) {
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
        if (!isset($invoice['quoteProjectId']) && isset($invoice['quoteprojectid'])) {
            $invoice['quoteProjectId'] = $invoice['quoteprojectid'];
        }
        $ttc = self::canonicalTotalTtc($companyId, $invoice);
        $paid = round((float) ($invoice['amountPaid'] ?? 0), 2);
        $rem = max(0.0, round($ttc - $paid, 2));
        $invoice['amountTotal'] = $ttc;
        $invoice['amountRemaining'] = $rem;

        return $invoice;
    }

    /**
     * Totaux pour affichage PDF / page publique.
     *
     * @param array<string, mixed> $invoice
     * @return array{ht:float,vat_rate:float,vat_amount:float,ttc:float,vat_by_rate:array<int, array{rate:float, ht:float, vat:float}>}
     */
    public static function displayTotalsForInvoice(int $companyId, array $invoice): array
    {
        $invoiceId = (int) ($invoice['id'] ?? 0);
        if ($invoiceId > 0) {
            $fromLines = self::fromInvoiceLines($companyId, $invoiceId);
            if ($fromLines !== null) {
                return $fromLines;
            }
        }

        $quoteId = (int) ($invoice['quoteId'] ?? 0);
        if ($quoteId > 0) {
            try {
                return self::fromQuote($companyId, $quoteId);
            } catch (\Throwable) {
            }
        }

        $smtp = (new SmtpSettingsRepository())->getByCompanyId($companyId);
        $vatRate = is_numeric($smtp['vat_rate'] ?? null) ? (float) $smtp['vat_rate'] : 20.0;
        $ttc = round((float) ($invoice['amountTotal'] ?? 0), 2);

        return [
            'ht' => 0.0,
            'vat_rate' => $vatRate,
            'vat_amount' => 0.0,
            'ttc' => $ttc,
            'vat_by_rate' => [],
        ];
    }
}
