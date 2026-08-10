<?php
/**
 * Mobil head/layout iskeleti.
 * Mobil sayfalarda boş kalması, tüm mobil CSS zincirini ve root/body sınıflarını bozduğu için
 * burada minimum ama tam bir iskelet sağlanır.
 */

if (!defined('BASE_PATH')) {
	define('BASE_PATH', dirname(dirname(__DIR__, 2)));
}

$assetCssDir = BASE_PATH . '/assets/css';
$mobileCssDir = BASE_PATH . '/assets/css';

$assetVersion = static function (string $path): string {
  if (!is_file($path)) {
    return '1';
  }

  $hash = @md5_file($path);
  if ($hash !== false) {
    return substr($hash, 0, 12) . '-' . (string) @filesize($path);
  }

  $mtime = @filemtime($path);
  $fsize = @filesize($path);
  return (string) ($mtime !== false ? $mtime : 0) . '-' . (string) ($fsize !== false ? $fsize : 0);
};

$ver = $assetVersion;
$assetFingerprint = $assetVersion;

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
$headFaviconUrl = (function_exists('cms_asset_url') ? cms_asset_url($headFaviconPath) : $headFaviconPath) . '?v=' . $headFaviconVersion;
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
$headManifestUrl = (function_exists('cms_asset_url') ? cms_asset_url($headManifestPath) : $headManifestPath) . '?v=' . $headManifestVersion;
$headThemeColor = (string) ($headMeta['theme_color'] ?? '#120023');
$requestPathRaw = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestPath = $requestPathRaw === '/' ? '/' : rtrim($requestPathRaw, '/');
$slotRoutes = ['/slot', '/bgaming', '/sanal-sporlar'];
$isLiveCasinoRoute = ($requestPath === '/livecasino');
$isSlotRoute = in_array($requestPath, $slotRoutes, true);
$isPromotionsRoute = ($requestPath === '/promotions' || $requestPath === '/promosyonlar');
$promoCssVer = (string) (is_file($assetCssDir . '/promotions.css') ? filemtime($assetCssDir . '/promotions.css') : 1);
$bonusModalCssVer = (string) (is_file($assetCssDir . '/promotions-bonus-modal.css') ? filemtime($assetCssDir . '/promotions-bonus-modal.css') : 1);
// Ana sayfada jackpot|kazananlar widget'ı var; CSS sadece slot rotasında
// yüklenirse paneller/kartlar stillenmez (ham metin listesi gibi görünür).
$isHomeRoute = ($requestPath === '/');
$needsJackpotAssets = true;
$mobilePageActiveClass = $isLiveCasinoRoute
    ? ' slot-page-active livecasino-page-active'
    : ($isSlotRoute ? ' slot-page-active' : '');
$mobileBodyClass = 'mobile-site' . $mobilePageActiveClass;
$mobileHtmlClass = 'is-mobile mobile-root' . $mobilePageActiveClass;
$isSportsbookLightweight = defined('SPORTSBOOK_LIGHTWEIGHT_LAYOUT') && SPORTSBOOK_LIGHTWEIGHT_LAYOUT;
?>
<!doctype html>
<html lang="<?= htmlspecialchars(function_exists('current_locale') ? current_locale() : 'tr', ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($mobileHtmlClass, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8">
  <script>
    try {
      if (localStorage.getItem('app_member_jwt')) {
        document.documentElement.classList.add('member-session-hint');
      }
    } catch (e) {}
  </script>
  <base href="/">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, maximum-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="<?= htmlspecialchars($headThemeColor, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="description" content="<?= htmlspecialchars($headDescription, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($headFaviconUrl, ENT_QUOTES, 'UTF-8') ?>" id="appFavicon">
  <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/favicons/apple-touch-icon.png?v=<?= time() ?>">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="VegasRoyal">
  <link rel="manifest" href="<?= htmlspecialchars($headManifestUrl, ENT_QUOTES, 'UTF-8') ?>">
  <title><?= htmlspecialchars($headTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <?php if ($isPromotionsRoute): ?>
  <link rel="preload" href="/assets/css/promotions.css?v=<?= htmlspecialchars($promoCssVer, ENT_QUOTES, 'UTF-8') ?>" as="style">
  <link rel="preload" href="/assets/css/promotions-bonus-modal.css?v=<?= htmlspecialchars($bonusModalCssVer, ENT_QUOTES, 'UTF-8') ?>" as="style">
  <?php endif; ?>
  <style>
    html.mobile-root {
      --body-bg: #0e0124;
      --headerBG: #0e0124;
      background: #0e0124 url('/assets/images/mobile-home-bg.jpg') no-repeat 50% 50% / cover !important;
    }
    body.mobile-site {
      --body-bg: #0e0124;
      --headerBG: #0e0124;
      background: #0e0124 url('/assets/images/mobile-home-bg.jpg') no-repeat 50% 50% / cover !important;
      background-attachment: scroll !important;
    }
  </style>

  <link rel="stylesheet" href="/assets/css/site-bootstrap-utils.css?v=<?= $ver($assetCssDir . '/site-global.css') ?>">
  <link rel="stylesheet" href="/assets/css/site-global.css?v=<?= $ver($assetCssDir . '/site-global.css') ?>">
  <link rel="stylesheet" href="/assets/css/layout-header.css?v=<?= rawurlencode($assetFingerprint($assetCssDir . '/layout-header.css')) ?>">
  <link rel="stylesheet" href="/assets/css/layout-sidebar.css?v=<?= $ver($assetCssDir . '/layout-sidebar.css') ?>">
  <link rel="stylesheet" href="/assets/css/site-components.css?v=<?= $ver($assetCssDir . '/site-components.css') ?>">
  <link rel="stylesheet" href="/assets/css/profile-cm622.css?v=<?= $ver($assetCssDir . '/profile-cm622.css') ?>">
  <link rel="stylesheet" href="/assets/css/profile-cm622-fix.css?v=<?= $ver($assetCssDir . '/profile-cm622-fix.css') ?>">
  <link rel="stylesheet" href="/assets/css/site-responsive.css?v=<?= $ver($assetCssDir . '/site-responsive.css') ?>">
  <link rel="stylesheet" href="/assets/css/mobile-bottom.css?v=<?= $ver($assetCssDir . '/mobile-bottom.css') ?>">
  <link rel="stylesheet" href="/assets/css/home.css?v=<?= $ver($assetCssDir . '/home.css') ?>">
  <?php if ($needsJackpotAssets): ?>
  <?php if ($isLiveCasinoRoute): ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/casino-live-cm622.css?v=<?= $ver($assetCssDir . '/casino-live-cm622.css') ?>">
  <?php elseif ($isSlotRoute): ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/casino-slots-cm622.css?v=<?= $ver($assetCssDir . '/casino-slots-cm622.css') ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="/assets/css/home-jackpot.css?v=<?= $ver($assetCssDir . '/home-jackpot.css') ?>">
  <link rel="stylesheet" href="/assets/css/home-winners.css?v=<?= $ver($assetCssDir . '/home-winners.css') ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="/assets/css/vendor-swiper.css?v=<?= $ver($assetCssDir . '/vendor-swiper.css') ?>">
  <link rel="stylesheet" href="/assets/css/home-slider.css?v=<?= $ver($assetCssDir . '/home-slider.css') ?>">
  <link rel="stylesheet" href="/assets/css/home-slider-mobile.css?v=<?= $ver($assetCssDir . '/home-slider-mobile.css') ?>">
  <link rel="stylesheet" href="/assets/css/layout-footer.css?v=<?= $ver($assetCssDir . '/layout-footer.css') ?>">
  <link rel="stylesheet" href="/assets/css/site-modal.css?v=<?= $ver($assetCssDir . '/site-modal.css') ?>">
  <link rel="stylesheet" href="/assets/css/auth-login.css?v=<?= $ver($assetCssDir . '/auth-login.css') ?>">
  <link rel="stylesheet" href="/assets/css/auth-register.css?v=<?= $ver($assetCssDir . '/auth-register.css') ?>">
  <link rel="stylesheet" href="/assets/css/auth-sliders.css?v=<?= $ver($assetCssDir . '/auth-sliders.css') ?>">
  <?php if ($isPromotionsRoute): ?>
  <link rel="stylesheet" href="/assets/css/promotions.css?v=<?= htmlspecialchars($promoCssVer, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="/assets/css/promotions-bonus-modal.css?v=<?= htmlspecialchars($bonusModalCssVer, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>

  <link rel="stylesheet" href="/assets/css/mobile-bc-index.css?v=<?= rawurlencode($assetFingerprint($assetCssDir . '/mobile-bc-index.css')) ?>">
  <link rel="stylesheet" href="/assets/css/mobile-bc-header.css?v=<?= rawurlencode($assetFingerprint($assetCssDir . '/mobile-bc-header.css')) ?>">
  <link rel="stylesheet" href="/assets/css/mobile-bc.css?v=<?= rawurlencode($assetFingerprint($assetCssDir . '/mobile-bc.css')) ?>">
  <link rel="stylesheet" href="/assets/css/mobile-bc-custom.css?v=<?= rawurlencode($assetFingerprint($assetCssDir . '/mobile-bc-custom.css')) ?>">

  <link rel="stylesheet" href="/assets/css/mobile-base.css?v=<?= $ver($mobileCssDir . '/mobile-base.css') ?>">
  <link rel="stylesheet" href="/assets/css/mobile-header.css?v=<?= rawurlencode($assetFingerprint($mobileCssDir . '/mobile-header.css')) ?>">
  <link rel="stylesheet" href="/assets/css/mobile-menu.css?v=<?= rawurlencode($assetFingerprint($mobileCssDir . '/mobile-menu.css')) ?>">
  <link rel="stylesheet" href="/assets/css/mobile-smart-panel.css?v=<?= rawurlencode($assetFingerprint($assetCssDir . '/mobile-smart-panel.css')) ?>">
  <link rel="stylesheet" href="/assets/css/mobile-right-sheet.css?v=<?= rawurlencode($assetFingerprint($assetCssDir . '/mobile-right-sheet.css')) ?>">
  <link rel="stylesheet" href="/assets/css/mobile-right-sheet-extra.css?v=<?= rawurlencode($assetFingerprint($mobileCssDir . '/mobile-right-sheet-extra.css')) ?>">
  <link rel="stylesheet" href="/assets/css/mobile-home.css?v=<?= $ver($mobileCssDir . '/mobile-home.css') ?>">
  <link rel="stylesheet" href="/assets/css/mobile-home-widgets.css?v=<?= $ver($mobileCssDir . '/mobile-home-widgets.css') ?>">
  <?php if ($isLiveCasinoRoute): ?>
  <link rel="stylesheet" href="/assets/css/mobile-live.css?v=<?= $ver($mobileCssDir . '/mobile-live.css') ?>">
  <?php elseif ($isSlotRoute): ?>
  <link rel="stylesheet" href="/assets/css/mobile-slots.css?v=<?= $ver($mobileCssDir . '/mobile-slots.css') ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="/assets/css/mobile-bottom-bar.css?v=<?= $ver($mobileCssDir . '/mobile-bottom-bar.css') ?>">
  <link rel="stylesheet" href="/assets/css/mobile-footer.css?v=<?= $ver($mobileCssDir . '/mobile-footer.css') ?>">
  <link rel="stylesheet" href="/assets/css/mobile-auth-modals.css?v=<?= $ver($mobileCssDir . '/mobile-auth-modals.css') ?>">
  <link rel="stylesheet" href="/assets/css/mobile-profile-panel.css?v=<?= $ver($mobileCssDir . '/mobile-profile-panel.css') ?>">
  <link rel="stylesheet" href="/assets/css/auth-login-modal.css?v=<?= $ver($assetCssDir . '/auth-login-modal.css') ?>">
  <link rel="stylesheet" href="/assets/css/auth-register-modal.css?v=<?= $ver($assetCssDir . '/auth-register-modal.css') ?>">
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
  <?php if ($requestPath === '/beni-ara'): ?>
  <link rel="stylesheet" href="/assets/css/page-beni-ara.css?v=<?= $ver($assetCssDir . '/page-beni-ara.css') ?>">
  <link rel="stylesheet" href="/assets/css/mobile-beni-ara.css?v=<?= $ver($mobileCssDir . '/mobile-beni-ara.css') ?>">
  <?php endif; ?>
  <?php if (!$isSportsbookLightweight): ?>
  <script defer src="/assets/js/swiper-bundle.min.js?v=<?= $ver(BASE_PATH . '/assets/js/swiper-bundle.min.js') ?>"></script>
  <script defer src="/assets/js/pwa-register.js?v=<?= $ver(BASE_PATH . '/assets/js/pwa-register.js') ?>"></script>
  <script defer src="/assets/js/mobile-right-sheet.js?v=<?= rawurlencode($assetFingerprint(BASE_PATH . '/assets/js/mobile-right-sheet.js')) ?>"></script>
  <?php endif; ?>
</head>
<body class="<?= htmlspecialchars($mobileBodyClass, ENT_QUOTES, 'UTF-8') ?>">
<?php include MOBILE_PATH . '/views/layouts/bc-root-open.php'; ?>
