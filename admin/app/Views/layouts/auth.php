<?php

// Giriş ekranı statiktir: marka/site bilgisi veritabanından çekilmez.
$pageTitle = isset($title) ? (string) $title : 'Admin Giriş';
$panelName = 'Nexthub Backoffice';
$siteName = 'Nexthub Backoffice';
$faviconUrl = '';
$htmlLang = 'tr';
?>
<!doctype html>
<html lang="<?= htmlspecialchars($htmlLang, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($pageTitle . ' · ' . $siteName, ENT_QUOTES, 'UTF-8') ?></title>
    <?php if ($faviconUrl !== ''): ?>
        <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
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
