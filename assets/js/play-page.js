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
        if (Shared.runtimeSessionLoggedIn) {
            return Shared.runtimeSessionLoggedIn();
        }
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
            return Promise.resolve(true);
        }
        if (memberLoggedIn()) {
            return Promise.resolve(true);
        }

        if (Shared && typeof Shared.hydrateMemberJwt === 'function') {
            return Shared.hydrateMemberJwt().then(function () {
                if (memberLoggedIn()) {
                    return true;
                }
                var here = window.location.pathname + window.location.search;
                window.location.href = apiUrl('/login') + '?next=' + encodeURIComponent(here);
                return false;
            }).catch(function () {
                var here = window.location.pathname + window.location.search;
                window.location.href = apiUrl('/login') + '?next=' + encodeURIComponent(here);
                return false;
            });
        }

        if (!memberLoggedIn()) {
            var here = window.location.pathname + window.location.search;
            window.location.href = apiUrl('/login') + '?next=' + encodeURIComponent(here);
            return Promise.resolve(false);
        }
        return Promise.resolve(true);
    }

    function showFatal(msg) {
        var message = String(msg || 'Oyun baslatilamadi.').trim();
        var ov = document.getElementById('playErrorOverlay');
        var t = document.getElementById('playErrorText');
        if (t) {
            t.textContent = message;
        }
        if (ov) {
            ov.hidden = false;
            return;
        }

        var fallback = document.getElementById('playFatalFallback');
        if (!fallback) {
            fallback = document.createElement('div');
            fallback.id = 'playFatalFallback';
            fallback.setAttribute('role', 'alert');
            fallback.style.cssText = 'position:fixed;inset:0;z-index:9999;display:grid;place-items:center;padding:20px;background:#0f0522;color:#fff;font-family:Segoe UI,system-ui,-apple-system,sans-serif;text-align:center;';
            fallback.innerHTML = '<div style="max-width:420px"><div id="playFatalFallbackText" style="font-size:15px;font-weight:700;line-height:1.45;margin-bottom:14px"></div><button type="button" id="playFatalFallbackBack" style="border:0;border-radius:10px;background:#FCAC00;color:#16061f;font-weight:800;padding:10px 16px;cursor:pointer">Oyunlara Don</button></div>';
            document.body.appendChild(fallback);
            var back = document.getElementById('playFatalFallbackBack');
            if (back) {
                back.onclick = function () {
                    window.location.href = playHomeUrl();
                };
            }
        }
        var fallbackText = document.getElementById('playFatalFallbackText');
        if (fallbackText) {
            fallbackText.textContent = message;
        }
    }

    function playHomeUrl() {
        var configured = window.__PLAY_HOME_URL__ ? String(window.__PLAY_HOME_URL__).trim() : '';
        if (configured !== '') {
            return configured;
        }
        try {
            var ref = document.referrer ? String(document.referrer).trim() : '';
            if (ref !== '') {
                var refUrl = new URL(ref, window.location.origin);
                if (refUrl.origin === window.location.origin) {
                    var path = refUrl.pathname || '/';
                    if (path.indexOf('/play') !== 0) {
                        return refUrl.pathname + refUrl.search + refUrl.hash;
                    }
                }
            }
        } catch (e) {}
        return '/';
    }

    function goPlayHome() {
        window.location.href = playHomeUrl();
    }

    function isHomeExitEvent(eventName, eventData) {
        var name = String(eventName || '').toLowerCase();
        if (
            name === 'go_home' ||
            name === 'gohome' ||
            name === 'home' ||
            name === 'exit' ||
            name === 'close' ||
            name === 'close_game' ||
            name === 'closegame' ||
            name === 'lobby' ||
            name === 'back_to_lobby' ||
            name === 'quit'
        ) {
            return true;
        }
        if (name === 'button-click' || name === 'button_click') {
            var data = String(eventData || '').toLowerCase();
            return data === 'home' || data === 'lobby' || data === 'exit' || data === 'close';
        }
        return false;
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

            if (isHomeExitEvent(eventName, eventData)) {
                goPlayHome();
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
        if (text.indexOf('//') === 0) {
            text = window.location.protocol + text;
        }
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
            return true;
        } catch (e) {
            return false;
        }
    }

    function normalizeOpenMode(value, fallbackMode) {
        var mode = String(value || '').toLowerCase();
        if (mode !== 'iframe' && mode !== 'redirect') {
            mode = String(fallbackMode || '').toLowerCase();
        }
        if (mode !== 'iframe' && mode !== 'redirect') {
            mode = 'iframe';
        }
        return mode;
    }

    function resolveLaunchTarget(data, fallbackOpenMode) {
        if (!data) {
            return null;
        }
        // Prefer provider URL over HTML content — some providers return both and
        // content-first left players stuck in srcdoc/document.write instead of
        // top-level navigation.
        var url = String(data.game_url || data.launch_url || data.url || '').trim();
        if (url) {
            return {
                url: url,
                openMode: normalizeOpenMode(data.open_mode, fallbackOpenMode)
            };
        }
        var content = String(data.content || '').trim();
        if (content !== '') {
            return {
                content: content,
                openMode: normalizeOpenMode(data.open_mode, 'html')
            };
        }
        return null;
    }

    function openLaunchContent(html, openMode) {
        var mode = String(openMode || 'html').toLowerCase();
        if (mode === 'redirect') {
            document.open();
            document.write(html);
            document.close();
            return;
        }
        var frame = document.getElementById('playFrame');
        if (!frame) {
            document.open();
            document.write(html);
            document.close();
            return;
        }
        frame.removeAttribute('src');
        frame.srcdoc = html;
    }

    // An http:// URL inside our https:// page is mixed content, which browsers
    // block silently and leaves the player staring at an empty frame — the same
    // URL still opens fine in its own tab. Upgrade the scheme for the frame only.
    function frameSafeUrl(url) {
        var text = String(url || '').trim();
        if (text.indexOf('//') === 0) {
            return window.location.protocol + text;
        }
        if (window.location.protocol === 'https:' && /^http:\/\//i.test(text)) {
            return text.replace(/^http:\/\//i, 'https://');
        }
        return text;
    }

    function openLaunchUrl(url, openMode) {
        var mode = String(openMode || 'iframe').toLowerCase();
        var urlLower = String(url || '').toLowerCase();
        // GSC+/Pragmatic efinity shells are IP/cookie bound and break in nested
        // iframes — only those hosts force top-level (never for aggregator iframe mode).
        var gscEfinityHost = urlLower.indexOf('prerelease-env.biz') !== -1
            || urlLower.indexOf('efinity') !== -1;
        var forceRedirect = mode === 'redirect'
            || (gscEfinityHost && mode !== 'iframe')
            || !document.getElementById('playFrame');
        // Desktop aggregator: keep iframe. Mobile sends open_mode=redirect above.
        if (forceRedirect) {
            // Anchor the current history entry on the same-origin home URL so
            // browser Back from the provider lands on the main site (not /play
            // or a one-shot launch hop). Then assign into the game once.
            var home = playHomeUrl();
            try {
                if (home && home !== window.location.href) {
                    history.replaceState(null, '', home);
                }
            } catch (e) {}
            window.location.assign(url);
            return;
        }
        var frame = document.getElementById('playFrame');
        var loader = document.getElementById('playLoader');
        var safeUrl = frameSafeUrl(url);

        // Aggregator iframe is unrestricted (see play.php) so bet/wallet
        // callbacks inside the game client can complete; other providers may
        // still use a permissive sandbox on the element.
        function onFrameLoaded() {
            if (loader) {
                loader.hidden = true;
            }
            launchInFlight = false;
        }
        function onFrameError() {
            if (loader) {
                loader.hidden = true;
            }
            launchInFlight = false;
            showFatal('Oyun play içinde açılamadı. Lütfen sayfayı yenileyip tekrar deneyin.');
        }

        frame.removeEventListener('load', onFrameLoaded);
        frame.removeEventListener('error', onFrameError);
        frame.addEventListener('load', onFrameLoaded, { once: true });
        frame.addEventListener('error', onFrameError, { once: true });

        // Safety: if load never fires (opaque cross-origin), hide loader anyway.
        window.setTimeout(function () {
            if (loader && !loader.hidden) {
                loader.hidden = true;
            }
            launchInFlight = false;
        }, 12000);

        try {
            frame.src = 'about:blank';
        } catch (e) {}
        // Assign after a tick so the new load event is attached cleanly.
        window.setTimeout(function () {
            frame.src = safeUrl;
        }, 0);
    }

    var launchInFlight = false;

    function launchGame(payload, attempt) {
        var launchAttempt = Number(attempt || 0);
        if (launchInFlight && launchAttempt === 0) {
            return;
        }
        launchInFlight = true;
        var loader = document.getElementById('playLoader');
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
        // Keep visitor IP from play.php SSR — some providers bind sessions to it.
        if (!launchPayload.ip && window.__PLAY_CLIENT_IP__) {
            launchPayload.ip = String(window.__PLAY_CLIENT_IP__);
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
                    launchInFlight = false;
                    if (launchAttempt < 1 && Shared && typeof Shared.handleMemberAuthFailure === 'function') {
                        Shared.handleMemberAuthFailure().then(function (recovered) {
                            if (recovered) {
                                launchGame(payload, launchAttempt + 1);
                                return;
                            }
                            var here = window.location.pathname + window.location.search;
                            window.location.href = apiUrl('/login') + '?next=' + encodeURIComponent(here);
                        }).catch(function () {
                            var here = window.location.pathname + window.location.search;
                            window.location.href = apiUrl('/login') + '?next=' + encodeURIComponent(here);
                        });
                        return;
                    }
                    var here = window.location.pathname + window.location.search;
                    window.location.href = apiUrl('/login') + '?next=' + encodeURIComponent(here);
                    return;
                }
                if (!x.j) {
                    launchInFlight = false;
                    var infraMsg =
                        x.status === 502
                            ? 'Backend sunucuya ulaÅŸÄ±lamadÄ± (502). PHP-FPM ve frontend .env (API_BACKEND_INTERNAL_BASE_URL) ayarlarÄ±nÄ± kontrol edin.'
                            : x.status === 503
                              ? 'Oyun servisi geÃ§ici olarak kullanÄ±lamÄ±yor (503).'
                              : 'Sunucu yanÄ±tÄ± okunamadÄ± (HTTP ' + x.status + ').';
                    showFatal(infraMsg);
                    return;
                }
                if (x.status === 422 && x.j && window.__MEMBER_API_CONSOLE__ === true) {
                    console.warn('[game-launch] 422', x.j.message || x.j.error || x.j);
                }
                if (x.j.success === true && x.j.data) {
                    var launchTarget = resolveLaunchTarget(x.j.data, launchPayload.open_mode);
                    if (launchTarget) {
                        if (launchTarget.content) {
                            openLaunchContent(launchTarget.content, launchTarget.openMode);
                            notifyFreespinsOnLaunch();
                            return;
                        }
                        if (!isSafeLaunchUrl(launchTarget.url)) {
                            launchInFlight = false;
                            showFatal(
                                (x.j.message && String(x.j.message)) ||
                                    'Gecersiz oyun URL dondu. Ayarlarinizi kontrol edin.'
                            );
                            return;
                        }
                        if (x.j.data.home_url) {
                            window.__PLAY_HOME_URL__ = String(x.j.data.home_url);
                        }
                        openLaunchUrl(launchTarget.url, launchTarget.openMode);
                        notifyFreespinsOnLaunch();
                        return;
                    }
                }
                launchInFlight = false;
                var msg =
                    (x.j && x.j.message) ||
                    (x.j && x.j.error) ||
                    'Oyun baÅŸlatÄ±lamadÄ± (' + x.status + ').';
                showFatal(msg);
            })
            .catch(function () {
                launchInFlight = false;
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
                goPlayHome();
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
        gateLoginIfNeeded(payload).then(function (allowed) {
            if (!allowed) {
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
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
