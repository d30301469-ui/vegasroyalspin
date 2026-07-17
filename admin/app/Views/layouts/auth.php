<?php

// Giriş ekranı statiktir: marka/site bilgisi veritabanından çekilmez.
$pageTitle = isset($title) ? (string) $title : 'Admin Giriş';
$panelName = 'Backoffice';
$siteName = 'Backoffice';
$faviconUrl = '';
$htmlLang = 'tr';
?>
<!doctype html>
<html lang="<?= htmlspecialchars($htmlLang, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($pageTitle . ' · ' . $siteName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" type="image/svg+xml" href="/assets/images/favicons/favicon.svg?v=<?= time() ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/favicons/apple-touch-icon.png?v=<?= time() ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicons/favicon-32x32.png?v=<?= time() ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicons/favicon-16x16.png?v=<?= time() ?>">
    <link rel="manifest" href="/assets/images/favicons/site.webmanifest?v=<?= time() ?>">
    <script>
        !function(){try{var t=localStorage.getItem("dash26-theme"),e=window.matchMedia("(prefers-color-scheme: dark)").matches;document.documentElement.setAttribute("data-theme",t||(e?"dark":"light"))}catch(t){document.documentElement.setAttribute("data-theme","light")}}()
    </script>
    <script defer src="<?= htmlspecialchars(AdminAuth::url('/runtime.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script defer src="<?= htmlspecialchars(AdminAuth::url('/2026.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <link href="<?= htmlspecialchars(AdminAuth::url('/style.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
</head>
<body>
    <?php require $viewFile; ?>
</body>
</html>
