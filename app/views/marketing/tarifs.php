<?php

declare(strict_types=1);

$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';

$packs = is_array($packs ?? null) ? $packs : [];

$p = static fn (string $path): string => $basePath . $path;

?>

<section class="marketing-page-header">

    <h1><?= m_icon('tags', 'marketing-page-header__icon') ?> Tarifs transparents pour votre entreprise BTP</h1>

    <p class="muted">Des packs adaptés à la taille de votre équipe : essai gratuit ou abonnement mensuel, sans engagement caché. Créez votre espace en ligne en quelques minutes.</p>

</section>

<section class="marketing-feature-intro">

    <p>Chaque pack inclut l'accès à votre espace Pilora dédié, le multi-utilisateurs et les mises à jour de l'application.</p>

</section>

<ul class="marketing-pricing-grid">

<?php foreach ($packs as $i => $pack): ?>

    <?php

    $price = (float) ($pack['price'] ?? 0);

    $isTrial = $price <= 0;

    $featured = !$isTrial && $i === 1;

    ?>

    <li class="marketing-pricing-card<?= $featured ? ' marketing-pricing-card--featured' : '' ?>">

        <?php if ($featured): ?>

            <span class="marketing-pricing-card__badge">Populaire</span>

        <?php endif; ?>

        <span class="marketing-pricing-card__icon"><?= m_icon($isTrial ? 'gift' : 'star-fill') ?></span>

        <h2><?= htmlspecialchars((string) ($pack['name'] ?? 'Pack'), ENT_QUOTES, 'UTF-8') ?></h2>

        <p class="marketing-pricing-card__price">

            <?php if ($isTrial): ?>

                Essai gratuit

            <?php else: ?>

                <?= htmlspecialchars(number_format($price, 0, ',', ' '), ENT_QUOTES, 'UTF-8') ?> &euro; <span>/ mois</span>

            <?php endif; ?>

        </p>

        <p class="marketing-pricing-card__meta"><?= m_icon('people') ?> Jusqu'à <?= (int) ($pack['maxUsers'] ?? 0) ?> utilisateurs</p>

        <div class="marketing-pricing-card__cta">
            <?= m_btn(
                $p('/inscription?pack_id=' . (int) ($pack['id'] ?? 0)),
                $isTrial ? "Commencer l'essai" : "S'inscrire",
                $isTrial ? 'gift' : 'cart-check',
                $isTrial ? 'secondary' : 'primary',
                true
            ) ?>
        </div>

    </li>

<?php endforeach; ?>

</ul>

<?php if ($packs === []): ?>

    <p class="muted marketing-center">Aucun pack configuré pour le moment.</p>

<?php endif; ?>

<section class="marketing-cta-band marketing-cta-band--inline" style="margin-top:2.5rem;">

    <h2>Une question sur les offres ?</h2>

    <p class="marketing-hero__cta" style="margin:0;">

        <?= m_btn($p('/demo'), 'Parler à un conseiller', 'chat-dots', 'light') ?>

        <?= m_btn($p('/faq'), 'Consulter la FAQ', 'question-circle', 'secondary') ?>

    </p>

</section>

