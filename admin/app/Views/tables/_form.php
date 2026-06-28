<?php

$table = (string) ($table ?? '');
$moduleKey = isset($moduleKey) ? (string) $moduleKey : '';
$columns = is_array($columns ?? null) ? $columns : [];
$row = is_array($row ?? null) ? $row : [];
$mode = (string) ($mode ?? 'create');
$primaryKey = isset($primaryKey) ? (string) $primaryKey : null;
$isEdit = $mode === 'edit';
$isModal = !empty($isModal);
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

$parseEnumOptions = static function (string $columnType): array {
    if (preg_match("/^enum\((.*)\)$/i", $columnType, $matches) !== 1) {
        return [];
    }

    $options = str_getcsv((string) $matches[1], ',', "'");
    return array_map(static fn (mixed $value): string => (string) $value, $options);
};
$dateInputValue = static function (mixed $value): string {
    $value = trim((string) ($value ?? ''));
    if ($value === '' || $value === 'CURRENT_TIMESTAMP') {
        return '';
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? $value : date('Y-m-d', $timestamp);
};
$dateTimeInputValue = static function (mixed $value): string {
    $value = trim((string) ($value ?? ''));
    if ($value === '' || $value === 'CURRENT_TIMESTAMP') {
        return '';
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? str_replace(' ', 'T', substr($value, 0, 16)) : date('Y-m-d\TH:i', $timestamp);
};
$jsonFlatten = static function (mixed $value, string $prefix = '') use (&$jsonFlatten): array {
    if (is_array($value)) {
        $rows = [];
        foreach ($value as $key => $child) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . (string) $key;
            $rows = array_merge($rows, $jsonFlatten($child, $path));
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
$jsonRows = static function (mixed $value) use ($jsonFlatten): array {
    $decoded = is_string($value) && trim($value) !== '' ? json_decode($value, true) : [];
    if (!is_array($decoded)) {
        $decoded = [];
    }

    return $jsonFlatten($decoded);
};
$jsonRootType = static function (mixed $value): string {
    $decoded = is_string($value) && trim($value) !== '' ? json_decode($value, true) : [];
    if (!is_array($decoded)) {
        return 'object';
    }

    return array_is_list($decoded) ? 'array' : 'object';
};
$fieldLabel = static function (string $name): string {
    return AdminFieldPresenter::label($name);
};
$fieldHelp = [
    'frontend_url' => 'Public site domaini. Örn: https://vegasroyalspin.com',
    'backend_url' => 'Admin/API/callback backend domaini. Örn: https://bo-nexthub.site',
    'backend_api_base_url' => 'Frontend isteklerinin gideceği API tabanı. Örn: https://bo-nexthub.site/api/v2',
    'allowed_url_hosts' => 'Virgülle ayrılmış güvenli host listesi. Örn: vegasroyalspin.com,www.vegasroyalspin.com,m.vegasroyalspin.com,bo-nexthub.site',
    'category' => $table === 'sliders'
        ? 'Slider yüzeyi: home, slots, live_casino veya bgaming. Desktop/mobil görseller desktop_path ve mobile_path alanlarından yönetilir.'
        : 'Kategori',
    'desktop_path' => 'Desktop slider görsel/video yolu. Örn: /uploads/sliders/home-desktop.webp (backend storage/uploads altında)',
    'mobile_path' => 'Mobil slider görsel/video yolu. Boş bırakılırsa desktop görsel kullanılır.',
    'button_link' => 'Slider tıklama linki. Site içi yol veya tam URL olabilir.',
];

$sliderCategoryOptions = [
    'home' => 'Home slider',
    'slots' => 'Slot slider',
    'live_casino' => 'Live casino slider',
    'bgaming' => 'BGaming slider',
];

$isAutoManaged = static function (array $column) use ($isEdit, $primaryKey): bool {
    $name = (string) ($column['name'] ?? '');
    $extra = strtolower((string) ($column['extra'] ?? ''));
    $default = strtolower((string) ($column['column_default'] ?? ''));
    $type = strtolower((string) ($column['data_type'] ?? ''));

    if (str_contains($extra, 'auto_increment') || str_contains($extra, 'generated')) {
        return true;
    }
    if ($isEdit && $primaryKey !== null && $name === $primaryKey) {
        return true;
    }
    if (!$isEdit && in_array($type, ['timestamp', 'datetime'], true) && ($default !== '' || str_contains($extra, 'on update'))) {
        return true;
    }

    return false;
};

$writableColumns = array_values(array_filter($columns, static fn (array $column): bool => !$isAutoManaged($column)));
$action = $isEdit ? '/table/update?name=' . rawurlencode($table) : '/table/store?name=' . rawurlencode($table);
?>
<style>
    .admin-json-editor { background:var(--bg-card); border:1px solid var(--border-soft); border-radius:12px; color:var(--t-base); overflow:hidden; }
    .admin-json-editor-head { align-items:center; background:color-mix(in srgb, var(--bg-muted) 86%, var(--bg-card)); color:var(--t-base); display:flex; font-size:12px; font-weight:900; justify-content:space-between; padding:10px 12px; }
    .admin-json-table-wrap { overflow:auto; scrollbar-color:var(--border) transparent; scrollbar-width:thin; }
    .admin-json-table-wrap::-webkit-scrollbar { height:6px; width:6px; }
    .admin-json-table-wrap::-webkit-scrollbar-thumb { background:var(--border); border-radius:999px; }
    .admin-json-table { border-collapse:separate; border-spacing:0; min-width:760px; width:100%; }
    .admin-json-table th,
    .admin-json-table td { border-bottom:1px solid var(--border-soft); padding:8px; text-align:left; vertical-align:middle; }
    .admin-json-table th { background:color-mix(in srgb, var(--bg-muted) 86%, var(--bg-card)); color:var(--t-light); font-size:11px; font-weight:900; letter-spacing:.06em; text-transform:uppercase; }
    .admin-json-table td { background:var(--bg-card); color:var(--t-base); }
    .admin-json-table tbody tr:nth-child(even) td { background:color-mix(in srgb, var(--bg-muted) 30%, var(--bg-card)); }
    .admin-json-table tbody tr:hover td { background:color-mix(in srgb, var(--primary-soft) 64%, var(--bg-card)); }
    .admin-json-table tbody tr:last-child td { border-bottom:0; }
    .admin-json-table .input,
    .admin-json-table .select { min-height:34px; padding:7px 9px; }
    .admin-json-action-cell { text-align:right !important; white-space:nowrap; width:86px; }
</style>

<form id="adminRecordForm" method="post" action="<?= htmlspecialchars(AdminAuth::url($action), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="name" value="<?= $text($table) ?>">
    <input type="hidden" name="module" value="<?= $text($moduleKey) ?>">
    <?php if ($isEdit && $primaryKey !== null): ?>
        <input type="hidden" name="_id" value="<?= $text($row[$primaryKey] ?? '') ?>">
    <?php endif; ?>

    <div class="form-grid">
        <?php if ($isEdit && $primaryKey !== null): ?>
            <div class="field">
                <label class="field-label" for="field_primary_key"><?= $text($primaryKey) ?></label>
                <input id="field_primary_key" class="input" value="<?= $text($row[$primaryKey] ?? '') ?>" disabled>
            </div>
        <?php endif; ?>

        <?php foreach ($writableColumns as $column): ?>
            <?php
            $name = (string) ($column['name'] ?? '');
            $type = strtolower((string) ($column['data_type'] ?? ''));
            $columnType = (string) ($column['type'] ?? '');
            $nullable = (string) ($column['nullable'] ?? 'NO') === 'YES';
            $value = $row[$name] ?? ($column['column_default'] ?? '');
            $fieldId = 'field_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $name);
            $required = (!$nullable && !$isEdit && $type !== 'tinyint') ? ' required' : '';
            $spanClass = in_array($type, ['text', 'tinytext', 'mediumtext', 'longtext', 'json'], true) ? ' span-2' : '';
            ?>
            <div class="field<?= $spanClass ?>">
                <label class="field-label" for="<?= $text($fieldId) ?>">
                    <?= $text($fieldLabel($name)) ?>
                    <?php if (!$nullable): ?><span class="req">*</span><?php endif; ?>
                </label>

                <?php if ($table === 'sliders' && $name === 'category'): ?>
                    <select id="<?= $text($fieldId) ?>" class="select" name="<?= $text($name) ?>"<?= $required ?>>
                        <?php foreach ($sliderCategoryOptions as $optionValue => $optionLabel): ?>
                            <option value="<?= $text($optionValue) ?>" <?= ((string) $value === $optionValue) ? 'selected' : '' ?>>
                                <?= $text($optionLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($type === 'tinyint' && preg_match('/tinyint\(1\)/i', $columnType) === 1): ?>
                    <input type="hidden" name="<?= $text($name) ?>" value="0">
                    <label class="switch">
                        <input id="<?= $text($fieldId) ?>" type="checkbox" name="<?= $text($name) ?>" value="1" <?= ((string) $value === '1') ? 'checked' : '' ?>>
                        <span class="track"></span>
                        Aktif
                    </label>
                <?php elseif ($type === 'enum'): ?>
                    <?php $options = $parseEnumOptions($columnType); ?>
                    <select id="<?= $text($fieldId) ?>" class="select" name="<?= $text($name) ?>"<?= $required ?>>
                        <?php if ($nullable): ?><option value="">Boş</option><?php endif; ?>
                        <?php foreach ($options as $option): ?>
                            <option value="<?= $text($option) ?>" <?= ((string) $value === $option) ? 'selected' : '' ?>><?= $text($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($type === 'json'): ?>
                    <?php $rows = $jsonRows($value); ?>
                    <input id="<?= $text($fieldId) ?>" type="hidden" name="<?= $text($name) ?>" value="<?= $text((string) ($value ?: '{}')) ?>" data-json-editor-value data-json-root="<?= $text($jsonRootType($value)) ?>">
                    <div class="admin-json-editor" data-json-editor>
                        <div class="admin-json-editor-head">
                            <span>İşlenmiş veri alanları</span>
                            <button class="btn btn--ghost" type="button" data-json-add-row>Alan Ekle</button>
                        </div>
                        <div class="admin-json-table-wrap">
                            <table class="admin-json-table">
                                <thead>
                                    <tr>
                                        <th>Alan Yolu</th>
                                        <th>Tip</th>
                                        <th>Değer</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody data-json-rows>
                                    <?php foreach ($rows as $jsonRow): ?>
                                        <tr data-json-row>
                                            <td><input class="input" data-json-path value="<?= $text($jsonRow['path'] ?? '') ?>" placeholder="alan.yolu"></td>
                                            <td>
                                                <select class="select" data-json-type>
                                                    <?php foreach (['string' => 'Metin', 'number' => 'Sayı', 'boolean' => 'Doğru/Yanlış', 'null' => 'Boş'] as $optionValue => $optionLabel): ?>
                                                        <option value="<?= $text($optionValue) ?>" <?= (string) ($jsonRow['type'] ?? 'string') === $optionValue ? 'selected' : '' ?>><?= $text($optionLabel) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td><input class="input" data-json-scalar value="<?= $text($jsonRow['value'] ?? '') ?>" placeholder="Değer"></td>
                                            <td class="admin-json-action-cell"><button class="btn btn--ghost" type="button" data-json-remove-row>Sil</button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php elseif (in_array($type, ['int', 'bigint', 'smallint', 'mediumint'], true)): ?>
                    <input id="<?= $text($fieldId) ?>" class="input" type="number" name="<?= $text($name) ?>" value="<?= $text($value) ?>"<?= $required ?>>
                <?php elseif (in_array($type, ['decimal', 'float', 'double'], true)): ?>
                    <input id="<?= $text($fieldId) ?>" class="input" type="number" step="0.01" name="<?= $text($name) ?>" value="<?= $text($value) ?>"<?= $required ?>>
                <?php elseif ($type === 'date'): ?>
                    <input id="<?= $text($fieldId) ?>" class="input admin-date-input" type="date" name="<?= $text($name) ?>" value="<?= $text($dateInputValue($value)) ?>"<?= $required ?>>
                <?php elseif (in_array($type, ['datetime', 'timestamp'], true)): ?>
                    <input id="<?= $text($fieldId) ?>" class="input admin-date-input" type="datetime-local" name="<?= $text($name) ?>" value="<?= $text($dateTimeInputValue($value)) ?>"<?= $required ?>>
                <?php elseif (preg_match('/password/i', $name) === 1): ?>
                    <input id="<?= $text($fieldId) ?>" class="input" type="password" name="<?= $text($name) ?>" autocomplete="new-password"<?= $isEdit ? '' : $required ?>>
                    <div class="field-help"><?= $isEdit ? 'Boş bırakırsanız mevcut değer korunur.' : 'En az 6 karakter girin.' ?></div>
                <?php else: ?>
                    <?php $inputType = preg_match('/email/i', $name) === 1 ? 'email' : (preg_match('/(^|_)url$|url$/i', $name) === 1 ? 'url' : 'text'); ?>
                    <input id="<?= $text($fieldId) ?>" class="input" type="<?= $text($inputType) ?>" name="<?= $text($name) ?>" value="<?= $text($value) ?>"<?= $required ?>>
                <?php endif; ?>

                <div class="field-help"><?= $text($fieldHelp[$name] ?? $columnType) ?><?= $nullable ? ' · nullable' : '' ?></div>
            </div>

            <?php if (preg_match('/password/i', $name) === 1 && in_array($table, ['users', 'admins'], true)): ?>
                <div class="field">
                    <label class="field-label" for="<?= $text($fieldId) ?>_confirmation">password_confirmation</label>
                    <input id="<?= $text($fieldId) ?>_confirmation" class="input" type="password" name="password_confirmation" autocomplete="new-password"<?= $isEdit ? '' : $required ?>>
                    <div class="field-help">Şifre alanı ile aynı olmalıdır.</div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div class="form-actions">
        <span class="badge dot info"><?= $text($isEdit ? 'Kayıt güncellenecek' : 'Yeni kayıt oluşturulacak') ?></span>
        <span class="spacer"></span>
        <?php if ($isModal): ?>
            <button class="btn btn--ghost" type="button" data-admin-modal-close>Vazgeç</button>
        <?php endif; ?>
        <button class="btn btn--primary" type="submit">Kaydet</button>
    </div>
</form>
<template id="adminJsonEditorRowTemplate">
    <table><tbody>
        <tr data-json-row>
            <td><input class="input" data-json-path value="" placeholder="alan.yolu"></td>
            <td>
                <select class="select" data-json-type>
                    <option value="string">Metin</option>
                    <option value="number">Sayı</option>
                    <option value="boolean">Doğru/Yanlış</option>
                    <option value="null">Boş</option>
                </select>
            </td>
            <td><input class="input" data-json-scalar value="" placeholder="Değer"></td>
            <td class="admin-json-action-cell"><button class="btn btn--ghost" type="button" data-json-remove-row>Sil</button></td>
        </tr>
    </tbody></table>
</template>
<script>
    (function () {
        var form = document.getElementById('adminRecordForm');
        var template = document.getElementById('adminJsonEditorRowTemplate');
        if (!form || !template) return;

        function castValue(type, value) {
            if (type === 'number') {
                var number = Number(value);
                return Number.isFinite(number) ? number : 0;
            }
            if (type === 'boolean') {
                return value === 'true' || value === '1' || value === 'on' || value === 'evet';
            }
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
            var hidden = editor.parentElement.querySelector('[data-json-editor-value]');
            if (!hidden) return;
            var payload = hidden.dataset.jsonRoot === 'array' ? [] : {};
            editor.querySelectorAll('[data-json-row]').forEach(function (row) {
                var path = row.querySelector('[data-json-path]').value.trim();
                if (!path) return;
                var type = row.querySelector('[data-json-type]').value;
                var value = row.querySelector('[data-json-scalar]').value;
                assignPath(payload, path, castValue(type, value));
            });
            hidden.value = JSON.stringify(payload);
        }

        document.addEventListener('click', function (event) {
            var add = event.target.closest('[data-json-add-row]');
            if (add) {
                var editor = add.closest('[data-json-editor]');
                var rows = editor ? editor.querySelector('[data-json-rows]') : null;
                var rowTemplate = template.content ? template.content.querySelector('tr') : null;
                if (rows) rows.insertAdjacentHTML('beforeend', rowTemplate ? rowTemplate.outerHTML : template.innerHTML);
                return;
            }

            var remove = event.target.closest('[data-json-remove-row]');
            if (remove) {
                var row = remove.closest('[data-json-row]');
                if (row) row.remove();
            }
        });

        form.addEventListener('submit', function () {
            form.querySelectorAll('[data-json-editor]').forEach(syncEditor);
        });
    })();
</script>
