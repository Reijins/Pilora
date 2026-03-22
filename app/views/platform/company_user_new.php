<?php
declare(strict_types=1);
$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
$company = is_array($company ?? null) ? $company : [];
$companyId = (int) ($company['id'] ?? 0);
$roles = is_array($roles ?? null) ? $roles : [];
?>
<section class="page">
    <div class="card">
        <div class="card-header card-header-with-back">
            <a class="link-back" href="<?= htmlspecialchars($basePath . '/platform/companies/show?id=' . $companyId . '&tab=users', ENT_QUOTES, 'UTF-8') ?>" aria-label="Retour utilisateurs société" title="Retour utilisateurs société">
                <svg class="link-back__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
            <div class="card-header-with-back__main">
                <h2>Nouvel utilisateur</h2>
                <p class="muted"><?= htmlspecialchars((string) ($company['name'] ?? 'Société'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form method="post" action="<?= htmlspecialchars($basePath . '/platform/companies/users/create', ENT_QUOTES, 'UTF-8') ?>" class="form-stack">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="company_id" value="<?= $companyId ?>">
                <label class="label">Nom complet</label>
                <input class="input" type="text" name="full_name" maxlength="255" required>
                <label class="label">Email</label>
                <input class="input" type="email" name="email" maxlength="255" required>
                <label class="label">Mot de passe temporaire</label>
                <input class="input" type="text" name="password" minlength="8" required>
                <label class="label">Rôle société</label>
                <select class="input" name="role_ids[]" required>
                    <?php foreach ($roles as $role): ?>
                        <?php $roleId = (int) ($role['id'] ?? 0); if ($roleId <= 0) {
                            continue;
                        } ?>
                        <?php $isAdmin = (string) ($role['name'] ?? '') === 'Admin'; ?>
                        <option value="<?= $roleId ?>" <?= $isAdmin ? 'selected' : '' ?>><?= htmlspecialchars((string) ($role['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="muted" style="margin:6px 0 0;font-size:13px;">Par défaut : <strong>Admin</strong> (toutes les permissions). Les rôles sont créés automatiquement pour chaque société.</p>
                <?php if ($roles === []): ?>
                    <p class="alert alert-danger" style="margin:8px 0 0;">Aucun rôle société : exécutez le script de réparation RBAC ou contactez le support.</p>
                <?php endif; ?>
                <div class="inline-actions">
                    <button class="btn btn-primary" type="submit">Créer utilisateur</button>
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/platform/companies/show?id=' . $companyId . '&tab=users', ENT_QUOTES, 'UTF-8') ?>">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</section>

