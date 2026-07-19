/**
 * Oyun başlatma - bakiye seçim modalı.
 * assets/js/game-wallet-picker.js
 *
 * Kullanıcının aktif bir bonusu varsa, gerçek para modunda (mode=real) oyun
 * başlatılmadan önce "Ana Bakiye" / "Bonus Bakiye" seçtirir. Aktif bonus yoksa
 * (veya bu script yüklenmemişse) hiçbir davranış değişmez — doğrudan ana bakiye
 * ile başlatılır.
 */
(function (global) {
    'use strict';

    var Shared = global.BetcoAuthShared || {};

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
        if (typeof global.__USER_LOGGED_IN__ !== 'undefined') {
            return !!global.__USER_LOGGED_IN__;
        }
        return !!(Shared.getMemberJwt && Shared.getMemberJwt());
    }

    function getScrollLock() {
        if (global.__BodyScrollLock && typeof global.__BodyScrollLock.lock === 'function') {
            return global.__BodyScrollLock;
        }
        return { lock: function () {}, unlock: function () {} };
    }

    var overlay = null;
    var modalBox = null;
    var summaryEl = null;

    function ensureModal() {
        if (modalBox) return;

        overlay = document.createElement('div');
        overlay.className = 'wallet-picker-overlay';
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(8,5,18,.74);z-index:100000;display:none;align-items:center;justify-content:center;padding:16px;';

        modalBox = document.createElement('div');
        modalBox.className = 'wallet-picker-modal';
        modalBox.setAttribute('role', 'dialog');
        modalBox.setAttribute('aria-modal', 'true');
        modalBox.setAttribute('aria-label', 'Bakiye seçimi');
        modalBox.style.cssText = 'background:#17102b;border:1px solid rgba(255,255,255,.1);border-radius:16px;max-width:380px;width:100%;padding:22px;color:#fff;box-shadow:0 24px 64px rgba(0,0,0,.55);font-family:inherit;';
        modalBox.innerHTML =
            '<h3 style="margin:0 0 8px;font-size:17px;font-weight:700;">Hangi bakiye ile oynamak istersiniz?</h3>' +
            '<p class="wallet-picker-summary" style="margin:0 0 18px;font-size:13px;line-height:1.5;color:rgba(255,255,255,.68);"></p>' +
            '<div style="display:flex;flex-direction:column;gap:10px;">' +
            '<button type="button" data-wallet="main" style="padding:12px;border-radius:10px;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.06);color:#fff;font-weight:700;font-size:14px;cursor:pointer;">Ana Bakiye ile Oyna</button>' +
            '<button type="button" data-wallet="bonus" style="padding:12px;border-radius:10px;border:none;background:linear-gradient(135deg,#8a3ffb,#4c1fb0);color:#fff;font-weight:700;font-size:14px;cursor:pointer;">Bonus Bakiye ile Oyna</button>' +
            '</div>' +
            '<button type="button" data-wallet-cancel style="margin-top:14px;width:100%;background:none;border:none;color:rgba(255,255,255,.5);font-size:12px;cursor:pointer;">Vazgeç</button>';

        overlay.appendChild(modalBox);
        document.body.appendChild(overlay);
        summaryEl = modalBox.querySelector('.wallet-picker-summary');
    }

    function formatBonusSummary(bonus) {
        if (!bonus) {
            return 'Bonus bakiyeyi seçerseniz, ileride kazanacağınız bir bonusun çevrim şartına bahisleriniz işlenir. Aktif bonusunuz yoksa bu seçim bahsinizi etkilemez.';
        }
        var name = bonus.name || bonus.displayName || 'Aktif bonus';
        var remaining = typeof bonus.remainingBet === 'number' ? bonus.remainingBet : null;
        if (remaining !== null) {
            var remainingText = remaining.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            return name + ' bonusunuz aktif. Bonus bakiyeyi seçerseniz bahisleriniz bu bonusun çevrimine de işlenir (kalan: ' + remainingText + ' ₺).';
        }
        return name + ' bonusunuz aktif. Bonus bakiyeyi seçerseniz bahisleriniz bu bonusun çevrimine de işlenir.';
    }

    function showPicker(bonus) {
        return new Promise(function (resolve) {
            ensureModal();
            if (summaryEl) {
                summaryEl.textContent = formatBonusSummary(bonus);
            }
            var scrollLock = getScrollLock();
            overlay.style.display = 'flex';
            scrollLock.lock();

            function cleanup(result) {
                overlay.style.display = 'none';
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

    function fetchActiveBonus() {
        if (!isLoggedIn()) {
            return Promise.resolve(null);
        }
        return fetch(apiUrl('/api/v2/active-bonus'), {
            method: 'GET',
            credentials: 'same-origin',
            headers: memberAuthHeaders({ Accept: 'application/json' }),
            cache: 'no-store'
        })
            .then(function (res) { return res.json().catch(function () { return null; }); })
            .then(function (json) {
                if (json && json.success === true && json.data && json.data.hasActiveBonus) {
                    return json.data.bonus || {};
                }
                return null;
            })
            .catch(function () { return null; });
    }

    /**
     * Giriş yapılmışsa her gerçek para başlatmasında kullanıcıya ana/bonus
     * bakiye seçimini sorar (aktif bir bonusu olup olmadığına bakılmaksızın).
     * Giriş yapılmamışsa doğrudan 'main' döner. Kullanıcı iptal ederse null
     * döner (çağıran taraf oyunu başlatmamalı).
     * @returns {Promise<'main'|'bonus'|null>}
     */
    function resolveWalletChoice() {
        if (!isLoggedIn()) {
            return Promise.resolve('main');
        }
        return fetchActiveBonus().then(function (bonus) {
            return showPicker(bonus);
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

    /**
     * Gerçek para modundaki (mode=real) /play URL'lerini bakiye seçim modalıyla
     * açar. Demo/fun modundaki URL'ler doğrudan navigateFn'e geçer.
     * @param {string} url
     * @param {(finalUrl: string) => void} navigateFn
     */
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
