<?php
$resetToken = isset($resetToken) ? (string) $resetToken : '';
$hasToken = $resetToken !== '';
?>
<style>
    .reset-password-shell,
    .reset-password-shell * {
        background-image: none !important;
    }

    body.reset-password-standalone header,
    body.reset-password-standalone footer,
    body.reset-password-standalone .layout-header-holder-bc,
    body.reset-password-standalone .layout-navigation-holder-bc,
    body.reset-password-standalone .hdr-navigation-scrollable-bc-holder,
    body.reset-password-standalone .layout-footer-holder-bc {
        display: none !important;
    }

    .reset-password-shell {
        position: relative;
        min-height: 100vh;
        min-height: 100dvh;
        padding: 20px 12px;
        background: rgba(7, 9, 25, 0.98) !important;
    }

    .reset-password-shell::before {
        content: "";
        position: fixed;
        inset: 0;
        background: rgba(4, 6, 18, 0.72);
        backdrop-filter: blur(3px);
        z-index: 0;
    }

    .reset-password-shell .modal {
        display: block;
        position: relative;
        z-index: 1;
        background: transparent;
        overflow: visible;
    }

    .reset-password-shell .modal-dialog {
        max-width: 420px;
        margin: 16px auto;
    }

    .reset-password-shell .modal-content {
        background: #0a0f3c;
        border-radius: 0;
        border: 1px solid rgba(255, 255, 255, 0.08);
        color: var(--white-color);
        box-shadow: 0 18px 40px rgba(0, 0, 0, 0.6);
        overflow: hidden;
    }

    .reset-password-shell .modal-body {
        padding: 0;
    }

    .reset-password-modal {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 0;
        background: linear-gradient(145deg, #1b0c49 0%, #0a0f3c 60%, #09123f 100%);
        min-height: 88vh;
    }

    .reset-password-close {
        position: absolute;
        top: 8px;
        right: 8px;
        width: 28px;
        height: 28px;
        min-width: 28px;
        min-height: 28px;
        border-radius: 0;
        border: 0;
        background-color: transparent;
        color: rgba(255, 255, 255, 0.92);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 3;
        transition: background-color 120ms ease, border-color 120ms ease;
    }

    .reset-password-close:hover {
        background-color: rgba(255, 255, 255, 0.08);
    }

    .reset-password-close::before {
        content: "\00d7";
        font-size: 18px;
        line-height: 1;
    }

    .reset-password-hero {
        position: relative;
        height: 286px;
        background: #15063f;
        border-bottom: 5px solid #ff00ff;
        overflow: hidden;
    }

    .reset-password-hero::before {
        content: "";
        position: absolute;
        inset: 0;
        background-image: url('/assets/images/login-bg.png');
        background-size: cover;
        background-position: center top;
        filter: saturate(1.05) contrast(1.06);
        opacity: 0.96;
    }

    .reset-password-brand {
        position: absolute;
        top: 22px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 2;
        font-size: 34px;
        line-height: 1;
        color: #fff;
        opacity: 0.95;
        font-weight: 700;
    }

    .reset-password-content {
        padding: 16px 14px 18px;
    }

    .reset-password-title {
        margin: 0 0 6px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        font-size: 15px;
        line-height: 1.2;
        color: rgba(255, 255, 255, 0.92);
    }

    .reset-password-subtitle {
        display: none;
    }

    .reset-password-lead {
        margin: 8px 0 0;
        font-size: 14px;
        line-height: 1.45;
        color: rgba(234, 231, 255, 0.78);
        max-width: none;
    }

    .reset-password-modal .login-form {
        margin-top: 12px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .reset-password-modal .form-group {
        margin-bottom: 0;
    }

    .reset-password-modal .form-control-label-bc {
        border-radius: 8px;
    }

    #resetPasswordModal .form-control-input-bc {
        height: 50px !important;
        min-height: 50px !important;
        border-radius: 4px !important;
        border: 1px solid rgba(236, 70, 170, 0.9) !important;
        background: rgba(83, 67, 122, 0.42) !important;
        color: #f7f4ff !important;
        padding-left: 12px !important;
    }

    #resetPasswordModal .form-control-input-bc:focus {
        border-color: rgba(236, 70, 170, 1) !important;
        box-shadow: none !important;
    }

    #resetPasswordModal .login-btn,
    #resetPasswordRequestSubmit,
    #resetPasswordSubmit {
        height: 48px !important;
        min-height: 48px !important;
        border-radius: 4px !important;
        background: rgba(111, 122, 176, 0.24) !important;
        border: 1px solid rgba(146, 156, 201, 0.16) !important;
        color: rgba(210, 214, 235, 0.8) !important;
        box-shadow: none !important;
        font-size: 28px !important;
        font-weight: 700 !important;
        letter-spacing: 0.02em !important;
        text-transform: uppercase !important;
    }

    #resetPasswordModal .login-btn:hover,
    #resetPasswordRequestSubmit:hover,
    #resetPasswordSubmit:hover {
        background: rgba(111, 122, 176, 0.3) !important;
        border-color: rgba(146, 156, 201, 0.24) !important;
        color: rgba(228, 232, 247, 0.88) !important;
        transform: none !important;
    }

    #resetPasswordModal .login-error-text {
        margin-top: -2px;
        border-radius: 3px;
        background: rgba(107, 26, 74, 0.8);
        color: #fff;
        font-size: 13px;
        padding: 3px 9px;
    }

    .reset-password-actions {
        margin-top: 10px;
        display: flex;
        align-items: center;
        gap: 6px;
        color: rgba(216, 216, 232, 0.72);
        font-size: 13px;
        line-height: 1.4;
    }

    .reset-password-actions::before {
        content: "i";
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 15px;
        height: 15px;
        border-radius: 50%;
        border: 1px solid rgba(216, 216, 232, 0.72);
        font-size: 11px;
        font-weight: 700;
        flex-shrink: 0;
    }

    .reset-password-actions a {
        color: rgba(216, 216, 232, 0.72);
        text-decoration: none;
        border: 0 !important;
        padding-bottom: 0;
        font-size: 13px;
    }

    .reset-password-actions a:hover {
        color: rgba(232, 232, 242, 0.9);
    }

    @media (max-width: 480px) {
        .reset-password-shell {
            min-height: 100dvh;
            padding: 6px;
        }

        .reset-password-shell .modal-dialog {
            margin: 0 auto;
        }

        .reset-password-close {
            top: 10px;
            right: 10px;
            width: 28px;
            height: 28px;
            min-width: 28px;
            min-height: 28px;
        }

        .reset-password-title {
            font-size: 14px;
        }

        .reset-password-hero {
            height: 300px;
        }

        .reset-password-content {
            padding: 12px 10px 16px;
        }

        #resetPasswordModal .form-control-input-bc,
        #resetPasswordModal .login-btn,
        #resetPasswordRequestSubmit,
        #resetPasswordSubmit {
            height: 52px !important;
            min-height: 52px !important;
        }

        #resetPasswordModal .login-btn,
        #resetPasswordRequestSubmit,
        #resetPasswordSubmit {
            font-size: 27px !important;
        }
    }
</style>
<section class="mainWrap reset-password-shell">
    <div class="modal show d-block" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordTitle" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content entrance-popup-bc sign-in">
                <div class="e-p-content-holder-bc">
                    <div class="e-p-content-bc">
                        <div class="modal-body e-p-body-bc">
                            <div class="reset-password-modal login-modal-container">
                                <div class="reset-password-hero" aria-hidden="true">
                                    <span class="reset-password-brand">Vegasroyalspin</span>
                                    <button type="button" class="reset-password-close login-close e-p-close-icon-bc" id="resetPasswordClose" aria-label="Kapat"></button>
                                </div>

                                <div class="reset-password-content">
                                    <div class="login-text-block">
                                    <p class="reset-password-subtitle">Şifre sıfırlama</p>
                                    <h1 class="login-main-title reset-password-title" id="resetPasswordTitle"><?= $hasToken ? 'Yeni şifre oluşturun' : 'ŞİFRE SIFIRLA' ?></h1>
                                </div>

                                <form method="post" action="#" class="login-form entrance-form-bc sign-in popup<?= $hasToken ? ' d-none' : '' ?>" id="resetPasswordRequestForm" novalidate>
                                    <div class="form-group entrance-f-item-bc">
                                        <label class="form-control-label-bc inputs">
                                            <input type="email" class="form-control-input-bc" name="email" id="resetPasswordEmail" required autocomplete="email">
                                            <i class="form-control-input-stroke-bc"></i>
                                            <span class="form-control-title-bc ellipsis">E-posta *</span>
                                        </label>
                                        <div class="login-error-text" data-error-for="email">Bu alan gerekli</div>
                                    </div>

                                    <div class="login-error-box login-ajax-alert d-none" id="resetPasswordRequestAlert" role="alert"></div>
                                    <div class="login-success-box d-none" id="resetPasswordRequestSuccess" role="status"></div>

                                    <button type="submit" class="login-btn" id="resetPasswordRequestSubmit">
                                        <span class="btn-text">SIFIRLA</span>
                                        <span class="loading" style="display: none;"></span>
                                    </button>
                                </form>

                                <form method="post" action="#" class="login-form entrance-form-bc sign-in popup<?= $hasToken ? '' : ' d-none' ?>" id="resetPasswordForm" novalidate>
                                    <input type="hidden" id="resetPasswordToken" value="<?= htmlspecialchars($resetToken, ENT_QUOTES, 'UTF-8') ?>">

                                    <div class="form-group entrance-f-item-bc">
                                        <label class="form-control-label-bc inputs">
                                            <input type="password" class="form-control-input-bc password-input" name="password" id="resetPasswordNew" required autocomplete="new-password" minlength="1">
                                            <i class="form-control-input-stroke-bc"></i>
                                            <span class="form-control-title-bc ellipsis">Yeni şifre *</span>
                                        </label>
                                        <div class="login-error-text" data-error-for="password">Bu alan gerekli</div>
                                    </div>

                                    <div class="form-group entrance-f-item-bc">
                                        <label class="form-control-label-bc inputs">
                                            <input type="password" class="form-control-input-bc password-input" name="password_confirmation" id="resetPasswordConfirm" required autocomplete="new-password" minlength="1">
                                            <i class="form-control-input-stroke-bc"></i>
                                            <span class="form-control-title-bc ellipsis">Yeni şifre tekrarı *</span>
                                        </label>
                                        <div class="login-error-text" data-error-for="password_confirmation">Bu alan gerekli</div>
                                    </div>

                                    <div class="login-error-box login-ajax-alert d-none" id="resetPasswordAjaxAlert" role="alert"></div>
                                    <div class="login-success-box d-none" id="resetPasswordSuccess" role="status"></div>

                                    <button type="submit" class="login-btn" id="resetPasswordSubmit">
                                        <span class="btn-text">SIFIRLA</span>
                                        <span class="loading" style="display: none;"></span>
                                    </button>
                                </form>

                                <div class="reset-password-actions">
                                    <a href="/">Şifrenizi sıfırlamak için kayıtlı e-posta adresinizi giriniz.</a>
                                </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.body.classList.add('reset-password-standalone');

    function closeResetModal() {
        if (window.history.length > 1) {
            window.history.back();
            return;
        }
        window.location.href = '/';
    }

    var closeBtn = document.getElementById('resetPasswordClose');
    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            closeResetModal();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeResetModal();
        }
    });
});
</script>
