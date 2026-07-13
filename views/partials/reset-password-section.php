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
        width: min(100%, 540px);
        border-radius: 12px;
        border: 1px solid rgba(133, 15, 131, 0.62);
        background-color: rgba(8, 10, 24, 0.95) !important;
        box-shadow:
            0 18px 38px rgba(0, 0, 0, 0.52),
            0 0 0 1px rgba(133, 15, 131, 0.42),
            inset 0 1px 0 rgba(255, 255, 255, 0.06);
        padding: 20px;
        gap: 12px;
        overflow: hidden;
    }

    .reset-password-modal::before {
        content: "";
        position: absolute;
        inset: 0;
        pointer-events: none;
        background-color: rgba(133, 15, 131, 0.06);
        opacity: 1;
    }

    .reset-password-modal > * {
        position: relative;
        z-index: 1;
    }

    .reset-password-title {
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-size: 20px;
    }

    .reset-password-lead {
        margin-bottom: 12px;
        font-size: 13px;
        line-height: 1.5;
        color: rgba(243, 240, 255, 0.86);
    }

    .reset-password-modal .form-control-label-bc {
        border-radius: 8px;
    }

    .reset-password-modal .form-control-input-bc {
        height: 46px;
        min-height: 46px;
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: rgba(15, 17, 36, 0.9);
    }

    .reset-password-modal .form-control-input-bc:focus {
        border-color: var(--secondary);
        box-shadow: 0 0 0 2px rgba(16, 145, 33, 0.18);
    }

    .reset-password-modal .login-btn {
        min-height: 46px;
        border-radius: 8px;
        border: 1px solid var(--primary) !important;
        background-color: var(--primary) !important;
        box-shadow: 0 8px 18px rgba(133, 15, 131, 0.33);
        color: #fff !important;
    }

    .reset-password-modal .login-btn:hover {
        color: #fff !important;
        border-color: var(--secondary) !important;
        background-color: var(--secondary) !important;
        box-shadow: 0 10px 22px rgba(16, 145, 33, 0.32);
    }

    .reset-password-actions {
        margin-top: 4px;
        text-align: center;
    }

    .reset-password-actions a {
        color: rgba(255, 255, 255, 0.9);
        text-decoration: none;
        border-bottom: 1px solid rgba(16, 145, 33, 0.45) !important;
        padding-bottom: 2px;
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
            border-radius: 10px;
            padding: 14px;
            gap: 10px;
        }

        .reset-password-title {
            font-size: 18px;
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
