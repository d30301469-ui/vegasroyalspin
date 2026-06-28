/**
 * Promosyonlar sayfası — tüm JavaScript
 * assets/js/promosyonlar.js
 */

(function () {
    'use strict';

    var cachedCards = null;
    var cachedPromoList = null;
    var HIDDEN_CLASS = 'promo-card-hidden';

    function getCards() {
        if (!cachedCards) {
            cachedCards = document.querySelectorAll('.promo-card, .bonus-card');
        }
        return cachedCards;
    }

    function getPromoList() {
        if (cachedPromoList === null) {
            cachedPromoList = window.__PROMO_LIST__ || [];
        }
        return cachedPromoList;
    }

    function initPromoCategoriesScroll() {
        var inner = document.querySelector('[data-promo-cats-scroll]');
        if (!inner) return;
        var wrap = inner.closest('[data-promo-cats-wrap]');
        if (!wrap) return;
        var btnL = wrap.querySelector('[data-promo-scroll="left"]');
        var btnR = wrap.querySelector('[data-promo-scroll="right"]');

        function update() {
            var overflow = inner.scrollWidth > inner.clientWidth + 2;
            wrap.classList.toggle('promo-cats--overflow', overflow);
            if (!overflow) {
                wrap.classList.remove('promo-cats--at-start', 'promo-cats--at-end');
                return;
            }
            var maxScroll = inner.scrollWidth - inner.clientWidth;
            wrap.classList.toggle('promo-cats--at-start', inner.scrollLeft <= 2);
            wrap.classList.toggle('promo-cats--at-end', inner.scrollLeft >= maxScroll - 2);
        }

        inner.addEventListener('scroll', update);
        window.addEventListener('resize', update);
        if (typeof ResizeObserver !== 'undefined') {
            try {
                new ResizeObserver(update).observe(inner);
            } catch (e) {}
        }
        if (btnL) {
            btnL.addEventListener('click', function () {
                inner.scrollBy({ left: -Math.min(140, inner.clientWidth * 0.6), behavior: 'smooth' });
            });
        }
        if (btnR) {
            btnR.addEventListener('click', function () {
                inner.scrollBy({ left: Math.min(140, inner.clientWidth * 0.6), behavior: 'smooth' });
            });
        }
        update();
        setTimeout(update, 50);
        setTimeout(update, 300);
    }

    function initCategoryFilter() {
        var bar = document.querySelector('.promo-categories-inner');
        if (!bar) return;

        var btns = bar.querySelectorAll('.promo-cat-btn');
        var cards = getCards();
        if (!btns.length || !cards.length) return;

        btns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                btns.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');

                var cat = btn.getAttribute('data-category');
                for (var i = 0; i < cards.length; i++) {
                    var card = cards[i];
                    var cardCat = card.getAttribute('data-category');
                    var show = !cat || cat === 'tumu' || cardCat === cat;
                    card.classList.toggle(HIDDEN_CLASS, !show);
                }
            });
        });
    }

    function handleGridClick(e) {
        var card = e.target.closest('.promo-card[data-promo-index], .bonus-card[data-promo-index]');
        if (!card) return;

        var index = card.getAttribute('data-promo-index');
        if (index === null || index === '') return;

        var list = getPromoList();
        var promo = list[parseInt(index, 10)];
        if (!promo || typeof window.BonusDetailModal === 'undefined') return;

        var imageUrl = promo.image_url || '';
        if (imageUrl && imageUrl.indexOf('http') !== 0 && imageUrl.indexOf('/') !== 0) {
            imageUrl = '/' + imageUrl;
        }
        window.BonusDetailModal.open({
            title: promo.title || '',
            imageUrl: imageUrl,
            sections: promo.sections || [],
            promotionId: typeof promo.promotionId === 'number' ? promo.promotionId : parseInt(promo.promotionId, 10) || 0,
            canClaim: !!promo.canClaim
        });
    }

    function initBonusDetailModal() {
        var promoGrid = document.querySelector('.promo-grid');
        var bonusGrid = document.querySelector('.bonus-grid');
        if (promoGrid) promoGrid.addEventListener('click', handleGridClick);
        if (bonusGrid && bonusGrid !== promoGrid) bonusGrid.addEventListener('click', handleGridClick);
    }

    function init() {
        if (window.__promosyonlarInit) return;
        window.__promosyonlarInit = true;
        initCategoryFilter();
        initPromoCategoriesScroll();
        initBonusDetailModal();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
