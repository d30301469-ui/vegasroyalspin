// Kayıt sayfası / kayıt modalı betikleri (sayfaya özel)
(function () {
    var $jq = window.jQuery || window.$;
    var Shared = window.BetcoAuthShared || {};

    function getJq() {
        return window.jQuery || window.$ || $jq;
    }

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

    function showModalByElement(modalEl) {
        var jq = getJq();
        if (jq && jq.fn && typeof jq.fn.modal === 'function') {
            jq(modalEl).modal('show');
            return;
        }
        if (typeof window.showModalById === 'function' && modalEl && modalEl.id) {
            window.showModalById(modalEl.id);
        }
    }

    function hideModalByElement(modalEl) {
        var jq = getJq();
        if (jq && jq.fn && typeof jq.fn.modal === 'function') {
            jq(modalEl).modal('hide');
            return;
        }
        if (typeof window.hideModalById === 'function' && modalEl && modalEl.id) {
            window.hideModalById(modalEl.id);
        }
    }

    function resetRegisterForm() {
        var form = document.getElementById('modalRegisterForm');
        if (!form) return;

        form.reset();
        var step1 = form.querySelector('.register-step-1');
        var step2 = form.querySelector('.register-step-2');
        var mobileSingle = form.classList.contains('register-form--mobile-single');
        if (step1) step1.classList.remove('d-none');
        if (step2) {
            if (mobileSingle) {
                step2.classList.remove('d-none');
            } else {
                step2.classList.add('d-none');
            }
        }
        if (typeof window.BetcoInputs !== 'undefined') {
            window.BetcoInputs.resetFormInputState(form, 'register-error-text');
        }
        form.setAttribute('data-active-step', '1');
        updateRegisterStepIndicators(1);
        resetCustomSelectsDisplay();
        resetDobDisplay();
    }

    function updateRegisterStepIndicators(activeStep) {
        var indicators = document.querySelectorAll('[data-register-step-indicator]');
        indicators.forEach(function (indicator) {
            indicator.classList.toggle('step-indicator-active', indicator.getAttribute('data-register-step-indicator') === String(activeStep));
        });
    }

    // Kayıt modalı: açma, kapatma, form doğrulama (global.js BetcoInputs kullanır)
    function initRegisterModal() {
        var registerBtn = document.getElementById('openRegister');
        var registerModal = document.getElementById('registerModal');
        if (!registerModal) return;

        if (registerBtn) {
            registerBtn.addEventListener('click', function (e) {
                if (e.defaultPrevented) return;
                e.preventDefault();
                showModalByElement(registerModal);
            });
        }

        var registerCloseBtn = registerModal.querySelector('.register-modal-close');
        if (registerCloseBtn) {
            registerCloseBtn.addEventListener('click', function () {
                hideModalByElement(registerModal);
                resetRegisterForm();
            });
        }

        document.addEventListener('click', function (e) {
            var t = e.target;
            if (t && (t.id === 'openRegister' || (t.closest && t.closest('#openRegister')) ||
                      t.id === 'openRegister2' || (t.closest && t.closest('#openRegister2')))) {
                e.preventDefault();
                showModalByElement(registerModal);
            }
        }, true);

        var jq = getJq();
        if (jq && jq.fn && typeof jq.fn.on === 'function') {
            jq(registerModal).on('hidden.bs.modal', resetRegisterForm);
        }
    }

    function initRegisterFormValidation() {
        var form = document.getElementById('modalRegisterForm');
        if (!form || typeof window.BetcoInputs === 'undefined') return;
        window.BetcoInputs.initFloatingLabels(form);
        window.BetcoInputs.initRequiredValidation(form, {
            fieldNames: [
                'username', 'password', 'confirm_password',
                'firstName', 'surname', 'dob', 'country', 'currency', 'city',
                'tcKimlik', 'email', 'gender', 'phone_country_code', 'phone', 'terms_accepted'
            ],
            errorTextClass: 'register-error-text'
        });
    }

    // Kayıt modalı adım geçişi (SONRAKİ / GERİ)
    function initRegisterStepNavigation() {
        var form = document.getElementById('modalRegisterForm');
        if (!form || form.classList.contains('register-form--mobile-single')) return;
        var step1 = form.querySelector('.register-step-1');
        var step2 = form.querySelector('.register-step-2');
        var nextBtn = document.getElementById('registerNextStep');
        var prevBtn = document.getElementById('registerPrevStep');
        if (!step1 || !step2 || !nextBtn) return;

        function showStep(stepEl) {
            var steps = form.querySelectorAll('.register-step');
            steps.forEach(function (s) {
                s.classList.add('d-none');
            });
            if (stepEl) {
                stepEl.classList.remove('d-none');
                var activeStep = stepEl.getAttribute('data-step') || '1';
                form.setAttribute('data-active-step', activeStep);
                updateRegisterStepIndicators(activeStep);
            }
        }

        function validateStep1() {
            var username = (form.querySelector('[name="username"]') || {}).value || '';
            var password = (form.querySelector('[name="password"]') || {}).value || '';
            var confirmPassword = (form.querySelector('[name="confirm_password"]') || {}).value || '';
            var errorClass = 'register-error-text';
            var errors = form.querySelectorAll('.' + errorClass + '[data-error-for="username"],.' + errorClass + '[data-error-for="password"],.' + errorClass + '[data-error-for="confirm_password"]');
            errors.forEach(function (el) { el.classList.add('d-none'); });

            var valid = true;
            if (!username.trim()) {
                var uErr = form.querySelector('.' + errorClass + '[data-error-for="username"]');
                if (uErr) { uErr.classList.remove('d-none'); valid = false; }
            }
            if (!password) {
                var pErr = form.querySelector('.' + errorClass + '[data-error-for="password"]');
                if (pErr) { pErr.classList.remove('d-none'); valid = false; }
            }
            if (password !== confirmPassword || !confirmPassword) {
                var cErr = form.querySelector('.' + errorClass + '[data-error-for="confirm_password"]');
                if (cErr) {
                    cErr.textContent = !confirmPassword ? 'Bu alan gerekli' : 'Şifreler eşleşmiyor';
                    cErr.classList.remove('d-none');
                    valid = false;
                }
            }
            return valid;
        }

        nextBtn.addEventListener('click', function () {
            if (!validateStep1()) return;
            showStep(step2);
        });

        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                showStep(step1);
            });
        }

        form.setAttribute('data-active-step', '1');
        updateRegisterStepIndicators(1);
    }

    function initCustomSelects() {
        var containers = document.querySelectorAll('.bc-custom-select[data-bc-custom-select]');
        containers.forEach(function (wrapper) {
            var select = wrapper.querySelector('.bc-custom-select__native');
            var trigger = wrapper.querySelector('.bc-custom-select__trigger');
            var valueEl = wrapper.querySelector('.bc-custom-select__value');
            var panel = wrapper.querySelector('.bc-custom-select__panel');
            var options = wrapper.querySelectorAll('.bc-custom-select__option');
            if (!select || !trigger || !valueEl || !panel) return;

            function getPlaceholder() {
                var first = select.querySelector('option[disabled]');
                return first ? first.textContent : 'Seçin';
            }

            function updateValueDisplay() {
                var val = select.value;
                var opt = select.querySelector('option[value="' + val + '"]');
                valueEl.textContent = val && opt ? opt.textContent : getPlaceholder();
            }

            function close() {
                wrapper.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
                panel.setAttribute('hidden', '');
            }

            function open() {
                panel.removeAttribute('hidden');
                wrapper.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
            }

            trigger.addEventListener('click', function (e) {
                e.preventDefault();
                if (wrapper.classList.contains('is-open')) {
                    close();
                } else {
                    open();
                }
            });

            options.forEach(function (opt) {
                opt.addEventListener('click', function (e) {
                    e.preventDefault();
                    var val = opt.getAttribute('data-value');
                    if (val) {
                        select.value = val;
                        updateValueDisplay();
                        close();
                    }
                });
            });

            document.addEventListener('click', function (e) {
                if (wrapper.classList.contains('is-open') && !wrapper.contains(e.target)) {
                    close();
                }
            });
        });
    }

    function resetCustomSelectsDisplay() {
        document.querySelectorAll('.bc-custom-select[data-bc-custom-select]').forEach(function (wrapper) {
            var select = wrapper.querySelector('.bc-custom-select__native');
            var valueEl = wrapper.querySelector('.bc-custom-select__value');
            if (!select || !valueEl) return;
            var first = select.querySelector('option[disabled]');
            valueEl.textContent = first ? first.textContent : 'Seçin';
        });
    }

    function initRegisterDatepicker() {
        var wrapper = document.getElementById('register_dob_picker');
        if (!wrapper) return;
        var input = document.getElementById('modal_dob');
        var trigger = wrapper.querySelector('.register-dob-trigger');
        var valueEl = wrapper.querySelector('.register-dob-value');
        var panel = document.getElementById('register_datepicker_panel');
        var monthSelect = wrapper.querySelector('.register-datepicker-month');
        var yearSelect = wrapper.querySelector('.register-datepicker-year');
        var daysEl = wrapper.querySelector('.register-datepicker-days');
        var prevBtn = wrapper.querySelector('.register-datepicker-prev');
        var cancelBtn = wrapper.querySelector('.register-datepicker-cancel');
        var applyBtn = wrapper.querySelector('.register-datepicker-apply');
        var placeholder = valueEl ? valueEl.getAttribute('data-placeholder') || 'gg.aa.yyyy' : 'gg.aa.yyyy';

        var viewDate = new Date();
        var pendingSelected = null;

        function pad(n) { return n < 10 ? '0' + n : String(n); }
        function toYMD(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
        function toDisplayYMD(str) {
            if (!str) return '';
            var p = str.split('-');
            if (p.length !== 3) return str;
            return p[2] + '.' + p[1] + '.' + p[0];
        }

        function fillYears() {
            if (!yearSelect) return;
            var currentYear = new Date().getFullYear();
            var minYear = currentYear - 100;
            var maxYear = currentYear - 18;
            yearSelect.innerHTML = '';
            for (var y = maxYear; y >= minYear; y--) {
                var opt = document.createElement('option');
                opt.value = y;
                opt.textContent = y;
                yearSelect.appendChild(opt);
            }
        }

        function renderDays() {
            if (!daysEl) return;
            var year = viewDate.getFullYear();
            var month = viewDate.getMonth();
            var first = new Date(year, month, 1);
            var last = new Date(year, month + 1, 0);
            var lastDay = last.getDate();
            var startOffset = (first.getDay() + 6) % 7;
            daysEl.innerHTML = '';
            var cell;
            for (var i = 0; i < startOffset; i++) {
                cell = document.createElement('span');
                cell.className = 'register-datepicker-day other-month';
                cell.setAttribute('aria-hidden', 'true');
                daysEl.appendChild(cell);
            }
            for (var d = 1; d <= lastDay; d++) {
                cell = document.createElement('button');
                cell.type = 'button';
                cell.className = 'register-datepicker-day';
                var dateStr = year + '-' + pad(month + 1) + '-' + pad(d);
                cell.setAttribute('data-date', dateStr);
                cell.textContent = d;
                if (pendingSelected === dateStr) cell.classList.add('selected');
                cell.addEventListener('click', function () {
                    var dt = this.getAttribute('data-date');
                    if (!dt) return;
                    pendingSelected = dt;
                    daysEl.querySelectorAll('.register-datepicker-day.selected').forEach(function (el) { el.classList.remove('selected'); });
                    this.classList.add('selected');
                });
                daysEl.appendChild(cell);
            }
        }

        function positionPanelFixed() {
            var rect = trigger.getBoundingClientRect();
            panel.style.position = 'fixed';
            panel.style.top = (rect.bottom + 6) + 'px';
            panel.style.right = (window.innerWidth - rect.right) + 'px';
            panel.style.left = 'auto';
        }

        function openPanel() {
            var val = input.value;
            if (val) {
                var parts = val.split('-');
                if (parts.length === 3) {
                    viewDate = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                    pendingSelected = val;
                }
            }
            monthSelect.value = viewDate.getMonth();
            yearSelect.value = viewDate.getFullYear();
            renderDays();
            positionPanelFixed();
            panel.removeAttribute('hidden');
            trigger.setAttribute('aria-expanded', 'true');
        }

        function closePanel() {
            panel.setAttribute('hidden', '');
            trigger.setAttribute('aria-expanded', 'false');
            panel.style.position = '';
            panel.style.top = '';
            panel.style.right = '';
            panel.style.left = '';
            panel.style.width = '';
        }

        function applySelection() {
            var toApply = pendingSelected;
            if (!toApply && viewDate) {
                var day = 15;
                toApply = viewDate.getFullYear() + '-' + pad(viewDate.getMonth() + 1) + '-' + pad(day);
            }
            if (toApply) {
                input.value = toApply;
                valueEl.textContent = toDisplayYMD(toApply);
                valueEl.classList.remove('is-placeholder');
            }
            closePanel();
        }

        trigger.addEventListener('click', function () {
            if (panel.getAttribute('hidden') !== null) {
                fillYears();
                openPanel();
            } else {
                closePanel();
            }
        });

        prevBtn && prevBtn.addEventListener('click', function () {
            viewDate.setMonth(viewDate.getMonth() - 1);
            monthSelect.value = viewDate.getMonth();
            yearSelect.value = viewDate.getFullYear();
            renderDays();
        });

        monthSelect.addEventListener('change', function () {
            viewDate.setMonth(parseInt(monthSelect.value, 10));
            renderDays();
        });
        yearSelect.addEventListener('change', function () {
            viewDate.setFullYear(parseInt(yearSelect.value, 10));
            renderDays();
        });

        cancelBtn && cancelBtn.addEventListener('click', closePanel);
        applyBtn && applyBtn.addEventListener('click', applySelection);

        document.addEventListener('click', function (e) {
            if (panel.getAttribute('hidden') === null && !wrapper.contains(e.target)) {
                closePanel();
            }
        });

        if (input.value) {
            valueEl.textContent = toDisplayYMD(input.value);
            valueEl.classList.remove('is-placeholder');
        } else {
            valueEl.textContent = placeholder;
            valueEl.classList.add('is-placeholder');
        }
    }

    function resetDobDisplay() {
        var wrapper = document.getElementById('register_dob_picker');
        if (!wrapper) return;
        var input = document.getElementById('modal_dob');
        var valueEl = wrapper.querySelector('.register-dob-value');
        var placeholder = valueEl ? valueEl.getAttribute('data-placeholder') || 'gg.aa.yyyy' : 'gg.aa.yyyy';
        if (input) input.value = '';
        if (valueEl) {
            valueEl.textContent = placeholder;
            valueEl.classList.add('is-placeholder');
        }
    }

    function initRegisterFormSubmit() {
        var form = document.getElementById('modalRegisterForm');
        var registerModal = document.getElementById('registerModal');
        var successModal = document.getElementById('registerSuccessModal');
        var submitBtn = form ? form.querySelector('#modalRegisterSubmit') : null;
        if (!form || !registerModal) return;

        function toast(type, msg, title) {
            if (window.MaltabetToast) {
                MaltabetToast.show(msg, { type: type || 'info', title: title });
            }
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(form);
            fd.append('register_submit', '1');
            if (Shared.setSubmitButtonLoading) {
                Shared.setSubmitButtonLoading(submitBtn, true);
            } else if (submitBtn) {
                submitBtn.disabled = true;
            }
            fetch(Shared.proxyApiUrl
                ? Shared.proxyApiUrl('/auth/register')
                : (Shared.apiUrl ? Shared.apiUrl('/api/v2/auth/register') : '/api/v2/auth/register'), {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: Shared.memberAuthHeaders
                    ? Shared.memberAuthHeaders({ Accept: 'application/json' })
                    : { Accept: 'application/json' }
            })
                .then(function (res) {
                    return res.text().then(function (text) {
                        var body = null;
                        try {
                            body = text ? JSON.parse(text.replace(/^\uFEFF/, '').trim()) : null;
                        } catch (eJson) {
                            body = null;
                        }
                        if (!body || typeof body !== 'object') {
                            body = {
                                success: false,
                                message: 'Sunucu yanıtı okunamadı. Sayfayı yenileyip tekrar deneyin.'
                            };
                        }
                        return { ok: res.ok, status: res.status, body: body };
                    });
                })
                .then(function (r) {
                    if (Shared.setSubmitButtonLoading) {
                        Shared.setSubmitButtonLoading(submitBtn, false);
                    } else if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                    var data = r.body || {};
                    var okRegister = data.success === true && (data.code === 201 || data.code === undefined);
                    if (okRegister) {
                        if (Shared.applyLoginEnvelope) {
                            Shared.applyLoginEnvelope(data);
                        } else {
                            window.__USER_LOGGED_IN__ = true;
                            window.__HAS_MEMBER_JWT__ = true;
                        }
                        toast('success', data.message || 'Kayıt başarılı.', 'Kayıt');
                        hideModalByElement(registerModal);
                        if (successModal) {
                            showModalByElement(successModal);
                        } else if (typeof window.location !== 'undefined' && window.location.reload) {
                            window.location.reload();
                        }
                        return;
                    }
                    var msg = data.message || 'Kayıt başarısız.';
                    if (data.errors && typeof data.errors === 'object') {
                        var parts = [];
                        Object.keys(data.errors).forEach(function (k) {
                            parts.push(data.errors[k]);
                        });
                        if (parts.length) {
                            msg = parts.join(' ');
                        }
                    }
                    if (window.MaltabetToast) toast('error', msg, 'Kayıt');
                    else alert(msg);
                })
                .catch(function (err) {
                    if (Shared.setSubmitButtonLoading) {
                        Shared.setSubmitButtonLoading(submitBtn, false);
                    } else if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                    var conn = Shared.MSG_CONN || 'Bağlantı hatası. Lütfen tekrar deneyin.';
                    if (window.MaltabetToast) toast('error', conn, 'Kayıt');
                    else alert(conn);
                });
        });

        var successOk = document.getElementById('registerSuccessOk');
        if (successOk && successModal) {
            successOk.addEventListener('click', function () {
                hideModalByElement(successModal);
                if (typeof window.location !== 'undefined' && window.location.reload) {
                    window.location.reload();
                }
            });
        }
    }

    onReady(function () {
        initRegisterModal();
        initRegisterFormValidation();
        initRegisterStepNavigation();
        initCustomSelects();
        initRegisterDatepicker();
        initRegisterFormSubmit();
    });

    // TC Kimlik numarası formatı (modal veya standalone form)
    var tcInput = document.querySelector('input[name="tcKimlik"]');
    if (tcInput) {
        tcInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 11) {
                this.value = this.value.slice(0, 11);
            }
        });
    }

    // Password visibility toggle
    document.addEventListener('click', function (e) {
        if (e.target.classList && e.target.classList.contains('toggle-password')) {
            var icon = e.target;
            var wrapper = icon.parentNode;
            if (!wrapper) return;
            var input = wrapper.querySelector('.password-input');
            if (!input) return;

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    });

    // Canlı username ve email kontrolü (modal: modal_username/modal_email, standalone: username/email)
    var usernameInput = document.getElementById('modal_username') || document.getElementById('username');
    var emailInput = document.getElementById('modal_email') || document.getElementById('email');

    function checkUsernameEmail() {
        var username = usernameInput ? usernameInput.value.trim() : '';
        var email = emailInput ? emailInput.value.trim() : '';

        var usernameFeedback = document.getElementById('username-feedback');
        var emailFeedback = document.getElementById('email-feedback');

        if (username.length === 0 && email.length === 0) {
            if (usernameFeedback) usernameFeedback.classList.add('d-none');
            if (emailFeedback) emailFeedback.classList.add('d-none');
            return;
        }

        var formData = new FormData();
        formData.append('ajax_check', 'true');
        formData.append('username', username);
        formData.append('email', email);

        var csrfInput = document.querySelector('input[name="csrf_token"]');
        if (csrfInput && csrfInput.value) {
            formData.append('csrf_token', csrfInput.value);
        }

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
            .then(function (response) {
                var ct = (response.headers.get('content-type') || '').toLowerCase();
                if (!response.ok || ct.indexOf('application/json') === -1) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                if (data.username === false) {
                    if (usernameFeedback) usernameFeedback.classList.remove('d-none');
                } else {
                    if (usernameFeedback) usernameFeedback.classList.add('d-none');
                }

                if (data.email === false) {
                    if (emailFeedback) emailFeedback.classList.remove('d-none');
                } else {
                    if (emailFeedback) emailFeedback.classList.add('d-none');
                }
            })
            .catch(function (error) {
                console.error('Error:', error);
            });
    }

    if (usernameInput) {
        usernameInput.addEventListener('blur', checkUsernameEmail);
    }

    if (emailInput) {
        emailInput.addEventListener('blur', checkUsernameEmail);
    }

    // Close button functionality
    var closeBtn = document.querySelector('.close-btn');
    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            if (confirm('Formu kapatmak istediğinizden emin misiniz?')) {
                window.location.href = '/';
            }
        });
    }

    // Form gönderiminde çift tıklamayı önlemek için buton disabled (dönme animasyonu yok)
    var registrationForm = document.getElementById('registrationForm');
    if (registrationForm) {
        registrationForm.addEventListener('submit', function () {
            var submitBtn = registrationForm.querySelector('.register-btn');
            if (submitBtn) {
                submitBtn.disabled = true;
            }
        });
    }

    // PHP tarafı yalnızca veri üretir; çalıştırılabilir JS kabul edilmez.
    try {
        var configScript = document.getElementById('register-page-config');
        if (configScript && configScript.textContent) {
            var cfg = JSON.parse(configScript.textContent);
            if (cfg && cfg.register_message) {
                toast(cfg.register_message_type || 'info', String(cfg.register_message), cfg.register_message_title || 'Bilgi');
            }
        }
    } catch (e) {
        console.error('Register page config parse error:', e);
    }
})();

