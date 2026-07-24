<?php
/**
 * BGaming dedicated page view.
 *
 * Variables expected from pages/bgaming.php:
 *   $games, $searchTerm, $loggedIn, $totalSlots, $perPage, $currentPage,
 *   $hasNext, $remainingGames, $showLoadMore, $nextPage, $apiError
 */
$slotGameType = 0;
$slotPageBaseUrl = '/bgaming';
$slotEmptyTitle = $slotEmptyTitle ?? 'BGaming oyunu bulunamadı';
$slotEmptyText  = $slotEmptyText ?? 'Admin panelinden BGaming oyun sync çalıştırın veya arama terimini değiştirin.';
$loopGames = $games ?? [];
$bgamingJsPath = BASE_PATH . '/assets/js/bgaming.js';
$bgamingJsVer = (string) ((is_file($bgamingJsPath) ? filemtime($bgamingJsPath) : time()) . '-' . (is_file($bgamingJsPath) ? filesize($bgamingJsPath) : '0'));
?>
<div class="slot-page-root">
    <div class="slot-page-layout">
        <!-- Hero / Banner alanı -->
        <div class="slot-hero" id="slot-hero">
            <div class="slot-hero-slider">
                <div class="slot-hero-track" id="slot-hero-track" data-slider-category="bgaming">
                    <div class="slot-hero-slide" data-slide-index="0">
                        <button class="slot-hero-slide-btn" disabled>
                            <img src="<?= htmlspecialchars(BASE_PATH . '/assets/images/bgaming-hero.webp', ENT_QUOTES, 'UTF-8') ?>"
                                 alt="BGaming"
                                 loading="eager"
                                 style="aspect-ratio: 96 / 25;"
                                 onerror="this.style.display='none'">
                        </button>
                    </div>
                </div>
                <div class="slot-hero-dots">1 / 1</div>
            </div>
        </div>

        <!-- Ana içerik -->
        <div class="slot-main-content">
            <div class="casinoGameListBlock games-main" id="gamesScrollContainer">
                <!-- Arama + Rastgele Oyun -->
                <div class="casinoGameListBlockHeader">
                    <div class="casinoTitleSearch">
                        <div class="games-search-expand is-expanded" id="gamesSearchExpand" aria-expanded="true">
                            <div class="casinoInputWrp">
                                <div class="searchInputWrp active games-search-bar">
                                    <input type="text"
                                           class="searchInput games-search-input"
                                           placeholder="BGaming oyun ara…"
                                           id="searchModalInput"
                                           value="<?= htmlspecialchars($searchTerm, ENT_QUOTES); ?>">
                                    <p class="searchInputIcon bc-i-search games-search-icon-btn"
                                       id="searchClearBtn"
                                       title="Aramayı temizle"
                                       aria-label="Aramayı temizle"></p>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="random-game-btn" id="randomGameBtn"
                                title="Rastgele Oyun Oyna" aria-label="Rastgele Oyun Oyna">
                            Rastgele Oyun Oyna
                        </button>
                    </div>
                </div>

                <!-- Başlık satırı -->
                <div class="casinoProviderRow casinoProviderRow--mobile-inline">
                    <p class="casinoGameListTitle">BGAMING OYUNLARI</p>
                    <span class="casinoGameListAllLink"><?= (int)$totalSlots ?> oyun</span>
                </div>

                <!-- Oyun grid -->
                <div class="casinoGameItemWrp slot-oyun-listesi slots-games-container"
                     id="casino_games_container" aria-label="BGaming oyun listesi">
                    <div class="casinoCategoryGames">
<?php if (empty($loopGames)): ?>
                        <div class="empty-state">
                            <i class="fas fa-gamepad"></i>
                            <h3><?= htmlspecialchars($slotEmptyTitle, ENT_QUOTES, 'UTF-8') ?></h3>
                            <p><?= htmlspecialchars($slotEmptyText, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
<?php else: ?>
<?php   foreach ($loopGames as $game): ?>
<?php
        $gameId    = (string) ($game['game_id'] ?? '');
        $gameName  = (string) ($game['game_name'] ?? '');
        $cover     = (string) ($game['cover'] ?? '');
        $catalogId = (string) ($game['id'] ?? '');
        $playHref  = '/play?game_id=' . rawurlencode($gameId) . '&mode=real&wallet=main';
        if (!$loggedIn) $playHref = '#login';
        $demoHref  = '/play?game_id=' . rawurlencode($gameId) . '&mode=fun';
        $playHrefJson = htmlspecialchars(json_encode($playHref, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
        $playIntentJs = 'if(event){event.preventDefault();event.stopPropagation();}if(window.__bgamingHandlePlayIntent){window.__bgamingHandlePlayIntent(event,' . $playHrefJson . ');}else{window.location.href=' . $playHrefJson . ';}';
?>
                        <div class="casinoGameItemContent"
                             data-favorite-kind="bgaming"
                             data-catalog-id="<?= htmlspecialchars($catalogId, ENT_QUOTES, 'UTF-8') ?>"
                             data-game-id="<?= htmlspecialchars($gameId, ENT_QUOTES, 'UTF-8') ?>"
                             onclick="<?= $playIntentJs ?>">
                            <span class="providerBadgeBlock" data-badge=""></span>
                            <div class="casinoGameItem">
                                <img alt="<?= htmlspecialchars($gameName, ENT_QUOTES); ?>"
                                     loading="eager"
                                     src="<?= htmlspecialchars($cover, ENT_QUOTES); ?>"
                                     data-src="<?= htmlspecialchars($cover, ENT_QUOTES); ?>"
                                     class="casinoGameItemImage"
                                     title="<?= htmlspecialchars($gameName, ENT_QUOTES); ?>"
                                     style="aspect-ratio: 44 / 31;"
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDMwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjMwMCIgaGVpZ2h0PSIyMDAiIHJ4PSI4IiBmaWxsPSIjMWExMTJlIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGRvbWluYW50LWJhc2VsaW5lPSJtaWRkbGUiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZpbGw9IiM2NjYiIGZvbnQtZmFtaWx5PSJzYW5zLXNlcmlmIiBmb250LXNpemU9IjE0Ij5ObyBJbWFnZTwvdGV4dD48L3N2Zz4='">
                                <i class="casinoGameItemFavBc bc-i-favorite"></i>
                                <div class="game-overlay">
                                    <div class="game-overlay-top"></div>
                                    <div class="game-title-wrap">
                                        <p class="game-title-text"><?= htmlspecialchars($gameName, ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div class="game-actions">
                                        <a class="play-btn" href="<?= htmlspecialchars($playHref, ENT_QUOTES, 'UTF-8') ?>"
                                           onclick="<?= $playIntentJs ?>">OYNA</a>
                                        <a class="demo-btn" href="<?= htmlspecialchars($demoHref, ENT_QUOTES, 'UTF-8') ?>"
                                           onclick="event.stopPropagation()">DEMO</a>
                                    </div>
                                </div>
                            </div>
                        </div>
<?php   endforeach; ?>
<?php endif; ?>
                    </div><!-- .casinoCategoryGames -->
                    <div id="load-more-sentinel" aria-hidden="true"
                         style="height:1px;min-height:1px;opacity:0;pointer-events:none;overflow:hidden;"></div>
                </div><!-- #casino_games_container -->
            </div><!-- #gamesScrollContainer -->
        </div><!-- .slot-main-content -->
    </div><!-- .slot-page-layout -->
</div><!-- .slot-page-root -->

<script>
window.BGAMING_CONFIG = {
    currentPage: <?= (int)$currentPage ?>,
    nextPage: <?= (int)$nextPage ?>,
    pageSize: <?= (int)$perPage ?>,
    loggedIn: <?= $loggedIn ? 'true' : 'false' ?>,
    search: <?= json_encode($searchTerm) ?>,
    totalSlots: <?= (int)$totalSlots ?>,
    remainingGames: <?= (int)$remainingGames ?>,
    showLoadMore: <?= $showLoadMore ? 'true' : 'false' ?>,
    emptyTitle: <?= json_encode($slotEmptyTitle, JSON_UNESCAPED_UNICODE) ?>,
    emptyText: <?= json_encode($slotEmptyText, JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="/assets/js/bgaming.js?v=<?= rawurlencode($bgamingJsVer) ?>"></script>
