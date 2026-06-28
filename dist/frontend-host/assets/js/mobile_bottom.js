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
        var lastScrollY = window.scrollY;
        var ticking = false;
        var state = { isHidden: false };

        function onScroll() {
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(function () {
                var currentScrollY = window.scrollY;
                var delta = currentScrollY - lastScrollY;
                lastScrollY = currentScrollY;

                var scrollingDown = delta > 0;
                var shouldHide = scrollingDown && currentScrollY > 80;

                if (mainMenu && shouldHide !== state.isHidden) {
                    state.isHidden = shouldHide;
                    if (shouldHide) {
                        mainMenu.classList.add('header-hidden');
                    } else {
                        mainMenu.classList.remove('header-hidden');
                    }
                }

                ticking = false;
            });
        }

        window.addEventListener('scroll', onScroll, { passive: true });

        mql.addEventListener('change', function (e) {
            if (!e.matches && mainMenu) {
                mainMenu.classList.remove('header-hidden');
                state.isHidden = false;
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
