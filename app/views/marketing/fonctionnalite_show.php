<?php
declare(strict_types=1);
$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
$f = is_array($feature ?? null) ? $feature : [];
$relatedFeatures = is_array($relatedFeatures ?? null) ? $relatedFeatures : [];
$p = static fn (string $path): string => $basePath . $path;
$benefits = is_array($f['benefits'] ?? null) ? $f['benefits'] : [];
$slug = (string) ($f['slug'] ?? '');
?>
<article class="marketing-article marketing-feature-detail" itemscope itemtype="https://schema.org/SoftwareApplication">
    <meta itemprop="name" content="Pilora">
    <nav class="marketing-breadcrumb" aria-label="Fil d'Ariane">
        <a href="<?= htmlspecialchars($p('/'), ENT_QUOTES, 'UTF-8') ?>"><?= m_icon('house') ?> Accueil</a>
        <span aria-hidden="true">/</span>
        <a href="<?= htmlspecialchars($p('/fonctionnalites'), ENT_QUOTES, 'UTF-8') ?>">Fonctionnalités</a>
        <span aria-hidden="true">/</span>
        <span><?= htmlspecialchars((string) ($f['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
    </nav>

    <header class="marketing-feature-detail__header">
        <p class="marketing-eyebrow" style="justify-content:flex-start;"><?= m_feature_icon($slug) ?> Module Pilora</p>
        <h1 itemprop="applicationSubCategory"><?= htmlspecialchars((string) ($f['h1'] ?? $f['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="marketing-hero__lead" style="text-align:left;margin-left:0;"><?= htmlspecialchars((string) ($f['intro'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    </header>

    <section class="marketing-feature-block" aria-labelledby="benefits-title">
        <h2 id="benefits-title"><?= m_icon('check2-circle') ?> Ce que vous gagnez concrètement</h2>
        <ul class="marketing-checklist">
            <?php foreach ($benefits as $b): ?>
                <li><?= m_icon('check-circle-fill') ?> <?= htmlspecialchars((string) $b, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="marketing-feature-block marketing-feature-block--muted" aria-labelledby="forwho-title">
        <h2 id="forwho-title"><?= m_icon('people') ?> Pour qui ?</h2>
        <p><?= htmlspecialchars((string) ($f['forWho'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    </section>

    <section class="marketing-feature-block" aria-labelledby="integrated-title">
        <h2 id="integrated-title"><?= m_icon('diagram-3') ?> Intégré à votre ERP BTP</h2>
        <p>Pilora relie ce module à vos clients, affaires, utilisateurs et droits d'accès. Les données circulent entre le commercial, le terrain et la direction sans double saisie.</p>
    </section>

    <?php if ($relatedFeatures !== []): ?>
    <section class="marketing-feature-block" aria-labelledby="related-title">
        <h2 id="related-title"><?= m_icon('link-45deg') ?> Modules complémentaires</h2>
        <ul class="marketing-card-grid marketing-card-grid--compact">
            <?php foreach ($relatedFeatures as $rel): ?>
                <?php $relSlug = (string) ($rel['slug'] ?? ''); ?>
                <li class="marketing-card">
                    <span class="marketing-card__icon"><?= m_feature_icon($relSlug) ?></span>
                    <h3><?= htmlspecialchars((string) ($rel['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p><?= htmlspecialchars((string) ($rel['teaser'] ?? $rel['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <a class="marketing-card__link" href="<?= htmlspecialchars($p('/fonctionnalites/' . $relSlug), ENT_QUOTES, 'UTF-8') ?>">
                        En savoir plus <?= m_icon('arrow-right') ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <section class="marketing-cta-band marketing-cta-band--inline">
        <h2>Voir Pilora en action</h2>
        <p>Échange de 45 minutes avec un conseiller, adapté à votre activité.</p>
        <p class="marketing-hero__cta">
            <?= m_btn($p('/demo'), 'Demander une démo', 'calendar-event', 'light') ?>
            <?= m_btn($p('/tarifs'), 'Voir les tarifs', 'tags', 'secondary') ?>
        </p>
    </section>
</article>
