(function () {
    var scrollY = 0;

    function openMenu() {
        var menu = document.getElementById('mobileMenu');
        var overlay = document.getElementById('mobileMenu-overlay');
        var toggle = document.getElementById('menu-toggle');
        if (!menu || !overlay) return;

        // Mevcut scroll konumunu sakla ve gövdeyi sabitle
        scrollY = window.scrollY || window.pageYOffset || 0;
        document.body.classList.add('mobileMenu-locked');
        document.body.style.top = -scrollY + 'px';

        overlay.classList.add('is-open');
        menu.classList.add('is-open');
        if (toggle) toggle.classList.add('is-open');
    }

    function closeMenu() {
        var menu = document.getElementById('mobileMenu');
        var overlay = document.getElementById('mobileMenu-overlay');
        var toggle = document.getElementById('menu-toggle');
        if (!menu || !overlay) return;

        menu.classList.remove('is-open');
        overlay.classList.remove('is-open');
        if (toggle) toggle.classList.remove('is-open');

        // Body sabitlenmeden önceki scroll konumuna anlık (animasyonsuz) dön
        var topValue = document.body.style.top || '0';
        var lockedScroll = parseInt(topValue, 10);
        document.body.classList.remove('mobileMenu-locked');
        document.body.style.top = '';

        if (!isNaN(lockedScroll) && lockedScroll !== 0) {
            var html = document.documentElement;
            var previousBehavior = html.style.scrollBehavior;
            // Geçici olarak smooth davranışını kapat
            html.style.scrollBehavior = 'auto';
            window.scrollTo(0, -lockedScroll);
            // Önceki değeri geri yükle
            html.style.scrollBehavior = previousBehavior || '';
        }
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

        if (toggle) toggle.addEventListener('click', openMenu);
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
