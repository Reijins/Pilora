<?php
declare(strict_types=1);
// Variables optionnelles: $companyName
?>
<header class="topbar">
    <div class="topbar-left">
        <div class="brand">
            <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>
            <span class="brand-mark" aria-hidden="true">
                <img
                    class="brand-mark-img"
                    src="<?= htmlspecialchars($basePath . '/public/assets/pilora-logo.png', ENT_QUOTES, 'UTF-8') ?>"
                    alt="Pilora"
                >
            </span>
            <span class="brand-name">Pilora</span>
        </div>
    </div>
    <div class="topbar-right">
        <div class="topbar-meta">
            <div class="meta-item meta-item-branding">
                <?php if (!empty($companyLogoUrl)): ?>
                    <img
                        class="company-logo-topbar"
                        src="<?= htmlspecialchars((string) $companyLogoUrl, ENT_QUOTES, 'UTF-8') ?>"
                        alt="<?= htmlspecialchars((string) ($companyName ?? 'Entreprise'), ENT_QUOTES, 'UTF-8') ?>"
                        width="120"
                        height="40"
                        style="max-height:40px;width:auto;object-fit:contain;"
                    >
                <?php else: ?>
                    <span class="meta-label">Entreprise</span>
                    <span class="meta-value"><?= htmlspecialchars($companyName ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>
            <div class="meta-item">
                <span class="meta-label">Session</span>
                <span class="meta-value"><?= isset($_SESSION['user_id']) ? 'Active' : 'Non authentifié' ?></span>
            </div>
        </div>
    </div>
</header>

