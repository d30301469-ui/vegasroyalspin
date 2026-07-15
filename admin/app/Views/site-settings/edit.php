<?php

$row = is_array($row ?? null) ? $row : [];
$sections = is_array($sections ?? null) ? $sections : [];
$section = (string) ($section ?? 'general');
$activeSection = $sections[$section] ?? ['label' => 'Ayarlar', 'caption' => '', 'fields' => []];
$fields = is_array($activeSection['fields'] ?? null) ? $activeSection['fields'] : [];
$flash = trim((string) ($flash ?? ''));
$error = trim((string) ($error ?? ''));
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$fieldValue = static function (array $field) use ($row): string {
    $name = (string) ($field['name'] ?? '');
    $value = $row[$name] ?? '';
    if (($field['type'] ?? '') === 'checkbox') {
        return !empty($value) ? '1' : '0';
    }

    return (string) $value;
};

// Marka görselleri
$brandingData = [
    'logo_url' => (string) ($row['logo_url'] ?? ''),
    'favicon_url' => (string) ($row['favicon_url'] ?? ''),
    'manifest_url' => (string) ($row['manifest_url'] ?? ''),
    'og_image_url' => (string) ($row['og_image_url'] ?? ''),
];
$brandingDataJson = json_encode($brandingData);
?>
<style>
    .site-settings-shell { display: grid; grid-template-columns: 240px minmax(0, 1fr); gap: 18px; align-items: start; }
    .site-settings-rail { background: var(--bg-card); border: 1px solid var(--border-soft); border-radius: 16px; padding: 12px; }
    .site-settings-rail-label { color: var(--t-light); font-size: 11px; font-weight: 800; letter-spacing: .08em; margin: 4px 8px 10px; text-transform: uppercase; }
    .site-settings-link { align-items: center; border-radius: 12px; color: var(--t-base); display: flex; flex-direction: column; gap: 2px; padding: 10px 12px; text-decoration: none; transition: background .15s ease; }
    .site-settings-link:hover { background: color-mix(in srgb, var(--primary-soft) 50%, transparent); }
    .site-settings-link.is-active { background: color-mix(in srgb, var(--primary-soft) 72%, var(--bg-card)); box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--primary) 24%, transparent); }
    .site-settings-link-title { font-size: 13px; font-weight: 800; }
    .site-settings-link-caption { color: var(--t-muted); font-size: 11px; line-height: 1.35; }
    .site-settings-panel { background: var(--bg-card); border: 1px solid var(--border-soft); border-radius: 16px; padding: 20px; }
    .site-settings-panel-head { border-bottom: 1px solid var(--border-soft); margin-bottom: 18px; padding-bottom: 14px; }
    .site-settings-panel-title { font-size: 18px; font-weight: 900; margin: 0 0 4px; }
    .site-settings-panel-caption { color: var(--t-muted); font-size: 13px; margin: 0; }
    .site-settings-grid { display: grid; gap: 14px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .site-settings-grid .field.span-2 { grid-column: 1 / -1; }
    .site-settings-help { color: var(--t-muted); font-size: 12px; line-height: 1.45; margin-top: 6px; }
    .site-settings-actions { border-top: 1px solid var(--border-soft); display: flex; gap: 10px; justify-content: flex-end; margin-top: 18px; padding-top: 16px; }
    
    /* Branding Preview Styles */
    .branding-preview { background: color-mix(in srgb, var(--primary-soft) 12%, transparent); border: 1px solid var(--border-soft); border-radius: 12px; margin-bottom: 20px; overflow: hidden; }
    .branding-preview-head { align-items: center; display: flex; gap: 8px; padding: 12px 16px; border-bottom: 1px solid var(--border-soft); background: color-mix(in srgb, var(--primary-soft) 24%, transparent); }
    .branding-preview-title { color: var(--t-base); flex: 1; font-size: 13px; font-weight: 800; margin: 0; text-transform: uppercase; letter-spacing: .06em; }
    .branding-preview-body { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; padding: 16px; }
    .branding-item { background: var(--bg-surface); border: 1px solid var(--border-soft); border-radius: 8px; overflow: hidden; padding: 12px; }
    .branding-item-label { color: var(--t-light); display: block; font-size: 11px; font-weight: 700; margin-bottom: 8px; text-transform: uppercase; letter-spacing: .06em; }
    .branding-item-preview { align-items: center; background: #fff; border: 1px solid var(--border-soft); border-radius: 6px; display: flex; height: 80px; justify-content: center; margin-bottom: 10px; overflow: hidden; }
    .branding-item-preview.empty { background: var(--bg-base); color: var(--t-muted); font-size: 12px; }
    .branding-item-preview img { max-height: 100%; max-width: 100%; object-fit: contain; }
    .branding-item-url { background: var(--bg-surface); border: 1px solid var(--border-soft); border-radius: 4px; color: var(--t-base); font-family: 'Monaco', 'Menlo', 'Courier', monospace; font-size: 11px; margin-bottom: 8px; overflow: auto; padding: 8px; word-break: break-all; }
    .branding-item-url.empty { background: var(--bg-base); color: var(--t-muted); font-style: italic; }
    .branding-item-actions { display: flex; gap: 6px; }
    .branding-item-btn { align-items: center; border: 1px solid var(--border-soft); border-radius: 4px; background: var(--bg-base); color: var(--t-muted); cursor: pointer; display: inline-flex; flex: 1; font-size: 11px; gap: 4px; justify-content: center; padding: 6px 8px; text-decoration: none; transition: all .15s ease; }
    .branding-item-btn:hover { background: color-mix(in srgb, var(--primary-soft) 32%, transparent); color: var(--t-base); border-color: var(--primary-soft); }
    .branding-preview-error { color: var(--t-error); font-size: 12px; text-align: center; padding: 16px; }
    .branding-preview-loading { color: var(--t-muted); font-size: 12px; padding: 16px; text-align: center; }
    
    @media (max-width: 900px) {
        .site-settings-shell { grid-template-columns: 1fr; }
        .site-settings-grid { grid-template-columns: 1fr; }
        .branding-preview-body { grid-template-columns: 1fr; }
    }
</style>

<section class="admin-surface">
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Site Yapısı · Ayarlar</span>
        <h1 class="hero-title">Site <span class="accent">ayarları</span></h1>
        <p class="hero-sub">Ayarlar bölümlere ayrılmıştır. Her modülü ayrı kaydedebilirsiniz.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn--primary" type="submit" form="siteSettingsForm">Bölümü Kaydet</button>
    </div>
</section>

<?php if ($error !== ''): ?>
    <div class="alert danger admin-alert-spaced">
        <span class="ico"><svg viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg></span>
        <div class="body"><?= $h($error) ?></div>
    </div>
<?php endif; ?>

<?php if ($section === 'branding'): ?>
<section class="branding-preview" id="brandingPreview">
    <div class="branding-preview-head">
        <h3 class="branding-preview-title">Mevcut Marka Görselleri</h3>
        <button type="button" class="btn btn--sm btn--ghost" id="refreshBrandingBtn" style="gap: 4px; display: inline-flex; align-items: center;">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8M21 3v5h-5M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16M3 21v-5h5"/></svg>
            Yenile
        </button>
    </div>
    <div class="branding-preview-body" id="brandingBody">
        <div class="branding-preview-loading">Marka görselleri yükleniyor...</div>
    </div>
</section>

<script>
(function() {
    const brandingData = <?php echo $brandingDataJson; ?>;
    const brandingBody = document.getElementById('brandingBody');
    const refreshBtn = document.getElementById('refreshBrandingBtn');
    
    function formatUrl(url) {
        if (!url) return '(Boş)';
        if (url.startsWith('http://') || url.startsWith('https://')) {
            return url;
        }
        return window.location.origin + url;
    }
    
    function copyToClipboard(text, btn) {
        navigator.clipboard.writeText(text).then(() => {
            const original = btn.textContent;
            btn.textContent = 'Kopyalandı!';
            setTimeout(() => { btn.textContent = original; }, 2000);
        }).catch(() => {
            alert('Kopyalama başarısız');
        });
    }
    
    function renderBrandingItem(label, urlKey) {
        const url = brandingData[urlKey] || '';
        const fullUrl = formatUrl(url);
        const isImage = ['logo_url', 'favicon_url', 'og_image_url'].includes(urlKey);
        
        return `
            <div class="branding-item">
                <label class="branding-item-label">${label}</label>
                ${isImage ? `
                    <div class="branding-item-preview ${url ? '' : 'empty'}">
                        ${url ? `<img src="${url}" alt="${label}" onerror="this.parentElement.textContent='Yükleme hatası'">` : 'Görsel yok'}
                    </div>
                ` : ''}
                <div class="branding-item-url ${url ? '' : 'empty'}">${url ? url : '(URL belirtilmedi)'}</div>
                <div class="branding-item-actions">
                    ${url ? `
                        <button type="button" class="branding-item-btn" onclick="window.brandingCopyToClipboard('${url.replace(/'/g, "\\'")}', this)">
                            <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><path d="M9 11h6v6H9z"/></svg>
                            Kopyala
                        </button>
                        <a href="${fullUrl}" target="_blank" class="branding-item-btn" style="flex: 1;">
                            <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3"/></svg>
                            Aç
                        </a>
                    ` : '<span style="padding: 6px 8px; font-size: 11px; color: var(--t-muted);">URL belirtilmedi</span>'}
                </div>
            </div>
        `;
    }
    
    function renderBranding() {
        if (brandingBody) {
            brandingBody.innerHTML = `
                ${renderBrandingItem('Logo', 'logo_url')}
                ${renderBrandingItem('Favicon', 'favicon_url')}
                ${renderBrandingItem('OG İmaj', 'og_image_url')}
                ${renderBrandingItem('Manifest', 'manifest_url')}
            `;
        }
    }
    
    refreshBtn?.addEventListener('click', renderBranding);
    window.brandingCopyToClipboard = copyToClipboard;
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderBranding);
    } else {
        renderBranding();
    }
})();
</script>
<?php endif; ?>


<div class="site-settings-shell">
    <aside class="site-settings-rail" aria-label="Ayar bölümleri">
        <div class="site-settings-rail-label">Modüller</div>
        <?php foreach ($sections as $key => $item): ?>
            <a class="site-settings-link<?= $key === $section ? ' is-active' : '' ?>" href="<?= $h(AdminAuth::url('/site-settings?section=' . rawurlencode((string) $key))) ?>">
                <span class="site-settings-link-title"><?= $h($item['label'] ?? $key) ?></span>
                <span class="site-settings-link-caption"><?= $h($item['caption'] ?? '') ?></span>
            </a>
        <?php endforeach; ?>
    </aside>

    <section class="site-settings-panel">
        <div class="site-settings-panel-head">
            <h2 class="site-settings-panel-title"><?= $h($activeSection['label'] ?? 'Ayarlar') ?></h2>
            <p class="site-settings-panel-caption"><?= $h($activeSection['caption'] ?? '') ?></p>
        </div>

        <form id="siteSettingsForm" method="post" action="<?= $h(AdminAuth::url('/site-settings')) ?>">
            <input type="hidden" name="_token" value="<?= $h(AdminAuth::csrfToken()) ?>">
            <input type="hidden" name="section" value="<?= $h($section) ?>">

            <div class="site-settings-grid">
                <?php foreach ($fields as $field): ?>
                    <?php
                    $name = (string) ($field['name'] ?? '');
                    $type = (string) ($field['type'] ?? 'text');
                    $span = in_array($type, ['textarea', 'checkbox'], true) || ($field['span'] ?? null) === 2 ? ' span-2' : '';
                    $value = $fieldValue($field);
                    ?>
                    <div class="field<?= $span ?>">
                        <?php if ($type === 'checkbox'): ?>
                            <label class="switch">
                                <input type="checkbox" name="<?= $h($name) ?>" value="1" <?= $value === '1' ? 'checked' : '' ?>>
                                <span class="track"></span>
                                <?= $h($field['label'] ?? $name) ?>
                            </label>
                        <?php else: ?>
                            <label class="field-label" for="field-<?= $h($name) ?>"><?= $h($field['label'] ?? $name) ?></label>
                            <?php if ($type === 'textarea'): ?>
                                <textarea id="field-<?= $h($name) ?>" class="input" name="<?= $h($name) ?>" rows="3" placeholder="<?= $h($field['placeholder'] ?? '') ?>"><?= $h($value) ?></textarea>
                            <?php else: ?>
                                <input
                                    id="field-<?= $h($name) ?>"
                                    class="input"
                                    type="<?= $h(in_array($type, ['url', 'color', 'email', 'number'], true) ? $type : 'text') ?>"
                                    name="<?= $h($name) ?>"
                                    value="<?= $h($value) ?>"
                                    placeholder="<?= $h($field['placeholder'] ?? '') ?>"
                                >
                            <?php endif; ?>
                            <?php if (!empty($field['help'])): ?>
                                <p class="site-settings-help"><?= $h($field['help']) ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="site-settings-actions">
                <a class="btn btn--ghost" href="<?= $h(AdminAuth::url('/site-settings?section=' . rawurlencode($section))) ?>">Sıfırla</a>
                <button class="btn btn--primary" type="submit">Bölümü Kaydet</button>
            </div>
        </form>
    </section>
</div>
</section>
