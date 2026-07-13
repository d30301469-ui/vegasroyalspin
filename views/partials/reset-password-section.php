<?php
$resetToken = isset($resetToken) ? (string) $resetToken : '';
$hasToken = $resetToken !== '';
?>
<style>
    .reset-password-modal-page,
    .reset-password-modal-page * {
        background-image: none !important;
    }

    .reset-password-modal-page {
        position: relative;
        min-height: calc(100vh - 96px);
        min-height: calc(100dvh - 96px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }

    .reset-password-modal-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(4, 6, 18, 0.72);
        backdrop-filter: blur(2px);
    }

    .reset-password-modal {
        position: relative;
        z-index: 1;
        width: min(100%, 540px);
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.14);
        background: rgba(8, 10, 24, 0.94);
        box-shadow: 0 18px 36px rgba(0, 0, 0, 0.45);
        padding: 20px;
    }

    .reset-password-title {
        margin-bottom: 10px;
        text-transform: none;
    }

    .reset-password-lead {
        margin-bottom: 14px;
        font-size: 13px;
        line-height: 1.5;
    }

    .reset-password-actions {
        margin-top: 2px;
        text-align: center;
    }

    .reset-password-actions a {
        color: rgba(255, 255, 255, 0.88);
        text-decoration: none;
        border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        padding-bottom: 2px;
    }

    @media (max-width: 480px) {
        .reset-password-modal-page {
            min-height: calc(100dvh - 78px);
            padding: 10px;
        }

        .reset-password-modal {
            width: 100%;
            border-radius: 10px;
            padding: 14px;
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
