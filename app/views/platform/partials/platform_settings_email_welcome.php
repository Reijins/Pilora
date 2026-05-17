<?php
declare(strict_types=1);
/** @var string $basePath @var array<string, mixed> $smtp @var string $csrfToken */
?>
<p class="muted" style="margin-bottom:16px;">Email envoyé à la création d'une nouvelle société cliente (utilisateur initial ou email de facturation).</p>
<form method="post" action="<?= htmlspecialchars($basePath . '/platform/settings/smtp/save', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:720px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="settings_sub" value="email-welcome">
    <p class="muted field-help">Variables : <code>{{company_name}}</code>, <code>{{login_email}}</code>, <code>{{login_url}}</code></p>
    <label class="label" for="company_welcome_subject">Objet</label>
    <input class="input" id="company_welcome_subject" name="company_welcome_subject" type="text" value="<?= htmlspecialchars((string) ($smtp['company_welcome_subject'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

    <label class="label" for="company_welcome_body">Corps du message</label>
    <textarea class="input" id="company_welcome_body" name="company_welcome_body" rows="10"><?= htmlspecialchars((string) ($smtp['company_welcome_body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>

    <button class="btn btn-primary" type="submit">Enregistrer le modèle</button>
</form>
