<?php
declare(strict_types=1);
$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
?>
<section class="page">
    <div class="card">
        <div class="card-header card-header-with-back">
            <a class="link-back" href="<?= htmlspecialchars($basePath . '/platform/companies?tab=users', ENT_QUOTES, 'UTF-8') ?>" aria-label="Retour utilisateurs plateforme" title="Retour utilisateurs plateforme">
                <svg class="link-back__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
            <div class="card-header-with-back__main">
                <h2>Nouvel utilisateur plateforme</h2>
                <p class="muted">Création d’un utilisateur administrateur/opérateur.</p>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form method="post" action="<?= htmlspecialchars($basePath . '/platform/users/create', ENT_QUOTES, 'UTF-8') ?>" class="form-stack">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <label class="label">Nom complet</label>
                <input class="input" type="text" name="full_name" maxlength="255" required>
                <label class="label">Email</label>
                <input class="input" type="email" name="email" maxlength="255" required>
                <label class="label">Mot de passe temporaire</label>
                <input class="input" type="text" name="password" minlength="8" required>
                <div class="inline-actions">
                    <button class="btn btn-primary" type="submit">Créer utilisateur</button>
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/platform/companies?tab=users', ENT_QUOTES, 'UTF-8') ?>">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</section>
