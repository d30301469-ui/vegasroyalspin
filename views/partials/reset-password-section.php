<?php
$resetToken = isset($resetToken) ? (string) $resetToken : '';
$hasToken = $resetToken !== '';
$resetBranding = (isset($siteBranding) && is_array($siteBranding)) ? $siteBranding : [];
$resetSiteName = (string) ($resetBranding['site_name'] ?? $ayar['site_adi'] ?? 'MaltaBet');
$resetLogoUrl = (string) ($resetBranding['logo_url'] ?? $ayar['logo_url'] ?? '/assets/images/MaltaBetLogo.png');
if (class_exists('ApiMediaUrl', false)) {
    $resetLogoUrl = ApiMediaUrl::resolve($resetLogoUrl);
}
?>
<div class="modal fade show" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="false" style="display: block;">
    <style>
        @media (max-width: 480px) {
            #resetPasswordModal .reset-password-back-row {
                display: none !important;
            }
        }
    </style>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content entrance-popup-bc sign-in">
            <div class="e-p-content-holder-bc">
                <div class="e-p-content-bc">
                    <div class="modal-body e-p-body-bc reset-password-modal-body">
                        <div class="login-modal-container reset-password-modal-container">
                            <div class="reset-password-topbar">
                                <a href="/" class="login-register-btn reset-password-home-link">ANASAYFA</a>
                                <button type="button" class="login-close reset-password-close e-p-close-icon-bc" aria-label="Kapat">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>

                            <div class="reset-password-modal-content">
                                <div class="login-text-block login-forgot-heading">
                                    <p class="login-top-text">Şifre sıfırlama</p>
                                    <h1 class="login-main-title reset-password-title" id="resetPasswordModalLabel">Yeni şifre belirleyin</h1>
                                    <p class="login-forgot-hint reset-password-lead">E-postadaki bağlantıdaki anahtar ile şifrenizi sıfırlayın. Anahtar adres çubuğunda (?token=) gelmiş olmalıdır.</p>
                                </div>

                                <div class="login-error-box<?= $hasToken ? ' d-none' : '' ?>" id="resetPasswordMissingToken" role="alert">
                                    <?= $hasToken ? '' : 'Geçersiz veya eksik bağlantı. E-postanızdaki şifre sıfırlama linkini kullanın veya yeni istek gönderin.' ?>
                                </div>

                                <form method="post" action="#" class="login-form<?= $hasToken ? '' : ' d-none' ?>" id="resetPasswordForm" novalidate>
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

                                    <div class="entrance-form-actions-holder-bc reg-ext-1 reset-password-actions">
                                        <button type="submit" class="login-btn" id="resetPasswordSubmit">
                                            <span class="btn-text">ŞİFREYİ GÜNCELLE</span>
                                            <span class="loading" style="display: none;"></span>
                                        </button>
                                    </div>

                                    <div class="login-forgot login-back-row reset-password-back-row">
                                        <a href="/" class="reset-password-back-link">Ana sayfaya dön</a>
                                    </div>
                                </form>
                            </div>

                            <div class="login-support reg-form-footer-bc">
                                <a href="<?= htmlspecialchars((string) ($siteContactLinks['live_support_url'] ?? (defined('LIVE_SUPPORT_URL') ? LIVE_SUPPORT_URL : '')), ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="live-chat-adviser-bc">
                                    <i class="bc-i-live-chat" aria-hidden="true"></i>
                                    <span>CANLI DESTEK İLE İLETİŞİME GEÇİN</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('resetPasswordModal');
    if (modalEl) {
        modalEl.classList.add('show');
        modalEl.style.display = 'block';
        modalEl.setAttribute('aria-hidden', 'false');
    }
    document.body.classList.add('modal-open', 'reset-password-modal-open');
    document.body.style.overflow = 'hidden';
    var header = document.querySelector('.layout-header-holder-bc');
    var nav = document.querySelector('.layout-navigation-holder-bc');
    var navScrollable = document.querySelector('.hdr-navigation-scrollable-bc-holder');
    if (header) header.style.display = 'none';
    if (nav) nav.style.display = 'none';
    if (navScrollable) navScrollable.style.display = 'none';
    var closeBtn = document.querySelector('.reset-password-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            window.location.href = '/';
        });
    }
});
</script>
