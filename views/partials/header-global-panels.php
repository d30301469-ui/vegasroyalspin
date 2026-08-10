<?php
/**
 * Header dışı global UI: yan çekmeceler, kupon paneli, profil modalı, arama paneli.
 */
global $ayar, $loggedIn, $siteContactLinks, $siteBranding, $siteSettingsPayload;
if ((!isset($siteContactLinks) || !is_array($siteContactLinks) || $siteContactLinks === [])
    && isset($siteSettingsPayload['contact']) && is_array($siteSettingsPayload['contact'])) {
    $siteContactLinks = $siteSettingsPayload['contact'];
}
$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>

<!-- Akıllı Menü: üç nokta butonunun altında açılan floating dikey menü -->
<aside class="hdr-smart-panel-fixed" id="smartPanelFixed" aria-label="<?= $h(__('nav.smart_menu')) ?>" aria-hidden="true">
    <div class="hdr-smart-panel-holder-bc">
        <button class="sp-button-bc" type="button" title="<?= $h(__('panel.notifications')) ?>" id="smart-panel-notification-btn"
                aria-label="<?= $h(__('panel.notifications')) ?>" data-sp-action="notification">
            <i class="sp-button-icon-bc bc-i-notification"></i>
            <span class="sp-badge" data-badge=""></span>
            <span class="sp-tooltip"><?= $h(__('panel.notifications')) ?></span>
        </button>
        <button class="sp-button-bc" type="button" title="<?= $h(__('panel.favorites')) ?>" id="smart-panel-favorites-btn"
                aria-label="<?= $h(__('panel.favorites')) ?>" data-sp-action="favorites">
            <i class="sp-button-icon-bc bc-i-favorite"></i>
            <span class="sp-tooltip"><?= $h(__('panel.favorites')) ?></span>
        </button>
        <a class="sp-button-bc" href="<?= !empty($loggedIn) ? '/profile/bonus-spor' : '/promotions' ?>" data-nav-mode="<?= !empty($loggedIn) ? 'modal' : 'page' ?>" title="<?= $h(__('nav.bonuses')) ?>" aria-label="<?= $h(__('nav.bonuses')) ?>">
            <i class="sp-button-icon-bc bc-i-promotions-3"></i>
            <span class="sp-tooltip"><?= $h(__('nav.bonuses')) ?></span>
        </a>
        <button class="sp-button-bc" type="button" title="<?= $h(__('panel.settings')) ?>" id="smart-panel-settings-btn"
                aria-label="<?= $h(__('panel.settings')) ?>" data-sp-action="settings">
            <i class="sp-button-icon-bc bc-i-settings"></i>
            <span class="sp-tooltip"><?= $h(__('panel.settings')) ?></span>
        </button>
        <button class="sp-button-bc" type="button" title="<?= $h(__('nav.live_support')) ?>" data-live-chat
                onclick='window.open(<?= htmlspecialchars(json_encode((string) ($siteContactLinks["live_support_url"] ?? (defined("LIVE_SUPPORT_URL") ? LIVE_SUPPORT_URL : "")), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>,"_blank")'>
            <i class="sp-button-icon-bc bc-i-live-chat"></i>
            <span class="sp-tooltip"><?= $h(__('nav.live_support')) ?></span>
        </button>
    </div>
</aside>

<!-- Right sidebar (ortak overlay; bildirim ve favoriler aynı yapıyı kullanır) -->
<div class="right-sidebar-overlay" id="rightSidebarOverlay" aria-hidden="true"></div>

<!-- Bildirimler sidebar -->
<aside class="right-sidebar" id="notificationDrawer" data-right-sidebar="notification" role="dialog" aria-label="<?= $h(__('panel.notifications')) ?>" aria-hidden="true">
    <div class="right-sidebar__header">
        <button type="button" class="right-sidebar__close" data-right-sidebar-close aria-label="<?= $h(__('auth.close')) ?>">&times;</button>
        <?php
        $notificationDrawerSite = (string) ($headerSiteName ?? $footerSiteName ?? $ayar['site_adi'] ?? 'SITE');
        $notificationDrawerSiteUpper = function_exists('mb_strtoupper')
            ? mb_strtoupper($notificationDrawerSite, 'UTF-8')
            : strtoupper($notificationDrawerSite);
        ?>
        <h2 class="right-sidebar__title"><?= $h(__('panel.news', ['site' => $notificationDrawerSiteUpper])) ?></h2>
    </div>
    <div class="notification-drawer__toolbar">
        <span class="notification-drawer__date" id="notificationDrawerDate"></span>
        <button type="button" class="notification-drawer__clear" id="notificationDrawerClear"><?= $h(__('panel.clear')) ?></button>
    </div>
    <div class="notification-drawer__list" id="notificationDrawerList" data-announcements-url="/api/v2/announcements" data-notifications-url="/api/v2/notifications" aria-live="polite"></div>
</aside>

<!-- Favoriler sidebar -->
<aside class="right-sidebar" id="favoritesDrawer" data-right-sidebar="favorites" role="dialog" aria-label="<?= $h(__('panel.favorites')) ?>" aria-hidden="true">
    <div class="right-sidebar__header">
        <button type="button" class="right-sidebar__close" data-right-sidebar-close aria-label="<?= $h(__('auth.close')) ?>">&times;</button>
        <h2 class="right-sidebar__title"><?= $h(__('panel.favorites_title')) ?></h2>
    </div>
    <p class="favorites-sidebar__guest-msg" id="favoritesGuestMsg" hidden><?= $h(__('panel.favorites_login')) ?></p>
    <div class="favorites-sidebar__tabs">
        <button type="button" class="favorites-sidebar__tab is-active" data-favorites-tab="sport"><?= $h(__('panel.favorites_sport')) ?> <span class="favorites-sidebar__count" data-favorites-count="sport">(0)</span></button>
        <button type="button" class="favorites-sidebar__tab" data-favorites-tab="slot"><?= $h(__('panel.favorites_slot')) ?> <span class="favorites-sidebar__count" data-favorites-count="slot">(0)</span></button>
        <button type="button" class="favorites-sidebar__tab" data-favorites-tab="live"><?= $h(__('panel.favorites_live')) ?> <span class="favorites-sidebar__count" data-favorites-count="live">(0)</span></button>
        <button type="button" class="favorites-sidebar__tab" data-favorites-tab="competition"><?= $h(__('panel.favorites_competition')) ?> <span class="favorites-sidebar__count" data-favorites-count="competition">(0)</span></button>
        <button type="button" class="favorites-sidebar__tab" data-favorites-tab="match"><?= $h(__('panel.favorites_match')) ?> <span class="favorites-sidebar__count" data-favorites-count="match">(0)</span></button>
    </div>
    <div class="favorites-sidebar__body" id="favoritesSidebarBody">
        <div class="favorites-sidebar__pane is-active" data-favorites-pane="sport"></div>
        <div class="favorites-sidebar__pane" data-favorites-pane="slot" hidden>
            <p class="favorites-sidebar__loading favorites-sidebar__msg" id="favoritesSlotLoading" hidden><?= $h(__('common.loading')) ?></p>
            <p class="favorites-sidebar__error favorites-sidebar__msg" id="favoritesSlotError" hidden></p>
            <ul class="favorites-game-list" id="favoritesSlotList" aria-label="<?= $h(__('panel.favorites_slot')) ?>"></ul>
            <p class="favorites-sidebar__empty favorites-sidebar__empty--tab" id="favoritesSlotEmpty" hidden><?= $h(__('panel.favorites_slot_empty')) ?></p>
        </div>
        <div class="favorites-sidebar__pane" data-favorites-pane="live" hidden>
            <p class="favorites-sidebar__loading favorites-sidebar__msg" id="favoritesLiveLoading" hidden><?= $h(__('common.loading')) ?></p>
            <p class="favorites-sidebar__error favorites-sidebar__msg" id="favoritesLiveError" hidden></p>
            <ul class="favorites-game-list" id="favoritesLiveList" aria-label="<?= $h(__('menu.live_casino')) ?>"></ul>
            <p class="favorites-sidebar__empty favorites-sidebar__empty--tab" id="favoritesLiveEmpty" hidden><?= $h(__('panel.favorites_live_empty')) ?></p>
        </div>
        <div class="favorites-sidebar__pane" data-favorites-pane="competition" hidden></div>
        <div class="favorites-sidebar__pane" data-favorites-pane="match" hidden></div>
    </div>
</aside>

<!-- Ayarlar sidebar -->
<aside class="right-sidebar" id="settingsDrawer" data-right-sidebar="settings" role="dialog" aria-label="<?= $h(__('panel.settings')) ?>" aria-hidden="true">
    <div class="right-sidebar__header">
        <button type="button" class="right-sidebar__close" data-right-sidebar-close aria-label="<?= $h(__('auth.close')) ?>">&times;</button>
        <h2 class="right-sidebar__title"><?= $h(__('panel.settings_title')) ?></h2>
    </div>
    <div class="right-sidebar__body settings-sidebar__body">
        <div class="settings-sidebar__field" data-settings-field="odds">
            <span class="settings-sidebar__label"><?= $h(__('panel.odds_format')) ?></span>
            <button type="button" class="settings-sidebar__select" aria-expanded="false" aria-haspopup="listbox" aria-label="<?= $h(__('panel.odds_format_select')) ?>">
                <span class="settings-sidebar__value" data-settings-value="odds"><?= $h(__('panel.odds_decimal')) ?></span>
                <i class="settings-sidebar__chevron fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="settings-sidebar__options" role="listbox" hidden>
                <button type="button" class="settings-sidebar__option" role="option" data-value="<?= $h(__('panel.odds_decimal')) ?>"><?= $h(__('panel.odds_decimal')) ?></button>
                <button type="button" class="settings-sidebar__option" role="option" data-value="<?= $h(__('panel.odds_fractional')) ?>"><?= $h(__('panel.odds_fractional')) ?></button>
                <button type="button" class="settings-sidebar__option" role="option" data-value="<?= $h(__('panel.odds_american')) ?>"><?= $h(__('panel.odds_american')) ?></button>
                <button type="button" class="settings-sidebar__option" role="option" data-value="HongKong">HongKong</button>
                <button type="button" class="settings-sidebar__option" role="option" data-value="Malay">Malay</button>
                <button type="button" class="settings-sidebar__option" role="option" data-value="Indo">Indo</button>
            </div>
        </div>
        <div class="settings-sidebar__field" data-settings-field="language">
            <?php
            $settingsLocale = function_exists('current_locale') ? current_locale() : 'tr';
            $settingsLangFlag = [
                'tr' => 'flag-icon-tr',
                'en' => 'flag-icon-us',
                'de' => 'flag-icon-de',
                'ru' => 'flag-icon-ru',
            ];
            $settingsLangName = [
                'tr' => __('lang.name_tr'),
                'en' => __('lang.name_en'),
                'de' => __('lang.name_de'),
                'ru' => __('lang.name_ru'),
            ];
            $settingsCurrentFlag = $settingsLangFlag[$settingsLocale] ?? 'flag-icon-tr';
            $settingsCurrentName = $settingsLangName[$settingsLocale] ?? __('lang.name_tr');
            ?>
            <span class="settings-sidebar__label"><?= $h(__('settings.language')) ?></span>
            <button type="button" class="settings-sidebar__select" aria-expanded="false" aria-haspopup="listbox" aria-label="<?= $h(__('settings.language_select')) ?>">
                <span class="settings-sidebar__value settings-sidebar__value--with-icon" data-settings-value="language">
                    <span class="flag-icon <?= $h($settingsCurrentFlag) ?>" aria-hidden="true"></span>
                    <?= $h($settingsCurrentName) ?>
                </span>
                <i class="settings-sidebar__chevron fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="settings-sidebar__options" role="listbox" hidden>
                <button type="button" class="settings-sidebar__option settings-sidebar__option--with-icon<?= $settingsLocale === 'tr' ? ' is-selected' : '' ?>" role="option" data-value="tr" data-lang="tr" data-i18n-href="<?= $h(i18n_switch_url('tr')) ?>"><span class="flag-icon flag-icon-tr"></span> <?= $h(__('lang.name_tr')) ?></button>
                <button type="button" class="settings-sidebar__option settings-sidebar__option--with-icon<?= $settingsLocale === 'en' ? ' is-selected' : '' ?>" role="option" data-value="en" data-lang="en" data-i18n-href="<?= $h(i18n_switch_url('en')) ?>"><span class="flag-icon flag-icon-us"></span> <?= $h(__('lang.name_en')) ?></button>
                <button type="button" class="settings-sidebar__option settings-sidebar__option--with-icon<?= $settingsLocale === 'de' ? ' is-selected' : '' ?>" role="option" data-value="de" data-lang="de" data-i18n-href="<?= $h(i18n_switch_url('de')) ?>"><span class="flag-icon flag-icon-de"></span> <?= $h(__('lang.name_de')) ?></button>
                <button type="button" class="settings-sidebar__option settings-sidebar__option--with-icon<?= $settingsLocale === 'ru' ? ' is-selected' : '' ?>" role="option" data-value="ru" data-lang="ru" data-i18n-href="<?= $h(i18n_switch_url('ru')) ?>"><span class="flag-icon flag-icon-ru"></span> <?= $h(__('lang.name_ru')) ?></button>
            </div>
        </div>
        <div class="settings-sidebar__field" data-settings-field="timeformat">
            <span class="settings-sidebar__label"><?= $h(__('panel.time_format')) ?></span>
            <button type="button" class="settings-sidebar__select" aria-expanded="false" aria-haspopup="listbox" aria-label="<?= $h(__('panel.time_format_select')) ?>">
                <span class="settings-sidebar__value" data-settings-value="timeformat"><?= $h(__('panel.time_24')) ?></span>
                <i class="settings-sidebar__chevron fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="settings-sidebar__options" role="listbox" hidden>
                <button type="button" class="settings-sidebar__option" role="option" data-value="<?= $h(__('panel.time_12')) ?>"><?= $h(__('panel.time_12')) ?></button>
                <button type="button" class="settings-sidebar__option" role="option" data-value="<?= $h(__('panel.time_24')) ?>"><?= $h(__('panel.time_24')) ?></button>
            </div>
        </div>
    </div>
</aside>

<!-- Oyun arama sidebar -->
<aside class="right-sidebar" id="searchDrawer" data-right-sidebar="search" role="dialog" aria-label="<?= $h(__('panel.game_search')) ?>" aria-hidden="true">
    <div class="right-sidebar__header">
        <button type="button" class="right-sidebar__close" data-right-sidebar-close aria-label="<?= $h(__('auth.close')) ?>">&times;</button>
        <h2 class="right-sidebar__title"><?= $h(__('panel.game_search')) ?></h2>
    </div>
    <div class="right-sidebar__body">
        <div class="game-search-bar right-sidebar-search-bar">
            <input type="text" class="game-search-input" placeholder="<?= $h(__('panel.game_search_placeholder')) ?>" id="searchModalInput" value="">
            <button type="button" class="game-search-btn games-search-icon-btn" id="searchClearBtn" title="<?= $h(__('panel.clear_search')) ?>" aria-label="<?= $h(__('panel.clear_search')) ?>"><i class="fas fa-search" id="searchClearBtnIcon" aria-hidden="true"></i></button>
        </div>
    </div>
</aside>

<!-- Kupon / Açık Bahisler paneli -->
<div class="betslip-panel-overlay" id="betslipPanelOverlay" aria-hidden="true"></div>
<div class="betslip-panel" id="betslipPanel" role="dialog" aria-label="<?= $h(__('panel.betslip')) ?>" aria-hidden="true">
    <button type="button" class="betslip-panel__close" id="betslipPanelClose" aria-label="<?= $h(__('auth.close')) ?>">&times;</button>
    <div class="betslip-panel__tabs">
        <button type="button" class="betslip-panel__tab" data-tab="kupon" aria-selected="true"><?= $h(__('panel.betslip_coupon')) ?></button>
        <button type="button" class="betslip-panel__tab" data-tab="acik-bahisler" aria-selected="false"><?= $h(__('panel.betslip_open')) ?></button>
    </div>
    <button type="button" class="betslip-panel__settings" id="betslipPanelSettings" aria-haspopup="listbox" aria-expanded="false" aria-label="<?= $h(__('panel.odds_pref')) ?>">
        <i class="betslip-panel__settings-icon bc-i-settings" aria-hidden="true"></i>
        <span class="betslip-panel__settings-text" id="betslipOddsPrefLabel"><?= $h(__('panel.odds_always_ask')) ?></span>
        <i class="betslip-panel__settings-chevron fa-solid fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="betslip-panel__body">
        <div class="betslip-panel__pane" data-pane="kupon">
            <p class="betslip-panel__empty"><?= $h(__('panel.betslip_empty')) ?></p>
        </div>
        <div class="betslip-panel__pane" data-pane="acik-bahisler" hidden>
            <a href="/profile/bet-history" class="betslip-panel__history-link" data-nav-mode="modal"><?= $h(__('panel.bet_history_link')) ?></a>
            <p class="betslip-panel__empty"><?= $h(__('panel.betslip_no_open')) ?></p>
        </div>
    </div>
    <?php if (empty($loggedIn) && defined('SURFACE') && SURFACE === 'mobile'): ?>
    <div class="betslip-panel__auth-warning" role="status">
        <span class="betslip-panel__auth-warning-icon" aria-hidden="true"><i class="fa-solid fa-exclamation-triangle"></i></span>
        <p class="betslip-panel__auth-warning-text"><?php
            $authLogin = '<a href="#" class="betslip-panel__auth-link" id="betslipAuthLoginLink">' . $h(__('nav.login')) . '</a>';
            $authRegister = '<a href="#" class="betslip-panel__auth-link" id="betslipAuthRegisterLink">' . $h(__('nav.register')) . '</a>';
            echo str_replace([':login', ':register'], [$authLogin, $authRegister], $h(__('panel.betslip_auth')));
        ?></p>
    </div>
    <?php endif; ?>
    <div class="betslip-panel__footer">
        <div class="betslip-panel__amounts">
            <button type="button" class="betslip-panel__amount-btn">50</button>
            <button type="button" class="betslip-panel__amount-btn">100</button>
            <button type="button" class="betslip-panel__amount-btn">500</button>
            <button type="button" class="betslip-panel__edit-btn" aria-label="<?= $h(__('panel.edit_amount')) ?>"><i class="fa-solid fa-pencil"></i></button>
        </div>
        <button type="button" class="betslip-panel__place-btn" disabled><?= $h(__('panel.place_bet')) ?></button>
    </div>
</div>

<!-- Mobil: oran tercihi tam ekran -->
<div class="betslip-odds-fullpage" id="betslipOddsFullpage" aria-hidden="true" hidden>
    <div class="betslip-odds-fullpage__inner" role="dialog" aria-modal="true" aria-label="<?= $h(__('panel.odds_pref')) ?>">
        <button type="button" class="betslip-odds-fullpage__bar" id="betslipOddsFullpageCloseBar" aria-expanded="true">
            <i class="betslip-panel__settings-icon bc-i-settings" aria-hidden="true"></i>
            <span class="betslip-odds-fullpage__bar-text" id="betslipOddsFullpageBarText"><?= $h(__('panel.odds_always_ask')) ?></span>
            <i class="fa-solid fa-chevron-up betslip-odds-fullpage__chev" aria-hidden="true"></i>
        </button>
        <ul class="betslip-odds-fullpage__list" id="betslipOddsFullpageList" role="listbox" aria-label="<?= $h(__('panel.odds_pref')) ?>">
            <li role="none"><button type="button" class="betslip-odds-fullpage__option is-selected" role="option" data-odds-pref="always_ask" data-label="<?= $h(__('panel.odds_always_ask')) ?>"><?= $h(__('panel.odds_always_ask')) ?></button></li>
            <li role="none"><button type="button" class="betslip-odds-fullpage__option" role="option" data-odds-pref="higher" data-label="<?= $h(__('panel.odds_higher')) ?>"><?= $h(__('panel.odds_higher')) ?></button></li>
            <li role="none"><button type="button" class="betslip-odds-fullpage__option" role="option" data-odds-pref="accept" data-label="<?= $h(__('panel.odds_accept')) ?>"><?= $h(__('panel.odds_accept')) ?></button></li>
        </ul>
    </div>
</div>

<!-- Para yatırma / ödeme uyarı-hata diyaloğu -->
<div class="app-feedback-dialog-overlay" id="appFeedbackDialogOverlay" aria-hidden="true"></div>
<div class="app-feedback-dialog" id="appFeedbackDialog" role="alertdialog" aria-modal="true" aria-hidden="true" aria-labelledby="appFeedbackDialogTitle">
    <div class="app-feedback-dialog__card">
        <button type="button" class="app-feedback-dialog__dismiss" id="appFeedbackDialogDismiss" aria-label="<?= $h(__('auth.close')) ?>">&times;</button>
        <div class="app-feedback-dialog__icon-wrap" id="appFeedbackDialogIconWrap" aria-hidden="true"></div>
        <h2 class="app-feedback-dialog__title" id="appFeedbackDialogTitle"></h2>
        <p class="app-feedback-dialog__message" id="appFeedbackDialogMessage"></p>
        <button type="button" class="app-feedback-dialog__primary" id="appFeedbackDialogOk"><?= $h(__('common.ok')) ?></button>
    </div>
</div>

<!-- CM622 profil popup kabuğu -->
<div class="profile-modal-overlay" id="profileModalOverlay" aria-hidden="true"></div>
<div class="popup-holder-bc windowed user-profile-container" id="profileModal" role="dialog" aria-label="<?= $h(__('panel.profile')) ?>" aria-modal="true" aria-hidden="true">
    <div class="popup-middleware-bc">
        <div id="base_popup_id" class="popup-inner-bc">
            <i id="close_popup_button_id" class="e-p-close-icon-bc bc-i-close-remove" data-profile-modal-close="1" role="button" tabindex="0" aria-label="<?= $h(__('auth.close')) ?>"></i>
            <div class="cm622-profile-loading is-hidden" id="profileModalLoading" aria-hidden="true">
                <span class="cm622-profile-spinner"></span>
                <span class="cm622-profile-loading-text"><?= $h(__('panel.loading_dots')) ?></span>
            </div>
            <div class="u-i-p-c-body-bc" id="profileModalContent"></div>
        </div>
    </div>
</div>

<!-- Arama paneli (sağdan açılır) -->
<div class="search-overlay" id="searchOverlay" aria-hidden="true"></div>
<aside class="search-panel" id="searchPanel" role="dialog" aria-label="<?= $h(__('panel.search')) ?>" aria-hidden="true">
    <button type="button" class="search-panel__toggle-tab" id="searchPanelClose" aria-label="<?= $h(__('auth.close')) ?>" title="<?= $h(__('auth.close')) ?>">
        <i class="bc-i-small-arrow-right" aria-hidden="true"></i>
    </button>
    <div class="search-panel__inner">
        <div class="search-panel__input-wrap">
            <input type="text" class="search-panel__input" id="searchPanelInput" placeholder="<?= $h(__('panel.search_sport')) ?>" autocomplete="off" aria-label="<?= $h(__('panel.search')) ?>">
            <i class="fa-solid fa-search search-panel__input-icon" aria-hidden="true"></i>
        </div>
        <div class="search-panel__filters">
            <button type="button" class="search-panel__filter is-active" data-filter="sport"><?= $h(__('menu.sports')) ?></button>
            <button type="button" class="search-panel__filter" data-filter="casino"><?= $h(__('menu.casino')) ?></button>
            <button type="button" class="search-panel__filter" data-filter="livecasino"><?= $h(__('menu.live_casino')) ?></button>
        </div>
        <div class="search-panel__body" id="searchPanelBody">
            <p class="search-panel__empty"><?= $h(__('panel.search_hint')) ?></p>
        </div>
    </div>
</aside>
