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
        return (gamesContainer && gamesContainer.querySelector(':scope > .casinoCategoryGames')) ||
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
    const EMPTY_TITLE = config.emptyTitle || 'Slot oyunu bulunamadı';
    const EMPTY_TEXT = config.emptyText || 'Arama teriminizi değiştirmeyi veya filtreleri temizlemeyi deneyin';

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
    const SEARCH_DEBOUNCE_MS = 600;
    const providerSearchInput = document.getElementById('providerSearchInput');
    const providersSidebar   = document.getElementById('providersSidebar');
    const sidebarProvidersList = document.getElementById('sidebarProvidersList');
    const viewModuleBtn      = document.getElementById('viewModuleBtn');
    const providerSheetGridBtn = document.getElementById('providerSheetGridBtn');
    const providerSheetBackBtn = document.getElementById('providerSheetBackBtn');
    const providerSheetApplyBtn = document.getElementById('providerSheetApplyBtn');
    const catArrowLeft       = document.getElementById('catArrowLeft');
    const catArrowRight      = document.getElementById('catArrowRight');
    const catScroll          = document.getElementById('categoryTabsScroll') || document.querySelector('.casinoNavigationAndFilters .casinoCategories .horizontal-scroll__inner');
    const gameGrid           = getCasinoCategoryGames(slotPageRoot);
    const activeFiltersRow   = document.querySelector('.active-filters-row');
    const activeFiltersBox   = document.getElementById('active-filters-box');

    /* Arama input artık right-sidebar içinde (header); başlangıç değerini state ile senkronize et */
    if (searchInput) searchInput.value = state.search;

    const PLACEHOLDER_IMG = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDMwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjMwMCIgaGVpZ2h0PSIyMDAiIHJ4PSI4IiBmaWxsPSIjMWExMTJlIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGRvbWluYW50LWJhc2VsaW5lPSJtaWRkbGUiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZpbGw9IiM2NjYiIGZvbnQtZmFtaWx5PSJzYW5zLXNlcmlmIiBmb250LXNpemU9IjE0Ij5ObyBJbWFnZTwvdGV4dD48L3N2Zz4=';

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
            state.providers.forEach(p => params.append('providers[]', p));
            if (state.sort) params.set('sort', state.sort);
        } else {
            state.providers.forEach(p => params.append('providers[]', p));
            if (state.sort) params.set('sort', state.sort);
        }
        return API_ENDPOINT + '?' + params.toString();
    }

    function playUrlReal(gameId) {
        var id = String(gameId || '');
        return '/play?game_id=' + encodeURIComponent(id) + '&mode=real&wallet=main';
    }

    function playUrlFun(gameId) {
        var id = String(gameId || '');
        return '/play?game_id=' + encodeURIComponent(id) + '&mode=fun';
    }

    function playTargetUrl(gameId) {
        var play = playUrlReal(gameId);
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

    function applyMobileActionButtonSizing() {
        if (!document.body.classList.contains('mobile-site')) return;
        var buttons = document.querySelectorAll('.slot-page-root .play-btn, .slot-page-root .demo-btn, .slots-games-container .play-btn, .slots-games-container .demo-btn, .casinoCategoryGames .play-btn, .casinoCategoryGames .demo-btn');
        buttons.forEach(function (btn) {
            btn.style.width = 'calc(50% - 4px)';
            btn.style.minWidth = '0';
            btn.style.maxWidth = 'none';
            btn.style.padding = '7px 8px';
            btn.style.fontSize = '10px';
            btn.style.lineHeight = '1';
            btn.style.borderRadius = '5px';
        });
    }

    function realPlayClickJs(gameUrlJs) {
        return "if(event){event.preventDefault();event.stopPropagation();}window.__slotHandlePlayIntent&&window.__slotHandlePlayIntent(event,'" + gameUrlJs + "')";
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
        const cover = escapeHtml(game.cover || '');
        const gameId = String(game.game_id || '');
        const gameIdEsc = escapeHtml(gameId);
        const catalogIdRaw = game.id != null && String(game.id).trim() !== '' ? String(game.id) : '';
        const catalogAttr = catalogIdRaw !== '' ? ' data-catalog-id="' + escapeHtml(catalogIdRaw) + '"' : '';
        const gameUrl = playTargetUrl(gameId);
        const gameUrlJs = gameUrl.replace(/\\/g, '\\\\').replace(/'/g, '\\\'');
        const demoUrl = playUrlFun(gameId);
        window.__slotOpenLoginModal = openLoginModal;
        window.__slotOpenPlayUrl = openPlayUrl;
        window.__slotLaunchPlayUrl = launchPlayUrl;
        window.__slotHandlePlayIntent = handlePlayIntent;
        const actionsHtml = ACTION_BUTTONS ? (
            '<div class="game-overlay">' +
            '<div class="game-overlay-top"></div>' +
            '<div class="game-title-wrap"><p class="game-title-text">' + name + '</p></div>' +
            '<div class="game-actions">' +
            '<a class="play-btn" href="' + escapeHtml(gameUrl) + '" onclick="' + realPlayClickJs(gameUrlJs) + '">OYNA</a>' +
            '<a class="demo-btn" href="' + escapeHtml(demoUrl) + '" onclick="event.stopPropagation()">DEMO</a>' +
            '</div>' +
            '</div>'
        ) : '';
        return (
            '<div class="casinoGameItemContent " data-favorite-kind="' + escapeHtml(FAVORITE_KIND) + '" data-game-id="' + gameIdEsc + '"' + catalogAttr + ' onclick="' + realPlayClickJs(gameUrlJs) + '">' +
            '<span class="providerBadgeBlock " data-badge=""></span>' +
            '<div class="casinoGameItem ">' +
            '<img alt="' + name + '" loading="eager" src="' + cover + '" data-src="' + cover + '" class="casinoGameItemImage" title="' + name + '" style="aspect-ratio: 44 / 31;" onerror="this.src=\'' + PLACEHOLDER_IMG + '\'">' +
            '<i class="casinoGameItemFavBc bc-i-favorite "></i>' +
            actionsHtml +
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
            return {
                id: game.id,
                game_id: game.game_id || game.slug || game.id,
                game_name: game.name || game.game_name || '',
                cover: game.image_url || game.thumbnail_url || game.banner || game.cover || '',
                has_demo: game.has_demo,
                provider: game.provider || '',
                provider_code: game.provider_code || '',
                source: game.source || ''
            };
        }).filter(function(game) {
            // On the dedicated bgaming page, keep all bgaming games.
            if (FAVORITE_KIND === 'bgaming') return true;
            return !isBgamingGame(game);
        });
        var page = Number(pagination.page || 1);
        var perPage = Number(pagination.perPage || PAGE_SIZE);
        var total = Number(pagination.total || games.length);
        var loaded = Math.max(0, (page - 1) * perPage) + games.length;
        var remaining = Math.max(0, total - loaded);

        return {
            ok: !!(data && data.success),
            games: games,
            totalSlots: total,
            remainingGames: remaining,
            showLoadMore: !!pagination.hasNext && remaining > 0,
            nextPage: page + 1,
            page: page,
            perPage: perPage
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

    function preloadImages(urls, timeoutMs) {
        timeoutMs = timeoutMs || 8000;
        return Promise.all(urls.map(function(url) {
            if (!url) return Promise.resolve();
            return new Promise(function(resolve) {
                var img = new Image();
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
        document.querySelectorAll('.sidebar-provider-item').forEach(function(item) {
            const provider = item.getAttribute('data-provider');
            if (provider === null) {
                item.classList.toggle('active', state.providers.length === 0 && !state.search);
            } else {
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

    function updateUrl() {
        const url = new URL(window.location.href);
        url.searchParams.delete('search');
        url.searchParams.delete('providers[]');
        url.searchParams.delete('offset');
        url.searchParams.delete('sort');
        if (state.search) url.searchParams.set('search', state.search);
        state.providers.forEach(function(p) { url.searchParams.append('providers[]', p); });
        if (state.sort) url.searchParams.set('sort', state.sort);
        window.history.replaceState({}, '', url.toString());
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

        var requestLimit = PAGE_SIZE;
        var url = buildApiUrl(append);

        if (!append) {
            gameGrid.innerHTML = renderSkeletonItems(PAGE_SIZE);
        } else {
            gameGrid.insertAdjacentHTML('beforeend', renderSkeletonItems(requestLimit));
        }

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(res) { return res.json(); })
            .then(function(data) {
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

                var coverUrls = games.map(function(g) { return g.cover || ''; });
                preloadImages(coverUrls, 8000).then(function() {
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
                    requestAnimationFrame(function() {
                        checkLoadMore();
                    });
                });
            })
            .catch(function() {
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
        loadSlots(false);
    }

    function clearSearch() {
        setSearch('');
        if (searchInput) searchInput.value = '';
        updateSearchBtnIcon();
        loadSlots(false);
    }

    var searchClearBtnIcon = searchClearBtn ? (searchClearBtn.querySelector('#searchClearBtnIcon') || searchClearBtn.querySelector('i')) : null;
    function updateSearchBtnIcon() {
        searchClearBtnIcon = searchClearBtn ? (searchClearBtn.querySelector('#searchClearBtnIcon') || searchClearBtn.querySelector('i')) : null;
        if (!searchClearBtnIcon || !searchClearBtn) return;
        var hasText = searchInput && searchInput.value.trim().length > 0;
        searchClearBtnIcon.className = hasText ? 'fas fa-times' : 'fas fa-search';
        searchClearBtn.title = hasText ? 'Aramayı temizle' : 'Oyun ara';
        searchClearBtn.setAttribute('aria-label', hasText ? 'Aramayı temizle' : 'Oyun ara');
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
            if (mobileExpand) {
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
                clearSearch();
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
        if (!providerSearchClearBtnIcon || !providerSearchClearBtn || !providerSearchInput) return;
        var hasText = providerSearchInput.value.trim().length > 0;
        providerSearchClearBtnIcon.className = hasText ? 'fas fa-times' : 'fas fa-search';
        providerSearchClearBtn.title = hasText ? 'Aramayı temizle' : 'Sağlayıcı ara';
        providerSearchClearBtn.setAttribute('aria-label', hasText ? 'Aramayı temizle' : 'Sağlayıcı ara');
    }
    if (providerSearchInput) {
        providerSearchInput.addEventListener('input', function() {
            updateProviderSearchBtnIcon();
            var q = providerSearchInput.value.toLowerCase().trim();
            var providerItems = sidebarProvidersList ? sidebarProvidersList.querySelectorAll('.sidebar-provider-item[data-provider]') : document.querySelectorAll('.sidebar-provider-item[data-provider]');
            providerItems.forEach(function(item) {
                var name = (item.dataset.provider || item.textContent).toLowerCase();
                item.style.display = name.indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }
    if (providerSearchClearBtn) {
        providerSearchClearBtn.addEventListener('click', function() {
            if (providerSearchInput && providerSearchInput.value.trim().length > 0) {
                providerSearchInput.value = '';
                providerSearchInput.focus();
                updateProviderSearchBtnIcon();
                var providerItems = sidebarProvidersList ? sidebarProvidersList.querySelectorAll('.sidebar-provider-item[data-provider]') : document.querySelectorAll('.sidebar-provider-item[data-provider]');
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

    function openProviderSheet() {
        if (!providersSidebar) return;
        providersSidebar.classList.add('mobile-open');
        ensureSidebarOverlay().classList.add('active');
        document.body.classList.add('provider-sheet-open');
        syncProviderSidebarAria();
        updateDrawerButtonStates();
    }

    function closeProviderSheet() {
        if (!providersSidebar) return;
        providersSidebar.classList.remove('mobile-open');
        var overlay = document.querySelector('.sidebar-overlay');
        if (overlay) overlay.classList.remove('active');
        document.body.classList.remove('provider-sheet-open');
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
        }
        if (applyBtn) {
            applyBtn.classList.toggle('active-apply', hasSelection);
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
        mobileSidebarToggle.addEventListener('click', function() {
            openProviderSheet();
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
    if (providerSheetApplyBtn) {
        providerSheetApplyBtn.addEventListener('click', closeProviderSheet);
    }

    /* ── Provider sheet reset button ── */
    var providerSheetResetBtn = document.getElementById('providerSheetResetBtn');
    if (providerSheetResetBtn) {
        providerSheetResetBtn.addEventListener('click', function() {
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
            loadSlots(false);
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
        if (window.innerWidth > 992) {
            closeProviderSheet();
        }
        syncProviderSidebarAria();
        updateSidebarToggleIcon();
    });
    syncProviderSidebarAria();

    /* ── Category tab arrows ── */
    function scrollCategoryTabs(direction) {
        if (!catScroll) return;
        var amount = Math.max(220, Math.floor(catScroll.clientWidth * 0.65));
        catScroll.scrollBy({ left: direction * amount, behavior: 'smooth' });
    }
    if (catArrowLeft && catScroll) catArrowLeft.addEventListener('click', function() { scrollCategoryTabs(-1); });
    if (catArrowRight && catScroll) catArrowRight.addEventListener('click', function() { scrollCategoryTabs(1); });

    /* Orijinal slider hissi: kategori satırını sürükleyerek yatay kaydır. */
    if (catScroll) {
        catScroll.querySelectorAll('.ds-chip, .cat-tab').forEach(function(tab) {
            tab.setAttribute('draggable', 'false');
            tab.addEventListener('dragstart', function(e) {
                e.preventDefault();
            });
        });

        var categoryDrag = {
            active: false,
            moved: false,
            startX: 0,
            startScrollLeft: 0
        };

        catScroll.addEventListener('pointerdown', function(e) {
            if (e.button !== undefined && e.button !== 0) return;
            categoryDrag.active = true;
            categoryDrag.moved = false;
            categoryDrag.startX = e.clientX;
            categoryDrag.startScrollLeft = catScroll.scrollLeft;
            catScroll.classList.add('is-dragging');
            try {
                catScroll.setPointerCapture(e.pointerId);
            } catch (err) {}
        });

        catScroll.addEventListener('pointermove', function(e) {
            if (!categoryDrag.active) return;
            var dx = e.clientX - categoryDrag.startX;
            if (Math.abs(dx) > 4) {
                categoryDrag.moved = true;
                e.preventDefault();
            }
            catScroll.scrollLeft = categoryDrag.startScrollLeft - dx;
        });

        function endCategoryDrag(e) {
            if (!categoryDrag.active) return;
            categoryDrag.active = false;
            catScroll.classList.remove('is-dragging');
            try {
                catScroll.releasePointerCapture(e.pointerId);
            } catch (err) {}
        }

        catScroll.addEventListener('pointerup', endCategoryDrag);
        catScroll.addEventListener('pointercancel', endCategoryDrag);
        catScroll.addEventListener('click', function(e) {
            if (!categoryDrag.moved) return;
            e.preventDefault();
            e.stopPropagation();
            categoryDrag.moved = false;
        }, true);
    }

    /* Aktif kategori sekmesini görünür yap (scroll alanında ortalanmış) */
    function scrollActiveCategoryIntoView() {
        if (!catScroll) return;
        var activeTab = catScroll.querySelector('.cat-tab.active, .ds-chip--selected');
        if (!activeTab) return;
        requestAnimationFrame(function() {
            var tabLeft = activeTab.offsetLeft;
            var tabWidth = activeTab.offsetWidth;
            var containerWidth = catScroll.clientWidth;
            var maxScroll = catScroll.scrollWidth - containerWidth;
            if (maxScroll <= 0) return;
            var scrollLeft = tabLeft - (containerWidth / 2) + (tabWidth / 2);
            scrollLeft = Math.max(0, Math.min(scrollLeft, maxScroll));
            catScroll.scrollTo({ left: scrollLeft, behavior: 'smooth' });
        });
    }

    /* Kategori çubuğunda fare tekerleği ile yatay kaydırma */
    if (catScroll) {
        catScroll.addEventListener('wheel', function(e) {
            var delta = Math.abs(e.deltaX) > Math.abs(e.deltaY) ? e.deltaX : e.deltaY;
            if (delta === 0) return;
            var maxScroll = catScroll.scrollWidth - catScroll.clientWidth;
            if (maxScroll <= 0) return;
            e.preventDefault();
            catScroll.scrollLeft += delta;
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
    function toggleProvider(provider) {
        const idx = state.providers.indexOf(provider);
        if (idx !== -1) {
            state.providers.splice(idx, 1);
        } else {
            state.providers.push(provider);
        }
        state.nextPage = 2;
        syncMobileFilterControls();
        updateDrawerButtonStates();
        loadSlots(false);
    }

    function selectAllProviders() {
        clearFilters();
        loadSlots(false);
    }

    function activateProviderItem(item) {
        if (!item) return;
        var provider = item.getAttribute('data-provider');
        if (provider) {
            toggleProvider(provider);
        } else if (item.getAttribute('data-provider-all') === '1') {
            selectAllProviders();
        }
    }

    if (sidebarProvidersList) {
        sidebarProvidersList.addEventListener('click', function(e) {
            var item = e.target.closest('.sidebar-provider-item');
            if (!item || !sidebarProvidersList.contains(item)) return;
            e.preventDefault();
            activateProviderItem(item);
        });

        sidebarProvidersList.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var item = e.target.closest('.sidebar-provider-item');
            if (!item || !sidebarProvidersList.contains(item)) return;
            e.preventDefault();
            activateProviderItem(item);
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

    /* ── Infinite scroll: iç scroll + mobilde IO (iOS momentum scroll olayları güvenilir değil) ── */
    const slotsGamesEl = getCasinoGamesContainer(slotPageRoot);
    const loadMoreSentinel = document.getElementById('load-more-sentinel');

    function checkLoadMore() {
        if (!slotsGamesEl || !state.showLoadMore || state.isLoadingMore || state.remainingGames <= 0) return;
        var scrollTop = slotsGamesEl.scrollTop;
        var scrollHeight = slotsGamesEl.scrollHeight;
        var clientHeight = slotsGamesEl.clientHeight;
        var distanceFromBottom = scrollHeight - scrollTop - clientHeight;
        var threshold = Math.max(clientHeight * 0.10, 80); /* mobilde küçük viewport için alt sınır */
        if (distanceFromBottom <= threshold) loadSlots(true);
    }

    if (slotsGamesEl) {
        slotsGamesEl.addEventListener('scroll', function() {
            requestAnimationFrame(checkLoadMore);
        }, { passive: true });
    }

    /* Dokunmatik / momentum: scroll olayı tetiklenmeden alt kısma gelindiğinde yükle */
    if (loadMoreSentinel && typeof IntersectionObserver !== 'undefined') {
        var loadMoreIo = new IntersectionObserver(function(entries) {
            for (var i = 0; i < entries.length; i++) {
                if (!entries[i].isIntersecting) continue;
                if (!state.showLoadMore || state.isLoadingMore || state.remainingGames <= 0) return;
                loadSlots(true);
                return;
            }
        }, {
            root: null,
            /* Alt sabit bar (~60–72px) + erken tetikleme */
            rootMargin: '0px 0px 100px 0px',
            threshold: 0
        });
        loadMoreIo.observe(loadMoreSentinel);
    }

    /* İlk boyut: liste kısa ve iç scroll yoksa veya sentinel zaten görünür alandaysa */
    runAfterLayout(function() {
        requestAnimationFrame(function() {
            checkLoadMore();
        });
    });

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
        Slots: 'slots'
    };

    function getMobileOriginalCategorySort(tab) {
        if (!tab) return null;
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
        catScroll.addEventListener('click', function(e) {
            var chip = e.target.closest('.casinoNavigationAndFilters .casinoCategories .ds-chip');
            if (!chip || !catScroll.contains(chip)) return;
            var sort = getMobileOriginalCategorySort(chip);
            if (sort === null) return;
            e.preventDefault();
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
            var tab = e.target.closest('.cat-tab[data-href]');
            if (!tab || !catScroll.contains(tab) || tab.tagName === 'A') return;
            e.preventDefault();
            var href = tab.getAttribute('data-href');
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
            var label = chip.querySelector('.chip__label');
            var text = label ? (label.textContent || '').trim().toLowerCase() : '';
            if (text === 'lobby') {
                chip.classList.toggle('ds-chip--selected', state.sort === '');
                return;
            }
            if (text === 'tüm oyunlar') {
                chip.classList.remove('ds-chip--selected');
                return;
            }
            var sort = getMobileOriginalCategorySort(chip);
            if (sort === null) return;
            chip.classList.toggle('ds-chip--selected', sort === state.sort);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setActiveCategoryTab();
            scrollActiveCategoryIntoView();
            syncMobileFilterControls();
        });
    } else {
        setActiveCategoryTab();
        scrollActiveCategoryIntoView();
        syncMobileFilterControls();
    }
})();
