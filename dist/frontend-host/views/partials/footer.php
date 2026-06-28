<?php
// Gömülü LiveChat widget'ı — mobil delegasyonundan önce, her iki akışta da bir kez.
include __DIR__ . '/live-chat.php';
if (function_exists('isMobile') && isMobile() && defined('MOBILE_PATH')) {
    $mobileFooter = MOBILE_PATH . '/views/partials/footer.php';
    if (file_exists($mobileFooter)) {
        include $mobileFooter;
        return;
    }
}
?>
<?php include __DIR__ . '/footer-bc.php'; ?>
</div><!-- .mainContentWrap -->
<?php include __DIR__ . '/scroll-to-top.php'; ?>
<?php
$backToTopJsPath = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/assets/js/back-to-top.js';
$backToTopJsVer = (string) (is_file($backToTopJsPath) ? filemtime($backToTopJsPath) : '1');
?>
<script src="<?= htmlspecialchars(asset_url('assets/js/back-to-top.js'), ENT_QUOTES, 'UTF-8') ?>?v=<?= $backToTopJsVer ?>"></script>
