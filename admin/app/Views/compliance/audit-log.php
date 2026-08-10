<?php

$items = is_array($items ?? null) ? $items : [];
$total = (int) ($total ?? 0);
$page = max(1, (int) ($page ?? 1));
$perPage = (int) ($perPage ?? 25);
$totalPages = max(1, (int) ($totalPages ?? 1));
$flash = trim((string) ($flash ?? ''));
$q = trim((string) ($q ?? ''));
$actionFilter = trim((string) ($actionFilter ?? ''));
$adminFilter = trim((string) ($adminFilter ?? ''));
$sourceFilter = trim((string) ($sourceFilter ?? ''));
$actionOptions = is_array($actionOptions ?? null) ? $actionOptions : [];
$text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$base = AdminAuth::url('/compliance/audit-log');
$queryUrl = static function (array $extra) use ($q, $actionFilter, $adminFilter, $sourceFilter, $base): string {
    $params = array_filter([
        'q' => $extra['q'] ?? $q,
        'action' => $extra['action'] ?? $actionFilter,
        'admin' => $extra['admin'] ?? $adminFilter,
        'source' => $extra['source'] ?? $sourceFilter,
        'page' => $extra['page'] ?? null,
    ], static fn ($v) => $v !== null && $v !== '');
    return $base . ($params !== [] ? ('?' . http_build_query($params)) : '');
};
?>
<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Uyumluluk</span>
        <h1 class="hero-title">Denetim <span class="accent">Logu</span></h1>
        <p class="hero-sub">Panel ve API üzerinden yapılan kritik admin işlemlerinin birleşik kaydı.</p>
    </div>
</div>

<?php if ($flash !== ''): ?>
    <div class="alert alert--info"><?= $text($flash) ?></div>
<?php endif; ?>

<section class="card" style="margin-bottom:14px">
    <form method="get" action="<?= $text($base) ?>" style="display:flex;flex-wrap:wrap;gap:8px;align-items:end">
        <label>Ara
            <input class="input" type="search" name="q" value="<?= $text($q) ?>" placeholder="işlem, admin, entity, not">
        </label>
        <label>İşlem
            <select class="input" name="action">
                <option value="">Tümü</option>
                <?php foreach ($actionOptions as $opt): ?>
                    <option value="<?= $text($opt) ?>" <?= $actionFilter === $opt ? 'selected' : '' ?>><?= $text($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Admin
            <input class="input" type="text" name="admin" value="<?= $text($adminFilter) ?>" placeholder="kullanıcı adı">
        </label>
        <label>Kaynak
            <select class="input" name="source">
                <option value="" <?= $sourceFilter === '' ? 'selected' : '' ?>>Tümü</option>
                <option value="audit" <?= $sourceFilter === 'audit' ? 'selected' : '' ?>>Audit</option>
                <option value="panel" <?= $sourceFilter === 'panel' ? 'selected' : '' ?>>Panel</option>
            </select>
        </label>
        <button class="btn btn--ghost" type="submit">Filtrele</button>
        <a class="btn btn--ghost" href="<?= $text($base) ?>">Sıfırla</a>
    </form>
</section>

<section class="card admin-compact-card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">Denetim Kaydı</span>
            <h2 class="card-title">Tüm kayıtlar <span class="badge primary"><?= number_format($total) ?></span></h2>
        </div>
    </div>
    <div class="admin-compact-table-wrap">
        <table class="admin-compact-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Kaynak</th>
                    <th>Admin</th>
                    <th>İşlem</th>
                    <th>Entity tipi</th>
                    <th>Entity ID</th>
                    <th>Not</th>
                    <th>IP</th>
                    <th>Tarih</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($items === []): ?>
                <tr><td colspan="9">Kayıt bulunamadı.</td></tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <?php
                    $src = strtolower((string) ($item['source'] ?? 'audit'));
                    $srcClass = $src === 'panel' ? 'info' : 'success';
                    $entityId = trim((string) ($item['entity_id'] ?? ''));
                    $entityType = strtolower((string) ($item['entity_type'] ?? ''));
                    $userLink = '';
                    if ($entityId !== '' && ctype_digit($entityId) && in_array($entityType, ['user', 'users', 'user_balance'], true)) {
                        $userLink = AdminAuth::url('/user?id=' . $entityId);
                    }
                    ?>
                    <tr>
                        <td>#<?= $text($item['id'] ?? '') ?></td>
                        <td><span class="badge <?= $text($srcClass) ?>"><?= $text($src) ?></span></td>
                        <td><?= $text($item['admin_username'] ?? (($item['admin_id'] ?? '') !== '' ? '#' . $item['admin_id'] : '-')) ?></td>
                        <td><span class="badge primary" style="font-size:10px"><?= $text($item['action'] ?? '') ?></span></td>
                        <td><?= $text($item['entity_type'] ?? '-') ?></td>
                        <td>
                            <?php if ($userLink !== ''): ?>
                                <a href="<?= $text($userLink) ?>"><?= $text($entityId) ?></a>
                            <?php else: ?>
                                <?= $text($entityId !== '' ? $entityId : '-') ?>
                            <?php endif; ?>
                        </td>
                        <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= $text($item['note'] ?? '') ?>"><?= $text(substr((string) ($item['note'] ?? ''), 0, 100) ?: '—') ?></td>
                        <td><?= $text($item['ip_address'] ?? '-') ?></td>
                        <td><?= $text($item['created_at'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;font-size:12px;color:var(--t-light)">
        <span><?= number_format($total) ?> kayıt · sayfa <?= htmlspecialchars((string) $page, ENT_QUOTES, 'UTF-8') ?>/<?= htmlspecialchars((string) $totalPages, ENT_QUOTES, 'UTF-8') ?></span>
        <div style="display:flex;gap:4px">
            <?php for ($p = max(1, $page - 3); $p <= min($totalPages, $page + 3); $p++): ?>
                <a href="<?= $text($queryUrl(['page' => $p])) ?>"
                   style="padding:4px 8px;border-radius:6px;<?= $p === $page ? 'background:var(--accent);color:#fff;font-weight:700' : '' ?>"><?= htmlspecialchars((string) $p, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</section>
</section>
