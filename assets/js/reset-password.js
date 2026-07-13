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

    function showResetPasswordModal() {
        var el = document.getElementById('resetPasswordModal');
        if (!el) return;
        var $jq = window.jQuery || window.$;
        if ($jq && $jq.fn && typeof $jq.fn.modal === 'function') {
            $jq(el).modal('show');
        } else {
            el.classList.add('show');
            el.style.display = 'block';
            el.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open', 'reset-password-modal-open');
            document.body.style.overflow = 'hidden';
        }
    }

    function hideResetPasswordModal() {
        var el = document.getElementById('resetPasswordModal');
        if (!el) return;
        var $jq = window.jQuery || window.$;
        if ($jq && $jq.fn && typeof $jq.fn.modal === 'function') {
            $jq(el).modal('hide');
        } else {
            el.classList.remove('show');
            el.style.display = 'none';
            el.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open', 'reset-password-modal-open');
            document.body.style.overflow = '';
        }
    }

    onReady(function () {
        var modalEl = document.getElementById('resetPasswordModal');
        var form = document.getElementById('resetPasswordForm');
        var tokenInput = document.getElementById('resetPasswordToken');
        var missingBox = document.getElementById('resetPasswordMissingToken');
        var alertEl = document.getElementById('resetPasswordAjaxAlert');
        var successEl = document.getElementById('resetPasswordSuccess');
        var submitBtn = document.getElementById('resetPasswordSubmit');
        var btnText = submitBtn ? submitBtn.querySelector('.btn-text') : null;
        var btnLoading = submitBtn ? submitBtn.querySelector('.loading') : null;
        var closeBtn = document.querySelector('.reset-password-close');

        if (modalEl) {
            showResetPasswordModal();
            if (closeBtn) {
                closeBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    hideResetPasswordModal();
                    window.location.href = '/';
                });
            }
        }

        if (!form || !tokenInput) return;

        var fromQuery = readTokenFromQuery();
        if (fromQuery && !String(tokenInput.value || '').trim()) {
            tokenInput.value = fromQuery;
        }

        var token = String(tokenInput.value || '').trim();
        if (token) {
            if (missingBox) missingBox.classList.add('d-none');
            form.classList.remove('d-none');
        } else {
            if (missingBox) missingBox.classList.remove('d-none');
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
                            window.location.href = '/?login=1&password_updated=1';
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
