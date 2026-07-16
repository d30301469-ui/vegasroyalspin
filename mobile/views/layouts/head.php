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
$mobileCssDir = BASE_PATH . '/mobile/assets/css';

$assetVersion = static function (string $path): string {
  if (!is_file($path)) {
    return '1';
  }

  $hash = @md5_file($path);
  if ($hash !== false) {
    return substr($hash, 0, 12) . '-' . (string) filesize($path);
  }

  return (string) filemtime($path) . '-' . (string) filesize($path);
};

$ver = $assetVersion;
$assetFingerprint = $assetVersion;

$headBranding = (isset($siteBranding) && is_array($siteBranding)) ? $siteBranding : [];
$headMeta = (isset($siteMeta) && is_array($siteMeta)) ? $siteMeta : [];
$headSiteName = (string) ($headBranding['site_name'] ?? $ayar['site_adi'] ?? 'MaltaBet');
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
$slotRoutes = ['/slot', '/livecasino', '/bgaming', '/sanal-sporlar'];
$isSlotRoute = in_array($requestPath, $slotRoutes, true);
$mobileBodyClass = 'mobile-site' . ($isSlotRoute ? ' slot-page-active' : '');
$mobileHtmlClass = 'is-mobile mobile-root' . ($isSlotRoute ? ' slot-page-active' : '');
?>
<!doctype html>
<html lang="tr" class="<?= htmlspecialchars($mobileHtmlClass, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8">
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

  <link rel="stylesheet" href="/assets/css/bootstrap-utils.css?v=<?= $ver($assetCssDir . '/global.css') ?>">
  <link rel="stylesheet" href="/assets/css/global.css?v=<?= $ver($assetCssDir . '/global.css') ?>">
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= rawurlencode($assetFingerprint($assetCssDir . '/header.css')) ?>">
  <link rel="stylesheet" href="/assets/css/sidebar.css?v=<?= $ver($assetCssDir . '/sidebar.css') ?>">
  <link rel="stylesheet" href="/assets/css/components.css?v=<?= $ver($assetCssDir . '/components.css') ?>">
  <link rel="stylesheet" href="/assets/css/profile.css?v=<?= $ver($assetCssDir . '/profile.css') ?>">
  <link rel="stylesheet" href="/assets/css/responsive.css?v=<?= $ver($assetCssDir . '/responsive.css') ?>">
  <link rel="stylesheet" href="/assets/css/mobile_bottom.css?v=<?= $ver($assetCssDir . '/mobile_bottom.css') ?>">
  <link rel="stylesheet" href="/assets/css/home.css?v=<?= $ver($assetCssDir . '/home.css') ?>">
  <link rel="stylesheet" href="/assets/css/slots.css?v=<?= $ver($assetCssDir . '/slots.css') ?>">
  <link rel="stylesheet" href="/assets/css/jackpot.css?v=<?= $ver($assetCssDir . '/jackpot.css') ?>">
  <link rel="stylesheet" href="/assets/css/winners.css?v=<?= $ver($assetCssDir . '/winners.css') ?>">
  <link rel="stylesheet" href="/assets/css/swiper-bundle.min.css?v=<?= $ver($assetCssDir . '/swiper-bundle.min.css') ?>">
  <link rel="stylesheet" href="/assets/css/slider.css?v=<?= $ver($assetCssDir . '/slider.css') ?>">
  <link rel="stylesheet" href="/assets/css/slider-mobile-bc.css?v=<?= $ver($assetCssDir . '/slider-mobile-bc.css') ?>">
  <link rel="stylesheet" href="/assets/css/footer-bc.css?v=<?= $ver($assetCssDir . '/footer-bc.css') ?>">
  <link rel="stylesheet" href="/assets/css/modal.css?v=<?= $ver($assetCssDir . '/modal.css') ?>">
  <link rel="stylesheet" href="/assets/css/login.css?v=<?= $ver($assetCssDir . '/login.css') ?>">
  <link rel="stylesheet" href="/assets/css/register.css?v=<?= $ver($assetCssDir . '/register.css') ?>">
  <link rel="stylesheet" href="/assets/css/auth-sliders.css?v=<?= $ver($assetCssDir . '/auth-sliders.css') ?>">

  <link rel="stylesheet" href="/assets/css/bc-mobile-index.css?v=<?= rawurlencode($assetFingerprint($assetCssDir . '/bc-mobile-index.css')) ?>">
  <link rel="stylesheet" href="/assets/css/bc-mobile-header-original.css?v=<?= rawurlencode($assetFingerprint($assetCssDir . '/bc-mobile-header-original.css')) ?>">
  <link rel="stylesheet" href="/mobile/assets/css/base.css?v=<?= $ver($mobileCssDir . '/base.css') ?>">
  <link rel="stylesheet" href="/mobile/assets/css/menu.css?v=<?= rawurlencode($assetFingerprint($mobileCssDir . '/menu.css')) ?>">
  <link rel="stylesheet" href="/assets/css/mobile-smart-panel.css?v=<?= rawurlencode($assetFingerprint($assetCssDir . '/mobile-smart-panel.css')) ?>">
  <link rel="stylesheet" href="/assets/css/mobile-right-sheet.css?v=<?= rawurlencode($assetFingerprint($assetCssDir . '/mobile-right-sheet.css')) ?>">
  <link rel="stylesheet" href="/mobile/assets/css/mobile-right-sheet.css?v=<?= rawurlencode($assetFingerprint($mobileCssDir . '/mobile-right-sheet.css')) ?>">
  <link rel="stylesheet" href="/mobile/assets/css/home.css?v=<?= $ver($mobileCssDir . '/home.css') ?>">
  <link rel="stylesheet" href="/mobile/assets/css/home-widgets.css?v=<?= $ver($mobileCssDir . '/home-widgets.css') ?>">
  <link rel="stylesheet" href="/mobile/assets/css/slots.css?v=<?= $ver($mobileCssDir . '/slots.css') ?>">
  <link rel="stylesheet" href="/mobile/assets/css/bottom-bar.css?v=<?= $ver($mobileCssDir . '/bottom-bar.css') ?>">
  <link rel="stylesheet" href="/mobile/assets/css/footer.css?v=<?= $ver($mobileCssDir . '/footer.css') ?>">
  <link rel="stylesheet" href="/mobile/assets/css/auth-modals.css?v=<?= $ver($mobileCssDir . '/auth-modals.css') ?>">
  <link rel="stylesheet" href="/assets/css/login-modal.css?v=<?= $ver($assetCssDir . '/login-modal.css') ?>">
  <link rel="stylesheet" href="/assets/css/register-modal.css?v=<?= $ver($assetCssDir . '/register-modal.css') ?>">
  <?php if ($requestPath === '/beni-ara'): ?>
  <link rel="stylesheet" href="/assets/css/beni-ara.css?v=<?= $ver($assetCssDir . '/beni-ara.css') ?>">
  <link rel="stylesheet" href="/mobile/assets/css/beni-ara.css?v=<?= $ver($mobileCssDir . '/beni-ara.css') ?>">
  <?php endif; ?>
  <script defer src="/assets/js/swiper-bundle.min.js?v=<?= $ver(BASE_PATH . '/assets/js/swiper-bundle.min.js') ?>"></script>
  <script defer src="/assets/js/pwa-register.js?v=<?= $ver(BASE_PATH . '/assets/js/pwa-register.js') ?>"></script>
  <script defer src="/assets/js/mobile-right-sheet.js?v=<?= rawurlencode($assetFingerprint(BASE_PATH . '/assets/js/mobile-right-sheet.js')) ?>"></script>
  <script defer src="/assets/js/footer.js?v=<?= rawurlencode($assetFingerprint(BASE_PATH . '/assets/js/footer.js')) ?>"></script>
</head>
<body class="<?= htmlspecialchars($mobileBodyClass, ENT_QUOTES, 'UTF-8') ?>">
<?php include MOBILE_PATH . '/views/layouts/bc-root-open.php'; ?>
