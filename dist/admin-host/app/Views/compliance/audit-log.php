<?php

$items = is_array($items ?? null) ? $items : [];
$total = (int) ($total ?? 0);
$page = (int) ($page ?? 1);
$perPage = (int) ($perPage ?? 25);
$totalPages = (int) ($totalPages ?? 1);
$flash = trim((string) ($flash ?? ''));

$text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$badgeClass = static function (string $v): string {
    return match (strtolower($v)) {
        'success', 'approved', 'resolved' => 'success dot',
        'failed', 'rejected' => 'danger dot',
        default => 'primary',
    };
};
?>
<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Uyumluluk</span>
        <h1 class="hero-title">API <span class="accent">audit log</span></h1>
        <p class="hero-sub">Admin API katmanı üzerinden gerçekleştirilen tüm kritik işlemlerin kaydı.</p>
    </div>
</div>

<?php if ($flash !== ''): ?>
    <div class="alert alert--info"><?= $text($flash) ?></div>
<?php endif; ?>

<section class="card admin-compact-card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">API İşlem Kaydı</span>
            <h2 class="card-title">Tüm audit loglar <span class="badge primary"><?= number_format($total) ?></span></h2>
        </div>
    </div>
    <div class="admin-compact-table-wrap">
        <table class="admin-compact-table">
            <thead>
                <tr>
                    <th>ID</th>
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
                <tr><td colspan="8">Kayıt bulunamadı. <code>admin_audit_logs</code> tablosu boş.</td></tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= $text($item['id'] ?? '') ?></td>
                        <td><?= $text($item['admin_username'] ?? $item['admin_id'] ?? '-') ?></td>
                        <td><span class="badge primary" style="font-size:10px"><?= $text($item['action'] ?? '') ?></span></td>
                        <td><?= $text($item['entity_type'] ?? '-') ?></td>
                        <td><?= $text($item['entity_id'] ?? '-') ?></td>
                        <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= $text($item['note'] ?? '') ?>"><?= $text(substr((string) ($item['note'] ?? ''), 0, 80)) ?></td>
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
        <span><?= number_format($total) ?> kayıt · sayfa <?= $page ?>/<?= $totalPages ?></span>
        <div style="display:flex;gap:4px">
            <?php for ($p = max(1, $page - 3); $p <= min($totalPages, $page + 3); $p++): ?>
                <a href="<?= $text(AdminAuth::url('/compliance/audit-log?page=' . $p)) ?>"
                   style="padding:4px 8px;border-radius:6px;<?= $p === $page ? 'background:var(--accent);color:#fff;font-weight:700' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</section>
</section>
