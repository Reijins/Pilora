<?php
declare(strict_types=1);
/** @var array $invoice @var array $items @var array $client @var array $company — name, email, billing_email (tenant) */
/** @var array $totals — ht, vat_rate, vat_amount, ttc */
/** @var array $contacts */
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body{font-family: DejaVu Sans, Arial, sans-serif; color:#0f172a; font-size:11px; margin:0; padding:16px; background:#f4f8fb;}
        .wrap{max-width:980px; margin:0 auto; background:#fff; border:1px solid #d9e7f1; border-radius:14px; padding:16px;}
        .muted{color:#64748b}
        .head{display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:10px;}
        .chip{display:inline-block; padding:4px 10px; border-radius:999px; background:#e0f2fe; color:#075985; font-weight:700; font-size:10px;}
        .blocks{width:100%; margin-top:6px;}
        .blocks td{width:50%; vertical-align:top; padding:4px 6px;}
        .block{border:1px solid #e2e8f0;border-radius:10px;padding:10px}
        table.data{width:100%;border-collapse:collapse;margin-top:10px;table-layout:fixed}
        th,td{border-bottom:1px solid #e2e8f0;padding:8px 6px;vertical-align:top}
        th{background:#f1f8fc;font-size:10px}
        .col-desc,.col-desc-h{text-align:left;width:26%}
        .col-qty,.col-qty-h{text-align:right;width:7%}
        .col-unit,.col-unit-h{text-align:center;width:7%}
        .col-pu,.col-pu-h{text-align:right;width:10%}
        .col-vat,.col-vat-h{text-align:right;width:8%}
        .col-ht,.col-ht-h{text-align:right;width:11%}
        .col-ttc,.col-ttc-h{text-align:right;width:11%}
        tfoot td{border-top:2px solid #dbe7ef;font-weight:700;background:#f8fbfd}
        tfoot .totals-label{text-align:right}
        ul.contacts{margin:4px 0 0 14px;padding:0;}
    </style>
</head>
<body>
    <div class="wrap">
        <div class="head">
            <div>
                <h2 style="margin:0 0 4px;font-size:16px;">Facture n° <?= htmlspecialchars((string) ($invoice['invoiceNumber'] ?? ('FA-' . (int) ($invoice['id'] ?? 0))), ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="muted">Échéance : <?= htmlspecialchars((string) ($invoice['dueDate'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <span class="chip">Facture</span>
        </div>
        <table class="blocks">
            <tr>
                <td>
                    <div class="block">
                        <strong><?= htmlspecialchars((string) ($company['name'] ?? 'Entreprise'), ENT_QUOTES, 'UTF-8') ?></strong><br>
                        <?php if (!empty($company['email'])): ?>
                            <span class="muted"><?= htmlspecialchars((string) $company['email'], ENT_QUOTES, 'UTF-8') ?></span><br>
                        <?php endif; ?>
                        <?php
                            $billEm = trim((string) ($company['billing_email'] ?? ''));
                            $mainEm = trim((string) ($company['email'] ?? ''));
                            if ($billEm !== '' && $billEm !== $mainEm):
                        ?>
                            <span class="muted">Email facturation : <?= htmlspecialchars($billEm, ENT_QUOTES, 'UTF-8') ?></span><br>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <div class="block">
                        <strong>Client</strong><br>
                        <?= htmlspecialchars((string) ($client['name'] ?? 'Client'), ENT_QUOTES, 'UTF-8') ?><br>
                        <?php if (!empty($client['address'])): ?>
                            <span class="muted" style="white-space:pre-line;"><?= htmlspecialchars((string) $client['address'], ENT_QUOTES, 'UTF-8') ?></span><br>
                        <?php endif; ?>
                        <?php if (!empty($client['siret'])): ?>
                            <span class="muted">SIRET : <?= htmlspecialchars((string) $client['siret'], ENT_QUOTES, 'UTF-8') ?></span><br>
                        <?php endif; ?>
                        <span class="muted"><?= htmlspecialchars((string) ($client['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span><br>
                        <?php if (!empty($client['phone'])): ?>
                            <span class="muted"><?= htmlspecialchars((string) $client['phone'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <?php if (($contacts ?? []) !== []): ?>
                            <div style="margin-top:6px;font-size:10px;"><strong>Contacts</strong>
                                <ul class="contacts">
                                    <?php foreach ($contacts as $c): ?>
                                        <li class="muted">
                                            <?= htmlspecialchars(trim((string) ($c['firstName'] ?? '') . ' ' . (string) ($c['lastName'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                            <?php if (!empty($c['email'])): ?> · <?= htmlspecialchars((string) $c['email'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                            <?php if (!empty($c['phone'])): ?> · <?= htmlspecialchars((string) $c['phone'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        </table>
        <table class="data">
            <thead>
                <tr>
                    <th class="col-desc-h">Prestation</th>
                    <th class="col-qty-h">Qté</th>
                    <th class="col-unit-h">Unité</th>
                    <th class="col-pu-h">PU HT</th>
                    <th class="col-vat-h">TVA %</th>
                    <th class="col-ht-h">Total HT</th>
                    <th class="col-ttc-h">Total TTC</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (($items ?? []) as $it): ?>
                    <?php
                        $uLab = trim((string) ($it['unitLabel'] ?? ''));
                        $vr = (float) ($it['vatRate'] ?? 20);
                        $lTtc = isset($it['lineTtc']) ? (float) $it['lineTtc'] : round((float) ($it['lineTotal'] ?? 0) * (1 + $vr / 100), 2);
                    ?>
                    <tr>
                        <td class="col-desc"><?= htmlspecialchars((string) ($it['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="col-qty"><?= htmlspecialchars((string) number_format((float) ($it['quantity'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="col-unit"><?= $uLab !== '' ? htmlspecialchars($uLab, ENT_QUOTES, 'UTF-8') : '—' ?></td>
                        <td class="col-pu"><?= htmlspecialchars((string) number_format((float) ($it['unitPrice'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                        <td class="col-vat"><?= htmlspecialchars((string) number_format($vr, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="col-ht"><?= htmlspecialchars((string) number_format((float) ($it['lineTotal'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                        <td class="col-ttc"><?= htmlspecialchars((string) number_format($lTtc, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <?php
                    $vatByRate = is_array($totals['vat_by_rate'] ?? null) ? $totals['vat_by_rate'] : [];
                    $totHt = (float) ($totals['ht'] ?? 0);
                    $totTtc = (float) ($totals['ttc'] ?? 0);
                    $vatAmt = (float) ($totals['vat_amount'] ?? 0);
                ?>
                <tr>
                    <td colspan="5" class="totals-label">Total HT</td>
                    <td class="col-ht"><?= htmlspecialchars((string) number_format($totHt, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                    <td class="col-ttc">—</td>
                </tr>
                <?php foreach ($vatByRate as $vr): ?>
                    <?php if (((float) ($vr['vat'] ?? 0)) <= 0 && ((float) ($vr['ht'] ?? 0)) <= 0) {
                        continue;
                    } ?>
                    <tr>
                        <td colspan="5" class="totals-label">TVA <?= htmlspecialchars((string) number_format((float) ($vr['rate'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> % — base HT <?= htmlspecialchars((string) number_format((float) ($vr['ht'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                        <td class="col-ht">—</td>
                        <td class="col-ttc"><?= htmlspecialchars((string) number_format((float) ($vr['vat'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($vatByRate === []): ?>
                <tr>
                    <td colspan="5" class="totals-label">TVA (<?= htmlspecialchars((string) number_format((float) ($totals['vat_rate'] ?? 20), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> %)</td>
                    <td class="col-ht">—</td>
                    <td class="col-ttc"><?= htmlspecialchars((string) number_format($vatAmt, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td colspan="5" class="totals-label">Total TTC</td>
                    <td class="col-ht">—</td>
                    <td class="col-ttc"><?= htmlspecialchars((string) number_format($totTtc, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>
