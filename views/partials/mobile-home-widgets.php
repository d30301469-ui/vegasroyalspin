<?php
$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$mobileContactLinks = is_array($siteContactLinks ?? null) ? $siteContactLinks : (class_exists('ApiSiteSettings') ? ApiSiteSettings::normalizeContactLinks(is_array($ayar ?? null) ? $ayar : []) : []);
$callbackUrl = (string) ($mobileContactLinks['callback_url'] ?? '/beni-ara');
$callbackText = (string) ($mobileContactLinks['callback_widget_text'] ?? 'Dolandırıcılara geçit verme! Size ulaşan numara bize mi ait tıkla!');
$callbackText = trim($callbackText);
?>
<div class="mobile-home-widgets" id="mobileHomeWidgets">
  <div class="informative-widget" id="informativeWidget">
    <a href="<?= htmlspecialchars($callbackUrl, ENT_QUOTES, 'UTF-8') ?>" class="informative-widget-link informative-widget-bc">
      <div class="informative-widget-container">
        <i class="bc-i-info" aria-hidden="true"></i>
        <span><?= htmlspecialchars($callbackText !== '' ? $callbackText : 'Dolandırıcılara geçit verme! Size ulaşan numara bize mi ait tıkla!', ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <span class="informative-widget-actions" aria-hidden="true">
        <i class="bc-i-call"></i>
      </span>
    </a>
  </div>
  <button type="button" class="informative-widget-close" id="informativeWidgetClose" aria-label="Uyarıyı kapat" title="Kapat">
    <i class="bc-i-close-remove" aria-hidden="true"></i>
  </button>

<?php /* 2x2 hızlı işlem grid — orijinal mobil header altında yok; kaldırıldı */ ?>
</div>
<script>
(function () {
  var STORAGE_KEY = 'vrs_callback_widget_dismissed';
  var root = document.getElementById('mobileHomeWidgets');
  var closeBtn = document.getElementById('informativeWidgetClose');
  if (!root) return;

  function isDismissed() {
    try {
      return localStorage.getItem(STORAGE_KEY) === '1';
    } catch (e) {
      return false;
    }
  }

  function dismiss() {
    try {
      localStorage.setItem(STORAGE_KEY, '1');
    } catch (e) { /* ignore */ }
    root.hidden = true;
    root.classList.add('is-dismissed');
    document.documentElement.classList.remove('has-callback-widget');
  }

  if (isDismissed()) {
    root.hidden = true;
    root.classList.add('is-dismissed');
    return;
  }

  document.documentElement.classList.add('has-callback-widget');

  if (closeBtn) {
    closeBtn.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      dismiss();
    });
  }
})();
</script>
