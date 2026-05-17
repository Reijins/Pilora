<?php
declare(strict_types=1);
/** @var string $contentHtml @var string $pageTitle */
$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
$cssHref = ($basePath !== '' ? $basePath : '') . '/public/css/app.css';
$brandLogoUrl = isset($brandLogoUrl) && is_string($brandLogoUrl) ? trim($brandLogoUrl) : '';
if ($brandLogoUrl === '') {
    try {
        $brandLogoUrl = (new \Modules\Marketing\Services\MarketingBrandService())->brandLogoUrl($basePath) ?? '';
    } catch (\Throwable) {
        $brandLogoUrl = '';
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars((string) ($pageTitle ?? 'Connexion — Pilora'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= htmlspecialchars($cssHref, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="auth-layout">
<main class="auth-layout__main">
    <?php if ($brandLogoUrl !== ''): ?>
        <a class="auth-layout__brand" href="<?= htmlspecialchars($basePath . '/', ENT_QUOTES, 'UTF-8') ?>">
            <img src="<?= htmlspecialchars($brandLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Pilora" class="auth-layout__logo">
        </a>
    <?php else: ?>
        <a class="auth-layout__brand auth-layout__brand--text" href="<?= htmlspecialchars($basePath . '/', ENT_QUOTES, 'UTF-8') ?>">Pilora</a>
    <?php endif; ?>
    <?= $contentHtml ?? '' ?>
    <p class="auth-layout__back">
        <a href="<?= htmlspecialchars($basePath . '/', ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Retour au site</a>
    </p>
</main>
</body>
</html>
