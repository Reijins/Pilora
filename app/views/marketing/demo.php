<?php

declare(strict_types=1);

$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';

$p = static fn (string $path): string => $basePath . $path;

?>

<section class="marketing-page-header">

    <h1><?= m_icon('calendar-event', 'marketing-page-header__icon') ?> Demander une démo</h1>

    <p class="muted">Présentation personnalisée de Pilora pour votre entreprise (45 min).</p>

</section>

<?php if (!empty($flashError)): ?>

    <div class="marketing-alert marketing-alert--danger" role="alert">

        <?= m_icon('exclamation-triangle-fill') ?>

        <span><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></span>

    </div>

<?php endif; ?>

<?php if (!empty($flashMessage)): ?>

    <div class="marketing-alert marketing-alert--success" role="status">

        <?= m_icon('check-circle-fill') ?>

        <span><?= htmlspecialchars((string) $flashMessage, ENT_QUOTES, 'UTF-8') ?></span>

    </div>

<?php endif; ?>

<form method="post" action="<?= htmlspecialchars($p('/demo'), ENT_QUOTES, 'UTF-8') ?>" class="marketing-form-card">

    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">

    <label class="label" for="demo-name"><?= m_icon('person') ?> Nom</label>

    <input class="input" id="demo-name" name="name" type="text" required maxlength="120" autocomplete="name">

    <label class="label" for="demo-email"><?= m_icon('envelope') ?> Email</label>

    <input class="input" id="demo-email" name="email" type="email" required maxlength="255" autocomplete="email">

    <label class="label" for="demo-company"><?= m_icon('building') ?> Entreprise</label>

    <input class="input" id="demo-company" name="company" type="text" maxlength="255" autocomplete="organization">

    <label class="label" for="demo-message"><?= m_icon('chat-left-text') ?> Votre besoin (optionnel)</label>

    <textarea class="input" id="demo-message" name="message" rows="4" maxlength="2000" placeholder="Taille d'équipe, modules recherchés…"></textarea>

    <?= m_btn_submit('Envoyer ma demande', 'send', 'primary') ?>

</form>

