/**
 * /reset-password — POST /api/v2/auth/password-reset (action: confirm; token from URL veya gizli alan).
 */
(function () {
    var BetcoInputs = window.BetcoInputs;
    var Shared = window.BetcoAuthShared || {};
    function apiUrl(path) {
        return Shared.apiUrl ? Shared.apiUrl(path) : path;
    }

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function readTokenFromQuery() {
        try {
            var q = new URLSearchParams(window.location.search);
            return (q.get('token') || q.get('reset_token') || '').trim();
        } catch (e) {
            return '';
        }
    }

    function showError(el, msg) {
        if (!el) return;
        el.textContent = msg || 'Bir hata oluştu.';
        el.classList.remove('d-none');
    }

    function hideError(el) {
        if (!el) return;
        el.textContent = '';
        el.classList.add('d-none');
    }

    function showSuccess(el, msg) {
        if (!el) return;
        el.textContent = msg || '';
        el.classList.remove('d-none');
    }

    function applyReferenceSkin() {
        var modal = document.querySelector('.reset-password-modal');
        if (!modal) return;

        var heroNodes = modal.querySelectorAll('.reset-password-hero, .reset-ref-hero');
        var primaryHero = heroNodes.length ? heroNodes[0] : null;
        for (var heroIndex = 1; heroIndex < heroNodes.length; heroIndex += 1) {
            if (heroNodes[heroIndex] && heroNodes[heroIndex].parentNode) {
                heroNodes[heroIndex].parentNode.removeChild(heroNodes[heroIndex]);
            }
        }

        var closeButtons = modal.querySelectorAll('#resetPasswordClose, .reset-password-close');
        for (var closeIndex = 1; closeIndex < closeButtons.length; closeIndex += 1) {
            if (closeButtons[closeIndex] && closeButtons[closeIndex].parentNode) {
                closeButtons[closeIndex].parentNode.removeChild(closeButtons[closeIndex]);
            }
        }

        var theme = window.__RESET_PASSWORD_THEME__ || {};
        function asText(value, fallback) {
            var t = String(value == null ? '' : value).trim();
            return t !== '' ? t : fallback;
        }

        var titleRequest = asText(theme.titleRequest, 'SIFRE SIFIRLA');
        var titleConfirm = asText(theme.titleConfirm, 'YENI SIFRE');
        var leadText = asText(theme.leadText, 'Sifrenizi sifirlamak icin kayitli e-posta adresinizi giriniz.');
        var buttonText = asText(theme.buttonText, 'SIFIRLA');
        var infoText = asText(theme.infoText, leadText);
        var brandText = asText(theme.brandText, 'Vegasroyalspin');
        var heroImageUrl = asText(theme.heroImageUrl, '/assets/images/login-bg.png');
        var cssHeroImageUrl = '"' + heroImageUrl.replace(/["\\]/g, '\\$&') + '"';
        var modalBg = asText(theme.modalBg, 'linear-gradient(145deg,#1b0c49 0%,#0a0f3c 60%,#09123f 100%)');
        var heroTopBorderColor = asText(theme.heroTopBorderColor, '#7d1c7a');
        var heroBottomBorderColor = asText(theme.heroBottomBorderColor, '#ff00ff');
        var inputBorderColor = asText(theme.inputBorderColor, '#ec46aa');
        var buttonTextColor = asText(theme.buttonTextColor, '#d2d6eb');

        document.body.classList.add('reset-password-reference-skin');

        var title = document.getElementById('resetPasswordTitle');
        if (title) {
            var t = (title.textContent || '').toLowerCase();
            title.textContent = t.indexOf('yeni') >= 0 ? titleConfirm : titleRequest;
        }

        var lead = modal.querySelector('.reset-password-lead');
        if (lead) {
            lead.textContent = leadText;
        }

        var requestBtnText = document.querySelector('#resetPasswordRequestSubmit .btn-text');
        if (requestBtnText) requestBtnText.textContent = buttonText;

        var confirmBtnText = document.querySelector('#resetPasswordSubmit .btn-text');
        if (confirmBtnText) confirmBtnText.textContent = buttonText;

        var infoLink = modal.querySelector('.reset-password-actions a');
        if (infoLink) {
            infoLink.textContent = infoText;
            infoLink.setAttribute('href', '#');
            infoLink.addEventListener('click', function (e) { e.preventDefault(); });
        }

        var hero = primaryHero || modal.querySelector('.reset-password-hero, .reset-ref-hero');
        if (!hero) {
            hero = document.createElement('div');
            hero.className = 'reset-ref-hero';
            var brand = document.createElement('div');
            brand.className = 'reset-ref-brand';
            brand.textContent = brandText;
            hero.appendChild(brand);
            modal.insertBefore(hero, modal.firstChild);
        } else {
            var currentBrand = hero.querySelector('.reset-ref-brand, .reset-password-brand');
            if (currentBrand) currentBrand.textContent = brandText;
        }

        var closeBtn = document.getElementById('resetPasswordClose') || modal.querySelector('.reset-password-close');
        if (closeBtn) {
            closeBtn.className = 'reset-password-close';
        }
        if (closeBtn && closeBtn.parentNode !== hero) {
            hero.appendChild(closeBtn);
        }

        if (!document.getElementById('resetReferenceSkinStyles')) {
            var style = document.createElement('style');
            style.id = 'resetReferenceSkinStyles';
            style.textContent = [
                'body.reset-password-reference-skin .reset-password-modal{background:linear-gradient(145deg,#1b0c49 0%,#0a0f3c 60%,#09123f 100%) !important;min-height:88vh !important;padding:0 !important;gap:0 !important;}',
                'body.reset-password-reference-skin .reset-ref-hero, body.reset-password-reference-skin .reset-password-hero{position:relative;height:300px;border-top:8px solid ' + heroTopBorderColor + ';border-bottom:5px solid ' + heroBottomBorderColor + ';background:#15063f;overflow:hidden;}',
                'body.reset-password-reference-skin .reset-ref-hero::before, body.reset-password-reference-skin .reset-password-hero::before{content:"";position:absolute;inset:0;background-image:url(' + cssHeroImageUrl + ');background-size:cover;background-position:center top;opacity:.97;}',
                'body.reset-password-reference-skin .reset-ref-brand{position:absolute;top:22px;left:50%;transform:translateX(-50%);z-index:2;color:#fff;font-size:38px;font-weight:700;line-height:1;}',
                'body.reset-password-reference-skin .reset-password-close{position:absolute !important;top:2px !important;right:6px !important;width:34px !important;height:34px !important;min-width:34px !important;min-height:34px !important;background:transparent !important;border:0 !important;color:rgba(170,164,198,.95) !important;z-index:3 !important;}',
                'body.reset-password-reference-skin .reset-password-close::before{content:"✕" !important;font-size:28px !important;font-weight:300 !important;line-height:1 !important;text-shadow:0 1px 0 rgba(0,0,0,.35) !important;}',
                'body.reset-password-reference-skin .login-text-block{display:none !important;}',
                'body.reset-password-reference-skin .reset-password-content, body.reset-password-reference-skin .login-form{padding:14px 10px 0 !important;}',
                'body.reset-password-reference-skin #resetPasswordModal .form-control-input-bc{height:50px !important;min-height:50px !important;border-radius:4px !important;border:1px solid ' + inputBorderColor + ' !important;background:rgba(83,67,122,.42) !important;color:#f7f4ff !important;padding-left:12px !important;}',
                'body.reset-password-reference-skin #resetPasswordModal .login-error-text{margin-top:-2px !important;border-radius:3px !important;background:rgba(107,26,74,.8) !important;color:#fff !important;font-size:13px !important;padding:3px 9px !important;}',
                'body.reset-password-reference-skin #resetPasswordModal .login-btn{height:52px !important;min-height:52px !important;border-radius:4px !important;background:rgba(111,122,176,.24) !important;border:1px solid rgba(146,156,201,.16) !important;color:' + buttonTextColor + ' !important;box-shadow:none !important;font-size:28px !important;font-weight:700 !important;text-transform:uppercase !important;}',
                'body.reset-password-reference-skin .reset-password-actions{margin:10px 10px 14px !important;display:flex !important;align-items:center !important;gap:6px !important;color:rgba(216,216,232,.72) !important;font-size:13px !important;}',
                'body.reset-password-reference-skin .reset-password-actions::before{content:"i";display:inline-flex;align-items:center;justify-content:center;width:15px;height:15px;border-radius:50%;border:1px solid rgba(216,216,232,.72);font-size:11px;font-weight:700;}',
                'body.reset-password-reference-skin .reset-password-actions a{color:rgba(216,216,232,.72) !important;border:0 !important;text-decoration:none !important;}',
                '@media (max-width:480px){body.reset-password-reference-skin .reset-ref-hero{height:300px;} body.reset-password-reference-skin #resetPasswordModal .login-btn{font-size:27px !important;}}'
            ].join('');
            style.textContent = style.textContent.replace('linear-gradient(145deg,#1b0c49 0%,#0a0f3c 60%,#09123f 100%)', modalBg);
            document.head.appendChild(style);
        }
    }

    onReady(function () {
        applyReferenceSkin();

        var requestForm = document.getElementById('resetPasswordRequestForm');
        var requestAlertEl = document.getElementById('resetPasswordRequestAlert');
        var requestSuccessEl = document.getElementById('resetPasswordRequestSuccess');
        var requestSubmitBtn = document.getElementById('resetPasswordRequestSubmit');
        var requestBtnText = requestSubmitBtn ? requestSubmitBtn.querySelector('.btn-text') : null;
        var requestBtnLoading = requestSubmitBtn ? requestSubmitBtn.querySelector('.loading') : null;

        var form = document.getElementById('resetPasswordForm');
        var tokenInput = document.getElementById('resetPasswordToken');
        var alertEl = document.getElementById('resetPasswordAjaxAlert');
        var successEl = document.getElementById('resetPasswordSuccess');
        var submitBtn = document.getElementById('resetPasswordSubmit');
        var btnText = submitBtn ? submitBtn.querySelector('.btn-text') : null;
        var btnLoading = submitBtn ? submitBtn.querySelector('.loading') : null;

        if (requestForm) {
            try {
                if (BetcoInputs) {
                    BetcoInputs.initFloatingLabels(requestForm);
                    BetcoInputs.initRequiredValidation(requestForm, {
                        fieldNames: ['email'],
                        errorTextClass: 'login-error-text'
                    });
                }
            } catch (err) {
                if (typeof console !== 'undefined') console.warn('Reset request form init:', err);
            }

            function setRequestLoading(loading) {
                if (!requestSubmitBtn) return;
                requestSubmitBtn.disabled = loading;
                if (requestBtnText) requestBtnText.style.display = loading ? 'none' : '';
                if (requestBtnLoading) requestBtnLoading.style.display = loading ? 'inline-block' : 'none';
            }

            requestForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var email = ((requestForm.querySelector('[name="email"]') || {}).value || '').trim();

                hideError(requestAlertEl);
                if (requestSuccessEl) {
                    requestSuccessEl.classList.add('d-none');
                    requestSuccessEl.textContent = '';
                }

                if (!email) {
                    showError(requestAlertEl, 'E-posta adresi gerekli.');
                    return;
                }

                setRequestLoading(true);
                fetch(apiUrl('/api/v2/auth/password-reset'), {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'request',
                        email: email
                    })
                })
                    .then(function (res) {
                        return res.json().catch(function () {
                            return {};
                        });
                    })
                    .then(function (data) {
                        setRequestLoading(false);
                        if (data && data.success) {
                            var okMsg =
                                (typeof data.message === 'string' && data.message.trim()) ||
                                'Eğer e-posta sistemde kayıtlıysa doğrulama kodu veya sıfırlama bağlantısı gönderilecektir.';
                            showSuccess(requestSuccessEl, okMsg);
                            requestForm.reset();
                            if (BetcoInputs) {
                                BetcoInputs.resetFormInputState(requestForm, 'login-error-text');
                            }
                            return;
                        }
                        var requestErr =
                            (data && typeof data.message === 'string' && data.message.trim()) ||
                            'İşlem tamamlanamadı. Lütfen tekrar deneyin.';
                        showError(requestAlertEl, requestErr);
                    })
                    .catch(function () {
                        setRequestLoading(false);
                        showError(requestAlertEl, 'Bağlantı hatası. Lütfen tekrar deneyin.');
                    });
            });
        }

        if (!form || !tokenInput) return;

        var fromQuery = readTokenFromQuery();
        if (fromQuery && !String(tokenInput.value || '').trim()) {
            tokenInput.value = fromQuery;
        }

        var token = String(tokenInput.value || '').trim();
        if (token) {
            form.classList.remove('d-none');
        } else {
            form.classList.add('d-none');
            return;
        }

        try {
            if (BetcoInputs) {
                BetcoInputs.initFloatingLabels(form);
                BetcoInputs.initRequiredValidation(form, {
                    fieldNames: ['password', 'password_confirmation'],
                    errorTextClass: 'login-error-text'
                });
            }
        } catch (err) {
            if (typeof console !== 'undefined') console.warn('Reset password form init:', err);
        }

        function setLoading(loading) {
            if (!submitBtn) return;
            submitBtn.disabled = loading;
            if (btnText) btnText.style.display = loading ? 'none' : '';
            if (btnLoading) btnLoading.style.display = loading ? 'inline-block' : 'none';
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var t = String(tokenInput.value || '').trim();
            var p = (form.querySelector('[name="password"]') || {}).value || '';
            var c = (form.querySelector('[name="password_confirmation"]') || {}).value || '';

            hideError(alertEl);
            if (successEl) {
                successEl.classList.add('d-none');
                successEl.textContent = '';
            }

            if (!t) {
                showError(alertEl, 'Sıfırlama anahtarı bulunamadı.');
                return;
            }
            if (!p || !c) {
                showError(alertEl, 'Yeni şifre ve tekrarı gerekli.');
                return;
            }
            if (p !== c) {
                showError(alertEl, 'Şifre ve şifre tekrarı eşleşmiyor.');
                return;
            }

            setLoading(true);

            fetch(apiUrl('/api/v2/auth/password-reset'), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'confirm',
                    token: t,
                    password: p,
                    password_confirmation: c
                })
            })
                .then(function (res) {
                    return res.json().catch(function () {
                        return {};
                    });
                })
                .then(function (data) {
                    setLoading(false);
                    if (data && data.success) {
                        var msg =
                            (typeof data.message === 'string' && data.message.trim()) ||
                            'Şifreniz başarıyla güncellendi. Yeni şifrenizle giriş yapabilirsiniz.';
                        showSuccess(successEl, msg);
                        form.reset();
                        if (BetcoInputs) {
                            BetcoInputs.resetFormInputState(form, 'login-error-text');
                        }
                        tokenInput.value = t;
                        window.setTimeout(function () {
                            window.location.href = '/';
                        }, 2000);
                        return;
                    }
                    var errMsg =
                        (data && typeof data.message === 'string' && data.message.trim()) ||
                        'Şifre güncellenemedi. Bağlantının süresi dolmuş olabilir.';
                    showError(alertEl, errMsg);
                })
                .catch(function () {
                    setLoading(false);
                    showError(alertEl, 'Bağlantı hatası. Lütfen tekrar deneyin.');
                });
        });
    });
})();
