<?php
declare(strict_types=1);
/** @var string $basePath @var array<string, mixed> $smtp @var string $csrfToken */
?>
<p class="muted" style="margin-bottom:16px;">Emails liés aux demandes de démo depuis le site public (<code>/demo</code>). L'accusé de réception part vers le contact ; la notification interne vers l'adresse ci-dessous (sinon l'email affiché dans les coordonnées légales).</p>
<form method="post" action="<?= htmlspecialchars($basePath . '/platform/settings/smtp/save', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:720px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="settings_sub" value="email-demo">

    <label class="label" for="demo_notify_email">Email interne (notifications)</label>
    <input class="input" id="demo_notify_email" name="demo_notify_email" type="email" placeholder="commercial@votre-domaine.fr" value="<?= htmlspecialchars((string) ($smtp['demo_notify_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

    <h3 style="margin:1.5rem 0 0.5rem;font-size:1rem;">Notification équipe</h3>
    <p class="muted field-help">Variables : <code>{{contact_name}}</code>, <code>{{contact_email}}</code>, <code>{{company_name}}</code>, <code>{{message}}</code>, <code>{{request_id}}</code></p>
    <label class="label" for="demo_notify_subject">Objet</label>
    <input class="input" id="demo_notify_subject" name="demo_notify_subject" type="text" value="<?= htmlspecialchars((string) ($smtp['demo_notify_subject'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

    <label class="label" for="demo_notify_body">Corps</label>
    <textarea class="input" id="demo_notify_body" name="demo_notify_body" rows="8"><?= htmlspecialchars((string) ($smtp['demo_notify_body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>

    <h3 style="margin:1.5rem 0 0.5rem;font-size:1rem;">Accusé de réception (contact)</h3>
    <p class="muted field-help">Variables : <code>{{contact_name}}</code>, <code>{{company_name}}</code></p>
    <label class="label" for="demo_ack_subject">Objet</label>
    <input class="input" id="demo_ack_subject" name="demo_ack_subject" type="text" value="<?= htmlspecialchars((string) ($smtp['demo_ack_subject'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

    <label class="label" for="demo_ack_body">Corps</label>
    <textarea class="input" id="demo_ack_body" name="demo_ack_body" rows="8"><?= htmlspecialchars((string) ($smtp['demo_ack_body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>

    <button class="btn btn-primary" type="submit">Enregistrer</button>
</form>
