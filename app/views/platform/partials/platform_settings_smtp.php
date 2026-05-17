<?php
declare(strict_types=1);
/** @var string $basePath @var array<string, mixed> $smtp @var string $csrfToken */
?>
<p class="muted" style="margin-bottom:16px;">Serveur d'envoi des emails émis par Pilora vers vos sociétés clientes (facturation abonnement, bienvenue).</p>
<form method="post" action="<?= htmlspecialchars($basePath . '/platform/settings/smtp/save', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:720px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="settings_sub" value="smtp">
    <section class="contact-form-grid" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
        <p class="field" style="margin:0;">
            <label class="label" for="smtp_host">Serveur SMTP</label>
            <input class="input" id="smtp_host" name="smtp_host" type="text" value="<?= htmlspecialchars((string) ($smtp['host'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </p>
        <p class="field" style="margin:0;">
            <label class="label" for="smtp_port">Port</label>
            <input class="input" id="smtp_port" name="smtp_port" type="number" min="1" value="<?= (int) ($smtp['port'] ?? 587) ?>" required>
        </p>
        <p class="field" style="margin:0;">
            <label class="label" for="smtp_username">Utilisateur</label>
            <input class="input" id="smtp_username" name="smtp_username" type="text" value="<?= htmlspecialchars((string) ($smtp['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </p>
        <p class="field" style="margin:0;">
            <label class="label" for="smtp_auth_enabled">Authentification</label>
            <?php $authEnabled = (string) ($smtp['auth_enabled'] ?? '1'); ?>
            <select class="input" id="smtp_auth_enabled" name="smtp_auth_enabled">
                <option value="1" <?= $authEnabled !== '0' ? 'selected' : '' ?>>Oui</option>
                <option value="0" <?= $authEnabled === '0' ? 'selected' : '' ?>>Non</option>
            </select>
        </p>
        <p class="field" style="margin:0;">
            <label class="label" for="smtp_password">Mot de passe</label>
            <input class="input" id="smtp_password" name="smtp_password" type="password" autocomplete="new-password" placeholder="Laisser vide pour conserver">
        </p>
        <p class="field" style="margin:0;">
            <label class="label" for="smtp_encryption">Sécurité</label>
            <?php $enc = (string) ($smtp['encryption'] ?? 'tls'); ?>
            <select class="input" id="smtp_encryption" name="smtp_encryption">
                <option value="none" <?= $enc === 'none' ? 'selected' : '' ?>>Aucune</option>
                <option value="ssl" <?= $enc === 'ssl' ? 'selected' : '' ?>>SSL</option>
                <option value="tls" <?= $enc === 'tls' ? 'selected' : '' ?>>TLS</option>
            </select>
        </p>
        <p class="field" style="margin:0;">
            <label class="label" for="smtp_from_email">Email expéditeur</label>
            <input class="input" id="smtp_from_email" name="smtp_from_email" type="email" value="<?= htmlspecialchars((string) ($smtp['from_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </p>
        <p class="field" style="margin:0;grid-column:1/-1;">
            <label class="label" for="smtp_from_name">Nom expéditeur</label>
            <input class="input" id="smtp_from_name" name="smtp_from_name" type="text" value="<?= htmlspecialchars((string) ($smtp['from_name'] ?? 'Pilora'), ENT_QUOTES, 'UTF-8') ?>">
        </p>
    </section>
    <button class="btn btn-primary" type="submit">Enregistrer SMTP</button>
</form>
<form method="post" action="<?= htmlspecialchars($basePath . '/platform/settings/smtp/test', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:480px;margin-top:20px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <label class="label" for="smtp_test_email">Email de test</label>
    <input class="input" id="smtp_test_email" name="smtp_test_email" type="email" value="<?= htmlspecialchars((string) ($smtp['from_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
    <button class="btn btn-secondary" type="submit" style="margin-top:8px;">Tester SMTP</button>
</form>
