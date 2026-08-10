<?php

$countryData = is_array($countryData ?? null) ? $countryData : [];
$recentVisitors = is_array($recentVisitors ?? null) ? $recentVisitors : [];
$mapPoints = is_array($mapPoints ?? null) ? $mapPoints : [];
$chartData = is_array($chartData ?? null) ? $chartData : [];
$totalVisitors = (int) ($totalVisitors ?? 0);
$uniqueCountries = (int) ($uniqueCountries ?? 0);
$queryError = trim((string) ($queryError ?? ''));
$number = static fn ($value): string => number_format((float) $value, 0, ',', '.');
$text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<style>
    .geomap-kpi-row { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin-bottom:14px; }
    .geomap-kpi { background:var(--bg-card); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow-card); padding:14px; text-align:center; }
    .geomap-kpi-value { color:var(--t-base); font-size:24px; font-weight:900; letter-spacing:-.03em; }
    .geomap-kpi-label { color:var(--t-muted); font-size:10px; font-weight:800; margin-top:4px; text-transform:uppercase; }
    .geomap-charts { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; margin-bottom:14px; }
    .geomap-card { background:var(--bg-card); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow-card); padding:14px; min-width:0; }
    .geomap-card.full { grid-column:1 / -1; }
    .geomap-card-head { align-items:center; display:flex; justify-content:space-between; gap:8px; margin-bottom:10px; }
    .geomap-card-title { color:var(--t-base); font-size:13px; font-weight:900; margin:0; }
    .geomap-chart-wrap { position:relative; height:280px; }
    .geomap-chart-wrap.tall { height:340px; }
    .geomap-map { height:440px; border-radius:12px; overflow:hidden; border:1px solid var(--border); }
    .geomap-list { display:flex; flex-direction:column; gap:6px; max-height:340px; overflow-y:auto; }
    .geomap-list-item { background:var(--bg-muted); border-radius:8px; padding:10px 12px; }
    .geomap-list-loc { color:var(--t-base); font-size:12px; font-weight:800; }
    .geomap-list-meta { color:var(--t-muted); font-size:10px; margin-top:3px; }
    .geomap-empty { color:var(--t-muted); font-size:13px; padding:32px 20px; text-align:center; }
    .geomap-alert { background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.35); color:#b91c1c; border-radius:12px; padding:12px 14px; margin-bottom:14px; font-size:13px; font-weight:700; }
    @media (max-width:1000px) {
        .geomap-charts, .geomap-kpi-row { grid-template-columns:1fr; }
        .geomap-card.full { grid-column:auto; }
        .geomap-map { height:320px; }
    }
</style>

<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Raporlar · Coğrafi</span>
        <h1 class="hero-title">Oyuncu <span class="accent">Haritası</span></h1>
        <p class="hero-sub">Visitor GeoIP konumları canlı haritada; ülke/şehir dağılımı Chart.js ile desteklenir.</p>
    </div>
</div>

<?php if ($queryError !== ''): ?>
    <div class="geomap-alert"><?= $text($queryError) ?></div>
<?php endif; ?>

<div class="geomap-kpi-row">
    <div class="geomap-kpi">
        <div class="geomap-kpi-value"><?= $text($number($totalVisitors)) ?></div>
        <div class="geomap-kpi-label">Toplam Ziyaret</div>
    </div>
    <div class="geomap-kpi">
        <div class="geomap-kpi-value"><?= $text($number($uniqueCountries)) ?></div>
        <div class="geomap-kpi-label">Ülke</div>
    </div>
    <div class="geomap-kpi">
        <div class="geomap-kpi-value"><?= $text($number(count($mapPoints))) ?></div>
        <div class="geomap-kpi-label">Harita Noktası</div>
    </div>
</div>

<div class="geomap-charts">
    <div class="geomap-card full">
        <div class="geomap-card-head">
            <h2 class="geomap-card-title">Canlı harita</h2>
            <span style="color:var(--t-muted);font-size:11px">Leaflet · OpenStreetMap</span>
        </div>
        <?php if ($mapPoints === []): ?>
            <div class="geomap-empty">Haritada gösterilecek lat/lon kaydı yok. GeoIP toplandıkça noktalar burada görünür.</div>
        <?php else: ?>
            <div id="geo-leaflet-map" class="geomap-map" role="img" aria-label="Ziyaretçi haritası"></div>
        <?php endif; ?>
    </div>
    <div class="geomap-card">
        <div class="geomap-card-head">
            <h2 class="geomap-card-title">Ülke payı</h2>
            <span style="color:var(--t-muted);font-size:11px">doughnut</span>
        </div>
        <div class="geomap-chart-wrap"><canvas id="geo-donut"></canvas></div>
    </div>
    <div class="geomap-card">
        <div class="geomap-card-head">
            <h2 class="geomap-card-title">14 günlük ziyaret trendi</h2>
            <span style="color:var(--t-muted);font-size:11px">line</span>
        </div>
        <div class="geomap-chart-wrap"><canvas id="geo-trend"></canvas></div>
    </div>
    <div class="geomap-card full">
        <div class="geomap-card-head">
            <h2 class="geomap-card-title">Ülkelere göre ziyaret</h2>
            <span style="color:var(--t-muted);font-size:11px">top 20 · bar</span>
        </div>
        <div class="geomap-chart-wrap tall"><canvas id="geo-countries"></canvas></div>
    </div>
    <div class="geomap-card">
        <div class="geomap-card-head">
            <h2 class="geomap-card-title">Şehir / bölge</h2>
            <span style="color:var(--t-muted);font-size:11px">top 15 · bar</span>
        </div>
        <div class="geomap-chart-wrap tall"><canvas id="geo-cities"></canvas></div>
    </div>
    <div class="geomap-card">
        <div class="geomap-card-head">
            <h2 class="geomap-card-title">Son ziyaretçiler</h2>
            <span style="color:var(--t-muted);font-size:11px">son 100</span>
        </div>
        <?php if ($recentVisitors === []): ?>
            <div class="geomap-empty">Henüz ziyaretçi kaydı yok.</div>
        <?php else: ?>
            <div class="geomap-list">
                <?php foreach ($recentVisitors as $v): ?>
                    <div class="geomap-list-item">
                        <div class="geomap-list-loc">
                            <?= $text(trim((string) ($v['city'] ?? '') . (($v['city'] ?? '') && ($v['region'] ?? '') ? ', ' : '') . (string) ($v['region'] ?? '')) ?: (string) ($v['country_name'] ?? 'Bilinmiyor')) ?>
                        </div>
                        <div class="geomap-list-meta">
                            <?= $text($v['country_name'] ?? '') ?> · <?= $text($v['ip_address'] ?? '-') ?> · <?= $text(date('d.m H:i', strtotime((string) ($v['created_at'] ?? 'now')))) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</section>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function(){
    var data = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var points = <?= json_encode($mapPoints, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

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

    var destroyIfAny = function(canvas) {
        if (canvas && canvas._chart) {
            canvas._chart.destroy();
            canvas._chart = null;
        }
    };

    var emptyPack = function(pack) {
        var values = (pack && pack.data ? pack.data : []).map(function(v){ return Math.max(0, Number(v || 0)); });
        var sum = values.reduce(function(a,b){ return a+b; }, 0);
        if (sum > 0 && (pack.labels || []).length) return pack;
        return {
            labels: ['Veri yok'],
            data: [1],
            colors: ['rgba(148,163,184,.35)'],
            empty: true
        };
    };

    var renderMap = function() {
        var el = document.getElementById('geo-leaflet-map');
        if (!el || typeof L === 'undefined' || !Array.isArray(points) || !points.length) return;
        var map = L.map(el, { scrollWheelZoom: false }).setView([39.0, 35.0], 4);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);
        var bounds = [];
        points.forEach(function(p) {
            var lat = Number(p.lat || 0);
            var lon = Number(p.lon || 0);
            if (!isFinite(lat) || !isFinite(lon)) return;
            var isCountry = p.type === 'country';
            var marker = L.circleMarker([lat, lon], {
                radius: isCountry ? Math.min(18, 6 + Math.log10(Math.max(1, Number(p.visitors || 1))) * 6) : 5,
                color: isCountry ? '#2563eb' : '#0ea5e9',
                weight: 1,
                fillColor: isCountry ? '#3b82f6' : '#38bdf8',
                fillOpacity: 0.72
            }).addTo(map);
            var html = '<strong>' + String(p.label || 'Nokta') + '</strong>';
            if (isCountry) html += '<br>' + Number(p.visitors || 0) + ' ziyaret';
            if (p.ip) html += '<br>' + String(p.ip);
            if (p.at) html += '<br>' + String(p.at);
            marker.bindPopup(html);
            bounds.push([lat, lon]);
        });
        if (bounds.length) {
            map.fitBounds(bounds, { padding: [24, 24], maxZoom: 8 });
        }
        setTimeout(function(){ map.invalidateSize(); }, 120);
    };

    var renderDonut = function() {
        var canvas = document.getElementById('geo-donut');
        if (!canvas || typeof Chart !== 'function') return;
        var pack = emptyPack(data.donut || {});
        destroyIfAny(canvas);
        canvas._chart = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: pack.labels,
                datasets: [{
                    data: pack.data,
                    backgroundColor: pack.colors || [],
                    borderColor: borderColor(),
                    borderWidth: 2,
                    hoverOffset: pack.empty ? 0 : 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                animation: { duration: 650, easing: 'easeOutQuart' },
                plugins: {
                    legend: legend(),
                    tooltip: { enabled: !pack.empty }
                }
            }
        });
    };

    var renderBar = function(elId, pack, opts) {
        var canvas = document.getElementById(elId);
        if (!canvas || typeof Chart !== 'function') return;
        opts = opts || {};
        var labels = (pack && pack.labels) ? pack.labels.slice() : [];
        var values = (pack && pack.data) ? pack.data.map(function(v){ return Math.max(0, Number(v || 0)); }) : [];
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
                    label: opts.label || 'Ziyaret',
                    data: values,
                    backgroundColor: opts.color || 'rgba(59,130,246,.85)',
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 26
                }]
            },
            options: {
                indexAxis: opts.horizontal ? 'y' : 'x',
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 650, easing: 'easeOutQuart' },
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: gridColor(), drawBorder: false },
                        ticks: { color: labelColor(), font: { size: 10, weight: '700' } }
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

    var renderTrend = function() {
        var canvas = document.getElementById('geo-trend');
        if (!canvas || typeof Chart !== 'function') return;
        var trend = data.trend || { labels: [], data: [] };
        destroyIfAny(canvas);
        canvas._chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: trend.labels || [],
                datasets: [{
                    label: 'Ziyaret',
                    data: trend.data || [],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59,130,246,.16)',
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

    var initCharts = function() {
        renderDonut();
        renderTrend();
        renderBar('geo-countries', data.countries || {}, { horizontal: true, color: 'rgba(99,102,241,.88)', label: 'Ziyaret' });
        renderBar('geo-cities', data.cities || {}, { horizontal: true, color: 'rgba(14,165,233,.88)', label: 'Ziyaret' });
    };

    var bootCharts = function(attempt) {
        if (typeof Chart === 'undefined') {
            if (attempt >= 50) return;
            setTimeout(function(){ bootCharts(attempt + 1); }, 100);
            return;
        }
        initCharts();
    };

    var boot = function() {
        renderMap();
        bootCharts(0);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
