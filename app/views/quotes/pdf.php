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
        table{width:100%;border-collapse:collapse;margin-top:14px}
        th,td{border-bottom:1px solid #e2e8f0;padding:10px 8px;text-align:left}
        th{background:#f1f8fc}
        th:nth-child(n+2),td:nth-child(n+2){text-align:right}
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
                    <?= htmlspecialchars((string) ($company['name'] ?? 'Pilora'), ENT_QUOTES, 'UTF-8') ?><br>
                    <span class="muted"><?= htmlspecialchars((string) ($company['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span><br>
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
            <tr><th>Prestation</th><th>Qte</th><th>PU</th><th>Total</th></tr>
        </thead>
        <tbody>
            <?php foreach (($items ?? []) as $it): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($it['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) number_format((float) ($it['quantity'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) number_format((float) ($it['unitPrice'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> EUR</td>
                    <td><?= htmlspecialchars((string) number_format((float) ($it['lineTotal'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> EUR</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="totals-label">Total HT</td>
                <td><?= htmlspecialchars((string) number_format((float) ($totalHt ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
            </tr>
            <tr>
                <td colspan="3" class="totals-label">Total TTC (<?= htmlspecialchars((string) number_format((float) ($vatRate ?? 20), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> %)</td>
                <td><?= htmlspecialchars((string) number_format((float) ($totalTtc ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
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

