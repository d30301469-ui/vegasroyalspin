<?php
$resetToken = isset($resetToken) ? (string) $resetToken : '';
$hasToken = $resetToken !== '';

global $siteBranding, $ayar;
$resetAuthSliderItems = class_exists('ApiAuthSliders') ? ApiAuthSliders::fetchFor('login') : [];
$resetAuthSliderClass = $resetAuthSliderItems !== [] ? ' has-auth-slider' : '';
$resetBranding = is_array($siteBranding ?? null) ? $siteBranding : [];
$resetSiteName = (string) ($resetBranding['site_name'] ?? $ayar['site_adi'] ?? 'MaltaBet');
$resetLogoUrl = (string) ($resetBranding['logo_url'] ?? $ayar['logo_url'] ?? '/assets/images/MaltaBetLogo.png');
if (class_exists('ApiMediaUrl', false)) {
    $resetLogoUrl = ApiMediaUrl::resolve($resetLogoUrl);
}
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$resetCssPath = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/assets/css/reset-password.css';
$resetCssVer = is_file($resetCssPath) ? (string) filemtime($resetCssPath) : (string) time();
?>
<link rel="stylesheet" href="/assets/css/reset-password.css?v=<?= $h($resetCssVer) ?>">
<section class="mainWrap reset-password-shell">
    <div class="modal show d-block" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordTitle" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content entrance-popup-bc sign-in<?= $h($resetAuthSliderClass) ?>">
                <i class="e-p-close-icon-bc bc-i-close-remove" id="resetPasswordClose" role="button" tabindex="0" aria-label="Kapat"></i>
                <div class="e-p-content-holder-bc">
                    <div class="e-p-content-bc">
                        <div class="reset-password-hero">
                            <?php
                            $authSliderScreen = 'login';
                            $authSliderItems = $resetAuthSliderItems;
                            include VIEW_PATH . '/partials/auth-slider-bg.php';
                            unset($authSliderScreen, $authSliderItems);
                            ?>
                            <div class="e-p-header-bc">
                                <a class="popup-t-logo-w-bc" href="/">
                                    <img class="hdr-logo-bc" src="<?= $h($resetLogoUrl) ?>" alt="<?= $h($resetSiteName) ?>">
                                </a>
                                <div class="e-p-sections-bc">
                                    <div class="e-p-section-item-bc">
                                        <span class="e-p-section-title-bc">GİRİŞ</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="e-p-body-bc">
                            <?php if (!$hasToken): ?>
                                <form method="post" action="#" novalidate class="entrance-form-bc login popup" id="resetPasswordRequestForm">
                                    <div class="sg-n-text-row-2-bc" id="resetPasswordTitle">Şifreyi Sıfırla</div>
                                    <div class="entrance-f-item-bc">
                                        <div class="form-control-bc default">
                                            <label class="form-control-label-bc inputs">
                                                <input type="email" class="form-control-input-bc" autocomplete="username" name="email" id="resetPasswordEmail" step="0" value="" required>
                                                <i class="form-control-input-stroke-bc"></i>
                                                <span class="form-control-title-bc ellipsis">E-posta *</span>
                                            </label>
                                        </div>
                                        <div class="login-error-text" data-error-for="email">Bu alan gerekli</div>
                                    </div>
                                    <div class="login-error-box login-ajax-alert d-none" id="resetPasswordRequestAlert" role="alert"></div>
                                    <div class="login-success-box d-none" id="resetPasswordRequestSuccess" role="status"></div>
                                    <div class="entrance-form-actions-holder-bc login-ext-1">
                                        <div class="entrance-form-action-item-bc right">
                                            <button class="btn a-color" type="submit" title="Sıfırla" id="resetPasswordRequestSubmit">
                                                <span>Sıfırla</span>
                                            </button>
                                        </div>
                                        <div class="reset-tooltip-info">
                                            <i class="bc-i-player-info"></i>
                                            <span class="reset-tooltip-content">Şifrenizi sıfırlamak için kayıtlı e-posta adresinizi giriniz.</span>
                                        </div>
                                    </div>
                                </form>
                            <?php else: ?>
                                <form method="post" action="#" novalidate class="entrance-form-bc login popup" id="resetPasswordForm">
                                    <input type="hidden" id="resetPasswordToken" value="<?= $h($resetToken) ?>">
                                    <div class="sg-n-text-row-2-bc" id="resetPasswordTitle">Yeni Şifre Oluştur</div>
                                    <div class="entrance-f-item-bc">
                                        <div class="form-control-bc default">
                                            <label class="form-control-label-bc inputs">
                                                <input type="password" class="form-control-input-bc password-input" name="password" id="resetPasswordNew" required autocomplete="new-password" minlength="1">
                                                <i class="form-control-input-stroke-bc"></i>
                                                <span class="form-control-title-bc ellipsis">Yeni şifre *</span>
                                            </label>
                                        </div>
                                        <div class="login-error-text" data-error-for="password">Bu alan gerekli</div>
                                    </div>
                                    <div class="entrance-f-item-bc">
                                        <div class="form-control-bc default">
                                            <label class="form-control-label-bc inputs">
                                                <input type="password" class="form-control-input-bc password-input" name="password_confirmation" id="resetPasswordConfirm" required autocomplete="new-password" minlength="1">
                                                <i class="form-control-input-stroke-bc"></i>
                                                <span class="form-control-title-bc ellipsis">Yeni şifre tekrarı *</span>
                                            </label>
                                        </div>
                                        <div class="login-error-text" data-error-for="password_confirmation">Bu alan gerekli</div>
                                    </div>
                                    <div class="login-error-box login-ajax-alert d-none" id="resetPasswordAjaxAlert" role="alert"></div>
                                    <div class="login-success-box d-none" id="resetPasswordSuccess" role="status"></div>
                                    <div class="entrance-form-actions-holder-bc login-ext-1">
                                        <div class="entrance-form-action-item-bc right">
                                            <button class="btn a-color" type="submit" title="Sıfırla" id="resetPasswordSubmit">
                                                <span>Sıfırla</span>
                                            </button>
                                        </div>
                                        <div class="reset-tooltip-info">
                                            <i class="bc-i-player-info"></i>
                                            <span class="reset-tooltip-content">Yeni şifrenizi girin ve onaylayın.</span>
                                        </div>
                                    </div>
                                </form>
                            <?php endif; ?>
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
        closeBtn.addEventListener('click', closeResetModal);
        closeBtn.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                closeResetModal();
            }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeResetModal();
        }
    });
});
</script>
