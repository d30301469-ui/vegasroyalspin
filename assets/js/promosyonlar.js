/**
 * Promosyonlar sayfası — tüm JavaScript
 * assets/js/promosyonlar.js
 */

(function () {
    'use strict';

    var cachedCards = null;
    var cachedPromoList = null;
    var HIDDEN_CLASS = 'promo-card-hidden';
    var lastOpenIndex = null;
    var lastOpenAt = 0;

    function normalizeCategory(raw) {
        var value = (raw || '').toString().toLowerCase().trim();
        if (!value) return '';
        value = value.replace(/[\s-]+/g, '_');
        if (value === 'all' || value === 'tumu' || value === 'tum' || value === 'hepsi') return 'tumu';
        if (value === 'livecasino' || value === 'live_casino') return 'live_casino';
        if (value === 'sport' || value === 'sportsbook' || value === 'spor' || value === 'sports') return 'sports';
        if (value === 'lossbonus' || value === 'loss_bonus' || value === 'kayip_bonusu' || value === 'kayipbonusu') return 'loss_bonus';
        return value;
    }

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

        function applyCategoryFilter(btn) {
            btns.forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');

            var cat = normalizeCategory(btn.getAttribute('data-category'));
            for (var i = 0; i < cards.length; i++) {
                var card = cards[i];
                var cardCat = normalizeCategory(card.getAttribute('data-category'));
                var show = !cat || cat === 'tumu' || cardCat === cat;
                card.classList.toggle(HIDDEN_CLASS, !show);
            }
        }

        btns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                applyCategoryFilter(btn);
            });

            btn.addEventListener('touchend', function (e) {
                if (e && typeof e.preventDefault === 'function') {
                    e.preventDefault();
                }
                applyCategoryFilter(btn);
            }, { passive: false });
        });
    }

    function handleGridClick(e) {
        var card = e.target.closest('.promo-card[data-promo-index], .bonus-card[data-promo-index]');
        if (!card) return;

        if (e && typeof e.preventDefault === 'function') {
            e.preventDefault();
        }

        var index = card.getAttribute('data-promo-index');
        if (index === null || index === '') return;

        openPromoByIndex(index);
    }

    function openPromoByIndex(index) {
        if (index === null || index === '') return;

        var now = Date.now();
        if (lastOpenIndex === String(index) && (now - lastOpenAt) < 300) {
            return;
        }
        lastOpenIndex = String(index);
        lastOpenAt = now;

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
            linkUrl: promo.link_url || '',
            sections: promo.sections || [],
            promotionId: typeof promo.promotionId === 'number' ? promo.promotionId : parseInt(promo.promotionId, 10) || 0,
            canClaim: !!promo.canClaim
        });
    }

    window.__openPromoModalByIndex = openPromoByIndex;

    function bindDirectCardOpenListeners() {
        var cards = getCards();
        if (!cards || !cards.length) return;

        for (var i = 0; i < cards.length; i++) {
            var card = cards[i];
            if (!card || card.getAttribute('data-promo-direct-bound') === '1') {
                continue;
            }
            card.setAttribute('data-promo-direct-bound', '1');

            card.addEventListener('click', function (e) {
                var idx = this.getAttribute('data-promo-index');
                if (idx === null || idx === '') return;
                if (e && typeof e.preventDefault === 'function') e.preventDefault();
                openPromoByIndex(idx);
            });

            card.addEventListener('touchend', function (e) {
                var idx = this.getAttribute('data-promo-index');
                if (idx === null || idx === '') return;
                if (e && typeof e.preventDefault === 'function') e.preventDefault();
                openPromoByIndex(idx);
            }, { passive: false });
        }
    }

    function bindGlobalCardOpenDelegation() {
        if (document.documentElement.getAttribute('data-promo-global-bound') === '1') {
            return;
        }
        document.documentElement.setAttribute('data-promo-global-bound', '1');

        function tryOpenFromEvent(e) {
            var t = e && e.target;
            if (!t || !t.closest) return;
            var card = t.closest('.promo-card[data-promo-index], .bonus-card[data-promo-index]');
            if (!card) return;
            var idx = card.getAttribute('data-promo-index');
            if (idx === null || idx === '') return;

            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            openPromoByIndex(idx);
        }

        document.addEventListener('click', tryOpenFromEvent, true);
        document.addEventListener('touchend', tryOpenFromEvent, { capture: true, passive: false });
    }

    function initBonusDetailModal() {
        var promoGrid = document.querySelector('.promo-grid');
        var bonusGrid = document.querySelector('.bonus-grid');
        if (promoGrid) promoGrid.addEventListener('click', handleGridClick);
        if (bonusGrid && bonusGrid !== promoGrid) bonusGrid.addEventListener('click', handleGridClick);
        bindDirectCardOpenListeners();
        bindGlobalCardOpenDelegation();
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
