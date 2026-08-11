/**
 * Profil ile ilgili tüm JavaScript davranışları tek dosyada.
 * Sayfa verisi: window.__PROFILE_PAYMENT_LIMITS__, window.__PROFILE_TRANSACTIONS__, window.__DEPOSIT_HISTORY_API__
 *
 * @dynamic-file
 * @entry openVegaPanel, closeVegaPanel, processVegaDeposit, processInlineVegaDeposit, processVegaWithdrawal
 * @entry closeSuccessWithdrawalPopup, showAppFeedbackDialog, setCm622MultiSelectValue
 * @entry openProfileModalFromHeader, openProfileModalUrl
 * @keep initProfileModal, initProfileSidebar, initCm622MultiSelects, bindCm622FormControls
 */
(function() {
    'use strict';
    var ProfileApi = window.MaltabetProfileApi || {};
    var Shared = window.BetcoAuthShared || {};
    function apiUrl(path) {
        return ProfileApi.apiUrl ? ProfileApi.apiUrl(path) : (Shared.apiUrl ? Shared.apiUrl(path) : path);
    }
    function appendQuery(url, query) {
        return ProfileApi.appendQuery ? ProfileApi.appendQuery(url, query) : url + (url.indexOf('?') >= 0 ? '&' : '?') + query;
    }
    function memberAuthHeaders(extra) {
        return ProfileApi.memberAuthHeaders ? ProfileApi.memberAuthHeaders(extra) : (Shared.memberAuthHeaders ? Shared.memberAuthHeaders(extra) : (function () {
            var h = extra || {};
            var csrf = (window.__CSRF_TOKEN__ || '').trim();
            if (csrf) h['X-CSRF-Token'] = csrf;
            return h;
        })());
    }
    function toastNotify(type, message, title) {
        if (ProfileApi.toastNotify) {
            ProfileApi.toastNotify(type, message, title);
            return;
        }
        var msg = String(message || '').trim();
        if (!msg) return;
        if (typeof window.MaltabetToast !== 'undefined' && window.MaltabetToast) {
            var m = window.MaltabetToast;
            if (type === 'error' && m.error) { m.error(msg, title); return; }
            if (type === 'warning' && m.warning) { m.warning(msg, title); return; }
            if (type === 'success' && m.success) { m.success(msg, title); return; }
            if (m.show) { m.show(msg, { type: type || 'info', title: title }); return; }
        }
        // MaltabetToast yoksa alert gosterme, sessizce console'a yaz
        if (typeof console !== 'undefined' && console.warn) {
            console.warn('[Profile] ' + (title ? title + ': ' : '') + msg);
        }
    }
    var TR_LOCALE = (typeof window.__INTL_LOCALE__ === 'string' && window.__INTL_LOCALE__)
        ? window.__INTL_LOCALE__
        : 'tr-TR';
    var DEFAULT_LIMITS = { min: 0, max: 999999 };
    var amountFormatter = new Intl.NumberFormat(TR_LOCALE, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    var dateTimeFormatter = new Intl.DateTimeFormat(TR_LOCALE, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    var shortDateFormatter = new Intl.DateTimeFormat(TR_LOCALE, { year: 'numeric', month: 'long', day: 'numeric' });
    var balanceCache = { data: null, fetchedAt: 0, pending: null };
    var BALANCE_CACHE_TTL_MS = 3000;

    function t(key, fallback) {
        var bag = window.__I18N__;
        if (bag && typeof bag === 'object' && Object.prototype.hasOwnProperty.call(bag, key) && bag[key] != null && bag[key] !== '') {
            return String(bag[key]);
        }
        return fallback != null ? String(fallback) : key;
    }

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function formatTryAmount(value) {
        var text = String(value == null ? '0' : value).trim();
        text = text.replace(/\s*[?]|(?:\s*TL)$/gi, '').trim();
        text = text.replace(/\s*₺$/g, '').trim();
        if (/^-?\d+(?:\.\d+)?$/.test(text)) {
            return amountFormatter.format(Number(text)) + ' ₺';
        }
        return text + ' ₺';
    }

    var PROFILE_BALANCE_HIDE_KEY = 'vrs_profile_balance_hidden';
    var PROFILE_BALANCE_MASK = '•••••• ₺';

    function isProfileBalanceHidden() {
        try {
            return localStorage.getItem(PROFILE_BALANCE_HIDE_KEY) === '1';
        } catch (eHide) {
            return false;
        }
    }

    function setProfileBalanceHidden(hidden) {
        try {
            localStorage.setItem(PROFILE_BALANCE_HIDE_KEY, hidden ? '1' : '0');
        } catch (eSet) { /* ignore */ }
        document.documentElement.classList.toggle('is-profile-balance-hidden', !!hidden);
    }

    function writeProfileBalanceEl(el, formatted) {
        if (!el) return;
        var value = String(formatted == null ? '' : formatted);
        el.setAttribute('data-balance-display', value);
        el.textContent = isProfileBalanceHidden() ? PROFILE_BALANCE_MASK : value;
    }

    function refreshProfileBalanceVisibility() {
        var hidden = isProfileBalanceHidden();
        document.documentElement.classList.toggle('is-profile-balance-hidden', hidden);
        document.querySelectorAll('[data-balance-display]').forEach(function(el) {
            var real = el.getAttribute('data-balance-display') || '';
            el.textContent = hidden ? PROFILE_BALANCE_MASK : real;
        });
        document.querySelectorAll('[data-profile-balance-toggle]').forEach(function(icon) {
            icon.classList.toggle('bc-i-eye', !hidden);
            icon.classList.toggle('bc-i-eye-hidden', hidden);
            icon.setAttribute('aria-pressed', hidden ? 'true' : 'false');
            icon.setAttribute('aria-label', hidden ? t('profile.show_balance', 'Bakiyeyi göster') : t('profile.hide_balance', 'Bakiyeyi gizle'));
            icon.setAttribute('title', hidden ? t('profile.show_balance', 'Bakiyeyi göster') : t('profile.hide_balance', 'Bakiyeyi gizle'));
        });
    }

    function initProfileBalanceToggleOnce() {
        if (window.__profileBalanceToggleBound) return;
        window.__profileBalanceToggleBound = true;
        setProfileBalanceHidden(isProfileBalanceHidden());
        document.addEventListener('click', function(e) {
            var icon = e.target && e.target.closest ? e.target.closest('[data-profile-balance-toggle]') : null;
            if (!icon) return;
            e.preventDefault();
            e.stopPropagation();
            setProfileBalanceHidden(!isProfileBalanceHidden());
            refreshProfileBalanceVisibility();
        }, true);
        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var icon = e.target && e.target.closest ? e.target.closest('[data-profile-balance-toggle]') : null;
            if (!icon) return;
            e.preventDefault();
            setProfileBalanceHidden(!isProfileBalanceHidden());
            refreshProfileBalanceVisibility();
        }, true);
        window.__refreshProfileBalanceVisibility = refreshProfileBalanceVisibility;
        window.__writeProfileBalanceEl = writeProfileBalanceEl;
        window.__isProfileBalanceHidden = isProfileBalanceHidden;
    }

    function normalizeBalancePayload(data) {
        if (!data) {
            return { status: 'error', ana_bakiye: 0, bonus_bakiye: 0, toplam_bonus: 0 };
        }
        if (data.success === true && data.data && typeof data.data === 'object') {
            var payload = data.data;
            var row = payload.balance && typeof payload.balance === 'object' ? payload.balance : payload;
            var main = row.balance != null ? row.balance : (payload.amount != null ? payload.amount : payload.ana_bakiye);
            var bonus = row.bonus_balance != null ? row.bonus_balance : (payload.bonus_balance != null ? payload.bonus_balance : payload.bonus_bakiye);
            main = Number(main || 0);
            bonus = Number(bonus || 0);
            return {
                status: 'success',
                ana_bakiye: isNaN(main) ? 0 : main,
                bonus_bakiye: isNaN(bonus) ? 0 : bonus,
                toplam_bonus: isNaN(bonus) ? 0 : bonus,
                balance: isNaN(main) ? 0 : main,
                bonus_balance: isNaN(bonus) ? 0 : bonus
            };
        }
        return data;
    }

    function bonusCategoryMatchesPageKind(category, kind) {
        var c = String(category || '').toLowerCase().trim();
        if (kind === 'spor') {
            return c === 'sports' || c === 'sport' || c === 'spor';
        }
        if (kind === 'casino') {
            return (
                c === 'slots'
                || c === 'live_casino'
                || c === 'casino'
                || c === 'loss_bonus'
                || c === 'vip'
            );
        }
        return false;
    }

    function formatProfileBonusDate(iso) {
        if (!iso) {
            return '—';
        }
        try {
            var d = new Date(iso);
            if (isNaN(d.getTime())) {
                return String(iso);
            }
            return dateTimeFormatter.format(d);
        } catch (eD) {
            return String(iso);
        }
    }

    function formatProfileMoneyPlain(n) {
        if (n == null || n === '') {
            return '0,00';
        }
        var x = Number(n);
        if (isNaN(x)) {
            return String(n);
        }
        return amountFormatter.format(x);
    }

    function buildActiveBonusCard(bonus) {
        var card = document.createElement('div');
        card.className = 'profile-active-bonus-card';
        var title = document.createElement('h2');
        title.className = 'profile-active-bonus-title';
        title.textContent = (bonus.displayName || bonus.name || bonus.promotionTitle || 'Aktif bonus').trim() || 'Aktif bonus';
        card.appendChild(title);
        var promo = (bonus.promotionTitle || '').trim();
        if (promo && promo !== title.textContent) {
            var sub = document.createElement('p');
            sub.className = 'profile-active-bonus-sub';
            sub.textContent = promo;
            card.appendChild(sub);
        }
        var dl = document.createElement('dl');
        dl.className = 'profile-active-bonus-dl';
        function addRow(label, value) {
            var dt = document.createElement('dt');
            dt.textContent = label;
            var dd = document.createElement('dd');
            dd.textContent = value;
            dl.appendChild(dt);
            dl.appendChild(dd);
        }
        var cur = bonus.currentBonusBalance != null ? bonus.currentBonusBalance : bonus.amount;
        addRow('Bonus bakiyesi', formatProfileMoneyPlain(cur) + ' ₺');
        var wagerLabel = (bonus.wageringRequirementLabel || '').trim();
        if (!wagerLabel && bonus.wageringRequirement != null) {
            wagerLabel = String(bonus.wageringRequirement) + 'x';
        }
        if (wagerLabel) {
            addRow('Çevrim sarti', wagerLabel);
        }
        var tgt = bonus.wageringTarget;
        var bet = bonus.totalBetAmount;
        if (tgt != null || bet != null) {
            addRow(
                'Çevrim ilerlemesi',
                formatProfileMoneyPlain(bet != null ? bet : 0) + ' / ' + formatProfileMoneyPlain(tgt != null ? tgt : 0) + ' ₺'
            );
        }
        var rem = bonus.remainingBet;
        if (rem != null) {
            addRow('Kalan çevrim', formatProfileMoneyPlain(rem) + ' ₺');
        }
        card.appendChild(dl);
        var prog = bonus.progress;
        if (prog != null && !isNaN(Number(prog))) {
            var pct = Math.min(100, Math.max(0, Number(prog)));
            var barWrap = document.createElement('div');
            barWrap.className = 'profile-active-bonus-progress';
            var bar = document.createElement('div');
            bar.className = 'profile-active-bonus-progress-bar';
            bar.style.width = pct + '%';
            bar.setAttribute('role', 'progressbar');
            bar.setAttribute('aria-valuenow', String(Math.round(pct)));
            bar.setAttribute('aria-valuemin', '0');
            bar.setAttribute('aria-valuemax', '100');
            barWrap.appendChild(bar);
            card.appendChild(barWrap);
            var pctTxt = document.createElement('p');
            pctTxt.className = 'profile-active-bonus-pct';
            var pctRounded = Math.round(pct * 100) / 100;
            pctTxt.textContent = pctRounded + '% tamamlandi';
            card.appendChild(pctTxt);
        }
        var meta = document.createElement('div');
        meta.className = 'profile-active-bonus-meta';
        var st = (bonus.status || '').trim();
        if (st) {
            var badge = document.createElement('span');
            badge.className = 'profile-active-bonus-badge';
            badge.textContent = st;
            meta.appendChild(badge);
        }
        var cat = (bonus.category || '').trim();
        if (cat) {
            var cEl = document.createElement('span');
            cEl.className = 'profile-active-bonus-cat';
            cEl.textContent = cat;
            meta.appendChild(cEl);
        }
        if (meta.childNodes.length) {
            card.appendChild(meta);
        }
        var foot = document.createElement('p');
        foot.className = 'profile-active-bonus-dates';
        foot.textContent = 'Bitis: ' + formatProfileBonusDate(bonus.deadline)
            + ' ? Veriliş: ' + formatProfileBonusDate(bonus.grantedAt);
        card.appendChild(foot);
        return card;
    }

    function profileBonusPromotionMatchesKind(promo, kind) {
        var category = String(promo && promo.category || '').toLowerCase().trim();
        var text = (String(promo && promo.title || '') + ' ' + String(promo && promo.description || '') + ' ' + String(promo && promo.long_description || '') + ' ' + category)
            .toLocaleLowerCase('tr-TR');
        if (kind === 'spor') {
            return category === 'sports' || category === 'sport' || category === 'spor'
                || text.indexOf('spor') !== -1
                || text.indexOf('sport') !== -1
                || text.indexOf('freebet') !== -1;
        }
        if (kind === 'casino') {
            if (category === 'slots' || category === 'live_casino' || category === 'casino' || category === 'loss_bonus' || category === 'vip') {
                return true;
            }
            return text.indexOf('slot') !== -1
                || text.indexOf('casino') !== -1
                || text.indexOf('canli') !== -1
                || text.indexOf('kayip') !== -1
                || text.indexOf('kayip') !== -1
                || text.indexOf('freespin') !== -1;
        }
        return false;
    }

    function profileBonusClaimNeedsDeposit(claimPolicy) {
        return !!(claimPolicy && claimPolicy.requiresConfirmedDeposit);
    }

    function profileBonusDepositWarning(claimPolicy) {
        var msg = claimPolicy && typeof claimPolicy.depositRequiredMessage === 'string'
            ? claimPolicy.depositRequiredMessage.trim()
            : '';
        return msg || 'Bu bonustan faydalanabilmeniz için yatırım yapmaniz gerekmektedir.';
    }

    function submitProfileBonusClaim(promotionId, statusEl, actionBtn) {
        var id = parseInt(promotionId, 10) || 0;
        if (id <= 0) {
            if (statusEl) {
                statusEl.textContent = 'Geçersiz promosyon seçimi.';
                statusEl.classList.add('is-error');
                statusEl.classList.remove('is-success');
            }
            return;
        }
        if (statusEl) {
            statusEl.textContent = '';
            statusEl.classList.remove('is-error', 'is-success');
        }
        if (actionBtn) actionBtn.disabled = true;

        fetch(apiUrl('/api/v2/bonus-claim'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: memberAuthHeaders({ 'Content-Type': 'application/json', Accept: 'application/json' }),
            body: JSON.stringify({ promotionId: id })
        })
            .then(function(r) {
                return r.text().then(function(text) {
                    var data = {};
                    try { data = text ? JSON.parse(text) : {}; } catch (ignore) {}
                    return { res: r, data: data };
                });
            })
            .then(function(result) {
                var ok = result.res.ok && !!(result.data && result.data.success);
                var message = (result.data && result.data.message)
                    ? String(result.data.message)
                    : (ok ? 'Bonus talebiniz alındı.' : 'Bonus talebi oluşturulamadı.');
                if (statusEl) {
                    statusEl.textContent = message;
                    statusEl.classList.toggle('is-success', ok);
                    statusEl.classList.toggle('is-error', !ok);
                }
                toastNotify(ok ? 'success' : 'error', message, ok ? t('common.success', 'Başarılı') : t('common.error', 'Hata'));
            })
            .catch(function() {
                if (statusEl) {
                    statusEl.textContent = t('common.connection_error', 'Bağlantı hatası. Lütfen tekrar deneyin.');
                    statusEl.classList.add('is-error');
                    statusEl.classList.remove('is-success');
                }
            })
            .then(function() {
                if (actionBtn) actionBtn.disabled = false;
            });
    }

    function renderProfileBonusClaimArea(root, kind, activePayload, promoPayload) {
        var activeData = activePayload || {};
        var promoData = promoPayload || {};
        var promoEnvelope = promoData.data || {};
        var claimPolicy = promoEnvelope.claimPolicy || {};
        var hasConfirmedDeposit = !!(promoEnvelope.viewer && promoEnvelope.viewer.hasConfirmedDeposit);
        var requiresDeposit = profileBonusClaimNeedsDeposit(claimPolicy);
        var depositWarning = profileBonusDepositWarning(claimPolicy);
        var promotions = Array.isArray(promoEnvelope.promotions) ? promoEnvelope.promotions : [];
        var filtered = promotions.filter(function(promo) {
            return promo && typeof promo === 'object' && profileBonusPromotionMatchesKind(promo, kind);
        });

        root.innerHTML = '';

        var panel = document.createElement('div');
        panel.className = 'profile-bonus-claim-panel';

        if (activeData.success && activeData.data && activeData.data.hasActiveBonus && activeData.data.bonus && bonusCategoryMatchesPageKind(activeData.data.bonus.category, kind)) {
            panel.appendChild(buildActiveBonusCard(activeData.data.bonus));
        }

        if (requiresDeposit && !hasConfirmedDeposit) {
            var warning = document.createElement('p');
            warning.className = 'profile-bonus-claim-warning';
            warning.textContent = depositWarning;
            panel.appendChild(warning);
        }

        var claimStatus = document.createElement('p');
        claimStatus.className = 'profile-bonus-claim-status';
        claimStatus.setAttribute('role', 'status');
        claimStatus.setAttribute('aria-live', 'polite');

        if (!filtered.length) {
            var empty = document.createElement('p');
            empty.className = 'bonus-casino-empty bonus-spor-empty profile-active-bonus-empty';
            empty.textContent = 'Seçilen tür için aktif bonus bulunmuyor.';
            panel.appendChild(empty);
            panel.appendChild(claimStatus);
            root.appendChild(panel);
            return;
        }

        var grid = document.createElement('div');
        grid.className = 'profile-bonus-claim-grid';
        filtered.forEach(function(promo) {
            var card = document.createElement('article');
            card.className = 'profile-bonus-claim-card';

            var imageUrl = String(promo.image_url || promo.imageUrl || promo.image || '').trim();
            if (imageUrl) {
                var img = document.createElement('img');
                img.className = 'profile-bonus-claim-image';
                img.src = imageUrl;
                img.alt = '';
                img.loading = 'lazy';
                img.decoding = 'async';
                card.appendChild(img);
            }

            var title = document.createElement('h3');
            title.className = 'profile-bonus-claim-title';
            title.textContent = String(promo.title || 'Bonus').trim() || 'Bonus';

            var desc = document.createElement('p');
            desc.className = 'profile-bonus-claim-desc';
            var rawDesc = String(promo.description || promo.long_description || 'Bu bonus için talep oluşturabilirsiniz.').trim();
            var normalizedTitle = title.textContent.toLocaleLowerCase('tr-TR');
            var normalizedDesc = rawDesc.toLocaleLowerCase('tr-TR');
            var finalDesc = rawDesc;
            if (!finalDesc || normalizedDesc === normalizedTitle) {
                finalDesc = 'Bu bonus için talep oluşturabilirsiniz.';
            }
            desc.textContent = finalDesc.length > 130 ? finalDesc.slice(0, 127) + '...' : finalDesc;

            var action = document.createElement('button');
            action.type = 'button';
            action.className = 'btn a-color profile-bonus-claim-btn';
            action.textContent = 'Talep Et';
            action.disabled = requiresDeposit && !hasConfirmedDeposit;
            if (action.disabled) {
                action.title = depositWarning;
            }
            action.addEventListener('click', function() {
                submitProfileBonusClaim(promo.id || promo.promotionId || 0, claimStatus, action);
            });

            card.appendChild(title);
            card.appendChild(desc);
            card.appendChild(action);
            grid.appendChild(card);
        });

        panel.appendChild(grid);
        panel.appendChild(claimStatus);
        root.appendChild(panel);
    }

    var activeBonusPromoCache = { pending: null, data: null, fetchedAt: 0 };
    var ACTIVE_BONUS_PROMO_CACHE_TTL_MS = 8000;

    /** /api/v2/active-bonus + /api/v2/content/promotions sonucunu kısa süreliğine önbelleğe alir.
     * Bonus modal sekmeleri (spor/casino) hızlıca değiştirildiğinde her seferinde yeniden istek atmayi önler. */
    function fetchActiveBonusAndPromotions(forceRefresh) {
        var now = Date.now();
        if (!forceRefresh && activeBonusPromoCache.data && (now - activeBonusPromoCache.fetchedAt) < ACTIVE_BONUS_PROMO_CACHE_TTL_MS) {
            return Promise.resolve(activeBonusPromoCache.data);
        }
        if (activeBonusPromoCache.pending) return activeBonusPromoCache.pending;

        var activeReq = fetch(apiUrl('/api/v2/active-bonus'), {
            credentials: 'same-origin',
            headers: memberAuthHeaders({ Accept: 'application/json' })
        })
            .then(function(r) {
                return r.json().then(function(data) {
                    return { ok: r.ok, status: r.status, data: data || {} };
                });
            })
            .catch(function() {
                return { ok: false, status: 0, data: {} };
            });

        var promoReq = fetch(apiUrl('/api/v2/content/promotions'), {
            credentials: 'same-origin',
            headers: memberAuthHeaders({ Accept: 'application/json' })
        })
            .then(function(r) {
                return r.json().then(function(data) {
                    return { ok: r.ok, status: r.status, data: data || {} };
                });
            })
            .catch(function() {
                return { ok: false, status: 0, data: {} };
            });

        activeBonusPromoCache.pending = Promise.all([activeReq, promoReq])
            .then(function(results) {
                var promoData = (results[1] || {}).data || {};
                if (promoData.success) {
                    activeBonusPromoCache.data = results;
                    activeBonusPromoCache.fetchedAt = Date.now();
                }
                return results;
            })
            .finally(function() {
                activeBonusPromoCache.pending = null;
            });
        return activeBonusPromoCache.pending;
    }

    function initProfileActiveBonus() {
        document.querySelectorAll('.js-profile-active-bonus:not([data-active-bonus-bound])').forEach(function(root) {
            root.setAttribute('data-active-bonus-bound', '1');
            var kind = (root.getAttribute('data-bonus-kind') || '').trim();
            root.innerHTML = '<p class="profile-active-bonus-loading">Yükleniyor…</p>';

            fetchActiveBonusAndPromotions()
                .then(function(results) {
                    var activeRes = results[0] || { data: {} };
                    var promoRes = results[1] || { data: {} };
                    var promoData = promoRes.data || {};

                    if (promoRes.status === 401 || promoData.code === 401) {
                        root.innerHTML = '';
                        var auth = document.createElement('p');
                        auth.className = 'profile-active-bonus-error';
                        auth.textContent = (promoData.message || 'Oturum gerekli.').trim();
                        root.appendChild(auth);
                        return;
                    }

                    if (!promoData.success) {
                        root.innerHTML = '';
                        var err = document.createElement('p');
                        err.className = 'profile-active-bonus-error';
                        err.textContent = (promoData.message || 'Bonus bilgisi alinamadi.').trim();
                        root.appendChild(err);
                        return;
                    }

                    renderProfileBonusClaimArea(root, kind, activeRes.data || {}, promoData);
                })
                .catch(function() {
                    root.innerHTML = '';
                    var err = document.createElement('p');
                    err.className = 'profile-active-bonus-error';
                    err.textContent = t('common.connection_error', 'Bağlantı hatası. Lütfen tekrar deneyin.');
                    root.appendChild(err);
                });
        });
    }

    function fetchBalanceData(forceRefresh) {
        var now = Date.now();
        if (!forceRefresh && balanceCache.data && (now - balanceCache.fetchedAt) < BALANCE_CACHE_TTL_MS) {
            return Promise.resolve(balanceCache.data);
        }
        if (balanceCache.pending) return balanceCache.pending;
        balanceCache.pending = fetch(apiUrl('/api/v2/balance'), {
            credentials: 'same-origin',
            headers: memberAuthHeaders({ Accept: 'application/json' }),
            cache: 'no-store'
        })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                balanceCache.data = normalizeBalancePayload(data);
                balanceCache.fetchedAt = Date.now();
                return balanceCache.data;
            })
            .finally(function() {
                balanceCache.pending = null;
            });
        return balanceCache.pending;
    }

    var profileShellHtmlCache = {}; /* CM622_PROFILE_CACHE_BUST_20260803x */
    var profileShellFetchPending = {};
    var profileShellPrefetchPending = {};
    var profileShellPrefetchQueued = false;
    var profileShellPrefetchDone = false;
    var PROFILE_AUTH_RELOAD_GUARD_KEY = 'profileAuthReloadGuard';
    var PROFILE_AUTH_RELOAD_WINDOW_MS = 15000;

    function clearProfileAuthReloadGuard() {
        try {
            sessionStorage.removeItem(PROFILE_AUTH_RELOAD_GUARD_KEY);
        } catch (eGuard) {}
        window.__profileAuthReloadAttempted = false;
    }

    function shouldAttemptProfileAuthReload() {
        var now = Date.now();
        try {
            var raw = sessionStorage.getItem(PROFILE_AUTH_RELOAD_GUARD_KEY);
            var guard = raw ? JSON.parse(raw) : null;
            var sameWindow = !!(guard && guard.ts && (now - Number(guard.ts) < PROFILE_AUTH_RELOAD_WINDOW_MS));
            var count = sameWindow ? Number(guard.count || 0) : 0;
            if (count >= 1) {
                return false;
            }
            sessionStorage.setItem(PROFILE_AUTH_RELOAD_GUARD_KEY, JSON.stringify({ ts: now, count: count + 1 }));
            return true;
        } catch (eGuard) {
            if (window.__profileAuthReloadAttempted) {
                return false;
            }
            window.__profileAuthReloadAttempted = true;
            return true;
        }
    }

    function fastStringHash(input) {
        var str = String(input || '');
        var hash = 5381;
        for (var i = 0; i < str.length; i++) {
            hash = ((hash << 5) + hash) ^ str.charCodeAt(i);
        }
        return (hash >>> 0).toString(36);
    }

    /** SPA: yalnızca tam profil kabuğu (#profilePlayerMain) yanıtlarını önbelleğe al (hata/redirect gövdeleri hariç). */
    function profileShellHtmlLooksComplete(html) {
        if (!html || typeof html !== 'string') {
            return false;
        }
        return html.indexOf('profilePlayerMain') !== -1;
    }
    var profileModalApi = { closeModal: null };

    function isPromotionsPagePath(url) {
        if (!url) return false;
        return url === '/promotions' || url.indexOf('/promotions/') === 0
            || url === '/promosyonlar' || url.indexOf('/promosyonlar/') === 0;
    }

    function isLogoutPath(url) {
        return url === '/logout';
    }

    /** Header / smart panel / kupon: tam sayfa geçişi ? profil modali açılmaz */
    function isFullPageHeaderNavPath(url) {
        if (!url) return false;
        return isPromotionsPagePath(url) || isLogoutPath(url);
    }

    function getLinkNavMode(link) {
        if (!link) return '';
        return (link.getAttribute('data-nav-mode') || '').trim().toLowerCase();
    }

    function shouldOpenProfileModalForHeaderLink(link, pathname) {
        var mode = getLinkNavMode(link);
        if (mode === 'page') return false;
        if (mode === 'modal') return canLoadInProfileModal(pathname);
        if (isFullPageHeaderNavPath(pathname)) return false;
        return canLoadInProfileModal(pathname);
    }

    function isHeaderProfileModalEntryLink(link) {
        if (!link) return false;
        if (link.closest('#profileModalContent')) return false;
        return !!(
            link.closest('#depositNav') ||
            link.closest('#playerNav') ||
            link.closest('.hdr-deposit-btn') ||
            link.closest('.connect-wallet') ||
            link.closest('.hdr-smart-panel-holder-bc') ||
            link.closest('#betslipPanel') ||
            link.closest('.loyaltyBonusHeader') ||
            link.closest('[data-loyalty-badge]')
        );
    }

    function canLoadInProfileModal(url) {
        if (!url || isFullPageHeaderNavPath(url)) return false;
        return url.indexOf('/profile/') === 0;
    }

    function canFullPageProfileShellSpa(pathname) {
        if (!pathname) return false;
        return pathname.indexOf('/profile/') === 0;
    }

    function isHomeBalanceHistoryQuery(urlObj) {
        return !!(urlObj
            && urlObj.pathname === '/'
            && urlObj.searchParams.get('profile') === 'open'
            && urlObj.searchParams.get('account') === 'balance'
            && urlObj.searchParams.get('page') === 'history');
    }

    function isMobileSiteContext() {
        var html = document.documentElement;
        if (html && html.classList && html.classList.contains('is-mobile')) return true;
        var host = String((window.location && window.location.hostname) || '').toLowerCase();
        if (host.indexOf('m.') === 0) return true;
        var body = document.body;
        return !!(body && body.classList && body.classList.contains('mobile-site'));
    }

    function toModalUrl(url) {
        var base = new URL(url, window.location.origin);
        if (isHomeBalanceHistoryQuery(base) && !isMobileSiteContext()) {
            base.pathname = '/profile/deposit-withdraw-history';
            base.search = '';
        } else if (base.pathname === '/profile/transaction-history') {
            base.pathname = '/profile/deposit-withdraw-history';
        } else if (base.pathname === '/profile/info') {
            base.pathname = '/profile/deposit';
            base.searchParams.set('bilgi', '1');
            base.hash = '#bilgi';
        } else if (base.pathname === '/profile/wallet') {
            base.pathname = '/profile/deposit';
        } else if (base.pathname === '/profile/deposit-withdraw') {
            // legacy alias
            if (base.searchParams.get('tab') === 'withdraw') {
                base.pathname = '/profile/withdraw';
                base.searchParams.delete('tab');
            } else {
                base.pathname = '/profile/deposit';
                base.searchParams.delete('tab');
            }
        }
        base.searchParams.set('modal', '1');
        return base.pathname + base.search + base.hash;
    }

    function modalUrlToDisplayUrl(modalUrl) {
        var u = new URL(modalUrl, window.location.origin);
        if (u.pathname === '/profile/deposit-withdraw-history') {
            return '/?profile=open&account=balance&page=history';
        }
        u.searchParams.delete('modal');
        return u.pathname + u.search + u.hash;
    }

    function ensureFreshProfileCssOnce() {
        if (window.__profileCssRefreshedOnce) return;
        var links = document.querySelectorAll('link[rel="stylesheet"][href*="/assets/css/profile-cm622"]');
        if (!links || !links.length) return;

        var stamp = String(Date.now());
        links.forEach(function(link) {
            try {
                var rawHref = link.getAttribute('href') || link.href || '';
                var hrefUrl = new URL(rawHref, window.location.origin);
                if (hrefUrl.origin !== window.location.origin) return;
                if (hrefUrl.pathname.indexOf('/assets/css/profile-cm622') !== 0) return;
                hrefUrl.searchParams.set('pmcss', stamp);
                link.setAttribute('href', hrefUrl.pathname + '?' + hrefUrl.searchParams.toString());
            } catch (eCss) {}
        });

        window.__profileCssRefreshedOnce = true;
    }

    function hideProfileHeaderFlyouts() {
        var playerNav = document.getElementById('playerNav');
        var depositNav = document.getElementById('depositNav');
        var playerBtn = document.getElementById('toggleButton');
        var balanceBtn = document.getElementById('balanceTrigger');
        var smartHolder = document.querySelector('.hdr-smart-panel-holder-arrow-bc');
        var smartToggle = document.getElementById('smart-panel-holder');
        var betslipPanel = document.getElementById('betslipPanel');
        var betslipOverlay = document.getElementById('betslipPanelOverlay');

        if (playerNav) playerNav.classList.add('hidesection');
        if (depositNav) depositNav.classList.add('hidesection');
        if (playerBtn) playerBtn.setAttribute('aria-expanded', 'false');
        if (balanceBtn) balanceBtn.setAttribute('aria-expanded', 'false');
        if (smartHolder) {
            smartHolder.classList.remove('is-open');
            smartHolder.style.display = 'none';
        }
        if (smartToggle) {
            smartToggle.classList.remove('is-open');
            smartToggle.setAttribute('aria-expanded', 'false');
        }
        if (typeof window.__closeSmartPanel === 'function') {
            window.__closeSmartPanel();
        }
        var smartPanelFixed = document.getElementById('smartPanelFixed');
        if (smartPanelFixed) {
            smartPanelFixed.classList.remove('is-open');
            smartPanelFixed.setAttribute('aria-hidden', 'true');
        }
        if (betslipPanel) {
            betslipPanel.classList.remove('is-open');
            betslipPanel.setAttribute('aria-hidden', 'true');
        }
        if (betslipOverlay) {
            betslipOverlay.classList.remove('is-open');
            betslipOverlay.setAttribute('aria-hidden', 'true');
        }
    }

    function normalizeCacheKey(url) {
        var parsed = new URL(url, window.location.origin);
        parsed.hash = '';
        return parsed.pathname + parsed.search;
    }

    function isAuthRedirectTarget(urlObj) {
        if (!urlObj) return false;
        var path = (urlObj.pathname || '').toLowerCase();
        if (path === '/login') return true;
        if (path !== '/') return false;

        // Mobile/profile canonical redirects can legitimately land on
        // /?profile=open&... while the session is still valid.
        var profileState = (urlObj.searchParams.get('profile') || '').toLowerCase();
        if (profileState === 'open') return false;

        return true;
    }

    function fetchProfilePage(targetUrl) {
        var recoveryAttempted = false;

        function tryRecoverAuthOnce(err) {
            if (!err || !err.authRequired || recoveryAttempted) {
                return Promise.reject(err);
            }
            recoveryAttempted = true;
            if (!Shared || typeof Shared.handleMemberAuthFailure !== 'function') {
                return Promise.reject(err);
            }
            return Promise.resolve(Shared.handleMemberAuthFailure())
                .then(function(ok) {
                    if (!ok) {
                        throw err;
                    }
                    return true;
                });
        }

        function parseProfileResponse(r) {
            if (r.redirected) {
                try {
                    var finalUrl = new URL(r.url, window.location.origin);
                    if (finalUrl.origin === window.location.origin && isAuthRedirectTarget(finalUrl)) {
                        var authErr = new Error('Profil oturumu doğrulanamadı');
                        authErr.authRequired = true;
                        throw authErr;
                    }
                } catch (eUrl) {
                    if (eUrl && eUrl.authRequired) throw eUrl;
                }
            }
            if (!r.ok) throw new Error('Profil içeriği alınamadı');
            return r.text();
        }

        var resolved = apiUrl(targetUrl);
        var direct = String(targetUrl || '');
        if (direct && direct.charAt(0) !== '/') {
            direct = '/' + direct;
        }

        var fetchOptions = {
            credentials: 'same-origin',
            headers: Shared.memberAuthHeaders ? Shared.memberAuthHeaders({ Accept: 'text/html' }) : undefined
        };

        return fetch(resolved, fetchOptions)
            .then(parseProfileResponse)
            .catch(function(primaryErr) {
                if (primaryErr && primaryErr.authRequired) {
                    return tryRecoverAuthOnce(primaryErr).then(function() {
                        return fetch(resolved, fetchOptions).then(parseProfileResponse);
                    });
                }
                if (!direct || direct === resolved) {
                    throw primaryErr;
                }
                return fetch(direct, fetchOptions)
                    .then(parseProfileResponse)
                    .catch(function(directErr) {
                        if (directErr && directErr.authRequired) {
                            return tryRecoverAuthOnce(directErr).then(function() {
                                return fetch(direct, fetchOptions).then(parseProfileResponse);
                            });
                        }
                        throw directErr;
                    });
            });
    }

    function activateInlineScripts(root) {
        if (!root) return;
        root.querySelectorAll('script').forEach(function(oldScript) {
            var s = document.createElement('script');
            s.textContent = oldScript.textContent;
            oldScript.parentNode.replaceChild(s, oldScript);
        });
    }

    function profileModalNavComparable(href) {
        try {
            var x = new URL(href, window.location.origin);
            x.searchParams.delete('modal');
            if (x.pathname === '/profile/bet-history') {
                var f = x.searchParams.get('filter') || 'tumu';
                if (f === 'tumu') return '/profile/bet-history';
                return '/profile/bet-history?filter=' + f;
            }
            if (x.pathname === '/profile/messages') {
                var b = x.searchParams.get('box') || 'inbox';
                if (b === 'inbox') return '/profile/messages';
                return '/profile/messages?box=' + b;
            }
            var q = x.searchParams.toString();
            return x.pathname + (q ? '?' + q : '') + (x.hash || '');
        } catch (e) {
            return '';
        }
    }

    function syncProfileModalSidebarFromUrl(sidebarEl, profileFullUrl) {
        if (!sidebarEl || !profileFullUrl) return;
        var want = profileModalNavComparable(profileFullUrl);
        if (!want) return;
        sidebarEl.querySelectorAll('.accordion-sub a, .user-profile-nav-item').forEach(function(a) {
            a.classList.remove('active');
        });
        var links = sidebarEl.querySelectorAll('.accordion-sub a[href], .user-profile-nav-item[href]');
        for (var i = 0; i < links.length; i++) {
            var a = links[i];
            var h = a.getAttribute('href') || '';
            if (!h || h.charAt(0) === '#') continue;
            if (profileModalNavComparable(h) === want) {
                a.classList.add('active');
                var item = a.closest('.accordion-item, .user-profile-nav');
                if (item) {
                    item.classList.add('open', 'active');
                    var tr = item.querySelector('a.accordion-trigger');
                    if (tr) tr.classList.add('open');
                    var arrow = item.querySelector('.user-profile-nav-arrow');
                    if (arrow) {
                        arrow.classList.remove('bc-i-small-arrow-down');
                        arrow.classList.add('bc-i-small-arrow-up');
                    }
                    var cursor = item.querySelector('.user-profile-nav-item-cursor');
                    if (cursor) cursor.classList.add('user-profile-cursor-visible');
                }
                break;
            }
        }
    }

    function ensureStylesheetsFromParsedDoc(doc) {
        if (!doc) return;
        doc.querySelectorAll('link[rel="stylesheet"]').forEach(function(l) {
            var href = l.getAttribute('href');
            if (!href) return;
            var abs = new URL(href, window.location.origin).href;
            var dup = Array.prototype.some.call(document.querySelectorAll('link[rel="stylesheet"]'), function(x) {
                return x.href === abs;
            });
            if (dup) return;
            var nl = document.createElement('link');
            nl.rel = 'stylesheet';
            nl.href = href;
            document.head.appendChild(nl);
        });
    }

    function runParsedScriptsOutsideMain(doc) {
        if (!doc || !doc.body) return;
        var injectedSrc = window.__profileShellInjectedScripts || (window.__profileShellInjectedScripts = {});
        var injectedInline = window.__profileShellInjectedInlineScripts || (window.__profileShellInjectedInlineScripts = {});
        doc.querySelectorAll('script').forEach(function(oldScript) {
            if (oldScript.closest && oldScript.closest('#profilePlayerMain')) return;
            var s = document.createElement('script');
            if (oldScript.src) {
                try {
                    var abs = new URL(oldScript.src, window.location.origin).href;
                    if (injectedSrc[abs]) return;
                    injectedSrc[abs] = true;
                } catch (eSrc) {
                    return;
                }
                s.src = oldScript.src;
                s.async = false;
            } else {
                var inlineCode = oldScript.textContent || '';
                if (inlineCode.trim() === '') return;
                // Payment page markers must re-run on every SPA navigation
                var isPaymentBoot = /__PROFILE_PAYMENT_PAGE__|__PROFILE_PAYMENT_LIMITS__|__DEPOSIT_PANEL_SITE_BRAND__/.test(inlineCode);
                if (!isPaymentBoot) {
                    var inlineKey = fastStringHash(inlineCode);
                    if (injectedInline[inlineKey]) return;
                    injectedInline[inlineKey] = true;
                }
                s.textContent = inlineCode;
            }
            document.head.appendChild(s);
        });
    }

    function syncProfileAuxiliaryNodesFromParsedDoc(doc) {
        if (!doc || !doc.body) return;
        [
            'sporDetailsModal',
            'gameHistoryModal',
            'successWithdrawalPopup',
            'appFeedbackDialog'
        ].forEach(function(id) {
            var next = doc.getElementById(id);
            if (!next) return;
            var current = document.getElementById(id);
            if (current && current.parentNode) {
                current.parentNode.removeChild(current);
            }
            document.body.appendChild(document.importNode(next, true));
        });
    }

    /**
     * Sidebar yalnızca modal kabuğu ilk açıldığında render edilir; sonraki SPA sekme
     * geçişlerinde (details/bonus/mesajlar vb.) yalnızca #profilePlayerMain degisir ve
     * mevcut aside DOM'u aynen korunur (flicker/accordion state kaybi olmasin diye).
     * Ancak bu, ilk render sirasinda isim/soyisim verisi (first_name/surname) her ne
     * sebeple olursa olsun eksik geldiyse ? kullanıcı "Kişisel Detaylar" sekmesine gidip
     * orada doğru veriyi görse bile ? sidebar'in kalici olarak eski/boş veriyle takili
     * kalmasina yol açar. Bu yüzden her navigasyonda, en son çekilen HTML'in aside'indan
     * kimlik alanlarini (avatar bas harfi, kullanıcı adi, kullanıcı id)
     * canli sidebar'a senkronize ediyoruz ? yapisal DOM'u (accordion vb.) degistirmeden.
     */
    function syncProfileSidebarIdentityFromParsedAside(existingAside, newAside) {
        if (!existingAside || !newAside) return;
        var fields = [
            ['.avatar-holder', 'text'],
            ['.u-i-p-p-u-i-avatar-holder-bc', 'text'],
            ['.user-right .username', 'text'],
            ['.u-i-p-p-u-i-d-username-bc', 'text']
        ];
        fields.forEach(function(pair) {
            var selector = pair[0];
            var curEl = existingAside.querySelector(selector);
            var newEl = newAside.querySelector(selector);
            if (!curEl || !newEl) return;
            var newText = newEl.textContent || '';
            if (curEl.textContent !== newText) curEl.textContent = newText;
        });
        var curId = existingAside.querySelector('.user-right .user-id, .u-i-p-p-u-i-d-user-id-bc.user-id, .user-id');
        var newId = newAside.querySelector('.user-right .user-id, .u-i-p-p-u-i-d-user-id-bc.user-id, .user-id');
        if (curId && newId) {
            var newIdVal = newId.getAttribute('data-user-id') || '';
            if (curId.getAttribute('data-user-id') !== newIdVal) {
                curId.setAttribute('data-user-id', newIdVal);
                var curIdIcon = curId.querySelector('.copy-id-icon, .u-i-p-p-u-i-d-user-id-copy-bc');
                curId.textContent = newIdVal;
                if (curIdIcon) {
                    curId.appendChild(curIdIcon);
                } else {
                    var icon = document.createElement('i');
                    icon.className = 'u-i-p-p-u-i-d-user-id-copy-bc bc-i-copy';
                    icon.setAttribute('aria-hidden', 'true');
                    curId.appendChild(icon);
                }
            }
        }
    }

    function mergeProfileShellResponse(html, navSyncUrl, shellRoot) {
        if (!shellRoot) return false;
        var raw = String(html || '');
        /* Force UTF-8 for HTML fragments (modal=1 responses lack <head> charset). */
        if (raw.indexOf('<meta charset') === -1 && raw.indexOf('charset=') === -1) {
            raw = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' + raw + '</body></html>';
        }
        var doc = new DOMParser().parseFromString(raw, 'text/html');
        if (!doc || !doc.body) return false;
        var newAside = doc.getElementById('profilePlayerSidebar');
        var newMain = doc.getElementById('profilePlayerMain');
        if (!newMain) return false;

        ensureStylesheetsFromParsedDoc(doc);
        syncProfileAuxiliaryNodesFromParsedDoc(doc);
        runParsedScriptsOutsideMain(doc);

        var existingAside = shellRoot.querySelector('#profilePlayerSidebar');
        var existingMain = shellRoot.querySelector('#profilePlayerMain');

        if (existingAside && existingMain) {
            existingMain.replaceWith(document.importNode(newMain, true));
            if (newAside) syncProfileSidebarIdentityFromParsedAside(existingAside, newAside);
        } else {
            shellRoot.innerHTML = '';
            if (newAside) {
                shellRoot.appendChild(document.importNode(newAside, true));
            }
            shellRoot.appendChild(document.importNode(newMain, true));
        }

        var sidebarLive = shellRoot.querySelector('#profilePlayerSidebar')
            || shellRoot.querySelector('.u-i-profile-page-bc')
            || shellRoot.querySelector('#profilePlayerSidebar') || shellRoot.querySelector('.u-i-profile-page-bc');
        syncProfileModalSidebarFromUrl(sidebarLive, navSyncUrl);

        var mainLive = shellRoot.querySelector('#profilePlayerMain');
        if (mainLive) activateInlineScripts(mainLive);
        return true;
    }

    /** Prefetch için: tıklanan link hangi kabuga ait? (Gezinti tıklaması artık doğrudan kabuk köküne bağlı.) */
    function getProfileShellRootForLink(link) {
        if (!link || !link.closest) return null;
        var sidebar = link.closest('#profilePlayerSidebar');
        if (!sidebar) return null;
        var modal = document.getElementById('profileModalContent');
        if (modal && modal.contains(sidebar)) return modal;
        var wrap = sidebar.closest('.centerWrap.porfileWrap') || sidebar.closest('.porfileWrap');
        return wrap && wrap.contains(sidebar) ? wrap : null;
    }

    function shellRootIsFullPage(shellRoot) {
        return !!(shellRoot && shellRoot.classList && shellRoot.classList.contains('porfileWrap'));
    }

    /**
     * Sol menü SPA: dinleyici yalnızca kabuk kökünde (modal içerik veya tam sayfa wrap).
     * Document capture/bubble + getProfileShellRootForLink birleşimi yerine tek net kök.
     */
    function bindProfileShellNav(shellRoot) {
        if (!shellRoot || shellRoot.getAttribute('data-profile-shell-nav') === '1') return;
        shellRoot.setAttribute('data-profile-shell-nav', '1');
        var isFullPageShell = shellRootIsFullPage(shellRoot);
        shellRoot.addEventListener('click', function(e) {
            if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            if (!shellRoot.contains(e.target)) return;
            var link = e.target.closest && e.target.closest('a[href]');
            if (!link || !shellRoot.contains(link)) return;

            if (link.hasAttribute('data-profile-modal-close')) {
                e.preventDefault();
                if (profileModalApi.closeModal) profileModalApi.closeModal();
                return;
            }

            var isSidebarProfileLink = !!link.closest('#profilePlayerSidebar');
            var isCasinoHistoryFilterLink = !!link.closest('.casino-history-filter-tabs');
            var isMessagesMainNavLink = !!link.closest('#profilePlayerMain .profile-messages-body');
            var isFreespinTabLink = !!link.closest('#profilePlayerMain .freespin-tabs');
            if (!isSidebarProfileLink && !isCasinoHistoryFilterLink && !isMessagesMainNavLink && !isFreespinTabLink) return;
            if ((link.getAttribute('target') || '').toLowerCase() === '_blank') return;

            if (isSidebarProfileLink && link.classList.contains('accordion-trigger') && link.getAttribute('data-toggle-sub') != null) {
                var accItem = link.closest('.accordion-item');
                if (accItem && accItem.querySelector('.accordion-sub')) return;
            }
            if (isSidebarProfileLink && link.closest('.user-profile-nav-header')) return;

            var rawHref = (link.getAttribute('href') || '').trim();
            if (!rawHref || rawHref.charAt(0) === '#' || rawHref.indexOf('javascript:') === 0) return;

            var targetUrl;
            try {
                targetUrl = new URL(rawHref, window.location.origin);
            } catch (ex) {
                return;
            }
            if (targetUrl.origin !== window.location.origin) return;

            if (isFullPageShell) {
                if (!canFullPageProfileShellSpa(targetUrl.pathname)) return;
            } else {
                if (!canLoadInProfileModal(targetUrl.pathname)) return;
            }

            e.preventDefault();
            var modalUrl = toModalUrl(targetUrl.pathname + targetUrl.search + targetUrl.hash);
            var currentUrl = String(window.__profileModalContentUrl || '');
            if (currentUrl && normalizeCacheKey(currentUrl) === normalizeCacheKey(modalUrl)) {
                return;
            }
            var sidebarEl = shellRoot.querySelector('#profilePlayerSidebar');
            syncProfileModalSidebarFromUrl(sidebarEl, modalUrl);
            loadProfileShellContent(modalUrl, {
                shellRoot: shellRoot,
                isFullPage: isFullPageShell,
                loadingOverlay: null,
                skipPushState: false
            });
        }, false);
    }

    function prefetchProfileShellUrl(modalUrl) {
        var key = normalizeCacheKey(modalUrl);
        if (profileShellHtmlCache[key]) return;
        if (profileShellPrefetchPending[key]) return;
        profileShellPrefetchPending[key] = fetchProfilePage(modalUrl).then(function(html) {
            if (profileShellHtmlLooksComplete(html)) {
                profileShellHtmlCache[key] = html;
            }
        }).catch(function() {}).finally(function() {
            delete profileShellPrefetchPending[key];
        });
    }

    /** Sidebar'daki tüm SPA profil linklerini arka planda önbelleğe al (hover beklemeden tıklanınca hazır olsun). */
    function prefetchVisibleProfileSidebarLinks() {
        document.querySelectorAll('#profilePlayerSidebar a[href]').forEach(function(a) {
            var raw = (a.getAttribute('href') || '').trim();
            if (!raw || raw.charAt(0) === '#' || raw.indexOf('javascript:') === 0) return;
            if ((a.getAttribute('target') || '').toLowerCase() === '_blank') return;
            var u;
            try {
                u = new URL(raw, window.location.origin);
            } catch (err) {
                return;
            }
            if (u.origin !== window.location.origin) return;
            var inModal = !!(a.closest && a.closest('#profileModalContent'));
            var pageWrap = a.closest('.centerWrap.porfileWrap') || a.closest('.porfileWrap');
            var isFullPage = !!(!inModal && pageWrap && pageWrap.classList.contains('porfileWrap'));
            var path = u.pathname || '';
            if (isFullPage) {
                if (!canFullPageProfileShellSpa(path)) return;
            } else {
                if (!canLoadInProfileModal(path)) return;
            }
            prefetchProfileShellUrl(toModalUrl(u.pathname + u.search + u.hash));
        });
    }

    function schedulePrefetchAllProfileSidebarLinks(forceNow) {
        if (profileShellPrefetchDone || profileShellPrefetchQueued) return;
        profileShellPrefetchQueued = true;
        var runner = function() {
            profileShellPrefetchQueued = false;
            if (profileShellPrefetchDone) return;
            try {
                prefetchVisibleProfileSidebarLinks();
            } catch (ePrefetch) {}
            profileShellPrefetchDone = true;
        };
        if (forceNow === true) {
            runner();
            return;
        }
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(runner, { timeout: 300 });
            return;
        }
        setTimeout(runner, 80);
    }

    function runProfileShellInits(profileUrl) {
        initProfileSidebar();
        initProfileActiveBonus();
        bindCm622FormControls(document.getElementById('profilePlayerMain') || document.getElementById('profileModalContent') || document);
        initCm622MultiSelects(document.getElementById('profilePlayerMain') || document.getElementById('profileModalContent') || document);
        initDetailsPage();
        initTwoFactorToggle();
        if (document.getElementById('bonusClaimsRoot')) initBonusClaimsMe();
        if (document.getElementById('depositSection')
            || document.getElementById('withdrawSection')
            || document.getElementById('depositGrid')
            || document.getElementById('withdrawGrid')) {
            initDepositWithdrawPage(profileUrl);
        }
        if (document.getElementById('transactionTableBody')) initDepositWithdrawHistory();
        if (document.querySelector('[data-casino-history-root]')) initCasinoGameHistory();
        if (document.getElementById('sporDetailsContent') || document.getElementById('gameHistoryContent')) initBetHistory();
        if (document.getElementById('copyReferralCode')) initReferences();
        if (document.querySelector('[data-profile-promo-block]')) {
            loadProfilePromocodesSelect();
        }
        bindProfilePromoUi();
        refreshProfileBalanceVisibility();
        schedulePrefetchAllProfileSidebarLinks();
        try {
            if (profileUrl && profileUrl.indexOf('/profile/messages') !== -1 && window.MemberInboxBadges) {
                window.MemberInboxBadges.applyUnreadToDom(document);
                window.MemberInboxBadges.syncBadges();
            }
        } catch (eInbox) {}
    }

    function loadProfileShellContent(targetUrl, options) {
        options = options || {};
        var shellRoot = options.shellRoot;
        if (!shellRoot) return;

        var isFullPage = !!options.isFullPage;
        var loadingOverlay = options.loadingOverlay;
        var debounceMs = options.debounceMs != null ? options.debounceMs : 0;
        var skipPushState = !!options.skipPushState;

        var profileUrl = targetUrl;
        try {
            var parsedProfileUrl = new URL(profileUrl, window.location.origin);
            if (parsedProfileUrl.pathname === '/profile/sadakat-puanlari') {
                ensureFreshProfileCssOnce();
            }
        } catch (eProfileUrl) {}
        window.__profileModalContentUrl = profileUrl;

        var mainEl = shellRoot.querySelector('#profilePlayerMain');
        var cacheKey = normalizeCacheKey(profileUrl);
        var cachedHtml = profileShellHtmlCache[cacheKey];
        var loadPromise;
        var loadingTimer = null;

        function showOverlay() {
            if (loadingOverlay) {
                loadingOverlay.classList.remove('is-hidden');
                loadingOverlay.setAttribute('aria-hidden', 'false');
            }
        }
        function hideOverlay() {
            if (loadingTimer) {
                clearTimeout(loadingTimer);
                loadingTimer = null;
            }
            if (loadingOverlay) {
                loadingOverlay.classList.add('is-hidden');
                loadingOverlay.setAttribute('aria-hidden', 'true');
            }
        }
        function scheduleOverlay() {
            if (!loadingOverlay) return;
            if (loadingTimer) clearTimeout(loadingTimer);
            loadingTimer = setTimeout(showOverlay, debounceMs);
        }

        if (cachedHtml) {
            loadPromise = Promise.resolve(cachedHtml);
        } else {
            scheduleOverlay();
            if (profileShellFetchPending[cacheKey]) {
                loadPromise = profileShellFetchPending[cacheKey];
            } else {
                profileShellFetchPending[cacheKey] = fetchProfilePage(profileUrl)
                    .then(function(html) {
                        if (profileShellHtmlLooksComplete(html)) {
                            profileShellHtmlCache[cacheKey] = html;
                        }
                        return html;
                    })
                    .finally(function() {
                        delete profileShellFetchPending[cacheKey];
                    });
                loadPromise = profileShellFetchPending[cacheKey];
            }
        }

        loadPromise
            .then(function(html) {
                clearProfileAuthReloadGuard();
                window.__profileBilgiTitleBackup = undefined;
                var merged = false;
                try {
                    merged = mergeProfileShellResponse(html, profileUrl, shellRoot);
                } catch (eMerge) {
                    merged = false;
                }
                if (!merged) {
                    if (isFullPage) {
                        window.location.href = modalUrlToDisplayUrl(profileUrl);
                        return;
                    }
                    if (shellRoot.id === 'profileModalContent') {
                        shellRoot.innerHTML = html;
                        activateInlineScripts(shellRoot);
                        syncProfileModalSidebarFromUrl(
                            shellRoot.querySelector('#profilePlayerSidebar')
                                || shellRoot.querySelector('.u-i-profile-page-bc')
                                || shellRoot.querySelector('#profilePlayerSidebar') || shellRoot.querySelector('.u-i-profile-page-bc'),
                            profileUrl
                        );
                    }
                } else if (isFullPage && !skipPushState) {
                    history.pushState({ profileShell: 1 }, '', modalUrlToDisplayUrl(profileUrl));
                }
                runProfileShellInits(profileUrl);
                if (!isFullPage) {
                    schedulePrefetchAllProfileSidebarLinks(true);
                }
                if (isFullPage && mainEl) {
                    try { mainEl.scrollTop = 0; } catch (eS) {}
                }
            })
            .catch(function(err) {
                if (err && err.authRequired) {
                    if (shellRoot.id === 'profileModalContent') {
                        shellRoot.innerHTML = '<div style="padding:16px;color:#fff;">Oturum doğrulanıyor...</div>';
                    }
                    if (window.MaltabetToast) {
                        MaltabetToast.warning('Oturum bilgisi yenileniyor. Lütfen tekrar deneyin.', 'Oturum');
                    }
                    if (shouldAttemptProfileAuthReload()) {
                        setTimeout(function() {
                            window.location.reload();
                        }, 900);
                    } else {
                        if (window.history && typeof window.history.replaceState === 'function') {
                            window.history.replaceState({}, '', '/');
                        }
                        if (profileModalApi && typeof profileModalApi.closeModal === 'function') {
                            profileModalApi.closeModal();
                        }
                        if (window.MaltabetToast) {
                            MaltabetToast.error('Oturum yenileme döngüsü durduruldu. Lütfen tekrar giriş yapın.', 'Oturum');
                        }
                    }
                    return;
                }
                if (isFullPage) {
                    window.location.href = modalUrlToDisplayUrl(profileUrl);
                    return;
                }
                if (shellRoot.id === 'profileModalContent') {
                    shellRoot.innerHTML = '<div style="padding:16px;color:#fff;">Profil yüklenirken hata oluştu. Tam sayfaya yönlendiriliyor...</div>';
                    setTimeout(function() {
                        window.location.href = modalUrlToDisplayUrl(profileUrl);
                    }, 350);
                }
            })
            .finally(function() {
                hideOverlay();
            });
    }

    function prefetchIfProfileShellLink(link, shell) {
        if (!link || !shell) return;
        var raw = (link.getAttribute('href') || '').trim();
        if (!raw || raw.charAt(0) === '#' || raw.indexOf('javascript:') === 0) return;
        if ((link.getAttribute('target') || '').toLowerCase() === '_blank') return;
        try {
            var u = new URL(raw, window.location.origin);
            if (u.origin !== window.location.origin) return;
            var path = u.pathname || '';
            if (shell.classList.contains('porfileWrap')) {
                if (!canFullPageProfileShellSpa(path)) return;
            } else {
                if (!canLoadInProfileModal(path)) return;
            }
            prefetchProfileShellUrl(toModalUrl(u.pathname + u.search + u.hash));
        } catch (err) {}
    }

    function initProfileShellPrefetchOnce() {
        if (window.__profileShellPrefetchBound) return;
        window.__profileShellPrefetchBound = true;
        document.addEventListener('pointerenter', function(e) {
            var link = e.target && e.target.closest && e.target.closest('#profilePlayerSidebar a[href]');
            if (!link) return;
            var shell = getProfileShellRootForLink(link);
            if (!shell) return;
            prefetchIfProfileShellLink(link, shell);
        }, true);
    }

    function initFullPageProfileShellPopstateOnce() {
        if (window.__profileShellPopstateBound) return;
        window.__profileShellPopstateBound = true;
        window.addEventListener('popstate', function() {
            var wrap = document.querySelector('.centerWrap.porfileWrap');
            if (!wrap || !wrap.querySelector('#profilePlayerSidebar')) return;
            var path = window.location.pathname || '';
            if (!canFullPageProfileShellSpa(path)) return;
            var full = window.location.pathname + window.location.search + window.location.hash;
            loadProfileShellContent(toModalUrl(full), {
                shellRoot: wrap,
                isFullPage: true,
                debounceMs: 0,
                loadingOverlay: null,
                skipPushState: true
            });
        });
    }

    /** Bahis geçmişi yan alt menü URL'lerini arka planda önbelleğe al (tıklanınca aninda gelsin). */
    function prefetchAllBetHistorySidebarUrls() {
        if (window.__betHistorySidebarPrefetchRan) return;
        window.__betHistorySidebarPrefetchRan = true;
        var paths = [
            '/profile/bet-history',
            '/profile/bet-history?filter=acik',
            '/profile/bet-history?filter=nakde',
            '/profile/bet-history?filter=kazanc',
            '/profile/bet-history?filter=kayip',
            '/profile/bet-history?filter=iade',
            '/profile/bet-history?filter=kazanan-iade',
            '/profile/bet-history?filter=kayip-iade',
            '/profile/casino-history',
            '/profile/casino-history?source=slot',
            '/profile/casino-history?source=live_casino'
        ];
        paths.forEach(function(p) {
            prefetchProfileShellUrl(toModalUrl(p));
        });
    }

    function initProfileBetHistorySubmenuPrefetchOnce() {
        if (window.__profileBetHistorySubmenuPrefetchBound) return;
        window.__profileBetHistorySubmenuPrefetchBound = true;
        function maybePrefetchBetHistory(e) {
            var t = e.target;
            if (!t || !t.closest) return;
            var subBet = t.closest('.__removed_legacy_profile_sidebar .accordion-sub a[href*="/profile/bet-history"] .accordion-sub a[href*="/profile/casino-history"]');
            var trigBet = t.closest('.__removed_legacy_profile_sidebar a.accordion-trigger[href="/profile/bet-history"]');
            if (!subBet && !trigBet) return;
            prefetchAllBetHistorySidebarUrls();
        }
        document.addEventListener('pointerenter', maybePrefetchBetHistory, true);
        /* Mobil: dokunmada pointerenter olmayabilir; akordeon basligina tıklanınca önbellekle */
        document.addEventListener('click', function(e) {
            var trig = e.target.closest && e.target.closest('.__removed_legacy_profile_sidebar a.accordion-trigger[href="/profile/bet-history"]');
            if (!trig) return;
            prefetchAllBetHistorySidebarUrls();
        }, true);
    }

    /** Bahis geçmişi filtre formu (GET): tam sayfa/modal yenilemeden shell içinde yükle */
    function initProfileBetHistoryFormSpaOnce() {
        if (window.__profileBetHistoryFormSpaBound) return;
        window.__profileBetHistoryFormSpaBound = true;
        document.addEventListener('submit', function(e) {
            var form = e.target;
            if (!form || form.id !== 'betHistoryFilterForm') return;
            var modalContent = form.closest('#profileModalContent');
            var wrap = form.closest('.centerWrap.porfileWrap');
            var shellRoot = modalContent || wrap;
            if (!shellRoot) return;
            if (!modalContent && (!wrap || !wrap.querySelector('#profilePlayerSidebar'))) return;
            e.preventDefault();
            var params = new URLSearchParams(new FormData(form));
            params.set('modal', '1');
            var qs = params.toString();
            var modalUrl = '/profile/bet-history' + (qs ? '?' + qs : '');
            var sidebarLive = shellRoot.querySelector('#profilePlayerSidebar') || shellRoot.querySelector('.u-i-profile-page-bc');
            syncProfileModalSidebarFromUrl(sidebarLive, modalUrl);
            loadProfileShellContent(modalUrl, {
                shellRoot: shellRoot,
                isFullPage: !modalContent,
                loadingOverlay: null,
                skipPushState: !!modalContent
            });
        }, false);
    }

    /** Mesajlar yan alt menü (gelen / gönderildi / yeni) — arka planda önbellekle */
    function prefetchAllMessagesSidebarUrls() {
        if (window.__messagesSidebarPrefetchRan) return;
        window.__messagesSidebarPrefetchRan = true;
        var paths = [
            '/profile/messages',
            '/profile/messages?box=sent',
            '/profile/messages?box=new'
        ];
        paths.forEach(function(p) {
            prefetchProfileShellUrl(toModalUrl(p));
        });
    }

    function initProfileMessagesSubmenuPrefetchOnce() {
        if (window.__profileMessagesSubmenuPrefetchBound) return;
        window.__profileMessagesSubmenuPrefetchBound = true;
        document.addEventListener('pointerenter', function(e) {
            var t = e.target;
            if (!t || !t.closest) return;
            var subMsg = t.closest('.__removed_legacy_profile_sidebar .accordion-sub a[href*="/profile/messages"]');
            var trigMsg = t.closest('.__removed_legacy_profile_sidebar a.accordion-trigger[href="/profile/messages"]');
            if (!subMsg && !trigMsg) return;
            prefetchAllMessagesSidebarUrls();
        }, true);
        document.addEventListener('click', function(e) {
            var trig = e.target.closest && e.target.closest('.__removed_legacy_profile_sidebar a.accordion-trigger[href="/profile/messages"]');
            if (!trig) return;
            prefetchAllMessagesSidebarUrls();
        }, true);
    }

    /** Gelen kutusu: satır genişletme, okundu (localStorage) + rozet güncelleme */
    function initMemberInboxExpandOnce() {
        if (window.__memberInboxExpandBound) return;
        window.__memberInboxExpandBound = true;
        document.addEventListener('click', function (e) {
            var btn = e.target.closest && e.target.closest('.inbox-item-expand');
            if (!btn || !btn.closest('.personal-details-page--messages')) return;
            var item = btn.closest('.js-inbox-item');
            if (!item) return;
            e.preventDefault();
            var body = item.querySelector('.inbox-item-body');
            var open = !item.classList.contains('is-expanded');
            if (open) item.classList.add('is-expanded');
            else item.classList.remove('is-expanded');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (body) body.hidden = !open;
            if (open && window.MemberInboxBadges) {
                var id = item.getAttribute('data-inbox-id');
                var u = item.getAttribute('data-inbox-updated') || '';
                window.MemberInboxBadges.markRead(id, u);
                item.classList.remove('unread');
                window.MemberInboxBadges.syncBadges();
            }
        }, false);
    }

    /** Gelen kutusu tarih filtre formu (GET): shell içinde yenilemeden yükle */
    function initProfileMessagesInboxFilterFormSpaOnce() {
        if (window.__profileMessagesInboxFilterFormSpaBound) return;
        window.__profileMessagesInboxFilterFormSpaBound = true;
        document.addEventListener('submit', function(e) {
            var form = e.target;
            if (!form || form.id !== 'messagesInboxFilterForm') return;
            var modalContent = form.closest('#profileModalContent');
            var wrap = form.closest('.centerWrap.porfileWrap');
            var shellRoot = modalContent || wrap;
            if (!shellRoot) return;
            if (!modalContent && (!wrap || !wrap.querySelector('#profilePlayerSidebar'))) return;
            e.preventDefault();
            var params = new URLSearchParams(new FormData(form));
            params.set('modal', '1');
            var qs = params.toString();
            var modalUrl = '/profile/messages' + (qs ? '?' + qs : '');
            var sidebarLive = shellRoot.querySelector('#profilePlayerSidebar') || shellRoot.querySelector('.u-i-profile-page-bc');
            syncProfileModalSidebarFromUrl(sidebarLive, modalUrl);
            loadProfileShellContent(modalUrl, {
                shellRoot: shellRoot,
                isFullPage: !modalContent,
                loadingOverlay: null,
                skipPushState: !!modalContent
            });
        }, false);
    }

    /** Yeni mesaj formu: modal içinde tam sayfa navigasyonu engelle, AJAX ile gönder. */
    function initProfileMessagesNewFormSpaOnce() {
        if (window.__profileMessagesNewFormSpaBound) return;
        window.__profileMessagesNewFormSpaBound = true;

        function renderInlineFeedback(form, ok, message) {
            var box = form ? form.querySelector('.js-new-message-feedback') : null;
            if (!box) return;
            box.className = 'pm-alert js-new-message-feedback ' + (ok ? 'pm-alert--success' : 'pm-alert--error');
            box.textContent = message || (ok ? 'Mesaj gönderildi.' : 'Mesaj gönderilemedi.');
            box.hidden = false;
        }

        document.addEventListener('submit', function(e) {
            var form = e.target;
            if (!form || form.id !== 'newMessageForm') return;
            if (!form.closest('.personal-details-page--messages-new')) return;

            e.preventDefault();

            var submitBtn = form.querySelector('.new-msg-btn-send');
            if (submitBtn) submitBtn.disabled = true;

            var formData = new FormData(form);
            formData.set('ajax', '1');

            fetch(form.getAttribute('action') || '/profile/messages?box=new', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            })
                .then(function(r) {
                    return r.text().then(function(text) {
                        var data = null;
                        try { data = text ? JSON.parse(text) : null; } catch (eJson) {}
                        var ok = !!(r.ok && data && data.success);
                        var message = (data && data.message) ? data.message : (ok ? 'Mesajınız admine iletildi.' : 'Mesaj gönderilemedi.');
                        return { ok: ok, message: message, data: data };
                    });
                })
                .then(function(result) {
                    renderInlineFeedback(form, result.ok, result.message);
                    if (window.MaltabetToast) {
                        toastNotify(result.ok ? 'success' : 'error', result.message);
                    }
                    if (!result.ok) return;
                    form.reset();
                })
                .catch(function() {
                    var message = t('common.connection_error', 'Bağlantı hatası. Lütfen tekrar deneyin.');
                    renderInlineFeedback(form, false, message);
                    if (window.MaltabetToast) {
                        toastNotify('error', message);
                    }
                })
                .finally(function() {
                    if (submitBtn) submitBtn.disabled = false;
                });
        }, false);
    }

    /** Sadakat puanı kullanımı: form submit'i SPA içinde API'ye gönder, sayfa navigasyonunu engelle. */
    function initLoyaltyRedeemFormSpaOnce() {
        if (window.__profileLoyaltyRedeemFormSpaBound) return;
        window.__profileLoyaltyRedeemFormSpaBound = true;

        document.addEventListener('submit', function(e) {
            var form = e.target;
            if (!form || !form.classList || !form.classList.contains('lp-redeem-form')) return;
            if (!form.closest('.personal-details-page--loyalty-points')) return;

            e.preventDefault();

            var input = form.querySelector('input[name="redeem_points"]');
            var submitBtn = form.querySelector('.lp-redeem-btn');
            var points = parseInt((input && input.value) ? input.value : '0', 10);
            var redeemable = parseInt(form.getAttribute('data-redeemable-points') || '0', 10);
            if (isNaN(points)) points = 0;
            if (isNaN(redeemable)) redeemable = 0;

            if (points <= 0) {
                toastNotify('warning', 'Geçerli bir puan miktarı giriniz.');
                return;
            }
            if (points < 100) {
                toastNotify('warning', 'Minimum 100 puan kullanabilirsiniz.');
                return;
            }
            if (points % 100 !== 0) {
                toastNotify('warning', 'Puanlar 100 ve katlari seklinde kullanilabilir.');
                return;
            }
            if (redeemable > 0 && points > redeemable) {
                toastNotify('warning', 'Yetersiz puan. Kullanilabilir puaniniz: ' + redeemable + '.');
                return;
            }

            if (submitBtn) submitBtn.disabled = true;

            fetch(apiUrl('/api/v2/loyalty/redeem'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: memberAuthHeaders({
                    Accept: 'application/json',
                    'Content-Type': 'application/json'
                }),
                body: JSON.stringify({ points: points })
            })
                .then(function(r) {
                    return r.text().then(function(text) {
                        var data = null;
                        try { data = text ? JSON.parse(text) : null; } catch (eJson) {}
                        var ok = !!(r.ok && data && data.success);
                        var message = (data && data.message) ? data.message : (ok ? 'Puanlar basariyla kullanildi.' : 'Puan kullanimi basarisiz.');
                        return { ok: ok, message: message };
                    });
                })
                .then(function(result) {
                    if (!result.ok) {
                        toastNotify('error', result.message || 'Puan kullanimi basarisiz.');
                        return;
                    }

                    toastNotify('success', result.message || 'Puanlar basariyla kullanildi.');

                    var modalContent = form.closest('#profileModalContent');
                    var wrap = form.closest('.centerWrap.porfileWrap');
                    var shellRoot = modalContent || wrap;
                    if (!shellRoot) {
                        window.location.reload();
                        return;
                    }

                    var refreshUrl = window.__profileModalContentUrl || '/profile/sadakat-puanlari';
                    try {
                        var parsed = new URL(refreshUrl, window.location.origin);
                        if (parsed.pathname.indexOf('/profile/') !== 0) {
                            refreshUrl = '/profile/sadakat-puanlari';
                        }
                    } catch (eUrl) {
                        refreshUrl = '/profile/sadakat-puanlari';
                    }

                    if (refreshUrl.indexOf('modal=1') === -1) {
                        refreshUrl = toModalUrl(refreshUrl);
                    }
                    refreshUrl = appendQuery(refreshUrl, 'refresh=' + Date.now());

                    loadProfileShellContent(refreshUrl, {
                        shellRoot: shellRoot,
                        isFullPage: !modalContent,
                        loadingOverlay: modalContent ? document.getElementById('profileModalLoading') : null,
                        skipPushState: !!modalContent
                    });
                })
                .catch(function() {
                    toastNotify('error', t('common.connection_error', 'Bağlantı hatası. Lütfen tekrar deneyin.'));
                })
                .finally(function() {
                    if (submitBtn) submitBtn.disabled = false;
                });
        }, false);
    }

    // ----- 0. Header profil modali (iframe yok, HTML fetch ile) -----
    function initProfileModal() {
        if (isMobileSiteContext()) return;
        var overlay = document.getElementById('profileModalOverlay');
        var modal = document.getElementById('profileModal');
        var loadingEl = document.getElementById('profileModalLoading');
        var contentEl = document.getElementById('profileModalContent');
        var profileLink = document.getElementById('profileLinkModal');

        if (!overlay || !modal || !contentEl) return;

        bindProfileShellNav(contentEl);

        /** Modal hiç açılmadan önce (kullanıcı profil ikonuna tıklamadan) varsayılan
         * profil sekmesini arka planda ısıtır, böylece ilk açılış da önbellekten gelir. */
        function prefetchInitialProfileShellOnce() {
            if (window.__profileModalInitialPrefetched) return;
            var isLoggedIn = !!document.querySelector('.hdr-auth-user, .hdr-auth-user *');
            if (!isLoggedIn) return;
            window.__profileModalInitialPrefetched = true;
            var runner = function() {
                var currentPath = window.location.pathname || '';
                var candidate = canLoadInProfileModal(currentPath) ? (currentPath + (window.location.search || '')) : '/profile/details';
                prefetchProfileShellUrl(toModalUrl(candidate));
            };
            if (typeof window.requestIdleCallback === 'function') {
                window.requestIdleCallback(runner, { timeout: 1500 });
            } else {
                setTimeout(runner, 400);
            }
        }
        prefetchInitialProfileShellOnce();

        contentEl.addEventListener('click', function (e) {
            var t = e.target;
            var shellClose = t && t.closest ? t.closest('.personal-details-page--deposit-withdraw .personal-details-close') : null;
            if (!shellClose || !contentEl.contains(shellClose)) return;
            if (typeof window.closeVegaPanel === 'function') window.closeVegaPanel();
        });

        function openModal() {
            // Drop any stale SPA shell HTML from previous CM622 markup iterations
            try {
                Object.keys(profileShellHtmlCache).forEach(function (k) { delete profileShellHtmlCache[k]; });
            } catch (eCache) {}
            overlay.classList.add('is-open');
            modal.classList.add('is-open', 'is-web');
            overlay.setAttribute('aria-hidden', 'false');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            document.documentElement.classList.add('is-web');
            document.body.classList.add('is-web', 'profile-modal-body');
        }

        function closeModal() {
            if (document.activeElement && modal.contains(document.activeElement)) {
                document.activeElement.blur();
            }
            overlay.classList.remove('is-open');
            modal.classList.remove('is-open', 'is-web');
            overlay.setAttribute('aria-hidden', 'true');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            document.body.classList.remove('profile-modal-body');
        }

        var closeBtn = document.getElementById('close_popup_button_id');
        if (closeBtn && closeBtn.getAttribute('data-profile-close-bound') !== '1') {
            closeBtn.setAttribute('data-profile-close-bound', '1');
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                closeModal();
            });
        }

        profileModalApi.closeModal = closeModal;

        function getInitialProfileUrl() {
            var currentPath = window.location.pathname || '';
            var currentQuery = window.location.search || '';
            var candidate = currentPath + currentQuery;
            if (!canLoadInProfileModal(currentPath)) {
                candidate = '/profile/details';
            }
            return toModalUrl(candidate);
        }

        function openProfileModalFromHeader() {
            if (Shared.ensureSessionForPage && !Shared.ensureSessionForPage('/profile/details')) {
                return false;
            }
            hideProfileHeaderFlyouts();
            var nextUrl = getInitialProfileUrl();
            openModal();
            loadProfileShellContent(nextUrl, {
                shellRoot: contentEl,
                isFullPage: false,
                loadingOverlay: loadingEl
            });
            return true;
        }

        function openProfileModalUrl(rawUrl) {
            if (!rawUrl) return false;
            if (Shared.ensureSessionForPage && !Shared.ensureSessionForPage(rawUrl)) {
                return true;
            }
            var parsed;
            try {
                parsed = new URL(rawUrl, window.location.origin);
            } catch (err) {
                return false;
            }
            if (parsed.origin !== window.location.origin) {
                return false;
            }
            if (!canLoadInProfileModal(parsed.pathname)) {
                if (!isHomeBalanceHistoryQuery(parsed)) {
                    return false;
                }
                if (isMobileSiteContext()) {
                    return false;
                }
                parsed = new URL('/profile/deposit-withdraw-history', window.location.origin);
            }

            hideProfileHeaderFlyouts();
            openModal();
            loadProfileShellContent(toModalUrl(parsed.pathname + parsed.search + parsed.hash), {
                shellRoot: contentEl,
                isFullPage: false,
                loadingOverlay: loadingEl
            });
            return true;
        }

        function maybeOpenProfileModalFromHomeHistoryQuery() {
            if (isMobileSiteContext()) {
                return;
            }
            var current;
            try {
                current = new URL(window.location.href);
            } catch (eUrl) {
                return;
            }
            if (!isHomeBalanceHistoryQuery(current)) {
                return false;
            }
            if (window.__profileHistoryAutoOpened) return;
            window.__profileHistoryAutoOpened = true;
            if (window.history && typeof window.history.replaceState === 'function') {
                window.history.replaceState({}, '', '/');
            }
            openProfileModalUrl('/?profile=open&account=balance&page=history');
        }

        if (profileLink) {
            profileLink.addEventListener('click', function(e) {
                e.preventDefault();
                openProfileModalFromHeader();
            });
        }

        window.__openProfileModalInitial = openProfileModalFromHeader;
        window.__openProfileModalUrl = openProfileModalUrl;
        maybeOpenProfileModalFromHomeHistoryQuery();

        function headerModalTargetPath(a) {
            var d = (a.getAttribute('data-profile-modal-href') || '').trim();
            if (d) return d;
            return (a.getAttribute('href') || '').trim();
        }

        document.addEventListener('click', function(e) {
            if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            var link = e.target && e.target.closest ? e.target.closest('a[href], [data-profile-modal-href]') : null;
            if (!link || !isHeaderProfileModalEntryLink(link)) return;
            if ((link.getAttribute('target') || '').toLowerCase() === '_blank') return;

            var raw = headerModalTargetPath(link);
            if (!raw || raw === '#' || raw.indexOf('javascript:') === 0) return;

            var parsed;
            try {
                parsed = new URL(raw, window.location.origin);
            } catch (err) {
                return;
            }
            if (parsed.origin !== window.location.origin) return;
            if (!shouldOpenProfileModalForHeaderLink(link, parsed.pathname)) return;
            if (parsed.pathname === '/profile/bonus-spor' && typeof window.__openMobileBonusesPage === 'function' && window.__openMobileBonusesPage('bonus-request')) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return;
            }
            if (!openProfileModalUrl(raw)) return;

            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
        }, true);

        overlay.addEventListener('click', closeModal);
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
        });
    }

    /** Akordeon: tek document dinleyicisi ? modal her yüklendiginde çift toggle hatası olmasin. */
    function initProfileAccordionDelegationOnce() {
        if (window.__profileAccordionDelegationBound) return;
        window.__profileAccordionDelegationBound = true;
        document.addEventListener('click', function(e) {
            var cmHeader = e.target.closest('.user-profile-nav-header[data-toggle-sub]');
            if (cmHeader) {
                var nav = cmHeader.closest('.user-profile-nav');
                if (!nav) return;
                e.preventDefault();
                var willOpen = !nav.classList.contains('active');
                nav.classList.toggle('active', willOpen);
                nav.classList.toggle('open', willOpen);
                var arrow = cmHeader.querySelector('.user-profile-nav-arrow');
                if (arrow) {
                    arrow.classList.toggle('bc-i-small-arrow-up', willOpen);
                    arrow.classList.toggle('bc-i-small-arrow-down', !willOpen);
                }
                return;
            }
            var trigger = e.target.closest('.__removed_legacy_profile_sidebar a.accordion-trigger[data-toggle-sub]');
            if (!trigger) return;
            if (e.target.closest('.accordion-sub')) return;
            var item = trigger.closest('.accordion-item');
            if (!item || !item.querySelector('.accordion-sub')) return;
            e.preventDefault();
            item.classList.toggle('open');
            trigger.classList.toggle('open', item.classList.contains('open'));
        });
    }

    // ----- 1. Profil sidebar: bakiye, kullanıcı ID kopyala, akordeon -----
    function initProfileSidebar() {
        var content = document.querySelector('#profileModalContent .u-i-profile-page-content')
            || document.querySelector('#profilePlayerSidebar .u-i-profile-page-content')
            || document.querySelector('.u-i-profile-page-bc .u-i-profile-page-content');
        if (!content) return;

        if (content.querySelector('.u-i-p-amounts-bc.withdrawable')) {
            var syncedFromHeader = typeof window.__syncProfileSidebarBalancesFromHeaderDom === 'function'
                && window.__syncProfileSidebarBalancesFromHeaderDom();
            if (syncedFromHeader) {
                refreshProfileBalanceVisibility();
            }
            if (!syncedFromHeader) {
                fetchBalanceData()
                    .then(function(data) {
                        if (data.status !== 'success') return;
                        content.querySelectorAll('[data-cm622-balance="main"], .u-i-p-amounts-bc.withdrawable .u-i-p-a-amount-bc').forEach(function(mainEl) {
                            writeProfileBalanceEl(mainEl, formatTryAmount(data.ana_bakiye));
                        });
                        content.querySelectorAll('[data-cm622-balance="bonus"], .u-i-p-amounts-bc.bonuses .u-i-p-a-amount-bc, .bonus-info-section b').forEach(function(bonusEl) {
                            writeProfileBalanceEl(bonusEl, formatTryAmount(data.bonus_bakiye || data.toplam_bonus || '0'));
                        });
                        refreshProfileBalanceVisibility();
                    })
                    .catch(function() {});
            }
        }

        document.querySelectorAll('.u-i-profile-page-content .u-i-p-p-u-i-d-user-id-bc[data-user-id]').forEach(function(el) {
            if (el.getAttribute('data-profile-userid-bound') === '1') return;
            el.setAttribute('data-profile-userid-bound', '1');
            el.addEventListener('click', function() {
                var id = this.getAttribute('data-user-id');
                if (id && navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(id).then(function() {
                        toastNotify('success', 'Kullanıcı ID kopyalandı');
                    });
                }
            });
        });

        initProfileAccordionDelegationOnce();

        if (document.getElementById('profileModalPromoSelect')) {
            loadProfilePromocodesSelect();
        }
    }

    // ----- 2. Kisisel detaylar sayfasi: CM622 form -----
    function initDetailsPage() {
        var form = document.getElementById('personalDetailsForm');
        var submitBtn = document.getElementById('saveDetailsBtn');
        if (!form) return;

        function notifyProfileFormMessage(ok, message) {
            var msg = message || (ok ? 'Değişiklikler kaydedildi.' : 'Kayıt sırasında hata oluştu.');
            if (window.MaltabetToast) {
                toastNotify(ok ? 'success' : 'error', msg);
            } else if (typeof console !== 'undefined' && console.warn) {
                console.warn('[ProfileForm] ' + msg);
            }
        }

        function pad2(n) { return n < 10 ? '0' + n : String(n); }
        function ymdToDisplay(str) {
            var m = String(str || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
            if (!m) return '';
            return m[3] + '.' + m[2] + '.' + m[1];
        }
        function normalizeDateInput(value) {
            var v = (value || '').trim();
            if (!v) return '';
            var m = v.match(/^(\d{4}-\d{2}-\d{2})/);
            if (m && m[1]) return m[1];
            var dmy = v.match(/^(\d{2})[./](\d{2})[./](\d{4})$/);
            if (dmy) return dmy[3] + '-' + dmy[2] + '-' + dmy[1];
            var d = new Date(v);
            if (!isNaN(d.getTime())) {
                return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
            }
            return '';
        }
        function normalizeGenderLabel(value) {
            var g = (value || '').trim().toLowerCase();
            if (g === 'male' || g === 'm' || g === 'erkek') return 'Erkek';
            if (g === 'female' || g === 'f' || g === 'kadin' || g === 'kadın') return 'Kadın';
            if (g === 'other' || g === 'o' || g === 'diger' || g === 'diğer') return 'Diğer';
            return value || '';
        }
        function normalizeCountryLabel(value) {
            var c = String(value || '').trim();
            var u = c.toUpperCase();
            if (u === 'TR' || u === 'TUR' || u === 'TURKEY' || c.toLowerCase() === 'türkiye' || c.toLowerCase() === 'turkiye') {
                return 'Türkiye';
            }
            return c;
        }
        function setIfPresent(selector, value) {
            var el = form.querySelector(selector);
            if (!el) return;
            el.value = value == null ? '' : String(value);
            var wrap = el.closest('.form-control-bc');
            if (wrap) {
                var filled = String(el.value || '').trim() !== '';
                wrap.classList.toggle('filled', filled);
                wrap.classList.toggle('valid', filled);
            }
        }
        function setDobValue(ymd) {
            var hidden = form.querySelector('#dob');
            var display = form.querySelector('#dob_display');
            var control = form.querySelector('#profileDobControl');
            var next = normalizeDateInput(ymd);
            if (hidden) hidden.value = next;
            if (display) display.value = next ? ymdToDisplay(next) : '';
            if (control) {
                var filled = !!next;
                control.classList.toggle('filled', filled);
                control.classList.toggle('valid', filled);
            }
        }

        function initProfileDatepicker() {
            var holder = form.querySelector('.profile-dob-holder');
            var hidden = form.querySelector('#dob');
            var display = form.querySelector('#dob_display');
            var control = form.querySelector('#profileDobControl');
            var icon = form.querySelector('#profileDobIcon');
            var panel = form.querySelector('#profile_datepicker_panel');
            if (!holder || !hidden || !display || !panel || !icon) return;
            if (holder.getAttribute('data-dob-bound') === '1') return;
            holder.setAttribute('data-dob-bound', '1');

            var monthWrap = panel.querySelector('[data-dp-select="month"]');
            var yearWrap = panel.querySelector('[data-dp-select="year"]');
            var monthValueEl = panel.querySelector('[data-dp-month-value]');
            var yearValueEl = panel.querySelector('[data-dp-year-value]');
            var yearMenu = panel.querySelector('[data-dp-year-menu]');
            var daysEl = panel.querySelector('.profile-datepicker-days');
            var prevBtn = panel.querySelector('.profile-datepicker-prev');
            var cancelBtn = panel.querySelector('.profile-datepicker-cancel');
            var applyBtn = panel.querySelector('.profile-datepicker-apply');
            var monthNames = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
            var viewDate = new Date();
            var pendingSelected = null;

            function closeDpSelects(except) {
                panel.querySelectorAll('.profile-dp-select').forEach(function(wrap) {
                    if (except && wrap === except) return;
                    var trigger = wrap.querySelector('.profile-dp-select-trigger');
                    var menu = wrap.querySelector('.profile-dp-select-menu');
                    wrap.classList.remove('is-open');
                    if (trigger) trigger.setAttribute('aria-expanded', 'false');
                    if (menu) menu.setAttribute('hidden', '');
                });
            }
            function openDpSelect(wrap) {
                if (!wrap) return;
                var isOpen = wrap.classList.contains('is-open');
                closeDpSelects();
                if (isOpen) return;
                var trigger = wrap.querySelector('.profile-dp-select-trigger');
                var menu = wrap.querySelector('.profile-dp-select-menu');
                wrap.classList.add('is-open');
                if (trigger) trigger.setAttribute('aria-expanded', 'true');
                if (menu) {
                    menu.removeAttribute('hidden');
                    var active = menu.querySelector('.profile-dp-select-option.is-active');
                    if (active && typeof active.scrollIntoView === 'function') {
                        active.scrollIntoView({ block: 'nearest' });
                    }
                }
            }
            function syncMonthYearUi() {
                if (monthValueEl) monthValueEl.textContent = monthNames[viewDate.getMonth()] || '';
                if (yearValueEl) yearValueEl.textContent = String(viewDate.getFullYear());
                if (monthWrap) {
                    monthWrap.querySelectorAll('.profile-dp-select-option').forEach(function(opt) {
                        opt.classList.toggle('is-active', String(opt.getAttribute('data-value')) === String(viewDate.getMonth()));
                    });
                }
                if (yearMenu) {
                    yearMenu.querySelectorAll('.profile-dp-select-option').forEach(function(opt) {
                        opt.classList.toggle('is-active', String(opt.getAttribute('data-value')) === String(viewDate.getFullYear()));
                    });
                }
            }
            function fillYears() {
                if (!yearMenu || yearMenu.getAttribute('data-built') === '1') return;
                yearMenu.setAttribute('data-built', '1');
                var currentYear = new Date().getFullYear();
                var minYear = currentYear - 100;
                var maxYear = currentYear - 18;
                yearMenu.innerHTML = '';
                for (var y = maxYear; y >= minYear; y--) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'profile-dp-select-option';
                    btn.setAttribute('role', 'option');
                    btn.setAttribute('data-value', String(y));
                    btn.textContent = String(y);
                    yearMenu.appendChild(btn);
                }
            }
            function renderDays() {
                if (!daysEl) return;
                var year = viewDate.getFullYear();
                var month = viewDate.getMonth();
                var first = new Date(year, month, 1);
                var last = new Date(year, month + 1, 0);
                var startOffset = (first.getDay() + 6) % 7;
                daysEl.innerHTML = '';
                var i, cell;
                for (i = 0; i < startOffset; i++) {
                    cell = document.createElement('span');
                    cell.className = 'profile-datepicker-day other-month';
                    cell.setAttribute('aria-hidden', 'true');
                    daysEl.appendChild(cell);
                }
                for (var d = 1; d <= last.getDate(); d++) {
                    cell = document.createElement('button');
                    cell.type = 'button';
                    cell.className = 'profile-datepicker-day';
                    var dateStr = year + '-' + pad2(month + 1) + '-' + pad2(d);
                    cell.setAttribute('data-date', dateStr);
                    cell.textContent = String(d);
                    if (pendingSelected === dateStr) cell.classList.add('selected');
                    cell.addEventListener('click', function() {
                        var dt = this.getAttribute('data-date');
                        if (!dt) return;
                        pendingSelected = dt;
                        daysEl.querySelectorAll('.profile-datepicker-day.selected').forEach(function(el) {
                            el.classList.remove('selected');
                        });
                        this.classList.add('selected');
                    });
                    daysEl.appendChild(cell);
                }
            }
            function positionPanel() {
                var anchor = control || icon;
                var rect = anchor.getBoundingClientRect();
                var width = 280;
                var left = Math.min(rect.right - width, window.innerWidth - width - 8);
                if (left < 8) left = 8;
                panel.style.position = 'fixed';
                panel.style.top = Math.min(rect.bottom + 6, window.innerHeight - 340) + 'px';
                panel.style.left = left + 'px';
                panel.style.right = 'auto';
                panel.style.zIndex = '100200';
            }
            function openPanel() {
                fillYears();
                var val = hidden.value;
                if (val) {
                    var parts = val.split('-');
                    if (parts.length === 3) {
                        viewDate = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                        pendingSelected = val;
                    }
                } else {
                    var now = new Date();
                    viewDate = new Date(now.getFullYear() - 18, now.getMonth(), now.getDate());
                    pendingSelected = null;
                }
                syncMonthYearUi();
                renderDays();
                closeDpSelects();
                positionPanel();
                panel.removeAttribute('hidden');
                icon.setAttribute('aria-expanded', 'true');
                if (control) control.classList.add('focused');
            }
            function closePanel() {
                closeDpSelects();
                panel.setAttribute('hidden', '');
                icon.setAttribute('aria-expanded', 'false');
                if (control) control.classList.remove('focused');
                panel.style.position = '';
                panel.style.top = '';
                panel.style.left = '';
                panel.style.right = '';
                panel.style.zIndex = '';
            }
            function togglePanel() {
                if (panel.hasAttribute('hidden')) openPanel();
                else closePanel();
            }
            function applySelection() {
                if (pendingSelected) setDobValue(pendingSelected);
                closePanel();
            }

            display.addEventListener('click', function(e) {
                e.preventDefault();
                togglePanel();
            });
            icon.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                togglePanel();
            });
            icon.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    togglePanel();
                }
            });
            if (prevBtn) {
                prevBtn.addEventListener('click', function() {
                    viewDate.setMonth(viewDate.getMonth() - 1);
                    syncMonthYearUi();
                    renderDays();
                });
            }
            panel.querySelectorAll('.profile-dp-select-trigger').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openDpSelect(btn.closest('.profile-dp-select'));
                });
            });
            panel.addEventListener('click', function(e) {
                var opt = e.target && e.target.closest ? e.target.closest('.profile-dp-select-option') : null;
                if (!opt || !panel.contains(opt)) return;
                e.preventDefault();
                e.stopPropagation();
                var wrap = opt.closest('.profile-dp-select');
                var val = parseInt(opt.getAttribute('data-value'), 10);
                if (!wrap || isNaN(val)) return;
                if (wrap.getAttribute('data-dp-select') === 'month') {
                    viewDate.setMonth(val);
                } else {
                    viewDate.setFullYear(val);
                }
                syncMonthYearUi();
                renderDays();
                closeDpSelects();
            });
            if (cancelBtn) cancelBtn.addEventListener('click', closePanel);
            if (applyBtn) applyBtn.addEventListener('click', applySelection);
            document.addEventListener('click', function(e) {
                if (!panel.hasAttribute('hidden') && !holder.contains(e.target) && !panel.contains(e.target)) closePanel();
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !panel.hasAttribute('hidden')) closePanel();
            });
            setDobValue(hidden.value);
        }

        function initProfileCountrySelect() {
            var wrap = form.querySelector('.cm622-country-select[data-cm622-country]');
            if (!wrap || wrap.getAttribute('data-country-bound') === '1') return;
            wrap.setAttribute('data-country-bound', '1');
            var control = wrap.querySelector('.form-control-bc');
            var trigger = wrap.querySelector('.form-control-label-bc');
            var list = wrap.querySelector('.multi-select-label-bc');
            var optionsEl = wrap.querySelector('[data-country-options]');
            var display = wrap.querySelector('[data-country-display]');
            var flagEl = wrap.querySelector('[data-country-flag]');
            var hidden = wrap.querySelector('#country');
            var searchInput = wrap.querySelector('.cm622-country-search-input');
            if (!control || !trigger || !list || !optionsEl || !display || !hidden) return;

            var countryCodes = [
                'AF','AX','AL','DZ','AS','AD','AO','AI','AQ','AG','AR','AM','AW','AU','AT','AZ',
                'BS','BH','BD','BB','BY','BE','BZ','BJ','BM','BT','BO','BQ','BA','BW','BV','BR','IO','BN','BG','BF','BI',
                'CV','KH','CM','CA','KY','CF','TD','CL','CN','CX','CC','CO','KM','CG','CD','CK','CR','CI','HR','CU','CW','CY','CZ',
                'DK','DJ','DM','DO','EC','EG','SV','GQ','ER','EE','SZ','ET','FK','FO','FJ','FI','FR','GF','PF','TF',
                'GA','GM','GE','DE','GH','GI','GR','GL','GD','GP','GU','GT','GG','GN','GW','GY',
                'HT','HM','VA','HN','HK','HU','IS','IN','ID','IR','IQ','IE','IM','IL','IT','JM','JP','JE','JO',
                'KZ','KE','KI','KP','KR','KW','KG','LA','LV','LB','LS','LR','LY','LI','LT','LU',
                'MO','MG','MW','MY','MV','ML','MT','MH','MQ','MR','MU','YT','MX','FM','MD','MC','MN','ME','MS','MA','MZ','MM',
                'NA','NR','NP','NL','NC','NZ','NI','NE','NG','NU','NF','MK','MP','NO','OM',
                'PK','PW','PS','PA','PG','PY','PE','PH','PN','PL','PT','PR','QA','RE','RO','RU','RW',
                'BL','SH','KN','LC','MF','PM','VC','WS','SM','ST','SA','SN','RS','SC','SL','SG','SX','SK','SI','SB','SO','ZA','GS','SS','ES','LK','SD','SR','SJ','SE','CH','SY',
                'TW','TJ','TZ','TH','TL','TG','TK','TO','TT','TN','TR','TM','TC','TV',
                'UG','UA','AE','GB','US','UM','UY','UZ','VU','VE','VN','VG','VI','WF','EH','YE','ZM','ZW'
            ];
            var trNames = { TR: 'Türkiye', DE: 'Almanya', US: 'Amerika Birleşik Devletleri', GB: 'Birleşik Krallık', AZ: 'Azerbaycan', NL: 'Hollanda', FR: 'Fransa', IT: 'İtalya', ES: 'İspanya', RU: 'Rusya', CY: 'Kıbrıs' };

            function codeToName(code) {
                if (trNames[code]) return trNames[code];
                try {
                    if (typeof Intl !== 'undefined' && typeof Intl.DisplayNames === 'function') {
                        var dn = new Intl.DisplayNames(['tr'], { type: 'region' });
                        return dn.of(code) || code;
                    }
                } catch (e) {}
                return code;
            }
            function flagUrl(code) {
                return 'https://flagcdn.com/24x18/' + String(code || '').toLowerCase() + '.png';
            }
            function setOpen(open) {
                control.classList.toggle('expanded', open);
                trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) {
                    list.hidden = false;
                    list.removeAttribute('hidden');
                    if (searchInput) {
                        searchInput.value = '';
                        filterOptions('');
                        setTimeout(function() { searchInput.focus(); }, 0);
                    }
                } else {
                    list.hidden = true;
                    list.setAttribute('hidden', '');
                }
            }
            function applyCountry(code, label, silent) {
                var name = label || codeToName(code) || '';
                if (code === 'TR') name = 'Türkiye';
                hidden.value = name;
                display.textContent = name || 'Seçin';
                if (flagEl) {
                    if (code) {
                        flagEl.style.backgroundImage = 'url(' + flagUrl(code) + ')';
                        flagEl.style.backgroundSize = 'cover';
                        flagEl.style.backgroundPosition = 'center';
                        flagEl.setAttribute('data-code', code);
                    } else {
                        flagEl.style.backgroundImage = '';
                        flagEl.removeAttribute('data-code');
                    }
                }
                control.classList.toggle('filled', !!name);
                control.classList.toggle('valid', !!name);
                optionsEl.querySelectorAll('.checkbox-control-content-bc').forEach(function(item) {
                    var on = String(item.getAttribute('data-option-code') || '') === String(code || '');
                    item.classList.toggle('active', on);
                    item.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                if (!silent) setOpen(false);
            }
            function findCodeForLabel(label) {
                var want = normalizeCountryLabel(label);
                if (!want) return '';
                if (want === 'Türkiye') return 'TR';
                var i, code, name;
                for (i = 0; i < countryCodes.length; i++) {
                    code = countryCodes[i];
                    name = codeToName(code);
                    if (name === want || code === want.toUpperCase()) return code;
                }
                return '';
            }
            function filterOptions(q) {
                var query = String(q || '').trim().toLocaleLowerCase('tr');
                optionsEl.querySelectorAll('.checkbox-control-content-bc').forEach(function(item) {
                    var label = String(item.getAttribute('data-option-label') || '').toLocaleLowerCase('tr');
                    var code = String(item.getAttribute('data-option-code') || '').toLowerCase();
                    var show = !query || label.indexOf(query) !== -1 || code.indexOf(query) !== -1;
                    item.style.display = show ? '' : 'none';
                });
            }
            function buildOptions() {
                if (optionsEl.getAttribute('data-built') === '1') return;
                optionsEl.setAttribute('data-built', '1');
                var frag = document.createDocumentFragment();
                countryCodes.forEach(function(code) {
                    var name = codeToName(code);
                    var item = document.createElement('label');
                    item.className = 'checkbox-control-content-bc';
                    item.setAttribute('role', 'option');
                    item.setAttribute('aria-selected', 'false');
                    item.setAttribute('data-option-code', code);
                    item.setAttribute('data-option-label', name);
                    item.innerHTML = '<span class="cm622-country-option-flag" style="background-image:url(' + flagUrl(code) + ')"></span><p class="checkbox-control-text-bc ellipsis">' + name + '</p>';
                    item.addEventListener('click', function(e) {
                        e.preventDefault();
                        applyCountry(code, name, false);
                    });
                    frag.appendChild(item);
                });
                optionsEl.appendChild(frag);
            }

            buildOptions();
            wrap.__cm622CountrySetValue = function(label) {
                var normalized = normalizeCountryLabel(label);
                var code = findCodeForLabel(normalized) || (normalized === 'Türkiye' ? 'TR' : '');
                applyCountry(code, normalized || codeToName(code), true);
            };
            wrap.__cm622CountrySetValue(hidden.value || 'Türkiye');

            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                setOpen(!control.classList.contains('expanded'));
            });
            if (searchInput) {
                searchInput.addEventListener('click', function(e) { e.stopPropagation(); });
                searchInput.addEventListener('input', function() { filterOptions(searchInput.value); });
            }
            document.addEventListener('click', function(e) {
                if (control.classList.contains('expanded') && !wrap.contains(e.target)) setOpen(false);
            });
        }

        initProfileDatepicker();
        initProfileCountrySelect();

        function hydrateDetailsFromApi() {
            fetch(apiUrl('/api/v2/profile/detail'), {
                method: 'GET',
                credentials: 'same-origin',
                headers: memberAuthHeaders({ 'Accept': 'application/json' })
            })
                .then(function(r) {
                    return r.text().then(function(text) {
                        var data = null;
                        try { data = text ? JSON.parse(text) : null; } catch (eJson) {}
                        if (!r.ok) throw new Error((data && data.message) ? data.message : ('HTTP ' + r.status + ' hatası'));
                        if (!data || !data.success) throw new Error((data && data.message) ? data.message : 'Profil detayı alınamadı.');
                        return data;
                    });
                })
                .then(function(env) {
                    var root = (env && env.data) || {};
                    var user = root.user || {};
                    setIfPresent('#first_name', user.name || user.first_name || '');
                    setIfPresent('#surname', user.surname || user.last_name || '');
                    setIfPresent('#city', user.city || '');
                    setIfPresent('#address', user.address || '');
                    setDobValue(normalizeDateInput(user.dob || user.birth_date || ''));
                    var countryWrap = form.querySelector('.cm622-country-select[data-cm622-country]');
                    var countryVal = normalizeCountryLabel(user.country || '');
                    if (countryWrap && typeof countryWrap.__cm622CountrySetValue === 'function') {
                        countryWrap.__cm622CountrySetValue(countryVal);
                    } else {
                        setIfPresent('#country', countryVal);
                    }
                    var genderVal = normalizeGenderLabel(user.gender || '');
                    if (typeof window.setCm622MultiSelectValue === 'function') {
                        window.setCm622MultiSelectValue('gender', genderVal, true);
                    } else {
                        setIfPresent('#gender', genderVal);
                    }
                })
                .catch(function(err) {
                    if (typeof console !== 'undefined' && console.warn) {
                        console.warn('[ProfileDetail]', err && err.message ? err.message : err);
                    }
                });
        }

        hydrateDetailsFromApi();

        if (submitBtn && form.getAttribute('data-details-submit-bound') !== '1') {
            form.setAttribute('data-details-submit-bound', '1');
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                submitBtn.disabled = true;
                var payload = {
                    current_password: (form.querySelector('#current_password') || {}).value || '',
                    name: (form.querySelector('#first_name') || {}).value || '',
                    surname: (form.querySelector('#surname') || {}).value || '',
                    gender: (form.querySelector('#gender') || {}).value || '',
                    dob: (form.querySelector('#dob') || {}).value || '',
                    city: (form.querySelector('#city') || {}).value || '',
                    country: (form.querySelector('#country') || {}).value || '',
                    address: (form.querySelector('#address') || {}).value || ''
                };

                fetch(apiUrl('/api/v2/profile/update'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: memberAuthHeaders({ 'Content-Type': 'application/json', 'Accept': 'application/json' }),
                    body: JSON.stringify(payload)
                })
                    .then(function(r) {
                        return r.text().then(function(text) {
                            var data = null;
                            try { data = text ? JSON.parse(text) : null; } catch (eJson) {}
                            if (!r.ok) {
                                var msg = (data && data.message) ? data.message : ('HTTP ' + r.status + ' hatası');
                                throw new Error(msg);
                            }
                            if (!data || typeof data !== 'object') {
                                throw new Error('Geçersiz sunucu yanıtı.');
                            }
                            return data;
                        });
                    })
                    .then(function(data) {
                        if (data.success) {
                            notifyProfileFormMessage(true, data.message || 'Profil güncellendi.');
                            Object.keys(profileShellHtmlCache).forEach(function(key) {
                                if (key.indexOf('/profile/details') === 0 || key.indexOf('/profile/') === 0) {
                                    delete profileShellHtmlCache[key];
                                }
                            });
                            var modalContent = form.closest('#profileModalContent');
                            if (modalContent) {
                                loadProfileShellContent(toModalUrl('/profile/details?refresh=' + Date.now()), {
                                    shellRoot: modalContent,
                                    isFullPage: false,
                                    loadingOverlay: document.getElementById('profileModalLoading')
                                });
                            } else {
                                hydrateDetailsFromApi();
                            }
                        } else {
                            notifyProfileFormMessage(false, data.message);
                        }
                    })
                    .catch(function(err) {
                        notifyProfileFormMessage(false, (err && err.message) ? err.message : 'Sunucu hatası.');
                    })
                    .finally(function() { submitBtn.disabled = false; });
            });
        }
    }

    function initTwoFactorToggle() {
        var toggle = document.getElementById('twofaToggle');
        var statusEl = document.getElementById('twofa-status');
        if (!toggle || !statusEl || toggle.disabled || toggle.getAttribute('data-twofa-bound') === '1') return;
        toggle.setAttribute('data-twofa-bound', '1');

        function setStatus(on) {
            statusEl.textContent = on
                ? 'İki faktörlü kimlik doğrulama etkin.'
                : 'İki faktörlü kimlik doğrulama kapatıldı';
        }

        toggle.addEventListener('change', function() {
            var wantOn = toggle.checked;
            var prev = !wantOn;
            var fd = new FormData();
            fd.append('ajax', '1');
            fd.append('action', 'twofa_toggle');
            fd.append('enabled', wantOn ? '1' : '0');
            fd.append('csrf_token', toggle.getAttribute('data-csrf-token') || '');
            fetch(apiUrl('/api/v2/two-factor'), {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: memberAuthHeaders({ Accept: 'application/json' })
            })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data && data.success) {
                        setStatus(!!data.enabled);
                        toggle.checked = !!data.enabled;
                    } else {
                        toggle.checked = prev;
                    }
                })
                .catch(function() {
                    toggle.checked = prev;
                });
        });
    }

    // ----- 3. Profil / kisisel detaylar: Toastify, sifre degistirme -----
    function initAccountDetails() {
        var changePwdBtn = document.getElementById('changePwdBtn');
        if (!changePwdBtn) return;
        changePwdBtn.addEventListener('click', function() {
            var oldPwd = (document.getElementById('oldPwd') || {}).value.trim();
            var newPwd = (document.getElementById('newPwd') || {}).value.trim();
            var confirmPass = (document.getElementById('confirmPass') || {}).value.trim();
            if (newPwd !== confirmPass) {
                toastNotify('error', 'Yeni sifreler uyusmuyor!');
                return;
            }
            if (!oldPwd || !newPwd) {
                toastNotify('error', 'Lütfen tüm alanları doldurun!');
                return;
            }
            fetch(apiUrl('/api/v2/password-update'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: memberAuthHeaders({ 'Content-Type': 'application/json', 'Accept': 'application/json' }),
                body: JSON.stringify({
                    current_password: oldPwd,
                    password: newPwd,
                    password_confirmation: confirmPass
                })
            })
                .then(function(res) { return res.json(); })
                .then(function(env) {
                    if (env && env.success) {
                        var okMsg = (env.message && String(env.message).trim()) ? String(env.message).trim() : 'Şifreniz güncellendi.';
                        toastNotify('success', okMsg);
                        var f = document.getElementById('changePasswordForm');
                        if (f) f.reset();
                        if (env.data && env.data.redirect) {
                            window.location.href = env.data.redirect;
                        }
                    } else {
                        var err = (env && env.message) ? env.message : 'Şifre güncellenemedi.';
                        toastNotify('error', err);
                    }
                })
                .catch(function() {
                    toastNotify('error', 'Sunucu hatası. Lütfen tekrar deneyin.');
                });
        });
    }

    // ----- 4. Bonus talep listesi: initBonusClaimsMe (escapeHtml sonrasi) -----

    // ----- 5. Para yatır/çek (deposit-withdraw): Vega panel -----
    function getPaymentLimits() {
        return window.__PROFILE_PAYMENT_LIMITS__ || {};
    }
    var selectedPaymentMethod = '';
    var selectedPaymentMethodId = '';
    var selectedWithdrawMethod = '';
    var selectedProvider = '';
    /** Çekim talebi için backend payment_method_id (withdraw_payment.php) */
    var selectedWithdrawPaymentMethodId = '';
    /** Para yatır API çağrısı sürerken ikinci tıklamayı / çağrıyı engelle */
    var depositSubmitInFlight = false;
    /** Çekim talebi sürerken ikinci tiklamayi engelle (buton metni yarisindan kaçın) */
    var withdrawSubmitInFlight = false;
    var activeDepositStatusPoll = null;

    function openDefaultDepositPanel() {
        setTimeout(function() {
            var depSecGate = document.getElementById('depositSection');
            if (!depSecGate || depSecGate.classList.contains('is-cm622-pane-hidden') || depSecGate.style.display === 'none') return;
            var grid = document.getElementById('depositGrid');
            if (grid) {
                var match = grid.querySelector('.deposit-card[data-dw-label="Banka Havale"]');
                var cards = grid.querySelectorAll('.deposit-card[data-dw-method]');
                var pick = match || cards[0];
                if (!pick) return;
                grid.querySelectorAll('.deposit-card').forEach(function(c) {
                    c.classList.remove('is-selected', 'active');
                    c.setAttribute('aria-selected', 'false');
                });
                pick.classList.add('is-selected', 'active');
                pick.setAttribute('aria-selected', 'true');
                applyDepositInline(
                    pick.getAttribute('data-dw-method'),
                    pick.getAttribute('data-dw-provider'),
                    pick.getAttribute('data-dw-label')
                );
                return;
            }
            var sel = document.getElementById('depositMethodSelect');
            if (!sel) return;
            var matchOpt = Array.prototype.find.call(sel.options, function(o) {
                return (o.getAttribute('data-dw-label') || '') === 'Banka Havale';
            });
            var pickOpt = matchOpt || Array.prototype.find.call(sel.options, function(o) {
                return !o.hidden && o.getAttribute('data-dw-method');
            });
            if (pickOpt) {
                sel.value = pickOpt.value;
                applyDepositInlineFromSelect(sel);
            }
        }, 80);
    }

    function syncDepositWithdrawShellTitle(tabName) {
        var title = tabName === 'withdraw' ? 'PARA ÇEKİM' : 'PARA YATIR';
        var el = document.querySelector('#profilePlayerMain .overlay-header')
            || document.querySelector('.personal-details-page--withdraw-only .personal-details-title')
            || document.querySelector('.personal-details-page--deposit-withdraw .personal-details-title');
        if (!el) return;
        el.textContent = title;
    }

    function primeWithdrawInlineSelection(opts) {
        opts = opts || {};
        var refreshBalance = opts.refreshBalance !== false;
        var grid = document.getElementById('withdrawGrid');
        if (!grid) {
            var sel = document.getElementById('withdrawMethodSelect');
            if (sel) {
                var first = Array.prototype.find.call(sel.options, function(o) {
                    return !o.hidden && o.getAttribute('data-dw-method');
                });
                if (first) {
                    sel.value = first.value;
                    applyWithdrawInlineFromSelect(sel);
                }
            }
            if (refreshBalance) {
                fillWithdrawBalanceStats();
            }
            return;
        }
        var cards = grid.querySelectorAll('.deposit-card');
        var first = null;
        for (var i = 0; i < cards.length; i++) {
            var c = cards[i];
            if (c.style.display === 'none') continue;
            if (!c.getAttribute('data-dw-method')) continue;
            first = c;
            break;
        }
        if (!first) return;
        grid.querySelectorAll('.deposit-card').forEach(function(x) {
            x.classList.remove('is-selected', 'active');
            x.setAttribute('aria-selected', 'false');
        });
        first.classList.add('is-selected', 'active');
        first.setAttribute('aria-selected', 'true');
        applyWithdrawInline(
            first.getAttribute('data-dw-method'),
            first.getAttribute('data-dw-provider'),
            first.getAttribute('data-dw-label')
        );
        if (refreshBalance) {
            fillWithdrawBalanceStats();
        }
    }

    function resolveProfilePaymentPageKind(modalFullUrl) {
        var path = '';
        try {
            var src = modalFullUrl || window.__profileModalContentUrl || window.location.href;
            path = new URL(src, window.location.origin).pathname || '';
        } catch (ePath) {
            path = '';
        }
        if (path.indexOf('/profile/withdraw') === 0) return 'withdraw';
        if (path.indexOf('/profile/deposit') === 0 && path.indexOf('/profile/deposit-withdraw-history') !== 0) return 'deposit';
        var main = document.getElementById('profilePlayerMain');
        var attr = main && main.getAttribute('data-profile-payment-page');
        if (attr === 'withdraw' || attr === 'deposit') return attr;
        if (document.getElementById('withdrawSection') && !document.getElementById('depositSection')) return 'withdraw';
        if (document.getElementById('depositSection')) return 'deposit';
        return window.__PROFILE_PAYMENT_PAGE__ === 'withdraw' ? 'withdraw' : 'deposit';
    }

    function profilePaymentPageUrl(kind) {
        var modal = !!(document.getElementById('profileModal') && document.getElementById('profileModal').classList.contains('is-open'));
        var path = kind === 'withdraw' ? '/profile/withdraw' : '/profile/deposit';
        return path + (modal ? '?modal=1' : '');
    }

    function navigateProfilePaymentPage(kind) {
        var url = profilePaymentPageUrl(kind);
        if (typeof window.__openProfileModalUrl === 'function' && document.getElementById('profileModalContent')) {
            if (window.__openProfileModalUrl(url)) return;
        }
        window.location.href = url;
    }

    function showVegaTab(tabName, opts) {
        opts = opts || {};
        var depositSection = document.getElementById('depositSection');
        var withdrawSection = document.getElementById('withdrawSection');
        // Separate pages: never toggle deposit?withdraw in the same document
        if (tabName === 'withdraw' && !withdrawSection && depositSection) {
            navigateProfilePaymentPage('withdraw');
            return;
        }
        if (tabName === 'deposit' && !depositSection && withdrawSection) {
            navigateProfilePaymentPage('deposit');
            return;
        }
        function showCm622Pane(el) {
            if (!el) return;
            el.classList.remove('is-cm622-pane-hidden');
            el.style.removeProperty('display');
            el.removeAttribute('hidden');
        }
        if (tabName === 'deposit') {
            showCm622Pane(depositSection);
            syncDepositWithdrawShellTitle('deposit');
            if (opts.openDefaultDepositPanel) openDefaultDepositPanel();
        } else {
            if (typeof closeVegaPanel === 'function') closeVegaPanel();
            showCm622Pane(withdrawSection);
            syncDepositWithdrawShellTitle('withdraw');
            if (withdrawSection && !opts.skipWithdrawInlinePrime) primeWithdrawInlineSelection();
        }
        syncDepositWithdrawShellTitle(tabName);
    }

    function resolvePaymentLimits(type, method, provider) {
        var paymentLimits = getPaymentLimits();
        var limits;
        if (provider === 'megapayz') {
            limits = type === 'deposit'
                ? (paymentLimits.megapayz && paymentLimits.megapayz.deposit && paymentLimits.megapayz.deposit[method])
                : (paymentLimits.megapayz && paymentLimits.megapayz.withdraw && paymentLimits.megapayz.withdraw[method]);
        }
        if (!limits) limits = { min: 0, max: 999999 };
        return limits;
    }

    function applyDepositInline(method, provider, name) {
        selectedPaymentMethod = method;
        selectedProvider = provider;
        var selCard = document.querySelector('#depositGrid .deposit-card.is-selected');
        var pmid = selCard && (selCard.getAttribute('data-payment-method-id') || selCard.getAttribute('data-dw-api-id'));
        selectedPaymentMethodId = pmid ? String(pmid).trim() : '';
        var limits = resolvePaymentLimits('deposit', method, provider);
        var min = limits.min != null ? limits.min : 0;
        var max = limits.max != null ? limits.max : 999999;
        var summaryName = name;
        if (provider === 'megapayz' && method === 'crypto') {
            summaryName = 'XpayioCrypto';
        }
        var dM = document.getElementById('dInlineMethod');
        var dMin = document.getElementById('dInlineMin');
        var dMax = document.getElementById('dInlineMax');
        var amt = document.getElementById('inlineDepositAmount');
        var cryptoWrap = document.getElementById('depositCryptoTypeWrap');
        var depGridSel = document.getElementById('depositGrid');
        var depCard = depGridSel && depGridSel.querySelector('.deposit-card.is-selected');
        var procD = depCard && depCard.getAttribute('data-dw-processing');
        var dProc = document.getElementById('dInlineProcTime');
        if (dM) dM.textContent = summaryName;
        if (dMin) dMin.textContent = min.toLocaleString('tr-TR') + ' ₺';
        if (dMax) dMax.textContent = max.toLocaleString('tr-TR') + ' ₺';
        if (dProc) dProc.textContent = (procD && String(procD).trim()) ? String(procD).trim() : 'Anlik';
        if (cryptoWrap) cryptoWrap.style.display = (provider === 'megapayz' && method === 'crypto') ? '' : 'none';
        if (amt) {
            amt.min = String(min);
            amt.max = String(max);
            amt.step = '1';
        }
    }

    function applyDepositInlineFromSelect(sel) {
        var o = sel && sel.options ? sel.options[sel.selectedIndex] : null;
        if (!o || !o.getAttribute('data-dw-method')) return;
        applyDepositInline(
            o.getAttribute('data-dw-method'),
            o.getAttribute('data-dw-provider'),
            o.getAttribute('data-dw-label')
        );
    }

    function applyWithdrawInlineFromSelect(sel) {
        var o = sel && sel.options ? sel.options[sel.selectedIndex] : null;
        if (!o || !o.getAttribute('data-dw-method')) return;
        applyWithdrawInline(
            o.getAttribute('data-dw-method'),
            o.getAttribute('data-dw-provider'),
            o.getAttribute('data-dw-label')
        );
    }

    function cm622TextFieldHtml(id, title, attrs) {
        return '<div class="u-i-p-control-item-holder-bc">' +
            '<div class="form-control-bc default">' +
            '<label class="form-control-label-bc inputs">' +
            '<input type="text" class="form-control-input-bc" id="' + escapeHtml(id) + '" ' + (attrs || '') + ' autocomplete="off">' +
            '<i class="form-control-input-stroke-bc" aria-hidden="true"></i>' +
            '<span class="form-control-title-bc ellipsis">' + escapeHtml(title) + '</span>' +
            '</label></div></div>';
    }

    function withdrawRecipientFieldsHtml(method, provider, name) {
        if (provider === 'megapayz') {
            if (method === 'banktransfer') {
                return cm622TextFieldHtml('iban', 'IBAN', 'required maxlength="26" minlength="26"');
            }
            if (method === 'crypto') {
                return '<input type="hidden" id="crypto_network" value="' + escapeHtml(String(getWithdrawCryptoNetworkId(name))) + '">' +
                    cm622TextFieldHtml('crypto_address', 'Adres', 'required');
            }
            if (method === 'wallet' || method === 'papara') {
                return cm622TextFieldHtml('wallet_account', 'Hesap No', 'required pattern="[0-9]{10}" maxlength="10" minlength="10" inputmode="numeric"');
            }
            return '';
        }
        return '';
    }

    function withdrawAmountFieldHtml(limits, provider) {
        var min = limits.min != null ? limits.min : 0;
        var max = limits.max != null ? limits.max : 999999;
        var step = provider === 'megapayz' ? '10' : '1';
        return '<div class="u-i-p-control-item-holder-bc">' +
            '<div class="form-control-bc default">' +
            '<label class="form-control-label-bc inputs">' +
            '<input type="text" inputmode="decimal" class="form-control-input-bc" id="withdrawAmount" name="amount" min="' + min + '" max="' + max + '" step="' + step + '" required autocomplete="off">' +
            '<i class="form-control-input-stroke-bc" aria-hidden="true"></i>' +
            '<span class="form-control-title-bc ellipsis">Tutar</span>' +
            '</label></div></div>';
    }

    function buildWithdrawFieldsOnlyHtml(limits, method, provider, name) {
        return withdrawRecipientFieldsHtml(method, provider, name) + withdrawAmountFieldHtml(limits, provider);
    }

    function applyWithdrawInline(method, provider, name) {
        selectedPaymentMethod = method;
        selectedWithdrawMethod = method;
        selectedProvider = provider;
        var limits = resolvePaymentLimits('withdraw', method, provider);
        var min = limits.min != null ? limits.min : 0;
        var max = limits.max != null ? limits.max : 999999;
        var wM = document.getElementById('wInlineMethod');
        var wMin = document.getElementById('wInlineMin');
        var wMax = document.getElementById('wInlineMax');
        var fieldsEl = document.getElementById('withdrawInlineFields');
        var wGridSel = document.getElementById('withdrawGrid');
        var wCard = wGridSel && wGridSel.querySelector('.deposit-card.is-selected');
        var wPmid = wCard && (wCard.getAttribute('data-payment-method-id') || wCard.getAttribute('data-dw-api-id'));
        selectedWithdrawPaymentMethodId = wPmid ? String(wPmid).trim() : '';
        var procW = wCard && wCard.getAttribute('data-dw-processing');
        var wProcEl = document.getElementById('wInlineProcTime');
        if (wM) wM.textContent = name;
        if (wMin) wMin.textContent = min.toLocaleString('tr-TR') + ' ₺';
        if (wMax) wMax.textContent = max.toLocaleString('tr-TR') + ' ₺';
        if (wProcEl) wProcEl.textContent = (procW && String(procW).trim()) ? String(procW).trim() : 'Anlik';
        if (fieldsEl) {
            fieldsEl.innerHTML = buildWithdrawFieldsOnlyHtml(limits, method, provider, name);
            bindCm622FormControls(fieldsEl);
        }
    }

    function openVegaPanel(type, method, provider, name) {
        selectedPaymentMethod = method;
        selectedWithdrawMethod = method;
        selectedProvider = provider;
        if (type === 'deposit') {
            var selCard = document.querySelector('#depositGrid .deposit-card.is-selected');
            var pmid = selCard && (selCard.getAttribute('data-payment-method-id') || selCard.getAttribute('data-dw-api-id'));
            selectedPaymentMethodId = pmid ? String(pmid).trim() : '';
        } else {
            selectedPaymentMethodId = '';
            var wSelCard = document.querySelector('#withdrawGrid .deposit-card.is-selected');
            var wPid = wSelCard && (wSelCard.getAttribute('data-payment-method-id') || wSelCard.getAttribute('data-dw-api-id'));
            selectedWithdrawPaymentMethodId = wPid ? String(wPid).trim() : '';
        }
        var panel = document.getElementById('vegaPanel');
        var overlay = document.getElementById('vegaOverlay');
        var panelTitle = document.getElementById('panelTitle');
        var panelContent = document.getElementById('panelContent');
        if (!panel || !panelContent) return;
        if (panelTitle) {
            panelTitle.textContent = '';
        }
        var limits = resolvePaymentLimits(type, method, provider);
        if (panel) {
            panel.classList.remove('vega-panel--deposit', 'vega-panel--withdraw');
            panel.classList.add(type === 'deposit' ? 'vega-panel--deposit' : 'vega-panel--withdraw');
        }
        if (type === 'deposit') {
            panelContent.innerHTML = createDepositFormHtml(limits, method, provider, name);
        } else {
            panelContent.innerHTML = createWithdrawFormHtml(limits, method, provider, name);
            setTimeout(fillWithdrawBalanceStats, 50);
        }
        panel.classList.add('active');
        if (overlay) overlay.classList.add('active');
    }

    function closeVegaPanel() {
        stopDepositStatusPolling();
        var panel = document.getElementById('vegaPanel');
        var overlay = document.getElementById('vegaOverlay');
        if (panel) panel.classList.remove('active');
        if (overlay) overlay.classList.remove('active');
    }

    function stopDepositStatusPolling() {
        if (activeDepositStatusPoll && activeDepositStatusPoll.timer) {
            clearTimeout(activeDepositStatusPoll.timer);
        }
        activeDepositStatusPoll = null;
    }

    function depositStatusLabel(status) {
        var normalized = String(status || '').toLowerCase();
        var labels = {
            pending: 'Ödeme bekleniyor',
            processing: 'İşlem kontrol ediliyor',
            approved: 'Ödeme onaylandı',
            confirmed: 'Ödeme onaylandı',
            completed: 'Ödeme tamamlandi',
            success: 'Ödeme tamamlandi',
            rejected: 'Ödeme reddedildi',
            failed: 'Ödeme basarisiz oldu',
            cancelled: 'Ödeme iptal edildi'
        };

        return labels[normalized] || 'İşlem durumu güncelleniyor';
    }

    function depositStatusIsTerminal(status) {
        return ['approved', 'confirmed', 'completed', 'success', 'rejected', 'failed', 'cancelled'].indexOf(String(status || '').toLowerCase()) !== -1;
    }

    function updateDepositMonitorUi(status, message) {
        var statusEl = document.getElementById('vegaDepositStatusText');
        var hintEl = document.getElementById('vegaDepositStatusHint');
        if (statusEl) statusEl.textContent = depositStatusLabel(status);
        if (hintEl && message) hintEl.textContent = message;
    }

    function openDesktopDepositResultSurface() {
        stopDepositStatusPolling();
        fetchBalanceData(true).catch(function() {});
        if (typeof closeVegaPanel === 'function') closeVegaPanel();
        if (typeof window.__openProfileModalUrl === 'function' && window.__openProfileModalUrl('/?profile=open&account=balance&page=history')) {
            return;
        }
        window.location.href = '/?profile=open&account=balance&page=history';
    }

    function pollDesktopDepositStatus(trx, attempt) {
        attempt = attempt || 0;
        if (!trx) return;
        fetch(appendQuery(apiUrl('/api/v2/payment/status'), 'trx=' + encodeURIComponent(trx)), {
            credentials: 'same-origin',
            headers: memberAuthHeaders({ Accept: 'application/json' })
        })
            .then(function(response) { return response.json().catch(function() { return null; }); })
            .then(function(env) {
                if (!env || !env.success || !env.data) {
                    throw new Error('status-unavailable');
                }
                var status = String(env.data.status || '').toLowerCase();
                updateDepositMonitorUi(status, env.message || 'Ödeme onayi bekleniyor.');
                if (['approved', 'confirmed', 'completed', 'success'].indexOf(status) !== -1) {
                    updateDepositMonitorUi(status, 'Ödemeniz onaylandı. İşlem geçmişiniz açılıyor.');
                    setTimeout(openDesktopDepositResultSurface, 600);
                    return;
                }
                if (depositStatusIsTerminal(status)) {
                    stopDepositStatusPolling();
                    showAppFeedbackDialog({ type: 'error', title: 'Ödeme tamamlanamadı', message: depositStatusLabel(status) + '.' });
                    return;
                }
                if (!activeDepositStatusPoll || activeDepositStatusPoll.trx !== trx) return;
                activeDepositStatusPoll.timer = setTimeout(function() {
                    pollDesktopDepositStatus(trx, attempt + 1);
                }, attempt < 20 ? 2500 : 4000);
            })
            .catch(function() {
                if (!activeDepositStatusPoll || activeDepositStatusPoll.trx !== trx) return;
                activeDepositStatusPoll.timer = setTimeout(function() {
                    pollDesktopDepositStatus(trx, attempt + 1);
                }, 4000);
            });
    }

    function openDesktopDepositMonitor(paymentUrl, trx) {
        var payUrl = String(paymentUrl || '').trim();
        if (!payUrl) {
            return;
        }
        var panel = document.getElementById('vegaPanel');
        var overlay = document.getElementById('vegaOverlay');
        var panelContent = document.getElementById('panelContent');
        if (!panel || !panelContent) {
            // Some desktop layouts no longer render vegaPanel; fall back to direct navigation.
            window.location.href = payUrl;
            return;
        }
        stopDepositStatusPolling();
        panel.classList.remove('vega-panel--withdraw');
        panel.classList.add('vega-panel--deposit');
        panelContent.innerHTML = ''
            + '<div class="vega-deposit-sheet">'
            + '<div class="panel-info-table vega-deposit-summary">'
            + '<div class="panel-info-cell"><strong>Durum</strong><span id="vegaDepositStatusText">Ödeme bekleniyor</span></div>'
            + '<div class="panel-info-cell"><strong>Referans</strong><span>' + escapeHtml(trx) + '</span></div>'
            + '</div>'
            + '<div class="panel-instruction vega-deposit-welcome" id="vegaDepositStatusHint">Ödeme onaylandığında bu alan otomatik güncellenecek.</div>'
            + '<div style="margin-top:16px;border-radius:18px;overflow:hidden;background:#fff;box-shadow:0 10px 28px rgba(15,23,42,.08)">'
            + '<iframe src="' + escapeHtml(payUrl) + '" title="MegaPayz ödeme" style="display:block;width:100%;height:560px;border:0;background:#fff"></iframe>'
            + '</div>'
            + '</div>';
        panel.classList.add('active');
        if (overlay) overlay.classList.add('active');
        if (trx) {
            activeDepositStatusPoll = { trx: trx, timer: null };
            pollDesktopDepositStatus(trx, 0);
        }
    }

    function createDepositFormHtml(limits, method, provider, name) {
        var min = limits.min != null ? limits.min : 0;
        var max = limits.max != null ? limits.max : 999999;
        var brand = (typeof window.__DEPOSIT_PANEL_SITE_BRAND__ === 'string' && window.__DEPOSIT_PANEL_SITE_BRAND__.trim())
            ? window.__DEPOSIT_PANEL_SITE_BRAND__.trim()
            : 'MaltaBet';
        var summaryName = name;
        if (provider === 'megapayz' && method === 'crypto') {
            summaryName = 'XpayioCrypto';
        }
        var instruction = brand + ' Ailesine hoş geldiniz. İyi eğlenceler, bol şanslar dileriz. Para yatırmak için lütfen aşağıdaki tüm gerekli alanları doldurun. ' +
            'Minimum tutar altı yatırımlar <strong class="panel-instruction-warn">\'İADE EDİLMEZ\'</strong> lütfen kurallara uygun yatırım yapınız.';
        var formHtml = '<div class="vega-deposit-sheet">' +
            '<div class="panel-info-table vega-deposit-summary">' +
            '<div class="panel-info-cell"><strong>Ödeme Yöntemi</strong><span>' + escapeHtml(summaryName) + '</span></div>' +
            '<div class="panel-info-cell"><strong>Ücret</strong><span>Ücretsiz</span></div>' +
            '<div class="panel-info-cell"><strong>İşlem Süresi</strong><span>Anlık</span></div>' +
            '<div class="panel-info-cell"><strong>Min.</strong><span>' + min.toLocaleString('tr-TR') + ' ?</span></div>' +
            '<div class="panel-info-cell"><strong>Maks.</strong><span>' + max.toLocaleString('tr-TR') + ' ?</span></div></div>' +
            '<div class="panel-instruction vega-deposit-welcome">' + instruction + '</div>';
        if (provider === 'megapayz' && method === 'crypto') {
            formHtml += '<div class="form-group form-group--crypto-type"><label for="cryptoType">Kripto türü *</label>' +
                '<select id="cryptoType" class="vega-select">' +
                '<option value="tron" selected>TRON (TRC-20)</option><option value="bsc">BSC (BEP-20)</option><option value="eth">Ethereum (ERC-20)</option>' +
                '<option value="BTC">Bitcoin</option><option value="LTC">Litecoin</option><option value="USDT_TRON">USDT (TRC-20)</option></select></div>';
        }
        formHtml += '<div class="form-group"><label for="depositAmount">Tutar *</label>' +
            '<input type="number" id="depositAmount" placeholder="Tutar *" min="' + min + '" max="' + max + '" step="1"></div>' +
            '<div class="panel-actions"><button type="button" class="vega-deposit-submit" onclick="processVegaDeposit()">PARA YATIR</button></div></div>';
        return formHtml;
    }

    function escapeHtml(text) {
        var s = String(text == null ? '' : text);
        return s
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function bonusClaimCategoryLabel(cat) {
        var c = String(cat || '').toLowerCase().trim();
        var map = {
            slots: 'Slots',
            sports: 'Spor',
            sport: 'Spor',
            spor: 'Spor',
            live_casino: 'Canli casino',
            casino: 'Casino',
            loss_bonus: 'Kayip bonusu',
            vip: 'VIP'
        };
        if (map[c]) {
            return map[c];
        }
        if (!c) {
            return '—';
        }
        return String(cat).charAt(0).toUpperCase() + String(cat).slice(1);
    }

    function bonusClaimStatusLabel(status) {
        var s = String(status || '').toLowerCase();
        if (s === 'pending') return 'Beklemede';
        if (s === 'approved') return 'Onaylandi';
        if (s === 'rejected') return 'Reddedildi';
        return status ? String(status) : '—';
    }

    function bonusClaimStatusModifier(status) {
        var s = String(status || '').toLowerCase();
        if (s === 'pending' || s === 'approved' || s === 'rejected') {
            return s;
        }
        return '';
    }

    function initBonusClaimsMe() {
        var root = document.getElementById('bonusClaimsRoot');
        if (!root) return;
        var limitSel = document.getElementById('bonusClaimsLimit');
        var reloadBtn = document.getElementById('bonusClaimsReload');
        var statusEl = document.getElementById('bonusClaimsStatus');
        var loadingEl = document.getElementById('bonusClaimsLoading');
        var emptyEl = document.getElementById('bonusClaimsEmpty');
        var wrapEl = document.getElementById('bonusClaimsTableWrap');
        var tbody = document.getElementById('bonusClaimsTableBody');

        function setVisible(el, show) {
            if (!el) return;
            if (show) {
                el.hidden = false;
                el.classList.remove('is-hidden');
            } else {
                el.hidden = true;
                el.classList.add('is-hidden');
            }
        }

        function load() {
            if (!limitSel || !tbody) return;
            var lim = parseInt(String(limitSel.value || '20'), 10);
            if (isNaN(lim) || lim < 1) lim = 20;
            if (lim > 50) lim = 50;
            setVisible(loadingEl, true);
            setVisible(emptyEl, false);
            setVisible(wrapEl, false);
            if (statusEl) statusEl.textContent = '';
            tbody.innerHTML = '';

            var url = appendQuery(apiUrl('/api/v2/bonus-claims-me'), 'limit=' + encodeURIComponent(String(lim)));
            fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then(function(r) {
                    return r.json().then(function(data) { return { ok: r.ok, data: data, http: r.status }; });
                })
                .then(function(res) {
                    var data = res.data || {};
                    setVisible(loadingEl, false);
                    if (res.http === 401 || (data.code === 401)) {
                        if (statusEl) statusEl.textContent = data.message || 'Oturum süresi doldu; lütfen yeniden giriş yapın.';
                        setVisible(emptyEl, true);
                        return;
                    }
                    if (!data.success) {
                        if (statusEl) statusEl.textContent = data.message || 'Liste alinamadi.';
                        setVisible(emptyEl, true);
                        return;
                    }
                    var inner = data.data || {};
                    var claims = inner.claims;
                    if (!Array.isArray(claims)) claims = [];
                    if (claims.length === 0) {
                        setVisible(emptyEl, true);
                        return;
                    }
                    setVisible(wrapEl, true);
                    claims.forEach(function(row) {
                        var tr = document.createElement('tr');
                        var createdRaw = row.createdAt != null ? String(row.createdAt) : '';
                        var createdIso = createdRaw.replace(' ', 'T');
                        var td0 = document.createElement('td');
                        td0.textContent = formatProfileBonusDate(createdIso || createdRaw);
                        var td1 = document.createElement('td');
                        td1.textContent = (row.bonusName || '').trim() || '—';
                        var td2 = document.createElement('td');
                        td2.textContent = bonusClaimCategoryLabel(row.category);
                        var td3 = document.createElement('td');
                        td3.textContent = formatProfileMoneyPlain(row.requestedAmount) + ' ₺';
                        var td4 = document.createElement('td');
                        var wm = row.wageringMultiplierLabel;
                        if (wm == null || String(wm).trim() === '') {
                            var n = row.wageringMultiplier;
                            wm = n != null && String(n).trim() !== '' ? String(n) + 'x' : '—';
                        }
                        td4.textContent = String(wm);
                        var td5 = document.createElement('td');
                        var st = bonusClaimStatusModifier(row.status);
                        var badge = document.createElement('span');
                        badge.className = 'bcm-status' + (st ? ' bcm-status--' + st : '');
                        badge.textContent = bonusClaimStatusLabel(row.status);
                        td5.appendChild(badge);
                        var td6 = document.createElement('td');
                        td6.className = 'bcm-note';
                        var rr = row.rejectReason;
                        td6.textContent = rr != null && String(rr).trim() !== '' ? String(rr) : '—';
                        tr.appendChild(td0);
                        tr.appendChild(td1);
                        tr.appendChild(td2);
                        tr.appendChild(td3);
                        tr.appendChild(td4);
                        tr.appendChild(td5);
                        tr.appendChild(td6);
                        tbody.appendChild(tr);
                    });
                })
                .catch(function() {
                    setVisible(loadingEl, false);
                    if (statusEl) statusEl.textContent = t('common.connection_error', 'Bağlantı hatası.');
                    setVisible(emptyEl, true);
                });
        }

        if (reloadBtn) reloadBtn.addEventListener('click', load);
        if (limitSel) limitSel.addEventListener('change', load);
        load();
    }

    function profileDwFallbackLogoUrl(methodId, providerCode) {
        var p = String(providerCode || '').toLowerCase();
        var m = String(methodId || '').toLowerCase();
        var connections = window.__FRONTEND_CONNECTIONS__ || {};
        var logoBase = String(connections.megapayzLogoBaseUrl || '').replace(/\/+$/, '');
        if (!logoBase) logoBase = 'https://docs.megapayz.com/images';
        if (m === 'banktransfer') return logoBase + '/megahavale-min.png';
        if (m === 'creditcard') return logoBase + '/megakredikarti-min.png';
        if (m === 'wallet') return logoBase + '/megawallet-min.png';
        return logoBase + '/megakripto-min.png';
    }

    function dwDepositCategoryFromApiMethod(apiType, methodId) {
        var t = String(apiType || '').toLowerCase();
        var mid = String(methodId || '').toLowerCase();
        if (mid === 'papara') return 'papara';
        if (t === 'card' || mid === 'creditcard') return 'creditcard';
        if (t === 'crypto' || t === 'cryptocurrency') return 'crypto';
        if (t === 'e_wallet') return 'papara';
        if (t === 'bank_transfer' || t === 'bank' || t === 'banktransfer' || t === 'wire' || t === 'havale') return 'bank';
        if (t === 'qr') return 'qr';
        if (t === 'mobile') return 'mobile';
        if (t === 'papara') return 'papara';
        if (t === 'wallet' || mid === 'wallet') return 'mobile papara';
        if (t === 'other') return 'bank';
        return 'bank';
    }

    function dwWithdrawCategoryFromApiMethod(apiType, methodId) {
        var t = String(apiType || '').toLowerCase();
        var mid = String(methodId || '').toLowerCase();
        if (t === 'crypto' || mid === 'crypto') return 'crypto';
        if (mid === 'wallet' || mid === 'papara' || t === 'e_wallet') return 'papara';
        if (t === 'wallet' || t === 'papara' || t === 'mobile') return 'papara';
        return 'bank';
    }

    function mergeProfilePaymentLimitsFromApi(methods) {
        var base = getPaymentLimits() || {};
        var lim = JSON.parse(JSON.stringify(base));
        methods.forEach(function(m) {
            if (!m || typeof m !== 'object') return;
            var pcode = (m.provider && m.provider.code) ? String(m.provider.code).toLowerCase() : 'megapayz';
            var mid = m.method_id ? String(m.method_id) : 'wallet';
            var minA = m.min_amount != null ? Number(m.min_amount) : 0;
            var maxA = m.max_amount != null ? Number(m.max_amount) : 999999;
            var entry = { min: minA, max: maxA, name: m.name || mid };
            if (!lim[pcode]) lim[pcode] = { deposit: {}, withdraw: {} };
            if (!lim[pcode].deposit) lim[pcode].deposit = {};
            if (!lim[pcode].withdraw) lim[pcode].withdraw = {};
            if (m.deposit_enabled) lim[pcode].deposit[mid] = Object.assign({}, entry);
            if (m.withdrawal_enabled) lim[pcode].withdraw[mid] = Object.assign({}, entry);
        });
        window.__PROFILE_PAYMENT_LIMITS__ = lim;
    }

    function buildProfileDepositCardHtml(row, idx) {
        var logo = row.logo_url && String(row.logo_url).trim() !== ''
            ? row.logo_url
            : profileDwFallbackLogoUrl(row.method_id, (row.provider && row.provider.code) || '');
        var cat = dwDepositCategoryFromApiMethod(row.type, row.method_id);
        var prov = row.provider && row.provider.code ? String(row.provider.code) : 'megapayz';
        var mid = row.method_id ? String(row.method_id) : '';
        var label = row.name ? String(row.name) : mid;
        var idAttr = row.id != null ? String(row.id) : '';
        var sel = idx === 0 ? ' is-selected active' : '';
        var ari = idx === 0 ? 'true' : 'false';
        var slug = ('deposit_' + mid).toLowerCase().replace(/[^a-z0-9_-]+/g, '');
        return '<div class="m-nav-items-list-item-bc deposit-card ' + escapeHtml(slug) + sel + '" role="option" aria-selected="' + ari + '"' +
            ' data-category="' + escapeHtml(cat) + '"' +
            ' data-dw-method="' + escapeHtml(mid) + '"' +
            ' data-dw-provider="' + escapeHtml(prov) + '"' +
            ' data-dw-label="' + escapeHtml(label) + '"' +
            (idAttr ? ' data-dw-api-id="' + escapeHtml(idAttr) + '"' : '') +
            ' data-dw-processing="' + escapeHtml(row.processing_time != null && String(row.processing_time).trim() !== '' ? String(row.processing_time) : 'Anlik') + '">' +
            '<div class="nav-ico-w-row-bc">' +
            (logo ? '<img alt="' + escapeHtml(label) + '" loading="lazy" decoding="async" src="' + escapeHtml(logo) + '" class="payment-logo">' : '<span class="payment-logo payment-logo--text">' + escapeHtml(label) + '</span>') +
            '</div>' +
            '<div class="m-nav-list-item-title-bc"><span class="ellipsis" title="' + escapeHtml(label) + '">' + escapeHtml(label) + '</span></div>' +
            '</div>';
    }

    function buildProfileWithdrawCardHtml(row, idx) {
        var logo = row.logo_url && String(row.logo_url).trim() !== ''
            ? row.logo_url
            : profileDwFallbackLogoUrl(row.method_id, (row.provider && row.provider.code) || '');
        var wcat = dwWithdrawCategoryFromApiMethod(row.type, row.method_id);
        var prov = row.provider && row.provider.code ? String(row.provider.code) : 'megapayz';
        var mid = row.method_id ? String(row.method_id) : '';
        var label = row.name ? String(row.name) : mid;
        var idAttr = row.id != null ? String(row.id) : '';
        var sel = idx === 0 ? ' is-selected active' : '';
        var ari = idx === 0 ? 'true' : 'false';
        var slug = ('withdraw_' + mid).toLowerCase().replace(/[^a-z0-9_-]+/g, '');
        return '<div class="m-nav-items-list-item-bc deposit-card ' + escapeHtml(slug) + sel + '" role="option" aria-selected="' + ari + '"' +
            ' data-wcategory="' + escapeHtml(wcat) + '"' +
            ' data-dw-method="' + escapeHtml(mid) + '"' +
            ' data-dw-provider="' + escapeHtml(prov) + '"' +
            ' data-dw-label="' + escapeHtml(label) + '"' +
            (idAttr ? ' data-dw-api-id="' + escapeHtml(idAttr) + '"' : '') +
            ' data-dw-processing="' + escapeHtml(row.processing_time != null && String(row.processing_time).trim() !== '' ? String(row.processing_time) : 'Anlik') + '">' +
            '<div class="nav-ico-w-row-bc">' +
            (logo ? '<img alt="' + escapeHtml(label) + '" loading="lazy" decoding="async" src="' + escapeHtml(logo) + '" class="payment-logo">' : '<span class="payment-logo payment-logo--text">' + escapeHtml(label) + '</span>') +
            '</div>' +
            '<div class="m-nav-list-item-title-bc"><span class="ellipsis" title="' + escapeHtml(label) + '">' + escapeHtml(label) + '</span></div>' +
            '</div>';
    }

    function dwBilgiTileFromApiMethod(row) {
        var mid = String((row && row.method_id) || '').toLowerCase();
        var type = String((row && row.type) || '').toLowerCase();
        if (mid === 'wallet' || type === 'wallet') return 'WALLET';
        if (mid === 'banktransfer' || type === 'bank_transfer') return 'HAVALE';
        if (mid === 'creditcard' || type === 'card') return 'KREDI KARTI';
        if (mid === 'crypto' || type === 'crypto') return 'KRIPTO';
        return (row && row.name ? String(row.name) : mid).toUpperCase();
    }

    function buildProfileBilgiRowHtml(row) {
        var logo = row.logo_url && String(row.logo_url).trim() !== ''
            ? row.logo_url
            : profileDwFallbackLogoUrl(row.method_id, (row.provider && row.provider.code) || '');
        var name = row.name ? String(row.name) : String(row.method_id || '');
        var minA = row.min_amount != null && !isNaN(Number(row.min_amount)) ? amountFormatter.format(Number(row.min_amount)) + ' ₺' : '—';
        var maxA = row.max_amount != null && !isNaN(Number(row.max_amount)) ? amountFormatter.format(Number(row.max_amount)) + ' ₺' : '—';
        var processing = row.processing_time != null && String(row.processing_time).trim() !== '' ? String(row.processing_time) : 'Anlik';
        return '<div class="bilgi-row" role="row">' +
            '<div class="bilgi-tile" role="cell">' +
            '<img class="bilgi-tile-logo" src="' + escapeHtml(logo) + '" alt="" loading="lazy">' +
            '<span class="bilgi-tile-label">' + escapeHtml(dwBilgiTileFromApiMethod(row)) + '</span>' +
            '</div>' +
            '<div class="bilgi-cell-stack" role="cell"><span class="bilgi-cell-lbl">Ödeme Yöntemi</span><span class="bilgi-cell-val">' + escapeHtml(name) + '</span></div>' +
            '<div class="bilgi-cell-stack" role="cell"><span class="bilgi-cell-lbl">Ücret</span><span class="bilgi-cell-val bilgi-cell-val--muted">Ücretsiz</span></div>' +
            '<div class="bilgi-cell-stack" role="cell"><span class="bilgi-cell-lbl">İşlem Süresi</span><span class="bilgi-cell-val bilgi-cell-val--muted">' + escapeHtml(processing) + '</span></div>' +
            '<div class="bilgi-cell-stack" role="cell"><span class="bilgi-cell-lbl">Min.</span><span class="bilgi-cell-val">' + escapeHtml(minA) + '</span></div>' +
            '<div class="bilgi-cell-stack" role="cell"><span class="bilgi-cell-lbl">Maks.</span><span class="bilgi-cell-val">' + escapeHtml(maxA) + '</span></div>' +
            '</div>';
    }

    function renderProfileBilgiMethods(kind, list) {
        var marker = document.querySelector('[data-bilgi-method-list="' + kind + '"]');
        var table = marker ? marker.closest('.bilgi-table') : null;
        if (!table) return;
        if (!list || !list.length) {
            table.innerHTML = '<p class="dw-methods-empty" data-bilgi-method-list="' + kind + '" role="status">Listelenecek yöntem bulunmuyor.</p>';
            return;
        }
        table.innerHTML = list.map(buildProfileBilgiRowHtml).join('');
    }

    function normDwLabel(s) {
        return String(s || '').toLowerCase().replace(/\s+/g, ' ').trim();
    }

    function extractWithdrawPaymentMethodsFromData(data) {
        if (!data || typeof data !== 'object') return [];
        if (Array.isArray(data.methods)) return data.methods;
        if (data.megapayz_withdraw_form && Array.isArray(data.megapayz_withdraw_form.methods)) {
            return data.megapayz_withdraw_form.methods;
        }
        if (data.create_withdraw && Array.isArray(data.create_withdraw.methods)) {
            return data.create_withdraw.methods;
        }
        return [];
    }

    function withdrawPaymentMethodRowLabel(m) {
        if (!m || typeof m !== 'object') return '';
        return String(m.name || m.label || m.title || m.method_name || '').trim();
    }

    function withdrawPaymentMethodRowId(m) {
        if (m == null || typeof m !== 'object') return '';
        var id = m.id != null ? m.id : (m.payment_method_id != null ? m.payment_method_id : null);
        return id != null ? String(id).trim() : '';
    }

    function attachWithdrawPaymentIdsByLabel(methods) {
        var grid = document.getElementById('withdrawGrid');
        if (!grid || !methods || !methods.length) return;
        grid.querySelectorAll('.deposit-card[data-dw-method]').forEach(function(card) {
            if ((card.getAttribute('data-dw-api-id') || '').trim() !== '') return;
            if ((card.getAttribute('data-payment-method-id') || '').trim() !== '') return;
            var label = normDwLabel(card.getAttribute('data-dw-label'));
            if (!label) return;
            for (var i = 0; i < methods.length; i++) {
                var ml = normDwLabel(withdrawPaymentMethodRowLabel(methods[i]));
                if (!ml) continue;
                if (ml === label || label.indexOf(ml) !== -1 || ml.indexOf(label) !== -1) {
                    var pid = withdrawPaymentMethodRowId(methods[i]);
                    if (pid) card.setAttribute('data-dw-api-id', pid);
                    break;
                }
            }
        });
    }

    function enrichWithdrawGridFromWithdrawPaymentApi() {
        if (!document.getElementById('withdrawGrid')) return Promise.resolve();
        var cached = window.__WITHDRAW_PAYMENT_METHODS_ENRICH__;
        if (cached && cached.length) {
            attachWithdrawPaymentIdsByLabel(cached);
            return Promise.resolve();
        }
        return fetch(apiUrl('/api/v2/withdraw-payment'), {
            credentials: 'same-origin',
            headers: memberAuthHeaders({ Accept: 'application/json' })
        })
            .then(function(r) { return r.json().catch(function() { return null; }); })
            .then(function(env) {
                var methods = env && env.success && env.data ? extractWithdrawPaymentMethodsFromData(env.data) : [];
                if (methods.length) {
                    window.__WITHDRAW_PAYMENT_METHODS_ENRICH__ = methods;
                    attachWithdrawPaymentIdsByLabel(methods);
                }
            })
            .catch(function() {});
    }

    function applyProfilePaymentMethodsEnvelope(env) {
        if (!env || !env.success || !env.data || !Array.isArray(env.data.payment_methods)) {
            return false;
        }
        var methods = env.data.payment_methods;
        window.__PROFILE_PAYMENT_CURRENCY__ = env.data.currency || 'TRY';
        mergeProfilePaymentLimitsFromApi(methods);
        var dep = methods.filter(function(m) { return m.deposit_enabled; });
        var wdr = methods.filter(function(m) { return m.withdrawal_enabled; });
        renderProfileBilgiMethods('deposit', dep);
        renderProfileBilgiMethods('withdraw', wdr);
        var dGrid = document.getElementById('depositGrid');
        if (dGrid) {
            if (dep.length) {
                dGrid.innerHTML = dep.map(function(m, i) { return buildProfileDepositCardHtml(m, i); }).join('');
            } else {
                dGrid.innerHTML = '<p class="dw-methods-empty" role="status">Şu an para yatırma için listelenen yöntem bulunmuyor.</p>';
            }
        }
        var wGrid = document.getElementById('withdrawGrid');
        if (wGrid) {
            if (wdr.length) {
                wGrid.innerHTML = wdr.map(function(m, i) { return buildProfileWithdrawCardHtml(m, i); }).join('');
            } else {
                wGrid.innerHTML = '<p class="dw-methods-empty" role="status">Şu an para çekme için listelenen yöntem bulunmuyor.</p>';
            }
        }
        return true;
    }

    function loadProfilePaymentMethods() {
        var cached = window.__PROFILE_PAYMENT_METHODS_ENVELOPE__;
        if (cached) {
            return Promise.resolve(applyProfilePaymentMethodsEnvelope(cached));
        }
        return fetch(apiUrl('/api/v2/payment-methods'), { credentials: 'same-origin', headers: memberAuthHeaders({ Accept: 'application/json' }) })
            .then(function(r) { return r.json(); })
            .then(function(env) {
                var ok = applyProfilePaymentMethodsEnvelope(env);
                if (ok) {
                    window.__PROFILE_PAYMENT_METHODS_ENVELOPE__ = env;
                } else {
                    markProfilePaymentMethodsUnavailable();
                }
                return ok;
            })
            .catch(function() {
                markProfilePaymentMethodsUnavailable();
                return false;
            });
    }

    function markProfilePaymentMethodsUnavailable() {
        var msg = 'Ödeme yöntemleri yüklenemedi. Lütfen sayfayı yenileyin.';
        [['depositGrid', 'Şu an para yatırma için listelenen yöntem bulunmuyor.'],
         ['withdrawGrid', 'Şu an para çekme için listelenen yöntem bulunmuyor.']].forEach(function(pair) {
            var grid = document.getElementById(pair[0]);
            if (!grid) return;
            if (grid.querySelector('.deposit-card[data-dw-method]')) return;
            grid.innerHTML = '<p class="dw-methods-empty" role="status">' + (pair[1] || msg) + '</p>';
        });
    }

    function getWithdrawCryptoNetworkId(displayName) {
        var n = String(displayName || '').toUpperCase();
        if (n.indexOf('TRC20') !== -1 || n.indexOf('TRC-20') !== -1) return '65bd7be5964700005d002ae5';
        if (n.indexOf('TRON') !== -1 || n.indexOf('TRX') !== -1) return '65bd7be5964700005d002ae5';
        if (n.indexOf('BITCO') !== -1 || n.indexOf('BITCO') !== -1 || n.indexOf('BTC') !== -1) return '65bd7bba964700005d002ae1';
        if (n.indexOf('LTC') !== -1 || n.indexOf('LITE') !== -1) return '65bd7bc1964700005d002ae2';
        return '65bd7bd5964700005d002ae4';
    }

    function createWithdrawFormHtml(limits, method, provider, name) {
        var min = limits.min != null ? limits.min : 0;
        var max = limits.max != null ? limits.max : 999999;
        var brand = (typeof window.__DEPOSIT_PANEL_SITE_BRAND__ === 'string' && window.__DEPOSIT_PANEL_SITE_BRAND__.trim())
            ? window.__DEPOSIT_PANEL_SITE_BRAND__.trim()
            : 'MaltaBet';
        var instruction = brand + ' Ailesi olarak kazancınız adına sizleri tebrik eder ve bol şanslar dileriz. Para çekmek için lütfen aşağıdaki tüm gerekli alanları doldurun.';
        var formHtml = '<div class="vega-withdraw-sheet">' +
            '<div class="panel-info-table vega-withdraw-summary">' +
            '<div class="panel-info-cell"><strong>Ödeme Yöntemi</strong><span>' + escapeHtml(name) + '</span></div>' +
            '<div class="panel-info-cell"><strong>Ücret</strong><span>Ücretsiz</span></div>' +
            '<div class="panel-info-cell"><strong>İşlem Süresi</strong><span>Anlık</span></div>' +
            '<div class="panel-info-cell"><strong>Min.</strong><span>' + min.toLocaleString('tr-TR') + ' ?</span></div>' +
            '<div class="panel-info-cell"><strong>Maks.</strong><span>' + max.toLocaleString('tr-TR') + ' ?</span></div></div>' +
            '<div class="vega-withdraw-balance" id="withdrawBalanceStats">' +
            '<div class="vega-withdraw-balance-head">Çekilebilir Tutar</div>' +
            '<div class="vega-withdraw-balance-row"><span class="vega-withdraw-balance-label">Bakiye</span><span class="vega-withdraw-balance-value" id="wdrBalance">0,00 ?</span></div>' +
            '<div class="vega-withdraw-balance-row"><span class="vega-withdraw-balance-label">Oynanmamış Tutar Yüzdesi</span><span class="vega-withdraw-balance-value" id="wdrUnplayedPct">0%</span></div></div>' +
            '<div class="panel-instruction vega-withdraw-welcome">' + instruction + '</div>';
        formHtml += withdrawRecipientFieldsHtml(method, provider, name);
        formHtml += withdrawAmountFieldHtml(limits, provider) +
            '<div class="panel-actions"><button type="button" class="vega-withdraw-submit withdraw-btn" onclick="processVegaWithdrawal()">ÇEKİM YAP</button></div></div>';
        return formHtml;
    }

    var appFeedbackDialogBound = false;

    function isDesktopWebUi() {
        var html = document.documentElement;
        return html.classList.contains('is-web')
            && !html.classList.contains('is-mobile')
            && !(document.body && document.body.classList.contains('mobile-site'));
    }

    function appFeedbackIconHtml(type) {
        // Desktop: CM622 casino-popup SVG icons. Mobile keeps Font Awesome.
        if (isDesktopWebUi()) {
            if (type === 'warning') {
                return '<svg viewBox="0 0 57 57" aria-hidden="true"><path fill="#fdbc0c" d="M28.5 5.5L52.5 49H4.5L28.5 5.5z"/><path fill="#000b24" d="M26.2 22.5h4.6v14.2h-4.6zm0 17.8h4.6V45h-4.6z"/></svg>';
            }
            if (type === 'error') {
                return '<svg viewBox="0 0 57 57" aria-hidden="true"><circle cx="28.5" cy="28.5" r="24" fill="#e74c3c"/><path fill="#fff" d="M20.2 20.2l17.6 17.6m0-17.6L20.2 37.8" stroke="#fff" stroke-width="4" stroke-linecap="round"/></svg>';
            }
            return '<svg viewBox="0 0 57 57" aria-hidden="true"><circle cx="28.5" cy="28.5" r="24" fill="#3b82f6"/><path fill="#fff" d="M26.4 22.2h4.2v18.4h-4.2zm0-8.6h4.2V18h-4.2z"/></svg>';
        }
        var iconClass = type === 'error' ? 'fa-solid fa-circle-xmark'
            : type === 'warning' ? 'fa-solid fa-triangle-exclamation'
                : 'fa-solid fa-circle-info';
        return '<i class="' + iconClass + '" aria-hidden="true"></i>';
    }

    function initAppFeedbackDialog() {
        var overlay = document.getElementById('appFeedbackDialogOverlay');
        var dialog = document.getElementById('appFeedbackDialog');
        var btnOk = document.getElementById('appFeedbackDialogOk');
        var btnX = document.getElementById('appFeedbackDialogDismiss');
        if (!overlay || !dialog || appFeedbackDialogBound) {
            return;
        }
        appFeedbackDialogBound = true;
        function closeAppFeedbackDialog() {
            overlay.classList.remove('is-open');
            dialog.classList.remove('is-open');
            overlay.setAttribute('aria-hidden', 'true');
            dialog.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            dialog.classList.remove('app-feedback-dialog--error', 'app-feedback-dialog--warning', 'app-feedback-dialog--info', 'app-feedback-dialog--cm622');
        }
        window.__closeAppFeedbackDialog = closeAppFeedbackDialog;
        overlay.addEventListener('click', closeAppFeedbackDialog);
        if (btnOk) {
            btnOk.addEventListener('click', closeAppFeedbackDialog);
        }
        if (btnX) {
            btnX.addEventListener('click', closeAppFeedbackDialog);
        }
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') {
                return;
            }
            if (!dialog.classList.contains('is-open')) {
                return;
            }
            closeAppFeedbackDialog();
        });
    }

    function showAppFeedbackDialog(opts) {
        initAppFeedbackDialog();
        var overlay = document.getElementById('appFeedbackDialogOverlay');
        var dialog = document.getElementById('appFeedbackDialog');
        var titleEl = document.getElementById('appFeedbackDialogTitle');
        var msgEl = document.getElementById('appFeedbackDialogMessage');
        var iconWrap = document.getElementById('appFeedbackDialogIconWrap');
        if (!overlay || !dialog || !titleEl || !msgEl || !iconWrap) {
            var fallback = opts && opts.message ? String(opts.message) : 'Bir hata oluştu.';
            toastNotify((opts && opts.type) || 'info', fallback, opts && opts.title);
            return;
        }
        opts = opts || {};
        var type = opts.type || 'info';
        if (type !== 'error' && type !== 'warning' && type !== 'info') {
            type = 'info';
        }
        var title = opts.title;
        if (!title) {
            title = type === 'error' ? t('common.error', 'Hata') : type === 'warning' ? t('common.warning', 'Uyarı') : t('common.ok', 'Bilgi');
        }
        dialog.classList.remove('app-feedback-dialog--error', 'app-feedback-dialog--warning', 'app-feedback-dialog--info', 'app-feedback-dialog--cm622');
        dialog.classList.add('app-feedback-dialog--' + type);
        if (isDesktopWebUi()) {
            dialog.classList.add('app-feedback-dialog--cm622');
        }
        titleEl.textContent = title;
        msgEl.textContent = opts.message ? String(opts.message) : '';
        iconWrap.innerHTML = appFeedbackIconHtml(type);
        overlay.classList.add('is-open');
        dialog.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        dialog.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        var btnOk = document.getElementById('appFeedbackDialogOk');
        if (btnOk) {
            btnOk.focus();
        }
    }

    function submitDepositCore(fieldOpts) {
        if (depositSubmitInFlight) {
            return;
        }
        fieldOpts = fieldOpts || {};
        var amountId = fieldOpts.amountId || 'depositAmount';
        var amountEl = document.getElementById(amountId);
        var amount = amountEl ? parseFloat(amountEl.value) : NaN;
        var limits;
        var paymentLimits = getPaymentLimits();
        if (selectedProvider === 'megapayz') limits = paymentLimits.megapayz && paymentLimits.megapayz.deposit && paymentLimits.megapayz.deposit[selectedPaymentMethod];
        if (!limits) limits = DEFAULT_LIMITS;
        if (isNaN(amount) || amount <= 0) {
            showAppFeedbackDialog({ type: 'warning', message: 'Lütfen geçerli bir miktar girin.' });
            return;
        }
        if (amount < limits.min) {
            showAppFeedbackDialog({ type: 'warning', message: 'Minimum para yatirma tutari ' + limits.min.toLocaleString('tr-TR') + " TL'dir." });
            return;
        }
        if (amount > limits.max) {
            showAppFeedbackDialog({ type: 'warning', message: 'Maksimum para yatirma tutari ' + limits.max.toLocaleString('tr-TR') + " TL'dir." });
            return;
        }
        var payload = { amount: amount };
        if (selectedProvider === 'megapayz') {
            payload.method = selectedPaymentMethod;
            payload.provider = selectedProvider || 'megapayz';
        } else if (selectedPaymentMethodId) {
            payload.payment_method_id = selectedPaymentMethodId;
        } else {
            payload.method = selectedPaymentMethod;
            payload.provider = selectedProvider || 'megapayz';
        }
        depositSubmitInFlight = true;
        document.querySelectorAll('.vega-deposit-submit').forEach(function(b) {
            b.disabled = true;
            b.setAttribute('aria-busy', 'true');
            b.textContent = 'Isleniyor...';
        });
        fetch(apiUrl('/api/v2/deposit-payment'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: memberAuthHeaders({ 'Content-Type': 'application/json', Accept: 'application/json' }),
            body: JSON.stringify(payload)
        })
            .then(function(response) { return response.json().catch(function() { return null; }); })
            .then(function(data) {
                if (!data) {
                    showAppFeedbackDialog({ type: 'error', message: 'Beklenmeyen yanit. Lütfen tekrar deneyin.' });
                    return;
                }
                if (data.success && data.data && data.data.payment_url) {
                    var payUrl = String(data.data.payment_url).trim();
                    if (payUrl) {
                        var trx = data.data.trx ? String(data.data.trx).trim() : '';
                        openDesktopDepositMonitor(payUrl, trx);
                        return;
                    }
                    return;
                }
                var msg = (typeof data.message === 'string' && data.message) ? data.message : (data.error || 'İşlem tamamlanamadı.');
                showAppFeedbackDialog({ type: 'error', title: 'İşlem basarisiz', message: msg });
            })
            .catch(function() {
                showAppFeedbackDialog({ type: 'error', message: 'Bir hata oluştu. Lütfen tekrar deneyin.' });
            })
            .finally(function() {
                depositSubmitInFlight = false;
                document.querySelectorAll('.vega-deposit-submit').forEach(function(b) {
                    b.disabled = false;
                    b.removeAttribute('aria-busy');
                    b.textContent = 'PARA YATIR';
                });
            });
    }

    function processVegaDeposit() {
        submitDepositCore({});
    }

    function processInlineVegaDeposit() {
        submitDepositCore({ amountId: 'inlineDepositAmount' });
    }

    function processVegaWithdrawal() {
        if (withdrawSubmitInFlight) {
            return;
        }
        var wGrid = document.getElementById('withdrawGrid');
        var wCard = wGrid && wGrid.querySelector('.deposit-card.is-selected');
        var pmFromCard = wCard && (wCard.getAttribute('data-payment-method-id') || wCard.getAttribute('data-dw-api-id'));
        if (pmFromCard) selectedWithdrawPaymentMethodId = String(pmFromCard).trim();
        var amountEl = document.getElementById('withdrawAmount');
        var amount = amountEl ? parseFloat(amountEl.value) : NaN;
        var limits;
        var paymentLimits = getPaymentLimits();
        if (selectedProvider === 'megapayz') limits = paymentLimits.megapayz && paymentLimits.megapayz.withdraw && paymentLimits.megapayz.withdraw[selectedWithdrawMethod];
        if (!limits) limits = DEFAULT_LIMITS;
        if (isNaN(amount) || amount <= 0) { toastNotify('warning', 'Lütfen geçerli bir miktar girin.'); return; }
        if (amount < limits.min) { toastNotify('warning', 'Minimum para çekme tutarı ' + limits.min.toLocaleString('tr-TR') + " TL'dir."); return; }
        if (amount > limits.max) { toastNotify('warning', 'Maksimum para çekme tutarı ' + limits.max.toLocaleString('tr-TR') + " TL'dir."); return; }
        if (!selectedWithdrawPaymentMethodId) {
            toastNotify('error', 'Çekim yöntemi kimliği alınamadı. Sayfayı yenileyip tekrar deneyin.');
            return;
        }
        var payload = {
            amount: amount,
            payment_method_id: String(selectedWithdrawPaymentMethodId),
            lang: 'tr'
        };
        var inputFields = {};
        if (selectedProvider === 'megapayz') {
            if (selectedWithdrawMethod === 'banktransfer') {
                var ibanIn = document.getElementById('iban');
                var iban = ibanIn ? String(ibanIn.value || '').replace(/\s/g, '') : '';
                if (iban.length !== 26) { toastNotify('warning', 'Geçerli bir IBAN girin (26 karakter).'); return; }
                payload.account_number = iban;
            } else if (selectedWithdrawMethod === 'crypto') {
                var netEl = document.getElementById('crypto_network');
                var addrEl = document.getElementById('crypto_address');
                var addr = addrEl ? String(addrEl.value || '').trim() : '';
                if (!addr) { toastNotify('warning', 'Kripto adresi zorunludur.'); return; }
                payload.account_number = addr;
                if (netEl && netEl.value) {
                    inputFields.bank_id = netEl.value;
                    inputFields.crypto_network = netEl.value;
                }
            } else if (selectedWithdrawMethod === 'wallet') {
                var wall = document.getElementById('wallet_account');
                var wa = wall ? String(wall.value || '').trim() : '';
                if (!wa) { toastNotify('warning', 'Mega Wallet hesap numarasi zorunludur.'); return; }
                payload.account_number = wa;
            }
        }
        if (Object.keys(inputFields).length) payload.input_fields = inputFields;
        withdrawSubmitInFlight = true;
        document.querySelectorAll('.vega-withdraw-submit').forEach(function(b) {
            b.disabled = true;
            b.setAttribute('aria-busy', 'true');
            b.textContent = 'Isleniyor...';
        });
        function resetWithdrawSubmitUi() {
            withdrawSubmitInFlight = false;
            document.querySelectorAll('.vega-withdraw-submit').forEach(function(b) {
                b.disabled = false;
                b.removeAttribute('aria-busy');
                b.textContent = 'ÇEKİM YAP';
            });
        }
        fetch(apiUrl('/api/v2/withdraw-payment'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: memberAuthHeaders({ 'Content-Type': 'application/json', Accept: 'application/json' }),
            body: JSON.stringify(payload)
        })
            .then(function(response) { return response.json().catch(function() { return null; }); })
            .then(function(env) {
                if (!env) {
                    toastNotify('error', 'Beklenmeyen yanit. Lütfen tekrar deneyin.');
                    return;
                }
                if (env.success && env.data) {
                    var d = env.data;
                    if (d.payment_url && String(d.payment_url).trim() !== '') {
                        if (typeof closeVegaPanel === 'function') closeVegaPanel();
                        window.location.href = d.payment_url;
                        return;
                    }
                    if (typeof closeVegaPanel === 'function') closeVegaPanel();
                    var msg = typeof d.message === 'string' && d.message
                        ? d.message
                        : (env.message || 'Çekim talebiniz başarılı bir şekilde oluşturulmuştur.');
                    if (d.reference_code) msg += ' Ref: ' + d.reference_code;
                    var popup = document.getElementById('successWithdrawalPopup');
                    if (popup) {
                        // Modal içindeki !important hide kurallarını aşmak için body'ye taşı.
                        if (popup.parentNode !== document.body) {
                            document.body.appendChild(popup);
                        }
                        var msgEl = popup.querySelector('.popup-body p');
                        if (msgEl) msgEl.textContent = msg;
                        popup.classList.add('is-open');
                        popup.style.display = 'flex';
                    } else if (typeof showAppFeedbackDialog === 'function') {
                        showAppFeedbackDialog({ type: 'success', title: 'İşlem Başarılı!', message: msg });
                    } else {
                        toastNotify('success', msg, 'İşlem Başarılı!');
                    }
                    fetchBalanceData(true).catch(function() {});
                    return;
                }
                var errMsg = typeof env.message === 'string' && env.message ? env.message : 'İşlem tamamlanamadı.';
                toastNotify('error', errMsg, 'Hata');
            })
            .catch(function() { toastNotify('error', 'Bir hata oluştu. Lütfen tekrar deneyin.'); })
            .then(function() { resetWithdrawSubmitUi(); });
    }

    function fillWithdrawBalanceStats() {
        fetchBalanceData().then(function(data) {
            if (data.status !== 'success') return;
            var ana = parseFloat(data.ana_bakiye || 0);
            var unplayed = 0;
            var pct = (ana > 0 && unplayed > 0) ? ((unplayed / ana) * 100).toFixed(1) : '0';
            var balTxt = amountFormatter.format(ana) + ' ₺';
            var pctTxt = pct + '%';
            [['wdrBalance', balTxt], ['wdrBalanceInline', balTxt]].forEach(function(pair) {
                var node = document.getElementById(pair[0]);
                if (node) node.textContent = pair[1];
            });
            [['wdrUnplayedPct', pctTxt], ['wdrUnplayedPctInline', pctTxt]].forEach(function(pair) {
                var node = document.getElementById(pair[0]);
                if (node) node.textContent = pair[1];
            });
        }).catch(function() {});
    }

    function closeSuccessWithdrawalPopup() {
        var popup = document.getElementById('successWithdrawalPopup');
        if (popup) {
            popup.classList.remove('is-open');
            popup.style.display = 'none';
        }
        fetchBalanceData(true).then(function(data) {
            var balanceText = document.querySelector('.amount');
            if (data.status === 'success' && balanceText) balanceText.textContent = formatTryAmount(data.ana_bakiye);
        }).catch(function() {});
    }

    function profileBilgiTitleEl() {
        return document.querySelector('#profilePlayerMain .overlay-header')
            || document.querySelector('.personal-details-title');
    }

    function setPaymentSectionsHiddenForBilgi(hidden) {
        ['depositSection', 'withdrawSection'].forEach(function(id) {
            var el = document.getElementById(id);
            if (!el) return;
            if (hidden) {
                el.setAttribute('hidden', '');
            } else {
                el.removeAttribute('hidden');
            }
        });
    }

    function openBilgiModal(preferWithdraw) {
        var panel = document.getElementById('bilgiModal');
        if (!panel) return;
        var pageRoot = document.getElementById('profilePlayerMain')
            || panel.closest('.personal-details-page--deposit-withdraw')
            || panel.closest('.personal-details-page')
            || panel.closest('.my-profile-info-block');
        if (pageRoot) pageRoot.classList.add('is-bilgi-active');
        setPaymentSectionsHiddenForBilgi(true);
        panel.removeAttribute('hidden');
        panel.classList.add('is-bilgi-shown');
        panel.setAttribute('aria-hidden', 'false');
        var titleEl = profileBilgiTitleEl();
        if (titleEl) {
            if (typeof window.__profileBilgiTitleBackup !== 'string') {
                window.__profileBilgiTitleBackup = (titleEl.textContent || '').trim();
            }
            titleEl.textContent = 'BILGI';
        }
        var wantW = preferWithdraw;
        if (typeof wantW !== 'boolean') {
            wantW = resolveProfilePaymentPageKind(window.__profileModalContentUrl || window.location.href) === 'withdraw';
        }
        applyBilgiSubTab(wantW);
        syncProfileSidebarBilgiNav(true);
    }
    function closeBilgiModal() {
        var panel = document.getElementById('bilgiModal');
        if (panel) {
            panel.classList.remove('is-bilgi-shown');
            panel.setAttribute('hidden', '');
            panel.setAttribute('aria-hidden', 'true');
            document.querySelectorAll('#profilePlayerMain.is-bilgi-active, .personal-details-page.is-bilgi-active, .my-profile-info-block.is-bilgi-active').forEach(function(root) {
                root.classList.remove('is-bilgi-active');
            });
            setPaymentSectionsHiddenForBilgi(false);
            var titleEl = profileBilgiTitleEl();
            if (titleEl && typeof window.__profileBilgiTitleBackup === 'string' && window.__profileBilgiTitleBackup !== '') {
                titleEl.textContent = window.__profileBilgiTitleBackup;
            }
            window.__profileBilgiTitleBackup = undefined;
            var u = new URL(window.location.href);
            u.searchParams.delete('bilgi');
            if (u.hash === '#bilgi') u.hash = '';
            var q = u.searchParams.toString();
            history.replaceState(null, '', u.pathname + (q ? '?' + q : '') + u.hash);
            syncProfileSidebarBilgiNav(false);
        }
    }

    /** Sunucu hash görmediği için sadece #bilgi ile gelen isteklerde yan menü vurgusunu düzeltir. */
    function syncProfileSidebarBilgiNav(showBilgi) {
        var sidebar = document.querySelector('#profileModalContent #profilePlayerSidebar')
            || document.querySelector('#profilePlayerSidebar')
            || document.querySelector('.u-i-profile-page-bc')
           ;
        if (!sidebar) return;
        var pageKind = resolveProfilePaymentPageKind(window.__profileModalContentUrl || window.location.href);
        var items = sidebar.querySelectorAll('.accordion-sub li a, .user-profile-nav-item');
        var mainLink = null;
        var bilgiLink = null;
        for (var i = 0; i < items.length; i++) {
            var h = items[i].getAttribute('href') || '';
            if (h.indexOf('bilgi=1') !== -1) {
                if ((pageKind === 'withdraw' && h.indexOf('/profile/withdraw') !== -1)
                    || (pageKind !== 'withdraw' && h.indexOf('/profile/deposit') !== -1)) {
                    bilgiLink = items[i];
                }
                continue;
            }
            if (pageKind === 'withdraw' && h.indexOf('/profile/withdraw') !== -1 && h.indexOf('bilgi=') === -1) {
                mainLink = items[i];
            } else if (pageKind !== 'withdraw' && h.indexOf('/profile/deposit') !== -1
                && h.indexOf('deposit-withdraw-history') === -1 && h.indexOf('bilgi=') === -1) {
                mainLink = items[i];
            }
        }
        if (!bilgiLink || !mainLink) return;
        if (showBilgi) {
            mainLink.classList.remove('active');
            bilgiLink.classList.add('active');
        } else {
            bilgiLink.classList.remove('active');
            mainLink.classList.add('active');
        }
    }

    window.openVegaPanel = openVegaPanel;
    window.closeVegaPanel = closeVegaPanel;
    window.processVegaDeposit = processVegaDeposit;
    window.processInlineVegaDeposit = processInlineVegaDeposit;
    window.processVegaWithdrawal = processVegaWithdrawal;
    window.closeSuccessWithdrawalPopup = closeSuccessWithdrawalPopup;

    /** Birlesik bilgi panelinde (para yatir + Çekim sekmeleri) doğru listeyi seç. */
    function applyBilgiSubTab(preferWithdraw) {
        var panel = document.getElementById('bilgiModal');
        if (!panel) return;
        var tabs = panel.querySelectorAll('.bilgi-tab');
        if (!tabs.length) return;
        var wantW = !!preferWithdraw;
        tabs.forEach(function(tab) {
            var isW = tab.getAttribute('data-bilgi-tab') === 'withdraw';
            var on = wantW ? isW : !isW;
            tab.classList.toggle('active', on);
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panel.querySelectorAll('.bilgi-list').forEach(function(list) {
            list.classList.remove('bilgi-list-active');
        });
        var listEl = document.getElementById(wantW ? 'bilgiListWithdraw' : 'bilgiListDeposit');
        if (listEl) listEl.classList.add('bilgi-list-active');
    }

    function bindCm622FormControls(root) {
        var scope = root || document;
        scope.querySelectorAll('.form-control-bc .form-control-input-bc, .form-control-bc select.form-control-select-bc').forEach(function(input) {
            if (input.getAttribute('data-cm622-fc-bound') === '1') return;
            input.setAttribute('data-cm622-fc-bound', '1');
            var wrap = input.closest('.form-control-bc');
            if (!wrap) return;
            function sync() {
                var filled = String(input.value || '').trim() !== '';
                wrap.classList.toggle('filled', filled);
                wrap.classList.toggle('valid', filled);
            }
            input.addEventListener('focus', function() { wrap.classList.add('focused'); });
            input.addEventListener('blur', function() { wrap.classList.remove('focused'); sync(); });
            input.addEventListener('input', sync);
            input.addEventListener('change', sync);
            sync();
        });
    }

    var cm622MultiSelectDocCloseBound = false;
    function closeCm622MultiSelect(wrap) {
        if (!wrap) return;
        var control = wrap.querySelector('.form-control-bc');
        var trigger = wrap.querySelector('.form-control-label-bc');
        var list = wrap.querySelector('.multi-select-label-bc');
        if (control) control.classList.remove('expanded');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
        if (list) {
            list.hidden = true;
            list.setAttribute('hidden', '');
        }
    }
    function initCm622MultiSelects(root) {
        if (isMobileSiteContext()) return;
        var scope = root && root.querySelectorAll ? root : document;
        var nodes = scope.querySelectorAll
            ? scope.querySelectorAll('.multi-select-bc[data-cm622-ms]')
            : [];
        Array.prototype.forEach.call(nodes, function(wrap) {
            if (wrap.getAttribute('data-cm622-ms-bound') === '1') return;
            if (wrap.getAttribute('data-cm622-country') === '1') return;
            wrap.setAttribute('data-cm622-ms-bound', '1');
            var control = wrap.querySelector('.form-control-bc');
            var trigger = wrap.querySelector('.form-control-label-bc');
            var list = wrap.querySelector('.multi-select-label-bc');
            var display = wrap.querySelector('.form-control-select-bc');
            var hidden = wrap.querySelector('input[type="hidden"]');
            if (!control || !trigger || !list || !display || !hidden) return;

            function setOpen(open) {
                control.classList.toggle('expanded', open);
                trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) {
                    list.hidden = false;
                    list.removeAttribute('hidden');
                } else {
                    list.hidden = true;
                    list.setAttribute('hidden', '');
                }
            }
            function isOpen() {
                return control.classList.contains('expanded');
            }
            function applyValue(value, label, silent) {
                var next = value == null ? '' : String(value);
                var nextLabel = label != null ? String(label) : '';
                var matched = null;
                list.querySelectorAll('.checkbox-control-content-bc').forEach(function(item) {
                    var on = String(item.getAttribute('data-option-value') || '') === next;
                    item.classList.toggle('active', on);
                    item.setAttribute('aria-selected', on ? 'true' : 'false');
                    if (on) matched = item;
                });
                if (!nextLabel && matched) {
                    nextLabel = matched.getAttribute('data-option-label') || (matched.textContent || '').trim();
                }
                hidden.value = next;
                display.textContent = nextLabel;
                /* Empty option values ('' => Tümü) still keep floating label raised */
                var isFilled = !!matched || String(nextLabel || '').trim() !== '';
                control.classList.toggle('filled', isFilled);
                control.classList.toggle('valid', isFilled);
                if (!silent) {
                    try {
                        hidden.dispatchEvent(new Event('change', { bubbles: true }));
                        hidden.dispatchEvent(new Event('input', { bubbles: true }));
                    } catch (eDispatch) {}
                }
            }
            function selectOption(opt) {
                if (!opt) return;
                var value = opt.getAttribute('data-option-value');
                if (value == null) value = '';
                var label = opt.getAttribute('data-option-label') || (opt.textContent || '').trim();
                applyValue(value, label, false);
                setOpen(false);
            }
            wrap.__cm622MsSetValue = function(val, silent) {
                applyValue(val, null, !!silent);
            };
            /* Sync visible label from current hidden value (API/server prefill). */
            if (String(hidden.value || '').trim() !== '' || String(display.textContent || '').trim() !== '') {
                applyValue(hidden.value, null, true);
            }

            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                // close siblings first
                document.querySelectorAll('.multi-select-bc[data-cm622-ms] .form-control-bc.expanded').forEach(function(openCtrl) {
                    var openWrap = openCtrl.closest('.multi-select-bc');
                    if (openWrap && openWrap !== wrap) closeCm622MultiSelect(openWrap);
                });
                setOpen(!isOpen());
            });
            trigger.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    setOpen(!isOpen());
                } else if (e.key === 'Escape' && isOpen()) {
                    e.preventDefault();
                    setOpen(false);
                }
            });
            list.querySelectorAll('.checkbox-control-content-bc').forEach(function(opt) {
                opt.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    selectOption(opt);
                });
            });
        });

        if (!cm622MultiSelectDocCloseBound) {
            cm622MultiSelectDocCloseBound = true;
            document.addEventListener('click', function(e) {
                document.querySelectorAll('.multi-select-bc[data-cm622-ms] .form-control-bc.expanded').forEach(function(ctrl) {
                    var wrap = ctrl.closest('.multi-select-bc');
                    if (!wrap || wrap.contains(e.target)) return;
                    closeCm622MultiSelect(wrap);
                });
            });
        }
    }
    window.setCm622MultiSelectValue = function(inputOrName, value, silent) {
        var input = null;
        if (typeof inputOrName === 'string') {
            input = document.getElementById(inputOrName) || document.querySelector('[name="' + inputOrName + '"]');
        } else {
            input = inputOrName;
        }
        if (!input) return false;
        var root = input.closest ? input.closest('.multi-select-bc[data-cm622-ms]') : null;
        if (root && typeof root.__cm622MsSetValue === 'function') {
            root.__cm622MsSetValue(value, !!silent);
            return true;
        }
        input.value = value == null ? '' : String(value);
        return false;
    };

    /** @deprecated use initCm622MultiSelects */
    function initDepositCryptoMultiSelect(root) {
        initCm622MultiSelects(root);
    }

    function initDepositWithdrawPage(modalFullUrl) {
        if (isMobileSiteContext()) return;
        var shell = document.getElementById('depositSection')
            || document.getElementById('withdrawSection')
            || document.getElementById('depositGrid')
            || document.getElementById('withdrawGrid');
        if (!shell) return;
        // Undo any prior display:block that breaks CM622 flex panes
        [document.getElementById('depositSection'), document.getElementById('withdrawSection')].forEach(function(el) {
            if (el && el.style.display === 'block') el.style.removeProperty('display');
        });
        bindCm622FormControls(document.getElementById('profilePlayerMain') || shell);
        initCm622MultiSelects(document.getElementById('profilePlayerMain') || shell);

        function continueInitDepositWithdrawPage() {
        var pageKind = resolveProfilePaymentPageKind(modalFullUrl);
        window.__PROFILE_PAYMENT_PAGE__ = pageKind;
        var hash = '';
        var search = window.location.search;
        if (typeof modalFullUrl === 'string' && modalFullUrl) {
            try {
                var pu = new URL(modalFullUrl, window.location.origin);
                hash = (pu.hash || '').trim();
                search = pu.search || '';
            } catch (ePu) {}
        } else {
            hash = (window.location.hash || '').trim();
        }
        var params = new URLSearchParams(search);
        var bilgiFromQs = params.get('bilgi') === '1';
        var wantBilgi = hash === '#bilgi' || bilgiFromQs;
        // Separate pages: never flip deposit?withdraw via hash/tab on the same document
        if (pageKind === 'deposit' && hash === '#withdraw') {
            navigateProfilePaymentPage('withdraw');
            return;
        }
        if (pageKind === 'withdraw' && hash === '#deposit') {
            navigateProfilePaymentPage('deposit');
            return;
        }
        if (pageKind === 'withdraw') {
            if (wantBilgi) {
                openBilgiModal(true);
            } else {
                showVegaTab('withdraw', { openDefaultDepositPanel: false, skipWithdrawInlinePrime: false });
            }
        } else if (wantBilgi) {
            openBilgiModal(false);
        } else {
            var qsOpenDeposit = params.get('openDepositPanel') === '1';
            showVegaTab('deposit', {
                openDefaultDepositPanel: qsOpenDeposit,
                skipWithdrawInlinePrime: false
            });
        }
        if (wantBilgi && hash === '#bilgi' && !bilgiFromQs) {
            syncProfileSidebarBilgiNav(true);
        }
        if (!window.__profileDepositWithdrawHashBound) {
            window.__profileDepositWithdrawHashBound = true;
            window.addEventListener('hashchange', function() {
                if (!document.getElementById('depositSection')
                    && !document.getElementById('withdrawSection')
                    && !document.getElementById('depositGrid')
                    && !document.getElementById('withdrawGrid')) return;
                var h = (window.location.hash || '').trim();
                var pk = resolveProfilePaymentPageKind(window.__profileModalContentUrl || window.location.href);
                window.__PROFILE_PAYMENT_PAGE__ = pk;
                var p = new URLSearchParams(window.location.search);
                var wBilgi = h === '#bilgi' || p.get('bilgi') === '1';
                if (pk === 'withdraw') {
                    if (h === '#deposit') {
                        navigateProfilePaymentPage('deposit');
                        return;
                    }
                    if (wBilgi) {
                        openBilgiModal(true);
                        syncProfileSidebarBilgiNav(true);
                    } else {
                        closeBilgiModal();
                    }
                    return;
                }
                if (h === '#withdraw') {
                    navigateProfilePaymentPage('withdraw');
                    return;
                }
                if (wBilgi) {
                    openBilgiModal(false);
                    syncProfileSidebarBilgiNav(true);
                } else {
                    closeBilgiModal();
                    var qsOpen = new URLSearchParams(window.location.search).get('openDepositPanel') === '1';
                    if (h === '#deposit') showVegaTab('deposit', { openDefaultDepositPanel: qsOpen });
                }
            });
        }
        document.querySelectorAll('.bilgi-tab').forEach(function(tab) {
            if (tab.getAttribute('data-dw-tab-bound') === '1') return;
            tab.setAttribute('data-dw-tab-bound', '1');
            tab.addEventListener('click', function() {
                var t = this.getAttribute('data-bilgi-tab');
                applyBilgiSubTab(t === 'withdraw');
            });
        });
        document.querySelectorAll('.deposit-tab').forEach(function(tab) {
            if (tab.getAttribute('data-dw-tab-bound') === '1') return;
            tab.setAttribute('data-dw-tab-bound', '1');
            tab.addEventListener('click', function() {
                document.querySelectorAll('.deposit-tab').forEach(function(t) {
                    t.classList.remove('active');
                    t.setAttribute('aria-selected', 'false');
                });
                this.classList.add('active');
                this.setAttribute('aria-selected', 'true');
                var cat = this.getAttribute('data-category');
                var depGrid = document.getElementById('depositGrid');
                if (depGrid) {
                    depGrid.querySelectorAll('.deposit-card').forEach(function(card) {
                        var raw = (card.getAttribute('data-category') || '').trim();
                        var cats = raw ? raw.split(/\s+/) : [];
                        var show = cat === 'all' || cats.indexOf(cat) !== -1;
                        card.style.display = show ? '' : 'none';
                    });
                    var cur = depGrid.querySelector('.deposit-card.is-selected');
                    if (!cur || cur.style.display === 'none') {
                        var firstVis = Array.prototype.find.call(depGrid.querySelectorAll('.deposit-card'), function(c) {
                            return c.style.display !== 'none' && c.getAttribute('data-dw-method');
                        });
                        if (firstVis) {
                            depGrid.querySelectorAll('.deposit-card').forEach(function(c) {
                                c.classList.remove('is-selected', 'active');
                                c.setAttribute('aria-selected', 'false');
                            });
                            firstVis.classList.add('is-selected', 'active');
                            firstVis.setAttribute('aria-selected', 'true');
                            applyDepositInline(
                                firstVis.getAttribute('data-dw-method'),
                                firstVis.getAttribute('data-dw-provider'),
                                firstVis.getAttribute('data-dw-label')
                            );
                        }
                    }
                    return;
                }
                var depSel = document.getElementById('depositMethodSelect');
                if (depSel) {
                    Array.prototype.forEach.call(depSel.options, function(opt) {
                        var raw = (opt.getAttribute('data-category') || '').trim();
                        var cats = raw ? raw.split(/\s+/) : [];
                        opt.hidden = !(cat === 'all' || cats.indexOf(cat) !== -1);
                    });
                    var curOpt = depSel.options[depSel.selectedIndex];
                    if (!curOpt || curOpt.hidden || !curOpt.getAttribute('data-dw-method')) {
                        var firstVisOpt = Array.prototype.find.call(depSel.options, function(o) {
                            return !o.hidden && o.getAttribute('data-dw-method');
                        });
                        if (firstVisOpt) depSel.value = firstVisOpt.value;
                    }
                    applyDepositInlineFromSelect(depSel);
                }
            });
        });
        document.querySelectorAll('.withdraw-tab').forEach(function(tab) {
            if (tab.getAttribute('data-dw-tab-bound') === '1') return;
            tab.setAttribute('data-dw-tab-bound', '1');
            tab.addEventListener('click', function() {
                document.querySelectorAll('.withdraw-tab').forEach(function(t) {
                    t.classList.remove('active');
                    t.setAttribute('aria-selected', 'false');
                });
                this.classList.add('active');
                this.setAttribute('aria-selected', 'true');
                var cat = this.getAttribute('data-wcategory');
                var wGridTab = document.getElementById('withdrawGrid');
                if (wGridTab) {
                    wGridTab.querySelectorAll('.deposit-card').forEach(function(card) {
                        var wc = card.getAttribute('data-wcategory');
                        card.style.display = (cat === 'all' || wc === cat) ? '' : 'none';
                    });
                    primeWithdrawInlineSelection({ refreshBalance: false });
                    return;
                }
                var wSelTab = document.getElementById('withdrawMethodSelect');
                if (wSelTab) {
                    Array.prototype.forEach.call(wSelTab.options, function(opt) {
                        var wc = opt.getAttribute('data-wcategory');
                        opt.hidden = !(cat === 'all' || wc === cat);
                    });
                    primeWithdrawInlineSelection({ refreshBalance: false });
                }
            });
        });

        var depSelInit = document.getElementById('depositMethodSelect');
        if (depSelInit && !depSelInit.dataset.dwBound) {
            depSelInit.dataset.dwBound = '1';
            depSelInit.addEventListener('change', function() {
                applyDepositInlineFromSelect(depSelInit);
            });
        }

        var wSelInit = document.getElementById('withdrawMethodSelect');
        if (wSelInit && !wSelInit.dataset.dwBound) {
            wSelInit.dataset.dwBound = '1';
            wSelInit.addEventListener('change', function() {
                applyWithdrawInlineFromSelect(wSelInit);
                fillWithdrawBalanceStats();
            });
        }

        var depositGrid = document.getElementById('depositGrid');
        if (depositGrid && !depositGrid.dataset.dwClickBound) {
            depositGrid.dataset.dwClickBound = '1';
            depositGrid.addEventListener('click', function(e) {
                var card = e.target.closest('.deposit-card');
                if (!card || !depositGrid.contains(card) || card.style.display === 'none') return;
                var method = card.getAttribute('data-dw-method');
                if (!method) return;
                depositGrid.querySelectorAll('.deposit-card').forEach(function(c) {
                    c.classList.remove('is-selected', 'active');
                    c.setAttribute('aria-selected', 'false');
                });
                card.classList.add('is-selected', 'active');
                card.setAttribute('aria-selected', 'true');
                applyDepositInline(
                    method,
                    card.getAttribute('data-dw-provider'),
                    card.getAttribute('data-dw-label')
                );
            });
            depositGrid.addEventListener('keydown', function(e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                var card = e.target.closest('.deposit-card');
                if (!card || !depositGrid.contains(card) || !card.getAttribute('data-dw-method')) return;
                e.preventDefault();
                card.click();
            });
        }

        var withdrawGrid = document.getElementById('withdrawGrid');
        if (withdrawGrid && !withdrawGrid.dataset.dwClickBound) {
            withdrawGrid.dataset.dwClickBound = '1';
            withdrawGrid.addEventListener('click', function(e) {
                var card = e.target.closest('.deposit-card');
                if (!card || !withdrawGrid.contains(card) || card.style.display === 'none') return;
                var method = card.getAttribute('data-dw-method');
                if (!method) return;
                withdrawGrid.querySelectorAll('.deposit-card').forEach(function(c) {
                    c.classList.remove('is-selected', 'active');
                    c.setAttribute('aria-selected', 'false');
                });
                card.classList.add('is-selected', 'active');
                card.setAttribute('aria-selected', 'true');
                applyWithdrawInline(
                    method,
                    card.getAttribute('data-dw-provider'),
                    card.getAttribute('data-dw-label')
                );
                fillWithdrawBalanceStats();
            });
            withdrawGrid.addEventListener('keydown', function(e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                var card = e.target.closest('.deposit-card');
                if (!card || !withdrawGrid.contains(card) || !card.getAttribute('data-dw-method')) return;
                e.preventDefault();
                card.click();
            });
        }

        var wSubmit = document.getElementById('withdrawInlineSubmit');
        if (wSubmit && !wSubmit.dataset.bound) {
            wSubmit.dataset.bound = '1';
            wSubmit.addEventListener('click', function() { processVegaWithdrawal(); });
        }

        var bootDepGrid = document.getElementById('depositGrid');
        var bootDepSel = document.getElementById('depositMethodSelect');
        var bootKind = resolveProfilePaymentPageKind(modalFullUrl);
        window.__PROFILE_PAYMENT_PAGE__ = bootKind;
        if (bootKind === 'deposit' && document.getElementById('depositSection')) {
            if (bootDepGrid) {
                var bootCard = bootDepGrid.querySelector('.deposit-card.is-selected') || bootDepGrid.querySelector('.deposit-card[data-dw-method]');
                if (bootCard && bootCard.getAttribute('data-dw-method')) {
                    applyDepositInline(
                        bootCard.getAttribute('data-dw-method'),
                        bootCard.getAttribute('data-dw-provider'),
                        bootCard.getAttribute('data-dw-label')
                    );
                }
            } else if (bootDepSel) {
                applyDepositInlineFromSelect(bootDepSel);
            }
        }
        }

        loadProfilePaymentMethods().finally(function() {
            continueInitDepositWithdrawPage();
            enrichWithdrawGridFromWithdrawPaymentApi().finally(function() {
                if (resolveProfilePaymentPageKind(modalFullUrl) === 'withdraw') {
                    primeWithdrawInlineSelection();
                }
            });
        });
    }

    // ----- 6. Yatirim geçmişi (JWT /api/v2/deposit-history) veya birlesik islem listesi -----
    function parseDepositHistoryDate(str) {
        if (!str) return null;
        var iso = String(str).trim().replace(' ', 'T');
        var d = new Date(iso);
        return isNaN(d.getTime()) ? null : d;
    }

    function initDepositHistoryApi(root) {
        var scope = root && root.querySelector ? root : document;
        var inProfileModal = !!(scope && scope.closest && scope.closest('#profileModalContent'));
        var depositEp = (typeof window.__DEPOSIT_HISTORY_ENDPOINT__ === 'string' && window.__DEPOSIT_HISTORY_ENDPOINT__.trim())
            ? window.__DEPOSIT_HISTORY_ENDPOINT__.trim()
            : '/api/v2/deposit-history';
        var withdrawEp = (typeof window.__WITHDRAW_HISTORY_ENDPOINT__ === 'string' && window.__WITHDRAW_HISTORY_ENDPOINT__.trim())
            ? window.__WITHDRAW_HISTORY_ENDPOINT__.trim()
            : '/api/v2/withdraw-history';
        var tbody = scope.querySelector('#transactionTableBody');
        var typeFilter = scope.querySelector('#depositHistoryTypeFilter');
        var statusFilter = scope.querySelector('#depositHistoryStatusFilter');
        var applyBtn = scope.querySelector('#depositHistoryApplyBtn');
        var emptyEl = scope.querySelector('#txHistoryEmpty');
        var errEl = scope.querySelector('#txHistoryError');
        var tableWrap = scope.querySelector('#txHistoryTableWrap');
        var pagNav = scope.querySelector('#depositHistoryPagination');
        if (!tbody || !statusFilter || !typeFilter) return;
        if (tbody.getAttribute('data-tx-history-bound') === '1') return;
        tbody.setAttribute('data-tx-history-bound', '1');

        var state = { page: 1, perPage: 20, loading: false, kind: 'deposit' };

        function listKey() {
            return state.kind === 'withdraw' ? 'withdrawals' : 'deposits';
        }

        function activeEndpoint() {
            return state.kind === 'withdraw' ? withdrawEp : depositEp;
        }

        function setKind(kind) {
            state.kind = kind === 'withdraw' ? 'withdraw' : 'deposit';
            if (typeFilter) {
                typeFilter.value = state.kind;
            }
            if (inProfileModal) return;
            try {
                var h = state.kind === 'withdraw' ? '#withdraw' : '#deposit';
                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', window.location.pathname + window.location.search.split('#')[0] + h);
                } else {
                    window.location.hash = h;
                }
            } catch (eHash) {}
        }

        function getStatusText(s) {
            var m = {
                pending: 'Beklemede',
                processing: 'Isleniyor',
                approved: 'Onaylandi',
                confirmed: 'Onaylandi',
                completed: 'Tamamlandi',
                rejected: 'Reddedildi',
                failed: 'Basarisiz',
                cancelled: 'Iptal'
            };
            return m[s] || s;
        }

        function setLoading() {
            state.loading = true;
            tbody.innerHTML = '<tr><td colspan="8" class="tx-history-cell-center">Yükleniyor…</td></tr>';
            if (emptyEl) emptyEl.style.display = 'none';
            if (errEl) {
                errEl.style.display = 'none';
                errEl.textContent = '';
            }
            if (tableWrap) tableWrap.style.display = 'block';
            if (pagNav) pagNav.style.display = 'none';
        }

        function renderPagination(p) {
            if (!pagNav) return;
            if (!p || typeof p !== 'object') {
                pagNav.style.display = 'none';
                return;
            }
            var page = parseInt(p.page, 10) || 1;
            var totalPages = parseInt(p.totalPages, 10) || 0;
            var hasPrev = !!p.hasPrev;
            var hasNext = !!p.hasNext;
            if (totalPages <= 1 && !hasPrev && !hasNext) {
                pagNav.style.display = 'none';
                return;
            }
            pagNav.style.display = 'flex';
            pagNav.innerHTML =
                '<button type="button" class="tx-pagination-btn" data-dep-page="prev"' + (hasPrev ? '' : ' disabled') + '>Önceki</button>' +
                '<span class="tx-pagination-meta">Sayfa ' + escapeHtml(String(page)) +
                (totalPages > 0 ? ' / ' + escapeHtml(String(totalPages)) : '') + '</span>' +
                '<button type="button" class="tx-pagination-btn" data-dep-page="next"' + (hasNext ? '' : ' disabled') + '>Sonraki</button>';
            pagNav.querySelectorAll('[data-dep-page]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (state.loading) return;
                    var dir = btn.getAttribute('data-dep-page');
                    if (dir === 'prev' && hasPrev) {
                        state.page = Math.max(1, page - 1);
                        load();
                    } else if (dir === 'next' && hasNext) {
                        state.page = page + 1;
                        load();
                    }
                });
            });
        }

        function renderRows(list) {
            if (!list || list.length === 0) {
                tbody.innerHTML = '';
                if (emptyEl) emptyEl.style.display = 'block';
                if (tableWrap) tableWrap.style.display = 'none';
                return;
            }
            if (emptyEl) emptyEl.style.display = 'none';
            if (tableWrap) tableWrap.style.display = 'block';
            var html = '';
            list.forEach(function(row) {
                if (!row || typeof row !== 'object') return;
                var id = row.id != null ? String(row.id) : '';
                var method = row.method != null ? String(row.method) : '—';
                var provider = row.provider != null ? String(row.provider) : '—';
                var ref = row.referenceCode != null && String(row.referenceCode) !== '' ? String(row.referenceCode) : '—';
                var amt = row.amount;
                var fee = row.fee;
                var amtTxt = amt != null && !isNaN(Number(amt)) ? amountFormatter.format(Number(amt)) + ' ₺' : '—';
                var feeTxt = fee != null && !isNaN(Number(fee)) ? amountFormatter.format(Number(fee)) + ' ₺' : '—';
                var st = row.status != null ? String(row.status) : '';
                var statusText = getStatusText(st);
                var statusClass = 'tx-badge tx-badge-' + escapeHtml(st);
                var created = row.createdAt || row.created_at;
                var d = parseDepositHistoryDate(created);
                var dateStr = d ? dateTimeFormatter.format(d) : (created ? escapeHtml(String(created)) : '—');
                html += '<tr class="transaction-row">' +
                    '<td data-label="ID">' + escapeHtml(id) + '</td>' +
                    '<td data-label="Yöntem">' + escapeHtml(method) + '</td>' +
                    '<td data-label="Saglayici">' + escapeHtml(provider) + '</td>' +
                    '<td data-label="Referans">' + escapeHtml(ref) + '</td>' +
                    '<td data-label="Tutar">' + amtTxt + '</td>' +
                    '<td data-label="Ücret">' + feeTxt + '</td>' +
                    '<td data-label="Durum"><span class="' + statusClass + '">' + escapeHtml(statusText) + '</span></td>' +
                    '<td data-label="Tarih">' + dateStr + '</td>' +
                    '</tr>';
            });
            tbody.innerHTML = html;
        }

        function load() {
            setLoading();
            var qs = new URLSearchParams();
            qs.set('page', String(state.page));
            qs.set('per_page', String(state.perPage));
            var st = statusFilter.value ? String(statusFilter.value).trim() : '';
            if (st) qs.set('status', st);

            fetch(appendQuery(apiUrl(activeEndpoint()), qs.toString()), {
                credentials: 'same-origin',
                headers: memberAuthHeaders({ Accept: 'application/json' })
            })
                .then(function(res) {
                    return res.text().then(function(txt) {
                        var j = {};
                        try {
                            j = txt ? JSON.parse(txt) : {};
                        } catch (eJson) {
                            j = { success: false, message: 'Geçersiz yanit.' };
                        }
                        return { res: res, j: j };
                    });
                })
                .then(function(pack) {
                    state.loading = false;
                    var j = pack.j || {};
                    if (pack.res.status === 401 || (j && j.code === 401)) {
                        tbody.innerHTML = '';
                        if (tableWrap) tableWrap.style.display = 'none';
                        if (emptyEl) emptyEl.style.display = 'none';
                        if (errEl) {
                            errEl.style.display = 'block';
                            errEl.textContent = 'Oturum gerekli. Lütfen tekrar giriş yapın.';
                        }
                        if (pagNav) pagNav.style.display = 'none';
                        return;
                    }
                    if (!j.success) {
                        tbody.innerHTML = '';
                        if (tableWrap) tableWrap.style.display = 'none';
                        if (emptyEl) emptyEl.style.display = 'none';
                        if (errEl) {
                            errEl.style.display = 'block';
                            errEl.textContent = j.message || 'Liste yüklenemedi.';
                        }
                        if (pagNav) pagNav.style.display = 'none';
                        return;
                    }
                    var data = j.data && typeof j.data === 'object' ? j.data : {};
                    var key = listKey();
                    var rows = Array.isArray(data[key]) ? data[key] : (Array.isArray(data.items) ? data.items : []);
                    renderRows(rows);
                    renderPagination(data.pagination);
                })
                .catch(function() {
                    state.loading = false;
                    tbody.innerHTML = '';
                    if (tableWrap) tableWrap.style.display = 'none';
                    if (emptyEl) emptyEl.style.display = 'none';
                    if (errEl) {
                        errEl.style.display = 'block';
                        errEl.textContent = t('common.connection_error', 'Bağlantı hatası. Lütfen tekrar deneyin.');
                    }
                    if (pagNav) pagNav.style.display = 'none';
                });
        }

        var ih = (window.location.hash || '').replace(/^#/, '').toLowerCase();
        if (inProfileModal && (ih === 'deposit' || ih === 'withdraw' || ih === 'cekim')) {
            try {
                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', window.location.pathname + window.location.search.split('#')[0]);
                } else {
                    window.location.hash = '';
                }
            } catch (eCleanHash) {}
        }
        if (ih === 'withdraw' || ih === 'cekim') {
            setKind('withdraw');
        } else {
            setKind(typeFilter.value || 'deposit');
        }

        typeFilter.addEventListener('change', function() {
            setKind(typeFilter.value || 'deposit');
            state.page = 1;
            load();
        });

        if (applyBtn) applyBtn.addEventListener('click', function() {
            state.page = 1;
            load();
        });
        statusFilter.addEventListener('change', function() {
            state.page = 1;
            load();
        });
        load();
    }

    function initDepositWithdrawHistory() {
        var apiRoots = document.querySelectorAll('.tx-history-main');
        if (apiRoots.length) {
            apiRoots.forEach(function(root) {
                initDepositHistoryApi(root);
            });
            return;
        }
        if (window.__DEPOSIT_HISTORY_API__ || document.getElementById('depositHistoryTypeFilter')) {
            initDepositHistoryApi(document);
            return;
        }
        var legacyRaw = window.__PROFILE_TRANSACTIONS__ || [];
        var tbody = document.getElementById('transactionTableBody');
        var typeFilter = document.getElementById('transactionTypeFilter');
        var dateStart = document.getElementById('dateStart');
        var dateEnd = document.getElementById('dateEnd');
        var applyFilterBtn = document.getElementById('applyFilter');
        var emptyEl = document.getElementById('txHistoryEmpty');
        var tableWrap = document.getElementById('txHistoryTableWrap');
        if (!tbody) return;

        function isWithdrawLegacyType(ty) {
            if (!ty) return false;
            var t = String(ty);
            return t === 'withdrawal' || t.indexOf('withdrawal') === 0;
        }

        function parseTxDate(s) {
            if (!s) return 0;
            var iso = String(s).replace(' ', 'T');
            var ms = new Date(iso).getTime();
            return isNaN(ms) ? 0 : ms;
        }

        function sortByCreatedDesc(list) {
            return list.slice().sort(function(a, b) {
                return parseTxDate(b.created_at) - parseTxDate(a.created_at);
            });
        }

        function mapWithdrawApiRow(w) {
            if (!w || typeof w !== 'object') return null;
            var created = w.createdAt != null ? String(w.createdAt) : '';
            var method = w.method != null ? String(w.method) : '';
            var prov = w.provider != null ? String(w.provider) : '';
            if (prov && prov !== method) {
                method = method ? method + ' / ' + prov : prov;
            }
            return {
                type: 'withdrawal',
                amount: w.amount != null ? Number(w.amount) : null,
                method: method || null,
                status: w.status != null ? String(w.status) : '',
                created_at: created,
                reference_code: w.referenceCode != null ? String(w.referenceCode) : '',
                api_id: w.id != null ? String(w.id) : '',
            };
        }

        var nonWithdrawLegacy = legacyRaw.filter(function(t) {
            return !isWithdrawLegacyType(t && t.type);
        });
        var allTransactions = sortByCreatedDesc(nonWithdrawLegacy.slice());

        function typeFilterMatches(optionValue, transactionType) {
            if (optionValue === 'ALL') return true;
            if (optionValue === 'deposit' || optionValue === 'deposit_request' || optionValue === 'deposit_payment') return transactionType === 'deposit';
            if (optionValue === 'withdrawal' || optionValue === 'withdrawal_payment' || optionValue === 'withdrawal_rejected') return transactionType === 'withdrawal';
            if (optionValue === 'bet') return transactionType === 'bet';
            return transactionType === optionValue;
        }
        function getStatusText(s) {
            var m = { pending: 'Beklemede', approved: 'Onaylandi', confirmed: 'Onaylandi', completed: 'Onaylandi', rejected: 'Reddedildi', cancelled: 'Iptal Edildi', processing: 'Isleniyor' };
            return m[s] || s;
        }
        function getTypeText(type) { return type === 'deposit' ? 'Yatirim' : 'Çekim Talebi'; }
        function renderTransactions(transactions) {
            if (transactions.length === 0) {
                if (emptyEl) emptyEl.style.display = 'block';
                if (tableWrap) tableWrap.style.display = 'none';
                return;
            }
            if (emptyEl) emptyEl.style.display = 'none';
            if (tableWrap) tableWrap.style.display = 'block';
            var counter = 1;
            var html = '';
            transactions.forEach(function(t) {
                var typeText = getTypeText(t.type);
                var amountText = t.amount != null ? amountFormatter.format(t.amount) + ' ₺' : '—';
                var dateStr = t.created_at ? dateTimeFormatter.format(new Date(String(t.created_at).replace(' ', 'T'))) : '—';
                var methodText = t.method || '—';
                var statusText = getStatusText(t.status);
                var statusClass = 'tx-badge tx-badge-' + (t.status || '');
                html += '<tr class="transaction-row" data-type="' + (t.type || '') + '" data-created="' + (t.created_at || '') + '">' +
                    '<td data-label="ID">' + (counter++) + '</td><td data-label="İşlem Türü">' + typeText + '</td><td data-label="Miktar">' + amountText + '</td>' +
                    '<td data-label="Ödeme Yöntemi">' + methodText + '</td><td data-label="Durum"><span class="' + statusClass + '">' + statusText + '</span></td>' +
                    '<td data-label="Tarih">' + dateStr + '</td></tr>';
            });
            tbody.innerHTML = html;
        }
        function filterTransactions() {
            var typeVal = typeFilter ? typeFilter.value : 'ALL';
            var startVal = dateStart ? dateStart.value : '';
            var endVal = dateEnd ? dateEnd.value : '';
            var filtered = allTransactions.filter(function(t) {
                if (!typeFilterMatches(typeVal, t.type)) return false;
                if (!startVal || !endVal) return true;
                var d = (t.created_at || '').split(' ')[0];
                return d >= startVal && d <= endVal;
            });
            renderTransactions(filtered);
        }
        if (applyFilterBtn) applyFilterBtn.addEventListener('click', filterTransactions);
        if (typeFilter) typeFilter.addEventListener('change', filterTransactions);
        if (dateStart) dateStart.addEventListener('change', filterTransactions);
        if (dateEnd) dateEnd.addEventListener('change', filterTransactions);

        fetch(appendQuery(apiUrl('/api/v2/withdraw-history'), 'per_page=50'), { credentials: 'same-origin', headers: memberAuthHeaders({ Accept: 'application/json' }) })
            .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, status: r.status, data: data }; }); })
            .then(function(res) {
                var payload = res.data || {};
                var inner = payload.data || {};
                var list = inner.withdrawals;
                if (res.status === 401 || (payload.success === false && (payload.code === 401 || res.status === 401))) {
                    return;
                }
                if (!payload.success || !Array.isArray(list)) {
                    toastNotify('warning', payload.message || 'Çekim listesi alınamadı.', 'Çekim geçmişi');
                    return;
                }
                var mapped = [];
                list.forEach(function(w) {
                    var row = mapWithdrawApiRow(w);
                    if (row) mapped.push(row);
                });
                allTransactions = sortByCreatedDesc(nonWithdrawLegacy.concat(mapped));
                filterTransactions();
            })
            .catch(function() {
                toastNotify('error', t('common.connection_error', 'Bağlantı hatası.'), 'Çekim geçmişi');
                filterTransactions();
            });

        if (allTransactions.length === 0) {
            tbody.innerHTML = '';
            if (emptyEl) emptyEl.style.display = 'block';
            if (tableWrap) tableWrap.style.display = 'none';
        } else filterTransactions();
    }

    function initCasinoGameHistory() {
        document.querySelectorAll('[data-casino-history-root]:not([data-casino-history-bound])').forEach(function(root) {
            root.setAttribute('data-casino-history-bound', '1');
            var api = root.getAttribute('data-api') || '/api/v2/profile/casino-game-history';
            var tbody = root.querySelector('[data-casino-history-body]');
            var emptyEl = root.querySelector('[data-casino-history-empty]');
            var tableWrap = root.querySelector('[data-casino-history-table-wrap]');
            var sourceFilter = root.querySelector('#casinoHistorySourceFilter');
            var txnFilter = root.querySelector('#casinoHistoryTxnFilter');
            var providerFilter = root.querySelector('#casinoHistoryProviderFilter');
            var applyBtn = root.querySelector('#casinoHistoryApplyBtn');
            if (!tbody) return;

            var lastLoadedRows = [];

            function normalizeSource(source) {
                source = String(source || 'all').toLowerCase();
                if (source === 'live' || source === 'livecasino') return 'live_casino';
                if (source !== 'slot' && source !== 'live_casino') return 'all';
                return source;
            }

            function sourceText(row) {
                var source = normalizeSource(row.source || row.category || '');
                return source === 'live_casino' ? 'Canli Casino' : 'Slot';
            }

            function txnText(txn) {
                txn = String(txn || 'bet').toLowerCase();
                if (txn === 'win') return 'Kazanç';
                if (txn === 'refund' || txn === 'cancel') return 'Iade';
                if (txn === 'adjustment') return 'Düzeltme';
                return 'Bahis';
            }

            function txnClass(txn) {
                txn = String(txn || 'bet').toLowerCase();
                if (txn === 'win') return 'text-success';
                if (txn === 'refund' || txn === 'cancel') return 'text-warning';
                if (txn === 'adjustment') return 'text-info';
                return 'text-danger';
            }

            function normalizeTxnFilter(value) {
                var v = String(value || 'all').toLowerCase().trim();
                if (v === 'all' || v === '') return 'all';
                if (v === 'refund' || v === 'cancel') return 'refund';
                if (v === 'win' || v === 'bet' || v === 'adjustment') return v;
                return 'all';
            }

            function money(value) {
                var n = Number(value || 0);
                return amountFormatter.format(isNaN(n) ? 0 : n) + ' ₺';
            }

            function dateText(value) {
                if (!value) return '—';
                var d = new Date(String(value).replace(' ', 'T'));
                return isNaN(d.getTime()) ? escapeHtml(String(value)) : dateTimeFormatter.format(d);
            }

            function setLoading() {
                tbody.innerHTML = '<tr><td colspan="10">Oyun geçmişi yükleniyor...</td></tr>';
                if (emptyEl) emptyEl.hidden = true;
                if (tableWrap) tableWrap.hidden = false;
            }

            function render(rows) {
                if (!Array.isArray(rows) || rows.length === 0) {
                    tbody.innerHTML = '';
                    if (emptyEl) emptyEl.hidden = false;
                    if (tableWrap) tableWrap.hidden = true;
                    return;
                }
                if (emptyEl) emptyEl.hidden = true;
                if (tableWrap) tableWrap.hidden = false;
                tbody.innerHTML = rows.map(function(row, idx) {
                    row = row || {};
                    var id = row.id != null ? String(row.id) : '';
                    var game = row.gameName || row.game_name || row.gameId || row.game_id || '—';
                    var provider = row.providerName || row.provider_name || '—';
                    var txn = row.txnType || row.txn_type || 'bet';
                    var round = row.roundId || row.round_id || '—';
                    return '<tr class="transaction-row" data-bet-type="game_history" data-transaction-id="' + escapeHtml(id) + '">' +
                        '<td data-label="ID">' + (idx + 1) + '</td>' +
                        '<td data-label="Oyun">' + escapeHtml(game) + '</td>' +
                        '<td data-label="Saglayici">' + escapeHtml(provider || '₺') + '</td>' +
                        '<td data-label="Kategori">' + escapeHtml(sourceText(row)) + '</td>' +
                        '<td data-label="Islem"><span class="' + txnClass(txn) + ' fw-bold">' + escapeHtml(txnText(txn)) + '</span></td>' +
                        '<td data-label="Bahis">' + money(row.betAmount != null ? row.betAmount : row.bet_amount) + '</td>' +
                        '<td data-label="Kazanç">' + money(row.winAmount != null ? row.winAmount : row.win_amount) + '</td>' +
                        '<td data-label="Bakiye">' + money(row.balanceAfter != null ? row.balanceAfter : row.balance_after) + '</td>' +
                        '<td data-label="Detay"><button class="btn btn-xs btn-outline-info game-history-details-btn" data-history-id="' + escapeHtml(id) + '">Detaylar</button>' +
                        '<div class="small mt-1"><span class="badge bg-secondary">Round: ' + escapeHtml(round || '—') + '</span></div></td>' +
                        '<td data-label="Tarih">' + dateText(row.createdAt || row.created_at) + '</td>' +
                        '</tr>';
                }).join('');
            }

            function applyLocalFilters() {
                var source = normalizeSource(sourceFilter ? sourceFilter.value : (root.getAttribute('data-source') || 'all'));
                var txn = normalizeTxnFilter(txnFilter ? txnFilter.value : 'all');
                var providerNeedle = String(providerFilter && providerFilter.value ? providerFilter.value : '').toLowerCase().trim();

                var rows = (Array.isArray(lastLoadedRows) ? lastLoadedRows : []).filter(function(row) {
                    row = row || {};
                    var rowSource = normalizeSource(row.source || row.category || '');
                    if (source !== 'all' && rowSource !== source) {
                        return false;
                    }

                    var rowTxn = String(row.txnType || row.txn_type || 'bet').toLowerCase();
                    if (txn !== 'all') {
                        if (txn === 'refund') {
                            if (rowTxn !== 'refund' && rowTxn !== 'cancel') return false;
                        } else if (rowTxn !== txn) {
                            return false;
                        }
                    }

                    if (providerNeedle !== '') {
                        var providerText = String(row.providerName || row.provider_name || '').toLowerCase();
                        if (providerText.indexOf(providerNeedle) === -1) {
                            return false;
                        }
                    }

                    return true;
                });

                render(rows);
            }

            function load(source) {
                source = normalizeSource(source);
                if (sourceFilter) {
                    sourceFilter.value = source;
                }
                root.setAttribute('data-source', source);
                setLoading();
                var qs = new URLSearchParams();
                qs.set('per_page', '100');
                if (source !== 'all') qs.set('source', source);
                fetch(appendQuery(apiUrl(api), qs.toString()), {
                    credentials: 'same-origin',
                    headers: memberAuthHeaders({ Accept: 'application/json' })
                })
                    .then(function(r) {
                        return r.json().then(function(data) {
                            return { status: r.status, data: data };
                        });
                    })
                    .then(function(res) {
                        var payload = res.data || {};
                        var inner = payload.data || {};
                        var rows = inner.transactions || inner.items || [];
                        if (res.status === 401 || payload.code === 401) {
                            toastNotify('warning', 'Oyun geçmişini görmek için tekrar giriş yapın.', 'Oturum');
                            render([]);
                            return;
                        }
                        if (!payload.success || !Array.isArray(rows)) {
                            toastNotify('error', payload.message || 'Oyun geçmişi alınamadı.', 'Casino geçmişi');
                            render([]);
                            return;
                        }
                        lastLoadedRows = rows;
                        applyLocalFilters();
                    })
                    .catch(function() {
                        toastNotify('error', t('common.connection_error', 'Bağlantı hatası.'), 'Casino geçmişi');
                        lastLoadedRows = [];
                        render([]);
                    });
            }

            if (applyBtn) {
                applyBtn.addEventListener('click', function() {
                    var selectedSource = normalizeSource(sourceFilter ? sourceFilter.value : root.getAttribute('data-source'));
                    load(selectedSource);
                });
            }

            if (providerFilter) {
                providerFilter.addEventListener('keydown', function(e) {
                    if (e.key !== 'Enter') return;
                    e.preventDefault();
                    var selectedSource = normalizeSource(sourceFilter ? sourceFilter.value : root.getAttribute('data-source'));
                    load(selectedSource);
                });
            }

            load(root.getAttribute('data-source') || 'all');
        });
    }

    // ----- 7. Bahis geçmişi (jQuery) -----
    function initBetHistory() {
        if (typeof window.jQuery === 'undefined' || (!document.getElementById('sporDetailsContent') && !document.getElementById('gameHistoryContent'))) return;
        var $ = window.jQuery;
        function renderDetailLoading(targetId) {
            var el = document.getElementById(targetId);
            if (!el) return;
            el.innerHTML = '<div class="profile-detail-loading">Detaylar yukleniyor...</div>';
        }
        function renderDetailError(targetId, message) {
            var el = document.getElementById(targetId);
            if (!el) return;
            el.innerHTML = '<div class="profile-detail-error">' + escapeHtml(message || 'Detaylar yuklenemedi.') + '</div>';
        }
        function extractAjaxError(xhr, fallback) {
            var msg = fallback || 'Detaylar yuklenemedi.';
            if (!xhr) return msg;
            if (xhr.status === 401) return 'Oturum suresi dolmus olabilir. Lutfen tekrar giriş yapın.';
            if (xhr.status === 404) return 'Kayit bulunamadi veya detay endpointine erisim saglanamadi.';
            var rt = (xhr.responseText || '').trim();
            if (rt) {
                try {
                    var parsed = JSON.parse(rt);
                    if (parsed && typeof parsed.message === 'string' && parsed.message.trim() !== '') {
                        return parsed.message;
                    }
                } catch (e) {}
            }
            return msg;
        }
        function openDetailModal(modalId) {
            var modalEl = document.getElementById(modalId);
            if (!modalEl) return;
            if (modalEl.parentElement !== document.body) {
                document.body.appendChild(modalEl);
            }
            modalEl.style.zIndex = '100200';
            if (window.showModalById) {
                window.showModalById(modalId);
            }
            var backdrop = document.querySelector('.modal-backdrop[data-modal-backdrop]');
            if (backdrop) {
                backdrop.style.zIndex = '100199';
            }
        }
        function showSporModal() { openDetailModal('sporDetailsModal'); }
        function showGameHistoryModalEl() { openDetailModal('gameHistoryModal'); }
        function showSporDetails(betId) {
            renderDetailLoading('sporDetailsContent');
            showSporModal();
            $.ajax({
                url: apiUrl('/api/v2/profile/spor-bet-detail'),
                type: 'GET',
                data: { bet_id: betId },
                headers: memberAuthHeaders({ Accept: 'text/html' }),
                success: function(response) {
                    $('#sporDetailsContent').html(response || '<div class="profile-detail-error">Detay bilgisi bulunamadi.</div>');
                },
                error: function(xhr) {
                    var errorMsg = extractAjaxError(xhr, 'Detaylar yuklenemedi');
                    renderDetailError('sporDetailsContent', errorMsg);
                    toastNotify('error', errorMsg, 'Hata');
                }
            });
        }
        function showGameHistoryDetails(historyId) {
            renderDetailLoading('gameHistoryContent');
            showGameHistoryModalEl();
            $.ajax({
                url: apiUrl('/api/v2/profile/game-history-detail'),
                type: 'GET',
                data: { history_id: historyId },
                headers: memberAuthHeaders({ Accept: 'text/html' }),
                success: function(response) {
                    $('#gameHistoryContent').html(response || '<div class="profile-detail-error">Oyun gecmisi detayi bulunamadi.</div>');
                },
                error: function(xhr) {
                    var errorMsg = extractAjaxError(xhr, 'Oyun gecmisi detaylari yuklenemedi');
                    renderDetailError('gameHistoryContent', errorMsg);
                    toastNotify('error', errorMsg, 'Hata');
                }
            });
        }
        $(document).off('click.betHist', '.spor-details-btn').on('click.betHist', '.spor-details-btn', function(e) {
            e.stopPropagation();
            showSporDetails($(this).data('bet-id'));
        });
        $(document).off('click.betHistGh', '.game-history-details-btn').on('click.betHistGh', '.game-history-details-btn', function(e) {
            e.stopPropagation();
            showGameHistoryDetails($(this).data('history-id'));
        });
        var bhPeriod = document.getElementById('bhPeriod');
        var bhPeriodCustomWrap = document.getElementById('bhPeriodCustomWrap');
        var bhStart = document.getElementById('bhPeriodStart');
        var bhEnd = document.getElementById('bhPeriodEnd');
        var bhDatePresets = document.getElementById('bhDatePresets');
        var bhForm = document.getElementById('betHistoryFilterForm');

        function toIso(d) {
            if (!(d instanceof Date) || isNaN(d.getTime())) return '';
            var year = String(d.getFullYear());
            var month = String(d.getMonth() + 1).padStart(2, '0');
            var day = String(d.getDate()).padStart(2, '0');
            return year + '-' + month + '-' + day;
        }

        function markPreset(range) {
            if (!bhDatePresets) return;
            bhDatePresets.querySelectorAll('.bhf-date-preset').forEach(function(btn) {
                btn.classList.toggle('is-active', btn.getAttribute('data-range') === range);
            });
        }

        function applyPreset(range) {
            if (!bhStart || !bhEnd) return;
            var today = new Date();
            var end = new Date(today.getFullYear(), today.getMonth(), today.getDate());
            var start = new Date(end);
            if (range === 'today') {
                // same day
            } else if (range === 'last7') {
                start.setDate(start.getDate() - 6);
            } else if (range === 'last30') {
                start.setDate(start.getDate() - 29);
            }
            bhStart.value = toIso(start);
            bhEnd.value = toIso(end);
            syncDateBounds();
            syncDateVisualState();
            markPreset(range);
        }

        function syncDateBounds() {
            if (!bhStart || !bhEnd) return;
            if (bhStart.value) bhEnd.min = bhStart.value;
            else bhEnd.removeAttribute('min');
            if (bhEnd.value) bhStart.max = bhEnd.value;
            else bhStart.removeAttribute('max');
        }

        function syncDateVisualState() {
            [bhStart, bhEnd].forEach(function(inputEl) {
                if (!inputEl) return;
                var wrap = inputEl.closest('.cm622-date-range') || inputEl.closest('.bhf-date-input-wrap');
                if (!wrap) return;
                wrap.classList.toggle('has-value', !!String(inputEl.value || '').trim());
                var control = inputEl.closest('.form-control-bc');
                if (control) {
                    var filled = !!(bhStart && bhStart.value) || !!(bhEnd && bhEnd.value);
                    control.classList.toggle('filled', filled);
                    control.classList.toggle('valid', filled);
                }
            });
        }

        function openNativePicker(inputEl) {
            if (!inputEl) return;
            inputEl.focus();
            if (typeof inputEl.showPicker === 'function') {
                try {
                    inputEl.showPicker();
                } catch (e) {}
            }
        }

        function updateCustomState() {
            var isCustom = bhPeriod && bhPeriod.value === 'custom';
            if (bhPeriodCustomWrap) {
                $(bhPeriodCustomWrap).toggle(!!isCustom);
            }
            if (bhForm) {
                bhForm.classList.toggle('is-custom-period', !!isCustom);
            }
            var filterWrap = bhForm ? bhForm.closest('.componentFilterWrapper-bc') : null;
            if (filterWrap) filterWrap.classList.toggle('is-custom-period', !!isCustom);
            if (bhStart) bhStart.required = !!isCustom;
            if (bhEnd) bhEnd.required = !!isCustom;
            if (isCustom) syncDateBounds();
            syncDateVisualState();
        }

        if (bhPeriod && bhPeriodCustomWrap && $) {
            $(bhPeriod).off('change.betHistPer').on('change.betHistPer', function() {
                updateCustomState();
            });
        }

        if (bhStart && bhEnd) {
            bhStart.addEventListener('change', function() {
                syncDateBounds();
                syncDateVisualState();
                markPreset('');
            });
            bhEnd.addEventListener('change', function() {
                syncDateBounds();
                syncDateVisualState();
                markPreset('');
            });

            bhStart.addEventListener('input', syncDateVisualState);
            bhEnd.addEventListener('input', syncDateVisualState);

            var dateRange = (bhStart.closest('.cm622-date-range') || bhEnd.closest('.cm622-date-range'));
            if (dateRange) {
                dateRange.addEventListener('click', function(e) {
                    if (e.target === bhStart || e.target === bhEnd) return;
                    openNativePicker(!bhStart.value ? bhStart : bhEnd);
                });
            } else {
                var startWrap = bhStart.closest('.bhf-date-input-wrap');
                var endWrap = bhEnd.closest('.bhf-date-input-wrap');
                if (startWrap) {
                    startWrap.addEventListener('click', function(e) {
                        if (e.target === bhStart) return;
                        openNativePicker(bhStart);
                    });
                }
                if (endWrap) {
                    endWrap.addEventListener('click', function(e) {
                        if (e.target === bhEnd) return;
                        openNativePicker(bhEnd);
                    });
                }
            }
        }

        if (bhDatePresets) {
            $(bhDatePresets).off('click.betHistPreset').on('click.betHistPreset', '.bhf-date-preset', function() {
                if (!(bhPeriod && bhPeriod.value === 'custom')) {
                    if (typeof window.setCm622MultiSelectValue === 'function') {
                        window.setCm622MultiSelectValue(bhPeriod, 'custom');
                    } else if (bhPeriod) {
                        bhPeriod.value = 'custom';
                    }
                    updateCustomState();
                }
                applyPreset(String(this.getAttribute('data-range') || ''));
            });
        }

        if (bhForm && bhStart && bhEnd) {
            bhForm.addEventListener('submit', function(e) {
                if (!(bhPeriod && bhPeriod.value === 'custom')) return;
                if (!bhStart.value || !bhEnd.value) {
                    e.preventDefault();
                    toastNotify('warning', 'Lutfen baslangic ve bitis tarihini secin.', 'Tarih araligi');
                    return;
                }
                if (bhStart.value > bhEnd.value) {
                    e.preventDefault();
                    toastNotify('warning', 'Baslangic tarihi, bitis tarihinden buyuk olamaz.', 'Tarih araligi');
                }
            });
        }

        updateCustomState();
        syncDateVisualState();
    }

    // ----- 8. Referanslar (jQuery) -----
    function initReferences() {
        if (typeof window.jQuery === 'undefined' || !document.getElementById('copyReferralCode')) return;
        var $ = window.jQuery;
        function fetchReferralData() {
            $.ajax({
                url: apiUrl('/api/v2/referrals'),
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        if (response.referral_code) {
                            $('#userReferralCode').text(response.referral_code);
                            var shareLink = response.data && response.data.share_link
                                ? response.data.share_link
                                : window.location.origin + '/register?ref=' + encodeURIComponent(response.referral_code);
                            $('#shareLink').text(shareLink);
                        } else {
                            $('#userReferralCode').text('Referans kodu bulunamadi.');
                            $('#shareLink').text('N/A');
                        }
                        var referredUsers = response.referred_users || [];
                        var tableBody = $('#referredUsersTableBody');
                        tableBody.empty();
                        if (referredUsers.length > 0) {
                            $.each(referredUsers, function(index, user) {
                                var formattedDate = shortDateFormatter.format(new Date(user.created_at));
                                var row = '<tr><td>' + (index + 1) + '</td><td>' + (user.first_name || '') + ' ' + (user.surname || '') + '</td><td>' + (user.username || '') + '</td><td>' + (user.email || '') + '</td><td>' + formattedDate + '</td></tr>';
                                tableBody.append(row);
                            });
                            $('#noReferralsMessage').hide();
                        } else {
                            $('#noReferralsMessage').show();
                            tableBody.append('<tr><td colspan="5" class="text-center-custom"></td></tr>');
                        }
                    } else {
                        toastNotify('error', response.message || 'Referans verileri yüklenemedi.', 'Hata');
                        $('#referredUsersTableBody').empty().append('<tr><td colspan="5" class="text-center-custom">' + (response.message || '') + '</td></tr>');
                        $('#noReferralsMessage').hide();
                    }
                },
                error: function(xhr, status, error) {
                    toastNotify('error', 'Referans verileri yüklenirken bir sorun oluştu: ' + error, 'Hata');
                    $('#referredUsersTableBody').empty().append('<tr><td colspan="5" class="text-center-custom">Veriler yüklenemedi. Lütfen tekrar deneyin.</td></tr>');
                    $('#noReferralsMessage').hide();
                }
            });
        }
        fetchReferralData();
        $('#copyReferralCode').on('click', function() {
            var referralCode = $('#userReferralCode').text();
            var tempInput = document.createElement('input');
            document.body.appendChild(tempInput);
            tempInput.value = referralCode;
            tempInput.select();
            document.execCommand('copy');
            tempInput.remove();
            toastNotify('success', 'Referans kodunuz panoya kopyalandı.', 'Kopyalandı');
        });
    }

    function profilePromoToast(kind, message) {
        var msg = message || '';
        toastNotify(kind === 'success' ? 'success' : (kind === 'warn' ? 'warning' : 'error'), msg);
    }

    function bindProfilePromoUi() {
        var input = document.getElementById('profileModalPromoCode');
        var btn = document.getElementById('profileModalPromoUseLegacy');
        if (!input) return;

        if (!input.getAttribute('placeholder')) {
            input.setAttribute('placeholder', ' ');
        }

        var wrap = input.closest('.form-control-bc');

        function syncPromoField() {
            var filled = String(input.value || '').trim() !== '';
            if (wrap) {
                wrap.classList.toggle('filled', filled);
                wrap.classList.toggle('valid', filled);
            }
            if (btn) {
                btn.disabled = !filled;
                btn.setAttribute('aria-disabled', filled ? 'false' : 'true');
            }
        }

        if (input.getAttribute('data-promo-float-bound') !== '1') {
            input.setAttribute('data-promo-float-bound', '1');
            input.addEventListener('focus', function() {
                if (wrap) wrap.classList.add('focused');
            });
            input.addEventListener('blur', function() {
                if (wrap) wrap.classList.remove('focused');
                syncPromoField();
            });
            input.addEventListener('input', syncPromoField);
            input.addEventListener('change', syncPromoField);
            input.addEventListener('keyup', syncPromoField);
        }
        syncPromoField();
    }

    function loadProfilePromocodesSelect() {
        var sel = document.getElementById('profileModalPromoSelect');
        var statusEl = document.getElementById('profilePromocodesStatus');
        if (!sel) return;

        function setStatus(text, isError) {
            if (!statusEl) return;
            statusEl.textContent = text || '';
            statusEl.classList.toggle('profile-promocodes-status--error', !!isError);
        }

        sel.innerHTML = '<option value="">Yükleniyor…</option>';
        setStatus('');

        fetch(apiUrl('/api/v2/promocodes'), { credentials: 'same-origin', headers: memberAuthHeaders({ Accept: 'application/json' }) })
            .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, status: r.status, data: data }; }); })
            .then(function(res) {
                var data = res.data || {};
                if (res.status === 401 || (data.error === 'UNAUTHORIZED')) {
                    sel.innerHTML = '<option value="">Oturum / token gerekli</option>';
                    setStatus('Promo listesi için yeniden giriş yapın.', true);
                    return;
                }
                if (!data.success) {
                    sel.innerHTML = '<option value="">—</option>';
                    setStatus(data.message || 'Promo listesi alinamadi.', true);
                    return;
                }
                var inner = data.data || {};
                var list = inner.promocodes;
                if (!Array.isArray(list)) {
                    list = [];
                }
                sel.innerHTML = '';
                if (list.length === 0) {
                    var opt0 = document.createElement('option');
                    opt0.value = '';
                    opt0.textContent = 'Kullanilabilir kod yok';
                    sel.appendChild(opt0);
                    setStatus(inner.message || data.message || 'Şu an talep edilebilir panel kodu yok.');
                    return;
                }
                var ph = document.createElement('option');
                ph.value = '';
                ph.textContent = 'Kod seçin…';
                sel.appendChild(ph);
                list.forEach(function(row) {
                    var id = parseInt(row.id, 10);
                    if (!id) return;
                    var code = String(row.code || '');
                    var amt = row.amount != null ? amountFormatter.format(Number(row.amount)) + ' ₺' : '—';
                    var exp = row.expiresAt != null && row.expiresAt !== '' ? row.expiresAt : 'Süresiz';
                    var rem = row.remainingUses;
                    var remTxt = rem === null || rem === undefined ? 'Sinirsiz' : String(rem);
                    var o = document.createElement('option');
                    o.value = String(id);
                    o.textContent = code + ' · ' + amt + ' · ' + exp + ' · Kalan: ' + remTxt;
                    sel.appendChild(o);
                });
                if (data.message) setStatus('');
            })
            .catch(function() {
                sel.innerHTML = '<option value="">Yüklenemedi</option>';
                setStatus('Liste alinamadi.', true);
            });
    }

    function initProfilePromoBlockOnce() {
        if (window.__profileModalPromoInit) return;
        window.__profileModalPromoInit = true;

        bindProfilePromoUi();

        document.body.addEventListener('click', function(e) {
            var talepBtn = e.target && e.target.closest && e.target.closest('#profileModalPromoApply');
            var legacyBtn = e.target && e.target.closest && e.target.closest('#profileModalPromoUseLegacy');
            if (!talepBtn && !legacyBtn) return;
            e.preventDefault();
            e.stopPropagation();

            if (legacyBtn) {
                if (legacyBtn.disabled) {
                    profilePromoToast('warn', t('promo.enter_code', 'Promosyon kodu girin.'));
                    return;
                }
                var inputLegacy = document.getElementById('profileModalPromoCode');
                var kod = inputLegacy && inputLegacy.value ? String(inputLegacy.value).trim() : '';
                if (!kod) {
                    profilePromoToast('warn', t('promo.enter_code', 'Promosyon kodu girin.'));
                    return;
                }
                legacyBtn.disabled = true;
                fetch(apiUrl('/api/v2/bonus/use-code'), {
                    method: 'POST',
                    headers: memberAuthHeaders({ 'Content-Type': 'application/json', Accept: 'application/json' }),
                    credentials: 'same-origin',
                    body: JSON.stringify({ kod: kod, code: kod })
                })
                    .then(function(r) { return r.json().catch(function() { return null; }).then(function(data) { return { ok: r.ok, status: r.status, data: data }; }); })
                    .then(function(res) {
                        var data = res.data || {};
                        var msg = data.mesaj || data.message || t('promo.failed', 'İşlem yapılamadı.');
                        if (data.status === 'success' || data.success === true) {
                            profilePromoToast('success', msg || t('common.ok', 'Tamam'));
                            if (inputLegacy) {
                                inputLegacy.value = '';
                            }
                            bindProfilePromoUi();
                        } else {
                            profilePromoToast('error', msg);
                            bindProfilePromoUi();
                        }
                    })
                    .catch(function() {
                        profilePromoToast('error', t('common.try_again', 'Hata oluştu, lütfen tekrar deneyin.'));
                        bindProfilePromoUi();
                    });
                return;
            }

            var sel = document.getElementById('profileModalPromoSelect');
            var pid = sel && sel.value ? parseInt(sel.value, 10) : 0;
            if (!pid) {
                profilePromoToast('warn', 'Listeden bir promo kodu seçin.');
                return;
            }
            var noteEl = document.getElementById('profileModalPromoNote');
            var note = noteEl && noteEl.value ? String(noteEl.value).trim() : '';
            var body = { promocodeId: pid };
            if (note) body.message = note;

            talepBtn.disabled = true;
            fetch(apiUrl('/api/v2/promocode-request'), {
                method: 'POST',
                headers: memberAuthHeaders({ 'Content-Type': 'application/json', Accept: 'application/json' }),
                credentials: 'same-origin',
                body: JSON.stringify(body)
            })
                .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
                .then(function(res) {
                    var data = res.data || {};
                    if (data.success) {
                        var d = data.data || {};
                        var m = (typeof d.message === 'string' && d.message.trim()) ? d.message.trim()
                            : (data.message || t('promo.request_ok', 'Talebiniz alındı.'));
                        profilePromoToast('success', m);
                        if (noteEl) noteEl.value = '';
                        loadProfilePromocodesSelect();
                    } else {
                        profilePromoToast('error', data.message || 'Talep gönderilemedi.');
                    }
                })
                .catch(function() {
                    profilePromoToast('error', t('common.connection_error', 'Bağlantı hatası.'));
                })
                .finally(function() {
                    talepBtn.disabled = false;
                });
        });
    }

    function initAccountFreezePage() {
        var btn = document.getElementById('freezeSaveBtn');
        var pwd = document.getElementById('freeze_password');
        if (!btn || !pwd) {
            return;
        }
        btn.addEventListener('click', function () {
            var password = pwd.value ? String(pwd.value) : '';
            if (!password.trim()) {
                toastNotify('warning', 'Sifrenizi girin.');
                return;
            }
            btn.disabled = true;
            fetch(apiUrl('/api/v2/account-freeze'), {
                method: 'POST',
                headers: memberAuthHeaders({ 'Content-Type': 'application/json', Accept: 'application/json' }),
                credentials: 'same-origin',
                body: JSON.stringify({ password: password })
            })
                .then(function (r) {
                    return r.json().then(function (data) {
                        return { res: r, data: data };
                    });
                })
                .then(function (out) {
                    var data = out.data || {};
                    if (data.success) {
                        var d = data.data || {};
                        var loc = typeof d.redirect === 'string' && d.redirect.indexOf('/') === 0 ? d.redirect : '/login?account_frozen=1';
                        window.location.href = loc;
                        return;
                    }
                    var msg = data.message || 'İşlem yapılamadı.';
                    var inner = data.data || {};
                    var extra = '';
                    if (inner.errors && typeof inner.errors === 'object') {
                        var parts = [];
                        Object.keys(inner.errors).forEach(function (k) {
                            var v = inner.errors[k];
                            if (Array.isArray(v)) {
                                v.forEach(function (x) {
                                    parts.push(String(x));
                                });
                            } else if (v != null) {
                                parts.push(String(v));
                            }
                        });
                        extra = parts.filter(Boolean).join(' ');
                    }
                    if (extra !== '') {
                        msg = (msg + ' ' + extra).trim();
                    }
                    toastNotify('error', msg);
                })
                .catch(function () {
                    toastNotify('error', t('common.connection_error', 'Bağlantı hatası.'));
                })
                .finally(function () {
                    btn.disabled = false;
                });
        });
    }

    // ----- Hepsi: sayfa öğelerine göre ilgili initleri çalıştır -----
    ready(function() {
        initAppFeedbackDialog();
        initProfileBalanceToggleOnce();
        initProfilePromoBlockOnce();
        initProfileShellPrefetchOnce();
        /* Akordeon önce: bubble fazinda toggle + alt menü tıkları birlikte doğru çalışsın */
        if (document.querySelector('#profilePlayerSidebar') || document.querySelector('.u-i-profile-page-bc')) {
            initProfileSidebar();
            initProfileActiveBonus();
            schedulePrefetchAllProfileSidebarLinks();
        }
        var fullPageShell = document.querySelector('.centerWrap.porfileWrap');
        if (fullPageShell && fullPageShell.querySelector('#profilePlayerSidebar')) {
            bindProfileShellNav(fullPageShell);
        }
        initFullPageProfileShellPopstateOnce();
        initProfileBetHistorySubmenuPrefetchOnce();
        initProfileBetHistoryFormSpaOnce();
        initProfileMessagesSubmenuPrefetchOnce();
        initProfileMessagesInboxFilterFormSpaOnce();
        initProfileMessagesNewFormSpaOnce();
        initLoyaltyRedeemFormSpaOnce();
        initMemberInboxExpandOnce();
        if (document.querySelector('.js-inbox-item') && window.MemberInboxBadges) {
            window.MemberInboxBadges.applyUnreadToDom(document);
        }
        if (document.getElementById('profileModal')) initProfileModal();
        if (!isMobileSiteContext()) {
            bindCm622FormControls(document.getElementById('profilePlayerMain') || document.getElementById('profileModalContent') || document);
            initCm622MultiSelects(document.getElementById('profilePlayerMain') || document.getElementById('profileModalContent') || document);
        }
        if (document.getElementById('personalDetailsForm')) initDetailsPage();
        if (document.getElementById('changePwdBtn')) initAccountDetails();
        if (document.getElementById('twofaToggle')) initTwoFactorToggle();
        if (document.getElementById('bonusClaimsRoot')) initBonusClaimsMe();
        if (!isMobileSiteContext() && (document.getElementById('depositSection')
            || document.getElementById('withdrawSection')
            || document.getElementById('depositGrid')
            || document.getElementById('withdrawGrid'))) {
            initDepositWithdrawPage();
        }
        if (document.getElementById('transactionTableBody')) initDepositWithdrawHistory();
        if (document.querySelector('[data-casino-history-root]')) initCasinoGameHistory();
        if (document.getElementById('sporDetailsContent') || document.getElementById('gameHistoryContent')) initBetHistory();
        if (document.getElementById('copyReferralCode')) initReferences();
        if (document.querySelector('[data-profile-promo-block]')) {
            loadProfilePromocodesSelect();
        }
        if (document.getElementById('freezeSaveBtn')) {
            initAccountFreezePage();
        }
    });

    window.showAppFeedbackDialog = showAppFeedbackDialog;
})();
