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
        max-width: 559px;
        margin: 42px auto;
    }

    .reset-password-shell .modal-content {
        background: var(--body-bg);
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        color: var(--white-color);
        box-shadow: 0 18px 40px rgba(0, 0, 0, 0.6), 0 0 0 1px var(--primary);
        overflow: hidden;
    }

    .reset-password-shell .modal-body {
        padding: 21px 31px 26px;
    }

    .reset-password-modal {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .reset-password-close {
        position: absolute;
        top: 12px;
        right: 12px;
        width: 30px;
        height: 30px;
        min-width: 30px;
        min-height: 30px;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        background-color: rgba(255, 255, 255, 0.08);
        color: rgba(255, 255, 255, 0.92);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 3;
        transition: background-color 120ms ease, border-color 120ms ease;
    }

    .reset-password-close:hover {
        background-color: rgba(133, 15, 131, 0.22);
        border-color: rgba(255, 255, 255, 0.34);
    }

    .reset-password-close::before {
        content: "\00d7";
        font-size: 18px;
        line-height: 1;
    }

    .reset-password-brand {
        font-size: 12px;
        letter-spacing: 0.08em;
        color: rgba(255, 255, 255, 0.78);
        text-transform: uppercase;
        font-weight: 700;
    }

    .reset-password-title {
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        font-size: 20px;
        line-height: 1.2;
    }

    .reset-password-subtitle {
        margin: 0;
        font-size: 12px;
        color: #cccccc;
        line-height: 1.4;
    }

    .reset-password-lead {
        margin: 0;
        font-size: 13px;
        line-height: 1.55;
        color: rgba(243, 240, 255, 0.86);
        max-width: 44ch;
    }

    .reset-password-modal .login-form {
        margin-top: 2px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .reset-password-modal .form-group {
        margin-bottom: 0;
    }

    .reset-password-modal .form-control-label-bc {
        border-radius: 8px;
    }

    #resetPasswordModal .form-control-input-bc {
        height: 48px !important;
        min-height: 48px !important;
        border-radius: 6px !important;
        border: 1px solid rgba(172, 102, 255, 0.3) !important;
    }

    #resetPasswordModal .form-control-input-bc:focus {
        border-color: rgba(172, 102, 255, 0.45) !important;
        box-shadow: none !important;
    }

    #resetPasswordModal .login-btn,
    #resetPasswordRequestSubmit,
    #resetPasswordSubmit {
        height: 48px !important;
        min-height: 48px !important;
        border-radius: 6px !important;
        background-color: transparent !important;
        border: 1px solid rgba(255, 255, 255, 0.95) !important;
        color: #fff !important;
        box-shadow: none !important;
    }

    #resetPasswordModal .login-btn:hover,
    #resetPasswordRequestSubmit:hover,
    #resetPasswordSubmit:hover {
        background-color: rgba(255, 255, 255, 0.08) !important;
        border-color: rgba(255, 255, 255, 0.95) !important;
        color: #fff !important;
        transform: none !important;
    }

    .reset-password-actions {
        margin-top: 2px;
        text-align: center;
    }

    .reset-password-actions a {
        color: rgba(255, 255, 255, 0.9);
        text-decoration: none;
        border-bottom: 1px solid rgba(133, 15, 131, 0.5) !important;
        padding-bottom: 2px;
        font-size: 13px;
    }

    .reset-password-actions a:hover {
        color: #fff;
        border-bottom-color: rgba(133, 15, 131, 0.82);
    }

    @media (max-width: 480px) {
        .reset-password-shell {
            min-height: 100dvh;
            padding: 8px;
        }

        .reset-password-shell .modal-dialog {
            margin: 10px auto;
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
            font-size: 19px;
        }

        #resetPasswordModal .form-control-input-bc,
        #resetPasswordModal .login-btn,
        #resetPasswordRequestSubmit,
        #resetPasswordSubmit {
            height: 44px !important;
            min-height: 44px !important;
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
                                <div class="login-modal-header e-p-header-bc">
                                    <div class="login-logo">
                                        <span class="reset-password-brand">VEGASROYALSPIN</span>
                                    </div>
                                    <div class="login-header-actions e-p-sections-bc">
                                        <a href="/" class="login-register-btn e-p-section-title-bc">ANASAYFA</a>
                                        <button type="button" class="reset-password-close login-close e-p-close-icon-bc" id="resetPasswordClose" aria-label="Kapat"></button>
                                    </div>
                                </div>

                                <div class="login-text-block">
                                    <p class="reset-password-subtitle">Şifre sıfırlama</p>
                                    <h1 class="login-main-title reset-password-title" id="resetPasswordTitle"><?= $hasToken ? 'Yeni şifre oluşturun' : 'Şifrenizi sıfırlayın' ?></h1>
                                    <p class="login-forgot-hint reset-password-lead"><?= $hasToken
                                        ? 'Yeni şifrenizi girin ve onaylayın.'
                                        : 'E-posta adresinizi girin. Hesap kayıtlıysa kod gönderilir.' ?></p>
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
                                        <span class="btn-text">KOD GÖNDER</span>
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
                                        <span class="btn-text">ŞİFREYİ GÜNCELLE</span>
                                        <span class="loading" style="display: none;"></span>
                                    </button>
                                </form>

                                <div class="reset-password-actions">
                                    <a href="/">Ana sayfaya dön</a>
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
