/**
 * Bonus Detay Modal — aç/kapa, accordion, ESC, erişilebilirlik
 * assets/js/bonus-detail-modal.js
 */

(function (global) {
    'use strict';

    var overlay = null;
    var modal = null;
    var closeBtn = null;
    var backBtn = null;
    var accordionList = null;
    var titleEl = null;
    var imgEl = null;
    var claimWrap = null;
    var linkCta = null;
    var claimSubmit = null;
    var claimStatus = null;
    var claimLogin = null;
    var currentPromotionId = 0;
    var claimSubmitListenerBound = false;
    var focusableSelector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
    var Shared = global.BetcoAuthShared || {};
    function apiUrl(path) {
        return Shared.apiUrl ? Shared.apiUrl(path) : path;
    }
    function memberAuthHeaders(extra) {
        if (Shared.memberAuthHeaders) {
            return Shared.memberAuthHeaders(extra);
        }
        var headers = extra || {};
        var csrf = (global.__CSRF_TOKEN__ || '').trim();
        if (csrf) headers['X-CSRF-Token'] = csrf;
        return headers;
    }
    var PROMO_CLAIM_URL = apiUrl('/api/v2/bonus-claim');
    var previousActiveElement = null;
    var previousMobileScrollY = null;
    var escapeEl = null;
    var isScrollLockedByModal = false;

    function openLoginModal(nextPath) {
        var targetPath = (typeof nextPath === 'string' && nextPath.trim()) ? nextPath.trim() : '/promotions';
        var nextEl = document.getElementById('loginFormNext');
        if (nextEl) {
            nextEl.value = targetPath;
        }
        if (typeof global.__openLoginModal === 'function') {
            global.__openLoginModal();
            return true;
        }
        if (global.MaltabetAuth && typeof global.MaltabetAuth.showLoginModal === 'function') {
            global.MaltabetAuth.showLoginModal();
            return true;
        }
        if (typeof global.showModalById === 'function') {
            global.showModalById('login2');
            return true;
        }
        var loginBtn = document.getElementById('Giris');
        if (loginBtn && typeof loginBtn.click === 'function') {
            loginBtn.click();
            return true;
        }
        return false;
    }

    function getSharedScrollLock() {
        if (global.__BodyScrollLock && typeof global.__BodyScrollLock.lock === 'function' && typeof global.__BodyScrollLock.unlock === 'function') {
            return global.__BodyScrollLock;
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
            state.scrollY = global.scrollY || global.pageYOffset || 0;
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

            var scrollbarWidth = Math.max(0, global.innerWidth - docEl.clientWidth);
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
            global.scrollTo(0, restoreY);
            html.style.scrollBehavior = previousBehavior || '';
        }

        global.__BodyScrollLock = {
            lock: lock,
            unlock: unlock
        };

        return global.__BodyScrollLock;
    }

    function getElements() {
        overlay = document.getElementById('bonus-detail-modal-overlay');
        modal = document.getElementById('bonus-detail-modal');
        if (modal) {
            closeBtn = modal.querySelector('.bonus-modal-close');
            backBtn = modal.querySelector('.bonus-modal-back');
            accordionList = modal.querySelector('.bonus-accordion-list');
            titleEl = modal.querySelector('#bonus-modal-title');
            imgEl = modal.querySelector('#bonus-modal-image');
            claimWrap = document.getElementById('bonus-modal-claim');
            linkCta = document.getElementById('bonus-modal-link');
            claimSubmit = document.getElementById('bonus-modal-claim-submit');
            claimStatus = document.getElementById('bonus-modal-claim-status');
            claimLogin = document.getElementById('bonus-modal-claim-login');
        } else {
            closeBtn = backBtn = accordionList = titleEl = imgEl = null;
            claimWrap = linkCta = claimSubmit = claimStatus = claimLogin = null;
        }
    }

    function createOverlayIfMissing() {
        var existing = document.getElementById('bonus-detail-modal-overlay');
        if (existing) {
            /* Static PHP partial içinde olabilir — body'ye taşı ki z-index header'ı geçsin */
            if (existing.parentElement !== document.body) {
                document.body.appendChild(existing);
                /* Elemanı taşıdıktan sonra bağlantıları sıfırla ve yeniden bağla */
                existing.removeAttribute('data-bonus-modal-bound');
                if (existing.querySelector('.bonus-accordion-list')) {
                    existing.querySelector('.bonus-accordion-list').removeAttribute('data-bonus-accordion-bound');
                }
            }
            getElements();
            bindCloseAndOverlay();
            bindAccordionDelegation();
            bindClaimSubmitOnce();
            return;
        }
        var div = document.createElement('div');
        div.id = 'bonus-detail-modal-overlay';
        div.className = 'bonus-modal-overlay';
        div.setAttribute('aria-hidden', 'true');
        div.innerHTML = [
            '<div id="bonus-detail-modal" class="bonus-modal" role="dialog" aria-modal="true" aria-labelledby="bonus-modal-title" aria-hidden="true" tabindex="-1">',
            '  <button type="button" class="bonus-modal-close" aria-label="Modalı kapat"><span aria-hidden="true">&times;</span></button>',
            '  <div class="bonus-modal-header">',
            '    <button type="button" class="bonus-modal-back" aria-label="Geri">',
            '      <svg class="bonus-modal-back-icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z" fill="currentColor"/></svg>',
            '    </button>',
            '    <h2 id="bonus-modal-title" class="bonus-modal-title"></h2>',
            '  </div>',
            '  <div class="bonus-modal-body">',
            '    <div class="bonus-modal-left">',
            '      <div class="bonus-image-wrap">',
            '        <img id="bonus-modal-image" src="" alt="">',
            '      </div>',
            '    </div>',
            '    <div class="bonus-modal-right">',
            '      <div class="bonus-accordion-list" role="list"></div>',
            '      <div class="bonus-modal-claim" id="bonus-modal-claim" hidden>',
            '        <div class="bonus-modal-claim-actions">',
            '          <a class="bonus-modal-claim-login bonus-modal-link" id="bonus-modal-link" href="#" hidden>Promosyona git</a>',
            '        </div>',
            '        <div class="bonus-modal-claim-actions">',
            '          <a class="bonus-modal-claim-login" id="bonus-modal-claim-login" href="/login">Giriş yap</a>',
            '          <button type="button" class="bonus-modal-claim-submit" id="bonus-modal-claim-submit">Bonus talep et</button>',
            '        </div>',
            '        <p class="bonus-modal-claim-status" id="bonus-modal-claim-status" role="status" aria-live="polite"></p>',
            '      </div>',
            '    </div>',
            '  </div>',
            '</div>'
        ].join('\n');
        document.body.appendChild(div);
        getElements();
        bindCloseAndOverlay();
        bindAccordionDelegation();
        bindClaimSubmitOnce();
    }

    function escapeHtml(text) {
        if (!escapeEl) escapeEl = document.createElement('div');
        escapeEl.textContent = text;
        return escapeEl.innerHTML;
    }

    /** Tek listener: accordion list üzerinde delegation */
    function bindAccordionDelegation() {
        if (!accordionList || accordionList.getAttribute('data-bonus-accordion-bound') === '1') return;
        accordionList.setAttribute('data-bonus-accordion-bound', '1');
        accordionList.addEventListener('click', function (e) {
            var trigger = e.target.closest('.bonus-accordion-trigger');
            if (!trigger) return;
            var item = trigger.closest('.bonus-accordion-item');
            if (!item) return;
            var isOpen = item.classList.contains('is-open');
            if (isOpen) {
                item.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
            } else {
                item.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
            }
        });
    }

    function bindClaimSubmitOnce() {
        if (claimSubmitListenerBound) return;
        claimSubmitListenerBound = true;
        document.addEventListener('click', function (e) {
            var loginLink = e.target && e.target.closest ? e.target.closest('#bonus-modal-claim-login') : null;
            if (!loginLink) return;

            e.preventDefault();
            var nextPath = location && location.pathname ? location.pathname : '/promotions';
            close();
            if (!openLoginModal(nextPath)) {
                global.location.href = loginLink.getAttribute('href') || '/login';
            }
        });
        document.addEventListener('click', function (e) {
            var btn = e.target && e.target.closest ? e.target.closest('#bonus-modal-claim-submit') : null;
            if (!btn || btn.disabled) return;
            e.preventDefault();
            if (!currentPromotionId) return;
            var body = { promotionId: currentPromotionId };
            if (claimStatus) {
                claimStatus.textContent = '';
                claimStatus.classList.remove('is-error', 'is-success');
            }
            btn.disabled = true;
            fetch(PROMO_CLAIM_URL, {
                method: 'POST',
                credentials: 'same-origin',
                headers: memberAuthHeaders({ 'Content-Type': 'application/json', Accept: 'application/json' }),
                body: JSON.stringify(body)
            })
                .then(function (res) {
                    return res.text().then(function (text) {
                        var json = {};
                        try {
                            json = text ? JSON.parse(text) : {};
                        } catch (ignore) {}
                        return { res: res, json: json };
                    });
                })
                .then(function (r) {
                    var j = r.json || {};
                    var ok = !!j.success && r.res.ok;
                    var line = '';
                    if (ok && j.data && typeof j.data === 'object' && j.data.message) {
                        line = String(j.data.message);
                    } else if (j.message) {
                        line = String(j.message);
                    } else {
                        line = ok ? 'Talebiniz alındı.' : 'İşlem tamamlanamadı.';
                    }
                    if (claimStatus) {
                        claimStatus.textContent = line;
                        claimStatus.classList.toggle('is-success', ok);
                        claimStatus.classList.toggle('is-error', !ok);
                    }
                    if (ok && global.MaltabetToast) {
                        global.MaltabetToast.success(line);
                    }
                })
                .catch(function () {
                    if (claimStatus) {
                        claimStatus.textContent = 'Bağlantı hatası. Lütfen tekrar deneyin.';
                        claimStatus.classList.add('is-error');
                        claimStatus.classList.remove('is-success');
                    }
                })
                .then(function () {
                    btn.disabled = false;
                });
        });
    }

    function configureClaimBlock(data) {
        getElements();
        if (!claimWrap) return;
        currentPromotionId = 0;
        var hasLink = false;
        if (linkCta) {
            var href = data && typeof data.linkUrl === 'string' ? data.linkUrl.trim() : '';
            var isExternal = /^https?:\/\//i.test(href);
            hasLink = href !== '';
            linkCta.hidden = href === '';
            if (href !== '') {
                linkCta.setAttribute('href', href);
                linkCta.setAttribute('target', isExternal ? '_blank' : '_self');
                linkCta.setAttribute('rel', isExternal ? 'noopener noreferrer' : 'noopener');
            } else {
                linkCta.removeAttribute('href');
                linkCta.removeAttribute('target');
                linkCta.removeAttribute('rel');
            }
        }
        if (!data || !data.canClaim || !data.promotionId) {
            claimWrap.hidden = !hasLink;
            if (claimSubmit) {
                claimSubmit.hidden = true;
                claimSubmit.disabled = true;
            }
            if (claimLogin) {
                claimLogin.hidden = true;
            }
            if (claimStatus) {
                claimStatus.textContent = '';
                claimStatus.classList.remove('is-error', 'is-success');
            }
            return;
        }
        currentPromotionId = data.promotionId;
        claimWrap.hidden = false;
        if (claimStatus) {
            claimStatus.textContent = '';
            claimStatus.classList.remove('is-error', 'is-success');
        }
        var logged = !!global.__USER_LOGGED_IN__;
        if (claimSubmit) {
            claimSubmit.disabled = !logged;
            claimSubmit.hidden = !logged;
        }
        if (claimLogin) {
            claimLogin.hidden = logged;
        }
    }

    /** İçerik HTML ise güvenilir kaynaktan gelmeli; production'da sanitize edin. */
    function setContent(data) {
        if (titleEl) titleEl.textContent = data.title || '';
        if (imgEl) {
            imgEl.decoding = 'async';
            imgEl.src = data.imageUrl || '';
            imgEl.alt = data.title || 'Bonus görseli';
        }
        if (accordionList && data.sections && data.sections.length) {
            var fragment = document.createDocumentFragment();
            var i = 0;
            var len = data.sections.length;
            for (i = 0; i < len; i++) {
                var section = data.sections[i];
                var id = 'bonus-accordion-content-' + i;
                var triggerId = 'bonus-accordion-trigger-' + i;
                var content = section.content || '';
                var item = document.createElement('div');
                item.className = 'bonus-accordion-item';
                item.setAttribute('role', 'listitem');
                item.innerHTML = [
                    '<button type="button" class="bonus-accordion-trigger" aria-expanded="false" aria-controls="' + id + '" id="' + triggerId + '">',
                    '  <span class="bonus-accordion-icon" aria-hidden="true">',
                    '    <svg viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z" fill="currentColor"/></svg>',
                    '  </span>',
                    '<span class="bonus-accordion-title">' + escapeHtml(section.title) + '</span>',
                    '</button>',
                    '<div id="' + id + '" class="bonus-accordion-content" role="region" aria-labelledby="' + triggerId + '">',
                    '  <div class="bonus-accordion-content-inner">' + content + '</div>',
                    '</div>'
                ].join('');
                /* Angular zone.js interferansını önlemek için delegation yerine doğrudan listener */
                (function attachAccordionClick(itemEl) {
                    var btn = itemEl.querySelector('.bonus-accordion-trigger');
                    if (btn) {
                        btn.addEventListener('click', function (e) {
                            e.stopPropagation(); /* delegationun çift-tetiklenmesini önle */
                            var isOpen = itemEl.classList.contains('is-open');
                            if (isOpen) {
                                itemEl.classList.remove('is-open');
                                btn.setAttribute('aria-expanded', 'false');
                            } else {
                                itemEl.classList.add('is-open');
                                btn.setAttribute('aria-expanded', 'true');
                            }
                        });
                    }
                }(item));
                fragment.appendChild(item);
            }
            accordionList.innerHTML = '';
            accordionList.appendChild(fragment);
        }
        configureClaimBlock(data);
    }

    function resetModalState() {
        if (titleEl) {
            titleEl.textContent = '';
        }
        if (imgEl) {
            imgEl.removeAttribute('src');
            imgEl.alt = '';
        }
        if (accordionList) {
            accordionList.innerHTML = '';
        }
        if (claimStatus) {
            claimStatus.textContent = '';
            claimStatus.classList.remove('is-error', 'is-success');
        }
        var body = modal ? modal.querySelector('.bonus-modal-body') : null;
        if (body) {
            body.scrollTop = 0;
        }
    }

    function trapFocus(e) {
        if (!modal || e.key !== 'Tab') return;
        var focusable = modal.querySelectorAll(focusableSelector);
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (e.shiftKey) {
            if (document.activeElement === first) {
                e.preventDefault();
                last.focus();
            }
        } else {
            if (document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    }

    function handleKeydown(e) {
        if (e.key === 'Escape') {
            close();
        }
        if (e.key === 'Tab') {
            trapFocus(e);
        }
    }

    function open(data) {
        createOverlayIfMissing();
        getElements();
        if (!overlay || !modal) return;
        bindClaimSubmitOnce();
        bindCloseAndOverlay();
        bindAccordionDelegation();

        var payload = null;
        if (data && typeof data === 'object') {
            payload = {
                title: data.title,
                imageUrl: data.imageUrl,
                linkUrl: data.linkUrl,
                sections: data.sections,
                promotionId: typeof data.promotionId === 'number' ? data.promotionId : parseInt(data.promotionId, 10) || 0,
                canClaim: !!data.canClaim
            };
            if (typeof data.content === 'string' && !payload.sections) {
                payload.sections = [
                    { title: 'BONUSTAN NASIL FAYDALANABİLİRİM', content: data.content },
                    { title: 'BONUS ÇEVRİM ŞARTI', content: '' },
                    { title: 'BONUS GENEL KURALLARI', content: '' }
                ];
            }
        }

        previousActiveElement = document.activeElement;
        if (document.body.classList.contains('mobile-site')) {
            previousMobileScrollY = global.scrollY || global.pageYOffset || 0;
            if (previousMobileScrollY > 0) {
                global.scrollTo(0, 0);
            }
        }
        if (document.body.classList.contains('mobile-site') && typeof window.__closeMobileNavMenu === 'function') {
            window.__closeMobileNavMenu();
        }
        if (document.body.classList.contains('mobile-site') && typeof window.__syncHeaderStickyTop === 'function') {
            window.__syncHeaderStickyTop();
            requestAnimationFrame(function () {
                if (typeof window.__syncHeaderStickyTop === 'function') {
                    window.__syncHeaderStickyTop();
                }
            });
        }

        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        if (modal) {
            modal.setAttribute('aria-hidden', 'false');
            modal.focus();
        }
        getSharedScrollLock().lock();
        isScrollLockedByModal = true;
        document.addEventListener('keydown', handleKeydown);

        // Her açılışta önce state'i sıfırla; ikinci+ açılışlarda kalan eski scroll/başlık görünümünü engelle.
        resetModalState();

        if (payload) {
            setContent(payload);
            if (document.body.classList.contains('mobile-site') && backBtn) {
                backBtn.focus();
            } else if (closeBtn) {
                closeBtn.focus();
            }
        } else if (document.body.classList.contains('mobile-site') && backBtn) {
            backBtn.focus();
        } else if (closeBtn) {
            closeBtn.focus();
        }
    }

    function close() {
        if (!overlay) return;
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        if (modal) modal.setAttribute('aria-hidden', 'true');
        if (isScrollLockedByModal) {
            getSharedScrollLock().unlock();
            isScrollLockedByModal = false;
        }
        document.removeEventListener('keydown', handleKeydown);
        if (previousActiveElement && typeof previousActiveElement.focus === 'function') {
            previousActiveElement.focus();
        }
        if (document.body.classList.contains('mobile-site') && previousMobileScrollY !== null) {
            var restoreY = previousMobileScrollY;
            previousMobileScrollY = null;
            requestAnimationFrame(function () {
                global.scrollTo(0, restoreY);
            });
        }
    }

    function bindCloseAndOverlay() {
        getElements();
        if (!overlay || overlay.getAttribute('data-bonus-modal-bound') === '1') return;
        overlay.setAttribute('data-bonus-modal-bound', '1');
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) close();
        });
        if (closeBtn) {
            closeBtn.addEventListener('click', close);
        }
        if (backBtn) {
            backBtn.addEventListener('click', close);
        }
        if (modal) {
            modal.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }
    }

    function bindEvents() {
        getElements();
        bindClaimSubmitOnce();
        if (overlay) {
            bindCloseAndOverlay();
            bindAccordionDelegation();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindEvents);
    } else {
        bindEvents();
    }

    global.BonusDetailModal = {
        open: open,
        close: close
    };
})(typeof window !== 'undefined' ? window : this);
