<?php
/**
 * HTML <head> bölümü.
 * $ayar bootstrap tarafından hazırlanmış olmalı.
 */

if (function_exists('isMobile') && isMobile() && defined('MOBILE_PATH')) {
    $mobileHead = MOBILE_PATH . '/views/layouts/head.php';
  if (is_file($mobileHead) && filesize($mobileHead) > 0) {
        include $mobileHead;
        return;
    }
}

$assetCssDir  = BASE_PATH . '/assets/css';
$assetVer     = (string) (file_exists($assetCssDir . '/site-global.css') ? filemtime($assetCssDir . '/site-global.css') : 0) ?: '1';
$pwaRegisterPath = BASE_PATH . '/assets/js/pwa-register.js';
$pwaRegisterVer = (string) (is_file($pwaRegisterPath) ? filemtime($pwaRegisterPath) : $assetVer);
$headerCssVer = (string) (
  file_exists($assetCssDir . '/layout-header.css')
    ? filemtime($assetCssDir . '/layout-header.css') . '-' . filesize($assetCssDir . '/layout-header.css')
    : $assetVer
);
$cm622ProfileCssVer = (string) (
    file_exists($assetCssDir . '/profile-cm622.css')
        ? (filemtime($assetCssDir . '/profile-cm622.css') . '-' . filesize($assetCssDir . '/profile-cm622.css'))
        : $assetVer
);
$cm622ProfileFixCssVer = (string) (
    file_exists($assetCssDir . '/profile-cm622-fix.css')
        ? (filemtime($assetCssDir . '/profile-cm622-fix.css') . '-' . filesize($assetCssDir . '/profile-cm622-fix.css'))
        : $assetVer
);
$modalCssVer    = (string) (file_exists($assetCssDir . '/site-modal.css') ? filemtime($assetCssDir . '/site-modal.css') : $assetVer);
$registerCssVer = (string) (file_exists($assetCssDir . '/auth-register.css') ? filemtime($assetCssDir . '/auth-register.css') : $assetVer);
$loginCssVer    = (string) (file_exists($assetCssDir . '/auth-login.css') ? filemtime($assetCssDir . '/auth-login.css') : $assetVer);
$registerModalCssVer = (string) (file_exists($assetCssDir . '/auth-register-modal.css') ? filemtime($assetCssDir . '/auth-register-modal.css') : $assetVer);
$loginModalCssVer = (string) (file_exists($assetCssDir . '/auth-login-modal.css') ? filemtime($assetCssDir . '/auth-login-modal.css') : $assetVer);
$authSlidersCssVer = (string) (file_exists($assetCssDir . '/auth-sliders.css') ? filemtime($assetCssDir . '/auth-sliders.css') : $assetVer);
$footerBcCssVer = (string) (file_exists($assetCssDir . '/layout-footer.css') ? filemtime($assetCssDir . '/layout-footer.css') : $assetVer);
$homeCssVer = (string) (file_exists($assetCssDir . '/home.css') ? filemtime($assetCssDir . '/home.css') : $assetVer);
$sliderCssPath  = BASE_PATH . '/assets/css/home-slider.css';
$sliderJsPath   = BASE_PATH . '/assets/js/slider.js';
$sliderAssetVer = (string) max(
    file_exists($sliderCssPath) ? filemtime($sliderCssPath) : 0,
    file_exists($sliderJsPath) ? filemtime($sliderJsPath) : 0
) ?: '1';

$requestPathRaw = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestPath    = $requestPathRaw === '/' ? '/' : rtrim($requestPathRaw, '/');
$scriptName     = basename($_SERVER['SCRIPT_NAME'] ?? '');

$isPromosyonlar = ($requestPath === '/promosyonlar' || $scriptName === 'promosyonlar.php');
$isPromotions   = ($requestPath === '/promotions' || $scriptName === 'promotions.php');
if ($isPromosyonlar || $isPromotions) {
    $promoVer      = (string) (file_exists($assetCssDir . '/promotions.css') ? filemtime($assetCssDir . '/promotions.css') : $assetVer);
    $bonusModalVer = (string) (file_exists($assetCssDir . '/promotions-bonus-modal.css') ? filemtime($assetCssDir . '/promotions-bonus-modal.css') : $assetVer);
}
$headBranding = (isset($siteBranding) && is_array($siteBranding)) ? $siteBranding : [];
$headMeta = (isset($siteMeta) && is_array($siteMeta)) ? $siteMeta : [];
$headSiteName = (string) ($headBranding['site_name'] ?? $ayar['site_adi'] ?? 'VegasRoyalSpin');
$headDescription = (string) ($headMeta['description'] ?? $headBranding['description'] ?? $ayar['site_aciklama'] ?? '');
$headTitle = (string) ($headMeta['title'] ?? trim($headSiteName . ' - ' . $headDescription));
$headFaviconPath = (string) ($headBranding['favicon_url'] ?? '/assets/images/favicons/favicon.svg');
$headFaviconPathForCheck = ltrim($headFaviconPath, '/');
$headFaviconVersion = time();
if (preg_match('#^https?://#i', $headFaviconPathForCheck) !== 1) {
  $faviconLocalPath = BASE_PATH . '/' . ltrim($headFaviconPath, '/');
  if (is_file($faviconLocalPath)) {
    $headFaviconVersion = (int) filemtime($faviconLocalPath);
  }
}
$headFaviconUrl = cms_asset_url($headFaviconPath) . '?v=' . $headFaviconVersion;
$headManifestPath = (string) ($headBranding['manifest_url'] ?? '/assets/images/favicons/site.webmanifest');
$headManifestPathForCheck = ltrim($headManifestPath, '/');
$headManifestDefaultPath = '/assets/images/favicons/site.webmanifest';
$headCurrentHost = strtolower((string) preg_replace('/:\\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
$headManifestHost = strtolower((string) (parse_url($headManifestPath, PHP_URL_HOST) ?: ''));
if ($headManifestPath === '' || ($headManifestHost !== '' && $headManifestHost !== $headCurrentHost)) {
  $headManifestPath = $headManifestDefaultPath;
}
$headManifestVersion = time();
if (preg_match('#^https?://#i', $headManifestPathForCheck) !== 1) {
  $manifestLocalPath = BASE_PATH . '/' . ltrim($headManifestPath, '/');
  if (is_file($manifestLocalPath)) {
    $headManifestVersion = (int) filemtime($manifestLocalPath);
  }
}
$headManifestUrl = cms_asset_url($headManifestPath) . '?v=' . $headManifestVersion;
$headOgImageUrl = cms_asset_url((string) ($headBranding['og_image_url'] ?? $headBranding['logo_url'] ?? ''));
$headKeywords = (string) ($headMeta['keywords'] ?? '');
$headRobots = (string) ($headMeta['robots'] ?? 'index, follow');
$headLanguage = (string) ($headMeta['language'] ?? 'tr');
$headThemeColor = (string) ($headMeta['theme_color'] ?? '#120023');
?>


<!doctype html>
<html lang="<?= htmlspecialchars(function_exists('current_locale') ? current_locale() : 'tr', ENT_QUOTES, 'UTF-8') ?>" class="is-web">
<head>
  <meta charset="utf-8">
  <base href="/">
  <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($headFaviconUrl, ENT_QUOTES, 'UTF-8') ?>" id="appFavicon">
  <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/favicons/apple-touch-icon.png?v=<?= time() ?>">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="VegasRoyal">
  <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicons/favicon-32x32.png?v=<?= time() ?>">
  <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicons/favicon-16x16.png?v=<?= time() ?>">
  <link rel="manifest" href="<?= htmlspecialchars($headManifestUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="keywords" content="<?= htmlspecialchars($headKeywords, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="robots" content="<?= htmlspecialchars($headRobots, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="language" content="<?= htmlspecialchars($headLanguage, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="theme-color" content="<?= htmlspecialchars($headThemeColor, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <?php include __DIR__ . '/../partials/mobile-redirect-script.php'; ?>
  <?php if ($isPromosyonlar || $isPromotions): ?>
  <link rel="preload" href="/assets/css/promotions.css?v=<?= htmlspecialchars($promoVer, ENT_QUOTES, 'UTF-8') ?>" as="style">
  <link rel="preload" href="/assets/css/promotions-bonus-modal.css?v=<?= htmlspecialchars($bonusModalVer, ENT_QUOTES, 'UTF-8') ?>" as="style">
  <?php endif; ?>
  <link href="/assets/css/site-bootstrap-utils.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/izitoast/dist/css/iziToast.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />

  <link rel="stylesheet" href="/assets/css/sports-icon.css">
  <!-- Yalnızca Casino Royal'de kullanılan font: BetConstruct-Icons (metin için sistem fontu) -->
  <link href="/assets/css/site-global.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/layout-header.css?v=<?= htmlspecialchars($headerCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/layout-sidebar.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/site-components.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/profile-cm622.css?v=<?= htmlspecialchars($cm622ProfileCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/profile-cm622-fix.css?v=<?= htmlspecialchars($cm622ProfileFixCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/site-modal.css?v=<?= htmlspecialchars($modalCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/site-responsive.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/layout-footer.css?v=<?= htmlspecialchars($footerBcCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/mobile-bottom.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/auth-register.css?v=<?= htmlspecialchars($registerCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/auth-login.css?v=<?= htmlspecialchars($loginCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/auth-register-modal.css?v=<?= htmlspecialchars($registerModalCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/auth-login-modal.css?v=<?= htmlspecialchars($loginModalCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/auth-sliders.css?v=<?= htmlspecialchars($authSlidersCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/vendor-daterangepicker.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <style>
      body.mobile-site .hdr-smart-panel-fixed {
        left: auto !important;
        right: 8px !important;
        top: calc(var(--header-sticky-top, 60px) + 8px) !important;
        bottom: auto !important;
        height: auto !important;
        max-height: 320px !important;
        overflow: hidden !important;
        transform: none !important;
      }
      body.mobile-site .hdr-smart-panel-fixed .hdr-smart-panel-holder-bc {
        max-height: 320px !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
      }
      body.mobile-site .hdr-smart-panel-fixed .hdr-smart-panel-holder-bc .sp-button-bc {
        width: 50px !important;
        height: 44px !important;
        font-size: 11px !important;
        line-height: 1 !important;
        padding: 0 !important;
      }
      body.mobile-site .hdr-smart-panel-fixed .hdr-smart-panel-holder-bc .sp-button-icon-bc {
        font-size: 15px !important;
      }
    </style>
  <?php if ($requestPath === '/'): ?>
    <link href="/assets/css/home.css?v=<?= htmlspecialchars($homeCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="/assets/css/home-jackpot.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="/assets/css/home-winners.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="/assets/css/home-slider.css?v=<?= htmlspecialchars($sliderAssetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <script defer src="/assets/js/slider.js?v=<?= htmlspecialchars($sliderAssetVer, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php if (!defined('SLIDER_ASSETS_IN_HEAD')) { define('SLIDER_ASSETS_IN_HEAD', true); } ?>
  <?php endif;
    if ($requestPath === '/livecasino'):
      $bcCm622LiveCssPath = $assetCssDir . '/casino-live-cm622.css';
      $bcCm622LiveCssVer = (string) (file_exists($bcCm622LiveCssPath) ? filemtime($bcCm622LiveCssPath) : $assetVer);
  ?>
    <link href="/assets/css/home-slider.css?v=<?= htmlspecialchars($sliderAssetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <script defer src="/assets/js/slider.js?v=<?= htmlspecialchars($sliderAssetVer, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php if (!defined('SLIDER_ASSETS_IN_HEAD')) { define('SLIDER_ASSETS_IN_HEAD', true); } ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="/assets/css/casino-live-cm622.css?v=<?= htmlspecialchars($bcCm622LiveCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <?php endif;
    if ($requestPath === '/sanal-sporlar'):
      $bcCm622SlotsCssPath = $assetCssDir . '/casino-slots-cm622.css';
      $bcCm622SlotsCssVer = (string) (file_exists($bcCm622SlotsCssPath) ? filemtime($bcCm622SlotsCssPath) : $assetVer);
  ?>
    <link href="/assets/css/casino-slots-cm622.css?v=<?= htmlspecialchars($bcCm622SlotsCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="/assets/css/home-jackpot.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="/assets/css/home-winners.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="/assets/css/home-slider.css?v=<?= htmlspecialchars($sliderAssetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <script defer src="/assets/js/slider.js?v=<?= htmlspecialchars($sliderAssetVer, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php if (!defined('SLIDER_ASSETS_IN_HEAD')) { define('SLIDER_ASSETS_IN_HEAD', true); } ?>
  <?php endif;
    if ($requestPath === '/slot' || $requestPath === '/bgaming'):
  ?>
    <link href="/assets/css/home-slider.css?v=<?= htmlspecialchars($sliderAssetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <script defer src="/assets/js/slider.js?v=<?= htmlspecialchars($sliderAssetVer, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php if (!defined('SLIDER_ASSETS_IN_HEAD')) { define('SLIDER_ASSETS_IN_HEAD', true); } ?>
  <?php endif;
    if ($requestPath === '/beni-ara'):
  ?>
    <link href="/assets/css/page-beni-ara.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <?php endif;
    if ($isPromosyonlar || $isPromotions):
  ?>
    <link href="/assets/css/promotions.css?v=<?= htmlspecialchars($promoVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="/assets/css/promotions-bonus-modal.css?v=<?= htmlspecialchars($bonusModalVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <?php endif; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick-theme.min.css">
  <?php
    // CM622: load in head for first paint; body also re-injects last so
    // footer/vendor CSS cannot override casino rules.
    if ($requestPath === '/slot'):
      $bcCm622SlotsCssPath = $assetCssDir . '/casino-slots-cm622.css';
      $bcCm622SlotsCssVer = (string) (file_exists($bcCm622SlotsCssPath) ? filemtime($bcCm622SlotsCssPath) : $assetVer);
  ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="/assets/css/casino-slots-cm622.css?v=<?= htmlspecialchars($bcCm622SlotsCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <?php endif;
    if ($requestPath === '/bgaming'):
      $bcCm622BgamingCssPath = $assetCssDir . '/casino-slots-cm622.css';
      $bcCm622BgamingCssVer = (string) (file_exists($bcCm622BgamingCssPath) ? filemtime($bcCm622BgamingCssPath) : $assetVer);
  ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="/assets/css/casino-slots-cm622.css?v=<?= htmlspecialchars($bcCm622BgamingCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <?php endif; ?>

  <meta name="description" content="<?= htmlspecialchars($headDescription, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:title" content="<?= htmlspecialchars($headTitle, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:description" content="<?= htmlspecialchars($headDescription, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:image" content="<?= htmlspecialchars($headOgImageUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:site_name" content="<?= htmlspecialchars($headSiteName, ENT_QUOTES, 'UTF-8') ?>">
  
  <title><?= htmlspecialchars($headTitle, ENT_QUOTES, 'UTF-8') ?></title>
  
  <?php
  if (!function_exists('csp_connect_src_directive') && defined('CONFIG_PATH') && is_readable(CONFIG_PATH . '/member_api_public.php')) {
      require_once CONFIG_PATH . '/member_api_public.php';
  }
  $cspConnectSrc = function_exists('csp_connect_src_directive')
      ? csp_connect_src_directive()
      : "connect-src 'self' wss://*.sptpub.com https://*.sptpub.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://*.google-analytics.com https://analytics.google.com https://*.analytics.google.com https://www.google.com https://*.googletagmanager.com https://stats.g.doubleclick.net https://*.livechatinc.com wss://*.livechatinc.com https://*.livechat.com wss://*.livechat.com https://*.livechat-static.com https://admin.vegasroyalspin.com https://challenges.cloudflare.com";
  ?>
  <meta http-equiv="Content-Security-Policy" content="<?= htmlspecialchars($cspConnectSrc, ENT_QUOTES, 'UTF-8') ?>">
    <script defer src="https://challenges.cloudflare.com/turnstile/v0/api.js"></script>

  <?php if (!(defined('SPORTSBOOK_LIGHTWEIGHT_LAYOUT') && SPORTSBOOK_LIGHTWEIGHT_LAYOUT)): ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <script defer src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  
    <script defer src="https://code.jquery.com/jquery-3.2.1.min.js"></script>
    <script defer src="/assets/js/modal-polyfill.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js"></script>
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.js"referrerpolicy="no-referrer"></script>
    <script defer type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script defer type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script defer type="text/javascript" id="sportbook" src="https://iceexchange.sptpub.com/bt-renderer.min.js"></script>

    

  <script defer src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>

  <script type="text/javascript">
    function loadHTMLVideo(sname) {
        var webrtcPlayer = null;
        try {
            webrtcPlayer = new T20RTCPlayer("remoteVideo", sname, "", "real-game.live", "", true, true, "tcp");
            webrtcPlayer.Play();
        } catch (error) {
        }
    }
    function loadStreamVideo(sname, url) {

        var webrtcPlayer = null;
        try {
            webrtcPlayer = new T20RTCPlayer("remoteVideo", sname, "", url, "", true, true, "tcp");
            webrtcPlayer.Play();
        } catch (error) {
        }
    }

    window.addEventListener('online', () => document.getElementById('gscale').classList.remove('grayscale'));
    window.addEventListener('offline', () => document.getElementById('gscale').classList.add('grayscale'));
  </script>

  <script defer src="https://cdn.jsdelivr.net/npm/izitoast/dist/js/iziToast.min.js"></script>
  <script defer src="/assets/js/pwa-register.js?v=<?= rawurlencode($pwaRegisterVer) ?>"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.js"></script>
  <script defer src="/assets/js/toastify-helper.js?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
  <?php endif; ?>
  
  <!-- runtime-es2015 / polyfills kaldırıldı: Angular build artığıydı; window.global assets/js/modal-polyfill.js içinde set ediliyor -->
</head>
