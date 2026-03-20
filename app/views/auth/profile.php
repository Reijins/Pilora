<?php
declare(strict_types=1);
// Variables: $companyId, $userId
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Mon profil</h2>
            <p class="muted">Informations d’authentification (squelette).</p>
        </div>
        <div class="card-body">
            <div class="kv-grid">
                <div class="kv">
                    <div class="kv-label">Utilisateur</div>
                    <div class="kv-value">#<?= htmlspecialchars((string) $userId, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="kv">
                    <div class="kv-label">Entreprise (vue actuelle)</div>
                    <div class="kv-value">#<?= htmlspecialchars((string) $companyId, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <?php if (!empty($impersonating) && isset($homeCompanyId)): ?>
                <div class="kv">
                    <div class="kv-label">Votre société d’origine</div>
                    <div class="kv-value">#<?= htmlspecialchars((string) $homeCompanyId, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <?php endif; ?>
            </div>

            <div style="margin-top:16px;">
                <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>
                <form method="POST" action="<?= htmlspecialchars($basePath . '/logout', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <button class="btn btn-secondary" type="submit">Se déconnecter</button>
                </form>
            </div>
        </div>
    </div>
</section>

