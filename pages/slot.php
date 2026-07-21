<?php
// En üstte, BOM veya boşluk olmadan session başlat
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/frontend_session.php';
    metropol_frontend_session_start();
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
$slotApiParams = [];
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
    $normalizedKey = preg_replace('/[^a-z0-9]+/i', '', mb_strtolower((string) $provider, 'UTF-8')) ?: '';

    if ($normalizedKey === 'iconic21') {
        $svgLogo = '<span class="CMSIconSVGWrapper provider-logo-svg provider-logo-svg--iconic21"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 727.05 253.89" fill="currentColor"><path d="m474.44,142.97l13.91,110.91-56.08-87.25-44.45,30.24,13.87-46.37-91.69-10.92,98.21-35.38L395.73,0l55.5,81.1,44.96-31.27-14.46,47.67,90.97,10.74-98.25,34.72Zm-195.06,19.63c4.92,8.35,7.38,17.78,7.38,28.26s-2.46,19.91-7.38,28.26c-4.92,8.35-11.88,14.92-20.89,19.68-9.01,4.77-19.43,7.15-31.27,7.15s-22.27-2.38-31.27-7.15c-9.01-4.77-15.97-11.33-20.89-19.68-4.92-8.35-7.38-17.77-7.38-28.26s2.46-19.91,7.38-28.26c4.92-8.35,11.88-14.91,20.89-19.68,9.01-4.77,19.43-7.15,31.27-7.15s22.27,2.38,31.27,7.15c9.01,4.77,15.97,11.33,20.89,19.68Zm-29.35,28.26c0-5.12-.94-9.6-2.82-13.43-1.88-3.84-4.54-6.81-7.98-8.92-3.44-2.11-7.44-3.16-12.01-3.16s-8.57,1.05-12,3.16c-3.44,2.11-6.1,5.08-7.98,8.92-1.88,3.84-2.82,8.32-2.82,13.43s.94,9.6,2.82,13.43c1.88,3.84,4.54,6.81,7.98,8.92,3.44,2.11,7.44,3.16,12,3.16s8.57-1.05,12.01-3.16c3.44-2.11,6.1-5.08,7.98-8.92,1.88-3.84,2.82-8.32,2.82-13.43ZM0,242.35h34.62v-103.86H0v103.86Zm91.75-74.1c3.44-2.18,7.51-3.27,12.23-3.27,3.51,0,6.75.69,9.71,2.07,2.96,1.38,5.37,3.38,7.22,5.98,1.86,2.61,2.96,5.69,3.31,9.26h35c-.5-9.18-3.16-17.28-7.98-24.31-4.82-7.02-11.4-12.48-19.76-16.37-8.35-3.89-17.95-5.83-28.79-5.83s-21.21,2.38-30.07,7.15c-8.86,4.77-15.77,11.34-20.74,19.72-4.97,8.38-7.45,17.79-7.45,28.22s2.48,19.85,7.45,28.22c4.97,8.38,11.88,14.95,20.74,19.72,8.86,4.77,18.88,7.15,30.07,7.15s20.43-1.94,28.79-5.83c8.35-3.89,14.94-9.34,19.76-16.37,4.82-7.02,7.48-15.13,7.98-24.31h-35c-.35,3.56-1.46,6.65-3.31,9.26-1.86,2.61-4.26,4.6-7.22,5.98-2.96,1.38-6.2,2.07-9.71,2.07-4.72,0-8.79-1.09-12.23-3.27-3.44-2.18-6.06-5.23-7.86-9.14-1.81-3.91-2.71-8.4-2.71-13.47s.9-9.56,2.71-13.47c1.81-3.91,4.43-6.96,7.86-9.15Zm300.95,35.79l-20.15,13.71,6.98-23.34,10.93-36.51-25.11-2.99v43.49l-25.99-46.58-30.38-3.62-14.99-1.78v95.95h31.16v-58.96l32.84,58.96h38.51v-40.91l-3.82,2.6Zm37.1-25.25l-14.34,9.76v53.8h34.62v-32.01l-20.28-31.55Zm197.72,31.73c2.1-1.61,5.07-3.47,8.88-5.57,6.57-3.61,11.84-7.15,15.8-10.61,3.97-3.47,6.89-7.2,8.77-11.22,1.88-4.01,2.83-8.57,2.83-13.69,0-6.82-1.49-12.76-4.48-17.8-2.99-5.05-7.35-8.93-13.1-11.67-5.75-2.74-12.71-4.1-20.89-4.1-8.68,0-16.19,1.63-22.54,4.89-6.35,3.26-11.24,7.78-14.68,13.55-3.44,5.77-5.28,12.31-5.53,19.64h31.69c-.1-3.91.86-6.91,2.86-8.99,2.01-2.08,4.6-3.12,7.75-3.12,1.7,0,3.2.31,4.48.94,1.28.63,2.27,1.52,2.97,2.67.7,1.15,1.05,2.53,1.05,4.14s-.38,3.08-1.13,4.41c-.76,1.33-2.12,2.76-4.1,4.28-1.98,1.53-4.78,3.28-8.39,5.23-10.09,5.47-17.77,10.98-23.03,16.52-4.87,5.13-8.31,10.74-10.3,16.82-.17.5-.32,1-.46,1.51-1.85,6.47-2.78,14.47-2.79,24.01h79.63v-25.51h-40.6c.21-.6.48-1.17.82-1.73.88-1.46,2.37-2.98,4.48-4.59Zm76.13-72.03c-1.46,4.97-3.56,9.08-6.32,12.34-2.76,3.26-6.26,5.75-10.5,7.45s-9.34,2.68-15.32,2.94v29.2c4.57.1,8.84-.39,12.83-1.47,3.89-1.05,7.31-2.65,10.27-4.78v58.18h32.44v-103.86h-23.41Zm-194.02,29.77c3.44-2.18,7.51-3.27,12.23-3.27,3.51,0,6.75.69,9.71,2.07,2.96,1.38,5.37,3.38,7.23,5.98,1.86,2.61,2.96,5.69,3.31,9.26h35c-.5-9.18-3.16-17.28-7.98-24.31-4.82-7.02-11.4-12.48-19.76-16.37-8.3-3.86-17.84-5.81-28.59-5.83l-36.87,13.03,11.59,92.38c7.58,3.18,15.95,4.77,25.09,4.77,10.84,0,20.43-1.94,28.79-5.83,8.35-3.89,14.94-9.34,19.76-16.37,4.82-7.02,7.48-15.13,7.98-24.31h-35c-.35,3.56-1.46,6.65-3.31,9.26-1.86,2.61-4.27,4.6-7.23,5.98-2.96,1.38-6.2,2.07-9.71,2.07-4.72,0-8.79-1.09-12.23-3.27-3.44-2.18-6.06-5.23-7.87-9.14-1.81-3.91-2.71-8.4-2.71-13.47s.9-9.56,2.71-13.47c1.81-3.91,4.43-6.96,7.87-9.15Z"></path></svg></span>';
    } else {
        $svgLabel = htmlspecialchars(mb_strtoupper((string) $provider, 'UTF-8'), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $svgLogo = '<span class="CMSIconSVGWrapper provider-logo-svg provider-logo-svg--fallback"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 220 24" fill="currentColor" role="img" aria-label="' . $svgLabel . '"><text x="110" y="18" text-anchor="middle" font-size="17" font-weight="700" font-family="Arial, sans-serif">' . $svgLabel . '</text></svg></span>';
    }

    return '<div title="' . $esc . '" class="providerItemsInner sidebar-provider-item' . ($active ? ' active' : '') . '" role="button" tabindex="0" data-provider="' . $esc . '"><span class="providerBadgeBlock ' . htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') . '" data-badge="' . htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') . '"></span><div class="providerItemsBtn has-provider-icon">' . $svgLogo . '<span class="provider-list-row">' . htmlspecialchars($provider, ENT_QUOTES, 'UTF-8') . '</span></div></div>';
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
            <!-- ds-drawer-wrapper: tam ekran overlay + blur arka plan -->
            <div class="ds-drawer-wrapper providers-drawer-wrapper casinoProviderBlock providers-sidebar" id="providersSidebar" role="presentation">
              <div class="ds-drawer-spring" aria-modal="true">
                <div class="ds-drawer ds-drawer--contained">
                  <!-- Drag handle + başlık -->
                  <div class="ds-drawer__header">
                    <div class="ds-drawer__drag-handle" id="providerSheetDragHandle">
                      <div class="ds-drawer__drag-bar"></div>
                    </div>
                    <div class="ds-drawer__title-bar">
                      <div class="ds-drawer__title-content">
                        <span class="ds-drawer__title-text">Sağlayıcılar</span>
                      </div>
                    </div>
                  </div>
                  <!-- Kaydırılabilir içerik -->
                  <div class="ds-drawer__content" data-scroll-lock-scrollable="">
                    <div class="casinoProvidersPopupContent">
                      <!-- Arama -->
                      <div class="casinoProvidersPopupContent__search">
                        <div class="providerSearchRow sidebar-search provider-search-bar">
                          <div class="searchInputWrp active">
                            <input type="text" placeholder="Sağlayıcı Ara" id="providerSearchInput" class="searchInput provider-search-input" autocomplete="off">
                            <p class="searchInputIcon bc-i-search provider-search-btn" id="providerSearchClearBtn" title="Sağlayıcı ara" aria-label="Sağlayıcı ara"><i id="providerSearchClearBtnIcon" aria-hidden="true"></i></p>
                          </div>
                        </div>
                      </div>
                      <!-- Sağlayıcı listesi -->
                      <div class="casinoProvidersPopupContent__underSearchContent">
                        <div class="casinoProvidersPopupContent__section">
                          <div class="casinoProvidersPopupContent__titleToggleContainer">
                            <span class="casinoProvidersPopupContent__sectionTitle">Tüm Sağlayıcılar</span>
                            <div class="casinoProvidersPopupContent__viewToggle">
                              <i class="bc-i-view-list provider-sheet-grid-btn" id="providerSheetGridBtn" title="Modül görünümü" aria-label="Modül görünümü"></i>
                            </div>
                          </div>
                          <div class="casinoProvidersPopupContent__grid" id="sidebarProvidersList">
                            <div class="providerItemsHolder module">
                            <div title="Tümü" class="providerItemsInner sidebar-provider-item <?= empty($selectedProviders) && empty($searchTerm) ? 'active' : '' ?>" role="button" tabindex="0" data-provider-all="1"><span class="providerBadgeBlock " data-badge=""></span><div class="providerItemsBtn">TÜMÜ</div></div>
                            <?php
                            foreach ($allUniqueProviders as $provider) {
                                echo $renderProviderBtn($provider);
                            }
                            ?>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Footer: Sıfırla + FİLTRE -->
                  <div class="casinoProvidersPopupContent__footer">
                    <button type="button" class="ds-btn provider-sheet-reset" id="providerSheetResetBtn"><span class="btn__label">Sıfırla</span></button>
                    <button type="button" class="ds-btn provider-sheet-apply" id="providerSheetApplyBtn"><span class="btn__label">FİLTRE</span></button>
                  </div>
                </div>
              </div>
            </div>

            <div class="casinoGameListBlock games-main" id="gamesScrollContainer">
                <div class="casinoGameListBlockHeader">
                    <div class="casinoTitleSearch">
                        <button type="button" class="mobile-sidebar-toggle" id="mobileSidebarToggle" title="Tüm Sağlayıcılar" aria-label="Tüm sağlayıcıları aç">
                            <span class="mobile-sidebar-toggle__pill">
                                <i class="fas fa-filter" aria-hidden="true"></i>
                                <span class="mobile-sidebar-toggle__pill-text">Sağlayıcılar</span>
                            </span>
                            <span class="mobile-sidebar-toggle__count" id="mobileSidebarToggleCount" aria-hidden="true"></span>
                        </button>
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
                <button class="random-game-btn" id="randomGameBtn" title="Rastgele Oyun Oyna" aria-label="Rastgele Oyun Oyna">Rastgele Oyun Oyna</button>

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
                        $playHrefJson = htmlspecialchars(
                            json_encode($playHref, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        $mobileOpenPlayJsBody = 'var m=(document.body&&document.body.classList.contains(&quot;mobile-site&quot;))||(((navigator.maxTouchPoints||0)&gt;0)&&window.matchMedia&&window.matchMedia(&quot;(max-width: 1024px)&quot;).matches);if(m){try{var p=new URL(nu,window.location.origin);p.searchParams.set(&quot;open_mode&quot;,&quot;redirect&quot;);nu=p.pathname+p.search+p.hash;}catch(e){nu+=(nu.indexOf(&quot;?&quot;)===-1?&quot;?&quot;:&quot;&amp;&quot;)+&quot;open_mode=redirect&quot;;}var a=document.createElement(&quot;a&quot;);a.href=nu;a.target=&quot;_blank&quot;;a.rel=&quot;noopener noreferrer&quot;;a.style.display=&quot;none&quot;;document.body.appendChild(a);a.click();a.remove();if(document.visibilityState===&quot;hidden&quot;){return;}}window.location.href=nu;';
                        // Play navigation is routed through MaltabetWalletPicker (main/bonus wallet choice modal)
                        // when available, falling back to direct navigation otherwise.
                        $mobileOpenPlayJs = 'var u=' . $playHrefJson . ';function __navPlay(nu){' . $mobileOpenPlayJsBody . '}if(window.MaltabetWalletPicker&&typeof window.MaltabetWalletPicker.launch===&quot;function&quot;){window.MaltabetWalletPicker.launch(u,__navPlay);}else{__navPlay(u);}';
                        $openLoginJs = 'if (typeof window.__openLoginModal === &quot;function&quot;) { window.__openLoginModal(); } else { var loginBtn = document.getElementById(&quot;Giris&quot;); if (loginBtn) loginBtn.click(); }';
                        ?>
                        <div class="casinoGameItemContent " data-catalog-id="<?= htmlspecialchars((string)($game['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-game-id="<?= htmlspecialchars((string)($game['game_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" onclick="<?= $loggedIn ? $mobileOpenPlayJs : $openLoginJs ?>">
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
                                        <a class="play-btn" href="<?= htmlspecialchars($playHref, ENT_QUOTES, 'UTF-8') ?>" onclick="event.stopPropagation();<?= $loggedIn ? ' event.preventDefault(); ' . $mobileOpenPlayJs : ' event.preventDefault(); ' . $openLoginJs ?>">OYNA</a>
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
<script src="/assets/js/winners.js?v=<?= urlencode((string) (is_file(BASE_PATH . '/assets/js/winners.js') ? filemtime(BASE_PATH . '/assets/js/winners.js') : time())) ?>"></script>
<?php
$slotJsPath = BASE_PATH . '/assets/js/slot.js';
$slotJsVer = (string) ((is_file($slotJsPath) ? filemtime($slotJsPath) : time()) . '-' . (is_file($slotJsPath) ? filesize($slotJsPath) : '0'));
?>
<script src="/assets/js/slot.js?v=<?= rawurlencode($slotJsVer) ?>"></script>