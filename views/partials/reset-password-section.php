<?php
$resetToken = isset($resetToken) ? (string) $resetToken : '';
$hasToken = $resetToken !== '';
?>
<section class="mainWrap reset-password-page py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="reset-password-card login-modal-container">
                    <h1 class="login-main-title reset-password-title">Yeni şifre belirleyin</h1>
                    <p class="login-forgot-hint reset-password-lead">E-postadaki bağlantıdaki anahtar ile şifrenizi sıfırlayın. Anahtar adres çubuğunda (?token=) gelmiş olmalıdır.</p>

                    <div class="login-error-box<?= $hasToken ? ' d-none' : '' ?>" id="resetPasswordMissingToken" role="alert">
                        <?= $hasToken ? '' : 'Geçersiz veya eksik bağlantı. E-postanızdaki şifre sıfırlama linkini kullanın veya yeni istek gönderin.' ?>
                    </div>

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
