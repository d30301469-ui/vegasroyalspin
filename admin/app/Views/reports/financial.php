<?php

$rows = is_array($rows ?? null) ? $rows : [];
$summary = is_array($summary ?? null) ? $summary : [];
$chartData = is_array($chartData ?? null) ? $chartData : [];
$from = (string) ($from ?? date('Y-m-01'));
$to = (string) ($to ?? date('Y-m-d'));
$groupBy = (string) ($groupBy ?? 'day');
$groupLabel = match ($groupBy) {
    'week' => 'Haftalık',
    'month' => 'Aylık',
    default => 'Günlük',
};

$text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$money = static fn (mixed $v): string => '₺' . number_format((float) $v, 2, ',', '.');
$netColor = (float) ($summary['net_revenue'] ?? 0) >= 0 ? 'var(--success)' : 'var(--danger)';
?>
<style>
    .fin-kpi-row { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin-bottom:14px; }
    .fin-kpi { background:var(--bg-card); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow-card); padding:16px; }
    .fin-kpi-label { color:var(--t-muted); font-size:11px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; margin-bottom:6px; }
    .fin-kpi-value { font-size:22px; font-weight:900; letter-spacing:-.02em; overflow-wrap:anywhere; }
    .fin-charts { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; margin-bottom:14px; }
    .fin-card { background:var(--bg-card); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow-card); padding:14px; min-width:0; }
    .fin-card.full { grid-column:1 / -1; }
    .fin-card-head { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:10px; }
    .fin-card-title { margin:0; color:var(--t-base); font-size:13px; font-weight:900; }
    .fin-card-meta { color:var(--t-muted); font-size:11px; font-weight:700; }
    .fin-chart-wrap { position:relative; height:280px; }
    .fin-chart-wrap.tall { height:320px; }
    .fin-filter { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; margin-bottom:18px; }
    @media (max-width:1000px) {
        .fin-kpi-row, .fin-charts { grid-template-columns:1fr; }
        .fin-card.full { grid-column:auto; }
    }
</style>

<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Raporlar · Finans</span>
        <h1 class="hero-title">Finansal <span class="accent">rapor</span></h1>
        <p class="hero-sub">Yatırım ve çekim özeti Chart.js grafikleriyle dönem bazında.</p>
    </div>
</div>
<?php if (trim((string) ($queryError ?? '')) !== ''): ?>
    <div class="alert alert--danger" style="margin-bottom:14px"><?= $text($queryError) ?></div>
<?php endif; ?>

<form method="get" action="<?= $text(AdminAuth::url('/reports/financial')) ?>" class="fin-filter">
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

<div class="fin-kpi-row">
    <div class="fin-kpi">
        <div class="fin-kpi-label">Toplam Yatırım</div>
        <div class="fin-kpi-value" style="color:var(--success)"><?= $text($money($summary['total_deposits'] ?? 0)) ?></div>
    </div>
    <div class="fin-kpi">
        <div class="fin-kpi-label">Toplam Çekim</div>
        <div class="fin-kpi-value" style="color:var(--danger)"><?= $text($money($summary['total_withdrawals'] ?? 0)) ?></div>
    </div>
    <div class="fin-kpi">
        <div class="fin-kpi-label">Net Gelir</div>
        <div class="fin-kpi-value" style="color:<?= htmlspecialchars($netColor, ENT_QUOTES, 'UTF-8') ?>"><?= $text($money($summary['net_revenue'] ?? 0)) ?></div>
    </div>
</div>

<div class="fin-charts">
    <div class="fin-card full">
        <div class="fin-card-head">
            <h2 class="fin-card-title">Yatırım / Çekim / Net trend</h2>
            <span class="fin-card-meta"><?= $text($groupLabel) ?> · line</span>
        </div>
        <div class="fin-chart-wrap tall"><canvas id="fin-trend"></canvas></div>
    </div>
    <div class="fin-card">
        <div class="fin-card-head">
            <h2 class="fin-card-title">Yatırım–çekim payı</h2>
            <span class="fin-card-meta">doughnut</span>
        </div>
        <div class="fin-chart-wrap"><canvas id="fin-share"></canvas></div>
    </div>
    <div class="fin-card">
        <div class="fin-card-head">
            <h2 class="fin-card-title">İşlem adetleri</h2>
            <span class="fin-card-meta"><?= $text($groupLabel) ?> · bar</span>
        </div>
        <div class="fin-chart-wrap"><canvas id="fin-counts"></canvas></div>
    </div>
</div>

<section class="card admin-compact-card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow"><?= $text($groupLabel) ?> Döküm</span>
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

<script>
(function(){
    var data = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    var theme = function() {
        return document.documentElement.getAttribute('data-theme') || 'light';
    };
    var labelColor = function() {
        return theme() === 'dark' ? '#94a3b8' : '#64748b';
    };
    var gridColor = function() {
        return theme() === 'dark' ? 'rgba(148,163,184,.12)' : 'rgba(15,23,42,.08)';
    };
    var borderColor = function() {
        return theme() === 'dark' ? '#1e293b' : '#ffffff';
    };
    var legend = function() {
        return {
            display: true,
            position: 'bottom',
            labels: {
                color: labelColor(),
                boxWidth: 8,
                boxHeight: 8,
                padding: 12,
                usePointStyle: true,
                font: { size: 11, weight: '700' }
            }
        };
    };
    var moneyTick = function(value) {
        var n = Number(value || 0);
        if (Math.abs(n) >= 1000000) return '₺' + (n / 1000000).toFixed(1) + 'M';
        if (Math.abs(n) >= 1000) return '₺' + (n / 1000).toFixed(0) + 'K';
        return '₺' + n.toLocaleString('tr-TR', { maximumFractionDigits: 0 });
    };
    var moneyTooltip = function(ctx) {
        var n = Number(ctx.raw || 0);
        return (ctx.dataset.label || '') + ': ₺' + n.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    var destroyIfAny = function(canvas) {
        if (canvas && canvas._chart) {
            canvas._chart.destroy();
            canvas._chart = null;
        }
    };

    var renderTrend = function() {
        var canvas = document.getElementById('fin-trend');
        if (!canvas || typeof Chart !== 'function') return;
        var trend = data.trend || { labels: [], deposits: [], withdrawals: [], net: [] };
        var labels = trend.labels || [];
        destroyIfAny(canvas);
        canvas._chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels.length ? labels : ['Veri yok'],
                datasets: [
                    {
                        label: 'Yatırım',
                        data: labels.length ? (trend.deposits || []) : [0],
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34,197,94,.12)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        borderWidth: 2
                    },
                    {
                        label: 'Çekim',
                        data: labels.length ? (trend.withdrawals || []) : [0],
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239,68,68,.10)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        borderWidth: 2
                    },
                    {
                        label: 'Net',
                        data: labels.length ? (trend.net || []) : [0],
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59,130,246,.08)',
                        fill: false,
                        tension: 0.35,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        borderWidth: 2,
                        borderDash: [5, 4]
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                animation: { duration: 650, easing: 'easeOutQuart' },
                plugins: {
                    legend: legend(),
                    tooltip: { callbacks: { label: moneyTooltip } }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: labelColor(), font: { size: 10, weight: '700' }, maxRotation: 0, autoSkip: true }
                    },
                    y: {
                        grid: { color: gridColor(), drawBorder: false },
                        ticks: {
                            color: labelColor(),
                            font: { size: 10, weight: '700' },
                            callback: moneyTick
                        }
                    }
                }
            }
        });
    };

    var renderShare = function() {
        var canvas = document.getElementById('fin-share');
        if (!canvas || typeof Chart !== 'function') return;
        var pack = data.share || { labels: [], data: [], colors: [] };
        var values = (pack.data || []).map(function(v){ return Math.max(0, Number(v || 0)); });
        var labels = (pack.labels || []).slice();
        var colors = (pack.colors || []).slice();
        var sum = values.reduce(function(a, b){ return a + b; }, 0);
        if (sum <= 0) {
            values = [1];
            labels = ['Veri yok'];
            colors = ['rgba(148,163,184,.35)'];
        }
        destroyIfAny(canvas);
        canvas._chart = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderColor: borderColor(),
                    borderWidth: 2,
                    hoverOffset: sum <= 0 ? 0 : 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                animation: { duration: 650, easing: 'easeOutQuart' },
                plugins: {
                    legend: legend(),
                    tooltip: {
                        enabled: sum > 0,
                        callbacks: {
                            label: function(ctx) {
                                var n = Number(ctx.raw || 0);
                                var pct = sum > 0 ? ((n / sum) * 100).toFixed(1) : '0';
                                return (ctx.label || '') + ': ₺' + n.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    };

    var renderCounts = function() {
        var canvas = document.getElementById('fin-counts');
        if (!canvas || typeof Chart !== 'function') return;
        var pack = data.counts || { labels: [], deposits: [], withdrawals: [] };
        var labels = pack.labels || [];
        destroyIfAny(canvas);
        canvas._chart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels.length ? labels : ['Veri yok'],
                datasets: [
                    {
                        label: 'Yatırım adedi',
                        data: labels.length ? (pack.deposits || []) : [0],
                        backgroundColor: 'rgba(34,197,94,.88)',
                        borderRadius: 8,
                        borderSkipped: false,
                        maxBarThickness: 22
                    },
                    {
                        label: 'Çekim adedi',
                        data: labels.length ? (pack.withdrawals || []) : [0],
                        backgroundColor: 'rgba(239,68,68,.88)',
                        borderRadius: 8,
                        borderSkipped: false,
                        maxBarThickness: 22
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 650, easing: 'easeOutQuart' },
                plugins: { legend: legend() },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: labelColor(), font: { size: 10, weight: '700' }, maxRotation: 0, autoSkip: true }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor(), drawBorder: false },
                        ticks: { color: labelColor(), font: { size: 10, weight: '700' }, precision: 0 }
                    }
                }
            }
        });
    };

    var init = function() {
        renderTrend();
        renderShare();
        renderCounts();
    };

    var boot = function(attempt) {
        if (typeof Chart === 'undefined') {
            if (attempt >= 50) return;
            setTimeout(function(){ boot(attempt + 1); }, 100);
            return;
        }
        init();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function(){ boot(0); });
    } else {
        boot(0);
    }
})();
</script>
