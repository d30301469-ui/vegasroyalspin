<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<script src="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.js"></script>

<div class="layout-content-holder-bc">
<div class="mainContentWrap">
<?php include VIEW_PATH . '/partials/header-global-panels.php'; ?>
<?php $registerSingleStepMobile = true; ?>
<?php include VIEW_PATH . '/partials/register.php'; ?>
<?php include VIEW_PATH . '/partials/login.php'; ?>
<?php include VIEW_PATH . '/partials/member-api-layout-script.php'; ?>
<?php
$mobileLoginJsPath = BASE_PATH . '/assets/js/login.js';
$mobileLoginJsVer = (string) ((is_file($mobileLoginJsPath) ? filemtime($mobileLoginJsPath) : '1') . '-' . (is_file($mobileLoginJsPath) ? filesize($mobileLoginJsPath) : '0'));
$mobileRegisterJsPath = BASE_PATH . '/assets/js/register.js';
$mobileRegisterJsVer = (string) ((is_file($mobileRegisterJsPath) ? filemtime($mobileRegisterJsPath) : '1') . '-' . (is_file($mobileRegisterJsPath) ? filesize($mobileRegisterJsPath) : '0'));
$mobileAssetVer = static function (string $relativePath): string {
	$fullPath = BASE_PATH . '/' . ltrim($relativePath, '/');
	return (string) ((is_file($fullPath) ? filemtime($fullPath) : '1') . '-' . (is_file($fullPath) ? filesize($fullPath) : '0'));
};

$mobileVersionedUrl = static function (string $path, string $version): string {
	$baseUrl = asset_url($path);
	$sep = (strpos($baseUrl, '?') !== false) ? '&' : '?';
	return $baseUrl . $sep . 'v=' . rawurlencode($version);
};

$mobileAuthSharedVer = $mobileAssetVer('assets/js/auth-shared.js');
$mobileHeaderSharedVer = $mobileAssetVer('assets/js/header.js');
$mobileFooterSharedVer = $mobileAssetVer('assets/js/footer.js');
$mobileNavigationVer = $mobileAssetVer('mobile/assets/js/navigation.js');
$mobileHeaderVer = $mobileAssetVer('mobile/assets/js/mobile-header.js');
$mobileProfilePanelVer = $mobileAssetVer('mobile/assets/js/profile-panel.js');
?>
<script src="<?= htmlspecialchars(asset_url('assets/js/global.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/modal-polyfill.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars($mobileVersionedUrl('assets/js/auth-shared.js', $mobileAuthSharedVer), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/game-wallet-picker.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/member-api-console.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/site-settings-hydrate.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/toastify-helper.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars($mobileVersionedUrl('assets/js/header.js', $mobileHeaderSharedVer), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/header-balance-poll.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/session-heartbeat.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/profile-api.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/profile-account.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/profile-payments.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/profile-history.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/profile-bonus.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/profile-kyc.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/profile.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="/assets/js/login.js?v=<?= rawurlencode($mobileLoginJsVer) ?>"></script>
<script src="/assets/js/register.js?v=<?= rawurlencode($mobileRegisterJsVer) ?>"></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/mobile-right-sheet.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars($mobileVersionedUrl('assets/js/footer.js', $mobileFooterSharedVer), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/footer-bc.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/favorites-drawer.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/game-favorites.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asset_url('mobile/assets/js/betslip-mobile.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars($mobileVersionedUrl('mobile/assets/js/navigation.js', $mobileNavigationVer), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars($mobileVersionedUrl('mobile/assets/js/mobile-header.js', $mobileHeaderVer), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars($mobileVersionedUrl('mobile/assets/js/profile-panel.js', $mobileProfilePanelVer), ENT_QUOTES, 'UTF-8') ?>"></script>
