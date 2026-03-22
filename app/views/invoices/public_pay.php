<?php
declare(strict_types=1);
/** @var array $invoice @var array $items @var array $client @var array $contacts @var array|null $project */
/** @var array $company @var array $smtp @var array $totals */
/** @var string $token @var string $publicBase @var bool $stripeOk @var bool $canPay */
/** @var float $amountRemaining @var bool $cancelled @var bool $paidJustNow */
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Facture en ligne</title>
    <style>
        body{font-family:Inter,Arial,sans-serif;background:#f4f8fb;color:#0f172a;margin:0;padding:24px}
        .wrap{max-width:900px;margin:0 auto;background:#fff;border:1px solid #d9e7f1;border-radius:18px;padding:22px;box-shadow:0 20px 40px rgba(15,23,42,.06)}
        .muted{color:#64748b;font-size:13px}
        table{width:100%;border-collapse:collapse;margin-top:14px}
        th,td{border-bottom:1px solid #e2e8f0;padding:10px 8px;text-align:left}
        th{background:#f1f8fc}
        th:nth-child(n+2),td:nth-child(n+2){text-align:right}
        th.col-unit,td.col-unit{text-align:center}
        tfoot td{border-top:2px solid #dbe7ef;font-weight:700;background:#f8fbfd}
        .blocks{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px}
        @media(max-width:720px){.blocks{grid-template-columns:1fr}}
        .block{border:1px solid #e2e8f0;border-radius:12px;padding:12px}
        .btn{display:inline-flex;align-items:center;justify-content:center;padding:12px 18px;border-radius:12px;border:1px solid transparent;cursor:pointer;font-weight:700;text-decoration:none}
        .btn-primary{background:#58B1D6;color:#fff}
        .btn-primary:hover{background:#3d9bc2}
        .flash-ok{background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;padding:12px;border-radius:12px;margin-bottom:12px;font-weight:600}
        .flash-warn{background:#fffbeb;border:1px solid #fcd34d;color:#92400e;padding:12px;border-radius:12px;margin-bottom:12px}
    </style>
</head>
<body>
<div class="wrap">
    <h2 style="margin:0 0 6px;">Facture n° <?= htmlspecialchars((string) ($invoice['invoiceNumber'] ?? ('FA-' . (int) ($invoice['id'] ?? 0))), ENT_QUOTES, 'UTF-8') ?></h2>
    <?php if (!empty($paidJustNow)): ?>
        <div class="flash-ok">Paiement enregistré. Merci !</div>
    <?php endif; ?>
    <?php if (!empty($cancelled)): ?>
        <div class="flash-warn">Paiement annulé. Vous pouvez réessayer ci-dessous.</div>
    <?php endif; ?>
    <p class="muted">Échéance : <?= htmlspecialchars((string) ($invoice['dueDate'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        · Statut : <?= htmlspecialchars((string) ($invoice['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>

    <div class="blocks">
        <div class="block">
            <strong><?= htmlspecialchars((string) ($company['name'] ?? 'Entreprise'), ENT_QUOTES, 'UTF-8') ?></strong>
            <?php if (!empty($company['email'])): ?>
                <div class="muted" style="margin-top:6px;"><?= htmlspecialchars((string) $company['email'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php
                $billEm = trim((string) ($company['billing_email'] ?? ''));
                $mainEm = trim((string) ($company['email'] ?? ''));
                if ($billEm !== '' && $billEm !== $mainEm):
            ?>
                <div class="muted">Email facturation : <?= htmlspecialchars($billEm, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>
        <div class="block">
            <strong>Client</strong>
            <div><?= htmlspecialchars((string) ($client['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
            <?php if (!empty($client['address'])): ?>
                <div class="muted" style="white-space:pre-line;"><?= htmlspecialchars((string) $client['address'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if (!empty($client['siret'])): ?>
                <div class="muted">SIRET : <?= htmlspecialchars((string) $client['siret'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if (!empty($client['email'])): ?>
                <div class="muted"><?= htmlspecialchars((string) $client['email'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if (!empty($client['phone'])): ?>
                <div class="muted"><?= htmlspecialchars((string) $client['phone'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if (($contacts ?? []) !== []): ?>
                <div style="margin-top:8px;font-size:12px;"><strong>Contacts</strong>
                    <ul style="margin:6px 0 0 16px;padding:0;">
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
    </div>

    <table>
        <thead>
            <tr><th>Prestation</th><th>Qté</th><th class="col-unit">Unité</th><th>PU HT</th><th>Total HT</th></tr>
        </thead>
        <tbody>
            <?php foreach (($items ?? []) as $it): ?>
                <?php $uLab = trim((string) ($it['unitLabel'] ?? '')); ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($it['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) number_format((float) ($it['quantity'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="col-unit"><?= $uLab !== '' ? htmlspecialchars($uLab, ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td><?= htmlspecialchars((string) number_format((float) ($it['unitPrice'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                    <td><?= htmlspecialchars((string) number_format((float) ($it['lineTotal'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="text-align:right;">Total HT</td>
                <td><?= htmlspecialchars(number_format((float) ($totals['ht'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
            </tr>
            <tr>
                <td colspan="4" style="text-align:right;">TVA (<?= htmlspecialchars((string) number_format((float) ($totals['vat_rate'] ?? 20), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> %)</td>
                <td><?= htmlspecialchars(number_format((float) ($totals['vat_amount'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
            </tr>
            <tr>
                <td colspan="4" style="text-align:right;">Total TTC</td>
                <td><?= htmlspecialchars(number_format((float) ($totals['ttc'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
            </tr>
        </tfoot>
    </table>

    <p style="margin-top:16px;"><strong>Reste à payer (TTC)</strong> : <?= htmlspecialchars(number_format($amountRemaining, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</p>

    <?php if ($canPay): ?>
        <form method="POST" action="<?= htmlspecialchars(rtrim($publicBase, '/') . '/invoice/pay/stripe', ENT_QUOTES, 'UTF-8') ?>" style="margin-top:14px;">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
            <button class="btn btn-primary" type="submit">Payer par carte (Stripe)</button>
        </form>
    <?php elseif (!$stripeOk): ?>
        <p class="muted" style="margin-top:12px;">Paiement en ligne : activez Stripe et renseignez la clé secrète dans <strong>Paramètres → Paramètres généraux</strong> (compte entreprise).</p>
    <?php endif; ?>
</div>
</body>
</html>
