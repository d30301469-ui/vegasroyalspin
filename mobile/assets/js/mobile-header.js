(function () {
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }

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

    function initMobileAvatarProfileModal() {
        var btn = document.getElementById('toggleButton');
        if (!btn) return;

        function openProfileFromAvatar() {
            if (typeof window.__closeSmartPanel === 'function') {
                window.__closeSmartPanel();
            }
            if (typeof window.__closeMobileNavMenu === 'function') {
                window.__closeMobileNavMenu();
            }

            // Öncelik: aynı ekranda profil modalını aç.
            if (typeof window.__openProfileModalUrl === 'function' && window.__openProfileModalUrl('/profile/deposit-withdraw')) {
                return;
            }

            // Fallback: modal hazır değilse mobil profile sayfasına geç.
            window.location.href = '/mobile/profile?profile=open&account=balance&page=deposit';
        }

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openProfileFromAvatar();
        });

        btn.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            e.preventDefault();
            e.stopPropagation();
            openProfileFromAvatar();
        });
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
