<?php
declare(strict_types=1);
$faqItems = is_array($faqItems ?? null) ? $faqItems : [];
$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
$p = static fn (string $path): string => $basePath . $path;
?>
<section class="marketing-page-header">
    <h1><?= m_icon('question-circle', 'marketing-page-header__icon') ?> Questions fréquentes</h1>
    <p class="muted">Tout ce qu'il faut savoir avant de démarrer avec Pilora.</p>
</section>
<div class="marketing-faq">
<?php foreach ($faqItems as $item): ?>
    <details class="marketing-faq__item">
        <summary>
            <span class="marketing-faq__icon"><?= m_icon('patch-question') ?></span>
            <?= htmlspecialchars((string) ($item['question'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </summary>
        <p class="marketing-faq__answer"><?= htmlspecialchars((string) ($item['answer'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    </details>
<?php endforeach; ?>
</div>
<section class="marketing-cta-band marketing-cta-band--inline" style="margin-top:2.5rem;">
    <h2>Besoin d'un échange personnalisé ?</h2>
    <p class="marketing-hero__cta" style="margin:0;">
        <?= m_btn($p('/demo'), 'Demander une démo', 'calendar-event', 'light') ?>
        <?= m_btn($p('/inscription'), 'Créer mon espace', 'rocket-takeoff', 'secondary') ?>
    </p>
</section>
