<?php
declare(strict_types=1);
$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
$features = is_array($features ?? null) ? $features : [];
$p = static fn (string $path): string => $basePath . $path;
?>
<section class="marketing-page-header">
    <h1><?= m_icon('grid', 'marketing-page-header__icon') ?> Fonctionnalités Pilora pour le BTP</h1>
    <p class="muted">Un ERP métier qui couvre le cycle commercial et opérationnel : du devis à la rentabilité chantier.</p>
</section>

<section class="marketing-feature-intro">
    <p>Pilora est conçu pour les entreprises du bâtiment qui en ont assez des tableurs et des logiciels généralistes.</p>
</section>

<ul class="marketing-card-grid">
<?php foreach ($features as $f): ?>
    <?php $slug = (string) ($f['slug'] ?? ''); ?>
    <li class="marketing-card marketing-card--feature">
        <span class="marketing-card__icon"><?= m_feature_icon($slug) ?></span>
        <h2><a href="<?= htmlspecialchars($p('/fonctionnalites/' . $slug), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($f['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a></h2>
        <p><?= htmlspecialchars((string) ($f['teaser'] ?? $f['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
        <a class="marketing-card__link" href="<?= htmlspecialchars($p('/fonctionnalites/' . $slug), ENT_QUOTES, 'UTF-8') ?>">
            Découvrir le module <?= m_icon('arrow-right') ?>
        </a>
    </li>
<?php endforeach; ?>
</ul>

<section class="marketing-cta-band marketing-cta-band--inline">
    <h2>Besoin d'un avis sur votre organisation ?</h2>
    <p class="marketing-hero__cta" style="margin:0;">
        <?= m_btn($p('/demo'), 'Demander une démo', 'calendar-event', 'light') ?>
        <?= m_btn($p('/inscription'), 'Essayer Pilora', 'rocket-takeoff', 'secondary') ?>
    </p>
</section>
