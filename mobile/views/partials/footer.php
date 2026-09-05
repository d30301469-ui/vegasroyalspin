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
    document.querySelectorAll('.game-item, .game-cta').forEach(function (card) {
      card.addEventListener('click', function (e) {
        // Butona (Oyna/Demo), bilgi veya favori yıldızına tıklandıysa normal çalışsın
        if (e.target.closest('.play-btn, .demo-btn, .casinoBtnWrp a, a, .game-fav')) return;

        // home.js __homeGameCardActivate owns mobile tap-to-reveal.
        // A second toggle here would open then immediately close the overlay.
        if (typeof window.__homeGameCardActivate === 'function') {
          return;
        }

        var isActive = card.classList.contains('overlay-active');

        // Tüm açık overlay'leri kapat
        document.querySelectorAll('.game-item.overlay-active, .game-cta.overlay-active, .casinoGameItemContent.overlay-active')
          .forEach(function (c) { c.classList.remove('overlay-active'); });

        // Bu kart kapalıysa aç
        if (!isActive) card.classList.add('overlay-active');

        e.preventDefault();
        e.stopPropagation();
      });
    });

    // Dışarı tıklayınca kapat (slot/live CM622 kartları dahil)
    document.addEventListener('click', function (e) {
      if (!e.target.closest('.game-item, .game-cta, .casinoGameItemContent')) {
        document.querySelectorAll('.game-item.overlay-active, .game-cta.overlay-active, .casinoGameItemContent.overlay-active')
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
