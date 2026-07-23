<?php
/**
 * Mobil header — m.casinomilyon591 DOM yapısı (layout-header-holder-bc > hdr-dynamic + header-bc)
 */
require_once VIEW_PATH . '/partials/header-init.php';
$loggedIn = isset($loggedIn) ? (bool) $loggedIn : false;
$ayar = isset($ayar) && is_array($ayar) ? $ayar : [];
$siteBranding = isset($siteBranding) && is_array($siteBranding) ? $siteBranding : [];
$siteContactLinks = isset($siteContactLinks) && is_array($siteContactLinks) ? $siteContactLinks : [];
$hdrAuthClass = $loggedIn ? ' hdr-auth-user' : ' hdr-auth-guest';
$depositHref = '/profile/deposit-withdraw?openDepositPanel=1';
$balanceHref = $loggedIn ? $depositHref : '#';
$smartPanelBadge = $loggedIn ? '23' : '2';
$mobileHeaderBranding = is_array($siteBranding ?? null) ? $siteBranding : [];
$mobileHeaderSiteName    = (string) ($mobileHeaderBranding['site_name']         ?? $ayar['site_adi']           ?? 'VegasRoyalSpin');
$mobileHeaderLogoUrl     = (string) ($mobileHeaderBranding['logo_mobile_url']   ?? $mobileHeaderBranding['logo_url'] ?? $ayar['logo_mobile_url'] ?? $ayar['logo_url'] ?? '');
$mobileHeaderLogoAnimUrl = (string) ($mobileHeaderBranding['logo_animated_url'] ?? $ayar['logo_animated_url']  ?? '');
if (class_exists('ApiMediaUrl', false)) {
    $mobileHeaderLogoUrl     = ApiMediaUrl::resolve($mobileHeaderLogoUrl);
    $mobileHeaderLogoAnimUrl = ApiMediaUrl::resolve($mobileHeaderLogoAnimUrl);
}
$mobileHeaderLoyaltyIconMap = [
  'bronze' => '/assets/images/loyalty/badges/bronze.png',
  'silver' => '/assets/images/loyalty/badges/silver.svg',
  'gold' => '/assets/images/loyalty/badges/gold.svg',
  'platinum' => '/assets/images/loyalty/badges/platinum.svg',
  'diamond' => '/assets/images/loyalty/badges/diamond.svg',
];
$mobileHeaderLoyaltySource = strtolower((string) ($headerLoyaltyBadge['code'] ?? '') . ' ' . (string) ($headerLoyaltyBadge['name'] ?? '') . ' ' . (string) ($headerLoyaltyBadge['icon_url'] ?? ''));
$mobileHeaderLoyaltyCode = 'bronze';
foreach (array_keys($mobileHeaderLoyaltyIconMap) as $mobileHeaderLoyaltyLevelCode) {
  if (str_contains($mobileHeaderLoyaltySource, $mobileHeaderLoyaltyLevelCode)) {
    $mobileHeaderLoyaltyCode = $mobileHeaderLoyaltyLevelCode;
    break;
  }
}
$mobileHeaderLoyaltyIconUrl = $mobileHeaderLoyaltyIconMap[$mobileHeaderLoyaltyCode] ?? '/assets/images/loyalty/badges/bronze.png';
$mobileHeaderSupportUrl = (string) ($siteContactLinks['live_support_url'] ?? (defined('LIVE_SUPPORT_URL') ? LIVE_SUPPORT_URL : ''));
$mobileHeaderSupportUrlJs = htmlspecialchars(json_encode($mobileHeaderSupportUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
?>
<div class="layout-header-holder-bc mobile-bc-header">
  <div class="hdr-dynamic-content" aria-hidden="true">
    <div class="hm-row-bc" style="grid-template-columns: 12fr;">
      <div class="hm-row-bc-inner">
        <div class="product-banner-container-bc col-4 product-banner-without-titles"></div>
      </div>
    </div>
  </div>

  <div class="header-bc<?= $hdrAuthClass ?>">
    <div class="hdr-main-content-bc">
      <div class="logo-container">
        <a class="logo" href="/" data-site-logo-link title="<?= htmlspecialchars($mobileHeaderSiteName, ENT_QUOTES, 'UTF-8') ?>">
          <?php if ($mobileHeaderLogoAnimUrl !== ''): ?>
            <?php $mAnimExt = strtolower(pathinfo((string) parse_url($mobileHeaderLogoAnimUrl, PHP_URL_PATH), PATHINFO_EXTENSION)); ?>
            <?php if ($mAnimExt === 'webm' || $mAnimExt === 'mp4'): ?>
              <video class="hdr-logo-bc" autoplay loop muted playsinline width="190" height="64" aria-label="<?= htmlspecialchars($mobileHeaderSiteName, ENT_QUOTES, 'UTF-8') ?>">
                <source src="<?= htmlspecialchars($mobileHeaderLogoAnimUrl, ENT_QUOTES, 'UTF-8') ?>" type="video/webm">
                <?php if ($mobileHeaderLogoUrl !== ''): ?><img class="hdr-logo-bc" src="<?= htmlspecialchars($mobileHeaderLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($mobileHeaderSiteName, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
              </video>
            <?php else: ?>
              <img class="hdr-logo-bc" src="<?= htmlspecialchars($mobileHeaderLogoAnimUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($mobileHeaderSiteName, ENT_QUOTES, 'UTF-8') ?>" width="190" height="64">
            <?php endif; ?>
          <?php elseif ($mobileHeaderLogoUrl !== ''): ?>
            <img class="hdr-logo-bc" src="<?= htmlspecialchars($mobileHeaderLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($mobileHeaderSiteName, ENT_QUOTES, 'UTF-8') ?>" width="190" height="64">
          <?php endif; ?>
        </a>
      </div>

      <div class="hdr-user-bc">
        <?php if ($loggedIn): ?>
          <div class="user-balance-dropdown">
            <a class="nav-menu-item hdr-balance-trigger"
               id="balanceTrigger"
               href="<?= htmlspecialchars($balanceHref, ENT_QUOTES, 'UTF-8') ?>"
               aria-label="Bakiye"
               role="button"
               aria-expanded="false"
               aria-haspopup="true"
               onclick="event.preventDefault();">
              <div class="hdr-user-info-content-bc">
                <div class="hdr-user-info-texts-bc ext-1 ellipsis" data-header-balance-main>
                  <p class="balanceAmount">
                    <span id="headerBalanceMain" data-balance-target="headerBalanceMain">0</span>
                    <span class="currencySymbol"> ₺</span>
                  </p>
                </div>
              </div>
            </a>
          </div>
          <div class="profileDetails" id="playerCol">
            <button type="button" class="userBtn nav-menu-item" id="toggleButton" aria-expanded="false" aria-label="Profil menüsü" onclick="return window.__mobileProfileIconTap ? window.__mobileProfileIconTap(event) : false;">
              <i class="hdr-user-avatar-icon-bc bc-i-user" aria-hidden="true"></i>
              <span class="backFace" aria-hidden="true"></span>
            </button>
          </div>
        <?php else: ?>
          <a href="#" class="btn sign-in loginBtn" id="Giris" role="button">GİRİŞ</a>
          <button id="openRegister" class="btn register" type="button">KAYIT</button>
        <?php endif; ?>
      </div>

      <div class="smartPanel-bc">
        <button type="button"
                class="hdr-toggle-button-bc bc-i-vertical-toggle count-odd-animation count-blink-even"
                id="smart-panel-holder"
                title="Akıllı Menü"
                aria-label="Akıllı Menü"
                aria-expanded="false"
                data-badge="<?= htmlspecialchars($smartPanelBadge, ENT_QUOTES, 'UTF-8') ?>"></button>
      </div>
    </div>

    <?php if ($loggedIn): ?>
    <div class="hdr-additional-info">
      <a class="loyaltyBonusHeader"
        href="/?profile=open&amp;account=bonuses&amp;page=loyalty-points"
         title="<?= htmlspecialchars((string) ($headerLoyaltyBadge['name'] ?? 'Bronze'), ENT_QUOTES, 'UTF-8') ?>"
         data-loyalty-badge
         data-loyalty-code="<?= htmlspecialchars((string) ($headerLoyaltyBadge['code'] ?? 'bronze'), ENT_QUOTES, 'UTF-8') ?>">
        <p class="loyaltyBonusHeaderShadow" aria-hidden="true"></p>
        <p class="loyaltyBonusHeaderBackground" aria-hidden="true"></p>
        <p class="loyaltyBonusHeaderText ellipsis" data-loyalty-level-name><?= htmlspecialchars((string) ($headerLoyaltyBadge['name'] ?? 'Bronze'), ENT_QUOTES, 'UTF-8') ?></p>
        <img class="loyaltyBonusImg"
             src="<?= htmlspecialchars($mobileHeaderLoyaltyIconUrl, ENT_QUOTES, 'UTF-8') ?>"
             alt=""
             width="24"
             height="24"
             loading="lazy"
             data-loyalty-level-icon
             onerror="this.style.display='none'">
      </a>
      <div class="hdr-user-bc hasLoyaltyLevel" data-hdr-shortcuts-strip>
        <div class="headerExpanded"
             data-hdr-additional-toggle
             role="button"
             tabindex="0"
             aria-expanded="false"
             aria-label="Kısayolları genişlet">
          <span class="hdr-expanded-label">KAPAT</span>
          <p class="headerExpandedIcons headerExpandedIcons--collapsed" aria-hidden="true">
            <i class="bc-i-small-arrow-left"></i>
            <i class="bc-i-small-arrow-left"></i>
          </p>
          <p class="headerExpandedIcons headerExpandedIcons--open" aria-hidden="true">
            <i class="bc-i-small-arrow-right"></i>
            <i class="bc-i-small-arrow-right"></i>
          </p>
        </div>
        <div class="hdr-shortcuts-icons">
          <a href="<?= htmlspecialchars($depositHref, ENT_QUOTES, 'UTF-8') ?>"
             class="user-nav-icon bc-i-wallet"
             onclick="event.preventDefault(); if (typeof window.__openMobileBalancePage === 'function' && window.__openMobileBalancePage('deposit')) return false; if (typeof redirectToDeposit === 'function') redirectToDeposit(); return false;"
             title="Para Yatır"
             aria-label="Para Yatır"></a>
          <a href="#"
             class="user-nav-icon bc-i-call callPanel"
             onclick="window.open(<?= $mobileHeaderSupportUrlJs ?>,'_blank'); return false;"
             title="Canlı Destek"
             aria-label="Canlı Destek"></a>
          <a href="/ortaklik"
             class="user-nav-icon bc-i-standings"
             title="Ortaklık"
             aria-label="Ortaklık"></a>
          <a href="/promotions"
             class="user-nav-icon bc-i-x50-wheel hdr-shortcut-wheel"
             title="Çark"
             aria-label="Çark"></a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!$loggedIn): ?>
    <div class="hdr-guest-shortcuts" aria-label="Hızlı işlemler">
      <a href="/promotions"
         class="guest-shortcut-icon bc-i-promotions-3"
         title="Promosyonlar"
         aria-label="Promosyonlar"></a>
      <a href="#"
         class="guest-shortcut-icon bc-i-call callPanel"
         onclick="window.open(<?= $mobileHeaderSupportUrlJs ?>,'_blank'); return false;"
         title="Canlı Destek"
         aria-label="Canlı Destek"></a>
      <button type="button"
              class="guest-shortcut-icon bc-i-wallet"
              onclick="if (typeof showLoginWarning === 'function') showLoginWarning();"
              title="Cüzdan"
              aria-label="Cüzdan"></button>
      <a href="/promotions"
         class="guest-shortcut-icon guest-shortcut-wheel bc-i-x50-wheel"
         title="Çark"
         aria-label="Çark"></a>
    </div>
    <?php endif; ?>

    <?php include VIEW_PATH . '/partials/mobile-bc-nav-menu.php'; ?>
  </div>
</div>

<?php include MOBILE_PATH . '/views/partials/profile-panel.php'; ?>

<script>
(function () {
  function ensureFallbackPanel() {
    var panel = document.getElementById('mprofilePanel');
    var overlay = document.getElementById('mprofileOverlay');
    if (panel && overlay) return { panel: panel, overlay: overlay };

    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'mprofileOverlay';
      overlay.setAttribute('aria-hidden', 'true');
      overlay.style.position = 'fixed';
      overlay.style.inset = '0';
      overlay.style.background = 'rgba(0,0,0,.55)';
      overlay.style.zIndex = '1069';
      overlay.style.display = 'none';
      document.body.appendChild(overlay);
    }

    if (!panel) {
      panel = document.createElement('aside');
      panel.id = 'mprofilePanel';
      panel.setAttribute('aria-hidden', 'true');
      panel.style.position = 'fixed';
      panel.style.left = '0';
      panel.style.right = '0';
      panel.style.bottom = '0';
      panel.style.height = '78vh';
      panel.style.background = '#0f1020';
      panel.style.zIndex = '1070';
      panel.style.transform = 'translateY(100%)';
      panel.style.transition = 'transform .25s ease';
      panel.style.borderTopLeftRadius = '14px';
      panel.style.borderTopRightRadius = '14px';
      panel.style.overflow = 'hidden';

      var head = document.createElement('div');
      head.style.height = '44px';
      head.style.display = 'flex';
      head.style.alignItems = 'center';
      head.style.justifyContent = 'space-between';
      head.style.padding = '0 12px';
      head.style.background = '#171a34';
      head.style.color = '#fff';
      head.textContent = 'Profil';

      var closeBtn = document.createElement('button');
      closeBtn.type = 'button';
      closeBtn.textContent = 'x';
      closeBtn.setAttribute('aria-label', 'Kapat');
      closeBtn.style.border = '0';
      closeBtn.style.background = 'transparent';
      closeBtn.style.color = '#fff';
      closeBtn.style.fontSize = '24px';
      closeBtn.style.lineHeight = '1';
      closeBtn.style.cursor = 'pointer';
      head.appendChild(closeBtn);

      var frame = document.createElement('iframe');
      frame.src = '/mobile/profile?profile=open&account=profile&page=details';
      frame.style.width = '100%';
      frame.style.height = 'calc(78vh - 44px)';
      frame.style.border = '0';
      frame.style.background = '#0f1020';

      panel.appendChild(head);
      panel.appendChild(frame);
      document.body.appendChild(panel);

      var closeFallback = function () {
        overlay.classList.remove('is-open');
        panel.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        panel.setAttribute('aria-hidden', 'true');
        overlay.style.display = 'none';
        panel.style.transform = 'translateY(100%)';
        document.body.classList.remove('mprofile-open', 'overlay-sliding-is-visible', 'overlaySlidingIsVisible');
      };

      closeBtn.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        closeFallback();
      });
      overlay.addEventListener('click', closeFallback);
    }

    return { panel: panel, overlay: overlay };
  }

  window.__mobileProfileIconTap = function (event) {
    if (event && event.preventDefault) event.preventDefault();
    if (event && event.stopPropagation) event.stopPropagation();

    if (typeof window.__openMobileProfilePanel === 'function') {
      var opened = window.__openMobileProfilePanel();
      if (opened) return false;
    }

    var refs = ensureFallbackPanel();
    if (!refs || !refs.panel || !refs.overlay) {
      window.location.href = '/?profile=open&account=profile&page=details';
      return false;
    }

    var panel = refs.panel;
    var overlay = refs.overlay;
    var isOpen = panel.classList.contains('is-open');
    if (isOpen) {
      if (typeof window.__closeMobileProfilePanel === 'function') {
        window.__closeMobileProfilePanel();
        return false;
      }
      overlay.classList.remove('is-open');
      panel.classList.remove('is-open');
      overlay.setAttribute('aria-hidden', 'true');
      panel.setAttribute('aria-hidden', 'true');
      overlay.style.display = 'none';
      panel.style.transform = 'translateY(100%)';
      document.body.classList.remove('mprofile-open', 'overlay-sliding-is-visible', 'overlaySlidingIsVisible');
      return false;
    }

    overlay.classList.add('is-open');
    panel.classList.add('is-open');
    overlay.setAttribute('aria-hidden', 'false');
    panel.setAttribute('aria-hidden', 'false');
    overlay.style.display = 'block';
    panel.style.transform = 'translateY(0)';
    document.body.classList.add('mprofile-open', 'overlay-sliding-is-visible', 'overlaySlidingIsVisible');
    return false;
  };
})();
</script>

<?php include MOBILE_PATH . '/views/partials/layout-after-header.php'; ?>
