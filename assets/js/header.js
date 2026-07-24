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
            return String(window.localStorage.getItem('metropol_member_jwt') || '').trim();
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
        var target = '/profile/deposit-withdraw?openDepositPanel=1';
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
            title: 'Bonus Kodunuzu Girin',
            input: 'text',
            inputLabel: 'Bonus Kodu',
            inputPlaceholder: 'Kodu buraya girin',
            showCancelButton: true,
            confirmButtonText: 'Kullan',
            cancelButtonText: 'İptal'
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
                    var msg = data.mesaj || data.message || 'İşlem tamamlanamadı.';
                    if (data.status === 'success' || data.success === true) {
                        window.MaltabetToast ? MaltabetToast.success(msg, 'Başarılı') : alert(msg);
                    } else {
                        window.MaltabetToast ? MaltabetToast.error(msg, 'Hata') : alert(msg);
                    }
                })
                .catch(function (err) {
                    if (window.MaltabetToast) MaltabetToast.error('Hata oluştu, lütfen tekrar deneyin.', 'Hata');
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
                url += '&open_mode=redirect';
            }
            var a = document.createElement('a');
            a.href = url;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();
            a.remove();
            if (document.visibilityState === 'hidden') {
                return;
            }
        }
        window.location.href = url;
    }
    function openGame(gameId) {
        var url = '/play?game_id=' + encodeURIComponent(gameId) + '&mode=real&wallet=main';
        if (window.MaltabetWalletPicker && typeof window.MaltabetWalletPicker.launch === 'function') {
            window.MaltabetWalletPicker.launch(url, launchGameUrl);
            return;
        }
        launchGameUrl(url);
    }
    window.openGame = openGame;

    var LANG_CODES = { en: 'ENG', de: 'DEU', ru: 'RUS', ar: 'ARB', tr: 'TUR' };

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
        var codeSpan = document.querySelector('.langSelect .lang-code');
        if (!codeSpan) return;
        var lang = (new URLSearchParams(window.location.search)).get('lang') || 'tr';
        codeSpan.textContent = LANG_CODES[lang] || 'TUR';
    }

    function runtimeLoggedIn() {
        if (window.__MEMBER_BOOTSTRAP_STATE__ && typeof window.__MEMBER_BOOTSTRAP_STATE__ === 'object') {
            return window.__MEMBER_BOOTSTRAP_STATE__.logged_in === true;
        }
        return window.__USER_LOGGED_IN__ === true;
    }

    function desktopUserNavMarkup() {
        return ''
            + '<div class="nav-menu-container header-user-nav">'
            + '  <ul class="nav-menu-other hdr-balance-nav">'
            + '    <li id="depositBalanceWrap">'
            + '      <a href="/profile/deposit-withdraw?openDepositPanel=1" class="nav-menu-item hdr-balance-trigger" id="balanceTrigger" role="button" aria-expanded="false" aria-haspopup="true">'
            + '        <div class="hdr-user-info-content-bc"><span class="hdr-user-info-texts-bc ext-1 ellipsis"><span class="balanceAmount"><span id="headerBalanceMain" data-balance-target="headerBalanceMain">0</span><span class="currencySymbol">&#8239;₺</span></span></span></div>'
            + '      </a>'
            + '    </li>'
            + '  </ul>'
            + '  <ul class="nav-menu-other profileDetails">'
            + '    <li><div class="user-nav-icon playerCol" id="playerCol">'
            + '      <button class="userBtn nav-menu-item" id="toggleButton" type="button" aria-expanded="false" aria-label="Profil menüsü">'
            + '        <span class="avatarHolderImg"><i class="bc-i-user hdr-user-avatar-icon-bc" aria-hidden="true"></i></span>'
            + '      </button>'
            + '      <div class="playerNav hidesection" id="playerNav" role="menu">'
            + '        <div class="playerNav-body">'
            + '          <a class="pl-link" href="#" id="profileLinkModal" role="menuitem"><i class="pl-link-icon bc-i-user" aria-hidden="true"></i> PROFİLİM</a>'
            + '          <a class="pl-link" href="/profile/deposit-withdraw" data-nav-mode="modal" role="menuitem"><i class="pl-link-icon bc-i-deposit" aria-hidden="true"></i> BAKİYE YÖNETİMİ</a>'
            + '          <a class="pl-link" href="/profile/bet-history" data-nav-mode="modal" role="menuitem"><i class="pl-link-icon bc-i-bet-history" aria-hidden="true"></i> BAHİS GEÇMİŞİ</a>'
            + '          <a class="pl-link" href="/profile/bonus-spor" data-nav-mode="modal" role="menuitem"><i class="pl-link-icon bc-i-promotions-3" aria-hidden="true"></i> BONUSLAR</a>'
            + '          <a class="pl-link" href="/profile/messages" data-nav-mode="modal" role="menuitem"><i class="pl-link-icon bc-i-message" aria-hidden="true"></i> MESAJLAR</a>'
            + '        </div>'
            + '        <div class="playerNav-footer">'
            + '          <a class="pl-link pl-link-logout" href="/logout" data-nav-mode="page" role="menuitem"><i class="pl-link-icon bc-i-logout" aria-hidden="true"></i> ÇIKIŞ YAP</a>'
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
            if (!document.querySelector('.header-user-nav')) {
                var loginCol = document.querySelector('.loginCol.hdr-user-bc, .hdr-user-bc');
                var langSelect = document.getElementById('langDropdown');
                if (loginCol) {
                    var navWrap = document.createElement('div');
                    navWrap.innerHTML = desktopUserNavMarkup();
                    loginCol.insertBefore(navWrap.firstChild, langSelect || null);
                }
            }
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
            panel.style.setProperty('left', 'auto', 'important');
            panel.style.setProperty('top', computedTop + 'px', 'important');
            panel.style.setProperty('right', Math.max(8, Math.round(window.innerWidth - rect.right)) + 'px', 'important');
        }

        function openPanel() {
            closeAllHeaderFlyouts('smart');
            applyMobilePanelSizing();
            syncPanelPosition();
            if (panel)  { panel.classList.add('is-open');  panel.setAttribute('aria-hidden', 'false'); }
            toggle.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
            lockBodyForSmartPanel();
        }

        function closePanel() {
            if (panel)  { panel.classList.remove('is-open'); panel.setAttribute('aria-hidden', 'true'); }
            toggle.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
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

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function inputPlaceholderFor(filter) {
            if (filter === 'casino') return 'Casino\'da ara';
            if (filter === 'livecasino') return 'Canlı Casino\'da ara';
            return 'Spor\'da ara';
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
            searchBody.innerHTML = '<p class="search-panel__empty">Yükleniyor...</p>';
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
            return String(game.image_url || game.thumbnail_url || game.banner || '').trim();
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
                setEmpty('Bu filtrede oyun bulunamadı.');
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
                var safeImage = image !== '' ? escapeHtml(image) : '';
                var initials = escapeHtml((name || 'O').charAt(0).toUpperCase());

                html += '<button type="button" class="search-panel__game" data-game-id="' + escapeHtml(gameId) + '">';
                html += '<span class="search-panel__game-thumb">';
                if (safeImage !== '') {
                    html += '<img src="' + safeImage + '" alt="' + escapeHtml(name) + '" loading="lazy">';
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
        var down = false, dragged = false, startX, startScroll;

        ul.addEventListener('dragstart', function (e) { e.preventDefault(); });
        ul.addEventListener('mousedown', function (e) {
            down = true;
            dragged = false;
            startX = e.pageX;
            startScroll = ul.scrollLeft;
            e.preventDefault();
        });
        ul.addEventListener('mouseup', function () { down = false; });
        ul.addEventListener('mouseleave', function () { down = false; });
        ul.addEventListener('mousemove', function (e) {
            if (!down) return;
            dragged = true;
            ul.scrollLeft = startScroll - (e.pageX - startX);
            e.preventDefault();
        });
        ul.addEventListener('click', function (e) {
            if (dragged) e.preventDefault(), e.stopPropagation();
            dragged = false;
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
        // In some templates auth-shared may initialize after header.js.
        // Retry a few times to avoid stale guest header after successful login.
        window.setTimeout(upgradeGuestHeaderIfNeeded, 180);
        window.setTimeout(upgradeGuestHeaderIfNeeded, 750);
        window.setTimeout(upgradeGuestHeaderIfNeeded, 1500);
        initToastr();
        initDepositMenu();
        initPlayerMenu();
        initTurkeyTime();
        initLangDropdown();
        initLangCodeDisplay();
        initSmartPanel();
        initMainMenuScroll();
        initMainMenuActive();
        initSearchPanel();

        window.addEventListener('metropol:member-jwt-ready', function () {
            upgradeGuestHeaderIfNeeded();
        });
        window.addEventListener('load', function () {
            upgradeGuestHeaderIfNeeded();
        });
    });
})();

