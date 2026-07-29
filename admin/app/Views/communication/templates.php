<?php

$settings = is_array($settings ?? null) ? $settings : [];
$flash = trim((string) ($flash ?? ''));
$emailSection = 'templates';
$resetPreviewHtml = (string) ($resetPreviewHtml ?? '');
$welcomePreviewHtml = (string) ($welcomePreviewHtml ?? '');
$depositApprovedPreviewHtml = (string) ($depositApprovedPreviewHtml ?? '');
$withdrawApprovedPreviewHtml = (string) ($withdrawApprovedPreviewHtml ?? '');
$previewUrl = (string) ($previewUrl ?? AdminAuth::url('/email/templates/preview'));

$placeholders = [
    '{{MEMBER_NAME}}' => 'Üyenin adı soyadı',
    '{{COMPANY_NAME}}' => 'Şirket / marka adı',
    '{{AMOUNT}}' => 'İşlem tutarı (yatırım/çekim)',
    '{{HEADING}}' => 'Mail başlığı',
    '{{BODY_HTML}}' => 'Mail metni',
    '{{CTA_LABEL}}' => 'Buton yazısı',
    '{{CTA_URL}}' => 'Buton bağlantısı',
    '{{SUPPORT_EMAIL}}' => 'Destek e-postası',
    '{{COMPANY_ADDRESS_HTML}}' => 'Footer adresi',
    '{{LOGO_HTML}}' => 'Logo alanı',
    '{{YEAR}}' => 'Yıl',
];
?>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">E-posta</span>
        <h1 class="hero-title">E-posta <span class="accent">şablonları</span></h1>
        <p class="hero-sub">Üyeye otomatik giden e-postaların içeriği ve görünümü.</p>
    </div>
</section>

<?php include __DIR__ . '/_nav.php'; ?>

<?php if ($flash !== ''): ?>
    <div class="alert <?= stripos($flash, 'kaydedilemedi') !== false ? 'alert--danger' : 'alert--success' ?>" style="display:block;margin-bottom:12px;white-space:pre-wrap;">
        <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<style>
.tpl-tabs{display:flex;flex-wrap:wrap;gap:8px}
.tpl-tab{padding:8px 14px;border:1px solid var(--border-soft);border-radius:999px;background:transparent;color:inherit;font-size:13px;font-weight:700;cursor:pointer;opacity:.7}
.tpl-tab:hover{opacity:1}
.tpl-tab.is-active{opacity:1;background:#850f83;border-color:#850f83;color:#fff}
.tpl-panel[hidden]{display:none}
.tpl-preview{margin-top:10px;border:1px solid var(--border-soft);border-radius:12px;overflow:hidden;background:#0a0719}
.tpl-preview-frame{display:block;width:100%;height:460px;border:0;background:#0a0719;transition:opacity .15s ease}
.tpl-hint-list{margin:8px 0 0;padding:0;list-style:none;display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:4px 16px}
.tpl-hint-list li{font-size:12px;color:var(--t-muted)}
.tpl-hint-list code{font-size:12px}
</style>

<form id="mailTemplatesForm" method="post" action="<?= htmlspecialchars(AdminAuth::url('/email/templates'), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

    <section class="card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Adım 1</span>
                <h2 class="card-title">Marka bilgileri</h2>
            </div>
        </div>
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
                <label class="field-label" for="company_address">Footer adresi</label>
                <textarea id="company_address" class="input" name="company_address" rows="2"><?= htmlspecialchars((string) ($settings['company_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                <div class="field-help">Tüm otomatik e-postaların alt kısmında görünür.</div>
            </div>
        </div>
    </section>

    <section class="card" style="margin-top:16px;">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Adım 2</span>
                <h2 class="card-title">Şablon içeriği</h2>
            </div>
            <div class="tpl-tabs" role="tablist">
                <button class="tpl-tab is-active" type="button" role="tab" aria-selected="true" data-tpl-tab="reset">Şifre sıfırlama</button>
                <button class="tpl-tab" type="button" role="tab" aria-selected="false" data-tpl-tab="welcome">Kayıt başarılı</button>
                <button class="tpl-tab" type="button" role="tab" aria-selected="false" data-tpl-tab="deposit_approved">Yatırım onaylandı</button>
                <button class="tpl-tab" type="button" role="tab" aria-selected="false" data-tpl-tab="withdraw_approved">Çekim tamamlandı</button>
            </div>
        </div>

        <div class="tpl-panel" data-tpl-panel="reset">
            <div class="form-grid">
                <div class="field span-2">
                    <label class="field-label" for="reset_template_html">Şifre sıfırlama HTML şablonu</label>
                    <textarea id="reset_template_html" class="input" name="reset_template_html" rows="10" placeholder="Boş bırakırsan sistem varsayılan şablonu kullanılır."><?= htmlspecialchars((string) ($settings['reset_template_html'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    <div class="field-help">Boş bırakırsan hazır tasarım kullanılır; sağdaki önizleme her zaman gerçek maili gösterir.</div>
                </div>
            </div>
        </div>

        <div class="tpl-panel" data-tpl-panel="welcome" hidden>
            <div class="form-grid">
                <div class="field span-2">
                    <label class="field-label" for="welcome_template_html">Kayıt başarılı HTML şablonu</label>
                    <textarea id="welcome_template_html" class="input" name="welcome_template_html" rows="10" placeholder="Boş bırakırsan sistem varsayılan şablonu kullanılır."><?= htmlspecialchars((string) ($settings['welcome_template_html'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    <div class="field-help">Yeni üye kaydı tamamlandığında otomatik gönderilir.</div>
                </div>
            </div>
        </div>

        <div class="tpl-panel" data-tpl-panel="deposit_approved" hidden>
            <div class="form-grid">
                <div class="field span-2">
                    <label class="field-label" for="deposit_approved_template_html">Yatırım onaylandı HTML şablonu</label>
                    <textarea id="deposit_approved_template_html" class="input" name="deposit_approved_template_html" rows="10" placeholder="Boş bırakırsan sistem varsayılan şablonu kullanılır."><?= htmlspecialchars((string) ($settings['deposit_approved_template_html'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    <div class="field-help">Para yatırma işlemi onaylandığında üyeye otomatik gönderilir. Tutar için <code>{{AMOUNT}}</code> kullanabilirsiniz.</div>
                </div>
            </div>
        </div>

        <div class="tpl-panel" data-tpl-panel="withdraw_approved" hidden>
            <div class="form-grid">
                <div class="field span-2">
                    <label class="field-label" for="withdraw_approved_template_html">Çekim tamamlandı HTML şablonu</label>
                    <textarea id="withdraw_approved_template_html" class="input" name="withdraw_approved_template_html" rows="10" placeholder="Boş bırakırsan sistem varsayılan şablonu kullanılır."><?= htmlspecialchars((string) ($settings['withdraw_approved_template_html'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    <div class="field-help">Para çekme işlemi tamamlandığında üyeye otomatik gönderilir. Tutar için <code>{{AMOUNT}}</code> kullanabilirsiniz.</div>
                </div>
            </div>
        </div>

        <details style="margin-top:4px;">
            <summary class="field-label" style="cursor:pointer;">Kullanılabilir alanlar</summary>
            <ul class="tpl-hint-list">
                <?php foreach ($placeholders as $token => $label): ?>
                    <li><code><?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?></code> — <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </details>

        <div class="form-actions">
            <button class="btn btn--ghost" type="button" id="mailPreviewRefresh">Önizlemeyi yenile</button>
            <span class="spacer"></span>
            <button class="btn btn--primary" type="submit">Kaydet</button>
        </div>
    </section>

    <section class="card" style="margin-top:16px;">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Önizleme</span>
                <h2 class="card-title" id="mailPreviewTitle">Şifre sıfırlama maili</h2>
            </div>
        </div>
        <div class="tpl-preview">
            <iframe
                id="mailPreviewFrame"
                class="tpl-preview-frame"
                title="E-posta önizlemesi"
                sandbox="allow-same-origin"
                srcdoc="<?= htmlspecialchars($resetPreviewHtml, ENT_QUOTES, 'UTF-8') ?>"
            ></iframe>
        </div>
        <div class="field-help" style="margin-top:8px;">Örnek üye adıyla oluşturulur; kaydetmeden önce değişikliği görebilirsin.</div>
    </section>
</form>

<script>
(function () {
    var form = document.getElementById('mailTemplatesForm');
    var frame = document.getElementById('mailPreviewFrame');
    if (!form || !frame) return;

    var previewUrl = <?= json_encode($previewUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var cache = {
        reset: <?= json_encode($resetPreviewHtml, JSON_UNESCAPED_UNICODE) ?>,
        welcome: <?= json_encode($welcomePreviewHtml, JSON_UNESCAPED_UNICODE) ?>,
        deposit_approved: <?= json_encode($depositApprovedPreviewHtml, JSON_UNESCAPED_UNICODE) ?>,
        withdraw_approved: <?= json_encode($withdrawApprovedPreviewHtml, JSON_UNESCAPED_UNICODE) ?>
    };
    var titles = {
        reset: 'Şifre sıfırlama maili',
        welcome: 'Kayıt başarılı maili',
        deposit_approved: 'Yatırım onaylandı maili',
        withdraw_approved: 'Çekim tamamlandı maili'
    };
    var titleEl = document.getElementById('mailPreviewTitle');
    var active = 'reset';

    function activate(type) {
        active = type;
        form.querySelectorAll('[data-tpl-tab]').forEach(function (tab) {
            var isActive = tab.getAttribute('data-tpl-tab') === type;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        form.querySelectorAll('[data-tpl-panel]').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-tpl-panel') !== type;
        });
        if (titleEl) titleEl.textContent = titles[type] || titles.reset;
        frame.srcdoc = cache[type] || '';
    }

    function refresh() {
        var type = active;
        var data = new FormData(form);
        data.set('template_type', type);

        frame.style.opacity = '0.5';
        fetch(previewUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (response) {
                return response.text().then(function (html) {
                    if (!response.ok) throw new Error(html || ('HTTP ' + response.status));
                    cache[type] = html;
                    if (active === type) frame.srcdoc = html;
                });
            })
            .catch(function (error) {
                frame.srcdoc = '<!DOCTYPE html><html lang="tr"><body style="font-family:Arial,sans-serif;padding:24px;color:#b00020;">Önizleme alınamadı: '
                    + String(error && error.message ? error.message : error) + '</body></html>';
            })
            .finally(function () {
                frame.style.opacity = '1';
            });
    }

    form.querySelectorAll('[data-tpl-tab]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            activate(String(tab.getAttribute('data-tpl-tab') || 'reset'));
        });
    });
    document.getElementById('mailPreviewRefresh').addEventListener('click', refresh);
})();
</script>
