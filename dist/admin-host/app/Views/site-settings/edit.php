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
    @media (max-width: 900px) {
        .site-settings-shell { grid-template-columns: 1fr; }
        .site-settings-grid { grid-template-columns: 1fr; }
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
