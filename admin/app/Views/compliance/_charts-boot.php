<?php
/**
 * Shared Chart.js boot helpers for compliance pages.
 * Expects $chartData (array) and optional $chartPrefix (string).
 */
$chartData = is_array($chartData ?? null) ? $chartData : [];
$chartPrefix = preg_replace('/[^a-z0-9_-]/i', '', (string) ($chartPrefix ?? 'cmp')) ?: 'cmp';
?>
<style>
    .cmp-charts { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; margin-bottom:14px; }
    .cmp-chart-card {
        background:var(--bg-card); border:1px solid var(--border); border-radius:14px;
        box-shadow:var(--shadow-card); padding:14px 14px 10px; min-width:0;
    }
    .cmp-chart-card.wide { grid-column: span 2; }
    .cmp-chart-card.full { grid-column: 1 / -1; }
    .cmp-chart-head {
        display:flex; align-items:center; justify-content:space-between; gap:8px;
        margin-bottom:8px;
    }
    .cmp-chart-title { margin:0; font-size:12px; font-weight:900; color:var(--t-base); letter-spacing:.02em; }
    .cmp-chart-wrap { position:relative; height:220px; }
    .cmp-chart-wrap.tall { height:260px; }
    @media (max-width:1200px) {
        .cmp-charts { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .cmp-chart-card.wide, .cmp-chart-card.full { grid-column: span 2; }
    }
    @media (max-width:720px) {
        .cmp-charts { grid-template-columns:1fr; }
        .cmp-chart-card.wide, .cmp-chart-card.full { grid-column: span 1; }
    }
</style>
<script>
(function(){
    var prefix = <?= json_encode($chartPrefix, JSON_UNESCAPED_UNICODE) ?>;
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

    var baseLegend = function() {
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

    var destroyIfAny = function(canvas) {
        if (canvas && canvas._chart) {
            canvas._chart.destroy();
            canvas._chart = null;
        }
    };

    var doughnut = function(elId, pack) {
        var canvas = document.getElementById(elId);
        if (!canvas || typeof Chart !== 'function') return;
        var values = (pack && pack.data ? pack.data : []).map(function(v){ return Math.max(0, Number(v || 0)); });
        var labels = pack && pack.labels ? pack.labels.slice() : [];
        var colors = pack && pack.colors ? pack.colors.slice() : [];
        var sum = values.reduce(function(a,b){ return a+b; }, 0);
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
                cutout: '64%',
                animation: { duration: 650, easing: 'easeOutQuart' },
                plugins: {
                    legend: baseLegend(),
                    tooltip: { enabled: sum > 0 }
                }
            }
        });
    };

    var bar = function(elId, pack, opts) {
        var canvas = document.getElementById(elId);
        if (!canvas || typeof Chart !== 'function') return;
        opts = opts || {};
        var values = (pack && pack.data ? pack.data : []).map(function(v){ return Math.max(0, Number(v || 0)); });
        var labels = pack && pack.labels ? pack.labels.slice() : [];
        if (!labels.length) {
            labels = ['Veri yok'];
            values = [0];
        }
        destroyIfAny(canvas);
        canvas._chart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: opts.label || 'Adet',
                    data: values,
                    backgroundColor: opts.color || 'rgba(59,130,246,.85)',
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 28
                }]
            },
            options: {
                indexAxis: opts.horizontal ? 'y' : 'x',
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 650, easing: 'easeOutQuart' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                var n = Number(ctx.raw || 0);
                                if (opts.money) {
                                    return '₺' + n.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                }
                                return n.toLocaleString('tr-TR');
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: gridColor(), drawBorder: false },
                        ticks: { color: labelColor(), font: { size: 10, weight: '700' }, maxRotation: 0, autoSkip: true }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor(), drawBorder: false },
                        ticks: { color: labelColor(), font: { size: 10, weight: '700' } }
                    }
                }
            }
        });
    };

    var line = function(elId, trend) {
        var canvas = document.getElementById(elId);
        if (!canvas || typeof Chart !== 'function') return;
        trend = trend || { labels: [], created: [], resolved: [] };
        destroyIfAny(canvas);
        canvas._chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: trend.labels || [],
                datasets: [
                    {
                        label: 'Oluşturulan',
                        data: trend.created || [],
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239,68,68,.15)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        borderWidth: 2
                    },
                    {
                        label: 'Kapanan',
                        data: trend.resolved || [],
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34,197,94,.12)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                animation: { duration: 650, easing: 'easeOutQuart' },
                plugins: { legend: baseLegend() },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: labelColor(), font: { size: 10, weight: '700' } }
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
        if (prefix === 'aml' || prefix === 'risk') {
            doughnut(prefix + '-severity', data.severity);
            doughnut(prefix + '-status', data.status);
            bar(prefix + '-rules', data.rules, { label: 'Açık uyarı', color: 'rgba(168,85,247,.85)', horizontal: true });
            line(prefix + '-trend', data.trend);
            return;
        }
        if (prefix === 'analysis') {
            doughnut('analysis-signals', data.signals);
            doughnut('analysis-levels', data.risk_levels);
            bar('analysis-withdraw', data.top_withdraw, { label: 'Bekleyen çekim', color: 'rgba(239,68,68,.85)', horizontal: true, money: true });
            bar('analysis-deposit', data.top_deposit, { label: 'Yatırım', color: 'rgba(56,189,248,.9)', horizontal: true, money: true });
            line('analysis-aml-trend', data.aml_trend);
            line('analysis-risk-trend', data.risk_trend);
        }
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
