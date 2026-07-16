/**
 * Giriş + üye JWT varken GET /api/v2/balance — header: yalnızca ana bakiye; profil: ana + bonus.
 */
(function () {
    'use strict';

    var Shared = window.BetcoAuthShared || {};
    function apiUrl(path) {
        return Shared.apiUrl ? Shared.apiUrl(path) : path;
    }
    function memberAuthHeaders(extra) {
        return Shared.memberAuthHeaders ? Shared.memberAuthHeaders(extra) : (function () {
            var h = extra || {};
            var csrf = (window.__CSRF_TOKEN__ || '').trim();
            if (csrf) h['X-CSRF-Token'] = csrf;
            return h;
        })();
    }
    function fetchCredentials(url) {
        if (typeof url === 'string' && url.indexOf('/api/v2/') === 0) {
            return 'same-origin';
        }
        return Shared.memberCredentials ? Shared.memberCredentials() : 'same-origin';
    }
    function memberLoggedIn() {
        if (Shared.isLogoutLanding && Shared.isLogoutLanding()) {
            return false;
        }
        return Shared.phpSessionLoggedIn
            ? Shared.phpSessionLoggedIn()
            : window.__USER_LOGGED_IN__ === true;
    }

    function balanceFetchInit() {
        var balanceUrl = apiUrl('/api/v2/balance');
        if (Shared.memberRequestInit) {
            var req = Shared.memberRequestInit(balanceUrl, { Accept: 'application/json' });
            return { url: balanceUrl, credentials: req.credentials, headers: req.headers };
        }
        return {
            url: balanceUrl,
            credentials: fetchCredentials(balanceUrl),
            headers: memberAuthHeaders({ Accept: 'application/json' })
        };
    }

    function fetchBalanceRow() {
        var req = balanceFetchInit();
        return fetch(req.url, {
            method: 'GET',
            credentials: req.credentials,
            headers: req.headers,
            cache: 'no-store'
        }).then(function (res) {
            return res.text().then(function (text) {
                var j = null;
                try {
                    j = text ? JSON.parse(text) : null;
                } catch (e) {
                    j = null;
                }
                return { status: res.status, j: j, proxied: req.url.indexOf('/api/v2/') === 0 };
            });
        }).then(function (x) {
            if (x.status !== 401 || !Shared.proxyApiUrl) {
                return x;
            }
            var proxyUrl = Shared.proxyApiUrl('/api/v2/balance');
            if (proxyUrl === req.url) {
                return x;
            }
            return fetch(proxyUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: Shared.memberSessionHeaders
                    ? Shared.memberSessionHeaders({ Accept: 'application/json' })
                    : { Accept: 'application/json' },
                cache: 'no-store'
            }).then(function (res2) {
                return res2.text().then(function (text2) {
                    var j2 = null;
                    try {
                        j2 = text2 ? JSON.parse(text2) : null;
                    } catch (e2) {
                        j2 = null;
                    }
                    return { status: res2.status, j: j2, proxied: true };
                });
            });
        });
    }
    // Bakiye polling aralığı. 1sn çok agresifti (sürekli ağ + DOM güncellemesi
    // kasmaya yol açıyordu); 5sn yeterli, oyun callbackleri zaten anlık güncelliyor.
    var INTERVAL_MS = 5000;
    var MAX_INTERVAL_MS = 30000;
    var currentIntervalMs = INTERVAL_MS;
    var balanceFailStreak = 0;
    var LOYALTY_INTERVAL_MS = 30000;
    var balanceInFlight = false;
    var loyaltyInFlight = false;
    var lastBalanceRow = null;

    function formatMoney(val) {
        var n = parseFloat(val);
        if (isNaN(n)) {
            n = 0;
        }
        return n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function parsePayload(j) {
        if (!j || j.success !== true || !j.data || typeof j.data !== 'object') {
            return null;
        }
        var b = j.data.balance && typeof j.data.balance === 'object' ? j.data.balance : j.data;
        return {
            balance: b.balance != null ? b.balance : (j.data.amount != null ? j.data.amount : j.data.ana_bakiye),
            bonus_balance: b.bonus_balance != null ? b.bonus_balance : j.data.bonus_bakiye
        };
    }

    function setText(id, text) {
        document.querySelectorAll('#' + id + ', [data-balance-target="' + id + '"]').forEach(function (el) {
            el.textContent = text;
        });
    }

    function syncProfileSidebarFromFormatted(mainFormatted, bonusFormatted) {
        var mainWith = mainFormatted + ' ₺';
        var bonusWith = bonusFormatted + ' ₺';
        document.querySelectorAll('.profile-sidebar-v2 .main-balance-card .total-balance .amount').forEach(function (el) {
            el.textContent = mainWith;
        });
        document.querySelectorAll('.profile-sidebar-v2 .bonus-balance-card .amount').forEach(function (el) {
            el.textContent = bonusWith;
        });
    }

    function applyBalances(row) {
        lastBalanceRow = row;
        var main = formatMoney(row.balance);
        var bonus = formatMoney(row.bonus_balance);
        setText('headerBalanceMain', main);
        setText('playBalanceMain', main);
        setText('playBalanceBonus', bonus);
        syncProfileSidebarFromFormatted(main, bonus);
    }

    function parseLoyaltyPayload(j) {
        if (!j || j.success !== true || !j.data || typeof j.data !== 'object') {
            return null;
        }
        var data = j.data;
        var badge = data.badge && typeof data.badge === 'object' ? data.badge : null;
        if (badge) {
            return {
                name: badge.name || 'Bronze',
                code: badge.code || 'bronze',
                icon_url: badge.icon_url || '/content/images/loyalty_points/bronze.png',
                initial: badge.initial || String(badge.name || 'Bronze').charAt(0).toUpperCase(),
                points: badge.points != null ? badge.points : 0,
                redeemable_points: badge.redeemable_points != null ? badge.redeemable_points : 0,
                progress_percent: badge.progress_percent != null ? badge.progress_percent : 0
            };
        }
        var level = data.level && typeof data.level === 'object' ? data.level : {};
        var account = data.account && typeof data.account === 'object' ? data.account : {};
        var progress = data.progress && typeof data.progress === 'object' ? data.progress : {};
        return {
            name: level.name || 'Bronze',
            code: level.code || account.level_code || 'bronze',
            icon_url: level.icon_url || '/content/images/loyalty_points/bronze.png',
            initial: (level.name || 'Bronze').charAt(0).toUpperCase(),
            points: account.points != null ? account.points : 0,
            redeemable_points: account.redeemable_points != null ? account.redeemable_points : 0,
            progress_percent: progress.percent != null ? progress.percent : 0
        };
    }

    function applyLoyalty(row) {
        if (row && String(row.code || '').toLowerCase() === 'bronze') {
            row.icon_url = '/assets/images/loyalty/badges/bronze.png';
        }
        document.querySelectorAll('[data-loyalty-badge]').forEach(function (el) {
            el.setAttribute('title', row.name);
            el.setAttribute('data-loyalty-code', row.code);
        });
        document.querySelectorAll('[data-loyalty-level-name]').forEach(function (el) {
            el.textContent = row.name;
        });
        document.querySelectorAll('[data-loyalty-level-initial]').forEach(function (el) {
            el.textContent = row.initial;
        });
        document.querySelectorAll('[data-loyalty-level-icon]').forEach(function (el) {
            if (row.icon_url && el.getAttribute('src') !== row.icon_url) {
                el.setAttribute('src', row.icon_url);
                el.style.display = '';
            }
        });
        document.querySelectorAll('[data-loyalty-points]').forEach(function (el) {
            el.textContent = String(row.points || 0) + ' puan';
        });
        document.querySelectorAll('[data-loyalty-progress]').forEach(function (el) {
            el.style.width = Math.max(0, Math.min(100, parseInt(row.progress_percent, 10) || 0)) + '%';
        });
    }

    /** Profil sidebar — son API yanıtındaki bonus ile header ana bakiyesini eşle. */
    window.__syncProfileSidebarBalancesFromHeaderDom = function () {
        var mainEl = document.getElementById('headerBalanceMain');
        if (!mainEl || !lastBalanceRow) {
            return false;
        }
        syncProfileSidebarFromFormatted(
            formatMoney(lastBalanceRow.balance),
            formatMoney(lastBalanceRow.bonus_balance)
        );
        return true;
    };

    function tick(force) {
        if (!memberLoggedIn()) {
            return;
        }
        if (document.hidden && force !== true) {
            return;
        }
        if (balanceInFlight) {
            return;
        }
        balanceInFlight = true;
        fetchBalanceRow()
            .then(function (x) {
                if (x.status === 401) {
                    if (Shared.handleMemberAuthFailure) {
                        return Shared.handleMemberAuthFailure().then(function (ok) {
                            if (ok) {
                                tick(true);
                            }
                        });
                    }
                    return;
                }
                var row = parsePayload(x.j);
                if (row) {
                    balanceFailStreak = 0;
                    currentIntervalMs = INTERVAL_MS;
                    applyBalances(row);
                }
            })
            .catch(function () {
                balanceFailStreak += 1;
                currentIntervalMs = Math.min(MAX_INTERVAL_MS, INTERVAL_MS * Math.pow(2, Math.min(balanceFailStreak, 3)));
            })
            .then(function () {
                balanceInFlight = false;
            });
    }

    function loyaltyTick(force) {
        if (!memberLoggedIn()) {
            return;
        }
        if (document.hidden && force !== true) {
            return;
        }
        if (loyaltyInFlight) {
            return;
        }
        loyaltyInFlight = true;
        var loyaltyUrl = apiUrl('/api/v2/loyalty');
        var loyaltyReq = Shared.memberRequestInit
            ? Shared.memberRequestInit(loyaltyUrl, { Accept: 'application/json' })
            : {
                credentials: fetchCredentials(loyaltyUrl),
                headers: memberAuthHeaders({ Accept: 'application/json' })
            };
        fetch(loyaltyUrl, {
            method: 'GET',
            credentials: loyaltyReq.credentials,
            headers: loyaltyReq.headers,
            cache: 'no-store'
        })
            .then(function (res) {
                return res.text().then(function (text) {
                    var j = null;
                    try {
                        j = text ? JSON.parse(text) : null;
                    } catch (e) {
                        j = null;
                    }
                    return { status: res.status, j: j };
                });
            })
            .then(function (x) {
                if (x.status === 401) {
                    if (Shared.handleMemberAuthFailure) {
                        return Shared.handleMemberAuthFailure().then(function (ok) {
                            if (ok) {
                                tick(true);
                            }
                        });
                    }
                    return;
                }
                var row = parseLoyaltyPayload(x.j);
                if (row) {
                    applyLoyalty(row);
                }
            })
            .catch(function () {
                /* ağ kesintisi: bir sonraki tick */
            })
            .then(function () {
                loyaltyInFlight = false;
            });
    }

    function scheduleBalanceTick() {
        window.setTimeout(function () {
            tick();
            scheduleBalanceTick();
        }, currentIntervalMs);
    }

    function start() {
        if (!memberLoggedIn()) {
            return;
        }
        if (window.__HEADER_BALANCE_POLL_STARTED__) {
            return;
        }
        window.__HEADER_BALANCE_POLL_STARTED__ = true;
        tick();
        loyaltyTick();
        scheduleBalanceTick();
        setInterval(loyaltyTick, LOYALTY_INTERVAL_MS);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                tick(true);
                loyaltyTick(true);
            }
        });
        window.__refreshHeaderBalance = function () {
            tick(true);
            loyaltyTick(true);
        };
        window.addEventListener('focus', function () {
            tick(true);
            loyaltyTick(true);
        });
        window.addEventListener('maltabet:balance-refresh', function () {
            tick(true);
            loyaltyTick(true);
        });
        window.addEventListener('metropol:member-jwt-ready', function () {
            tick(true);
            loyaltyTick(true);
        });
    }

    function boot() {
        if (Shared.isLogoutLanding && Shared.isLogoutLanding()) {
            return;
        }
        if (window.__USER_LOGGED_IN__ !== true) {
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
