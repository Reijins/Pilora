<?php
declare(strict_types=1);
/** @var string $basePath @var array<string, mixed> $smtp @var string $csrfToken */
?>
<p class="muted" style="margin-bottom:16px;">Email envoyé lors du renouvellement d'abonnement (script cron ou facturation plateforme).</p>
<form method="post" action="<?= htmlspecialchars($basePath . '/platform/settings/smtp/save', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:720px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="settings_sub" value="email-billing">
    <p class="muted field-help">Variables : <code>{{pack_name}}</code>, <code>{{billing_cycle}}</code>, <code>{{amount}}</code>, <code>{{renew_date}}</code></p>
    <label class="label" for="billing_email_subject">Objet</label>
    <input class="input" id="billing_email_subject" name="billing_email_subject" type="text" value="<?= htmlspecialchars((string) ($smtp['billing_email_subject'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

    <label class="label" for="billing_email_body">Corps du message</label>
    <textarea class="input" id="billing_email_body" name="billing_email_body" rows="8"><?= htmlspecialchars((string) ($smtp['billing_email_body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>

    <button class="btn btn-primary" type="submit">Enregistrer le modèle</button>
</form>
