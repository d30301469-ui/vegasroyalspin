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
$assetVer = (string) (file_exists($assetCssDir . '/site-global.css') ? filemtime($assetCssDir . '/site-global.css') : 0) ?: '1';
$pwaRegisterPath = BASE_PATH . '/assets/js/pwa-register.js';
$pwaRegisterVer = (string) (is_file($pwaRegisterPath) ? filemtime($pwaRegisterPath) : $assetVer);
$headerCssVer = (string) (file_exists($assetCssDir . '/layout-header.css') ? filemtime($assetCssDir . '/layout-header.css') : $assetVer);
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
$cm622ProfileOriginalDepositCssVer = (string) (
    file_exists($assetCssDir . '/profile-cm622-original-deposit.css')
        ? (filemtime($assetCssDir . '/profile-cm622-original-deposit.css') . '-' . filesize($assetCssDir . '/profile-cm622-original-deposit.css'))
        : $assetVer
);
$cm622ProfileOriginalFiltersCssVer = (string) (
    file_exists($assetCssDir . '/profile-cm622-original-filters-tables.css')
        ? (filemtime($assetCssDir . '/profile-cm622-original-filters-tables.css') . '-' . filesize($assetCssDir . '/profile-cm622-original-filters-tables.css'))
        : $assetVer
);
$cm622ProfileOriginalCompleteCssVer = (string) (
    file_exists($assetCssDir . '/profile-cm622-original-complete.css')
        ? (filemtime($assetCssDir . '/profile-cm622-original-complete.css') . '-' . filesize($assetCssDir . '/profile-cm622-original-complete.css'))
        : $assetVer
);
$siteComponentsCssVer = (string) (
    file_exists($assetCssDir . '/site-components.css')
        ? (filemtime($assetCssDir . '/site-components.css') . '-' . filesize($assetCssDir . '/site-components.css'))
        : $assetVer
);
$modalCssVer = (string) (file_exists($assetCssDir . '/site-modal.css') ? filemtime($assetCssDir . '/site-modal.css') : $assetVer);
$registerCssVer = (string) (file_exists($assetCssDir . '/auth-register.css') ? filemtime($assetCssDir . '/auth-register.css') : $assetVer);
$loginCssVer = (string) (file_exists($assetCssDir . '/auth-login.css') ? filemtime($assetCssDir . '/auth-login.css') : $assetVer);
$registerModalCssVer = (string) (file_exists($assetCssDir . '/auth-register-modal.css') ? filemtime($assetCssDir . '/auth-register-modal.css') : $assetVer);
$loginModalCssVer = (string) (file_exists($assetCssDir . '/auth-login-modal.css') ? filemtime($assetCssDir . '/auth-login-modal.css') : $assetVer);
$footerBcCssVer = (string) (file_exists($assetCssDir . '/layout-footer.css') ? filemtime($assetCssDir . '/layout-footer.css') : $assetVer);
$sportsIconCssVer = (string) (file_exists($assetCssDir . '/sports-icon.css') ? filemtime($assetCssDir . '/sports-icon.css') : $assetVer);
$homeCssVer = (string) (file_exists($assetCssDir . '/home.css') ? filemtime($assetCssDir . '/home.css') : $assetVer);
$requestPathRaw = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestPath = $requestPathRaw === '/' ? '/' : rtrim($requestPathRaw, '/');
$scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isPromosyonlar = ($requestPath === '/promosyonlar' || $scriptName === 'promosyonlar.php');
$isPromotions = ($requestPath === '/promotions' || $scriptName === 'promotions.php');
if ($isPromosyonlar || $isPromotions) {
    $promoVer = (string) (file_exists($assetCssDir . '/promotions.css') ? filemtime($assetCssDir . '/promotions.css') : $assetVer);
    $bonusModalVer = (string) (file_exists($assetCssDir . '/promotions-bonus-modal.css') ? filemtime($assetCssDir . '/promotions-bonus-modal.css') : $assetVer);
}
$headBranding = (isset($siteBranding) && is_array($siteBranding)) ? $siteBranding : [];
$headMeta = (isset($siteMeta) && is_array($siteMeta)) ? $siteMeta : [];
$headSiteName = (string) ($headBranding['site_name'] ?? $ayar['site_adi'] ?? 'VegasRoyalSpin');
$headDescription = (string) ($headMeta['description'] ?? $headBranding['description'] ?? $ayar['site_aciklama'] ?? '');
$headTitle = (string) ($headMeta['title'] ?? trim($headSiteName . ' - ' . $headDescription));
$headFaviconPath = (string) ($headBranding['favicon_url'] ?? '/assets/images/favicons/favicon.svg');
$headManifestPath = (string) ($headBranding['manifest_url'] ?? '/assets/images/favicons/site.webmanifest');
$headManifestDefaultPath = '/assets/images/favicons/site.webmanifest';
$headCurrentHost = strtolower((string) preg_replace('/:\\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
$headManifestHost = strtolower((string) (parse_url($headManifestPath, PHP_URL_HOST) ?: ''));
if ($headManifestPath === '' || ($headManifestHost !== '' && $headManifestHost !== $headCurrentHost)) {
    $headManifestPath = $headManifestDefaultPath;
}
$headManifestUrl = $headManifestPath;
$headOgImageUrl = (string) ($headBranding['og_image_url'] ?? $headBranding['logo_url'] ?? '');
if (class_exists('ApiMediaUrl', false)) {
    $headFaviconPath = ApiMediaUrl::resolve($headFaviconPath);
    $headOgImageUrl = ApiMediaUrl::resolve($headOgImageUrl);
}
$headFaviconUrl = $headFaviconPath . '?v=' . (int)(@filemtime(BASE_PATH . '/assets/images/favicons/favicon.svg') ?: time());
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
  <link rel="preload" href="/assets/BetConstruct-Icons.CPdFP1TD.woff2" as="font" type="font/woff2" crossorigin>
  <link href="/assets/css/fonts-critical.css?v=<?= htmlspecialchars((string) (file_exists($assetCssDir . '/fonts-critical.css') ? filemtime($assetCssDir . '/fonts-critical.css') : $assetVer), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/site-bootstrap-utils.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/izitoast/dist/css/iziToast.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
  <link rel="stylesheet" href="/assets/css/sports-icon.css?v=<?= htmlspecialchars($sportsIconCssVer, ENT_QUOTES, 'UTF-8') ?>">
  <link href="/assets/css/site-global.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/layout-header.css?v=<?= htmlspecialchars($headerCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/layout-sidebar.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/site-components.css?v=<?= htmlspecialchars($siteComponentsCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="/assets/css/profile-cm622.css?v=<?= htmlspecialchars($cm622ProfileCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/profile-cm622-fix.css?v=<?= htmlspecialchars($cm622ProfileFixCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/profile-cm622-original-deposit.css?v=<?= htmlspecialchars($cm622ProfileOriginalDepositCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/profile-cm622-original-filters-tables.css?v=<?= htmlspecialchars($cm622ProfileOriginalFiltersCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/profile-cm622-original-complete.css?v=<?= htmlspecialchars($cm622ProfileOriginalCompleteCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/site-modal.css?v=<?= htmlspecialchars($modalCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/site-responsive.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/layout-footer.css?v=<?= htmlspecialchars($footerBcCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/mobile-bottom.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/auth-register.css?v=<?= htmlspecialchars($registerCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/auth-login.css?v=<?= htmlspecialchars($loginCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="/assets/css/auth-register-modal.css?v=<?= htmlspecialchars($registerModalCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="/assets/css/auth-login-modal.css?v=<?= htmlspecialchars($loginModalCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/vendor-daterangepicker.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <?php if ($requestPath === '/' || $scriptName === 'index.php'): ?>
  <link href="/assets/css/home.css?v=<?= htmlspecialchars($homeCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/home-jackpot.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <?php endif; ?>
  <?php if ($requestPath === '/livecasino' || $scriptName === 'livecasino.php'):
      $bcCm622LiveCssPath = $assetCssDir . '/casino-live-cm622.css';
      $bcCm622LiveCssVer = (string) (file_exists($bcCm622LiveCssPath) ? filemtime($bcCm622LiveCssPath) : $assetVer);
      $lobbyStableCssVer = (string) (file_exists($assetCssDir . '/casino-lobby-stable.css') ? filemtime($assetCssDir . '/casino-lobby-stable.css') : $assetVer);
  ?>
  <link href="/assets/css/casino-live-cm622.css?v=<?= htmlspecialchars($bcCm622LiveCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/casino-lobby-stable.css?v=<?= htmlspecialchars($lobbyStableCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <?php elseif ($requestPath === '/slot' || $requestPath === '/bgaming' || $requestPath === '/sanal-sporlar' || $scriptName === 'slot.php' || $scriptName === 'bgaming.php' || $scriptName === 'sanal-sporlar.php'):
      $bcCm622SlotsCssPath = $assetCssDir . '/casino-slots-cm622.css';
      $bcCm622SlotsCssVer = (string) (file_exists($bcCm622SlotsCssPath) ? filemtime($bcCm622SlotsCssPath) : $assetVer);
      $isBgamingHead = ($requestPath === '/bgaming' || $scriptName === 'bgaming.php');
      $bgamingMotionCssPath = $assetCssDir . '/casino-bgaming-motion.css';
      $bgamingMotionCssVer = (string) (file_exists($bgamingMotionCssPath) ? filemtime($bgamingMotionCssPath) : $assetVer);
      $lobbyStableCssVer = (string) (file_exists($assetCssDir . '/casino-lobby-stable.css') ? filemtime($assetCssDir . '/casino-lobby-stable.css') : $assetVer);
  ?>
  <link href="/assets/css/casino-slots-cm622.css?v=<?= htmlspecialchars($bcCm622SlotsCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <?php if ($isBgamingHead): ?>
  <link href="/assets/css/casino-bgaming-motion.css?v=<?= htmlspecialchars($bgamingMotionCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <?php endif; ?>
  <link href="/assets/css/casino-lobby-stable.css?v=<?= htmlspecialchars($lobbyStableCssVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/home-jackpot.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/home-winners.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <?php endif; ?>
  <?php if ($requestPath === '/beni-ara' || $scriptName === 'beni-ara.php'): ?>
  <link href="/assets/css/page-beni-ara.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <?php endif; ?>
  <?php if ($requestPath === '/jackpot' || $scriptName === 'jackpot.php'): ?>
  <link href="/assets/css/home-jackpot.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <?php endif; ?>
  <?php if ($isPromosyonlar || $isPromotions): ?>
  <link href="/assets/css/promotions.css?v=<?= htmlspecialchars($promoVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <link href="/assets/css/promotions-bonus-modal.css?v=<?= htmlspecialchars($bonusModalVer, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
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
  if (!function_exists('csp_connect_src_directive') && is_readable(BASE_PATH . '/config/member_api_public.php')) {
      require_once BASE_PATH . '/config/member_api_public.php';
  }
  $cspConnectSrc = function_exists('csp_connect_src_directive')
      ? csp_connect_src_directive()
      : "connect-src 'self' wss://*.sptpub.com https://*.sptpub.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://*.google-analytics.com https://analytics.google.com https://*.analytics.google.com https://www.google.com https://*.googletagmanager.com https://stats.g.doubleclick.net https://*.livechatinc.com wss://*.livechatinc.com https://*.livechat.com wss://*.livechat.com https://*.livechat-static.com https://admin.vegasroyalspin.com https://challenges.cloudflare.com";
  ?>
  <meta http-equiv="Content-Security-Policy" content="<?= htmlspecialchars($cspConnectSrc, ENT_QUOTES, 'UTF-8') ?>">
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
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
  <script src="/assets/js/toastify-helper.js?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="/assets/js/pwa-register.js?v=<?= rawurlencode($pwaRegisterVer) ?>"></script>
  <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
</head>
