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
$backToTopJsVer = (string) ((is_file($backToTopJsPath) ? filemtime($backToTopJsPath) : '1') . '-' . (is_file($backToTopJsPath) ? filesize($backToTopJsPath) : '0'));
?>
<script src="/assets/js/back-to-top.js?v=<?= rawurlencode($backToTopJsVer) ?>"></script>

<script>
(function () {
  function initGameOverlayTap() {
    var cards = document.querySelectorAll('.game-item, .game-cta');
    if (!cards.length) return;

    cards.forEach(function (card) {
      card.addEventListener('click', function (e) {
        // Butona (Oyna/Demo), bilgi veya favori yıldızına tıklandıysa normal çalışsın
        if (e.target.closest('.play-btn, .demo-btn, a, .game-fav')) return;

        var isActive = card.classList.contains('overlay-active');

        // Tüm açık overlay'leri kapat
        document.querySelectorAll('.game-item.overlay-active, .game-cta.overlay-active')
          .forEach(function (c) { c.classList.remove('overlay-active'); });

        // Bu kart kapalıysa aç
        if (!isActive) card.classList.add('overlay-active');

        e.preventDefault();
        e.stopPropagation();
      });
    });

    // Dışarı tıklayınca kapat
    document.addEventListener('click', function (e) {
      if (!e.target.closest('.game-item, .game-cta')) {
        document.querySelectorAll('.game-item.overlay-active, .game-cta.overlay-active')
          .forEach(function (c) { c.classList.remove('overlay-active'); });
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initGameOverlayTap);
  } else {
    initGameOverlayTap();
  }
})();
</script>
</html>
