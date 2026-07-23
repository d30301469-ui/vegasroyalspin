/**
 * Login / register + member API client (direct api.bo-nexthub.site + same-origin proxy for session).
 */
(function (w) {
    var JWT_KEY = 'metropol_member_jwt';

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
        '/game_launch.php': true
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

    function sessionHintActive() {
        if (isLogoutLanding()) {
            return false;
        }
        if (!phpSessionLoggedIn()) {
            return false;
        }
        if (Shared.getMemberJwt && Shared.getMemberJwt() !== '') {
            return true;
        }
        return typeof w.__MEMBER_JWT_BOOTSTRAP__ === 'string' && w.__MEMBER_JWT_BOOTSTRAP__.trim() !== '';
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
            h['X-Metropol-Member-Jwt'] = jwt;
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
        document.cookie = 'metropol_member_jwt=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax';
    }

    function emitJwtReady() {
        try {
            w.dispatchEvent(new CustomEvent('metropol:member-jwt-ready', {
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
            try {
                return String(w.localStorage.getItem(JWT_KEY) || '').trim();
            } catch (e) {
                return '';
            }
        },
        setMemberJwt: function (token) {
            var t = String(token || '').trim();
            try {
                if (t === '') {
                    w.localStorage.removeItem(JWT_KEY);
                    w.__HAS_MEMBER_JWT__ = false;
                    persistMemberJwtCookie('');
                } else {
                    w.localStorage.setItem(JWT_KEY, t);
                    w.__MEMBER_LOGIN_AT__ = Date.now();
                    w.__HAS_MEMBER_JWT__ = true;
                    persistMemberJwtCookie(t);
                    if (phpSessionLoggedIn()) {
                        w.__USER_LOGGED_IN__ = true;
                    }
                    emitJwtReady();
                }
            } catch (e2) {
                /* ignore */
            }
        },
        clearMemberJwt: function () {
            this.setMemberJwt('');
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
        isLogoutLanding: isLogoutLanding,
        phpSessionLoggedIn: phpSessionLoggedIn,
        handleMemberAuthFailure: function () {
            var self = this;
            if (isLogoutLanding() || !phpSessionLoggedIn()) {
                self.clearMemberJwt();
                w.__USER_LOGGED_IN__ = false;
                w.__HAS_MEMBER_JWT__ = false;
                return Promise.resolve(false);
            }
            if (memberAuthFailureInFlight) {
                return Promise.resolve(false);
            }
            memberAuthFailureInFlight = true;
            self.clearMemberJwt();

            var sessionUrl = self.proxyApiUrl('/auth/session');
            return w.fetch(sessionUrl, {
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
                    w.__USER_LOGGED_IN__ = false;
                    w.__HAS_MEMBER_JWT__ = false;
                    try {
                        w.dispatchEvent(new CustomEvent('metropol:member-auth-lost'));
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

            if (!phpLoggedIn && !sessionHintActive()) {
                var recentLogin = typeof w.__MEMBER_LOGIN_AT__ === 'number'
                    && (Date.now() - w.__MEMBER_LOGIN_AT__) < 30000;
                if (!recentLogin && self.getMemberJwt() === '') {
                    self.clearMemberJwt();
                }
                // Only bail out if we have no JWT at all; if we have one
                // (e.g. just stored by login before reload), try /auth/session.
                if (!recentLogin && self.getMemberJwt() === '') {
                    return Promise.resolve('');
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
                        self.clearMemberJwt();
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
            return w.__TURNSTILE_ENABLED__ === true || w.__TURNSTILE_ENABLED__ === 1 || w.__TURNSTILE_ENABLED__ === '1';
        },
        turnstileSiteKey: function () {
            return typeof w.__TURNSTILE_SITE_KEY__ === 'string' ? w.__TURNSTILE_SITE_KEY__.trim() : '';
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
            Shared.clearMemberJwt();
            w.__USER_LOGGED_IN__ = false;
            w.__HAS_MEMBER_JWT__ = false;
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
        if (isLogoutLanding()) {
            handleLogoutQuery();
            return;
        }
        if (!phpSessionLoggedIn()) {
            // If we have a stored JWT (e.g. just logged in before reload),
            // try to validate it via /auth/session instead of giving up.
            if (Shared.getMemberJwt() !== '') {
                Shared.hydrateMemberJwt().then(function (token) {
                    if (token !== '') {
                        // JWT tek başına oturum anlamına gelmez; PHP session doğrulanana
                        // kadar UI'yi "logged in" moduna zorlamayız.
                        w.__USER_LOGGED_IN__ = false;
                        w.__HAS_MEMBER_JWT__ = true;
                        if (w.__MEMBER_BOOTSTRAP_STATE__ && typeof w.__MEMBER_BOOTSTRAP_STATE__ === 'object') {
                            w.__MEMBER_BOOTSTRAP_STATE__.logged_in = false;
                            w.__MEMBER_BOOTSTRAP_STATE__.has_session_jwt = true;
                        }
                        if (w.MetropolMemberConsole && w.MetropolMemberConsole.fetchAll) {
                            w.MetropolMemberConsole.fetchAll();
                        }
                        return;
                    }
                    w.__USER_LOGGED_IN__ = false;
                    w.__HAS_MEMBER_JWT__ = false;
                    Shared.clearMemberJwt();
                }).catch(function () {
                    w.__USER_LOGGED_IN__ = false;
                    w.__HAS_MEMBER_JWT__ = false;
                    Shared.clearMemberJwt();
                });
                return;
            }
            w.__USER_LOGGED_IN__ = false;
            w.__HAS_MEMBER_JWT__ = false;
            Shared.clearMemberJwt();
            if (w.MetropolMemberConsole && w.MetropolMemberConsole.dump) {
                w.MetropolMemberConsole.dump();
            }
            return;
        }
        Shared.hydrateMemberJwt().then(function (token) {
            if (token !== '' && phpSessionLoggedIn()) {
                w.__HAS_MEMBER_JWT__ = true;
                if (w.MetropolMemberConsole && w.MetropolMemberConsole.fetchAll) {
                    w.MetropolMemberConsole.fetchAll();
                }
                return;
            }
            if (!phpSessionLoggedIn()) {
                w.__USER_LOGGED_IN__ = false;
                w.__HAS_MEMBER_JWT__ = false;
                Shared.clearMemberJwt();
            }
        });
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
                        && phpSessionLoggedIn()
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
})(window);
