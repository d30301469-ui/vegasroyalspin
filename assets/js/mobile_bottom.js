(function () {
    // If mobile navigation.js is already managing the menu, delegate to it
    // to avoid double-binding and scroll lock races.
    if (window.__MOBILE_NAV_ACTIVE__) {
        // Still run highlightActiveTab and scroll behavior, but skip menu binding
        function highlightActiveTab() {
            var path = window.location.pathname;
            var items = document.querySelectorAll('.mobFooter-item[href]');
            items.forEach(function (item) {
                var href = item.getAttribute('href');
                if (href && href !== '#' && path.indexOf(href) === 0) {
                    item.classList.add('active');
                }
            });
        }

        function initMobileScrollBehavior() {
            var mql = window.matchMedia('(max-width: 992px)');
            if (!mql.matches) return;

            var mainMenu = document.querySelector('.mainMenu');
            if (mainMenu) {
                mainMenu.classList.remove('header-hidden');
            }

            if (typeof window.__syncHeaderStickyTop === 'function') {
                window.__syncHeaderStickyTop();
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                highlightActiveTab();
                initMobileScrollBehavior();
            });
        } else {
            highlightActiveTab();
            initMobileScrollBehavior();
        }
        return;
    }

    var scrollY = 0;

    function getSharedScrollLock() {
        if (window.__BodyScrollLock && typeof window.__BodyScrollLock.lock === 'function' && typeof window.__BodyScrollLock.unlock === 'function') {
            return window.__BodyScrollLock;
        }

        var state = {
            count: 0,
            scrollY: 0,
            prev: null
        };

        function lock() {
            state.count += 1;
            if (state.count > 1) return;

            var body = document.body;
            var docEl = document.documentElement;
            state.scrollY = window.scrollY || window.pageYOffset || 0;
            state.prev = {
                position: body.style.position,
                top: body.style.top,
                left: body.style.left,
                right: body.style.right,
                width: body.style.width,
                overflow: body.style.overflow,
                touchAction: body.style.touchAction,
                paddingRight: body.style.paddingRight
            };

            var scrollbarWidth = Math.max(0, window.innerWidth - docEl.clientWidth);
            if (scrollbarWidth > 0) {
                body.style.paddingRight = scrollbarWidth + 'px';
            }

            body.style.position = 'fixed';
            body.style.top = -state.scrollY + 'px';
            body.style.left = '0';
            body.style.right = '0';
            body.style.width = '100%';
            body.style.overflow = 'hidden';
            body.style.touchAction = 'none';
            body.classList.add('body-scroll-locked');
        }

        function unlock() {
            if (state.count <= 0) return;
            state.count -= 1;
            if (state.count > 0) return;

            var body = document.body;
            var restoreY = state.scrollY;
            var prev = state.prev || {};

            body.style.position = prev.position || '';
            body.style.top = prev.top || '';
            body.style.left = prev.left || '';
            body.style.right = prev.right || '';
            body.style.width = prev.width || '';
            body.style.overflow = prev.overflow || '';
            body.style.touchAction = prev.touchAction || '';
            body.style.paddingRight = prev.paddingRight || '';
            body.classList.remove('body-scroll-locked');

            var html = document.documentElement;
            var previousBehavior = html.style.scrollBehavior;
            html.style.scrollBehavior = 'auto';
            window.scrollTo(0, restoreY);
            html.style.scrollBehavior = previousBehavior || '';
        }

        window.__BodyScrollLock = {
            lock: lock,
            unlock: unlock
        };

        return window.__BodyScrollLock;
    }

    function openMenu() {
        var menu = document.getElementById('mobileMenu');
        var overlay = document.getElementById('mobileMenu-overlay');
        var toggle = document.getElementById('menu-toggle');
        if (!menu) return;

        // Mevcut scroll konumunu sakla ve gövdeyi sabitle
        scrollY = window.scrollY || window.pageYOffset || 0;
        getSharedScrollLock().lock();
        document.body.classList.add('mobileMenu-locked');

        if (overlay) overlay.classList.add('is-open');
        menu.classList.add('is-open');
        menu.setAttribute('aria-hidden', 'false');
        if (toggle) toggle.classList.add('is-open');
    }

    function closeMenu() {
        var menu = document.getElementById('mobileMenu');
        var overlay = document.getElementById('mobileMenu-overlay');
        var toggle = document.getElementById('menu-toggle');
        if (!menu) return;

        menu.classList.remove('is-open');
        menu.setAttribute('aria-hidden', 'true');
        if (overlay) overlay.classList.remove('is-open');
        if (toggle) toggle.classList.remove('is-open');

        // Body sabitlenmeden önceki scroll konumuna anlık (animasyonsuz) dön
        document.body.classList.remove('mobileMenu-locked');
        getSharedScrollLock().unlock();
    }

    function highlightActiveTab() {
        var path = window.location.pathname;
        var items = document.querySelectorAll('.mobFooter-item[href]');
        items.forEach(function (item) {
            var href = item.getAttribute('href');
            if (href && href !== '#' && path.indexOf(href) === 0) {
                item.classList.add('active');
            }
        });
    }

    function init() {
        var toggle = document.getElementById('menu-toggle');
        var closeBtn = document.getElementById('mobileMenu-close');
        var overlay = document.getElementById('mobileMenu-overlay');

        if (toggle) {
            ['click', 'touchend', 'pointerup'].forEach(function (eventName) {
                toggle.addEventListener(eventName, function (e) {
                    if (e && typeof e.preventDefault === 'function') {
                        e.preventDefault();
                    }
                    if (e && typeof e.stopPropagation === 'function') {
                        e.stopPropagation();
                    }
                    openMenu();
                }, true);
            });
        }
        if (closeBtn) closeBtn.addEventListener('click', closeMenu);
        if (overlay) overlay.addEventListener('click', closeMenu);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeMenu();
        });

        highlightActiveTab();
    }

    function initMobileScrollBehavior() {
        var mql = window.matchMedia('(max-width: 992px)');
        if (!mql.matches) return;

        var mainMenu = document.querySelector('.mainMenu');
        if (mainMenu) {
            mainMenu.classList.remove('header-hidden');
        }

        if (typeof window.__syncHeaderStickyTop === 'function') {
            window.__syncHeaderStickyTop();
        }

        mql.addEventListener('change', function (e) {
            if (mainMenu) {
                mainMenu.classList.remove('header-hidden');
            }

            if (typeof window.__syncHeaderStickyTop === 'function') {
                window.__syncHeaderStickyTop();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            init();
            initMobileScrollBehavior();
        });
    } else {
        init();
        initMobileScrollBehavior();
    }
})();
