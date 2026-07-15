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
$assetVer     = (string) (file_exists($assetCssDir . '/global.css') ? filemtime($assetCssDir . '/global.css') : 0) ?: '1';
$pwaRegisterPath = BASE_PATH . '/assets/js/pwa-register.js';
$pwaRegisterVer = (string) (is_file($pwaRegisterPath) ? filemtime($pwaRegisterPath) : $assetVer);
$headerCssVer = (string) (
  file_exists($assetCssDir . '/header.css')
    ? filemtime($assetCssDir . '/header.css') . '-' . filesize($assetCssDir . '/header.css')
    : $assetVer
);
$modalCssVer    = (string) (file_exists($assetCssDir . '/modal.css') ? filemtime($assetCssDir . '/modal.css') : $assetVer);
$registerCssVer = (string) (file_exists($assetCssDir . '/register.css') ? filemtime($assetCssDir . '/register.css') : $assetVer);
$loginCssVer    = (string) (file_exists($assetCssDir . '/login.css') ? filemtime($assetCssDir . '/login.css') : $assetVer);
$registerModalCssVer = (string) (file_exists($assetCssDir . '/register-modal.css') ? filemtime($assetCssDir . '/register-modal.css') : $assetVer);
$loginModalCssVer = (string) (file_exists($assetCssDir . '/login-modal.css') ? filemtime($assetCssDir . '/login-modal.css') : $assetVer);
$authSlidersCssVer = (string) (file_exists($assetCssDir . '/auth-sliders.css') ? filemtime($assetCssDir . '/auth-sliders.css') : $assetVer);
$footerBcCssVer = (string) (file_exists($assetCssDir . '/footer-bc.css') ? filemtime($assetCssDir . '/footer-bc.css') : $assetVer);
$slotsCssVer = (string) (file_exists($assetCssDir . '/slots.css') ? filemtime($assetCssDir . '/slots.css') : $assetVer);
$homeCssVer = (string) (file_exists($assetCssDir . '/home.css') ? filemtime($assetCssDir . '/home.css') : $assetVer);
$sliderCssPath  = BASE_PATH . '/assets/css/slider.css';
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
    $promoVer      = (string) (file_exists($assetCssDir . '/promosyonlar.css') ? filemtime($assetCssDir . '/promosyonlar.css') : $assetVer);
    $bonusModalVer = (string) (file_exists($assetCssDir . '/bonus-detail-modal.css') ? filemtime($assetCssDir . '/bonus-detail-modal.css') : $assetVer);
}
$headBranding = (isset($siteBranding) && is_array($siteBranding)) ? $siteBranding : [];
$headMeta = (isset($siteMeta) && is_array($siteMeta)) ? $siteMeta : [];
$headSiteName = (string) ($headBranding['site_name'] ?? $ayar['site_adi'] ?? 'MaltaBet');
$headDescription = (string) ($headMeta['description'] ?? $headBranding['description'] ?? $ayar['site_aciklama'] ?? '');
$headTitle = (string) ($headMeta['title'] ?? trim($headSiteName . ' - ' . $headDescription));
$headFaviconPath = (string) ($headBranding['favicon_url'] ?? '/assets/images/favicons/favicon.svg');
$headFaviconUrl = cms_asset_url($headFaviconPath) . '?v=' . (int)(filemtime(BASE_PATH . '/' . ltrim($headFaviconPath, '/')) ?: time());
$headManifestPath = (string) ($headBranding['manifest_url'] ?? '/assets/images/favicons/site.webmanifest');
$headManifestUrl = cms_asset_url($headManifestPath) . '?v=' . (int)(filemtime(BASE_PATH . '/' . ltrim($headManifestPath, '/')) ?: time());
$headOgImageUrl = cms_asset_url((string) ($headBranding['og_image_url'] ?? $headBranding['logo_url'] ?? '/assets/images/MaltaBetLogo.png'));
$headKeywords = (string) ($headMeta['keywords'] ?? '');
$headRobots = (string) ($headMeta['robots'] ?? 'index, follow');
$headLanguage = (string) ($headMeta['language'] ?? 'tr');
$headThemeColor = (string) ($headMeta['theme_color'] ?? '#120023');
?>


<!doctype html>
<html lang="tr" class="is-web">
<head>
  <meta charset="utf-8">
  <base href="/">
  <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($headFaviconUrl, ENT_QUOTES, 'UTF-8') ?>" id="appFavicon">
  <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/favicons/apple-touch-icon.png?v=<?= time() ?>">
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
  <link rel="preload" href="/assets/css/promosyonlar.css?v=<?= $promoVer ?>" as="style">
  <link rel="preload" href="/assets/css/bonus-detail-modal.css?v=<?= $bonusModalVer ?>" as="style">
  <?php endif; ?>
  <link href="/assets/css/bootstrap-utils.css?v=<?= $assetVer ?>" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/izitoast/dist/css/iziToast.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />

  <link rel="stylesheet" href="/assets/sports-icon.css">
  <!-- Yalnızca Casino Royal'de kullanılan font: BetConstruct-Icons (metin için sistem fontu) -->
  <link href="/assets/css/global.css?v=<?= $assetVer ?>" rel="stylesheet">
  <link href="/assets/css/header.css?v=<?= $headerCssVer ?>" rel="stylesheet">
  <link href="/assets/css/sidebar.css?v=<?= $assetVer ?>" rel="stylesheet">
  <link href="/assets/css/components.css?v=<?= $assetVer ?>" rel="stylesheet">
  <link href="/assets/css/profile.css?v=<?= $assetVer ?>" rel="stylesheet">
  <link href="/assets/css/modal.css?v=<?= $modalCssVer ?>" rel="stylesheet">
  <link href="/assets/css/responsive.css?v=<?= $assetVer ?>" rel="stylesheet">
  <link href="/assets/css/footer-bc.css?v=<?= $footerBcCssVer ?>" rel="stylesheet">
  <link href="/assets/css/mobile_bottom.css?v=<?= $assetVer ?>" rel="stylesheet">
  <link href="/assets/css/register.css?v=<?= $registerCssVer ?>" rel="stylesheet">
  <link href="/assets/css/login.css?v=<?= $loginCssVer ?>" rel="stylesheet">
  <link href="/assets/css/register-modal.css?v=<?= $registerModalCssVer ?>" rel="stylesheet">
  <link href="/assets/css/login-modal.css?v=<?= $loginModalCssVer ?>" rel="stylesheet">
  <link href="/assets/css/auth-sliders.css?v=<?= $authSlidersCssVer ?>" rel="stylesheet">
  <link href="/assets/css/daterangepicker.css?v=<?= $assetVer ?>" rel="stylesheet">
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
    <link href="/assets/css/home.css?v=<?= $homeCssVer ?>" rel="stylesheet">
    <link href="/assets/css/jackpot.css?v=<?= $assetVer ?>" rel="stylesheet">
    <link href="/assets/css/winners.css?v=<?= $assetVer ?>" rel="stylesheet">
    <link href="/assets/css/slider.css?v=<?= $sliderAssetVer ?>" rel="stylesheet">
    <script defer src="/assets/js/slider.js?v=<?= $sliderAssetVer ?>"></script>
    <?php if (!defined('SLIDER_ASSETS_IN_HEAD')) { define('SLIDER_ASSETS_IN_HEAD', true); } ?>
  <?php endif;
    if ($requestPath === '/slot' || $requestPath === '/livecasino' || $requestPath === '/bgaming' || $requestPath === '/sanal-sporlar'):
  ?>
    <link href="/assets/css/slots.css?v=<?= $slotsCssVer ?>" rel="stylesheet">
    <link href="/assets/css/jackpot.css?v=<?= $assetVer ?>" rel="stylesheet">
    <link href="/assets/css/winners.css?v=<?= $assetVer ?>" rel="stylesheet">
    <link href="/assets/css/slider.css?v=<?= $sliderAssetVer ?>" rel="stylesheet">
    <script defer src="/assets/js/slider.js?v=<?= $sliderAssetVer ?>"></script>
    <?php if (!defined('SLIDER_ASSETS_IN_HEAD')) { define('SLIDER_ASSETS_IN_HEAD', true); } ?>
  <?php endif;
    if ($requestPath === '/beni-ara'):
  ?>
    <link href="/assets/css/beni-ara.css?v=<?= $assetVer ?>" rel="stylesheet">
  <?php endif;
    if ($isPromosyonlar || $isPromotions):
  ?>
    <link href="/assets/css/promosyonlar.css?v=<?= $promoVer ?>" rel="stylesheet">
    <link href="/assets/css/bonus-detail-modal.css?v=<?= $bonusModalVer ?>" rel="stylesheet">
  <?php endif; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick-theme.min.css">

  <meta name="description" content="<?= htmlspecialchars($headDescription, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:title" content="<?= htmlspecialchars($headTitle, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:description" content="<?= htmlspecialchars($headDescription, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:image" content="<?= htmlspecialchars($headOgImageUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:site_name" content="<?= htmlspecialchars($headSiteName, ENT_QUOTES, 'UTF-8') ?>">
  
  <title><?= htmlspecialchars($headTitle, ENT_QUOTES, 'UTF-8') ?></title>
  
  <?php
  if (!function_exists('metropol_csp_connect_src_directive') && defined('CONFIG_PATH') && is_readable(CONFIG_PATH . '/member_api_public.php')) {
      require_once CONFIG_PATH . '/member_api_public.php';
  }
  $cspConnectSrc = function_exists('metropol_csp_connect_src_directive')
      ? metropol_csp_connect_src_directive()
      : "connect-src 'self' wss://*.sptpub.com https://*.sptpub.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://*.google-analytics.com https://analytics.google.com https://*.analytics.google.com https://www.google.com https://*.googletagmanager.com https://stats.g.doubleclick.net https://*.livechatinc.com wss://*.livechatinc.com https://*.livechat.com wss://*.livechat.com https://*.livechat-static.com https://api.vegasroyalspin.com https://admin.vegasroyalspin.com";
  ?>
  <meta http-equiv="Content-Security-Policy" content="<?= htmlspecialchars($cspConnectSrc, ENT_QUOTES, 'UTF-8') ?>">

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
  <script defer src="/assets/js/toastify-helper.js?v=<?= $assetVer ?>"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
  
  <!-- runtime-es2015 / polyfills kaldırıldı: Angular build artığıydı; window.global assets/js/modal-polyfill.js içinde set ediliyor -->
</head>
