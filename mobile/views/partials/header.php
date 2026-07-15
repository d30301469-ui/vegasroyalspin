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
    <div class="hdr-main-content-bc" data-mobile-header-main>
      <div class="logo-container">
        <a class="logo" href="/" title="<?= htmlspecialchars($mobileHeaderSiteName, ENT_QUOTES, 'UTF-8') ?>">
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
            <button type="button" class="userBtn nav-menu-item" id="toggleButton" aria-expanded="false" aria-label="Profil menüsü">
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
    <div class="hdr-additional-info" data-mobile-header-additional>
      <a class="loyaltyBonusHeader"
         href="/profile/deposit-withdraw"
         title="<?= htmlspecialchars((string) ($headerLoyaltyBadge['name'] ?? 'Bronze'), ENT_QUOTES, 'UTF-8') ?>"
         data-loyalty-badge
         data-loyalty-code="<?= htmlspecialchars((string) ($headerLoyaltyBadge['code'] ?? 'bronze'), ENT_QUOTES, 'UTF-8') ?>">
        <p class="loyaltyBonusHeaderShadow" aria-hidden="true"></p>
        <p class="loyaltyBonusHeaderBackground" aria-hidden="true"></p>
        <p class="loyaltyBonusHeaderText ellipsis" data-loyalty-level-name><?= htmlspecialchars((string) ($headerLoyaltyBadge['name'] ?? 'Bronze'), ENT_QUOTES, 'UTF-8') ?></p>
        <img class="loyaltyBonusImg"
             src="<?= htmlspecialchars((string) ($headerLoyaltyBadge['icon_url'] ?? '/content/images/loyalty_points/bronze.png'), ENT_QUOTES, 'UTF-8') ?>"
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
             onclick="event.preventDefault(); if (typeof redirectToDeposit === 'function') redirectToDeposit();"
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

    <?php if ($loggedIn) include VIEW_PATH . '/partials/mobile-hdr-crypto.php'; ?>
    <?php include VIEW_PATH . '/partials/mobile-bc-nav-menu.php'; ?>
  </div>
</div>

<?php include MOBILE_PATH . '/views/partials/layout-after-header.php'; ?>
