<?php
declare(strict_types=1);

namespace Modules\Invoices\Services;

use Modules\Clients\Repositories\ClientRepository;
use Modules\Invoices\Repositories\InvoiceItemRepository;
use Modules\Invoices\Repositories\InvoiceRepository;
use Modules\Settings\Services\AccountingSettingsService;

final class InvoiceAccountingExportService
{
    /**
     * @param array<int, int|string>|null $onlyInvoiceIds Si défini et non vide, limite l’export à ces factures (éligibles).
     * @return array{csv:string, exportedIds:array<int, int>, errors:array<int, string>}
     */
    public function buildExportCsv(int $companyId, array $settings, ?bool $onlyNotExported, bool $markExported, ?array $onlyInvoiceIds = null): array
    {
        $repo = new InvoiceRepository();
        if ($onlyInvoiceIds !== null && $onlyInvoiceIds !== []) {
            $rows = $repo->listForAccountingByIds($companyId, $onlyInvoiceIds, $onlyNotExported);
        } else {
            $rows = $repo->listForAccountingLines($companyId, $onlyNotExported, 500);
        }
        $clientRepo = new ClientRepository();
        $itemRepo = new InvoiceItemRepository();
        $vatPairs = AccountingSettingsService::parseVatRateAccounts($settings['vat_rate_accounts'] ?? []);
        $defaultClient = AccountingSettingsService::defaultClientAccount($settings);
        $defaultRevenue = trim((string) ($settings['default_revenue_account'] ?? ''));

        $errors = [];
        $exportedIds = [];
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, [
            'piece_date',
            'invoice_id',
            'invoice_number',
            'account',
            'label',
            'debit',
            'credit',
        ], ';');

        $today = (new \DateTimeImmutable('now'))->format('Y-m-d');

        foreach ($rows as $inv) {
            $invoiceId = (int) ($inv['id'] ?? 0);
            if ($invoiceId <= 0) {
                continue;
            }
            $items = $itemRepo->listByCompanyIdAndInvoiceId($companyId, $invoiceId);
            if ($items === []) {
                $errors[$invoiceId] = 'Aucune ligne de facture (snapshot manquant).';
                continue;
            }

            $totals = DocumentTotalsService::aggregate($items);
            $ttc = $totals['ttc'];
            $clientId = (int) ($inv['clientId'] ?? 0);
            $client = $clientId > 0 ? $clientRepo->findByCompanyIdAndId($companyId, $clientId) : null;
            $clientAcc = '';
            if (is_array($client)) {
                $clientAcc = trim((string) ($client['accountingCustomerAccount'] ?? ''));
            }
            if ($clientAcc === '') {
                $clientAcc = $defaultClient;
            }
            if ($clientAcc === '') {
                $clientAcc = '41100000';
            }

            foreach ($totals['vat_by_rate'] as $vr) {
                $rate = (float) $vr['rate'];
                $vatAmt = (float) $vr['vat'];
                if ($vatAmt > 0.0001) {
                    $vatAcc = AccountingSettingsService::vatAccountForRate($vatPairs, $rate);
                    if ($vatAcc === null || $vatAcc === '') {
                        $errors[$invoiceId] = 'Compte TVA manquant pour le taux ' . (string) $rate . ' % (Paramètres).';
                        continue 2;
                    }
                }
            }

            $invNo = (string) ($inv['invoiceNumber'] ?? ('FA-' . $invoiceId));
            $labelBase = 'Facture ' . $invNo;

            fputcsv($fh, [
                $today,
                (string) $invoiceId,
                $invNo,
                $clientAcc,
                $labelBase . ' — client',
                number_format($ttc, 2, '.', ''),
                '',
            ], ';');

            $prodBuckets = [];
            foreach ($items as $it) {
                $rate = round((float) ($it['vatRate'] ?? 0), 2);
                $acc = trim((string) ($it['revenueAccount'] ?? ''));
                if ($acc === '') {
                    $acc = $defaultRevenue;
                }
                if ($acc === '') {
                    $acc = '70600000';
                }
                $key = $acc . '|' . (string) $rate;
                if (!isset($prodBuckets[$key])) {
                    $prodBuckets[$key] = ['account' => $acc, 'rate' => $rate, 'ht' => 0.0];
                }
                $prodBuckets[$key]['ht'] = round($prodBuckets[$key]['ht'] + (float) ($it['lineTotal'] ?? 0), 2);
            }

            foreach ($prodBuckets as $b) {
                if ($b['ht'] <= 0) {
                    continue;
                }
                fputcsv($fh, [
                    $today,
                    (string) $invoiceId,
                    $invNo,
                    $b['account'],
                    $labelBase . ' — ventes HT ' . (string) $b['rate'] . '%',
                    '',
                    number_format($b['ht'], 2, '.', ''),
                ], ';');
            }

            foreach ($totals['vat_by_rate'] as $vr) {
                $rate = (float) $vr['rate'];
                $vatAmt = (float) $vr['vat'];
                if ($vatAmt <= 0.0001) {
                    continue;
                }
                $vatAcc = AccountingSettingsService::vatAccountForRate($vatPairs, $rate);
                if ($vatAcc === null || $vatAcc === '') {
                    continue;
                }
                fputcsv($fh, [
                    $today,
                    (string) $invoiceId,
                    $invNo,
                    $vatAcc,
                    $labelBase . ' — TVA ' . (string) $rate . ' %',
                    '',
                    number_format($vatAmt, 2, '.', ''),
                ], ';');
            }

            $exportedIds[] = $invoiceId;
        }

        if ($markExported && $exportedIds !== []) {
            foreach ($exportedIds as $iid) {
                $repo->markAccountingExported($companyId, $iid);
            }
        }

        rewind($fh);
        $csv = stream_get_contents($fh) ?: '';
        fclose($fh);

        return ['csv' => $csv, 'exportedIds' => $exportedIds, 'errors' => $errors];
    }
}
