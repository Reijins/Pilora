<?php
declare(strict_types=1);
/** @var string $basePath @var array<string, mixed> $platformBillingSettings @var string $csrfToken */
?>
<p class="muted" style="margin-bottom:12px;">Coordonnées légales et bancaires affichées sur les factures (entête Pilora). Chaque société peut aussi renseigner sa clé Stripe dans <strong>Paramètres → Paramètres généraux</strong> ; la clé ci-dessous sert de secours si la clé société est vide.</p>
<form method="post" action="<?= htmlspecialchars($basePath . '/platform/settings/billing/save', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:640px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="settings_sub" value="legal">
    <label class="label" for="legal_name">Raison sociale / nom commercial</label>
    <input class="input" id="legal_name" name="legal_name" type="text" value="<?= htmlspecialchars((string) ($platformBillingSettings['legal_name'] ?? 'Pilora'), ENT_QUOTES, 'UTF-8') ?>">

    <label class="label" for="address">Adresse postale</label>
    <textarea class="input" id="address" name="address" rows="3" placeholder="Rue, CP, ville"><?= htmlspecialchars((string) ($platformBillingSettings['address'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>

    <section class="settings-grid" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
        <p class="field" style="margin:0;">
            <label class="label" for="siret">SIRET</label>
            <input class="input" id="siret" name="siret" type="text" value="<?= htmlspecialchars((string) ($platformBillingSettings['siret'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </p>
        <p class="field" style="margin:0;">
            <label class="label" for="phone">Téléphone</label>
            <input class="input" id="phone" name="phone" type="text" value="<?= htmlspecialchars((string) ($platformBillingSettings['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </p>
    </section>

    <label class="label" for="email">Email affiché</label>
    <input class="input" id="email" name="email" type="email" value="<?= htmlspecialchars((string) ($platformBillingSettings['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

    <label class="label" for="website">Site web (optionnel)</label>
    <input class="input" id="website" name="website" type="url" placeholder="https://" value="<?= htmlspecialchars((string) ($platformBillingSettings['website'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

    <label class="label" for="rib">RIB / IBAN (texte libre)</label>
    <textarea class="input" id="rib" name="rib" rows="4" placeholder="IBAN, BIC…"><?= htmlspecialchars((string) ($platformBillingSettings['rib'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>

    <label class="label" for="stripe_secret_key">Clé secrète Stripe (sk_…)</label>
    <input class="input" id="stripe_secret_key" name="stripe_secret_key" type="password" autocomplete="new-password" placeholder="Laisser vide pour ne pas modifier la valeur enregistrée" value="">
    <p class="muted field-help" style="margin-top:4px;">Saisissez une nouvelle clé uniquement pour la remplacer ; la valeur actuelle n'est pas affichée pour des raisons de sécurité.</p>

    <label class="label" for="stripe_webhook_secret">Secret webhook Stripe inscriptions (whsec_…)</label>
    <input class="input" id="stripe_webhook_secret" name="stripe_webhook_secret" type="password" autocomplete="new-password" placeholder="Laisser vide pour conserver la valeur enregistrée" value="">
    <p class="muted field-help" style="margin-top:4px;">Endpoint : <code>POST …/webhooks/stripe/platform</code> — événement <code>checkout.session.completed</code>.</p>

    <button class="btn btn-primary" type="submit">Enregistrer</button>
</form>
