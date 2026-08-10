<?php
/**
 * Profil modal iframe için minimal head (header/footer yok).
 * BASE_PATH ve $assetVer kullanılır; bootstrap zaten çalıştırılmış olmalı.
 */
$assetCssDir = defined('BASE_PATH') ? BASE_PATH . '/assets/css' : (__DIR__ . '/../../assets/css');
$assetVer = (string)(file_exists($assetCssDir . '/site-global.css') ? filemtime($assetCssDir . '/site-global.css') : 0) ?: '1';
$cm622ProfileCssVer = (string)(
    file_exists($assetCssDir . '/profile-cm622.css')
        ? (filemtime($assetCssDir . '/profile-cm622.css') . '-' . filesize($assetCssDir . '/profile-cm622.css'))
        : $assetVer
);
$cm622ProfileFixCssVer = (string)(
    file_exists($assetCssDir . '/profile-cm622-fix.css')
        ? (filemtime($assetCssDir . '/profile-cm622-fix.css') . '-' . filesize($assetCssDir . '/profile-cm622-fix.css'))
        : $assetVer
);
$assetJsDir = defined('BASE_PATH') ? BASE_PATH . '/assets/js' : (__DIR__ . '/../../assets/js');
$authSharedVer = (string)((file_exists($assetJsDir . '/auth-shared.js') ? filemtime($assetJsDir . '/auth-shared.js') : 1) . '-' . (file_exists($assetJsDir . '/auth-shared.js') ? filesize($assetJsDir . '/auth-shared.js') : 0));
$profileJsVer = (string)((file_exists($assetJsDir . '/profile.js') ? filemtime($assetJsDir . '/profile.js') : 1) . '-' . (file_exists($assetJsDir . '/profile.js') ? filesize($assetJsDir . '/profile.js') : 0));
?>
<!doctype html>
<html lang="<?= htmlspecialchars(function_exists('current_locale') ? current_locale() : 'tr', ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8">
  <base href="/">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Profil</title>
  <link href="/assets/css/site-bootstrap-utils.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/site-global.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/site-components.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/profile-cm622.css?v=<?= htmlspecialchars($cm622ProfileCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/profile-cm622-fix.css?v=<?= htmlspecialchars($cm622ProfileFixCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/layout-sidebar.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/site-responsive.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Toast (modal iframe içinde hata/başarı bildirimleri için) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<script src="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.js"></script>
<!-- Profil davranışları (sidebar/bakiye/vega işlemleri) -->
<?php include __DIR__ . '/../partials/member-api-layout-script.php'; ?>
<script src="/assets/js/auth-shared.js?v=<?= rawurlencode($authSharedVer) ?>"></script>
<script src="/assets/js/toastify-helper.js?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="/assets/js/profile.js?v=<?= rawurlencode($profileJsVer) ?>"></script>
</head>
<body class="profile-modal-body">
<div class="centerWrap porfileWrap">
