<?php
/**
 * BGaming dedicated page view — matches slot.php CSS structure,
 * independent from slot.js / provider filtering / category tabs.
 *
 * Variables from pages/bgaming.php:
 *   $games, $searchTerm, $loggedIn, $totalSlots, $perPage, $currentPage,
 *   $hasNext, $remainingGames, $showLoadMore, $nextPage, $apiError
 */
$slotGameType     = 0;
$slotShowActionButtons = true;
$slotHideProviders = true;
$slotMobileOriginalNav = true;
$searchTerm       = $searchTerm ?? '';
$slotEmptyTitle   = $slotEmptyTitle ?? 'BGaming oyunu bulunamadı';
$slotEmptyText    = $slotEmptyText ?? 'Admin panelinden BGaming oyun sync calistirin veya arama terimini degistirin.';

$bgamingJsPath = BASE_PATH . '/assets/js/bgaming.js';
$bgamingJsVer  = (string) ((is_file($bgamingJsPath) ? filemtime($bgamingJsPath) : time()) . '-' . (is_file($bgamingJsPath) ? filesize($bgamingJsPath) : '0'));
?>
<?php include VIEW_PATH . '/layouts/head.php'; ?>
<?php include VIEW_PATH . '/partials/header.php'; ?>

<div class="slot-page-root slot-page-root--unified" data-slot-game-type="0">
<section class="slot-top-section" aria-label="BGaming sayfasi ust alan">
    <?php $sliderApiCategory = 'bgaming'; include VIEW_PATH . '/partials/slider.php'; ?>
</section>

<?php if ($apiError): ?>
<div class="alert alert-warning mx-3 my-2" role="alert">
    Oyun listesi su an yuklenemedi. Backend baglantisini kontrol edin (<code>API_BACKEND_MAIN_BASE_URL</code>).
</div>
<?php endif; ?>

<div class="casino-container">
    <div class="casinoProviderContent slots-filter-and-games" id="slotsFilterAndGames">
        <div class="casinoNavigationAndFilters">
            <div class="horizontal-scroll horizontal-scroll--left casinoCategories" style="display:none;"></div>
            <div class="casinoGameProviderFilters casinoGameProviderFilters--withRandomGame casinoGameProviderFilters--noProviders">
                <div class="casinoSearchWrapper">
                    <div class="ds-textfield ds-textfield-size--md ds-textfield-layout--fill">
                        <div class="ds-textfield__field">
                            <div class="ds-textfield__text">
                                <input type="text"
                                       class="ds-textfield__input searchInput games-search-input"
                                       id="gamesFilterSearchInput"
                                       placeholder="BGaming oyun ara..."
                                       aria-label="BGaming oyun ara"
                                       value="<?= htmlspecialchars($searchTerm, ENT_QUOTES); ?>"
                                       autocomplete="off">
                            </div>
                            <div class="ds-textfield__right">
                                <span class="CMSIconSVGWrapper ds-textfield__icon ds-textfield__icon--right searchInputIcon games-search-icon-btn"
                                      id="gamesFilterSearchClearBtn"
                                      title="Aramayi temizle" aria-label="Aramayi temizle" role="button" tabindex="0">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M15.0001 9.1665C15.0001 5.94484 12.3884 3.33317 9.16675 3.33317C5.94509 3.33317 3.33341 5.94484 3.33341 9.1665C3.33341 12.3882 5.94509 14.9998 9.16675 14.9998C10.7418 14.9998 12.1699 14.3745 13.2195 13.36C13.2398 13.3342 13.2624 13.3098 13.2862 13.286C13.31 13.2622 13.3344 13.2396 13.3603 13.2192C14.3748 12.1697 15.0001 10.7415 15.0001 9.1665ZM16.6667 9.1665C16.6667 10.9373 16.0516 12.5636 15.0253 13.8467L18.0893 16.9106L18.1462 16.9741C18.4132 17.3014 18.3944 17.7839 18.0893 18.089C17.7842 18.3941 17.3017 18.413 16.9744 18.146L16.9109 18.089L13.8469 15.0251C12.5639 16.0514 10.9376 16.6665 9.16675 16.6665C5.02461 16.6665 1.66675 13.3086 1.66675 9.1665C1.66675 5.02437 5.02461 1.6665 9.16675 1.6665C13.3089 1.6665 16.6667 5.02437 16.6667 9.1665Z"></path></svg>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="casinoRandomGameButtonWrapper">
                    <button type="button" class="ds-btn ds-btn-variant--transparent ds-btn-size--md ds-btn-radius--full ds-btn-appearance--filled random-game-btn" id="randomGameBtn" aria-disabled="false" aria-busy="false" title="Rastgele Oyun Oyna" aria-label="Rastgele Oyun Oyna">
                        <span class="ds-label ds-label--medium-regular btn__label">Rastgele Oyun Oyna</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="casinoProviderRow casinoProviderRow--mobile-inline casinoProviderRow--no-providers">
            <p class="casinoGameListTitle">BGAMING OYUNLARI</p>
            <span class="casinoGameListAllLink"><?= (int)$totalSlots ?> oyun</span>
        </div>

        <div class="casinoProviderAndGame slots-layout slots-layout--full-games" id="slotsLayout">
            <div class="casinoGameListBlock games-main" id="gamesScrollContainer">
                <div class="casinoGameItemWrp slot-oyun-listesi slots-games-container" id="casino_games_container" aria-label="BGaming oyun listesi">
                    <div class="casinoCategoryGames">
<?php if (empty($games)): ?>
                        <div class="empty-state">
                            <i class="fas fa-gamepad"></i>
                            <h3><?= htmlspecialchars($slotEmptyTitle, ENT_QUOTES, 'UTF-8') ?></h3>
                            <p><?= htmlspecialchars($slotEmptyText, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
<?php else: ?>
<?php   foreach ($games as $game): ?>
<?php
        $gId      = (string) ($game['game_id'] ?? '');
        $gName    = (string) ($game['game_name'] ?? '');
        $gCover   = (string) ($game['cover'] ?? '');
        $gCatId   = (string) ($game['id'] ?? '');
        $gPlay    = '/play?game_id=' . rawurlencode($gId) . '&mode=real&wallet=main';
        if (!$loggedIn) $gPlay = '#login';
        $gDemo    = '/play?game_id=' . rawurlencode($gId) . '&mode=fun';
        $gPlayJs  = htmlspecialchars(json_encode($gPlay, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
        $gIntent  = 'if(event){event.preventDefault();event.stopPropagation();}if(window.__bgamingHandlePlayIntent){window.__bgamingHandlePlayIntent(event,' . $gPlayJs . ');}else{window.location.href=' . $gPlayJs . ';}';
?>
                        <div class="casinoGameItemContent"
                             data-favorite-kind="bgaming"
                             data-catalog-id="<?= htmlspecialchars($gCatId, ENT_QUOTES, 'UTF-8') ?>"
                             data-game-id="<?= htmlspecialchars($gId, ENT_QUOTES, 'UTF-8') ?>"
                             onclick="<?= $gIntent ?>">
                            <span class="providerBadgeBlock" data-badge=""></span>
                            <div class="casinoGameItem">
                                <img alt="<?= htmlspecialchars($gName, ENT_QUOTES); ?>"
                                     loading="eager"
                                     src="<?= htmlspecialchars($gCover, ENT_QUOTES); ?>"
                                     data-src="<?= htmlspecialchars($gCover, ENT_QUOTES); ?>"
                                     class="casinoGameItemImage"
                                     title="<?= htmlspecialchars($gName, ENT_QUOTES); ?>"
                                     style="aspect-ratio: 44 / 31;"
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDMwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjMwMCIgaGVpZ2h0PSIyMDAiIHJ4PSI4IiBmaWxsPSIjMWExMTJlIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGRvbWluYW50LWJhc2VsaW5lPSJtaWRkbGUiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZpbGw9IiM2NjYiIGZvbnQtZmFtaWx5PSJzYW5zLXNlcmlmIiBmb250LXNpemU9IjE0Ij5ObyBJbWFnZTwvdGV4dD48L3N2Zz4='">
                                <i class="casinoGameItemFavBc bc-i-favorite"></i>
                                <div class="game-overlay">
                                    <div class="game-overlay-top"></div>
                                    <div class="game-title-wrap">
                                        <p class="game-title-text"><?= htmlspecialchars($gName, ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div class="game-actions">
                                        <a class="play-btn" href="<?= htmlspecialchars($gPlay, ENT_QUOTES, 'UTF-8') ?>"
                                           onclick="<?= $gIntent ?>">OYNA</a>
                                        <a class="demo-btn" href="<?= htmlspecialchars($gDemo, ENT_QUOTES, 'UTF-8') ?>"
                                           onclick="event.stopPropagation()">DEMO</a>
                                    </div>
                                </div>
                            </div>
                        </div>
<?php   endforeach; ?>
<?php endif; ?>
                    </div>
                    <div id="load-more-sentinel" aria-hidden="true"
                         style="height:1px;min-height:1px;opacity:0;pointer-events:none;overflow:hidden;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

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
