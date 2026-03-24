<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Consultation devis</title>
    <style>
        body{font-family:Inter,Arial,sans-serif;background:#f4f8fb;color:#0f172a;margin:0;padding:24px}
        .wrap{max-width:980px;margin:0 auto;background:#fff;border:1px solid #d9e7f1;border-radius:18px;padding:22px;box-shadow:0 20px 40px rgba(15,23,42,.06)}
        .muted{color:#64748b}
        table{width:100%;border-collapse:collapse;margin-top:14px;table-layout:fixed}
        th,td{border-bottom:1px solid #e2e8f0;padding:10px 8px;vertical-align:top}
        th{background:#f1f8fc;font-size:13px}
        .col-desc,.col-desc-h{text-align:left;width:28%}
        .col-qty,.col-qty-h{text-align:right;width:8%}
        .col-unit,.col-unit-h{text-align:center;width:8%}
        .col-pu,.col-pu-h{text-align:right;width:11%}
        .col-vat,.col-vat-h{text-align:right;width:8%}
        .col-ht,.col-ht-h{text-align:right;width:11%}
        .col-ttc,.col-ttc-h{text-align:right;width:11%}
        tfoot td{border-top:2px solid #dbe7ef;font-weight:700;background:#f8fbfd}
        tfoot .totals-label{text-align:right}
        .blocks{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px}
        .block{border:1px solid #e2e8f0;border-radius:12px;padding:12px}
        .actions{margin-top:16px;border:1px solid #dbeafe;background:#f8fbff;border-radius:12px;padding:14px}
        .btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:12px;border:1px solid transparent;cursor:pointer;font-weight:700}
        .btn-primary{background:#58B1D6;color:#fff}
        .btn-primary:hover{background:#3d9bc2}
        .input{border:1px solid #cbd5e1;border-radius:10px;padding:10px 12px;min-width:220px}
        .flash-success{margin:0 0 14px;padding:12px 14px;border-radius:12px;background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;font-weight:600;font-size:15px}
        .flash-error{margin:0 0 14px;padding:12px 14px;border-radius:12px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;font-weight:600;font-size:15px}
    </style>
</head>
<body>
    <?php
        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        $basePath = ($basePath === '.' || $basePath === '\\') ? '' : $basePath;
    ?>
    <div class="wrap">
        <h2 style="margin:0 0 6px;">Devis n° <?= htmlspecialchars((string) ($quote['quoteNumber'] ?? ('DEV-' . (int) ($quote['id'] ?? 0))), ENT_QUOTES, 'UTF-8') ?></h2>
        <?php if (!empty($_GET['err']) && (string) ($quote['status'] ?? '') === 'accepte'): ?>
            <div class="flash-error"><?= htmlspecialchars((string) $_GET['err'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <div class="blocks">
            <div class="block">
                <strong>Entreprise</strong>
                <div><?= htmlspecialchars((string) ($company['name'] ?? 'Entreprise'), ENT_QUOTES, 'UTF-8') ?></div>
                <?php if (!empty($company['email'])): ?>
                    <div class="muted"><?= htmlspecialchars((string) $company['email'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php
                    $be = trim((string) ($company['billing_email'] ?? ''));
                    $em = trim((string) ($company['email'] ?? ''));
                    if ($be !== '' && $be !== $em):
                ?>
                    <div class="muted">Email facturation : <?= htmlspecialchars($be, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <div class="muted">Numero: <?= htmlspecialchars((string) ($quote['quoteNumber'] ?? ('DEV-' . (int) ($quote['id'] ?? 0))), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="block">
                <strong>Client</strong>
                <div><?= htmlspecialchars((string) ($client['name'] ?? 'Client'), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="muted"><?= htmlspecialchars((string) ($client['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                <?php if (!empty($client['phone'])): ?><div class="muted"><?= htmlspecialchars((string) $client['phone'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                <?php if (!empty($client['address'])): ?><div class="muted"><?= htmlspecialchars((string) $client['address'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                <?php if (!empty($contact)): ?>
                    <div class="muted">Contact affaire: <?= htmlspecialchars(trim((string) ($contact['firstName'] ?? '') . ' ' . (string) ($contact['lastName'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
                    <?php if (!empty($contact['email'])): ?><div class="muted">Email contact: <?= htmlspecialchars((string) $contact['email'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
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
                    $totHt = (float) ($totalHt ?? 0);
                    $totTtc = (float) ($totalTtc ?? 0);
                    $vatAmt = isset($vatAmount) ? (float) $vatAmount : round(max(0, $totTtc - $totHt), 2);
                    $vatByRate = is_array($vatByRate ?? null) ? $vatByRate : [];
                ?>
                <tr>
                    <td colspan="5" class="totals-label">Total HT</td>
                    <td class="col-ht"><?= htmlspecialchars((string) number_format($totHt, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                    <td class="col-ttc muted">—</td>
                </tr>
                <?php foreach ($vatByRate as $vr): ?>
                    <?php if (((float) ($vr['vat'] ?? 0)) <= 0 && ((float) ($vr['ht'] ?? 0)) <= 0) {
                        continue;
                    } ?>
                    <tr>
                        <td colspan="5" class="totals-label">TVA <?= htmlspecialchars((string) number_format((float) ($vr['rate'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> % — base <?= htmlspecialchars((string) number_format((float) ($vr['ht'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                        <td class="col-ht muted">—</td>
                        <td class="col-ttc"><?= htmlspecialchars((string) number_format((float) ($vr['vat'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($vatByRate === []): ?>
                <tr>
                    <td colspan="5" class="totals-label">TVA (<?= htmlspecialchars((string) number_format((float) ($vatRate ?? 20), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> %)</td>
                    <td class="col-ht muted">—</td>
                    <td class="col-ttc"><?= htmlspecialchars((string) number_format($vatAmt, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td colspan="5" class="totals-label">Total TTC</td>
                    <td class="col-ht muted">—</td>
                    <td class="col-ttc"><?= htmlspecialchars((string) number_format($totTtc, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                </tr>
            </tfoot>
        </table>

        <?php if ((string) ($quote['status'] ?? '') !== 'accepte'): ?>
            <div class="actions">
                <h3 style="margin:0 0 8px;">Signature électronique</h3>
                <p class="muted" style="margin:0 0 10px;">Contact de l'affaire : <?= htmlspecialchars(trim((string) ($contact['firstName'] ?? '') . ' ' . (string) ($contact['lastName'] ?? '')) ?: (string) ($client['name'] ?? 'Client'), ENT_QUOTES, 'UTF-8') ?></p>
                <?php if (!empty($_GET['err'])): ?>
                    <div class="flash-error"><?= htmlspecialchars((string) $_GET['err'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if (!empty($_GET['msg'])): ?>
                    <div class="flash-success"><?= htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <form method="POST" action="<?= htmlspecialchars($basePath . '/quotes/signature/request-code', ENT_QUOTES, 'UTF-8') ?>" style="margin-bottom:10px;">
                    <input type="hidden" name="token" value="<?= htmlspecialchars((string) ($token ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <button class="btn btn-primary" type="submit">Signer - Récupérer le code</button>
                </form>
                <form method="POST" action="<?= htmlspecialchars($basePath . '/quotes/signature/confirm', ENT_QUOTES, 'UTF-8') ?>" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <input type="hidden" name="token" value="<?= htmlspecialchars((string) ($token ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <input class="input" type="text" name="signature_code" maxlength="6" placeholder="Code 6 chiffres" required>
                    <button class="btn btn-primary" type="submit">Valider</button>
                </form>
                <p class="muted" style="margin:8px 0 0;">Code valable 10 minutes.</p>
            </div>
        <?php else: ?>
            <?php
                $notes = (string) ($quote['notes'] ?? '');
                preg_match('/\[SIGNED_FIRST_NAME:([^\]]*)\]/', $notes, $mFn);
                preg_match('/\[SIGNED_LAST_NAME:([^\]]*)\]/', $notes, $mLn);
                preg_match('/\[SIGNED_EMAIL:([^\]]*)\]/', $notes, $mEm);
                preg_match('/\[SIGNED_AT:([^\]]*)\]/', $notes, $mAt);
                $signedName = trim(((string) ($mFn[1] ?? '')) . ' ' . ((string) ($mLn[1] ?? '')));
            ?>
            <div class="actions">
                <?php if (!empty($_GET['msg'])): ?>
                    <div class="flash-success"><?= htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <strong style="color:#166534;">Devis signé et validé.</strong>
                <div class="muted" style="margin-top:8px;">Signataire : <?= htmlspecialchars($signedName !== '' ? $signedName : (string) ($client['name'] ?? 'Client'), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="muted">Email : <?= htmlspecialchars((string) ($mEm[1] ?? ($client['email'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="muted">Date signature : <?= htmlspecialchars((string) ($mAt[1] ?? ($quote['acceptedAt'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
                <div style="margin-top:8px; border-top:1px solid #94a3b8; width:280px; padding-top:6px;"></div>
                <div style="margin-top:10px;">
                    <a class="btn btn-primary" href="<?= htmlspecialchars($basePath . '/quotes/signed/download?token=' . urlencode((string) ($token ?? '')), ENT_QUOTES, 'UTF-8') ?>">Télécharger le devis signé</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

