/**
 * /play — POST /api/v2/game-launch, iframe oyun URL.
 */
(function () {
    'use strict';

    var Shared = window.BetcoAuthShared || {};
    function apiUrl(path) {
        return Shared.apiUrl ? Shared.apiUrl(path) : path;
    }
    var LAUNCH_URL = apiUrl('/api/v2/game-launch');
    function memberAuthHeaders(extra) {
        return Shared.memberAuthHeaders ? Shared.memberAuthHeaders(extra) : (function () {
            var h = extra || {};
            var csrf = (window.__CSRF_TOKEN__ || '').trim();
            if (csrf) h['X-CSRF-Token'] = csrf;
            return h;
        })();
    }
    function memberCredentials() {
        return Shared.memberCredentials ? Shared.memberCredentials() : 'same-origin';
    }
    function memberLoggedIn() {
        return window.__USER_LOGGED_IN__ || (Shared.getMemberJwt && !!Shared.getMemberJwt());
    }

    function formatMoney(val) {
        var n = parseFloat(val);
        if (isNaN(n)) {
            n = 0;
        }
        return n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function setBalanceTexts(main, bonus) {
        var mainEl = document.getElementById('playBalanceMain');
        var bonusEl = document.getElementById('playBalanceBonus');
        if (mainEl) {
            mainEl.textContent = formatMoney(main);
        }
        if (bonusEl) {
            bonusEl.textContent = formatMoney(bonus);
        }
        document.querySelectorAll('#headerBalanceMain, [data-balance-target="headerBalanceMain"]').forEach(function (el) {
            el.textContent = formatMoney(main);
        });
    }

    function tickBalance() {
        if (!memberLoggedIn()) {
            return;
        }
        if (typeof window.__refreshHeaderBalance === 'function') {
            window.__refreshHeaderBalance();
            return;
        }
        fetch(apiUrl('/api/v2/balance'), {
            method: 'GET',
            credentials: memberCredentials(),
            headers: memberAuthHeaders({ Accept: 'application/json' }),
            cache: 'no-store'
        })
            .then(function (res) {
                return res.json().catch(function () {
                    return null;
                });
            })
            .then(function (j) {
                if (!j || j.success !== true || !j.data || !j.data.balance) {
                    return;
                }
                var b = j.data.balance;
                setBalanceTexts(b.balance, b.bonus_balance);
            })
            .catch(function () {
                /* ignore */
            });
    }

    function gateLoginIfNeeded(payload) {
        var mode = (payload.mode || 'real').toLowerCase();
        var isDemo = payload.demo === true || payload.isDemo === true;
        if (isDemo || mode === 'fun' || mode === 'demo') {
            return true;
        }
        if (!memberLoggedIn()) {
            var here = window.location.pathname + window.location.search;
            window.location.href = apiUrl('/login') + '?next=' + encodeURIComponent(here);
            return false;
        }
        return true;
    }

    function showFatal(msg) {
        var ov = document.getElementById('playErrorOverlay');
        var t = document.getElementById('playErrorText');
        if (t) {
            t.textContent = msg;
        }
        if (ov) {
            ov.hidden = false;
        }
    }

    function isSafeLaunchUrl(url) {
        var text = String(url || '').trim();
        if (!/^https?:\/\//i.test(text)) {
            return false;
        }
        try {
            var parsed = new URL(text);
            var host = parsed.hostname.toLowerCase();
            if (
                host === 'gator.drakon.casino'
                || host === 'drakon.casino'
                || host === 'gator.drakonapi.tech'
                || host === 'drakonapi.tech'
                || host.endsWith('.drakon.casino')
                || host.endsWith('.drakonapi.tech')
            ) {
                return false;
            }
            var path = parsed.pathname.replace(/^\/+|\/+$/g, '').toLowerCase();
            if (!path || path === 'index.php') {
                return false;
            }
            var query = parsed.search.toLowerCase();
            if (
                path.indexOf('games/game_launch') !== -1
                || path.indexOf('drakon_callback') !== -1
                || path.indexOf('drakon_api') !== -1
            ) {
                return false;
            }
            if (/(?:^|&)(?:agent_token|agent_secret|agent_secret_key|agent_code)=/.test(query)) {
                return false;
            }
            return true;
        } catch (e) {
            return false;
        }
    }

    function resolveLaunchTarget(data) {
        if (!data) {
            return null;
        }
        var url = String(data.game_url || data.launch_url || '').trim();
        if (!url) {
            return null;
        }
        return {
            url: url,
            openMode: String(data.open_mode || 'iframe').toLowerCase()
        };
    }

    function openLaunchUrl(url, openMode) {
        var mode = String(openMode || 'iframe').toLowerCase();
        if (mode === 'redirect' || !document.getElementById('playFrame')) {
            window.location.href = url;
            return;
        }
        var frame = document.getElementById('playFrame');
        frame.src = url;
    }

    function launchGame(payload) {
        var loader = document.getElementById('playLoader');
        var frame = document.getElementById('playFrame');
        if (loader) {
            loader.hidden = false;
        }

        var launchPayload = Object.assign({}, payload || {});
        var launchMode = String(launchPayload.mode || 'real').toLowerCase();
        if (launchPayload.demo === true || launchPayload.isDemo === true || launchMode === 'fun' || launchMode === 'demo') {
            launchPayload.mode = 'fun';
            launchPayload.demo = true;
            delete launchPayload.wallet;
        } else {
            launchPayload.mode = 'real';
        }

        fetch(LAUNCH_URL, {
            method: 'POST',
            credentials: (function () {
                if (Shared.memberRequestInit) {
                    return Shared.memberRequestInit(LAUNCH_URL, {
                        Accept: 'application/json',
                        'Content-Type': 'application/json'
                    }).credentials;
                }
                return memberCredentials();
            })(),
            headers: (function () {
                if (Shared.memberRequestInit) {
                    return Shared.memberRequestInit(LAUNCH_URL, {
                        Accept: 'application/json',
                        'Content-Type': 'application/json'
                    }).headers;
                }
                return memberAuthHeaders({
                    Accept: 'application/json',
                    'Content-Type': 'application/json'
                });
            })(),
            body: JSON.stringify(launchPayload)
        })
            .then(function (res) {
                return res.text().then(function (text) {
                    var j = null;
                    try {
                        j = text ? JSON.parse(text) : null;
                    } catch (e) {
                        j = null;
                    }
                    return { ok: res.ok, status: res.status, j: j };
                });
            })
            .then(function (x) {
                if (loader) {
                    loader.hidden = true;
                }
                if (x.status === 401) {
                    var here = window.location.pathname + window.location.search;
                    window.location.href = apiUrl('/login') + '?next=' + encodeURIComponent(here);
                    return;
                }
                if (!x.j) {
                    var infraMsg =
                        x.status === 502
                            ? 'Backend sunucuya ulaşılamadı (502). PHP-FPM ve frontend .env (API_BACKEND_INTERNAL_BASE_URL) ayarlarını kontrol edin.'
                            : x.status === 503
                              ? 'Oyun servisi geçici olarak kullanılamıyor (503).'
                              : 'Sunucu yanıtı okunamadı (HTTP ' + x.status + ').';
                    showFatal(infraMsg);
                    return;
                }
                if (x.status === 422 && x.j) {
                    console.warn('[game-launch] 422', x.j.message || x.j.error || x.j);
                }
                if (x.j.success === true && x.j.data) {
                    var launchTarget = resolveLaunchTarget(x.j.data);
                    if (launchTarget) {
                        if (!isSafeLaunchUrl(launchTarget.url)) {
                            showFatal(
                                (x.j.message && String(x.j.message)) ||
                                    'Drakon geçersiz oyun URL döndü. Agent ayarlarını ve webhook URL\'ini kontrol edin.'
                            );
                            return;
                        }
                        openLaunchUrl(launchTarget.url, launchTarget.openMode);
                        return;
                    }
                }
                var msg =
                    (x.j && x.j.message) ||
                    (x.j && x.j.error) ||
                    'Oyun başlatılamadı (' + x.status + ').';
                showFatal(msg);
            })
            .catch(function () {
                if (loader) {
                    loader.hidden = true;
                }
                showFatal('Bağlantı hatası.');
            });
    }

    function bindChrome() {
        var closeBtn = document.getElementById('playCloseBtn');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                if (window.history.length > 1) {
                    window.history.back();
                } else {
                    window.location.href = '/slot';
                }
            });
        }

        var fsBtn = document.getElementById('playFullscreenBtn');
        var fsIcon = document.getElementById('playFullscreenIcon');
        var playStage = document.querySelector('.play-stage');

        function getFullscreenElement() {
            return (
                document.fullscreenElement ||
                document.webkitFullscreenElement ||
                document.msFullscreenElement ||
                null
            );
        }

        function syncFullscreenUi() {
            var isFs = !!getFullscreenElement();
            if (fsIcon) {
                fsIcon.className =
                    'fa-solid ' + (isFs ? 'fa-compress' : 'fa-expand') + ' fa-fw';
            }
            if (fsBtn) {
                fsBtn.title = isFs ? 'Tam ekrandan çık' : 'Tam ekran (oyun alanı)';
                fsBtn.setAttribute('aria-label', isFs ? 'Tam ekrandan çık' : 'Tam ekran');
            }
        }

        if (fsBtn) {
            fsBtn.addEventListener('click', function () {
                if (!getFullscreenElement()) {
                    var target = playStage || document.documentElement;
                    var req =
                        target.requestFullscreen ||
                        target.webkitRequestFullscreen ||
                        target.msRequestFullscreen;
                    if (req) {
                        var p = req.call(target);
                        if (p && typeof p.then === 'function') {
                            p.catch(function () {
                                if (target !== document.documentElement) {
                                    var docEl = document.documentElement;
                                    var r2 =
                                        docEl.requestFullscreen ||
                                        docEl.webkitRequestFullscreen ||
                                        docEl.msRequestFullscreen;
                                    if (r2) {
                                        var p2 = r2.call(docEl);
                                        if (p2 && typeof p2.catch === 'function') {
                                            p2.catch(function () {
                                                /* izin yok */
                                            });
                                        }
                                    }
                                }
                            });
                        }
                    }
                } else if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
            });
        }

        document.addEventListener('fullscreenchange', syncFullscreenUi);
        document.addEventListener('webkitfullscreenchange', syncFullscreenUi);
        syncFullscreenUi();
    }

    function run() {
        var payload = window.__PLAY_LAUNCH_PAYLOAD__;
        if (!payload || !payload.game_id) {
            showFatal('Oyun bilgisi eksik.');
            return;
        }
        bindChrome();
        if (!gateLoginIfNeeded(payload)) {
            return;
        }
        tickBalance();
        window.addEventListener('focus', tickBalance);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                tickBalance();
            }
        });
        launchGame(payload);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
