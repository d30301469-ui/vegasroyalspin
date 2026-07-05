<?php
// En üstte, BOM veya boşluk olmadan session başlat
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../config/frontend_session.php';
    metropol_frontend_session_start();
}

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../services/SlotGamesQuery.php';

function getGames($searchTerm = '', $providers = [], $limit = 18, $offset = 0, $sort = '')
{
    $p = SlotGamesQuery::slotsPage($searchTerm, $providers, $limit, $offset, $sort);
    return $p['games'];
}

function getAllUniqueProviders()
{
    return SlotGamesQuery::allProviders();
}

function getTotalSlotsCount($searchTerm = '', $providers = [])
{
    return SlotGamesQuery::slotsPage($searchTerm, $providers, 1, 0, '')['total'];
}

// Form verilerini al
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$selectedProviders = isset($_GET['providers']) ? (array)$_GET['providers'] : [];
$currentSort = isset($_GET['sort']) ? trim($_GET['sort']) : '';
$limit = 30;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

// Oyunları al - SADECE SLOTS
$games = getGames($searchTerm, $selectedProviders, $limit, $offset, $currentSort);
$allUniqueProviders = getAllUniqueProviders();
$slotDemoHref = static function (array $game): string {
    $gid = (string) ($game['game_id'] ?? '');

    return '/play?game_id=' . rawurlencode($gid) . '&mode=fun';
};

// Toplam slot oyun sayısını al
$totalSlots = getTotalSlotsCount($searchTerm, $selectedProviders);
$remainingGames = $totalSlots - ($offset + count($games));
$showLoadMore = $remainingGames > 0;

// Sağlayıcı badge'leri (EN İYİ, JACKPOT, SICAK vb.) – referans providerListRow
$providerBadges = [
    'pragmatic'     => ['EN İYİ', 'JACKPOT', 'SICAK'],
    'pgsoft'        => ['SICAK'],
    'spribe'        => ['JACKPOT', 'SICAK'],
    'hacksaw'       => ['EN İYİ', 'SICAK'],
    'nolimitcity-A' => ['JACKPOT'],
    'bgaming'       => ['SICAK'],
    'evoplay'       => ['EN İYİ'],
    'play-son'      => [],
    'booming'       => ['JACKPOT'],
    'quickspin'     => ['EN İYİ', 'SICAK'],
];

sort($allUniqueProviders);
?>

<?php require_once __DIR__ . '/../../views/layouts/head_full.php'; ?>
<?php include __DIR__ . '/../../views/partials/header.php'; ?>

<!-- Slot üst: Slider -->
<section class="slot-top-section" aria-label="Slot sayfası üst alan">
    <?php $sliderApiCategory = 'slots'; include __DIR__ . '/../../views/partials/slider.php'; ?>
    <div class="slot-below-hero">
        <div class="slot-jackpot-wrap">
            <?php include __DIR__ . '/../../views/partials/jackpot.php'; ?>
        </div>
        <div class="slot-winners-wrap">
            <?php include __DIR__ . '/../../views/partials/winners.php'; ?>
        </div>
    </div>
</section>

<div class="casino-container">
    <!-- Slots: Ayrılmaz 4’lü – 1) Ana Kategori Filtreleri, 2) Sağlayıcı Filtreleri, 3) Oyun Arama/Rastgele, 4) Oyunlar Konteyneri -->
    <div class="slots-filter-and-games" id="slotsFilterAndGames">
        <!-- 1) Ana Kategori Filtreleri -->
        <div class="slots-category-filters category-tabs-wrapper" id="slotsCategoryFilters">
        <button class="cat-tab-arrow cat-tab-arrow-left" id="catArrowLeft"><i class="fas fa-chevron-left"></i></button>
        <div class="category-tabs-scroll" id="categoryTabsScroll">
            <a href="/slot" class="cat-tab <?= $currentSort === '' ? 'active' : '' ?>" data-sort=""><i class="bc-i-default-icon bc-i-all-games1" aria-hidden="true"></i> Tüm Oyunlar</a>
            <a href="/slot?sort=betil" class="cat-tab <?= $currentSort === 'betil' ? 'active' : '' ?>" data-sort="betil"><i class="fas fa-link"></i> Betil Link</a>
            <a href="/slot?sort=slots" class="cat-tab <?= $currentSort === 'slots' ? 'active' : '' ?>" data-sort="slots"><i class="fas fa-dice"></i> Slots</a>
            <a href="/slot?sort=liked" class="cat-tab <?= $currentSort === 'liked' ? 'active' : '' ?>" data-sort="liked"><i class="fas fa-thumbs-up"></i> En Beğenilen Oyunlar</a>
            <a href="/slot?sort=popular" class="cat-tab <?= $currentSort === 'popular' ? 'active' : '' ?>" data-sort="popular"><i class="fas fa-fire"></i> Popüler Oyunlar</a>
            <a href="/slot?sort=new" class="cat-tab <?= $currentSort === 'new' ? 'active' : '' ?>" data-sort="new"><i class="fas fa-star"></i> Yeni Oyunlar</a>
            <a href="/jackpot" class="cat-tab"><i class="fas fa-crown"></i> Jackportlar</a>
            <a href="/slot?sort=bonus-buy" class="cat-tab <?= $currentSort === 'bonus-buy' ? 'active' : '' ?>" data-sort="bonus-buy"><i class="fas fa-cart-shopping"></i> Bonus Satın Alma Oyunları</a>
            <a href="/slot?sort=video" class="cat-tab <?= $currentSort === 'video' ? 'active' : '' ?>" data-sort="video"><i class="fas fa-film"></i> Video Slotları</a>
            <a href="/slot?sort=freespin" class="cat-tab <?= $currentSort === 'freespin' ? 'active' : '' ?>" data-sort="freespin"><i class="fas fa-rotate"></i> Free Spin Satın Alma Oyunları</a>
            <a href="/slot?sort=skill" class="cat-tab <?= $currentSort === 'skill' ? 'active' : '' ?>" data-sort="skill"><i class="fas fa-bullseye"></i> Beceri Oyunları</a>
            <a href="/slot?sort=special" class="cat-tab <?= $currentSort === 'special' ? 'active' : '' ?>" data-sort="special"><i class="fas fa-gift"></i> Yılbaşı Özel</a>
        </div>
        <button class="cat-tab-arrow cat-tab-arrow-right" id="catArrowRight"><i class="fas fa-chevron-right"></i></button>
        </div>

        <!-- Tam genişlik: SAĞLAYICILAR | OYUNLAR (ok ve SAĞLAYICILAR = sağlayıcıları aç/kapat) -->
        <div class="slots-providers-games-line" aria-label="Sağlayıcılar ve Oyunlar">
            <button type="button" class="providers-games-chevron" id="lineSidebarToggle" title="Sağlayıcıları aç/kapat" aria-label="Sağlayıcıları aç/kapat"><i class="fas fa-chevron-right" id="lineSidebarToggleIcon"></i></button>
            <button type="button" class="providers-games-label providers-games-active" id="lineSidebarToggleLabel" title="Sağlayıcıları aç/kapat">SAĞLAYICILAR</button>
            <span class="providers-games-sep" aria-hidden="true">|</span>
            <span class="providers-games-label">OYUNLAR</span>
        </div>

        <!-- Main Content: 2) Sağlayıcı Filtreleri + 3) Oyun Arama + 4) Oyunlar Konteyneri -->
        <div class="slots-layout" id="slotsLayout">
            <!-- 2) Sağlayıcı Filtreleri (daralt/genişlet üst satırdan) -->
            <aside class="slots-provider-filters providers-sidebar" id="providersSidebar">
            <div class="sidebar-search">
                <input type="text" placeholder="Sağlayıcı Ara" id="providerSearchInput" class="provider-search-input">
                <i class="fas fa-search provider-search-icon"></i>
            </div>
            <div class="sidebar-providers-list" id="sidebarProvidersList">
                <button class="sidebar-provider-item <?= empty($selectedProviders) && empty($searchTerm) ? 'active' : '' ?>" onclick="selectAllProviders()">
                    Tümü
                </button>
                <?php foreach ($allUniqueProviders as $provider):
                    $badgeSlug = ProviderDisplayBadgeMap::slugForDisplay($provider);
                    $badges = $badgeSlug !== null ? array_slice($providerBadges[$badgeSlug] ?? [], 0, 1) : [];
                    $jsProvider = json_encode($provider, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
                ?>
                    <button type="button" class="sidebar-provider-item <?= in_array($provider, $selectedProviders, true) ? 'active' : '' ?>"
                            onclick='toggleProvider(<?= $jsProvider ?>)'
                            data-provider="<?= htmlspecialchars($provider, ENT_QUOTES, 'UTF-8') ?>">
                        <span class="provider-list-row"><?= htmlspecialchars($provider) ?></span>
                        <?php if (!empty($badges)): ?>
                            <span class="provider-badges">
                                <?php foreach ($badges as $badge): ?>
                                    <span class="provider-badge <?= strtoupper($badge) === 'SICAK' ? 'provider-badge-hot' : (strtoupper($badge) === 'YENİ' ? 'provider-badge-new' : '') ?>"><?= htmlspecialchars($badge) ?></span>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </aside>

            <!-- Right Content: Oyun Arama + Oyunlar Konteyneri -->
            <div class="games-main" id="gamesScrollContainer">
                <!-- 3) Oyun Arama ve Rastgele Seçim -->
                <div class="slots-game-search-bar games-header">
                <button type="button"
                        class="mobile-sidebar-toggle"
                        id="mobileSidebarToggle"
                        title="Tüm Sağlayıcılar"
                        aria-label="Tüm sağlayıcıları aç">
                    <span class="mobile-sidebar-toggle__pill">
                        <i class="fas fa-filter" aria-hidden="true"></i>
                        <span class="mobile-sidebar-toggle__pill-text">Tüm Sağlayıcılar</span>
                    </span>
                    <span class="mobile-sidebar-toggle__menu-icon" aria-hidden="true"><i class="fas fa-bars"></i></span>
                </button>
                <button type="button" class="all-games-btn iconButtonBlock" id="allGamesBtn" title="Tüm Oyunlar" aria-label="Tüm Oyunlar">
                    <i class="bc-i-default-icon bc-i-all-games1" aria-hidden="true"></i>
                </button>
                <button type="button" class="view-module-btn iconButtonBlock" id="viewModuleBtn" title="Modül görünümü" aria-label="Modül görünümü" aria-pressed="false">
                    <i class="bc-i-view-module-icon" aria-hidden="true"></i>
                </button>
                <div class="games-search-bar">
                    <input type="text"
                           class="games-search-input"
                           placeholder="Oyun Ara"
                           id="searchModalInput"
                           value="<?= htmlspecialchars($searchTerm, ENT_QUOTES); ?>">
                    <button type="button" class="games-search-btn games-search-icon-btn" id="searchClearBtn" title="Aramayı temizle" aria-label="Aramayı temizle"><i class="fas fa-search" id="searchClearBtnIcon" aria-hidden="true"></i></button>
                </div>
                <button class="random-game-btn" id="randomGameBtn" title="Rastgele Bir Oyun Oyna">
                    <i class="fas fa-random"></i> RASTGELE BİR OYUN OYNA
                </button>
                <div class="sort-toggle-wrap" id="sortToggleWrap">
                    <button type="button" class="sort-toggle-btn iconButtonBlock bc-i-sort" id="sortToggleBtn" title="Sıralama" aria-label="Sıralama"></button>
                </div>
                </div>

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
                        <span><?= htmlspecialchars(ucfirst($provider)) ?></span>
                        <span class="remove" onclick='removeFilter(<?= json_encode($provider, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>)'>×</span>
                    </div>
                <?php endforeach; ?>
                <?php if (!empty($searchTerm) || !empty($selectedProviders)): ?>
                <button class="clear-all-filters-btn" onclick="clearAllUrlFilters()">Temizle</button>
                <?php endif; ?>
                </div>

                <!-- 4) Oyun listesi alanı (tek blok, isim: slot-oyun-listesi) -->
                <div class="slot-oyun-listesi slots-games-container" id="slotsGamesContainer" aria-label="Oyun listesi">
                    <div class="game-grid" id="game-grid">
                <?php if (empty($games)): ?>
                    <div class="empty-state">
                        <i class="fas fa-gamepad"></i>
                        <h3>Slot oyunu bulunamadı</h3>
                        <p>Arama teriminizi değiştirmeyi veya filtreleri temizlemeyi deneyin</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($games as $game): ?>
                        <div class="game-item" onclick="window.location.href='/play?game_id=<?= (int)$game['game_id']; ?>&mode=real&wallet=main'">
                            <img src="<?= htmlspecialchars($game['cover'], ENT_QUOTES); ?>"
                                 alt="<?= htmlspecialchars($game['game_name'], ENT_QUOTES); ?>"
                                 width="200" height="200"
                                 loading="lazy"
                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDMwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjMwMCIgaGVpZ2h0PSIyMDAiIHJ4PSI4IiBmaWxsPSIjMWExMTJlIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGRvbWluYW50LWJhc2VsaW5lPSJtaWRkbGUiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZpbGw9IiM2NjYiIGZvbnQtZmFtaWx5PSJzYW5zLXNlcmlmIiBmb250LXNpemU9IjE0Ij5ObyBJbWFnZTwvdGV4dD48L3N2Zz4='">
                            <div class="game-overlay">
                                <div class="game-overlay-top">
                                    <span class="game-fav"><i class="far fa-star"></i></span>
                                    <a href="#" class="game-info-btn" onclick="event.stopPropagation(); return false;" aria-label="Bilgi"><i class="fas fa-info-circle"></i></a>
                                </div>
                                <div class="game-title-wrap">
                                    <p class="game-title-text"><?= htmlspecialchars($game['game_name'], ENT_QUOTES); ?></p>
                                </div>
                                <div class="game-actions">
                                    <a href="/play?game_id=<?= (int)$game['game_id']; ?>&mode=real&wallet=main" class="play-btn" onclick="event.stopPropagation();">OYNA</a>
                                    <?php if (!empty($game['has_demo'])): ?>
                                        <a href="<?= htmlspecialchars($slotDemoHref($game), ENT_QUOTES, 'UTF-8') ?>" class="demo-btn" onclick="event.stopPropagation();">DEMO</a>
                                    <?php else: ?>
                                        <span class="demo-btn demo-btn--disabled" aria-disabled="true">DEMO</span>
                                    <?php endif; ?>
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

<script>
window.SLOT_CONFIG = {
    currentOffset: <?= (int)($offset + count($games)) ?>,
    limit: <?= (int)$limit ?>,
    search: <?= json_encode($searchTerm) ?>,
    providers: <?= json_encode($selectedProviders) ?>,
    sort: <?= json_encode($currentSort) ?>,
    totalSlots: <?= (int)$totalSlots ?>,
    remainingGames: <?= (int)$remainingGames ?>,
    showLoadMore: <?= $showLoadMore ? 'true' : 'false' ?>
};
</script>
<script src="/assets/js/jackpot.js"></script>
<script src="/assets/js/winners.js?v=<?= urlencode((string) (is_file(BASE_PATH . '/assets/js/winners.js') ? filemtime(BASE_PATH . '/assets/js/winners.js') : time())) ?>"></script>
<?php
$slotJsPath = BASE_PATH . '/assets/js/slot.js';
$slotJsVer = (string) ((is_file($slotJsPath) ? filemtime($slotJsPath) : time()) . '-' . (is_file($slotJsPath) ? filesize($slotJsPath) : '0'));
?>
<script src="/assets/js/slot.js?v=<?= rawurlencode($slotJsVer) ?>"></script>
