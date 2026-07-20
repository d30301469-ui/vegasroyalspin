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
  #mprofilePanel .mprofile-payment-modal{position:absolute;inset:0;z-index:5;display:none}#mprofilePanel .mprofile-payment-modal.is-open{display:block}#mprofilePanel .mprofile-payment-modal__overlay{position:absolute;inset:0;background:rgba(0,0,0,.5)}#mprofilePanel .mprofile-payment-modal__sheet{position:absolute;left:0;right:0;bottom:0;max-height:calc(100% - 46px);display:flex;flex-direction:column;background:linear-gradient(89deg,#661760 0%,rgb(0 11 36) 50%,#0e005a 100%);color:#fff;border-radius:10px 10px 0 0;box-shadow:0 -8px 24px rgba(0,0,0,.45);overflow:hidden}#mprofilePanel .mprofile-payment-modal__head{display:flex;align-items:center;min-height:42px;padding:0 10px;background:rgba(0,0,0,.18)}#mprofilePanel .mprofile-payment-modal__head h3{flex:1;margin:0;text-align:center;font-size:13px}#mprofilePanel .mprofile-payment-modal__back,#mprofilePanel .mprofile-payment-modal__close{width:34px;height:34px;border:0;border-radius:4px;background:rgba(255,255,255,.08);color:#fff}#mprofilePanel .mprofile-payment-modal__content{overflow-y:auto;padding:12px 10px 16px}#mprofilePanel .mprofile-payment-form{display:flex;flex-direction:column;gap:10px}#mprofilePanel .mprofile-payment-summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}#mprofilePanel .mprofile-payment-summary>div{min-width:0;padding:8px;border-radius:4px;background:rgba(255,255,255,.1)}#mprofilePanel .mprofile-payment-summary strong,#mprofilePanel .mprofile-payment-summary span{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}#mprofilePanel .mprofile-payment-summary strong{margin-bottom:3px;color:rgba(255,255,255,.62);font-size:10px;text-transform:uppercase}#mprofilePanel .mprofile-payment-summary span{font-size:12px;font-weight:700}#mprofilePanel .mprofile-payment-field input{width:100%;min-height:46px;border:0;border-radius:4px;padding:0 12px;background:rgba(255,255,255,.15);color:#fff;font-size:13px;outline:none}#mprofilePanel .mprofile-payment-submit{width:100%;min-height:48px;border:0;border-radius:4px;background:rgba(var(--oc-1,156,77,177),1);color:#fff;font-weight:700}#mprofilePanel .m-nav-items-list-item-bc{border:0;padding:0}
  #mprofilePanel .mprofile-payment-modal.is-open{display:block!important;transform:translateY(0)!important;opacity:1!important;background:linear-gradient(89deg,#661760 0%,rgb(0 11 36) 50%,#0e005a 100%)}#mprofilePanel .mprofile-payment-modal .overlay-sliding-w-c-content-slider-bc{height:100%;transform:none!important;animation:none!important;background:linear-gradient(89deg,#661760 0%,rgb(0 11 36) 50%,#0e005a 100%)}#mprofilePanel .payment-details-scrollable-container{height:calc(100% - 38px);overflow-y:auto;padding:10px 10px 18px}#mprofilePanel .payment-info-bc{outline:0}#mprofilePanel .payment-info-content{display:flex;flex-direction:column;gap:10px}#mprofilePanel .expandableContentWrapper{background:rgba(23,12,56,.62);border-radius:4px;padding:14px 0;text-align:center;color:rgba(255,255,255,.62);font-size:11px;line-height:1.25}#mprofilePanel .expandableContentWrapper p{margin:0 8px;text-align:left}#mprofilePanel .withdraw-form-l-bc form{display:flex;flex-direction:column;gap:10px}#mprofilePanel .withdraw-form-l-bc .u-i-p-control-item-holder-bc{width:100%}#mprofilePanel .withdraw-form-l-bc .form-control-bc{min-height:49px;background:rgba(255,255,255,.15);border-radius:4px;position:relative}#mprofilePanel .withdraw-form-l-bc .form-control-label-bc{display:flex;flex-direction:column;height:49px;padding:8px 12px;color:#fff}#mprofilePanel .withdraw-form-l-bc .form-control-input-bc,#mprofilePanel .withdraw-form-l-bc .form-control-select-bc{width:100%;border:0;background:transparent;color:#fff;outline:0;font-size:13px;padding-top:13px}#mprofilePanel .withdraw-form-l-bc .form-control-title-bc{position:absolute;top:8px;left:12px;color:rgba(255,255,255,.58);font-size:11px}#mprofilePanel .withdraw-form-l-bc .u-i-p-c-footer-bc{margin-top:18px}#mprofilePanel .withdraw-form-l-bc .btn{width:100%;height:35px;border:0;border-radius:4px;background:rgba(156,77,177,.72);color:#fff;font-size:11px;font-weight:700}#mprofilePanel .withdraw-form-l-bc .btn:disabled{opacity:.55}
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
    <div class="overlay-sliding-wrapper-bc mprofile-payment-modal" id="mprofilePaymentModal" aria-hidden="true" style="transform: translateY(100%); opacity: 0;">
      <div class="overlay-sliding-w-c-content-slider-bc" data-scroll-lock-scrollable>
        <div class="back-nav-bc" data-mprofile-payment-close><i class="back-nav-icon-bc bc-i-round-arrow-left"></i><span class="back-nav-title-bc ellipsis" id="mprofilePaymentModalTitle">Ödeme</span></div>
        <div class="payment-details-scrollable-container" id="mprofilePaymentModalContent" data-scroll-lock-scrollable></div>
      </div>
    </div>
  </div>
</aside>
<script id="mprofilePaymentModalFallback">
(function(){
  if (window.__mprofilePaymentModalFallbackBound) return;
  window.__mprofilePaymentModalFallbackBound = true;
  var methodCache = null, active = null, submitting = false;
  function api(path){return window.BetcoAuthShared&&window.BetcoAuthShared.apiUrl?window.BetcoAuthShared.apiUrl(path):path;}
  function headers(extra){return window.BetcoAuthShared&&window.BetcoAuthShared.memberAuthHeaders?window.BetcoAuthShared.memberAuthHeaders(extra):(extra||{});}
  function esc(v){return String(v==null?'':v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
  function limit(v){var n=Number(v);return isFinite(n)?new Intl.NumberFormat('tr-TR',{maximumFractionDigits:0}).format(n)+' ₺':'—';}
  function mid(m){return String(m&&(m.method_id||m.id||m.payment_method_id)||'').trim();}
  function mname(m){return String(m&&(m.name||m.label||m.title||m.method_id||m.id)||'Ödeme').trim();}
  function provider(m){return m&&m.provider&&m.provider.code?String(m.provider.code):'megapayz';}
  function parseCardId(card, kind){var cls=card?String(card.className||''):'';var re=new RegExp('(?:^|\\s)'+kind+'_([^\\s]+)');var mt=cls.match(re);return mt?mt[1]:'';}
  function extractWithdraw(data){if(!data||typeof data!=='object')return[];if(Array.isArray(data.methods))return data.methods;if(data.megapayz_withdraw_form&&Array.isArray(data.megapayz_withdraw_form.methods))return data.megapayz_withdraw_form.methods;if(data.create_withdraw&&Array.isArray(data.create_withdraw.methods))return data.create_withdraw.methods;return[];}
  function loadMethods(){if(methodCache)return Promise.resolve(methodCache);return fetch(api('/api/v2/payment-methods'),{credentials:'same-origin',headers:headers({Accept:'application/json'})}).then(function(r){return r.json();}).then(function(env){var all=env&&env.success&&env.data&&Array.isArray(env.data.payment_methods)?env.data.payment_methods:[];methodCache={deposit:all.filter(function(m){return m&&m.deposit_enabled;}),withdraw:all.filter(function(m){return m&&m.withdrawal_enabled;})};if(methodCache.withdraw.length)return methodCache;return fetch(api('/api/v2/withdraw-payment'),{credentials:'same-origin',headers:headers({Accept:'application/json'})}).then(function(wr){return wr.json();}).then(function(wenv){methodCache.withdraw=wenv&&wenv.success&&wenv.data?extractWithdraw(wenv.data):[];return methodCache;});});}
  function findMethod(kind, card){var id=parseCardId(card,kind);var list=(methodCache&&methodCache[kind])||[];return list.find(function(m){return mid(m).toLowerCase()===id.toLowerCase()||String(m.id||'').toLowerCase()===id.toLowerCase();})||list[0]||null;}
  function close(){var modal=document.getElementById('mprofilePaymentModal');if(modal){modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');modal.style.transform='translateY(100%)';modal.style.opacity='0';}active=null;submitting=false;}
  function msg(type,text){var el=document.querySelector('#mprofilePaymentModal [data-mprofile-payment-message]');if(!el)return;el.textContent=text||'';el.classList.toggle('is-error',type==='error');el.classList.toggle('is-success',type==='success');}
  function syncSubmit(){var amount=document.getElementById('mprofilePaymentAmount'),submit=document.querySelector('#mprofilePaymentModal .mprofile-payment-submit');if(submit&&!submitting)submit.disabled=!(amount&&String(amount.value||'').trim());}
  function logo(m){return String(m&&(m.logo_url||m.logo)||'');}
  function methodClass(m){return mname(m).replace(/[^A-Za-z0-9_-]+/g,'')||mid(m)||'payment';}
  function fields(kind,m){var id=mid(m).toLowerCase();if(kind==='deposit'&&id.indexOf('crypto')!==-1){return '<div class="u-i-p-control-item-holder-bc"><div class="form-control-bc select has-icon valid filled"><label class="form-control-label-bc inputs"><select class="form-control-select-bc active" name="crypto_type" id="mprofilePaymentCryptoType" step="0"><option value="TRON">tron</option><option value="ETH">eth</option><option value="BSC">bsc</option></select><i class="form-control-icon-bc bc-i-small-arrow-down"></i><i class="form-control-input-stroke-bc"></i><span class="form-control-title-bc ellipsis">crypto_type</span></label></div></div>';}if(kind!=='withdraw')return'';var ph=id.indexOf('bank')!==-1?'IBAN':'address';var hidden=id.indexOf('crypto')!==-1?'<input type="hidden" id="mprofilePaymentNetwork" value="TRON">':'';return hidden+'<div class="u-i-p-control-item-holder-bc"><div class="form-control-bc default filled"><label class="form-control-label-bc inputs"><input type="text" class="form-control-input-bc" id="mprofilePaymentAccount" name="account_number" step="0" value=""><i class="form-control-input-stroke-bc"></i><span class="form-control-title-bc ellipsis">'+ph+'</span></label></div></div>';}
  function open(kind,m){var modal=document.getElementById('mprofilePaymentModal'),title=document.getElementById('mprofilePaymentModalTitle'),content=document.getElementById('mprofilePaymentModalContent');if(!modal||!content||!m)return;active={kind:kind,method:m};var min=m.min_amount!=null?Number(m.min_amount):0,max=m.max_amount!=null?Number(m.max_amount):999999,name=mname(m),lg=logo(m);if(title)title.textContent=name;content.innerHTML='<div class="payment-info-bc" tabindex="-1"><div class="payment-info-content"><div class="description-c-row-bc '+esc(methodClass(m))+'"><div class="description-c-row-column-bc pay-logo">'+(lg?'<img alt="" loading="lazy" decoding="async" src="'+esc(lg)+'">':'<span class="payment-logo payment-logo--text">'+esc(name)+'</span>')+'</div><div class="description-c-row-column-bc texts"><div class="description-c-row-c-title-bc description_payment-title"><div class="description-c-r-c-t-column-bc"><span class="description-title ellipsis">Ücret: Ücretsiz</span></div><div class="description-c-r-c-t-column-bc"><span class="description-instant ellipsis">'+esc(m.processing_time||'Anlık')+'</span></div></div><div class="description-card-info"><div class="description-c-r-c-t-column-bc"><span class="description-title ellipsis" title="Min.">Min.</span><span class="description-value ellipsis" title="'+esc(limit(min))+'">'+esc(limit(min))+'</span></div><div class="description-c-r-c-t-column-bc"><span class="description-title ellipsis" title="Maks.">Maks.</span><span class="description-value ellipsis" title="'+esc(limit(max))+'">'+esc(limit(max))+'</span></div></div></div></div><div class="expandableContentWrapper"><div class="expandableContentData '+esc(methodClass(m))+' payment-content not-expandable" data-scroll-lock-scrollable><div class="container"><p>CasinoMilyon Ailesine hoş geldiniz. İyi eğlenceler, bol şanslar dileriz. '+(kind==='withdraw'?'Para çekmek':'Para yatırmak')+' için lütfen aşağıdaki tüm gerekli alanları doldurun. Minimum tutar altı yatırımlar "İADE EDİLMEZ" lütfen kurallara uygun yatırım yapınız.</p></div></div></div><div class="withdraw-form-l-bc"><form id="mprofilePaymentForm"><div id="screenArea">'+fields(kind,m)+'<div class="u-i-p-control-item-holder-bc"><div class="form-control-bc default"><label class="form-control-label-bc inputs"><input type="text" inputmode="decimal" class="form-control-input-bc" id="mprofilePaymentAmount" name="amount" step="0" value=""><i class="form-control-input-stroke-bc"></i><span class="form-control-title-bc ellipsis">Tutar</span></label></div></div><div class="mprofile-form-message" data-mprofile-payment-message role="status" aria-live="polite"></div><div class="u-i-p-c-footer-bc"><button class="btn a-color '+(kind==='withdraw'?'withdraw':'deposit')+' mprofile-payment-submit" type="submit" title="'+(kind==='withdraw'?'ÇEKİM YAP':'PARA YATIR')+'" disabled><span>'+(kind==='withdraw'?'ÇEKİM YAP':'PARA YATIR')+'</span></button></div></div></form></div></div></div>';modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');modal.style.transform='translateY(0px)';modal.style.opacity='1';var amount=document.getElementById('mprofilePaymentAmount');if(amount)amount.focus({preventScroll:true});}
  function submit(){if(submitting||!active)return;var m=active.method,kind=active.kind,amountEl=document.getElementById('mprofilePaymentAmount'),amount=amountEl?Number(amountEl.value):NaN,min=m.min_amount!=null?Number(m.min_amount):0,max=m.max_amount!=null?Number(m.max_amount):999999;if(!isFinite(amount)||amount<=0){msg('error','Lütfen geçerli bir tutar girin.');return;}if(amount<min){msg('error','Minimum tutar '+limit(min)+'.');return;}if(amount>max){msg('error','Maksimum tutar '+limit(max)+'.');return;}var pid=String(m.id||m.payment_method_id||'').trim(),payload={amount:amount};if(pid)payload.payment_method_id=pid;else{payload.method=mid(m);payload.provider=provider(m);}if(kind==='withdraw'){var acc=document.getElementById('mprofilePaymentAccount'),an=acc?String(acc.value||'').trim():'';if(!an){msg('error','Lütfen hesap bilgisini girin.');return;}payload.payment_method_id=pid||mid(m);payload.account_number=an.replace(/\s/g,'');payload.lang='tr';var nw=document.getElementById('mprofilePaymentNetwork');if(nw&&nw.value)payload.input_fields={crypto_network:nw.value,bank_id:nw.value};}submitting=true;var btn=document.querySelector('#mprofilePaymentModal .mprofile-payment-submit');if(btn){btn.disabled=true;btn.textContent='İşleniyor...';}fetch(api(kind==='withdraw'?'/api/v2/withdraw-payment':'/api/v2/deposit-payment'),{method:'POST',credentials:'same-origin',headers:headers({'Content-Type':'application/json',Accept:'application/json'}),body:JSON.stringify(payload)}).then(function(r){return r.json();}).then(function(env){if(env&&env.success&&env.data&&env.data.payment_url){location.href=String(env.data.payment_url);return;}if(env&&env.success){msg('success',env.message||(kind==='withdraw'?'Çekim talebiniz alındı.':'İşlem başlatıldı.'));setTimeout(close,900);return;}msg('error',env&&env.message?env.message:'İşlem tamamlanamadı.');}).catch(function(){msg('error','Sunucu hatası. Lütfen tekrar deneyin.');}).then(function(){submitting=false;if(btn){btn.disabled=false;btn.textContent=kind==='withdraw'?'ÇEKİM YAP':'PARA YATIR';}});}
  document.addEventListener('click',function(e){var t=e.target&&e.target.closest?e.target:null;if(!t)return;var closeBtn=t.closest('[data-mprofile-payment-close]');if(closeBtn){e.preventDefault();e.stopImmediatePropagation();close();return;}var submitBtn=t.closest('#mprofilePaymentModal .mprofile-payment-submit');if(submitBtn){e.preventDefault();e.stopImmediatePropagation();submit();return;}var dep=t.closest('[data-mbalance-method]'),wdr=t.closest('[data-mbalance-withdraw-method]');var card=dep||wdr;if(!card)return;e.preventDefault();e.stopImmediatePropagation();var kind=wdr?'withdraw':'deposit';loadMethods().then(function(){open(kind,findMethod(kind,card));});},true);
  document.addEventListener('input',function(e){if(e.target&&e.target.id==='mprofilePaymentAmount')syncSubmit();},true);
  document.addEventListener('submit',function(e){if(e.target&&e.target.id==='mprofilePaymentForm'){e.preventDefault();submit();}},true);
})();
</script>
