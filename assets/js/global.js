/**
 * Global input davranışı: floating label, zorunlu alan doğrulama.
 * Tüm projede form-control-label-bc / form-control-input-bc yapısı için kullanılır.
 */
(function (global) {
    'use strict';

    var SELECTORS = {
        labelWrap: '.form-control-label-bc.inputs',
        input: '.form-control-input-bc, input',
        errorText: '[data-error-for]'
    };

    /**
     * Bir container içindeki tüm floating label inputları başlatır.
     * @param {HTMLElement|Document} [container=document] - Arama yapılacak öğe
     */
    function initFloatingLabels(container) {
        container = container || document;
        var wraps = container.querySelectorAll(SELECTORS.labelWrap);
        wraps.forEach(function (wrap) {
            var input = wrap.querySelector(SELECTORS.input);
            if (!input) return;
            updateFloatLabelState(wrap);
            input.addEventListener('focus', function () {
                wrap.classList.add('focused');
            });
            input.addEventListener('blur', function () {
                wrap.classList.remove('focused');
                updateFloatLabelState(wrap);
            });
            input.addEventListener('input', function () {
                updateFloatLabelState(wrap);
            });
            if (input.tagName === 'SELECT') {
                input.addEventListener('change', function () {
                    updateFloatLabelState(wrap);
                });
            }
        });
    }

    function updateFloatLabelState(wrap) {
        if (!wrap) return;
        var input = wrap.querySelector(SELECTORS.input);
        var val = input ? (input.value || '').trim() : '';
        var hasValue = val.length > 0;
        if (hasValue) {
            wrap.classList.add('has-value');
        } else {
            wrap.classList.remove('has-value');
        }
    }

    /**
     * Form gönderiminde zorunlu alan doğrulaması ve blur'da hata gösterme.
     * @param {HTMLFormElement} form
     * @param {Object} options - { fieldNames: string[], errorTextClass: string }
     */
    function initRequiredValidation(form, options) {
        if (!form) return;
        var fieldNames = options && options.fieldNames ? options.fieldNames : [];
        var errorTextClass = (options && options.errorTextClass) ? options.errorTextClass : 'field-error-text';

        function setFieldError(fieldName, show) {
            var input = form.querySelector('[name="' + fieldName + '"]');
            var errorEl = form.querySelector('.' + errorTextClass + '[data-error-for="' + fieldName + '"]');
            if (!input || !errorEl) return;
            if (show) {
                input.classList.add('error');
                errorEl.style.display = 'block';
            } else {
                input.classList.remove('error');
                errorEl.style.display = 'none';
            }
        }

        function clearErrors() {
            fieldNames.forEach(function (name) {
                var input = form.querySelector('[name="' + name + '"]');
                if (input) {
                    input.classList.remove('error');
                    var errorEl = form.querySelector('.' + errorTextClass + '[data-error-for="' + name + '"]');
                    if (errorEl) errorEl.style.display = 'none';
                }
            });
        }

        function isEmpty(input) {
            if (!input) return true;
            if (input.type === 'checkbox') return !input.checked;
            return !input.value.trim();
        }

        form.addEventListener('submit', function (e) {
            var hasError = false;
            clearErrors();
            fieldNames.forEach(function (name) {
                var input = form.querySelector('[name="' + name + '"]');
                if (isEmpty(input)) {
                    setFieldError(name, true);
                    hasError = true;
                }
            });
            if (hasError) {
                e.preventDefault();
                return false;
            }
        });

        fieldNames.forEach(function (fieldName) {
            var input = form.querySelector('[name="' + fieldName + '"]');
            if (!input) return;
            input.addEventListener('input', function () {
                setFieldError(fieldName, false);
            });
            input.addEventListener('change', function () {
                setFieldError(fieldName, false);
            });
            input.addEventListener('blur', function () {
                if (isEmpty(input)) {
                    setFieldError(fieldName, true);
                }
            });
        });
    }

    /**
     * Formdaki floating label state ve hata görünümünü sıfırlar (modal kapatılınca vb.).
     * @param {HTMLFormElement} form
     * @param {string} [errorTextClass='field-error-text']
     */
    function resetFormInputState(form, errorTextClass) {
        if (!form) return;
        errorTextClass = errorTextClass || 'field-error-text';
        var wraps = form.querySelectorAll(SELECTORS.labelWrap);
        wraps.forEach(function (wrap) {
            wrap.classList.remove('focused', 'has-value');
        });
        var errors = form.querySelectorAll('.' + errorTextClass);
        errors.forEach(function (el) {
            el.style.display = 'none';
        });
        var inputs = form.querySelectorAll('.form-control-input-bc, input');
        inputs.forEach(function (inp) {
            inp.classList.remove('error');
        });
    }

    var BetcoInputs = {
        initFloatingLabels: initFloatingLabels,
        initRequiredValidation: initRequiredValidation,
        resetFormInputState: resetFormInputState,
        updateFloatLabelState: function (wrap) {
            updateFloatLabelState(wrap);
        }
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = BetcoInputs;
    } else {
        global.BetcoInputs = BetcoInputs;
    }
})(typeof window !== 'undefined' ? window : this);

// Runtime fallback: guarantees same-origin manifest and PWA register loader.
(function () {
    'use strict';

    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    function sameHost(urlValue) {
        try {
            var parsed = new URL(urlValue, window.location.origin);
            return parsed.host === window.location.host;
        } catch (e) {
            return false;
        }
    }

    function ensureManifest() {
        var manifestHref = '/assets/images/favicons/site.webmanifest';
        var manifestLink = document.querySelector('link[rel="manifest"]');

        if (!manifestLink) {
            manifestLink = document.createElement('link');
            manifestLink.setAttribute('rel', 'manifest');
            document.head.appendChild(manifestLink);
        }

        var currentHref = manifestLink.getAttribute('href') || '';
        if (!currentHref || !sameHost(currentHref)) {
            manifestLink.setAttribute('href', manifestHref + '?v=' + String(Date.now()));
        }
    }

    function ensurePwaRegisterScript() {
        if (window.__pwaRegisterBootstrapLoaded) {
            return;
        }
        window.__pwaRegisterBootstrapLoaded = true;

        var existing = document.querySelector('script[src*="/assets/js/pwa-register.js"]');
        if (existing) {
            return;
        }

        var script = document.createElement('script');
        script.defer = true;
        script.src = '/assets/js/pwa-register.js?v=' + String(Date.now());
        script.setAttribute('data-pwa-register-fallback', '1');
        document.head.appendChild(script);
    }

    function initPwaFallback() {
        ensureManifest();
        ensurePwaRegisterScript();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPwaFallback);
    } else {
        initPwaFallback();
    }
})();
