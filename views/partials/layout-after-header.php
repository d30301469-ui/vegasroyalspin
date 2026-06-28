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
?>
<!-- Toastr (global bildirimler) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<script src="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.js"></script>

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
if (!function_exists('metropol_member_api_layout_vars')) {
    require_once (defined('CONFIG_PATH') ? CONFIG_PATH : dirname(__DIR__, 2) . '/config') . '/member_api_public.php';
}
$memberApiLayout = metropol_member_api_layout_vars();
?>
<?php include __DIR__ . '/member-api-layout-script.php'; ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/global.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/auth-shared.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/member-api-console.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/toastify-helper.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/header.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php
$headerBalancePollPath = BASE_PATH . '/assets/js/header-balance-poll.js';
$headerBalancePollVer = (string) (is_file($headerBalancePollPath) ? filemtime($headerBalancePollPath) : '1');
?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/header-balance-poll.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/session-heartbeat.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/profile-api.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/profile-account.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/profile-payments.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/profile-history.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/profile-bonus.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/profile-kyc.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/profile.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/login.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/register.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/footer.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/footer-bc.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php
$favDrawerPath = BASE_PATH . '/assets/js/favorites-drawer.js';
$favDrawerVer = (string) (is_file($favDrawerPath) ? filemtime($favDrawerPath) : '1');
$gameFavPath = BASE_PATH . '/assets/js/game-favorites.js';
$gameFavVer = (string) (is_file($gameFavPath) ? filemtime($gameFavPath) : '1');
?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/favorites-drawer.js') . '?v=' . rawurlencode($favDrawerVer), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/game-favorites.js') . '?v=' . rawurlencode($gameFavVer), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/mobile_bottom.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
