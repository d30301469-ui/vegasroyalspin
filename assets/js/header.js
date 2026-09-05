/**
 * @dynamic-file
 */
// Tüm header JavaScript fonksiyonları
(function () {
    'use strict';

    // Smart panel artık header.js initSmartPanel tarafından yönetiliyor (onclick attr. kaldırıldı)

    var MENU_CLOSE_DELAY_MS = 100;
    /** Profil menüsü: mouseleave sonrası kapanma gecikmesi */
    var PLAYER_MENU_CLOSE_DELAY_MS = 300;
    var RESIZE_DEBOUNCE_MS = 150;
    var Shared = window.BetcoAuthShared || {};
    function resolveShared() {
        return window.BetcoAuthShared || Shared || {};
    }
    function readStoredMemberJwt() {
        try {
            return String(window.localStorage.getItem('app_member_jwt') || '').trim();
        } catch (eJwt) {
            return '';
        }
    }
    function apiUrl(path) {
        var dynamicShared = resolveShared();
        return dynamicShared.apiUrl ? dynamicShared.apiUrl(path) : path;
    }

    /** Hover menüleri: dışarıdan kapatırken scheduleClose zamanlayıcılarını temizlemek için */
    var headerMenuTimers = { deposit: null, player: null };

    /**
     * Masaüstü header: profil, bakiye/cüzdan, smart menü, dil ve arama birbirini dışlasın.
     * @param {string} except — 'deposit' | 'player' | 'smart' | 'lang' | 'search'
     */
    function closeAllHeaderFlyouts(except) {
        except = except || '';
        if (headerMenuTimers.deposit) {
            clearTimeout(headerMenuTimers.deposit);
            headerMenuTimers.deposit = null;
        }
        if (headerMenuTimers.player) {
            clearTimeout(headerMenuTimers.player);
            headerMenuTimers.player = null;
        }

        if (except !== 'deposit') {
            var depNav = document.getElementById('depositNav');
            var depTrigger = document.getElementById('balanceTrigger');
            if (depNav) depNav.classList.add('hidesection');
            if (depTrigger) depTrigger.setAttribute('aria-expanded', 'false');
        }
        if (except !== 'player') {
            var pNav = document.getElementById('playerNav');
            var pBtn = document.getElementById('toggleButton');
            if (pNav) pNav.classList.add('hidesection');
            if (pBtn) pBtn.setAttribute('aria-expanded', 'false');
        }
        if (except !== 'smart') {
            if (typeof window.__closeSmartPanel === 'function') {
                window.__closeSmartPanel();
            } else {
                var spPanel     = document.getElementById('smartPanelFixed');
                var smartToggle = document.getElementById('smart-panel-holder');
                if (spPanel)     { spPanel.classList.remove('is-open');   spPanel.setAttribute('aria-hidden', 'true'); }
                if (smartToggle) { smartToggle.classList.remove('is-open'); smartToggle.setAttribute('aria-expanded', 'false'); }
            }
        }
        if (except !== 'lang') {
            var langWrap = document.getElementById('langDropdown');
            if (langWrap) {
                var lt = langWrap.querySelector('.dropdown-toggle');
                var lm = langWrap.querySelector('.dropdown-menu');
                langWrap.classList.remove('show');
                if (lm) lm.classList.remove('show');
                if (lt) lt.setAttribute('aria-expanded', 'false');
            }
        }
        if (except !== 'search') {
            var searchOverlay = document.getElementById('searchOverlay');
            var searchPanel = document.getElementById('searchPanel');
            var searchBtn = document.getElementById('headerSearchBtn');
            if (searchPanel && searchPanel.classList.contains('is-open')) {
                if (searchOverlay) {
                    searchOverlay.classList.remove('is-open');
                    searchOverlay.setAttribute('aria-hidden', 'true');
                }
                searchPanel.classList.remove('is-open');
                searchPanel.setAttribute('aria-hidden', 'true');
                if (searchBtn) searchBtn.setAttribute('aria-expanded', 'false');
                document.body.style.overflow = '';
            }
        }
    }
    window.__closeAllHeaderFlyouts = closeAllHeaderFlyouts;

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function debounce(fn, ms) {
        var tid;
        return function () {
            if (tid) clearTimeout(tid);
            var self = this, args = arguments;
            tid = setTimeout(function () {
                tid = null;
                fn.apply(self, args);
            }, ms);
        };
    }

    function safeLog(msg, err) {
        if (typeof window !== 'undefined' && window.__MEMBER_API_CONSOLE__ !== true) {
            return;
        }
        if (typeof console !== 'undefined' && console.error) {
            console.error(msg, err !== undefined ? err : '');
        }
    }

    function initToastr() {
        if (typeof toastr === 'undefined') return;
        toastr.options = {
            closeButton: true,
            debug: false,
            newestOnTop: true,
            progressBar: true,
            positionClass: 'toast-top-right',
            preventDuplicates: false,
            onclick: null,
            showDuration: '300',
            hideDuration: '1000',
            timeOut: '5000',
            extendedTimeOut: '1000',
            showEasing: 'swing',
            hideEasing: 'linear',
            showMethod: 'fadeIn',
            hideMethod: 'fadeOut'
        };
    }

    function redirectToDeposit() {
        var target = '/profile/deposit?openDepositPanel=1';
        if (Shared.ensureSessionForPage && !Shared.ensureSessionForPage(target)) {
            return;
        }
        // Header'daki "CÜZDANA BAĞLAN" tıklanınca deposit paneli otomatik açılsın.
        if (typeof window.__openProfileModalUrl === 'function' && window.__openProfileModalUrl(target)) {
            return;
        }
        window.location.href = target;
    }
    window.redirectToDeposit = redirectToDeposit;

    function initDepositMenu() {
        if (document.body.classList.contains('mobile-site')) return;
        var wrap = document.getElementById('depositBalanceWrap');
        var trigger = document.getElementById('balanceTrigger');
        var nav = document.getElementById('depositNav');
        if (!wrap || !nav) return;

        var GAP = 4;

        function positionMenu() {
            var rect = wrap.getBoundingClientRect();
            nav.style.top  = (rect.bottom + GAP) + 'px';
            nav.style.right = (window.innerWidth - rect.right) + 'px';
            nav.style.left  = 'auto';
        }

        function show() {
            if (headerMenuTimers.deposit) {
                clearTimeout(headerMenuTimers.deposit);
                headerMenuTimers.deposit = null;
            }
            closeAllHeaderFlyouts('deposit');
            nav.classList.remove('hidesection');
            positionMenu();
            if (trigger) trigger.setAttribute('aria-expanded', 'true');
        }

        function hide() {
            nav.classList.add('hidesection');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
        }

        function scheduleClose() {
            clearTimeout(headerMenuTimers.deposit);
            headerMenuTimers.deposit = setTimeout(function () {
                headerMenuTimers.deposit = null;
                hide();
            }, MENU_CLOSE_DELAY_MS);
        }

        /* Tüm wrap alanı (CÜZDANA BAĞLAN + bakiye) üzerine hover */
        wrap.addEventListener('mouseenter', show);
        wrap.addEventListener('mouseleave', function (e) {
            if (!nav.contains(e.relatedTarget)) scheduleClose();
        });
        nav.addEventListener('mouseenter', function () {
            if (headerMenuTimers.deposit) {
                clearTimeout(headerMenuTimers.deposit);
                headerMenuTimers.deposit = null;
            }
        });
        nav.addEventListener('mouseleave', function (e) {
            if (!wrap.contains(e.relatedTarget)) scheduleClose();
        });

        /* Masaüstünde bakiye ikonu dropdown açar; modal akışı dropdown linklerinden ilerler. */
        if (trigger) {
            trigger.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                nav.classList.contains('hidesection') ? show() : hide();
            });
            trigger.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    nav.classList.contains('hidesection') ? show() : hide();
                }
            });
        }

        window.addEventListener('resize', debounce(function () {
            if (!nav.classList.contains('hidesection')) positionMenu();
        }, RESIZE_DEBOUNCE_MS));

        document.addEventListener('click', function (e) {
            if (wrap.contains(e.target) || nav.contains(e.target)) return;
            hide();
        });

        /* Dropdown linkleri */
        nav.querySelectorAll('a.depositNav-link').forEach(function (a) {
            a.addEventListener('click', function () {
                hide();
            });
        });
    }

    function initPlayerMenu() {
        if (document.body.classList.contains('mobile-site')) return;
        var btn = document.getElementById('toggleButton');
        var col = document.getElementById('playerCol') || (btn && btn.closest('.playerCol')) || (btn && btn.closest('.user-nav-icon'));
        var hoverZone = (btn && btn.closest('.profileDetails')) || col;
        var nav = document.getElementById('playerNav');
        if (!btn || !nav || !hoverZone) return;

        var GAP = 4;

        function positionNav() {
            var rect = hoverZone.getBoundingClientRect();
            nav.style.top  = (rect.bottom + GAP) + 'px';
            nav.style.right = (window.innerWidth - rect.right) + 'px';
            nav.style.left = 'auto';
        }

        function show() {
            if (headerMenuTimers.player) {
                clearTimeout(headerMenuTimers.player);
                headerMenuTimers.player = null;
            }
            closeAllHeaderFlyouts('player');
            positionNav();
            nav.classList.remove('hidesection');
            btn.setAttribute('aria-expanded', 'true');
        }

        function hide() {
            nav.classList.add('hidesection');
            btn.setAttribute('aria-expanded', 'false');
        }

        function scheduleClose() {
            clearTimeout(headerMenuTimers.player);
            headerMenuTimers.player = setTimeout(function () {
                headerMenuTimers.player = null;
                hide();
            }, PLAYER_MENU_CLOSE_DELAY_MS);
        }

        hoverZone.addEventListener('mouseenter', show);
        hoverZone.addEventListener('mouseleave', function (e) {
            if (!nav.contains(e.relatedTarget)) scheduleClose();
        });
        nav.addEventListener('mouseenter', function () {
            if (headerMenuTimers.player) {
                clearTimeout(headerMenuTimers.player);
                headerMenuTimers.player = null;
            }
        });
        nav.addEventListener('mouseleave', function (e) {
            if (!hoverZone.contains(e.relatedTarget)) scheduleClose();
        });
        window.addEventListener('resize', debounce(function () {
            if (!nav.classList.contains('hidesection')) positionNav();
        }, 150));
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            nav.classList.contains('hidesection') ? show() : hide();
        });
        btn.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                e.stopPropagation();
                nav.classList.contains('hidesection') ? show() : hide();
            }
        });
        document.addEventListener('click', function (e) {
            if (hoverZone.contains(e.target) || nav.contains(e.target)) return;
            hide();
        });
    }

    function initTurkeyTime() {
        var el = document.getElementById('turkeyTime');
        if (!el) return;

        var tid = null;
        var INTERVAL = 1000;
        var opts = { timeZone: 'Europe/Istanbul', hour12: false };

        function tick() {
            el.textContent = new Date().toLocaleTimeString('tr-TR', opts);
        }

        function startInterval() {
            if (!tid) tid = setInterval(tick, INTERVAL);
        }

        function stopInterval() {
            if (tid) clearInterval(tid), tid = null;
        }

        tick();
        startInterval();
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) stopInterval();
            else { tick(); startInterval(); }
        });
    }

    function bonusKoduKullan() {
        if (typeof Swal === 'undefined') return;
        Swal.fire({
            title: (window.__ ? window.__('promo.enter_bonus_title', 'Bonus Kodunuzu Girin') : 'Bonus Kodunuzu Girin'),
            input: 'text',
            inputLabel: (window.__ ? window.__('promo.bonus_code', 'Bonus Kodu') : 'Bonus Kodu'),
            inputPlaceholder: (window.__ ? window.__('promo.enter_code', 'Kodu buraya girin') : 'Kodu buraya girin'),
            showCancelButton: true,
            confirmButtonText: (window.__ ? window.__('profile.promo_apply', 'Kullan') : 'Kullan'),
            cancelButtonText: (window.__ ? window.__('promo.cancel', 'İptal') : 'İptal')
        }).then(function (result) {
            if (!result.isConfirmed) return;
            var kod = result.value;
            fetch(apiUrl('/api/v2/bonus/use-code'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: Shared.memberAuthHeaders
                    ? Shared.memberAuthHeaders({ 'Content-Type': 'application/json' })
                    : { 'Content-Type': 'application/json' },
                body: JSON.stringify({ kod: kod })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var msg = data.mesaj || data.message || (window.__ ? window.__('promo.failed', 'İşlem tamamlanamadı.') : 'İşlem tamamlanamadı.');
                    if (data.status === 'success' || data.success === true) {
                        window.MaltabetToast ? MaltabetToast.success(msg, (window.__ ? window.__('common.success', 'Başarılı') : 'Başarılı')) : alert(msg);
                    } else {
                        window.MaltabetToast ? MaltabetToast.error(msg, (window.__ ? window.__('common.error', 'Hata') : 'Hata')) : alert(msg);
                    }
                })
                .catch(function (err) {
                    if (window.MaltabetToast) MaltabetToast.error((window.__ ? window.__('common.try_again', 'Hata oluştu, lütfen tekrar deneyin.') : 'Hata oluştu, lütfen tekrar deneyin.'), (window.__ ? window.__('common.error', 'Hata') : 'Hata'));
                    else alert('Hata oluştu, lütfen tekrar deneyin.');
                    safeLog('Bonus API:', err);
                });
        });
    }
    window.bonusKoduKullan = bonusKoduKullan;

    // Oyun açma fonksiyonu
    function launchGameUrl(url) {
        var isMobileSite = !!(document.body && document.body.classList.contains('mobile-site'));
        if (!isMobileSite) {
            var hasTouch = (navigator.maxTouchPoints || 0) > 0;
            var narrowViewport = !!(window.matchMedia && window.matchMedia('(max-width: 1024px)').matches);
            isMobileSite = hasTouch && narrowViewport;
        }
        if (isMobileSite) {
            try {
                var parsed = new URL(url, window.location.origin);
                parsed.searchParams.set('open_mode', 'redirect');
                url = parsed.pathname + parsed.search + parsed.hash;
            } catch (e) {
                url += (url.indexOf('?') === -1 ? '?' : '&') + 'open_mode=redirect';
            }
        }
        window.location.href = url;
    }
    function openGame(gameId) {
        var url = '/play?game_id=' + encodeURIComponent(gameId).replace(/%3A/gi, ':') + '&mode=real&wallet=main';
        if (window.MaltabetWalletPicker && typeof window.MaltabetWalletPicker.launch === 'function') {
            window.MaltabetWalletPicker.launch(url, launchGameUrl);
            return;
        }
        launchGameUrl(url);
    }
    window.openGame = openGame;

    var LANG_CODES = { en: 'ENG', de: 'DEU', ru: 'RUS', ar: 'ARB', tr: 'TUR' };
    var LANG_FLAGS = { en: 'flag-icon-us', de: 'flag-icon-de', ru: 'flag-icon-ru', ar: 'flag-icon-sa', tr: 'flag-icon-tr' };

    function readCookie(name) {
        var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : '';
    }

    function resolveSiteLocale() {
        var allowed = /^(tr|en|de|ru)$/i;
        if (window.__LOCALE__ && allowed.test(String(window.__LOCALE__))) {
            return String(window.__LOCALE__).toLowerCase();
        }
        var fromQuery = (new URLSearchParams(window.location.search)).get('lang');
        if (fromQuery && allowed.test(fromQuery)) {
            return String(fromQuery).toLowerCase();
        }
        var fromCookie = readCookie('site_lang');
        if (fromCookie && allowed.test(fromCookie)) {
            return String(fromCookie).toLowerCase();
        }
        return 'tr';
    }

    function initLangDropdown() {
        var wrap = document.getElementById('langDropdown');
        if (!wrap) return;
        var toggle = wrap.querySelector('.dropdown-toggle');
        var menu = wrap.querySelector('.dropdown-menu');
        if (!toggle || !menu) return;

        function setOpen(open) {
            wrap.classList.toggle('show', open);
            menu.classList.toggle('show', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var willOpen = !wrap.classList.contains('show');
            if (willOpen) closeAllHeaderFlyouts('lang');
            setOpen(willOpen);
        });
        document.addEventListener('click', function (e) {
            if (!wrap.contains(e.target)) setOpen(false);
        });
    }

    function initLangCodeDisplay() {
        var lang = resolveSiteLocale();
        var codeSpan = document.querySelector('.langSelect .lang-code, .langSelect [data-lang-code]');
        if (codeSpan) {
            codeSpan.textContent = LANG_CODES[lang] || 'TUR';
        }
        var flagSpan = document.querySelector('.langSelect .dropdown-toggle .flag-icon, .langSelect [data-lang-flag]');
        if (flagSpan) {
            flagSpan.className = 'flag-icon ' + (LANG_FLAGS[lang] || 'flag-icon-tr');
            if (flagSpan.hasAttribute('data-lang-flag')) {
                flagSpan.setAttribute('data-lang-flag', lang);
            }
        }
        var wrap = document.getElementById('langDropdown');
        if (wrap) {
            wrap.setAttribute('data-locale', lang);
        }
    }

    function runtimeLoggedIn() {
        var Shared = window.BetcoAuthShared || {};
        if (Shared.runtimeSessionLoggedIn) {
            return Shared.runtimeSessionLoggedIn();
        }
        if (window.__MEMBER_BOOTSTRAP_STATE__ && typeof window.__MEMBER_BOOTSTRAP_STATE__ === 'object') {
            return window.__MEMBER_BOOTSTRAP_STATE__.logged_in === true;
        }
        return window.__USER_LOGGED_IN__ === true;
    }

    function desktopLoyaltyBadgeMarkup() {
        return ''
            + '<a class="loyaltyBonusHeader hasLoyaltyLevel"'
            + ' href="/profile/sadakat-puanlari"'
            + ' data-profile-modal-href="/profile/sadakat-puanlari"'
            + ' data-nav-mode="modal"'
            + ' title="Bronze"'
            + ' data-loyalty-badge'
            + ' data-loyalty-code="bronze"'
            + ' aria-label="Sadakat / Royalty">'
            + '  <p class="loyaltyBonusHeaderShadow" aria-hidden="true"></p>'
            + '  <p class="loyaltyBonusHeaderBackground" aria-hidden="true"></p>'
            + '  <span class="loyaltyBonusHeaderText ellipsis" data-loyalty-level-name>BRONZE</span>'
            + '  <img class="loyaltyBonusImg" src="/assets/images/loyalty/badges/bronze.svg" alt="" width="20" height="20" loading="lazy" data-loyalty-level-icon onerror="this.style.display=\'none\'">'
            + '</a>';
    }

    function ensureDesktopLoyaltyBadge() {
        if (document.body.classList.contains('mobile-site')) {
            return;
        }
        if (!runtimeLoggedIn()) {
            return;
        }
        var loginCol = document.querySelector('.headBar .loginCol.hdr-user-bc, .header-bc .loginCol.hdr-user-bc');
        if (!loginCol) {
            return;
        }
        if (loginCol.querySelector('.loyaltyBonusHeader, [data-loyalty-badge]')) {
            return;
        }
        var wrap = document.createElement('div');
        wrap.innerHTML = desktopLoyaltyBadgeMarkup();
        var badge = wrap.firstElementChild;
        if (!badge) {
            return;
        }
        loginCol.insertBefore(badge, loginCol.firstChild);
        if (typeof window.__refreshHeaderBalance === 'function') {
            try { window.__refreshHeaderBalance(); } catch (eRefresh) { /* ignore */ }
        }
    }

    function desktopUserNavMarkup() {
        return ''
            + '<div class="nav-menu-container header-user-nav">'
            + '  <ul class="nav-menu-other hdr-balance-nav">'
            + '    <li id="depositBalanceWrap">'
            + '      <a href="/profile/deposit?openDepositPanel=1" class="nav-menu-item hdr-balance-trigger" id="balanceTrigger" role="button" aria-expanded="false" aria-haspopup="true">'
            + '        <div class="hdr-user-info-content-bc"><span class="hdr-user-info-texts-bc ext-1 ellipsis"><span class="balanceAmount"><span id="headerBalanceMain" data-balance-target="headerBalanceMain">0</span><span class="currencySymbol">&#8239;₺</span></span></span></div>'
            + '      </a>'
            + '    </li>'
            + '  </ul>'
            + '  <ul class="nav-menu-other profileDetails">'
            + '    <li><div class="user-nav-icon playerCol" id="playerCol">'
            + '      <button class="userBtn nav-menu-item" id="toggleButton" type="button" aria-expanded="false" aria-label="' + (window.__ ? window.__('nav.profile_menu', 'Profil menüsü') : 'Profil menüsü') + '">'
            + '        <span class="avatarHolderImg"><i class="bc-i-user hdr-user-avatar-icon-bc" aria-hidden="true"></i></span>'
            + '      </button>'
            + '      <div class="playerNav hidesection" id="playerNav" role="menu">'
            + '        <div class="playerNav-body">'
            + '          <a class="pl-link" href="#" id="profileLinkModal" role="menuitem"><i class="pl-link-icon bc-i-user" aria-hidden="true"></i> ' + (window.__ ? window.__('nav.my_profile', 'PROFİLİM') : 'PROFİLİM') + '</a>'
            + '          <a class="pl-link" href="/profile/deposit" data-nav-mode="modal" role="menuitem"><i class="pl-link-icon bc-i-deposit" aria-hidden="true"></i> ' + (window.__ ? window.__('nav.balance_management', 'BAKİYE YÖNETİMİ') : 'BAKİYE YÖNETİMİ') + '</a>'
            + '          <a class="pl-link" href="/profile/bet-history" data-nav-mode="modal" role="menuitem"><i class="pl-link-icon bc-i-bet-history" aria-hidden="true"></i> ' + (window.__ ? window.__('nav.bet_history', 'BAHİS GEÇMİŞİ') : 'BAHİS GEÇMİŞİ') + '</a>'
            + '          <a class="pl-link" href="/profile/bonus-spor" data-nav-mode="modal" role="menuitem"><i class="pl-link-icon bc-i-promotions-3" aria-hidden="true"></i> ' + (window.__ ? window.__('nav.bonuses', 'BONUSLAR') : 'BONUSLAR') + '</a>'
            + '          <a class="pl-link" href="/profile/messages" data-nav-mode="modal" role="menuitem"><i class="pl-link-icon bc-i-message" aria-hidden="true"></i> ' + (window.__ ? window.__('nav.messages', 'MESAJLAR') : 'MESAJLAR') + '</a>'
            + '        </div>'
            + '        <div class="playerNav-footer">'
            + '          <a class="pl-link pl-link-logout" href="/logout" data-nav-mode="page" role="menuitem"><i class="pl-link-icon bc-i-logout" aria-hidden="true"></i> ' + (window.__ ? window.__('nav.logout', 'ÇIKIŞ YAP') : 'ÇIKIŞ YAP') + '</a>'
            + '        </div>'
            + '      </div>'
            + '    </div></li>'
            + '  </ul>'
            + '</div>';
    }

    function upgradeGuestHeaderIfNeeded() {
        if (!runtimeLoggedIn()) {
            return false;
        }

        var guestLoginBtn = document.getElementById('Giris');
        var guestRegisterBtn = document.getElementById('openRegister');
        if (!guestLoginBtn && !guestRegisterBtn) {
            ensureDesktopLoyaltyBadge();
            return false;
        }

        if (document.body.classList.contains('mobile-site')) {
            // Mobil başlık işaretleme/bağlama tamamen mobile/assets/js/mobile-header.js
            // dosyasına ait — masaüstü header.js bu mantığı barındırmaz, sadece tetikler.
            // Böylece mobil ve masaüstü akışları arasında hiçbir kod paylaşımı/çakışma olmaz.
            if (typeof window.__mobileUpgradeUserHeader === 'function') {
                window.__mobileUpgradeUserHeader();
            }
            var mobileHeader = document.querySelector('.header-bc');
            if (mobileHeader) {
                mobileHeader.classList.remove('hdr-auth-guest');
                mobileHeader.classList.add('hdr-auth-user');
            }
        } else {
            var header = document.querySelector('.header-bc');
            if (header) {
                header.classList.remove('hdr-auth-guest');
                header.classList.add('hdr-auth-user');
            }
            if (guestLoginBtn && guestLoginBtn.parentNode) {
                guestLoginBtn.parentNode.removeChild(guestLoginBtn);
            }
            if (guestRegisterBtn && guestRegisterBtn.parentNode) {
                guestRegisterBtn.parentNode.removeChild(guestRegisterBtn);
            }
            var guestDeposit = document.getElementById('openRegister2');
            if (guestDeposit) {
                guestDeposit.removeAttribute('id');
                guestDeposit.classList.add('hdr-deposit-btn');
                guestDeposit.setAttribute('href', '/profile/deposit?openDepositPanel=1');
                guestDeposit.setAttribute('data-profile-modal-href', '/profile/deposit?openDepositPanel=1');
                guestDeposit.setAttribute('data-nav-mode', 'modal');
                guestDeposit.setAttribute('onclick', 'event.preventDefault(); redirectToDeposit();');
                guestDeposit.setAttribute('title', 'Para Yatır');
            }
            if (!document.querySelector('.header-user-nav')) {
                var loginCol = document.querySelector('.loginCol.hdr-user-bc, .hdr-user-bc');
                var langSelect = document.getElementById('langDropdown');
                if (loginCol) {
                    var navWrap = document.createElement('div');
                    navWrap.innerHTML = desktopUserNavMarkup();
                    loginCol.insertBefore(navWrap.firstChild, langSelect || null);
                }
            }
            ensureDesktopLoyaltyBadge();
        }

        initDepositMenu();
        initPlayerMenu();
        if (typeof window.__refreshHeaderBalance === 'function') {
            window.__refreshHeaderBalance();
        }

        return true;
    }

    function initSmartPanel() {
        var panel   = document.getElementById('smartPanelFixed');
        var toggle  = document.getElementById('smart-panel-holder');
        var isMobile = document.body.classList.contains('mobile-site');
        var isScrollLockedBySmartPanel = false;

        function lockBodyForSmartPanel() {
            if (!isMobile || isScrollLockedBySmartPanel) return;
            isScrollLockedBySmartPanel = true;
            document.body.classList.add('smart-panel-open');
        }

        function unlockBodyForSmartPanel() {
            if (!isMobile || !isScrollLockedBySmartPanel) return;
            isScrollLockedBySmartPanel = false;
            document.body.classList.remove('smart-panel-open');
        }

        if (!toggle) return;

        if (isMobile && panel && panel.parentNode !== document.body) {
            document.body.appendChild(panel);
        }

        function applyMobilePanelSizing() {
            if (!isMobile || !panel) return;
            panel.style.setProperty('left', 'auto', 'important');
            panel.style.setProperty('bottom', 'auto', 'important');
            panel.style.setProperty('height', 'auto', 'important');
            panel.style.setProperty('max-height', '320px', 'important');
            panel.style.setProperty('overflow', 'hidden', 'important');
            panel.style.setProperty('transform', 'none', 'important');

            var holder = panel.querySelector('.hdr-smart-panel-holder-bc');
            if (holder) {
                holder.style.setProperty('max-height', '320px', 'important');
                holder.style.setProperty('overflow-y', 'auto', 'important');
                holder.style.setProperty('overflow-x', 'hidden', 'important');
            }

            panel.querySelectorAll('.sp-button-bc').forEach(function (btn) {
                btn.style.setProperty('width', '50px', 'important');
                btn.style.setProperty('height', '44px', 'important');
                btn.style.setProperty('font-size', '11px', 'important');
                btn.style.setProperty('line-height', '1', 'important');
                btn.style.setProperty('padding', '0', 'important');
            });

            panel.querySelectorAll('.sp-button-icon-bc').forEach(function (icon) {
                icon.style.setProperty('font-size', '15px', 'important');
            });
        }

        function syncPanelPosition() {
            if (!panel) return;
            var rect = toggle.getBoundingClientRect();
            if (rect.width === 0 && rect.height === 0) return;

            var safeTop = 52;
            if (isMobile) {
                var rootStyles = getComputedStyle(document.documentElement);
                var promoTop = parseFloat(rootStyles.getPropertyValue('--mobile-promo-sheet-top'));
                var stickyTop = parseFloat(rootStyles.getPropertyValue('--header-sticky-top'));
                if (!isNaN(promoTop) && promoTop > 0) {
                    safeTop = promoTop;
                } else if (!isNaN(stickyTop) && stickyTop > 0) {
                    safeTop = stickyTop;
                }
            }

            var computedTop = Math.ceil(rect.bottom + 8);
            if (!isNaN(safeTop) && computedTop < safeTop) {
                computedTop = Math.ceil(safeTop + 2);
            }
            
            panel.style.setProperty('position', 'fixed', 'important');
            panel.style.setProperty('bottom', 'auto', 'important');
            panel.style.setProperty('top', computedTop + 'px', 'important');
            if (isMobile) {
                panel.style.setProperty('left', 'auto', 'important');
                panel.style.setProperty('right', Math.max(8, Math.round(window.innerWidth - rect.right)) + 'px', 'important');
            } else {
                panel.style.setProperty('left', Math.round(rect.left + (rect.width / 2)) + 'px', 'important');
                panel.style.setProperty('right', 'auto', 'important');
            }
        }

        function openPanel() {
            closeAllHeaderFlyouts('smart');
            applyMobilePanelSizing();
            syncPanelPosition();
            if (panel)  { panel.classList.add('is-open');  panel.setAttribute('aria-hidden', 'false'); }
            toggle.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
            toggle.setAttribute('title', 'Kapat');
            toggle.setAttribute('aria-label', 'Kapat');
            lockBodyForSmartPanel();
        }

        function closePanel() {
            if (panel)  { panel.classList.remove('is-open'); panel.setAttribute('aria-hidden', 'true'); }
            toggle.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('title', 'Akıllı Menü');
            toggle.setAttribute('aria-label', 'Akıllı Menü');
            unlockBodyForSmartPanel();
        }

        window.__closeSmartPanel = closePanel;

        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            if (panel && panel.classList.contains('is-open')) closePanel();
            else openPanel();
        });


        /* Escape tuşu kapatır */
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && panel && panel.classList.contains('is-open')) closePanel();
        });

        document.addEventListener('click', function (e) {
            if (!panel || !panel.classList.contains('is-open')) return;
            if (panel.contains(e.target) || toggle.contains(e.target)) return;
            closePanel();
        });

        window.addEventListener('resize', syncPanelPosition);
        window.addEventListener('scroll', syncPanelPosition, true);
        applyMobilePanelSizing();
        syncPanelPosition();
    }

    function initSearchPanel() {
        var searchBtn = document.getElementById('headerSearchBtn');
        var searchOverlay = document.getElementById('searchOverlay');
        var searchPanel = document.getElementById('searchPanel');
        var searchClose = document.getElementById('searchPanelClose');
        var searchInput = document.getElementById('searchPanelInput');
        var searchBody = document.getElementById('searchPanelBody');
        var filterBtns = document.querySelectorAll('.search-panel__filter');
        var activeFilter = 'sport';

        if (!window.__searchThumbError) {
            window.__searchThumbError = function (img) {
                if (!img) return;
                var raw = img.getAttribute('data-fallbacks') || '[]';
                var list = [];
                try {
                    list = JSON.parse(raw);
                } catch (e) {
                    list = [];
                }
                if (!Array.isArray(list)) list = [];
                while (list.length) {
                    var next = String(list.shift() || '').trim();
                    if (!next || next === img.getAttribute('src')) continue;
                    img.setAttribute('data-fallbacks', JSON.stringify(list));
                    img.src = next;
                    return;
                }
                img.style.display = 'none';
                var fallback = img.nextElementSibling;
                if (fallback && fallback.classList.contains('search-panel__game-thumb-fallback')) {
                    fallback.hidden = false;
                }
            };
        }

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function inputPlaceholderFor(filter) {
            if (filter === 'casino') return (window.__ ? window.__('panel.search_casino', "Casino'da ara") : "Casino'da ara");
            if (filter === 'livecasino') return (window.__ ? window.__('panel.search_live', "Canlı Casino'da ara") : "Canlı Casino'da ara");
            return (window.__ ? window.__('panel.search_sport', "Spor'da ara") : "Spor'da ara");
        }

        function gameTypeFor(filter) {
            if (filter === 'casino') return '0';
            if (filter === 'livecasino') return '1';
            return null;
        }

        function setEmpty(text) {
            if (!searchBody) return;
            searchBody.innerHTML = '<p class="search-panel__empty">' + escapeHtml(text) + '</p>';
        }

        function setLoading() {
            if (!searchBody) return;
            searchBody.innerHTML = '<p class="search-panel__empty">' + escapeHtml(window.__ ? window.__('panel.loading_dots', 'Yükleniyor...') : 'Yükleniyor...') + '</p>';
        }

        function normalizeGameId(game) {
            if (!game || typeof game !== 'object') return '';
            var gid = game.game_id || game.id || game.identifier || '';
            return String(gid || '').trim();
        }

        function normalizeGameName(game) {
            if (!game || typeof game !== 'object') return '';
            return String(game.name || game.title || game.game_name || 'Oyun').trim();
        }

        function normalizeGameProvider(game) {
            if (!game || typeof game !== 'object') return '';
            return String(game.provider || game.provider_name || game.provider_code || '').trim();
        }

        function normalizeGameImage(game) {
            if (!game || typeof game !== 'object') return '';
            var primary = String(
                game.cover
                || game.image_url
                || game.thumbnail_url
                || game.banner
                || game.game_image_url
                || game.game_image
                || ''
            ).trim();
            if (primary !== '') return primary;
            var fallbacks = game.cover_fallbacks || game.image_fallbacks;
            if (Array.isArray(fallbacks)) {
                for (var i = 0; i < fallbacks.length; i++) {
                    var next = String(fallbacks[i] || '').trim();
                    if (next !== '') return next;
                }
            }
            return '';
        }

        function normalizeGameImageFallbacks(game) {
            if (!game || typeof game !== 'object') return [];
            var out = [];
            var seen = {};
            var push = function (url) {
                var value = String(url || '').trim();
                if (value === '' || seen[value]) return;
                seen[value] = true;
                out.push(value);
            };
            push(game.cover);
            push(game.image_url);
            push(game.thumbnail_url);
            push(game.banner);
            push(game.game_image_url);
            push(game.game_image);
            var lists = [game.cover_fallbacks, game.image_fallbacks];
            for (var i = 0; i < lists.length; i++) {
                var list = lists[i];
                if (!Array.isArray(list)) continue;
                for (var j = 0; j < list.length; j++) push(list[j]);
            }
            return out;
        }

        function normalizeText(value) {
            return String(value || '')
                .toLocaleLowerCase('tr-TR')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '');
        }

        function sortByPopularity(items, filter) {
            if (!Array.isArray(items) || !items.length) return [];

            var popularByFilter = {
                casino: [
                    'gates of olympus',
                    'gates of olympus 1000',
                    'sweet bonanza',
                    'wanted dead or a wild',
                    'starlight princess',
                    'big bass',
                    'sugar rush',
                    'zeus vs hades'
                ],
                livecasino: [
                    'lightning roulette',
                    'crazy time',
                    'mega roulette',
                    'monopoly live',
                    'blackjack',
                    'baccarat',
                    'sweet bonanza candyland'
                ]
            };

            var exactRank = popularByFilter[filter] || [];
            var mapped = items.slice().map(function (game, index) {
                var nameNorm = normalizeText(normalizeGameName(game));
                var providerNorm = normalizeText(normalizeGameProvider(game));
                var score = 0;

                for (var i = 0; i < exactRank.length; i++) {
                    if (nameNorm.indexOf(exactRank[i]) !== -1) {
                        score += 1000 - (i * 25);
                    }
                }

                if (providerNorm.indexOf('pragmatic') !== -1) score += 140;
                if (providerNorm.indexOf('evolution') !== -1 && filter === 'livecasino') score += 120;
                if (providerNorm.indexOf('play\'n go') !== -1 || providerNorm.indexOf('play n go') !== -1) score += 55;

                return {
                    game: game,
                    score: score,
                    index: index
                };
            });

            mapped.sort(function (a, b) {
                if (b.score !== a.score) return b.score - a.score;
                return a.index - b.index;
            });

            return mapped.map(function (row) { return row.game; });
        }

        function renderGames(items) {
            if (!searchBody) return;
            if (!items || !items.length) {
                setEmpty(window.__ ? window.__('panel.search_empty', 'Bu filtrede oyun bulunamadı.') : 'Bu filtrede oyun bulunamadı.');
                return;
            }

            items = sortByPopularity(items, activeFilter);

            var html = '<div class="search-panel__results">';
            for (var i = 0; i < items.length; i++) {
                var game = items[i] || {};
                var gameId = normalizeGameId(game);
                if (!gameId) continue;
                var name = normalizeGameName(game);
                var provider = normalizeGameProvider(game);
                var image = normalizeGameImage(game);
                var imageFallbacks = normalizeGameImageFallbacks(game);
                var safeImage = image !== '' ? escapeHtml(image) : '';
                var initials = escapeHtml((name || 'O').charAt(0).toUpperCase());
                var fallbackAttr = '';
                if (imageFallbacks.length > 1) {
                    fallbackAttr = ' data-fallbacks="' + escapeHtml(JSON.stringify(imageFallbacks.slice(1))) + '"';
                }

                html += '<button type="button" class="search-panel__game" data-game-id="' + escapeHtml(gameId) + '">';
                html += '<span class="search-panel__game-thumb">';
                if (safeImage !== '') {
                    html += '<img src="' + safeImage + '" alt="' + escapeHtml(name) + '" loading="lazy" referrerpolicy="no-referrer"' + fallbackAttr + ' onerror="window.__searchThumbError&&window.__searchThumbError(this)">';
                    html += '<span class="search-panel__game-thumb-fallback" hidden>' + initials + '</span>';
                } else {
                    html += '<span class="search-panel__game-thumb-fallback">' + initials + '</span>';
                }
                html += '</span>';
                html += '<span class="search-panel__game-meta">';
                html += '<span class="search-panel__game-name">' + escapeHtml(name) + '</span>';
                if (provider !== '') {
                    html += '<span class="search-panel__game-provider">' + escapeHtml(provider) + '</span>';
                }
                html += '</span>';
                html += '</button>';
            }
            html += '</div>';
            searchBody.innerHTML = html;
        }

        function extractItems(payload) {
            if (!payload || typeof payload !== 'object') return [];
            var data = payload.data || {};
            var list = data.items || data.games || [];
            return Array.isArray(list) ? list : [];
        }

        function fetchCasinoGames() {
            var gameType = gameTypeFor(activeFilter);
            if (gameType === null) {
                setEmpty('Arama yapmak için yukarıdaki alanı kullanın.');
                return;
            }

            var q = searchInput ? String(searchInput.value || '').trim() : '';
            var params = new URLSearchParams();
            params.set('limit', '36');
            params.set('page', '1');
            params.set('game_type', gameType);
            if (q !== '') params.set('search', q);

            setLoading();
            fetch(apiUrl('/api/v2/games?' + params.toString()), {
                method: 'GET',
                credentials: 'include',
                headers: { Accept: 'application/json' }
            })
                .then(function (resp) {
                    if (!resp.ok) {
                        throw new Error('HTTP ' + resp.status);
                    }
                    return resp.json();
                })
                .then(function (json) {
                    renderGames(extractItems(json));
                })
                .catch(function () {
                    setEmpty('Oyunlar yüklenemedi. Lütfen tekrar deneyin.');
                });
        }

        function applyFilter(filter) {
            activeFilter = filter || 'sport';
            if (searchInput) {
                searchInput.placeholder = inputPlaceholderFor(activeFilter);
            }
            if (activeFilter === 'sport') {
                setEmpty('Arama yapmak için yukarıdaki alanı kullanın.');
                return;
            }
            fetchCasinoGames();
        }

        var fetchCasinoGamesDebounced = debounce(fetchCasinoGames, 260);

        function openSearchPanel() {
            if (!searchOverlay || !searchPanel) return;
            closeAllHeaderFlyouts('search');
            if (document.body.classList.contains('mobile-site') && typeof window.__syncHeaderStickyTop === 'function') {
                window.__syncHeaderStickyTop();
            }
            searchOverlay.classList.add('is-open');
            searchOverlay.setAttribute('aria-hidden', 'false');
            searchPanel.classList.add('is-open');
            searchPanel.setAttribute('aria-hidden', 'false');
            if (searchBtn) searchBtn.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
            var activeBtn = document.querySelector('.search-panel__filter.is-active');
            applyFilter(activeBtn ? (activeBtn.getAttribute('data-filter') || 'sport') : 'sport');
            setTimeout(function () { if (searchInput) searchInput.focus(); }, 300);
        }

        function closeSearchPanel() {
            if (!searchOverlay || !searchPanel) return;
            searchOverlay.classList.remove('is-open');
            searchOverlay.setAttribute('aria-hidden', 'true');
            searchPanel.classList.remove('is-open');
            searchPanel.setAttribute('aria-hidden', 'true');
            if (searchBtn) searchBtn.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }
        window.__closeSearchPanel = closeSearchPanel;

        /* Sayfa yükünde hayalet Close sekmesi kalmasın */
        closeSearchPanel();

        if (searchBtn) {
            searchBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (searchPanel && searchPanel.classList.contains('is-open')) closeSearchPanel();
                else openSearchPanel();
            });
        }
        if (searchClose) searchClose.addEventListener('click', closeSearchPanel);
        if (searchOverlay) searchOverlay.addEventListener('click', closeSearchPanel);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && searchPanel && searchPanel.classList.contains('is-open')) closeSearchPanel();
        });

        filterBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                filterBtns.forEach(function (b) { b.classList.remove('is-active'); });
                btn.classList.add('is-active');
                applyFilter(btn.getAttribute('data-filter') || 'sport');
            });
        });

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                if (activeFilter === 'sport') return;
                fetchCasinoGamesDebounced();
            });
        }

        if (searchBody) {
            searchBody.addEventListener('click', function (e) {
                var gameBtn = e.target && e.target.closest ? e.target.closest('.search-panel__game[data-game-id]') : null;
                if (!gameBtn) return;
                var gameId = (gameBtn.getAttribute('data-game-id') || '').trim();
                if (!gameId) return;
                closeSearchPanel();
                openGame(gameId);
            });
        }
    }

    function initMainMenuScroll() {
        var ul = document.querySelector('.mainMenu ul');
        if (!ul) return;
        var down = false;
        var dragged = false;
        var startX;
        var startScroll;
        var dragThreshold = 6;

        ul.addEventListener('dragstart', function (e) { e.preventDefault(); });
        ul.addEventListener('mousedown', function (e) {
            down = true;
            dragged = false;
            startX = e.pageX;
            startScroll = ul.scrollLeft;
        });
        ul.addEventListener('mouseup', function () { down = false; });
        ul.addEventListener('mouseleave', function () { down = false; });
        ul.addEventListener('mousemove', function (e) {
            if (!down) return;
            if (!dragged && Math.abs(e.pageX - startX) >= dragThreshold) {
                dragged = true;
            }
            if (!dragged) return;
            ul.scrollLeft = startScroll - (e.pageX - startX);
            e.preventDefault();
        });
        ul.addEventListener('click', function (e) {
            if (dragged) {
                e.preventDefault();
                e.stopPropagation();
            }
            dragged = false;
        }, true);
    }

    function initMainMenuPrefetch() {
        var menu = document.querySelector('.mainMenu');
        if (!menu) return;
        var prefetched = Object.create(null);
        menu.addEventListener('pointerenter', function (e) {
            var link = e.target && e.target.closest ? e.target.closest('a[href]') : null;
            if (!link) return;
            var href = (link.getAttribute('href') || '').trim();
            if (!href || href.charAt(0) !== '/' || prefetched[href]) return;
            prefetched[href] = true;
            var hint = document.createElement('link');
            hint.rel = 'prefetch';
            hint.href = href;
            hint.as = 'document';
            document.head.appendChild(hint);
        }, true);
    }

    function initMainMenuActive() {
        var menu = document.querySelector('.mainMenu');
        if (!menu) return;
        var links = menu.querySelectorAll('a[href]');
        if (!links.length) return;

        var currentPath = (window.location.pathname || '/').replace(/\/+$/, '') || '/';
        var best = null;
        var bestScore = 0;

        for (var i = 0; i < links.length; i++) {
            var href = links[i].getAttribute('href');
            if (!href || href.charAt(0) === '#') continue;
            var path;
            try {
                path = new URL(href, window.location.origin).pathname.replace(/\/+$/, '') || '/';
            } catch (e) { continue; }
            var score = currentPath === path ? path.length + 1000
                : (path !== '/' && currentPath.indexOf(path + '/') === 0 ? path.length : 0);
            if (score > bestScore) { bestScore = score; best = links[i]; }
        }

        if (!best) return;
        best.classList.add('active');
        var li = best.closest('li');
        if (li) li.classList.add('active');

        var ul = menu.querySelector('ul');
        if (ul && typeof ul.scrollLeft === 'number') {
            var itemR = best.getBoundingClientRect();
            var contR = ul.getBoundingClientRect();
            var targetScroll = (itemR.left - contR.left) - (contR.width / 2 - itemR.width / 2);
            ul.scrollTo ? ul.scrollTo({ left: targetScroll, behavior: 'smooth' }) : (ul.scrollLeft = targetScroll);
        } else if (best.scrollIntoView) {
            best.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
    }

    ready(function () {
        upgradeGuestHeaderIfNeeded();
        ensureDesktopLoyaltyBadge();
        // In some templates auth-shared may initialize after header.js.
        // Retry a few times to avoid stale guest header after successful login.
        window.setTimeout(upgradeGuestHeaderIfNeeded, 180);
        window.setTimeout(ensureDesktopLoyaltyBadge, 200);
        window.setTimeout(upgradeGuestHeaderIfNeeded, 750);
        window.setTimeout(ensureDesktopLoyaltyBadge, 800);
        window.setTimeout(upgradeGuestHeaderIfNeeded, 1500);
        window.setTimeout(ensureDesktopLoyaltyBadge, 1600);
        initToastr();
        initDepositMenu();
        initPlayerMenu();
        initTurkeyTime();
        initLangDropdown();
        initLangCodeDisplay();
        initSmartPanel();
        initMainMenuScroll();
        initMainMenuPrefetch();
        initMainMenuActive();
        initSearchPanel();

        window.addEventListener('app:member-jwt-ready', function () {
            upgradeGuestHeaderIfNeeded();
            ensureDesktopLoyaltyBadge();
        });
        window.addEventListener('load', function () {
            upgradeGuestHeaderIfNeeded();
            ensureDesktopLoyaltyBadge();
        });
    });
})();

