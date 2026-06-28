/**
 * Beni Ara sayfası — floating label ve form doğrulama (BetcoInputs kullanır)
 */
(function () {
    const onReady = (fn) => {
        document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', fn) : fn();
    };

    function initBeniAraForm() {
        const form = document.getElementById('beniAraForm');
        if (!form) return;

        const BetcoInputs = window.BetcoInputs;
        if (BetcoInputs) {
            BetcoInputs.initFloatingLabels(form);
            BetcoInputs.initRequiredValidation(form, {
                fieldNames: ['ad', 'telefon', 'neden'],
                errorTextClass: 'register-error-text'
            });
        }

        const telefonInput = form.querySelector('input[name="telefon"]');
        if (telefonInput) {
            telefonInput.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9\s+()-]/g, '');
            });
        }

        form.addEventListener('submit', function (e) {
            if (e.defaultPrevented) {
                return;
            }
            e.preventDefault();

            const interactive = document.getElementById('beniAraInteractive');
            const errEl = document.getElementById('beniAraFormError');
            const btn = form.querySelector('button[type="submit"]');
            const url = form.getAttribute('action') || '/beni-ara';

            if (errEl) {
                errEl.textContent = '';
                errEl.hidden = true;
            }

            const origLabel = btn ? btn.textContent : '';
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Gönderiliyor...';
            }

            const fd = new FormData(form);
            fetch(url, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (res) {
                    return res.json().then(function (data) {
                        return { okHttp: res.ok, data: data };
                    });
                })
                .then(function (wrapped) {
                    const data = wrapped.data || {};
                    if (data.ok) {
                        const msg =
                            typeof data.message === 'string' && data.message
                                ? data.message
                                : 'Talebiniz alındı. En kısa sürede sizinle iletişime geçeceğiz.';
                        if (interactive) {
                            var successDiv = document.createElement('div');
                            successDiv.className = 'beni-ara-alert beni-ara-alert-success';
                            successDiv.textContent = msg;
                            interactive.innerHTML = '';
                            interactive.appendChild(successDiv);
                        }
                    } else {
                        var errMsg =
                            typeof data.message === 'string' && data.message
                                ? data.message
                                : 'Talebiniz gönderilemedi.';
                        if (errEl) {
                            errEl.textContent = errMsg;
                            errEl.hidden = false;
                        }
                    }
                })
                .catch(function () {
                    if (errEl) {
                        errEl.textContent = 'Bağlantı hatası. Lütfen tekrar deneyin.';
                        errEl.hidden = false;
                    }
                })
                .finally(function () {
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = origLabel;
                    }
                });
        });
    }

    onReady(initBeniAraForm);
})();
