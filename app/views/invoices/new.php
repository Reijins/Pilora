<?php
declare(strict_types=1);
// Variables: $permissionDenied, $csrfToken, $quoteId, $quoteTitle, $quoteNumber, $clientId, $invoiceTotals, $dueDateYmd
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Créer une facture</h2>
            <p class="muted">Depuis un devis (squelette) - statut : brouillon.</p>
        </div>
        <div class="card-body">
            <?php if (!empty($permissionDenied)): ?>
                <div class="alert alert-danger">Accès refusé : permissions insuffisantes.</div>
            <?php else: ?>
                <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>

                <div style="margin-bottom:12px;">
                    <div class="kv-grid">
                        <div class="kv">
                            <div class="kv-label">Devis</div>
                            <div class="kv-value"><?= htmlspecialchars((string) ($quoteNumber ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="kv">
                            <div class="kv-label">Client</div>
                            <div class="kv-value">#<?= (int) $clientId ?></div>
                        </div>
                    </div>
                </div>

                <form method="POST" action="<?= htmlspecialchars($basePath . '/invoices/create', ENT_QUOTES, 'UTF-8') ?>" class="form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="quote_id" value="<?= (int) $quoteId ?>">

                    <label class="label" for="title">Titre de la facture</label>
                    <input class="input" id="title" name="title" type="text" value="<?= htmlspecialchars((string) ($quoteTitle ?? 'Facture'), ENT_QUOTES, 'UTF-8') ?>" disabled>

                    <label class="label" for="due_date">Date d’échéance</label>
                    <input class="input" id="due_date" name="due_date" type="date" value="<?= htmlspecialchars((string) ($dueDateYmd ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>

                    <label class="label">Montants (depuis le devis)</label>
                    <div class="kv-grid" style="margin-bottom:10px;">
                        <div class="kv"><div class="kv-label">Total HT</div><div class="kv-value"><?= htmlspecialchars(number_format((float) ($invoiceTotals['ht'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</div></div>
                        <div class="kv"><div class="kv-label">TVA (<?= htmlspecialchars((string) ($invoiceTotals['vat_rate'] ?? 20), ENT_QUOTES, 'UTF-8') ?> %)</div><div class="kv-value"><?= htmlspecialchars(number_format((float) ($invoiceTotals['vat_amount'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</div></div>
                        <div class="kv"><div class="kv-label">Total TTC (facturé)</div><div class="kv-value"><strong><?= htmlspecialchars(number_format((float) ($invoiceTotals['ttc'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</strong></div></div>
                    </div>

                    <label class="label" for="notes">Notes (optionnel)</label>
                    <input class="input" id="notes" name="notes" type="text">

                    <div style="display:flex; gap:12px; flex-wrap:wrap;">
                        <button class="btn btn-primary" type="submit">Créer la facture</button>
                        <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/invoices', ENT_QUOTES, 'UTF-8') ?>">Annuler</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

