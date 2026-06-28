<?php

$stats = is_array($stats ?? null) ? $stats : [];
$dailyVisits = is_array($dailyVisits ?? null) ? $dailyVisits : [];
$dailyDeposits = is_array($dailyDeposits ?? null) ? $dailyDeposits : [];
$money = static fn (float $value): string => '₺' . number_format($value, 2, ',', '.');
?>
<section class="admin-surface">
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Raporlar · Grafikler</span>
        <h1 class="hero-title">Operasyon <span class="accent">grafikleri</span></h1>
        <p class="hero-sub">Ziyaret, finans, oyun ve üye metrikleri tema chart kartlarıyla raporlanır.</p>
    </div>
    <div class="hero-actions"><a class="btn btn--primary" href="<?= htmlspecialchars(AdminAuth::url('/dashboard'), ENT_QUOTES, 'UTF-8') ?>">Dashboard</a></div>
</section>

<section class="kpi-grid" aria-label="Rapor metrikleri">
    <?php foreach ([['Üyeler', $stats['users'] ?? 0, 'purple'], ['Yatırım', $money((float) ($stats['deposits'] ?? 0)), 'success'], ['Çekim', $money((float) ($stats['withdrawals'] ?? 0)), 'danger'], ['Aktif oyun', $stats['games'] ?? 0, 'primary']] as $item): ?>
        <article class="kpi-card c-<?= htmlspecialchars((string) $item[2], ENT_QUOTES, 'UTF-8') ?>"><div class="kpi-top"><div class="kpi-identity"><div class="kpi-icon <?= htmlspecialchars((string) $item[2], ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><path d="M12 20V10M18 20V4M6 20v-4"/></svg></div><div class="kpi-label"><?= htmlspecialchars((string) $item[0], ENT_QUOTES, 'UTF-8') ?></div></div><span class="kpi-pill info">live</span></div><div class="kpi-value" style="font-size:30px"><?= htmlspecialchars((string) $item[1], ENT_QUOTES, 'UTF-8') ?></div><div class="kpi-compare"><?= htmlspecialchars((string) ($site['site_name'] ?? 'Site'), ENT_QUOTES, 'UTF-8') ?> veritabanından canlı metrik</div></article>
    <?php endforeach; ?>
</section>

<div class="grid">
    <section class="col-6 card">
        <div class="card-head"><div class="card-title-wrap"><span class="eyebrow">Performans</span><h2 class="card-title">Aylık istatistikler</h2></div><span class="card-action"><?= htmlspecialchars(date('F Y'), ENT_QUOTES, 'UTF-8') ?></span></div>
        <div class="chart-canvas-wrap" style="height:260px"><canvas data-chart-key="dashboard-monthly"></canvas></div>
        <div class="monthly-footer">
            <?php foreach (array_slice($dailyDeposits, -4) as $row): ?>
                <div class="stat-cell"><div class="stat-cell-label"><?= htmlspecialchars((string) ($row['day'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div><div class="stat-cell-value">₺<?= htmlspecialchars(number_format((float) ($row['total'] ?? 0), 0, ',', '.'), ENT_QUOTES, 'UTF-8') ?> <svg class="trend-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M7 17l10-10M7 7h10v10"/></svg></div></div>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="col-6 card">
        <div class="card-head"><div class="card-title-wrap"><span class="eyebrow">Trafik</span><h2 class="card-title">Günlük ziyaretler</h2></div></div>
        <table class="table"><thead><tr><th>Gün</th><th style="text-align:right">Ziyaret</th></tr></thead><tbody>
        <?php foreach ($dailyVisits as $row): ?>
            <tr><td class="cell-name"><?= htmlspecialchars((string) ($row['day'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td><td class="cell-price pos"><?= (int) ($row['total'] ?? 0) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
    </section>
</div>
</section>
