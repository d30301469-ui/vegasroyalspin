<?php

$stats = is_array($stats ?? null) ? $stats : [];
$chartData = is_array($chartData ?? null) ? $chartData : [];
$text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$money = static fn (mixed $value): string => '₺' . number_format((float) $value, 2, ',', '.');
$number = static fn (mixed $value): string => number_format((float) $value, 0, ',', '.');
?>
<style>
    .op-kpi-row { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:12px; margin-bottom:14px; }
    .op-kpi { background:var(--bg-card); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow-card); padding:14px; text-align:center; min-width:0; }
    .op-kpi-value { color:var(--t-base); font-size:22px; font-weight:900; letter-spacing:-.03em; overflow-wrap:anywhere; }
    .op-kpi-label { color:var(--t-muted); font-size:10px; font-weight:800; margin-top:4px; text-transform:uppercase; letter-spacing:.05em; }
    .op-charts { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; margin-bottom:14px; }
    .op-card { background:var(--bg-card); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow-card); padding:14px; min-width:0; }
    .op-card.full { grid-column:1 / -1; }
    .op-card-head { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:10px; }
    .op-card-title { margin:0; color:var(--t-base); font-size:13px; font-weight:900; }
    .op-card-meta { color:var(--t-muted); font-size:11px; font-weight:700; }
    .op-chart-wrap { position:relative; height:280px; }
    .op-chart-wrap.tall { height:320px; }
    @media (max-width:1200px) {
        .op-kpi-row { grid-template-columns:repeat(3,minmax(0,1fr)); }
    }
    @media (max-width:1000px) {
        .op-charts, .op-kpi-row { grid-template-columns:1fr; }
        .op-card.full { grid-column:auto; }
    }
</style>

<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Raporlar · Grafikler</span>
        <h1 class="hero-title">Operasyon <span class="accent">grafikleri</span></h1>
        <p class="hero-sub">Ziyaret, finans, üye ve oyun metrikleri Chart.js ile raporlanır.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/reports/financial')) ?>">Finansal rapor</a>
        <a class="btn btn--primary" href="<?= $text(AdminAuth::url('/dashboard')) ?>">Dashboard</a>
    </div>
</div>
<?php if (trim((string) ($queryError ?? '')) !== ''): ?>
    <div class="alert alert--danger" style="margin-bottom:14px"><?= $text($queryError) ?></div>
<?php endif; ?>

<div class="op-kpi-row">
    <div class="op-kpi">
        <div class="op-kpi-value"><?= $text($number($stats['users'] ?? 0)) ?></div>
        <div class="op-kpi-label">Üyeler</div>
    </div>
    <div class="op-kpi">
        <div class="op-kpi-value" style="color:var(--success)"><?= $text($money($stats['deposits'] ?? 0)) ?></div>
        <div class="op-kpi-label">Yatırım</div>
    </div>
    <div class="op-kpi">
        <div class="op-kpi-value" style="color:var(--danger)"><?= $text($money($stats['withdrawals'] ?? 0)) ?></div>
        <div class="op-kpi-label">Çekim</div>
    </div>
    <div class="op-kpi">
        <div class="op-kpi-value"><?= $text($number($stats['games'] ?? 0)) ?></div>
        <div class="op-kpi-label">Aktif oyun</div>
    </div>
    <div class="op-kpi">
        <div class="op-kpi-value"><?= $text($number($stats['visits'] ?? 0)) ?></div>
        <div class="op-kpi-label">Ziyaret</div>
    </div>
</div>

<div class="op-charts">
    <div class="op-card full">
        <div class="op-card-head">
            <h2 class="op-card-title">14 günlük finans trendi</h2>
            <span class="op-card-meta">yatırım · çekim · net · line</span>
        </div>
        <div class="op-chart-wrap tall"><canvas id="op-finance"></canvas></div>
    </div>

    <div class="op-card">
        <div class="op-card-head">
            <h2 class="op-card-title">Günlük ziyaretler</h2>
            <span class="op-card-meta">14 gün · line</span>
        </div>
        <div class="op-chart-wrap"><canvas id="op-visits"></canvas></div>
    </div>

    <div class="op-card">
        <div class="op-card-head">
            <h2 class="op-card-title">Yeni üye kayıtları</h2>
            <span class="op-card-meta">14 gün · bar</span>
        </div>
        <div class="op-chart-wrap"><canvas id="op-users"></canvas></div>
    </div>

    <div class="op-card">
        <div class="op-card-head">
            <h2 class="op-card-title">Yatırım–çekim payı</h2>
            <span class="op-card-meta">doughnut</span>
        </div>
        <div class="op-chart-wrap"><canvas id="op-share"></canvas></div>
    </div>

    <div class="op-card">
        <div class="op-card-head">
            <h2 class="op-card-title">Oyun kataloğu</h2>
            <span class="op-card-meta">sağlayıcı · doughnut</span>
        </div>
        <div class="op-chart-wrap"><canvas id="op-games"></canvas></div>
    </div>

    <div class="op-card full">
        <div class="op-card-head">
            <h2 class="op-card-title">Genel hacim özeti</h2>
            <span class="op-card-meta">üye · ziyaret · oyun · bar</span>
        </div>
        <div class="op-chart-wrap"><canvas id="op-overview"></canvas></div>
    </div>
</div>
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

    var renderFinance = function() {
        var canvas = document.getElementById('op-finance');
        if (!canvas || typeof Chart !== 'function') return;
        var pack = data.finance || { labels: [], deposits: [], withdrawals: [], net: [] };
        var labels = pack.labels || [];
        destroyIfAny(canvas);
        canvas._chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels.length ? labels : ['Veri yok'],
                datasets: [
                    {
                        label: 'Yatırım',
                        data: labels.length ? (pack.deposits || []) : [0],
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
                        data: labels.length ? (pack.withdrawals || []) : [0],
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
                        data: labels.length ? (pack.net || []) : [0],
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
                        ticks: { color: labelColor(), font: { size: 10, weight: '700' }, callback: moneyTick }
                    }
                }
            }
        });
    };

    var renderVisits = function() {
        var canvas = document.getElementById('op-visits');
        if (!canvas || typeof Chart !== 'function') return;
        var pack = data.visits || { labels: [], data: [] };
        var labels = pack.labels || [];
        destroyIfAny(canvas);
        canvas._chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels.length ? labels : ['Veri yok'],
                datasets: [{
                    label: 'Ziyaret',
                    data: labels.length ? (pack.data || []) : [0],
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14,165,233,.16)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                animation: { duration: 650, easing: 'easeOutQuart' },
                plugins: { legend: { display: false } },
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

    var renderUsers = function() {
        var canvas = document.getElementById('op-users');
        if (!canvas || typeof Chart !== 'function') return;
        var pack = data.users || { labels: [], data: [] };
        var labels = pack.labels || [];
        destroyIfAny(canvas);
        canvas._chart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels.length ? labels : ['Veri yok'],
                datasets: [{
                    label: 'Yeni üye',
                    data: labels.length ? (pack.data || []) : [0],
                    backgroundColor: 'rgba(99,102,241,.88)',
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 24
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 650, easing: 'easeOutQuart' },
                plugins: { legend: { display: false } },
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

    var renderDonut = function(elId, pack) {
        var canvas = document.getElementById(elId);
        if (!canvas || typeof Chart !== 'function') return;
        pack = pack || { labels: [], data: [], colors: [] };
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
                    tooltip: { enabled: sum > 0 }
                }
            }
        });
    };

    var renderOverview = function() {
        var canvas = document.getElementById('op-overview');
        if (!canvas || typeof Chart !== 'function') return;
        var pack = data.overview || { labels: [], data: [], colors: [] };
        var labels = pack.labels || [];
        destroyIfAny(canvas);
        canvas._chart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels.length ? labels : ['Veri yok'],
                datasets: [{
                    label: 'Adet',
                    data: labels.length ? (pack.data || []) : [0],
                    backgroundColor: pack.colors || ['rgba(59,130,246,.88)'],
                    borderRadius: 10,
                    borderSkipped: false,
                    maxBarThickness: 48
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 650, easing: 'easeOutQuart' },
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: labelColor(), font: { size: 11, weight: '700' } }
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
        renderFinance();
        renderVisits();
        renderUsers();
        renderDonut('op-share', data.share);
        renderDonut('op-games', data.games);
        renderOverview();
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
