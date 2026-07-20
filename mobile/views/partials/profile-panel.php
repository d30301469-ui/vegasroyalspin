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
  #mprofilePanel .mprofile-payment-header{display:flex;align-items:center;position:relative;height:46px;min-height:46px;padding:0 12px;background:#050b39;border-bottom:1px solid rgba(255,255,255,.05)}#mprofilePanel .mprofile-payment-header .logo-container{height:100%;display:flex;align-items:center;flex:auto;min-width:0;margin-right:5px}#mprofilePanel .mprofile-payment-header .logo{display:flex;align-items:center;height:46px;flex-shrink:0}#mprofilePanel .mprofile-payment-header .hdr-logo-bc{max-width:150px;max-height:30px;object-fit:contain}#mprofilePanel .mprofile-payment-header .header-icon{display:flex;align-items:center;margin-left:7px}#mprofilePanel .mprofile-payment-header .header-icon img{max-width:28px;max-height:28px;object-fit:contain}#mprofilePanel .mprofile-payment-header .hdr-user-close{margin-left:auto;color:#c7bbe5;font-size:24px;line-height:46px}#mprofilePanel .mprofile-payment-titlebar{height:37px;min-height:37px;padding:0 8px;background:#202020;color:#fff}#mprofilePanel .mprofile-payment-titlebar .back-nav-icon-bc{width:20px;height:20px;border-radius:50%;background:#d7d7d7;color:#555;font-size:20px;line-height:20px}#mprofilePanel .mprofile-payment-titlebar .back-nav-title-bc{font-size:12px;font-weight:700;text-transform:uppercase}#mprofilePanel .payment-details-scrollable-container{height:calc(100% - 83px)!important;padding:14px 8px 18px!important}#mprofilePanel .payment-info-content{gap:10px!important}#mprofilePanel .description-c-row-bc{display:grid!important;grid-template-columns:142px minmax(0,1fr);align-items:start;gap:0;min-height:61px;margin:0!important;padding:0!important;background:transparent!important;border:0!important}#mprofilePanel .description-c-row-column-bc.pay-logo{width:142px;height:50px;display:flex;align-items:center;justify-content:center;margin:0;background:#080b36;border:2px solid rgba(156,77,177,.8);border-radius:0;overflow:hidden}#mprofilePanel .description-c-row-column-bc.pay-logo img{max-width:100%;max-height:100%;object-fit:contain}#mprofilePanel .description-c-row-column-bc.texts{padding:0 0 0 92px;font-size:11px;color:#c9b8dc}#mprofilePanel .description_payment-title,#mprofilePanel .description-card-info{display:grid;grid-template-columns:1fr 63px;gap:8px;min-height:20px}#mprofilePanel .description-title{color:#aaa0c1;font-size:11px;font-weight:600}#mprofilePanel .description-instant,#mprofilePanel .description-value{display:block;color:#fff;font-size:11px;font-weight:700;text-align:right}#mprofilePanel .expandableContentWrapper{position:relative;min-height:104px;padding:20px 0 22px!important;background:rgba(25,8,76,.74)!important;border-radius:4px!important;color:#a99fbc!important}#mprofilePanel .expandableContentWrapper p{margin:0 8px!important;font-size:11px;font-weight:700;line-height:1.22;text-align:left}#mprofilePanel .expandableContentWrapper:after{content:"";position:absolute;left:50%;bottom:10px;width:9px;height:9px;border-right:2px solid #aaa6bc;border-bottom:2px solid #aaa6bc;transform:translateX(-50%) rotate(45deg)}#mprofilePanel .withdraw-form-l-bc form{gap:10px!important}#mprofilePanel .withdraw-form-l-bc .form-control-bc{height:49px!important;background:linear-gradient(90deg,rgba(142,70,138,.72),rgba(51,64,109,.82))!important;border-radius:4px!important}#mprofilePanel .withdraw-form-l-bc .form-control-title-bc{top:11px!important;color:rgba(255,255,255,.42)!important;font-size:11px!important;font-weight:700}#mprofilePanel .withdraw-form-l-bc .form-control-input-bc,#mprofilePanel .withdraw-form-l-bc .form-control-select-bc{padding-top:16px!important;color:#fff!important;font-size:12px!important;font-weight:700}#mprofilePanel .withdraw-form-l-bc .u-i-p-c-footer-bc{margin-top:18px!important;padding-bottom:10px;border-bottom:1px solid rgba(156,77,177,.55)}#mprofilePanel .withdraw-form-l-bc .btn{height:35px!important;background:rgba(96,76,138,.68)!important;color:rgba(255,255,255,.42)!important;font-size:11px!important;font-weight:700!important}#mprofilePanel .withdraw-form-l-bc .btn:not(:disabled){background:rgba(156,77,177,.95)!important;color:#fff!important}
  #mprofilePanel .payment-details-scrollable-container{padding:13px 7px 18px!important}#mprofilePanel .description-c-row-bc{position:relative!important;display:block!important;width:100%!important;height:58px!important;min-height:58px!important}#mprofilePanel .description-c-row-column-bc.pay-logo{position:absolute;left:0;top:0;width:142px!important;height:45px!important;padding:0!important}#mprofilePanel .description-c-row-column-bc.texts{position:absolute;right:0;top:0;width:134px!important;height:58px!important;padding:0!important;display:block!important}#mprofilePanel .description_payment-title,#mprofilePanel .description-card-info{display:grid!important;grid-template-columns:1fr 64px!important;gap:8px!important;min-height:20px!important}#mprofilePanel .description-c-r-c-t-column-bc{min-width:0!important}#mprofilePanel .description-title,#mprofilePanel .description-instant,#mprofilePanel .description-value{line-height:18px!important}#mprofilePanel .expandableContentWrapper{margin-top:0!important;min-height:92px!important;padding:15px 0 22px!important}#mprofilePanel .withdraw-form-l-bc .u-i-p-control-item-holder-bc{margin:0!important;padding:0!important}#mprofilePanel .withdraw-form-l-bc .form-control-bc{width:100%!important;height:50px!important;min-height:50px!important}#mprofilePanel .withdraw-form-l-bc .form-control-label-bc{height:50px!important;padding:8px 12px!important}#mprofilePanel .withdraw-form-l-bc .u-i-p-c-footer-bc{width:100%!important;margin:18px 0 0!important;padding-bottom:10px!important}#mprofilePanel .withdraw-form-l-bc .btn{width:100%!important;height:35px!important;min-height:35px!important}
  #mprofilePanel .mprofile-payment-header .header-icon{display:none!important}#mprofilePanel .mprofile-payment-header .hdr-user-close{width:20px!important;height:20px!important;font-size:20px!important;line-height:20px!important;margin-left:auto!important}
  #mprofilePanel .mprofile-payment-modal{will-change:transform!important;position:fixed!important;inset:auto auto 0 0!important;width:100%!important;height:calc(100% - var(--mobile-header-main-section-height,46px))!important;z-index:1072!important;background:rgba(var(--b,0,0,0),.7)!important}#mprofilePanel .mprofile-payment-modal .overlay-sliding-w-c-content-slider-bc{height:100%!important}#mprofilePanel .mprofile-payment-modal .payment-details-scrollable-container{height:calc(100% - 37px)!important}
  #mprofilePanel .mprofile-payment-modal .description-c-row-bc{height:73px!important;min-height:73px!important}#mprofilePanel .mprofile-payment-modal .description-c-row-column-bc.pay-logo{width:178px!important;height:58px!important}#mprofilePanel .mprofile-payment-modal .description-c-row-column-bc.texts{left:205px!important;right:0!important;width:auto!important;height:73px!important}#mprofilePanel .mprofile-payment-modal .description_payment-title,#mprofilePanel .mprofile-payment-modal .description-card-info{display:block!important;min-height:0!important}#mprofilePanel .mprofile-payment-modal .description-c-r-c-t-column-bc{display:flex!important;align-items:center!important;justify-content:space-between!important;min-height:22px!important;width:100%!important}#mprofilePanel .mprofile-payment-modal .description-title,#mprofilePanel .mprofile-payment-modal .description-instant,#mprofilePanel .mprofile-payment-modal .description-value{line-height:22px!important;white-space:nowrap!important}#mprofilePanel .mprofile-payment-modal .description-instant,#mprofilePanel .mprofile-payment-modal .description-value{text-align:right!important;max-width:92px!important}
  #mprofilePanel .mprofile-payment-modal .description-c-row-bc{display:grid!important;grid-template-columns:minmax(132px,44%) minmax(128px,1fr)!important;gap:12px!important;height:auto!important;min-height:58px!important;align-items:start!important}#mprofilePanel .mprofile-payment-modal .description-c-row-column-bc.pay-logo{position:static!important;width:100%!important;height:45px!important;min-width:0!important}#mprofilePanel .mprofile-payment-modal .description-c-row-column-bc.texts{position:static!important;left:auto!important;right:auto!important;width:100%!important;height:auto!important;min-width:0!important;padding:0!important;display:block!important}#mprofilePanel .mprofile-payment-modal .description_payment-title{display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;gap:8px!important;min-height:18px!important}#mprofilePanel .mprofile-payment-modal .description-card-info{display:block!important;min-height:0!important}#mprofilePanel .mprofile-payment-modal .description_payment-title .description-c-r-c-t-column-bc{display:block!important;min-height:18px!important;width:auto!important}#mprofilePanel .mprofile-payment-modal .description-card-info .description-c-r-c-t-column-bc{display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;gap:8px!important;align-items:center!important;min-height:18px!important;width:100%!important}#mprofilePanel .mprofile-payment-modal .description-title,#mprofilePanel .mprofile-payment-modal .description-instant,#mprofilePanel .mprofile-payment-modal .description-value{line-height:18px!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}#mprofilePanel .mprofile-payment-modal .description-instant,#mprofilePanel .mprofile-payment-modal .description-value{max-width:none!important;text-align:right!important;overflow:visible!important}
  #mprofilePanel .mprofile-payment-modal #screenArea{display:flex!important;flex-direction:column!important;gap:10px!important}#mprofilePanel .mprofile-payment-modal #screenArea>.u-i-p-control-item-holder-bc{width:100%!important;margin:0!important}#mprofilePanel .mprofile-payment-modal #screenArea>.u-i-p-c-footer-bc{margin-top:18px!important}
  #mprofilePanel .mprofile-crypto-select-button{width:100%;border:0;background:transparent;color:#fff;text-align:left;padding:16px 44px 4px 0;font-size:12px;font-weight:700;line-height:16px}#mprofilePanel .mprofile-crypto-popup{position:absolute;left:12px;right:12px;top:12px;z-index:30;min-height:482px;padding:60px 12px 12px;background:#56135f;box-shadow:0 8px 24px rgba(0,0,0,.45);border-radius:2px;color:#cfc8d8}#mprofilePanel .mprofile-crypto-popup[hidden]{display:none!important}#mprofilePanel .mprofile-crypto-popup .e-p-close-icon-bc{position:absolute;top:12px;right:14px;color:#cfc8d8;font-size:20px;line-height:20px}#mprofilePanel .mprofile-crypto-popup .status-popup-content-w-bc,#mprofilePanel .mprofile-crypto-popup .multi-select-bc,#mprofilePanel .mprofile-crypto-popup .multi-select-popup{height:100%}#mprofilePanel .mprofile-crypto-popup .form-control-bc{position:relative;background:transparent;border-radius:0}#mprofilePanel .mprofile-crypto-popup .form-control-input-bc{width:100%;height:40px;border:0;background:#6b3972;color:#fff;padding:0 38px 0 14px;font-size:12px;outline:0}#mprofilePanel .mprofile-crypto-popup .form-control-input-bc::placeholder{color:rgba(255,255,255,.45)}#mprofilePanel .mprofile-crypto-popup .ss-icon-bc{position:absolute;right:14px;top:11px;color:rgba(255,255,255,.55);font-size:18px}#mprofilePanel .mprofile-crypto-popup .multi-select-label-bc{max-height:384px;overflow-y:auto;background:#2f2f31;box-shadow:0 4px 10px rgba(0,0,0,.25)}#mprofilePanel .mprofile-crypto-popup .checkbox-control-content-bc{display:flex;align-items:center;height:43px;padding:0 14px;border-bottom:1px solid rgba(255,255,255,.055);background:#303032;color:#cfcfcf;font-size:11px;font-weight:700}#mprofilePanel .mprofile-crypto-popup .checkbox-control-content-bc.active{background:#3a3a3d;color:#fff}#mprofilePanel .mprofile-crypto-popup .checkbox-control-text-bc{margin:0;max-width:100%}
  #mprofilePanel .mprofile-crypto-popup{position:fixed!important;left:46px!important;right:35px!important;top:77px!important;width:auto!important;height:auto!important;min-height:0!important;max-height:none!important;padding:0!important;margin:0!important;background:transparent!important;box-shadow:none!important;border-radius:0!important;overflow:visible!important}#mprofilePanel .mprofile-crypto-popup .e-p-close-icon-bc{display:none!important}#mprofilePanel .mprofile-crypto-popup .form-control-bc{height:auto!important;min-height:0!important;margin:0!important;padding:0!important;background:transparent!important;border-radius:0!important}#mprofilePanel .mprofile-crypto-popup .form-control-input-bc{width:100%!important;height:38px!important;border:0!important;background:#6b3972!important;color:#d1c8d8!important;padding:0 37px 0 15px!important;font-size:11px!important;font-weight:400!important;line-height:38px!important;outline:0!important}#mprofilePanel .mprofile-crypto-popup .ss-icon-bc{left:auto!important;right:17px!important;top:10px!important;width:16px!important;height:16px!important;font-size:14px!important;line-height:16px!important;pointer-events:none!important;transform:none!important;margin:0!important}#mprofilePanel .mprofile-crypto-popup .ss-icon-bc:before{position:static!important;display:block!important}#mprofilePanel .mprofile-crypto-popup .multi-select-label-bc{max-height:468px!important;overflow-y:auto!important;background:#2f2f31!important}#mprofilePanel .mprofile-crypto-popup .checkbox-control-content-bc{height:43px!important;min-height:43px!important;padding:0 15px!important;background:#303032!important;color:#cfcfcf!important;font-size:11px!important;font-weight:700!important;line-height:43px!important}#mprofilePanel .mprofile-crypto-popup .checkbox-control-content-bc.active{background:#3a3a3d!important;color:#fff!important}#mprofilePanel .mprofile-crypto-popup .checkbox-control-text-bc{margin:0!important;max-width:100%!important;font-size:11px!important;line-height:43px!important}
</style>
<div class="mprofile-overlay" id="mprofileOverlay" aria-hidden="true"></div>
<aside class="overlay-sliding-wrapper-bc user-profile-container mprofile-panel" id="mprofilePanel" aria-hidden="true" role="dialog" aria-label="Profil" data-site-name="<?= htmlspecialchars($panelSiteName, ENT_QUOTES, 'UTF-8') ?>">
  <div class="overlay-sliding-w-c-content-slider-bc" data-scroll-lock-scrollable>
    <div class="hdr-main-content-bc">
      <div class="logo-container">
        <a class="logo" href="/tr/">
          <?php if ($panelLogoUrl !== ''): ?><img class="hdr-logo-bc" src="<?= htmlspecialchars($panelLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($panelSiteName, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($panelSiteName, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
        </a>
        <a href="https://affiliates.my/" target="_blank" class=" header-icon"><img alt="<?= htmlspecialchars($panelSiteName, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($panelSiteName, ENT_QUOTES, 'UTF-8') ?>" loading="lazy" decoding="async" src="https://cms.casinomilyon615.com/storage/medias/casinomilyon-18755179/media_18755179_1617883267cb571ec980e925adbb0427.gif"></a>
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
                      <span class="u-i-p-a-amount-bc"><span data-balance-target="mprofileBonus">0.00</span> ₺</span>
                      <div class="bonus-info-section"><div><span class="ellipsis">TOPLAM BONUS PARA</span><b><span data-balance-target="mprofileBonus">0.00</span> ₺</b></div></div>
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
          <div class="u-i-p-l-head-bc" data-href="/profile/casino-history"><i class="user-nav-icon bc-i-history" aria-hidden="true"></i><span class="u-i-p-l-h-title-bc ellipsis">CASINO GEÇMİŞİ</span><i class="count-blink-even" data-badge=""></i><i class="u-i-p-l-h-icon-bc bc-i-small-arrow-right" aria-hidden="true"></i></div>
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
      <div class="mprofile-bet-history-view" data-mprofile-view="bet-history" aria-hidden="true">
        <div class="back-nav-bc"><i class="back-nav-icon-bc bc-i-round-arrow-left"></i><span class="back-nav-title-bc ellipsis">BAHİS GEÇMİŞİ</span></div>
        <div class="hdr-navigation-scrollable-bc user-tab-navigation"><div class="hdr-navigation-scrollable-content" data-scroll-lock-scrollable>
          <a class="hdr-navigation-link-bc active" href="/?profile=open&amp;account=history&amp;page=bets" data-mbet-history-tab="bets"><span class="nav-menu-title">TÜMÜ<i class="count-blink-even" data-badge=""></i></span></a>
          <a class="hdr-navigation-link-bc" href="/?profile=open&amp;account=history&amp;page=open-bets" data-mbet-history-tab="open-bets"><span class="nav-menu-title">AÇIK BAHİSLER<i class="count-blink-even" data-badge=""></i></span></a>
          <a class="hdr-navigation-link-bc" href="/?profile=open&amp;account=history&amp;page=cashed-out" data-mbet-history-tab="cashed-out"><span class="nav-menu-title">NAKDE ÇEVRİLDİ<i class="count-blink-even" data-badge=""></i></span></a>
          <a class="hdr-navigation-link-bc" href="/?profile=open&amp;account=history&amp;page=won" data-mbet-history-tab="won"><span class="nav-menu-title">KAZANÇ<i class="count-blink-even" data-badge=""></i></span></a>
          <a class="hdr-navigation-link-bc" href="/?profile=open&amp;account=history&amp;page=lost" data-mbet-history-tab="lost"><span class="nav-menu-title">KAYIP<i class="count-blink-even" data-badge=""></i></span></a>
          <a class="hdr-navigation-link-bc" href="/?profile=open&amp;account=history&amp;page=returned" data-mbet-history-tab="returned"><span class="nav-menu-title">İADE EDİLDİ<i class="count-blink-even" data-badge=""></i></span></a>
          <a class="hdr-navigation-link-bc" href="/?profile=open&amp;account=history&amp;page=won-return" data-mbet-history-tab="won-return"><span class="nav-menu-title">KAZANAN İADE<i class="count-blink-even" data-badge=""></i></span></a>
          <a class="hdr-navigation-link-bc" href="/?profile=open&amp;account=history&amp;page=lost-return" data-mbet-history-tab="lost-return"><span class="nav-menu-title">KAYIP-IADE<i class="count-blink-even" data-badge=""></i></span></a>
        </div></div>
        <div class="u-i-e-p-p-content-bc u-i-common-content mprofile-bet-history-content" data-scroll-lock-scrollable>
          <div class="componentFilterWrapper-bc" data-mbet-filter-wrapper>
            <div class="componentFilterLabel-bc active" data-mbet-filter-toggle>
              <i class="componentFilterLabel-filter-i-bc bc-i-filter"></i>
              <div class="componentFilterLabel-filter-bc"><p class="ellipsis">FİLTRE</p></div>
              <i class="componentFilterChevron-bc bc-i-small-arrow-down"></i>
            </div>
            <div class="componentFilterBody-bc">
              <div class="componentFilterElsWrapper-bc"><form class="filter-form-w-bc">
                <div class="u-i-p-control-item-holder-bc"><div class="form-control-bc default "><label class="form-control-label-bc inputs"><input type="text" inputmode="decimal" class="form-control-input-bc" name="bet_id" step="0" value=""><i class="form-control-input-stroke-bc"></i><span class="form-control-title-bc ellipsis">BAHİS KİMLİĞİ</span></label></div></div>
                <div class="u-i-p-control-item-holder-bc"><div class="form-control-bc default "><label class="form-control-label-bc inputs"><input type="text" class="form-control-input-bc" name="name" step="0" value=""><i class="form-control-input-stroke-bc"></i><span class="form-control-title-bc ellipsis">Spor Adı</span></label></div><i class="sport-search-icon bc-i-search"></i></div>
                <div class="u-i-p-control-item-holder-bc"><div class="form-control-bc select has-icon valid filled"><label class="form-control-label-bc inputs"><select class="form-control-select-bc active" name="bet_type" step="0"><option value="">TÜMÜ</option><option value="1">Tekli</option><option value="2">Kombine</option><option value="3">Sistem</option><option value="50">Bahis Oluşturucu</option></select><i class="form-control-icon-bc bc-i-small-arrow-down"></i><i class="form-control-input-stroke-bc"></i><span class="form-control-title-bc ellipsis">BAHİS TÜRÜ</span></label></div></div>
                <div class="u-i-p-control-item-holder-bc"><div class="form-control-bc select has-icon valid filled"><label class="form-control-label-bc inputs"><select class="form-control-select-bc active" name="period" step="0"><option value="24">24 saat</option><option value="72">72 saat</option><option value="168">Bir hafta</option><option value="720">30 Gün</option><option value="">Özel</option></select><i class="form-control-icon-bc bc-i-small-arrow-down"></i><i class="form-control-input-stroke-bc"></i><span class="form-control-title-bc ellipsis">PERİYOT</span></label></div></div>
                <div class="u-i-p-c-footer-bc"><button class="btn a-color " type="submit" title="Göster"><span>Göster</span></button></div>
              </form></div>
            </div>
          </div>
          <div class="mprofile-bet-history-list" id="mprofileBetHistoryList" data-mbet-history-list><p class="empty-b-text-v-bc" role="status">BAHİS GEÇMİŞİ YÜKLENİYOR...</p></div>
        </div>
      </div>
      <div class="mprofile-casino-history-view" data-mprofile-view="casino-history" aria-hidden="true">
        <div class="back-nav-bc"><i class="back-nav-icon-bc bc-i-round-arrow-left"></i><span class="back-nav-title-bc ellipsis">CASINO GEÇMİŞİ</span></div>
        <div class="hdr-navigation-scrollable-bc user-tab-navigation"><div class="hdr-navigation-scrollable-content" data-scroll-lock-scrollable>
          <a class="hdr-navigation-link-bc active" href="/?profile=open&amp;account=bet&amp;page=casino-history" data-mcasino-history-tab="bets"><span class="nav-menu-title">TÜMÜ<i class="count-blink-even" data-badge=""></i></span></a>
          <a class="hdr-navigation-link-bc" href="/?profile=open&amp;account=bet&amp;page=casino-history&amp;filter=won" data-mcasino-history-tab="won"><span class="nav-menu-title">KAZANÇ<i class="count-blink-even" data-badge=""></i></span></a>
          <a class="hdr-navigation-link-bc" href="/?profile=open&amp;account=bet&amp;page=casino-history&amp;filter=lost" data-mcasino-history-tab="lost"><span class="nav-menu-title">KAYIP<i class="count-blink-even" data-badge=""></i></span></a>
          <a class="hdr-navigation-link-bc" href="/?profile=open&amp;account=bet&amp;page=casino-history&amp;filter=returned" data-mcasino-history-tab="returned"><span class="nav-menu-title">İADE EDİLDİ<i class="count-blink-even" data-badge=""></i></span></a>
        </div></div>
        <div class="u-i-e-p-p-content-bc u-i-common-content mprofile-bet-history-content mprofile-casino-history-content" data-scroll-lock-scrollable>
          <div class="componentFilterWrapper-bc" data-mcasino-filter-wrapper>
            <div class="componentFilterLabel-bc active" data-mcasino-filter-toggle>
              <i class="componentFilterLabel-filter-i-bc bc-i-filter"></i>
              <div class="componentFilterLabel-filter-bc"><p class="ellipsis">FİLTRE</p></div>
              <i class="componentFilterChevron-bc bc-i-small-arrow-down"></i>
            </div>
            <div class="componentFilterBody-bc">
              <div class="componentFilterElsWrapper-bc"><form class="filter-form-w-bc">
                <div class="u-i-p-control-item-holder-bc"><div class="form-control-bc default "><label class="form-control-label-bc inputs"><input type="text" inputmode="decimal" class="form-control-input-bc" name="bet_id" step="0" value=""><i class="form-control-input-stroke-bc"></i><span class="form-control-title-bc ellipsis">OYUN KİMLİĞİ</span></label></div></div>
                <div class="u-i-p-control-item-holder-bc"><div class="form-control-bc default "><label class="form-control-label-bc inputs"><input type="text" class="form-control-input-bc" name="name" step="0" value=""><i class="form-control-input-stroke-bc"></i><span class="form-control-title-bc ellipsis">Oyun Adı</span></label></div><i class="sport-search-icon bc-i-search"></i></div>
                <div class="u-i-p-control-item-holder-bc"><div class="form-control-bc select has-icon valid filled"><label class="form-control-label-bc inputs"><select class="form-control-select-bc active" name="bet_type" step="0"><option value="">TÜMÜ</option><option value="bet">Bahis</option><option value="win">Kazanç</option><option value="cancel">İade</option></select><i class="form-control-icon-bc bc-i-small-arrow-down"></i><i class="form-control-input-stroke-bc"></i><span class="form-control-title-bc ellipsis">İŞLEM TÜRÜ</span></label></div></div>
                <div class="u-i-p-control-item-holder-bc"><div class="form-control-bc select has-icon valid filled"><label class="form-control-label-bc inputs"><select class="form-control-select-bc active" name="period" step="0"><option value="24">24 saat</option><option value="72">72 saat</option><option value="168">Bir hafta</option><option value="720">30 Gün</option><option value="">Özel</option></select><i class="form-control-icon-bc bc-i-small-arrow-down"></i><i class="form-control-input-stroke-bc"></i><span class="form-control-title-bc ellipsis">PERİYOT</span></label></div></div>
                <div class="u-i-p-c-footer-bc"><button class="btn a-color " type="submit" title="Göster"><span>Göster</span></button></div>
              </form></div>
            </div>
          </div>
          <div class="mprofile-bet-history-list" id="mprofileCasinoHistoryList" data-mcasino-history-list><p class="empty-b-text-v-bc" role="status">CASINO GEÇMİŞİ YÜKLENİYOR...</p></div>
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
        <div class="back-nav-bc mprofile-payment-titlebar" data-mprofile-payment-close><i class="back-nav-icon-bc bc-i-round-arrow-left"></i><span class="back-nav-title-bc ellipsis" id="mprofilePaymentModalTitle">Ödeme</span></div>
        <div class="payment-details-scrollable-container" id="mprofilePaymentModalContent" data-scroll-lock-scrollable></div>
      </div>
    </div>
  </div>
</aside>
<script id="mprofilePaymentModalFallback">
(function(){
  if (window.__mprofilePaymentModalFallbackBound) return;
  window.__mprofilePaymentModalFallbackBound = true;
  window.__mprofileSiteName = <?= json_encode($panelSiteName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var methodCache = null, active = null, submitting = false;
  function api(path){return window.BetcoAuthShared&&window.BetcoAuthShared.apiUrl?window.BetcoAuthShared.apiUrl(path):path;}
  function headers(extra){return window.BetcoAuthShared&&window.BetcoAuthShared.memberAuthHeaders?window.BetcoAuthShared.memberAuthHeaders(extra):(extra||{});}
  function esc(v){return String(v==null?'':v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
  function limit(v){var n=Number(v);return isFinite(n)?new Intl.NumberFormat('tr-TR',{maximumFractionDigits:0}).format(n)+' ₺':'—';}
  function mid(m){return String(m&&(m.method_id||m.id||m.payment_method_id)||'').trim();}
  function mname(m){return String(m&&(m.name||m.label||m.title||m.method_id||m.id)||'Ödeme').trim();}
  function siteName(){var panel=document.getElementById('mprofilePanel');return String(window.__mprofileSiteName||(panel&&panel.getAttribute('data-site-name'))||document.documentElement.getAttribute('data-site-name')||'VegasRoyalSpin').trim()||'VegasRoyalSpin';}
  function isCrypto(m){var id=mid(m).toLowerCase(),type=String(m&&m.type||'').toLowerCase(),name=mname(m).toLowerCase();return type==='crypto'||id.indexOf('crypto')!==-1||id.indexOf('btc')!==-1||id.indexOf('usdt')!==-1||name.indexOf('crypto')!==-1||name.indexOf('kripto')!==-1||name.indexOf('bitcoin')!==-1||name.indexOf('tether')!==-1||name.indexOf('tron')!==-1||name.indexOf('usdt')!==-1||name.indexOf('btc')!==-1;}
  function displayName(m){return mname(m);}
  function provider(m){return m&&m.provider&&m.provider.code?String(m.provider.code):'megapayz';}
  function parseCardId(card, kind){var cls=card?String(card.className||''):'';var re=new RegExp('(?:^|\\s)'+kind+'_([^\\s]+)');var mt=cls.match(re);return mt?mt[1]:'';}
  function extractWithdraw(data){if(!data||typeof data!=='object')return[];if(Array.isArray(data.methods))return data.methods;if(data.megapayz_withdraw_form&&Array.isArray(data.megapayz_withdraw_form.methods))return data.megapayz_withdraw_form.methods;if(data.create_withdraw&&Array.isArray(data.create_withdraw.methods))return data.create_withdraw.methods;return[];}
  function loadMethods(){if(methodCache)return Promise.resolve(methodCache);return fetch(api('/api/v2/payment-methods'),{credentials:'same-origin',headers:headers({Accept:'application/json'})}).then(function(r){return r.json();}).then(function(env){var all=env&&env.success&&env.data&&Array.isArray(env.data.payment_methods)?env.data.payment_methods:[];methodCache={deposit:all.filter(function(m){return m&&m.deposit_enabled;}),withdraw:all.filter(function(m){return m&&m.withdrawal_enabled;})};if(methodCache.withdraw.length)return methodCache;return fetch(api('/api/v2/withdraw-payment'),{credentials:'same-origin',headers:headers({Accept:'application/json'})}).then(function(wr){return wr.json();}).then(function(wenv){methodCache.withdraw=wenv&&wenv.success&&wenv.data?extractWithdraw(wenv.data):[];return methodCache;});});}
  function findMethod(kind, card){var id=parseCardId(card,kind);var list=(methodCache&&methodCache[kind])||[];return list.find(function(m){return mid(m).toLowerCase()===id.toLowerCase()||String(m.id||'').toLowerCase()===id.toLowerCase();})||list[0]||null;}
  function close(){var modal=document.getElementById('mprofilePaymentModal');if(modal){modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');modal.style.transform='translateY(100%)';modal.style.opacity='0';}active=null;submitting=false;}
  function msg(type,text){var el=document.querySelector('#mprofilePaymentModal [data-mprofile-payment-message]');if(!el)return;el.textContent=text||'';el.classList.toggle('is-error',type==='error');el.classList.toggle('is-success',type==='success');}
  function syncSubmit(){var amount=document.getElementById('mprofilePaymentAmount'),submit=document.querySelector('#mprofilePaymentModal .mprofile-payment-submit');if(submit&&!submitting)submit.disabled=!(amount&&String(amount.value||'').trim());}
  function logo(m){return String(m&&(m.logo_url||m.logo)||'');}
  function methodClass(m){return displayName(m).replace(/[^A-Za-z0-9_-]+/g,'')||mid(m)||'payment';}
  function withdrawCryptoOptions(){return [['65bd7bba964700005d002ae1','Bitcoin'],['65bd7bc1964700005d002ae2','Litecoin'],['65bd7bd5964700005d002ae4','USDT TRC20']];}
  function cryptoOptions(){return withdrawCryptoOptions();}
  function cryptoPopup(){return '<div id="mprofileCryptoPopup" class="popup-inner-bc mprofile-crypto-popup" aria-hidden="true" hidden><div class="status-popup-content-w-bc"><div><div class="multi-select-bc multi-select-popup"><div class="form-control-bc"><input class="form-control-input-bc" type="text" placeholder="Arama Kripto" value="" id="mprofileCryptoSearch"><i class="ss-icon-bc bc-i-search"></i><div class="multi-select-label-bc" data-scroll-lock-scrollable>'+cryptoOptions().map(function(o,i){return '<label class="checkbox-control-content-bc '+(i===0?'active ':'')+'" data-option-value="'+esc(o[0])+'" data-option-label="'+esc(o[1])+'"><p class="checkbox-control-text-bc ellipsis" style="pointer-events: none;">'+esc(o[1])+'</p></label>';}).join('')+'</div></div></div></div></div></div>';}
  function fields(kind,m){var id=mid(m).toLowerCase();if(kind!=='withdraw')return'';if(isCrypto(m)){return '<div class="u-i-p-control-item-holder-bc"><div class="form-control-bc select has-icon valid filled mprofile-crypto-select" data-mprofile-crypto-open><label class="form-control-label-bc inputs"><input type="hidden" name="bank_id" id="mprofilePaymentNetwork" value="65bd7bba964700005d002ae1"><button type="button" class="form-control-select-bc active mprofile-crypto-select-button"><span id="mprofilePaymentCryptoLabel">Bitcoin</span></button><i class="form-control-icon-bc bc-i-small-arrow-down"></i><i class="form-control-input-stroke-bc"></i><span class="form-control-title-bc ellipsis">Banka</span></label></div></div><div class="u-i-p-control-item-holder-bc"><div class="form-control-bc default filled"><label class="form-control-label-bc inputs"><input type="text" class="form-control-input-bc" id="mprofilePaymentAccount" name="account_number" step="0" value=""><i class="form-control-input-stroke-bc"></i><span class="form-control-title-bc ellipsis">Cüzdan</span></label></div></div>';}var ph=id.indexOf('bank')!==-1?'IBAN':'address';return '<div class="u-i-p-control-item-holder-bc"><div class="form-control-bc default filled"><label class="form-control-label-bc inputs"><input type="text" class="form-control-input-bc" id="mprofilePaymentAccount" name="account_number" step="0" value=""><i class="form-control-input-stroke-bc"></i><span class="form-control-title-bc ellipsis">'+ph+'</span></label></div></div>';}
  function open(kind,m){var modal=document.getElementById('mprofilePaymentModal'),title=document.getElementById('mprofilePaymentModalTitle'),content=document.getElementById('mprofilePaymentModalContent');if(!modal||!content||!m)return;active={kind:kind,method:m};var min=m.min_amount!=null?Number(m.min_amount):0,max=m.max_amount!=null?Number(m.max_amount):999999,name=displayName(m),lg=logo(m),site=siteName();if(title)title.textContent=name;content.innerHTML='<div class="payment-info-bc" tabindex="-1"><div class="payment-info-content"><div class="description-c-row-bc '+esc(methodClass(m))+'"><div class="description-c-row-column-bc pay-logo">'+(lg?'<img alt="" loading="lazy" decoding="async" src="'+esc(lg)+'">':'<span class="payment-logo payment-logo--text">'+esc(name)+'</span>')+'</div><div class="description-c-row-column-bc texts"><div class="description-c-row-c-title-bc description_payment-title"><div class="description-c-r-c-t-column-bc"><span class="description-title ellipsis">Ücret: Ücretsiz</span></div><div class="description-c-r-c-t-column-bc"><span class="description-instant ellipsis">'+esc(m.processing_time||'Anlık')+'</span></div></div><div class="description-card-info"><div class="description-c-r-c-t-column-bc"><span class="description-title ellipsis" title="Min.">Min.</span><span class="description-value ellipsis" title="'+esc(limit(min))+'">'+esc(limit(min))+'</span></div><div class="description-c-r-c-t-column-bc"><span class="description-title ellipsis" title="Maks.">Maks.</span><span class="description-value ellipsis" title="'+esc(limit(max))+'">'+esc(limit(max))+'</span></div></div></div></div><div class="expandableContentWrapper"><div class="expandableContentData '+esc(methodClass(m))+' payment-content not-expandable" data-scroll-lock-scrollable><div class="container"><p>'+esc(site)+' Ailesine hoş geldiniz. İyi eğlenceler, bol şanslar dileriz. '+(kind==='withdraw'?'Para çekmek':'Para yatırmak')+' için lütfen aşağıdaki tüm gerekli alanları doldurun. Minimum tutar altı yatırımlar "İADE EDİLMEZ" lütfen kurallara uygun yatırım yapınız.</p></div></div></div><div class="withdraw-form-l-bc"><form id="mprofilePaymentForm"><div id="screenArea">'+fields(kind,m)+'<div class="u-i-p-control-item-holder-bc"><div class="form-control-bc default"><label class="form-control-label-bc inputs"><input type="text" inputmode="decimal" class="form-control-input-bc" id="mprofilePaymentAmount" name="amount" step="0" value=""><i class="form-control-input-stroke-bc"></i><span class="form-control-title-bc ellipsis">Tutar</span></label></div></div><div class="mprofile-form-message" data-mprofile-payment-message role="status" aria-live="polite"></div><div class="u-i-p-c-footer-bc"><button class="btn a-color '+(kind==='withdraw'?'withdraw':'deposit')+' mprofile-payment-submit" type="submit" title="'+(kind==='withdraw'?'ÇEKİM YAP':'PARA YATIR')+'" disabled><span>'+(kind==='withdraw'?'ÇEKİM YAP':'PARA YATIR')+'</span></button></div></div></form></div></div></div>';modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');modal.style.transform='translateY(0px)';modal.style.opacity='1';var amount=document.getElementById('mprofilePaymentAmount');if(amount)amount.focus({preventScroll:true});}
  function openCrypto(){var popup=document.getElementById('mprofileCryptoPopup'),content=document.getElementById('mprofilePaymentModalContent');if(!popup&&content){content.insertAdjacentHTML('beforeend',cryptoPopup());popup=document.getElementById('mprofileCryptoPopup');}if(!popup)return;popup.hidden=false;popup.setAttribute('aria-hidden','false');var search=document.getElementById('mprofileCryptoSearch');if(search)search.focus({preventScroll:true});}
  function closeCrypto(){var popup=document.getElementById('mprofileCryptoPopup');if(!popup)return;popup.hidden=true;popup.setAttribute('aria-hidden','true');}
  function chooseCrypto(option){if(!option)return;var value=option.getAttribute('data-option-value')||'',label=option.getAttribute('data-option-label')||option.textContent.trim(),input=document.getElementById('mprofilePaymentCryptoType'),network=document.getElementById('mprofilePaymentNetwork'),labelEl=document.getElementById('mprofilePaymentCryptoLabel');if(input)input.value=value;if(network)network.value=value;if(labelEl)labelEl.textContent=label;document.querySelectorAll('#mprofileCryptoPopup .checkbox-control-content-bc').forEach(function(item){item.classList.toggle('active',item===option);});closeCrypto();}
  function filterCrypto(value){var needle=String(value||'').trim().toLowerCase();document.querySelectorAll('#mprofileCryptoPopup .checkbox-control-content-bc').forEach(function(item){var text=String(item.getAttribute('data-option-label')||item.textContent||'').toLowerCase();item.hidden=needle!==''&&text.indexOf(needle)===-1;});}
  if(!window.__mprofileCryptoFetchPatched&&window.fetch){window.__mprofileCryptoFetchPatched=true;var nativeFetch=window.fetch.bind(window);window.fetch=function(input,init){try{var url=String(input&&input.url?input.url:input||'');if(init&&init.body&&url.indexOf('/api/v2/withdraw-payment')!==-1){var body=JSON.parse(init.body),network=document.getElementById('mprofilePaymentNetwork');if(network&&network.value){body.input_fields=body.input_fields||{};body.input_fields.bank_id=network.value;delete body.input_fields.crypto_network;init=Object.assign({},init,{body:JSON.stringify(body)});}}}catch(err){}return nativeFetch(input,init);};}
  function submit(){if(submitting||!active)return;var m=active.method,kind=active.kind,amountEl=document.getElementById('mprofilePaymentAmount'),amount=amountEl?Number(amountEl.value):NaN,min=m.min_amount!=null?Number(m.min_amount):0,max=m.max_amount!=null?Number(m.max_amount):999999;if(!isFinite(amount)||amount<=0){msg('error','Lütfen geçerli bir tutar girin.');return;}if(amount<min){msg('error','Minimum tutar '+limit(min)+'.');return;}if(amount>max){msg('error','Maksimum tutar '+limit(max)+'.');return;}var pid=String(m.id||m.payment_method_id||'').trim(),payload={amount:amount};if(pid)payload.payment_method_id=pid;else{payload.method=mid(m);payload.provider=provider(m);}if(kind==='withdraw'){var acc=document.getElementById('mprofilePaymentAccount'),an=acc?String(acc.value||'').trim():'';if(!an){msg('error','Lütfen hesap bilgisini girin.');return;}payload.payment_method_id=pid||mid(m);payload.account_number=an.replace(/\s/g,'');payload.lang='tr';var nw=document.getElementById('mprofilePaymentNetwork');if(nw&&nw.value)payload.input_fields={crypto_network:nw.value,bank_id:nw.value};}submitting=true;var btn=document.querySelector('#mprofilePaymentModal .mprofile-payment-submit');if(btn){btn.disabled=true;btn.textContent='İşleniyor...';}fetch(api(kind==='withdraw'?'/api/v2/withdraw-payment':'/api/v2/deposit-payment'),{method:'POST',credentials:'same-origin',headers:headers({'Content-Type':'application/json',Accept:'application/json'}),body:JSON.stringify(payload)}).then(function(r){return r.json();}).then(function(env){if(env&&env.success&&env.data&&env.data.payment_url){location.href=String(env.data.payment_url);return;}if(env&&env.success){msg('success',env.message||(kind==='withdraw'?'Çekim talebiniz alındı.':'İşlem başlatıldı.'));setTimeout(close,900);return;}msg('error',env&&env.message?env.message:'İşlem tamamlanamadı.');}).catch(function(){msg('error','Sunucu hatası. Lütfen tekrar deneyin.');}).then(function(){submitting=false;if(btn){btn.disabled=false;btn.textContent=kind==='withdraw'?'ÇEKİM YAP':'PARA YATIR';}});}
  document.addEventListener('click',function(e){var t=e.target&&e.target.closest?e.target:null;if(!t)return;var cryptoOpen=t.closest('[data-mprofile-crypto-open]');if(cryptoOpen){e.preventDefault();e.stopImmediatePropagation();openCrypto();return;}var cryptoClose=t.closest('[data-mprofile-crypto-close]');if(cryptoClose){e.preventDefault();e.stopImmediatePropagation();closeCrypto();return;}var cryptoOption=t.closest('#mprofileCryptoPopup .checkbox-control-content-bc');if(cryptoOption){e.preventDefault();e.stopImmediatePropagation();chooseCrypto(cryptoOption);return;}var closeBtn=t.closest('[data-mprofile-payment-close]');if(closeBtn){e.preventDefault();e.stopImmediatePropagation();close();return;}var submitBtn=t.closest('#mprofilePaymentModal .mprofile-payment-submit');if(submitBtn){e.preventDefault();e.stopImmediatePropagation();submit();return;}var dep=t.closest('[data-mbalance-method]'),wdr=t.closest('[data-mbalance-withdraw-method]');var card=dep||wdr;if(!card)return;e.preventDefault();e.stopImmediatePropagation();var kind=wdr?'withdraw':'deposit';loadMethods().then(function(){open(kind,findMethod(kind,card));});},true);
  document.addEventListener('input',function(e){if(e.target&&e.target.id==='mprofilePaymentAmount')syncSubmit();if(e.target&&e.target.id==='mprofileCryptoSearch')filterCrypto(e.target.value);},true);
  document.addEventListener('submit',function(e){if(e.target&&e.target.id==='mprofilePaymentForm'){e.preventDefault();submit();}},true);
})();
</script>
