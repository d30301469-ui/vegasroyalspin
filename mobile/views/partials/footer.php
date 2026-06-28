<div class="layout-footer-holder-bc">
  <div class="mobileFooter">
    <?php include MOBILE_PATH . '/views/partials/mobile-footer-bc.php'; ?>
  </div>
</div>

<?php include VIEW_PATH . '/partials/footer-modals.php'; ?>

</div><!-- .mainContentWrap -->
</div><!-- .layout-content-holder-bc -->

<?php include MOBILE_PATH . '/views/partials/bc-navigation.php'; ?>

<?php include MOBILE_PATH . '/views/layouts/bc-root-close.php'; ?>

<?php include VIEW_PATH . '/partials/scroll-to-top.php'; ?>
<?php
$backToTopJsPath = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3)) . '/assets/js/back-to-top.js';
$backToTopJsVer = (string) (is_file($backToTopJsPath) ? filemtime($backToTopJsPath) : '1');
?>
<script src="<?= htmlspecialchars(asset_url('assets/js/back-to-top.js'), ENT_QUOTES, 'UTF-8') ?>?v=<?= $backToTopJsVer ?>"></script>
