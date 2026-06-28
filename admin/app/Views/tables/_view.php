<?php

$table = (string) ($table ?? '');
$moduleKey = isset($moduleKey) ? (string) $moduleKey : '';
$columns = is_array($columns ?? null) ? $columns : [];
$row = is_array($row ?? null) ? $row : [];
$primaryKey = isset($primaryKey) ? (string) $primaryKey : '';
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$formatValue = static function (string $column, mixed $value): string {
    return AdminFieldPresenter::format($column, $value, 0);
};
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
        $value = $value ? 'true' : 'false';
    } elseif ($value === null) {
        $value = 'null';
    }

    return [['path' => $prefix, 'value' => (string) $value]];
};
$fieldLabel = static function (string $name) use ($moduleKey): string {
    return AdminFieldPresenter::label($name, $moduleKey);
};
?>
<style>
    .admin-json-view { background:var(--bg-card); border:1px solid var(--border-soft); border-radius:12px; color:var(--t-base); overflow:auto; scrollbar-color:var(--border) transparent; scrollbar-width:thin; }
    .admin-json-view::-webkit-scrollbar { height:6px; width:6px; }
    .admin-json-view::-webkit-scrollbar-thumb { background:var(--border); border-radius:999px; }
    .admin-json-view-table { border-collapse:separate; border-spacing:0; min-width:620px; width:100%; }
    .admin-json-view-table th,
    .admin-json-view-table td { border-bottom:1px solid var(--border-soft); padding:8px; text-align:left; vertical-align:middle; }
    .admin-json-view-table th { background:color-mix(in srgb, var(--bg-muted) 86%, var(--bg-card)); color:var(--t-light); font-size:11px; font-weight:900; letter-spacing:.06em; text-transform:uppercase; }
    .admin-json-view-table td { background:var(--bg-card); color:var(--t-base); }
    .admin-json-view-table tbody tr:nth-child(even) td { background:color-mix(in srgb, var(--bg-muted) 30%, var(--bg-card)); }
    .admin-json-view-table tbody tr:hover td { background:color-mix(in srgb, var(--primary-soft) 64%, var(--bg-card)); }
    .admin-json-view-table tbody tr:last-child td { border-bottom:0; }
    .admin-readonly-value { background:var(--bg-card); border:1px solid var(--border); border-radius:10px; color:var(--t-base); font-family:inherit; font-size:12px; line-height:1.55; margin:0; max-height:220px; overflow:auto; padding:10px 12px; white-space:pre-wrap; word-break:break-word; }
</style>
<div class="card-head">
    <div class="card-title-wrap">
        <span class="eyebrow">Salt Okunur</span>
        <h2 class="card-title"><?= $text($table) ?> kayıt detayı</h2>
    </div>
    <?php if ($primaryKey !== '' && array_key_exists($primaryKey, $row)): ?>
        <span class="badge solid">#<?= $text($row[$primaryKey]) ?></span>
    <?php endif; ?>
</div>

<div class="form-grid">
    <?php foreach ($columns as $column): ?>
        <?php
        $name = (string) ($column['name'] ?? '');
        $dataType = strtolower((string) ($column['data_type'] ?? ''));
        $type = (string) ($column['type'] ?? '');
        $rawValue = $row[$name] ?? null;
        $value = $formatValue($name, $rawValue);
        $spanClass = strlen($value) > 120 ? ' span-2' : '';
        ?>
        <div class="field<?= $spanClass ?>">
            <label class="field-label"><?= $text($fieldLabel($name)) ?></label>
            <?php if ($dataType === 'json'): ?>
                <?php
                $decoded = is_string($rawValue) && trim($rawValue) !== '' ? json_decode($rawValue, true) : [];
                $jsonRows = is_array($decoded) ? $flattenJson($decoded) : [];
                ?>
                <div class="admin-json-view">
                    <?php if ($jsonRows === []): ?>
                        <input class="input" value="Veri yok" readonly>
                    <?php else: ?>
                        <table class="admin-json-view-table">
                            <thead>
                                <tr>
                                    <th>Alan Yolu</th>
                                    <th>Değer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jsonRows as $jsonRow): ?>
                                    <tr>
                                        <td><input class="input" value="<?= $text($jsonRow['path'] ?? '') ?>" readonly></td>
                                        <td><input class="input" value="<?= $text($jsonRow['value'] ?? '') ?>" readonly></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php elseif (strlen($value) > 120): ?>
                <pre class="admin-readonly-value"><?= $text($value) ?></pre>
            <?php else: ?>
                <input class="input" value="<?= $text($value) ?>" readonly>
            <?php endif; ?>
            <div class="field-help"><?= $text($type) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="form-actions">
    <span class="badge dot info">Sadece görüntüleme</span>
    <span class="spacer"></span>
    <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url($moduleKey !== '' ? '/module?key=' . rawurlencode($moduleKey) : '/table?name=' . rawurlencode($table)), ENT_QUOTES, 'UTF-8') ?>">Listeye dön</a>
</div>
