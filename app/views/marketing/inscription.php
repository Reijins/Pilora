<?php

declare(strict_types=1);

$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';

$packs = is_array($packs ?? null) ? $packs : [];

$selectedPackId = (int) ($selectedPackId ?? 0);

$p = static fn (string $path): string => $basePath . $path;

?>

<section class="marketing-page-header">

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



    <h2 class="marketing-signup-form__heading"><?= m_icon('box-seam') ?> Votre pack</h2>

    <p class="muted" style="margin-bottom:1rem;">Consultez le <a href="<?= htmlspecialchars($p('/tarifs'), ENT_QUOTES, 'UTF-8') ?>">détail des tarifs</a>.</p>

    <div class="marketing-signup-packs">

        <?php foreach ($packs as $pack): ?>

            <?php

            $pid = (int) ($pack['id'] ?? 0);

            $price = (float) ($pack['price'] ?? 0);

            $isTrial = $price <= 0;

            $checked = $pid === $selectedPackId ? ' checked' : '';

            ?>

            <label class="marketing-signup-pack">

                <input type="radio" name="pack_id" value="<?= $pid ?>" required<?= $checked ?>>

                <span class="marketing-signup-pack__icon"><?= m_icon($isTrial ? 'gift' : 'star-fill') ?></span>

                <span class="marketing-signup-pack__body">

                    <strong><?= htmlspecialchars((string) ($pack['name'] ?? 'Pack'), ENT_QUOTES, 'UTF-8') ?></strong>

                    <span class="muted">

                        <?php if ($isTrial): ?>

                            Essai gratuit — <?= (int) ($pack['maxUsers'] ?? 0) ?> utilisateurs

                        <?php else: ?>

                            <?= htmlspecialchars(number_format($price, 0, ',', ' '), ENT_QUOTES, 'UTF-8') ?> &euro;/mois — <?= (int) ($pack['maxUsers'] ?? 0) ?> utilisateurs

                        <?php endif; ?>

                    </span>

                </span>

            </label>

        <?php endforeach; ?>

    </div>

    <?php if ($packs === []): ?>

        <p class="marketing-alert marketing-alert--danger"><?= m_icon('info-circle') ?> Aucun pack disponible. <a href="<?= htmlspecialchars($p('/demo'), ENT_QUOTES, 'UTF-8') ?>">Contactez-nous</a>.</p>

    <?php endif; ?>



    <label class="label" for="billing_cycle"><?= m_icon('calendar3') ?> Facturation (packs payants)</label>

    <select class="input" id="billing_cycle" name="billing_cycle">

        <option value="monthly">Mensuelle</option>

        <option value="annual">Annuelle</option>

    </select>



    <h2 class="marketing-signup-form__heading"><?= m_icon('building') ?> Votre entreprise</h2>

    <label class="label" for="company_name">Nom de la société</label>

    <input class="input" id="company_name" name="company_name" type="text" required maxlength="255" autocomplete="organization">



    <h2 class="marketing-signup-form__heading"><?= m_icon('person-badge') ?> Compte administrateur</h2>

    <label class="label" for="full_name">Nom complet</label>

    <input class="input" id="full_name" name="full_name" type="text" maxlength="120" autocomplete="name">

    <label class="label" for="email"><?= m_icon('envelope') ?> Email (identifiant)</label>

    <input class="input" id="email" name="email" type="email" required maxlength="255" autocomplete="email">

    <label class="label" for="password"><?= m_icon('lock') ?> Mot de passe</label>

    <input class="input" id="password" name="password" type="password" required minlength="8" autocomplete="new-password">

    <label class="label" for="password_confirm"><?= m_icon('lock-fill') ?> Confirmer le mot de passe</label>

    <input class="input" id="password_confirm" name="password_confirm" type="password" required minlength="8" autocomplete="new-password">



    <?= m_btn_submit('Créer mon espace', 'rocket-takeoff', 'primary', $packs === []) ?>

    <p class="muted" style="margin-top:12px;text-align:center;">

        <?= m_icon('box-arrow-in-right') ?> Déjà client ? <a href="<?= htmlspecialchars($p('/login'), ENT_QUOTES, 'UTF-8') ?>">Se connecter</a>

    </p>

</form>

