<?php
$resetToken = isset($resetToken) ? (string) $resetToken : '';
$hasToken = $resetToken !== '';

global $siteBranding, $siteContactLinks, $ayar;
$resetAuthSliderItems = class_exists('ApiAuthSliders') ? ApiAuthSliders::fetchFor('login') : [];
$resetAuthSliderClass = $resetAuthSliderItems !== [] ? ' has-auth-slider' : '';
$resetBranding = is_array($siteBranding ?? null) ? $siteBranding : [];
$resetSiteName = (string) ($resetBranding['site_name'] ?? $ayar['site_adi'] ?? 'MaltaBet');
$resetLogoUrl = (string) ($resetBranding['logo_url'] ?? $ayar['logo_url'] ?? '/assets/images/MaltaBetLogo.png');
if (class_exists('ApiMediaUrl', false)) {
    $resetLogoUrl = ApiMediaUrl::resolve($resetLogoUrl);
}
$resetSupportUrl = (string) ($siteContactLinks['live_support_url'] ?? (defined('LIVE_SUPPORT_URL') ? LIVE_SUPPORT_URL : ''));
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<style>
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
        padding: 10px 0;
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
        margin: 24px auto;
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

    #resetPasswordModal .auth-slider-bg {
        position: absolute !important;
        inset: 0 0 auto 0 !important;
        height: 320px !important;
        z-index: 0 !important;
        overflow: hidden !important;
        pointer-events: none !important;
    }

    #resetPasswordModal .auth-slider-bg__slide {
        position: absolute !important;
        inset: 0 !important;
    }

    #resetPasswordModal .auth-slider-bg__image {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        object-position: center top !important;
    }

    #resetPasswordModal .e-p-content-holder-bc {
        position: relative !important;
        z-index: 1 !important;
        display: block !important;
        padding-top: 320px !important;
        background: linear-gradient(180deg, rgba(5, 5, 20, 0) 0, rgba(5, 5, 20, 0) 320px, rgba(5, 5, 20, 0.96) 320px, rgba(5, 5, 20, 0.98) 100%) !important;
    }

    #resetPasswordModal .login-modal-header.e-p-header-bc {
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        z-index: 2 !important;
        min-height: 320px !important;
        padding: 24px 18px 0 !important;
        display: flex !important;
        align-items: flex-start !important;
        justify-content: space-between !important;
        pointer-events: none !important;
    }

    #resetPasswordModal .login-logo,
    #resetPasswordModal .login-header-actions {
        pointer-events: auto !important;
    }

    #resetPasswordModal .login-logo {
        width: 100% !important;
        justify-content: center !important;
        display: flex !important;
    }

    #resetPasswordModal .login-header-actions {
        position: absolute !important;
        top: 8px !important;
        right: 8px !important;
        gap: 0 !important;
    }

    body.mobile-site #resetPasswordModal .login-modal-header.e-p-header-bc {
        pointer-events: auto !important;
    }

    body.mobile-site #resetPasswordModal .login-header-actions {
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        pointer-events: auto !important;
    }

    body.mobile-site #resetPasswordModal .login-close {
        position: fixed !important;
        top: max(8px, env(safe-area-inset-top, 0px)) !important;
        right: 8px !important;
        width: 24px !important;
        height: 24px !important;
        min-width: 24px !important;
        min-height: 24px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 0 !important;
        background: rgba(0, 0, 0, 0.26) !important;
        border: 1px solid rgba(255, 255, 255, 0.14) !important;
        border-radius: 50% !important;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.06) !important;
        color: #fff !important;
        cursor: pointer !important;
        pointer-events: auto !important;
        z-index: 100206 !important;
    }

    body.mobile-site #resetPasswordModal .login-close span {
        display: none !important;
    }

    body.mobile-site #resetPasswordModal .login-close::before {
        content: "\00d7";
        display: block;
        color: #fff;
        font-size: 18px;
        line-height: 1;
    }

    #resetPasswordModal #resetPasswordScreen {
        margin-top: 0 !important;
        width: 100% !important;
        max-width: 520px !important;
        margin-left: auto !important;
        margin-right: auto !important;
        padding-top: 14px !important;
    }

    #resetPasswordModal #resetPasswordScreen .login-forgot-heading {
        margin: 0 0 12px !important;
        padding: 12px 14px !important;
        border-radius: 10px !important;
        border: 1px solid rgba(255, 255, 255, 0.12) !important;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.055), rgba(255, 255, 255, 0.015)) !important;
    }

    #resetPasswordModal #resetPasswordScreen .login-top-text {
        margin: 0 0 4px !important;
        font-size: 12px !important;
        color: rgba(255, 255, 255, 0.76) !important;
        text-transform: none !important;
    }

    #resetPasswordModal #resetPasswordScreen .login-forgot-title {
        margin: 0 !important;
        font-size: 20px !important;
        line-height: 1.2 !important;
        letter-spacing: 0 !important;
        text-transform: none !important;
    }

    #resetPasswordModal #resetPasswordScreen .login-forgot-hint {
        margin: 7px 0 0 !important;
        font-size: 13px !important;
        line-height: 1.42 !important;
        color: rgba(255, 255, 255, 0.85) !important;
    }

    #resetPasswordModal #resetPasswordScreen .login-form {
        display: flex !important;
        flex-direction: column !important;
        gap: 10px !important;
    }

    #resetPasswordModal #resetPasswordScreen .form-group {
        margin-bottom: 0 !important;
    }

    #resetPasswordModal #resetPasswordScreen .form-control-label-bc,
    #resetPasswordModal #resetPasswordScreen .form-control-input-bc,
    #resetPasswordModal #resetPasswordScreen .login-btn,
    #resetPasswordModal #resetPasswordScreen #resetPasswordRequestSubmit,
    #resetPasswordModal #resetPasswordScreen #resetPasswordSubmit {
        width: 100% !important;
        max-width: 100% !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        box-sizing: border-box !important;
    }

    #resetPasswordModal #resetPasswordScreen .login-btn,
    #resetPasswordModal #resetPasswordScreen #resetPasswordRequestSubmit,
    #resetPasswordModal #resetPasswordScreen #resetPasswordSubmit {
        height: 36px !important;
        min-height: 36px !important;
        font-size: 12px !important;
    }

    #resetPasswordModal #resetPasswordScreen .login-error-text {
        margin-top: -2px;
    }

    #resetPasswordModal #resetPasswordScreen .reset-password-note {
        display: flex;
        align-items: flex-start;
        gap: 6px;
        color: rgba(216, 216, 232, 0.72);
        font-size: 12px;
        line-height: 1.4;
    }

    #resetPasswordModal #resetPasswordScreen .reset-password-note i {
        margin-top: 1px;
    }

    #resetPasswordModal #resetPasswordScreen .reset-password-note a {
        color: rgba(216, 216, 232, 0.72);
        text-decoration: none;
    }

    #resetPasswordModal #resetPasswordScreen:not(.d-none) + .login-support {
        display: none !important;
    }

    @media (max-width: 480px) {
        .reset-password-shell {
            min-height: 100dvh;
            padding: 6px 0;
        }

        #resetPasswordModal .auth-slider-bg {
            height: 320px !important;
        }

        #resetPasswordModal .e-p-content-holder-bc {
            padding-top: 320px !important;
        }

        .reset-password-shell .modal-body {
            padding: 16px 16px 20px;
        }

        body.mobile-site #resetPasswordModal .login-btn {
            width: calc(100% - 14px) !important;
            margin-left: 7px !important;
            margin-right: 7px !important;
        }

        body.mobile-site #resetPasswordModal #resetPasswordScreen {
            width: calc(100% - 14px) !important;
            margin: 0 7px !important;
        }

        body.mobile-site #resetPasswordModal #resetPasswordScreen .login-forgot-heading {
            padding: 10px 11px !important;
            border-radius: 9px !important;
        }
    }
</style>
<section class="mainWrap reset-password-shell">
    <div class="modal show d-block" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordTitle" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content entrance-popup-bc sign-in<?= $h($resetAuthSliderClass) ?>">
                <?php
                $authSliderScreen = 'login';
                $authSliderItems = $resetAuthSliderItems;
                include VIEW_PATH . '/partials/auth-slider-bg.php';
                unset($authSliderScreen, $authSliderItems);
                ?>
                <div class="e-p-content-holder-bc">
                    <div class="e-p-content-bc">
                        <div class="modal-body e-p-body-bc">
                            <div class="login-modal-container reset-password-modal">
                                <div class="login-modal-header e-p-header-bc">
                                    <div class="login-logo">
                                        <img src="<?= $h($resetLogoUrl) ?>" alt="<?= $h($resetSiteName) ?>" class="login-logo-img">
                                    </div>
                                    <div class="login-header-actions e-p-sections-bc">
                                        <button type="button" class="login-close e-p-close-icon-bc" id="resetPasswordClose" aria-label="Kapat">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                </div>

                                <div id="resetPasswordScreen">
                                    <?php if (!$hasToken): ?>
                                        <div class="login-text-block login-forgot-heading">
                                            <p class="login-top-text">Şifrenizi mi unuttunuz?</p>
                                            <h1 class="login-main-title login-forgot-title" id="resetPasswordTitle"><?= $h('E-posta doğrulama') ?></h1>
                                            <p class="login-forgot-hint"><?= $h('E-posta adresinizi girin. Hesabınız varsa doğrulama kodunu hemen gönderelim.') ?></p>
                                        </div>
                                        <form method="post" action="#" novalidate class="login-form" id="resetPasswordRequestForm">
                                            <div class="form-group entrance-f-item-bc">
                                                <label class="form-control-label-bc inputs">
                                                    <input type="email" class="form-control-input-bc" name="email" id="resetPasswordEmail" value="" required autocomplete="email">
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
                                            <div class="reset-password-note">
                                                <i class="bc-i-player-info" aria-hidden="true"></i>
                                                <a href="#"><?= $h('Şifrenizi sıfırlamak için kayıtlı e-posta adresinizi giriniz.') ?></a>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <div class="login-text-block login-forgot-heading">
                                            <p class="login-top-text">Yeni şifrenizi belirleyin</p>
                                            <h1 class="login-main-title login-forgot-title" id="resetPasswordTitle"><?= $h('Yeni şifre oluştur') ?></h1>
                                            <p class="login-forgot-hint"><?= $h('Yeni şifrenizi girin ve onaylayın.') ?></p>
                                        </div>
                                        <form method="post" action="#" novalidate class="login-form" id="resetPasswordForm">
                                            <input type="hidden" id="resetPasswordToken" value="<?= $h($resetToken) ?>">
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
                                    <?php endif; ?>
                                </div>

                                <div class="login-support reg-form-footer-bc">
                                    <a href="<?= $h($resetSupportUrl) ?>" target="_blank" class="live-chat-adviser-bc">
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
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.body.classList.add('reset-password-standalone');

    var noteLink = document.querySelector('#resetPasswordScreen .reset-password-note a');
    if (noteLink) {
        noteLink.addEventListener('click', function (e) {
            e.preventDefault();
        });
    }

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
