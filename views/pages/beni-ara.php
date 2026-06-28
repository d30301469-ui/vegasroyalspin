<?php
$formData = $formData ?? [];
$callMeSuccessMessage = $callMeSuccessMessage ?? '';
$callMeProfileReadonly = $callMeProfileReadonly ?? [
    'ad'            => false,
    'kullanici_adi' => false,
    'telefon'       => false,
];
$esc = function ($key) use ($formData) {
    return htmlspecialchars($formData[$key] ?? '', ENT_QUOTES, 'UTF-8');
};
$ro = static function (string $field) use ($callMeProfileReadonly): string {
    return !empty($callMeProfileReadonly[$field]) ? ' readonly' : '';
};
?>
<?php include VIEW_PATH . '/layouts/head.php'; ?>
<?php include VIEW_PATH . '/partials/header.php'; ?>

<section class="mainWrap beni-ara-page py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-10 col-xl-9">
                <div class="beni-ara-card">
                    <div class="beni-ara-header">
                        <h1 class="beni-ara-title">Beni Ara</h1>
                        <p class="beni-ara-lead">Geri arama talebinizi bırakın, en kısa sürede sizinle iletişime geçelim.</p>
                    </div>

                    <?php if ($gonderildi): ?>
                        <div class="beni-ara-alert beni-ara-alert-success">
                            <?= htmlspecialchars($callMeSuccessMessage !== '' ? $callMeSuccessMessage : 'Talebiniz alındı. En kısa sürede sizinle iletişime geçeceğiz.', ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php else: ?>
                        <div id="beniAraInteractive">
                        <div id="beniAraFormError" class="beni-ara-alert beni-ara-alert-danger" role="alert"<?= ($hata ?? '') === '' ? ' hidden' : '' ?>><?= ($hata ?? '') !== '' ? htmlspecialchars($hata, ENT_QUOTES, 'UTF-8') : '' ?></div>

                        <form id="beniAraForm" method="post" action="/beni-ara" class="beni-ara-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                            <div class="row beni-ara-form-row">
                                <div class="col-12 col-md-6 beni-ara-field">
                                    <div class="form-group">
                                        <label class="form-control-label-bc inputs">
                                            <input type="text" class="form-control-input-bc" id="ad" name="ad" required
                                                   value="<?= $esc('ad') ?>"
                                                   placeholder="Adınız"<?= $ro('ad') ?>>
                                            <i class="form-control-input-stroke-bc"></i>
                                            <span class="form-control-title-bc ellipsis">Adınız *</span>
                                        </label>
                                        <div class="register-error-text" data-error-for="ad">Bu alan gerekli</div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6 beni-ara-field">
                                    <div class="form-group">
                                        <label class="form-control-label-bc inputs">
                                            <input type="text" class="form-control-input-bc" id="kullanici_adi" name="kullanici_adi"
                                                   value="<?= $esc('kullanici_adi') ?>"
                                                   placeholder="Kullanıcı adı"<?= $ro('kullanici_adi') ?>>
                                            <i class="form-control-input-stroke-bc"></i>
                                            <span class="form-control-title-bc ellipsis">Kullanıcı adınız (zorunlu değil)</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="row beni-ara-form-row">
                                <div class="col-12 col-md-6 beni-ara-field">
                                    <div class="form-group">
                                        <label class="form-control-label-bc inputs">
                                            <input type="tel" class="form-control-input-bc" id="telefon" name="telefon" required
                                                   value="<?= $esc('telefon') ?>"
                                                   placeholder="5XX XXX XX XX"<?= $ro('telefon') ?>>
                                            <i class="form-control-input-stroke-bc"></i>
                                            <span class="form-control-title-bc ellipsis">Telefon numaranız *</span>
                                        </label>
                                        <div class="register-error-text" data-error-for="telefon">Bu alan gerekli</div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6 beni-ara-field">
                                    <div class="form-group">
                                        <label class="form-control-label-bc inputs">
                                            <input type="text" class="form-control-input-bc" id="neden" name="neden" required
                                                   value="<?= $esc('neden') ?>"
                                                   placeholder="Örn: Hesap, para yatırma, bonus">
                                            <i class="form-control-input-stroke-bc"></i>
                                            <span class="form-control-title-bc ellipsis">Neden aranmak istediğinizi belirtiniz *</span>
                                        </label>
                                        <div class="register-error-text" data-error-for="neden">Bu alan gerekli</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row beni-ara-form-row">
                                <div class="col-12 beni-ara-field beni-ara-field-full">
                                    <div class="form-group">
                                        <label class="form-control-label-bc inputs beni-ara-textarea-wrap">
                                            <textarea class="form-control-input-bc beni-ara-textarea" id="mesaj" name="mesaj" rows="4"
                                                      placeholder="Kısaca ne hakkında aranmak istediğinizi yazınız"><?= $esc('mesaj') ?></textarea>
                                            <i class="form-control-input-stroke-bc"></i>
                                            <span class="form-control-title-bc ellipsis">Mesajınız (zorunlu değil)</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="beni-ara-actions">
                                <button type="submit" class="beni-ara-submit-btn">Gönder</button>
                            </div>
                        </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php include VIEW_PATH . '/partials/footer.php'; ?>
<script src="/assets/js/beni-ara.js" defer></script>
