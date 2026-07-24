(function () {
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }

    var Shared = window.BetcoAuthShared || {};

    function syncHeaderLayout() {
        if (typeof window.__syncHeaderStickyTop === 'function') {
            window.__syncHeaderStickyTop();
        }
    }

    function initAdditionalToggle() {
        var strip = document.querySelector('[data-hdr-shortcuts-strip].hdr-user-bc.hasLoyaltyLevel');
        if (!strip) return;

        var row = strip.closest('.hdr-additional-info');
        var toggles = strip.querySelectorAll('[data-hdr-additional-toggle]');
        toggles.forEach(function (el) {
            function setExpanded(expanded) {
                strip.classList.toggle('isExpandedIcons', expanded);
                if (row) {
                    row.classList.toggle('hdr-shortcuts-row-expanded', expanded);
                }
                el.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                el.setAttribute('aria-label', expanded ? 'Kısayolları kapat' : 'Kısayolları genişlet');
                syncHeaderLayout();
            }

            el.addEventListener('click', function (e) {
                if (e.target.closest('a.user-nav-icon')) return;
                e.preventDefault();
                e.stopPropagation();
                setExpanded(!strip.classList.contains('isExpandedIcons'));
            });

            el.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    setExpanded(!strip.classList.contains('isExpandedIcons'));
                }
            });
        });

        strip.querySelectorAll('a.user-nav-icon').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        });
    }

    function initProfileButton() {
        var btn = document.getElementById('toggleButton');
        if (!btn) return;
        btn.style.border = '0';
        btn.style.background = 'transparent';
        btn.style.padding = '0';
        btn.style.color = 'inherit';
    }

    function ensureFallbackMobileProfilePanel() {
        var panel = document.getElementById('mprofilePanel');
        var overlay = document.getElementById('mprofileOverlay');
        return !!(panel && overlay);
    }

    function toggleMobileProfilePanelSafely() {
        var panel = document.getElementById('mprofilePanel');
        var overlay = document.getElementById('mprofileOverlay');
        if ((!panel || !overlay) && !ensureFallbackMobileProfilePanel()) {
            return false;
        }
        panel = document.getElementById('mprofilePanel');
        overlay = document.getElementById('mprofileOverlay');
        if (!panel || !overlay) {
            return false;
        }

        var isOpen = panel.classList.contains('is-open');
        if (isOpen) {
            if (typeof window.__closeMobileProfilePanel === 'function') {
                window.__closeMobileProfilePanel();
                return true;
            }
            overlay.classList.remove('is-open');
            panel.classList.remove('is-open');
            overlay.setAttribute('aria-hidden', 'true');
            panel.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('mprofile-open', 'overlay-sliding-is-visible', 'overlaySlidingIsVisible');
            return true;
        }

        if (typeof window.__openMobileProfilePanel === 'function') {
            return !!window.__openMobileProfilePanel();
        }

        overlay.classList.add('is-open');
        panel.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        panel.setAttribute('aria-hidden', 'false');
        overlay.style.display = 'block';
        panel.style.transform = 'translateY(0)';
        document.body.classList.add('mprofile-open', 'overlay-sliding-is-visible', 'overlaySlidingIsVisible');
        return true;
    }

    // Mobil kullanıcı başlığı (avatar + bakiye) işaretlemesi ve bağlama mantığı.
    // Bu tamamen mobile-header.js'e ait — masaüstü assets/js/header.js sadece
    // window.__mobileUpgradeUserHeader() üzerinden tetikler, hiçbir mobil DOM/markup
    // masaüstü dosyasında bulunmaz. Sunucu tarafı SSR misafir başlığı render edip
    // istemci JS geçerli bir JWT tespit ettiğinde çalışır (guest→user yükseltmesi).
    function mobileUserHeaderMarkup() {
        return ''
            + '<div class="user-balance-dropdown">'
            + '  <a class="nav-menu-item hdr-balance-trigger" id="balanceTrigger" href="/profile/deposit-withdraw?openDepositPanel=1" aria-label="Bakiye" role="button" aria-expanded="false" aria-haspopup="true">'
            + '    <div class="hdr-user-info-content-bc">'
            + '      <div class="hdr-user-info-texts-bc ext-1 ellipsis" data-header-balance-main>'
            + '        <p class="balanceAmount"><span id="headerBalanceMain" data-balance-target="headerBalanceMain">0</span><span class="currencySymbol"> ₺</span></p>'
            + '      </div>'
            + '    </div>'
            + '  </a>'
            + '</div>'
            + '<div class="profileDetails" id="playerCol">'
            + '  <button type="button" class="userBtn nav-menu-item" id="toggleButton" aria-expanded="false" aria-label="Profil menüsü">'
            + '    <i class="hdr-user-avatar-icon-bc bc-i-user" aria-hidden="true"></i><span class="backFace" aria-hidden="true"></span>'
            + '  </button>'
            + '</div>';
    }

    function bindMobileUserHeaderActions(scope) {
        if (!scope) return;
        var profileTarget = '/?profile=open&account=profile&page=details';

        var balanceTrigger = scope.querySelector('#balanceTrigger');
        if (balanceTrigger) {
            balanceTrigger.addEventListener('click', function (e) {
                e.preventDefault();
                if (typeof window.__openMobileBalancePage === 'function' && window.__openMobileBalancePage('deposit')) {
                    return;
                }
                if (typeof window.redirectToDeposit === 'function') {
                    window.redirectToDeposit();
                }
            });
        }

        // #toggleButton profili initMobileAvatarProfileModal() uzerinden
        // tek kanaldan yonetilir — burada ayrica bind etme.
    }

    // Sunucu SSR misafir başlığı render etti ama istemci JS geçerli JWT tespit ettiğinde
    // (assets/js/header.js upgradeGuestHeaderIfNeeded()) çağırır. Tüm mobil DOM/markup ve
    // olay bağlama burada, mobile-header.js'de kalır.
    window.__mobileUpgradeUserHeader = function () {
        var mobileUserWrap = document.querySelector('.mobile-bc-header .hdr-user-bc');
        if (mobileUserWrap) {
            mobileUserWrap.innerHTML = mobileUserHeaderMarkup();
            bindMobileUserHeaderActions(mobileUserWrap);
            initProfileButton();
            initMobileAvatarProfileModal();
        }
        var guestShortcuts = document.querySelector('.mobile-bc-header .hdr-guest-shortcuts');
        if (guestShortcuts && guestShortcuts.parentNode) {
            guestShortcuts.parentNode.removeChild(guestShortcuts);
        }
    };

    function openProfileFromAvatarTap(event) {
        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }
        if (event && typeof event.stopPropagation === 'function') {
            event.stopPropagation();
        }

        var profileTarget = '/?profile=open&account=profile&page=details';
        if (Shared.ensureSessionForPage && !Shared.ensureSessionForPage(profileTarget)) {
            return false;
        }
        if (typeof window.__closeSmartPanel === 'function') {
            window.__closeSmartPanel();
        }
        if (typeof window.__closeMobileNavMenu === 'function') {
            window.__closeMobileNavMenu();
        }

        if (toggleMobileProfilePanelSafely()) {
            return false;
        }

        // Native panel DOM'u yoksa aynı query formatıyla tam sayfaya geç.
        window.location.href = profileTarget;
        return false;
    }

    window.__mobileProfileIconTap = openProfileFromAvatarTap;

    function initMobileAvatarProfileModal() {
        var btn = document.getElementById('toggleButton');
        if (!btn) return;
        if (btn.getAttribute('data-mobile-profile-bound') === '1') return;
        btn.setAttribute('data-mobile-profile-bound', '1');

        function handleTap(e) {
            var now = Date.now();
            var lastTap = Number(btn.getAttribute('data-mobile-profile-lasttap') || '0');
            if (!isNaN(lastTap) && (now - lastTap) < 350) {
                if (e && typeof e.preventDefault === 'function') e.preventDefault();
                return;
            }
            btn.setAttribute('data-mobile-profile-lasttap', String(now));
            openProfileFromAvatarTap(e);
        }

        btn.addEventListener('click', function (e) {
            handleTap(e);
        });

        btn.addEventListener('touchend', function (e) {
            handleTap(e);
        }, { passive: false });

        btn.addEventListener('pointerup', function (e) {
            handleTap(e);
        });

        btn.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            openProfileFromAvatarTap(e);
        });
    }

    function initSiteLogoNavigation() {
        document.addEventListener('click', function (e) {
            var link = e.target.closest('a[data-site-logo-link]');
            if (!link) return;
            if (typeof window.__openMobileHomeWithCurrentPage === 'function' && window.__openMobileHomeWithCurrentPage()) {
                e.preventDefault();
                e.stopPropagation();
            }
        }, true);
    }

    function initNavScrollStart() {
        var nav = document.querySelector(
            '.hdr-navigation-scrollable-bc-holder[data-mobile-nav-strip] .hdr-navigation-scrollable-content'
        );
        if (!nav) return;
        nav.scrollLeft = 0;
        var wrap = nav.closest('.hdr-navigation-scrollable-bc');
        if (wrap) wrap.classList.add('scroll-start');
    }

    function initBackToTopLayout() {
        var btn = document.getElementById('scrollToTopBtn');
        if (!btn) return;
        btn.style.position = 'fixed';
        btn.style.left = '0';
        btn.style.right = '0';
        btn.style.bottom = '90px';
        btn.style.width = '44px';
        btn.style.height = '44px';
        btn.style.minWidth = '44px';
        btn.style.minHeight = '44px';
        btn.style.marginLeft = 'auto';
        btn.style.marginRight = 'auto';
        btn.style.zIndex = '9990';
    }

    function observeHeaderHeight() {
        var header = document.querySelector('#root.layout-bc .layout-header-holder-bc, .layout-header-holder-bc');
        if (!header) return;

        syncHeaderLayout();
        if (typeof ResizeObserver === 'function') {
            var observer = new ResizeObserver(syncHeaderLayout);
            observer.observe(header);
        }
        window.addEventListener('resize', syncHeaderLayout, { passive: true });
        window.addEventListener('orientationchange', syncHeaderLayout, { passive: true });
    }

    function boot() {
        initAdditionalToggle();
        initProfileButton();
        initMobileAvatarProfileModal();
        initSiteLogoNavigation();
        initNavScrollStart();
        initBackToTopLayout();
        observeHeaderHeight();

        if ((location.pathname === '/' || location.pathname === '/index.php') && !location.hash) {
            window.scrollTo(0, 0);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
