<?php
$providerBadges = $providerBadges ?? [];
$loggedIn = $loggedIn ?? false;
$currentPage = $currentPage ?? $page ?? 1;
$nextPage = $nextPage ?? ($currentPage + 1);
$perPage = $perPage ?? $limit ?? 30;
$slotPageBaseUrl = isset($slotPageBaseUrl) ? (string) $slotPageBaseUrl : '/slot';
$slotPageTitle = isset($slotPageTitle) ? (string) $slotPageTitle : 'OYUNLAR';
$slotEmptyTitle = isset($slotEmptyTitle) ? (string) $slotEmptyTitle : 'Slot oyunu bulunamadı';
$slotEmptyText = isset($slotEmptyText) ? (string) $slotEmptyText : 'Arama teriminizi değiştirmeyi veya filtreleri temizlemeyi deneyin';
$slotApiParams = isset($slotApiParams) && is_array($slotApiParams) ? $slotApiParams : [];
$searchTerm = isset($searchTerm) ? (string) $searchTerm : '';
$selectedProviders = isset($selectedProviders) && is_array($selectedProviders) ? $selectedProviders : [];
$currentSort = isset($currentSort) ? (string) $currentSort : '';
$allUniqueProviders = isset($allUniqueProviders) && is_array($allUniqueProviders) ? $allUniqueProviders : [];
$remainingGames = isset($remainingGames) ? (int) $remainingGames : 0;
$showLoadMore = !empty($showLoadMore);
$slotGameType = isset($slotGameType) ? (int) $slotGameType : 0;
$slotShowActionButtons = !empty($slotShowActionButtons);
$slotHideProviders = !empty($slotHideProviders);
$apiError = !empty($apiError ?? false);
$slotJsPath = BASE_PATH . '/assets/js/slot.js';
$slotJsVer = (string) ((is_file($slotJsPath) ? filemtime($slotJsPath) : time()) . '-' . (is_file($slotJsPath) ? filesize($slotJsPath) : '0'));
$slotFavoriteKind = $slotGameType === 1 ? 'live' : ((($slotApiParams['source'] ?? '') === 'bgaming') ? 'bgaming' : 'slot');

$slotPlayTarget = static function (array $game) use ($loggedIn): string {
    $gid = (string) ($game['game_id'] ?? '');
    $play = '/play?game_id=' . rawurlencode($gid) . '&mode=real&wallet=main';
    if ($loggedIn) {
        return $play;
    }
    return '#login';
};
$slotDemoHref = static function (array $game): string {
    $gid = (string) ($game['game_id'] ?? '');

    return '/play?game_id=' . rawurlencode($gid) . '&mode=fun';
};

/** @return string BEM modifier sınıfı (provider-badge--jackpot vb.) */
$providerBadgeModifierClass = static function (string $badge): string {
    $n = strtoupper(preg_replace('/\s+/u', ' ', trim($badge)));
    $n = str_replace(['İ', 'ı'], ['I', 'I'], $n);
    if (strpos($n, 'EN') !== false && strpos($n, 'IYI') !== false) {
        return 'provider-badge--best';
    }
    if ($n === 'JACKPOT') {
        return 'provider-badge--jackpot';
    }
    if ($n === 'SICAK') {
        return 'provider-badge-hot';
    }
    if ($n === 'PROMOSYON') {
        return 'provider-badge--promo';
    }
    if ($n === 'OZEL' || $n === 'ÖZEL') {
        return 'provider-badge--special';
    }
    if ($n === 'YENI' || $n === 'YENİ') {
        return 'provider-badge-new';
    }
    return 'provider-badge--default';
};

$providerBadgeBlockClass = static function (string $badge): string {
    $n = strtoupper(preg_replace('/\s+/u', ' ', trim($badge)));
    $n = str_replace(['İ', 'ı'], ['I', 'I'], $n);
    if (strpos($n, 'EN') !== false && strpos($n, 'IYI') !== false) {
        return 'badge-top';
    }
    if ($n === 'JACKPOT') {
        return 'badge-jackpot';
    }
    if ($n === 'SICAK') {
        return 'badge-hot';
    }
    if ($n === 'PROMOSYON') {
        return 'badge-promo';
    }
    if ($n === 'OZEL' || $n === 'ÖZEL') {
        return 'badge-exclusive';
    }
    if ($n === 'ORTAK') {
        return 'badge-ortak';
    }
    return '';
};

$slotCategoryItems = [
    ['sort' => '', 'slug' => 'all-games1', 'id' => '-1', 'title' => 'Tüm Oyunlar', 'icon' => 'bc-i-all-games1', 'href' => $slotPageBaseUrl],
    ['sort' => 'liked', 'slug' => 'topslots', 'id' => '93', 'title' => 'En Beğenilen Oyunlar', 'icon' => 'bc-i-topslots', 'href' => $slotPageBaseUrl . '?sort=liked'],
    ['sort' => 'popular', 'slug' => 'populargames', 'id' => '95', 'title' => 'Popüler Oyunlar', 'icon' => 'bc-i-populargames', 'href' => $slotPageBaseUrl . '?sort=popular'],
    ['sort' => 'new', 'slug' => 'new', 'id' => '65', 'title' => 'Yeni Oyunlar', 'icon' => 'bc-i-new', 'href' => $slotPageBaseUrl . '?sort=new'],
    ['sort' => 'jackpots', 'slug' => 'jackpots', 'id' => '59', 'title' => 'Jackpotlar', 'icon' => 'bc-i-jackpots', 'href' => $slotPageBaseUrl . '?sort=jackpots'],
    ['sort' => 'bonus-buy', 'slug' => 'buybonus', 'id' => '247', 'title' => 'Bonus Satın Alma Oyunları', 'icon' => 'bc-i-buybonus', 'href' => $slotPageBaseUrl . '?sort=bonus-buy'],
    ['sort' => 'video', 'slug' => 'videoslots', 'id' => '51', 'title' => 'Video Slotları', 'icon' => 'bc-i-videoslots', 'href' => $slotPageBaseUrl . '?sort=video'],
    ['sort' => 'special', 'slug' => 'newyear', 'id' => '619', 'title' => 'Yılbaşı Özel', 'icon' => 'bc-i-newyear', 'href' => $slotPageBaseUrl . '?sort=special'],
    ['sort' => 'crash', 'slug' => 'crashgames', 'id' => '406', 'title' => 'Uçak Oyunları', 'icon' => 'bc-i-crashgames', 'href' => $slotPageBaseUrl . '?sort=crash'],
    ['sort' => 'freespin', 'slug' => 'buyfeature', 'id' => '274', 'title' => 'Free Spin Satın Alma Oyunları', 'icon' => 'bc-i-buyfeature', 'href' => $slotPageBaseUrl . '?sort=freespin'],
    ['sort' => 'instant', 'slug' => 'instantwin', 'id' => '46', 'title' => 'Anında Kazanç', 'icon' => 'bc-i-instantwin', 'href' => $slotPageBaseUrl . '?sort=instant'],
    ['sort' => 'table', 'slug' => 'tablegames', 'id' => '94', 'title' => 'Masa Oyunları', 'icon' => 'bc-i-tablegames', 'href' => $slotPageBaseUrl . '?sort=table'],
    ['sort' => 'slots', 'slug' => 'slots', 'id' => '57', 'title' => 'Slots', 'icon' => 'bc-i-slots', 'href' => $slotPageBaseUrl . '?sort=slots'],
];
if (($slotGameType ?? 0) === 1) {
    $slotCategoryItems = [
        ['sort' => '', 'slug' => 'all-games1', 'id' => '-1', 'title' => 'Tüm Oyunlar', 'icon' => 'bc-i-all-games1', 'href' => $slotPageBaseUrl],
        ['sort' => 'popular', 'slug' => 'populargames', 'id' => '95', 'title' => 'Popüler Oyunlar', 'icon' => 'bc-i-populargames', 'href' => $slotPageBaseUrl . '?sort=popular'],
        ['sort' => 'roulette', 'slug' => 'roulette', 'id' => '201', 'title' => 'Rulet', 'icon' => 'bc-i-tablegames', 'href' => $slotPageBaseUrl . '?sort=roulette'],
        ['sort' => 'blackjack', 'slug' => 'blackjack', 'id' => '202', 'title' => 'Blackjack', 'icon' => 'bc-i-tablegames', 'href' => $slotPageBaseUrl . '?sort=blackjack'],
        ['sort' => 'baccarat', 'slug' => 'baccarat', 'id' => '203', 'title' => 'Baccarat', 'icon' => 'bc-i-tablegames', 'href' => $slotPageBaseUrl . '?sort=baccarat'],
        ['sort' => 'game-show', 'slug' => 'game-show', 'id' => '204', 'title' => 'Game Show', 'icon' => 'bc-i-populargames', 'href' => $slotPageBaseUrl . '?sort=game-show'],
    ];
}

$renderProviderBtn = function ($provider) use ($providerBadges, $selectedProviders, $providerBadgeBlockClass) {
    $badgeSlug = ProviderDisplayBadgeMap::slugForDisplay($provider);
    $badges    = $badgeSlug !== null ? array_slice($providerBadges[$badgeSlug] ?? [], 0, 1) : [];
    $active = in_array($provider, $selectedProviders);
    $label = htmlspecialchars($provider);
    $esc = htmlspecialchars($provider, ENT_QUOTES, 'UTF-8');
    $badge = $badges[0] ?? '';
    $badgeClass = $badge !== '' ? $providerBadgeBlockClass($badge) : '';
    return '<div title="' . $esc . '" class="providerItemsInner sidebar-provider-item' . ($active ? ' active' : '') . '" role="button" tabindex="0" data-provider="' . $esc . '"><span class="providerBadgeBlock ' . htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') . '" data-badge="' . htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') . '"></span><div class="providerItemsBtn"><span class="provider-list-row">' . $label . '</span></div></div>';
};

?>
<?php include VIEW_PATH . '/layouts/head.php'; ?>
<?php include VIEW_PATH . '/partials/header.php'; ?>

<div class="slot-page-root slot-page-root--unified" data-slot-game-type="<?= (int) $slotGameType ?>">
<section class="slot-top-section" aria-label="Slot sayfası üst alan">
    <?php $sliderApiCategory = isset($sliderApiCategory) ? (string) $sliderApiCategory : 'slots'; include VIEW_PATH . '/partials/slider.php'; ?>
    <div class="slot-below-hero">
        <div class="slot-hero-tabs" data-slot-hero-tabs>
            <div class="slot-hero-tablist" role="tablist" aria-label="Jackpot ve kazananlar">
                <button type="button"
                        class="slot-hero-tab slot-hero-tab--active"
                        id="slot-hero-tab-jackpot"
                        role="tab"
                        aria-selected="true"
                        aria-controls="slot-hero-panel-jackpot"
                        data-slot-hero-tab="jackpot">JACKPOT</button>
                <button type="button"
                        class="slot-hero-tab"
                        id="slot-hero-tab-winners"
                        role="tab"
                        aria-selected="false"
                        aria-controls="slot-hero-panel-winners"
                        data-slot-hero-tab="winners">KAZANANLAR</button>
            </div>
            <div class="slot-hero-panels">
                <div class="slot-hero-tabpanel slot-hero-tabpanel--active"
                     id="slot-hero-panel-jackpot"
                     role="tabpanel"
                     aria-labelledby="slot-hero-tab-jackpot"
                     data-slot-hero-panel="jackpot">
                    <div class="slot-jackpot-wrap">
                        <?php include VIEW_PATH . '/partials/jackpot.php'; ?>
                    </div>
                </div>
                <div class="slot-hero-tabpanel"
                     id="slot-hero-panel-winners"
                     role="tabpanel"
                     aria-labelledby="slot-hero-tab-winners"
                     data-slot-hero-panel="winners"
                     hidden>
                    <div class="slot-winners-wrap">
                        <?php include VIEW_PATH . '/partials/winners.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($apiError): ?>
<div class="alert alert-warning mx-3 my-2" role="alert">
    Oyun listesi şu an yüklenemedi. Backend bağlantısını kontrol edin (<code>API_BACKEND_MAIN_BASE_URL</code>).
</div>
<?php endif; ?>

<div class="casino-container">
    <div class="casinoProviderContent slots-filter-and-games" id="slotsFilterAndGames">
        <div class="casinoCategoryChooserContainer slots-category-filters category-tabs-wrapper" id="slotsCategoryFilters">
        <div tabindex="0" class="horizontalSliderWrapper horizontalItemsExpanded scroll-start">
        <i class="horizontalSliderNav bc-i-small-arrow-left cat-tab-arrow cat-tab-arrow-left" id="catArrowLeft"></i>
        <div class="horizontalSliderRow category-tabs-scroll" id="categoryTabsScroll" style="transform: translateX(0px);">
            <?php foreach ($slotCategoryItems as $category): ?>
                <?php $isActive = $category['sort'] === $currentSort || ($category['sort'] === '' && $currentSort === ''); ?>
                <a href="<?= htmlspecialchars($category['href'], ENT_QUOTES, 'UTF-8') ?>"
                   class="horizontalCategoryItemWrp <?= $isActive ? 'active ' : '' ?><?= htmlspecialchars($category['slug'], ENT_QUOTES, 'UTF-8') ?> cat-tab<?= $isActive ? ' active' : '' ?>"
                   data-id="<?= htmlspecialchars($category['id'], ENT_QUOTES, 'UTF-8') ?>"
                   data-sort="<?= htmlspecialchars($category['sort'], ENT_QUOTES, 'UTF-8') ?>">
                    <div data-id="<?= htmlspecialchars($category['id'], ENT_QUOTES, 'UTF-8') ?>"
                         title="<?= htmlspecialchars($category['title'], ENT_QUOTES, 'UTF-8') ?>"
                         data-badge=""
                         class="horizontalCategoryItem">
                        <i class="bc-i-default-icon <?= htmlspecialchars($category['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                        <div class="horCatItemTitleWrp"><p class="horCatItemTitle"><?= htmlspecialchars($category['title'], ENT_QUOTES, 'UTF-8') ?></p></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <i class="horizontalSliderNav bc-i-small-arrow-right cat-tab-arrow cat-tab-arrow-right" id="catArrowRight"></i>
        </div>
        </div>

        <div class="casinoProviderRow<?= $slotHideProviders ? ' casinoProviderRow--no-providers' : '' ?>">
            <?php if (!$slotHideProviders): ?>
            <p class="casinoProviderBlockTitle" id="lineSidebarToggle" title="Sağlayıcıları aç/kapat"><i class="casinoProviderBlockIcon bc-i-small-arrow-left" id="lineSidebarToggleIcon"></i><span id="lineSidebarToggleLabel">Sağlayıcılar</span></p>
            <?php endif; ?>
            <p class="casinoGameListTitle"><?= htmlspecialchars($slotGameType === 1 ? 'CANLI CASINO OYUNLARI' : 'CASINO OYUNLARI', ENT_QUOTES, 'UTF-8') ?></p>
            <a class="casinoGameListAllLink" href="<?= htmlspecialchars($slotPageBaseUrl, ENT_QUOTES, 'UTF-8') ?>">TÜMÜ</a>
        </div>

        <div class="casinoProviderAndGame slots-layout<?= $slotHideProviders ? ' slots-layout--full-games' : '' ?>" id="slotsLayout">
            <?php if (!$slotHideProviders): ?>
            <div class="casinoProviderBlock providers-sidebar" id="providersSidebar">
            <div class="casinoProviderBlockHolder provider-sheet">
                <div class="provider-sheet-header">
                    <button type="button" class="provider-sheet-back" id="providerSheetBackBtn" aria-label="Geri">
                        <i class="fas fa-chevron-left" aria-hidden="true"></i> GERİ
                    </button>
                </div>
                <div class="providerSearchAndReset provider-sheet-tools">
                    <div class="providerSearchRow sidebar-search provider-search-bar provider-sheet-search">
                        <div class="searchInputWrp active">
                        <input type="text" placeholder="Sağlayıcı Ara" id="providerSearchInput" class="searchInput provider-search-input" autocomplete="off">
                        <p class="searchInputIcon bc-i-search provider-search-btn" id="providerSearchClearBtn" title="Sağlayıcı ara" aria-label="Sağlayıcı ara"><i id="providerSearchClearBtnIcon" aria-hidden="true"></i></p>
                        </div>
                    </div>
                    <div class="providerResetRow"><p class="providerCountTxt" title=""></p><div class="providerTypeIconWrp"><div class="tooltipIconWrapper"><i class="bc-i-view-list provider-sheet-grid-btn" id="providerSheetGridBtn" title="Modül görünümü" aria-label="Modül görünümü"></i></div></div></div>
                </div>
                <div class="providerItemsContainer sidebar-providers-list" id="sidebarProvidersList" data-scroll-lock-scrollable="">
                    <div class="providerItemsHolder module">
                    <div title="Tümü" class="providerItemsInner sidebar-provider-item <?= empty($selectedProviders) && empty($searchTerm) ? 'active' : '' ?>" role="button" tabindex="0" data-provider-all="1"><span class="providerBadgeBlock " data-badge=""></span><div class="providerItemsBtn">TÜMÜ</div></div>
                    <?php
                    foreach ($allUniqueProviders as $provider) {
                        echo $renderProviderBtn($provider);
                    }
                    ?>
                    </div>
                </div>
                <div class="provider-sheet-footer">
                    <button type="button" class="provider-sheet-apply" id="providerSheetApplyBtn">UYGULA</button>
                </div>
            </div>
        </div>
        <?php endif; ?>

            <div class="casinoGameListBlock games-main" id="gamesScrollContainer">
                <div class="casinoGameListBlockHeader">
                    <div class="casinoTitleSearch ">
                        <button type="button" class="mobile-sidebar-toggle" id="mobileSidebarToggle" title="Tüm Sağlayıcılar" aria-label="Tüm sağlayıcıları aç">
                            <span class="mobile-sidebar-toggle__pill">
                                <i class="fas fa-filter" aria-hidden="true"></i>
                                <span class="mobile-sidebar-toggle__pill-text">Sağlayıcılar</span>
                            </span>
                            <span class="mobile-sidebar-toggle__count" id="mobileSidebarToggleCount" aria-hidden="true"></span>
                        </button>
                        <div class="selectedProviderBlock"></div>
                        <div class="games-search-expand" id="gamesSearchExpand" aria-expanded="false">
                            <div class="casinoInputWrp">
                                <div class="searchInputWrp active games-search-bar">
                                    <input type="text"
                                           class="searchInput games-search-input"
                                           placeholder="Oyun Ara"
                                           id="searchModalInput"
                                           value="<?= htmlspecialchars($searchTerm, ENT_QUOTES); ?>">
                                    <p class="searchInputIcon bc-i-search games-search-icon-btn" id="searchClearBtn" title="Aramayı temizle" aria-label="Aramayı temizle"></p>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="random-game-btn" id="randomGameBtn" title="Rastgele Oyun Oyna" aria-label="Rastgele Oyun Oyna">Rastgele Oyun Oyna</button>
                        <div class="tooltipIconWrapper">
                            <button class="bc-i-sort iconButtonBlock" type="button" id="sortToggleBtn" title="Sıralama" aria-label="Sıralama"></button>
                        </div>
                    </div>
                    <div class="active-filters-row" style="display:none;">
                        <div id="active-filters-box" class="active-filters-box" style="display:none;"></div>
                    </div>
                </div>

                <div class="casinoProviderRow casinoProviderRow--mobile-inline<?= $slotHideProviders ? ' casinoProviderRow--no-providers' : '' ?>">
                    <p class="casinoGameListTitle"><?= htmlspecialchars($slotGameType === 1 ? 'CANLI CASINO OYUNLARI' : 'CASINO OYUNLARI', ENT_QUOTES, 'UTF-8') ?></p>
                    <a class="casinoGameListAllLink" href="<?= htmlspecialchars($slotPageBaseUrl, ENT_QUOTES, 'UTF-8') ?>">TÜMÜ</a>
                </div>

                <div class="casinoGameItemWrp slot-oyun-listesi slots-games-container" id="casino_games_container" aria-label="Oyun listesi">
                    <div class="casinoCategoryGames">
                <?php if (empty($games)): ?>
                    <div class="empty-state">
                        <i class="fas fa-gamepad"></i>
                        <h3><?= htmlspecialchars($slotEmptyTitle, ENT_QUOTES, 'UTF-8') ?></h3>
                        <p><?= htmlspecialchars($slotEmptyText, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($games as $game): ?>
                        <?php
                        $playHref = $slotPlayTarget($game);
                        $demoHref = $slotDemoHref($game);
                        $playHrefJson = htmlspecialchars(json_encode($playHref, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                        // Play navigation is routed through MaltabetWalletPicker (main/bonus wallet choice modal)
                        // when available, falling back to direct navigation otherwise.
                        $playNavJs = 'var u=' . $playHrefJson . ';function __navPlay(nu){window.location.href=nu;}if(window.MaltabetWalletPicker&&typeof window.MaltabetWalletPicker.launch===&quot;function&quot;){window.MaltabetWalletPicker.launch(u,__navPlay);}else{__navPlay(u);}';
                        $openLoginJs = 'if (typeof window.__openLoginModal === &quot;function&quot;) { window.__openLoginModal(); } else { var loginBtn = document.getElementById(&quot;Giris&quot;); if (loginBtn) loginBtn.click(); }';
                        ?>
                        <div class="casinoGameItemContent " data-favorite-kind="<?= htmlspecialchars($slotFavoriteKind, ENT_QUOTES, 'UTF-8') ?>" data-catalog-id="<?= htmlspecialchars((string)($game['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-game-id="<?= htmlspecialchars((string)($game['game_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" onclick="<?= $loggedIn ? $playNavJs : $openLoginJs ?>">
                            <span class="providerBadgeBlock " data-badge=""></span>
                            <div class="casinoGameItem ">
                                <img alt="<?= htmlspecialchars($game['game_name'], ENT_QUOTES); ?>"
                                     loading="eager"
                                     src="<?= htmlspecialchars($game['cover'], ENT_QUOTES); ?>"
                                     data-src="<?= htmlspecialchars($game['cover'], ENT_QUOTES); ?>"
                                     class="casinoGameItemImage"
                                     title="<?= htmlspecialchars($game['game_name'], ENT_QUOTES); ?>"
                                     style="aspect-ratio: 44 / 31;"
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDMwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjMwMCIgaGVpZ2h0PSIyMDAiIHJ4PSI4IiBmaWxsPSIjMWExMTJlIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGRvbWluYW50LWJhc2VsaW5lPSJtaWRkbGUiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZpbGw9IiM2NjYiIGZvbnQtZmFtaWx5PSJzYW5zLXNlcmlmIiBmb250LXNpemU9IjE0Ij5ObyBJbWFnZTwvdGV4dD48L3N2Zz4='">
                                <i class="casinoGameItemFavBc bc-i-favorite "></i>
                                <?php if ($slotShowActionButtons): ?>
                                    <div class="game-overlay">
                                        <div class="game-overlay-top"></div>
                                        <div class="game-title-wrap">
                                            <p class="game-title-text"><?= htmlspecialchars((string) ($game['game_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                        </div>
                                        <div class="game-actions">
                                            <a class="play-btn" href="<?= htmlspecialchars($playHref, ENT_QUOTES, 'UTF-8') ?>" onclick="event.stopPropagation();event.preventDefault();<?= $loggedIn ? $playNavJs : $openLoginJs ?>">OYNA</a>
                                            <a class="demo-btn" href="<?= htmlspecialchars($demoHref, ENT_QUOTES, 'UTF-8') ?>" onclick="event.stopPropagation()">DEMO</a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                    </div>
                    <div id="load-more-sentinel" aria-hidden="true" style="height:1px;min-height:1px;opacity:0;pointer-events:none;overflow:hidden;"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="slot-games-scrollbar-rail" id="slotGamesScrollbarRail" aria-hidden="true">
        <div class="slot-games-scrollbar-thumb" id="slotGamesScrollbarThumb"></div>
    </div>
</div>
</div><!-- .slot-page-root -->

<script>
window.SLOT_CONFIG = {
    apiEndpoint: '/api/v2/games',
    apiAdapter: 'member_api_games',
    gameType: <?= (int) $slotGameType ?>,
    currentPage: <?= (int)$currentPage ?>,
    nextPage: <?= (int)$nextPage ?>,
    pageSize: <?= (int)$perPage ?>,
    loggedIn: <?= $loggedIn ? 'true' : 'false' ?>,
    search: <?= json_encode($searchTerm) ?>,
    providers: <?= json_encode($selectedProviders) ?>,
    sort: <?= json_encode($currentSort) ?>,
    apiParams: <?= json_encode($slotApiParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    actionButtons: <?= $slotShowActionButtons ? 'true' : 'false' ?>,
    hideProviders: <?= $slotHideProviders ? 'true' : 'false' ?>,
    emptyTitle: <?= json_encode($slotEmptyTitle, JSON_UNESCAPED_UNICODE) ?>,
    emptyText: <?= json_encode($slotEmptyText, JSON_UNESCAPED_UNICODE) ?>,
    totalSlots: <?= (int)$totalSlots ?>,
    remainingGames: <?= (int)$remainingGames ?>,
    showLoadMore: <?= $showLoadMore ? 'true' : 'false' ?>
};
</script>
<script src="/assets/js/jackpot.js"></script>
<script src="/assets/js/winners.js?v=<?= htmlspecialchars($slotJsVer, ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="/assets/js/slot.js?v=<?= rawurlencode($slotJsVer) ?>"></script>
