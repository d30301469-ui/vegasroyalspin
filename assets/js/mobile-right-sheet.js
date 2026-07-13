/**
 * Mobil standart sağ sheet — başlık + geri + içerik (isteğe bağlı alt şerit).
 * Örnek: MobileRightSheet.open({ title: 'Başlık', bodyHtml: '<p>...</p>' });
 */
(function (global) {
    'use strict';

    var overlay = null;
    var panel = null;
    var backBtn = null;
    var closeBtn = null;
    var titleEl = null;
    var subbarEl = null;
    var bodyEl = null;
    var previousActiveElement = null;
    var focusableSelector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
    var onCloseCallback = null;
    var closeOnBackdrop = true;

    function getElements() {
        overlay = document.getElementById('mobile-right-sheet-overlay');
        if (!overlay) return;
        panel = document.getElementById('mobile-right-sheet');
        backBtn = overlay.querySelector('.mobile-right-sheet__back');
        closeBtn = overlay.querySelector('.mobile-right-sheet__close');
        titleEl = document.getElementById('mobile-right-sheet-title');
        subbarEl = overlay.querySelector('.mobile-right-sheet__subbar');
        bodyEl = overlay.querySelector('.mobile-right-sheet__body');
    }

    function createIfMissing() {
        if (document.getElementById('mobile-right-sheet-overlay')) return;
        var div = document.createElement('div');
        div.id = 'mobile-right-sheet-overlay';
        div.className = 'mobile-right-sheet-overlay';
        div.setAttribute('aria-hidden', 'true');
        div.innerHTML = [
            '<div id="mobile-right-sheet" class="mobile-right-sheet" role="dialog" aria-modal="true" aria-labelledby="mobile-right-sheet-title" aria-hidden="true" tabindex="-1">',
            '  <div class="mobile-right-sheet__header">',
            '    <button type="button" class="mobile-right-sheet__back" aria-label="Geri">',
            '      <svg class="mobile-right-sheet__back-icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z" fill="currentColor"/></svg>',
            '    </button>',
            '    <h2 id="mobile-right-sheet-title" class="mobile-right-sheet__title"></h2>',
            '    <button type="button" class="mobile-right-sheet__close" aria-label="Kapat">',
            '      <svg class="mobile-right-sheet__close-icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z" fill="currentColor"/></svg>',
            '    </button>',
            '  </div>',
            '  <div class="mobile-right-sheet__subbar" hidden></div>',
            '  <div class="mobile-right-sheet__body"></div>',
            '</div>'
        ].join('\n');
        document.body.appendChild(div);
        getElements();
        bindUi();
    }

    function bindUi() {
        if (!overlay) return;
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay && closeOnBackdrop) {
                close();
            }
        });
        if (panel) {
            panel.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }
        if (backBtn) {
            backBtn.addEventListener('click', close);
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', close);
        }
    }

    function trapFocus(e) {
        if (!panel || e.key !== 'Tab') return;
        var focusable = panel.querySelectorAll(focusableSelector);
        if (!focusable.length) return;
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

    function open(opts) {
        if (!document.body.classList.contains('mobile-site')) return;

        createIfMissing();
        getElements();
        if (!overlay || !panel || !bodyEl) return;

        var options = opts && typeof opts === 'object' ? opts : {};
        onCloseCallback = typeof options.onClose === 'function' ? options.onClose : null;
        closeOnBackdrop = options.closeOnBackdrop !== false;

        if (typeof options.zIndex === 'number' && options.zIndex > 0) {
            overlay.style.zIndex = String(options.zIndex);
        } else {
            overlay.style.zIndex = '';
        }

        if (titleEl) {
            titleEl.textContent = options.title != null ? String(options.title) : '';
        }

        if (subbarEl) {
            var subHtml = options.subbarHtml;
            if (subHtml) {
                subbarEl.innerHTML = subHtml;
                subbarEl.removeAttribute('hidden');
            } else {
                subbarEl.innerHTML = '';
                subbarEl.setAttribute('hidden', '');
            }
        }

        bodyEl.innerHTML = '';
        if (options.bodyElement && options.bodyElement.nodeType === 1) {
            bodyEl.appendChild(options.bodyElement);
        } else if (typeof options.bodyHtml === 'string') {
            bodyEl.innerHTML = options.bodyHtml;
        }

        previousActiveElement = document.activeElement;
        if (typeof global.__closeMobileNavMenu === 'function') {
            global.__closeMobileNavMenu();
        }
        if (typeof global.__syncHeaderStickyTop === 'function') {
            global.__syncHeaderStickyTop();
        }

        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        panel.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', handleKeydown);

        requestAnimationFrame(function () {
            panel.focus();
            if (backBtn) {
                backBtn.focus();
            }
        });
    }

    function close() {
        if (!overlay || !overlay.classList.contains('is-open')) return;
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        if (panel) {
            panel.setAttribute('aria-hidden', 'true');
        }
        document.body.style.overflow = '';
        document.removeEventListener('keydown', handleKeydown);
        if (previousActiveElement && typeof previousActiveElement.focus === 'function') {
            previousActiveElement.focus();
        }
        if (onCloseCallback) {
            try {
                onCloseCallback();
            } catch (err) { /* ignore */ }
            onCloseCallback = null;
        }
    }

    function isOpen() {
        return !!(overlay && overlay.classList.contains('is-open'));
    }

    /** İçerik alanına doğrudan DOM eklemek için (open çağrılmadan önce veya sonra). */
    function getBody() {
        if (!document.body.classList.contains('mobile-site')) return null;
        createIfMissing();
        getElements();
        return bodyEl;
    }

    function init() {
        getElements();
        if (overlay) {
            bindUi();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    global.MobileRightSheet = {
        open: open,
        close: close,
        isOpen: isOpen,
        getBody: getBody
    };
})(typeof window !== 'undefined' ? window : this);
