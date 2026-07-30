<?php
$topAffiliates = is_array($topAffiliates ?? null) ? $topAffiliates : [];
$summary = $summary ?? [];
$plans = is_array($plans ?? null) ? $plans : [];
$text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$money = static fn (mixed $v): string => number_format((float) $v, 2, ',', '.');
$flash = (string) ($flash ?? '');
?>
<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Ortaklık Sistemi</span>
        <h1 class="hero-title">Ortaklık <span class="accent">raporları</span></h1>
        <p class="hero-sub"><?= date('d.m.Y', strtotime($dateFrom)) ?> - bugün</p>
    </div>
</div>
<?php if ($flash !== ''): ?>
    <div class="alert alert--success"><?= $text($flash) ?></div>
<?php endif; ?>

<!-- Filters -->
<section class="card admin-compact-card" style="margin-bottom:24px">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">Filtre</span>
            <h2 class="card-title">Rapor filtresi</h2>
        </div>
        <form method="get" action="<?= AdminAuth::url('/affiliate/reports') ?>" style="display:flex;gap:8px;align-items:center">
            <select name="period" class="select" style="width:auto">
                <option value="week" <?= $period === 'week' ? 'selected' : '' ?>>Son 7 Gün</option>
                <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>Son 30 Gün</option>
                <option value="quarter" <?= $period === 'quarter' ? 'selected' : '' ?>>Son 3 Ay</option>
                <option value="year" <?= $period === 'year' ? 'selected' : '' ?>>Son 1 Yıl</option>
            </select>
            <select name="plan_id" class="select" style="width:auto">
                <option value="0">Tüm Planlar</option>
                <?php foreach ($plans as $pl): ?>
                    <option value="<?= $pl['id'] ?>" <?= $planId === (int) $pl['id'] ? 'selected' : '' ?>><?= $text($pl['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn--ghost" type="submit">Filtrele</button>
        </form>
    </div>
</section>

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px">
    <div class="user-stat-card">
        <span>Aktif Ortak</span>
        <strong><?= (int) ($summary['total_active'] ?? 0) ?></strong>
    </div>
    <div class="user-stat-card">
        <span>Dönem Komisyonu</span>
        <strong><?= $money($summary['period_commission'] ?? 0) ?> ₺</strong>
    </div>
    <div class="user-stat-card">
        <span>İşlem Sayısı</span>
        <strong><?= (int) ($summary['period_transactions'] ?? 0) ?></strong>
    </div>
</div>

<section class="card admin-compact-card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">Sıralama</span>
            <h2 class="card-title">En çok kazandıran ortaklar</h2>
        </div>
    </div>
    <div class="admin-compact-table-wrap">
        <table class="admin-compact-table">
            <thead>
                <tr><th>#</th><th>Ortak</th><th>Kod</th><th>Plan</th><th>Yönlendirme</th><th>Komisyon</th></tr>
            </thead>
            <tbody>
                <?php if (empty($topAffiliates)): ?>
                    <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--t-light)">Bu dönemde veri yok.</td></tr>
                <?php else: ?>
                    <?php $i = 1; foreach ($topAffiliates as $a): ?>
                    <tr>
                        <td><span class="data-cell-mono">#<?= $i++ ?></span></td>
                        <td>
                            <strong><?= $text($a['full_name'] ?: $a['email']) ?></strong>
                        </td>
                        <td><code><?= $text($a['referral_code']) ?></code></td>
                        <td><small><?= $text($a['plan_name'] ?? '-') ?></small></td>
                        <td><?= (int) $a['referred_count'] ?></td>
                        <td><span class="data-cell-mono"><?= $money($a['total_commission']) ?> ₺</span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</section>
