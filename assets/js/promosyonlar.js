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
    var TOUCH_MOVE_THRESHOLD = 10;
    var TOUCH_SCROLL_THRESHOLD = 10;
    var TOUCH_TAP_MAX_DURATION = 700;

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
                card.style.display = show ? '' : 'none';
                card.setAttribute('aria-hidden', show ? 'false' : 'true');
            }
        }

        function applyFromEventTarget(target) {
            if (!target || !target.closest) return false;
            var btn = target.closest('.promo-cat-btn');
            if (!btn || !bar.contains(btn)) return false;
            applyCategoryFilter(btn);
            return true;
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

        // Bazı mobil tarayıcılarda click/touchend sırası farklı olabildiği için pointerup fallback.
        bar.addEventListener('pointerup', function (e) {
            applyFromEventTarget(e && e.target ? e.target : null);
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

    function firstTouchPoint(e) {
        if (!e) return null;
        if (e.changedTouches && e.changedTouches.length) return e.changedTouches[0];
        if (e.touches && e.touches.length) return e.touches[0];
        return null;
    }

    function setCardTouchMeta(card, e) {
        if (!card) return;
        var point = firstTouchPoint(e);
        if (!point) return;
        card.__promoTouchMeta = {
            x: point.clientX,
            y: point.clientY,
            at: Date.now(),
            scrollY: window.scrollY || window.pageYOffset || 0
        };
    }

    function clearCardTouchMeta(card) {
        if (!card) return;
        card.__promoTouchMeta = null;
    }

    function isIntentionalCardTap(card, e) {
        if (!card || !card.__promoTouchMeta) return false;
        var meta = card.__promoTouchMeta;
        clearCardTouchMeta(card);

        var point = firstTouchPoint(e);
        if (!point) return false;

        var dx = Math.abs(point.clientX - meta.x);
        var dy = Math.abs(point.clientY - meta.y);
        var moved = dx > TOUCH_MOVE_THRESHOLD || dy > TOUCH_MOVE_THRESHOLD;
        var dt = Date.now() - meta.at;
        var scrollDelta = Math.abs((window.scrollY || window.pageYOffset || 0) - meta.scrollY);

        if (moved) return false;
        if (scrollDelta > TOUCH_SCROLL_THRESHOLD) return false;
        if (dt > TOUCH_TAP_MAX_DURATION) return false;
        return true;
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

            card.addEventListener('touchstart', function (e) {
                setCardTouchMeta(this, e);
            }, { passive: true });

            card.addEventListener('touchcancel', function () {
                clearCardTouchMeta(this);
            }, { passive: true });

            card.addEventListener('touchend', function (e) {
                var idx = this.getAttribute('data-promo-index');
                if (idx === null || idx === '') return;
                if (!isIntentionalCardTap(this, e)) return;
                openPromoByIndex(idx);
            }, { passive: true });
        }
    }

    function bindGlobalCardOpenDelegation() {
        if (document.documentElement.getAttribute('data-promo-global-bound') === '1') {
            return;
        }
        document.documentElement.setAttribute('data-promo-global-bound', '1');

        var suppressCardClickUntil = 0;
        var lastTouchState = {
            card: null,
            x: 0,
            y: 0,
            scrollY: 0,
            moved: false
        };

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

        function suppressIfNeeded(e) {
            var t = e && e.target;
            if (!t || !t.closest) return;
            var card = t.closest('.promo-card[data-promo-index], .bonus-card[data-promo-index]');
            if (!card) return;
            if (Date.now() < suppressCardClickUntil) {
                if (typeof e.preventDefault === 'function') e.preventDefault();
                if (typeof e.stopPropagation === 'function') e.stopPropagation();
                if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
            }
        }

        document.addEventListener('touchstart', function (e) {
            var t = e && e.target;
            if (!t || !t.closest) {
                lastTouchState.card = null;
                return;
            }
            var card = t.closest('.promo-card[data-promo-index], .bonus-card[data-promo-index]');
            if (!card) {
                lastTouchState.card = null;
                return;
            }
            var point = firstTouchPoint(e);
            if (!point) return;
            lastTouchState.card = card;
            lastTouchState.x = point.clientX;
            lastTouchState.y = point.clientY;
            lastTouchState.scrollY = window.scrollY || window.pageYOffset || 0;
            lastTouchState.moved = false;
        }, { capture: true, passive: true });

        document.addEventListener('touchmove', function (e) {
            if (!lastTouchState.card) return;
            var point = firstTouchPoint(e);
            if (!point) return;
            var dx = Math.abs(point.clientX - lastTouchState.x);
            var dy = Math.abs(point.clientY - lastTouchState.y);
            if (dx > TOUCH_MOVE_THRESHOLD || dy > TOUCH_MOVE_THRESHOLD) {
                lastTouchState.moved = true;
            }
        }, { capture: true, passive: true });

        document.addEventListener('touchend', function () {
            if (!lastTouchState.card) return;
            var scrollDelta = Math.abs((window.scrollY || window.pageYOffset || 0) - lastTouchState.scrollY);
            if (lastTouchState.moved || scrollDelta > TOUCH_SCROLL_THRESHOLD) {
                suppressCardClickUntil = Date.now() + 500;
            }
            lastTouchState.card = null;
        }, { capture: true, passive: true });

        document.addEventListener('click', suppressIfNeeded, true);

        document.addEventListener('click', tryOpenFromEvent, true);
        document.addEventListener('touchend', function (e) {
            var t = e && e.target;
            if (!t || !t.closest) return;
            var card = t.closest('.promo-card[data-promo-index], .bonus-card[data-promo-index]');
            if (!card) return;
            if (!isIntentionalCardTap(card, e)) return;
            tryOpenFromEvent(e);
        }, { capture: true, passive: true });
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
