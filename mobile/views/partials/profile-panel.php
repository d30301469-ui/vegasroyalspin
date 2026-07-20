<?php
/**
 * Mobile-native profil paneli (slide-in overlay) — masaüstü profil modalinden bağımsız.
 * Yalnızca giriş yapmış üyeler için header.php içinde include edilir.
 */
$panelLoggedIn = isset($loggedIn) ? (bool) $loggedIn : (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true);
if (!$panelLoggedIn) {
    return;
}

$panelUsername = trim((string) ($_SESSION['username'] ?? ''));
$panelUserId = (string) ($_SESSION['user_id'] ?? '');
$panelInitial = strtoupper(mb_substr($panelUsername !== '' ? $panelUsername : 'U', 0, 2));

$panelNormalizeDateInput = static function (string $value): string {
  $value = trim($value);
  if ($value === '') {
    return '';
  }
  if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value, $match) === 1) {
    return (string) ($match[0] ?? '');
  }
  $timestamp = strtotime($value);
  return $timestamp !== false ? date('Y-m-d', $timestamp) : '';
};
$panelNormalizeGenderLabel = static function (string $value): string {
  $gender = strtolower(trim($value));
  return match ($gender) {
    'male', 'm', 'erkek' => 'Erkek',
    'female', 'f', 'kadın', 'kadin' => 'Kadın',
    'other', 'o', 'diğer', 'diger' => 'Diğer',
    default => trim($value),
  };
};
$panelNormalizeCountryLabel = static function (string $value): string {
  $country = strtoupper(trim($value));
  if ($country === 'TR' || $country === 'TUR' || $country === 'TURKEY') {
    return 'Türkiye';
  }
  return trim($value);
};
$panelProfile = [];
if (!class_exists('MemberViewDataService', false) && defined('BASE_PATH') && is_readable(BASE_PATH . '/services/MemberViewDataService.php')) {
  require_once BASE_PATH . '/services/MemberViewDataService.php';
}
if (class_exists('MemberViewDataService')) {
  $panelProfile = MemberViewDataService::profileForSession();
}
$panelProfileUsername = trim((string) ($panelProfile['username'] ?? $panelUsername));
if ($panelProfileUsername === '') {
  $panelProfileUsername = $panelUsername;
}
$panelFirstName = trim((string) ($panelProfile['name'] ?? $panelProfile['first_name'] ?? ''));
$panelSurname = trim((string) ($panelProfile['surname'] ?? $panelProfile['last_name'] ?? ''));
$panelDob = $panelNormalizeDateInput((string) ($panelProfile['dob'] ?? $panelProfile['birth_date'] ?? ''));
$panelGender = $panelNormalizeGenderLabel((string) ($panelProfile['gender'] ?? ''));
$panelCountry = $panelNormalizeCountryLabel((string) ($panelProfile['country'] ?? ''));
$panelCountry = $panelCountry !== '' ? $panelCountry : 'Türkiye';
$panelCity = trim((string) ($panelProfile['city'] ?? ''));
$panelAddress = trim((string) ($panelProfile['address'] ?? ''));

$panelBadge = isset($headerLoyaltyBadge) && is_array($headerLoyaltyBadge) ? $headerLoyaltyBadge : [];
$panelLoyaltyName = (string) ($panelBadge['name'] ?? 'Bronze');
$panelLoyaltyIconMap = [
  'bronze' => '/assets/images/loyalty/badges/bronze.png',
  'silver' => '/assets/images/loyalty/badges/silver.svg',
  'gold' => '/assets/images/loyalty/badges/gold.svg',
  'platinum' => '/assets/images/loyalty/badges/platinum.svg',
  'diamond' => '/assets/images/loyalty/badges/diamond.svg',
];
$panelLoyaltySource = strtolower((string) ($panelBadge['code'] ?? '') . ' ' . $panelLoyaltyName . ' ' . (string) ($panelBadge['icon_url'] ?? ''));
$panelLoyaltyCode = 'bronze';
foreach (array_keys($panelLoyaltyIconMap) as $panelLoyaltyLevelCode) {
    if (str_contains($panelLoyaltySource, $panelLoyaltyLevelCode)) {
        $panelLoyaltyCode = $panelLoyaltyLevelCode;
        break;
    }
}
$panelLoyaltyIcon = $panelLoyaltyIconMap[$panelLoyaltyCode] ?? '/assets/images/loyalty/badges/bronze.png';
$panelInitialLower = mb_strtolower($panelInitial);
$panelBranding = isset($siteBranding) && is_array($siteBranding) ? $siteBranding : [];
$panelSettings = isset($ayar) && is_array($ayar) ? $ayar : [];
$panelSiteName = (string) ($panelBranding['site_name'] ?? $panelSettings['site_adi'] ?? 'VegasRoyalSpin');
$panelLogoUrl = (string) ($panelBranding['logo_animated_url'] ?? $panelSettings['logo_animated_url'] ?? $panelBranding['logo_mobile_url'] ?? $panelBranding['logo_url'] ?? $panelSettings['logo_mobile_url'] ?? $panelSettings['logo_url'] ?? '');
if (class_exists('ApiMediaUrl', false)) {
    $panelLogoUrl = ApiMediaUrl::resolve($panelLogoUrl);
}
$panelCsrfKey = 'vegasroyalspin_csrf_token';
if (empty($_SESSION[$panelCsrfKey]) || !is_string($_SESSION[$panelCsrfKey])) {
  $_SESSION[$panelCsrfKey] = isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token'])
    ? $_SESSION['csrf_token']
    : bin2hex(random_bytes(32));
}
$_SESSION['csrf_token'] = $_SESSION[$panelCsrfKey];
$panelTwofaEnabled = !empty($_SESSION['twofa_enabled']);
?>
<style id="mprofileInfoTabsStyle">
  #mprofilePanel .description-container-bc .second-tabs-bc{height:33px;min-height:33px;padding:0!important;gap:2px!important;column-gap:2px!important;background:rgba(5,7,38,.9)!important;border-radius:2px!important;overflow:hidden}
  #mprofilePanel .description-container-bc .tab-bc.selected-underline{height:33px;border:0!important;border-bottom:0!important;border-radius:2px;background:rgba(31,35,74,.92)!important;color:rgba(255,255,255,.52)!important}
  #mprofilePanel .description-container-bc .tab-bc.selected-underline.active{border:0!important;border-bottom:0!important;background:rgba(123,75,130,.94)!important;color:#fff!important}
  #mprofilePanel .description-container-bc .tab-bc.selected-underline:before,#mprofilePanel .description-container-bc .tab-bc.selected-underline:after{display:none!important;content:none!important}
</style>
<div class="mprofile-overlay" id="mprofileOverlay" aria-hidden="true"></div>
<aside class="overlay-sliding-wrapper-bc user-profile-container mprofile-panel" id="mprofilePanel" aria-hidden="true" role="dialog" aria-label="Profil">
  <div class="overlay-sliding-w-c-content-slider-bc" data-scroll-lock-scrollable>
    <div class="hdr-main-content-bc">
      <div class="logo-container">
        <a class="logo" href="/tr/">
          <?php if ($panelLogoUrl !== ''): ?><img class="hdr-logo-bc" src="<?= htmlspecialchars($panelLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Header Logo"><?php endif; ?>
        </a>
        <a href="https://affiliates.my/" target="_blank" class=" header-icon"><img alt="Header Icon" loading="lazy" decoding="async" src="https://cms.casinomilyon615.com/storage/medias/casinomilyon-18755179/media_18755179_1617883267cb571ec980e925adbb0427.gif"></a>
      </div>
      <i class="hdr-user-close bc-i-close-remove"></i>
    </div>
    <div class="u-i-p-c-body-bc">
      <div class="u-i-profile-page-bc">
        <div class="u-i-p-amount-holder-bc">
          <div class="carouselWrapper carousel carouselArrowsDisabled">
            <div class="swiper swiper-initialized swiper-horizontal" dir="ltr">
              <div class="swiper-wrapper">
                <div class="swiper-slide swiper-slide-active">
                  <div class="u-i-p-amounts-bc withdrawable">
                    <div class="u-i-p-a-content-bc">
                      <div class="total-balance-r-bc">
                        <div class="u-i-p-a-user-balance">
                          <span class="u-i-p-a-title-bc ellipsis">ANA BAKİYE</span>
                          <b class="u-i-p-a-amount-bc"><span data-balance-target="mprofileMain">0</span> ₺</b>
                        </div>
                        <i class="u-i-p-a-c-icon-bc bc-i-eye" aria-hidden="true"></i>
                      </div>
                      <div class="u-i-p-a-buttons-bc">
                        <a class="u-i-p-a-deposit-bc ellipsis" href="/?profile=open&amp;account=balance&amp;page=deposit"><i class="bc-i-wallet" aria-hidden="true"></i><span class="ellipsis" title="PARA YATIR">PARA YATIR</span></a>
                        <a class="u-i-p-a-withdraw-bc ellipsis" href="/?profile=open&amp;account=balance&amp;page=withdraw"><i class="bc-i-withdraw" aria-hidden="true"></i><span class="ellipsis" title="ÇEKİM">ÇEKİM</span></a>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="swiper-slide swiper-slide-next">
                  <div class="u-i-p-amounts-bc bonuses">
                    <div class="u-i-p-a-content-bc">
                      <span class="u-i-p-a-title-bc ellipsis">TOPLAM BONUS PARA</span>
                      <span class="u-i-p-a-amount-bc">0 ₺</span>
                      <div class="bonus-info-section"><div><span class="ellipsis">TOPLAM BONUS PARA</span><b>0.00 ₺</b></div></div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="swiper-pagination"><span class="swiper-pagination-bullet swiper-pagination-bullet-active"></span><span class="swiper-pagination-bullet"></span></div>
            </div>
          </div>
        </div>
        <a class="u-i-p-a-loyaltyPoint-bc" href="/?profile=open&amp;account=bonuses&amp;page=loyalty-points"><div class="loyaltyBonusHeader"><img class="loyaltyBonusImg" src="<?= htmlspecialchars($panelLoyaltyIcon, ENT_QUOTES, 'UTF-8') ?>" alt="" onerror="this.style.display='none'"></div><p class="u-i-p-a-loyaltyPointText-bc ellipsis">Sadakat Puanları</p></a>
        <div class="u-i-p-p-u-i-edit-button-bc">
          <p class="u-i-p-p-u-i-avatar-holder-bc"><?= htmlspecialchars($panelInitialLower, ENT_QUOTES, 'UTF-8') ?></p>
          <p class="u-i-p-p-u-i-identifiers-bc"><span class="u-i-p-p-u-i-d-username-bc ellipsis"><?= htmlspecialchars($panelUsername, ENT_QUOTES, 'UTF-8') ?></span><?php if ($panelUserId !== ''): ?><span class="u-i-p-p-u-i-d-user-id-bc ellipsis"><?= htmlspecialchars($panelUserId, ENT_QUOTES, 'UTF-8') ?><i class="u-i-p-p-u-i-d-user-id-copy-bc bc-i-copy" data-user-id="<?= htmlspecialchars($panelUserId, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i></span><?php endif; ?></p>
          <a class="u-i-p-l-h-icon-bc right bc-i-small-arrow-right" aria-label="Profile Details" href="/?profile=open&amp;account=profile&amp;page=details"></a>
        </div>
        <div class="u-i-p-links-lists-holder-bc">
          <div class="u-i-p-l-head-bc" data-href="/profile/details"><i class="user-nav-icon bc-i-user" aria-hidden="true"></i><span class="u-i-p-l-h-title-bc ellipsis">PROFİLİM</span><i class="count-blink-even" data-badge=""></i><i class="u-i-p-l-h-icon-bc bc-i-small-arrow-right" aria-hidden="true"></i></div>
          <div class="u-i-p-l-head-bc" data-href="/profile/deposit-withdraw"><i class="user-nav-icon bc-i-balance-management" aria-hidden="true"></i><span class="u-i-p-l-h-title-bc ellipsis">BAKİYE YÖNETİMİ</span><i class="count-blink-even" data-badge=""></i><i class="u-i-p-l-h-icon-bc bc-i-small-arrow-right" aria-hidden="true"></i></div>
          <div class="u-i-p-l-head-bc" data-href="/profile/bet-history"><i class="user-nav-icon bc-i-history" aria-hidden="true"></i><span class="u-i-p-l-h-title-bc ellipsis">BAHİS GEÇMİŞİ</span><i class="count-blink-even" data-badge=""></i><i class="u-i-p-l-h-icon-bc bc-i-small-arrow-right" aria-hidden="true"></i></div>
          <div class="u-i-p-l-head-bc" data-href="/profile/bonus-spor"><i class="user-nav-icon bc-i-promotion" aria-hidden="true"></i><span class="u-i-p-l-h-title-bc ellipsis">BONUSLAR</span><i class="count-blink-even" data-badge=""></i><i class="u-i-p-l-h-icon-bc bc-i-small-arrow-right" aria-hidden="true"></i></div>
          <div class="u-i-p-l-head-bc" data-href="/profile/messages"><i class="user-nav-icon bc-i-message" aria-hidden="true"></i><span class="u-i-p-l-h-title-bc ellipsis">MESAJLAR</span><i class="count-blink-even" data-badge=""></i><i class="u-i-p-l-h-icon-bc bc-i-small-arrow-right" aria-hidden="true"></i></div>
        </div>
        <div class="promoCodeWrapper-bc profile-panel-promo-code"><form onsubmit="return false;"><div class="u-i-p-control-item-holder-bc"><div class="form-control-bc default"><label class="form-control-label-bc inputs"><input type="text" class="form-control-input-bc" name="promoCode" step="0" value="" autocomplete="off"><i class="form-control-input-stroke-bc" aria-hidden="true"></i><span class="form-control-title-bc ellipsis">PROMOSYON KODU</span></label></div></div><div class="u-i-p-c-footer-bc"><button class="btn a-color big-btn" type="submit" title="UYGULA " disabled><span>UYGULA </span></button></div></form></div>
        <button class="userLogoutBtn btn" type="button"><i class="userLogoutIcon bc-i-logout" aria-hidden="true"></i><span>Çıkış Yap</span></button>
      </div>
      <div class="mprofile-balance-view" data-mprofile-view="balance" aria-hidden="true">
        <div class="back-nav-bc"><i class="back-nav-icon-bc bc-i-round-arrow-left"></i><span class="back-nav-title-bc ellipsis">BAKİYE YÖNETİMİ</span></div>
        <div class="hdr-navigation-scrollable-bc user-tab-navigation"><div class="hdr-navigation-scrollable-content" data-scroll-lock-scrollable>
          <a class="hdr-navigation-link-bc active" href="/?profile=open&amp;account=balance&amp;page=deposit" data-mbalance-tab="deposit"><span class="nav-menu-title">PARA YATIR<i class="count-blink-even" data-badge=""></i></span></a>
          <a class="hdr-navigation-link-bc" href="/?profile=open&amp;account=balance&amp;page=withdraw" data-mbalance-tab="withdraw"><span class="nav-menu-title">ÇEKİM<i class="count-blink-even" data-badge=""></i></span></a>
          <a class="hdr-navigation-link-bc" href="/?profile=open&amp;account=balance&amp;page=history" data-mbalance-tab="history"><span class="nav-menu-title">İŞLEM GEÇMİŞİ<i class="count-blink-even" data-badge=""></i></span></a>
          <a class="hdr-navigation-link-bc" href="/?profile=open&amp;account=balance&amp;page=info" data-mbalance-tab="info"><span class="nav-menu-title">Bilgi<i class="count-blink-even" data-badge=""></i></span></a>
          <a class="hdr-navigation-link-bc" href="/?profile=open&amp;account=balance&amp;page=withdraws" data-mbalance-tab="withdraws"><span class="nav-menu-title">PARA ÇEKME DURUMU<i class="count-blink-even" data-badge=""></i></span></a>
        </div></div>
        <div class="dep-w-info-bc deposit-page" data-mbalance-section="deposit" data-scroll-lock-scrollable>
          <div class="horizontalList scroll-start"><div class="horizontal-sl-list-container" data-scroll-lock-scrollable><div class="horizontal-sl-list">
            <div data-id="-1" title="TÜMÜ" data-badge="" class="horizontal-sl-item-bc accordion-button all active" data-mbalance-category="all"><i class="horizontal-sl-icon-bc bc-i-default-icon bc-i-all"></i><p class="horizontal-sl-title-bc">TÜMÜ</p></div>
            <div data-id="1" title="KREDİ KARTI" data-badge="" class="horizontal-sl-item-bc accordion-button bank-card" data-mbalance-category="card"><i class="horizontal-sl-icon-bc bc-i-default-icon bc-i-bank-card"></i><p class="horizontal-sl-title-bc">KREDİ KARTI</p></div>
            <div data-id="4" title="Kripto" data-badge="" class="horizontal-sl-item-bc accordion-button crypto" data-mbalance-category="crypto"><i class="horizontal-sl-icon-bc bc-i-default-icon bc-i-crypto"></i><p class="horizontal-sl-title-bc">Kripto</p></div>
            <div data-id="5" title="Banka transferi" data-badge="" class="horizontal-sl-item-bc accordion-button transfer" data-mbalance-category="bank"><i class="horizontal-sl-icon-bc bc-i-default-icon bc-i-transfer"></i><p class="horizontal-sl-title-bc">Banka transferi</p></div>
            <div data-id="7" title="QR" data-badge="" class="horizontal-sl-item-bc accordion-button qr" data-mbalance-category="qr"><i class="horizontal-sl-icon-bc bc-i-default-icon bc-i-qr"></i><p class="horizontal-sl-title-bc">QR</p></div>
          </div></div></div>
          <div class="m-block-nav-items-bc" id="mprofileDepositMethods"><p class="dw-methods-empty" role="status">Ödeme yöntemleri yükleniyor...</p></div>
        </div>
        <div class="dep-w-info-bc withdraw-page" data-mbalance-section="withdraw" data-scroll-lock-scrollable hidden>
          <div class="horizontalList scroll-start"><div class="horizontal-sl-list-container" data-scroll-lock-scrollable><div class="horizontal-sl-list">
            <div data-id="-1" title="TÜMÜ" data-badge="" class="horizontal-sl-item-bc accordion-button all active" data-mbalance-withdraw-category="all"><i class="horizontal-sl-icon-bc bc-i-default-icon bc-i-all"></i><p class="horizontal-sl-title-bc">TÜMÜ</p></div>
            <div data-id="4" title="Kripto" data-badge="" class="horizontal-sl-item-bc accordion-button crypto" data-mbalance-withdraw-category="crypto"><i class="horizontal-sl-icon-bc bc-i-default-icon bc-i-crypto"></i><p class="horizontal-sl-title-bc">Kripto</p></div>
            <div data-id="5" title="Banka transferi" data-badge="" class="horizontal-sl-item-bc accordion-button transfer" data-mbalance-withdraw-category="bank"><i class="horizontal-sl-icon-bc bc-i-default-icon bc-i-transfer"></i><p class="horizontal-sl-title-bc">Banka transferi</p></div>
          </div></div></div>
          <div class="m-block-nav-items-bc" id="mprofileWithdrawMethods"><p class="dw-methods-empty" role="status">Çekim yöntemleri yükleniyor...</p></div>
        </div>
        <div class="dep-w-info-bc mprofile-history-page" data-mbalance-section="history" data-scroll-lock-scrollable hidden>
          <div class="mprofile-history-filters" role="tablist" aria-label="İşlem geçmişi filtreleri"><button class="active" type="button" data-mbalance-history-filter="all">TÜMÜ</button><button type="button" data-mbalance-history-filter="deposit">YATIRIM</button><button type="button" data-mbalance-history-filter="withdraw">ÇEKİM</button></div>
          <div class="mprofile-history-list" id="mprofileTransactionHistory"><p class="dw-methods-empty" role="status">İşlem geçmişi yükleniyor...</p></div>
        </div>
        <div class="description-wrapper-bc" data-mbalance-section="info" data-scroll-lock-scrollable hidden>
          <div class="description-container-bc deposit logged-in">
            <div class="second-tabs-bc"><div class="tab-bc selected-underline active" title="" data-mbalance-info-tab="deposit"><span>PARA YATIR</span></div><div class="tab-bc selected-underline" title="" data-mbalance-info-tab="withdraw"><span>ÇEKİM</span></div></div>
            <div id="mprofileDepositInfo" data-mbalance-info-list="deposit"><p class="dw-methods-empty" role="status">Yatırım bilgileri yükleniyor...</p></div>
            <div id="mprofileWithdrawInfo" data-mbalance-info-list="withdraw" hidden><p class="dw-methods-empty" role="status">Çekim bilgileri yükleniyor...</p></div>
          </div>
        </div>
        <div class="dep-w-info-bc mprofile-withdraw-status-page" data-mbalance-section="withdraws" data-scroll-lock-scrollable hidden>
          <div class="mprofile-history-list" id="mprofileWithdrawStatus"><p class="dw-methods-empty" role="status">Para çekme durumu yükleniyor...</p></div>
        </div>
      </div>
      <div class="mprofile-detail-view" data-mprofile-view="details" aria-hidden="true">
        <div class="back-nav-bc"><i class="back-nav-icon-bc bc-i-round-arrow-left"></i><span class="back-nav-title-bc ellipsis">PROFİLİM</span></div>
        <div class="hdr-navigation-scrollable-bc user-tab-navigation"><div class="hdr-navigation-scrollable-content" data-scroll-lock-scrollable>
          <a class="hdr-navigation-link-bc active" href="/?profile=open&amp;account=profile&amp;page=details" data-mprofile-tab="details"><span class="nav-menu-title">KİŞİSEL DETAYLAR<i class="count-blink-even" data-badge=""></i></span></a>
          <a class="hdr-navigation-link-bc" href="/?profile=open&amp;account=profile&amp;page=change-password" data-mprofile-tab="change-password"><span class="nav-menu-title">ŞİFRE DEĞİŞTİR<i class="count-blink-even" data-badge=""></i></span></a>
          <a class="hdr-navigation-link-bc" href="/?profile=open&amp;account=profile&amp;page=two-factor-authentication" data-mprofile-tab="two-factor-authentication"><span class="nav-menu-title">İKİ AŞAMALI KORUMA (2FA)<i class="count-blink-even" data-badge=""></i></span></a>
          <a class="hdr-navigation-link-bc" href="/?profile=open&amp;account=profile&amp;page=timeout-limits" data-mprofile-tab="timeout-limits"><span class="nav-menu-title">HESABI DONDUR<i class="count-blink-even" data-badge=""></i></span></a>
        </div></div>
        <div class="u-i-e-p-p-content-bc u-i-common-content user-profile" data-mprofile-section="details" data-scroll-lock-scrollable>
          <form onsubmit="return false;">
            <div class="userProfile-content" data-scroll-lock-scrollable>
              <div class="userProfileWrapper-bc userProfileSection-0">
                <div class="u-i-p-control-item-holder-bc"><div class="form-control-bc default valid filled"><label class="form-control-label-bc inputs"><input type="text" class="form-control-input-bc" name="username" readonly step="0" value="<?= htmlspecialchars($panelProfileUsername, ENT_QUOTES, 'UTF-8') ?>"><i class="form-control-input-stroke-bc" aria-hidden="true"></i><span class="form-control-title-bc ellipsis">Kullanıcı adı *</span></label></div></div>
                <div class="u-i-p-control-item-holder-bc"><div class="form-control-bc default valid filled"><label class="form-control-label-bc inputs"><input type="text" class="form-control-input-bc" name="first_name" readonly step="0" value="<?= htmlspecialchars($panelFirstName, ENT_QUOTES, 'UTF-8') ?>"><i class="form-control-input-stroke-bc" aria-hidden="true"></i><span class="form-control-title-bc ellipsis">Adı *</span></label></div></div>
                <div class="u-i-p-control-item-holder-bc"><div class="form-control-bc default valid filled"><label class="form-control-label-bc inputs"><input type="text" class="form-control-input-bc" name="last_name" readonly step="0" value="<?= htmlspecialchars($panelSurname, ENT_QUOTES, 'UTF-8') ?>"><i class="form-control-input-stroke-bc" aria-hidden="true"></i><span class="form-control-title-bc ellipsis">Soyadı *</span></label></div></div>
                <div class="u-i-p-control-item-holder-bc"><div class="form-control-bc default valid filled"><label class="form-control-label-bc inputs"><input type="text" class="form-control-input-bc" name="birth_date" readonly step="0" value="<?= htmlspecialchars($panelDob, ENT_QUOTES, 'UTF-8') ?>"><i class="form-control-input-stroke-bc" aria-hidden="true"></i><span class="form-control-title-bc ellipsis">Doğum tarihi *</span></label></div><i class="dropdownIcon-bc bc-i-datepicker disabled" aria-hidden="true"></i></div>
                <div class="u-i-p-control-item-holder-bc"><div class="form-control-bc select has-icon focused valid filled"><label class="form-control-label-bc inputs"><input type="text" class="form-control-input-bc" name="gender" readonly step="0" value="<?= htmlspecialchars($panelGender, ENT_QUOTES, 'UTF-8') ?>"><i class="form-control-input-stroke-bc" aria-hidden="true"></i><span class="form-control-title-bc ellipsis">Cinsiyet *</span></label></div></div>
                <div class="u-i-p-control-item-holder-bc dropdownArrowParent-bc"><div class="form-controls-field-bc country-code"><label class="form-control-label-bc form-control-select-bc inputs"><i class="ftr-lang-bar-flag-bc flag-bc turkey" aria-hidden="true"></i><span class="form-control-title-bc ellipsis">Ülke</span><?= htmlspecialchars($panelCountry, ENT_QUOTES, 'UTF-8') ?></label></div></div>
                <div class="u-i-p-control-item-holder-bc"><div class="form-control-bc default valid filled"><label class="form-control-label-bc inputs"><input type="text" class="form-control-input-bc" name="city" step="0" value="<?= htmlspecialchars($panelCity, ENT_QUOTES, 'UTF-8') ?>"><i class="form-control-input-stroke-bc" aria-hidden="true"></i><span class="form-control-title-bc ellipsis">Şehir</span></label></div></div>
                <div class="u-i-p-control-item-holder-bc"><div class="form-control-bc default valid filled"><label class="form-control-label-bc inputs"><input type="text" class="form-control-input-bc" name="address" step="0" value="<?= htmlspecialchars($panelAddress, ENT_QUOTES, 'UTF-8') ?>"><i class="form-control-input-stroke-bc" aria-hidden="true"></i><span class="form-control-title-bc ellipsis">Adres</span></label></div></div>
              </div>
              <div class="userProfileWrapper-bc userProfileSection-1">
                <div class="u-i-p-control-item-holder-bc"><hr class="passwordAboveSeparator"></div>
                <div class="u-i-p-control-item-holder-bc"><div class="entrance-f-item-bc"><div class="entrance-f-error-message-bc">Değişiklikleri kaydetmek için şifrenizi girin.</div></div></div>
                <div class="u-i-p-control-item-holder-bc"><div class="form-control-bc default has-icon"><label class="form-control-label-bc inputs"><input type="password" class="form-control-input-bc" name="password" step="1" value=""><i class="form-control-input-stroke-bc" aria-hidden="true"></i><span class="form-control-title-bc ellipsis">Geçerli Şifre *</span></label></div></div>
              </div>
            </div>
            <div class="u-i-p-c-footer-bc"><button class="btn a-color right-aligned" type="submit" title="DEĞİŞİKLİKLERİ KAYDET" disabled><span>DEĞİŞİKLİKLERİ KAYDET</span></button></div>
          </form>
        </div>
        <div class="u-i-e-p-p-content-bc u-i-common-content user-profile mprofile-password-section" data-mprofile-section="change-password" data-scroll-lock-scrollable hidden>
          <div class="profile-security-single profile-security-single--password" id="mprofileChangePassword">
            <form id="mprofileChangePasswordForm" class="password-change-form" onsubmit="return false;">
              <div class="password-change-field"><div class="form-control-bc default has-icon"><label class="form-control-label-bc inputs"><input type="password" class="form-control-input-bc" id="mprofileOldPwd" name="current_password" required step="0" value="" autocomplete="current-password"><i class="form-control-input-stroke-bc" aria-hidden="true"></i><span class="form-control-title-bc ellipsis">Geçerli Şifre *</span></label></div></div>
              <div class="password-change-field"><div class="form-control-bc default has-icon"><label class="form-control-label-bc inputs"><input type="password" class="form-control-input-bc" id="mprofileNewPwd" name="password" required step="0" value="" autocomplete="new-password"><i class="form-control-input-stroke-bc" aria-hidden="true"></i><span class="form-control-title-bc ellipsis">Yeni Şifre *</span></label></div></div>
              <div class="password-change-field"><div class="form-control-bc default has-icon"><label class="form-control-label-bc inputs"><input type="password" class="form-control-input-bc" id="mprofileConfirmPass" name="password_confirmation" required step="0" value="" autocomplete="new-password"><i class="form-control-input-stroke-bc" aria-hidden="true"></i><span class="form-control-title-bc ellipsis">Yeni şifreyi onayla *</span></label></div></div>
              <div class="mprofile-form-message" data-mprofile-password-message role="status" aria-live="polite"></div>
              <div class="u-i-p-c-footer-bc password-change-footer"><button class="btn a-color right-aligned" type="submit" id="mprofileChangePwdBtn" title="ŞİFRE DEĞİŞTİR"><span>ŞİFRE DEĞİŞTİR</span></button></div>
            </form>
          </div>
        </div>
        <div class="u-i-e-p-p-content-bc u-i-common-content user-profile mprofile-twofa-section" data-mprofile-section="two-factor-authentication" data-scroll-lock-scrollable hidden>
          <div class="profile-security-single profile-security-single--twofa" id="mprofileTwoFactor">
            <p class="twofa-status" id="mprofile-twofa-status"><?= $panelTwofaEnabled ? 'İki faktörlü kimlik doğrulama etkin.' : 'İki faktörlü kimlik doğrulama kapatıldı' ?></p>
            <div class="twofa-activate-row">
              <div class="twofa-left-col">
                <div class="twofa-icon-wrap"><span class="twofa-icon" aria-hidden="true" title="Google Authenticator"><svg class="twofa-icon-ga" viewBox="0 0 48 48" width="28" height="28" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" role="img"><title>Google Authenticator</title><path fill="#4285F4" d="M24 24V4A20 20 0 0 1 44 24H24z"/><path fill="#EA4335" d="M24 24h20a20 20 0 0 1-20 20V24z"/><path fill="#FBBC04" d="M24 24v20A20 20 0 0 1 4 24h20z"/><path fill="#34A853" d="M24 24H4A20 20 0 0 1 24 4v20z"/><circle cx="24" cy="24" r="7" fill="#fff"/></svg></span></div>
                <span class="twofa-activate-label">İKİ FAKTÖRLÜ DOĞRULAMAYI ETKİNLEŞTİR</span>
              </div>
              <label class="twofa-toggle">
                <input type="checkbox" class="twofa-toggle-input" id="mprofileTwofaToggle" data-csrf-token="<?= htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>" <?= $panelTwofaEnabled ? 'checked' : '' ?> aria-describedby="mprofile-twofa-status">
                <span class="twofa-toggle-slider"></span>
              </label>
            </div>
          </div>
        </div>
        <div class="u-i-e-p-p-content-bc u-i-common-content user-profile mprofile-freeze-section" data-mprofile-section="timeout-limits" data-scroll-lock-scrollable hidden>
          <div class="profile-security-single profile-security-single--freeze" id="mprofileFreezeAccount">
            <p class="freeze-text">Hesabınızı dondurduğunuzda oturumunuz sonlanır ve mevcut giriş anahtarınız geçersiz olur. Tekrar siteyi kullanmak için hesap dondurmayı kaldırmanız gerekir.</p>
            <p class="personal-details-hint freeze-hint">Onaylamak için hesap şifrenizi girin.</p>
            <form id="mprofileFreezeForm" class="freeze-form" action="#" autocomplete="off" onsubmit="return false;">
              <div class="u-i-p-control-item-holder-bc freeze-password-field"><div class="form-control-bc default has-icon"><label class="form-control-label-bc inputs"><input type="password" class="form-control-input-bc" id="mprofileFreezePassword" name="password" required step="0" value="" autocomplete="current-password"><i class="form-control-input-stroke-bc" aria-hidden="true"></i><span class="form-control-title-bc ellipsis">Hesap şifresi *</span></label></div></div>
              <div class="mprofile-form-message" data-mprofile-freeze-message role="status" aria-live="polite"></div>
              <div class="u-i-p-c-footer-bc freeze-footer"><button class="btn a-color right-aligned" type="submit" id="mprofileFreezeSaveBtn" title="HESABI DONDUR"><span>HESABI DONDUR</span></button></div>
            </form>
          </div>
        </div>
      </div>
    </div>
    <div class="mprofile-payment-modal" id="mprofilePaymentModal" aria-hidden="true">
      <div class="mprofile-payment-modal__overlay" data-mprofile-payment-close></div>
      <section class="mprofile-payment-modal__sheet" role="dialog" aria-modal="true" aria-labelledby="mprofilePaymentModalTitle">
        <div class="mprofile-payment-modal__head"><button type="button" class="mprofile-payment-modal__back" data-mprofile-payment-close aria-label="Geri"><i class="bc-i-round-arrow-left" aria-hidden="true"></i></button><h3 id="mprofilePaymentModalTitle">Ödeme</h3><button type="button" class="mprofile-payment-modal__close" data-mprofile-payment-close aria-label="Kapat"><i class="bc-i-close-remove" aria-hidden="true"></i></button></div>
        <div class="mprofile-payment-modal__content" id="mprofilePaymentModalContent"></div>
      </section>
    </div>
  </div>
</aside>
