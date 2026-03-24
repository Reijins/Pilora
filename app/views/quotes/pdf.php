<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body{font-family: DejaVu Sans, Arial, sans-serif; color:#0f172a; font-size:12px; margin:0; padding:20px; background:#f4f8fb;}
        .wrap{max-width:980px; margin:0 auto; background:#fff; border:1px solid #d9e7f1; border-radius:18px; padding:20px;}
        .muted{color:#64748b}
        .blocks{width:100%; margin-top:12px;}
        .blocks td{width:50%; vertical-align:top; padding:0 6px;}
        .block{border:1px solid #e2e8f0;border-radius:12px;padding:12px}
        table{width:100%;border-collapse:collapse;margin-top:14px;table-layout:fixed}
        th,td{border-bottom:1px solid #e2e8f0;padding:10px 8px;vertical-align:top}
        th{background:#f1f8fc;font-size:11px}
        .col-desc,.col-desc-h{text-align:left;width:28%}
        .col-qty,.col-qty-h{text-align:right;width:8%}
        .col-unit,.col-unit-h{text-align:center;width:8%}
        .col-pu,.col-pu-h{text-align:right;width:11%}
        .col-vat,.col-vat-h{text-align:right;width:8%}
        .col-ht,.col-ht-h{text-align:right;width:11%}
        .col-ttc,.col-ttc-h{text-align:right;width:11%}
        tfoot td{border-top:2px solid #dbe7ef;font-weight:700;background:#f8fbfd}
        tfoot .totals-label{text-align:right}
    </style>
</head>
<body>
    <div class="wrap">
    <h2 style="margin:0 0 6px;">Devis n° <?= htmlspecialchars((string) ($quote['quoteNumber'] ?? ('DEV-' . (int) ($quote['id'] ?? 0))), ENT_QUOTES, 'UTF-8') ?></h2>
    <table class="blocks">
        <tr>
            <td>
                <div class="block">
                    <strong>Entreprise</strong><br>
                    <?= htmlspecialchars((string) ($company['name'] ?? 'Entreprise'), ENT_QUOTES, 'UTF-8') ?><br>
                    <?php if (!empty($company['email'])): ?>
                        <span class="muted"><?= htmlspecialchars((string) $company['email'], ENT_QUOTES, 'UTF-8') ?></span><br>
                    <?php endif; ?>
                    <?php
                        $be = trim((string) ($company['billing_email'] ?? ''));
                        $em = trim((string) ($company['email'] ?? ''));
                        if ($be !== '' && $be !== $em):
                    ?>
                        <span class="muted">Email facturation : <?= htmlspecialchars($be, ENT_QUOTES, 'UTF-8') ?></span><br>
                    <?php endif; ?>
                    <span class="muted">Numero: <?= htmlspecialchars((string) ($quote['quoteNumber'] ?? ('DEV-' . (int) ($quote['id'] ?? 0))), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </td>
            <td>
                <div class="block">
                    <strong>Client</strong><br>
                    <?= htmlspecialchars((string) ($client['name'] ?? 'Client'), ENT_QUOTES, 'UTF-8') ?><br>
                    <span class="muted"><?= htmlspecialchars((string) ($client['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if (!empty($client['phone'])): ?><br><span class="muted"><?= htmlspecialchars((string) $client['phone'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                    <?php if (!empty($client['address'])): ?><br><span class="muted"><?= htmlspecialchars((string) $client['address'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                    <?php if (!empty($contact)): ?>
                        <br><span class="muted">Contact affaire: <?= htmlspecialchars(trim((string) ($contact['firstName'] ?? '') . ' ' . (string) ($contact['lastName'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if (!empty($contact['email'])): ?><br><span class="muted">Email contact: <?= htmlspecialchars((string) $contact['email'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    </table>
    <table>
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
                <?php $uLab = trim((string) ($it['unitLabel'] ?? '')); ?>
                <?php
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
                $totHt = (float) ($totalHt ?? 0);
                $totTtc = (float) ($totalTtc ?? 0);
                $vatAmt = isset($vatAmount) ? (float) $vatAmount : round(max(0, $totTtc - $totHt), 2);
                $vatByRate = is_array($vatByRate ?? null) ? $vatByRate : [];
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
                <td colspan="5" class="totals-label">TVA (<?= htmlspecialchars((string) number_format((float) ($vatRate ?? 20), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> %)</td>
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
    <?php
        $notes = (string) ($quote['notes'] ?? '');
        preg_match('/\[SIGNED_FIRST_NAME:([^\]]*)\]/', $notes, $mFn);
        preg_match('/\[SIGNED_LAST_NAME:([^\]]*)\]/', $notes, $mLn);
        preg_match('/\[SIGNED_EMAIL:([^\]]*)\]/', $notes, $mEm);
        preg_match('/\[SIGNED_AT:([^\]]*)\]/', $notes, $mAt);
        $signedName = trim(((string) ($mFn[1] ?? '')) . ' ' . ((string) ($mLn[1] ?? '')));
        $signedAt = (string) ($mAt[1] ?? ($quote['acceptedAt'] ?? ''));
    ?>
    <?php if ($signedName !== '' || $signedAt !== ''): ?>
        <div class="block" style="margin-top:14px;">
            <strong>Signature électronique</strong><br>
            Signataire: <?= htmlspecialchars($signedName !== '' ? $signedName : (string) ($client['name'] ?? 'Client'), ENT_QUOTES, 'UTF-8') ?><br>
            Email: <?= htmlspecialchars((string) ($mEm[1] ?? ($client['email'] ?? '')), ENT_QUOTES, 'UTF-8') ?><br>
            Date/heure: <?= htmlspecialchars($signedAt, ENT_QUOTES, 'UTF-8') ?><br>
            <div style="margin-top:8px; border-top:1px solid #94a3b8; width:280px; padding-top:6px;"></div>
        </div>
    <?php endif; ?>
    </div>
</body>
</html>

