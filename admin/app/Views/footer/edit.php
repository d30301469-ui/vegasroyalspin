<?php

$payload = is_array($payload ?? null) ? $payload : [];
$about = is_array($payload['about'] ?? null) ? $payload['about'] : [];
$supportBadge = is_array($payload['support_badge'] ?? null) ? $payload['support_badge'] : [];
$jsonFields = is_array($jsonFields ?? null) ? $jsonFields : [];
$flash = trim((string) ($flash ?? ''));
$error = trim((string) ($error ?? ''));
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$jsonLabels = [
    'social_icons' => 'Sosyal Medya İkonları',
    'menu_columns' => 'Footer Menü Kolonları',
    'payments' => 'Ödeme Logoları',
    'licence_rows' => 'Yönetmelikler & Partnerler',
    'awards' => 'Ödül Görselleri',
    'partner_logos' => 'Sponsor / Lig Logoları (Ana Sayfa Şeridi)',
    'jackpot_config' => 'Jackpot Widget Ayarları (epoch + providers JSON)',
];
$flattenJson = static function (mixed $value, string $prefix = '') use (&$flattenJson): array {
    if (is_array($value)) {
        $rows = [];
        foreach ($value as $key => $child) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . (string) $key;
            $rows = array_merge($rows, $flattenJson($child, $path));
        }
        return $rows;
    }

    if (is_bool($value)) {
        return [['path' => $prefix, 'type' => 'boolean', 'value' => $value ? 'true' : 'false']];
    }
    if (is_int($value) || is_float($value)) {
        return [['path' => $prefix, 'type' => 'number', 'value' => (string) $value]];
    }
    if ($value === null) {
        return [['path' => $prefix, 'type' => 'null', 'value' => '']];
    }

    return [['path' => $prefix, 'type' => 'string', 'value' => (string) $value]];
};
$jsonFieldRows = static function (string $field) use ($jsonFields, $flattenJson): array {
    $decoded = json_decode((string) ($jsonFields[$field] ?? '[]'), true);
    return is_array($decoded) ? $flattenJson($decoded) : [];
};
?>
<style>
    .footer-payload-editor { background:var(--bg-card); border:1px solid var(--border-soft); border-radius:12px; color:var(--t-base); overflow:hidden; }
    .footer-payload-editor-head { align-items:center; background:color-mix(in srgb, var(--bg-muted) 86%, var(--bg-card)); color:var(--t-base); display:flex; font-size:12px; font-weight:900; justify-content:space-between; padding:10px 12px; }
    .footer-payload-table-wrap { overflow:auto; scrollbar-color:var(--border) transparent; scrollbar-width:thin; }
    .footer-payload-table-wrap::-webkit-scrollbar { height:6px; width:6px; }
    .footer-payload-table-wrap::-webkit-scrollbar-thumb { background:var(--border); border-radius:999px; }
    .footer-payload-table { border-collapse:separate; border-spacing:0; min-width:760px; width:100%; }
    .footer-payload-table th,
    .footer-payload-table td { border-bottom:1px solid var(--border-soft); padding:8px; text-align:left; vertical-align:middle; }
    .footer-payload-table th { background:color-mix(in srgb, var(--bg-muted) 86%, var(--bg-card)); color:var(--t-light); font-size:11px; font-weight:900; letter-spacing:.06em; text-transform:uppercase; }
    .footer-payload-table td { background:var(--bg-card); color:var(--t-base); }
    .footer-payload-table tbody tr:nth-child(even) td { background:color-mix(in srgb, var(--bg-muted) 30%, var(--bg-card)); }
    .footer-payload-table tbody tr:hover td { background:color-mix(in srgb, var(--primary-soft) 64%, var(--bg-card)); }
    .footer-payload-table tbody tr:last-child td { border-bottom:0; }
    .footer-payload-table .input,
    .footer-payload-table .select { min-height:34px; padding:7px 9px; }
    .footer-payload-action-cell { text-align:right !important; white-space:nowrap; width:86px; }
</style>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">İçerik · Footer</span>
        <h1 class="hero-title">Footer <span class="accent">Yönetimi</span></h1>
        <p class="hero-sub">Footer alanlarının tamamı bu ekrandan düzenlenir ve API payload olarak yayınlanır.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $h(AdminAuth::url('/module?key=footer-settings')) ?>">Kayıtları Gör</a>
        <button class="btn btn--primary" type="submit" form="footerSettingsForm">Kaydet</button>
    </div>
</section>

<?php if ($error !== ''): ?>
    <div class="alert danger admin-alert-spaced">
        <span class="ico"><svg viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg></span>
        <div class="body"><?= $h($error) ?></div>
    </div>
<?php endif; ?>

<form id="footerSettingsForm" method="post" action="<?= $h(AdminAuth::url('/footer')) ?>">
    <input type="hidden" name="_token" value="<?= $h(AdminAuth::csrfToken()) ?>">

    <div class="grid">
        <section class="col-12 card">
            <div class="card-head">
                <div class="card-title-wrap">
                    <span class="eyebrow">General</span>
                    <h2 class="card-title">Genel Bilgiler</h2>
                </div>
                <label class="switch">
                    <input type="checkbox" name="show_custom_content" value="1" <?= !empty($payload['show_custom_content']) ? 'checked' : '' ?>>
                    <span class="track"></span>
                    Üst özel içerik aktif
                </label>
                <label class="switch">
                    <input type="checkbox" name="support_badge_enabled" value="1" <?= !empty($supportBadge['enabled']) ? 'checked' : '' ?>>
                    <span class="track"></span>
                    7/24 online rozeti aktif
                </label>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label class="field-label" for="site_name">Site adı</label>
                    <input id="site_name" class="input" name="site_name" value="<?= $h($payload['site_name'] ?? '') ?>">
                </div>
                <div class="field">
                    <label class="field-label" for="copyright_since">Copyright başlangıç yılı</label>
                    <input id="copyright_since" class="input" type="number" name="copyright_since" value="<?= $h($payload['copyright_since'] ?? 2014) ?>">
                </div>
                <div class="field span-2">
                    <label class="field-label" for="flag_image">Dil bayrağı görsel yolu</label>
                    <input id="flag_image" class="input" name="flag_image" value="<?= $h($payload['flag_image'] ?? '') ?>">
                </div>
                <div class="field">
                    <label class="field-label" for="support_badge_label">Online rozet üst metni</label>
                    <input id="support_badge_label" class="input" name="support_badge_label" value="<?= $h($supportBadge['label'] ?? '7/24') ?>">
                </div>
                <div class="field">
                    <label class="field-label" for="support_badge_text">Online rozet alt metni</label>
                    <input id="support_badge_text" class="input" name="support_badge_text" value="<?= $h($supportBadge['text'] ?? 'ONLINE') ?>">
                </div>
                <div class="field span-2">
                    <label class="field-label" for="support_badge_href">Online rozet linki</label>
                    <input id="support_badge_href" class="input" name="support_badge_href" value="<?= $h($supportBadge['href'] ?? 'javascript:void(0)') ?>">
                </div>
            </div>
        </section>

        <section class="col-12 card">
            <div class="card-head">
                <div class="card-title-wrap">
                    <span class="eyebrow">About</span>
                    <h2 class="card-title">Tarihimiz / Geleceğimiz / Ödüllerimiz</h2>
                </div>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label class="field-label" for="history_title">Tarihimiz başlık</label>
                    <input id="history_title" class="input" name="history_title" value="<?= $h($about['history_title'] ?? '') ?>">
                </div>
                <div class="field">
                    <label class="field-label" for="future_title">Geleceğimiz başlık</label>
                    <input id="future_title" class="input" name="future_title" value="<?= $h($about['future_title'] ?? '') ?>">
                </div>
                <div class="field span-2">
                    <label class="field-label" for="history_text">Tarihimiz metni</label>
                    <textarea id="history_text" class="textarea" name="history_text" rows="5"><?= $h($about['history_text'] ?? '') ?></textarea>
                </div>
                <div class="field span-2">
                    <label class="field-label" for="future_text">Geleceğimiz metni</label>
                    <textarea id="future_text" class="textarea" name="future_text" rows="5"><?= $h($about['future_text'] ?? '') ?></textarea>
                </div>
                <div class="field span-2">
                    <label class="field-label" for="awards_title">Ödüller başlığı</label>
                    <input id="awards_title" class="input" name="awards_title" value="<?= $h($about['awards_title'] ?? '') ?>">
                </div>
            </div>
        </section>

        <?php foreach ($jsonLabels as $field => $label): ?>
            <section class="col-12 card">
                <div class="card-head">
                    <div class="card-title-wrap">
                        <span class="eyebrow">İşlenmiş Veri</span>
                        <h2 class="card-title"><?= $h($label) ?></h2>
                    </div>
                    <span class="badge solid"><?= $h($field) ?></span>
                </div>
                <div class="field span-2">
                    <input type="hidden" name="<?= $h($field) ?>" value="<?= $h($jsonFields[$field] ?? '[]') ?>" data-footer-payload-value>
                    <div class="footer-payload-editor" data-footer-payload-editor>
                        <div class="footer-payload-editor-head">
                            <span>Alan yolu, tip ve değer olarak düzenlenir</span>
                            <button class="btn btn--ghost" type="button" data-footer-payload-add>Alan Ekle</button>
                        </div>
                        <div class="footer-payload-table-wrap">
                            <table class="footer-payload-table">
                                <thead>
                                    <tr>
                                        <th>Alan Yolu</th>
                                        <th>Tip</th>
                                        <th>Değer</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody data-footer-payload-rows>
                                    <?php foreach ($jsonFieldRows($field) as $row): ?>
                                        <tr data-footer-payload-row>
                                            <td><input class="input" data-footer-payload-path value="<?= $h($row['path'] ?? '') ?>" placeholder="0.title veya 0.links.0.href"></td>
                                            <td>
                                                <select class="select" data-footer-payload-type>
                                                    <?php foreach (['string' => 'Metin', 'number' => 'Sayı', 'boolean' => 'Doğru/Yanlış', 'null' => 'Boş'] as $optionValue => $optionLabel): ?>
                                                        <option value="<?= $h($optionValue) ?>" <?= (string) ($row['type'] ?? 'string') === $optionValue ? 'selected' : '' ?>><?= $h($optionLabel) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td><input class="input" data-footer-payload-scalar value="<?= $h($row['value'] ?? '') ?>" placeholder="Değer"></td>
                                            <td class="footer-payload-action-cell"><button class="btn btn--ghost" type="button" data-footer-payload-remove>Sil</button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="field-help">Bu alan API payload içindeki <code><?= $h($field) ?></code> dizisini satır bazlı yönetir.</div>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

        <div class="form-actions admin-action-spaced">
        <span class="badge dot success">API: /api/v2/content/footer</span>
        <span class="spacer"></span>
        <button class="btn btn--primary" type="submit">Footer Ayarlarını Kaydet</button>
    </div>
</form>
<template id="footerPayloadRowTemplate">
    <table><tbody>
        <tr data-footer-payload-row>
            <td><input class="input" data-footer-payload-path value="" placeholder="0.title veya 0.links.0.href"></td>
            <td>
                <select class="select" data-footer-payload-type>
                    <option value="string">Metin</option>
                    <option value="number">Sayı</option>
                    <option value="boolean">Doğru/Yanlış</option>
                    <option value="null">Boş</option>
                </select>
            </td>
            <td><input class="input" data-footer-payload-scalar value="" placeholder="Değer"></td>
            <td class="footer-payload-action-cell"><button class="btn btn--ghost" type="button" data-footer-payload-remove>Sil</button></td>
        </tr>
    </tbody></table>
</template>
<script>
    (function () {
        var form = document.getElementById('footerSettingsForm');
        var template = document.getElementById('footerPayloadRowTemplate');
        if (!form || !template) return;

        function castValue(type, value) {
            if (type === 'number') {
                var number = Number(value);
                return Number.isFinite(number) ? number : 0;
            }
            if (type === 'boolean') return value === 'true' || value === '1' || value === 'on' || value === 'evet';
            if (type === 'null') return null;
            return value;
        }

        function isListKey(key) {
            return /^\d+$/.test(key);
        }

        function assignPath(root, path, value) {
            var parts = path.split('.').map(function (part) { return part.trim(); }).filter(Boolean);
            if (!parts.length) return;
            var cursor = root;
            parts.forEach(function (part, index) {
                var last = index === parts.length - 1;
                if (last) {
                    cursor[part] = value;
                    return;
                }
                var nextPart = parts[index + 1];
                if (cursor[part] === undefined || cursor[part] === null || typeof cursor[part] !== 'object') {
                    cursor[part] = isListKey(nextPart) ? [] : {};
                }
                cursor = cursor[part];
            });
        }

        function syncEditor(editor) {
            var hidden = editor.parentElement.querySelector('[data-footer-payload-value]');
            if (!hidden) return;
            var payload = [];
            editor.querySelectorAll('[data-footer-payload-row]').forEach(function (row) {
                var path = row.querySelector('[data-footer-payload-path]').value.trim();
                if (!path) return;
                var type = row.querySelector('[data-footer-payload-type]').value;
                var value = row.querySelector('[data-footer-payload-scalar]').value;
                assignPath(payload, path, castValue(type, value));
            });
            hidden.value = JSON.stringify(payload);
        }

        document.addEventListener('click', function (event) {
            var add = event.target.closest('[data-footer-payload-add]');
            if (add) {
                var editor = add.closest('[data-footer-payload-editor]');
                var rows = editor ? editor.querySelector('[data-footer-payload-rows]') : null;
                var rowTemplate = template.content ? template.content.querySelector('tr') : null;
                if (rows) rows.insertAdjacentHTML('beforeend', rowTemplate ? rowTemplate.outerHTML : template.innerHTML);
                return;
            }

            var remove = event.target.closest('[data-footer-payload-remove]');
            if (remove) {
                var row = remove.closest('[data-footer-payload-row]');
                if (row) row.remove();
            }
        });

        form.addEventListener('submit', function () {
            form.querySelectorAll('[data-footer-payload-editor]').forEach(syncEditor);
        });
    })();
</script>
