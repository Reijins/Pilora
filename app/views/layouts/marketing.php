<?php

declare(strict_types=1);

/** @var string $contentHtml @var string $pageTitle @var string $metaDescription @var string $canonicalUrl @var string $bodyClass */

$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';

$cssMarketing = ($basePath !== '' ? $basePath : '') . '/public/css/marketing.css';

$jsMarketing = ($basePath !== '' ? $basePath : '') . '/public/js/marketing.js';

$analyticsId = trim((string) ($analyticsId ?? ''));

?>

<!doctype html>

<html lang="fr">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= htmlspecialchars((string) ($pageTitle ?? 'Pilora'), ENT_QUOTES, 'UTF-8') ?></title>

    <meta name="description" content="<?= htmlspecialchars((string) ($metaDescription ?? ''), ENT_QUOTES, 'UTF-8') ?>">

    <link rel="canonical" href="<?= htmlspecialchars((string) ($canonicalUrl ?? ''), ENT_QUOTES, 'UTF-8') ?>">

    <meta property="og:type" content="website">

    <meta property="og:title" content="<?= htmlspecialchars((string) ($ogTitle ?? $pageTitle ?? 'Pilora'), ENT_QUOTES, 'UTF-8') ?>">

    <meta property="og:description" content="<?= htmlspecialchars((string) ($metaDescription ?? ''), ENT_QUOTES, 'UTF-8') ?>">

    <meta property="og:url" content="<?= htmlspecialchars((string) ($canonicalUrl ?? ''), ENT_QUOTES, 'UTF-8') ?>">

    <meta name="twitter:card" content="summary_large_image">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous">

    <link rel="stylesheet" href="<?= htmlspecialchars($cssMarketing, ENT_QUOTES, 'UTF-8') ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">

    <?php if (!empty($jsonLd)): ?>

    <script type="application/ld+json"><?= $jsonLd ?></script>

    <?php endif; ?>

    <?php if ($analyticsId !== ''): ?>

    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($analyticsId, ENT_QUOTES, 'UTF-8') ?>"></script>

    <script>

    window.dataLayer = window.dataLayer || [];

    function gtag(){dataLayer.push(arguments);}

    gtag('js', new Date());

    gtag('config', <?= json_encode($analyticsId, JSON_UNESCAPED_UNICODE) ?>);

    </script>

    <?php endif; ?>

</head>

<body class="marketing-root <?= htmlspecialchars((string) ($bodyClass ?? ''), ENT_QUOTES, 'UTF-8') ?>">

<?php require __DIR__ . '/../marketing/partials/header.php'; ?>

<main class="marketing-main" id="contenu-principal">

    <?= $contentHtml ?? '' ?>

</main>

<?php require __DIR__ . '/../marketing/partials/footer.php'; ?>

<script src="<?= htmlspecialchars($jsMarketing, ENT_QUOTES, 'UTF-8') ?>" defer></script>

</body>

</html>

