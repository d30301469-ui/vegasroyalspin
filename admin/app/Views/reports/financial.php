<?php

$rows = is_array($rows ?? null) ? $rows : [];
$summary = is_array($summary ?? null) ? $summary : [];
$from = (string) ($from ?? date('Y-m-01'));
$to = (string) ($to ?? date('Y-m-d'));
$groupBy = (string) ($groupBy ?? 'day');

$text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$money = static fn (mixed $v): string => '₺' . number_format((float) $v, 2, ',', '.');
$netColor = (float) ($summary['net_revenue'] ?? 0) >= 0 ? 'var(--success)' : 'var(--danger)';
?>
<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Raporlar</span>
        <h1 class="hero-title">Finansal <span class="accent">rapor</span></h1>
        <p class="hero-sub">Yatırım ve çekim işlemlerinin zaman bazlı özeti.</p>
    </div>
</div>

<form method="get" action="<?= $text(AdminAuth::url('/reports/financial')) ?>" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:18px">
    <div class="field">
        <label class="field-label" for="repFrom">Başlangıç</label>
        <input id="repFrom" class="input" type="date" name="from" value="<?= $text($from) ?>">
    </div>
    <div class="field">
        <label class="field-label" for="repTo">Bitiş</label>
        <input id="repTo" class="input" type="date" name="to" value="<?= $text($to) ?>">
    </div>
    <div class="field">
        <label class="field-label" for="repGroup">Gruplama</label>
        <select id="repGroup" class="select" name="group_by">
            <option value="day"<?= $groupBy === 'day' ? ' selected' : '' ?>>Günlük</option>
            <option value="week"<?= $groupBy === 'week' ? ' selected' : '' ?>>Haftalık</option>
            <option value="month"<?= $groupBy === 'month' ? ' selected' : '' ?>>Aylık</option>
        </select>
    </div>
    <button class="btn btn--primary" type="submit">Filtrele</button>
</form>

<div style="display:grid;grid-template-columns:repeat(3,minmax(200px,1fr));gap:14px;margin-bottom:18px">
    <div class="card admin-compact-card" style="padding:18px">
        <div style="color:var(--t-light);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px">Toplam Yatırım</div>
        <div style="font-size:22px;font-weight:900;color:var(--success)"><?= $text($money($summary['total_deposits'] ?? 0)) ?></div>
    </div>
    <div class="card admin-compact-card" style="padding:18px">
        <div style="color:var(--t-light);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px">Toplam Çekim</div>
        <div style="font-size:22px;font-weight:900;color:var(--danger)"><?= $text($money($summary['total_withdrawals'] ?? 0)) ?></div>
    </div>
    <div class="card admin-compact-card" style="padding:18px">
        <div style="color:var(--t-light);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px">Net Gelir</div>
        <div style="font-size:22px;font-weight:900;color:<?= $netColor ?>"><?= $text($money($summary['net_revenue'] ?? 0)) ?></div>
    </div>
</div>

<section class="card admin-compact-card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow"><?= $text(match ($groupBy) { 'week' => 'Haftalık', 'month' => 'Aylık', default => 'Günlük' }) ?> Döküm</span>
            <h2 class="card-title"><?= $text($from) ?> — <?= $text($to) ?></h2>
        </div>
    </div>
    <div class="admin-compact-table-wrap">
        <table class="admin-compact-table">
            <thead>
                <tr>
                    <th>Dönem</th>
                    <th>Yatırım (₺)</th>
                    <th>Çekim (₺)</th>
                    <th>Net (₺)</th>
                    <th>Yatırım adedi</th>
                    <th>Çekim adedi</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="6">Bu dönemde işlem bulunamadı.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td style="font-weight:700"><?= $text($row['period'] ?? '') ?></td>
                        <td><span class="data-cell-mono" style="color:var(--success)"><?= $text($money($row['deposits'] ?? 0)) ?></span></td>
                        <td><span class="data-cell-mono" style="color:var(--danger)"><?= $text($money($row['withdrawals'] ?? 0)) ?></span></td>
                        <td>
                            <?php $net = (float) ($row['net'] ?? 0); ?>
                            <span class="data-cell-mono" style="color:<?= $net >= 0 ? 'var(--success)' : 'var(--danger)' ?>"><?= $text($money($net)) ?></span>
                        </td>
                        <td><?= $text($row['deposit_count'] ?? 0) ?></td>
                        <td><?= $text($row['withdrawal_count'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</section>
