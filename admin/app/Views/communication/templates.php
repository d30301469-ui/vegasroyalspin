<?php

$settings = is_array($settings ?? null) ? $settings : [];
$flash = trim((string) ($flash ?? ''));
$emailSection = 'templates';
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

<section class="card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">Şablon</span>
            <h2 class="card-title">Otomatik üye e-postaları</h2>
        </div>
    </div>
    <form method="post" action="<?= htmlspecialchars(AdminAuth::url('/email/templates'), ENT_QUOTES, 'UTF-8') ?>">
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
                <textarea id="reset_template_html" class="input" name="reset_template_html" rows="18" placeholder="Boş bırakırsan sistem varsayılan şablonunu kullanır."><?= htmlspecialchars((string) ($settings['reset_template_html'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                <div class="field-help">Placeholder: {{PREHEADER}}, {{HEADING}}, {{BODY_HTML}}, {{CTA_LABEL}}, {{CTA_URL}}, {{COMPANY_NAME}}, {{MEMBER_NAME}}, {{SUPPORT_EMAIL}}, {{SUPPORT_EMAIL_LINK}}, {{YEAR}}, {{COMPANY_ADDRESS_HTML}}, {{LOGO_HTML}}, {{FALLBACK_URL}}</div>
            </div>
            <div class="field span-2">
                <label class="field-label" for="welcome_template_html">Başarılı kayıt HTML şablonu (opsiyonel)</label>
                <textarea id="welcome_template_html" class="input" name="welcome_template_html" rows="18" placeholder="Boş bırakırsan sistem varsayılan hoş geldiniz şablonunu kullanır."><?= htmlspecialchars((string) ($settings['welcome_template_html'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                <div class="field-help">Placeholder: {{PREHEADER}}, {{HEADING}}, {{BODY_HTML}}, {{CTA_LABEL}}, {{CTA_URL}}, {{COMPANY_NAME}}, {{MEMBER_NAME}}, {{SUPPORT_EMAIL}}, {{SUPPORT_EMAIL_LINK}}, {{YEAR}}, {{COMPANY_ADDRESS_HTML}}, {{LOGO_HTML}}, {{FALLBACK_URL}}</div>
            </div>
        </div>
        <div class="form-actions">
            <span class="spacer"></span>
            <button class="btn btn--primary" type="submit">Kaydet</button>
        </div>
    </form>
</section>
