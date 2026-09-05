/**
 * @dynamic-file
 */
(function() {
    'use strict';

    /* ── Sticky top: footer.js __syncHeaderStickyTop ile aynı (menü dahil alt kenar) ── */
    var HEADER_FALLBACK_PX = 126;

    function updateStickyOffsets() {
        if (typeof window.__syncHeaderStickyTop === 'function') {
            window.__syncHeaderStickyTop();
        } else {
            var header = document.querySelector('header.headBar');
            var menu = header && header.querySelector('.mainMenu');
            var bottom = menu ? menu.getBoundingClientRect().bottom : (header ? header.getBoundingClientRect().bottom : 0);
            var px = bottom > 0 ? Math.ceil(bottom) : HEADER_FALLBACK_PX;
            document.documentElement.style.setProperty('--header-sticky-top', px + 'px');
        }

        var catTabs = document.querySelector('.category-tabs-wrapper');
        if (catTabs) {
            document.documentElement.style.setProperty('--category-tabs-height', catTabs.offsetHeight + 'px');
        }
    }

    function runAfterLayout(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                requestAnimationFrame(function() {
                    requestAnimationFrame(fn);
                });
            });
        } else {
            requestAnimationFrame(function() {
                requestAnimationFrame(fn);
            });
        }
    }
    runAfterLayout(updateStickyOffsets);
    window.addEventListener('resize', updateStickyOffsets);

    function getCasinoGamesContainer(root) {
        var scope = root || document;
        return scope.querySelector('#casino_games_container') ||
            document.getElementById('casino_games_container') ||
            document.getElementById('slotsGamesContainer') ||
            document.getElementById('gamesScrollContainer');
    }

    function getCasinoCategoryGames(root) {
        var gamesContainer = getCasinoGamesContainer(root);
        return (gamesContainer && (
                gamesContainer.querySelector(':scope > .casinoGamesList') ||
                gamesContainer.querySelector(':scope > .casinoCategoryGames')
            )) ||
            (root || document).querySelector('#casino_games_container > .casinoGamesList') ||
            (root || document).querySelector('#casino_games_container > .casinoCategoryGames') ||
            document.getElementById('game-grid');
    }

    function getSlotSearchInput(root) {
        var scope = root || document;
        return scope.querySelector('.casinoGameProviderFilters .games-search-input') ||
            scope.querySelector('#gamesFilterSearchInput') ||
            scope.querySelector('.casinoGameListBlockHeader .casinoInputWrp .searchInputWrp input.searchInput') ||
            scope.querySelector('.casinoGameListBlockHeader input.searchInput') ||
            scope.querySelector('#searchModalInput') ||
            document.getElementById('searchModalInput');
    }

    /* ── Slot üst: JACKPOT | KAZANANLAR sekmeleri ── */
    function initSlotHeroTabs() {
        var root = document.querySelector('[data-slot-hero-tabs]');
        if (!root) return;
        var tabs = root.querySelectorAll('.slot-hero-tab[data-slot-hero-tab]');
        var panels = root.querySelectorAll('.slot-hero-tabpanel[data-slot-hero-panel]');
        if (!tabs.length || !panels.length) return;

        function activate(key) {
            tabs.forEach(function (t) {
                var on = t.getAttribute('data-slot-hero-tab') === key;
                t.classList.toggle('slot-hero-tab--active', on);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            panels.forEach(function (p) {
                var on = p.getAttribute('data-slot-hero-panel') === key;
                p.classList.toggle('slot-hero-tabpanel--active', on);
                if (on) {
                    p.removeAttribute('hidden');
                } else {
                    p.setAttribute('hidden', '');
                }
            });
        }

        root.addEventListener('click', function (e) {
            var tab = e.target.closest('.slot-hero-tab[data-slot-hero-tab]');
            if (!tab || !root.contains(tab)) return;
            var key = tab.getAttribute('data-slot-hero-tab');
            if (!key || tab.classList.contains('slot-hero-tab--active')) return;
            e.preventDefault();
            activate(key);
        });
    }

    /* ── Slot sayfası: Panel header altında sabitken sayfada herhangi bir yerde scroll = oyun listesini kaydır ── */
    function initSlotScrollLock() {
        var slotRoot = document.querySelector('.slot-page-root');
        // CM622 desktop lobby / filtered grid uses normal document scroll.
        // Locking page scroll here clips the list after search/provider filters
        // ("sayfa yarım kalıyor") because #casino_games_container is not a scrollport.
        if (slotRoot && (
            slotRoot.classList.contains('slot-page-root--cm622')
            || slotRoot.querySelector('.casinoGamesList')
            || document.getElementById('casinoLobbySections')
        )) {
            return;
        }
        var stickyBar = document.querySelector('.slots-sticky-bar') || document.querySelector('.slots-filter-and-games');
        var gamesScrollEl = getCasinoGamesContainer(slotRoot);
        var headerEl = document.querySelector('header.headBar');
        if (!stickyBar || !gamesScrollEl || !headerEl) return;

        // Header yüksekliğini CSS değişkeni ile senkron tut (header bar sınırını doğru hesapla)
        var cssHeaderTop = parseFloat(
            getComputedStyle(document.documentElement).getPropertyValue('--header-sticky-top') || '0'
        );
        var headerHeight = cssHeaderTop > 0 ? cssHeaderTop : headerEl.offsetHeight;
        var maxScrollY = 0;

        function updateMaxScrollY() {
            if (!stickyBar) return;
            var rect = stickyBar.getBoundingClientRect();
            if (rect.top > headerHeight + 5) {
                maxScrollY = Math.max(0, window.scrollY + rect.top - headerHeight);
            }
        }

        function clampPageScroll() {
            if (window.scrollY > maxScrollY) {
                window.scrollTo(0, maxScrollY);
            }
        }

        window.addEventListener('scroll', function() {
            updateMaxScrollY();
            clampPageScroll();
        }, { passive: true });

        var providersSidebarEl = document.getElementById('providersSidebar');
        var categoryTabsWrapper = document.querySelector('.category-tabs-wrapper');

        window.addEventListener('wheel', function(e) {
            updateMaxScrollY();
            var rect = stickyBar.getBoundingClientRect();
            var stickyBarStuck = rect.top <= headerHeight + 2;
            if (!stickyBarStuck) return;
            /* Sağlayıcılar alanında scroll: oyunları değil sağlayıcı listesini kaydır */
            if (providersSidebarEl && providersSidebarEl.contains(e.target)) return;
            /* Kategori alanında scroll: oyunları değil kategorileri kaydır */
            if (categoryTabsWrapper && categoryTabsWrapper.contains(e.target)) return;
            /* Panel sabitken diğer alanlarda scroll = oyun listesini kaydır */
            var scrollDown = e.deltaY > 0;
            var gameScrollTop = gamesScrollEl.scrollTop;
            var gameScrollHeight = gamesScrollEl.scrollHeight - gamesScrollEl.clientHeight;

            if (scrollDown && gameScrollHeight > 0) {
                e.preventDefault();
                gamesScrollEl.scrollTop = Math.min(gamesScrollEl.scrollTop + e.deltaY, gameScrollHeight);
            } else if (!scrollDown && gameScrollTop > 0) {
                e.preventDefault();
                gamesScrollEl.scrollTop = Math.max(0, gamesScrollEl.scrollTop + e.deltaY);
            }
            /* Oyun listesi tepede/değildeyken sayfa scroll’u (yukarı çıkma) serbest bırakılır. */
        }, { passive: false });

        updateMaxScrollY();
        if (maxScrollY === 0) {
            var initialRect = stickyBar.getBoundingClientRect();
            maxScrollY = Math.max(0, initialRect.top + window.scrollY - headerHeight);
        }
    }

    /* ── Oyunlar scrollbar’ı: sitenin en sağında, site scroll’u gibi; #slotsGamesContainer ile senkron ── */
    function initSlotEdgeScrollbar() {
        var container = getCasinoGamesContainer(document.querySelector('.slot-page-root'));
        var rail = document.getElementById('slotGamesScrollbarRail');
        var thumb = document.getElementById('slotGamesScrollbarThumb');
        if (!container || !rail || !thumb) return;

        function updateScrollbar() {
            var sh = container.scrollHeight;
            var ch = container.clientHeight;
            if (sh <= ch) {
                rail.setAttribute('aria-hidden', 'true');
                rail.classList.remove('is-active');
                return;
            }
            rail.removeAttribute('aria-hidden');
            rail.classList.add('is-active');
            var railRect = rail.getBoundingClientRect();
            var railHeight = railRect.height;
            var maxScroll = sh - ch;
            var thumbHeight = Math.max(40, (ch / sh) * railHeight);
            var thumbTop = maxScroll > 0 ? (container.scrollTop / maxScroll) * (railHeight - thumbHeight) : 0;
            thumb.style.height = thumbHeight + 'px';
            thumb.style.transform = 'translateY(' + thumbTop + 'px)';
        }

        container.addEventListener('scroll', updateScrollbar);
        window.addEventListener('resize', updateScrollbar);
        runAfterLayout(updateScrollbar);

        thumb.addEventListener('mousedown', function(e) {
            e.preventDefault();
            thumb.classList.add('dragging');
            var startY = e.clientY;
            var startScrollTop = container.scrollTop;
            var railRect = rail.getBoundingClientRect();
            var railHeight = railRect.height;
            var maxScroll = container.scrollHeight - container.clientHeight;
            if (maxScroll <= 0) return;

            function onMove(e) {
                var deltaY = e.clientY - startY;
                var ratio = railHeight > 0 ? deltaY / railHeight : 0;
                container.scrollTop = Math.max(0, Math.min(maxScroll, startScrollTop + ratio * maxScroll));
            }
            function onUp() {
                thumb.classList.remove('dragging');
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            }
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });

        rail.addEventListener('mousedown', function(e) {
            if (e.target !== rail) return;
            var railRect = rail.getBoundingClientRect();
            var y = e.clientY - railRect.top;
            var maxScroll = container.scrollHeight - container.clientHeight;
            if (maxScroll <= 0) return;
            var ratio = y / railRect.height;
            container.scrollTop = Math.max(0, Math.min(maxScroll, ratio * maxScroll));
        });

        window.refreshSlotEdgeScrollbar = updateScrollbar;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initSlotHeroTabs();
            initSlotScrollLock();
            initSlotEdgeScrollbar();
        });
    } else {
        initSlotHeroTabs();
        initSlotScrollLock();
        initSlotEdgeScrollbar();
    }

    const config = window.SLOT_CONFIG || { currentPage: 1, nextPage: 2, pageSize: 30, loggedIn: false, search: '', providers: [], sort: '', totalSlots: 0, remainingGames: 0, showLoadMore: false };
    const PAGE_SIZE = Math.min(100, Math.max(1, config.pageSize || 30));
    const API_ENDPOINT = config.apiEndpoint || '/slot_api.php';
    const API_ADAPTER = config.apiAdapter || 'slot_api';
    const API_GAME_TYPE = config.gameType != null ? String(config.gameType) : '';
    const API_EXTRA_PARAMS = config.apiParams && typeof config.apiParams === 'object' ? config.apiParams : {};
    const FAVORITE_KIND = (function () {
        if (API_EXTRA_PARAMS.source === 'bgaming') return 'bgaming';
        if (String(API_GAME_TYPE) === '1') return 'live';
        return 'slot';
    })();
    const ACTION_BUTTONS = config.actionButtons === true;
    const DESKTOP_LOBBY = config.desktopLobby === true;
    const LOBBY_MODE = config.lobbyMode === true;
    const EMPTY_TITLE = config.emptyTitle || 'Slot oyunu bulunamadı';
    const EMPTY_TEXT = config.emptyText || 'Arama teriminizi değiştirmeyi veya filtreleri temizlemeyi deneyin';
    const FETCH_TIMEOUT_MS = 15000;
    const PRELOAD_FIRST_N = 6;
    const PRELOAD_TIMEOUT_MS = 1200;
    const MAX_EMPTY_FILTER_PAGES = 3;
    var fetchAbort = null;
    var emptyFilterPages = 0;
    var loadMoreArmed = true;

    /* ── State (sayfa yenilemeden tutulur) ── */
    let state = {
        search: config.search || '',
        providers: Array.isArray(config.providers) ? config.providers : [],
        sort: config.sort || '',
        nextPage: config.nextPage != null ? config.nextPage : ((config.currentPage || 1) + 1),
        totalSlots: config.totalSlots || 0,
        showLoadMore: config.showLoadMore !== undefined ? config.showLoadMore : false,
        remainingGames: config.remainingGames !== undefined ? config.remainingGames : 0,
        isLoadingMore: false
    };
    var slotLoggedIn = !!config.loggedIn;

    function runtimeMemberLoggedIn() {
        var Shared = window.BetcoAuthShared || {};
        if (Shared && typeof Shared.runtimeSessionLoggedIn === 'function' && Shared.runtimeSessionLoggedIn()) {
            return true;
        }
        if (Shared && typeof Shared.getMemberJwt === 'function' && Shared.getMemberJwt() !== '') {
            return true;
        }
        if (window.__USER_LOGGED_IN__ === true || window.__HAS_MEMBER_JWT__ === true) {
            return true;
        }
        return slotLoggedIn;
    }

    /* ── DOM refs (oyun arama: .slot-page-root içinde — header panellerindeki aynı id’lerle çakışmasın) ── */
    const slotPageRoot       = document.querySelector('.slot-page-root');
    const searchInput        = getSlotSearchInput(slotPageRoot);
    const searchClearBtn     = slotPageRoot ? (slotPageRoot.querySelector('#gamesFilterSearchClearBtn') || slotPageRoot.querySelector('.casinoGameProviderFilters .games-search-icon-btn') || slotPageRoot.querySelector('#searchClearBtn')) : document.getElementById('searchClearBtn');
    const gamesSearchExpandEl = slotPageRoot ? slotPageRoot.querySelector('#gamesSearchExpand') : document.getElementById('gamesSearchExpand');
    let searchDebounceTimer  = null;
    const SEARCH_DEBOUNCE_MS = document.body.classList.contains('mobile-site') ? 350 : 600;
    const providerSearchInput = document.getElementById('providerSearchInput');
    const providersSidebar   = document.getElementById('providersSidebar');
    const sidebarProvidersList = document.getElementById('sidebarProvidersList');
    const viewModuleBtn      = document.getElementById('viewModuleBtn');
    const providerSheetGridBtn = document.getElementById('providerSheetGridBtn');
    const providerSheetBackBtn = document.getElementById('providerSheetBackBtn');
    const providerSheetApplyBtn = document.getElementById('providerSheetApplyBtn');
    const catArrowLeft       = document.getElementById('slotCatArrowLeft')
        || document.getElementById('catArrowLeft');
    const catArrowRight      = document.getElementById('slotCatArrowRight')
        || document.getElementById('catArrowRight');
    const catArrowShadowLeft = document.getElementById('slotCatArrowShadowLeft');
    const catArrowShadowRight = document.getElementById('slotCatArrowShadowRight');
    // CM622 HorizontalScroll: overflow:hidden viewport + translateX on inner track.
    const catScrollInner     = document.getElementById('slotCategoryRailInner')
        || document.querySelector('.casinoNavigationAndFilters .casinoCategories .horizontal-scroll__inner');
    const catScrollViewport  = document.getElementById('slotCategoryRail')
        || document.querySelector('.casinoNavigationAndFilters .casinoCategories.horizontal-scroll')
        || document.querySelector('.casinoNavigationAndFilters .casinoCategories');
    const catScroll          = document.getElementById('categoryTabsScroll')
        || catScrollViewport
        || catScrollInner;
    const categoryRailOffset = { value: 0, max: 0 };
    const gameGrid           = getCasinoCategoryGames(slotPageRoot);
    const activeFiltersRow   = document.querySelector('.active-filters-row');
    const activeFiltersBox   = document.getElementById('active-filters-box');

    /* Arama input artık right-sidebar içinde (header); başlangıç değerini state ile senkronize et */
    if (searchInput) searchInput.value = state.search;

    const PLACEHOLDER_IMG = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDMwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjMwMCIgaGVpZ2h0PSIyMDAiIHJ4PSI4IiBmaWxsPSIjMWExMTJlIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGRvbWluYW50LWJhc2VsaW5lPSJtaWRkbGUiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZpbGw9IiM2NjYiIGZvbnQtZmFtaWx5PSJzYW5zLXNlcmlmIiBmb250LXNpemU9IjE0Ij5ObyBJbWFnZTwvdGV4dD48L3N2Zz4=';

    function preferCompatibleCover(url) {
        return String(url || '').trim();
    }

    function dedupeCoverFallbacks(fallbacks) {
        if (!Array.isArray(fallbacks) || !fallbacks.length) return [];
        var unique = [];
        fallbacks.forEach(function(url) {
            var value = String(url || '').trim();
            if (value && unique.indexOf(value) === -1) unique.push(value);
        });
        return unique;
    }

    function expandCoverFormatFallbacks(fallbacks) {
        var base = dedupeCoverFallbacks(fallbacks);
        if (!base.length) return [];
        var out = base.slice();
        var exts = ['png', 'avif', 'webp', 'jpg'];
        base.forEach(function(url) {
            var match = String(url).match(/^(.*)\.(avif|webp|jpe?g|gif|png)(\?.*)?$/i);
            if (!match) return;
            exts.forEach(function(ext) {
                var alt = match[1] + '.' + ext + (match[3] || '');
                if (out.indexOf(alt) === -1) out.push(alt);
            });
        });
        return out;
    }

    function pickBestCoverSource(game) {
        if (!game || typeof game !== 'object') return '';
        var primary = String(game.image_url || game.cover || game.thumbnail_url || game.banner || '').trim();
        var fallbacks = expandCoverFormatFallbacks(Array.isArray(game.cover_fallbacks) ? game.cover_fallbacks
            : (Array.isArray(game.image_fallbacks) ? game.image_fallbacks : []));
        if (primary) return primary;
        return fallbacks.length ? fallbacks[0] : '';
    }

    function gameThumbError(img) {
        if (!img) return;
        var fallbacks = [];
        try {
            var raw = img.getAttribute('data-fallbacks');
            if (raw) fallbacks = JSON.parse(raw);
        } catch (e) {}
        fallbacks = dedupeCoverFallbacks(fallbacks);
        var currentSrc = String(img.getAttribute('src') || img.src || '').trim();
        var idx = parseInt(img.getAttribute('data-fallback-idx') || '0', 10);
        for (var i = idx + 1; i < fallbacks.length; i++) {
            var url = String(fallbacks[i] || '').trim();
            if (!url || url === currentSrc) continue;
            img.setAttribute('data-fallbacks', JSON.stringify(fallbacks));
            img.setAttribute('data-fallback-idx', String(i));
            img.src = url;
            return;
        }
        img.onerror = null;
        img.onload = null;
        img.src = PLACEHOLDER_IMG;
    }
    function gameThumbLoaded(img) {
        if (!img || img.naturalWidth > 0) return;
        gameThumbError(img);
    }
    window.__gameThumbError = gameThumbError;
    window.__gameThumbLoaded = gameThumbLoaded;

    function buildApiUrl(append) {
        const params = new URLSearchParams();
        Object.keys(API_EXTRA_PARAMS).forEach(function(key) {
            if (API_EXTRA_PARAMS[key] !== undefined && API_EXTRA_PARAMS[key] !== null && API_EXTRA_PARAMS[key] !== '') {
                params.set(key, String(API_EXTRA_PARAMS[key]));
            }
        });
        if (state.search) params.set('search', state.search);
        params.set('limit', String(PAGE_SIZE));
        params.set('page', String(append ? state.nextPage : 1));
        if (API_ADAPTER === 'member_api_games') {
            if (API_GAME_TYPE !== '') params.set('game_type', API_GAME_TYPE);
        }
        writeProvidersParam(params, state.providers);
        if (state.sort) params.set('sort', state.sort);
        return API_ENDPOINT + '?' + params.toString();
    }

    /** Clean query: providers=PragmaticPlay,SA-Gaming (no [] / %5B%5D / +). */
    function providerUrlToken(name) {
        return String(name || '').trim().replace(/\s+/g, '-');
    }

    function writeProvidersParam(params, providers) {
        params.delete('providers');
        params.delete('providers[]');
        var list = Array.isArray(providers)
            ? providers.map(providerUrlToken).filter(Boolean)
            : [];
        if (list.length > 0) {
            params.set('providers', list.join(','));
        }
    }

    function buildProvidersQueryValue(providers) {
        return (Array.isArray(providers) ? providers : [])
            .map(providerUrlToken)
            .filter(Boolean)
            .map(function (token) { return encodeURIComponent(token); })
            .join(',');
    }

    function playUrlReal(gameId, gameType) {
        if (window.MetropolPlayUrl && typeof window.MetropolPlayUrl.real === 'function') {
            return window.MetropolPlayUrl.real(gameId, 'main');
        }
        var id = String(gameId || '');
        return '/play?game_id=' + encodeURIComponent(id).replace(/%3A/gi, ':') + '&mode=real&wallet=main';
    }

    function resolveLaunchGameId(game) {
        if (!game || typeof game !== 'object') {
            return '';
        }
        var gid = String(game.game_id || game.gameId || '').trim();
        if (gid.indexOf('aggregator:') === 0 || gid.indexOf('bgaming:') === 0) {
            return gid;
        }
        if (gid.indexOf(':') !== -1) {
            return gid;
        }
        var source = String(game.source || '').trim().toLowerCase();
        var productCode = String(game.product_code || game.provider_code || '').trim();
        var gameCode = String(game.game_code || game.slug || '').trim();
        if (source === 'aggregator' && productCode !== '' && gameCode !== '') {
            return 'aggregator:' + productCode + ':' + gameCode;
        }
        if (source === 'bgaming' && gameCode !== '') {
            return 'bgaming:' + gameCode;
        }
        var slug = String(game.slug || '').trim();
        if (slug !== '') {
            return slug;
        }
        return gid;
    }

    function playUrlFun(gameId) {
        if (window.MetropolPlayUrl && typeof window.MetropolPlayUrl.fun === 'function') {
            var funUrl = String(window.MetropolPlayUrl.fun(gameId) || '');
            if (funUrl && funUrl.indexOf('demo=') === -1) {
                funUrl += (funUrl.indexOf('?') === -1 ? '?' : '&') + 'demo=1';
            }
            return funUrl;
        }
        var id = String(gameId || '');
        return '/play?game_id=' + encodeURIComponent(id).replace(/%3A/gi, ':') + '&mode=fun&demo=1';
    }

    function handleDemoIntent(event, url) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        var target = String(url || '').trim();
        if (!target) {
            return;
        }
        // Demo: no login gate, no wallet picker — same path as home DEMO.
        openPlayUrl(target);
    }

    window.__slotHandleDemoIntent = handleDemoIntent;

    function playTargetUrl(game) {
        var gameId = resolveLaunchGameId(game);
        var gameType = game && typeof game === 'object' ? String(game.game_type || '') : '';
        var play = playUrlReal(gameId, gameType);
        return play;
    }

    function isMobilePlayLaunchMode() {
        var hasMobileClass = !!(document.body && document.body.classList.contains('mobile-site'));
        if (hasMobileClass) {
            return true;
        }
        var hasTouch = (navigator.maxTouchPoints || 0) > 0;
        var narrowViewport = !!(window.matchMedia && window.matchMedia('(max-width: 1024px)').matches);
        return hasTouch && narrowViewport;
    }

    function openPlayUrl(url) {
        var targetUrl = String(url || '');
        if (isMobilePlayLaunchMode()) {
            try {
                var parsed = new URL(targetUrl, window.location.origin);
                parsed.searchParams.set('open_mode', 'redirect');
                targetUrl = parsed.pathname + parsed.search + parsed.hash;
            } catch (e) {
                targetUrl += (targetUrl.indexOf('?') === -1 ? '?' : '&') + 'open_mode=redirect';
            }
        }
        if (window.MetropolPlayUrl && typeof window.MetropolPlayUrl.canonicalize === 'function') {
            targetUrl = window.MetropolPlayUrl.canonicalize(targetUrl);
        } else {
            targetUrl = targetUrl.replace(/(game_id=)([^&]*)/i, function (_, p, raw) {
                try {
                    return p + encodeURIComponent(decodeURIComponent(String(raw).replace(/\+/g, ' '))).replace(/%3A/gi, ':');
                } catch (err) {
                    return p + String(raw).replace(/%3A/gi, ':');
                }
            });
        }
        window.location.href = targetUrl;
    }

    function openLoginModal() {
        if (typeof window.__openLoginModal === 'function') {
            window.__openLoginModal();
            return;
        }
        if (window.MaltabetAuth && typeof window.MaltabetAuth.showLoginModal === 'function') {
            window.MaltabetAuth.showLoginModal();
            return;
        }
        var loginBtn = document.getElementById('Giris');
        if (loginBtn) {
            loginBtn.click();
        }
    }

    function handlePlayIntent(event, url) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        if (runtimeMemberLoggedIn()) {
            launchPlayUrl(url);
            return;
        }
        var Shared = window.BetcoAuthShared || {};
        if (Shared && typeof Shared.hydrateMemberJwt === 'function') {
            Shared.hydrateMemberJwt().then(function () {
                if (runtimeMemberLoggedIn()) {
                    launchPlayUrl(url);
                    return;
                }
                openLoginModal();
            }).catch(function () {
                openLoginModal();
            });
            return;
        }
        openLoginModal();
    }

    function activateGameCard(event, url) {
        if (event && event.target && event.target.closest) {
            if (event.target.closest('.play-btn, .demo-btn, .casinoBtnWrp a, .casinoGameItemFavBc, .casinoGameIconsFavoriteWrapper')) {
                return;
            }
        }
        // Direct launch on all surfaces — mobile overlay toggle was unstable
        // (pointer-events / double-handlers / missing mobile-site on apex hosts).
        handlePlayIntent(event, url);
    }

    window.__slotGameCardActivate = activateGameCard;

    function applyMobileActionButtonSizing() {
        // CM622 ds-btn stilleri orijinal CSS'ten gelir; inline override kullanma.
        return;
    }

    function realPlayClickJs(gameUrlJs) {
        return "if(event){event.preventDefault();event.stopPropagation();}window.__slotHandlePlayIntent&&window.__slotHandlePlayIntent(event,'" + gameUrlJs + "')";
    }

    function demoPlayClickJs(demoUrlJs) {
        return "if(event){event.preventDefault();event.stopPropagation();}window.__slotHandleDemoIntent&&window.__slotHandleDemoIntent(event,'" + demoUrlJs + "')";
    }

    function launchPlayUrl(url) {
        if (window.MaltabetWalletPicker && typeof window.MaltabetWalletPicker.launch === 'function') {
            window.MaltabetWalletPicker.launch(url, openPlayUrl);
            return;
        }
        openPlayUrl(url);
    }

    function renderGameItem(game) {
        const name = escapeHtml(game.game_name || '');
        const providerName = escapeHtml(String(game.provider || game.provider_name || '').trim());
        const fallbacks = expandCoverFormatFallbacks(Array.isArray(game.cover_fallbacks) ? game.cover_fallbacks : (Array.isArray(game.image_fallbacks) ? game.image_fallbacks : []));
        const coverSource = pickBestCoverSource(Object.assign({}, game, {
            cover_fallbacks: fallbacks,
            image_fallbacks: fallbacks
        }));
        const cover = escapeHtml(preferCompatibleCover(coverSource));
        const fallbackAttr = fallbacks.length ? ' data-fallbacks="' + escapeHtml(JSON.stringify(fallbacks)) + '" data-fallback-idx="0"' : '';
        const gameId = resolveLaunchGameId(game);
        const gameIdEsc = escapeHtml(gameId);
        const catalogIdRaw = game.id != null && String(game.id).trim() !== '' ? String(game.id) : '';
        const catalogAttr = catalogIdRaw !== '' ? ' data-catalog-id="' + escapeHtml(catalogIdRaw) + '"' : '';
        const gameUrl = playTargetUrl(game);
        const gameUrlJs = gameUrl.replace(/\\/g, '\\\\').replace(/'/g, '\\\'');
        const demoUrl = playUrlFun(gameId);
        const demoUrlJs = demoUrl.replace(/\\/g, '\\\\').replace(/'/g, '\\\'');
        const hasDemo = !(game.has_demo === false || game.has_demo === 0 || game.has_demo === '0');
        window.__slotOpenLoginModal = openLoginModal;
        window.__slotOpenPlayUrl = openPlayUrl;
        window.__slotLaunchPlayUrl = launchPlayUrl;
        window.__slotHandlePlayIntent = handlePlayIntent;
        window.__slotHandleDemoIntent = handleDemoIntent;
        const providerHtml = providerName
            ? '<span class="casinoGameItemProviderBc">' + providerName + '</span>'
            : '';
        const buttonsHtml = ACTION_BUTTONS
            ? (
                '<div class="casinoGameButtons">' +
                '<div class="casinoBtnWrp">' +
                '<a class="play-btn ds-btn ds-btn-variant--secondary ds-btn-size--sm ds-btn-radius--full ds-btn-appearance--filled" href="' + escapeHtml(gameUrl) + '" onclick="' + realPlayClickJs(gameUrlJs) + '">OYNA</a>' +
                '</div>' +
                (hasDemo
                    ? (
                        '<div class="casinoBtnWrp">' +
                        '<a class="demo-btn ds-btn ds-btn-variant--transparent ds-btn-size--sm ds-btn-radius--full ds-btn-appearance--filled" href="' + escapeHtml(demoUrl) + '" onclick="' + demoPlayClickJs(demoUrlJs) + '">DEMO</a>' +
                        '</div>'
                    )
                    : '') +
                '</div>'
            )
            : '';
        const blockHtml =
            '<div class="casinoGameItemBlock">' +
            '<div class="casinoGameIconsWrp">' +
            '<div class="casinoGameIconsLeft"></div>' +
            '<div class="casinoGameIconsFavoriteWrapper">' +
            '<i class="casinoGameItemFavBc bc-i-favorite " aria-hidden="true"></i>' +
            '</div>' +
            '</div>' +
            '<div class="casinoGameItemLabelBc">' + name + providerHtml + '</div>' +
            buttonsHtml +
            '</div>';
        return (
            '<div class="casinoGameItemContent casinoGameItemContent--regular" data-favorite-kind="' + escapeHtml(FAVORITE_KIND) + '" data-game-id="' + gameIdEsc + '"' + catalogAttr + ' onclick="window.__slotGameCardActivate&&window.__slotGameCardActivate(event,\'' + gameUrlJs + '\')">' +
            '<div class="casinoGameItem ">' +
            '<img alt="' + name + '" loading="lazy" decoding="async" referrerpolicy="no-referrer" src="' + cover + '" data-src="' + cover + '"' + fallbackAttr + ' class="casinoGameItemImage casinoGameItemImage--regular" title="' + name + '" onload="window.__gameThumbLoaded&&window.__gameThumbLoaded(this)" onerror="window.__gameThumbError&&window.__gameThumbError(this)">' +
            blockHtml +
            '</div>' +
            '</div>'
        );
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    window.__slotHandlePlayIntent = handlePlayIntent;

    function renderEmptyState() {
        return (
            '<div class="empty-state">' +
            '<i class="fas fa-gamepad"></i>' +
            '<h3>' + escapeHtml(EMPTY_TITLE) + '</h3>' +
            '<p>' + escapeHtml(EMPTY_TEXT) + '</p>' +
            '</div>'
        );
    }

    function isBgamingGame(game) {
        if (!game || typeof game !== 'object') return false;
        const provider = String(game.provider || game.provider_code || '').trim().toLowerCase();
        const source = String(game.source || '').trim().toLowerCase();
        return provider === 'bgaming' || source === 'bgaming';
    }

    function normalizeApiResponse(data) {
        if (API_ADAPTER !== 'member_api_games') {
            return data || {};
        }

        var inner = data && data.data ? data.data : {};
        var pagination = inner.pagination || {};
        var rawGames = Array.isArray(inner.games) ? inner.games : [];
        var games = rawGames.map(function(game) {
            var fallbacks = expandCoverFormatFallbacks(Array.isArray(game.cover_fallbacks) ? game.cover_fallbacks
                : (Array.isArray(game.image_fallbacks) ? game.image_fallbacks : []));
            var cover = preferCompatibleCover(pickBestCoverSource({
                cover: game.cover,
                image_url: game.image_url,
                thumbnail_url: game.thumbnail_url,
                banner: game.banner,
                cover_fallbacks: fallbacks,
                image_fallbacks: fallbacks
            }));
            var mapped = {
                id: game.id,
                game_id: resolveLaunchGameId(game),
                game_code: game.game_code || '',
                product_code: game.product_code || game.provider_code || '',
                game_type: game.game_type || '',
                game_name: game.name || game.game_name || '',
                cover: cover,
                cover_fallbacks: fallbacks,
                image_fallbacks: fallbacks,
                has_demo: game.has_demo,
                provider: game.provider || '',
                provider_code: game.provider_code || '',
                source: game.source || ''
            };
            return mapped;
        }).filter(function(game) {
            // BGaming stays off /slot; keep it on the dedicated /bgaming page.
            if (API_EXTRA_PARAMS.source === 'bgaming') {
                return true;
            }
            return !isBgamingGame(game);
        });
        var page = Number(pagination.page || 1);
        var perPage = Number(pagination.perPage || PAGE_SIZE);
        var rawCount = rawGames.length;
        var filteredCount = games.length;
        var total = Number(pagination.total || filteredCount);
        var hasNext = !!pagination.hasNext;
        var loadedCount = (page - 1) * perPage + filteredCount;
        var remaining = Math.max(0, total - loadedCount);
        var showLoadMore = hasNext && (filteredCount > 0 || rawCount > 0);

        return {
            ok: !!(data && data.success),
            games: games,
            totalSlots: total,
            remainingGames: remaining,
            showLoadMore: showLoadMore,
            nextPage: page + 1,
            page: page,
            perPage: perPage,
            rawCount: rawCount
        };
    }

    function renderSkeletonItems(count) {
        var html = '';
        var skeleton = (
            '<div class="casinoGameItemContent skeleton-loader-game-cube slot-skeleton-item"></div>'
        );
        for (var i = 0; i < count; i++) {
            html += skeleton;
        }
        return html;
    }

    function preloadImages(urls, timeoutMs, maxCount) {
        timeoutMs = timeoutMs || PRELOAD_TIMEOUT_MS;
        maxCount = maxCount == null ? PRELOAD_FIRST_N : maxCount;
        var slice = (urls || []).slice(0, maxCount);
        return Promise.all(slice.map(function(url) {
            if (!url) return Promise.resolve();
            return new Promise(function(resolve) {
                var img = new Image();
                img.referrerPolicy = 'no-referrer';
                var t = setTimeout(function() { resolve(); }, timeoutMs);
                img.onload = img.onerror = function() {
                    clearTimeout(t);
                    resolve();
                };
                img.src = url;
            });
        }));
    }

    function updateActiveFiltersRow() {
        if (!activeFiltersRow) return;
        const hasFilters = state.search || state.providers.length > 0;
        if (!hasFilters) {
            activeFiltersRow.style.display = 'none';
            if (activeFiltersBox) activeFiltersBox.style.display = 'none';
            return;
        }
        if (activeFiltersBox) activeFiltersBox.style.display = 'inline-flex';
        activeFiltersRow.style.display = 'flex';
        let html = '';
        if (state.search) {
            html += '<div class="active-filter-tag"><span>"' + escapeHtml(state.search) + '"</span><span class="remove" data-action="remove-search">×</span></div>';
        }
        state.providers.forEach(function(provider) {
            html += '<div class="active-filter-tag"><span>' + escapeHtml(provider) + '</span><span class="remove" data-action="remove-filter" data-provider="' + escapeHtml(provider) + '">×</span></div>';
        });
        activeFiltersRow.innerHTML = html;
        activeFiltersRow.querySelectorAll('[data-action="remove-search"]').forEach(function(el) {
            el.addEventListener('click', function() { setSearch(''); loadSlots(false); });
        });
        activeFiltersRow.querySelectorAll('[data-action="remove-filter"]').forEach(function(el) {
            const p = el.getAttribute('data-provider');
            el.addEventListener('click', function() { removeProvider(p); loadSlots(false); });
        });
    }

    function updateSidebarActive() {
        document.querySelectorAll('.sidebar-provider-item, .provider-chip').forEach(function(item) {
            if (item.getAttribute('data-provider-all') === '1') {
                item.classList.toggle('active', state.providers.length === 0 && !state.search);
                return;
            }
            const provider = item.getAttribute('data-provider');
            if (provider !== null) {
                item.classList.toggle('active', state.providers.indexOf(provider) !== -1);
            }
        });
    }

    function syncMobileFilterControls() {
        if (!document.body.classList.contains('mobile-site')) return;
        if (gamesSearchExpandEl) {
            gamesSearchExpandEl.classList.add('is-expanded');
            gamesSearchExpandEl.setAttribute('aria-expanded', 'true');
            var searchBar = gamesSearchExpandEl.querySelector('.games-search-bar');
            var searchField = gamesSearchExpandEl.querySelector('.games-search-input');
            if (searchBar) {
                searchBar.style.width = '100%';
                searchBar.style.flex = '1 1 auto';
            }
            if (searchField) {
                searchField.style.position = 'relative';
                searchField.style.opacity = '1';
                searchField.style.pointerEvents = 'auto';
                searchField.style.width = 'auto';
                searchField.style.height = 'auto';
                searchField.style.clip = 'auto';
                searchField.style.margin = '0';
            }
        }
        if (!mobileSidebarToggle) return;
        if (mobileSidebarToggle.classList.contains('ds-select')) {
            var originalCountNode = mobileSidebarToggle.querySelector('.mobile-sidebar-toggle__count');
            var originalProviderCount = state.providers.length;
            if (originalCountNode) {
                originalCountNode.textContent = originalProviderCount > 0 ? ('+' + originalProviderCount) : '';
                originalCountNode.style.display = originalProviderCount > 0 ? 'inline-flex' : 'none';
            }
            mobileSidebarToggle.setAttribute('title', originalProviderCount > 0 ? ('Sağlayıcılar +' + originalProviderCount) : 'Sağlayıcılar');
            mobileSidebarToggle.setAttribute('aria-label', originalProviderCount > 0 ? ('Sağlayıcılar +' + originalProviderCount) : 'Sağlayıcılar');
            return;
        }
        if (!mobileSidebarToggle.querySelector('.mobile-sidebar-toggle__pill')) {
            mobileSidebarToggle.innerHTML = '';
            var pill = document.createElement('span');
            pill.className = 'mobile-sidebar-toggle__pill';
            var icon = document.createElement('i');
            icon.className = 'fas fa-filter';
            icon.setAttribute('aria-hidden', 'true');
            var txt = document.createElement('span');
            txt.className = 'mobile-sidebar-toggle__pill-text';
            txt.textContent = 'Sağlayıcılar';
            pill.appendChild(icon);
            pill.appendChild(txt);
            mobileSidebarToggle.appendChild(pill);
        }
        var countNode = mobileSidebarToggle.querySelector('.mobile-sidebar-toggle__count');
        if (!countNode) {
            countNode = document.createElement('span');
            countNode.className = 'mobile-sidebar-toggle__count';
            countNode.id = 'mobileSidebarToggleCount';
            countNode.setAttribute('aria-hidden', 'true');
            mobileSidebarToggle.appendChild(countNode);
        }
        var providerCount = state.providers.length;
        if (countNode) {
            countNode.textContent = providerCount > 0 ? ('+' + providerCount) : '';
            countNode.style.display = providerCount > 0 ? 'inline-flex' : 'none';
        }
        mobileSidebarToggle.setAttribute('title', providerCount > 0 ? ('Sağlayıcılar +' + providerCount) : 'Sağlayıcılar');
        mobileSidebarToggle.setAttribute('aria-label', providerCount > 0 ? ('Sağlayıcılar +' + providerCount) : 'Sağlayıcılar');
    }

    function buildFilterPageUrl() {
        var url = new URL(window.location.href);
        var kept = [];
        url.searchParams.forEach(function (value, key) {
            if (key === 'search' || key === 'providers' || key === 'providers[]' || key === 'offset' || key === 'sort' || key === 'view' || key === 'page') {
                return;
            }
            kept.push(encodeURIComponent(key) + '=' + encodeURIComponent(value));
        });
        if (state.search) {
            kept.push('search=' + encodeURIComponent(state.search));
        }
        if (state.providers.length > 0) {
            // Join after encode so commas stay literal (not %2C); spaces become '-'.
            kept.push('providers=' + buildProvidersQueryValue(state.providers));
        }
        if (state.sort) {
            kept.push('sort=' + encodeURIComponent(state.sort));
        } else if (!LOBBY_MODE && DESKTOP_LOBBY && !state.search && state.providers.length === 0) {
            kept.push('view=all');
        }
        var qs = kept.join('&');
        return url.pathname + (qs ? ('?' + qs) : '') + url.hash;
    }

    function updateUrl() {
        window.history.replaceState({}, '', buildFilterPageUrl());
    }

    /** Lobby has no infinite grid — hard-navigate when filters change. */
    function navigateFromLobbyIfNeeded() {
        if (!LOBBY_MODE) return false;
        window.location.href = buildFilterPageUrl();
        return true;
    }

    function setSearch(val) {
        state.search = String(val).trim();
        state.nextPage = 2;
        if (searchInput) searchInput.value = state.search;
        syncMobileFilterControls();
    }

    function removeProvider(provider) {
        state.providers = state.providers.filter(function(p) { return p !== provider; });
        state.nextPage = 2;
        syncMobileFilterControls();
    }

    function clearFilters() {
        state.search = '';
        state.providers = [];
        state.nextPage = 2;
        if (searchInput) searchInput.value = '';
        syncMobileFilterControls();
        updateDrawerButtonStates();
    }

    function setSort(sortVal) {
        state.sort = sortVal || '';
        state.nextPage = 2;
    }

    function loadSlots(append) {
        if (!gameGrid) return;
        if (append && state.isLoadingMore) return;
        if (append) state.isLoadingMore = true;
        if (!append) emptyFilterPages = 0;

        var requestLimit = PAGE_SIZE;
        var url = buildApiUrl(append);

        if (!append) {
            if (fetchAbort) {
                try { fetchAbort.abort(); } catch (e) {}
            }
            gameGrid.innerHTML = renderSkeletonItems(PAGE_SIZE);
        } else {
            gameGrid.insertAdjacentHTML('beforeend', renderSkeletonItems(requestLimit));
        }

        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        if (!append) fetchAbort = controller;
        var timeoutId = setTimeout(function() {
            if (controller) controller.abort();
        }, FETCH_TIMEOUT_MS);

        var fetchOpts = { headers: { 'X-Requested-With': 'XMLHttpRequest' } };
        if (controller) fetchOpts.signal = controller.signal;

        fetch(url, fetchOpts)
            .then(function(res) { return res.json(); })
            .then(function(data) {
                clearTimeout(timeoutId);
                data = normalizeApiResponse(data);
                if (!data.ok) {
                    if (append) {
                        removeLastSkeletons(requestLimit);
                        state.isLoadingMore = false;
                    } else {
                        gameGrid.innerHTML = renderEmptyState();
                    }
                    return;
                }
                state.totalSlots = data.totalSlots || 0;
                state.remainingGames = data.remainingGames != null ? data.remainingGames : 0;
                state.showLoadMore = !!data.showLoadMore;
                if (data.nextPage != null) {
                    state.nextPage = data.nextPage;
                } else if (append && data.page != null) {
                    state.nextPage = (data.page || 1) + 1;
                }

                var games = data.games || [];
                if (games.length === 0) {
                    if (append) {
                        removeLastSkeletons(requestLimit);
                        // BGaming filtreyle boş sayfa: sınırlı otomatik atlama
                        if (data.showLoadMore && (data.rawCount || 0) > 0 && emptyFilterPages < MAX_EMPTY_FILTER_PAGES) {
                            emptyFilterPages += 1;
                            state.isLoadingMore = false;
                            loadSlots(true);
                            return;
                        }
                        state.showLoadMore = false;
                    } else {
                        gameGrid.innerHTML = renderEmptyState();
                    }
                    state.isLoadingMore = false;
                    if (!append) {
                        updateActiveFiltersRow();
                        updateSidebarActive();
                        syncMobileFilterControls();
                        updateUrl();
                    }
                    return;
                }

                emptyFilterPages = 0;
                var coverUrls = games.map(function(g) { return g.cover || ''; });
                preloadImages(coverUrls, PRELOAD_TIMEOUT_MS, PRELOAD_FIRST_N).then(function() {
                    if (append) {
                        removeLastSkeletons(requestLimit);
                        gameGrid.insertAdjacentHTML('beforeend', games.map(renderGameItem).join(''));
                        if (window.refreshSlotEdgeScrollbar) window.refreshSlotEdgeScrollbar();
                    } else {
                        gameGrid.innerHTML = games.map(renderGameItem).join('');
                        updateActiveFiltersRow();
                        updateSidebarActive();
                        syncMobileFilterControls();
                        updateUrl();
                    }
                    applyMobileActionButtonSizing();
                    state.isLoadingMore = false;
                });
            })
            .catch(function() {
                clearTimeout(timeoutId);
                state.isLoadingMore = false;
                if (!append) {
                    gameGrid.innerHTML = renderEmptyState();
                } else {
                    removeLastSkeletons(requestLimit);
                }
            });
    }

    function removeLastSkeletons(count) {
        if (!gameGrid) return;
        var skeletons = gameGrid.querySelectorAll('.skeleton-loader-game-cube.slot-skeleton-item');
        var toRemove = Math.min(count, skeletons.length);
        var list = Array.prototype.slice.call(skeletons, skeletons.length - toRemove);
        list.forEach(function(el) {
            if (el.parentNode) el.parentNode.removeChild(el);
        });
    }

    /* ── Inline Search: 600 ms debounce ile otomatik arama, X ile temizleme ── */
    function applySearch(value) {
        if (!searchInput && value === undefined) return;
        setSearch(value !== undefined ? value : searchInput.value);
        if (navigateFromLobbyIfNeeded()) return;
        loadSlots(false);
    }

    function clearSearch() {
        setSearch('');
        if (searchInput) searchInput.value = '';
        updateSearchBtnIcon();
        if (navigateFromLobbyIfNeeded()) return;
        loadSlots(false);
    }

    var searchClearBtnIcon = searchClearBtn ? (searchClearBtn.querySelector('#searchClearBtnIcon') || searchClearBtn.querySelector('i')) : null;
    function updateSearchBtnIcon() {
        var hasText = !!(searchInput && searchInput.value.trim().length > 0);
        var textField = searchInput && searchInput.closest ? searchInput.closest('.ds-textfield') : null;
        if (textField) {
            textField.classList.toggle('is-filled', hasText);
        }
        if (searchClearBtn) {
            searchClearBtn.classList.toggle('is-clearable', hasText);
            searchClearBtn.title = hasText ? 'Aramayı temizle' : 'Oyun ara';
            searchClearBtn.setAttribute('aria-label', hasText ? 'Aramayı temizle' : 'Oyun ara');
        }
        searchClearBtnIcon = searchClearBtn ? (searchClearBtn.querySelector('#searchClearBtnIcon') || searchClearBtn.querySelector('i')) : null;
        if (!searchClearBtnIcon) return;
        searchClearBtnIcon.className = hasText ? 'fas fa-times' : 'fas fa-search';
    }

    function scheduleSearch(value) {
        if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(function() {
            searchDebounceTimer = null;
            applySearch(value);
        }, SEARCH_DEBOUNCE_MS);
    }

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            updateSearchBtnIcon();
            scheduleSearch();
        });
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
                searchDebounceTimer = null;
                applySearch();
            }
        });
    }
    document.addEventListener('input', function(e) {
        var field = e.target && e.target.closest ? e.target.closest('.games-search-input') : null;
        if (!field || field === searchInput || (slotPageRoot && !slotPageRoot.contains(field))) return;
        setSearch(field.value);
        updateSearchBtnIcon();
        scheduleSearch(field.value);
    });
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter') return;
        var field = e.target && e.target.closest ? e.target.closest('.games-search-input') : null;
        if (!field || field === searchInput || (slotPageRoot && !slotPageRoot.contains(field))) return;
        if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
        searchDebounceTimer = null;
        setSearch(field.value);
        applySearch(field.value);
    });
    function syncGamesSearchExpandAria() {
        if (!gamesSearchExpandEl || !document.body.classList.contains('mobile-site')) return;
        gamesSearchExpandEl.setAttribute('aria-expanded', gamesSearchExpandEl.classList.contains('is-expanded') ? 'true' : 'false');
    }

    if (searchClearBtn) {
        searchClearBtn.addEventListener('click', function(e) {
            var expand = gamesSearchExpandEl;
            var mobileExpand = document.body.classList.contains('mobile-site') && expand;
            var cm622Search = !!(slotPageRoot && slotPageRoot.classList.contains('slot-page-root--cm622')
                && searchClearBtn.closest && searchClearBtn.closest('.casinoGameProviderFilters'));
            if (mobileExpand && !cm622Search) {
                if (!expand.classList.contains('is-expanded')) {
                    e.preventDefault();
                    expand.classList.add('is-expanded');
                    syncGamesSearchExpandAria();
                    if (searchInput) {
                        searchInput.focus();
                    }
                    return;
                }
            }
            if (searchInput && searchInput.value.trim().length > 0) {
                e.preventDefault();
                clearSearch();
                if (searchInput) searchInput.focus();
            }
        });
    }
    document.addEventListener('click', function(e) {
        var btn = e.target && e.target.closest ? e.target.closest('.games-search-icon-btn') : null;
        if (!btn || btn === searchClearBtn || (slotPageRoot && !slotPageRoot.contains(btn))) return;
        var wrapper = btn.closest('.casinoSearchWrapper, .games-search-expand, .casinoInputWrp') || slotPageRoot || document;
        var field = wrapper.querySelector('.games-search-input');
        if (!field) return;
        if (field.value.trim().length > 0) {
            field.value = '';
            setSearch('');
            updateSearchBtnIcon();
            loadSlots(false);
        } else {
            field.focus();
        }
    });
    updateSearchBtnIcon();

    /* ── Provider search (sidebar) – oyun arama kutusu gibi: sağda ikon, metin varken çarpı ── */
    var providerSearchClearBtn = document.getElementById('providerSearchClearBtn');
    var providerSearchClearBtnIcon = document.getElementById('providerSearchClearBtnIcon');
    function updateProviderSearchBtnIcon() {
        if (!providerSearchClearBtn || !providerSearchInput) return;
        var hasText = providerSearchInput.value.trim().length > 0;
        // Keep CM622 SVG icon; only swap FA class when legacy <i> is present.
        if (providerSearchClearBtnIcon && providerSearchClearBtnIcon.tagName === 'I') {
            providerSearchClearBtnIcon.className = hasText ? 'fas fa-times' : 'fas fa-search';
        }
        providerSearchClearBtn.classList.toggle('is-clear', hasText);
        providerSearchClearBtn.title = hasText ? 'Aramayı temizle' : 'Sağlayıcı ara';
        providerSearchClearBtn.setAttribute('aria-label', hasText ? 'Aramayı temizle' : 'Sağlayıcı ara');
    }
    function syncProviderSearchFieldState() {
        if (!providerSearchInput) return;
        var field = providerSearchInput.closest('.ds-textfield');
        if (!field) return;
        field.classList.toggle('is-filled', providerSearchInput.value.trim().length > 0);
    }
    if (providerSearchInput) {
        providerSearchInput.addEventListener('input', function() {
            updateProviderSearchBtnIcon();
            syncProviderSearchFieldState();
            var q = providerSearchInput.value.toLowerCase().trim();
            var providerItems = sidebarProvidersList
                ? sidebarProvidersList.querySelectorAll('.sidebar-provider-item[data-provider], .providerItemsInner[data-provider]')
                : document.querySelectorAll('.sidebar-provider-item[data-provider], .providerItemsInner[data-provider]');
            providerItems.forEach(function(item) {
                var name = (item.getAttribute('data-provider') || item.dataset.provider || item.textContent || '').toLowerCase();
                item.style.display = name.indexOf(q) !== -1 ? '' : 'none';
            });
        });
        syncProviderSearchFieldState();
    }
    if (providerSearchClearBtn) {
        providerSearchClearBtn.addEventListener('click', function() {
            if (providerSearchInput && providerSearchInput.value.trim().length > 0) {
                providerSearchInput.value = '';
                providerSearchInput.focus();
                updateProviderSearchBtnIcon();
                syncProviderSearchFieldState();
                var providerItems = sidebarProvidersList
                    ? sidebarProvidersList.querySelectorAll('.sidebar-provider-item[data-provider], .providerItemsInner[data-provider]')
                    : document.querySelectorAll('.sidebar-provider-item[data-provider], .providerItemsInner[data-provider]');
                providerItems.forEach(function(item) {
                    item.style.display = '';
                });
            }
        });
    }
    updateProviderSearchBtnIcon();

    /* ── Sağlayıcı paneli: mobilde header altı tam genişlik sheet; masaüstünde dar sütun ── */
    function isMobileProviderSidebar() {
        return !!(document.body && document.body.classList.contains('mobile-site'));
    }

    function syncProviderSidebarAria() {
        if (!providersSidebar) return;
        if (isMobileProviderSidebar()) {
            providersSidebar.setAttribute('aria-hidden', providersSidebar.classList.contains('mobile-open') ? 'false' : 'true');
        } else {
            providersSidebar.removeAttribute('aria-hidden');
        }
    }

    function ensureSidebarOverlay() {
        var overlay = document.querySelector('.sidebar-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            document.body.appendChild(overlay);
            overlay.addEventListener('click', closeProviderSheet);
        }
        return overlay;
    }

    var providerDrawerHomeParent = null;
    var providerDrawerHomeNext = null;

    function isProvidersDrawerModal() {
        return !!(providersSidebar && providersSidebar.classList.contains('providers-drawer-wrapper'));
    }

    function mountProviderDrawerToBody() {
        if (!providersSidebar || !isProvidersDrawerModal()) return;
        // Keep CM622 token/FDS scope after portal (selectors are .slot-page-root--cm622 …).
        if (DESKTOP_LOBBY) {
            providersSidebar.classList.add('slot-page-root--cm622');
        }
        if (providersSidebar.parentElement === document.body) return;
        providerDrawerHomeParent = providersSidebar.parentElement;
        providerDrawerHomeNext = providersSidebar.nextSibling;
        document.body.appendChild(providersSidebar);
    }

    function restoreProviderDrawerHome() {
        if (!providersSidebar || !providerDrawerHomeParent) return;
        if (providerDrawerHomeNext && providerDrawerHomeNext.parentNode === providerDrawerHomeParent) {
            providerDrawerHomeParent.insertBefore(providersSidebar, providerDrawerHomeNext);
        } else {
            providerDrawerHomeParent.appendChild(providersSidebar);
        }
        providerDrawerHomeParent = null;
        providerDrawerHomeNext = null;
    }

    function openProviderSheet() {
        if (!providersSidebar) return;
        // Desktop CM622 drawer already paints its own backdrop. A separate
        // .sidebar-overlay on <body> sits above in-page fixed layers and steals clicks.
        if (isProvidersDrawerModal()) {
            mountProviderDrawerToBody();
            var overlay = document.querySelector('.sidebar-overlay');
            if (overlay) overlay.classList.remove('active');
        } else {
            ensureSidebarOverlay().classList.add('active');
        }
        providersSidebar.classList.add('mobile-open');
        document.body.classList.add('provider-sheet-open');
        syncProviderSidebarAria();
        updateDrawerButtonStates();
        syncProviderItemActiveStates();
    }

    function closeProviderSheet() {
        if (!providersSidebar) return;
        providersSidebar.classList.remove('mobile-open');
        var overlay = document.querySelector('.sidebar-overlay');
        if (overlay) overlay.classList.remove('active');
        document.body.classList.remove('provider-sheet-open');
        restoreProviderDrawerHome();
        syncProviderSidebarAria();
    }

    /* ── Drawer footer butonlarını provider seçimine göre aktif/pasif yap ── */
    function updateDrawerButtonStates() {
        var resetBtn = document.getElementById('providerSheetResetBtn');
        var applyBtn = document.getElementById('providerSheetApplyBtn');
        var hasSelection = state.providers.length > 0;
        var applyLabel = applyBtn ? applyBtn.querySelector('.btn__label') : null;
        if (resetBtn) {
            resetBtn.classList.toggle('active-reset', hasSelection);
            resetBtn.classList.toggle('ds-btn--disabled', !hasSelection);
            resetBtn.disabled = !hasSelection;
            resetBtn.setAttribute('aria-disabled', hasSelection ? 'false' : 'true');
        }
        if (applyBtn) {
            applyBtn.classList.toggle('active-apply', hasSelection);
            applyBtn.classList.toggle('ds-btn--disabled', !hasSelection);
            applyBtn.disabled = !hasSelection;
            applyBtn.setAttribute('aria-disabled', hasSelection ? 'false' : 'true');
        }
        if (applyLabel) {
            applyLabel.textContent = hasSelection ? ('FİLTRE +' + state.providers.length) : 'FİLTRE';
        }
    }

    /* ── Sidebar toggle: üst satırdaki ok ve SAĞLAYICILAR metni ── */
    const lineSidebarToggle = document.getElementById('lineSidebarToggle');
    const lineSidebarToggleLabel = document.getElementById('lineSidebarToggleLabel');
    const lineSidebarToggleIcon = document.getElementById('lineSidebarToggleIcon');
    function updateSidebarToggleIcon() {
        if (!providersSidebar || !lineSidebarToggleIcon) return;
        var isCollapsed = providersSidebar.classList.contains('collapsed');
        lineSidebarToggleIcon.className = isCollapsed ? 'fas fa-chevron-right' : 'fas fa-chevron-left';
    }
    function doSidebarToggle() {
        if (!providersSidebar) return;
        if (isMobileProviderSidebar()) {
            if (providersSidebar.classList.contains('mobile-open')) {
                closeProviderSheet();
            } else {
                openProviderSheet();
            }
        } else {
            closeProviderSheet();
            providersSidebar.classList.toggle('collapsed');
            updateSidebarToggleIcon();
            requestAnimationFrame(function() {
                if (window.refreshSlotEdgeScrollbar) window.refreshSlotEdgeScrollbar();
            });
        }
    }
    if (lineSidebarToggle && providersSidebar) {
        lineSidebarToggle.addEventListener('click', doSidebarToggle);
    }
    if (lineSidebarToggleLabel && providersSidebar) {
        lineSidebarToggleLabel.addEventListener('click', doSidebarToggle);
    }
    if (providersSidebar) {
        updateSidebarToggleIcon();
    }

    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    if (mobileSidebarToggle && providersSidebar) {
        var openProvidersUi = function(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            openProviderSheet();
        };
        mobileSidebarToggle.addEventListener('click', openProvidersUi);
        mobileSidebarToggle.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                openProvidersUi(e);
            }
        });
    }

    if (providersSidebar && providersSidebar.classList.contains('providers-drawer-wrapper')) {
        providersSidebar.addEventListener('click', function(e) {
            if (e.target === providersSidebar) {
                closeProviderSheet();
            }
        });
    }

    if (providerSheetBackBtn) {
        providerSheetBackBtn.addEventListener('click', closeProviderSheet);
    }
    var providerSheetCloseBtn = document.getElementById('providerSheetCloseBtn');
    if (providerSheetCloseBtn) {
        providerSheetCloseBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeProviderSheet();
        });
    }
    if (providerSheetApplyBtn) {
        providerSheetApplyBtn.addEventListener('click', function() {
            if (providerSheetApplyBtn.disabled) return;
            closeProviderSheet();
            if (navigateFromLobbyIfNeeded()) return;
            updateUrl();
            if (gameGrid) {
                loadSlots(false);
            } else {
                window.location.href = buildFilterPageUrl();
            }
        });
    }

    /* ── Provider sheet reset button ── */
    var providerSheetResetBtn = document.getElementById('providerSheetResetBtn');
    if (providerSheetResetBtn) {
        providerSheetResetBtn.addEventListener('click', function() {
            if (providerSheetResetBtn.disabled) return;
            /* Clear all selected providers and reload */
            state.providers = [];
            state.nextPage = 2;
            if (sidebarProvidersList) {
                sidebarProvidersList.querySelectorAll('.sidebar-provider-item').forEach(function(item) {
                    item.classList.remove('active');
                });
                var allItem = sidebarProvidersList.querySelector('[data-provider-all]');
                if (allItem) allItem.classList.add('active');
            }
            syncMobileFilterControls();
            updateDrawerButtonStates();
            closeProviderSheet();
            if (navigateFromLobbyIfNeeded()) return;
            updateUrl();
            if (gameGrid) {
                loadSlots(false);
            } else {
                window.location.href = buildFilterPageUrl();
            }
        });
    }

    function syncViewModulePressed() {
        if (!gameGrid || !viewModuleBtn) return;
        viewModuleBtn.setAttribute('aria-pressed', gameGrid.classList.contains('view-module-active') ? 'true' : 'false');
    }

    function toggleViewModule() {
        if (!gameGrid) return;
        gameGrid.classList.toggle('view-module-active');
        syncViewModulePressed();
    }

    if (viewModuleBtn) {
        viewModuleBtn.addEventListener('click', toggleViewModule);
        syncViewModulePressed();
    }
    if (providerSheetGridBtn) {
        providerSheetGridBtn.addEventListener('click', toggleViewModule);
    }

    window.addEventListener('resize', function() {
        // Desktop CM622 uses the same drawer modal — do not auto-close on width > 992
        // (scrollbar hide on open also fires resize and was instantly closing the modal).
        if (!isProvidersDrawerModal() && !isMobileProviderSidebar() && window.innerWidth > 992) {
            closeProviderSheet();
        }
        syncProviderSidebarAria();
        updateSidebarToggleIcon();
    });
    syncProviderSidebarAria();

    /* ── Category rail: desktop = CM622 translateX+arrows; mobile = native swipe, no arrow icons ── */
    var categoryRailUsesArrows = !!(catArrowLeft || catArrowRight);
    var isMobileCategoryRail = !categoryRailUsesArrows
        || !!(document.body && document.body.classList.contains('mobile-site'))
        || (typeof window.matchMedia === 'function' && window.matchMedia('(max-width: 992px)').matches);

    var categoryDrag = {
        active: false,
        moved: false,
        pointerId: null,
        startX: 0,
        startOffset: 0
    };

    function measureCategoryRailMax() {
        if (!catScrollViewport || !catScrollInner) {
            categoryRailOffset.max = 0;
            return 0;
        }
        var max = Math.max(0, catScrollInner.scrollWidth - catScrollViewport.clientWidth);
        categoryRailOffset.max = max;
        if (categoryRailOffset.value > max) categoryRailOffset.value = max;
        return max;
    }

    function applyCategoryRailOffset(next, opts) {
        opts = opts || {};
        if (!catScrollInner || !catScrollViewport) return;
        measureCategoryRailMax();
        var value = Math.max(0, Math.min(categoryRailOffset.max, next));
        categoryRailOffset.value = value;
        if (isMobileCategoryRail || !categoryRailUsesArrows) {
            catScrollInner.style.transform = 'none';
            catScrollViewport.scrollLeft = value;
            return;
        }
        if (opts.instant && catScrollInner) {
            catScrollInner.classList.add('is-dragging');
        }
        catScrollInner.style.transform = 'translateX(' + (-value) + 'px)';
        if (!opts.instant && catScrollInner.classList.contains('is-dragging') && !opts.keepDragging) {
            catScrollInner.classList.remove('is-dragging');
        }
        updateCategoryArrowState();
    }

    function updateCategoryArrowState() {
        if (!catScrollViewport) return;
        // Mobile: never show arrow / fade chrome.
        if (isMobileCategoryRail || !categoryRailUsesArrows) {
            if (catArrowLeft) catArrowLeft.hidden = true;
            if (catArrowRight) catArrowRight.hidden = true;
            if (catArrowShadowLeft) catArrowShadowLeft.hidden = true;
            if (catArrowShadowRight) catArrowShadowRight.hidden = true;
            catScrollViewport.classList.remove('horizontal-scroll--fade-start', 'horizontal-scroll--fade-end', 'horizontal-scroll--has-arrows');
            return;
        }
        measureCategoryRailMax();
        var max = categoryRailOffset.max;
        var left = categoryRailOffset.value;
        var showLeft = max > 2 && left > 2;
        var showRight = max > 2 && left < max - 2;

        catScrollViewport.classList.toggle('horizontal-scroll--fade-start', showLeft);
        catScrollViewport.classList.toggle('horizontal-scroll--fade-end', showRight || (!showLeft && max > 2));
        catScrollViewport.classList.toggle('horizontal-scroll--has-arrows', max > 2);
        catScrollViewport.classList.toggle('horizontal-scroll--no-scroll', max <= 2);
        if (catArrowLeft) catArrowLeft.hidden = !showLeft;
        if (catArrowShadowLeft) catArrowShadowLeft.hidden = !showLeft;
        if (catArrowRight) catArrowRight.hidden = !showRight;
        if (catArrowShadowRight) catArrowShadowRight.hidden = !showRight;
    }

    function scrollCategoryTabs(direction) {
        if (!catScrollViewport) return;
        var amount = catScrollViewport.clientWidth * 0.5;
        applyCategoryRailOffset(categoryRailOffset.value + direction * amount);
    }
    if (catArrowLeft && catScrollViewport && categoryRailUsesArrows) {
        catArrowLeft.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            scrollCategoryTabs(-1);
        });
    }
    if (catArrowRight && catScrollViewport && categoryRailUsesArrows) {
        catArrowRight.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            scrollCategoryTabs(1);
        });
    }

    if (catScrollViewport && catScrollInner) {
        catScrollViewport.querySelectorAll('.ds-chip, .cat-tab, a').forEach(function(tab) {
            tab.setAttribute('draggable', 'false');
            tab.addEventListener('dragstart', function(e) {
                e.preventDefault();
            });
        });

        // Desktop only: pointer drag → translateX. Mobile uses native overflow swipe.
        if (categoryRailUsesArrows && !isMobileCategoryRail) {
            var DRAG_THRESHOLD = 10;

            catScrollViewport.addEventListener('pointerdown', function(e) {
                if (e.button !== undefined && e.button !== 0) return;
                if (e.target && e.target.closest && e.target.closest('.horizontal-scroll__arrow')) return;
                categoryDrag.active = true;
                categoryDrag.moved = false;
                categoryDrag.pointerId = e.pointerId;
                categoryDrag.startX = e.clientX;
                categoryDrag.startOffset = categoryRailOffset.value;
            });

            catScrollViewport.addEventListener('pointermove', function(e) {
                if (!categoryDrag.active) return;
                var dx = e.clientX - categoryDrag.startX;
                if (!categoryDrag.moved && Math.abs(dx) < DRAG_THRESHOLD) return;
                if (!categoryDrag.moved) {
                    categoryDrag.moved = true;
                    catScrollInner.classList.add('is-dragging');
                    try {
                        catScrollViewport.setPointerCapture(e.pointerId);
                    } catch (err) {}
                }
                e.preventDefault();
                applyCategoryRailOffset(categoryDrag.startOffset - dx, { instant: true, keepDragging: true });
            });

            function endCategoryDrag(e) {
                if (!categoryDrag.active) return;
                var wasMoved = categoryDrag.moved;
                categoryDrag.active = false;
                categoryDrag.pointerId = null;
                catScrollInner.classList.remove('is-dragging');
                try {
                    if (e && e.pointerId !== undefined) {
                        catScrollViewport.releasePointerCapture(e.pointerId);
                    }
                } catch (err) {}
                if (wasMoved) {
                    window.setTimeout(function() {
                        categoryDrag.moved = false;
                    }, 0);
                }
                updateCategoryArrowState();
            }

            catScrollViewport.addEventListener('pointerup', endCategoryDrag);
            catScrollViewport.addEventListener('pointercancel', endCategoryDrag);
            catScrollViewport.addEventListener('lostpointercapture', function() {
                categoryDrag.active = false;
                catScrollInner.classList.remove('is-dragging');
            });
            catScrollViewport.addEventListener('click', function(e) {
                if (!categoryDrag.moved) return;
                e.preventDefault();
                e.stopPropagation();
                categoryDrag.moved = false;
            }, true);

            if (typeof ResizeObserver !== 'undefined') {
                var railRo = new ResizeObserver(function() {
                    applyCategoryRailOffset(categoryRailOffset.value);
                });
                railRo.observe(catScrollViewport);
                railRo.observe(catScrollInner);
            }
            window.addEventListener('resize', function() {
                applyCategoryRailOffset(categoryRailOffset.value);
            });
            applyCategoryRailOffset(0, { instant: true });
            catScrollInner.classList.remove('is-dragging');
            catScrollInner.setAttribute('data-rail-ready', '1');
        } else {
            catScrollInner.style.transform = 'none';
            updateCategoryArrowState();
            if (catScrollInner) catScrollInner.setAttribute('data-rail-ready', '1');
        }
    }

    /* Aktif kategori sekmesini görünür yap (scroll alanında ortalanmış) */
    function scrollActiveCategoryIntoView() {
        if (!catScrollViewport || !catScrollInner) return;
        // Lobby chip is first / already selected — avoid smooth scroll jump on open.
        if (LOBBY_MODE) {
            var lobbyChip = catScrollViewport.querySelector('.ds-chip--selected, .cat-tab.active');
            if (lobbyChip && categoryRailOffset.max <= 0) {
                return;
            }
        }
        var activeTab = catScrollViewport.querySelector('.cat-tab.active, .ds-chip--selected');
        if (!activeTab) return;
        requestAnimationFrame(function() {
            if (isMobileCategoryRail || !categoryRailUsesArrows) {
                try {
                    activeTab.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'auto' });
                } catch (err) {
                    activeTab.scrollIntoView(false);
                }
                return;
            }
            measureCategoryRailMax();
            if (categoryRailOffset.max <= 0) return;
            var railRect = catScrollViewport.getBoundingClientRect();
            var tabRect = activeTab.getBoundingClientRect();
            var tabCenterInContent = (tabRect.left - railRect.left) + categoryRailOffset.value + (tabRect.width / 2);
            var next = tabCenterInContent - (catScrollViewport.clientWidth / 2);
            applyCategoryRailOffset(next, { instant: true });
        });
    }

    /* Desktop CM622: wheel → translateX */
    if (catScrollViewport && categoryRailUsesArrows && !isMobileCategoryRail) {
        catScrollViewport.addEventListener('wheel', function(e) {
            measureCategoryRailMax();
            if (categoryRailOffset.max <= 0) return;
            var delta = e.deltaY + e.deltaX;
            if (delta === 0) return;
            var atStart = categoryRailOffset.value <= 0 && delta < 0;
            var atEnd = categoryRailOffset.value >= categoryRailOffset.max - 1 && delta > 0;
            if (atStart || atEnd) return;
            e.preventDefault();
            applyCategoryRailOffset(categoryRailOffset.value + delta, { instant: true, keepDragging: true });
            catScrollInner && catScrollInner.classList.remove('is-dragging');
        }, { passive: false });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (providersSidebar && providersSidebar.classList.contains('mobile-open')) {
                closeProviderSheet();
            }
        }
    });

    /* ── Sidebar provider chip ── */
    function isProviderDrawerOpen() {
        return !!(providersSidebar && providersSidebar.classList.contains('mobile-open'));
    }

    function syncProviderItemActiveStates() {
        if (!sidebarProvidersList) return;
        var items = sidebarProvidersList.querySelectorAll('.sidebar-provider-item, .providerItemsInner');
        items.forEach(function(item) {
            if (item.getAttribute('data-provider-all') === '1') {
                item.classList.toggle('active', state.providers.length === 0);
                return;
            }
            var p = item.getAttribute('data-provider');
            item.classList.toggle('active', !!(p && state.providers.indexOf(p) !== -1));
        });
    }

    function toggleProvider(provider, opts) {
        opts = opts || {};
        const idx = state.providers.indexOf(provider);
        if (idx !== -1) {
            state.providers.splice(idx, 1);
        } else {
            state.providers.push(provider);
        }
        state.nextPage = 2;
        syncMobileFilterControls();
        updateDrawerButtonStates();
        syncProviderItemActiveStates();
        // In drawer: wait for Apply. Carousel/direct click navigates immediately.
        if (opts.deferNavigate || (DESKTOP_LOBBY && isProviderDrawerOpen())) {
            return;
        }
        if (navigateFromLobbyIfNeeded()) return;
        loadSlots(false);
    }

    function selectAllProviders(opts) {
        opts = opts || {};
        state.providers = [];
        state.search = '';
        state.nextPage = 2;
        syncMobileFilterControls();
        updateDrawerButtonStates();
        syncProviderItemActiveStates();
        if (opts.deferNavigate || (DESKTOP_LOBBY && isProviderDrawerOpen())) {
            return;
        }
        if (navigateFromLobbyIfNeeded()) return;
        loadSlots(false);
    }

    function activateProviderItem(item, opts) {
        if (!item) return;
        var provider = item.getAttribute('data-provider');
        if (provider) {
            toggleProvider(provider, opts);
        } else if (item.getAttribute('data-provider-all') === '1') {
            selectAllProviders(opts);
        }
    }

    if (sidebarProvidersList) {
        sidebarProvidersList.addEventListener('click', function(e) {
            var item = e.target.closest('.sidebar-provider-item, .providerItemsInner[data-provider], .providerItemsInner[data-provider-all]');
            if (!item || !sidebarProvidersList.contains(item)) return;
            e.preventDefault();
            activateProviderItem(item, { deferNavigate: DESKTOP_LOBBY && isProviderDrawerOpen() });
        });

        sidebarProvidersList.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var item = e.target.closest('.sidebar-provider-item, .providerItemsInner[data-provider], .providerItemsInner[data-provider-all]');
            if (!item || !sidebarProvidersList.contains(item)) return;
            e.preventDefault();
            activateProviderItem(item, { deferNavigate: DESKTOP_LOBBY && isProviderDrawerOpen() });
        });
    }

    var providerChipRail = document.getElementById('providerChipRail');
    if (providerChipRail) {
        providerChipRail.addEventListener('click', function(e) {
            var chip = e.target.closest('.provider-chip');
            if (!chip || !providerChipRail.contains(chip)) return;
            e.preventDefault();
            activateProviderItem(chip);
        });
    }

    function removeSearch() {
        setSearch('');
        loadSlots(false);
    }

    function removeFilter(provider) {
        removeProvider(provider);
        loadSlots(false);
    }

    function clearAllUrlFilters() {
        clearFilters();
        loadSlots(false);
    }

    /* Çarpı butonu: hem PHP hem JS ile eklenen etiketler için event delegation */
    if (activeFiltersRow) {
        activeFiltersRow.addEventListener('click', function(e) {
            var removeBtn = e.target.closest('.remove');
            if (!removeBtn) return;
            e.preventDefault();
            e.stopPropagation();
            var action = removeBtn.getAttribute('data-action');
            if (action === 'remove-search') {
                setSearch('');
                loadSlots(false);
            } else if (action === 'remove-filter') {
                var p = removeBtn.getAttribute('data-provider');
                if (p) {
                    removeProvider(p);
                    loadSlots(false);
                }
            }
        });
    }

    window.toggleProvider = toggleProvider;
    window.selectAllProviders = selectAllProviders;
    window.removeSearch = removeSearch;
    window.removeFilter = removeFilter;
    window.clearAllUrlFilters = clearAllUrlFilters;

    /* ── Infinite scroll: yalnızca kullanıcı altta / sentinel görünürken ── */
    const slotsGamesEl = getCasinoGamesContainer(slotPageRoot);
    const loadMoreSentinel = document.getElementById('load-more-sentinel');

    function checkLoadMore() {
        if (!slotsGamesEl || !state.showLoadMore || state.isLoadingMore || state.remainingGames <= 0) return;
        var scrollTop = slotsGamesEl.scrollTop;
        var scrollHeight = slotsGamesEl.scrollHeight;
        var clientHeight = slotsGamesEl.clientHeight;
        // İç scroll yoksa (scrollHeight ≈ clientHeight) otomatik dump yapma — IO sentinel'e bırak.
        if (scrollHeight <= clientHeight + 4) return;
        var distanceFromBottom = scrollHeight - scrollTop - clientHeight;
        var threshold = Math.max(clientHeight * 0.10, 80);
        if (distanceFromBottom <= threshold) loadSlots(true);
    }

    if (slotsGamesEl) {
        slotsGamesEl.addEventListener('scroll', function() {
            requestAnimationFrame(checkLoadMore);
        }, { passive: true });
    }

    if (loadMoreSentinel && typeof IntersectionObserver !== 'undefined') {
        var ioRoot = slotsGamesEl && slotsGamesEl.scrollHeight > slotsGamesEl.clientHeight + 4
            ? slotsGamesEl
            : null;
        var loadMoreIo = new IntersectionObserver(function(entries) {
            for (var i = 0; i < entries.length; i++) {
                if (!entries[i].isIntersecting) {
                    loadMoreArmed = true;
                    continue;
                }
                if (!loadMoreArmed) return;
                if (!state.showLoadMore || state.isLoadingMore || state.remainingGames <= 0) return;
                loadMoreArmed = false;
                loadSlots(true);
                return;
            }
        }, {
            root: ioRoot,
            rootMargin: '0px 0px 100px 0px',
            threshold: 0
        });
        loadMoreIo.observe(loadMoreSentinel);
    }

    /* ── Random Game ── */
    var randomGameBtn = document.getElementById('randomGameBtn');
    if (!randomGameBtn && document.body.classList.contains('mobile-site')) {
        var searchRow = slotPageRoot ? slotPageRoot.querySelector('.casinoTitleSearch') : document.querySelector('.casinoTitleSearch');
        if (searchRow) {
            randomGameBtn = document.createElement('button');
            randomGameBtn.type = 'button';
            randomGameBtn.className = 'random-game-btn';
            randomGameBtn.id = 'randomGameBtn';
            randomGameBtn.title = 'Rastgele Oyun Oyna';
            randomGameBtn.setAttribute('aria-label', 'Rastgele Oyun Oyna');
            randomGameBtn.textContent = 'Rastgele Oyun Oyna';
            searchRow.appendChild(randomGameBtn);
        }
    }
    if (randomGameBtn) {
        randomGameBtn.addEventListener('click', function() {
            const gameItems = document.querySelectorAll('.casinoGameItemContent[data-game-id]');
            if (gameItems.length === 0) return;
            const randomIndex = Math.floor(Math.random() * gameItems.length);
            gameItems[randomIndex].click();
        });
    }

    /* ── Category tabs: normal link navigation; drag-scroll click guard handles real drags. ── */

    const mobileOriginalSortMap = {
        TopSlots: 'liked',
        PopularGames: 'popular',
        New: 'new',
        Jackpots: 'jackpots',
        BuyBonus: 'bonus-buy',
        VideoSlots: 'video',
        CrashGames: 'crash',
        BuyFeature: 'freespin',
        InstantWin: 'instant',
        TableGames: 'table',
        Slots: 'slots',
        // CM622 live-casino/home chips
        gameShows: 'game-show',
        poker: 'poker',
        blackjack: 'blackjack',
        roulette: 'roulette',
        asianGames: 'asian',
        turkishTables: 'turkish',
        baccarat: 'baccarat',
        farsiTables: 'farsi',
        indianTables: 'indian',
        brazilianTables: 'brazilian',
        dutchTables: 'dutch',
        arabicTables: 'arabic'
    };

    function getMobileOriginalCategorySort(tab) {
        if (!tab) return null;
        if (tab.hasAttribute('data-sort')) {
            return tab.getAttribute('data-sort') || '';
        }
        var wrapper = tab.parentElement;
        var className = wrapper && typeof wrapper.className === 'string' ? wrapper.className : '';
        var matched = className.match(/category-([A-Za-z0-9]+)/);
        if (matched && mobileOriginalSortMap[matched[1]] !== undefined) {
            return mobileOriginalSortMap[matched[1]];
        }
        var label = tab.querySelector('.chip__label');
        var text = label ? (label.textContent || '').trim().toLowerCase() : '';
        if (text === 'lobby' || text === 'tüm oyunlar') {
            return '';
        }
        return null;
    }

    function buildCategoryHref(sort) {
        var basePath = window.location.pathname || '/slot';
        if (!sort) return basePath;
        return basePath + '?sort=' + encodeURIComponent(sort);
    }

    if (catScroll) {
        // Prefer native <a href> navigation; only patch chips that lack a real href.
        catScroll.addEventListener('click', function(e) {
            var chip = e.target.closest('.casinoNavigationAndFilters .casinoCategories .ds-chip');
            if (!chip || !catScroll.contains(chip)) return;
            if (categoryDrag && categoryDrag.moved) return;
            var dataHref = chip.getAttribute('href') || chip.getAttribute('data-href') || '';
            if (chip.tagName === 'A' && dataHref && dataHref !== '#') {
                // Let the browser follow the link (works with Ctrl/Cmd+click too).
                return;
            }
            e.preventDefault();
            if (dataHref) {
                window.location.href = dataHref;
                return;
            }
            if (chip.getAttribute('data-lobby-chip') === '1') {
                window.location.href = buildCategoryHref('');
                return;
            }
            var sort = getMobileOriginalCategorySort(chip);
            if (sort === null) return;
            window.location.href = buildCategoryHref(sort);
        });

        catScroll.addEventListener('click', function(e) {
            var tab = e.target.closest('.cat-tab[data-href]');
            if (!tab || !catScroll.contains(tab) || tab.tagName === 'A') return;
            var href = tab.getAttribute('data-href');
            if (!href) return;
            e.preventDefault();
            window.location.href = href;
        });

        catScroll.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var tab = e.target.closest('.cat-tab[data-href], .ds-chip[href], .ds-chip[data-href]');
            if (!tab || !catScroll.contains(tab)) return;
            if (tab.tagName === 'A' && tab.getAttribute('href')) return;
            e.preventDefault();
            var href = tab.getAttribute('data-href') || tab.getAttribute('href');
            if (href) window.location.href = href;
        });
    }

    function setActiveCategoryTab() {
        if (!catScroll) return;
        var dataSortTabs = catScroll.querySelectorAll('.cat-tab[data-sort]');
        if (dataSortTabs.length > 0) {
            dataSortTabs.forEach(function(t) {
                const tabSort = t.getAttribute('data-sort') || '';
                t.classList.toggle('active', tabSort === state.sort);
                t.classList.toggle('ds-chip--selected', tabSort === state.sort);
            });
            return;
        }

        catScroll.querySelectorAll('.ds-chip').forEach(function(chip) {
            if (chip.getAttribute('data-lobby-chip') === '1') {
                chip.classList.toggle('ds-chip--selected', LOBBY_MODE || (!DESKTOP_LOBBY && state.sort === '' && !state.search && state.providers.length === 0));
                return;
            }
            if (chip.hasAttribute('data-sort')) {
                var chipSort = chip.getAttribute('data-sort') || '';
                var allSelected = state.sort === ''
                    && !state.search
                    && state.providers.length === 0
                    && (
                        !DESKTOP_LOBBY
                        || String(config.view || '') === 'all'
                    )
                    && !LOBBY_MODE;
                if (!DESKTOP_LOBBY && chipSort === '') {
                    // Mobile original nav: Lobby chip owns the empty-sort state.
                    chip.classList.remove('ds-chip--selected');
                    return;
                }
                chip.classList.toggle(
                    'ds-chip--selected',
                    chipSort === '' ? allSelected : (chipSort === state.sort)
                );
                return;
            }
            var label = chip.querySelector('.chip__label');
            var text = label ? (label.textContent || '').trim().toLowerCase() : '';
            if (text === 'tüm oyunlar') {
                if (!DESKTOP_LOBBY) {
                    chip.classList.remove('ds-chip--selected');
                    return;
                }
                chip.classList.toggle(
                    'ds-chip--selected',
                    !LOBBY_MODE
                    && state.sort === ''
                    && String(config.view || '') === 'all'
                );
                return;
            }
            var sort = getMobileOriginalCategorySort(chip);
            if (sort === null) return;
            chip.classList.toggle('ds-chip--selected', sort === state.sort);
        });
    }

    function syncLobbyCarouselNav(swiper, prevBtn, nextBtn) {
        if (!swiper) return;
        var atStart = swiper.isBeginning;
        var atEnd = swiper.isEnd;
        if (prevBtn) {
            prevBtn.disabled = atStart;
            prevBtn.setAttribute('aria-disabled', atStart ? 'true' : 'false');
            prevBtn.classList.toggle('ds-btn--disabled', atStart);
        }
        if (nextBtn) {
            nextBtn.disabled = atEnd;
            nextBtn.setAttribute('aria-disabled', atEnd ? 'true' : 'false');
            nextBtn.classList.toggle('ds-btn--disabled', atEnd);
        }
    }

    function initLobbyCarousels() {
        if (!LOBBY_MODE || typeof Swiper === 'undefined') return;
        var roots = document.querySelectorAll('[data-lobby-carousel-el]');
        roots.forEach(function(wrapper) {
            var id = wrapper.getAttribute('data-lobby-carousel-el') || '';
            var swiperEl = wrapper.querySelector('.swiper');
            if (!swiperEl || swiperEl.swiper) return;
            var nav = document.querySelector('.sectionTitleBtnWrapper[data-lobby-carousel="' + id + '"]');
            var prevBtn = nav ? nav.querySelector('.lobby-carousel-prev') : null;
            var nextBtn = nav ? nav.querySelector('.lobby-carousel-next') : null;
            var swiper = new Swiper(swiperEl, {
                slidesPerView: 'auto',
                spaceBetween: 16,
                watchOverflow: true,
                resistanceRatio: 0.65,
                on: {
                    init: function() { syncLobbyCarouselNav(this, prevBtn, nextBtn); },
                    slideChange: function() { syncLobbyCarouselNav(this, prevBtn, nextBtn); },
                    resize: function() { syncLobbyCarouselNav(this, prevBtn, nextBtn); }
                }
            });
            if (prevBtn) {
                prevBtn.addEventListener('click', function() { swiper.slidePrev(); });
            }
            if (nextBtn) {
                nextBtn.addEventListener('click', function() { swiper.slideNext(); });
            }
        });

        var lobbySections = document.getElementById('casinoLobbySections');
        if (lobbySections) {
            lobbySections.addEventListener('click', function(e) {
                var openProvidersLink = e.target.closest('[data-lobby-section="providers"] .ds-link[data-href], .casinoProvidersWidget .ds-link[data-href]');
                if (openProvidersLink && lobbySections.contains(openProvidersLink)) {
                    e.preventDefault();
                    openProviderSheet();
                    return;
                }
                var item = e.target.closest('.lobby-providers-swiper .providerItemsInner, .lobby-providers-swiper .sidebar-provider-item');
                if (!item || !lobbySections.contains(item)) return;
                e.preventDefault();
                var provider = item.getAttribute('data-provider');
                if (!provider) return;
                state.providers = [provider];
                state.search = '';
                state.sort = '';
                state.nextPage = 2;
                window.location.href = buildFilterPageUrl();
            });
        }
    }

    function hasServerRenderedGames() {
        if (!gameGrid) return false;
        return !!gameGrid.querySelector('.casinoGameItemContent, .casinoGameItem');
    }

    function maybeLoadSlotsOnBoot() {
        // Keep SSR tiles on first paint. Refetch only when the grid is empty
        // (filters still call loadSlots after user interaction).
        if (API_ADAPTER === 'member_api_games' && gameGrid && !LOBBY_MODE && !hasServerRenderedGames()) {
            loadSlots(false);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setActiveCategoryTab();
            scrollActiveCategoryIntoView();
            syncMobileFilterControls();
            initLobbyCarousels();
            // Canonicalize legacy providers[]=%5B%5D URLs to providers=Name.
            if (state.providers.length > 0 || /providers(%5B%5D|\[\]|=)/i.test(window.location.search)) {
                updateUrl();
            }
            maybeLoadSlotsOnBoot();
        });
    } else {
        setActiveCategoryTab();
        scrollActiveCategoryIntoView();
        syncMobileFilterControls();
        initLobbyCarousels();
        if (state.providers.length > 0 || /providers(%5B%5D|\[\]|=)/i.test(window.location.search)) {
            updateUrl();
        }
        maybeLoadSlotsOnBoot();
    }
})();
