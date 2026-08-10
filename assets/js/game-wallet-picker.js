/**
 * Oyun başlatma - bakiye seçim modalı.
 * assets/js/game-wallet-picker.js
 *
 * Görünüm: para çekim CM622 kartı; ikon/içerik bakiye seçimine özel.
 * Gerçek para modunda Ana / Bonus bakiye seçtirir.
 */
(function (global) {
    'use strict';

    var Shared = global.BetcoAuthShared || {};

    function t(key, fallback) {
        var bag = global.__I18N__;
        if (bag && typeof bag === 'object' && Object.prototype.hasOwnProperty.call(bag, key) && bag[key] != null && bag[key] !== '') {
            return String(bag[key]);
        }
        return fallback != null ? String(fallback) : key;
    }

    function apiUrl(path) {
        return Shared.apiUrl ? Shared.apiUrl(path) : path;
    }

    function memberAuthHeaders(extra) {
        if (Shared.memberAuthHeaders) {
            return Shared.memberAuthHeaders(extra);
        }
        var headers = extra || {};
        var csrf = (global.__CSRF_TOKEN__ || '').trim();
        if (csrf) headers['X-CSRF-Token'] = csrf;
        return headers;
    }

    function isLoggedIn() {
        if (Shared && typeof Shared.runtimeSessionLoggedIn === 'function') {
            return Shared.runtimeSessionLoggedIn();
        }
        if (global.__USER_LOGGED_IN__ === true || global.__HAS_MEMBER_JWT__ === true) {
            return true;
        }
        if (typeof global.__MEMBER_JWT_BOOTSTRAP__ === 'string' && global.__MEMBER_JWT_BOOTSTRAP__.trim() !== '') {
            return true;
        }
        return !!(Shared.getMemberJwt && Shared.getMemberJwt() !== '');
    }

    function getScrollLock() {
        if (global.__BodyScrollLock && typeof global.__BodyScrollLock.lock === 'function') {
            return global.__BodyScrollLock;
        }
        return { lock: function () {}, unlock: function () {} };
    }

    function money(n) {
        var v = Number(n);
        if (!isFinite(v)) v = 0;
        var loc = (typeof global.__INTL_LOCALE__ === 'string' && global.__INTL_LOCALE__) ? global.__INTL_LOCALE__ : 'tr-TR';
        return v.toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    var overlay = null;
    var modalBox = null;
    var summaryEl = null;
    var mainBtn = null;
    var bonusBtn = null;

    function ensureModal() {
        if (modalBox) return;

        overlay = document.createElement('div');
        overlay.className = 'app-feedback-dialog-overlay wallet-picker-overlay';
        overlay.setAttribute('aria-hidden', 'true');

        modalBox = document.createElement('div');
        modalBox.className = 'app-feedback-dialog app-feedback-dialog--cm622 wallet-picker-dialog';
        modalBox.setAttribute('role', 'dialog');
        modalBox.setAttribute('aria-modal', 'true');
        modalBox.setAttribute('aria-labelledby', 'walletPickerTitle');
        modalBox.setAttribute('aria-hidden', 'true');
        modalBox.innerHTML =
            '<div class="app-feedback-dialog__card wallet-picker-card">'
            + '  <button type="button" class="app-feedback-dialog__dismiss" data-wallet-cancel aria-label="' + escapeHtml(t('common.cancel', 'Kapat')) + '">&times;</button>'
            + '  <div class="app-feedback-dialog__icon-wrap wallet-picker-icon" aria-hidden="true"><i class="bc-i-wallet"></i></div>'
            + '  <h2 class="app-feedback-dialog__title" id="walletPickerTitle">' + escapeHtml(t('wallet.title', 'BAKIYE SEÇİMİ')) + '</h2>'
            + '  <div class="wallet-picker-summary" role="status"></div>'
            + '  <div class="wallet-picker-actions">'
            + '    <button type="button" class="app-feedback-dialog__primary wallet-picker-btn" data-wallet="main">' + escapeHtml(t('wallet.main', 'Ana Bakiye')) + '</button>'
            + '    <button type="button" class="app-feedback-dialog__primary wallet-picker-btn" data-wallet="bonus">' + escapeHtml(t('wallet.bonus', 'Bonus Bakiye')) + '</button>'
            + '  </div>'
            + '  <button type="button" class="wallet-picker-cancel" data-wallet-cancel>' + escapeHtml(t('wallet.cancel', 'Vazgeç')) + '</button>'
            + '</div>';

        document.body.appendChild(overlay);
        document.body.appendChild(modalBox);
        summaryEl = modalBox.querySelector('.wallet-picker-summary');
        mainBtn = modalBox.querySelector('[data-wallet="main"]');
        bonusBtn = modalBox.querySelector('[data-wallet="bonus"]');
    }

    function setButtonState(btn, enabled, label) {
        if (!btn) return;
        btn.disabled = !enabled;
        btn.textContent = label;
        btn.classList.toggle('is-disabled', !enabled);
        btn.setAttribute('aria-disabled', enabled ? 'false' : 'true');
    }

    function summaryRow(label, value, tone) {
        return ''
            + '<div class="wallet-picker-row' + (tone ? ' wallet-picker-row--' + tone : '') + '">'
            + '  <span class="wallet-picker-row__label">' + escapeHtml(label) + '</span>'
            + '  <span class="wallet-picker-row__value">' + escapeHtml(value) + '</span>'
            + '</div>';
    }

    function formatSummaryHtml(balances, bonus) {
        var mainBal = Number(balances && balances.main) || 0;
        var bonusBal = Number(balances && balances.bonus) || 0;
        var html = '<div class="wallet-picker-panel">';

        html += summaryRow(t('wallet.main_label', 'Ana bakiye'), money(mainBal) + ' ₺', 'main');
        html += summaryRow(t('wallet.bonus_label', 'Bonus bakiye'), money(bonusBal) + ' ₺', 'bonus');

        if (bonus) {
            var name = bonus.name || bonus.displayName || t('wallet.bonus', 'Aktif bonus');
            var remaining = typeof bonus.remainingBet === 'number' ? bonus.remainingBet : null;
            if (remaining !== null) {
                html += summaryRow(name + ' çevrimi kalan', money(remaining) + ' ₺', 'wager');
            } else {
                html += '<p class="wallet-picker-note">' + escapeHtml(t('wallet.bonus_wager_note', 'Bonus seçerseniz çevrime işlenir.')) + '</p>';
            }
        } else if (mainBal <= 0 && bonusBal <= 0) {
            html += '<p class="wallet-picker-note">' + escapeHtml(t('wallet.empty_both', 'Oynamak için bakiyenizi yükleyin.')) + '</p>';
        } else if (mainBal <= 0 && bonusBal > 0) {
            html += '<p class="wallet-picker-note">' + escapeHtml(t('wallet.empty_main', 'Ana bakiyeniz boş. Bonus bakiye ile devam edin.')) + '</p>';
        } else if (bonusBal <= 0 && mainBal > 0) {
            html += '<p class="wallet-picker-note">' + escapeHtml(t('wallet.empty_bonus', 'Bonus bakiyeniz boş. Ana bakiye ile devam edin.')) + '</p>';
        } else {
            html += '<p class="wallet-picker-note">' + escapeHtml(t('wallet.choose', 'Hangi bakiye ile oynamak istersiniz?')) + '</p>';
        }

        html += '</div>';
        return html;
    }

    function openUi() {
        overlay.classList.add('is-open');
        modalBox.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        modalBox.setAttribute('aria-hidden', 'false');
    }

    function closeUi() {
        overlay.classList.remove('is-open');
        modalBox.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        modalBox.setAttribute('aria-hidden', 'true');
    }

    function showPicker(balances, bonus) {
        return new Promise(function (resolve) {
            ensureModal();
            var mainBal = Number(balances && balances.main) || 0;
            var bonusBal = Number(balances && balances.bonus) || 0;
            var mainOk = mainBal > 0;
            var bonusOk = bonusBal > 0;

            setButtonState(mainBtn, mainOk, t('wallet.main', 'Ana Bakiye') + ' (' + money(mainBal) + ' ₺)');
            setButtonState(bonusBtn, bonusOk, t('wallet.bonus', 'Bonus Bakiye') + ' (' + money(bonusBal) + ' ₺)');

            if (summaryEl) {
                summaryEl.innerHTML = formatSummaryHtml(balances, bonus);
            }

            if (mainOk && !bonusOk) {
                resolve('main');
                return;
            }
            if (bonusOk && !mainOk) {
                resolve('bonus');
                return;
            }
            if (!mainOk && !bonusOk) {
                resolve(null);
                return;
            }

            var scrollLock = getScrollLock();
            openUi();
            scrollLock.lock();

            function cleanup(result) {
                closeUi();
                scrollLock.unlock();
                overlay.removeEventListener('click', onOverlayClick);
                modalBox.removeEventListener('click', onModalClick);
                document.removeEventListener('keydown', onKeydown);
                resolve(result);
            }
            function onOverlayClick(e) {
                if (e.target === overlay) {
                    cleanup(null);
                }
            }
            function onModalClick(e) {
                var target = e.target;
                var btn = target && target.closest ? target.closest('[data-wallet], [data-wallet-cancel]') : null;
                if (!btn) return;
                if (btn.hasAttribute('data-wallet-cancel')) {
                    cleanup(null);
                    return;
                }
                if (btn.disabled || btn.classList.contains('is-disabled')) return;
                cleanup(btn.getAttribute('data-wallet'));
            }
            function onKeydown(e) {
                if (e.key === 'Escape') {
                    cleanup(null);
                }
            }

            overlay.addEventListener('click', onOverlayClick);
            modalBox.addEventListener('click', onModalClick);
            document.addEventListener('keydown', onKeydown);
        });
    }

    function fetchJson(path) {
        return fetch(apiUrl(path), {
            method: 'GET',
            credentials: 'same-origin',
            headers: memberAuthHeaders({ Accept: 'application/json' }),
            cache: 'no-store'
        }).then(function (res) {
            return res.json().catch(function () { return null; });
        }).catch(function () { return null; });
    }

    function fetchActiveBonus() {
        if (!isLoggedIn()) {
            return Promise.resolve(null);
        }
        return fetchJson('/api/v2/active-bonus').then(function (json) {
            if (json && json.success === true && json.data && json.data.hasActiveBonus) {
                return json.data.bonus || {};
            }
            return null;
        });
    }

    function fetchBalances() {
        if (!isLoggedIn()) {
            return Promise.resolve({ main: 0, bonus: 0 });
        }
        return fetchJson('/api/v2/balance').then(function (json) {
            var data = (json && json.data) || {};
            var nested = data.balance && typeof data.balance === 'object' ? data.balance : data;
            var main = Number(nested.balance ?? data.amount ?? data.ana_bakiye ?? 0);
            var bonus = Number(nested.bonus_balance ?? data.bonus_balance ?? data.bonus_bakiye ?? 0);
            if (!isFinite(main)) main = 0;
            if (!isFinite(bonus)) bonus = 0;
            return { main: main, bonus: bonus };
        }).catch(function () {
            return { main: 0, bonus: 0 };
        });
    }

    function resolveWalletChoice() {
        if (!isLoggedIn()) {
            return Promise.resolve('main');
        }
        return Promise.all([fetchBalances(), fetchActiveBonus()]).then(function (parts) {
            return showPicker(parts[0] || { main: 0, bonus: 0 }, parts[1] || null);
        });
    }

    function patchWalletParam(url, wallet) {
        var target = String(url || '');
        try {
            var parsed = new URL(target, global.location.origin);
            parsed.searchParams.set('wallet', wallet);
            return parsed.pathname + parsed.search + parsed.hash;
        } catch (e) {
            if (/([?&])wallet=[^&]*/.test(target)) {
                return target.replace(/([?&])wallet=[^&]*/, '$1wallet=' + encodeURIComponent(wallet));
            }
            return target + (target.indexOf('?') === -1 ? '?' : '&') + 'wallet=' + encodeURIComponent(wallet);
        }
    }

    function launch(url, navigateFn) {
        var target = String(url || '');
        var isRealMode = /(\?|&)mode=real(&|$)/.test(target);
        if (!isRealMode) {
            navigateFn(target);
            return;
        }
        resolveWalletChoice().then(function (wallet) {
            if (!wallet) {
                return;
            }
            navigateFn(patchWalletParam(target, wallet));
        });
    }

    global.MaltabetWalletPicker = {
        resolveWalletChoice: resolveWalletChoice,
        launch: launch
    };
})(window);
