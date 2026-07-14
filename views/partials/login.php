<?php
$login_error = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : '';
if (isset($_SESSION['login_error'])) {
    unset($_SESSION['login_error']);
}
$login_next_safe = '';
if (isset($_GET['next']) && is_string($_GET['next'])) {
    $n = $_GET['next'];
    if ($n !== '' && str_starts_with($n, '/') && !str_starts_with($n, '//')) {
        $login_next_safe = $n;
    }
}
$login_account_frozen_notice = !empty($_GET['account_frozen']) && (string) $_GET['account_frozen'] === '1';
$login_password_updated_notice = !empty($_GET['password_updated']) && (string) $_GET['password_updated'] === '1';
$loginAuthSliderItems = class_exists('ApiAuthSliders') ? ApiAuthSliders::fetchFor('login') : [];
$loginAuthSliderClass = $loginAuthSliderItems !== [] ? ' has-auth-slider' : '';
$loginBranding = (isset($siteBranding) && is_array($siteBranding)) ? $siteBranding : [];
$loginSiteName = (string) ($loginBranding['site_name'] ?? $ayar['site_adi'] ?? 'MaltaBet');
$loginLogoUrl = (string) ($loginBranding['logo_url'] ?? $ayar['logo_url'] ?? '/assets/images/MaltaBetLogo.png');
if (class_exists('ApiMediaUrl', false)) {
    $loginLogoUrl = ApiMediaUrl::resolve($loginLogoUrl);
}
?>
<!-- LOGIN MODAL -->
<div class="modal fade" id="login2" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content entrance-popup-bc sign-in<?= htmlspecialchars($loginAuthSliderClass, ENT_QUOTES, 'UTF-8') ?>">
            <style>
                body.mobile-site #login2 .login-modal-header.e-p-header-bc {
                    pointer-events: auto !important;
                }

                body.mobile-site #login2 .login-header-actions {
                    display: flex !important;
                    align-items: center !important;
                    gap: 8px !important;
                    pointer-events: auto !important;
                }

                body.mobile-site #login2 .login-close {
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

                body.mobile-site #login2 .login-close span {
                    display: none !important;
                }

                body.mobile-site #login2 .login-close::before {
                    content: "\00d7";
                    display: block;
                    color: #fff;
                    font-size: 18px;
                    line-height: 1;
                }

                body.mobile-site #login2 .login-password-field {
                    position: relative;
                }

                body.mobile-site #login2 .login-password-field .login-password-toggle {
                    position: absolute;
                    right: 8px;
                    top: 50%;
                    transform: translateY(-50%);
                    z-index: 4;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 30px;
                    height: 30px;
                    padding: 0;
                    border: 1px solid rgba(255, 255, 255, 0.12);
                    border-radius: 999px;
                    background: linear-gradient(180deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.03));
                    color: rgba(236, 231, 255, 0.92);
                    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.16), inset 0 1px 0 rgba(255, 255, 255, 0.05);
                    cursor: pointer;
                }

                body.mobile-site #login2 .login-password-field .login-password-toggle:hover {
                    background: linear-gradient(180deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.05));
                    border-color: rgba(255, 255, 255, 0.18);
                }

                body.mobile-site #login2 .login-password-field .form-control-input-bc {
                    padding-right: 52px;
                }

                body.mobile-site #login2 .login-password-field .login-password-toggle-icon,
                body.mobile-site #login2 .login-password-field .login-password-toggle-icon svg {
                    width: 14px;
                    height: 14px;
                }

                body.mobile-site #login2 .login-password-field .login-password-toggle-icon svg {
                    width: 14px !important;
                    height: 14px !important;
                    fill: none;
                    stroke: currentColor;
                    stroke-width: 1.8;
                    stroke-linecap: round;
                    stroke-linejoin: round;
                }

                body.mobile-site #login2 .login-btn,
                body.mobile-site #login2 .login-walletconnect-btn,
                body.mobile-site #login2 .login-or-separator {
                    width: calc(100% - 14px) !important;
                    margin-left: 7px !important;
                    margin-right: 7px !important;
                    box-sizing: border-box !important;
                }

                body.mobile-site #login2 .login-remember-row {
                    margin: 0 7px 10px !important;
                    width: calc(100% - 14px) !important;
                    box-sizing: border-box !important;
                }

                body.mobile-site #login2 .login-remember-label {
                    margin-left: 7px !important;
                }

                body.mobile-site #login2 #forgotPasswordScreen {
                    margin-top: 0 !important;
                }

                body.mobile-site #login2 #forgotPasswordScreen.d-none {
                    display: none !important;
                }

                body.mobile-site #login2 #forgotPasswordScreen:not(.d-none) {
                    display: flex !important;
                    flex-direction: column !important;
                    justify-content: center !important;
                    width: 100% !important;
                    max-width: 520px !important;
                    margin: 0 auto !important;
                }

                body.mobile-site #login2 #forgotPasswordScreen .login-forgot-heading {
                    margin: 0 0 12px !important;
                    padding: 12px 14px !important;
                    border-radius: 10px !important;
                    border: 1px solid rgba(255, 255, 255, 0.12) !important;
                    background: linear-gradient(180deg, rgba(255, 255, 255, 0.055), rgba(255, 255, 255, 0.015)) !important;
                }

                body.mobile-site #login2 #forgotPasswordScreen .login-top-text {
                    margin: 0 0 4px !important;
                    font-size: 12px !important;
                    color: rgba(255, 255, 255, 0.76) !important;
                    text-transform: none !important;
                }

                body.mobile-site #login2 #forgotPasswordScreen .login-forgot-title {
                    margin: 0 !important;
                    font-size: 20px !important;
                    line-height: 1.2 !important;
                    letter-spacing: 0 !important;
                    text-transform: none !important;
                }

                body.mobile-site #login2 #forgotPasswordScreen .login-forgot-hint {
                    margin: 7px 0 0 !important;
                    font-size: 13px !important;
                    line-height: 1.42 !important;
                    color: rgba(255, 255, 255, 0.85) !important;
                }

                body.mobile-site #login2 #forgotPasswordScreen .login-form {
                    display: flex !important;
                    flex-direction: column !important;
                    gap: 10px !important;
                }

                body.mobile-site #login2 #forgotPasswordScreen .login-forgot-heading,
                body.mobile-site #login2 #forgotPasswordScreen .login-form {
                    width: 100% !important;
                    max-width: 100% !important;
                    margin-left: 0 !important;
                    margin-right: 0 !important;
                }

                body.mobile-site #login2 #forgotPasswordScreen .form-group {
                    margin-bottom: 0 !important;
                    width: 100% !important;
                    max-width: 100% !important;
                    box-sizing: border-box !important;
                    overflow: hidden !important;
                }

                body.mobile-site #login2 #forgotPasswordScreen .form-control-label-bc,
                body.mobile-site #login2 #forgotPasswordScreen .form-control-input-bc {
                    width: 100% !important;
                    max-width: 100% !important;
                    box-sizing: border-box !important;
                }

                body.mobile-site #login2 #forgotPasswordScreen .login-btn,
                body.mobile-site #login2 #forgotPasswordScreen #forgotPasswordSubmit {
                    width: 100% !important;
                    max-width: 100% !important;
                    margin-left: 0 !important;
                    margin-right: 0 !important;
                    box-sizing: border-box !important;
                }

                body.mobile-site #login2 #forgotPasswordScreen .login-back-row {
                    width: 100% !important;
                    height: auto !important;
                    margin-top: 2px !important;
                    text-align: center !important;
                }

                body.mobile-site #login2 #forgotPasswordScreen .login-back-row a {
                    font-size: 13px !important;
                    color: rgba(255, 255, 255, 0.88) !important;
                    text-decoration: none !important;
                    border-bottom: 1px solid rgba(255, 255, 255, 0.3) !important;
                    padding-bottom: 2px !important;
                }

                body.mobile-site #login2 #forgotPasswordScreen .login-back-row a:hover {
                    color: #fff !important;
                    border-bottom-color: rgba(255, 255, 255, 0.55) !important;
                }

                body.mobile-site #login2 #forgotPasswordScreen .login-forgot-heading {
                    display: none !important;
                }

                body.mobile-site #login2 #forgotPasswordScreen:not(.d-none) {
                    min-height: 100% !important;
                    justify-content: flex-start !important;
                    width: calc(100% - 24px) !important;
                    max-width: none !important;
                    margin: 0 12px !important;
                    padding-top: clamp(250px, 37vh, 320px) !important;
                    padding-bottom: 12px !important;
                }

                body.mobile-site #login2 #forgotPasswordScreen .login-form {
                    flex: 0 0 auto !important;
                    margin-top: 8px !important;
                }

                body.mobile-site #login2 #forgotPasswordScreen .login-forgot-title {
                    font-size: 18px !important;
                }

                body.mobile-site #login2 #forgotPasswordScreen .login-forgot-hint {
                    font-size: 12px !important;
                }

                body.mobile-site #login2 #forgotPasswordScreen .login-forgot-note {
                    margin-top: 8px !important;
                }

                body.mobile-site #login2 #forgotPasswordScreen:not(.d-none) + .login-support {
                    display: none !important;
                }
            </style>
            <?php
            $authSliderScreen = 'login';
            $authSliderItems = $loginAuthSliderItems;
            include VIEW_PATH . '/partials/auth-slider-bg.php';
            unset($authSliderScreen, $authSliderItems);
            ?>
            <div class="e-p-content-holder-bc">
                <div class="e-p-content-bc">
                    <div class="modal-body e-p-body-bc">
                        <div class="login-modal-container">
                            <div class="login-modal-header e-p-header-bc">
                                <div class="login-logo">
                                    <img src="<?= htmlspecialchars($loginLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($loginSiteName, ENT_QUOTES, 'UTF-8') ?>" class="login-logo-img">
                                </div>
                                <div class="login-header-actions e-p-sections-bc">
                                    <button type="button" class="login-register-btn e-p-section-title-bc" id="openRegisterFromLogin">KAYIT</button>
                                    <button type="button" class="login-close e-p-close-icon-bc" data-dismiss="modal" aria-label="Kapat">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            </div>

                            <div id="loginFormScreen">
                                <form method="POST" action="#" novalidate class="login-form entrance-form-bc sign-in popup" id="loginForm">
                                    <input type="hidden" name="next" id="loginFormNext" value="<?= htmlspecialchars($login_next_safe, ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="entrance-form-content-bc single-side step-0">
                                        <div class="sg-n-text-row-1-bc login-text-block" id="loginScreenHeader">Hesabınız var mı?</div>
                                        <div class="sg-n-text-row-2-bc login-text-block">HEMEN GİRİŞ YAPIN!</div>

                                        <div class="form-group entrance-f-item-bc">
                                            <label class="form-control-label-bc inputs">
                                                <input type="text" class="form-control-input-bc" name="username" id="loginUsername" value="" required>
                                                <i class="form-control-input-stroke-bc"></i>
                                                <span class="form-control-title-bc ellipsis">E-posta / Kullanıcı Adı *</span>
                                            </label>
                                            <div class="login-error-text" data-error-for="username">Bu alan gerekli</div>
                                        </div>

                                        <div class="form-group entrance-f-item-bc">
                                            <label class="form-control-label-bc inputs login-password-field">
                                                <input type="password" class="form-control-input-bc password-input" name="password" id="loginPassword" value="" required>
                                                <button type="button" class="login-password-toggle" aria-label="Şifreyi göster" aria-pressed="false" data-target-password="#loginPassword">
                                                    <span class="login-password-toggle-icon login-password-toggle-icon-show" aria-hidden="true">
                                                        <svg viewBox="0 0 24 24" role="presentation" focusable="false" aria-hidden="true">
                                                            <path d="M2 12c1.8-4 6.1-7 10-7s8.2 3 10 7c-1.8 4-6.1 7-10 7S3.8 16 2 12Z"></path>
                                                            <circle cx="12" cy="12" r="3.2"></circle>
                                                        </svg>
                                                    </span>
                                                    <span class="login-password-toggle-icon login-password-toggle-icon-hide" aria-hidden="true">
                                                        <svg viewBox="0 0 24 24" role="presentation" focusable="false" aria-hidden="true">
                                                            <path d="M2 12c1.8-4 6.1-7 10-7s8.2 3 10 7c-1.8 4-6.1 7-10 7S3.8 16 2 12Z"></path>
                                                            <circle cx="12" cy="12" r="3.2"></circle>
                                                            <path d="M5 19 19 5"></path>
                                                        </svg>
                                                    </span>
                                                </button>
                                                <i class="form-control-input-stroke-bc"></i>
                                                <span class="form-control-title-bc ellipsis">Şifre *</span>
                                            </label>
                                            <div class="login-error-text" data-error-for="password">Bu alan gerekli</div>
                                        </div>

                                        <div class="login-remember-row">
                                            <label class="login-remember-label">
                                                <input type="checkbox" name="remember_me" class="login-remember-checkbox">
                                                <span>Beni hatırla</span>
                                            </label>
                                        </div>

                                        <?php if ($login_error !== ''): ?>
                                            <div class="login-error-box" role="alert"><?php echo htmlspecialchars($login_error); ?></div>
                                        <?php endif; ?>
                                        <?php if ($login_account_frozen_notice): ?>
                                            <div class="login-success-box" role="status">Hesabınız donduruldu. Tekrar kullanmak için hesap dondurmayı kaldırmanız gerekir.</div>
                                        <?php endif; ?>
                                        <?php if ($login_password_updated_notice): ?>
                                            <div class="login-success-box" role="status">Şifreniz güncellendi. Yeni şifrenizle giriş yapabilirsiniz.</div>
                                        <?php endif; ?>
                                        <div class="login-error-box login-ajax-alert d-none" id="loginAjaxAlert" role="alert"></div>

                                        <div class="entrance-form-actions-holder-bc reg-ext-1">
                                            <button type="submit" class="login-btn btn a-color">
                                                <span class="btn-text">GİRİŞ YAP</span>
                                                <span class="loading" style="display: none;"></span>
                                            </button>
                                        </div>

                                        <div class="login-or-separator">
                                            <span class="login-or-line"></span>
                                            <span class="login-or-text">VEYA</span>
                                            <span class="login-or-line"></span>
                                        </div>

                                        <button type="button" class="login-walletconnect-btn btn">
                                            <span class="login-walletconnect-icon">
                                                <svg width="141" height="24" viewBox="0 0 178 29" id="w3m-wc-logo" aria-hidden="true">
                                                    <path d="M10.683 7.926c5.284-5.17 13.85-5.17 19.134 0l.636.623a.652.652 0 0 1 0 .936l-2.176 2.129a.343.343 0 0 1-.478 0l-.875-.857c-3.686-3.607-9.662-3.607-13.348 0l-.937.918a.343.343 0 0 1-.479 0l-2.175-2.13a.652.652 0 0 1 0-.936l.698-.683Zm23.633 4.403 1.935 1.895a.652.652 0 0 1 0 .936l-8.73 8.543a.687.687 0 0 1-.956 0L20.37 17.64a.172.172 0 0 0-.239 0l-6.195 6.063a.687.687 0 0 1-.957 0l-8.73-8.543a.652.652 0 0 1 0-.936l1.936-1.895a.687.687 0 0 1 .957 0l6.196 6.064a.172.172 0 0 0 .239 0l6.195-6.064a.687.687 0 0 1 .957 0l6.196 6.064a.172.172 0 0 0 .24 0l6.195-6.064a.687.687 0 0 1 .956 0ZM48.093 20.948l2.338-9.355c.139-.515.258-1.07.416-1.942.12.872.258 1.427.357 1.942l2.022 9.355h4.181l3.528-13.874h-3.21l-1.943 8.523a24.825 24.825 0 0 0-.456 2.457c-.158-.931-.317-1.625-.495-2.438l-1.883-8.542h-4.201l-2.042 8.542a41.204 41.204 0 0 0-.475 2.438 41.208 41.208 0 0 0-.476-2.438l-1.903-8.542h-3.349l3.508 13.874h4.083ZM63.33 21.304c1.585 0 2.596-.654 3.11-1.605-.059.297-.078.595-.078.892v.357h2.655V15.22c0-2.735-1.248-4.32-4.3-4.32-2.636 0-4.36 1.466-4.52 3.487h2.914c.1-.891.734-1.426 1.705-1.426.911 0 1.407.515 1.407 1.11 0 .435-.258.693-1.03.792l-1.388.159c-2.061.257-3.825 1.01-3.825 3.19 0 1.982 1.645 3.092 3.35 3.092Zm.891-2.041c-.773 0-1.348-.436-1.348-1.19 0-.733.655-1.09 1.645-1.268l.674-.119c.575-.118.892-.218 1.09-.396v.912c0 1.228-.892 2.06-2.06 2.06ZM70.398 7.074v13.874h2.874V7.074h-2.874ZM74.934 7.074v13.874h2.874V7.074h-2.874ZM84.08 21.304c2.735 0 4.5-1.546 4.697-3.567h-2.893c-.139.892-.892 1.387-1.804 1.387-1.228 0-2.12-.99-2.14-2.358h6.897v-.555c0-3.21-1.764-5.312-4.816-5.312-2.933 0-4.994 2.062-4.994 5.173 0 3.37 2.12 5.232 5.053 5.232Zm-2.16-6.421c.119-1.11.932-1.922 2.081-1.922 1.11 0 1.883.772 1.903 1.922H81.92ZM94.92 21.146c.633 0 1.248-.1 1.525-.179v-2.18c-.218.04-.475.06-.693.06-1.05 0-1.427-.595-1.427-1.566v-3.805h2.338v-2.24h-2.338V7.788H91.47v3.448H89.37v2.24h2.1v4.201c0 2.3 1.15 3.469 3.45 3.469ZM104.62 21.304c3.924 0 6.302-2.299 6.599-5.608h-3.111c-.238 1.803-1.506 3.032-3.369 3.032-2.2 0-3.746-1.784-3.746-4.796 0-2.953 1.605-4.638 3.805-4.638 1.883 0 2.953 1.15 3.171 2.834h3.191c-.317-3.448-2.854-5.41-6.342-5.41-3.984 0-7.036 2.695-7.036 7.214 0 4.677 2.676 7.372 6.838 7.372ZM117.449 21.304c2.993 0 5.114-1.882 5.114-5.172 0-3.23-2.121-5.233-5.114-5.233-2.972 0-5.093 2.002-5.093 5.233 0 3.29 2.101 5.172 5.093 5.172Zm0-2.22c-1.327 0-2.18-1.09-2.18-2.952 0-1.903.892-2.973 2.18-2.973 1.308 0 2.2 1.07 2.2 2.973 0 1.862-.872 2.953-2.2 2.953ZM126.569 20.948v-5.689c0-1.208.753-2.1 1.823-2.1 1.011 0 1.606.773 1.606 2.06v5.729h2.873v-6.144c0-2.339-1.229-3.905-3.428-3.905-1.526 0-2.458.734-2.953 1.606a5.31 5.31 0 0 0 .079-.892v-.377h-2.874v9.712h2.874ZM137.464 20.948v-5.689c0-1.208.753-2.1 1.823-2.1 1.011 0 1.606.773 1.606 2.06v5.729h2.873v-6.144c0-2.339-1.228-3.905-3.428-3.905-1.526 0-2.458.734-2.953 1.606a5.31 5.31 0 0 0 .079-.892v-.377h-2.874v9.712h2.874ZM149.949 21.304c2.735 0 4.499-1.546 4.697-3.567h-2.893c-.139.892-.892 1.387-1.804 1.387-1.228 0-2.12-.99-2.14-2.358h6.897v-.555c0-3.21-1.764-5.312-4.816-5.312-2.933 0-4.994 2.062-4.994 5.173 0 3.37 2.12 5.232 5.053 5.232Zm-2.16-6.421c.119-1.11.932-1.922 2.081-1.922 1.11 0 1.883.772 1.903 1.922h-3.984ZM160.876 21.304c3.013 0 4.658-1.645 4.975-4.201h-2.874c-.099 1.07-.713 1.982-2.001 1.982-1.309 0-2.2-1.21-2.2-2.993 0-1.942 1.03-2.933 2.259-2.933 1.209 0 1.803.872 1.883 1.882h2.873c-.218-2.358-1.823-4.142-4.776-4.142-2.874 0-5.153 1.903-5.153 5.193 0 3.25 1.923 5.212 5.014 5.212ZM172.067 21.146c.634 0 1.248-.1 1.526-.179v-2.18c-.218.04-.476.06-.694.06-1.05 0-1.427-.595-1.427-1.566v-3.805h2.339v-2.24h-2.339V7.788h-2.854v3.448h-2.1v2.24h2.1v4.201c0 2.3 1.15 3.469 3.449 3.469Z"/>
                                                </svg>
                                            </span>
                                        </button>
                                    </div>
                                </form>

                                <div class="login-forgot">
                                    <a href="#" id="openForgotPassword" onclick="return window.__openForgotPasswordInline ? window.__openForgotPasswordInline(this) : false;">ŞİFRENİZİ Mİ UNUTTUNUZ?</a>
                                </div>
                            </div>

                            <div id="forgotPasswordScreen" class="d-none">
                                <div class="login-text-block login-forgot-heading">
                                    <p class="login-top-text">Şifrenizi mi unuttunuz?</p>
                                    <h2 class="login-main-title login-forgot-title">Şifre sıfırlama</h2>
                                    <p class="login-forgot-hint">E-posta adresinizi girin. Hesabınız varsa şifre sıfırlama bağlantısını hemen gönderelim.</p>
                                </div>
                                <form method="POST" action="#" novalidate class="login-form" id="forgotPasswordForm">
                                    <div class="form-group">
                                        <label class="form-control-label-bc inputs">
                                            <input type="email" class="form-control-input-bc" name="email" id="forgotPasswordEmail" value="" required autocomplete="email">
                                            <i class="form-control-input-stroke-bc"></i>
                                            <span class="form-control-title-bc ellipsis">E-posta *</span>
                                        </label>
                                        <div class="login-error-text" data-error-for="email">Bu alan gerekli</div>
                                    </div>
                                    <div class="login-error-box login-ajax-alert d-none" id="forgotPasswordAjaxAlert" role="alert"></div>
                                    <div class="login-success-box d-none" id="forgotPasswordSuccess" role="status"></div>
                                    <button type="submit" class="login-btn" id="forgotPasswordSubmit">
                                        <span class="btn-text">BAĞLANTI GÖNDER</span>
                                        <span class="loading" style="display: none;"></span>
                                    </button>
                                    <div class="login-forgot-note" role="note">
                                        <i class="bc-i-player-info" aria-hidden="true"></i>
                                        <span>Şifrenizi sıfırlamak için kayıtlı e-posta adresinizi giriniz.</span>
                                    </div>
                                    <div class="login-forgot login-back-row">
                                        <a href="#" id="backToLoginFromForgot" onclick="return window.__backToLoginInline ? window.__backToLoginInline(this) : false;">Girişe dön</a>
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
window.__openForgotPasswordInline = window.__openForgotPasswordInline || function (el) {
    try {
        var root = (el && el.closest) ? el.closest('#login2') : document.getElementById('login2');
        var main = root ? root.querySelector('#loginFormScreen') : document.getElementById('loginFormScreen');
        var forgot = root ? root.querySelector('#forgotPasswordScreen') : document.getElementById('forgotPasswordScreen');
        var forgotForm = forgot ? forgot.querySelector('#forgotPasswordForm') : null;
        var isMobile = document.body && document.body.classList.contains('mobile-site');
        if (main) main.classList.add('d-none');
        if (forgot) forgot.classList.remove('d-none');
        if (isMobile && forgot) {
            forgot.style.justifyContent = 'flex-start';
            forgot.style.paddingTop = 'clamp(330px, 46vh, 410px)';
            forgot.style.paddingBottom = '12px';
            forgot.style.width = 'calc(100% - 24px)';
            forgot.style.margin = '0 12px';
        }
        if (isMobile && forgotForm) {
            forgotForm.style.flex = '0 0 auto';
            forgotForm.style.marginTop = '16px';
        }
    } catch (e) {}
    return false;
};

window.__backToLoginInline = window.__backToLoginInline || function (el) {
    try {
        var root = (el && el.closest) ? el.closest('#login2') : document.getElementById('login2');
        var main = root ? root.querySelector('#loginFormScreen') : document.getElementById('loginFormScreen');
        var forgot = root ? root.querySelector('#forgotPasswordScreen') : document.getElementById('forgotPasswordScreen');
        var forgotForm = forgot ? forgot.querySelector('#forgotPasswordForm') : null;
        if (forgot) forgot.classList.add('d-none');
        if (main) main.classList.remove('d-none');
        if (forgot) {
            forgot.style.justifyContent = '';
            forgot.style.paddingTop = '';
            forgot.style.paddingBottom = '';
            forgot.style.width = '';
            forgot.style.margin = '';
        }
        if (forgotForm) {
            forgotForm.style.flex = '';
            forgotForm.style.marginTop = '';
        }
    } catch (e) {}
    return false;
};
</script>
<?php if ($login_error !== '' || $login_account_frozen_notice): ?>
<script>document.addEventListener('DOMContentLoaded',function(){var m=document.getElementById('login2'),$=window.jQuery||window.$;if(m&&$)$(m).modal('show');});</script>
<?php endif; ?>
