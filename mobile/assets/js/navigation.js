(function () {
    'use strict';

// GUARD: prevent double initialization
if (window.__MOBILE_NAV_INITIALIZED__) { return; }
window.__MOBILE_NAV_INITIALIZED__ = true;


    var menuOpen = false;
    var Shared = window.BetcoAuthShared || {};

    function getMenu() {
        return document.getElementById('mobileMenu');
    }

    function getToggles() {
        return Array.prototype.slice.call(document.querySelectorAll(
            '#menu-toggle, [data-mobile-menu-toggle], .tab-nav-item-bc.menu[aria-controls="mobileMenu"], .mobFooter-item.menu[aria-controls="mobileMenu"]'
        ));
    }

    function getRoot() {
        return document.getElementById('root');
    }

    function setMenuOpenState(isOpen) {
        var menu = getMenu();
        var toggles = getToggles();
        var root = getRoot();

        menuOpen = isOpen;

        if (menu) {
            if (isOpen) {
                menu.scrollTop = 0;
                var menuList = menu.querySelector('.m-nav-menu-list-bc');
                if (menuList) {
                    menuList.scrollTop = 0;
                }
            }
            menu.classList.toggle('is-open', isOpen);
            menu.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        }

        document.body.classList.toggle('navigation-is-visible', isOpen);
        document.body.classList.toggle('mobileMenu-locked', isOpen);
        if (root) {
            root.classList.toggle('navigation-is-visible', isOpen);
        }

        toggles.forEach(function (toggle) {
            toggle.classList.toggle('is-open', isOpen);
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        if (typeof window.__syncHeaderStickyTop === 'function') {
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(window.__syncHeaderStickyTop);
            });
        }
    }

    function clearStaleProfileLocks() {
        // Delegate to profile panel's own close function if available
        if (typeof window.__closeMobileProfilePanel === 'function') {
            var panel = document.getElementById('mprofilePanel');
            if (panel && panel.classList.contains('is-open')) {
                window.__closeMobileProfilePanel();
            }
        }
        var panel = document.getElementById('mprofilePanel');
        var overlay = document.getElementById('mprofileOverlay');
        var panelOpen = !!(panel && panel.classList.contains('is-open'));
        var overlayOpen = !!(overlay && overlay.classList.contains('is-open'));

        if (!panelOpen && !overlayOpen) {
            document.body.classList.remove('mprofile-open', 'overlay-sliding-is-visible', 'overlaySlidingIsVisible');
        }
    }

    function forceCloseCompetingPanels() {
        // First, use proper close functions for panels that have them
        if (typeof window.__closeMobileProfilePanel === 'function') {
            window.__closeMobileProfilePanel();
        }
        if (typeof window.__closeMobileBetslipPanel === 'function') {
            window.__closeMobileBetslipPanel();
        }

        var ids = [
            'mprofileOverlay',
            'mprofilePanel',
            'rightSidebarOverlay',
            'betslipPanelOverlay',
            'profileModalOverlay',
            'searchOverlay',
            'appFeedbackDialogOverlay',
            'bonus-detail-modal-overlay',
            'mobileMenu-overlay'
        ];

        for (var i = 0; i < ids.length; i += 1) {
            var el = document.getElementById(ids[i]);
            if (!el || !el.classList) {
                continue;
            }
            el.classList.remove('is-open');
            el.setAttribute('aria-hidden', 'true');
        }

        var panel = document.getElementById('mprofilePanel');
        if (panel) {
            panel.style.transform = '';
        }

        document.body.classList.remove('mprofile-open', 'overlay-sliding-is-visible', 'overlaySlidingIsVisible');
    }

    function normalizeMenuState() {
        var menu = getMenu();
        var isOpen = !!(menu && menu.classList.contains('is-open'));
        setMenuOpenState(isOpen);
        if (!isOpen) {
            clearStaleProfileLocks();
            recoverLegacyMenuScrollLock();
        }
    }

    function hasAnyBlockingOverlayOpen() {
        var ids = [
            'rightSidebarOverlay',
            'betslipPanelOverlay',
            'profileModalOverlay',
            'searchOverlay',
            'appFeedbackDialogOverlay',
            'bonus-detail-modal-overlay'
        ];
        for (var i = 0; i < ids.length; i += 1) {
            var el = document.getElementById(ids[i]);
            if (el && el.classList && el.classList.contains('is-open')) {
                return true;
            }
        }
        return false;
    }

    function recoverLegacyMenuScrollLock() {
        var menu = getMenu();
        var menuOpenNow = !!(menu && menu.classList.contains('is-open'));
        var profilePanel = document.getElementById('mprofilePanel');
        var profileOpen = !!(profilePanel && profilePanel.classList.contains('is-open'));
        if (menuOpenNow || profileOpen || hasAnyBlockingOverlayOpen()) {
            return;
        }

        var body = document.body;
        body.classList.remove('mobileMenu-locked', 'body-scroll-locked');
        body.style.position = '';
        body.style.top = '';
        body.style.left = '';
        body.style.right = '';
        body.style.width = '';
        body.style.overflow = '';
        body.style.touchAction = '';
        body.style.paddingRight = '';
    }

    function openMenu(evt) {
        if (evt && typeof evt.preventDefault === 'function') {
            evt.preventDefault();
        }
        if (evt && typeof evt.stopPropagation === 'function') {
            evt.stopPropagation();
        }
        if (typeof window.__closeMobileProfilePanel === 'function') {
            window.__closeMobileProfilePanel();
        }
        forceCloseCompetingPanels();
        clearStaleProfileLocks();
        if (menuOpen) {
            return;
        }
        setMenuOpenState(true);
    }

    function closeMenu(force) {
        if (!menuOpen && !force) {
            return;
        }
        setMenuOpenState(false);
        clearStaleProfileLocks();
    }

    function toggleMenu(evt) {
        if (menuOpen) {
            closeMenu();
        } else {
            openMenu(evt);
        }
    }

    function highlightActiveTab() {
        var path = window.location.pathname;
        var items = document.querySelectorAll(
            '.tab-navigation-w-bc .tab-nav-item-bc[href], .mobFooter-item[href], .hdr-navigation-scrollable-content .hdr-navigation-link-bc[href], .nav-content-bc .hdr-navigation-link-bc[href]'
        );
        items.forEach(function (item) {
            var href = item.getAttribute('href');
            if (!href || href === '#') {
                return;
            }
            item.classList.remove('active');
            if (href === '/' && path === '/') {
                item.classList.add('active');
            } else if (href === '/canli-bahis' && (path.indexOf('/canli-bahis') === 0 || path.indexOf('/live') === 0)) {
                item.classList.add('active');
            } else if (href !== '/' && path.indexOf(href) === 0) {
                item.classList.add('active');
            }
        });
    }

    function init() {
        var toggles = getToggles();
        var closeBtn = document.getElementById('mobileMenu-close');
        var menu = getMenu();

        normalizeMenuState();

        function findToggleTarget(target) {
            if (!target || !target.closest) return null;
            return target.closest('#menu-toggle, [data-mobile-menu-toggle], .tab-nav-item-bc.menu[aria-controls="mobileMenu"], .mobFooter-item.menu[aria-controls="mobileMenu"]');
        }

        function handleToggleIntent(e, toggle) {
            if (!toggle) return;
            var now = Date.now();
            var lastTap = Number(toggle.getAttribute('data-mobile-menu-lasttap') || '0');
            if (!isNaN(lastTap) && (now - lastTap) < 350) {
                if (e && typeof e.preventDefault === 'function') {
                    e.preventDefault();
                }
                return;
            }
            toggle.setAttribute('data-mobile-menu-lasttap', String(now));
            e.preventDefault();
            e.stopPropagation();
            toggleMenu(e);
        }

        ['click', 'touchend', 'pointerup'].forEach(function (eventName) {
            document.addEventListener(eventName, function (e) {
                var toggle = findToggleTarget(e.target);
                if (!toggle) return;
                handleToggleIntent(e, toggle);
            }, true);
        });

        document.addEventListener('keydown', function (e) {
            var toggle = findToggleTarget(e.target);
            if (!toggle) return;
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleMenu(e);
            }
        }, true);

        document.addEventListener('click', function (e) {
            var closeTarget = e.target && e.target.closest ? e.target.closest('#mobileMenu-close') : null;
            if (!closeTarget) return;
            e.preventDefault();
            e.stopPropagation();
            closeMenu(true);
        }, true);

        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                closeMenu(true);
            });
        }

        if (menu) {
            menu.addEventListener('click', function (e) {
                var link = e.target.closest('a.app-nav-link[href]');
                if (!link || link.getAttribute('href') === '#') {
                    return;
                }
                var href = link.getAttribute('href') || '';
                if (Shared.ensureSessionForPage && !Shared.ensureSessionForPage(href)) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeMenu();
                    return;
                }
                closeMenu();
            });
        }

        document.addEventListener('click', function (e) {
            var link = e.target && e.target.closest ? e.target.closest('a[href]') : null;
            if (!link) {
                return;
            }
            var href = (link.getAttribute('href') || '').trim();
            if (!href || href === '#' || href.indexOf('javascript:') === 0) {
                return;
            }
            if (Shared.ensureSessionForPage && !Shared.ensureSessionForPage(href)) {
                e.preventDefault();
                e.stopPropagation();
            }
        }, true);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeMenu();
            }
        });

        window.addEventListener('pageshow', function () {
            // BFCache donuslerinde stale kilit class'larini temizle.
            closeMenu(true);
            normalizeMenuState();
        });

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                normalizeMenuState();
            }
        });

        window.__openMobileNavMenu = function () {
            openMenu();
        };

        window.setTimeout(function () {
            normalizeMenuState();
        }, 0);

        highlightActiveTab();
    }

    function initMainMenuScrollHide() {
        if (!document.body.classList.contains('mobile-site')) {
            return;
        }

        var mainMenu = document.querySelector('.layout-header-holder-bc .hdr-navigation-scrollable-bc-holder[data-mobile-nav-strip]')
            || document.querySelector('.layout-header-holder-bc .nav-content-bc[data-mobile-nav-strip]')
            || document.querySelector('header.mobileHeader .mainMenu');
        if (!mainMenu) {
            return;
        }

        // Scroll ile gizleme kapalı — header her zaman görünür kalsın.
        mainMenu.classList.remove('header-hidden');

        if (typeof window.__syncHeaderStickyTop === 'function') {
            window.__syncHeaderStickyTop();
        }

        window.addEventListener(
            'resize',
            function () {
                if (typeof window.__syncHeaderStickyTop === 'function') {
                    window.__syncHeaderStickyTop();
                }
            },
            { passive: true }
        );
    }

    function boot() {
        init();
        initMainMenuScrollHide();
    }

    window.__closeMobileNavMenu = function () {
        closeMenu(true);
    };

    // Signal to mobile_bottom.js that navigation.js handles menu — prevents double-binding
    window.__MOBILE_NAV_ACTIVE__ = true;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
