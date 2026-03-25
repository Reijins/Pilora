<?php
declare(strict_types=1);

$perms = (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) ? $_SESSION['permissions'] : [];
$isBackOffice = in_array('platform.company.manage', $perms, true);
$impersonateTarget = isset($_SESSION['impersonate_target_company_id']) ? (int) $_SESSION['impersonate_target_company_id'] : 0;
$isImpersonatingTenant = $impersonateTarget > 0 && in_array('platform.impersonate.start', $perms, true);
/** Sidebar ERP tenant : masquée pour le back-office sauf pendant l’impersonation « Se connecter en admin ». */
$showTenantAppNav = !$isBackOffice || $isImpersonatingTenant;
?>
<aside class="sidebar" aria-label="Navigation principale">
    <nav class="nav">
        <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>
        <?php if ($showTenantAppNav): ?>
            <a class="nav-link" href="<?= htmlspecialchars($basePath . '/dashboard', ENT_QUOTES, 'UTF-8') ?>">Tableau de bord</a>
            <a class="nav-link" href="<?= htmlspecialchars($basePath . '/planning', ENT_QUOTES, 'UTF-8') ?>">Planning</a>
            <a class="nav-link" href="<?= htmlspecialchars($basePath . '/clients', ENT_QUOTES, 'UTF-8') ?>">Clients</a>
            <a class="nav-link" href="<?= htmlspecialchars($basePath . '/projects', ENT_QUOTES, 'UTF-8') ?>">Affaires</a>
            <a class="nav-link" href="<?= htmlspecialchars($basePath . '/projects/rentability', ENT_QUOTES, 'UTF-8') ?>">Rentabilité</a>
            <a class="nav-link" href="<?= htmlspecialchars($basePath . '/invoices', ENT_QUOTES, 'UTF-8') ?>">Factures</a>
            <a class="nav-link" href="<?= htmlspecialchars($basePath . '/price-library', ENT_QUOTES, 'UTF-8') ?>">Bibliothèque de prestations</a>
            <a class="nav-link" href="<?= htmlspecialchars($basePath . '/hr', ENT_QUOTES, 'UTF-8') ?>">RH</a>
            <a class="nav-link" href="<?= htmlspecialchars($basePath . '/settings', ENT_QUOTES, 'UTF-8') ?>">Paramètres</a>
        <?php endif; ?>
        <?php
        if ($isBackOffice) {
            require __DIR__ . '/sidebar_platform_nav.php';
        }
        ?>
    </nav>
</aside>

