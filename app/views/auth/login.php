<?php
declare(strict_types=1);
// Variables: $error ?string
?>
<section class="auth-page">
    <div class="auth-card card">
        <div class="card-header">
            <h2>Connexion</h2>
            <p class="muted">Accédez à votre espace Pilora.</p>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>
            <form method="POST" action="<?= htmlspecialchars($basePath . '/login', ENT_QUOTES, 'UTF-8') ?>" class="form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

                <label class="label" for="email">Email</label>
                <input class="input" id="email" name="email" type="email" autocomplete="email" required>

                <label class="label" for="password">Mot de passe</label>
                <input class="input" id="password" name="password" type="password" autocomplete="current-password" required minlength="8">

                <button class="btn btn-primary" type="submit">Se connecter</button>
            </form>
        </div>
    </div>
</section>

