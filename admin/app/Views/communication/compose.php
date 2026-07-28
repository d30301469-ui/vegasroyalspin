<?php

$flash = trim((string) ($flash ?? ''));
$emailSection = 'send';
$oldMode = trim((string) ($oldMode ?? 'single'));
$oldToEmail = trim((string) ($oldToEmail ?? ''));
$oldToEmails = trim((string) ($oldToEmails ?? ''));
$oldSubject = trim((string) ($oldSubject ?? ''));
$oldBody = trim((string) ($oldBody ?? ''));
$oldIncludeAllMembers = !empty($oldIncludeAllMembers);
?>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">E-posta</span>
        <h1 class="hero-title">E-posta <span class="accent">gönder</span></h1>
        <p class="hero-sub">Tek alıcı veya toplu liste ile SMTP üzerinden e-posta gönderir; üye bulunan adreslere gelen kutusu mesajı da düşer.</p>
    </div>
</section>

<?php include __DIR__ . '/_nav.php'; ?>

<?php if ($flash !== ''): ?>
    <?php
    $flashLower = mb_strtolower($flash, 'UTF-8');
    $flashDanger = str_contains($flashLower, 'gönderilemedi')
        || str_contains($flashLower, 'gonderilemedi')
        || str_contains($flashLower, 'hata')
        || preg_match('/\b0\s+başarılı\b/u', $flashLower) === 1
        || preg_match('/\b0\s+basarili\b/u', $flashLower) === 1;
    $flashOk = !$flashDanger && (
        str_contains($flashLower, 'gönderildi')
        || str_contains($flashLower, 'gonderildi')
        || str_contains($flashLower, 'başarılı')
        || str_contains($flashLower, 'basarili')
        || str_contains($flashLower, 'özet')
        || str_contains($flashLower, 'ozet')
    );
    ?>
    <div class="alert <?= $flashOk ? 'alert--success' : 'alert--danger' ?>" style="display:block;margin-bottom:12px;white-space:pre-wrap;">
        <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<section class="card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">Yeni</span>
            <h2 class="card-title">Mesaj oluştur</h2>
        </div>
    </div>
    <form id="composeForm" method="post" action="<?= htmlspecialchars(AdminAuth::url('/email/send'), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-grid">
            <div class="field span-2">
                <label class="field-label">Gönderim tipi</label>
                <div style="display:flex;flex-wrap:wrap;gap:14px;padding-top:6px;">
                    <label style="display:inline-flex;align-items:center;gap:8px;font-weight:600;cursor:pointer;">
                        <input type="radio" name="send_mode" value="single" <?= $oldMode !== 'bulk' ? 'checked' : '' ?> data-compose-mode>
                        Tek alıcı
                    </label>
                    <label style="display:inline-flex;align-items:center;gap:8px;font-weight:600;cursor:pointer;">
                        <input type="radio" name="send_mode" value="bulk" <?= $oldMode === 'bulk' ? 'checked' : '' ?> data-compose-mode>
                        Toplu gönderim
                    </label>
                </div>
            </div>

            <div class="field span-2" data-compose-single <?= $oldMode === 'bulk' ? 'hidden' : '' ?>>
                <label class="field-label" for="to_email">Alıcı e-posta</label>
                <input id="to_email" class="input" type="email" name="to_email" placeholder="uye@ornek.com" value="<?= htmlspecialchars($oldToEmail, ENT_QUOTES, 'UTF-8') ?>" <?= $oldMode === 'bulk' ? '' : 'required' ?>>
            </div>

            <div class="field span-2" data-compose-bulk <?= $oldMode === 'bulk' ? '' : 'hidden' ?>>
                <label class="field-label" for="to_emails">Alıcı listesi</label>
                <textarea id="to_emails" class="textarea" name="to_emails" rows="6" placeholder="her satira bir e-posta&#10;veya virgul / noktali virgul ile ayirin"><?= htmlspecialchars($oldToEmails, ENT_QUOTES, 'UTF-8') ?></textarea>
                <p class="field-help">Maksimum 200 adres. Geçersiz satırlar atlanır. Aynı adres bir kez gönderilir.</p>
                <label style="display:inline-flex;align-items:center;gap:8px;margin-top:10px;font-weight:600;cursor:pointer;">
                    <input type="checkbox" name="include_all_members" value="1" <?= $oldIncludeAllMembers ? 'checked' : '' ?>>
                    Banlı olmayan tüm üyelerin e-postasını da ekle
                </label>
            </div>

            <div class="field span-2">
                <label class="field-label" for="subject">Konu</label>
                <input id="subject" class="input" name="subject" type="text" required value="<?= htmlspecialchars($oldSubject, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="field span-2">
                <label class="field-label" for="body">Mesaj</label>
                <textarea id="body" class="textarea" name="body" rows="10" required><?= htmlspecialchars($oldBody, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
        </div>
        <div class="form-actions">
            <span class="spacer"></span>
            <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/email/inbox'), ENT_QUOTES, 'UTF-8') ?>">Vazgeç</a>
            <button class="btn btn--primary" type="submit" data-compose-submit>
                <?= $oldMode === 'bulk' ? 'Toplu gönder' : 'Gönder' ?>
            </button>
        </div>
    </form>
</section>

<script>
(function () {
    var form = document.getElementById('composeForm');
    if (!form) return;
    var singleWrap = form.querySelector('[data-compose-single]');
    var bulkWrap = form.querySelector('[data-compose-bulk]');
    var toEmail = form.querySelector('#to_email');
    var submitBtn = form.querySelector('[data-compose-submit]');
    var modes = form.querySelectorAll('[data-compose-mode]');

    function syncMode() {
        var mode = 'single';
        Array.prototype.forEach.call(modes, function (input) {
            if (input.checked) mode = input.value;
        });
        var isBulk = mode === 'bulk';
        if (singleWrap) singleWrap.hidden = isBulk;
        if (bulkWrap) bulkWrap.hidden = !isBulk;
        if (toEmail) {
            if (isBulk) {
                toEmail.removeAttribute('required');
            } else {
                toEmail.setAttribute('required', 'required');
            }
        }
        if (submitBtn) {
            submitBtn.textContent = isBulk ? 'Toplu gönder' : 'Gönder';
        }
    }

    Array.prototype.forEach.call(modes, function (input) {
        input.addEventListener('change', syncMode);
    });
    syncMode();
})();
</script>
