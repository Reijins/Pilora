<?php

declare(strict_types=1);

use Modules\Marketing\Helpers\MarketingUi;

$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';

$packs = is_array($packs ?? null) ? $packs : [];

$selectedPackId = (int) ($selectedPackId ?? 0);

$p = static fn (string $path): string => $basePath . $path;

$paidPackIndex = 0;

?>

<section class="marketing-page-header marketing-page-header--signup">

    <h1><?= m_icon('rocket-takeoff', 'marketing-page-header__icon') ?> Créer votre espace Pilora</h1>

    <p class="muted">Choisissez votre pack, renseignez votre entreprise et accédez à votre ERP BTP en quelques minutes.</p>

</section>

<?php if (!empty($flashError)): ?>

    <div class="marketing-alert marketing-alert--danger" role="alert">

        <?= m_icon('exclamation-triangle-fill') ?>

        <span><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></span>

    </div>

<?php endif; ?>

<form method="post" action="<?= htmlspecialchars($p('/inscription'), ENT_QUOTES, 'UTF-8') ?>" class="marketing-form-card marketing-signup-form">

    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">

    <section class="marketing-signup-section marketing-signup-section--packs">

        <div class="marketing-signup-section__head">

            <h2 class="marketing-signup-form__heading"><?= m_icon('box-seam') ?> Choisissez votre pack</h2>

            <p class="muted marketing-signup-form__intro">Consultez le <a href="<?= htmlspecialchars($p('/tarifs'), ENT_QUOTES, 'UTF-8') ?>">détail des tarifs</a>.</p>

        </div>

        <?php if ($packs !== []): ?>

            <div class="marketing-billing-toggle" role="group" aria-label="Cycle de facturation">

                <span class="marketing-billing-toggle__label is-active" data-billing-label="monthly">Mensuel</span>

                <button type="button" class="marketing-billing-toggle__switch" id="billing_cycle_switch" role="switch" aria-checked="false" aria-labelledby="billing-label-monthly billing-label-annual">

                    <span class="marketing-billing-toggle__track"><span class="marketing-billing-toggle__thumb"></span></span>

                </button>

                <span class="marketing-billing-toggle__label" data-billing-label="annual" id="billing-label-annual">Annuel</span>

                <span class="marketing-billing-toggle__hint muted" id="billing-label-monthly">Facturation au mois ou à l'année</span>

            </div>

            <input type="hidden" name="billing_cycle" id="billing_cycle" value="monthly">

        <?php endif; ?>

        <div class="marketing-signup-packs-grid">

            <?php foreach ($packs as $pack): ?>

                <?php

                $pid = (int) ($pack['id'] ?? 0);

                $price = (float) ($pack['price'] ?? 0);

                $isTrial = $price <= 0;

                $currentPaidIndex = $isTrial ? -1 : $paidPackIndex;

                if (!$isTrial) {
                    ++$paidPackIndex;
                }

                $featured = !$isTrial && $currentPaidIndex === 1;

                $checked = $pid === $selectedPackId ? ' checked' : '';

                $annualPrice = $isTrial ? 0 : (int) round($price * 12);

                $tierClass = $isTrial ? 'marketing-signup-pack-card--tier-free' : 'marketing-signup-pack-card--tier-' . max(0, $currentPaidIndex);

                $packIcon = MarketingUi::packIcon($isTrial, max(0, $currentPaidIndex));

                ?>

                <label class="marketing-signup-pack-card <?= htmlspecialchars($tierClass, ENT_QUOTES, 'UTF-8') ?><?= $featured ? ' marketing-signup-pack-card--featured' : '' ?><?= $checked !== '' ? ' is-selected' : '' ?>">

                    <input type="radio" name="pack_id" value="<?= $pid ?>" required<?= $checked ?> class="marketing-signup-pack-card__input">

                    <?php if ($featured): ?>

                        <span class="marketing-signup-pack-card__badge">Populaire</span>

                    <?php endif; ?>

                    <span class="marketing-signup-pack-card__icon"><?= m_icon($packIcon) ?></span>

                    <span class="marketing-signup-pack-card__name"><?= htmlspecialchars((string) ($pack['name'] ?? 'Pack'), ENT_QUOTES, 'UTF-8') ?></span>

                    <span class="marketing-signup-pack-card__price" data-monthly="<?= $isTrial ? '' : (int) round($price) ?>" data-annual="<?= $annualPrice ?>" data-trial="<?= $isTrial ? '1' : '0' ?>">

                        <?php if ($isTrial): ?>

                            <strong>Essai gratuit</strong>

                        <?php else: ?>

                            <strong class="marketing-signup-pack-card__amount"><?= htmlspecialchars(number_format($price, 0, ',', ' '), ENT_QUOTES, 'UTF-8') ?> &euro;</strong>

                            <span class="marketing-signup-pack-card__period">/ mois</span>

                        <?php endif; ?>

                    </span>

                    <span class="marketing-signup-pack-card__meta"><?= m_icon('people') ?> Jusqu'à <?= (int) ($pack['maxUsers'] ?? 0) ?> utilisateur<?= (int) ($pack['maxUsers'] ?? 0) > 1 ? 's' : '' ?></span>

                    <span class="marketing-signup-pack-card__check"><?= m_icon('check-circle-fill') ?></span>

                </label>

            <?php endforeach; ?>

        </div>

        <?php if ($packs === []): ?>

            <p class="marketing-alert marketing-alert--danger"><?= m_icon('info-circle') ?> Aucun pack disponible. <a href="<?= htmlspecialchars($p('/demo'), ENT_QUOTES, 'UTF-8') ?>">Contactez-nous</a>.</p>

        <?php endif; ?>

    </section>

    <section class="marketing-signup-section marketing-signup-section--details">

        <div class="marketing-signup-details">

            <div class="marketing-signup-details__col">

                <h2 class="marketing-signup-form__heading"><?= m_icon('building') ?> Votre entreprise</h2>

                <label class="label" for="company_name">Nom de la société</label>

                <input class="input" id="company_name" name="company_name" type="text" required maxlength="255" autocomplete="organization">

            </div>

            <div class="marketing-signup-details__col">

                <h2 class="marketing-signup-form__heading"><?= m_icon('person-badge') ?> Compte administrateur</h2>

                <label class="label" for="full_name">Nom complet</label>

                <input class="input" id="full_name" name="full_name" type="text" maxlength="120" autocomplete="name">

                <label class="label" for="email"><?= m_icon('envelope') ?> Email (identifiant)</label>

                <input class="input" id="email" name="email" type="email" required maxlength="255" autocomplete="email">

                <label class="label" for="password"><?= m_icon('lock') ?> Mot de passe</label>

                <input class="input" id="password" name="password" type="password" required minlength="8" autocomplete="new-password">

                <label class="label" for="password_confirm"><?= m_icon('lock-fill') ?> Confirmer le mot de passe</label>

                <input class="input" id="password_confirm" name="password_confirm" type="password" required minlength="8" autocomplete="new-password">

            </div>

        </div>

        <div class="marketing-signup-submit">

            <?= m_btn_submit('Créer mon espace', 'rocket-takeoff', 'primary', $packs === []) ?>

            <p class="muted marketing-signup-submit__login">

                <?= m_icon('box-arrow-in-right') ?> Déjà client ? <a href="<?= htmlspecialchars($p('/login'), ENT_QUOTES, 'UTF-8') ?>">Se connecter</a>

            </p>

        </div>

    </section>

</form>
