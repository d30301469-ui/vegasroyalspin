(function () {
    'use strict';

    var RETRY_DELAY_MS = 150;
    var MAX_RETRY_COUNT = 30;
    var retryCount = 0;
    var retryTimer = null;
    var readyBound = false;

    function updateFractionCounter(sw, total) {
        var pag = sw.pagination && sw.pagination.el;
        if (!pag || total < 1) {
            return;
        }
        var current = typeof sw.realIndex === 'number' ? sw.realIndex + 1 : 1;
        pag.textContent = current + ' / ' + total;
    }

    function initFallbackWithoutSwiper() {
        document.querySelectorAll('[data-mobile-bc-slider] .mobile-bc-hero-swiper').forEach(function (root) {
            if (root.getAttribute('data-fallback-ready') === '1') {
                return;
            }
            var slides = root.querySelectorAll('.swiper-slide');
            if (!slides.length) {
                return;
            }

            root.setAttribute('data-fallback-ready', '1');
            root.classList.add('mobile-bc-swiper-fallback');
            for (var i = 0; i < slides.length; i++) {
                slides[i].style.display = i === 0 ? '' : 'none';
            }

            var paginationEl = root.querySelector('.swiper-pagination');
            if (paginationEl) {
                paginationEl.textContent = '1 / ' + slides.length;
            }
        });
    }

    function initMobileBcSliders() {
        if (typeof window.Swiper === 'undefined') {
            initFallbackWithoutSwiper();
            return false;
        }

        document.querySelectorAll('[data-mobile-bc-slider] .mobile-bc-hero-swiper').forEach(function (root) {
            if (root.swiper) {
                return;
            }

            var slides = root.querySelectorAll('.swiper-slide');
            var count = slides.length;
            if (count === 0) {
                return;
            }

            var paginationEl = root.querySelector('.swiper-pagination');
            var loop = count > 1;

            var swiper = new window.Swiper(root, {
                loop: loop,
                slidesPerView: 1,
                spaceBetween: 0,
                speed: 400,
                watchOverflow: false,
                resistanceRatio: 0.65,
                observer: true,
                observeParents: true,
                autoplay: loop
                    ? {
                        delay: 6000,
                        disableOnInteraction: false,
                        pauseOnMouseEnter: true
                    }
                    : false,
                pagination: paginationEl
                    ? {
                        el: paginationEl,
                        type: 'fraction',
                        clickable: false
                    }
                    : false,
                on: {
                    init: function (sw) {
                        updateFractionCounter(sw, count);
                        sw.update();
                    },
                    slideChange: function (sw) {
                        updateFractionCounter(sw, count);
                    },
                    resize: function (sw) {
                        updateFractionCounter(sw, count);
                        sw.update();
                    }
                }
            });

            requestAnimationFrame(function () {
                updateFractionCounter(swiper, count);
                swiper.update();
            });

            root.removeAttribute('data-fallback-ready');
            root.classList.remove('mobile-bc-swiper-fallback');
        });

        document.querySelectorAll('[data-mobile-bc-slider-row] a.sdr-item-bc[href="javascript:void(0)"]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
            });
        });

        return true;
    }

    function clearRetry() {
        if (retryTimer) {
            clearTimeout(retryTimer);
            retryTimer = null;
        }
    }

    function scheduleRetry() {
        if (retryCount >= MAX_RETRY_COUNT || retryTimer) {
            return;
        }
        retryTimer = setTimeout(function () {
            retryTimer = null;
            retryCount += 1;
            bootstrapMobileBcSliders();
        }, RETRY_DELAY_MS);
    }

    function bootstrapMobileBcSliders() {
        var ok = initMobileBcSliders();
        if (ok) {
            clearRetry();
            return;
        }
        scheduleRetry();
    }

    function bindReadyHooks() {
        if (readyBound) {
            return;
        }
        readyBound = true;

        window.addEventListener('pageshow', function () {
            retryCount = 0;
            bootstrapMobileBcSliders();
        });

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                bootstrapMobileBcSliders();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            bindReadyHooks();
            bootstrapMobileBcSliders();
        });
    } else {
        bindReadyHooks();
        bootstrapMobileBcSliders();
    }
})();
