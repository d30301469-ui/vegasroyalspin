(function() {
    'use strict';

    /* ── Config (injected by PHP) ── */
    var CONFIG = window.BGAMING_CONFIG || {
        currentPage: 1,
        nextPage: 2,
        pageSize: 30,
        loggedIn: false,
        search: '',
        totalSlots: 0,
        remainingGames: 0,
        showLoadMore: false
    };

    var PAGE_SIZE = Math.min(100, Math.max(1, CONFIG.pageSize || 30));
    var API_ENDPOINT = '/api/v2/games';
    var API_EXTRA_PARAMS = { source: 'bgaming' };
    var FAVORITE_KIND = 'bgaming';
    var EMPTY_TITLE = CONFIG.emptyTitle || 'BGaming oyunu bulunamadı';
    var EMPTY_TEXT  = CONFIG.emptyText || 'Admin panelinden BGaming oyun sync çalıştırın veya arama terimini değiştirin.';
    var PLACEHOLDER_IMG = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDMwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjMwMCIgaGVpZ2h0PSIyMDAiIHJ4PSI4IiBmaWxsPSIjMWExMTJlIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGRvbWluYW50LWJhc2VsaW5lPSJtaWRkbGUiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZpbGw9IiM2NjYiIGZvbnQtZmFtaWx5PSJzYW5zLXNlcmlmIiBmb250LXNpemU9IjE0Ij5ObyBJbWFnZTwvdGV4dD48L3N2Zz4=';

    /* ── State ── */
    var state = {
        search: CONFIG.search || '',
        nextPage: CONFIG.nextPage != null ? CONFIG.nextPage : ((CONFIG.currentPage || 1) + 1),
        totalSlots: CONFIG.totalSlots || 0,
        showLoadMore: CONFIG.showLoadMore !== false,
        remainingGames: CONFIG.remainingGames || 0,
        isLoadingMore: false
    };

    /* ── DOM refs ── */
    var gameGrid       = document.querySelector('#casino_games_container .casinoCategoryGames');
    var searchInput    = document.getElementById('searchModalInput') || document.querySelector('.games-search-input');
    var searchClearBtn = document.getElementById('searchClearBtn');
    var loadMoreSentinel = document.getElementById('load-more-sentinel');
    var randomGameBtn  = document.getElementById('randomGameBtn');
    var searchDebounceTimer = null;
    var SEARCH_DEBOUNCE_MS = 500;

    /* ── Utilities ── */
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function isMobilePlayLaunchMode() {
        if (document.body && document.body.classList.contains('mobile-site')) return true;
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

    function launchPlayUrl(url) {
        if (window.MaltabetWalletPicker && typeof window.MaltabetWalletPicker.launch === 'function') {
            window.MaltabetWalletPicker.launch(url, openPlayUrl);
            return;
        }
        openPlayUrl(url);
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
        if (loginBtn) loginBtn.click();
    }

    function runtimeMemberLoggedIn() {
        var Shared = window.BetcoAuthShared || {};
        if (Shared && typeof Shared.runtimeSessionLoggedIn === 'function' && Shared.runtimeSessionLoggedIn()) return true;
        if (Shared && typeof Shared.getMemberJwt === 'function' && Shared.getMemberJwt() !== '') return true;
        if (window.__USER_LOGGED_IN__ === true || window.__HAS_MEMBER_JWT__ === true) return true;
        return !!CONFIG.loggedIn;
    }

    function handlePlayIntent(event, url) {
        if (event) { event.preventDefault(); event.stopPropagation(); }
        if (runtimeMemberLoggedIn()) { launchPlayUrl(url); return; }
        var Shared = window.BetcoAuthShared || {};
        if (Shared && typeof Shared.hydrateMemberJwt === 'function') {
            Shared.hydrateMemberJwt().then(function() {
                if (runtimeMemberLoggedIn()) { launchPlayUrl(url); return; }
                openLoginModal();
            }).catch(function() { openLoginModal(); });
            return;
        }
        openLoginModal();
    }

    function playTargetUrl(gameId) {
        var id = String(gameId || '');
        return '/play?game_id=' + encodeURIComponent(id) + '&mode=real&wallet=main';
    }

    function playUrlFun(gameId) {
        return '/play?game_id=' + encodeURIComponent(String(gameId || '')) + '&mode=fun';
    }

    /* ── API ── */
    function buildApiUrl(append) {
        var params = new URLSearchParams();
        params.set('source', 'bgaming');
        params.set('game_type', '0');
        if (state.search) params.set('search', state.search);
        params.set('limit', String(PAGE_SIZE));
        params.set('page', String(append ? state.nextPage : 1));
        return API_ENDPOINT + '?' + params.toString();
    }

    function normalizeApiResponse(data) {
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
        });
        // No bgaming filter — this is the dedicated bgaming page.

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

    /* ── Render ── */
    function realPlayClickJs(gameUrlJs) {
        return "if(event){event.preventDefault();event.stopPropagation();}window.__bgamingHandlePlayIntent&&window.__bgamingHandlePlayIntent(event,'" + gameUrlJs + "')";
    }

    window.__bgamingHandlePlayIntent = handlePlayIntent;

    function renderGameItem(game) {
        var name = escapeHtml(game.game_name || '');
        var cover = escapeHtml(game.cover || '');
        var gameId = String(game.game_id || '');
        var gameIdEsc = escapeHtml(gameId);
        var catalogIdRaw = game.id != null && String(game.id).trim() !== '' ? String(game.id) : '';
        var catalogAttr = catalogIdRaw !== '' ? ' data-catalog-id="' + escapeHtml(catalogIdRaw) + '"' : '';
        var gameUrl = playTargetUrl(gameId);
        var gameUrlJs = gameUrl.replace(/\\/g, '\\\\').replace(/'/g, '\\\'');
        var demoUrl = playUrlFun(gameId);

        return (
            '<div class="casinoGameItemContent " data-favorite-kind="bgaming" data-game-id="' + gameIdEsc + '"' + catalogAttr + ' onclick="' + realPlayClickJs(gameUrlJs) + '">' +
            '<span class="providerBadgeBlock " data-badge=""></span>' +
            '<div class="casinoGameItem ">' +
            '<img alt="' + name + '" loading="lazy" src="' + cover + '" data-src="' + cover + '" class="casinoGameItemImage" title="' + name + '" style="aspect-ratio: 44 / 31;" onerror="this.src=\'' + PLACEHOLDER_IMG + '\'">' +
            '<i class="casinoGameItemFavBc bc-i-favorite "></i>' +
            '<div class="game-overlay">' +
            '<div class="game-overlay-top"></div>' +
            '<div class="game-title-wrap"><p class="game-title-text">' + name + '</p></div>' +
            '<div class="game-actions">' +
            '<a class="play-btn" href="' + escapeHtml(gameUrl) + '" onclick="' + realPlayClickJs(gameUrlJs) + '">OYNA</a>' +
            '<a class="demo-btn" href="' + escapeHtml(demoUrl) + '" onclick="event.stopPropagation()">DEMO</a>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>'
        );
    }

    function renderEmptyState() {
        return (
            '<div class="empty-state">' +
            '<i class="fas fa-gamepad"></i>' +
            '<h3>' + escapeHtml(EMPTY_TITLE) + '</h3>' +
            '<p>' + escapeHtml(EMPTY_TEXT) + '</p>' +
            '</div>'
        );
    }

    function renderSkeletonItems(count) {
        var html = '';
        var skeleton = '<div class="casinoGameItemContent skeleton-loader-game-cube slot-skeleton-item"></div>';
        for (var i = 0; i < count; i++) { html += skeleton; }
        return html;
    }

    function preloadImages(urls, timeoutMs) {
        timeoutMs = timeoutMs || 6000;
        return Promise.all(urls.map(function(url) {
            if (!url) return Promise.resolve();
            return new Promise(function(resolve) {
                var img = new Image();
                var t = setTimeout(function() { resolve(); }, timeoutMs);
                img.onload = img.onerror = function() { clearTimeout(t); resolve(); };
                img.src = url;
            });
        }));
    }

    function removeLastSkeletons(count) {
        if (!gameGrid) return;
        var skeletons = gameGrid.querySelectorAll('.skeleton-loader-game-cube.slot-skeleton-item');
        var toRemove = Math.min(count, skeletons.length);
        var list = Array.prototype.slice.call(skeletons, skeletons.length - toRemove);
        list.forEach(function(el) { if (el.parentNode) el.parentNode.removeChild(el); });
    }

    /* ── Load slots ── */
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
                    if (append) { removeLastSkeletons(requestLimit); state.isLoadingMore = false; }
                    else { gameGrid.innerHTML = renderEmptyState(); }
                    return;
                }
                state.totalSlots = data.totalSlots || 0;
                state.remainingGames = data.remainingGames != null ? data.remainingGames : 0;
                state.showLoadMore = !!data.showLoadMore;
                if (data.nextPage != null) state.nextPage = data.nextPage;
                else if (append && data.page != null) state.nextPage = (data.page || 1) + 1;

                var games = data.games || [];
                if (games.length === 0) {
                    if (append) { removeLastSkeletons(requestLimit); state.showLoadMore = false; }
                    else { gameGrid.innerHTML = renderEmptyState(); }
                    state.isLoadingMore = false;
                    return;
                }

                var coverUrls = games.map(function(g) { return g.cover || ''; });
                preloadImages(coverUrls, 6000).then(function() {
                    if (append) {
                        removeLastSkeletons(requestLimit);
                        gameGrid.insertAdjacentHTML('beforeend', games.map(renderGameItem).join(''));
                    } else {
                        gameGrid.innerHTML = games.map(renderGameItem).join('');
                    }
                    state.isLoadingMore = false;
                    requestAnimationFrame(function() { checkLoadMore(); });
                });
            })
            .catch(function() {
                state.isLoadingMore = false;
                if (!append) gameGrid.innerHTML = renderEmptyState();
                else removeLastSkeletons(requestLimit);
            });
    }

    /* ── Load more (infinite scroll) ── */
    function checkLoadMore() {
        if (!state.showLoadMore || state.isLoadingMore || state.remainingGames <= 0) return;
        loadSlots(true);
    }

    if (loadMoreSentinel && typeof IntersectionObserver !== 'undefined') {
        var loadMoreIo = new IntersectionObserver(function(entries) {
            for (var i = 0; i < entries.length; i++) {
                if (!entries[i].isIntersecting) continue;
                if (!state.showLoadMore || state.isLoadingMore || state.remainingGames <= 0) return;
                loadSlots(true);
                return;
            }
        }, { root: null, rootMargin: '0px 0px 200px 0px', threshold: 0 });
        loadMoreIo.observe(loadMoreSentinel);
    }

    /* İlk yüklemede sentinel görünür alandaysa hemen tetikle */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            requestAnimationFrame(function() { requestAnimationFrame(checkLoadMore); });
        });
    } else {
        requestAnimationFrame(function() { requestAnimationFrame(checkLoadMore); });
    }

    /* ── Search ── */
    function applySearch(value) {
        if (!searchInput && value === undefined) return;
        var term = value !== undefined ? value : searchInput.value;
        state.search = String(term || '').trim();
        state.nextPage = 2;
        loadSlots(false);
    }

    function clearSearch() {
        if (searchInput) searchInput.value = '';
        state.search = '';
        state.nextPage = 2;
        loadSlots(false);
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

    if (searchClearBtn) {
        searchClearBtn.addEventListener('click', function() {
            if (searchInput && searchInput.value.trim().length > 0) clearSearch();
        });
    }

    /* ── Random Game ── */
    if (!randomGameBtn && document.body.classList.contains('mobile-site')) {
        var searchRow = document.querySelector('.casinoTitleSearch');
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
            var gameItems = document.querySelectorAll('.casinoGameItemContent[data-game-id]');
            if (gameItems.length === 0) return;
            var randomIndex = Math.floor(Math.random() * gameItems.length);
            gameItems[randomIndex].click();
        });
    }

})();
