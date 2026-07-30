<?php

$flash = trim((string) ($flash ?? ''));
$emailSection = 'send';
$oldMode = trim((string) ($oldMode ?? 'single'));
$oldToEmail = trim((string) ($oldToEmail ?? ''));
$memberEmailCount = (int) ($memberEmailCount ?? 0);
$oldSubject = trim((string) ($oldSubject ?? ''));
$oldBody = trim((string) ($oldBody ?? ''));
$isBulk = $oldMode === 'bulk';
$customTemplates = is_array($customTemplates ?? null) ? $customTemplates : [];
?>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">E-posta</span>
        <h1 class="hero-title">E-posta <span class="accent">gönder</span></h1>
        <p class="hero-sub">Tek alıcı: yalnızca girdiğiniz bir üyeye. Toplu: veritabanındaki tüm üyelere.</p>
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
                <div style="display:flex;flex-wrap:wrap;gap:10px;padding-top:6px;">
                    <label class="compose-mode-card" style="flex:1;min-width:220px;display:block;padding:12px 14px;border:1px solid rgba(0,0,0,.12);border-radius:12px;cursor:pointer;">
                        <span style="display:flex;align-items:center;gap:8px;font-weight:700;">
                            <input type="radio" name="send_mode" value="single" <?= !$isBulk ? 'checked' : '' ?> data-compose-mode>
                            Tek mail
                        </span>
                        <span class="field-help" style="display:block;margin:6px 0 0;">Sadece girdiğiniz bir üye e-postasına gider.</span>
                    </label>
                    <label class="compose-mode-card" style="flex:1;min-width:220px;display:block;padding:12px 14px;border:1px solid rgba(0,0,0,.12);border-radius:12px;cursor:pointer;">
                        <span style="display:flex;align-items:center;gap:8px;font-weight:700;">
                            <input type="radio" name="send_mode" value="bulk" <?= $isBulk ? 'checked' : '' ?> data-compose-mode>
                            Toplu mail
                        </span>
                        <span class="field-help" style="display:block;margin:6px 0 0;">Veritabanındaki tüm üyelere gider (<?= (int) $memberEmailCount ?> adres).</span>
                    </label>
                </div>
            </div>

            <div class="field span-2" data-compose-single <?= $isBulk ? 'hidden' : '' ?>>
                <label class="field-label" for="to_email">Üye e-posta</label>
                <input id="to_email" class="input" type="email" name="to_email" placeholder="uye@ornek.com" value="<?= htmlspecialchars($oldToEmail, ENT_QUOTES, 'UTF-8') ?>" <?= $isBulk ? 'disabled' : 'required' ?>>
                <p class="field-help">Tek mail modunda yalnızca bu adrese gönderilir.</p>
            </div>

            <div class="field span-2" data-compose-bulk <?= $isBulk ? '' : 'hidden' ?>>
                <label class="field-label">Alıcılar</label>
                <div style="padding:14px;border:1px solid rgba(0,0,0,.08);border-radius:12px;background:rgba(0,0,0,.02);">
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <span class="badge primary"><?= (int) $memberEmailCount ?> üye</span>
                        <strong>Tüm kullanıcılar</strong>
                    </div>
                    <p class="field-help" style="margin:8px 0 0;">Toplu mail, veritabanındaki e-postası olan bütün üyelere otomatik gönderilir. Her alıcıya isim-soyisim kişisel olarak yazılır.</p>
                </div>
            </div>

            <div class="field span-2">
                <label class="field-label" for="custom_template_id">Hazır şablon</label>
                <select id="custom_template_id" class="input" name="custom_template_id">
                    <option value="0">Şablon kullanma</option>
                    <?php foreach ($customTemplates as $template): ?>
                        <option
                            value="<?= (int) ($template['id'] ?? 0) ?>"
                            data-template-subject="<?= htmlspecialchars((string) ($template['subject'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <?= htmlspecialchars((string) ($template['name'] ?? 'Özel şablon'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-help">
                    Seçilen özel şablon hem tek mailde hem toplu mailde kullanılır, kayıtlı konusu otomatik uygulanır ve mesaj alanı zorunlu olmaz.
                </p>
            </div>

            <div class="field span-2">
                <label class="field-label" for="subject">Konu</label>
                <input id="subject" class="input" name="subject" type="text" required value="<?= htmlspecialchars($oldSubject, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="field span-2">
                <label class="field-label" for="body">
                    Mesaj
                    <span data-body-optional hidden style="margin-left:6px;font-weight:600;opacity:.75;">(isteğe bağlı)</span>
                </label>
                <textarea id="body" class="textarea" name="body" rows="10" required><?= htmlspecialchars($oldBody, ENT_QUOTES, 'UTF-8') ?></textarea>
                <p class="field-help">Her üyeye isim-soyisim otomatik eklenir (To: İsim Soyisim &lt;mail&gt;). İsterseniz metinde {{MEMBER_NAME}}, {{ISIM}}, {{SOYISIM}} kullanın; yoksa başına “Merhaba İsim Soyisim,” eklenir.</p>
                <p class="field-help" data-body-template-help hidden>Hazır şablon seçili: mesaj alanını boş bırakabilirsiniz, şablon içeriği gönderilir. Yazarsanız şablonun {{BODY_HTML}} alanına eklenir.</p>
            </div>
        </div>
        <div class="form-actions">
            <span class="spacer"></span>
            <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/email/inbox'), ENT_QUOTES, 'UTF-8') ?>">Vazgeç</a>
            <button class="btn btn--primary" type="submit" data-compose-submit>
                <?= $isBulk ? 'Tüm üyelere gönder' : 'Tek üye gönder' ?>
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
    var templateSelect = form.querySelector('#custom_template_id');
    var subjectInput = form.querySelector('#subject');
    var bodyInput = form.querySelector('#body');
    var bodyOptionalBadge = form.querySelector('[data-body-optional]');
    var bodyTemplateHelp = form.querySelector('[data-body-template-help]');

    function syncMode() {
        var mode = 'single';
        Array.prototype.forEach.call(modes, function (input) {
            if (input.checked) mode = input.value;
        });
        var isBulk = mode === 'bulk';
        if (singleWrap) singleWrap.hidden = isBulk;
        if (bulkWrap) bulkWrap.hidden = !isBulk;
        if (toEmail) {
            toEmail.disabled = isBulk;
            if (isBulk) {
                toEmail.removeAttribute('required');
            } else {
                toEmail.setAttribute('required', 'required');
            }
        }
        if (submitBtn) {
            submitBtn.textContent = isBulk ? 'Tüm üyelere gönder' : 'Tek üye gönder';
        }
    }

    function syncTemplate() {
        var usesTemplate = !!templateSelect && parseInt(templateSelect.value, 10) > 0;
        if (bodyInput) {
            if (usesTemplate) {
                bodyInput.removeAttribute('required');
            } else {
                bodyInput.setAttribute('required', 'required');
            }
        }
        if (bodyOptionalBadge) bodyOptionalBadge.hidden = !usesTemplate;
        if (bodyTemplateHelp) bodyTemplateHelp.hidden = !usesTemplate;
    }

    Array.prototype.forEach.call(modes, function (input) {
        input.addEventListener('change', syncMode);
    });
    if (templateSelect) {
        templateSelect.addEventListener('change', function () {
            var option = templateSelect.options[templateSelect.selectedIndex];
            var templateSubject = option ? String(option.getAttribute('data-template-subject') || '') : '';
            if (subjectInput && templateSubject !== '') subjectInput.value = templateSubject;
            syncTemplate();
        });
    }
    syncMode();
    syncTemplate();
})();
</script>
