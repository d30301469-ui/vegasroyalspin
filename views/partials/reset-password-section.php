<?php
$resetToken = isset($resetToken) ? (string) $resetToken : '';
$hasToken = $resetToken !== '';
?>
<style>
    .reset-password-modal-page,
    .reset-password-modal-page * {
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

    .reset-password-modal-page {
        position: relative;
        min-height: 100vh;
        min-height: 100dvh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
        background-color: #070919 !important;
    }

    .reset-password-modal-backdrop {
        position: absolute;
        inset: 0;
        background-color: rgba(4, 6, 18, 0.72) !important;
        backdrop-filter: blur(3px);
    }

    .reset-password-modal {
        position: relative;
        z-index: 1;
        width: min(100%, 520px);
        border-radius: 14px;
        border: 1px solid rgba(255, 255, 255, 0.14);
        background-color: rgba(8, 10, 24, 0.95) !important;
        box-shadow:
            0 20px 48px rgba(0, 0, 0, 0.56),
            0 0 0 1px rgba(133, 15, 131, 0.28),
            inset 0 1px 0 rgba(255, 255, 255, 0.06);
        padding: 22px;
        gap: 14px;
        overflow: hidden;
    }

    .reset-password-modal::before {
        content: "";
        position: absolute;
        inset: 0;
        pointer-events: none;
        background-color: rgba(133, 15, 131, 0.04);
        opacity: 1;
    }

    .reset-password-modal::after {
        content: "";
        position: absolute;
        left: 0;
        right: 0;
        top: 0;
        height: 3px;
        background-color: var(--secondary);
        opacity: 0.85;
    }

    .reset-password-modal > * {
        position: relative;
        z-index: 1;
    }

    .reset-password-title {
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        font-size: 21px;
        line-height: 1.2;
    }

    .reset-password-lead {
        margin: 0;
        font-size: 13px;
        line-height: 1.55;
        color: rgba(243, 240, 255, 0.86);
        max-width: 46ch;
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

    .reset-password-modal .form-control-input-bc {
        height: 48px;
        min-height: 48px;
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.14);
        background-color: rgba(15, 17, 36, 0.94) !important;
        transition: border-color 120ms ease, box-shadow 120ms ease;
    }

    .reset-password-modal .form-control-input-bc:focus {
        border-color: var(--secondary);
        box-shadow: 0 0 0 3px rgba(16, 145, 33, 0.16);
    }

    .reset-password-modal .login-btn {
        min-height: 48px;
        border-radius: 8px;
        border: 1px solid var(--primary) !important;
        background-color: var(--primary) !important;
        box-shadow: 0 10px 20px rgba(133, 15, 131, 0.32);
        color: #fff !important;
        font-weight: 700;
        letter-spacing: 0.02em;
        transition: background-color 140ms ease, border-color 140ms ease, box-shadow 140ms ease, transform 120ms ease;
    }

    .reset-password-modal .login-btn:hover {
        color: #fff !important;
        border-color: var(--secondary) !important;
        background-color: var(--secondary) !important;
        box-shadow: 0 12px 24px rgba(16, 145, 33, 0.32);
        transform: translateY(-1px);
    }

    .reset-password-modal .login-btn:active {
        transform: translateY(0);
    }

    #resetPasswordRequestSubmit,
    #resetPasswordSubmit {
        background-color: var(--primary) !important;
        border-color: var(--primary) !important;
        color: #fff !important;
    }

    #resetPasswordRequestSubmit:hover,
    #resetPasswordSubmit:hover {
        background-color: var(--secondary) !important;
        border-color: var(--secondary) !important;
        color: #fff !important;
    }

    .reset-password-actions {
        margin-top: 6px;
        text-align: center;
    }

    .reset-password-actions a {
        color: rgba(255, 255, 255, 0.9);
        text-decoration: none;
        border-bottom: 1px solid rgba(16, 145, 33, 0.45) !important;
        padding-bottom: 2px;
        font-size: 13px;
    }

    .reset-password-actions a:hover {
        color: #fff;
        border-bottom-color: rgba(16, 145, 33, 0.8);
    }

    @media (max-width: 480px) {
        .reset-password-modal-page {
            min-height: 100dvh;
            padding: 10px;
        }

        .reset-password-modal {
            width: 100%;
            border-radius: 12px;
            padding: 15px;
            gap: 10px;
        }

        .reset-password-title {
            font-size: 19px;
        }

        .reset-password-modal .form-control-input-bc,
        .reset-password-modal .login-btn {
            min-height: 44px;
            height: 44px;
        }
    }
</style>
<section class="mainWrap reset-password-modal-page">
    <div class="reset-password-modal-backdrop" aria-hidden="true"></div>
    <div class="reset-password-modal login-modal-container" role="dialog" aria-modal="true" aria-label="Şifre sıfırlama penceresi">
        <h1 class="login-main-title reset-password-title"><?= $hasToken ? 'Yeni şifre belirleyin' : 'Şifre sıfırlama' ?></h1>
        <p class="login-forgot-hint reset-password-lead"><?= $hasToken
            ? 'E-postadaki bağlantı ile yeni şifrenizi belirleyin.'
            : 'Hesabınıza ait e-posta adresini girin. Kayıtlıysa doğrulama kodu veya sıfırlama bağlantısı gönderilir.' ?></p>

        <form method="post" action="#" class="login-form<?= $hasToken ? ' d-none' : '' ?>" id="resetPasswordRequestForm" novalidate>
            <div class="form-group">
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

        <form method="post" action="#" class="login-form<?= $hasToken ? '' : ' d-none' ?>" id="resetPasswordForm" novalidate>
            <input type="hidden" id="resetPasswordToken" value="<?= htmlspecialchars($resetToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">
                <label class="form-control-label-bc inputs">
                    <input type="password" class="form-control-input-bc password-input" name="password" id="resetPasswordNew" required autocomplete="new-password" minlength="1">
                    <i class="form-control-input-stroke-bc"></i>
                    <span class="form-control-title-bc ellipsis">Yeni şifre *</span>
                </label>
                <div class="login-error-text" data-error-for="password">Bu alan gerekli</div>
            </div>

            <div class="form-group">
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
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.body.classList.add('reset-password-standalone');
});
</script>
