<?php
declare(strict_types=1);
// Variables: $permissionDenied, $invoices, $statusLabels, $statusFilter, $canMarkPaid, $canExport, $canInvoiceUpdate, $canInvoiceCreate, $csrfToken, $flashMessage, $flashError

use Core\Support\DateFormatter;
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Factures</h2>
            <p class="muted">Liste des factures (squelette). Conversion possible depuis les devis.</p>
        </div>
        <div class="card-body">
            <?php if (!empty($permissionDenied)): ?>
                <div class="alert alert-danger">Accès refusé : permissions insuffisantes.</div>
            <?php else: ?>
                <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>
                <?php if (is_string($flashMessage ?? null) && trim((string) $flashMessage) !== ''): ?>
                    <div class="alert alert-success" style="margin-bottom:12px;"><?= htmlspecialchars(rawurldecode((string) $flashMessage), ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if (is_string($flashError ?? null) && trim((string) $flashError) !== ''): ?>
                    <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars(rawurldecode((string) $flashError), ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <form method="GET" action="<?= htmlspecialchars($basePath . '/invoices', ENT_QUOTES, 'UTF-8') ?>" class="search-bar">
                    <select class="input" name="status">
                        <option value="">Tous les statuts</option>
                        <?php foreach ($statusLabels as $code => $label): ?>
                            <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" <?= ($statusFilter === $code) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary" type="submit">Filtrer</button>
                    <?php if (!empty($canExport)): ?>
                        <a
                            class="btn btn-secondary"
                            href="<?= htmlspecialchars($basePath . '/invoices/export?status=' . urlencode((string) ($statusFilter ?? '')), ENT_QUOTES, 'UTF-8') ?>"
                        >Exporter CSV</a>
                        <a
                            class="btn btn-secondary"
                            href="<?= htmlspecialchars($basePath . '/invoices/export-accounting', ENT_QUOTES, 'UTF-8') ?>"
                        >Export comptable…</a>
                    <?php endif; ?>
                </form>

                <div style="height:12px;"></div>

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>Titre</th>
                                <th>Échéance</th>
                                <th>Statut</th>
                                <th>Montant</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($invoices)): ?>
                                <?php foreach ($invoices as $inv): ?>
                                    <?php
                                        $code = (string) ($inv['status'] ?? '');
                                        $label = $statusLabels[$code] ?? $code;
                                        $badgeCls = 'inv-badge inv-badge--' . preg_replace('/[^a-z_]/', '', $code);
                                        $remaining = (float) ($inv['amountRemaining'] ?? 0);
                                        if ($remaining < 0) { $remaining = 0; }
                                        $hasRemaining = $remaining > 0;
                                        $iid = (int) ($inv['id'] ?? 0);
                                        $projId = (int) ($inv['quoteProjectId'] ?? 0);
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) ($inv['invoiceNumber'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($inv['title'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(DateFormatter::frDate(isset($inv['dueDate']) ? (string) $inv['dueDate'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="<?= htmlspecialchars($badgeCls, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td><?= htmlspecialchars((string) ($inv['amountTotal'] ?? '0'), ENT_QUOTES, 'UTF-8') ?> €</td>
                                        <td>
                                            <div class="table-actions-icons">
                                                <a class="btn btn-secondary btn-icon" href="<?= htmlspecialchars($basePath . '/invoices/show?invoiceId=' . $iid, ENT_QUOTES, 'UTF-8') ?>" title="Consulter" aria-label="Consulter la facture"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
                                                <?php if (!empty($canInvoiceUpdate) && in_array($code, ['brouillon', 'envoyee'], true)): ?>
                                                    <a class="btn btn-secondary btn-icon" href="<?= htmlspecialchars($basePath . '/invoices/edit?invoiceId=' . $iid, ENT_QUOTES, 'UTF-8') ?>" title="Modifier" aria-label="Modifier la facture"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg></a>
                                                <?php endif; ?>
                                                <?php
                                                    $invQuoteIdRow = (int) ($inv['quoteId'] ?? 0);
                                                    if (
                                                        $code === 'brouillon'
                                                        && $invQuoteIdRow === 0
                                                        && (!empty($canInvoiceUpdate) || !empty($canInvoiceCreate))
                                                    ):
                                                ?>
                                                    <form method="POST" action="<?= htmlspecialchars($basePath . '/invoices/delete-manual-draft', ENT_QUOTES, 'UTF-8') ?>" style="display:inline;" onsubmit="return confirm('Supprimer définitivement ce brouillon (facture manuelle sans devis) ?');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="invoice_id" value="<?= $iid ?>">
                                                        <input type="hidden" name="project_id" value="<?= $projId ?>">
                                                        <button class="btn btn-danger btn-icon" type="submit" title="Supprimer le brouillon" aria-label="Supprimer le brouillon"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6h14zM10 11v6M14 11v6"/></svg></button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if (!empty($canMarkPaid) && $iid > 0 && $hasRemaining && $code !== 'annulee'): ?>
                                                    <button
                                                        type="button"
                                                        class="btn btn-primary btn-icon js-open-invoice-payment-list"
                                                        title="Enregistrer un paiement"
                                                        aria-label="Enregistrer un paiement"
                                                        data-invoice-id="<?= $iid ?>"
                                                        data-project-id="<?= $projId ?>"
                                                        data-remaining="<?= htmlspecialchars((string) round($remaining, 2), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-label="<?= htmlspecialchars((string) ($inv['invoiceNumber'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    ><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></button>
                                                <?php elseif (!$hasRemaining && $code !== 'annulee'): ?>
                                                    <span class="muted">Payée</span>
                                                <?php elseif ($code === 'annulee'): ?>
                                                    <span class="muted">—</span>
                                                <?php else: ?>
                                                    <span class="muted">—</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="muted">Aucune facture.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($canMarkPaid)): ?>
                    <div id="invoice-payment-modal-list" class="status-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="invoice-payment-list-title">
                        <div class="status-modal" style="max-width:420px;">
                            <div class="status-modal-header">
                                <h3 class="status-modal-title" id="invoice-payment-list-title">Enregistrer un paiement</h3>
                                <button type="button" class="btn btn-secondary btn-icon js-close-invoice-payment-list" aria-label="Fermer">×</button>
                            </div>
                            <p class="status-modal-subtitle" id="invoice-payment-list-sub">Saisissez le montant reçu (virement, espèces, chèque).</p>
                            <form method="POST" action="<?= htmlspecialchars($basePath . '/invoices/payment/manual', ENT_QUOTES, 'UTF-8') ?>" class="status-modal-form">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="project_id" id="invoice-pay-list-project-id" value="0">
                                <input type="hidden" name="invoice_id" id="invoice-pay-list-invoice-id" value="">
                                <label class="label" for="invoice-pay-list-amount">Montant TTC (€)</label>
                                <input class="input" type="number" name="amount" id="invoice-pay-list-amount" step="0.01" min="0.01" required placeholder="0,00">
                                <p class="muted" style="margin:0;font-size:12px;">Reste à payer : <strong id="invoice-pay-list-remaining">—</strong> €</p>
                                <div class="status-reason-actions">
                                    <button type="button" class="btn btn-secondary js-close-invoice-payment-list">Annuler</button>
                                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <script>
                    (function () {
                        var modal = document.getElementById('invoice-payment-modal-list');
                        var amt = document.getElementById('invoice-pay-list-amount');
                        var remEl = document.getElementById('invoice-pay-list-remaining');
                        var sub = document.getElementById('invoice-payment-list-sub');
                        if (!modal || !amt) return;
                        function closeM() { modal.style.display = 'none'; }
                        document.addEventListener('click', function (ev) {
                            var btn = ev.target.closest('.js-open-invoice-payment-list');
                            if (!btn) return;
                            var rem = parseFloat(String(btn.getAttribute('data-remaining') || '0').replace(',', '.')) || 0;
                            document.getElementById('invoice-pay-list-project-id').value = btn.getAttribute('data-project-id') || '0';
                            document.getElementById('invoice-pay-list-invoice-id').value = btn.getAttribute('data-invoice-id') || '';
                            remEl.textContent = rem.toFixed(2).replace('.', ',');
                            amt.max = rem > 0 ? rem.toFixed(2) : '';
                            amt.value = '';
                            var lab = btn.getAttribute('data-label') || '';
                            if (sub) sub.textContent = lab ? ('Facture ' + lab + ' — reste ' + rem.toFixed(2).replace('.', ',') + ' €') : 'Saisissez le montant reçu.';
                            modal.style.display = 'flex';
                            amt.focus();
                        });
                        document.querySelectorAll('.js-close-invoice-payment-list').forEach(function (b) {
                            b.addEventListener('click', closeM);
                        });
                        modal.addEventListener('click', function (e) {
                            if (e.target === modal) closeM();
                        });
                    })();
                    </script>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
