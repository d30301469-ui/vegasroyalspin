(function () {
    'use strict';

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

    function openMenu(evt) {
        if (evt && typeof evt.preventDefault === 'function') {
            evt.preventDefault();
        }
        if (evt && typeof evt.stopPropagation === 'function') {
            evt.stopPropagation();
        }
        if (menuOpen) {
            return;
        }
        setMenuOpenState(true);
    }

    function closeMenu() {
        if (!menuOpen) {
            return;
        }
        setMenuOpenState(false);
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

        toggles.forEach(function (toggle) {
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                toggleMenu(e);
            });
            toggle.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleMenu(e);
                }
            });
        });
        if (closeBtn) {
            closeBtn.addEventListener('click', closeMenu);
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
                if (e.stopImmediatePropagation) {
                    e.stopImmediatePropagation();
                }
            }
        }, true);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeMenu();
            }
        });

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

    window.__closeMobileNavMenu = closeMenu;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
