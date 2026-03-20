<?php
declare(strict_types=1);
$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
$c = $company ?? [];
$id = (int) ($c['id'] ?? 0);
$canBilling = !empty($canBilling);
$canImpersonate = !empty($canImpersonate);
$renews = isset($c['subscriptionRenewsAt']) && $c['subscriptionRenewsAt'] !== null && $c['subscriptionRenewsAt'] !== ''
    ? (string) $c['subscriptionRenewsAt']
    : '';
if ($renews !== '' && strlen($renews) >= 10) {
    $renews = substr($renews, 0, 10);
}
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2><?= htmlspecialchars((string) ($c['name'] ?? 'Société'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="muted"><a href="<?= htmlspecialchars($basePath . '/platform/companies', ENT_QUOTES, 'UTF-8') ?>">← Liste</a></p>
        </div>
        <div class="card-body">
            <h3 class="h3-section">Informations générales</h3>
            <form method="post" action="<?= htmlspecialchars($basePath . '/platform/companies/update', ENT_QUOTES, 'UTF-8') ?>" class="form-stack">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                <label class="label">Nom</label>
                <input class="input" type="text" name="name" required maxlength="255" value="<?= htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                <label class="label">Email facturation</label>
                <input class="input" type="email" name="billing_email" maxlength="255" value="<?= htmlspecialchars((string) ($c['billingEmail'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                <label class="label">Statut</label>
                <?php $st = (string) ($c['status'] ?? 'active'); ?>
                <select class="input" name="status">
                    <?php foreach (['active', 'suspended', 'disabled'] as $opt): ?>
                        <option value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>" <?= $st === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>

                <div style="margin-top:16px;">
                    <button class="btn btn-primary" type="submit">Enregistrer</button>
                </div>
            </form>

            <?php if ($canImpersonate): ?>
                <div style="margin-top:24px; padding-top:24px; border-top:1px solid var(--border);">
                    <h3 class="h3-section">Impersonation</h3>
                    <p class="muted">Ouvre une session en contexte de cette société (données filtrées sur le tenant cible).</p>
                    <form method="post" action="<?= htmlspecialchars($basePath . '/platform/impersonate/start', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="company_id" value="<?= $id ?>">
                        <button class="btn btn-secondary" type="submit">Agir pour cette société</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($canBilling): ?>
                <div style="margin-top:24px; padding-top:24px; border-top:1px solid var(--border);">
                    <h3 class="h3-section">Facturation / abonnement</h3>
                    <form method="post" action="<?= htmlspecialchars($basePath . '/platform/companies/billing', ENT_QUOTES, 'UTF-8') ?>" class="form-stack">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="id" value="<?= $id ?>">

                        <label class="label">Plan (libellé)</label>
                        <input class="input" type="text" name="billing_plan" maxlength="80" value="<?= htmlspecialchars((string) ($c['billingPlan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                        <label class="label">Statut billing</label>
                        <?php $bs = (string) ($c['billingStatus'] ?? ''); ?>
                        <select class="input" name="billing_status">
                            <option value="">—</option>
                            <?php foreach (['trial', 'active', 'past_due', 'cancelled'] as $opt): ?>
                                <option value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>" <?= $bs === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label class="label">Sièges max</label>
                        <input class="input" type="number" name="max_seats" min="0" value="<?= htmlspecialchars((string) ($c['maxSeats'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                        <label class="label">Renouvellement</label>
                        <input class="input" type="date" name="subscription_renews_at" value="<?= htmlspecialchars($renews, ENT_QUOTES, 'UTF-8') ?>">

                        <label class="label">Réf. externe (Stripe…)</label>
                        <input class="input" type="text" name="external_billing_ref" maxlength="120" value="<?= htmlspecialchars((string) ($c['externalBillingRef'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                        <div style="margin-top:16px;">
                            <button class="btn btn-primary" type="submit">Enregistrer la facturation</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
