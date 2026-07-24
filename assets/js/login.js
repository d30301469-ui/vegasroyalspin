/**
 * Login modal (#login2) – açma, kapatma. jQuery yoksa modal-polyfill (showModalById/hideModalById) kullanılır.
 */
(function () {
    var $jq = window.jQuery || window.$;
    var BetcoInputs = window.BetcoInputs;
    var Shared = window.BetcoAuthShared || {};

    function onReady(fn) {
        if (Shared.onReady) {
            Shared.onReady(fn);
            return;
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function applyMobileAuthLayoutFix(modalId) {
        if (!document.body.classList.contains('mobile-site')) return;
        var modalEl = document.getElementById(modalId);
        if (!modalEl) return;

        var run = function () {
            var authSliderBg = modalEl.querySelector('.auth-slider-bg');
            if (authSliderBg) {
                authSliderBg.style.position = 'absolute';
                authSliderBg.style.inset = '0';
                authSliderBg.style.zIndex = '0';
                authSliderBg.style.pointerEvents = 'none';
                authSliderBg.style.overflow = 'hidden';
            }

            var contentHolder = modalEl.querySelector('.has-auth-slider > .e-p-content-holder-bc');
            if (contentHolder) {
                contentHolder.style.position = 'relative';
                contentHolder.style.zIndex = '1';
                contentHolder.style.flex = '1 1 auto';
            }

            if (modalId === 'login2') {
                var loginSpacer = modalEl.querySelector('.sg-n-text-row-2-bc');
                if (loginSpacer) {
                    loginSpacer.style.flex = '0 0 clamp(78px, 15vh, 126px)';
                    loginSpacer.style.minHeight = '0';
                }

                var loginContainers = modalEl.querySelectorAll('.modal-body, .e-p-content-holder-bc, .e-p-content-bc, #loginFormScreen, .login-form, .entrance-form-content-bc.single-side.step-0');
                loginContainers.forEach(function (el) {
                    el.style.minHeight = '0';
                    if (el.classList.contains('modal-body')) {
                        el.style.display = 'flex';
                        el.style.flexDirection = 'column';
                        el.style.flex = '1 1 auto';
                        el.style.overflowY = 'auto';
                    }
                });
            }

            if (modalId === 'registerModal') {
                var registerBody = modalEl.querySelector('.register-modal-body');
                if (registerBody) {
                    registerBody.style.display = 'flex';
                    registerBody.style.flexDirection = 'column';
                    registerBody.style.flex = '1 1 auto';
                    registerBody.style.minHeight = '0';
                    registerBody.style.overflow = 'hidden';
                }

                var registerContainers = modalEl.querySelectorAll('.e-p-content-holder-bc, .e-p-content-bc, .reg-form-block-bc, .entrance-form-bc.registration.popup');
                registerContainers.forEach(function (el) {
                    el.style.display = 'flex';
                    el.style.flexDirection = 'column';
                    el.style.flex = '1 1 auto';
                    el.style.minHeight = '0';
                    el.style.height = '100%';
                });

                var stepScroll = modalEl.querySelector('.register-steps-scroll');
                if (stepScroll) {
                    stepScroll.style.display = 'block';
                    stepScroll.style.flex = '1 1 auto';
                    stepScroll.style.minHeight = '0';
                    stepScroll.style.height = 'auto';
                    stepScroll.style.overflowY = 'auto';
                }

                var form = modalEl.querySelector('#modalRegisterForm[data-active-step="1"] .reg-form-content');
                if (form) {
                    form.style.justifyContent = 'flex-start';
                    form.style.minHeight = '0';
                    form.style.height = 'auto';
                    form.style.paddingBottom = '18px';
                }
            }

            var scrollAreas = modalEl.querySelectorAll('.modal-body, .register-steps-scroll, .entrance-form-content-bc.single-side.step-0');
            scrollAreas.forEach(function (area) {
                area.scrollTop = 0;
            });
        };

        requestAnimationFrame(function () {
            run();
            requestAnimationFrame(run);
        });
    }

    function closeMobileSurfacesBeforeAuthModal() {
        if (!document.body || !document.body.classList.contains('mobile-site')) {
            return;
        }
        if (typeof window.__closeMobileProfilePanel === 'function') {
            window.__closeMobileProfilePanel();
        }
        if (typeof window.__closeMobileNavMenu === 'function') {
            window.__closeMobileNavMenu();
        }
        if (typeof window.__closeSmartPanel === 'function') {
            window.__closeSmartPanel();
        }
    }

    function showLoginModal() {
        // Mobil katmanlari kapat, body lock/state cakismasini onle.
        closeMobileSurfacesBeforeAuthModal();
        var el = document.getElementById('login2');
        if (!el) return;
        if ($jq && $jq.fn && typeof $jq.fn.modal === 'function') {
            $jq(el).modal('show');
        } else if (typeof window.showModalById === 'function') {
            window.showModalById('login2');
        } else {
            el.classList.add('show');
            el.style.display = 'block';
            el.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');
            document.body.style.overflow = 'hidden';
        }
        document.body.classList.add('login-modal-open');
        document.body.classList.remove('register-modal-open');
        applyMobileAuthLayoutFix('login2');
        ensureLoginTurnstileWidget();
    }
    window.__openLoginModal = showLoginModal;
    window.MaltabetAuth = window.MaltabetAuth || {};
    window.MaltabetAuth.showLoginModal = showLoginModal;

    function hideLoginModal() {
        var el = document.getElementById('login2');
        if (!el) return;
        if ($jq && $jq.fn && typeof $jq.fn.modal === 'function') {
            $jq(el).modal('hide');
        } else if (typeof window.hideModalById === 'function') {
            window.hideModalById('login2');
        } else {
            el.classList.remove('show');
            el.style.display = 'none';
            el.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
        }
        document.body.classList.remove('login-modal-open');
        resetLoginTurnstileWidget();
    }

    function showRegisterModal() {
        closeMobileSurfacesBeforeAuthModal();
        var el = document.getElementById('registerModal');
        if (!el) return;
        if ($jq && $jq.fn && typeof $jq.fn.modal === 'function') {
            $jq(el).modal('show');
        } else if (typeof window.showModalById === 'function') {
            window.showModalById('registerModal');
        } else {
            el.classList.add('show');
            el.style.display = 'block';
            el.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');
            document.body.style.overflow = 'hidden';
        }
        document.body.classList.add('register-modal-open');
        document.body.classList.remove('login-modal-open');
        applyMobileAuthLayoutFix('registerModal');
    }
    window.__openRegisterModal = showRegisterModal;
    window.MaltabetAuth = window.MaltabetAuth || {};
    window.MaltabetAuth.showRegisterModal = showRegisterModal;

    function hideRegisterModal() {
        var el = document.getElementById('registerModal');
        if (!el) return;
        if ($jq && $jq.fn && typeof $jq.fn.modal === 'function') {
            $jq(el).modal('hide');
        } else if (typeof window.hideModalById === 'function') {
            window.hideModalById('registerModal');
        } else {
            el.classList.remove('show');
            el.style.display = 'none';
            el.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
        }
        document.body.classList.remove('register-modal-open');
    }

    function removeMobileCloseButton(modalEl) {
        if (!modalEl) return;
        var staleBtn = modalEl.querySelector('.mobile-auth-close');
        if (staleBtn && staleBtn.parentNode) {
            staleBtn.parentNode.removeChild(staleBtn);
        }
    }

    function loginTurnstileContainer() {
        return document.getElementById('loginTurnstile');
    }

    function syncLoginTurnstileWrapState(container) {
        var widgetContainer = container || loginTurnstileContainer();
        var wrap = widgetContainer && widgetContainer.closest ? widgetContainer.closest('.login-turnstile-wrap') : null;
        if (!wrap) return;
        var widgetId = widgetContainer.getAttribute('data-turnstile-widget-id') || '';
        wrap.classList.toggle('has-turnstile-widget', widgetId !== '');
    }

    function ensureLoginTurnstileWidget() {
        if (!Shared.hasTurnstile || !Shared.hasTurnstile()) return;
        var container = loginTurnstileContainer();
        if (!container) return;

        // Modal gorunur degilken render denemesi widget'i yarim birakabiliyor.
        // Mobilde CSS transition/animasyon sirasinda getClientRects() bos donebilir;
        // bu durumda gecikmeli tekrar dene, aninda vazgecme.
        if (!container.offsetParent && container.getClientRects().length === 0) {
            var isMobileSite = !!(document.body && document.body.classList.contains('mobile-site'));
            if (isMobileSite) {
                window.setTimeout(ensureLoginTurnstileWidget, 200);
            }
            return;
        }

        if (!window.turnstile || typeof window.turnstile.render !== 'function') {
            window.setTimeout(ensureLoginTurnstileWidget, 120);
            return;
        }

        var widgetId = container.getAttribute('data-turnstile-widget-id');
        if (widgetId) {
            // Turnstile iframe'i kapali shadow root icinde oldugundan DOM'dan
            // kontrol edilemez; canlilik testi icin getResponse kullanilir.
            try {
                window.turnstile.getResponse(widgetId);
                syncLoginTurnstileWrapState(container);
                return; // Widget saglikli, yeniden render gerekmez.
            } catch (eStale) {
                container.removeAttribute('data-turnstile-widget-id');
                container.innerHTML = '';
                syncLoginTurnstileWrapState(container);
            }
        }

        if (typeof Shared.renderTurnstileWidget !== 'function') return;
        Shared.renderTurnstileWidget(container, { theme: 'dark', action: 'login' });
        syncLoginTurnstileWrapState(container);
    }

    function resetLoginTurnstileWidget() {
        var container = loginTurnstileContainer();
        if (!container) return;
        if (typeof Shared.resetTurnstileWidget !== 'function') return;
        Shared.resetTurnstileWidget(container);
        syncLoginTurnstileWrapState(container);
    }

    function loginTurnstileToken() {
        var container = loginTurnstileContainer();
        if (!container) return '';
        if (typeof Shared.turnstileTokenFromContainer !== 'function') return '';
        return Shared.turnstileTokenFromContainer(container);
    }

    function alignLoginFieldErrorsToInputs() {
        var modal = document.getElementById('login2');
        if (!modal) return;

        var groups = modal.querySelectorAll('.form-group.entrance-f-item-bc');
        groups.forEach(function (group) {
            var input = group.querySelector('.form-control-input-bc');
            var errorEl = group.querySelector('.login-error-text');
            if (!input || !errorEl) return;

            var leftInset = input.offsetLeft;
            var rightInset = Math.max(0, group.clientWidth - (input.offsetLeft + input.offsetWidth));
            errorEl.style.left = leftInset + 'px';
            errorEl.style.right = rightInset + 'px';
            errorEl.style.boxSizing = 'border-box';
        });
    }

    function initPasswordToggle() {
        document.addEventListener('click', function (e) {
            var toggle = e.target && e.target.closest ? e.target.closest('.login-password-toggle') : null;
            if (!toggle) return;

            var targetSelector = toggle.getAttribute('data-target-password');
            var input = targetSelector ? document.querySelector(targetSelector) : null;
            if (!input) return;

            var showPassword = input.type === 'password';
            input.type = showPassword ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
            toggle.setAttribute('aria-label', showPassword ? 'Şifreyi gizle' : 'Şifreyi göster');
            if (toggle.classList) {
                toggle.classList.toggle('is-password-visible', showPassword);
            }
        });
    }

    function getLoginModalScope(fromEl) {
        if (fromEl && fromEl.closest) {
            var scoped = fromEl.closest('#login2');
            if (scoped) return scoped;
        }
        var active = document.querySelector('#login2.show');
        if (active) return active;
        return document.getElementById('login2') || document;
    }

    function inLoginScope(scope, id) {
        if (!id) return null;
        var root = scope || getLoginModalScope();
        if (!root || !root.querySelector) return document.getElementById(id);
        return root.querySelector('#' + id) || document.getElementById(id);
    }

    function showLoginFormScreen(scope) {
        var hdr = inLoginScope(scope, 'loginScreenHeader');
        var main = inLoginScope(scope, 'loginFormScreen');
        var forg = inLoginScope(scope, 'forgotPasswordScreen');
        var forgotForm = forg ? forg.querySelector('.login-form') : null;
        // Toast bildirimlerini geri ac
        if (typeof window.__restoreForgotToast === 'function') {
            window.__restoreForgotToast();
        }
        if (hdr) {
            hdr.classList.remove('d-none');
        }
        if (main) {
            main.classList.remove('d-none');
        }
        if (forg) {
            forg.classList.add('d-none');
            forg.style.justifyContent = '';
            forg.style.paddingTop = '';
            forg.style.paddingBottom = '';
            forg.style.width = '';
            forg.style.margin = '';
        }
        if (forgotForm) {
            forgotForm.style.flex = '';
            forgotForm.style.marginTop = '';
            forgotForm.style.justifyContent = '';
        }
    }

    function showForgotPasswordScreen(scope) {
        var hdr = inLoginScope(scope, 'loginScreenHeader');
        var main = inLoginScope(scope, 'loginFormScreen');
        var forg = inLoginScope(scope, 'forgotPasswordScreen');
        var forgotForm = forg ? forg.querySelector('.login-form') : null;
        var forgotSubmit = forg ? forg.querySelector('#forgotPasswordSubmit') : null;
        var forgotNote = forg ? forg.querySelector('.login-forgot-note') : null;
        var isMobileSite = !!(document.body && document.body.classList.contains('mobile-site'));
        if (hdr) {
            hdr.classList.add('d-none');
        }
        if (main) {
            main.classList.add('d-none');
        }
        if (forg) {
            forg.classList.remove('d-none');
        }
        // Toast bildirimlerini gecici olarak sustur (mobil toast overlay sorunu)
        if (typeof window.__disableForgotToast === 'function') {
            window.__disableForgotToast();
        }
        if (forgotForm) {
            if (isMobileSite) {
                if (forg) {
                    forg.style.justifyContent = 'flex-start';
                    forg.style.paddingTop = 'clamp(330px, 46vh, 410px)';
                    forg.style.paddingBottom = '12px';
                    forg.style.width = 'calc(100% - 24px)';
                    forg.style.margin = '0 12px';
                }
                forgotForm.style.flex = '0 0 auto';
                forgotForm.style.marginTop = '16px';
                forgotForm.style.justifyContent = '';
            } else {
                forgotForm.style.flex = '0 0 auto';
                forgotForm.style.marginTop = 'auto';
                forgotForm.style.justifyContent = 'flex-end';
            }
        }
        if (forgotForm && forgotSubmit && forgotNote) {
            var submitNext = forgotSubmit.nextElementSibling;
            if (submitNext !== forgotNote) {
                if (submitNext) {
                    forgotForm.insertBefore(forgotNote, submitNext);
                } else {
                    forgotForm.appendChild(forgotNote);
                }
            }
        }
    }

    function resetForgotPasswordAlerts(scope) {
        var err = inLoginScope(scope, 'forgotPasswordAjaxAlert');
        var ok = inLoginScope(scope, 'forgotPasswordSuccess');
        if (err) {
            err.textContent = '';
            err.classList.add('d-none');
        }
        if (ok) {
            ok.textContent = '';
            ok.classList.add('d-none');
        }
    }

    function initLoginModal() {
        var loginTrigger = document.getElementById('Giris');
        var loginModalEl = document.getElementById('login2');
        var registerModalEl = document.getElementById('registerModal');
        var openRegisterFromLogin = document.getElementById('openRegisterFromLogin');

        if (!loginModalEl) return;

        removeMobileCloseButton(loginModalEl);
        removeMobileCloseButton(registerModalEl);

        var loginCloseBtn = loginModalEl.querySelector('.login-close');
        if (loginCloseBtn) {
            loginCloseBtn.addEventListener('click', function (e) {
                e.preventDefault();
                hideLoginModal();
            });
        }

        if (loginTrigger) {
            loginTrigger.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                showLoginModal();
            });
        }

        if (openRegisterFromLogin && registerModalEl) {
            openRegisterFromLogin.addEventListener('click', function (e) {
                e.preventDefault();
                hideLoginModal();
                showRegisterModal();
            });
        }

        var directForgotLink = loginModalEl.querySelector('#openForgotPassword, .login-forgot a');
        if (directForgotLink) {
            directForgotLink.addEventListener('click', function (e) {
                e.preventDefault();
                var scopeDirect = getLoginModalScope(directForgotLink);
                showForgotPasswordScreen(scopeDirect);
                var ffDirect = inLoginScope(scopeDirect, 'forgotPasswordForm');
                if (ffDirect && BetcoInputs) {
                    BetcoInputs.resetFormInputState(ffDirect, 'login-error-text');
                }
                resetForgotPasswordAlerts(scopeDirect);
            });
        }

        var directBackLink = loginModalEl.querySelector('#backToLoginFromForgot');
        if (directBackLink) {
            directBackLink.addEventListener('click', function (e) {
                e.preventDefault();
                var scopeDirectBack = getLoginModalScope(directBackLink);
                showLoginFormScreen(scopeDirectBack);
                resetForgotPasswordAlerts(scopeDirectBack);
            });
        }

        document.addEventListener('click', function (e) {
            var forgotLink = null;
            if (e.target && e.target.closest) {
                forgotLink = e.target.closest('#openForgotPassword');
                if (!forgotLink) {
                    forgotLink = e.target.closest('#login2 .login-forgot a');
                }
            }
            if (forgotLink) {
                e.preventDefault();
                var scope = getLoginModalScope(forgotLink);
                showForgotPasswordScreen(scope);
                var ff = inLoginScope(scope, 'forgotPasswordForm');
                if (ff && BetcoInputs) {
                    BetcoInputs.resetFormInputState(ff, 'login-error-text');
                }
                resetForgotPasswordAlerts(scope);
                return;
            }

            var backLink = e.target && e.target.closest ? e.target.closest('#backToLoginFromForgot') : null;
            if (backLink) {
                e.preventDefault();
                var scopeBack = getLoginModalScope(backLink);
                showLoginFormScreen(scopeBack);
                resetForgotPasswordAlerts(scopeBack);
            }
        }, true);

        var registerLoginLink = document.querySelector('.register-modal-login-link');
        if (registerLoginLink && registerModalEl) {
            registerLoginLink.addEventListener('click', function (e) {
                e.preventDefault();
                hideRegisterModal();
                showLoginModal();
            });
        }

        if ($jq) {
            $jq(loginModalEl).on('shown.bs.modal', function () {
                applyMobileAuthLayoutFix('login2');
                alignLoginFieldErrorsToInputs();
                // Bazi acilis akislari showLoginModal() uzerinden gecmeyebilir.
                // Modal gorunur oldugunda widget'i burada da garanti render et.
                ensureLoginTurnstileWidget();
                window.setTimeout(ensureLoginTurnstileWidget, 160);
                window.setTimeout(ensureLoginTurnstileWidget, 420);
            });
            $jq(loginModalEl).on('hidden.bs.modal', function () {
                var form = document.getElementById('loginForm');
                if (BetcoInputs && form) {
                    BetcoInputs.resetFormInputState(form, 'login-error-text');
                }
                if (form) form.reset();
                resetLoginTurnstileWidget();
                showLoginFormScreen();
                resetForgotPasswordAlerts();
                var ff = document.getElementById('forgotPasswordForm');
                if (ff) ff.reset();
                if (BetcoInputs && ff) {
                    BetcoInputs.resetFormInputState(ff, 'login-error-text');
                }
            });
            if (registerModalEl) {
                $jq(registerModalEl).on('shown.bs.modal', function () {
                    applyMobileAuthLayoutFix('registerModal');
                });
            }
        }
    }

    function initLoginForm() {
        var loginForm = document.getElementById('loginForm');
        if (!loginForm) return;
        try {
            if (BetcoInputs) {
                BetcoInputs.initFloatingLabels(loginForm);
                BetcoInputs.initRequiredValidation(loginForm, {
                    fieldNames: ['username', 'password'],
                    errorTextClass: 'login-error-text'
                });
            }
        } catch (err) {
            if (typeof console !== 'undefined') console.warn('Login form init:', err);
        }
    }

    function initLoginAjaxSubmit() {
        var loginForm = document.getElementById('loginForm');
        var loginModalEl = document.getElementById('login2');
        if (!loginForm || !loginModalEl) return;

        // Signal that the AJAX submit handler is active — prevents traditional form submit
        window.__loginAjaxReady = true;

        var submitBtn = loginForm.querySelector('.login-btn');
        var btnText = submitBtn ? submitBtn.querySelector('.btn-text') : null;
        var btnLoading = submitBtn ? submitBtn.querySelector('.loading') : null;

        function toast(type, msg, title) {
            if (window.MaltabetToast) {
                MaltabetToast.show(msg, { type: type || 'info', title: title });
            }
        }

        function isMobileSite() {
            return !!(document.body && document.body.classList.contains('mobile-site'));
        }

        function notify(type, msg, title) {
            if (isMobileSite()) {
                return;
            }
            toast(type, msg, title);
        }

        function showError(msg) {
            notify('error', msg || 'Bir hata oluştu.', 'Giriş');
        }

        function hideError() {}

        function setLoading(loading) {
            if (Shared.setSubmitButtonLoading) {
                Shared.setSubmitButtonLoading(submitBtn, loading);
                return;
            }
            if (!submitBtn) return;
            submitBtn.disabled = loading;
            if (btnText) btnText.style.display = loading ? 'none' : '';
            if (btnLoading) btnLoading.style.display = loading ? 'inline-block' : 'none';
        }

        function navigateAfterToast(url) {
            window.setTimeout(function () {
                if (url) {
                    window.location.href = url;
                    return;
                }
                window.location.reload();
            }, 2200);
        }

        loginForm.addEventListener('submit', function (e) {
            e.preventDefault();
            // Prevent double-submit from MutationObserver or onclick handlers
            if (window.__loginSubmitting) {
                return;
            }
            window.__loginSubmitting = true;
            alignLoginFieldErrorsToInputs();
            var username = (loginForm.querySelector('[name="username"]') || {}).value || '';
            var password = (loginForm.querySelector('[name="password"]') || {}).value || '';
            var turnstileToken = loginTurnstileToken();
            if (!username.trim() || !password) {
                window.__loginSubmitting = false;
                return;
            }
            if (Shared.hasTurnstile && Shared.hasTurnstile() && !turnstileToken) {
                showError('Cloudflare doğrulamasını tamamlayın.');
                ensureLoginTurnstileWidget();
                window.__loginSubmitting = false;
                return;
            }

            hideError();
            setLoading(true);

            var fd = new FormData(loginForm);
            fd.set('username', username.trim());
            fd.set('login', username.trim());
            fd.set('password', password);
            if (turnstileToken) {
                fd.set('turnstile_response', turnstileToken);
                fd.set('cf-turnstile-response', turnstileToken);
            }

            var loginUrl = Shared.proxyApiUrl
                ? Shared.proxyApiUrl('/auth/login')
                : (Shared.apiUrl ? Shared.apiUrl('/api/v2/auth/login') : '/api/v2/auth/login');

            fetch(loginUrl, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: Shared.memberAuthHeaders
                    ? Shared.memberAuthHeaders({ Accept: 'application/json' })
                    : { Accept: 'application/json' }
            })
                .then(function (res) {
                    return res.text().then(function (text) {
                        var data = null;
                        try {
                            data = text ? JSON.parse(text.replace(/^\uFEFF/, '').trim()) : null;
                        } catch (eJson) {
                            data = null;
                        }
                        if (!data || typeof data !== 'object') {
                            return {
                                success: false,
                                message: 'Sunucu yanıtı okunamadı. Sayfayı yenileyip tekrar deneyin.'
                            };
                        }
                        return data;
                    });
                })
                .then(function (data) {
                    setLoading(false);
                    if (data.success) {
                        var tokenOk = Shared.applyLoginEnvelope
                            ? Shared.applyLoginEnvelope(data)
                            : false;
                        if (!tokenOk) {
                            window.__loginSubmitting = false;
                            showError('Giriş başarılı ancak oturum token\'ı alınamadı. Sayfayı yenileyip tekrar deneyin.');
                            return;
                        }                        window.__loginSubmitting = false;                        window.__USER_LOGGED_IN__ = true;
                        window.__HAS_MEMBER_JWT__ = true;
                        window.__MEMBER_LOGIN_AT__ = Date.now();
                        notify('success', data.message || 'Giriş başarılı.', 'Giriş');
                        hideLoginModal();
                        loginForm.reset();
                        hideError();
                        if (typeof window.BetcoInputs !== 'undefined' && window.BetcoInputs.resetFormInputState) {
                            window.BetcoInputs.resetFormInputState(loginForm, 'login-error-text');
                        }
                        if (typeof window.__refreshHeaderBalance === 'function') {
                            window.__refreshHeaderBalance();
                        }
                        if (window.MetropolMemberConsole && window.MetropolMemberConsole.fetchAll) {
                            window.MetropolMemberConsole.fetchAll();
                        }
                        if (Shared.hydrateMemberJwt) {
                            Shared.hydrateMemberJwt().finally(function () {
                                if (typeof window.__refreshHeaderBalance === 'function') {
                                    window.__refreshHeaderBalance();
                                }
                            });
                        }
                        var nextEl = document.getElementById('loginFormNext');
                        var nextVal = nextEl && nextEl.value ? String(nextEl.value).trim() : '';
                        window.setTimeout(function () {
                            if (nextVal.indexOf('/') === 0 && nextVal.indexOf('//') !== 0) {
                                window.location.href = nextVal;
                                return;
                            }
                            window.location.reload();
                        }, 1200);
                        return;
                    } else {
                        window.__loginSubmitting = false;
                        showError(data.message || 'Giriş başarısız.');
                        resetLoginTurnstileWidget();
                    }
                })
                .catch(function () {
                    window.__loginSubmitting = false;
                    setLoading(false);
                    showError(Shared.MSG_CONN || 'Bağlantı hatası. Lütfen tekrar deneyin.');
                    resetLoginTurnstileWidget();
                });
        });
    }

    function initForgotPasswordForm() {
        var scope = getLoginModalScope();
        var form = inLoginScope(scope, 'forgotPasswordForm');
        if (!form) return;
        try {
            if (BetcoInputs) {
                BetcoInputs.initFloatingLabels(form);
                BetcoInputs.initRequiredValidation(form, {
                    fieldNames: ['email'],
                    errorTextClass: 'login-error-text'
                });
            }
        } catch (err) {
            if (typeof console !== 'undefined') console.warn('Forgot password form init:', err);
        }

        var alertEl = inLoginScope(scope, 'forgotPasswordAjaxAlert');
        var successEl = inLoginScope(scope, 'forgotPasswordSuccess');
        var submitBtn = inLoginScope(scope, 'forgotPasswordSubmit');
        var btnText = submitBtn ? submitBtn.querySelector('.btn-text') : null;
        var btnLoading = submitBtn ? submitBtn.querySelector('.loading') : null;

        function normalizeForgotSuccessMessage(msg) {
            return String(msg || '')
                .replace(/doğrulama\s*kodu/gi, 'şifre sıfırlama bağlantısı')
                .replace(/dogrulama\s*kodu/gi, 'sifre sifirlama baglantisi')
                .replace(/kodu\s*gönderildi/gi, 'bağlantısı gönderilecektir')
                .replace(/kodu\s*gonderildi/gi, 'baglantisi gonderilecektir');
        }

        function showForgotError(msg) {
            if (!alertEl) return;
            alertEl.textContent = msg || 'Bir hata oluştu.';
            alertEl.classList.remove('d-none');
            if (successEl) {
                successEl.classList.add('d-none');
                successEl.textContent = '';
            }
        }

        function hideForgotError() {
            if (alertEl) {
                alertEl.textContent = '';
                alertEl.classList.add('d-none');
            }
        }

        function showForgotSuccess(msg) {
            var finalMsg = normalizeForgotSuccessMessage(msg || defaultOkMsg);
            if (!successEl) return;
            successEl.textContent = finalMsg;
            successEl.classList.remove('d-none');
            hideForgotError();
        }

        function setForgotLoading(loading) {
            if (Shared.setSubmitButtonLoading) {
                Shared.setSubmitButtonLoading(submitBtn, loading);
                return;
            }
            if (!submitBtn) return;
            submitBtn.disabled = loading;
            if (btnText) btnText.style.display = loading ? 'none' : '';
            if (btnLoading) btnLoading.style.display = loading ? 'inline-block' : 'none';
        }

        var defaultOkMsg =
            'E-posta adresiniz kayıtlıysa tarafımızdan şifre sıfırlama bağlantısı gönderilecektir. Gelen kutunuzu ve spam klasörünüzü kontrol edin.';

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var inp = form.querySelector('[name="email"]');
            var email = inp ? String(inp.value || '').trim() : '';
            if (!email) {
                showForgotError('E-posta adresi gerekli.');
                return;
            }

            hideForgotError();
            if (successEl) {
                successEl.classList.add('d-none');
                successEl.textContent = '';
            }
            setForgotLoading(true);

            fetch(Shared.proxyApiUrl
                ? Shared.proxyApiUrl('/auth/password-reset')
                : (Shared.apiUrl ? Shared.apiUrl('/api/v2/auth/password-reset') : '/api/v2/auth/password-reset'), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'request', email: email })
            })
                .then(function (res) {
                    return res.json().catch(function () {
                        return {};
                    });
                })
                .then(function (data) {
                    setForgotLoading(false);
                    if (data && data.success) {
                        var m =
                            (typeof data.message === 'string' && data.message.trim()) ||
                            (data.data &&
                                typeof data.data.message === 'string' &&
                                data.data.message.trim()) ||
                            defaultOkMsg;
                        showForgotSuccess(m);
                        return;
                    }
                    var errMsg =
                        (data && typeof data.message === 'string' && data.message.trim()) ||
                        'İşlem tamamlanamadı. Lütfen tekrar deneyin.';
                    showForgotError(errMsg);
                })
                .catch(function () {
                    setForgotLoading(false);
                    showForgotError(Shared.MSG_CONN || 'Bağlantı hatası. Lütfen tekrar deneyin.');
                });
        });
    }

    onReady(function () {
        initLoginModal();
        initPasswordToggle();
        initLoginForm();
        initLoginAjaxSubmit();
        initForgotPasswordForm();
        alignLoginFieldErrorsToInputs();
        window.addEventListener('resize', alignLoginFieldErrorsToInputs);

        // MutationObserver fallback: if the login form is added to DOM dynamically
        // after the initial page load, re-attach submit handlers and field bindings.
        if (typeof MutationObserver !== 'undefined') {
            var observerAttached = false;
            var domObserver = new MutationObserver(function () {
                var form = document.getElementById('loginForm');
                var modal = document.getElementById('login2');
                if (form && modal && !observerAttached) {
                    observerAttached = true;
                    initLoginForm();
                    initLoginAjaxSubmit();
                    initForgotPasswordForm();
                    alignLoginFieldErrorsToInputs();
                }
            });
            domObserver.observe(document.documentElement || document.body, {
                childList: true,
                subtree: true
            });
            // Re-check on modal show in case the observer missed it
            document.addEventListener('click', function (e) {
                var target = e.target;
                if (target && (target.id === 'Giris' || (target.closest && target.closest('#Giris')))) {
                    window.setTimeout(function () {
                        var f = document.getElementById('loginForm');
                        if (f && !observerAttached) {
                            observerAttached = true;
                            initLoginForm();
                            initLoginAjaxSubmit();
                            initForgotPasswordForm();
                            alignLoginFieldErrorsToInputs();
                        }
                    }, 300);
                }
            }, true);
        }
    });
})();
