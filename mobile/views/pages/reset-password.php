<?php
$resetToken = $resetToken ?? '';
?>
<?php
$mobileHead = MOBILE_PATH . '/views/layouts/head.php';
if (is_file($mobileHead) && filesize($mobileHead) > 0) {
	include $mobileHead;
} else {
	include VIEW_PATH . '/layouts/head_full.php';
}
?>
<?php include VIEW_PATH . '/partials/reset-password-section.php'; ?>
<script src="/assets/js/reset-password.js" defer></script>
