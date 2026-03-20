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
        .head{display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:12px;}
        .chip{display:inline-block; padding:4px 10px; border-radius:999px; background:#e0f2fe; color:#075985; font-weight:700; font-size:11px;}
        .blocks{width:100%; margin-top:8px;}
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
        <div class="head">
            <div>
                <h2 style="margin:0 0 6px;">Facture n° <?= htmlspecialchars((string) ($invoice['invoiceNumber'] ?? ('FA-' . (int) ($invoice['id'] ?? 0))), ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="muted">Échéance : <?= htmlspecialchars((string) ($invoice['dueDate'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <span class="chip">Facture</span>
        </div>
        <table class="blocks">
            <tr>
                <td>
                    <div class="block">
                        <strong>Entreprise</strong><br>
                        <?= htmlspecialchars((string) ($company['name'] ?? 'Pilora'), ENT_QUOTES, 'UTF-8') ?><br>
                        <span class="muted"><?= htmlspecialchars((string) ($company['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </td>
                <td>
                    <div class="block">
                        <strong>Client</strong><br>
                        <?= htmlspecialchars((string) ($client['name'] ?? 'Client'), ENT_QUOTES, 'UTF-8') ?><br>
                        <span class="muted"><?= htmlspecialchars((string) ($client['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </td>
            </tr>
        </table>
        <table>
            <thead>
                <tr><th>Prestation</th><th>Qté</th><th>PU</th><th>Total</th></tr>
            </thead>
            <tbody>
                <?php foreach (($items ?? []) as $it): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($it['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) number_format((float) ($it['quantity'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) number_format((float) ($it['unitPrice'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                        <td><?= htmlspecialchars((string) number_format((float) ($it['lineTotal'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="totals-label">Total TTC</td>
                    <td><?= htmlspecialchars((string) number_format((float) ($invoice['amountTotal'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>
