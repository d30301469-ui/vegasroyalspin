<?php
/**
 * Layout: Header sonrası – ana içerik sarmalayıcı, global modallar, scriptler.
 * Header dışındaki sayfa iskeleti burada toplanır.
 */
if (function_exists('isMobile') && isMobile() && defined('MOBILE_PATH')) {
    $mobileLayoutAfterHeader = MOBILE_PATH . '/views/partials/layout-after-header.php';
    if (file_exists($mobileLayoutAfterHeader)) {
        include $mobileLayoutAfterHeader;
        return;
    }
}
$layoutSportbookLight = (defined('SPORTSBOOK_LIGHTWEIGHT_LAYOUT') && SPORTSBOOK_LIGHTWEIGHT_LAYOUT)
    || in_array(rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/', ['/sportbook', '/sportsbook'], true);
?>
<?php if (!$layoutSportbookLight): ?>
<!-- Toastr (global bildirimler) — toastify head'de -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<script defer src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<?php endif; ?>

<div class="mainContentWrap">
<?php include __DIR__ . '/register.php'; ?>
<?php include __DIR__ . '/login.php'; ?>

<?php
$mobileBottomPath = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/mobile_bottom.php';
if (file_exists($mobileBottomPath)) {
    include $mobileBottomPath;
} elseif (file_exists(__DIR__ . '/mobile_bottom.php')) {
    include __DIR__ . '/mobile_bottom.php';
}
?>

<?php
if (!function_exists('member_api_layout_vars')) {
    require_once (defined('CONFIG_PATH') ? CONFIG_PATH : dirname(__DIR__, 2) . '/config') . '/member_api_public.php';
}
$memberApiLayout = member_api_layout_vars();
?>
<?php include __DIR__ . '/member-api-layout-script.php'; ?>
<?php
$loginJsPath = BASE_PATH . '/assets/js/login.js';
$loginJsVer = (string) ((is_file($loginJsPath) ? filemtime($loginJsPath) : '1') . '-' . (is_file($loginJsPath) ? filesize($loginJsPath) : '0'));
$registerJsPath = BASE_PATH . '/assets/js/register.js';
$registerJsVer = (string) ((is_file($registerJsPath) ? filemtime($registerJsPath) : '1') . '-' . (is_file($registerJsPath) ? filesize($registerJsPath) : '0'));
$assetVersion = static function (string $relativePath): string {
    $fullPath = BASE_PATH . '/' . ltrim($relativePath, '/');
    return (string) ((is_file($fullPath) ? filemtime($fullPath) : '1') . '-' . (is_file($fullPath) ? filesize($fullPath) : '0'));
};
$versionedAsset = static function (string $path) use ($assetVersion): string {
    // asset_url() already appends ?v= — do not double-version
    return asset_url($path);
};
?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/global.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars($versionedAsset('assets/js/auth-shared.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php if (!$layoutSportbookLight): ?>
<link rel="stylesheet" href="<?= htmlspecialchars($versionedAsset('assets/css/game-wallet-picker.css'), ENT_QUOTES, 'UTF-8') ?>">
<script defer src="<?= htmlspecialchars($versionedAsset('assets/js/game-wallet-picker.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
<?php if (!empty($memberApiConsole)): ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/member-api-console.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
<?php
$siteSettingsHydratePath = BASE_PATH . '/assets/js/site-settings-hydrate.js';
$siteSettingsHydrateVer = (string) (is_file($siteSettingsHydratePath) ? filemtime($siteSettingsHydratePath) : '1');
?>
<script defer src="/assets/js/site-settings-hydrate.js?v=<?= rawurlencode($siteSettingsHydrateVer) ?>"></script>
<?php
$shellNavJsPath = BASE_PATH . '/assets/js/shell-nav.js';
$shellNavJsVer = (string) ((is_file($shellNavJsPath) ? filemtime($shellNavJsPath) : '1') . '-' . (is_file($shellNavJsPath) ? filesize($shellNavJsPath) : '0'));
$headerJsPath = BASE_PATH . '/assets/js/header.js';
$headerJsVer = (string) ((is_file($headerJsPath) ? filemtime($headerJsPath) : '1') . '-' . (is_file($headerJsPath) ? filesize($headerJsPath) : '0'));
$layoutRequestPathRaw = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$layoutRequestPath = $layoutRequestPathRaw === '/' ? '/' : rtrim($layoutRequestPathRaw, '/');
$layoutProfileBundleEager = !$layoutSportbookLight && (
    str_starts_with($layoutRequestPath, '/profile')
    || (isset($_GET['profile']) && (string) $_GET['profile'] === 'open')
);
$layoutLoggedIn = !empty($loggedIn) || (isset($GLOBALS['loggedIn']) && $GLOBALS['loggedIn']);
$profileBundleScripts = [
    asset_url('assets/js/profile-api.js'),
    asset_url('assets/js/profile-account.js'),
    asset_url('assets/js/profile-payments.js'),
    asset_url('assets/js/profile-history.js'),
    asset_url('assets/js/profile-bonus.js'),
    asset_url('assets/js/profile-kyc.js'),
    $versionedAsset('assets/js/profile.js'),
];
$profileBundleStyles = [
    asset_url('assets/css/profile-cm622.css'),
    asset_url('assets/css/profile-cm622-fix.css'),
    asset_url('assets/css/profile-cm622-original-deposit.css'),
    asset_url('assets/css/profile-cm622-original-filters-tables.css'),
    asset_url('assets/css/profile-cm622-original-complete.css'),
    asset_url('assets/css/profile-cm622-original-panes.css'),
];
$profileBundleLoaderPath = BASE_PATH . '/assets/js/profile-bundle-loader.js';
$profileBundleLoaderVer = (string) ((is_file($profileBundleLoaderPath) ? filemtime($profileBundleLoaderPath) : '1') . '-' . (is_file($profileBundleLoaderPath) ? filesize($profileBundleLoaderPath) : '0'));
?>
<?php if (!$layoutSportbookLight && !$layoutProfileBundleEager): ?>
<script>
window.__PROFILE_BUNDLE_SCRIPTS__ = <?= json_encode($profileBundleScripts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
window.__PROFILE_BUNDLE_STYLES__ = <?= json_encode($profileBundleStyles, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
window.__PROFILE_BUNDLE_IDLE__ = <?= $layoutLoggedIn ? 'true' : 'false' ?>;
</script>
<script defer src="/assets/js/profile-bundle-loader.js?v=<?= rawurlencode($profileBundleLoaderVer) ?>"></script>
<?php endif; ?>
<script defer src="/assets/js/shell-nav.js?v=<?= rawurlencode($shellNavJsVer) ?>"></script>
<script defer src="/assets/js/header.js?v=<?= rawurlencode($headerJsVer) ?>"></script>
<script defer src="<?= htmlspecialchars($versionedAsset('assets/js/header-balance-poll.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars($versionedAsset('assets/js/session-heartbeat.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php if ($layoutProfileBundleEager): ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/profile-api.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/profile-account.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/profile-payments.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/profile-history.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/profile-bonus.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/profile-kyc.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars($versionedAsset('assets/js/profile.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
<?php if (!empty($_GET['js_usage'])): ?>
<script defer src="<?= htmlspecialchars($versionedAsset('assets/js/js-usage-probe.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
<script defer src="/assets/js/login.js?v=<?= rawurlencode($loginJsVer) ?>"></script>
<script defer src="/assets/js/register.js?v=<?= rawurlencode($registerJsVer) ?>"></script>
<?php if (!$layoutSportbookLight): ?>
<script defer src="<?= htmlspecialchars($versionedAsset('assets/js/footer.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/footer-bc.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php
$favDrawerPath = BASE_PATH . '/assets/js/favorites-drawer.js';
$favDrawerVer = (string) (is_file($favDrawerPath) ? filemtime($favDrawerPath) : '1');
$gameFavPath = BASE_PATH . '/assets/js/game-favorites.js';
$gameFavVer = (string) (is_file($gameFavPath) ? filemtime($gameFavPath) : '1');
?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/favorites-drawer.js') . '?v=' . rawurlencode($favDrawerVer), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/game-favorites.js') . '?v=' . rawurlencode($gameFavVer), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
<?php /* Menü mantığı tek dosyada: mobile/assets/js/navigation.js (mobile_bottom.js kaldırıldı) */ ?>
<script defer src="<?= htmlspecialchars($versionedAsset('mobile/assets/js/navigation.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
