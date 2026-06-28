(function () {
    'use strict';

    var menuOpen = false;

    function getMenu() {
        return document.getElementById('mobileMenu');
    }

    function getToggle() {
        return document.getElementById('menu-toggle');
    }

    function getRoot() {
        return document.getElementById('root');
    }

    function setMenuOpenState(isOpen) {
        var menu = getMenu();
        var toggle = getToggle();
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

        if (toggle) {
            toggle.classList.toggle('is-open', isOpen);
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }

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
        var toggle = getToggle();
        var closeBtn = document.getElementById('mobileMenu-close');
        var menu = getMenu();

        if (toggle) {
            toggle.addEventListener('click', toggleMenu);
            toggle.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleMenu(e);
                }
            });
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', closeMenu);
        }

        if (menu) {
            menu.addEventListener('click', function (e) {
                var link = e.target.closest('a.app-nav-link[href]');
                if (!link || link.getAttribute('href') === '#') {
                    return;
                }
                closeMenu();
            });
        }

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

        var lastScrollY = window.scrollY || window.pageYOffset || 0;
        var ticking = false;
        var state = { isHidden: false };
        var revealY = 80;
        var deltaThreshold = 6;
        var hideAfterScrollDown = 22;
        var showAfterScrollUp = 18;
        var dirAcc = 0;

        function setMenuHidden(hidden) {
            if (menuOpen) {
                return;
            }
            if (hidden === state.isHidden) {
                return;
            }
            state.isHidden = hidden;
            dirAcc = 0;
            if (hidden) {
                mainMenu.classList.add('header-hidden');
            } else {
                mainMenu.classList.remove('header-hidden');
            }
            if (typeof window.__syncHeaderStickyTop === 'function') {
                window.__syncHeaderStickyTop();
            }
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    lastScrollY = window.scrollY || window.pageYOffset || 0;
                });
            });
        }

        function onScroll() {
            if (ticking) {
                return;
            }
            ticking = true;
            window.requestAnimationFrame(function () {
                var currentScrollY = window.scrollY || window.pageYOffset || 0;
                var delta = currentScrollY - lastScrollY;
                lastScrollY = currentScrollY;

                if (currentScrollY <= revealY) {
                    dirAcc = 0;
                    setMenuHidden(false);
                    ticking = false;
                    return;
                }

                if (Math.abs(delta) < deltaThreshold) {
                    ticking = false;
                    return;
                }

                if (delta > 0) {
                    if (dirAcc < 0) {
                        dirAcc = 0;
                    }
                    dirAcc += delta;
                    if (dirAcc >= hideAfterScrollDown) {
                        setMenuHidden(true);
                    }
                } else {
                    if (dirAcc > 0) {
                        dirAcc = 0;
                    }
                    dirAcc += delta;
                    if (dirAcc <= -showAfterScrollUp) {
                        setMenuHidden(false);
                    }
                }

                ticking = false;
            });
        }

        window.addEventListener('scroll', onScroll, { passive: true });

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
