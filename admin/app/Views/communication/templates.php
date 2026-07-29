<?php

$settings = is_array($settings ?? null) ? $settings : [];
$flash = trim((string) ($flash ?? ''));
$emailSection = 'templates';
$resetPreviewHtml = (string) ($resetPreviewHtml ?? '');
$welcomePreviewHtml = (string) ($welcomePreviewHtml ?? '');
$previewUrl = (string) ($previewUrl ?? AdminAuth::url('/email/templates/preview'));
?>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">E-posta</span>
        <h1 class="hero-title">E-posta <span class="accent">şablonları</span></h1>
        <p class="hero-sub">Şifre sıfırlama ve başarılı kayıt e-postalarının HTML şablonları.</p>
    </div>
</section>

<?php include __DIR__ . '/_nav.php'; ?>

<?php if ($flash !== ''): ?>
    <div class="alert <?= stripos($flash, 'kaydedilemedi') !== false ? 'alert--danger' : 'alert--success' ?>" style="display:block;margin-bottom:12px;white-space:pre-wrap;">
        <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<style>
.mail-template-preview-wrap{margin-top:12px;border:1px solid rgba(0,0,0,.08);border-radius:12px;overflow:hidden;background:#0a0719}
.mail-template-preview-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 12px;background:rgba(255,255,255,.04);border-bottom:1px solid rgba(255,255,255,.08)}
.mail-template-preview-head strong{color:#fff;font-size:13px}
.mail-template-preview-frame{width:100%;height:520px;border:0;background:#0a0719;display:block}
.mail-template-preview-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
</style>

<section class="card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">Şablon</span>
            <h2 class="card-title">Otomatik üye e-postaları</h2>
        </div>
    </div>
    <form id="mailTemplatesForm" method="post" action="<?= htmlspecialchars(AdminAuth::url('/email/templates'), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-grid">
            <div class="field">
                <label class="field-label" for="company_name">Şirket / marka adı</label>
                <input id="company_name" class="input" type="text" name="company_name" placeholder="Vegasroyalspin" value="<?= htmlspecialchars((string) ($settings['company_name'] ?? 'Vegasroyalspin'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="field">
                <label class="field-label" for="support_email">Destek e-postası</label>
                <input id="support_email" class="input" type="email" name="support_email" placeholder="support@vegasroyalspin.com" value="<?= htmlspecialchars((string) ($settings['support_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="field span-2">
                <label class="field-label" for="company_address">Adres (footer)</label>
                <textarea id="company_address" class="input" name="company_address" rows="3"><?= htmlspecialchars((string) ($settings['company_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="field span-2">
                <label class="field-label" for="reset_template_html">Şifre sıfırlama HTML şablonu (opsiyonel)</label>
                <textarea id="reset_template_html" class="input" name="reset_template_html" rows="14" placeholder="Boş bırakırsan sistem varsayılan şablonunu kullanır."><?= htmlspecialchars((string) ($settings['reset_template_html'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                <div class="field-help">Placeholder: {{PREHEADER}}, {{HEADING}}, {{BODY_HTML}}, {{CTA_LABEL}}, {{CTA_URL}}, {{COMPANY_NAME}}, {{MEMBER_NAME}}, {{SUPPORT_EMAIL}}, {{SUPPORT_EMAIL_LINK}}, {{YEAR}}, {{COMPANY_ADDRESS_HTML}}, {{LOGO_HTML}}, {{FALLBACK_URL}}</div>
                <div class="mail-template-preview-actions">
                    <button class="btn" type="button" data-mail-preview="reset">Şifre sıfırlama önizle</button>
                </div>
                <div class="mail-template-preview-wrap">
                    <div class="mail-template-preview-head">
                        <strong>Şifre sıfırlama önizlemesi</strong>
                    </div>
                    <iframe
                        id="resetPreviewFrame"
                        class="mail-template-preview-frame"
                        title="Şifre sıfırlama önizlemesi"
                        sandbox="allow-same-origin"
                        srcdoc="<?= htmlspecialchars($resetPreviewHtml, ENT_QUOTES, 'UTF-8') ?>"
                    ></iframe>
                </div>
            </div>
            <div class="field span-2">
                <label class="field-label" for="welcome_template_html">Başarılı kayıt HTML şablonu (opsiyonel)</label>
                <textarea id="welcome_template_html" class="input" name="welcome_template_html" rows="14" placeholder="Boş bırakırsan sistem varsayılan hoş geldiniz şablonunu kullanır."><?= htmlspecialchars((string) ($settings['welcome_template_html'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                <div class="field-help">Placeholder: {{PREHEADER}}, {{HEADING}}, {{BODY_HTML}}, {{CTA_LABEL}}, {{CTA_URL}}, {{COMPANY_NAME}}, {{MEMBER_NAME}}, {{SUPPORT_EMAIL}}, {{SUPPORT_EMAIL_LINK}}, {{YEAR}}, {{COMPANY_ADDRESS_HTML}}, {{LOGO_HTML}}, {{FALLBACK_URL}}</div>
                <div class="mail-template-preview-actions">
                    <button class="btn" type="button" data-mail-preview="welcome">Kayıt maili önizle</button>
                </div>
                <div class="mail-template-preview-wrap">
                    <div class="mail-template-preview-head">
                        <strong>Başarılı kayıt önizlemesi</strong>
                    </div>
                    <iframe
                        id="welcomePreviewFrame"
                        class="mail-template-preview-frame"
                        title="Başarılı kayıt önizlemesi"
                        sandbox="allow-same-origin"
                        srcdoc="<?= htmlspecialchars($welcomePreviewHtml, ENT_QUOTES, 'UTF-8') ?>"
                    ></iframe>
                </div>
            </div>
        </div>
        <div class="form-actions">
            <span class="spacer"></span>
            <button class="btn btn--primary" type="submit">Kaydet</button>
        </div>
    </form>
</section>

<script>
(function () {
    var form = document.getElementById('mailTemplatesForm');
    if (!form) return;

    var previewUrl = <?= json_encode($previewUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var frames = {
        reset: document.getElementById('resetPreviewFrame'),
        welcome: document.getElementById('welcomePreviewFrame')
    };

    function refreshPreview(type) {
        var frame = frames[type];
        if (!frame) return;

        var data = new FormData(form);
        data.set('template_type', type);

        frame.style.opacity = '0.55';
        fetch(previewUrl, {
            method: 'POST',
            body: data,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.text().then(function (html) {
                if (!response.ok) {
                    throw new Error(html || ('HTTP ' + response.status));
                }
                frame.srcdoc = html;
            });
        }).catch(function (error) {
            frame.srcdoc = '<!DOCTYPE html><html lang="tr"><body style="font-family:Arial,sans-serif;padding:24px;color:#b00020;">Önizleme alınamadı: '
                + String(error && error.message ? error.message : error)
                + '</body></html>';
        }).finally(function () {
            frame.style.opacity = '1';
        });
    }

    form.querySelectorAll('[data-mail-preview]').forEach(function (button) {
        button.addEventListener('click', function () {
            refreshPreview(String(button.getAttribute('data-mail-preview') || 'reset'));
        });
    });
})();
</script>
