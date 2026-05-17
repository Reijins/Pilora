<?php
declare(strict_types=1);
$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
?>
<section class="auth-page">
    <div class="auth-card card">
        <div class="card-header">
            <h2><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Connexion</h2>
            <p class="muted">Accédez à votre espace Pilora.</p>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                    <?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($flashMessage)): ?>
                <div class="alert alert-success" role="status">
                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                    <?= htmlspecialchars((string) $flashMessage, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= htmlspecialchars($basePath . '/login', ENT_QUOTES, 'UTF-8') ?>" class="form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                <label class="label" for="email"><i class="bi bi-envelope" aria-hidden="true"></i> Email</label>
                <input class="input" id="email" name="email" type="email" autocomplete="email" required>

                <label class="label" for="password"><i class="bi bi-lock" aria-hidden="true"></i> Mot de passe</label>
                <input class="input" id="password" name="password" type="password" autocomplete="current-password" required minlength="8">

                <button class="btn btn-primary auth-btn-submit" type="submit">
                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Se connecter
                </button>
            </form>
            <p class="auth-card__footer muted">
                Pas encore de compte ?
                <a href="<?= htmlspecialchars($basePath . '/inscription', ENT_QUOTES, 'UTF-8') ?>">Créer mon espace</a>
            </p>
        </div>
    </div>
</section>
