<?php
declare(strict_types=1);
// $contentHtml: string
// $pageTitle: string
// $companyName: string
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle ?? 'Pilora', ENT_QUOTES, 'UTF-8') ?></title>
    <?php
        $basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
        $cssHref = ($basePath !== '' ? $basePath : '') . '/public/css/app.css';
    ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($cssHref, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="app-root">
<?php if (!empty($impersonationBanner) && is_array($impersonationBanner)): ?>
    <div class="impersonation-banner" role="status">
        <span>Vous agissez pour l’entreprise <strong><?= htmlspecialchars((string) ($impersonationBanner['companyName'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>.</span>
        <?php
        $basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
        ?>
        <form method="post" action="<?= htmlspecialchars($basePath . '/platform/impersonate/stop', ENT_QUOTES, 'UTF-8') ?>" style="display:inline;margin-left:1rem;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Core\Security\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn-sm">Quitter l’impersonation</button>
        </form>
    </div>
<?php endif; ?>
<?php if (!empty($billingLockBanner) && is_array($billingLockBanner)): ?>
    <div class="impersonation-banner" role="alert" style="background:#7f1d1d;">
        <span><?= htmlspecialchars((string) ($billingLockBanner['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>
        <a class="btn btn-sm" href="<?= htmlspecialchars($basePath . '/settings?tab=billing', ENT_QUOTES, 'UTF-8') ?>" style="margin-left:1rem;">Aller à la facturation</a>
    </div>
<?php endif; ?>
<div class="app-shell">
    <?php require __DIR__ . '/../partials/topbar.php'; ?>
    <div class="app-body">
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>
        <main class="content">
            <div class="content-header">
                <h1 class="page-title"><?= htmlspecialchars($pageTitle ?? '', ENT_QUOTES, 'UTF-8') ?></h1>
            </div>
            <div class="content-inner">
                <?= $contentHtml ?>
            </div>
        </main>
    </div>
</div>
</body>
</html>

