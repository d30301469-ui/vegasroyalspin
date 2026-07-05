<?php

$table = (string) ($table ?? '');
$columns = is_array($columns ?? null) ? $columns : [];
$rows = is_array($rows ?? null) ? $rows : [];
$primaryKey = isset($primaryKey) ? (string) $primaryKey : '';
$page = max(1, (int) ($page ?? 1));
$perPage = max(1, (int) ($perPage ?? 25));
$total = max(0, (int) ($total ?? 0));
$search = (string) ($search ?? '');
$flash = trim((string) ($flash ?? ''));
$tableError = trim((string) ($tableError ?? ''));
$totalPages = max(1, (int) ceil($total / $perPage));
$module = is_array($module ?? null) ? $module : [];
$moduleKey = isset($moduleKey) ? (string) $moduleKey : '';
$isReadOnlyModule = in_array($moduleKey, ['deposits', 'withdrawals', 'promocode-requests'], true);
$isWriteProtectedTable = in_array($table, [
    'users',
    'admin_permissions',
    'admin_sessions',
    'admin_logs',
    'megapayz_config',
    'megapayz_transactions',
    'megapayz_callbacks',
    'bgaming_config',
    'bgaming_transactions',
    'bgaming_wallet_logs',
    'drakon_config',
    'drakon_providers',
    'drakon_games',
    'drakon_transactions',
    'drakon_webhook_logs',
], true);
$actionColumnWidth = in_array($moduleKey, ['withdrawals', 'promocode-requests'], true)
    ? '17%'
    : (($isReadOnlyModule || $isWriteProtectedTable) ? '7%' : '12%');
$visibleColumnNames = is_array($visibleColumnNames ?? null) ? $visibleColumnNames : [];
$columnByName = [];
foreach ($columns as $column) {
    $columnByName[(string) $column['name']] = $column;
}
$visibleColumns = [];
foreach ($visibleColumnNames as $columnName) {
    if (isset($columnByName[$columnName])) {
        $visibleColumns[] = $columnByName[$columnName];
    }
}
if ($visibleColumns === []) {
    $visibleColumns = array_slice($columns, 0, 6);
} elseif (count($visibleColumns) > 10) {
    $visibleColumns = array_slice($visibleColumns, 0, 10);
}
$pageStart = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$pageEnd = min($total, $page * $perPage);

$formatValue = static function (string $column, mixed $value): string {
    return AdminFieldPresenter::format($column, $value, 80);
};

$badgeClass = static function (string $value): string {
    $value = strtolower($value);
    return match (true) {
        in_array($value, ['active', 'confirmed', 'approved', 'success', '1'], true) => 'success dot',
        in_array($value, ['pending', 'waiting_approval', 'draft'], true) => 'warning dot',
        in_array($value, ['rejected', 'inactive', 'failed', 'cancelled', 'banned', '0'], true) => 'danger dot',
        default => 'primary',
    };
};

$columnLabel = static function (string $column) use ($moduleKey): string {
    return AdminFieldPresenter::label($column, $moduleKey);
};

$columnWidth = static function (string $column): string {
    return match (true) {
        preg_match('/(^image_url$|thumbnail|banner|cover|logo)/i', $column) === 1 => '7%',
        in_array($column, ['username', 'email', 'name', 'title', 'game_name', 'provider_name'], true) => '14%',
        preg_match('/(^id$|_id$|user_id|admin_id)/i', $column) === 1 => '6%',
        preg_match('/status|active|verified|banned|enabled|type|txn_type/i', $column) === 1 => '8%',
        preg_match('/amount|balance|price|total|fee|miktar/i', $column) === 1 => '9%',
        preg_match('/created_at|updated_at|date|deadline|submitted_at|reviewed_at|processed_at/i', $column) === 1 => '10%',
        default => '10%',
    };
};

$recordUrl = static function (string $path) use ($moduleKey, $table): string {
    $separator = str_contains($path, '?') ? '&' : '?';
    if ($moduleKey !== '') {
        return AdminAuth::url($path . $separator . 'module=' . rawurlencode($moduleKey));
    }

    return AdminAuth::url($path . $separator . 'name=' . rawurlencode($table));
};
?>
<style>
    .admin-tx-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        min-width: 64px;
        height: 30px;
        padding: 0 9px;
        border: 0;
        border-radius: 9px;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        line-height: 1;
        cursor: pointer;
    }
    .admin-tx-action svg { width: 14px; height: 14px; stroke: currentColor; stroke-width: 2; fill: none; }
    .admin-tx-action--approve { background: var(--success); }
    .admin-tx-action--reject { background: var(--danger); }
    .admin-tx-action--approve:hover { background: #059669; }
    .admin-tx-action--reject:hover { background: #dc2626; }
    .admin-game-thumb {
        display: block;
        width: 58px;
        height: 58px;
        border-radius: 12px;
        object-fit: cover;
        background: var(--bg-muted);
        border: 1px solid var(--border);
    }
    .admin-game-thumb-placeholder {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 58px;
        height: 58px;
        border-radius: 12px;
        background: var(--bg-muted);
        color: var(--t-muted);
        border: 1px dashed var(--border);
        font-size: 11px;
        font-weight: 700;
    }
</style>
<?php if ($moduleKey === 'users'): ?>
<?php
$userScalar = static function (string $sql): float {
    try {
        return (float) AdminDatabase::pdo()->query($sql)->fetchColumn();
    } catch (Throwable) {
        return 0.0;
    }
};
$money = static fn (mixed $value): string => '₺' . number_format((float) $value, 2, ',', '.');
$userStats = [
    ['label' => 'Toplam Aktif Oyuncular', 'value' => $userScalar('SELECT COUNT(*) FROM users WHERE COALESCE(banned, 0) = 0'), 'color' => '#22c55e'],
    ['label' => 'Toplam İnaktif Oyuncular', 'value' => $userScalar('SELECT COUNT(*) FROM users WHERE COALESCE(banned, 0) = 1'), 'color' => '#ef4444'],
    ['label' => 'Aktif (7 gün)', 'value' => $userScalar("SELECT COUNT(*) FROM users WHERE COALESCE(last_login_at, updated_at, created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)"), 'color' => '#8b5cf6'],
    ['label' => 'Bugün Kayıt', 'value' => $userScalar('SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()'), 'color' => '#3b82f6'],
    ['label' => 'Toplam Bakiye', 'value' => $userScalar('SELECT COALESCE(SUM(balance), 0) FROM users'), 'color' => '#f59e0b', 'money' => true],
];
$userColumns = [
    ['key' => 'id', 'label' => 'ID', 'width' => '4%'],
    ['key' => 'partner', 'label' => 'Partner', 'width' => '7%'],
    ['key' => 'tag', 'label' => 'Etiket', 'width' => '4%'],
    ['key' => 'username', 'label' => 'Kullanıcı Adı', 'width' => '12%'],
    ['key' => 'name', 'label' => 'İsim', 'width' => '7%'],
    ['key' => 'surname', 'label' => 'Soyadı', 'width' => '7%'],
    ['key' => 'phone', 'label' => 'Telefon No', 'width' => '9%'],
    ['key' => 'email', 'label' => 'Mail', 'width' => '13%'],
    ['key' => 'balance', 'label' => 'Bakiye', 'width' => '7%'],
    ['key' => 'gender', 'label' => 'Cinsiyet', 'width' => '6%'],
    ['key' => 'identity', 'label' => 'Kimlik No', 'width' => '10%'],
    ['key' => 'active', 'label' => 'Aktif?', 'width' => '5%'],
    ['key' => 'country', 'label' => 'Para', 'width' => '3%'],
];
?>
<script>document.body.classList.add('players-surface');</script>
<style>
    :root[data-theme=light] {
        --players-page-bg: #eef2f7;
        --players-topbar-bg: rgba(255,255,255,.86);
        --players-topbar-border: #e5e7eb;
        --players-panel-bg: #ffffff;
        --players-panel-soft: #f8fafc;
        --players-panel-mid: #eef2f7;
        --players-row-bg: #ffffff;
        --players-row-alt: #f8fafc;
        --players-row-hover: #eef6ff;
        --players-border: #dfe5ee;
        --players-text: #111827;
        --players-muted: #64748b;
        --players-soft-text: #334155;
        --players-input-bg: #ffffff;
        --players-input-border: #d5dce7;
        --players-button-bg: #ffffff;
        --players-button-hover: #f1f5f9;
        --players-info-bg: #eaf3ff;
        --players-info-border: #bfdbfe;
        --players-info-text: #1e40af;
        --players-skeleton: linear-gradient(90deg, rgba(15,23,42,.14), rgba(15,23,42,.06));
    }
    :root[data-theme=dark] {
        --players-page-bg: #18191c;
        --players-topbar-bg: #242528;
        --players-topbar-border: rgba(255,255,255,.06);
        --players-panel-bg: #202124;
        --players-panel-soft: #2f3033;
        --players-panel-mid: #3a3a3d;
        --players-row-bg: #26272a;
        --players-row-alt: #242528;
        --players-row-hover: #2e3137;
        --players-border: rgba(255,255,255,.07);
        --players-text: #f3f4f6;
        --players-muted: #9ca3af;
        --players-soft-text: #d1d5db;
        --players-input-bg: #292a2d;
        --players-input-border: rgba(255,255,255,.06);
        --players-button-bg: #303236;
        --players-button-hover: #3a3d42;
        --players-info-bg: #13243c;
        --players-info-border: rgba(59,130,246,.35);
        --players-info-text: #bcd7ff;
        --players-skeleton: linear-gradient(90deg, rgba(255,255,255,.18), rgba(255,255,255,.08));
    }
    body.players-surface .main { background:var(--players-page-bg); }
    body.players-surface .content { background:var(--players-page-bg); padding:14px 18px 18px; }
    body.players-surface .d-topbar { background:var(--players-topbar-bg); border-bottom-color:var(--players-topbar-border); color:var(--players-text); }
    body.players-surface .crumbs { color:var(--players-muted); }
    body.players-surface .crumbs .current { color:var(--players-text); }
    body.players-surface .cmd,
    body.players-surface .icon-btn { background:var(--players-button-bg); border-color:var(--players-border); color:var(--players-soft-text); }
    body.players-surface .kbd { background:var(--players-panel-soft); border-color:var(--players-border); color:var(--players-muted); }
    body.players-surface .avatar { border-color:var(--players-topbar-bg); }
    body.players-surface .d-footer { display:none; }
    .players-page { display:flex; flex-direction:column; gap:10px; }
    .players-topline { align-items:center; background:var(--players-panel-bg); border:1px solid var(--players-border); border-radius:4px; display:flex; flex-wrap:wrap; gap:20px; padding:9px 12px; }
    .players-stat { align-items:center; color:var(--players-soft-text); display:inline-flex; font-size:11px; font-weight:800; gap:7px; white-space:nowrap; }
    .players-stat:before { background:var(--dot); border-radius:50%; content:""; height:7px; width:7px; }
    .players-stat strong { color:var(--players-text); font-size:13px; font-weight:900; }
    .players-toolbar { align-items:center; display:flex; gap:8px; justify-content:space-between; }
    .players-toolbar-left, .players-toolbar-right { align-items:center; display:flex; flex-wrap:wrap; gap:7px; }
    .players-btn { align-items:center; background:var(--players-button-bg); border:1px solid var(--players-border); border-radius:4px; color:var(--players-soft-text); display:inline-flex; font-size:11px; font-weight:800; gap:6px; min-height:29px; padding:7px 10px; }
    .players-btn svg { fill:none; height:13px; stroke:currentColor; stroke-width:2; width:13px; }
    .players-btn:hover { background:var(--players-button-hover); color:var(--players-text); }
    .players-btn.primary { background:#22c55e; border-color:#22c55e; color:#fff; }
    .players-btn.primary:hover { background:#16a34a; }
    .players-btn .pill { background:#2563eb; border-radius:999px; color:#fff; font-size:10px; line-height:1; padding:3px 6px; }
    .players-info { align-items:center; background:var(--players-info-bg); border:1px solid var(--players-info-border); border-radius:4px; color:var(--players-info-text); display:flex; font-size:11px; font-weight:700; gap:8px; padding:9px 12px; }
    .players-info svg { fill:none; height:14px; stroke:#3b82f6; stroke-width:2; width:14px; }
    .players-card { background:var(--players-panel-bg); border:1px solid var(--players-border); border-radius:5px; box-shadow:var(--shadow-card); overflow:hidden; }
    .players-table-wrap { overflow-x:hidden; width:100%; }
    .players-table { border-collapse:collapse; min-width:0; table-layout:fixed; width:100%; }
    .players-table th { background:var(--players-panel-mid); border-bottom:1px solid var(--players-border); color:var(--players-soft-text); font-size:10px; font-weight:900; overflow:hidden; padding:7px 5px; text-align:left; text-overflow:ellipsis; white-space:nowrap; }
    .players-table th:first-child, .players-table td:first-child { text-align:center; width:3%; }
    .players-table .filter-row th { background:var(--players-panel-soft); padding:5px 8px; }
    .players-filter-input { background:var(--players-input-bg); border:1px solid var(--players-input-border); border-radius:3px; color:var(--players-text); font-size:10px; height:22px; outline:0; padding:0 5px; width:100%; }
    .players-filter-input:focus { border-color:#3b82f6; box-shadow:0 0 0 2px rgba(59,130,246,.16); }
    .players-table td { background:var(--players-row-bg); border-bottom:1px solid var(--players-border); color:var(--players-soft-text); font-size:10.5px; overflow:hidden; padding:6px 5px; text-overflow:ellipsis; white-space:nowrap; }
    .players-table tbody tr:nth-child(even) td { background:var(--players-row-alt); }
    .players-table tbody tr:hover td { background:var(--players-row-hover); }
    .players-check { appearance:none; background:var(--players-input-bg); border:1px solid var(--players-border); border-radius:3px; cursor:pointer; height:14px; width:14px; }
    .players-check:checked { background:#3b82f6; border-color:#3b82f6; box-shadow:inset 0 0 0 3px var(--players-input-bg); }
    .players-user { align-items:center; color:#60a5fa; display:inline-flex; font-weight:900; gap:4px; max-width:100%; min-width:0; overflow:hidden; text-overflow:ellipsis; vertical-align:middle; }
    .players-user svg { fill:none; height:12px; stroke:currentColor; stroke-width:2; width:12px; }
    .players-label { background:#4f46e5; border-radius:3px; color:#c4b5fd; display:inline-flex; font-size:10px; font-weight:900; line-height:1; padding:3px 5px; }
    .players-plus { background:#ef4444; border-radius:3px; color:#fff; display:inline-flex; font-size:10px; font-weight:900; line-height:1; margin-left:4px; padding:3px 5px; }
    .players-mask { color:var(--players-text); font-family:JetBrains Mono,monospace; }
    .players-phone { align-items:center; display:inline-flex; gap:5px; }
    .players-eye { background:#2563eb; border:0; border-radius:4px; color:#fff; cursor:pointer; display:inline-grid; height:18px; margin-left:0; padding:0; place-items:center; width:20px; }
    .players-eye:hover { background:#1d4ed8; }
    .players-eye svg { fill:none; height:12px; stroke:currentColor; stroke-width:2; width:12px; }
    .players-money { color:var(--players-text); font-family:JetBrains Mono,monospace; font-weight:800; }
    .players-cell-text { display:block; max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .players-gender { border-radius:4px; font-size:10px; font-weight:900; padding:3px 7px; }
    .players-gender.male { background:#1e3a8a55; color:#60a5fa; }
    .players-gender.female { background:#9d174d55; color:#f472b6; }
    .players-ok { color:#7dd3fc; font-weight:900; }
    .players-muted-bar { background:var(--players-skeleton); border-radius:3px; display:inline-block; height:15px; min-width:70px; vertical-align:middle; }
    .players-foot { align-items:center; background:var(--players-panel-soft); color:var(--players-soft-text); display:flex; gap:12px; justify-content:space-between; padding:10px 12px; }
    .players-page-size { align-items:center; display:flex; gap:4px; }
    .players-page-size a, .players-pager a, .players-pager span { border-radius:3px; color:var(--players-soft-text); display:inline-flex; font-size:11px; font-weight:800; min-width:24px; padding:5px 7px; place-content:center; }
    .players-page-size .active, .players-pager .active { background:var(--players-panel-mid); color:var(--players-text); }
    .players-empty { color:var(--players-muted); display:none; font-size:12px; font-weight:800; padding:18px; text-align:center; }
    @media(max-width:760px) {
        .players-toolbar { align-items:stretch; flex-direction:column; }
        .players-toolbar-left, .players-toolbar-right { justify-content:flex-start; }
    }
</style>

<section class="players-page">
    <div class="players-topline">
        <?php foreach ($userStats as $stat): ?>
            <span class="players-stat" style="--dot:<?= htmlspecialchars((string) $stat['color'], ENT_QUOTES, 'UTF-8') ?>">
                <strong><?= !empty($stat['money']) ? htmlspecialchars($money($stat['value']), ENT_QUOTES, 'UTF-8') : htmlspecialchars(number_format((float) $stat['value'], 0, ',', '.'), ENT_QUOTES, 'UTF-8') ?></strong>
                <?= htmlspecialchars((string) $stat['label'], ENT_QUOTES, 'UTF-8') ?>
            </span>
        <?php endforeach; ?>
    </div>

    <div class="players-toolbar">
        <form class="players-toolbar-left" method="get" action="<?= htmlspecialchars(AdminAuth::url('/module'), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="key" value="users">
            <input type="hidden" name="per_page" value="<?= htmlspecialchars((string) $perPage, ENT_QUOTES, 'UTF-8') ?>">
            <button class="players-btn" type="submit"><svg viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>Filtre <span class="pill"><?= $search !== '' ? '1' : '0' ?></span></button>
            <input class="players-filter-input admin-search-md" type="search" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Oyuncu listesinde ara..." data-players-global-filter>
            <a class="players-btn" href="<?= htmlspecialchars(AdminAuth::url('/module?key=users'), ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg></a>
        </form>
        <div class="players-toolbar-right">
            <button class="players-btn" type="button" data-players-export><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>Excel İndir</button>
            <a class="players-btn" href="<?= htmlspecialchars(AdminAuth::url('/email'), ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>Mail Gönder</a>
            <a class="players-btn" href="<?= htmlspecialchars(AdminAuth::url('/email'), ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><path d="M21 15a4 4 0 0 1-4 4H7l-4 4V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>Mesaj Gönder</a>
            <a class="players-btn primary" href="<?= htmlspecialchars(AdminAuth::url('/user/create'), ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>Oyuncu Ekle</a>
        </div>
    </div>

    <div class="players-info">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
        Oyuncu listesi, kullanıcıları son giriş tarihine göre sıralar. Harici filtreleme seçeneklerine ek olarak tablo içerisindeki her veri filtrelenebilir.
    </div>

    <section class="players-card">
        <div class="players-table-wrap">
            <table class="players-table" data-players-table>
                <colgroup>
                    <col style="width:3%">
                    <?php foreach ($userColumns as $column): ?><col style="width:<?= htmlspecialchars($column['width'], ENT_QUOTES, 'UTF-8') ?>"><?php endforeach; ?>
                </colgroup>
                <thead>
                <tr>
                    <th><input class="players-check" type="checkbox" data-players-check-all></th>
                    <?php foreach ($userColumns as $column): ?><th><?= htmlspecialchars($column['label'], ENT_QUOTES, 'UTF-8') ?></th><?php endforeach; ?>
                </tr>
                <tr class="filter-row">
                    <th></th>
                    <?php foreach ($userColumns as $filterIndex => $column): ?><th><input class="players-filter-input" type="text" aria-label="<?= htmlspecialchars($column['label'], ENT_QUOTES, 'UTF-8') ?>" data-players-column-filter="<?= $filterIndex + 1 ?>"></th><?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $index => $row): ?>
                    <?php
                    $id = (string) ($row[$primaryKey] ?? $row['id'] ?? '');
                    $username = (string) ($row['username'] ?? '-');
                    $isActive = (string) ($row['banned'] ?? '0') !== '1';
                    $gender = strtolower((string) ($row['gender'] ?? ''));
                    $genderLabel = $gender === 'female' || $gender === 'kadin' || $gender === 'kadın' ? 'Kadın' : 'Erkek';
                    $phoneRaw = trim((string) ($row['phone'] ?? ''));
                    $phoneDisplay = $phoneRaw !== '' ? $phoneRaw : '-';
                    $phoneMasked = $phoneRaw !== '' ? substr($phoneRaw, 0, 2) . str_repeat('*', max(6, strlen($phoneRaw) - 2)) : '-';
                    $balanceValue = (float) ($row['balance'] ?? 0);
                    $emailRaw = trim((string) ($row['email'] ?? ''));
                    $identityRaw = trim((string) ($row['identity_number'] ?? $row['tc_no'] ?? ''));
                    ?>
                    <tr data-players-row>
                        <td><input class="players-check" type="checkbox" data-players-row-check></td>
                        <td><?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($row['partner'] ?? $row['source'] ?? 'System User'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="players-label"><?= htmlspecialchars(strtoupper(substr($username, 0, 1) ?: 'S'), ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td>
                            <a class="players-user" href="<?= htmlspecialchars(AdminAuth::url('/user?id=' . rawurlencode($id)), ENT_QUOTES, 'UTF-8') ?>">
                                <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>
                            </a>
                            <?php if (($index + 1) % 3 === 0): ?><span class="players-plus">+<?= ($index + 1) * 2 ?></span><?php endif; ?>
                        </td>
                        <td><?= trim((string) ($row['name'] ?? '')) !== '' ? htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') : '<span class="players-muted-bar"></span>' ?></td>
                        <td><?= trim((string) ($row['surname'] ?? '')) !== '' ? htmlspecialchars((string) $row['surname'], ENT_QUOTES, 'UTF-8') : '<span class="players-muted-bar"></span>' ?></td>
                        <td data-filter-value="<?= htmlspecialchars($phoneDisplay, ENT_QUOTES, 'UTF-8') ?>" data-export-value="<?= htmlspecialchars($phoneDisplay, ENT_QUOTES, 'UTF-8') ?>">
                            <span class="players-phone">
                                <span class="players-mask" data-phone-mask="<?= htmlspecialchars($phoneMasked, ENT_QUOTES, 'UTF-8') ?>" data-phone-full="<?= htmlspecialchars($phoneDisplay, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($phoneMasked, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($phoneRaw !== ''): ?>
                                    <button class="players-eye" type="button" aria-label="Telefonu göster" title="Telefonu göster" data-phone-toggle aria-pressed="false"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td data-filter-value="<?= htmlspecialchars($emailRaw, ENT_QUOTES, 'UTF-8') ?>" data-export-value="<?= htmlspecialchars($emailRaw, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($emailRaw, ENT_QUOTES, 'UTF-8') ?>">
                            <span class="players-cell-text"><?= $emailRaw !== '' ? htmlspecialchars($emailRaw, ENT_QUOTES, 'UTF-8') : '*' ?></span>
                        </td>
                        <td data-filter-value="<?= htmlspecialchars($money($balanceValue), ENT_QUOTES, 'UTF-8') ?>" data-export-value="<?= htmlspecialchars($money($balanceValue), ENT_QUOTES, 'UTF-8') ?>"><span class="players-money"><?= htmlspecialchars($money($balanceValue), ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><span class="players-gender <?= $genderLabel === 'Kadın' ? 'female' : 'male' ?>"><?= htmlspecialchars($genderLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td data-filter-value="<?= htmlspecialchars($identityRaw, ENT_QUOTES, 'UTF-8') ?>" data-export-value="<?= htmlspecialchars($identityRaw, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($identityRaw, ENT_QUOTES, 'UTF-8') ?>">
                            <span class="players-cell-text"><?= $identityRaw !== '' ? htmlspecialchars($identityRaw, ENT_QUOTES, 'UTF-8') : '*' ?></span>
                        </td>
                        <td><span class="players-ok"><?= $isActive ? '✓' : '×' ?></span></td>
                        <td><?= htmlspecialchars(strtoupper((string) ($row['currency'] ?? 'TRY')) === 'TRY' ? '₺' : (string) ($row['currency'] ?? '₺'), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="<?= count($userColumns) + 1 ?>">Kayıt bulunamadı.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="players-empty" data-players-empty>Filtreye uygun oyuncu bulunamadı.</div>
        <div class="players-foot">
            <div class="players-page-size">
                <?php foreach ([12, 25, 50, 100, 200] as $size): ?>
                    <a class="<?= $perPage === $size ? 'active' : '' ?>" href="<?= htmlspecialchars(AdminAuth::url('/module?key=users&search=' . rawurlencode($search) . '&per_page=' . $size), ENT_QUOTES, 'UTF-8') ?>"><?= $size ?></a>
                <?php endforeach; ?>
            </div>
            <div class="players-pager">
                <span>Sayfa #<?= $page ?>. Toplam Sayfa: <?= $totalPages ?> (<?= $total ?> Adet İçerik)</span>
                <?php for ($i = max(1, $page - 1); $i <= min($totalPages, $page + 3); $i++): ?>
                    <a class="<?= $i === $page ? 'active' : '' ?>" href="<?= htmlspecialchars(AdminAuth::url('/module?key=users&search=' . rawurlencode($search) . '&per_page=' . $perPage . '&page=' . $i), ENT_QUOTES, 'UTF-8') ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </section>
</section>
<script>
    (function () {
        var table = document.querySelector('[data-players-table]');
        if (!table) return;

        var rows = Array.prototype.slice.call(table.querySelectorAll('[data-players-row]'));
        var empty = document.querySelector('[data-players-empty]');
        var globalFilter = document.querySelector('[data-players-global-filter]');
        var columnFilters = Array.prototype.slice.call(document.querySelectorAll('[data-players-column-filter]'));
        var checkAll = document.querySelector('[data-players-check-all]');
        var exportButton = document.querySelector('[data-players-export]');

        function normalized(value) {
            return String(value || '').toLocaleLowerCase('tr-TR').trim();
        }

        function cellValue(cell) {
            return cell ? (cell.getAttribute('data-filter-value') || cell.textContent || '') : '';
        }

        function rowText(row) {
            return normalized(Array.prototype.slice.call(row.cells).map(cellValue).join(' '));
        }

        function updateCheckAllState() {
            if (!checkAll) return;
            var visible = visibleRows();
            var checked = visible.filter(function (row) {
                var checkbox = row.querySelector('[data-players-row-check]');
                return checkbox && checkbox.checked;
            }).length;
            checkAll.indeterminate = checked > 0 && checked < visible.length;
            checkAll.checked = visible.length > 0 && checked === visible.length;
        }

        function applyFilters() {
            var globalQuery = normalized(globalFilter ? globalFilter.value : '');
            var activeFilters = columnFilters.map(function (input) {
                return {
                    index: Number(input.getAttribute('data-players-column-filter') || 0),
                    value: normalized(input.value)
                };
            }).filter(function (filter) {
                return filter.index > 0 && filter.value !== '';
            });
            var visible = 0;

            rows.forEach(function (row) {
                var matchesGlobal = !globalQuery || rowText(row).indexOf(globalQuery) !== -1;
                var matchesColumns = activeFilters.every(function (filter) {
                    var cell = row.cells[filter.index];
                    return cell && normalized(cellValue(cell)).indexOf(filter.value) !== -1;
                });
                var show = matchesGlobal && matchesColumns;
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            if (empty) empty.style.display = visible === 0 ? 'block' : 'none';
            updateCheckAllState();
        }

        function visibleRows() {
            return rows.filter(function (row) {
                return row.style.display !== 'none';
            });
        }

        if (globalFilter) {
            globalFilter.addEventListener('input', applyFilters);
        }
        columnFilters.forEach(function (input) {
            input.addEventListener('input', applyFilters);
        });

        if (checkAll) {
            checkAll.addEventListener('change', function () {
                visibleRows().forEach(function (row) {
                    var checkbox = row.querySelector('[data-players-row-check]');
                    if (checkbox) checkbox.checked = checkAll.checked;
                });
                updateCheckAllState();
            });
        }

        rows.forEach(function (row) {
            var checkbox = row.querySelector('[data-players-row-check]');
            if (checkbox) checkbox.addEventListener('change', updateCheckAllState);
        });

        table.querySelectorAll('[data-phone-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var wrap = button.closest('.players-phone');
                var value = wrap ? wrap.querySelector('[data-phone-mask]') : null;
                if (!value) return;
                var isVisible = button.getAttribute('aria-pressed') === 'true';
                value.textContent = isVisible ? value.getAttribute('data-phone-mask') : value.getAttribute('data-phone-full');
                button.setAttribute('aria-pressed', isVisible ? 'false' : 'true');
                button.setAttribute('aria-label', isVisible ? 'Telefonu göster' : 'Telefonu gizle');
                button.setAttribute('title', isVisible ? 'Telefonu göster' : 'Telefonu gizle');
            });
        });

        if (exportButton) {
            exportButton.addEventListener('click', function () {
                var headers = Array.prototype.slice.call(table.querySelectorAll('thead tr:first-child th'))
                    .slice(1)
                    .map(function (cell) { return '"' + String(cell.textContent || '').trim().replace(/"/g, '""') + '"'; });
                var lines = [headers.join(',')];
                visibleRows().forEach(function (row) {
                    var cells = Array.prototype.slice.call(row.cells).slice(1).map(function (cell) {
                        var value = cell.getAttribute('data-export-value') || cell.textContent || '';
                        return '"' + String(value).replace(/\s+/g, ' ').trim().replace(/"/g, '""') + '"';
                    });
                    lines.push(cells.join(','));
                });
                var blob = new Blob(["\uFEFF" + lines.join("\n")], { type: 'text/csv;charset=utf-8;' });
                var url = URL.createObjectURL(blob);
                var link = document.createElement('a');
                link.href = url;
                link.download = 'oyuncular.csv';
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(url);
            });
        }

        applyFilters();
    })();
</script>
<?php return; ?>
<?php endif; ?>
<?php
$listPath = $moduleKey !== '' ? '/module' : '/table';
$listIdentityName = $moduleKey !== '' ? 'key' : 'name';
$listIdentityValue = $moduleKey !== '' ? $moduleKey : $table;
$baseListQuery = ($moduleKey !== '' ? '/module?key=' . rawurlencode($moduleKey) : '/table?name=' . rawurlencode($table));
$createUrl = AdminAuth::url('/table/create?name=' . rawurlencode($table) . ($moduleKey !== '' ? '&module=' . rawurlencode($moduleKey) : ''));
$reservedWidth = 3 + (int) rtrim($actionColumnWidth, '%');
$preferredWidths = [];
$preferredTotal = 0;
foreach ($visibleColumns as $column) {
    $width = (int) rtrim($columnWidth((string) $column['name']), '%');
    $preferredWidths[] = $width;
    $preferredTotal += $width;
}
$availableWidth = max(40, 100 - $reservedWidth);
$scale = $preferredTotal > $availableWidth ? $availableWidth / $preferredTotal : 1;
?>
<section class="admin-surface">
    <div class="hero">
        <div class="hero-text">
            <span class="eyebrow">Nexthub · Modül</span>
            <h1 class="hero-title"><?= htmlspecialchars((string) ($module['title'] ?? $table), ENT_QUOTES, 'UTF-8') ?> <span class="accent">kayıtları</span></h1>
            <p class="hero-sub"><?= htmlspecialchars((string) $total, ENT_QUOTES, 'UTF-8') ?> kayıt · Arama, kolon filtreleri, seçim ve dışa aktarma bu ekranda yönetilir.</p>
        </div>
        <?php if (!$isReadOnlyModule && !$isWriteProtectedTable): ?>
        <div class="hero-actions">
            <a class="admin-action-btn primary" href="<?= htmlspecialchars($createUrl, ENT_QUOTES, 'UTF-8') ?>">
                <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                Yeni Kayıt
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($tableError !== ''): ?>
        <div class="card admin-compact-card" style="margin-bottom:16px;padding:16px;border-left:3px solid var(--warning)">
            <strong>Veritabanı uyarısı:</strong> <?= htmlspecialchars($tableError, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="admin-actionbar">
        <form method="get" action="<?= htmlspecialchars(AdminAuth::url($listPath), ENT_QUOTES, 'UTF-8') ?>" class="admin-actionbar-left">
            <input type="hidden" name="<?= $listIdentityName ?>" value="<?= htmlspecialchars($listIdentityValue, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="per_page" value="<?= htmlspecialchars((string) $perPage, ENT_QUOTES, 'UTF-8') ?>">
            <button class="admin-action-btn" type="submit"><svg viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>Filtre <span class="badge primary"><?= $search !== '' ? '1' : '0' ?></span></button>
            <input class="admin-filter-input admin-search-lg" type="search" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars((string) ($module['search_placeholder'] ?? 'Tabloda ara...'), ENT_QUOTES, 'UTF-8') ?>" data-admin-compact-global-filter>
            <a class="admin-action-btn" href="<?= htmlspecialchars(AdminAuth::url($baseListQuery), ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg></a>
        </form>
        <div class="admin-actionbar-right">
            <button class="admin-action-btn" type="button" data-admin-compact-export data-export-name="<?= htmlspecialchars($moduleKey !== '' ? $moduleKey : $table, ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>Excel İndir</button>
            <form method="get" action="<?= htmlspecialchars(AdminAuth::url($listPath), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="<?= $listIdentityName ?>" value="<?= htmlspecialchars($listIdentityValue, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                <select class="select admin-select-compact" name="per_page" onchange="this.form.submit()">
                    <?php foreach ([10, 25, 50, 100] as $option): ?>
                        <option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= $option ?> / sayfa</option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <div class="admin-compact-info">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
        Kolon filtreleri ekranda görünen kayıtlar üzerinde çalışır. CSV dışa aktarma yalnızca görünür satırları indirir.
    </div>

    <section class="admin-compact-card">
    <div class="admin-compact-table-wrap">
        <table class="admin-compact-table" data-admin-compact-table>
            <colgroup>
                <col style="width:3%">
                <?php foreach ($visibleColumns as $columnIndex => $column): ?>
                    <?php $scaledWidth = max(5, (int) floor(($preferredWidths[$columnIndex] ?? 10) * $scale)); ?>
                    <col style="width:<?= htmlspecialchars((string) $scaledWidth . '%', ENT_QUOTES, 'UTF-8') ?>">
                <?php endforeach; ?>
                <col style="width:<?= htmlspecialchars($actionColumnWidth, ENT_QUOTES, 'UTF-8') ?>">
            </colgroup>
            <thead>
            <tr>
                <th><input class="admin-row-check" type="checkbox" data-admin-compact-check-all></th>
                <?php foreach ($visibleColumns as $column): ?>
                    <th><?= htmlspecialchars($columnLabel((string) $column['name']), ENT_QUOTES, 'UTF-8') ?></th>
                <?php endforeach; ?>
                <th>İşlem</th>
            </tr>
            <tr class="admin-filter-row">
                <th></th>
                <?php foreach ($visibleColumns as $filterIndex => $column): ?>
                    <?php
                    $filterColumnName = (string) $column['name'];
                    $filterColumnType = strtolower((string) ($column['data_type'] ?? ''));
                    $filterInputType = in_array($filterColumnType, ['date', 'datetime', 'timestamp'], true) || preg_match('/created_at|updated_at|date|deadline|submitted_at|reviewed_at|processed_at/i', $filterColumnName) === 1
                        ? 'date'
                        : (in_array($filterColumnType, ['int', 'bigint', 'smallint', 'mediumint', 'decimal', 'float', 'double'], true) ? 'number' : 'text');
                    ?>
                    <th><input class="admin-filter-input" type="<?= $filterInputType ?>" aria-label="<?= htmlspecialchars($columnLabel($filterColumnName), ENT_QUOTES, 'UTF-8') ?>" data-admin-compact-column-filter="<?= $filterIndex + 1 ?>"></th>
                <?php endforeach; ?>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="<?= count($visibleColumns) + 2 ?>">Kayıt bulunamadı.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $index => $row): ?>
                <tr data-admin-compact-row>
                    <td><input class="admin-row-check" type="checkbox" data-admin-compact-row-check></td>
                    <?php foreach ($visibleColumns as $column): ?>
                        <?php
                        $columnName = (string) $column['name'];
                        $rawValue = $row[$columnName] ?? null;
                        $textValue = $formatValue($columnName, $rawValue);
                        ?>
                        <td title="<?= htmlspecialchars($textValue, ENT_QUOTES, 'UTF-8') ?>" data-filter-value="<?= htmlspecialchars($textValue, ENT_QUOTES, 'UTF-8') ?>" data-export-value="<?= htmlspecialchars($textValue, ENT_QUOTES, 'UTF-8') ?>">
                            <?php if (preg_match('/(^image_url$|thumbnail|banner|cover|logo)/i', $columnName) === 1): ?>
                                <?php
                                $imageUrl = trim((string) $rawValue);
                                if ($imageUrl === '' && $columnName === 'image_url') {
                                    $imageUrl = trim((string) ($row['banner'] ?? ''));
                                }
                                if ($imageUrl === '' && !empty($row['raw_payload'])) {
                                    $rawPayload = json_decode((string) $row['raw_payload'], true);
                                    if (is_array($rawPayload)) {
                                        $imageUrl = trim((string) ($rawPayload['banner'] ?? $rawPayload['image_url'] ?? $rawPayload['image'] ?? ''));
                                    }
                                }
                                ?>
                                <?php if ($imageUrl !== ''): ?>
                                    <img class="admin-game-thumb" src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Ön izleme" loading="lazy" onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'admin-game-thumb-placeholder',textContent:'Yok'}));">
                                <?php else: ?>
                                    <span class="admin-game-thumb-placeholder">Yok</span>
                                <?php endif; ?>
                            <?php elseif (in_array($columnName, ['username', 'email', 'name', 'title'], true)): ?>
                                <div class="data-cell-user" style="min-width:0">
                                    <div class="av ma-<?= ($index % 6) + 1 ?>"><?= htmlspecialchars(strtoupper(substr($textValue, 0, 2)), ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="data-cell-user-meta" style="min-width:0">
                                        <div class="data-cell-user-name" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($textValue, ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="data-cell-user-email" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($table, ENT_QUOTES, 'UTF-8') ?></div>
                                    </div>
                                </div>
                            <?php elseif (preg_match('/status|active|verified|banned|enabled/i', $columnName) === 1): ?>
                                <span class="badge <?= htmlspecialchars($badgeClass((string) $rawValue), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($textValue, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php elseif (preg_match('/id$|_id$|amount|balance|created_at|updated_at|date/i', $columnName) === 1): ?>
                                <span class="data-cell-mono"><?= htmlspecialchars($textValue, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <span class="admin-cell-text"><?= htmlspecialchars($textValue, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    <td>
                        <div class="data-cell-actions">
                        <?php if ($primaryKey !== '' && isset($row[$primaryKey])): ?>
                            <?php $id = (string) $row[$primaryKey]; ?>
                            <?php if ($moduleKey === 'withdrawals' && (string) ($row['status'] ?? '') === 'pending'): ?>
                                <form class="admin-inline-form" method="post" action="<?= htmlspecialchars(AdminAuth::url('/megapayz/withdraw/approve'), ENT_QUOTES, 'UTF-8') ?>" data-admin-confirm="Bu çekim MegaPayz API’ye iletilsin mi?">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">
                                    <button class="admin-tx-action admin-tx-action--approve" aria-label="Approve withdraw" title="Onayla" type="submit"><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>Onayla</button>
                                </form>
                                <form class="admin-inline-form" method="post" action="<?= htmlspecialchars(AdminAuth::url('/megapayz/withdraw/reject'), ENT_QUOTES, 'UTF-8') ?>" data-admin-confirm="Bu çekim reddedilsin ve bakiye iade edilsin mi?">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">
                                    <button class="admin-tx-action admin-tx-action--reject" aria-label="Reject withdraw" title="Reddet" type="submit"><svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>Red</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($moduleKey === 'promocode-requests' && (string) ($row['status'] ?? '') === 'pending'): ?>
                                <form class="admin-inline-form" method="post" action="<?= htmlspecialchars(AdminAuth::url('/promocode-request/approve'), ENT_QUOTES, 'UTF-8') ?>" data-admin-confirm="Bu promo talep onaylansın ve üye bakiyesine eklensin mi?">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">
                                    <button class="admin-tx-action admin-tx-action--approve" aria-label="Approve promo request" title="Onayla" type="submit"><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>Onayla</button>
                                </form>
                                <form class="admin-inline-form" method="post" action="<?= htmlspecialchars(AdminAuth::url('/promocode-request/reject'), ENT_QUOTES, 'UTF-8') ?>" data-admin-confirm="Bu promo talep reddedilsin mi?">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">
                                    <button class="admin-tx-action admin-tx-action--reject" aria-label="Reject promo request" title="Reddet" type="submit"><svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>Red</button>
                                </form>
                            <?php endif; ?>
                            <?php if (!$isReadOnlyModule && !$isWriteProtectedTable): ?>
                                <?php $viewUrl = AdminAuth::url($table === 'users' ? '/user?id=' . rawurlencode($id) : '/table/edit?name=' . rawurlencode($table) . '&id=' . rawurlencode($id) . ($moduleKey !== '' ? '&module=' . rawurlencode($moduleKey) : '')); ?>
                                <?php $editUrl = AdminAuth::url($table === 'users' ? '/user/edit?id=' . rawurlencode($id) : '/table/edit?name=' . rawurlencode($table) . '&id=' . rawurlencode($id) . ($moduleKey !== '' ? '&module=' . rawurlencode($moduleKey) : '')); ?>
                                <a class="btn--icon" aria-label="View" href="<?= htmlspecialchars($viewUrl, ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
                                <a class="btn--icon" aria-label="Edit" href="<?= htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') ?>" data-admin-modal-url="<?= htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') ?>" data-admin-modal-title="<?= htmlspecialchars((string) ($module['title'] ?? $table) . ' düzenle', ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4z"/></svg></a>
                                <form class="admin-inline-form" method="post" action="<?= htmlspecialchars(AdminAuth::url('/table/delete'), ENT_QUOTES, 'UTF-8') ?>" data-admin-confirm="Bu kayıt silinsin mi?">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="name" value="<?= htmlspecialchars($table, ENT_QUOTES, 'UTF-8') ?>">
                                    <?php if ($moduleKey !== ''): ?><input type="hidden" name="module" value="<?= htmlspecialchars($moduleKey, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
                                    <input type="hidden" name="_id" value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">
                                    <button class="btn--icon" aria-label="Delete" type="submit"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6 18 20a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg></button>
                                </form>
                            <?php else: ?>
                                <?php $viewUrl = AdminAuth::url('/table/view?name=' . rawurlencode($table) . '&id=' . rawurlencode($id) . ($moduleKey !== '' ? '&module=' . rawurlencode($moduleKey) : '')); ?>
                                <a class="btn--icon" aria-label="View" title="Görüntüle" href="<?= htmlspecialchars($viewUrl, ENT_QUOTES, 'UTF-8') ?>" data-admin-modal-url="<?= htmlspecialchars($viewUrl, ENT_QUOTES, 'UTF-8') ?>" data-admin-modal-title="<?= htmlspecialchars((string) ($module['title'] ?? $table) . ' görüntüle', ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="tag t-unavail">Primary key yok</span>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="admin-empty-state" data-admin-compact-empty>Filtreye uygun kayıt bulunamadı.</div>

    <div class="admin-compact-foot">
        <div class="admin-page-size">
            <?php foreach ([10, 25, 50, 100] as $size): ?>
                <a class="<?= $perPage === $size ? 'active' : '' ?>" href="<?= htmlspecialchars(AdminAuth::url($baseListQuery . '&search=' . rawurlencode($search) . '&per_page=' . $size), ENT_QUOTES, 'UTF-8') ?>"><?= $size ?></a>
            <?php endforeach; ?>
        </div>
        <div class="admin-compact-pager">
            <span><?= $pageStart ?>-<?= $pageEnd ?> / <?= $total ?></span>
            <span class="badge solid"><?= htmlspecialchars($table, ENT_QUOTES, 'UTF-8') ?></span>
            <?php if ($page > 1): ?>
                <a href="<?= htmlspecialchars(AdminAuth::url($baseListQuery . '&search=' . rawurlencode($search) . '&per_page=' . $perPage . '&page=' . ($page - 1)), ENT_QUOTES, 'UTF-8') ?>" aria-label="Previous">‹</a>
            <?php endif; ?>
            <span class="active"><?= $page ?></span>
            <span>/</span>
            <span><?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="<?= htmlspecialchars(AdminAuth::url($baseListQuery . '&search=' . rawurlencode($search) . '&per_page=' . $perPage . '&page=' . ($page + 1)), ENT_QUOTES, 'UTF-8') ?>" aria-label="Next">›</a>
            <?php endif; ?>
        </div>
    </div>
</section>
</section>
