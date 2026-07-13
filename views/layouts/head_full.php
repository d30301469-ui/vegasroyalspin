<?php
/**
 * Tam head layout – legacy sayfalar için (sunum katmanı: views/layouts).
 * Tüm bootstrap / DB / site ayarları `core/bootstrap.php` üzerinden gelir.
 */
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(dirname(__DIR__)));
}
require_once BASE_PATH . '/core/bootstrap.php';

$assetCssDir = BASE_PATH . '/assets/css';
$assetVer = (string) (file_exists($assetCssDir . '/global.css') ? filemtime($assetCssDir . '/global.css') : 0) ?: '1';
$headerCssVer = (string) (file_exists($assetCssDir . '/header.css') ? filemtime($assetCssDir . '/header.css') : $assetVer);
$modalCssVer = (string) (file_exists($assetCssDir . '/modal.css') ? filemtime($assetCssDir . '/modal.css') : $assetVer);
$registerCssVer = (string) (file_exists($assetCssDir . '/register.css') ? filemtime($assetCssDir . '/register.css') : $assetVer);
$loginCssVer = (string) (file_exists($assetCssDir . '/login.css') ? filemtime($assetCssDir . '/login.css') : $assetVer);
$registerModalCssVer = (string) (file_exists($assetCssDir . '/register-modal.css') ? filemtime($assetCssDir . '/register-modal.css') : $assetVer);
$loginModalCssVer = (string) (file_exists($assetCssDir . '/login-modal.css') ? filemtime($assetCssDir . '/login-modal.css') : $assetVer);
$footerBcCssVer = (string) (file_exists($assetCssDir . '/footer-bc.css') ? filemtime($assetCssDir . '/footer-bc.css') : $assetVer);
$slotsCssVer = (string) (file_exists($assetCssDir . '/slots.css') ? filemtime($assetCssDir . '/slots.css') : $assetVer);
$homeCssVer = (string) (file_exists($assetCssDir . '/home.css') ? filemtime($assetCssDir . '/home.css') : $assetVer);
$requestPathRaw = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestPath = $requestPathRaw === '/' ? '/' : rtrim($requestPathRaw, '/');
$scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isPromosyonlar = ($requestPath === '/promosyonlar' || $scriptName === 'promosyonlar.php');
$isPromotions = ($requestPath === '/promotions' || $scriptName === 'promotions.php');
if ($isPromosyonlar || $isPromotions) {
    $promoVer = (string) (file_exists($assetCssDir . '/promosyonlar.css') ? filemtime($assetCssDir . '/promosyonlar.css') : $assetVer);
    $bonusModalVer = (string) (file_exists($assetCssDir . '/bonus-detail-modal.css') ? filemtime($assetCssDir . '/bonus-detail-modal.css') : $assetVer);
}
$headBranding = (isset($siteBranding) && is_array($siteBranding)) ? $siteBranding : [];
$headMeta = (isset($siteMeta) && is_array($siteMeta)) ? $siteMeta : [];
$headSiteName = (string) ($headBranding['site_name'] ?? $ayar['site_adi'] ?? 'MaltaBet');
$headDescription = (string) ($headMeta['description'] ?? $headBranding['description'] ?? $ayar['site_aciklama'] ?? '');
$headTitle = (string) ($headMeta['title'] ?? trim($headSiteName . ' - ' . $headDescription));
$headFaviconUrl = (string) ($headBranding['favicon_url'] ?? '/assets/images/favicons/favicon.svg');
$headManifestUrl = (string) ($headBranding['manifest_url'] ?? '/assets/images/favicons/site.webmanifest');
$headOgImageUrl = (string) ($headBranding['og_image_url'] ?? $headBranding['logo_url'] ?? '/assets/images/MaltaBetLogo.png');
if (class_exists('ApiMediaUrl', false)) {
    $headFaviconUrl = ApiMediaUrl::resolve($headFaviconUrl);
    $headOgImageUrl = ApiMediaUrl::resolve($headOgImageUrl);
}
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
  <link rel="icon" href="<?= htmlspecialchars($headFaviconUrl, ENT_QUOTES, 'UTF-8') ?>" id="appFavicon">
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
  <link href="/assets/css/daterangepicker.css?v=<?= $assetVer ?>" rel="stylesheet">
  <?php if ($requestPath === '/' || $scriptName === 'index.php'): ?>
  <link href="/assets/css/home.css?v=<?= $homeCssVer ?>" rel="stylesheet">
  <link href="/assets/css/jackpot.css?v=<?= $assetVer ?>" rel="stylesheet">
  <?php endif; ?>
  <?php if ($requestPath === '/slot' || $requestPath === '/livecasino' || $requestPath === '/bgaming' || $requestPath === '/sanal-sporlar' || $scriptName === 'slot.php' || $scriptName === 'livecasino.php' || $scriptName === 'bgaming.php' || $scriptName === 'sanal-sporlar.php'): ?>
  <link href="/assets/css/slots.css?v=<?= $slotsCssVer ?>" rel="stylesheet">
  <link href="/assets/css/jackpot.css?v=<?= $assetVer ?>" rel="stylesheet">
  <link href="/assets/css/winners.css?v=<?= $assetVer ?>" rel="stylesheet">
  <?php endif; ?>
  <?php if ($requestPath === '/beni-ara' || $scriptName === 'beni-ara.php'): ?>
  <link href="/assets/css/beni-ara.css?v=<?= $assetVer ?>" rel="stylesheet">
  <?php endif; ?>
  <?php if ($requestPath === '/jackpot' || $scriptName === 'jackpot.php'): ?>
  <link href="/assets/css/jackpot.css?v=<?= $assetVer ?>" rel="stylesheet">
  <?php endif; ?>
  <?php if ($isPromosyonlar || $isPromotions): ?>
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
  if (!function_exists('metropol_csp_connect_src_directive') && is_readable(BASE_PATH . '/config/member_api_public.php')) {
      require_once BASE_PATH . '/config/member_api_public.php';
  }
  $cspConnectSrc = function_exists('metropol_csp_connect_src_directive')
      ? metropol_csp_connect_src_directive()
      : "connect-src 'self' wss://*.sptpub.com https://*.sptpub.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://*.google-analytics.com https://analytics.google.com https://*.analytics.google.com https://www.google.com https://*.googletagmanager.com https://stats.g.doubleclick.net https://*.livechatinc.com wss://*.livechatinc.com https://*.livechat.com wss://*.livechat.com https://*.livechat-static.com https://api.vegasroyalspin.com https://admin.vegasroyalspin.com";
  ?>
  <meta http-equiv="Content-Security-Policy" content="<?= htmlspecialchars($cspConnectSrc, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://code.jquery.com/jquery-3.2.1.min.js"></script>
  <script src="/assets/js/modal-polyfill.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.js" referrerpolicy="no-referrer"></script>
  <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
  <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
  <script type="text/javascript" id="sportbook" src="https://iceexchange.sptpub.com/bt-renderer.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
  <script type="text/javascript">
    function loadHTMLVideo(sname) {
        var webrtcPlayer = null;
        try {
            webrtcPlayer = new T20RTCPlayer("remoteVideo", sname, "", "real-game.live", "", true, true, "tcp");
            webrtcPlayer.Play();
        } catch (error) {}
    }
    function loadStreamVideo(sname, url) {
        var webrtcPlayer = null;
        try {
            webrtcPlayer = new T20RTCPlayer("remoteVideo", sname, "", url, "", true, true, "tcp");
            webrtcPlayer.Play();
        } catch (error) {}
    }
    window.addEventListener('online', function() { var el = document.getElementById('gscale'); if (el) el.classList.remove('grayscale'); });
    window.addEventListener('offline', function() { var el = document.getElementById('gscale'); if (el) el.classList.add('grayscale'); });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/izitoast/dist/js/iziToast.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.js"></script>
  <script src="/assets/js/toastify-helper.js?v=<?= $assetVer ?>"></script>
  <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
</head>
