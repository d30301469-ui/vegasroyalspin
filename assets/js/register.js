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

    // Mobil tek-adım: KAYIT + WalletConnect butonlarini kaydirilan alandan
    // sabit alt bar'a tasi (referans m.casinomilyon duzeni). Masaustunu etkilemez.
    function relocateMobileFooterActions(modalEl) {
        if (!modalEl || !document.body.classList.contains('mobile-site')) return;
        var form = modalEl.querySelector('#modalRegisterForm');
        if (!form || !form.classList.contains('register-form--mobile-single')) return;
        var footer = modalEl.querySelector('.register-footer-bar');
        if (!footer) return;
        var support = footer.querySelector('.register-support-btn');
        var actions = form.querySelector('.register-step-2 .register-actions-row, .reg-form-content .register-actions-row');
        var wallet = form.querySelector('.register-step-2 .register-walletconnect-full, .reg-form-content .register-walletconnect-full');
        if (actions && !footer.contains(actions)) {
            footer.insertBefore(actions, support || null);
        }
        if (wallet && !footer.contains(wallet)) {
            footer.insertBefore(wallet, support || null);
        }
        if (actions || wallet) {
            footer.classList.add('register-footer-bar--has-actions');
        }
    }

    function applyMobileRegisterLayoutFix(modalEl) {
        if (!modalEl || !document.body.classList.contains('mobile-site')) return;
        var run = function () {
            relocateMobileFooterActions(modalEl);
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

            var registerBody = modalEl.querySelector('.register-modal-body');
            if (registerBody) {
                registerBody.style.display = 'flex';
                registerBody.style.flexDirection = 'column';
                registerBody.style.flex = '1 1 auto';
                registerBody.style.minHeight = '0';
                registerBody.style.overflow = 'hidden';
            }

            var containers = modalEl.querySelectorAll('.e-p-content-holder-bc, .e-p-content-bc, .reg-form-block-bc, .entrance-form-bc.registration.popup');
            containers.forEach(function (el) {
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
            var scrollAreas = modalEl.querySelectorAll('.register-steps-scroll, .modal-body, .entrance-form-content-bc.single-side.step-0');
            scrollAreas.forEach(function (area) {
                area.scrollTop = 0;
            });
        };
        requestAnimationFrame(function () {
            run();
            requestAnimationFrame(run);
        });
    }

    function showModalByElement(modalEl) {
        var jq = getJq();
        if (!modalEl) return;
        if (jq && jq.fn && typeof jq.fn.modal === 'function') {
            jq(modalEl).modal('show');
            if (modalEl.id === 'registerModal') {
                document.body.classList.add('register-modal-open');
                document.body.classList.remove('login-modal-open');
            }
            return;
        }
        if (typeof window.showModalById === 'function' && modalEl && modalEl.id) {
            window.showModalById(modalEl.id);
            if (modalEl.id === 'registerModal') {
                document.body.classList.add('register-modal-open');
                document.body.classList.remove('login-modal-open');
            }
            return;
        }
        modalEl.classList.add('show');
        modalEl.style.display = 'block';
        modalEl.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        document.body.style.overflow = 'hidden';
        if (modalEl.id === 'registerModal') {
            document.body.classList.add('register-modal-open');
            document.body.classList.remove('login-modal-open');
        }
        applyMobileRegisterLayoutFix(modalEl);
    }

    function hideModalByElement(modalEl) {
        var jq = getJq();
        if (!modalEl) return;
        if (jq && jq.fn && typeof jq.fn.modal === 'function') {
            jq(modalEl).modal('hide');
            if (modalEl.id === 'registerModal') {
                document.body.classList.remove('register-modal-open');
            }
            return;
        }
        if (typeof window.hideModalById === 'function' && modalEl && modalEl.id) {
            window.hideModalById(modalEl.id);
            if (modalEl.id === 'registerModal') {
                document.body.classList.remove('register-modal-open');
            }
            return;
        }
        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        modalEl.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        if (modalEl.id === 'registerModal') {
            document.body.classList.remove('register-modal-open');
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

    function applyMobileRegisterHeaderFix(registerModal) {
        if (!registerModal || !document.body || !document.body.classList.contains('mobile-site')) return;

        var topBar = registerModal.querySelector('.register-modal-top-bar');
        var logo = registerModal.querySelector('.register-logo');
        var logoImg = registerModal.querySelector('.register-logo-img');
        var topRight = registerModal.querySelector('.register-top-right');
        var loginBtn = registerModal.querySelector('.register-modal-login-link');
        var closeBtn = registerModal.querySelector('.register-modal-close');
        var closeIcon = closeBtn ? closeBtn.querySelector('span') : null;

        if (topBar) {
            topBar.style.display = 'flex';
            topBar.style.alignItems = 'center';
            topBar.style.justifyContent = 'space-between';
            topBar.style.gap = '8px';
            topBar.style.width = '100%';
        }

        if (logo) {
            logo.style.display = 'flex';
            logo.style.alignItems = 'center';
            logo.style.flex = '1 1 auto';
            logo.style.minWidth = '0';
        }

        if (logoImg) {
            logoImg.style.display = 'block';
            logoImg.style.width = 'auto';
            logoImg.style.height = '28px';
            logoImg.style.maxWidth = '122px';
            logoImg.style.objectFit = 'contain';
        }

        if (topRight) {
            topRight.style.display = 'flex';
            topRight.style.alignItems = 'center';
            topRight.style.justifyContent = 'flex-end';
            topRight.style.gap = '8px';
            topRight.style.flex = '0 0 auto';
        }

        if (loginBtn) {
            loginBtn.style.display = 'inline-flex';
            loginBtn.style.alignItems = 'center';
            loginBtn.style.justifyContent = 'center';
            loginBtn.style.minWidth = '56px';
            loginBtn.style.height = '32px';
            loginBtn.style.padding = '0 12px';
            loginBtn.style.borderRadius = '8px';
            loginBtn.style.background = 'rgba(255, 255, 255, 0.04)';
            loginBtn.style.border = '1px solid rgba(255, 255, 255, 0.16)';
            loginBtn.style.color = '#fff';
            loginBtn.style.fontSize = '12px';
            loginBtn.style.lineHeight = '1';
            loginBtn.style.fontWeight = '700';
            loginBtn.style.textTransform = 'uppercase';
            loginBtn.style.boxShadow = '0 0 0 1px rgba(255, 255, 255, 0.03) inset';
        }

        if (closeBtn) {
            closeBtn.style.display = 'inline-flex';
            closeBtn.style.alignItems = 'center';
            closeBtn.style.justifyContent = 'center';
            closeBtn.style.width = '36px';
            closeBtn.style.height = '36px';
            closeBtn.style.minWidth = '36px';
            closeBtn.style.minHeight = '36px';
            closeBtn.style.padding = '0';
            closeBtn.style.borderRadius = '50%';
            closeBtn.style.border = '1px solid rgba(255, 255, 255, 0.18)';
            closeBtn.style.background = 'linear-gradient(180deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.06))';
            closeBtn.style.color = '#fff';
            closeBtn.style.fontSize = '28px';
            closeBtn.style.lineHeight = '1';
            closeBtn.style.opacity = '1';
            closeBtn.style.boxShadow = '0 6px 14px rgba(0, 0, 0, 0.24), inset 0 1px 0 rgba(255, 255, 255, 0.08)';
        }

        if (closeIcon) {
            closeIcon.style.fontSize = '30px';
            closeIcon.style.lineHeight = '1';
            closeIcon.style.transform = 'translateY(-1px)';
        }

        window.__registerHeaderFixApplied = true;
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
            jq(registerModal).on('shown.bs.modal', function () {
                applyMobileRegisterLayoutFix(registerModal);
                applyMobileRegisterHeaderFix(registerModal);
            });
            jq(registerModal).on('hidden.bs.modal', resetRegisterForm);
        } else {
            applyMobileRegisterHeaderFix(registerModal);
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
            var isCountrySelect = wrapper.hasAttribute('data-country-select');
            var isPhoneCodeSelect = wrapper.hasAttribute('data-phone-code-select');
            var searchInput = null;
            if (isCountrySelect) {
                searchInput = panel.querySelector('.bc-country-search-input');
            } else if (isPhoneCodeSelect) {
                searchInput = panel.querySelector('.bc-phone-search-input');
            }
            if (!select || !trigger || !valueEl || !panel) return;

            function escapeHtml(str) {
                return String(str || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function getPlaceholder() {
                var first = select.querySelector('option[disabled]');
                return first ? first.textContent : 'Seçin';
            }

            function updateValueDisplay() {
                var val = select.value;
                var opt = select.querySelector('option[value="' + val + '"]');
                if (!val || !opt) {
                    valueEl.textContent = getPlaceholder();
                    return;
                }
                if (isCountrySelect) {
                    var label = opt.getAttribute('data-label') || opt.textContent || val;
                    var flagUrl = opt.getAttribute('data-flag-url') || '';
                    if (flagUrl) {
                        valueEl.innerHTML = '<span class="bc-option-flag"><img src="' + escapeHtml(flagUrl) + '" alt="" loading="lazy"></span><span class="bc-option-label">' + escapeHtml(label) + '</span>';
                        return;
                    }
                    valueEl.textContent = label;
                    return;
                }
                valueEl.textContent = opt.textContent;
            }

            function close() {
                wrapper.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
                panel.setAttribute('hidden', '');
                panel.style.left = '';
                panel.style.right = '';
                panel.style.width = '';
            }

            function applyOptionFilter(query) {
                if (!isCountrySelect && !isPhoneCodeSelect) return;
                var q = String(query || '').toLowerCase().trim();
                panel.querySelectorAll('.bc-custom-select__option').forEach(function (opt) {
                    var txt = (opt.textContent || '').toLowerCase();
                    opt.style.display = (!q || txt.indexOf(q) !== -1) ? '' : 'none';
                });
            }

            function open() {
                var triggerRect = trigger.getBoundingClientRect();
                var wrapperRect = wrapper.getBoundingClientRect();
                var relativeLeft = Math.max(0, triggerRect.left - wrapperRect.left);
                panel.style.left = relativeLeft + 'px';
                panel.style.right = 'auto';
                panel.style.width = triggerRect.width + 'px';
                panel.removeAttribute('hidden');
                wrapper.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');

                if ((isCountrySelect || isPhoneCodeSelect) && searchInput) {
                    searchInput.value = '';
                    applyOptionFilter('');
                    setTimeout(function () {
                        try {
                            searchInput.focus();
                        } catch (e) {}
                    }, 0);
                }
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

            // Dinamik eklenen option'larda da secim calissin (event delegation).
            panel.addEventListener('click', function (e) {
                var target = e.target && e.target.closest ? e.target.closest('.bc-custom-select__option') : null;
                if (!target || !panel.contains(target)) return;
                e.preventDefault();
                var val = target.getAttribute('data-value');
                if (val) {
                    select.value = val;
                    updateValueDisplay();
                    close();
                }
            });

            document.addEventListener('click', function (e) {
                if (wrapper.classList.contains('is-open') && !wrapper.contains(e.target)) {
                    close();
                }
            });

            if ((isCountrySelect || isPhoneCodeSelect) && searchInput) {
                searchInput.addEventListener('input', function () {
                    applyOptionFilter(searchInput.value || '');
                });
                searchInput.addEventListener('click', function (e) {
                    e.stopPropagation();
                });
                searchInput.addEventListener('keydown', function (e) {
                    e.stopPropagation();
                });
            }

            updateValueDisplay();
        });
    }

    function initCountrySelectOptions() {
        var wrapper = document.querySelector('.bc-custom-select[data-country-select]');
        if (!wrapper) return;
        var select = wrapper.querySelector('#modal_country.bc-custom-select__native');
        var panel = wrapper.querySelector('#modal_country_listbox.bc-custom-select__panel');
        if (!select || !panel) return;

        function ensureSearchBox() {
            var existing = panel.querySelector('.bc-custom-select__search');
            if (existing) return;
            var searchWrap = document.createElement('div');
            searchWrap.className = 'bc-custom-select__search';
            var searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.className = 'bc-country-search-input';
            searchInput.placeholder = 'Ulke ara...';
            searchInput.autocomplete = 'off';
            searchWrap.appendChild(searchInput);
            panel.insertBefore(searchWrap, panel.firstChild || null);
        }

        // Tekrar init durumunda listeyi yeniden basmamak icin.
        if (select.querySelector('option[value="TR"]')) {
            ensureSearchBox();
            return;
        }

        ensureSearchBox();

        var countryCodes = [
            'AF','AX','AL','DZ','AS','AD','AO','AI','AQ','AG','AR','AM','AW','AU','AT','AZ',
            'BS','BH','BD','BB','BY','BE','BZ','BJ','BM','BT','BO','BQ','BA','BW','BV','BR','IO','BN','BG','BF','BI',
            'CV','KH','CM','CA','KY','CF','TD','CL','CN','CX','CC','CO','KM','CG','CD','CK','CR','CI','HR','CU','CW','CY','CZ',
            'DK','DJ','DM','DO',
            'EC','EG','SV','GQ','ER','EE','SZ','ET',
            'FK','FO','FJ','FI','FR','GF','PF','TF',
            'GA','GM','GE','DE','GH','GI','GR','GL','GD','GP','GU','GT','GG','GN','GW','GY',
            'HT','HM','VA','HN','HK','HU',
            'IS','IN','ID','IR','IQ','IE','IM','IL','IT',
            'JM','JP','JE','JO',
            'KZ','KE','KI','KP','KR','KW','KG',
            'LA','LV','LB','LS','LR','LY','LI','LT','LU',
            'MO','MG','MW','MY','MV','ML','MT','MH','MQ','MR','MU','YT','MX','FM','MD','MC','MN','ME','MS','MA','MZ','MM',
            'NA','NR','NP','NL','NC','NZ','NI','NE','NG','NU','NF','MK','MP','NO',
            'OM',
            'PK','PW','PS','PA','PG','PY','PE','PH','PN','PL','PT','PR',
            'QA',
            'RE','RO','RU','RW',
            'BL','SH','KN','LC','MF','PM','VC','WS','SM','ST','SA','SN','RS','SC','SL','SG','SX','SK','SI','SB','SO','ZA','GS','SS','ES','LK','SD','SR','SJ','SE','CH','SY',
            'TW','TJ','TZ','TH','TL','TG','TK','TO','TT','TN','TR','TM','TC','TV',
            'UG','UA','AE','GB','US','UM','UY','UZ',
            'VU','VE','VN','VG','VI',
            'WF','EH',
            'YE',
            'ZM','ZW'
        ];

        function codeToDisplayName(code) {
            try {
                if (typeof Intl !== 'undefined' && typeof Intl.DisplayNames === 'function') {
                    var dn = new Intl.DisplayNames(['en'], { type: 'region' });
                    return dn.of(code) || code;
                }
            } catch (e) {}
            return code;
        }

        function toAsciiLabel(str) {
            return String(str || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^\x20-\x7E]/g, '');
        }

        countryCodes.forEach(function (code) {
            var codeLc = code.toLowerCase();
            var name = toAsciiLabel(codeToDisplayName(code));
            var label = code + ' - ' + name;
            var flagUrl = 'https://flagcdn.com/24x18/' + codeLc + '.png';

            var opt = document.createElement('option');
            opt.value = code;
            opt.textContent = label;
            opt.setAttribute('data-label', label);
            opt.setAttribute('data-flag-url', flagUrl);
            select.appendChild(opt);

            var panelOpt = document.createElement('div');
            panelOpt.className = 'bc-custom-select__option';
            panelOpt.setAttribute('data-value', code);
            panelOpt.setAttribute('role', 'option');
            panelOpt.innerHTML = '<span class="bc-option-flag"><img src="' + flagUrl + '" alt="" loading="lazy"></span><span class="bc-option-label">' + label + '</span>';
            panel.appendChild(panelOpt);
        });

        // Varsayilan secim: Turkiye
        select.value = 'TR';
        var valueEl = wrapper.querySelector('.bc-custom-select__value');
        var selectedOpt = select.options[select.selectedIndex];
        if (valueEl && selectedOpt) {
            valueEl.textContent = selectedOpt.textContent;
        }
    }

    function resetCustomSelectsDisplay() {
        document.querySelectorAll('.bc-custom-select[data-bc-custom-select]').forEach(function (wrapper) {
            var select = wrapper.querySelector('.bc-custom-select__native');
            var valueEl = wrapper.querySelector('.bc-custom-select__value');
            if (!select || !valueEl) return;
            var first = select.querySelector('option[disabled]');
            if (first) {
                valueEl.textContent = first.textContent;
                return;
            }
            var selected = select.options[select.selectedIndex] || select.options[0] || null;
            valueEl.textContent = selected ? selected.textContent : 'Secin';
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
        var alertContainer = form.querySelector('.register-submit-alert');

        function isMobileSite() {
            return !!(document.body && document.body.classList.contains('mobile-site'));
        }

        function ensureAlertContainer() {
            if (alertContainer) return alertContainer;
            var targetParent = form.querySelector('.register-steps-scroll') || form;
            var el = document.createElement('div');
            el.className = 'login-error-box register-submit-alert d-none';
            el.setAttribute('role', 'alert');
            targetParent.insertBefore(el, targetParent.firstChild || null);
            alertContainer = el;
            return alertContainer;
        }

        function showRegisterError(msg) {
            var box = ensureAlertContainer();
            if (!box) return;
            box.textContent = msg || 'Bir hata olustu.';
            box.classList.remove('d-none');
        }

        function hideRegisterError() {
            var box = ensureAlertContainer();
            if (!box) return;
            box.textContent = '';
            box.classList.add('d-none');
        }

        function toast(type, msg, title) {
            if (window.MaltabetToast) {
                MaltabetToast.show(msg, { type: type || 'info', title: title });
            }
        }

        function notify(type, msg, title) {
            if (isMobileSite()) {
                return;
            }
            toast(type, msg, title);
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            hideRegisterError();
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
                        hideRegisterError();
                        notify('success', data.message || 'Kayıt başarılı.', 'Kayıt');
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
                    showRegisterError(msg);
                    notify('error', msg, 'Kayıt');
                })
                .catch(function (err) {
                    if (Shared.setSubmitButtonLoading) {
                        Shared.setSubmitButtonLoading(submitBtn, false);
                    } else if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                    var conn = Shared.MSG_CONN || 'Bağlantı hatası. Lütfen tekrar deneyin.';
                    showRegisterError(conn);
                    notify('error', conn, 'Kayıt');
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
        initCountrySelectOptions();
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
        var icon = e.target && e.target.closest ? e.target.closest('.toggle-password') : null;
        if (!icon) return;
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

