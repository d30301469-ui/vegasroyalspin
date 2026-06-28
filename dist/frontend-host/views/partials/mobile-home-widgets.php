<?php
$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$mobileContactLinks = is_array($siteContactLinks ?? null) ? $siteContactLinks : (class_exists('ApiSiteSettings') ? ApiSiteSettings::normalizeContactLinks(is_array($ayar ?? null) ? $ayar : []) : []);
$callbackUrl = (string) ($mobileContactLinks['callback_url'] ?? '/beni-ara');
$callbackText = (string) ($mobileContactLinks['callback_widget_text'] ?? 'Dolandırıcılara geçit verme! Size ulaşan numara bize mi ait tıkla!');
$callbackText = trim($callbackText);
?>
<div class="mobile-home-widgets">
<div class="informative-widget">
  <a href="<?= htmlspecialchars($callbackUrl, ENT_QUOTES, 'UTF-8') ?>" class="informative-widget-link informative-widget-bc">
    <div class="informative-widget-container">
      <i class="bc-i-info" aria-hidden="true"></i>
      <span><?= htmlspecialchars($callbackText !== '' ? $callbackText : 'Dolandırıcılara geçit verme! Size ulaşan numara bize mi ait tıkla!', ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <span class="informative-widget-actions" aria-hidden="true">
      <i class="bc-i-call"></i>
      <i class="bc-i-whatsapp"></i>
    </span>
  </a>
</div>

<?php /* 2x2 hızlı işlem grid — orijinal mobil header altında yok; kaldırıldı */ ?>
</div>
