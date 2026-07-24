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
    <div class="slot-hero" id="slot-hero">
        <div class="slot-hero-slider">
            <div class="slot-hero-track" id="slot-hero-track" data-slider-category="bgaming">
                <div class="slot-hero-slide" data-slide-index="0">
                    <button class="slot-hero-slide-btn" disabled>
                        <span class="CMSIconSVGWrapper provider-logo-svg provider-logo-svg--bgaming" style="width:100%;max-width:320px;display:block;margin:0 auto;padding:12px 0;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 87 16" fill="currentColor" style="width:100%;height:auto;"><path d="M12.83 0v16H0V0h12.83zM5.471 2.924l-2.309.002a.197.197 0 0 0-.054.014v10.614h.146l2.347.005c.391 0 .782-.001 1.174-.005.368-.002.735-.041 1.095-.118.886-.198 1.573-.662 1.95-1.506a3.54 3.54 0 0 0 .253-1.839c-.1-.898-.539-1.757-1.623-2.034.045-.024.08-.046.119-.065.45-.22.805-.595.998-1.055.211-.504.278-1.057.194-1.597-.105-.865-.518-1.532-1.301-1.953a3.968 3.968 0 0 0-1.834-.453l-1.155-.01zM6.664 8.8c.508 0 .852.231 1.015.705.168.475.184.989.047 1.473-.138.492-.477.784-.991.81-.568.025-1.139.004-1.723.004V8.808c.044 0 .084-.008.126-.008h1.526zM5.012 4.6c.501.02 1 0 1.487.067.488.067.796.383.876.871.048.286.055.576.021.864-.07.556-.416.912-.988.973-.458.05-.925.01-1.396.01V4.6zM87 11.961c-.133.167-.256.344-.404.498-.74.777-1.662 1.121-2.72 1.14-.731.013-1.44-.086-2.09-.442-1.009-.55-1.55-1.429-1.724-2.547a6.883 6.883 0 0 1-.072-.981 204.33 204.33 0 0 1 0-3.245 4.325 4.325 0 0 1 .43-1.977c.522-1.024 1.37-1.592 2.49-1.753a4.55 4.55 0 0 1 1.934.117c1.171.34 1.826 1.152 2.044 2.337.04.224.052.453.08.688h-1.426c-.027-.196-.044-.392-.083-.588-.19-.94-.82-1.437-1.77-1.468-1.172-.038-1.771.637-2.012 1.366a3.075 3.075 0 0 0-.178.878c-.029 1.058-.026 2.125-.03 3.186-.01.334 0 .668.03 1 .031.318.1.63.205.93.325.887 1.07 1.374 2.024 1.36.405-.007.806-.046 1.16-.27.119-.076.23-.163.33-.26a.873.873 0 0 0 .3-.706c-.024-.643-.008-1.293-.008-1.931v-.164H83.7V8.036H87v3.925zM25.645 0c0 .055.014.117.014.166V15.82c0 .06-.009.117-.014.18h-.788V0h.788zM38.69 0v16h-.787c0-.064-.015-.13-.015-.194V.18c0-.06.01-.117.015-.18h.787zM50.93 16c0-.064-.014-.13-.014-.194V.18c0-.06.009-.117.014-.18h.788v16h-.788zM64.749 0v15.808a1.674 1.674 0 0 1-.024.192h-.753a1.439 1.439 0 0 1-.025-.192V0h.802zm13.016 0c0 .06.014.117.014.18v15.64c0 .06-.009.117-.014.18h-.788V0h.788zM57.13 13.6V3h1.404v10.6H57.13zM22.014 5.593h-1.428c-.055 0-.1.045-.1.1v4.413c0 .055.045.1.1.1h1.428c.055 0 .1-.045.1-.1V5.693c0-.055-.045-.1-.1-.1z"/></svg>
                        </span>
                    </button>
                </div>
            </div>
            <div class="slot-hero-dots">1 / 1</div>
        </div>
    </div>
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
                                       placeholder="BGaming oyun ara..."
                                       aria-label="BGaming oyun ara"
                                       value="<?= htmlspecialchars($searchTerm, ENT_QUOTES); ?>"
                                       autocomplete="off">
                            </div>
                            <div class="ds-textfield__right">
                                <span class="CMSIconSVGWrapper ds-textfield__icon ds-textfield__icon--right searchInputIcon games-search-icon-btn"
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

        <div class="casinoProviderRow casinoProviderRow--no-providers">
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
