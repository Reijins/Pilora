<?php
declare(strict_types=1);
$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
$p = static fn (string $path): string => $basePath . $path;
$demoVideoUrl = trim((string) ($demoVideoUrl ?? ''));
$homeFeatures = [
    ['slug' => 'devis', 'title' => 'Devis', 'text' => 'Création, envoi PDF, relances et suivi commercial.'],
    ['slug' => 'factures', 'title' => 'Factures', 'text' => 'Facturation, paiement en ligne et export comptable.'],
    ['slug' => 'chantiers', 'title' => 'Chantiers', 'text' => 'Suivi terrain, photos, comptes rendus et équipes.'],
    ['slug' => 'rentabilite', 'title' => 'Rentabilité', 'text' => 'Marges et indicateurs par affaire.'],
];
?>
<section class="marketing-hero">
    <p class="marketing-eyebrow"><?= m_icon('building') ?> ERP BTP tout-en-un</p>
    <h1 class="marketing-hero__title">Pilotez devis, chantiers et facturation dans un seul outil</h1>
    <p class="marketing-hero__lead">Pilora aide les entreprises du bâtiment à gagner du temps sur l'administratif, suivre la rentabilité des chantiers et encaisser plus vite.</p>
    <p class="marketing-hero__cta">
        <?= m_btn($p('/inscription'), 'Créer mon espace', 'rocket-takeoff', 'primary') ?>
        <?= m_btn($p('/tarifs'), 'Voir les tarifs', 'tags', 'secondary') ?>
    </p>
</section>

<?php if ($demoVideoUrl !== ''): ?>
<section class="marketing-video" aria-labelledby="video-demo-title">
    <h2 id="video-demo-title" class="marketing-section-title"><?= m_icon('play-circle') ?> Découvrez Pilora en vidéo</h2>
    <figure class="marketing-video__embed">
        <iframe src="<?= htmlspecialchars($demoVideoUrl, ENT_QUOTES, 'UTF-8') ?>" title="Démonstration Pilora" loading="lazy" allowfullscreen></iframe>
    </figure>
</section>
<?php endif; ?>

<section class="marketing-steps" aria-labelledby="steps-title">
    <h2 id="steps-title" class="marketing-section-title">Comment ça marche</h2>
    <ol class="marketing-steps__list">
        <li>
            <span class="marketing-steps__icon"><?= m_icon('rocket-takeoff') ?></span>
            <strong>Créez votre espace</strong>
            <span>Choisissez un pack adapté à votre équipe et configurez votre entreprise.</span>
        </li>
        <li>
            <span class="marketing-steps__icon"><?= m_icon('diagram-3') ?></span>
            <strong>Centralisez vos affaires</strong>
            <span>Clients, devis, chantiers et factures partagent les mêmes données.</span>
        </li>
        <li>
            <span class="marketing-steps__icon"><?= m_icon('graph-up-arrow') ?></span>
            <strong>Pilotez la rentabilité</strong>
            <span>Tableaux de bord et indicateurs pour décider avant la fin du chantier.</span>
        </li>
    </ol>
</section>

<section class="marketing-features-preview">
    <h2 class="marketing-section-title">Tout ce dont votre entreprise a besoin</h2>
    <ul class="marketing-card-grid">
        <?php foreach ($homeFeatures as $hf): ?>
        <li class="marketing-card">
            <span class="marketing-card__icon"><?= m_feature_icon((string) $hf['slug']) ?></span>
            <h3><?= htmlspecialchars($hf['title'], ENT_QUOTES, 'UTF-8') ?></h3>
            <p><?= htmlspecialchars($hf['text'], ENT_QUOTES, 'UTF-8') ?></p>
            <a class="marketing-card__link" href="<?= htmlspecialchars($p('/fonctionnalites/' . $hf['slug']), ENT_QUOTES, 'UTF-8') ?>">
                En savoir plus <?= m_icon('arrow-right') ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    <p class="marketing-center">
        <?= m_btn($p('/fonctionnalites'), 'Toutes les fonctionnalités', 'grid', 'ghost') ?>
    </p>
</section>

<section class="marketing-trust" aria-labelledby="trust-title">
    <h2 id="trust-title" class="marketing-section-title">Pensé pour le terrain et le bureau</h2>
    <ul class="marketing-trust__grid">
        <li>
            <span class="marketing-trust__icon"><?= m_icon('people-fill') ?></span>
            <strong>Multi-utilisateurs</strong>
            <p>Rôles et permissions par profil : commercial, conducteur, comptable, dirigeant.</p>
        </li>
        <li>
            <span class="marketing-trust__icon"><?= m_icon('shield-lock-fill') ?></span>
            <strong>Données isolées</strong>
            <p>Chaque entreprise dispose de son espace sécurisé, sans mélange avec d'autres clients.</p>
        </li>
        <li>
            <span class="marketing-trust__icon"><?= m_icon('arrow-up-circle-fill') ?></span>
            <strong>Évolutif</strong>
            <p>Commencez par les devis, étendez au planning et à la rentabilité quand vous êtes prêts.</p>
        </li>
    </ul>
</section>

<section class="marketing-cta-band">
    <h2 class="marketing-section-title">Prêt à simplifier votre gestion ?</h2>
    <p>Essai gratuit ou démonstration guidée avec notre équipe.</p>
    <p class="marketing-hero__cta">
        <?= m_btn($p('/inscription'), 'Créer mon espace', 'rocket-takeoff', 'light') ?>
        <?= m_btn($p('/demo'), 'Demander une démo', 'calendar-event', 'secondary') ?>
    </p>
</section>
