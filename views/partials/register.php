<?php
$registerMobileSingleStep = !empty($registerSingleStepMobile);
$registerAuthSliderItems = class_exists('ApiAuthSliders') ? ApiAuthSliders::fetchFor('register') : [];
$registerAuthSliderClass = $registerAuthSliderItems !== [] ? ' has-auth-slider' : '';
$registerBranding = (isset($siteBranding) && is_array($siteBranding)) ? $siteBranding : [];
$registerSiteName = (string) ($registerBranding['site_name'] ?? $ayar['site_adi'] ?? 'VegasRoyalSpin');
$registerLogoUrl = (string) ($registerBranding['logo_url'] ?? $ayar['logo_url'] ?? '');
if (class_exists('ApiMediaUrl', false)) {
    $registerLogoUrl = ApiMediaUrl::resolve($registerLogoUrl);
}
?>
<!-- KAYIT MODAL (masaüstü: iki adım; mobil: tek form) -->
<div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered register-modal-dialog">
        <div class="modal-content register-modal-content entrance-popup-bc register<?= htmlspecialchars($registerAuthSliderClass, ENT_QUOTES, 'UTF-8') ?>">
            <?php
            $authSliderScreen = 'register';
            $authSliderItems = $registerAuthSliderItems;
            include VIEW_PATH . '/partials/auth-slider-bg.php';
            unset($authSliderScreen, $authSliderItems);
            ?>
            <div class="e-p-content-holder-bc">
                <div class="e-p-content-bc">
                    <div class="register-modal-header-text e-p-header-bc">
                        <div class="register-modal-top-bar">
                            <a class="popup-t-logo-w-bc register-logo" href="/">
                                <img src="<?= htmlspecialchars($registerLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($registerSiteName, ENT_QUOTES, 'UTF-8') ?>" class="register-logo-img hdr-logo-bc">
                            </a>
                            <div class="register-top-right e-p-sections-bc">
                                <div class="e-p-section-item-bc">
                                    <button type="button" class="register-modal-login-link e-p-section-title-bc" id="openLoginFromRegister">
                                        GİRİŞ
                                    </button>
                                </div>
                                <button type="button" class="register-modal-close" data-dismiss="modal" aria-label="Kapat">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="register-modal-body e-p-body-bc">
                        <div class="reg-form-block-bc">
                <form id="modalRegisterForm" novalidate class="entrance-form-bc registration popup register-form-layout<?php echo $registerMobileSingleStep ? ' register-form--mobile-single' : ''; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                    <?php if (!$registerMobileSingleStep): ?>
                    <div class="steps-indicator register-steps-indicator" aria-hidden="true">
                        <span class="step-indicator step-indicator-active" data-register-step-indicator="1"></span>
                        <span class="step-indicator" data-register-step-indicator="2"></span>
                    </div>
                    <?php endif; ?>
                    <div class="register-steps-scroll entrance-form-content-bc single-side step-0" data-scroll-lock-scrollable="">
                    <div class="reg-form-content" data-scroll-lock-scrollable="">
                    <div class="sg-n-text-row-1-bc"><?= htmlspecialchars($registerSiteName, ENT_QUOTES, 'UTF-8') ?>'da Yeni misin?</div>
                    <div class="sg-n-text-row-2-bc">ŞİMDİ KAYDOLUN, HERŞEY ÇOK KOLAY!</div>
                    <div class="reg-form-fields">
                    <!-- ADIM 1 -->
                    <div class="register-step register-step-1" data-step="1">
                        <?php if (!$registerMobileSingleStep): ?>
                        <h3 class="register-step-title reg-step-title-v-bc">KAYIT ADIMI 1</h3>
                        <?php endif; ?>

                        <div class="form-group entrance-f-item-bc">
                            <label class="form-control-label-bc inputs">
                                <input type="text" class="form-control-input-bc" name="username" id="modal_username" maxlength="20" required>
                                <i class="form-control-input-stroke-bc"></i>
                                <span class="form-control-title-bc ellipsis">Kullanıcı adı *</span>
                            </label>
                            <div class="register-error-text" data-error-for="username">Bu alan gerekli</div>
                        </div>

                        <div class="form-group entrance-f-item-bc">
                            <label class="form-control-label-bc inputs">
                                <input type="password" class="form-control-input-bc password-input" name="password" id="modal_password" required>
                                <i class="form-control-input-stroke-bc"></i>
                                <span class="form-control-title-bc ellipsis">Şifre *</span>
                            </label>
                            <div class="register-error-text" data-error-for="password">Bu alan gerekli</div>
                        </div>

                        <div class="form-group entrance-f-item-bc">
                            <label class="form-control-label-bc inputs">
                                <input type="password" class="form-control-input-bc password-input" name="confirm_password" id="modal_confirm_password" required>
                                <i class="form-control-input-stroke-bc"></i>
                                <span class="form-control-title-bc ellipsis">Şifreyi onayla *</span>
                            </label>
                            <div class="register-error-text" data-error-for="confirm_password">Bu alan gerekli</div>
                        </div>

                        <?php if (!$registerMobileSingleStep): ?>
                        <div class="register-actions register-actions-row">
                            <button type="button" class="register-primary-btn" id="registerNextStep">
                                SONRAKİ
                            </button>
                        </div>
                        <button type="button" class="register-secondary-btn register-walletconnect-btn register-walletconnect-full">
                            <span class="register-walletconnect-icon">
                                <svg width="141" height="24" viewBox="0 0 178 29" id="w3m-wc-logo">
                                    <path d="M10.683 7.926c5.284-5.17 13.85-5.17 19.134 0l.636.623a.652.652 0 0 1 0 .936l-2.176 2.129a.343.343 0 0 1-.478 0l-.875-.857c-3.686-3.607-9.662-3.607-13.348 0l-.937.918a.343.343 0 0 1-.479 0l-2.175-2.13a.652.652 0 0 1 0-.936l.698-.683Zm23.633 4.403 1.935 1.895a.652.652 0 0 1 0 .936l-8.73 8.543a.687.687 0 0 1-.956 0L20.37 17.64a.172.172 0 0 0-.239 0l-6.195 6.063a.687.687 0 0 1-.957 0l-8.73-8.543a.652.652 0 0 1 0-.936l1.936-1.895a.687.687 0 0 1 .957 0l6.196 6.064a.172.172 0 0 0 .239 0l6.195-6.064a.687.687 0 0 1 .957 0l6.196 6.064a.172.172 0 0 0 .24 0l6.195-6.064a.687.687 0 0 1 .956 0ZM48.093 20.948l2.338-9.355c.139-.515.258-1.07.416-1.942.12.872.258 1.427.357 1.942l2.022 9.355h4.181l3.528-13.874h-3.21l-1.943 8.523a24.825 24.825 0 0 0-.456 2.457c-.158-.931-.317-1.625-.495-2.438l-1.883-8.542h-4.201l-2.042 8.542a41.204 41.204 0 0 0-.475 2.438 41.208 41.208 0 0 0-.476-2.438l-1.903-8.542h-3.349l3.508 13.874h4.083ZM63.33 21.304c1.585 0 2.596-.654 3.11-1.605-.059.297-.078.595-.078.892v.357h2.655V15.22c0-2.735-1.248-4.32-4.3-4.32-2.636 0-4.36 1.466-4.52 3.487h2.914c.1-.891.734-1.426 1.705-1.426.911 0 1.407.515 1.407 1.11 0 .435-.258.693-1.03.792l-1.388.159c-2.061.257-3.825 1.01-3.825 3.19 0 1.982 1.645 3.092 3.35 3.092Zm.891-2.041c-.773 0-1.348-.436-1.348-1.19 0-.733.655-1.09 1.645-1.268l.674-.119c.575-.118.892-.218 1.09-.396v.912c0 1.228-.892 2.06-2.06 2.06ZM70.398 7.074v13.874h2.874V7.074h-2.874ZM74.934 7.074v13.874h2.874V7.074h-2.874ZM84.08 21.304c2.735 0 4.5-1.546 4.697-3.567h-2.893c-.139.892-.892 1.387-1.804 1.387-1.228 0-2.12-.99-2.14-2.358h6.897v-.555c0-3.21-1.764-5.312-4.816-5.312-2.933 0-4.994 2.062-4.994 5.173 0 3.37 2.12 5.232 5.053 5.232Zm-2.16-6.421c.119-1.11.932-1.922 2.081-1.922 1.11 0 1.883.772 1.903 1.922H81.92ZM94.92 21.146c.633 0 1.248-.1 1.525-.179v-2.18c-.218.04-.475.06-.693.06-1.05 0-1.427-.595-1.427-1.566v-3.805h2.338v-2.24h-2.338V7.788H91.47v3.448H89.37v2.24h2.1v4.201c0 2.3 1.15 3.469 3.45 3.469ZM104.62 21.304c3.924 0 6.302-2.299 6.599-5.608h-3.111c-.238 1.803-1.506 3.032-3.369 3.032-2.2 0-3.746-1.784-3.746-4.796 0-2.953 1.605-4.638 3.805-4.638 1.883 0 2.953 1.15 3.171 2.834h3.191c-.317-3.448-2.854-5.41-6.342-5.41-3.984 0-7.036 2.695-7.036 7.214 0 4.677 2.676 7.372 6.838 7.372ZM117.449 21.304c2.993 0 5.114-1.882 5.114-5.172 0-3.23-2.121-5.233-5.114-5.233-2.972 0-5.093 2.002-5.093 5.233 0 3.29 2.101 5.172 5.093 5.172Zm0-2.22c-1.327 0-2.18-1.09-2.18-2.952 0-1.903.892-2.973 2.18-2.973 1.308 0 2.2 1.07 2.2 2.973 0 1.862-.872 2.953-2.2 2.953ZM126.569 20.948v-5.689c0-1.208.753-2.1 1.823-2.1 1.011 0 1.606.773 1.606 2.06v5.729h2.873v-6.144c0-2.339-1.229-3.905-3.428-3.905-1.526 0-2.458.734-2.953 1.606a5.31 5.31 0 0 0 .079-.892v-.377h-2.874v9.712h2.874ZM137.464 20.948v-5.689c0-1.208.753-2.1 1.823-2.1 1.011 0 1.606.773 1.606 2.06v5.729h2.873v-6.144c0-2.339-1.228-3.905-3.428-3.905-1.526 0-2.458.734-2.953 1.606a5.31 5.31 0 0 0 .079-.892v-.377h-2.874v9.712h2.874ZM149.949 21.304c2.735 0 4.499-1.546 4.697-3.567h-2.893c-.139.892-.892 1.387-1.804 1.387-1.228 0-2.12-.99-2.14-2.358h6.897v-.555c0-3.21-1.764-5.312-4.816-5.312-2.933 0-4.994 2.062-4.994 5.173 0 3.37 2.12 5.232 5.053 5.232Zm-2.16-6.421c.119-1.11.932-1.922 2.081-1.922 1.11 0 1.883.772 1.903 1.922h-3.984ZM160.876 21.304c3.013 0 4.658-1.645 4.975-4.201h-2.874c-.099 1.07-.713 1.982-2.001 1.982-1.309 0-2.2-1.21-2.2-2.993 0-1.942 1.03-2.933 2.259-2.933 1.209 0 1.803.872 1.883 1.882h2.873c-.218-2.358-1.823-4.142-4.776-4.142-2.874 0-5.153 1.903-5.153 5.193 0 3.25 1.923 5.212 5.014 5.212ZM172.067 21.146c.634 0 1.248-.1 1.526-.179v-2.18c-.218.04-.476.06-.694.06-1.05 0-1.427-.595-1.427-1.566v-3.805h2.339v-2.24h-2.339V7.788h-2.854v3.448h-2.1v2.24h2.1v4.201c0 2.3 1.15 3.469 3.449 3.469Z"></path>
                                </svg>
                            </span>
                        </button>
                        <?php endif; ?>
                    </div>

                    <!-- ADIM 2 -->
                    <div class="register-step register-step-2<?php echo $registerMobileSingleStep ? '' : ' d-none'; ?>" data-step="2">
                        <?php if (!$registerMobileSingleStep): ?>
                        <h3 class="register-step-title reg-step-title-v-bc">KAYIT ADIMI 2</h3>
                        <?php endif; ?>

                        <div class="form-group entrance-f-item-bc">
                            <label class="form-control-label-bc inputs">
                                <input type="text" class="form-control-input-bc" name="firstName" id="modal_firstName" required>
                                <i class="form-control-input-stroke-bc"></i>
                                <span class="form-control-title-bc ellipsis">Adı *</span>
                            </label>
                            <div class="register-error-text" data-error-for="firstName">Bu alan gerekli</div>
                        </div>

                        <div class="form-group entrance-f-item-bc">
                            <label class="form-control-label-bc inputs">
                                <input type="text" class="form-control-input-bc" name="middleName" id="modal_middleName">
                                <i class="form-control-input-stroke-bc"></i>
                                <span class="form-control-title-bc ellipsis">İkinci İsim</span>
                            </label>
                        </div>

                        <div class="form-group entrance-f-item-bc">
                            <label class="form-control-label-bc inputs">
                                <input type="text" class="form-control-input-bc" name="surname" id="modal_surname" required>
                                <i class="form-control-input-stroke-bc"></i>
                                <span class="form-control-title-bc ellipsis">Soyadı *</span>
                            </label>
                            <div class="register-error-text" data-error-for="surname">Bu alan gerekli</div>
                        </div>

                        <div class="form-group register-dob-wrap entrance-f-item-bc">
                            <div class="register-dob-picker" id="register_dob_picker">
                                <input type="date" name="dob" id="modal_dob" required class="register-dob-input-hidden" aria-hidden="true" tabindex="-1">
                                <button type="button" class="register-dob-trigger" aria-expanded="false" aria-haspopup="dialog">
                                    <span class="register-dob-trigger-text">
                                        <span class="form-control-title-bc register-dob-label">Doğum Tarihi *</span>
                                        <span class="register-dob-value" data-placeholder="gg.aa.yyyy">gg.aa.yyyy</span>
                                    </span>
                                    <span class="register-dob-icon" aria-hidden="true">
                                        <i class="dropdownIcon-bc bc-i-datepicker"></i>
                                    </span>
                                </button>
                                <div class="register-datepicker-panel" id="register_datepicker_panel" role="dialog" aria-label="Doğum tarihi seçin" hidden>
                                    <div class="register-datepicker-nav">
                                        <button type="button" class="register-datepicker-prev" aria-label="Önceki ay">&lt;</button>
                                        <select class="register-datepicker-month" aria-label="Ay">
                                            <option value="0">Ocak</option><option value="1">Şubat</option><option value="2">Mart</option><option value="3">Nisan</option><option value="4">Mayıs</option><option value="5">Haziran</option><option value="6">Temmuz</option><option value="7">Ağustos</option><option value="8">Eylül</option><option value="9">Ekim</option><option value="10">Kasım</option><option value="11">Aralık</option>
                                        </select>
                                        <select class="register-datepicker-year" aria-label="Yıl"></select>
                                    </div>
                                    <div class="register-datepicker-weekdays">
                                        <span>Pzt</span><span>Sal</span><span>Çar</span><span>Per</span><span>Cum</span><span>Cmt</span><span>Paz</span>
                                    </div>
                                    <div class="register-datepicker-days"></div>
                                    <div class="register-datepicker-actions">
                                        <button type="button" class="register-datepicker-cancel">İPTAL</button>
                                        <button type="button" class="register-datepicker-apply">UYGULA</button>
                                    </div>
                                </div>
                            </div>
                            <div class="register-error-text" data-error-for="dob">Bu alan gerekli</div>
                        </div>

                        <div class="form-group entrance-f-item-bc">
                            <input type="hidden" name="country" id="modal_country" value="TR">
                            <div class="bc-fixed-select" aria-hidden="true">
                                <span class="bc-fixed-select__label">Ülke</span>
                                <span class="bc-fixed-select__value bc-fixed-select__value--with-flag">
                                    <span class="flag-icon flag-icon-tr" aria-hidden="true"></span>
                                    Türkiye
                                </span>
                            </div>
                        </div>

                        <div class="form-group entrance-f-item-bc">
                            <input type="hidden" name="currency" id="modal_currency" value="TRY">
                            <div class="bc-fixed-select" aria-hidden="true">
                                <span class="bc-fixed-select__label">Para birimi *</span>
                                <span class="bc-fixed-select__value">TRY</span>
                            </div>
                        </div>

                        <div class="register-form-group entrance-f-item-bc">
                            <input
                                type="text"
                                name="city"
                                class="register-input"
                                id="modal_city"
                                placeholder="Şehir *"
                                required
                            >
                            <div class="register-error-text" data-error-for="city">Bu alan gerekli</div>
                        </div>

                        <div class="register-form-group entrance-f-item-bc">
                            <input
                                type="text"
                                name="tcKimlik"
                                class="register-input"
                                id="modal_tcKimlik"
                                placeholder="Kimlik Numarası *"
                                maxlength="11"
                                required
                            >
                            <div class="register-error-text" data-error-for="tcKimlik">Bu alan gerekli</div>
                        </div>

                        <div class="register-form-group entrance-f-item-bc">
                            <input
                                type="email"
                                name="email"
                                class="register-input"
                                id="modal_email"
                                placeholder="E-posta *"
                                required
                            >
                            <div class="register-error-text" data-error-for="email">Bu alan gerekli</div>
                        </div>

                        <div class="register-form-group bc-custom-select entrance-f-item-bc" data-bc-custom-select>
                            <select name="gender" id="modal_gender" class="bc-custom-select__native" required aria-hidden="true" tabindex="-1">
                                <option value="" disabled selected>Cinsiyet seçin</option>
                                <option value="Erkek">Erkek</option>
                                <option value="Kadın">Kadın</option>
                                <option value="Diğer">Diğer</option>
                            </select>
                            <button type="button" class="bc-custom-select__trigger" aria-expanded="false" aria-haspopup="listbox" id="modal_gender_trigger">
                                <span class="bc-custom-select__label">Cinsiyet *</span>
                                <span class="bc-custom-select__value">Cinsiyet seçin</span>
                                <span class="bc-custom-select__arrow" aria-hidden="true"></span>
                            </button>
                            <div class="bc-custom-select__panel" role="listbox" id="modal_gender_listbox" hidden>
                                <div class="bc-custom-select__option" data-value="Erkek" role="option">Erkek</div>
                                <div class="bc-custom-select__option" data-value="Kadın" role="option">Kadın</div>
                                <div class="bc-custom-select__option" data-value="Diğer" role="option">Diğer</div>
                            </div>
                            <div class="register-error-text" data-error-for="gender">Bu alan gerekli</div>
                        </div>

                        <div class="form-group register-phone-row entrance-f-item-bc">
                            <div class="register-phone-code">
                                <input type="hidden" name="phone_country_code" id="modal_phone_country_code" value="90">
                                <div class="bc-fixed-select bc-fixed-select--passive" aria-hidden="true">
                                    <span class="bc-fixed-select__label">Kodu</span>
                                    <span class="bc-fixed-select__value bc-fixed-select__value--with-flag">
                                        <span class="flag-icon flag-icon-tr" aria-hidden="true"></span>
                                        90
                                    </span>
                                </div>
                            </div>
                            <div class="register-phone-number">
                                <input
                                    type="tel"
                                    name="phone"
                                    class="register-input"
                                    id="modal_phone"
                                    placeholder="Telefon numarası *"
                                    maxlength="15"
                                    required
                                >
                                <div class="register-error-text" data-error-for="phone">Bu alan gerekli</div>
                            </div>
                        </div>

                        <div class="form-group entrance-f-item-bc">
                            <label class="form-control-label-bc inputs">
                                <input type="text" class="form-control-input-bc" name="bonusCode" id="modal_bonusCode">
                                <i class="form-control-input-stroke-bc"></i>
                                <span class="form-control-title-bc ellipsis">Promosyon Kodu</span>
                            </label>
                        </div>

                        <div class="register-form-group register-terms entrance-f-item-bc">
                            <label class="register-terms-label">
                                <input type="checkbox" name="terms_accepted" id="modal_terms_accepted" required class="register-terms-checkbox">
                                <span class="register-terms-text">
                                    18 yaşından büyüğüm, Genel Kurallar ve Şartları okudum ve kabul ediyorum.
                                    <a href="/gizlilik-politikasi" target="_blank">Gizlilik Politikası</a>
                                    ve
                                    <a href="/genel-sartlar" target="_blank">Genel Şartlar ve Koşullar</a>
                                </span>
                            </label>
                            <a href="/gizlilik-politikasi" target="_blank" class="register-terms-secondary-link">
                                <span class="register-terms-icon" aria-hidden="true"></span>
                                Gizlilik Politikası ve Kullanım Şartları
                            </a>
                            <div class="register-error-text" data-error-for="terms_accepted">Devam etmek için bu kutuyu işaretlemelisiniz.</div>
                        </div>

                        <div class="register-actions register-actions-row entrance-form-actions-holder-bc reg-ext-1">
                            <?php if (!$registerMobileSingleStep): ?>
                            <button type="button" class="register-secondary-btn" id="registerPrevStep">
                                GERİ
                            </button>
                            <?php endif; ?>
                            <button type="submit" class="register-primary-btn btn a-color" id="modalRegisterSubmit" title="Kayıt">
                                <span>Kayıt</span>
                            </button>
                        </div>
                        <button type="button" class="register-secondary-btn register-walletconnect-btn register-walletconnect-full btn">
                            <span class="register-walletconnect-icon">
                                <svg width="141" height="24" viewBox="0 0 178 29" id="w3m-wc-logo-step2" aria-hidden="true">
                                    <path d="M10.683 7.926c5.284-5.17 13.85-5.17 19.134 0l.636.623a.652.652 0 0 1 0 .936l-2.176 2.129a.343.343 0 0 1-.478 0l-.875-.857c-3.686-3.607-9.662-3.607-13.348 0l-.937.918a.343.343 0 0 1-.479 0l-2.175-2.13a.652.652 0 0 1 0-.936l.698-.683Zm23.633 4.403 1.935 1.895a.652.652 0 0 1 0 .936l-8.73 8.543a.687.687 0 0 1-.956 0L20.37 17.64a.172.172 0 0 0-.239 0l-6.195 6.063a.687.687 0 0 1-.957 0l-8.73-8.543a.652.652 0 0 1 0-.936l1.936-1.895a.687.687 0 0 1 .957 0l6.196 6.064a.172.172 0 0 0 .239 0l6.195-6.064a.687.687 0 0 1 .957 0l6.196 6.064a.172.172 0 0 0 .24 0l6.195-6.064a.687.687 0 0 1 .956 0ZM48.093 20.948l2.338-9.355c.139-.515.258-1.07.416-1.942.12.872.258 1.427.357 1.942l2.022 9.355h4.181l3.528-13.874h-3.21l-1.943 8.523a24.825 24.825 0 0 0-.456 2.457c-.158-.931-.317-1.625-.495-2.438l-1.883-8.542h-4.201l-2.042 8.542a41.204 41.204 0 0 0-.475 2.438 41.208 41.208 0 0 0-.476-2.438l-1.903-8.542h-3.349l3.508 13.874h4.083ZM63.33 21.304c1.585 0 2.596-.654 3.11-1.605-.059.297-.078.595-.078.892v.357h2.655V15.22c0-2.735-1.248-4.32-4.3-4.32-2.636 0-4.36 1.466-4.52 3.487h2.914c.1-.891.734-1.426 1.705-1.426.911 0 1.407.515 1.407 1.11 0 .435-.258.693-1.03.792l-1.388.159c-2.061.257-3.825 1.01-3.825 3.19 0 1.982 1.645 3.092 3.35 3.092Zm.891-2.041c-.773 0-1.348-.436-1.348-1.19 0-.733.655-1.09 1.645-1.268l.674-.119c.575-.118.892-.218 1.09-.396v.912c0 1.228-.892 2.06-2.06 2.06ZM70.398 7.074v13.874h2.874V7.074h-2.874ZM74.934 7.074v13.874h2.874V7.074h-2.874ZM84.08 21.304c2.735 0 4.5-1.546 4.697-3.567h-2.893c-.139.892-.892 1.387-1.804 1.387-1.228 0-2.12-.99-2.14-2.358h6.897v-.555c0-3.21-1.764-5.312-4.816-5.312-2.933 0-4.994 2.062-4.994 5.173 0 3.37 2.12 5.232 5.053 5.232Zm-2.16-6.421c.119-1.11.932-1.922 2.081-1.922 1.11 0 1.883.772 1.903 1.922H81.92ZM94.92 21.146c.633 0 1.248-.1 1.525-.179v-2.18c-.218.04-.475.06-.693.06-1.05 0-1.427-.595-1.427-1.566v-3.805h2.338v-2.24h-2.338V7.788H91.47v3.448H89.37v2.24h2.1v4.201c0 2.3 1.15 3.469 3.45 3.469ZM104.62 21.304c3.924 0 6.302-2.299 6.599-5.608h-3.111c-.238 1.803-1.506 3.032-3.369 3.032-2.2 0-3.746-1.784-3.746-4.796 0-2.953 1.605-4.638 3.805-4.638 1.883 0 2.953 1.15 3.171 2.834h3.191c-.317-3.448-2.854-5.41-6.342-5.41-3.984 0-7.036 2.695-7.036 7.214 0 4.677 2.676 7.372 6.838 7.372ZM117.449 21.304c2.993 0 5.114-1.882 5.114-5.172 0-3.23-2.121-5.233-5.114-5.233-2.972 0-5.093 2.002-5.093 5.233 0 3.29 2.101 5.172 5.093 5.172Zm0-2.22c-1.327 0-2.18-1.09-2.18-2.952 0-1.903.892-2.973 2.18-2.973 1.308 0 2.2 1.07 2.2 2.973 0 1.862-.872 2.953-2.2 2.953ZM126.569 20.948v-5.689c0-1.208.753-2.1 1.823-2.1 1.011 0 1.606.773 1.606 2.06v5.729h2.873v-6.144c0-2.339-1.229-3.905-3.428-3.905-1.526 0-2.458.734-2.953 1.606a5.31 5.31 0 0 0 .079-.892v-.377h-2.874v9.712h2.874ZM137.464 20.948v-5.689c0-1.208.753-2.1 1.823-2.1 1.011 0 1.606.773 1.606 2.06v5.729h2.873v-6.144c0-2.339-1.228-3.905-3.428-3.905-1.526 0-2.458.734-2.953 1.606a5.31 5.31 0 0 0 .079-.892v-.377h-2.874v9.712h2.874ZM149.949 21.304c2.735 0 4.499-1.546 4.697-3.567h-2.893c-.139.892-.892 1.387-1.804 1.387-1.228 0-2.12-.99-2.14-2.358h6.897v-.555c0-3.21-1.764-5.312-4.816-5.312-2.933 0-4.994 2.062-4.994 5.173 0 3.37 2.12 5.232 5.053 5.232Zm-2.16-6.421c.119-1.11.932-1.922 2.081-1.922 1.11 0 1.883.772 1.903 1.922h-3.984ZM160.876 21.304c3.013 0 4.658-1.645 4.975-4.201h-2.874c-.099 1.07-.713 1.982-2.001 1.982-1.309 0-2.2-1.21-2.2-2.993 0-1.942 1.03-2.933 2.259-2.933 1.209 0 1.803.872 1.883 1.882h2.873c-.218-2.358-1.823-4.142-4.776-4.142-2.874 0-5.153 1.903-5.153 5.193 0 3.25 1.923 5.212 5.014 5.212ZM172.067 21.146c.634 0 1.248-.1 1.526-.179v-2.18c-.218.04-.476.06-.694.06-1.05 0-1.427-.595-1.427-1.566v-3.805h2.339v-2.24h-2.339V7.788h-2.854v3.448h-2.1v2.24h2.1v4.201c0 2.3 1.15 3.469 3.449 3.469Z"></path>
                                </svg>
                            </span>
                        </button>
                    </div>
                    </div>
                    </div>
                    </div><!-- .register-steps-scroll -->

                    <div class="register-footer-bar reg-form-footer-bc via-wallet-enabled">
                        <button type="button" class="register-support-btn live-chat-adviser-bc" title="DESTEK İLE İLETİŞİME GEÇİN" onclick='window.open(<?= htmlspecialchars(json_encode((string) ($siteContactLinks["live_support_url"] ?? (defined("LIVE_SUPPORT_URL") ? LIVE_SUPPORT_URL : "")), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>, "_blank")'>
                            <i class="sp-button-icon-bc bc-i-live-chat" aria-hidden="true"></i>
                            <span class="ellipsis">DESTEK İLE İLETİŞİME GEÇİN</span>
                        </button>
                    </div>
                </form>
            </div>
            </div>
            </div>
            </div>
        </div>
    </div>
</div>

<!-- Kayıt başarılı kutusu -->
<div class="modal fade" id="registerSuccessModal" tabindex="-1" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content register-success-box">
            <div class="register-success-body">
                <div class="register-success-icon">✓</div>
                <h4 class="register-success-title">Kayıt Başarılı</h4>
                <p class="register-success-text">Hesabınız oluşturuldu. Giriş yapabilirsiniz.</p>
                <button type="button" class="register-primary-btn register-success-ok" data-dismiss="modal" id="registerSuccessOk">TAMAM</button>
            </div>
        </div>
    </div>
</div>

