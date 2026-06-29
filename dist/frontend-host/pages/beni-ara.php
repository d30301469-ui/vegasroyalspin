<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../views/layouts/head_full.php';
include __DIR__ . '/../views/partials/header.php';

$gonderildi = false;
$hata = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
        !hash_equals((string) $_SESSION['csrf_token'], (string) $_POST['csrf_token'])
    require_once __DIR__ . '/../config/frontend_session.php';
    metropol_frontend_session_start();
        $hata = 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $ad = trim($_POST['ad'] ?? '');
        $kullanici_adi = trim($_POST['kullanici_adi'] ?? '');
        $telefon = trim($_POST['telefon'] ?? '');
        $neden = trim($_POST['neden'] ?? '');
        $mesaj = trim($_POST['mesaj'] ?? '');

        if (empty($ad)) {
            $hata = 'Adınızı giriniz.';
        } elseif (empty($telefon)) {
            $hata = 'Telefon numaranızı giriniz.';
        } elseif (empty($neden)) {
            $hata = 'Neden aranmak istediğinizi belirtiniz.';
        } else {
            $gonderildi = true;
            // İsteğe bağlı: veritabanına kaydet veya e-posta gönder
            // require_once __DIR__ . '/config/database.php';
            // $stmt = $mainDb->prepare("INSERT INTO beni_ara (ad, kullanici_adi, telefon, neden, mesaj, tarih) VALUES (?,?,?,?,?, NOW())");
            // $stmt->execute([$ad, $kullanici_adi, $telefon, $neden, $mesaj]);
        }
    }
}
?>
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
                            Talebiniz alındı. En kısa sürede sizinle iletişime geçeceğiz.
                        </div>
                    <?php else: ?>
                        <?php if ($hata): ?>
                            <div class="beni-ara-alert beni-ara-alert-danger"><?php echo htmlspecialchars($hata); ?></div>
                        <?php endif; ?>

                        <form id="beniAraForm" method="post" action="/beni-ara" class="beni-ara-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                            <div class="row beni-ara-form-row">
                                <div class="col-12 col-md-6 beni-ara-field">
                                    <div class="form-group">
                                        <label class="form-control-label-bc inputs">
                                            <input type="text" class="form-control-input-bc" id="ad" name="ad" required
                                                   value="<?php echo htmlspecialchars($_POST['ad'] ?? ''); ?>"
                                                   placeholder="Adınız">
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
                                                   value="<?php echo htmlspecialchars($_POST['kullanici_adi'] ?? ''); ?>"
                                                   placeholder="Kullanıcı adı">
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
                                                   value="<?php echo htmlspecialchars($_POST['telefon'] ?? ''); ?>"
                                                   placeholder="5XX XXX XX XX">
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
                                                   value="<?php echo htmlspecialchars($_POST['neden'] ?? ''); ?>"
                                                   placeholder="Örn: Hesap, para yatırma, bonus">
                                            <i class="form-control-input-stroke-bc"></i>
                                            <span class="form-control-title-bc ellipsis">Neden aranmak istiyorsunuz? *</span>
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
                                                      placeholder="Kısaca ne hakkında aranmak istediğinizi yazınız"><?php echo htmlspecialchars($_POST['mesaj'] ?? ''); ?></textarea>
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
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
<script src="/assets/js/beni-ara.js"></script>
