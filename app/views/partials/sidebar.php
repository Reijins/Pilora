<?php
declare(strict_types=1);
?>
<aside class="sidebar" aria-label="Navigation principale">
    <nav class="nav">
        <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>
        <a class="nav-link" href="<?= htmlspecialchars($basePath . '/dashboard', ENT_QUOTES, 'UTF-8') ?>">Tableau de bord</a>
        <a class="nav-link" href="<?= htmlspecialchars($basePath . '/clients', ENT_QUOTES, 'UTF-8') ?>">Clients</a>
        <a class="nav-link" href="<?= htmlspecialchars($basePath . '/invoices', ENT_QUOTES, 'UTF-8') ?>">Factures</a>
        <a class="nav-link" href="<?= htmlspecialchars($basePath . '/price-library', ENT_QUOTES, 'UTF-8') ?>">Bibliothèque de prestations</a>
        <a class="nav-link" href="<?= htmlspecialchars($basePath . '/planning', ENT_QUOTES, 'UTF-8') ?>">Planning</a>
        <a class="nav-link" href="<?= htmlspecialchars($basePath . '/hr', ENT_QUOTES, 'UTF-8') ?>">RH</a>
        <a class="nav-link" href="<?= htmlspecialchars($basePath . '/settings', ENT_QUOTES, 'UTF-8') ?>">Paramètres</a>
        <?php
        $perms = (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) ? $_SESSION['permissions'] : [];
        if (in_array('platform.company.manage', $perms, true)):
        ?>
            <a class="nav-link nav-link-platform" href="<?= htmlspecialchars($basePath . '/platform/companies', ENT_QUOTES, 'UTF-8') ?>">Plateforme</a>
        <?php endif; ?>
    </nav>
</aside>

