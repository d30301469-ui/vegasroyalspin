/**
 * Login / register + member API client (direct api.bo-nexthub.site + same-origin proxy for session).
 */
'use strict';
(function (w) {
    var JWT_KEY = 'app_member_jwt';
    var JWT_MEMORY_KEY = '__MEMBER_JWT_MEMORY__';

    function readJwtFromMemory() {
        try {
            return String(w[JWT_MEMORY_KEY] || '').trim();
        } catch (eMem) {
            return '';
        }
    }

    function writeJwtToMemory(token) {
        try {
            w[JWT_MEMORY_KEY] = String(token || '').trim();
        } catch (eWrite) {
            /* ignore */
        }
    }

    function migrateLegacyJwtStorage() {
        try {
            var legacy = String(w.localStorage.getItem(JWT_KEY) || w.localStorage.getItem('metropol_member_jwt') || '').trim();
            if (legacy !== '') {
                writeJwtToMemory(legacy);
            }
            w.localStorage.removeItem(JWT_KEY);
            w.localStorage.removeItem('metropol_member_jwt');
        } catch (eMigrate) {
            /* ignore */
        }
    }

    var BOOTSTRAP_ROUTES = {
        '/auth/login': true,
        '/auth/register': true,
        '/auth/password-reset': true,
        '/auth/forgot-password': true,
        '/login.php': true,
        '/register.php': true,
        '/forgot_password.php': true,
        '/password_reset.php': true
    };

    /** Yalnızca PHP session cookie — Authorization gönderme (stale JWT önlenir). */
    var SESSION_COOKIE_ROUTES = {
        '/auth/session': true,
        '/session.php': true
    };

    /** Oturum/refresh/logout + bakiye/sadakat — same-origin proxy (PHP session JWT). */
    var SESSION_PROXY_ROUTES = {
        '/auth/session': true,
        '/session.php': true,
        '/auth/refresh': true,
        '/auth/logout': true,
        '/logout.php': true,
        '/balance': true,
        '/balance.php': true,
        '/loyalty': true,
        '/loyalty.php': true,
        '/game-launch': true,
        '/game_launch.php': true,
        '/sportsbook-launch': true,
        '/sportsbook_launch.php': true,
        '/sportsbook/launch': true
    };

    function basePath() {
        var configured = typeof w.__APP_BASE_PATH__ === 'string' ? w.__APP_BASE_PATH__ : '';
        return configured.replace(/\/+$/, '');
    }

    function memberApiBase() {
        var base = typeof w.__MEMBER_API_BASE__ === 'string' ? w.__MEMBER_API_BASE__ : '';
        return base.replace(/\/+$/, '');
    }

    function directMemberApiEnabled() {
        return w.__FRONTEND_DIRECT_MEMBER_API__ !== false && memberApiBase() !== '';
    }

    function normalizeMemberPath(path) {
        var p = String(path || '');
        if (/^(?:[a-z][a-z0-9+.-]*:)?\/\//i.test(p)) {
            return p;
        }
        if (p.indexOf('/api/v2') === 0) {
            p = p.slice('/api/v2'.length);
        }
        if (p.charAt(0) !== '/') {
            p = '/' + p;
        }
        return p;
    }

    function isBootstrapRoute(path) {
        return !!BOOTSTRAP_ROUTES[normalizeMemberPath(path).toLowerCase()];
    }

    function isSessionCookieRoute(path) {
        return !!SESSION_COOKIE_ROUTES[normalizeMemberPath(path).toLowerCase()];
    }

    function isSessionProxyRoute(path) {
        return !!SESSION_PROXY_ROUTES[normalizeMemberPath(path).toLowerCase()];
    }

    function shouldUseSessionProxy(path) {
        var p = normalizeMemberPath(path).toLowerCase();
        if (isSessionProxyRoute(p)) {
            return true;
        }
        if (!phpSessionLoggedIn() || !needsMemberAuth(path)) {
            return false;
        }
        return !Shared.getMemberJwt || Shared.getMemberJwt() === '';
    }

    function forceProxyRoute(path) {
        return isBootstrapRoute(path) || isSessionProxyRoute(path) || shouldUseSessionProxy(path);
    }

    function proxiedSameOrigin(url) {
        return typeof url === 'string' && url.indexOf('/api/v2/') === 0;
    }

    function memberRequestInit(url, extraHeaders) {
        var path = normalizeMemberPath(typeof url === 'string' ? url : '');
        var proxied = isProxiedMemberUrl(typeof url === 'string' ? url : '');
        if (proxied && (forceProxyRoute(path) || forceProxyRoute(url) || !directMemberApiEnabled())) {
            return {
                credentials: 'same-origin',
                headers: Shared.memberSessionHeaders(extraHeaders || {})
            };
        }
        if (proxied) {
            return {
                credentials: 'same-origin',
                headers: Shared.memberSessionHeaders(extraHeaders || {})
            };
        }
        return {
            credentials: isBootstrapRoute(url) || isSessionProxyRoute(url) ? 'same-origin' : (directMemberApiEnabled() ? 'include' : 'same-origin'),
            headers: Shared.memberAuthHeaders(extraHeaders || {})
        };
    }

    function needsMemberAuth(path) {
        var p = normalizeMemberPath(path).toLowerCase();
        if (isBootstrapRoute(p)) {
            return false;
        }
        if (p.indexOf('/content/') === 0 || p.indexOf('content/') === 0) {
            return false;
        }
        var publicExact = {
            '/winners': true,
            '/winners.php': true,
            '/announcements': true,
            '/announcements.php': true,
            '/site-settings': true,
            '/site_settings.php': true,
            '/site-settings.php': true,
            '/track-visit': true,
            '/track_visit.php': true,
            '/games': true,
            '/games.php': true
        };
        if (publicExact[p]) {
            return false;
        }
        return /(^|\/)(balance|loyalty|me|profile|deposit|withdraw|bonus|game-launch|game_launch|favorite|payment|kyc|notification|freespin|referral|wallet|account|auth\/session|session\.php)/.test(p);
    }

    function isLogoutLanding() {
        try {
            return new URLSearchParams(w.location.search || '').get('logout') === '1';
        } catch (eLogout) {
            return false;
        }
    }

    function isProxiedMemberUrl(url) {
        if (typeof url !== 'string' || url === '') {
            return false;
        }
        if (url.indexOf('/api/v2/') === 0) {
            return true;
        }
        var base = basePath();
        if (base && url.indexOf(base + '/api/v2/') === 0) {
            return true;
        }
        try {
            var parsed = new URL(url, w.location.origin);
            return parsed.origin === w.location.origin && parsed.pathname.indexOf('/api/v2/') !== -1;
        } catch (eUrl) {
            return false;
        }
    }

    function phpSessionLoggedIn() {
        if (w.__USER_LOGGED_IN__ === true) {
            return true;
        }
        if (w.__MEMBER_BOOTSTRAP_STATE__ && typeof w.__MEMBER_BOOTSTRAP_STATE__ === 'object') {
            return w.__MEMBER_BOOTSTRAP_STATE__.logged_in === true;
        }
        return false;
    }

    function runtimeSessionLoggedIn() {
        if (isLogoutLanding()) {
            return false;
        }
        if (phpSessionLoggedIn()) {
            return true;
        }
        if (Shared.getMemberJwt && Shared.getMemberJwt() !== '') {
            return true;
        }
        return typeof w.__MEMBER_JWT_BOOTSTRAP__ === 'string' && w.__MEMBER_JWT_BOOTSTRAP__.trim() !== '';
    }

    function normalizePagePath(pathname) {
        var p = String(pathname || '').trim();
        if (p === '') {
            return '/';
        }
        if (p.charAt(0) !== '/') {
            p = '/' + p;
        }
        if (p.length > 1) {
            p = p.replace(/\/+$/, '');
        }
        return p.toLowerCase();
    }

    function parseSameOriginUrl(urlLike) {
        try {
            var parsed = new URL(String(urlLike || ''), w.location.origin);
            if (parsed.origin !== w.location.origin) {
                return null;
            }
            return parsed;
        } catch (eUrl) {
            return null;
        }
    }

    function sessionRequiredPage(urlLike) {
        var parsed = parseSameOriginUrl(urlLike);
        if (!parsed) {
            return false;
        }
        var path = normalizePagePath(parsed.pathname);
        if (path.indexOf('/profile/') === 0 || path === '/mobile/profile') {
            return true;
        }
        if (path === '/deposit' || path === '/withdraw') {
            return true;
        }
        if (path === '/') {
            var profile = (parsed.searchParams.get('profile') || '').toLowerCase();
            var account = (parsed.searchParams.get('account') || '').toLowerCase();
            if (profile === 'open' && account !== '') {
                return true;
            }
        }
        return false;
    }

    function showLoginPrompt() {
        try {
            if (typeof w.__openLoginModal === 'function') {
                w.__openLoginModal();
                return;
            }
            if (w.MaltabetAuth && typeof w.MaltabetAuth.showLoginModal === 'function') {
                w.MaltabetAuth.showLoginModal();
                return;
            }
            if (typeof w.showLoginWarning === 'function') {
                w.showLoginWarning();
                return;
            }
            var loginBtn = document.getElementById('Giris');
            if (loginBtn && typeof loginBtn.click === 'function') {
                loginBtn.click();
                return;
            }
            // Son care: login.js henuz yuklenmediyse gecikmeli tekrar dene.
            // MutationObserver ile DOM'da #login2 modalini bekle, gelince ac.
            if (!document.getElementById('login2')) {
                if (typeof MutationObserver !== 'undefined' && !w.__loginPromptObserverAttached) {
                    w.__loginPromptObserverAttached = true;
                    var observer = new MutationObserver(function () {
                        if (typeof w.__openLoginModal === 'function') {
                            observer.disconnect();
                            w.__openLoginModal();
                        }
                    });
                    observer.observe(document.documentElement || document.body, { childList: true, subtree: true });
                    // Guvenlik supabi: 5 saniye sonra observer'i temizle
                    window.setTimeout(function () { observer.disconnect(); w.__loginPromptObserverAttached = false; }, 5000);
                }
            }
        } catch (eLogin) {
            /* ignore */
        }
    }

    function sessionHintActive() {
        if (isLogoutLanding()) {
            return false;
        }
        if (Shared.getMemberJwt && Shared.getMemberJwt() !== '') {
            return true;
        }
        if (restoreSessionHintActive()) {
            return true;
        }
        if (!phpSessionLoggedIn()) {
            return false;
        }
        return typeof w.__MEMBER_JWT_BOOTSTRAP__ === 'string' && w.__MEMBER_JWT_BOOTSTRAP__.trim() !== '';
    }

    function restoreSessionHintActive() {
        if (isLogoutLanding()) {
            return false;
        }
        var state = w.__MEMBER_BOOTSTRAP_STATE__;
        if (state && typeof state === 'object') {
            if (state.logged_in === true || state.has_session_jwt === true || state.has_restore_cookie === true) {
                return true;
            }
        }
        if (w.__HAS_MEMBER_JWT__ === true) {
            return true;
        }
        var bootstrap = typeof w.__MEMBER_JWT_BOOTSTRAP__ === 'string'
            ? w.__MEMBER_JWT_BOOTSTRAP__.trim()
            : '';
        return bootstrap !== '';
    }

    function shouldAttemptSessionHydrate() {
        if (isLogoutLanding()) {
            return false;
        }
        if (phpSessionLoggedIn() || runtimeSessionLoggedIn()) {
            return true;
        }
        if (restoreSessionHintActive()) {
            return true;
        }
        if (typeof w.__MEMBER_LOGIN_AT__ === 'number' && (Date.now() - w.__MEMBER_LOGIN_AT__) < 30000) {
            return true;
        }
        return false;
    }

    var memberAuthFailureInFlight = false;

    function sessionOnlyHeaders(extra) {
        var h = extra || {};
        var csrf = typeof w.__CSRF_TOKEN__ === 'string' ? w.__CSRF_TOKEN__.trim() : '';
        if (csrf) {
            h['X-CSRF-Token'] = csrf;
        }
        // Send JWT from localStorage as fallback for split-deploy setups
        // where the PHP session may not persist across server instances.
        var jwt = Shared.getMemberJwt();
        if (jwt) {
            h['X-App-Member-Jwt'] = jwt;
        }
        return h;
    }

    function persistMemberJwtCookie(token) {
        // Durable restore cookie is now managed server-side as HttpOnly.
        // Keep this function only as a legacy clear path for old JS-managed cookies.
        var value = String(token || '').trim();
        if (value !== '') {
            return;
        }
        document.cookie = 'app_member_jwt=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax';
        document.cookie = 'metropol_member_jwt=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax';
    }

    function emitJwtReady() {
        try {
            w.dispatchEvent(new CustomEvent('app:member-jwt-ready', {
                detail: { token: Shared.getMemberJwt() }
            }));
        } catch (e) {
            /* ignore */
        }
    }

    var Shared = {
        onReady: function (fn) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', fn);
            } else {
                fn();
            }
        },
        getMemberJwt: function () {
            var fromMemory = readJwtFromMemory();
            if (fromMemory !== '') {
                return fromMemory;
            }
            var bootstrap = typeof w.__MEMBER_JWT_BOOTSTRAP__ === 'string'
                ? w.__MEMBER_JWT_BOOTSTRAP__.trim()
                : '';
            if (bootstrap !== '') {
                writeJwtToMemory(bootstrap);
                return bootstrap;
            }
            migrateLegacyJwtStorage();
            return readJwtFromMemory();
        },
        setMemberJwt: function (token) {
            var t = String(token || '').trim();
            var previous = this.getMemberJwt();
            try {
                if (t === '') {
                    writeJwtToMemory('');
                    try { w.localStorage.removeItem(JWT_KEY); w.localStorage.removeItem('metropol_member_jwt'); } catch (eLegacy) {}
                    document.documentElement.classList.remove('member-session-hint');
                    w.__HAS_MEMBER_JWT__ = false;
                    persistMemberJwtCookie('');
                } else {
                    writeJwtToMemory(t);
                    try { w.localStorage.removeItem(JWT_KEY); w.localStorage.removeItem('metropol_member_jwt'); } catch (eLegacy2) {}
                    document.documentElement.classList.add('member-session-hint');
                    w.__MEMBER_LOGIN_AT__ = Date.now();
                    w.__HAS_MEMBER_JWT__ = true;
                    persistMemberJwtCookie(t);
                    if (phpSessionLoggedIn()) {
                        w.__USER_LOGGED_IN__ = true;
                    }
                    emitJwtReady();
                }
                if (previous !== t) {
                    w.dispatchEvent(new CustomEvent('app:member-jwt-changed', {
                        detail: { authenticated: t !== '' }
                    }));
                }
            } catch (e2) {
                /* ignore */
            }
        },
        clearMemberJwt: function () {
            this.setMemberJwt('');
        },
        logout: function () {
            var self = this;
            var savedJwt = self.getMemberJwt();
            var logoutUrl = self.proxyApiUrl('/auth/logout');
            var headers = self.memberSessionHeaders({
                Accept: 'application/json',
                'Content-Type': 'application/json'
            });
            if (savedJwt) {
                headers['X-App-Member-Jwt'] = savedJwt;
                headers.Authorization = 'Bearer ' + savedJwt;
            }

            self.clearMemberJwt();
            w.__USER_LOGGED_IN__ = false;
            w.__HAS_MEMBER_JWT__ = false;
            w.__MEMBER_JWT_BOOTSTRAP__ = '';

            return w.fetch(logoutUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers,
                body: '{}'
            }).catch(function () {
                return null;
            }).then(function () {
                w.location.href = '/?logout=1';
                return true;
            });
        },
        memberApiBase: memberApiBase,
        isBootstrapRoute: isBootstrapRoute,
        proxyApiUrl: function (path) {
            var p = String(path || '');
            if (/^(?:[a-z][a-z0-9+.-]*:)?\/\//i.test(p)) {
                return p;
            }
            if (p.indexOf('/api/v2') !== 0) {
                p = '/api/v2' + normalizeMemberPath(p);
            }
            var base = basePath();
            if (base && p.indexOf('/api/') === 0 && base.indexOf('/api') !== -1) {
                return p;
            }
            return base + p;
        },
        memberApiUrl: function (path) {
            if (forceProxyRoute(path)) {
                return this.proxyApiUrl(path);
            }
            var base = memberApiBase();
            if (!base || !directMemberApiEnabled()) {
                var legacy = String(path || '');
                if (legacy.indexOf('/api/v2') !== 0) {
                    legacy = '/api/v2' + normalizeMemberPath(legacy);
                }
                return this.proxyApiUrl(legacy);
            }
            return base + normalizeMemberPath(path);
        },
        memberRequestInit: memberRequestInit,
        memberCredentials: function () {
            return directMemberApiEnabled() ? 'include' : 'same-origin';
        },
        apiUrl: function (path) {
            var p = String(path || '');
            if (/^(?:[a-z][a-z0-9+.-]*:)?\/\//i.test(p)) {
                return p;
            }
            if (p.indexOf('/api/v2/') === 0) {
                if (forceProxyRoute(p)) {
                    return this.proxyApiUrl(p);
                }
                if (isBootstrapRoute(p)) {
                    return this.proxyApiUrl(p);
                }
                if (directMemberApiEnabled()) {
                    return this.memberApiUrl(p);
                }
            }
            if (p.charAt(0) !== '/') {
                p = '/' + p;
            }
            var base = basePath();
            if (base) {
                if (p === base || p.indexOf(base + '/') === 0) {
                    return p;
                }
                if (p.indexOf('/api/') === 0 && base.indexOf('/api') !== -1) {
                    return p;
                }
            }
            return base + p;
        },
        memberAuthHeaders: function (extra) {
            var h = extra || {};
            var jwt = this.getMemberJwt();
            if (jwt) {
                h.Authorization = 'Bearer ' + jwt;
            }
            var csrf = typeof w.__CSRF_TOKEN__ === 'string' ? w.__CSRF_TOKEN__.trim() : '';
            if (csrf) {
                h['X-CSRF-Token'] = csrf;
            }
            return h;
        },
        memberSessionHeaders: function (extra) {
            return sessionOnlyHeaders(extra);
        },
        hasMemberJwt: function () {
            return this.getMemberJwt() !== '';
        },
        runtimeSessionLoggedIn: runtimeSessionLoggedIn,
        restoreSessionHintActive: restoreSessionHintActive,
        shouldAttemptSessionHydrate: shouldAttemptSessionHydrate,
        isSessionRequiredPage: sessionRequiredPage,
        ensureSessionForPage: function (urlLike) {
            if (!sessionRequiredPage(urlLike)) {
                return true;
            }
            if (runtimeSessionLoggedIn()) {
                return true;
            }
            showLoginPrompt();
            return false;
        },
        isLogoutLanding: isLogoutLanding,
        phpSessionLoggedIn: phpSessionLoggedIn,
        handleMemberAuthFailure: function () {
            var self = this;
            var savedJwt = self.getMemberJwt();
            var bootstrapJwt = typeof w.__MEMBER_JWT_BOOTSTRAP__ === 'string'
                ? w.__MEMBER_JWT_BOOTSTRAP__.trim()
                : '';
            if (isLogoutLanding()) {
                self.clearMemberJwt();
                w.__USER_LOGGED_IN__ = false;
                w.__HAS_MEMBER_JWT__ = false;
                return Promise.resolve(false);
            }
            if (!phpSessionLoggedIn() && savedJwt === '' && bootstrapJwt === '') {
                self.clearMemberJwt();
                w.__USER_LOGGED_IN__ = false;
                w.__HAS_MEMBER_JWT__ = false;
                return Promise.resolve(false);
            }
            if (memberAuthFailureInFlight) {
                return Promise.resolve(false);
            }
            memberAuthFailureInFlight = true;
            // localStorage JWT'yi temizlemeden ONCE kaydet — /auth/session
            // isteginde bu JWT'yi gonder ki sunucu oturumu dogrulayabilsin.
            // Recovery basarisiz olursa JWT'yi geri yukle.
            self.clearMemberJwt();

            var sessionUrl = self.proxyApiUrl('/auth/session');
            var headers = self.memberSessionHeaders({ Accept: 'application/json' });
            if (savedJwt) {
                headers['X-App-Member-Jwt'] = savedJwt;
            }
            return w.fetch(sessionUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: headers
            }).then(function (res) {
                return res.text().then(function (text) {
                    var data = null;
                    try {
                        data = text ? JSON.parse(text.replace(/^\uFEFF/, '').trim()) : null;
                    } catch (eJson) {
                        data = null;
                    }
                    if (!data || data.success !== true) {
                        return false;
                    }
                    self.applyLoginEnvelope(data);
                    return self.getMemberJwt() !== '' || phpSessionLoggedIn();
                });
            }).catch(function () {
                return false;
            }).then(function (ok) {
                memberAuthFailureInFlight = false;
                if (!ok) {
                    // Recovery basarisiz — JWT'yi geri yukle ki sonraki
                    // sayfa yuklemesi (shouldAttemptProfileAuthReload) veya
                    // tekrar deneme sirasinda localStorage'da bulunsun.
                    if (savedJwt) {
                        self.setMemberJwt(savedJwt);
                    }
                    w.__USER_LOGGED_IN__ = false;
                    w.__HAS_MEMBER_JWT__ = false;
                    try {
                        w.dispatchEvent(new CustomEvent('app:member-auth-lost'));
                    } catch (eEv) {
                        /* ignore */
                    }
                }
                return ok;
            });
        },
        memberFetch: function (path, options) {
            options = options || {};
            var url = this.memberApiUrl(path);
            var resolved = memberRequestInit(url, options.headers || {});
            options.credentials = resolved.credentials;
            options.headers = resolved.headers;
            return w.fetch(url, options);
        },
        applyLoginEnvelope: function (data) {
            if (!data || data.success !== true) {
                return false;
            }
            var payload = data.data && typeof data.data === 'object' ? data.data : {};
            var token = String(payload.token || data.token || '').trim();
            w.__USER_LOGGED_IN__ = true;
            if (w.__MEMBER_BOOTSTRAP_STATE__ && typeof w.__MEMBER_BOOTSTRAP_STATE__ === 'object') {
                w.__MEMBER_BOOTSTRAP_STATE__.logged_in = true;
                if (payload.user_id) {
                    w.__MEMBER_BOOTSTRAP_STATE__.user_id = payload.user_id;
                }
                if (token !== '') {
                    w.__MEMBER_BOOTSTRAP_STATE__.has_session_jwt = true;
                }
            }
            if (token !== '') {
                this.setMemberJwt(token);
                w.__HAS_MEMBER_JWT__ = true;
                return true;
            }
            return phpSessionLoggedIn();
        },
        refreshMemberJwt: function () {
            var self = this;
            var jwt = self.getMemberJwt();
            if (jwt === '') {
                return Promise.resolve('');
            }
            var refreshUrl = self.proxyApiUrl('/auth/refresh');
            return w.fetch(refreshUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: self.memberSessionHeaders({
                    Accept: 'application/json',
                    'Content-Type': 'application/json'
                }),
                body: '{}'
            }).then(function (res) {
                return res.text().then(function (text) {
                    var data = null;
                    try {
                        data = text ? JSON.parse(text.replace(/^\uFEFF/, '').trim()) : null;
                    } catch (eJson) {
                        data = null;
                    }
                    if (!data || data.success !== true) {
                        return '';
                    }
                    self.applyLoginEnvelope(data);
                    return self.getMemberJwt();
                });
            }).catch(function () {
                return '';
            });
        },
        hydrateMemberJwt: function () {
            var self = this;
            // Dedup: if a hydration is already in flight, chain onto it
            // instead of firing duplicate /auth/session requests.
            if (self.__hydratePromise) {
                return self.__hydratePromise;
            }
            if (isLogoutLanding()) {
                self.clearMemberJwt();
                w.__USER_LOGGED_IN__ = false;
                w.__HAS_MEMBER_JWT__ = false;
                w.__MEMBER_JWT_BOOTSTRAP__ = '';
                return Promise.resolve('');
            }
            var bootstrap = typeof w.__MEMBER_JWT_BOOTSTRAP__ === 'string'
                ? w.__MEMBER_JWT_BOOTSTRAP__.trim()
                : '';
            var phpLoggedIn = phpSessionLoggedIn();

            if (bootstrap !== '' && phpLoggedIn) {
                self.setMemberJwt(bootstrap);
                return Promise.resolve(bootstrap);
            }

            if (!shouldAttemptSessionHydrate()) {
                self.__hydratePromise = Promise.resolve('');
                return self.__hydratePromise;
            }

            if (!phpLoggedIn && !sessionHintActive()) {
                var recentLogin = typeof w.__MEMBER_LOGIN_AT__ === 'number'
                    && (Date.now() - w.__MEMBER_LOGIN_AT__) < 30000;
                if (recentLogin && self.getMemberJwt() !== '') {
                    // keep in-memory token from login before reload
                }
            }

            var sessionUrl = self.proxyApiUrl('/auth/session');
            self.__hydratePromise = w.fetch(sessionUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: self.memberSessionHeaders({ Accept: 'application/json' })
            }).then(function (res) {
                return res.text().then(function (text) {
                    var data = null;
                    try {
                        data = text ? JSON.parse(text.replace(/^\uFEFF/, '').trim()) : null;
                    } catch (eJson) {
                        data = null;
                    }
                    if (data && data.success === true && self.applyLoginEnvelope(data)) {
                        return self.getMemberJwt();
                    }
                    var existing = self.getMemberJwt();
                    if (existing !== '' && phpLoggedIn) {
                        return self.refreshMemberJwt().then(function (refreshed) {
                            return refreshed !== '' ? refreshed : existing;
                        });
                    }
                    if (!phpLoggedIn) {
                        if (existing !== '') {
                            return existing;
                        }

                        return '';
                    }
                    return existing;
                });
            }).catch(function () {
                return self.getMemberJwt();
            }).then(function (result) {
                self.__hydratePromise = null;
                return result;
            });
            return self.__hydratePromise;
        },
        setSubmitButtonLoading: function (submitBtn, loading) {
            if (!submitBtn) {
                return;
            }
            submitBtn.disabled = !!loading;
            var btnText = submitBtn.querySelector('.btn-text');
            var btnLoading = submitBtn.querySelector('.loading');
            if (btnText) {
                btnText.style.display = loading ? 'none' : '';
            }
            if (btnLoading) {
                btnLoading.style.display = loading ? 'inline-block' : 'none';
            }
        },
        turnstileEnabled: function () {
            if (w.__TURNSTILE_ENABLED__ === true || w.__TURNSTILE_ENABLED__ === 1 || w.__TURNSTILE_ENABLED__ === '1') {
                return true;
            }
            // Hydrate / API sonrasi __SITE_SETTINGS__ guncel olabilir.
            var settings = w.__SITE_SETTINGS__;
            if (settings && typeof settings === 'object') {
                var root = (settings.site_settings && typeof settings.site_settings === 'object')
                    ? settings.site_settings
                    : settings;
                var value = root.turnstile_enabled;
                if (value === true || value === 1 || value === '1') {
                    return true;
                }
                var normalized = String(value == null ? '' : value).trim().toLowerCase();
                return normalized === 'true' || normalized === 'on' || normalized === 'yes';
            }
            return false;
        },
        turnstileSiteKey: function () {
            if (typeof w.__TURNSTILE_SITE_KEY__ === 'string' && w.__TURNSTILE_SITE_KEY__.trim() !== '') {
                return w.__TURNSTILE_SITE_KEY__.trim();
            }
            var settings = w.__SITE_SETTINGS__;
            if (settings && typeof settings === 'object') {
                var root = (settings.site_settings && typeof settings.site_settings === 'object')
                    ? settings.site_settings
                    : settings;
                return String(root.turnstile_site_key || settings.turnstile_site_key || '').trim();
            }
            return '';
        },
        hasTurnstile: function () {
            return this.turnstileEnabled() && this.turnstileSiteKey() !== '';
        },
        resolveTurnstileContainer: function (container) {
            if (!container) return null;
            if (typeof container === 'string') {
                return document.querySelector(container);
            }
            return container.nodeType === 1 ? container : null;
        },
        renderTurnstileWidget: function (container, options) {
            if (!this.hasTurnstile() || !w.turnstile || typeof w.turnstile.render !== 'function') {
                return '';
            }
            var el = this.resolveTurnstileContainer(container);
            if (!el) {
                return '';
            }
            var existing = el.getAttribute('data-turnstile-widget-id');
            if (existing) {
                return existing;
            }
            var cfg = options && typeof options === 'object' ? options : {};
            var widgetId = w.turnstile.render(el, {
                sitekey: this.turnstileSiteKey(),
                theme: cfg.theme || 'dark',
                size: cfg.size || 'flexible',
                action: cfg.action || '',
                callback: typeof cfg.callback === 'function' ? cfg.callback : undefined,
                'error-callback': typeof cfg.errorCallback === 'function' ? cfg.errorCallback : undefined,
                'expired-callback': typeof cfg.expiredCallback === 'function' ? cfg.expiredCallback : undefined,
            });
            if (widgetId !== undefined && widgetId !== null && widgetId !== '') {
                el.setAttribute('data-turnstile-widget-id', String(widgetId));
                return String(widgetId);
            }

            return '';
        },
        turnstileTokenFromContainer: function (container) {
            if (!this.hasTurnstile() || !w.turnstile || typeof w.turnstile.getResponse !== 'function') {
                return '';
            }
            var el = this.resolveTurnstileContainer(container);
            if (!el) {
                return '';
            }
            var widgetId = el.getAttribute('data-turnstile-widget-id') || '';
            if (widgetId === '') {
                return '';
            }

            return String(w.turnstile.getResponse(widgetId) || '').trim();
        },
        resetTurnstileWidget: function (container) {
            if (!this.hasTurnstile() || !w.turnstile || typeof w.turnstile.reset !== 'function') {
                return;
            }
            var el = this.resolveTurnstileContainer(container);
            if (!el) {
                return;
            }
            var widgetId = el.getAttribute('data-turnstile-widget-id') || '';
            if (widgetId === '') {
                return;
            }
            try {
                w.turnstile.reset(widgetId);
            } catch (e) {
                /* ignore */
            }
        },
        MSG_CONN: 'Bağlantı hatası. Lütfen tekrar deneyin.'
    };

    function syncLoginFlagsFromStorage() {
        if (isLogoutLanding()) {
            Shared.clearMemberJwt();
            w.__USER_LOGGED_IN__ = false;
            w.__HAS_MEMBER_JWT__ = false;
            w.__MEMBER_JWT_BOOTSTRAP__ = '';
            return;
        }

        var bootstrapJwt = typeof w.__MEMBER_JWT_BOOTSTRAP__ === 'string'
            ? w.__MEMBER_JWT_BOOTSTRAP__.trim()
            : '';
        var phpLoggedIn = phpSessionLoggedIn();
        w.__USER_LOGGED_IN__ = phpLoggedIn;
        var storedJwt = Shared.getMemberJwt();

        if (bootstrapJwt !== '' && phpLoggedIn) {
            Shared.setMemberJwt(bootstrapJwt);
            w.__HAS_MEMBER_JWT__ = true;
            return;
        }

        // Preserve JWT from localStorage across page reloads — the PHP
        // session may not persist in load-balanced deployments.
        if (storedJwt !== '') {
            w.__HAS_MEMBER_JWT__ = true;
            return;
        }

        if (!phpLoggedIn) {
            // HttpOnly restore cookie may rehydrate via hydrateMemberJwt onReady.
            if (storedJwt !== '') {
                w.__HAS_MEMBER_JWT__ = true;
            }
            return;
        }

        var jwt = Shared.getMemberJwt();
        if (jwt) {
            w.__HAS_MEMBER_JWT__ = true;
        }
    }

    function handleLogoutQuery() {
        try {
            if (!isLogoutLanding()) {
                return;
            }
            var jwt = Shared.getMemberJwt();
            var base = memberApiBase();
            if (jwt && base) {
                w.fetch(base + '/auth/logout', {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        Authorization: 'Bearer ' + jwt
                    },
                    body: '{}'
                }).catch(function () {});
            }
            Shared.clearMemberJwt();
            w.__USER_LOGGED_IN__ = false;
            w.__HAS_MEMBER_JWT__ = false;
            w.__MEMBER_JWT_BOOTSTRAP__ = '';
            if (w.history && w.history.replaceState) {
                w.history.replaceState(null, '', '/');
            }
        } catch (e) {
            /* ignore */
        }
    }

    w.BetcoAuthShared = Shared;

    if (isLogoutLanding()) {
        handleLogoutQuery();
    } else {
        syncLoginFlagsFromStorage();
    }

    Shared.onReady(function () {
        document.addEventListener('click', function (e) {
            var logoutLink = e.target && e.target.closest ? e.target.closest('a[href="/logout"], .pl-link-logout, .userLogoutBtn') : null;
            if (!logoutLink) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            if (e.stopImmediatePropagation) {
                e.stopImmediatePropagation();
            }
            Shared.logout();
        }, true);

        if (isLogoutLanding()) {
            handleLogoutQuery();
            return;
        }
        if (shouldAttemptSessionHydrate()) {
            Shared.hydrateMemberJwt().then(function (token) {
                if (token !== '') {
                    w.__USER_LOGGED_IN__ = true;
                    w.__HAS_MEMBER_JWT__ = true;
                    if (w.__MEMBER_BOOTSTRAP_STATE__ && typeof w.__MEMBER_BOOTSTRAP_STATE__ === 'object') {
                        w.__MEMBER_BOOTSTRAP_STATE__.logged_in = true;
                        w.__MEMBER_BOOTSTRAP_STATE__.has_session_jwt = true;
                    }
                    try { w.dispatchEvent(new CustomEvent('app:member-jwt-ready', { detail: { token: token } })); } catch (eEv) {}
                    if (w.MetropolMemberConsole && w.MetropolMemberConsole.fetchAll) {
                        w.MetropolMemberConsole.fetchAll();
                    }
                    if (typeof w.__refreshHeaderBalance === 'function') {
                        w.__refreshHeaderBalance();
                    }
                    return;
                }
                if (restoreSessionHintActive() || phpSessionLoggedIn()) {
                    w.__USER_LOGGED_IN__ = false;
                    w.__HAS_MEMBER_JWT__ = false;
                    Shared.clearMemberJwt();
                }
            }).catch(function () {
                if (restoreSessionHintActive() || phpSessionLoggedIn()) {
                    w.__USER_LOGGED_IN__ = false;
                    w.__HAS_MEMBER_JWT__ = false;
                    Shared.clearMemberJwt();
                }
            });
        }
    });

    if (directMemberApiEnabled() && typeof w.fetch === 'function') {
        var nativeFetch = w.fetch.bind(w);
        var MEMBER_FETCH_TIMEOUT_MS = 5000;
        function memberFetch(input, init) {
            init = init || {};
            if (!init.signal && typeof AbortSignal !== 'undefined' && typeof AbortSignal.timeout === 'function') {
                try {
                    init.signal = AbortSignal.timeout(MEMBER_FETCH_TIMEOUT_MS);
                } catch (timeoutErr) {
                    /* ignore */
                }
            }
            return nativeFetch(input, init);
        }
        w.fetch = function (input, init) {
            init = init || {};
            var url = typeof input === 'string' ? input : (input && input.url ? input.url : '');
            var base = memberApiBase();
            if (url.indexOf('/api/v2/') === 0 && forceProxyRoute(url)) {
                url = Shared.proxyApiUrl(url);
                input = url;
                if (!init.credentials) {
                    init.credentials = 'same-origin';
                }
                if (!(init.headers instanceof Headers)) {
                    init.headers = Shared.memberSessionHeaders(init.headers || {});
                }
                return nativeFetch(input, init);
            }
            if (url.indexOf('/api/v2/') === 0 && isBootstrapRoute(url)) {
                url = Shared.proxyApiUrl(url);
                input = url;
                if (!init.credentials) {
                    init.credentials = 'same-origin';
                }
                return nativeFetch(input, init);
            }
            if (base && (url.indexOf(base) === 0 || url.indexOf('/api/v2/') === 0)) {
                if (url.indexOf('/api/v2/') === 0) {
                    url = Shared.memberApiUrl(url);
                    input = url;
                }
                var plainHeaders = init.headers instanceof Headers ? {} : (init.headers || {});
                var resolved = memberRequestInit(
                    typeof url === 'string' ? url : '',
                    plainHeaders
                );
                init.credentials = resolved.credentials;
                if (!(init.headers instanceof Headers)) {
                    init.headers = resolved.headers;
                }
                return memberFetch(input, init).then(function (res) {
                    if (
                        res
                        && res.status === 401
                        && !isLogoutLanding()
                        && (phpSessionLoggedIn() || (Shared.getMemberJwt && Shared.getMemberJwt() !== '') || (typeof w.__MEMBER_JWT_BOOTSTRAP__ === 'string' && w.__MEMBER_JWT_BOOTSTRAP__.trim() !== ''))
                        && typeof url === 'string'
                        && needsMemberAuth(url.indexOf(base) === 0 ? url.slice(base.length) : url)
                    ) {
                        return Shared.handleMemberAuthFailure().then(function (recovered) {
                            var memberPath = url.indexOf(base) === 0
                                ? url.slice(base.length)
                                : url;
                            var proxyUrl = Shared.proxyApiUrl(memberPath);
                            var proxyReq = memberRequestInit(proxyUrl, { Accept: 'application/json' });
                            var proxyInit = Object.assign({}, init, {
                                credentials: proxyReq.credentials,
                                headers: proxyReq.headers
                            });
                            if (recovered || url.indexOf('/api/v2/') !== 0) {
                                return memberFetch(proxyUrl, proxyInit);
                            }
                            return memberFetch(proxyUrl, proxyInit).then(function (proxyRes) {
                                return proxyRes.status === 401 ? res : proxyRes;
                            });
                        });
                    }
                    return res;
                });
            }
            return nativeFetch(input, init);
        };
    }

    /** Ortaklık ref kodunu URL'den yakala; çerez + localStorage ile kayıt anına kadar taşı. */
    (function persistAffiliateRef() {
        var valid = /^[A-Za-z0-9_-]{1,64}$/;
        var key = 'vrs_ref';
        var ttlMs = 30 * 86400000;
        try {
            var fromUrl = new URLSearchParams(w.location.search).get('ref');
            if (fromUrl && valid.test(fromUrl)) {
                w.localStorage.setItem(key, fromUrl);
                w.localStorage.setItem(key + '_ts', String(Date.now()));
                var cookie = key + '=' + encodeURIComponent(fromUrl) + ';path=/;max-age=' + (30 * 86400) + ';SameSite=Lax';
                if (w.location.protocol === 'https:') cookie += ';Secure';
                document.cookie = cookie;
                return;
            }
            var stored = String(w.localStorage.getItem(key) || '').trim();
            var ts = parseInt(String(w.localStorage.getItem(key + '_ts') || '0'), 10) || 0;
            if (stored && valid.test(stored) && ts > 0 && (Date.now() - ts) < ttlMs) {
                if (!document.cookie.match(/(?:^|;\s*)vrs_ref=/)) {
                    var restore = key + '=' + encodeURIComponent(stored) + ';path=/;max-age=' + (30 * 86400) + ';SameSite=Lax';
                    if (w.location.protocol === 'https:') restore += ';Secure';
                    document.cookie = restore;
                }
            }
        } catch (eRef) { /* ignore */ }
    })();

    w.readAffiliateReferralCode = function readAffiliateReferralCode() {
        var valid = /^[A-Za-z0-9_-]{1,64}$/;
        try {
            var fromUrl = new URLSearchParams(w.location.search).get('ref');
            if (fromUrl && valid.test(fromUrl)) return fromUrl;
        } catch (eUrl) { /* ignore */ }
        try {
            var stored = String(w.localStorage.getItem('vrs_ref') || '').trim();
            if (stored && valid.test(stored)) return stored;
        } catch (eLs) { /* ignore */ }
        try {
            var match = document.cookie.match(/(?:^|;\s*)vrs_ref=([^;]+)/);
            if (match && match[1]) {
                var cookieValue = decodeURIComponent(match[1]);
                if (valid.test(cookieValue)) return cookieValue;
            }
        } catch (eCk) { /* ignore */ }
        return '';
    };
})(window);
