<?php
$resetToken = $resetToken ?? '';
$resetPasswordJs = BASE_PATH . '/assets/js/reset-password.js';
$resetPasswordJsVer = is_file($resetPasswordJs) ? (string) filemtime($resetPasswordJs) : (string) time();
?>
<?php include VIEW_PATH . '/layouts/head.php'; ?>
<?php include VIEW_PATH . '/partials/reset-password-section.php'; ?>
<script src="/assets/js/reset-password.js?v=<?= urlencode($resetPasswordJsVer) ?>" defer></script>
