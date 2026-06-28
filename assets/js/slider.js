(function () {
    var MOBILE_BP = 768;
    var AUTO_SLIDE_MS = 6000;

    function isMobileWidth() {
        return (window.innerWidth || document.documentElement.clientWidth || 0) <= MOBILE_BP;
    }

    function syncVideo(el, shouldPlay) {
        if (el.tagName !== 'VIDEO') return;
        shouldPlay ? el.paused && el.play().catch(function () {}) : !el.paused && el.pause();
    }

    function updateSliderMedia() {
        var mob = isMobileWidth();
        var els = document.querySelectorAll('.home-hero-slider [data-desktop][data-mobile]');
        for (var i = 0; i < els.length; i++) {
            var el = els[i];
            var target = mob ? (el.getAttribute('data-mobile') || el.getAttribute('data-desktop'))
                : (el.getAttribute('data-desktop') || el.getAttribute('data-mobile'));
            if (target && el.getAttribute('src') !== target) {
                el.setAttribute('src', target);
                if (el.tagName === 'VIDEO') { el.load(); el.play().catch(function () {}); }
            }
        }
        var dEls = document.querySelectorAll('.home-hero-slider .slider-desktop-media');
        var mEls = document.querySelectorAll('.home-hero-slider .slider-mobile-media');
        for (var j = 0; j < dEls.length; j++) syncVideo(dEls[j], !mob);
        for (var k = 0; k < mEls.length; k++) syncVideo(mEls[k], mob);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var container = document.querySelector('.home-hero-slider-inner');
        if (!container) return;

        var slidesEl = container.querySelector('.slides');
        var sections = container.querySelectorAll('.slides .sdr-item-holder-bc, .slides section');
        var total = sections.length;
        var counter = container.querySelector('.carousel-count-arrow-container.with-count');
        var counterText = counter ? counter.querySelector('.home-hero-slider-counter-text') : null;
        var prevBtn = counter ? counter.querySelector('.home-hero-slider-counter-prev') : null;
        var nextBtn = counter ? counter.querySelector('.home-hero-slider-counter-next') : null;

        if (total === 0) return;

        var currentIndex = 0;
        var autoSlideTimer = null;
        var startX = 0, dragging = false, navigated = false, touchActive = false;

        function goTo(index) {
            if (index < 0) index = total - 1;
            if (index >= total) index = 0;
            currentIndex = index;
            for (var i = 0; i < sections.length; i++) {
                sections[i].classList.toggle('active', i === currentIndex);
                var vid = sections[i].querySelector('video');
                if (vid) {
                    if (i === currentIndex) vid.play().catch(function () {});
                    else vid.pause();
                }
            }
            if (counterText) counterText.textContent = (currentIndex + 1) + '/' + total;
            resetAutoSlide();
        }

        function next() {
            goTo(currentIndex + 1);
        }

        function prev() {
            goTo(currentIndex - 1);
        }

        function resetAutoSlide() {
            if (autoSlideTimer) clearTimeout(autoSlideTimer);
            autoSlideTimer = setTimeout(next, AUTO_SLIDE_MS);
        }

        function stopAutoSlide() {
            if (autoSlideTimer) clearTimeout(autoSlideTimer);
            autoSlideTimer = null;
        }

        // İlk slaytı aktif yap
        goTo(0);

        if (prevBtn) prevBtn.addEventListener('click', function () { prev(); });
        if (nextBtn) nextBtn.addEventListener('click', function () { next(); });

        container.addEventListener('mouseenter', stopAutoSlide);
        container.addEventListener('mouseleave', function () { resetAutoSlide(); });

        // Sürükleme ile önceki/sonraki
        container.addEventListener('mousedown', function (e) {
            if (e.button !== 0) return;
            dragging = true;
            navigated = false;
            startX = e.clientX;
        });

        document.addEventListener('mousemove', function (e) {
            if (!dragging || Math.abs(e.clientX - startX) < 40) return;
            if (e.clientX - startX > 0) prev();
            else next();
            navigated = true;
            dragging = false;
        });

        document.addEventListener('mouseup', function () { dragging = false; });
        document.addEventListener('mouseleave', function () { dragging = false; });

        container.addEventListener('touchstart', function (e) {
            if (!e.touches || !e.touches.length) return;
            touchActive = true;
            dragging = true;
            navigated = false;
            startX = e.touches[0].clientX;
        }, { passive: true });

        container.addEventListener('touchmove', function (e) {
            if (!touchActive || !e.touches || !e.touches.length) return;
            var delta = e.touches[0].clientX - startX;
            if (Math.abs(delta) < 36) return;
            if (delta > 0) prev();
            else next();
            navigated = true;
            dragging = false;
            touchActive = false;
        }, { passive: true });

        container.addEventListener('touchend', function () {
            dragging = false;
            touchActive = false;
        });
        container.addEventListener('touchcancel', function () {
            dragging = false;
            touchActive = false;
        });

        container.addEventListener('click', function (e) {
            if (navigated) {
                e.preventDefault();
                e.stopPropagation();
            }
        }, true);

        updateSliderMedia();
        window.addEventListener('load', updateSliderMedia);
        window.addEventListener('resize', updateSliderMedia);
        setTimeout(updateSliderMedia, 300);
        setTimeout(updateSliderMedia, 800);
    });
})();
