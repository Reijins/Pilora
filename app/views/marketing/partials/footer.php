<?php

declare(strict_types=1);

$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';

$p = static fn (string $path): string => $basePath . $path;

?>

<footer class="marketing-footer">

    <section class="marketing-footer__grid">

        <div class="marketing-footer__brand">
            <?php
            $footerLogo = isset($brandLogoUrl) && is_string($brandLogoUrl) ? trim($brandLogoUrl) : '';
            if ($footerLogo !== ''): ?>
                <img class="marketing-footer__logo" src="<?= htmlspecialchars($footerLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Pilora" width="40" height="40">
            <?php else: ?>
                <strong><?= m_icon('layers-fill') ?> Pilora</strong>
            <?php endif; ?>
            <p class="muted" style="margin:0;color:#94a3b8;">ERP pour artisans et PME du bâtiment.</p>
        </div>

        <div class="marketing-footer__links">

            <strong style="color:#e2e8f0;margin-bottom:0.25rem;">Produit</strong>

            <a href="<?= htmlspecialchars($p('/fonctionnalites'), ENT_QUOTES, 'UTF-8') ?>"><?= m_icon('grid', 'm-btn__icon') ?> Fonctionnalités</a>

            <a href="<?= htmlspecialchars($p('/tarifs'), ENT_QUOTES, 'UTF-8') ?>"><?= m_icon('tags', 'm-btn__icon') ?> Tarifs</a>

            <a href="<?= htmlspecialchars($p('/faq'), ENT_QUOTES, 'UTF-8') ?>"><?= m_icon('question-circle', 'm-btn__icon') ?> FAQ</a>

        </div>

        <div class="marketing-footer__links">

            <strong style="color:#e2e8f0;margin-bottom:0.25rem;">Commencer</strong>

            <a href="<?= htmlspecialchars($p('/inscription'), ENT_QUOTES, 'UTF-8') ?>"><?= m_icon('rocket-takeoff', 'm-btn__icon') ?> Créer mon espace</a>

            <a href="<?= htmlspecialchars($p('/demo'), ENT_QUOTES, 'UTF-8') ?>"><?= m_icon('calendar-event', 'm-btn__icon') ?> Demander une démo</a>

            <a href="<?= htmlspecialchars($p('/login'), ENT_QUOTES, 'UTF-8') ?>"><?= m_icon('box-arrow-in-right', 'm-btn__icon') ?> Espace client</a>

        </div>

    </section>

    <p class="marketing-footer__copy">&copy; <?= date('Y') ?> Pilora. Tous droits réservés.</p>

</footer>

