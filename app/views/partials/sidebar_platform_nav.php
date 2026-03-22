<?php
declare(strict_types=1);
/** @var string $basePath */
$perms = (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) ? $_SESSION['permissions'] : [];
$canBilling = in_array('platform.billing.manage', $perms, true);
$canAudit = in_array('platform.audit.read', $perms, true);
?>
<div class="nav-section nav-section--platform">
    <div class="nav-section-label">Plateforme</div>
    <a class="nav-link nav-link-platform" href="<?= htmlspecialchars($basePath . '/platform/companies?tab=companies', ENT_QUOTES, 'UTF-8') ?>">Sociétés</a>
    <?php if ($canBilling): ?>
        <a class="nav-link nav-link-platform" href="<?= htmlspecialchars($basePath . '/platform/companies?tab=packs', ENT_QUOTES, 'UTF-8') ?>">Packs</a>
    <?php endif; ?>
    <?php if ($canAudit): ?>
        <a class="nav-link nav-link-platform" href="<?= htmlspecialchars($basePath . '/platform/companies?tab=audit', ENT_QUOTES, 'UTF-8') ?>">Audit</a>
    <?php endif; ?>
    <?php if ($canBilling): ?>
        <a class="nav-link nav-link-platform" href="<?= htmlspecialchars($basePath . '/platform/companies?tab=invoices', ENT_QUOTES, 'UTF-8') ?>">Suivi factures</a>
    <?php endif; ?>
    <a class="nav-link nav-link-platform" href="<?= htmlspecialchars($basePath . '/platform/companies?tab=users', ENT_QUOTES, 'UTF-8') ?>">Utilisateurs plateforme</a>
    <?php if ($canBilling): ?>
        <a class="nav-link nav-link-platform" href="<?= htmlspecialchars($basePath . '/platform/companies?tab=settings', ENT_QUOTES, 'UTF-8') ?>">Paramètres Pilora</a>
    <?php endif; ?>
</div>
