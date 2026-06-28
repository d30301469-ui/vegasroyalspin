/**
 * Üye API tanı konsolu — tüm /api/v2 yanıtları ve bootstrap state console'da.
 * Kapatmak: localStorage.removeItem('metropol_member_debug')
 */
(function (w) {
    'use strict';

    function consoleEnabled() {
        if (w.__MEMBER_API_CONSOLE__ === false) {
            return false;
        }
        if (w.__MEMBER_API_CONSOLE__ === true) {
            return true;
        }
        try {
            if (w.localStorage.getItem('metropol_member_debug') === '1') {
                return true;
            }
            return /(?:\?|&)debug=1(?:&|$)/.test(w.location.search || '');
        } catch (e) {
            return false;
        }
    }

    function log() {
        if (!consoleEnabled()) {
            return;
        }
        try {
            w.console.info.apply(w.console, arguments);
        } catch (eLog) {
            /* ignore */
        }
    }

    function warn() {
        if (!consoleEnabled()) {
            return;
        }
        try {
            w.console.warn.apply(w.console, arguments);
        } catch (eWarn) {
            /* ignore */
        }
    }

    function snapshot() {
        var Shared = w.BetcoAuthShared || {};
        var jwt = Shared.getMemberJwt ? Shared.getMemberJwt() : '';
        return {
            url: w.location.href,
            logged_in: w.__USER_LOGGED_IN__ === true,
            has_member_jwt_flag: w.__HAS_MEMBER_JWT__ === true,
            bootstrap_jwt_len: String(w.__MEMBER_JWT_BOOTSTRAP__ || '').length,
            local_jwt_len: jwt.length,
            direct_member_api: w.__FRONTEND_DIRECT_MEMBER_API__ === true,
            member_api_base: String(w.__MEMBER_API_BASE__ || ''),
            csrf_len: String(w.__CSRF_TOKEN__ || '').length,
            site_settings_api: String(w.__SITE_SETTINGS_API__ || ''),
            bootstrap: w.__MEMBER_BOOTSTRAP_STATE__ || null
        };
    }

    function parseJsonSafe(text) {
        if (!text) {
            return null;
        }
        try {
            return JSON.parse(String(text).replace(/^\uFEFF/, '').trim());
        } catch (e) {
            return null;
        }
    }

    function memberPath(url) {
        var s = String(url || '');
        var idx = s.indexOf('/api/v2/');
        return idx >= 0 ? s.slice(idx) : s;
    }

    function fetchMember(path) {
        var Shared = w.BetcoAuthShared || {};
        var full = Shared.apiUrl ? Shared.apiUrl(path) : path;
        var req = Shared.memberRequestInit
            ? Shared.memberRequestInit(full, { Accept: 'application/json' })
            : { credentials: 'same-origin', headers: { Accept: 'application/json' } };
        return w.fetch(full, {
            method: 'GET',
            credentials: req.credentials,
            headers: req.headers,
            cache: 'no-store'
        }).then(function (res) {
            return res.text().then(function (text) {
                var body = parseJsonSafe(text);
                var row = {
                    path: memberPath(full),
                    status: res.status,
                    ok: res.ok,
                    body: body !== null ? body : text.slice(0, 500)
                };
                log('[Metropol API]', row.path, row.status, row.body);
                return row;
            });
        }).catch(function (err) {
            warn('[Metropol API]', path, err);
            return { path: path, error: String(err) };
        });
    }

    w.MetropolMemberConsole = {
        enabled: consoleEnabled,
        snapshot: snapshot,
        dump: function () {
            log('[Metropol] state', snapshot());
            if (w.__SITE_SETTINGS__) {
                log('[Metropol] site_settings', w.__SITE_SETTINGS__);
            }
            if (w.__FRONTEND_CONNECTIONS__) {
                log('[Metropol] connections', w.__FRONTEND_CONNECTIONS__);
            }
        },
        fetchAll: function () {
            var paths = [
                '/api/v2/site-settings',
                '/api/v2/auth/session',
                '/api/v2/balance',
                '/api/v2/loyalty'
            ];
            if (w.__USER_LOGGED_IN__ !== true && !(w.__MEMBER_BOOTSTRAP_STATE__ && w.__MEMBER_BOOTSTRAP_STATE__.logged_in === true)) {
                paths = ['/api/v2/site-settings'];
            }
            return Promise.all(paths.map(fetchMember)).then(function (rows) {
                log('[Metropol] fetchAll complete', rows);
                return rows;
            });
        },
        fetchBalance: function () {
            return fetchMember('/api/v2/balance');
        }
    };

    if (!consoleEnabled()) {
        return;
    }

    log('[Metropol] member-api-console active');
    log('[Metropol] bootstrap', w.__MEMBER_BOOTSTRAP_STATE__ || snapshot());

    if (w.__SITE_SETTINGS__ && typeof w.__SITE_SETTINGS__ === 'object') {
        log('[Metropol] site_settings', w.__SITE_SETTINGS__);
    }

    function wrapFetch() {
        if (typeof w.fetch !== 'function' || w.__METROPOL_FETCH_LOGGED__) {
            return;
        }
        w.__METROPOL_FETCH_LOGGED__ = true;
        var native = w.fetch.bind(w);
        w.fetch = function (input, init) {
            var url = typeof input === 'string' ? input : (input && input.url ? input.url : '');
            return native(input, init).then(function (res) {
                if (memberPath(url).indexOf('/api/v2/') === 0) {
                    try {
                        var jwtSync = res.headers.get('X-Metropol-Jwt-Sync');
                        var proxyBackend = res.headers.get('X-Metropol-Proxy-Backend');
                        if (jwtSync) {
                            log('[Metropol JWT]', memberPath(url), 'sync=' + jwtSync);
                        }
                        if (proxyBackend) {
                            log('[Metropol Proxy]', memberPath(url), 'backend=' + proxyBackend);
                        }
                        res.clone().text().then(function (text) {
                            log('[Metropol API]', memberPath(url), res.status, parseJsonSafe(text) || text.slice(0, 300));
                        });
                    } catch (eClone) {
                        /* ignore */
                    }
                }
                return res;
            });
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            wrapFetch();
            w.MetropolMemberConsole.dump();
            if (w.__USER_LOGGED_IN__ === true) {
                w.MetropolMemberConsole.fetchAll();
            }
        });
    } else {
        wrapFetch();
        w.MetropolMemberConsole.dump();
        if (w.__USER_LOGGED_IN__ === true) {
            w.MetropolMemberConsole.fetchAll();
        }
    }

    w.addEventListener('metropol:member-jwt-ready', function () {
        log('[Metropol] jwt-ready', snapshot());
        if (w.__USER_LOGGED_IN__ === true && w.MetropolMemberConsole) {
            w.MetropolMemberConsole.fetchAll();
        }
    });
})(window);
