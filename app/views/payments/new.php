<?php
declare(strict_types=1);
// Variables: $permissionDenied, $csrfToken, $invoiceId, $invoiceNumber, $remaining, $amountTotal
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Enregistrer un paiement</h2>
            <p class="muted">Facture #<?= htmlspecialchars((string) ($invoiceNumber ?? ''), ENT_QUOTES, 'UTF-8') ?>.</p>
        </div>
        <div class="card-body">
            <?php if (!empty($permissionDenied)): ?>
                <div class="alert alert-danger">Accès refusé.</div>
            <?php else: ?>
                <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>

                <div style="margin-bottom:12px;">
                    <div class="kv-grid">
                        <div class="kv">
                            <div class="kv-label">Montant total</div>
                            <div class="kv-value"><?= htmlspecialchars((string) number_format((float) ($amountTotal ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</div>
                        </div>
                        <div class="kv">
                            <div class="kv-label">Montant restant</div>
                            <div class="kv-value"><?= htmlspecialchars((string) number_format((float) ($remaining ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</div>
                        </div>
                    </div>
                </div>

                <form method="POST" action="<?= htmlspecialchars($basePath . '/payments/create', ENT_QUOTES, 'UTF-8') ?>" class="form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="invoice_id" value="<?= (int) $invoiceId ?>">

                    <label class="label" for="amount">Montant du paiement (€)</label>
                    <input
                        class="input"
                        id="amount"
                        name="amount"
                        type="number"
                        step="0.01"
                        min="0"
                        value="<?= htmlspecialchars((string) ($remaining ?? 0), ENT_QUOTES, 'UTF-8') ?>"
                        required
                    >

                    <label class="label" for="provider">Mode de paiement</label>
                    <input class="input" id="provider" name="provider" type="text" value="Manuel" required>

                    <label class="label" for="reference">Référence (optionnel)</label>
                    <input class="input" id="reference" name="reference" type="text" placeholder="Ex: VIRE-2026-001">

                    <div style="display:flex; gap:12px; flex-wrap:wrap;">
                        <button class="btn btn-primary" type="submit">Valider paiement</button>
                        <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/invoices', ENT_QUOTES, 'UTF-8') ?>">Annuler</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

