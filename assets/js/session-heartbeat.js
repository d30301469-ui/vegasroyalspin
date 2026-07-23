/**
 * Üye oturumu: proxy üzerinden GET /api/v2/auth/session (PHP session cookie).
 * 401 → sessiz yenileme; yalnızca gerçekten kurtarılamazsa çıkış.
 */
(function () {
    'use strict';

    var Shared = window.BetcoAuthShared || {};
    var SESSION_INTERVAL_MS = 180000;
    var LOGIN_GRACE_MS = 90000;
    var ended = false;
    var sessionInFlight = false;
    var recoveryAttempts = 0;
    var MAX_RECOVERY_ATTEMPTS = 3;

    function sessionUrl() {
        return Shared.proxyApiUrl
            ? Shared.proxyApiUrl('/auth/session')
            : '/api/v2/auth/session';
    }

    function sessionHeaders() {
        return Shared.memberSessionHeaders
            ? Shared.memberSessionHeaders({ Accept: 'application/json' })
            : { Accept: 'application/json' };
    }

    function phpSessionActive() {
        if (Shared.isLogoutLanding && Shared.isLogoutLanding()) {
            return false;
        }
        return Shared.phpSessionLoggedIn
            ? Shared.phpSessionLoggedIn()
            : window.__USER_LOGGED_IN__ === true;
    }

    function shouldRunHeartbeat() {
        return phpSessionActive();
    }

    function withinLoginGrace() {
        var at = window.__MEMBER_LOGIN_AT__;
        return typeof at === 'number' && at > 0 && (Date.now() - at) < LOGIN_GRACE_MS;
    }

    function goLogout() {
        if (Shared.clearMemberJwt) {
            Shared.clearMemberJwt();
        }
        window.location.href = '/?logout=1';
    }

    function notifyAndLogout(message) {
        if (ended) {
            return;
        }
        ended = true;
        var msg = message || 'Oturumunuz sonlandırıldı. Lütfen tekrar giriş yapın.';
        var title = 'Oturum kapatılıyor';

        if (window.MaltabetToast) {
            MaltabetToast.warning(msg, title);
            window.setTimeout(goLogout, 2800);
            return;
        }
        window.alert(title + '\n\n' + msg);
        goLogout();
    }

    function tryRecoverSession() {
        if (recoveryAttempts >= MAX_RECOVERY_ATTEMPTS) {
            return Promise.resolve(false);
        }
        recoveryAttempts += 1;
        if (Shared.handleMemberAuthFailure) {
            return Shared.handleMemberAuthFailure();
        }
        return Promise.resolve(false);
    }

    function handlePayload(status, payload) {
        if (ended) {
            return;
        }
        if (status === 503) {
            return;
        }
        if (payload && payload.success === true) {
            recoveryAttempts = 0;
            if (Shared.applyLoginEnvelope) {
                Shared.applyLoginEnvelope(payload);
            }
            return;
        }
        var code = payload && typeof payload.code === 'number' ? payload.code : status;
        var unauthorized =
            status === 401 ||
            code === 401 ||
            (payload && payload.error === 'UNAUTHORIZED');
        if (!unauthorized) {
            return;
        }
        if (withinLoginGrace()) {
            return;
        }
        tryRecoverSession().then(function (ok) {
            if (!ok && phpSessionActive()) {
                notifyAndLogout(
                    'Oturum doğrulanamadı. Lütfen tekrar giriş yapın.'
                );
            }
        });
    }

    function tick() {
        if (ended || sessionInFlight) {
            return;
        }
        if (!shouldRunHeartbeat()) {
            return;
        }
        sessionInFlight = true;
        fetch(sessionUrl(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: sessionHeaders(),
            cache: 'no-store'
        })
            .then(function (res) {
                return res.text().then(function (text) {
                    var j = null;
                    try {
                        j = text ? JSON.parse(text.replace(/^\uFEFF/, '').trim()) : null;
                    } catch (e) {
                        j = null;
                    }
                    return { status: res.status, j: j };
                });
            })
            .then(function (x) {
                handlePayload(x.status, x.j);
            })
            .catch(function () {
                /* Ağ kesintisi */
            })
            .then(function () {
                sessionInFlight = false;
            });
    }

    function start() {
        if (window.__SESSION_HEARTBEAT_STARTED__) {
            return;
        }
        window.__SESSION_HEARTBEAT_STARTED__ = true;
        window.setTimeout(tick, withinLoginGrace() ? 15000 : 8000);
        setInterval(tick, SESSION_INTERVAL_MS);
        window.addEventListener('metropol:member-jwt-ready', function () {
            recoveryAttempts = 0;
            window.setTimeout(tick, 5000);
        });
    }

    function boot() {
        if (Shared.isLogoutLanding && Shared.isLogoutLanding()) {
            return;
        }
        if (!phpSessionActive()) {
            // PHP session may not persist in load-balanced setups.
            // If we have a JWT in localStorage, try hydration first.
            var storedJwt = Shared.getMemberJwt ? Shared.getMemberJwt() : '';
            if (storedJwt !== '' && Shared.hydrateMemberJwt) {
                Shared.hydrateMemberJwt().then(function (token) {
                    if (token !== '' && phpSessionActive()) {
                        window.__HAS_MEMBER_JWT__ = true;
                    }
                }).finally(start);
                return;
            }
            return;
        }
        if (Shared.hydrateMemberJwt) {
            Shared.hydrateMemberJwt().finally(start);
            return;
        }
        start();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
