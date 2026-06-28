<?php
$assetCssDir = BASE_PATH . '/assets/css';
$assetVer = (string) (file_exists($assetCssDir . '/global.css') ? filemtime($assetCssDir . '/global.css') : 0) ?: '1';
$modalCssVer = (string) (file_exists($assetCssDir . '/modal.css') ? filemtime($assetCssDir . '/modal.css') : $assetVer);
$registerCssVer = (string) (file_exists($assetCssDir . '/register.css') ? filemtime($assetCssDir . '/register.css') : $assetVer);
$loginCssVer = (string) (file_exists($assetCssDir . '/login.css') ? filemtime($assetCssDir . '/login.css') : $assetVer);
$authSlidersCssVer = (string) (file_exists($assetCssDir . '/auth-sliders.css') ? filemtime($assetCssDir . '/auth-sliders.css') : $assetVer);
$homeCssVer = (string) (file_exists($assetCssDir . '/home.css') ? filemtime($assetCssDir . '/home.css') : $assetVer);
$requestPathRaw = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestPath = $requestPathRaw === '/' ? '/' : rtrim($requestPathRaw, '/');
$isPromosyonlar = ($requestPath === '/promosyonlar');
$isPromotions = ($requestPath === '/promotions');
if ($isPromosyonlar || $isPromotions) {
  $promoVer = (string) (file_exists($assetCssDir . '/promosyonlar.css') ? filemtime($assetCssDir . '/promosyonlar.css') : $assetVer);
  $bonusModalVer = (string) (file_exists($assetCssDir . '/bonus-detail-modal.css') ? filemtime($assetCssDir . '/bonus-detail-modal.css') : $assetVer);
}
$headBranding = is_array($siteBranding ?? null) ? $siteBranding : [];
$headMeta = is_array($siteMeta ?? null) ? $siteMeta : [];
$headSiteName = (string) ($headBranding['site_name'] ?? $ayar['site_adi'] ?? 'MaltaBet');
$headDescription = (string) ($headMeta['description'] ?? $headBranding['description'] ?? $ayar['site_aciklama'] ?? '');
$headTitle = (string) ($headMeta['title'] ?? trim($headSiteName . ' - ' . $headDescription));
$headFaviconUrl = (string) ($headBranding['favicon_url'] ?? '/assets/images/favicons/favicon.svg');
$headManifestUrl = (string) ($headBranding['manifest_url'] ?? '/assets/images/favicons/site.webmanifest');
$headOgImageUrl = (string) ($headBranding['og_image_url'] ?? $headBranding['logo_url'] ?? '/assets/images/MaltaBetLogo.png');
$headKeywords = (string) ($headMeta['keywords'] ?? '');
$headRobots = (string) ($headMeta['robots'] ?? 'index, follow');
$headLanguage = (string) ($headMeta['language'] ?? 'tr');
$headThemeColor = (string) ($headMeta['theme_color'] ?? '#120023');
?>
<!doctype html>
<html lang="tr" class="mobile-root is-mobile">
<head>
  <meta charset="utf-8">
  <base href="/">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
  <meta name="description" content="<?= htmlspecialchars($headDescription, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="keywords" content="<?= htmlspecialchars($headKeywords, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="robots" content="<?= htmlspecialchars($headRobots, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="language" content="<?= htmlspecialchars($headLanguage, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="theme-color" content="<?= htmlspecialchars($headThemeColor, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:title" content="<?= htmlspecialchars($headTitle, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:description" content="<?= htmlspecialchars($headDescription, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:image" content="<?= htmlspecialchars($headOgImageUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:site_name" content="<?= htmlspecialchars($headSiteName, ENT_QUOTES, 'UTF-8') ?>">
  <title><?= htmlspecialchars($headTitle, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="icon" href="<?= htmlspecialchars($headFaviconUrl, ENT_QUOTES, 'UTF-8') ?>" id="appFavicon">
  <link rel="manifest" href="<?= htmlspecialchars($headManifestUrl, ENT_QUOTES, 'UTF-8') ?>">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/izitoast/dist/css/iziToast.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick-theme.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

  <link href="/assets/css/bootstrap-utils.css?v=<?= $assetVer ?>" rel="stylesheet">
  <link href="/assets/css/global.css?v=<?= $assetVer ?>" rel="stylesheet">
  <?php
  $mobileBaseCssPath = BASE_PATH . '/mobile/assets/css/base.css';
  $mobileBaseCssVer = (string) (file_exists($mobileBaseCssPath) ? filemtime($mobileBaseCssPath) : $assetVer);
  ?>
  <link href="/mobile/assets/css/base.css?v=<?= htmlspecialchars($mobileBaseCssVer) ?>" rel="stylesheet">
  <link href="/assets/css/components.css?v=<?= $assetVer ?>" rel="stylesheet">
  <link href="/assets/css/modal.css?v=<?= $modalCssVer ?>" rel="stylesheet">
  <link href="/assets/css/register.css?v=<?= $registerCssVer ?>" rel="stylesheet">
  <link href="/assets/css/login.css?v=<?= $loginCssVer ?>" rel="stylesheet">
  <link href="/assets/css/auth-sliders.css?v=<?= htmlspecialchars($authSlidersCssVer) ?>" rel="stylesheet">
  <link href="/assets/css/daterangepicker.css?v=<?= $assetVer ?>" rel="stylesheet">
  <link href="/assets/sports-icon.css?v=<?= $assetVer ?>" rel="stylesheet">
  <link href="/assets/css/profile.css?v=<?= $assetVer ?>" rel="stylesheet">
  <?php
  $bcIndexPath = BASE_PATH . '/assets/css/bc-mobile-index.css';
  $bcCustomPath = BASE_PATH . '/assets/css/bc-mobile-custom.css';
  $bcMaltabetPath = BASE_PATH . '/assets/css/bc-mobile-maltabet.css';
  $bcIndexVer = (string) (file_exists($bcIndexPath) ? filemtime($bcIndexPath) : $assetVer);
  $bcCustomVer = (string) (file_exists($bcCustomPath) ? filemtime($bcCustomPath) : $assetVer);
  $bcMaltabetVer = (string) (file_exists($bcMaltabetPath) ? filemtime($bcMaltabetPath) : $assetVer);
  ?>
  <link href="/assets/css/bc-mobile-index.css?v=<?= htmlspecialchars($bcIndexVer) ?>" rel="stylesheet">
  <link href="/assets/css/bc-mobile-custom.css?v=<?= htmlspecialchars($bcCustomVer) ?>" rel="stylesheet">
  <link href="/assets/css/bc-mobile-maltabet.css?v=<?= htmlspecialchars($bcMaltabetVer) ?>" rel="stylesheet">
  <?php
  $bottomBarCssPath = BASE_PATH . '/mobile/assets/css/bottom-bar.css';
  $bottomBarCssVer = (string) (file_exists($bottomBarCssPath) ? filemtime($bottomBarCssPath) : $assetVer);
  ?>
  <link href="/mobile/assets/css/bottom-bar.css?v=<?= htmlspecialchars($bottomBarCssVer) ?>" rel="stylesheet">
  <?php
  $headerBcCssPath = BASE_PATH . '/mobile/assets/css/header-bc.css';
  $headerBcCssVer = (string) (file_exists($headerBcCssPath) ? filemtime($headerBcCssPath) : $assetVer);
  $headerOriginalCssPath = BASE_PATH . '/assets/css/bc-mobile-header-original.css';
  $headerOriginalCssVer = (string) (file_exists($headerOriginalCssPath) ? filemtime($headerOriginalCssPath) : $assetVer);
  $mobileSmartPanelCssPath = BASE_PATH . '/assets/css/mobile-smart-panel.css';
  $mobileSmartPanelCssVer = (string) (file_exists($mobileSmartPanelCssPath) ? filemtime($mobileSmartPanelCssPath) : $assetVer);
  ?>
  <link href="/mobile/assets/css/header-bc.css?v=<?= htmlspecialchars($headerBcCssVer) ?>" rel="stylesheet">
  <link href="/assets/css/bc-mobile-header-original.css?v=<?= htmlspecialchars($headerOriginalCssVer) ?>" rel="stylesheet">
  <link href="/assets/css/mobile-smart-panel.css?v=<?= htmlspecialchars($mobileSmartPanelCssVer) ?>" rel="stylesheet">
  <link href="/assets/css/footer-bc.css?v=<?= $assetVer ?>" rel="stylesheet">
  <?php
  $mobileAuthModalsCssPath = BASE_PATH . '/mobile/assets/css/auth-modals.css';
  $mobileAuthModalsCssVer = (string) (file_exists($mobileAuthModalsCssPath) ? filemtime($mobileAuthModalsCssPath) : $assetVer);
  ?>
  <link href="/mobile/assets/css/auth-modals.css?v=<?= htmlspecialchars($mobileAuthModalsCssVer) ?>" rel="stylesheet">
  <link href="/mobile/assets/css/footer.css?v=<?= $assetVer ?>" rel="stylesheet">
  <link href="/mobile/assets/css/menu.css?v=<?= $assetVer ?>" rel="stylesheet">
  <?php
  $mobileRightSheetCssPath = BASE_PATH . '/assets/css/mobile-right-sheet.css';
  $mobileRightSheetCssVer = (string) (file_exists($mobileRightSheetCssPath) ? filemtime($mobileRightSheetCssPath) : $assetVer);
  ?>
  <link href="/assets/css/mobile-right-sheet.css?v=<?= htmlspecialchars($mobileRightSheetCssVer) ?>" rel="stylesheet">
  <?php
  $mobileBetslipCssPath = BASE_PATH . '/mobile/assets/css/betslip-mobile.css';
  $mobileBetslipCssVer = (string) (file_exists($mobileBetslipCssPath) ? filemtime($mobileBetslipCssPath) : $assetVer);
  ?>
  <link href="/mobile/assets/css/betslip-mobile.css?v=<?= htmlspecialchars($mobileBetslipCssVer) ?>" rel="stylesheet">

  <?php if ($requestPath === '/'): ?>
    <link href="/assets/css/home.css?v=<?= $homeCssVer ?>" rel="stylesheet">
    <link href="/assets/css/slots.css?v=<?= $assetVer ?>" rel="stylesheet">
    <link href="/assets/css/jackpot.css?v=<?= $assetVer ?>" rel="stylesheet">
    <link href="/assets/css/winners.css?v=<?= $assetVer ?>" rel="stylesheet">
    <link href="/mobile/assets/css/home.css?v=<?= $assetVer ?>" rel="stylesheet">
    <?php
    $sliderMobileBcCssPath = BASE_PATH . '/assets/css/slider-mobile-bc.css';
    $sliderMobileBcCssVer = (string) (file_exists($sliderMobileBcCssPath) ? filemtime($sliderMobileBcCssPath) : $assetVer);
    ?>
    <link href="/assets/css/slider-mobile-bc.css?v=<?= htmlspecialchars($sliderMobileBcCssVer) ?>" rel="stylesheet">
  <?php endif; ?>
  <?php if ($requestPath === '/slot'): ?>
    <link href="/assets/css/slots.css?v=<?= $assetVer ?>" rel="stylesheet">
    <link href="/assets/css/jackpot.css?v=<?= $assetVer ?>" rel="stylesheet">
    <link href="/assets/css/winners.css?v=<?= $assetVer ?>" rel="stylesheet">
    <link href="/mobile/assets/css/slots.css?v=<?= $assetVer ?>" rel="stylesheet">
  <?php endif; ?>
  <?php if (strpos($requestPath, '/profile') === 0): ?>
    <link href="/mobile/assets/css/profile.css?v=<?= $assetVer ?>" rel="stylesheet">
  <?php endif; ?>
  <?php if ($requestPath === '/beni-ara'):
    $beniAraCssVer = (string) (file_exists($assetCssDir . '/beni-ara.css') ? filemtime($assetCssDir . '/beni-ara.css') : $assetVer);
    $mobileBeniAraPath = BASE_PATH . '/mobile/assets/css/beni-ara.css';
    $mobileBeniAraVer = (string) (file_exists($mobileBeniAraPath) ? filemtime($mobileBeniAraPath) : $assetVer);
  ?>
    <link href="/assets/css/beni-ara.css?v=<?= $beniAraCssVer ?>" rel="stylesheet">
    <link href="/mobile/assets/css/beni-ara.css?v=<?= $mobileBeniAraVer ?>" rel="stylesheet">
  <?php endif; ?>
  <?php if ($isPromosyonlar || $isPromotions): ?>
    <link href="/assets/css/promosyonlar.css?v=<?= $promoVer ?>" rel="stylesheet">
    <link href="/assets/css/bonus-detail-modal.css?v=<?= $bonusModalVer ?>" rel="stylesheet">
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://code.jquery.com/jquery-3.2.1.min.js"></script>
  <script src="/assets/js/modal-polyfill.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.js" referrerpolicy="no-referrer"></script>
  <script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/izitoast/dist/js/iziToast.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.js"></script>
  <script src="/assets/js/toastify-helper.js?v=<?= $assetVer ?>"></script>
  <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
</head>
<body class="mobile-site has-sub-navigation">
<?php include MOBILE_PATH . '/views/layouts/bc-root-open.php'; ?>
