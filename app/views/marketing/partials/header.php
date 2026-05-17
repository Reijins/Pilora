<?php

declare(strict_types=1);

/** @var string $basePath */

$navPath = static function (string $path) use ($basePath): string {

    return $basePath . $path;

};

$brandLogoUrl = isset($brandLogoUrl) && is_string($brandLogoUrl) ? trim($brandLogoUrl) : '';

?>

<header class="marketing-header">

    <nav class="marketing-header__inner" aria-label="Navigation principale">

        <a class="marketing-logo" href="<?= htmlspecialchars($navPath('/'), ENT_QUOTES, 'UTF-8') ?>">

            <?php if ($brandLogoUrl !== ''): ?>

                <img class="marketing-logo__img" src="<?= htmlspecialchars($brandLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Pilora" width="44" height="44">

            <?php else: ?>

                <span class="marketing-logo__icon" aria-hidden="true"><?= m_icon('layers-fill') ?></span>

                <span>Pilora</span>

            <?php endif; ?>

        </a>

        <button type="button" class="marketing-nav-toggle" data-marketing-nav-toggle aria-expanded="false" aria-controls="marketing-nav-panel" aria-label="Ouvrir le menu">

            <?= m_icon('list') ?>

        </button>

        <div class="marketing-nav-panel" id="marketing-nav-panel" data-marketing-nav-panel>

            <ul class="marketing-nav">

                <li><?= m_nav($navPath('/fonctionnalites'), 'Fonctionnalités', 'grid') ?></li>

                <li><?= m_nav($navPath('/tarifs'), 'Tarifs', 'tags') ?></li>

                <li><?= m_nav($navPath('/faq'), 'FAQ', 'question-circle') ?></li>

                <li><?= m_nav($navPath('/demo'), 'Démo', 'calendar-event') ?></li>

            </ul>

            <p class="marketing-header__actions">

                <?= m_btn($navPath('/login'), 'Connexion', 'box-arrow-in-right', 'ghost', false, ['class' => 'm-btn--sm']) ?>

                <?= m_btn($navPath('/inscription'), "S'inscrire", 'rocket-takeoff', 'primary', false, ['class' => 'm-btn--sm']) ?>

            </p>

        </div>

    </nav>

</header>

