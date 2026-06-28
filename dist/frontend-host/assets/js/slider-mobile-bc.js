(function () {
    'use strict';

    function updateFractionCounter(sw, total) {
        var pag = sw.pagination && sw.pagination.el;
        if (!pag || total < 1) {
            return;
        }
        var current = typeof sw.realIndex === 'number' ? sw.realIndex + 1 : 1;
        pag.textContent = current + ' / ' + total;
    }

    function initMobileBcSliders() {
        if (typeof window.Swiper === 'undefined') {
            return;
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
                watchOverflow: true,
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
        });

        document.querySelectorAll('[data-mobile-bc-slider-row] a.sdr-item-bc[href="javascript:void(0)"]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileBcSliders);
    } else {
        initMobileBcSliders();
    }
})();
