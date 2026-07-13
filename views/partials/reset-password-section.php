<?php
$resetToken = isset($resetToken) ? (string) $resetToken : '';
$hasToken = $resetToken !== '';
?>
<style>
    .reset-password-page,
    .reset-password-page .container,
    .reset-password-page .row,
    .reset-password-page .col-12,
    .reset-password-page .reset-password-card {
        background-image: none !important;
    }

    .reset-password-page {
        min-height: calc(100vh - 120px);
        display: flex;
        align-items: center;
        padding-top: 24px;
        padding-bottom: 24px;
    }

    .reset-password-card {
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: rgba(8, 10, 24, 0.92);
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

    @media (max-width: 480px) {
        .reset-password-page {
            min-height: calc(100dvh - 96px);
            padding-top: 12px;
            padding-bottom: 12px;
        }

        .reset-password-card {
            padding: 14px;
            border-radius: 10px;
        }
    }
</style>
<section class="mainWrap reset-password-page py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="reset-password-card login-modal-container">
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

                        <div class="login-forgot login-back-row">
                            <a href="/">Ana sayfaya dön</a>
                        </div>
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

                        <div class="login-forgot login-back-row">
                            <a href="/">Ana sayfaya dön</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
