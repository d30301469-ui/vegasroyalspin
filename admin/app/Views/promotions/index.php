<?php

$promotions = is_array($promotions ?? null) ? $promotions : [];
$total = (int) ($total ?? 0);
$page = (int) ($page ?? 1);
$perPage = (int) ($perPage ?? 25);
$totalPages = (int) ($totalPages ?? 1);
$search = (string) ($search ?? '');
$flash = trim((string) ($flash ?? ''));

$text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$money = static fn (mixed $v): string => '₺' . number_format((float) $v, 2, ',', '.');
$badgeClass = static function (string $v): string {
    return match (strtolower($v)) {
        'active' => 'success dot',
        'inactive', 'draft' => 'warning dot',
        default => 'primary',
    };
};
?>
<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Marketing</span>
        <h1 class="hero-title">Promosyonlar <span class="accent">yönetimi</span></h1>
        <p class="hero-sub">Aktif promosyonları yönetin, yeni promosyon ekleyin, kullanıcılara bonus atayın.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--primary" href="<?= $text(AdminAuth::url('/promotion/create')) ?>">Promosyon ekle</a>
    </div>
</div>

<?php if ($flash !== ''): ?>
    <div class="alert alert--<?= str_contains($flash, 'başarısız') || str_contains($flash, 'Geçersiz') ? 'danger' : 'success' ?>"><?= $text($flash) ?></div>
<?php endif; ?>

<section class="card admin-compact-card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">Liste</span>
            <h2 class="card-title">Tüm promosyonlar <span class="badge primary"><?= $total ?></span></h2>
        </div>
        <form method="get" action="<?= $text(AdminAuth::url('/promotions')) ?>" style="display:flex;gap:8px;align-items:center">
            <input class="input" type="search" name="search" value="<?= $text($search) ?>" placeholder="Başlık, tip veya kategori..." style="min-width:240px">
            <button class="btn btn--ghost" type="submit">Ara</button>
            <?php if ($search !== ''): ?><a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/promotions')) ?>">Temizle</a><?php endif; ?>
        </form>
    </div>
    <div class="admin-compact-table-wrap">
        <table class="admin-compact-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Başlık</th>
                    <th>Tip</th>
                    <th>Kategori</th>
                    <th>Durum</th>
                    <th>Bonus</th>
                    <th>Çevrim</th>
                    <th>Sıra</th>
                    <th style="width:18%">İşlemler</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($promotions === []): ?>
                <tr><td colspan="9">Promosyon bulunamadı.</td></tr>
            <?php else: ?>
                <?php foreach ($promotions as $promo): ?>
                    <tr>
                        <td><?= $text($promo['id'] ?? '') ?></td>
                        <td>
                            <?php $img = trim((string) ($promo['image_url'] ?? '')); ?>
                            <div style="display:flex;align-items:center;gap:8px">
                                <?php if ($img !== ''): ?><img src="<?= $text($img) ?>" alt="" style="width:32px;height:32px;border-radius:6px;object-fit:cover;border:1px solid var(--border)"><?php endif; ?>
                                <span style="font-weight:700"><?= $text($promo['title'] ?? '') ?></span>
                            </div>
                        </td>
                        <td><?= $text($promo['type'] ?? '-') ?></td>
                        <td><?= $text($promo['category'] ?? '-') ?></td>
                        <td><span class="badge <?= $text($badgeClass((string) ($promo['status'] ?? ''))) ?>"><?= $text($promo['status'] ?? '') ?></span></td>
                        <td><span class="data-cell-mono"><?= $text($money($promo['bonus_amount'] ?? 0)) ?></span></td>
                        <td><?= $text($promo['wagering_multiplier'] ?? 0) ?>x</td>
                        <td><?= $text($promo['sort_order'] ?? 0) ?></td>
                        <td>
                            <?php $editUrl = AdminAuth::url('/promotion/edit?id=' . rawurlencode((string) ($promo['id'] ?? ''))); ?>
                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                <a
                                    class="btn btn--ghost"
                                    style="font-size:11px;padding:4px 8px"
                                    href="<?= $text($editUrl) ?>"
                                    data-admin-modal-url="<?= $text($editUrl) ?>"
                                    data-admin-modal-title="<?= $text(((string) ($promo['title'] ?? 'Promosyon')) . ' düzenle') ?>"
                                >Düzenle</a>
                                <a class="btn btn--ghost" style="font-size:11px;padding:4px 8px" href="<?= $text(AdminAuth::url('/promotion/claims?id=' . rawurlencode((string) ($promo['id'] ?? '')))) ?>">Talepler</a>
                                <form method="post" action="<?= $text(AdminAuth::url('/promotion/delete')) ?>" onsubmit="return confirm('Promosyonu silmek istediğinizden emin misiniz?')" style="display:inline">
                                    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= $text($promo['id'] ?? '') ?>">
                                    <button class="btn btn--ghost" style="font-size:11px;padding:4px 8px;color:var(--danger)" type="submit">Sil</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;font-size:12px;color:var(--t-light)">
        <span><?= number_format($total) ?> kayıt</span>
        <div style="display:flex;gap:4px">
            <?php for ($p = max(1, $page - 3); $p <= min($totalPages, $page + 3); $p++): ?>
                <a href="<?= $text(AdminAuth::url('/promotions?page=' . $p . ($search !== '' ? '&search=' . rawurlencode($search) : ''))) ?>"
                   style="padding:4px 8px;border-radius:6px;<?= $p === $page ? 'background:var(--accent);color:#fff;font-weight:700' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</section>
