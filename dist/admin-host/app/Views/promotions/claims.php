<?php

$promotion = is_array($promotion ?? null) ? $promotion : [];
$claims = is_array($claims ?? null) ? $claims : [];
$total = (int) ($total ?? 0);
$page = (int) ($page ?? 1);
$perPage = (int) ($perPage ?? 25);
$totalPages = (int) ($totalPages ?? 1);
$statusFilter = (string) ($statusFilter ?? '');
$flash = trim((string) ($flash ?? ''));

$text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$money = static fn (mixed $v): string => '₺' . number_format((float) $v, 2, ',', '.');
$badgeClass = static function (string $v): string {
    return match (strtolower($v)) {
        'active' => 'success dot',
        'completed' => 'primary',
        'revoked', 'expired' => 'danger dot',
        default => 'warning dot',
    };
};
$promoId = (string) ($promotion['id'] ?? '');
?>
<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Promotions · Talepler</span>
        <h1 class="hero-title"><?= $text($promotion['title'] ?? '') ?> <span class="accent">talepler</span></h1>
        <p class="hero-sub">Bu promosyonu kullanan üyelerin listesi.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/promotions')) ?>">Geri dön</a>
    </div>
</div>

<?php if ($flash !== ''): ?>
    <div class="alert alert--<?= str_contains($flash, 'başarısız') ? 'danger' : 'success' ?>"><?= $text($flash) ?></div>
<?php endif; ?>

<section class="card admin-compact-card">
    <div class="card-head">
        <div class="card-title-wrap">
            <h2 class="card-title">Talepler <span class="badge primary"><?= $total ?></span></h2>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
            <?php foreach (['', 'active', 'completed', 'revoked', 'expired'] as $s): ?>
                <a href="<?= $text(AdminAuth::url('/promotion/claims?id=' . rawurlencode($promoId) . ($s !== '' ? '&status=' . rawurlencode($s) : ''))) ?>"
                   class="btn btn--ghost" style="font-size:11px<?= ($statusFilter === $s ? ';font-weight:900;text-decoration:underline' : '') ?>">
                    <?= $s === '' ? 'Tümü' : ucfirst($text($s)) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="admin-compact-table-wrap">
        <table class="admin-compact-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Kullanıcı</th>
                    <th>Bonus adı</th>
                    <th>Durum</th>
                    <th>Başlangıç</th>
                    <th>Mevcut</th>
                    <th>Çevrim</th>
                    <th>Deadline</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($claims === []): ?>
                <tr><td colspan="9">Talep bulunamadı.</td></tr>
            <?php else: ?>
                <?php foreach ($claims as $claim): ?>
                    <tr>
                        <td><?= $text($claim['id'] ?? '') ?></td>
                        <td>
                            <a href="<?= $text(AdminAuth::url('/user?id=' . rawurlencode((string) ($claim['user_id'] ?? '')))) ?>" style="color:var(--accent);font-weight:700">
                                <?= $text($claim['username'] ?? $claim['user_id'] ?? '-') ?>
                            </a>
                        </td>
                        <td><?= $text($claim['name'] ?? '-') ?></td>
                        <td><span class="badge <?= $text($badgeClass((string) ($claim['status'] ?? ''))) ?>"><?= $text($claim['status'] ?? '') ?></span></td>
                        <td><span class="data-cell-mono"><?= $text($money($claim['initial_amount'] ?? 0)) ?></span></td>
                        <td><span class="data-cell-mono"><?= $text($money($claim['current_bonus_balance'] ?? 0)) ?></span></td>
                        <td><?= $text(number_format((float) ($claim['total_bet_amount'] ?? 0), 2)) ?> / <?= $text(number_format((float) ($claim['wagering_requirement'] ?? 0), 2)) ?>x</td>
                        <td><?= $text($claim['deadline'] ?? '-') ?></td>
                        <td>
                            <?php if ((string) ($claim['status'] ?? '') === 'active'): ?>
                            <form method="post" action="<?= $text(AdminAuth::url('/bonus/revoke')) ?>" onsubmit="return confirm('Bonusu iptal etmek istiyor musunuz?')" style="display:inline">
                                <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                                <input type="hidden" name="bonus_id" value="<?= $text($claim['id'] ?? '') ?>">
                                <button class="btn btn--ghost" style="font-size:11px;color:var(--danger)" type="submit">İptal et</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;font-size:12px;color:var(--t-light)">
        <span><?= number_format($total) ?> talep</span>
        <div style="display:flex;gap:4px">
            <?php for ($p = max(1, $page - 3); $p <= min($totalPages, $page + 3); $p++): ?>
                <a href="<?= $text(AdminAuth::url('/promotion/claims?id=' . rawurlencode($promoId) . '&page=' . $p . ($statusFilter !== '' ? '&status=' . rawurlencode($statusFilter) : ''))) ?>"
                   style="padding:4px 8px;border-radius:6px;<?= $p === $page ? 'background:var(--accent);color:#fff;font-weight:700' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</section>
</section>
