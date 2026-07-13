<?php
/**
 * Header dışı global UI: yan çekmeceler, kupon paneli, profil modalı, arama paneli.
 */
?>

<!-- Akıllı Menü: üç nokta butonunun altında açılan floating dikey menü -->
<aside class="hdr-smart-panel-fixed" id="smartPanelFixed" aria-label="Akıllı Menü" aria-hidden="true">
    <div class="hdr-smart-panel-holder-bc">
        <button class="sp-button-bc" type="button" title="Bildirimler" id="smart-panel-notification-btn"
                aria-label="Bildirimler" data-sp-action="notification">
            <i class="sp-button-icon-bc bc-i-notification"></i>
            <span class="sp-badge" data-badge=""></span>
            <span class="sp-tooltip">Bildirimler</span>
        </button>
        <button class="sp-button-bc" type="button" title="Favoriler" id="smart-panel-favorites-btn"
                aria-label="Favoriler" data-sp-action="favorites">
            <i class="sp-button-icon-bc bc-i-favorite"></i>
            <span class="sp-tooltip">Favoriler</span>
        </button>
        <a class="sp-button-bc" href="/promotions" data-nav-mode="page" title="Bonuslar" aria-label="Bonuslar">
            <i class="sp-button-icon-bc bc-i-promotions-3"></i>
            <span class="sp-tooltip">Bonuslar</span>
        </a>
        <button class="sp-button-bc" type="button" title="Ayarlar" id="smart-panel-settings-btn"
                aria-label="Ayarlar" data-sp-action="settings">
            <i class="sp-button-icon-bc bc-i-settings"></i>
            <span class="sp-tooltip">Ayarlar</span>
        </button>
        <button class="sp-button-bc" type="button" title="Canlı Destek" data-live-chat
                onclick='window.open(<?= htmlspecialchars(json_encode((string) ($siteContactLinks["live_support_url"] ?? (defined("LIVE_SUPPORT_URL") ? LIVE_SUPPORT_URL : "")), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>,"_blank")'>
            <i class="sp-button-icon-bc bc-i-live-chat"></i>
            <span class="sp-tooltip">Canlı Destek</span>
        </button>
    </div>
</aside>

<!-- Right sidebar (ortak overlay; bildirim ve favoriler aynı yapıyı kullanır) -->
<div class="right-sidebar-overlay" id="rightSidebarOverlay" aria-hidden="true"></div>

<!-- Bildirimler sidebar -->
<aside class="right-sidebar" id="notificationDrawer" data-right-sidebar="notification" role="dialog" aria-label="Bildirimler" aria-hidden="true">
    <div class="right-sidebar__header">
        <button type="button" class="right-sidebar__close" data-right-sidebar-close aria-label="Kapat">&times;</button>
        <?php
        $notificationDrawerSite = (string) ($headerSiteName ?? $footerSiteName ?? $ayar['site_adi'] ?? 'SITE');
        $notificationDrawerSiteUpper = function_exists('mb_strtoupper')
            ? mb_strtoupper($notificationDrawerSite, 'UTF-8')
            : strtoupper($notificationDrawerSite);
        ?>
        <h2 class="right-sidebar__title"><?= htmlspecialchars($notificationDrawerSiteUpper, ENT_QUOTES, 'UTF-8') ?> YENİLİKLER</h2>
    </div>
    <div class="notification-drawer__toolbar">
        <span class="notification-drawer__date" id="notificationDrawerDate">18 Mart 2026</span>
        <button type="button" class="notification-drawer__clear" id="notificationDrawerClear">Temizle</button>
    </div>
    <div class="notification-drawer__list" id="notificationDrawerList" data-announcements-url="/api/v2/announcements" aria-live="polite"></div>
</aside>

<!-- Favoriler sidebar -->
<aside class="right-sidebar" id="favoritesDrawer" data-right-sidebar="favorites" role="dialog" aria-label="Favoriler" aria-hidden="true">
    <div class="right-sidebar__header">
        <button type="button" class="right-sidebar__close" data-right-sidebar-close aria-label="Kapat">&times;</button>
        <h2 class="right-sidebar__title">FAVORİLER</h2>
    </div>
    <p class="favorites-sidebar__guest-msg" id="favoritesGuestMsg" hidden>Favorilerinizi görmek için giriş yapın.</p>
    <div class="favorites-sidebar__tabs">
        <button type="button" class="favorites-sidebar__tab is-active" data-favorites-tab="sport">SPOR <span class="favorites-sidebar__count" data-favorites-count="sport">(0)</span></button>
        <button type="button" class="favorites-sidebar__tab" data-favorites-tab="slot">SLOT <span class="favorites-sidebar__count" data-favorites-count="slot">(0)</span></button>
        <button type="button" class="favorites-sidebar__tab" data-favorites-tab="live">CANLI <span class="favorites-sidebar__count" data-favorites-count="live">(0)</span></button>
        <button type="button" class="favorites-sidebar__tab" data-favorites-tab="competition">Yarışma <span class="favorites-sidebar__count" data-favorites-count="competition">(0)</span></button>
        <button type="button" class="favorites-sidebar__tab" data-favorites-tab="match">Kibrit <span class="favorites-sidebar__count" data-favorites-count="match">(0)</span></button>
    </div>
    <div class="favorites-sidebar__body" id="favoritesSidebarBody">
        <div class="favorites-sidebar__pane is-active" data-favorites-pane="sport"></div>
        <div class="favorites-sidebar__pane" data-favorites-pane="slot" hidden>
            <p class="favorites-sidebar__loading favorites-sidebar__msg" id="favoritesSlotLoading" hidden>Yükleniyor…</p>
            <p class="favorites-sidebar__error favorites-sidebar__msg" id="favoritesSlotError" hidden></p>
            <ul class="favorites-game-list" id="favoritesSlotList" aria-label="Slot favorileri"></ul>
            <p class="favorites-sidebar__empty favorites-sidebar__empty--tab" id="favoritesSlotEmpty" hidden>Slot favoriniz yok. Eklemek için slot sayfasında yıldıza tıklayın.</p>
        </div>
        <div class="favorites-sidebar__pane" data-favorites-pane="live" hidden>
            <p class="favorites-sidebar__loading favorites-sidebar__msg" id="favoritesLiveLoading" hidden>Yükleniyor…</p>
            <p class="favorites-sidebar__error favorites-sidebar__msg" id="favoritesLiveError" hidden></p>
            <ul class="favorites-game-list" id="favoritesLiveList" aria-label="Canlı casino favorileri"></ul>
            <p class="favorites-sidebar__empty favorites-sidebar__empty--tab" id="favoritesLiveEmpty" hidden>Canlı casino favoriniz yok.</p>
        </div>
        <div class="favorites-sidebar__pane" data-favorites-pane="competition" hidden></div>
        <div class="favorites-sidebar__pane" data-favorites-pane="match" hidden></div>
    </div>
</aside>

<!-- Ayarlar sidebar -->
<aside class="right-sidebar" id="settingsDrawer" data-right-sidebar="settings" role="dialog" aria-label="Ayarlar" aria-hidden="true">
    <div class="right-sidebar__header">
        <button type="button" class="right-sidebar__close" data-right-sidebar-close aria-label="Kapat">&times;</button>
        <h2 class="right-sidebar__title">AYARLAR</h2>
    </div>
    <div class="right-sidebar__body settings-sidebar__body">
        <div class="settings-sidebar__field" data-settings-field="odds">
            <span class="settings-sidebar__label">Oran formatı</span>
            <button type="button" class="settings-sidebar__select" aria-expanded="false" aria-haspopup="listbox" aria-label="Oran formatı seçin">
                <span class="settings-sidebar__value" data-settings-value="odds">Ondalık</span>
                <i class="settings-sidebar__chevron fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="settings-sidebar__options" role="listbox" hidden>
                <button type="button" class="settings-sidebar__option" role="option" data-value="Ondalık">Ondalık</button>
                <button type="button" class="settings-sidebar__option" role="option" data-value="Kesirli">Kesirli</button>
                <button type="button" class="settings-sidebar__option" role="option" data-value="Amerikan">Amerikan</button>
                <button type="button" class="settings-sidebar__option" role="option" data-value="HongKong">HongKong</button>
                <button type="button" class="settings-sidebar__option" role="option" data-value="Malay">Malay</button>
                <button type="button" class="settings-sidebar__option" role="option" data-value="Indo">Indo</button>
            </div>
        </div>
        <div class="settings-sidebar__field" data-settings-field="language">
            <span class="settings-sidebar__label">Dil</span>
            <button type="button" class="settings-sidebar__select" aria-expanded="false" aria-haspopup="listbox" aria-label="Dil seçin">
                <span class="settings-sidebar__value settings-sidebar__value--with-icon" data-settings-value="language">
                    <span class="flag-icon flag-icon-tr" aria-hidden="true"></span>
                    Türkçe
                </span>
                <i class="settings-sidebar__chevron fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="settings-sidebar__options" role="listbox" hidden>
                <button type="button" class="settings-sidebar__option settings-sidebar__option--with-icon" role="option" data-value="Türkçe"><span class="flag-icon flag-icon-tr"></span> Türkçe</button>
                <button type="button" class="settings-sidebar__option settings-sidebar__option--with-icon" role="option" data-value="English"><span class="flag-icon flag-icon-us"></span> English</button>
                <button type="button" class="settings-sidebar__option settings-sidebar__option--with-icon" role="option" data-value="Deutsch"><span class="flag-icon flag-icon-de"></span> Deutsch</button>
            </div>
        </div>
        <div class="settings-sidebar__field" data-settings-field="timeformat">
            <span class="settings-sidebar__label">SAAT FORMATI</span>
            <button type="button" class="settings-sidebar__select" aria-expanded="false" aria-haspopup="listbox" aria-label="Saat formatı seçin">
                <span class="settings-sidebar__value" data-settings-value="timeformat">24 saat</span>
                <i class="settings-sidebar__chevron fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="settings-sidebar__options" role="listbox" hidden>
                <button type="button" class="settings-sidebar__option" role="option" data-value="12 saat">12 saat</button>
                <button type="button" class="settings-sidebar__option" role="option" data-value="24 saat">24 saat</button>
            </div>
        </div>
    </div>
</aside>

<!-- Oyun arama sidebar (sağda açılan smart menü ile aynı right-sidebar şablonu) -->
<aside class="right-sidebar" id="searchDrawer" data-right-sidebar="search" role="dialog" aria-label="Oyun Ara" aria-hidden="true">
    <div class="right-sidebar__header">
        <button type="button" class="right-sidebar__close" data-right-sidebar-close aria-label="Kapat">&times;</button>
        <h2 class="right-sidebar__title">OYUN ARA</h2>
    </div>
    <div class="right-sidebar__body">
        <div class="games-search-bar right-sidebar-search-bar">
            <input type="text" class="games-search-input" placeholder="Oyun Ara" id="searchModalInput" value="">
            <button type="button" class="games-search-btn games-search-icon-btn" id="searchClearBtn" title="Aramayı temizle" aria-label="Aramayı temizle"><i class="fas fa-search" id="searchClearBtnIcon" aria-hidden="true"></i></button>
        </div>
    </div>
</aside>

<!-- Kupon / Açık Bahisler paneli (ortadan yukarı açılır) -->
<div class="betslip-panel-overlay" id="betslipPanelOverlay" aria-hidden="true"></div>
<div class="betslip-panel" id="betslipPanel" role="dialog" aria-label="Kupon ve Açık Bahisler" aria-hidden="true">
    <button type="button" class="betslip-panel__close" id="betslipPanelClose" aria-label="Kapat">&times;</button>
    <div class="betslip-panel__tabs">
        <button type="button" class="betslip-panel__tab" data-tab="kupon" aria-selected="true">KUPON</button>
        <button type="button" class="betslip-panel__tab" data-tab="acik-bahisler" aria-selected="false">AÇIK BAHİSLER</button>
    </div>
    <button type="button" class="betslip-panel__settings" id="betslipPanelSettings" aria-haspopup="listbox" aria-expanded="false" aria-label="Oran değişikliği tercihi">
        <i class="betslip-panel__settings-icon bc-i-settings" aria-hidden="true"></i>
        <span class="betslip-panel__settings-text" id="betslipOddsPrefLabel">Her zaman sor</span>
        <i class="betslip-panel__settings-chevron fa-solid fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="betslip-panel__body">
        <div class="betslip-panel__pane" data-pane="kupon">
            <p class="betslip-panel__empty">Bahis kuponunuz boş</p>
        </div>
        <div class="betslip-panel__pane" data-pane="acik-bahisler" hidden>
            <a href="/profile/bet-history" class="betslip-panel__history-link" data-nav-mode="modal">Bahis geçmişine git</a>
            <p class="betslip-panel__empty">Şu anda açık bahsiniz yok</p>
        </div>
    </div>
    <?php if (empty($loggedIn) && defined('SURFACE') && SURFACE === 'mobile'): ?>
    <div class="betslip-panel__auth-warning" role="status">
        <span class="betslip-panel__auth-warning-icon" aria-hidden="true"><i class="fa-solid fa-exclamation-triangle"></i></span>
        <p class="betslip-panel__auth-warning-text">Bahis yapmak için lütfen <a href="#" class="betslip-panel__auth-link" id="betslipAuthLoginLink">GİRİŞ</a> veya <a href="#" class="betslip-panel__auth-link" id="betslipAuthRegisterLink">KAYIT</a></p>
    </div>
    <?php endif; ?>
    <div class="betslip-panel__footer">
        <div class="betslip-panel__amounts">
            <button type="button" class="betslip-panel__amount-btn">50</button>
            <button type="button" class="betslip-panel__amount-btn">100</button>
            <button type="button" class="betslip-panel__amount-btn">500</button>
            <button type="button" class="betslip-panel__edit-btn" aria-label="Tutar düzenle"><i class="fa-solid fa-pencil"></i></button>
        </div>
        <button type="button" class="betslip-panel__place-btn" disabled>BAHİS YAP</button>
    </div>
</div>

<!-- Mobil: oran tercihi tam ekran (smart menü kupon ayarı) -->
<div class="betslip-odds-fullpage" id="betslipOddsFullpage" aria-hidden="true" hidden>
    <div class="betslip-odds-fullpage__inner" role="dialog" aria-modal="true" aria-label="Oran değişikliği">
        <button type="button" class="betslip-odds-fullpage__bar" id="betslipOddsFullpageCloseBar" aria-expanded="true">
            <i class="betslip-panel__settings-icon bc-i-settings" aria-hidden="true"></i>
            <span class="betslip-odds-fullpage__bar-text" id="betslipOddsFullpageBarText">Her zaman sor</span>
            <i class="fa-solid fa-chevron-up betslip-odds-fullpage__chev" aria-hidden="true"></i>
        </button>
        <ul class="betslip-odds-fullpage__list" id="betslipOddsFullpageList" role="listbox" aria-label="Tercihler">
            <li role="none"><button type="button" class="betslip-odds-fullpage__option is-selected" role="option" data-odds-pref="always_ask" data-label="Her zaman sor">Her zaman sor</button></li>
            <li role="none"><button type="button" class="betslip-odds-fullpage__option" role="option" data-odds-pref="higher" data-label="Daha yüksek oranları kabul et">Daha yüksek oranları kabul et</button></li>
            <li role="none"><button type="button" class="betslip-odds-fullpage__option" role="option" data-odds-pref="accept" data-label="Oranı kabul et">Oranı kabul et</button></li>
        </ul>
    </div>
</div>

<!-- Para yatırma / ödeme uyarı-hata diyaloğu (profil modalının üstünde) -->
<div class="app-feedback-dialog-overlay" id="appFeedbackDialogOverlay" aria-hidden="true"></div>
<div class="app-feedback-dialog" id="appFeedbackDialog" role="alertdialog" aria-modal="true" aria-hidden="true" aria-labelledby="appFeedbackDialogTitle">
    <div class="app-feedback-dialog__card">
        <button type="button" class="app-feedback-dialog__dismiss" id="appFeedbackDialogDismiss" aria-label="Kapat">&times;</button>
        <div class="app-feedback-dialog__icon-wrap" id="appFeedbackDialogIconWrap" aria-hidden="true"></div>
        <h2 class="app-feedback-dialog__title" id="appFeedbackDialogTitle"></h2>
        <p class="app-feedback-dialog__message" id="appFeedbackDialogMessage"></p>
        <button type="button" class="app-feedback-dialog__primary" id="appFeedbackDialogOk">Tamam</button>
    </div>
</div>

<!-- Profil sayfası modalı (iframe yok, içerik AJAX ile yüklenir) -->
<div class="profile-modal-overlay" id="profileModalOverlay" aria-hidden="true"></div>
<div class="profile-modal" id="profileModal" role="dialog" aria-label="Profil" aria-modal="true" aria-hidden="true">
    <div class="profile-modal__frame-wrap">
        <div class="profile-modal__loading" id="profileModalLoading" aria-hidden="true">
            <span class="profile-modal__spinner"></span>
            <span class="profile-modal__loading-text">Yükleniyor...</span>
        </div>
        <div id="profileModalContent" class="profile-modal__content"></div>
    </div>
</div>

<!-- Arama paneli (sağdan açılır) -->
<div class="search-overlay" id="searchOverlay" aria-hidden="true"></div>
<aside class="search-panel" id="searchPanel" role="dialog" aria-label="Arama" aria-hidden="true">
    <button type="button" class="search-panel__toggle-tab" id="searchPanelClose" aria-label="Kapat" title="Kapat">
        <i class="fa-solid fa-chevron-right"></i>
    </button>
    <div class="search-panel__inner">
        <div class="search-panel__input-wrap">
            <input type="text" class="search-panel__input" id="searchPanelInput" placeholder="Spor'da ara" autocomplete="off" aria-label="Arama">
            <i class="fa-solid fa-search search-panel__input-icon" aria-hidden="true"></i>
        </div>
        <div class="search-panel__filters">
            <button type="button" class="search-panel__filter is-active" data-filter="sport">SPOR</button>
            <button type="button" class="search-panel__filter" data-filter="casino">CASİNO</button>
            <button type="button" class="search-panel__filter" data-filter="livecasino">CANLI CASİNO</button>
        </div>
        <div class="search-panel__body" id="searchPanelBody">
            <p class="search-panel__empty">Arama yapmak için yukarıdaki alanı kullanın.</p>
        </div>
    </div>
</aside>
