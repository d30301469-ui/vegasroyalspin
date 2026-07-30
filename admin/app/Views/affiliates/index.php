<?php
$affiliates = is_array($affiliates ?? null) ? $affiliates : [];
$text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$money = static fn (mixed $v): string => number_format((float) $v, 2, ',', '.');
$flash = (string) ($flash ?? '');
$statusLabel = static fn (string $s): string => match ($s) {
    'active' => 'Aktif', 'pending' => 'Bekliyor', 'suspended' => 'Askıda',
    'rejected' => 'Red', default => $s,
};
$badgeClass = static fn (string $s): string => match ($s) {
    'active' => 'success dot', 'pending' => 'warning dot',
    'suspended', 'rejected' => 'danger dot', default => 'primary dot',
};
?>
<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Ortaklık Sistemi</span>
        <h1 class="hero-title">Ortak <span class="accent">yönetimi</span></h1>
        <p class="hero-sub">Tüm ortakları görüntüleyin, onaylayın ve yönetin.</p>
    </div>
</div>
<?php if ($flash !== ''): ?>
<div class="alert is-success"><?= $text($flash) ?></div>
<?php endif; ?>

<!-- Data toolbar -->
<div class="data-toolbar">
    <div class="data-toolbar-group">
        <form method="get" action="<?= AdminAuth::url('/affiliates') ?>" class="data-toolbar-filter" style="display:flex;gap:8px">
            <select name="status" class="select" style="width:140px" onchange="this.form.submit()">
                <option value="">Tümü</option>
                <option value="active" <?= ($status ?? '') === 'active' ? 'selected' : '' ?>>Aktif</option>
                <option value="pending" <?= ($status ?? '') === 'pending' ? 'selected' : '' ?>>Bekleyen</option>
                <option value="suspended" <?= ($status ?? '') === 'suspended' ? 'selected' : '' ?>>Askıda</option>
                <option value="rejected" <?= ($status ?? '') === 'rejected' ? 'selected' : '' ?>>Red</option>
            </select>
            <div class="input-icon">
                <svg class="ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input class="input" type="search" name="search" placeholder="İsim, e-posta, kod..." value="<?= $text($search ?? '') ?>" style="width:200px">
            </div>
            <button class="btn btn--ghost" type="submit">Ara</button>
            <?php if (($search ?? '') !== '' || ($status ?? '') !== ''): ?>
                <a class="btn btn--ghost" href="<?= AdminAuth::url('/affiliates') ?>">Temizle</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="table-scroll">
<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Ortak</th>
            <th>Referans Kodu</th>
            <th>Plan</th>
            <th>Durum</th>
            <th class="cell-price">Bakiye</th>
            <th class="cell-price">Kazanç</th>
            <th>Bekleyen Kom.</th>
            <th>Kayıt</th>
            <th>İşlem</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($affiliates)): ?>
        <tr><td colspan="10" style="text-align:center;padding:40px;color:var(--t-muted)">Henüz ortak bulunmuyor.</td></tr>
        <?php else: ?>
            <?php foreach ($affiliates as $a): ?>
            <tr>
                <td><span class="data-cell-mono">#<?= $a['id'] ?></span></td>
                <td>
                    <div class="data-cell-user">
                        <div class="av"><?= strtoupper(mb_substr(($a['full_name'] ?: $a['email']), 0, 1)) ?></div>
                        <div class="data-cell-user-meta">
                            <div class="data-cell-user-name"><?= $text($a['full_name'] ?: $a['email']) ?></div>
                            <div class="data-cell-user-email"><?= $text($a['email']) ?></div>
                            <?php if ($a['company_name'] !== ''): ?>
                            <div style="font-size:10px;color:var(--t-light)"><?= $text($a['company_name']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td><span class="data-cell-mono"><?= $text($a['referral_code']) ?></span></td>
                <td><span style="font-size:12px;color:var(--t-muted)"><?= $text($a['plan_name'] ?? '-') ?></span></td>
                <td><span class="badge <?= $badgeClass($a['status']) ?>"><?= $statusLabel($a['status']) ?></span></td>
                <td class="cell-price"><?= $money($a['balance']) ?> ₺</td>
                <td class="cell-price"><?= $money($a['total_earned']) ?> ₺</td>
                <td><span class="data-cell-mono"><?= (int) ($a['pending_commissions'] ?? 0) ?></span></td>
                <td class="cell-date"><?= date('d.m.Y', strtotime($a['created_at'])) ?></td>
                <td>
                    <div class="data-cell-actions">
                        <a class="btn btn--sm btn--ghost" href="<?= AdminAuth::url('/affiliate/detail?id=' . $a['id']) ?>">Detay</a>
                        <?php if ($a['status'] === 'pending'): ?>
                        <form method="post" action="<?= AdminAuth::url('/affiliate/quick-action') ?>" style="display:inline">
                            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button class="btn btn--sm btn--outline-success" onclick="return confirm('Onaylıyor musunuz?')">Onayla</button>
                        </form>
                        <form method="post" action="<?= AdminAuth::url('/affiliate/quick-action') ?>" style="display:inline">
                            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <button class="btn btn--sm btn--outline-danger" onclick="return confirm('Reddediyor musunuz?')">Reddet</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</div>

<?php if (($totalPages ?? 0) > 1): ?>
<div class="data-foot">
    <div class="data-foot-info"><?= number_format($total ?? 0) ?> kayıt</div>
    <div class="pager">
        <?php for ($p = max(1, ($page ?? 1) - 3); $p <= min($totalPages, ($page ?? 1) + 3); $p++): ?>
        <a class="pager-item <?= $p === ($page ?? 1) ? 'is-active' : '' ?>"
           href="<?= AdminAuth::url('/affiliates?page=' . $p . (($search ?? '') !== '' ? '&search=' . rawurlencode($search) : '') . (($status ?? '') !== '' ? '&status=' . rawurlencode($status) : '')) ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>
</section>
