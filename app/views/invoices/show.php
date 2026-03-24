<?php
declare(strict_types=1);

use Core\Support\DateFormatter;

$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
$invoice = is_array($invoice ?? null) ? $invoice : [];
$items = is_array($items ?? null) ? $items : [];
$tot = is_array($displayTotals ?? null) ? $displayTotals : ['ht' => 0.0, 'ttc' => 0.0, 'vat_by_rate' => [], 'vat_amount' => 0.0, 'vat_rate' => 20.0];
$invoiceId = (int) ($invoice['id'] ?? 0);
$status = (string) ($invoice['status'] ?? '');
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Facture <?= htmlspecialchars((string) ($invoice['invoiceNumber'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="muted"><?= htmlspecialchars((string) ($invoice['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="card-body">
            <?php if (is_string($flashMessage ?? null) && trim((string) $flashMessage) !== ''): ?>
                <div class="alert alert-success" style="margin-bottom:12px;"><?= htmlspecialchars(rawurldecode((string) $flashMessage), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if (is_string($flashError ?? null) && trim((string) $flashError) !== ''): ?>
                <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars(rawurldecode((string) $flashError), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:16px; align-items:center;">
                <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/invoices', ENT_QUOTES, 'UTF-8') ?>">Liste des factures</a>
                <?php if (!empty($projectId) && (int) $projectId > 0): ?>
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/projects/show?projectId=' . (int) $projectId . '&invoiceId=' . $invoiceId, ENT_QUOTES, 'UTF-8') ?>">Fiche affaire</a>
                <?php endif; ?>
                <?php if (!empty($canSendInvoice)): ?>
                    <form method="POST" action="<?= htmlspecialchars($basePath . '/invoices/send', ENT_QUOTES, 'UTF-8') ?>" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="project_id" value="<?= (int) $projectId ?>">
                        <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>">
                        <button class="btn btn-primary" type="submit">Envoyer la facture au client</button>
                    </form>
                <?php endif; ?>
                <?php if (!empty($canResendInvoice)): ?>
                    <form method="POST" action="<?= htmlspecialchars($basePath . '/invoices/resend', ENT_QUOTES, 'UTF-8') ?>" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="project_id" value="<?= (int) $projectId ?>">
                        <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>">
                        <button class="btn btn-secondary" type="submit">Renvoyer la facture par e-mail</button>
                    </form>
                <?php endif; ?>
                <?php if (!empty($canAddPaymentFromShow)): ?>
                    <button
                        type="button"
                        class="btn btn-primary js-open-invoice-payment-show"
                        data-invoice-id="<?= $invoiceId ?>"
                        data-project-id="<?= (int) ($projectId ?? 0) ?>"
                        data-remaining="<?= htmlspecialchars((string) ($amountRemainingTtc ?? 0), ENT_QUOTES, 'UTF-8') ?>"
                        data-label="<?= htmlspecialchars((string) ($invoice['invoiceNumber'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    >Ajouter un paiement</button>
                <?php endif; ?>
                <?php if (!empty($canDeleteManualDraft)): ?>
                    <form method="POST" action="<?= htmlspecialchars($basePath . '/invoices/delete-manual-draft', ENT_QUOTES, 'UTF-8') ?>" style="display:inline;" onsubmit="return confirm('Supprimer définitivement ce brouillon (facture manuelle sans devis) ?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>">
                        <input type="hidden" name="project_id" value="<?= (int) ($projectId ?? 0) ?>">
                        <button class="btn btn-danger" type="submit">Supprimer le brouillon</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="kv-grid" style="margin-bottom:16px;">
                <div class="kv"><div class="kv-label">Statut</div><div class="kv-value"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></div></div>
                <div class="kv"><div class="kv-label">Échéance</div><div class="kv-value"><?= htmlspecialchars(DateFormatter::frDate(isset($invoice['dueDate']) ? (string) $invoice['dueDate'] : null), ENT_QUOTES, 'UTF-8') ?></div></div>
                <div class="kv"><div class="kv-label">Total TTC</div><div class="kv-value"><?= htmlspecialchars(number_format((float) ($tot['ttc'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</div></div>
            </div>

            <?php if (!empty($canEditDraft)): ?>
                <p style="margin:0 0 16px;">
                    <a class="btn btn-primary" href="<?= htmlspecialchars($basePath . '/invoices/edit?invoiceId=' . $invoiceId, ENT_QUOTES, 'UTF-8') ?>">Modifier la facture</a>
                </p>
            <?php endif; ?>

            <h3 style="margin:0 0 10px; font-size:16px;">Lignes</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Prestation</th>
                            <th>Qté</th>
                            <th>PU HT</th>
                            <th>TVA %</th>
                            <th>Total HT</th>
                            <th>Total TTC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                            <?php
                                $vr = (float) ($it['vatRate'] ?? 20);
                                $lTtc = isset($it['lineTtc']) ? (float) $it['lineTtc'] : round((float) ($it['lineTotal'] ?? 0) * (1 + $vr / 100), 2);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($it['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars(number_format((float) ($it['quantity'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars(number_format((float) ($it['unitPrice'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                                <td><?= htmlspecialchars(number_format($vr, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars(number_format((float) ($it['lineTotal'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                                <td><?= htmlspecialchars(number_format($lTtc, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="kv-grid" style="margin-top:14px;">
                <div class="kv"><div class="kv-label">Total HT</div><div class="kv-value"><?= htmlspecialchars(number_format((float) ($tot['ht'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</div></div>
                <?php $vbr = is_array($tot['vat_by_rate'] ?? null) ? $tot['vat_by_rate'] : []; ?>
                <?php foreach ($vbr as $vr): ?>
                    <div class="kv">
                        <div class="kv-label">TVA <?= htmlspecialchars(number_format((float) ($vr['rate'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> %</div>
                        <div class="kv-value"><?= htmlspecialchars(number_format((float) ($vr['vat'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</div>
                    </div>
                <?php endforeach; ?>
                <?php if ($vbr === []): ?>
                    <div class="kv"><div class="kv-label">TVA</div><div class="kv-value"><?= htmlspecialchars(number_format((float) ($tot['vat_amount'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</div></div>
                <?php endif; ?>
                <div class="kv"><div class="kv-label">Total TTC</div><div class="kv-value"><strong><?= htmlspecialchars(number_format((float) ($tot['ttc'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</strong></div></div>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($canAddPaymentFromShow)): ?>
<div id="invoice-payment-modal-show" class="status-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="invoice-payment-show-title">
    <div class="status-modal" style="max-width:420px;">
        <div class="status-modal-header">
            <h3 class="status-modal-title" id="invoice-payment-show-title">Enregistrer un paiement</h3>
            <button type="button" class="btn btn-secondary btn-icon js-close-invoice-payment-show" aria-label="Fermer">×</button>
        </div>
        <p class="status-modal-subtitle" id="invoice-payment-show-sub">Saisissez le montant reçu (virement, espèces, chèque).</p>
        <form method="POST" action="<?= htmlspecialchars($basePath . '/invoices/payment/manual', ENT_QUOTES, 'UTF-8') ?>" class="status-modal-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="project_id" id="invoice-pay-show-project-id" value="0">
            <input type="hidden" name="invoice_id" id="invoice-pay-show-invoice-id" value="">
            <label class="label" for="invoice-pay-show-amount">Montant TTC (€)</label>
            <input class="input" type="number" name="amount" id="invoice-pay-show-amount" step="0.01" min="0.01" required placeholder="0,00">
            <p class="muted" style="margin:0;font-size:12px;">Reste à payer : <strong id="invoice-pay-show-remaining">—</strong> €</p>
            <div class="status-reason-actions">
                <button type="button" class="btn btn-secondary js-close-invoice-payment-show">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('invoice-payment-modal-show');
    var amt = document.getElementById('invoice-pay-show-amount');
    var remEl = document.getElementById('invoice-pay-show-remaining');
    var sub = document.getElementById('invoice-payment-show-sub');
    if (!modal || !amt) return;
    function closeM() { modal.style.display = 'none'; }
    document.querySelectorAll('.js-open-invoice-payment-show').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var rem = parseFloat(String(btn.getAttribute('data-remaining') || '0').replace(',', '.')) || 0;
            document.getElementById('invoice-pay-show-project-id').value = btn.getAttribute('data-project-id') || '0';
            document.getElementById('invoice-pay-show-invoice-id').value = btn.getAttribute('data-invoice-id') || '';
            remEl.textContent = rem.toFixed(2).replace('.', ',');
            amt.max = rem > 0 ? rem.toFixed(2) : '';
            amt.value = '';
            var lab = btn.getAttribute('data-label') || '';
            if (sub) sub.textContent = lab ? ('Facture ' + lab + ' — reste ' + rem.toFixed(2).replace('.', ',') + ' €') : 'Saisissez le montant reçu.';
            modal.style.display = 'flex';
            amt.focus();
        });
    });
    document.querySelectorAll('.js-close-invoice-payment-show').forEach(function (b) {
        b.addEventListener('click', closeM);
    });
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeM();
    });
})();
</script>
<?php endif; ?>
