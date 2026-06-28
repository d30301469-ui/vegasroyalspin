<?php
// En üstte, BOM veya boşluk olmadan session başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hata raporlama
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../services/SlotGamesQuery.php';
require_once __DIR__ . '/../services/ProviderDisplayBadgeMap.php';

function getAllUniqueProviders()
{
    return SlotGamesQuery::allProviders();
}

// Form verilerini al
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$selectedProviders = isset($_GET['providers']) ? (array)$_GET['providers'] : [];
$currentSort = isset($_GET['sort']) ? trim($_GET['sort']) : '';
$limit = 30;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

$result = SlotGamesQuery::slotsPage($searchTerm, $selectedProviders, $limit, $page, $currentSort);
$games = $result['games'];
$allUniqueProviders = array_values(array_filter(getAllUniqueProviders(), static function (string $provider): bool {
    return stripos($provider, 'bgaming') === false && stripos($provider, 'b gaming') === false;
}));
$slotApiParams = ['source' => 'drakon'];
$totalSlots = $result['total'];
$perPage = $result['perPage'];
$currentPage = $result['page'];
$hasNext = $result['hasNext'];
$nextPage = $currentPage + 1;
$loadedCount = ($currentPage - 1) * $perPage + count($games);
$remainingGames = max(0, $totalSlots - $loadedCount);
$showLoadMore = $hasNext && $remainingGames > 0;

// Sağlayıcı badge'leri (EN İYİ, JACKPOT, SICAK vb.) – referans providerListRow
$providerBadges = [
    'pragmatic'     => ['EN İYİ', 'JACKPOT', 'SICAK'],
    'pgsoft'        => ['SICAK'],
    'spribe'        => ['JACKPOT', 'SICAK'],
    'hacksaw'       => ['EN İYİ', 'SICAK'],
    'nolimitcity-A' => ['JACKPOT'],
    'evoplay'       => ['EN İYİ'],
    'play-son'      => [],
    'booming'       => ['JACKPOT'],
    'quickspin'     => ['EN İYİ', 'SICAK'],
];

sort($allUniqueProviders);

$providerBadgeBlockClass = static function (string $badge): string {
    $normalized = mb_strtolower($badge, 'UTF-8');
    if (str_contains($normalized, 'jackpot')) {
        return 'badge-jackpot';
    }
    if (str_contains($normalized, 'sıcak') || str_contains($normalized, 'sicak')) {
        return 'badge-hot';
    }
    if (str_contains($normalized, 'promosyon')) {
        return 'badge-promo';
    }
    if (str_contains($normalized, 'özel') || str_contains($normalized, 'ozel')) {
        return 'badge-exclusive';
    }
    if (str_contains($normalized, 'ortak')) {
        return 'badge-ortak';
    }
    if (str_contains($normalized, 'iyi') || str_contains($normalized, 'top')) {
        return 'badge-top';
    }
    return '';
};

$slotCategoryItems = [
    ['sort' => '', 'slug' => 'all-games1', 'id' => '-1', 'title' => 'Tüm Oyunlar', 'icon' => 'bc-i-all-games1', 'href' => '/slot'],
    ['sort' => 'liked', 'slug' => 'topslots', 'id' => '93', 'title' => 'En Beğenilen Oyunlar', 'icon' => 'bc-i-topslots', 'href' => '/slot?sort=liked'],
    ['sort' => 'popular', 'slug' => 'populargames', 'id' => '95', 'title' => 'Popüler Oyunlar', 'icon' => 'bc-i-populargames', 'href' => '/slot?sort=popular'],
    ['sort' => 'new', 'slug' => 'new', 'id' => '65', 'title' => 'Yeni Oyunlar', 'icon' => 'bc-i-new', 'href' => '/slot?sort=new'],
    ['sort' => 'jackpots', 'slug' => 'jackpots', 'id' => '59', 'title' => 'Jackpotlar', 'icon' => 'bc-i-jackpots', 'href' => '/slot?sort=jackpots'],
    ['sort' => 'bonus-buy', 'slug' => 'buybonus', 'id' => '247', 'title' => 'Bonus Satın Alma Oyunları', 'icon' => 'bc-i-buybonus', 'href' => '/slot?sort=bonus-buy'],
    ['sort' => 'video', 'slug' => 'videoslots', 'id' => '51', 'title' => 'Video Slotları', 'icon' => 'bc-i-videoslots', 'href' => '/slot?sort=video'],
    ['sort' => 'special', 'slug' => 'newyear', 'id' => '619', 'title' => 'Yılbaşı Özel', 'icon' => 'bc-i-newyear', 'href' => '/slot?sort=special'],
    ['sort' => 'crash', 'slug' => 'crashgames', 'id' => '406', 'title' => 'Uçak Oyunları', 'icon' => 'bc-i-crashgames', 'href' => '/slot?sort=crash'],
    ['sort' => 'freespin', 'slug' => 'buyfeature', 'id' => '274', 'title' => 'Free Spin Satın Alma Oyunları', 'icon' => 'bc-i-buyfeature', 'href' => '/slot?sort=freespin'],
    ['sort' => 'instant', 'slug' => 'instantwin', 'id' => '46', 'title' => 'Anında Kazanç', 'icon' => 'bc-i-instantwin', 'href' => '/slot?sort=instant'],
    ['sort' => 'table', 'slug' => 'tablegames', 'id' => '94', 'title' => 'Masa Oyunları', 'icon' => 'bc-i-tablegames', 'href' => '/slot?sort=table'],
    ['sort' => 'slots', 'slug' => 'slots', 'id' => '57', 'title' => 'Slots', 'icon' => 'bc-i-slots', 'href' => '/slot?sort=slots'],
];

$renderProviderBtn = function ($provider) use ($providerBadges, $selectedProviders, $providerBadgeBlockClass) {
    $badgeSlug = ProviderDisplayBadgeMap::slugForDisplay($provider);
    $badges = $badgeSlug !== null ? array_slice($providerBadges[$badgeSlug] ?? [], 0, 1) : [];
    $badge = $badges[0] ?? '';
    $active = in_array($provider, $selectedProviders, true);
    $esc = htmlspecialchars($provider, ENT_QUOTES, 'UTF-8');
    $badgeClass = $badge !== '' ? $providerBadgeBlockClass($badge) : '';
    return '<div title="' . $esc . '" class="providerItemsInner sidebar-provider-item' . ($active ? ' active' : '') . '" role="button" tabindex="0" data-provider="' . $esc . '"><span class="providerBadgeBlock ' . htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') . '" data-badge="' . htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') . '"></span><div class="providerItemsBtn"><span class="provider-list-row">' . htmlspecialchars($provider, ENT_QUOTES, 'UTF-8') . '</span></div></div>';
};

$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
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
?>

<?php require_once __DIR__ . '/../views/layouts/head_full.php'; ?>
<?php include __DIR__ . '/../views/partials/header.php'; ?>

<div class="slot-page-root">
<!-- Slot üst: Slider -->
<section class="slot-top-section" aria-label="Slot sayfası üst alan">
    <?php $sliderApiCategory = 'slots'; include __DIR__ . '/../views/partials/slider.php'; ?>
    <div class="slot-below-hero">
        <div class="slot-jackpot-wrap">
            <?php include __DIR__ . '/../views/partials/jackpot.php'; ?>
        </div>
        <div class="slot-winners-wrap">
            <?php include __DIR__ . '/../views/partials/winners.php'; ?>
        </div>
    </div>
</section>

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

        <div class="casinoProviderRow">
            <p class="casinoProviderBlockTitle" id="lineSidebarToggleLabel" title="Sağlayıcıları aç/kapat"><i class="casinoProviderBlockIcon bc-i-small-arrow-left" id="lineSidebarToggleIcon"></i><span>Sağlayıcılar</span></p>
            <p class="casinoGameListTitle">OYUNLAR</p>
        </div>

        <div class="casinoProviderAndGame slots-layout" id="slotsLayout">
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

            <div class="casinoGameListBlock games-main" id="gamesScrollContainer">
                <div class="casinoGameListBlockHeader">
                    <div class="casinoTitleSearch">
                        <button type="button" class="mobile-sidebar-toggle" id="mobileSidebarToggle" title="Tüm Sağlayıcılar" aria-label="Tüm sağlayıcıları aç" hidden></button>
                        <div class="selectedProviderBlock"></div>
                        <div class="casinoInputWrp games-search-expand<?= !empty($searchTerm) ? ' is-expanded' : '' ?>"
                             id="gamesSearchExpand"
                             role="search"
                             aria-expanded="<?= !empty($searchTerm) ? 'true' : 'false' ?>">
                            <div class="searchInputWrp active games-search-bar">
                                <input type="text"
                                       class="searchInput games-search-input"
                                       placeholder="Oyun Ara"
                                       id="searchModalInput"
                                       value="<?= htmlspecialchars($searchTerm, ENT_QUOTES); ?>">
                                <p class="searchInputIcon bc-i-search games-search-btn games-search-icon-btn" id="searchClearBtn" title="Aramayı temizle" aria-label="Aramayı temizle"><i id="searchClearBtnIcon" aria-hidden="true"></i></p>
                            </div>
                        </div>
                        <div class="tooltipIconWrapper sort-toggle-wrap" id="sortToggleWrap">
                            <button type="button" class="bc-i-sort iconButtonBlock sort-toggle-btn" id="sortToggleBtn" title="Sıralama" aria-label="Sıralama"></button>
                        </div>
                    </div>
                </div>
                <button type="button" class="all-games-btn iconButtonBlock" id="allGamesBtn" title="Tüm Oyunlar" aria-label="Tüm Oyunlar" hidden></button>
                <button type="button" class="view-module-btn iconButtonBlock" id="viewModuleBtn" title="Modül görünümü" aria-label="Modül görünümü" aria-pressed="false" hidden></button>
                <button class="random-game-btn" id="randomGameBtn" title="Rastgele Bir Oyun Oyna" hidden></button>

                <!-- Active Filters (AJAX güncellemesi için her zaman DOM'da) -->
                <div class="active-filters-row" id="active-filters-row" style="<?= (empty($searchTerm) && empty($selectedProviders)) ? 'display:none' : '' ?>">
                <?php if (!empty($searchTerm)): ?>
                    <div class="active-filter-tag">
                        <span>"<?= htmlspecialchars($searchTerm) ?>"</span>
                        <span class="remove" onclick="removeSearch()">×</span>
                    </div>
                <?php endif; ?>
                <?php foreach ($selectedProviders as $provider): ?>
                    <div class="active-filter-tag">
                        <span><?= htmlspecialchars($provider) ?></span>
                        <span class="remove" onclick='removeFilter(<?= json_encode($provider, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>)'>×</span>
                    </div>
                <?php endforeach; ?>
                <?php if (!empty($searchTerm) || !empty($selectedProviders)): ?>
                <button class="clear-all-filters-btn" onclick="clearAllUrlFilters()">Temizle</button>
                <?php endif; ?>
                </div>

                <!-- 4) Oyun listesi alanı (tek blok, isim: slot-oyun-listesi) -->
                <div class="casinoGameItemWrp slot-oyun-listesi slots-games-container" id="casino_games_container" aria-label="Oyun listesi">
                    <div class="casinoCategoryGames">
                <?php if (empty($games)): ?>
                    <div class="empty-state">
                        <i class="fas fa-gamepad"></i>
                        <h3>Slot oyunu bulunamadı</h3>
                        <p>Arama teriminizi değiştirmeyi veya filtreleri temizlemeyi deneyin</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($games as $game): ?>
                        <?php
                        $playHref = $slotPlayTarget($game);
                        $demoHref = $slotDemoHref($game);
                        ?>
                        <div class="casinoGameItemContent " data-catalog-id="<?= htmlspecialchars((string)($game['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-game-id="<?= htmlspecialchars((string)($game['game_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" onclick="<?= $loggedIn ? 'window.location.href=' . htmlspecialchars(json_encode($playHref, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') : 'if (typeof window.__openLoginModal === &quot;function&quot;) { window.__openLoginModal(); } else { var loginBtn = document.getElementById(&quot;Giris&quot;); if (loginBtn) loginBtn.click(); }' ?>">
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
                                <div class="game-overlay">
                                    <div class="game-overlay-top"></div>
                                    <div class="game-title-wrap">
                                        <p class="game-title-text"><?= htmlspecialchars((string) ($game['game_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div class="game-actions">
                                        <a class="play-btn" href="<?= htmlspecialchars($playHref, ENT_QUOTES, 'UTF-8') ?>" onclick="event.stopPropagation();<?= $loggedIn ? '' : ' event.preventDefault(); if (typeof window.__openLoginModal === &quot;function&quot;) { window.__openLoginModal(); } else { var loginBtn = document.getElementById(&quot;Giris&quot;); if (loginBtn) loginBtn.click(); }' ?>">OYNA</a>
                                        <a class="demo-btn" href="<?= htmlspecialchars($demoHref, ENT_QUOTES, 'UTF-8') ?>" onclick="event.stopPropagation()">DEMO</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                    </div>
                    <!-- Infinite scroll tetikleyici (IntersectionObserver için görünmez ama layout'ta yer kaplar) -->
                    <div id="load-more-sentinel" aria-hidden="true" style="height:1px;min-height:1px;opacity:0;pointer-events:none;overflow:hidden;"></div>
                </div>
            </div>
        </div>
    </div>
    <!-- Oyunlar scrollbar’ı sitenin en sağında (site scroll’u gibi) -->
    <div class="slot-games-scrollbar-rail" id="slotGamesScrollbarRail" aria-hidden="true">
        <div class="slot-games-scrollbar-thumb" id="slotGamesScrollbarThumb"></div>
    </div>
</div>
</div>

<script>
window.SLOT_CONFIG = {
    apiEndpoint: '/api/v2/games',
    apiAdapter: 'member_api_games',
    gameType: 0,
    currentPage: <?= (int)$currentPage ?>,
    nextPage: <?= (int)$nextPage ?>,
    pageSize: <?= (int)$perPage ?>,
    loggedIn: <?= $loggedIn ? 'true' : 'false' ?>,
    search: <?= json_encode($searchTerm) ?>,
    providers: <?= json_encode($selectedProviders) ?>,
    sort: <?= json_encode($currentSort) ?>,
    apiParams: <?= json_encode($slotApiParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    actionButtons: true,
    totalSlots: <?= (int)$totalSlots ?>,
    remainingGames: <?= (int)$remainingGames ?>,
    showLoadMore: <?= $showLoadMore ? 'true' : 'false' ?>
};
</script>
<script src="/assets/js/jackpot.js"></script>
<script src="/assets/js/winners.js"></script>
<script src="/assets/js/slot.js"></script>