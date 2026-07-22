<?php
/**
 * Header partial — casinomilyon590.com BC platform yapısı
 */
if (function_exists('isMobile') && isMobile() && defined('MOBILE_PATH')) {
    $mobileHeader = MOBILE_PATH . '/views/partials/header.php';
    if (file_exists($mobileHeader)) { include $mobileHeader; return; }
}
require_once __DIR__ . '/header-init.php';
$loggedIn = isset($loggedIn) ? (bool) $loggedIn : false;
$ayar = isset($ayar) && is_array($ayar) ? $ayar : [];
$siteContactLinks = isset($siteContactLinks) && is_array($siteContactLinks) ? $siteContactLinks : [];
$siteBranding = isset($siteBranding) && is_array($siteBranding) ? $siteBranding : [];
$headerContactLinks = is_array($siteContactLinks ?? null)
    ? $siteContactLinks
    : (class_exists('ApiSiteSettings') ? ApiSiteSettings::normalizeContactLinks(is_array($ayar ?? null) ? $ayar : []) : []);
$headerPartnershipUrl = (string) ($headerContactLinks['partnership_url'] ?? '/ortaklik');
$headerPartnershipLabel = (string) ($headerContactLinks['partnership_label'] ?? 'ORTAKLIK');
$headerPartnershipTitle = (string) ($headerContactLinks['partnership_title'] ?? 'Ortaklık');
$headerSupportUrl = (string) ($headerContactLinks['live_support_url'] ?? (defined('LIVE_SUPPORT_URL') ? LIVE_SUPPORT_URL : ''));
$headerSupportTitle = (string) ($headerContactLinks['live_support_title'] ?? 'Canlı Destek');
$headerSupportUrlJs = json_encode($headerSupportUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$headerSupportUrlJs = is_string($headerSupportUrlJs) ? $headerSupportUrlJs : '""';
$headerSupportOnclick = 'window.open(' . $headerSupportUrlJs . ', "_blank"); return false;';
$headerBranding = is_array($siteBranding ?? null) ? $siteBranding : [];
$headerSiteName      = (string) ($headerBranding['site_name']         ?? $ayar['site_adi']           ?? 'VegasRoyalSpin');
$headerLogoUrl       = cms_asset_url((string) ($headerBranding['logo_url']          ?? $ayar['logo_url']          ?? ''));
$headerLogoAnimated  = cms_asset_url((string) ($headerBranding['logo_animated_url'] ?? $ayar['logo_animated_url'] ?? ''));
?>
<header class="headBar header-bc generic-search-enabled<?= $loggedIn ? ' hdr-auth-user' : ' hdr-auth-guest' ?>">
    <div class="settingBar" aria-hidden="true"></div>
    <div class="container-fluid">
        <div class="row align-items-center">
          <div class="col-4 col-sm-3 col-md-2 col-lg-2 position-relative pl-2 pl-lg-0 d-flex align-items-center">
            <a class="headLogo logo" href="/" data-site-logo-link>
                <?php if ($headerLogoAnimated !== ''): ?>
                    <?php $animExt = strtolower(pathinfo((string) parse_url($headerLogoAnimated, PHP_URL_PATH), PATHINFO_EXTENSION)); ?>
                    <?php if ($animExt === 'webm' || $animExt === 'mp4'): ?>
                        <video class="hdr-logo-bc" autoplay loop muted playsinline width="240" height="80" aria-label="<?= htmlspecialchars($headerSiteName, ENT_QUOTES, 'UTF-8') ?>">
                            <source src="<?= htmlspecialchars($headerLogoAnimated, ENT_QUOTES, 'UTF-8') ?>" type="video/webm">
                            <img src="<?= htmlspecialchars($headerLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($headerSiteName, ENT_QUOTES, 'UTF-8') ?>">
                        </video>
                    <?php else: ?>
                        <img src="<?= htmlspecialchars($headerLogoAnimated, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($headerSiteName, ENT_QUOTES, 'UTF-8') ?>" width="240" height="80" class="hdr-logo-bc">
                    <?php endif; ?>
                <?php elseif ($headerLogoUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($headerLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($headerSiteName, ENT_QUOTES, 'UTF-8') ?>" width="240" height="80" class="hdr-logo-bc">
                <?php endif; ?>
            </a>
          </div>
          <div class="col-8 col-sm-9 col-md-10 col-lg-10 d-md-flex justify-content-end align-items-center">
            <div class="headerItem hdr-main-content-bc">
              <div class="loginCol hdr-user-bc">

                <?php if ($loggedIn): ?>

                    <div class="loyaltyBonusHeader hasLoyaltyLevel"
                         title="<?= htmlspecialchars((string) ($headerLoyaltyBadge['name'] ?? 'Bronze'), ENT_QUOTES, 'UTF-8') ?>"
                         data-loyalty-badge
                         data-loyalty-code="<?= htmlspecialchars((string) ($headerLoyaltyBadge['code'] ?? 'bronze'), ENT_QUOTES, 'UTF-8') ?>">
                        <p class="loyaltyBonusHeaderShadow" aria-hidden="true"></p>
                        <p class="loyaltyBonusHeaderBackground" aria-hidden="true"></p>
                        <span class="loyaltyBonusHeaderText" data-loyalty-level-name><?= htmlspecialchars(strtoupper((string) ($headerLoyaltyBadge['name'] ?? 'Bronze')), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="loyaltyBonusImg" aria-hidden="true" data-loyalty-level-initial><?= htmlspecialchars((string) ($headerLoyaltyBadge['initial'] ?? 'B'), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <!-- ORTAKLIK + Destek -->
                    <div class="header-custom-buttons">
                        <a href="<?= htmlspecialchars($headerPartnershipUrl, ENT_QUOTES, 'UTF-8') ?>"
                           class="btn a-color header-icon-text bc-i-standings"
                           title="<?= htmlspecialchars($headerPartnershipTitle, ENT_QUOTES, 'UTF-8') ?>">
                            <span><?= htmlspecialchars($headerPartnershipLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </a>
                        <a href="#"
                           class="btn a-color header-icon-text bc-i-call callPanel"
                           onclick="<?= htmlspecialchars($headerSupportOnclick, ENT_QUOTES, 'UTF-8') ?>"
                           title="<?= htmlspecialchars($headerSupportTitle, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($headerSupportTitle, ENT_QUOTES, 'UTF-8') ?>"></a>
                    </div>

                    <!-- Yeşil PARA YATIR (giriş sonrası — referans: bc-i-wallet) -->
                    <a class="btn a-color header-icon-text bc-i-wallet hdr-deposit-btn"
                       href="/profile/deposit-withdraw?openDepositPanel=1"
                       data-profile-modal-href="/profile/deposit-withdraw?openDepositPanel=1"
                       data-nav-mode="modal"
                       onclick="event.preventDefault(); redirectToDeposit();"
                       title="Para Yatır">
                        <span>PARA YATIR</span>
                    </a>

                    <!-- CÜZDANA BAĞLAN (ayrı buton) -->
                    <div class="header-buttons-wallet">
                        <button class="btn a-color connect-wallet"
                                id="connectWalletBtn"
                                type="button"
                                data-profile-modal-href="/profile/deposit-withdraw?openDepositPanel=1"
                                data-nav-mode="modal"
                                onclick="redirectToDeposit();"
                                title="Cüzdana Bağlan">
                            CÜZDANA BAĞLAN
                        </button>
                    </div>

                    <!-- Bakiye + Profil (referans: header-user-nav) -->
                    <div class="nav-menu-container header-user-nav">
                        <ul class="nav-menu-other hdr-balance-nav">
                            <li id="depositBalanceWrap">
                                <a href="#"
                                   class="nav-menu-item hdr-balance-trigger"
                                   id="balanceTrigger"
                                   role="button"
                                   aria-expanded="false"
                                   aria-haspopup="true">
                                    <div class="hdr-user-info-content-bc">
                                        <span class="hdr-user-info-texts-bc ext-1 ellipsis">
                                            <span class="balanceAmount">
                                                <span id="headerBalanceMain" data-balance-target="headerBalanceMain"><?= htmlspecialchars(number_format((float) ($headerInitialBalance ?? 0), 2, '.', ','), ENT_QUOTES, 'UTF-8') ?></span>
                                                <span class="currencySymbol">&#8239;₺</span>
                                            </span>
                                        </span>
                                    </div>
                                </a>
                                <div class="depositNav hidesection" id="depositNav" role="menu">
                                    <a class="depositNav-link" href="/profile/deposit-withdraw" data-nav-mode="modal" role="menuitem">
                                        <i class="depositNav-icon bc-i-circle-dollar" aria-hidden="true"></i> PARA YATIR
                                    </a>
                                    <a class="depositNav-link" href="/profile/withdraw" data-nav-mode="modal" role="menuitem">
                                        <i class="depositNav-icon bc-i-withdraw" aria-hidden="true"></i> ÇEKİM
                                    </a>
                                    <a class="depositNav-link" href="/?profile=open&amp;account=balance&amp;page=history" data-profile-modal-href="/profile/deposit-withdraw-history" data-nav-mode="modal" role="menuitem">
                                        <i class="depositNav-icon bc-i-bet-history" aria-hidden="true"></i> İŞLEM GEÇMİŞİ
                                    </a>
                                    <a class="depositNav-link" href="/profile/deposit-withdraw?bilgi=1#bilgi" data-nav-mode="modal" role="menuitem">
                                        <i class="depositNav-icon bc-i-info" aria-hidden="true"></i> BİLGİ
                                    </a>
                                    <a class="depositNav-link" href="/profile/withdrawal-status" data-nav-mode="modal" role="menuitem">
                                        <i class="depositNav-icon bc-i-withdraw" aria-hidden="true"></i> PARA ÇEKME DURUMU
                                    </a>
                                </div>
                            </li>
                        </ul>
                        <ul class="nav-menu-other profileDetails">
                            <li>
                                <div class="user-nav-icon playerCol" id="playerCol">
                                    <button class="userBtn nav-menu-item" id="toggleButton" type="button"
                                            aria-expanded="false" aria-label="Profil menüsü">
                                        <span class="avatarHolderImg">
                                            <i class="bc-i-user hdr-user-avatar-icon-bc" aria-hidden="true"></i>
                                        </span>
                                    </button>
                                    <div class="playerNav hidesection" id="playerNav" role="menu">
                                        <div class="playerNav-body">
                                        <a class="pl-link" href="#" id="profileLinkModal" role="menuitem">
                                            <i class="pl-link-icon bc-i-user" aria-hidden="true"></i> PROFİLİM
                                        </a>
                                        <a class="pl-link" href="/profile/deposit-withdraw" data-nav-mode="modal" role="menuitem">
                                            <i class="pl-link-icon bc-i-deposit" aria-hidden="true"></i> BAKİYE YÖNETİMİ
                                        </a>
                                        <a class="pl-link" href="/profile/bet-history" data-nav-mode="modal" role="menuitem">
                                            <i class="pl-link-icon bc-i-bet-history" aria-hidden="true"></i> BAHİS GEÇMİŞİ
                                        </a>
                                        <a class="pl-link" href="/profile/bonus-spor" data-nav-mode="modal" role="menuitem">
                                            <i class="pl-link-icon bc-i-promotions-3" aria-hidden="true"></i> BONUSLAR
                                        </a>
                                        <a class="pl-link" href="/profile/messages" data-nav-mode="modal" role="menuitem">
                                            <i class="pl-link-icon bc-i-message" aria-hidden="true"></i> MESAJLAR
                                        </a>
                                        </div>
                                        <div class="playerNav-footer">
                                        <a class="pl-link pl-link-logout" href="/logout" data-nav-mode="page" role="menuitem">
                                            <i class="pl-link-icon bc-i-logout" aria-hidden="true"></i> ÇIKIŞ YAP
                                        </a>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>

                <?php else: ?>

                    <div class="header-custom-buttons">
                        <a href="<?= htmlspecialchars($headerPartnershipUrl, ENT_QUOTES, 'UTF-8') ?>"
                           class="btn a-color header-icon-text bc-i-standings"
                           title="<?= htmlspecialchars($headerPartnershipTitle, ENT_QUOTES, 'UTF-8') ?>">
                            <span><?= htmlspecialchars($headerPartnershipLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </a>
                        <a href="#"
                           class="btn a-color header-icon-text bc-i-call callPanel"
                           onclick="<?= htmlspecialchars($headerSupportOnclick, ENT_QUOTES, 'UTF-8') ?>"
                           title="<?= htmlspecialchars($headerSupportTitle, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($headerSupportTitle, ENT_QUOTES, 'UTF-8') ?>"></a>
                    </div>

                    <a href="#"
                       class="btn a-color header-icon-text bc-i-wallet hdr-deposit-btn"
                       id="openRegister2" role="button">
                        <span>PARA YATIR</span>
                    </a>

                    <a href="#" class="btn sign-in loginBtn" id="Giris" role="button">GİRİŞ</a>
                    <button id="openRegister" class="btn register" type="button">KAYIT</button>

                <?php endif; ?>

                <!-- Dil -->
                <div class="langSelect dropdown" id="langDropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button"
                       aria-expanded="false" id="dropdown09">
                        <span class="flag-icon flag-icon-tr"></span>
                        <span class="lang-code">TUR</span>
                    </a>
                    <div class="dropdown-menu" aria-labelledby="dropdown09">
                        <a class="dropdown-item" href="?lang=tr">
                            <span class="flag-icon flag-icon-tr"></span><span class="code">TUR</span>
                        </a>
                        <a class="dropdown-item" href="?lang=en">
                            <span class="flag-icon flag-icon-us"></span><span class="code">ENG</span>
                        </a>
                        <a class="dropdown-item" href="?lang=de">
                            <span class="flag-icon flag-icon-de"></span><span class="code">DEU</span>
                        </a>
                    </div>
                </div>

                <!-- Saat -->
                <span class="badge currentTime infoTime" id="turkeyTime">00:00:00</span>

                <!-- Akıllı menü -->
                <div class="smartPanel-bc">
                    <button class="hdr-toggle-button-bc bc-i-vertical-toggle count-odd-animation"
                            id="smart-panel-holder"
                            title="Akıllı Menü" type="button"
                            aria-label="Akıllı Menü" aria-expanded="false"
                            data-badge=""></button>
                </div>

                <!-- Arama -->
                <button type="button"
                        class="header-search-btn generic-search-btn"
                        id="headerSearchBtn"
                        title="Ara" aria-label="Ara" aria-expanded="false">
                    <i class="bc-i-search" aria-hidden="true"></i>
                </button>

              </div><!-- /.loginCol -->
            </div><!-- /.headerItem -->
          </div>
        </div>
    </div>
    <?php include __DIR__ . '/main-menu-nav.php'; ?>
</header>
<?php include __DIR__ . '/header-global-panels.php'; ?>
<?php include __DIR__ . '/layout-after-header.php'; ?>
