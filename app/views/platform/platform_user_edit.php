<?php
declare(strict_types=1);
$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
$u = is_array($user ?? null) ? $user : [];
$uid = (int) ($u['id'] ?? 0);
$ust = (string) ($u['status'] ?? 'active');
$currentUserId = (int) ($currentUserId ?? 0);
?>
<section class="page">
    <div class="card">
        <div class="card-header card-header-with-back">
            <a class="link-back" href="<?= htmlspecialchars($basePath . '/platform/companies?tab=users', ENT_QUOTES, 'UTF-8') ?>" aria-label="Retour utilisateurs plateforme" title="Retour utilisateurs plateforme">
                <svg class="link-back__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
            <div class="card-header-with-back__main">
                <h2>Modifier utilisateur</h2>
                <p class="muted">Mise à jour des informations de compte.</p>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form method="post" action="<?= htmlspecialchars($basePath . '/platform/users/update', ENT_QUOTES, 'UTF-8') ?>" class="form-stack">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="user_id" value="<?= $uid ?>">
                <label class="label">Nom complet</label>
                <input class="input" type="text" name="full_name" maxlength="255" required value="<?= htmlspecialchars((string) ($u['fullName'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <label class="label">Email</label>
                <input class="input" type="email" name="email" maxlength="255" required value="<?= htmlspecialchars((string) ($u['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <label class="label">Statut</label>
                <select class="input" name="status">
                    <?php foreach (['active','inactive','pending','invited','disabled'] as $s): ?>
                        <?php $selfBlocked = $uid === $currentUserId && in_array($s, ['inactive', 'disabled'], true); ?>
                        <option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>" <?= $ust === $s ? 'selected' : '' ?> <?= $selfBlocked ? 'disabled' : '' ?>><?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($uid === $currentUserId): ?>
                    <p class="muted" style="margin:4px 0 0;">Protection active: vous ne pouvez pas désactiver votre propre compte.</p>
                <?php endif; ?>
                <div class="inline-actions">
                    <button class="btn btn-primary" type="submit">Enregistrer</button>
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/platform/companies?tab=users', ENT_QUOTES, 'UTF-8') ?>">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</section>
