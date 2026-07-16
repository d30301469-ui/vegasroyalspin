/**
 * /play â€” POST /api/v2/game-launch, iframe oyun URL.
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

    var balanceSyncTimers = [];
    var lastBalanceSyncAt = 0;
    var BALANCE_SYNC_MIN_GAP_MS = 250;

    function queueBalanceSyncBurst() {
        if (!memberLoggedIn()) {
            return;
        }
        while (balanceSyncTimers.length) {
            window.clearTimeout(balanceSyncTimers.pop());
        }
        [0, 350, 1100, 2200].forEach(function (delay) {
            var timerId = window.setTimeout(function () {
                tickBalance(true);
            }, delay);
            balanceSyncTimers.push(timerId);
        });
    }

    function isBalanceAffectingEvent(eventName, eventData, context) {
        if (!eventName) {
            return false;
        }
        if (/(^|_)(balance|bet|win|wager|round|spin|transaction|rollback|settle|payout|credit|debit|cashout|bonus|freespin)(_|$)/.test(eventName)) {
            return true;
        }
        if (context && typeof context === 'object') {
            if (
                Object.prototype.hasOwnProperty.call(context, 'balance') ||
                Object.prototype.hasOwnProperty.call(context, 'wallet') ||
                Object.prototype.hasOwnProperty.call(context, 'amount')
            ) {
                return true;
            }
        }
        if (eventData && typeof eventData === 'object') {
            if (
                Object.prototype.hasOwnProperty.call(eventData, 'balance') ||
                Object.prototype.hasOwnProperty.call(eventData, 'amount')
            ) {
                return true;
            }
        }
        return false;
    }

    function fetchFreespinCount() {
        if (!memberLoggedIn()) {
            return Promise.resolve(0);
        }

        function fetchTabCount(tab) {
            return fetch(apiUrl('/api/v2/freespins.php?tab=' + encodeURIComponent(tab)), {
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
                .then(function (json) {
                    var items = json && json.success && json.data && Array.isArray(json.data.items) ? json.data.items : [];
                    return items.length;
                })
                .catch(function () {
                    return 0;
                });
        }

        return Promise.all([fetchTabCount('aktif'), fetchTabCount('yeni')]).then(function (counts) {
            var total = Number(counts[0] || 0) + Number(counts[1] || 0);
            return total > 0 ? total : 0;
        });
    }

    function notifyFreespinsOnLaunch() {
        if (window.__PLAY_FREESPIN_NOTICE_SHOWN__) {
            return;
        }
        fetchFreespinCount().then(function (count) {
            if (count <= 0) {
                return;
            }
            window.__PLAY_FREESPIN_NOTICE_SHOWN__ = true;
            showNotice('Hesabinizda ' + count + ' adet freespin bulunuyor.', 'info');
            if (window.MaltabetToast) {
                MaltabetToast.warning('Hesabinizda ' + count + ' adet freespin var.', 'Freespin');
            }
        });
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

    function tickBalance(force) {
        if (!memberLoggedIn()) {
            return;
        }
        var now = Date.now();
        if (!force && now - lastBalanceSyncAt < BALANCE_SYNC_MIN_GAP_MS) {
            return;
        }
        lastBalanceSyncAt = now;
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

    function showNotice(msg, kind) {
        var text = String(msg || '').trim();
        if (!text) {
            return;
        }
        var box = document.getElementById('playRuntimeNotice');
        if (!box) {
            box = document.createElement('div');
            box.id = 'playRuntimeNotice';
            box.setAttribute('role', 'status');
            box.setAttribute('aria-live', 'polite');
            box.style.position = 'fixed';
            box.style.left = '50%';
            box.style.bottom = '18px';
            box.style.transform = 'translateX(-50%)';
            box.style.zIndex = '9999';
            box.style.maxWidth = 'min(92vw, 560px)';
            box.style.padding = '12px 14px';
            box.style.borderRadius = '12px';
            box.style.boxShadow = '0 10px 28px rgba(0,0,0,.35)';
            box.style.fontSize = '14px';
            box.style.lineHeight = '1.45';
            box.style.fontWeight = '600';
            box.style.display = 'none';
            document.body.appendChild(box);
        }

        var isError = kind === 'error';
        box.style.background = isError ? 'rgba(132, 32, 41, .94)' : 'rgba(16, 24, 40, .94)';
        box.style.border = isError ? '1px solid rgba(255, 99, 115, .55)' : '1px solid rgba(252, 172, 0, .35)';
        box.style.color = '#fff';
        box.textContent = text;
        box.style.display = 'block';

        if (box.__hideTimer) {
            window.clearTimeout(box.__hideTimer);
        }
        box.__hideTimer = window.setTimeout(function () {
            box.style.display = 'none';
        }, isError ? 5200 : 3200);
    }

    function frameWindow() {
        var frame = document.getElementById('playFrame');
        return frame ? frame.contentWindow : null;
    }

    function parseProviderEvent(payload) {
        if (!payload) {
            return null;
        }
        var data = payload;
        if (typeof data === 'string') {
            try {
                data = JSON.parse(data);
            } catch (e) {
                return null;
            }
        }
        if (!data || typeof data !== 'object') {
            return null;
        }
        if (typeof data.eventName !== 'string' || !data.eventName.trim()) {
            return null;
        }
        return data;
    }

    function bindProviderEvents() {
        window.addEventListener('message', function (event) {
            if (event.source && frameWindow() && event.source !== frameWindow()) {
                return;
            }

            var providerEvent = parseProviderEvent(event.data);
            if (!providerEvent) {
                return;
            }

            var eventName = String(providerEvent.eventName || '').toLowerCase();
            var eventData = providerEvent.data;
            var context = providerEvent.context && typeof providerEvent.context === 'object' ? providerEvent.context : {};

            if (eventName === 'balance_update' || eventName === 'api_response') {
                queueBalanceSyncBurst();
                return;
            }

            if (eventName === 'go_home') {
                window.location.href = '/slot';
                return;
            }

            if (eventName === 'go_deposit') {
                window.location.href = '/deposit';
                return;
            }

            if (eventName === 'game_error') {
                var messageText = String(context.messageText || eventData || 'Oyun beklenmeyen bir hata bildirdi.').trim();
                showNotice(messageText, 'error');
                return;
            }

            if (eventName === 'button-click' && String(eventData || '').toLowerCase() === 'deposit') {
                window.location.href = '/deposit';
                return;
            }

            if (isBalanceAffectingEvent(eventName, eventData, context)) {
                queueBalanceSyncBurst();
            }
        });
    }

    function isSafeLaunchUrl(url) {
        var text = String(url || '').trim();
        if (!/^https?:\/\//i.test(text)) {
            return false;
        }
        try {
            var parsed = new URL(text);
            var host = parsed.hostname.toLowerCase();
            var path = parsed.pathname.replace(/^\/+|\/+$/g, '').toLowerCase();
            var currentHost = (window.location.hostname || '').toLowerCase();
            // Reject self-referential launch URLs (our own site root / index.php),
            // but allow external provider URLs that carry the game in the query
            // string with an empty or root path (e.g. https://launch.provider/?...).
            if (path === 'index.php') {
                return false;
            }
            if (!path && host === currentHost) {
                return false;
            }
            var query = parsed.search.toLowerCase();
            if (
                path.indexOf('games/game_launch') !== -1
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
                            ? 'Backend sunucuya ulaÅŸÄ±lamadÄ± (502). PHP-FPM ve frontend .env (API_BACKEND_INTERNAL_BASE_URL) ayarlarÄ±nÄ± kontrol edin.'
                            : x.status === 503
                              ? 'Oyun servisi geÃ§ici olarak kullanÄ±lamÄ±yor (503).'
                              : 'Sunucu yanÄ±tÄ± okunamadÄ± (HTTP ' + x.status + ').';
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
                                    'Gecersiz oyun URL dondu. Ayarlarinizi kontrol edin.'
                            );
                            return;
                        }
                        openLaunchUrl(launchTarget.url, launchTarget.openMode);
                        notifyFreespinsOnLaunch();
                        return;
                    }
                }
                var msg =
                    (x.j && x.j.message) ||
                    (x.j && x.j.error) ||
                    'Oyun baÅŸlatÄ±lamadÄ± (' + x.status + ').';
                showFatal(msg);
            })
            .catch(function () {
                if (loader) {
                    loader.hidden = true;
                }
                showFatal('BaÄŸlantÄ± hatasÄ±.');
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
                fsBtn.title = isFs ? 'Tam ekrandan Ã§Ä±k' : 'Tam ekran (oyun alanÄ±)';
                fsBtn.setAttribute('aria-label', isFs ? 'Tam ekrandan Ã§Ä±k' : 'Tam ekran');
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
        bindProviderEvents();
        if (!gateLoginIfNeeded(payload)) {
            return;
        }
        tickBalance();
        window.setInterval(function () {
            if (!document.hidden) {
                tickBalance();
            }
        }, 2000);
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
