<?php
declare(strict_types=1);
// Variables: $permissionDenied, $csrfToken, $flashMessage, $flashError, $roles
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Créer un utilisateur</h2>
            <p class="muted">Ajout d'un utilisateur avec attribution de rôles.</p>
        </div>
        <div class="card-body">
            <?php if (!empty($permissionDenied)): ?>
                <div class="alert alert-danger">Accès refusé : permissions insuffisantes.</div>
            <?php else: ?>
                <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>
                <?php if (!empty($flashError)): ?>
                    <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if (!empty($flashMessage)): ?>
                    <div class="alert alert-success" style="margin-bottom:12px; border-color: var(--success); background: rgba(22,163,74,.08); color: var(--success);">
                        <?= htmlspecialchars((string) $flashMessage, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= htmlspecialchars($basePath . '/settings/users/create', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:720px;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

                    <div class="contact-form-grid">
                        <div class="field">
                            <label class="label" for="email_new">Email</label>
                            <input class="input" id="email_new" name="email" type="email" required>
                        </div>
                        <div class="field">
                            <label class="label" for="password_new">Mot de passe (min 8)</label>
                            <input class="input" id="password_new" name="password" type="password" required minlength="8">
                        </div>
                        <div class="field contact-field-full">
                            <label class="label" for="full_name_new">Nom complet</label>
                            <input class="input" id="full_name_new" name="full_name" type="text" required>
                        </div>
                    </div>

                    <div>
                        <div class="label" style="margin-bottom:8px;">Rôles</div>
                        <div class="checkbox-list">
                            <?php foreach (($roles ?? []) as $role): ?>
                                <?php $roleId = (int) ($role['id'] ?? 0); ?>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="role_ids[]" value="<?= $roleId ?>">
                                    <span><?= htmlspecialchars((string) ($role['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="inline-actions">
                        <button class="btn btn-primary" type="submit">Créer l'utilisateur</button>
                        <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/settings?tab=users', ENT_QUOTES, 'UTF-8') ?>">Retour aux paramètres</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

