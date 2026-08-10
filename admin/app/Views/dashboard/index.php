<?php

declare(strict_types=1);

$money    = static fn ($value): string => '₺' . number_format((float) $value, 2, ',', '.');
$number   = static fn ($value): string => number_format((float) $value, 0, ',', '.');
$shortMoney = static fn ($value): string => '₺' . number_format((float) $value, 2, ',', '.');

$cards         = isset($kpiCards) && is_array($kpiCards) ? $kpiCards : [];
$affiliateCards = isset($affiliateCards) && is_array($affiliateCards) ? $affiliateCards : [];
$sportStats    = isset($sportStats) && is_array($sportStats) ? $sportStats : [];
$casinoStats   = isset($casinoStats) && is_array($casinoStats) ? $casinoStats : [];
$bonusStats    = isset($bonusStats) && is_array($bonusStats) ? $bonusStats : [];
$depositRows   = isset($depositRows) && is_array($depositRows) ? $depositRows : [];
$withdrawRows  = isset($withdrawRows) && is_array($withdrawRows) ? $withdrawRows : [];
$onlineUserRows = isset($onlineUserRows) && is_array($onlineUserRows) ? $onlineUserRows : [];
$onlineUsers   = (int) ($onlineUsers ?? count($onlineUserRows));
$opQueue       = isset($operationQueue) && is_array($operationQueue) ? $operationQueue : [];
$contentSystem = isset($contentSystem) && is_array($contentSystem) ? $contentSystem : [];
$recentLogs    = isset($recentLogs) && is_array($recentLogs) ? $recentLogs : [];
$topCountries  = isset($topCountries) && is_array($topCountries) ? $topCountries : [];
$visitorMap    = isset($visitorMap) && is_array($visitorMap) ? $visitorMap : ['points' => [], 'total' => 0, 'countries' => 0];
$visitorMapPoints = is_array($visitorMap['points'] ?? null) ? $visitorMap['points'] : [];
$visitorMapTotal = (int) ($visitorMap['total'] ?? 0);
$visitorMapCountries = (int) ($visitorMap['countries'] ?? 0);
$quickActions  = isset($quickActions) && is_array($quickActions) ? $quickActions : [];
$selectedPeriod = (string) ($selectedPeriod ?? 'all');
$dateFrom      = (string) ($dateFrom ?? '2020-01-01');
$dateTo        = (string) ($dateTo ?? date('Y-m-d'));
$flash         = (string) ($flash ?? '');
$openOperations = (int) ($openOperations ?? 0);
$liveUrl       = (string) ($liveUrl ?? AdminAuth::url('/dashboard/live'));
$generatedAt   = (string) ($generated_at ?? date(DATE_ATOM));

$periodUrl = static fn (string $period): string => AdminAuth::url('/dashboard?period=' . rawurlencode($period));

$periodLabels = [
    'all' => 'Tümü',
    'yesterday' => 'Dün',
    'today' => 'Bugün',
    'week' => 'Bu Hafta',
    'month' => 'Bu Ay',
    'prev_month' => 'Geçen Ay',
    'custom' => 'Özel Aralık',
];
$periodLabel = $periodLabels[$selectedPeriod] ?? 'Tümü';

$formatKpi = static function (array $card) use ($money, $number): string {
    return ($card['type'] ?? 'number') === 'money'
        ? $money((float) ($card['value'] ?? 0))
        : $number((float) ($card['value'] ?? 0));
};

$kpiIconSvg = static function (string $icon): string {
    $paths = [
        'deposit' => '<path d="M12 3v12m0 0l-4-4m4 4l4-4M5 19h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'withdraw' => '<path d="M12 21V9m0 0l-4 4m4-4l4 4M5 5h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'adjust-up' => '<path d="M12 19V5m0 0l-5 5m5-5l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'adjust-down' => '<path d="M12 5v14m0 0l5-5m-5 5l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'players' => '<path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M11 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm10 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'new-players' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm8-6v6m3-3h-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'login-users' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'active-players' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'wallet' => '<path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2zm0 5h.01M16 7V5a2 2 0 0 0-2-2H6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'bonus' => '<path d="M20 12v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8M12 2v6m0 0l-3-3m3 3l3-3M2 12h20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
    ];
    $path = $paths[$icon] ?? $paths['players'];

    return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true">' . $path . '</svg>';
};

$chartColors = ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316', '#eab308', '#94a3b8', '#ec4899'];

$buildDonutSeries = static function (array $stats) use (&$chartColors): array {
    $series = [];
    $labels = [];
    $colors = [];
    $legend = (array) ($stats['legend'] ?? []);
    foreach ($legend as $item) {
        $labels[] = (string) ($item['label'] ?? '');
        $series[] = (float) ($item['value'] ?? 0);
        $colors[] = (string) ($item['color'] ?? $chartColors[count($colors) % count($chartColors)]);
    }
    return ['series' => $series, 'labels' => $labels, 'colors' => $colors];
};

$chartData = [
    'sport' => [
        'donut'  => $buildDonutSeries($sportStats),
        'bars'   => ['labels' => (array) ($sportStats['labels'] ?? []), 'values' => array_map('floatval', (array) ($sportStats['values'] ?? [])), 'formats' => (array) ($sportStats['formats'] ?? [])],
        'tabs'   => (array) ($sportStats['tabs'] ?? []),
        'active' => (string) ($sportStats['active_tab'] ?? ''),
        'empty'  => (string) ($sportStats['empty_message'] ?? ''),
        'module' => (string) ($sportStats['module_url'] ?? '/dashboard'),
    ],
    'casino' => [
        'donut'  => $buildDonutSeries($casinoStats),
        'bars'   => ['labels' => (array) ($casinoStats['labels'] ?? []), 'values' => array_map('floatval', (array) ($casinoStats['values'] ?? [])), 'formats' => (array) ($casinoStats['formats'] ?? [])],
        'tabs'   => (array) ($casinoStats['tabs'] ?? []),
        'active' => (string) ($casinoStats['active_tab'] ?? ''),
        'empty'  => (string) ($casinoStats['empty_message'] ?? ''),
        'module' => (string) ($casinoStats['module_url'] ?? '/dashboard'),
    ],
    'bonus' => [
        'donut'  => $buildDonutSeries($bonusStats),
        'bars'   => ['labels' => (array) ($bonusStats['labels'] ?? []), 'values' => array_map('floatval', (array) ($bonusStats['values'] ?? [])), 'formats' => (array) ($bonusStats['formats'] ?? [])],
        'tabs'   => (array) ($bonusStats['tabs'] ?? []),
        'active' => (string) ($bonusStats['active_tab'] ?? ''),
        'empty'  => (string) ($bonusStats['empty_message'] ?? ''),
        'module' => (string) ($bonusStats['module_url'] ?? '/module?key=active-bonuses'),
    ],
];
foreach ((array) ($casinoStats['datasets'] ?? []) as $tab => $dataset) {
    if (!is_array($dataset)) {
        continue;
    }
    $chartData['casino']['datasets'][(string) $tab] = [
        'donut' => $buildDonutSeries($dataset),
        'bars' => [
            'labels' => (array) ($dataset['labels'] ?? []),
            'values' => array_map('floatval', (array) ($dataset['values'] ?? [])),
            'formats' => (array) ($dataset['formats'] ?? []),
        ],
    ];
}

$formatStatValue = static function (float $value, string $format) use ($shortMoney, $number): string {
    return match ($format) {
        'number'  => $number($value),
        'percent' => number_format($value, 2, ',', '.') . '%',
        default   => $shortMoney($value),
    };
};

$kpiColorMap = [
    'success' => ['accent' => '#16a34a'],
    'danger'  => ['accent' => '#dc2626'],
    'primary' => ['accent' => '#2563eb'],
    'warning' => ['accent' => '#d97706'],
    'purple'  => ['accent' => '#7c3aed'],
    'info'    => ['accent' => '#0891b2'],
];

$opColors = ['primary' => '#2563eb', 'danger' => '#dc2626', 'warning' => '#d97706', 'info' => '#0891b2', 'purple' => '#7c3aed', 'success' => '#16a34a'];
$showFlashModal = $flash !== '';
$countryMax = 1;
foreach ($topCountries as $cRow) {
    $countryMax = max($countryMax, (int) ($cRow['total'] ?? 0));
}
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<style>
.db-shell { display:flex; flex-direction:column; gap:16px; animation:dbIn .45s ease both; }
@keyframes dbIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
@keyframes dbPulse { 0%,100% { opacity:1; box-shadow:0 0 0 0 color-mix(in srgb, var(--danger) 45%, transparent); } 50% { opacity:.85; box-shadow:0 0 0 6px transparent; } }

.db-hero {
    align-items:flex-end; background:
        radial-gradient(1200px 220px at 12% -40%, color-mix(in srgb, var(--primary) 22%, transparent), transparent 60%),
        radial-gradient(900px 180px at 92% 0%, color-mix(in srgb, #06b6d4 14%, transparent), transparent 55%),
        var(--bg-card);
    border:1px solid var(--border); border-radius:18px; box-shadow:var(--shadow-card);
    display:flex; flex-wrap:wrap; gap:16px; justify-content:space-between; overflow:hidden; padding:18px 20px 16px; position:relative;
}
.db-hero::after {
    background:linear-gradient(90deg, color-mix(in srgb, var(--primary) 70%, transparent), color-mix(in srgb, #06b6d4 55%, transparent), transparent 70%);
    content:""; height:2px; left:0; position:absolute; right:0; top:0;
}
.db-hero-copy { min-width:min(100%,280px); }
.db-eyebrow { color:var(--primary); display:inline-flex; font-size:10px; font-weight:900; letter-spacing:.12em; margin-bottom:6px; text-transform:uppercase; }
.db-title { color:var(--t-base); font-family:'Inter Tight',Inter,sans-serif; font-size:26px; font-weight:900; letter-spacing:-.04em; line-height:1.1; margin:0; }
.db-title span { background:linear-gradient(135deg, var(--primary), #06b6d4); -webkit-background-clip:text; background-clip:text; color:transparent; }
.db-sub { color:var(--t-muted); font-size:12.5px; font-weight:600; line-height:1.45; margin:8px 0 0; max-width:46ch; }
.db-hero-meta { align-items:center; display:flex; flex-wrap:wrap; gap:8px; }
.db-pill {
    align-items:center; background:color-mix(in srgb, var(--bg-muted) 80%, var(--bg-card)); border:1px solid var(--border-soft);
    border-radius:999px; color:var(--t-muted); display:inline-flex; font-size:11px; font-weight:800; gap:7px; padding:7px 12px;
}
.db-pill strong { color:var(--t-base); font-weight:900; }
.db-pill.live { color:var(--success); }
.db-pill.live .db-dot { animation:dbPulse 1.8s ease infinite; background:var(--success); border-radius:50%; height:7px; width:7px; }
.db-pill.warn { color:var(--warning, #d97706); }
.db-pill.warn .db-dot { background:var(--warning, #d97706); }
.db-pill.ops-alert { color:var(--danger); }
.db-pill.ops-alert .db-dot { background:var(--danger); }

.db-toolbar { align-items:center; display:flex; flex-wrap:wrap; gap:10px; justify-content:space-between; }
.db-seg {
    background:var(--bg-card); border:1px solid var(--border); border-radius:12px; box-shadow:var(--shadow-card);
    display:inline-flex; flex-wrap:wrap; gap:2px; padding:4px;
}
.db-filter {
    background:transparent; border:0; border-radius:9px; color:var(--t-muted); cursor:pointer; display:inline-flex;
    font-size:11.5px; font-weight:800; line-height:1.2; padding:8px 12px; text-decoration:none; transition:.16s ease;
}
.db-filter:hover { background:var(--bg-hover); color:var(--t-base); }
.db-filter.is-on { background:var(--primary); color:#fff; box-shadow:0 8px 18px -12px color-mix(in srgb, var(--primary) 80%, transparent); }
.db-tools { align-items:center; display:flex; flex-wrap:wrap; gap:8px; }
.db-date {
    background:var(--bg-card); border:1px solid var(--border); border-radius:10px; color:var(--t-base);
    font-size:11.5px; font-weight:700; height:36px; padding:0 10px;
}
.db-btn {
    background:var(--primary); border:1px solid var(--primary); border-radius:10px; color:#fff; cursor:pointer;
    font-size:11.5px; font-weight:800; height:36px; padding:0 14px; transition:.16s ease; white-space:nowrap;
}
.db-btn:hover { filter:brightness(1.06); transform:translateY(-1px); }
.db-btn.ghost { background:var(--bg-card); border-color:var(--border); color:var(--t-base); }
.db-btn.danger { background:color-mix(in srgb, var(--danger) 12%, var(--bg-card)); border-color:color-mix(in srgb, var(--danger) 35%, var(--border)); color:var(--danger); }

.db-quick { display:grid; gap:12px; grid-template-columns:repeat(4,minmax(0,1fr)); }
.db-qa {
    background:var(--bg-card); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow-card);
    display:flex; flex-direction:column; gap:4px; padding:16px; text-decoration:none; transition:.16s ease;
}
.db-qa:hover { background:color-mix(in srgb, var(--bg-muted) 35%, var(--bg-card)); }
.db-qa-count {
    color:var(--t-base); font-size:22px; font-weight:900;
    letter-spacing:-.02em; line-height:1.05; overflow-wrap:anywhere;
}
.db-qa-count.is-alert { color:var(--qa-accent,#3b82f6); }
.db-qa-title {
    color:var(--t-muted); font-size:11px; font-weight:800; letter-spacing:.06em;
    margin-top:6px; text-transform:uppercase;
}
.db-qa-text { display:none; }

.db-kpi-grid { display:grid; gap:12px; grid-template-columns:repeat(5,minmax(0,1fr)); }
.db-kpi-grid.aff { grid-template-columns:repeat(3,minmax(0,1fr)); }
.db-kpi {
    background:var(--bg-card); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow-card);
    padding:14px 14px 12px; min-width:0; transition:.16s ease; display:flex; flex-direction:column; gap:8px;
}
.db-kpi:hover { background:color-mix(in srgb, var(--bg-muted) 28%, var(--bg-card)); }
.db-kpi-top { display:flex; align-items:flex-start; justify-content:space-between; gap:8px; }
.db-kpi-icon {
    align-items:center; background:color-mix(in srgb, var(--kpi-accent,#3b82f6) 16%, transparent);
    border-radius:10px; color:var(--kpi-accent,#3b82f6); display:inline-flex; flex-shrink:0; height:34px; justify-content:center; width:34px;
}
.db-kpi-label {
    color:var(--t-muted); display:block; font-size:11px; font-weight:800; letter-spacing:.04em;
    line-height:1.3; text-transform:uppercase;
}
.db-kpi-val {
    color:var(--t-base); font-size:20px; font-weight:900;
    letter-spacing:-.02em; line-height:1.15; overflow-wrap:anywhere;
}
.db-kpi-val.tone { color:var(--kpi-accent,#3b82f6); }
.db-kpi-sub { color:var(--t-muted); font-size:11px; font-weight:700; }
.db-section-label {
    color:var(--t-muted); font-size:11px; font-weight:800; letter-spacing:.06em; margin:2px 0 0; text-transform:uppercase;
}

.db-col2 { display:grid; gap:12px; grid-template-columns:repeat(2,minmax(0,1fr)); }
.db-col3 { display:grid; gap:12px; grid-template-columns:repeat(3,minmax(0,1fr)); }
.db-panel {
    background:var(--bg-card); border:1px solid var(--border); border-radius:16px; box-shadow:var(--shadow-card);
    padding:14px 16px 16px; min-width:0;
}
.db-panel.full { grid-column:1/-1; }
.db-panel-head {
    align-items:center; border-bottom:1px solid var(--border-soft); display:flex; gap:10px;
    justify-content:space-between; margin:0 -16px 14px; padding:0 16px 12px;
}
.db-panel-title { color:var(--t-base); font-size:13.5px; font-weight:900; letter-spacing:-.02em; margin:0; }
.db-panel-hint { color:var(--t-light); font-size:10.5px; font-weight:700; }
.db-link { color:var(--primary); font-size:11px; font-weight:800; text-decoration:none; }
.db-link:hover { text-decoration:underline; }

.db-map-layout { display:grid; gap:14px; grid-template-columns:minmax(0,1.7fr) minmax(240px,.9fr); }
.db-map-wrap {
    background:var(--bg-muted); border:1px solid var(--border-soft); border-radius:14px; height:440px; overflow:hidden; position:relative;
}
.db-map-wrap .leaflet-control-attribution { background:color-mix(in srgb, var(--bg-card) 82%, transparent) !important; color:var(--t-muted) !important; font-size:9px !important; }
.db-map-side { display:flex; flex-direction:column; gap:12px; min-width:0; }
.db-map-stats { display:grid; gap:8px; grid-template-columns:repeat(3,minmax(0,1fr)); }
.db-map-stat {
    background:color-mix(in srgb, var(--bg-muted) 75%, var(--bg-card)); border:1px solid var(--border-soft);
    border-radius:12px; padding:10px 11px; text-align:center;
}
.db-map-stat strong { color:var(--t-base); display:block; font-family:'Inter Tight',Inter,sans-serif; font-size:18px; font-weight:900; letter-spacing:-.03em; }
.db-map-stat span { color:var(--t-muted); display:block; font-size:10px; font-weight:800; margin-top:3px; text-transform:uppercase; letter-spacing:.04em; }
.db-map-countries { display:flex; flex:1; flex-direction:column; gap:8px; max-height:320px; overflow:auto; padding-right:2px; }
.db-map-country {
    background:color-mix(in srgb, var(--bg-muted) 70%, var(--bg-card)); border:1px solid transparent; border-radius:12px;
    display:grid; gap:6px; padding:10px 11px; transition:.14s ease;
}
.db-map-country:hover { border-color:var(--border); }
.db-map-country-top { align-items:center; display:flex; justify-content:space-between; gap:8px; }
.db-map-country span { color:var(--t-base); font-size:12px; font-weight:800; }
.db-map-country em { color:var(--t-muted); font-family:'JetBrains Mono',monospace; font-size:11px; font-style:normal; font-weight:800; }
.db-map-bar { background:var(--bg-muted); border-radius:999px; height:5px; overflow:hidden; }
.db-map-bar > i { background:linear-gradient(90deg, #3b82f6, #06b6d4); border-radius:inherit; display:block; height:100%; }

.db-chart-wrap { height:260px; min-height:260px; position:relative; }
.db-chart-wrap.tall { height:280px; min-height:280px; }
.db-chart-wrap canvas { height:100% !important; width:100% !important; }
.db-empty {
    background:color-mix(in srgb, var(--bg-muted) 70%, var(--bg-card)); border:1px dashed var(--border); border-radius:12px;
    color:var(--t-muted); font-size:11.5px; font-weight:700; margin-bottom:10px; padding:14px 14px;
}
.db-tabs { display:flex; flex-wrap:wrap; gap:4px; margin-bottom:12px; }
.db-tab {
    background:transparent; border:0; border-radius:8px; color:var(--t-muted); cursor:pointer;
    font-size:10.5px; font-weight:800; padding:6px 10px; transition:.14s;
}
.db-tab:hover { background:var(--bg-hover); color:var(--t-base); }
.db-tab.on { background:var(--primary); color:#fff; }

.db-ops { display:flex; flex-direction:column; gap:7px; }
.db-op {
    align-items:center; background:color-mix(in srgb, var(--bg-muted) 72%, var(--bg-card)); border:1px solid transparent;
    border-radius:12px; display:flex; gap:11px; padding:11px 12px; text-decoration:none; transition:.16s ease;
}
.db-op:hover { background:var(--bg-hover); border-color:var(--border); transform:translateX(2px); }
.db-op-dot { border-radius:50%; box-shadow:0 0 0 4px color-mix(in srgb, var(--op-c,#3b82f6) 18%, transparent); flex-shrink:0; height:9px; width:9px; background:var(--op-c,#3b82f6); }
.db-op-label { color:var(--t-base); flex:1; font-size:12.5px; font-weight:750; }
.db-op-badge {
    background:var(--bg-card); border:1px solid var(--border-soft); border-radius:8px; color:var(--t-muted);
    font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:800; padding:3px 8px;
}

.db-table { border-collapse:collapse; width:100%; }
.db-table th {
    border-bottom:1px solid var(--border); color:var(--t-light); font-size:9.5px; font-weight:800;
    letter-spacing:.05em; padding:8px 8px; text-align:left; text-transform:uppercase;
}
.db-table td { border-bottom:1px solid var(--border-soft); color:var(--t-muted); font-size:12px; padding:9px 8px; }
.db-table tr:last-child td { border-bottom:0; }
.db-table tr:hover td { background:color-mix(in srgb, var(--bg-muted) 45%, transparent); }
.db-badge {
    border-radius:999px; display:inline-flex; font-size:10px; font-weight:900; letter-spacing:.02em; padding:3px 8px; text-transform:uppercase;
}
.db-badge.ok { background:var(--success-soft); color:var(--success); }
.db-badge.bad { background:var(--danger-soft); color:var(--danger); }

.db-ck { align-items:center; display:flex; gap:10px; padding:8px 0; }
.db-ck + .db-ck { border-top:1px solid var(--border-soft); }
.db-ck-icon {
    align-items:center; background:var(--success-soft); border-radius:10px; color:var(--success);
    display:flex; flex-shrink:0; font-size:11px; font-weight:900; height:28px; justify-content:center; width:28px;
}
.db-ck-icon.off { background:var(--danger-soft); color:var(--danger); }
.db-ck-label { color:var(--t-base); font-size:12.5px; font-weight:800; }
.db-ck-meta { color:var(--t-muted); font-size:10.5px; margin-top:2px; }
.db-log { border-bottom:1px solid var(--border-soft); padding:10px 0; }
.db-log:last-child { border-bottom:0; }
.db-log-who { color:var(--t-base); font-size:12px; font-weight:800; }
.db-log-what { color:var(--t-muted); font-size:11px; margin-top:3px; }

@media (max-width:1280px) {
    .db-kpi-grid { grid-template-columns:repeat(3,minmax(0,1fr)); }
    .db-quick { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .db-col2,.db-col3,.db-map-layout { grid-template-columns:1fr; }
    .db-map-wrap { height:360px; }
}
@media (max-width:760px) {
    .db-title { font-size:22px; }
    .db-kpi-grid,.db-kpi-grid.aff,.db-quick,.db-map-stats { grid-template-columns:1fr; }
    .db-map-wrap { height:300px; }
    .db-seg { width:100%; }
}
</style>

<section class="db-shell" data-live-url="<?= htmlspecialchars($liveUrl, ENT_QUOTES, 'UTF-8') ?>" data-generated-at="<?= htmlspecialchars($generatedAt, ENT_QUOTES, 'UTF-8') ?>">
    <div class="db-hero">
        <div class="db-hero-copy">
            <div class="db-eyebrow">Operasyon · Realtime</div>
            <h1 class="db-title">Gösterge <span>Paneli</span></h1>
            <p class="db-sub">Finans ve oyuncu metrikleri dönem filtresiyle canlı güncellenir. Kartlar ve Chart.js grafikleri 12 sn’de bir yenilenir.</p>
        </div>
        <div class="db-hero-meta">
            <div class="db-pill">Dönem · <strong id="db-period-label"><?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></strong></div>
            <div class="db-pill" id="db-period-range"><?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="db-pill live" id="db-live-pill">
                <span class="db-dot" aria-hidden="true"></span>
                Canlı · <strong id="db-live-age">şimdi</strong>
            </div>
            <div class="db-pill <?= $openOperations > 0 ? 'ops-alert' : '' ?>" id="db-ops-pill">
                <?php if ($openOperations > 0): ?><span class="db-dot" aria-hidden="true"></span><?php endif; ?>
                Açık operasyon · <strong id="db-open-ops"><?= htmlspecialchars($number($openOperations), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
        </div>
    </div>

    <div class="db-toolbar">
        <div class="db-seg" role="tablist" aria-label="Dönem filtresi">
            <?php foreach (['all' => 'Tümü', 'yesterday' => 'Dün', 'today' => 'Bugün', 'week' => 'Bu Hafta', 'month' => 'Bu Ay', 'prev_month' => 'Geçen Ay'] as $p => $l): ?>
                <a class="db-filter <?= $selectedPeriod === $p ? 'is-on' : '' ?>" href="<?= htmlspecialchars($periodUrl($p)) ?>"><?= htmlspecialchars($l, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
        </div>
        <div class="db-tools">
            <form method="get" action="<?= htmlspecialchars(AdminAuth::url('/dashboard')) ?>" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                <input type="hidden" name="period" value="custom">
                <input class="db-date" type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" title="Başlangıç">
                <input class="db-date" type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" title="Bitiş">
                <button class="db-btn" type="submit">Uygula</button>
            </form>
            <form method="post" action="<?= htmlspecialchars(AdminAuth::url('/dashboard/cache-purge')) ?>" onsubmit="return confirm('Tüm CMS/API önbellekleri temizlensin mi?')" style="display:inline">
                <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken()) ?>">
                <button class="db-btn danger" type="submit">Önbellek Temizle</button>
            </form>
        </div>
    </div>

    <?php if ($quickActions !== []): ?>
    <div class="db-quick" id="db-quick-actions">
        <?php foreach ($quickActions as $qa):
            $qaClass = (string) ($qa['class'] ?? 'primary');
            $qaAccent = $opColors[$qaClass] ?? '#3b82f6';
            $qaCount = (float) ($qa['count'] ?? 0);
            $qaKey = match ((string) ($qa['title'] ?? '')) {
                'Çekim Onayı' => 'qa_withdraw',
                'KYC Kontrol' => 'qa_kyc',
                'Yatırım Takibi' => 'qa_deposit',
                'Bonus Talepleri' => 'qa_bonus',
                default => 'qa_' . substr(md5((string) ($qa['title'] ?? '')), 0, 6),
            };
        ?>
        <a class="db-qa" data-qa-key="<?= htmlspecialchars($qaKey, ENT_QUOTES, 'UTF-8') ?>" style="--qa-accent:<?= htmlspecialchars($qaAccent, ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars(AdminAuth::url((string) ($qa['url'] ?? '#')), ENT_QUOTES, 'UTF-8') ?>">
            <span class="db-qa-count <?= $qaCount > 0 ? 'is-alert' : '' ?>" data-qa-count><?= htmlspecialchars($number($qaCount), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="db-qa-title"><?= htmlspecialchars((string) ($qa['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="db-kpi-grid" id="db-kpi-grid">
        <?php foreach ($cards as $card):
            $st = (string) ($card['status'] ?? 'primary');
            $c = $kpiColorMap[$st] ?? $kpiColorMap['primary'];
            $isMoney = ($card['type'] ?? 'number') === 'money';
            $key = (string) ($card['key'] ?? '');
            $icon = (string) ($card['icon'] ?? 'players');
            $count = (float) ($card['count'] ?? 0);
            $countLabel = (string) ($card['count_label'] ?? 'Oyuncu');
        ?>
        <div class="db-kpi" data-kpi-key="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" data-kpi-type="<?= htmlspecialchars((string) ($card['type'] ?? 'number'), ENT_QUOTES, 'UTF-8') ?>" data-kpi-count-label="<?= htmlspecialchars($countLabel, ENT_QUOTES, 'UTF-8') ?>" style="--kpi-accent:<?= htmlspecialchars($c['accent'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="db-kpi-top">
                <span class="db-kpi-icon"><?= $kpiIconSvg($icon) ?></span>
            </div>
            <span class="db-kpi-label"><?= htmlspecialchars((string) ($card['label'] ?? '')) ?></span>
            <div class="db-kpi-val <?= $isMoney && in_array($st, ['success', 'danger'], true) ? 'tone' : '' ?>" data-kpi-value><?= htmlspecialchars($formatKpi($card)) ?></div>
            <div class="db-kpi-sub" data-kpi-count><?= htmlspecialchars($number($count), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($countLabel, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($affiliateCards !== []): ?>
    <div class="db-section-label">Affiliate</div>
    <div class="db-kpi-grid aff" id="db-aff-grid">
        <?php foreach ($affiliateCards as $card):
            $st = (string) ($card['status'] ?? 'primary');
            $c = $kpiColorMap[$st] ?? $kpiColorMap['primary'];
            $isMoney = ($card['type'] ?? 'number') === 'money';
            $key = (string) ($card['key'] ?? '');
            $icon = (string) ($card['icon'] ?? 'players');
            $count = (float) ($card['count'] ?? 0);
            $countLabel = (string) ($card['count_label'] ?? 'Oyuncu');
        ?>
        <div class="db-kpi" data-kpi-key="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" data-kpi-type="<?= htmlspecialchars((string) ($card['type'] ?? 'number'), ENT_QUOTES, 'UTF-8') ?>" data-kpi-count-label="<?= htmlspecialchars($countLabel, ENT_QUOTES, 'UTF-8') ?>" style="--kpi-accent:<?= htmlspecialchars($c['accent'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="db-kpi-top">
                <span class="db-kpi-icon"><?= $kpiIconSvg($icon) ?></span>
            </div>
            <span class="db-kpi-label"><?= htmlspecialchars((string) ($card['label'] ?? '')) ?></span>
            <div class="db-kpi-val <?= $isMoney && in_array($st, ['success', 'danger'], true) ? 'tone' : '' ?>" data-kpi-value><?= htmlspecialchars($formatKpi($card)) ?></div>
            <div class="db-kpi-sub" data-kpi-count><?= htmlspecialchars($number($count), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($countLabel, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="db-col2" id="db-primary-charts">
        <?php foreach (['sport' => 'Spor', 'casino' => 'Casino'] as $key => $title): $d = $chartData[$key]; ?>
        <div class="db-panel" data-chart-panel="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
            <div class="db-panel-head">
                <h2 class="db-panel-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> İstatistikleri</h2>
                <a class="db-link" href="<?= htmlspecialchars(AdminAuth::url($d['module'])) ?>">Modül →</a>
            </div>
            <?php if ($d['empty']): ?><div class="db-empty"><?= htmlspecialchars($d['empty']) ?></div><?php endif; ?>
            <?php if (!empty($d['tabs'])): ?>
            <div class="db-tabs">
                <?php foreach ($d['tabs'] as $tab): ?>
                <button type="button" class="db-tab <?= $tab === $d['active'] ? 'on' : '' ?>" data-tab="<?= htmlspecialchars($tab) ?>"><?= htmlspecialchars($tab) ?></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="db-chart-wrap tall"><canvas id="db-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>-donut"></canvas></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="db-panel full">
        <div class="db-panel-head">
            <div>
                <h2 class="db-panel-title">Ziyaretçi Dünya Haritası</h2>
                <div class="db-panel-hint">GeoIP · seçili dönem (SSR)</div>
            </div>
            <a class="db-link" href="<?= htmlspecialchars(AdminAuth::url('/reports/geomap'), ENT_QUOTES, 'UTF-8') ?>">Detaylı harita →</a>
        </div>
        <div class="db-map-layout">
            <div>
                <?php if ($visitorMapPoints === []): ?>
                    <div class="db-empty">Seçili dönemde haritada gösterilecek GeoIP kaydı yok. Ziyaretler geldikçe dünya haritası burada güncellenir.</div>
                <?php else: ?>
                    <div id="db-visitor-map" class="db-map-wrap" role="img" aria-label="Ziyaretçi dünya haritası"></div>
                <?php endif; ?>
            </div>
            <div class="db-map-side">
                <div class="db-map-stats">
                    <div class="db-map-stat"><strong><?= htmlspecialchars($number($visitorMapTotal), ENT_QUOTES, 'UTF-8') ?></strong><span>Ziyaret</span></div>
                    <div class="db-map-stat"><strong><?= htmlspecialchars($number($visitorMapCountries), ENT_QUOTES, 'UTF-8') ?></strong><span>Ülke</span></div>
                    <div class="db-map-stat"><strong><?= htmlspecialchars($number(count($visitorMapPoints)), ENT_QUOTES, 'UTF-8') ?></strong><span>Nokta</span></div>
                </div>
                <?php if ($topCountries !== []): ?>
                    <div class="db-map-countries">
                        <?php foreach ($topCountries as $c):
                            $tot = (int) ($c['total'] ?? 0);
                            $pct = $countryMax > 0 ? max(4, min(100, (int) round(($tot / $countryMax) * 100))) : 4;
                        ?>
                            <div class="db-map-country">
                                <div class="db-map-country-top">
                                    <span><?= htmlspecialchars((string) ($c['country'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
                                    <em><?= htmlspecialchars($number($tot), ENT_QUOTES, 'UTF-8') ?></em>
                                </div>
                                <div class="db-map-bar"><i style="width:<?= $pct ?>%"></i></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="db-empty" style="margin:0">Ülke dağılımı henüz yok.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="db-col2">
        <div class="db-panel" data-chart-panel="bonus">
            <div class="db-panel-head">
                <h2 class="db-panel-title">Bonus İstatistikleri</h2>
                <a class="db-link" href="<?= htmlspecialchars(AdminAuth::url($chartData['bonus']['module'])) ?>">Modül →</a>
            </div>
            <?php if ($chartData['bonus']['empty']): ?><div class="db-empty"><?= htmlspecialchars($chartData['bonus']['empty']) ?></div><?php endif; ?>
            <?php if (!empty($chartData['bonus']['tabs'])): ?>
            <div class="db-tabs">
                <?php foreach ($chartData['bonus']['tabs'] as $tab): ?>
                <button type="button" class="db-tab <?= $tab === $chartData['bonus']['active'] ? 'on' : '' ?>" data-tab="<?= htmlspecialchars($tab) ?>"><?= htmlspecialchars($tab) ?></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="db-chart-wrap tall"><canvas id="db-bonus-donut"></canvas></div>
        </div>

        <div class="db-panel">
            <div class="db-panel-head"><h2 class="db-panel-title">Operasyon Kuyruğu</h2></div>
            <div class="db-ops" id="db-ops-queue">
                <?php foreach ($opQueue as $op): $cls = (string) ($op['class'] ?? 'primary'); ?>
                <a class="db-op" data-op-key="<?= htmlspecialchars((string) ($op['key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars(AdminAuth::url((string) ($op['url'] ?? '#'))) ?>">
                    <span class="db-op-dot" style="--op-c:<?= htmlspecialchars($opColors[$cls] ?? '#3b82f6') ?>"></span>
                    <span class="db-op-label"><?= htmlspecialchars((string) ($op['label'] ?? '')) ?></span>
                    <span class="db-op-badge" data-op-value><?= (int) ($op['value'] ?? 0) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="db-col2">
        <div class="db-panel">
            <div class="db-panel-head"><h2 class="db-panel-title">Sistem Sağlığı</h2></div>
            <?php foreach ($contentSystem as $item): $ok = !empty($item['ok']); ?>
            <div class="db-ck">
                <span class="db-ck-icon <?= $ok ? '' : 'off' ?>"><?= $ok ? '✓' : '!' ?></span>
                <div>
                    <div class="db-ck-label"><?= htmlspecialchars((string) ($item['name'] ?? '')) ?></div>
                    <div class="db-ck-meta"><?= (int) ($item['value'] ?? 0) ?> <?= htmlspecialchars((string) ($item['label'] ?? '')) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($contentSystem)): ?>
            <div class="db-empty">Sistem sağlığı verisi yok.</div>
            <?php endif; ?>
        </div>

        <div class="db-panel">
            <div class="db-panel-head"><h2 class="db-panel-title">Son Log Kayıtları</h2></div>
            <?php foreach ($recentLogs as $log): ?>
            <div class="db-log">
                <div class="db-log-who"><?= htmlspecialchars((string) ($log['admin_username'] ?? 'Sistem')) ?></div>
                <div class="db-log-what"><?= htmlspecialchars((string) ($log['action'] ?? '')) ?> · <?= htmlspecialchars((string) ($log['created_at'] ?? '')) ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($recentLogs)): ?>
            <div class="db-empty">Henüz log kaydı yok.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="db-col2">
        <?php foreach ([['title'=>'Son Yatırımlar','rows'=>$depositRows,'url'=>'/module?key=deposits','key'=>'deposit'],['title'=>'Son Çekimler','rows'=>$withdrawRows,'url'=>'/module?key=withdrawals','key'=>'withdraw']] as $tbl): ?>
        <div class="db-panel">
            <div class="db-panel-head">
                <h2 class="db-panel-title"><?= htmlspecialchars($tbl['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                <a class="db-link" href="<?= htmlspecialchars(AdminAuth::url($tbl['url'])) ?>">Tümü →</a>
            </div>
            <div style="overflow-x:auto">
            <table class="db-table" data-tx-table="<?= htmlspecialchars($tbl['key'], ENT_QUOTES, 'UTF-8') ?>">
                <thead><tr><th>Tarih</th><th>Oyuncu</th><th>Miktar</th><th>Durum</th></tr></thead>
                <tbody>
                <?php foreach ((array) $tbl['rows'] as $r): $stTx = strtolower((string) ($r['status'] ?? '')); $okTx = in_array($stTx,['confirmed','completed','approved','success'],true); ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($r['created_at'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) ($r['username'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars($shortMoney($r['amount'] ?? 0)) ?></td>
                    <td><span class="db-badge <?= $okTx ? 'ok' : 'bad' ?>"><?= htmlspecialchars((string) ($r['status'] ?? '-')) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($tbl['rows'])): ?><tr><td colspan="4"><div class="db-empty" style="margin:0">Henüz işlem yok.</div></td></tr><?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="db-panel" style="grid-column:1 / -1">
            <div class="db-panel-head">
                <h2 class="db-panel-title">Çevrimiçi Kullanıcılar <span class="db-badge ok" id="db-online-count"><?= htmlspecialchars($number($onlineUsers), ENT_QUOTES, 'UTF-8') ?></span></h2>
                <span class="db-link" style="cursor:default;opacity:.75">Son 10 dk · canlı</span>
            </div>
            <div style="overflow-x:auto">
            <table class="db-table" data-online-table="1">
                <thead><tr><th>Oyuncu</th><th>Son görülme</th><th>Bakiye</th><th>Bonus</th></tr></thead>
                <tbody>
                <?php foreach ($onlineUserRows as $ou): ?>
                <tr>
                    <td>
                        <?php if (!empty($ou['url'])): ?>
                        <a class="db-link" href="<?= htmlspecialchars((string) $ou['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($ou['username'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></a>
                        <?php else: ?>
                        <?= htmlspecialchars((string) ($ou['username'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars((string) ($ou['last_seen_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($shortMoney($ou['balance'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($shortMoney($ou['bonus_balance'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if ($onlineUserRows === []): ?>
                <tr><td colspan="4"><div class="db-empty" style="margin:0">Şu an çevrimiçi üye yok.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</section>

<script>
(function(){
    var shell = document.querySelector('.db-shell');
    var liveUrl = shell ? String(shell.getAttribute('data-live-url') || '') : '';
    var lastFetchAt = Date.now();
    var pollTimer = null;
    var fetching = false;
    var POLL_MS = 12000;

    var theme = function() {
        return document.documentElement.getAttribute('data-theme') || 'light';
    };
    var labelColor = function() {
        return theme() === 'dark' ? '#94a3b8' : '#64748b';
    };
    var centerTextColor = function() {
        return theme() === 'dark' ? '#e2e8f0' : '#0f172a';
    };
    var moneyFmt = function(n) {
        return '₺' + Number(n || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    var numberFmt = function(n) {
        return Number(n || 0).toLocaleString('tr-TR', { maximumFractionDigits: 0 });
    };
    var formatKpi = function(value, type) {
        return type === 'money' ? moneyFmt(value) : numberFmt(value);
    };

    var centerPlugin = {
        id: 'dbCenterTotal',
        afterDraw: function(chart) {
            var meta = chart.$dbCenter;
            if (!meta || meta.empty) return;
            var ctx = chart.ctx;
            var area = chart.chartArea;
            if (!area) return;
            var x = (area.left + area.right) / 2;
            var y = (area.top + area.bottom) / 2;
            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = centerTextColor();
            ctx.font = '800 15px Inter, system-ui, sans-serif';
            ctx.fillText(meta.totalText || '', x, y - 8);
            ctx.fillStyle = labelColor();
            ctx.font = '700 10px Inter, system-ui, sans-serif';
            ctx.fillText('Toplam', x, y + 12);
            ctx.restore();
        }
    };

    var chartOptions = function(isEmpty) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            animation: { duration: 450, easing: 'easeOutQuart' },
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        color: labelColor(),
                        boxWidth: 8,
                        boxHeight: 8,
                        padding: 14,
                        usePointStyle: true,
                        font: { size: 11, weight: '700' }
                    }
                },
                tooltip: {
                    enabled: !isEmpty,
                    callbacks: {
                        label: function(ctx) {
                            var n = Number(ctx.raw || 0);
                            return (ctx.label || '') + ': ' + moneyFmt(n);
                        }
                    }
                }
            }
        };
    };

    var sumAbs = function(arr) {
        return (arr || []).reduce(function(s, v){ return s + Math.abs(Number(v || 0)); }, 0);
    };

    var renderChart = function(elId, series, labels, colors, soft) {
        var canvas = document.getElementById(elId);
        if (!canvas || typeof Chart !== 'function') return null;
        var values = Array.isArray(series) ? series.map(function(value) { return Math.max(0, Number(value || 0)); }) : [];
        var isEmpty = values.reduce(function(sum, value) { return sum + value; }, 0) <= 0;
        var total = sumAbs(series);
        var totalText = moneyFmt(total);
        if (isEmpty) {
            values = [1];
            labels = ['Veri yok'];
            colors = ['rgba(148, 163, 184, .35)'];
            totalText = '—';
        }
        if (canvas._chart && soft) {
            canvas._chart.data.labels = labels;
            canvas._chart.data.datasets[0].data = values;
            canvas._chart.data.datasets[0].backgroundColor = colors;
            canvas._chart.data.datasets[0].borderColor = theme() === 'dark' ? '#202124' : '#ffffff';
            canvas._chart.data.datasets[0].hoverOffset = isEmpty ? 0 : 5;
            canvas._chart.$dbCenter = { empty: isEmpty, totalText: totalText };
            canvas._chart.update('none');
            return canvas._chart;
        }
        if (canvas._chart) canvas._chart.destroy();
        canvas._chart = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderColor: theme() === 'dark' ? '#202124' : '#ffffff',
                    borderWidth: 3,
                    hoverOffset: isEmpty ? 0 : 5
                }]
            },
            options: chartOptions(isEmpty),
            plugins: [centerPlugin]
        });
        canvas._chart.$dbCenter = { empty: isEmpty, totalText: totalText };
        return canvas._chart;
    };

    var buildDonut = function(stats) {
        var legend = (stats && stats.legend) ? stats.legend : [];
        var series = [];
        var labels = [];
        var colors = [];
        legend.forEach(function(item) {
            labels.push(String(item.label || ''));
            series.push(Number(item.value || 0));
            colors.push(String(item.color || '#94a3b8'));
        });
        return { series: series, labels: labels, colors: colors };
    };

    var packChartData = function(payload) {
        var out = {
            sport: { donut: buildDonut(payload.sportStats || {}), datasets: null },
            casino: { donut: buildDonut(payload.casinoStats || {}), datasets: {} },
            bonus: { donut: buildDonut(payload.bonusStats || {}), datasets: null }
        };
        var casinoDatasets = (payload.casinoStats && payload.casinoStats.datasets) ? payload.casinoStats.datasets : {};
        Object.keys(casinoDatasets).forEach(function(tab) {
            out.casino.datasets[tab] = { donut: buildDonut(casinoDatasets[tab] || {}) };
        });
        return out;
    };

    var data = packChartData({
        sportStats: <?= json_encode($sportStats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        casinoStats: <?= json_encode($casinoStats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        bonusStats: <?= json_encode($bonusStats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    });
    var ssr = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    if (ssr && ssr.casino && ssr.casino.datasets) {
        data.casino.datasets = ssr.casino.datasets;
        data.casino.donut = ssr.casino.donut;
    }
    if (ssr && ssr.sport) data.sport.donut = ssr.sport.donut;
    if (ssr && ssr.bonus) data.bonus.donut = ssr.bonus.donut;

    document.querySelectorAll('[data-chart-panel]').forEach(function(panel) {
        panel.querySelectorAll('[data-tab]').forEach(function(button) {
            button.addEventListener('click', function() {
                var key = String(panel.getAttribute('data-chart-panel') || '');
                var tab = String(button.getAttribute('data-tab') || '');
                var dataset = data[key] && data[key].datasets ? data[key].datasets[tab] : null;
                if (!dataset || !dataset.donut) return;
                panel.querySelectorAll('[data-tab]').forEach(function(item) { item.classList.toggle('on', item === button); });
                data[key].activeTab = tab;
                renderChart('db-' + key + '-donut', dataset.donut.series, dataset.donut.labels, dataset.donut.colors, true);
            });
        });
    });

    var initCharts = function() {
        ['sport', 'casino', 'bonus'].forEach(function(key) {
            var d = data[key];
            if (!d || !d.donut) return;
            renderChart('db-' + key + '-donut', d.donut.series, d.donut.labels, d.donut.colors, false);
        });
    };

    var setLiveStatus = function(ok, ageText) {
        var pill = document.getElementById('db-live-pill');
        var age = document.getElementById('db-live-age');
        if (age) age.textContent = ageText || 'şimdi';
        if (!pill) return;
        pill.classList.toggle('live', !!ok);
        pill.classList.toggle('warn', !ok);
    };

    var tickAge = function() {
        var sec = Math.max(0, Math.round((Date.now() - lastFetchAt) / 1000));
        var text = sec < 3 ? 'şimdi' : (sec + 's önce');
        setLiveStatus(true, text);
    };

    var applyKpis = function(cards) {
        (cards || []).forEach(function(card) {
            var key = String(card.key || '');
            if (!key) return;
            var el = document.querySelector('[data-kpi-key="' + key + '"]');
            if (!el) return;
            var type = String(card.type || el.getAttribute('data-kpi-type') || 'number');
            var val = el.querySelector('[data-kpi-value]');
            var cnt = el.querySelector('[data-kpi-count]');
            if (val) val.textContent = formatKpi(card.value, type);
            if (cnt) {
                var countLabel = String(card.count_label || el.getAttribute('data-kpi-count-label') || 'Oyuncu');
                cnt.textContent = numberFmt(card.count) + ' ' + countLabel;
            }
        });
    };

    var applyOps = function(queue, openOps) {
        (queue || []).forEach(function(op) {
            var key = String(op.key || '');
            if (!key) return;
            var el = document.querySelector('[data-op-key="' + key + '"] [data-op-value]');
            if (el) el.textContent = String(Number(op.value || 0));
        });
        var openEl = document.getElementById('db-open-ops');
        if (openEl) openEl.textContent = numberFmt(openOps || 0);
        var opsPill = document.getElementById('db-ops-pill');
        if (opsPill) {
            var has = Number(openOps || 0) > 0;
            opsPill.classList.toggle('ops-alert', has);
            var dot = opsPill.querySelector('.db-dot');
            if (has && !dot) {
                var span = document.createElement('span');
                span.className = 'db-dot';
                span.setAttribute('aria-hidden', 'true');
                opsPill.insertBefore(span, opsPill.firstChild);
            } else if (!has && dot) {
                dot.remove();
            }
        }
    };

    var applyQuick = function(actions) {
        var map = {
            'Çekim Onayı': 'qa_withdraw',
            'KYC Kontrol': 'qa_kyc',
            'Yatırım Takibi': 'qa_deposit',
            'Bonus Talepleri': 'qa_bonus'
        };
        (actions || []).forEach(function(qa) {
            var key = map[String(qa.title || '')];
            if (!key) return;
            var el = document.querySelector('[data-qa-key="' + key + '"] [data-qa-count]');
            if (!el) return;
            var n = Number(qa.count || 0);
            el.textContent = numberFmt(n);
            el.classList.toggle('is-alert', n > 0);
        });
    };

    var applyTxTable = function(key, rows) {
        var table = document.querySelector('[data-tx-table="' + key + '"] tbody');
        if (!table) return;
        var list = Array.isArray(rows) ? rows : [];
        if (!list.length) {
            table.innerHTML = '<tr><td colspan="4"><div class="db-empty" style="margin:0">Henüz işlem yok.</div></td></tr>';
            return;
        }
        table.innerHTML = list.map(function(r) {
            var st = String(r.status || '').toLowerCase();
            var ok = ['confirmed','completed','approved','success'].indexOf(st) >= 0;
            return '<tr>'
                + '<td>' + String(r.created_at || '-') + '</td>'
                + '<td>' + String(r.username || '-') + '</td>'
                + '<td>' + moneyFmt(r.amount || 0) + '</td>'
                + '<td><span class="db-badge ' + (ok ? 'ok' : 'bad') + '">' + String(r.status || '-') + '</span></td>'
                + '</tr>';
        }).join('');
    };

    var escHtml = function(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    };

    var applyOnlineUsers = function(rows, total) {
        var countEl = document.getElementById('db-online-count');
        if (countEl) {
            countEl.textContent = numberFmt(total != null ? total : (Array.isArray(rows) ? rows.length : 0));
        }
        var table = document.querySelector('[data-online-table="1"] tbody');
        if (!table) return;
        var list = Array.isArray(rows) ? rows : [];
        if (!list.length) {
            table.innerHTML = '<tr><td colspan="4"><div class="db-empty" style="margin:0">Şu an çevrimiçi üye yok.</div></td></tr>';
            return;
        }
        table.innerHTML = list.map(function(r) {
            var name = escHtml(r.username || '-');
            var url = String(r.url || '').trim();
            var playerCell = url
                ? '<a class="db-link" href="' + escHtml(url) + '">' + name + '</a>'
                : name;
            return '<tr>'
                + '<td>' + playerCell + '</td>'
                + '<td>' + escHtml(r.last_seen_at || '-') + '</td>'
                + '<td>' + moneyFmt(r.balance || 0) + '</td>'
                + '<td>' + moneyFmt(r.bonus_balance || 0) + '</td>'
                + '</tr>';
        }).join('');
    };

    var applyChartsFromPayload = function(payload) {
        var packed = packChartData(payload);
        data.sport.donut = packed.sport.donut;
        data.bonus.donut = packed.bonus.donut;
        data.casino.datasets = packed.casino.datasets;
        var casinoPanel = document.querySelector('[data-chart-panel="casino"]');
        var activeBtn = casinoPanel ? casinoPanel.querySelector('.db-tab.on') : null;
        var activeTab = activeBtn ? activeBtn.getAttribute('data-tab') : null;
        if (activeTab && data.casino.datasets[activeTab]) {
            data.casino.donut = data.casino.datasets[activeTab].donut;
        } else {
            data.casino.donut = packed.casino.donut;
        }
        renderChart('db-sport-donut', data.sport.donut.series, data.sport.donut.labels, data.sport.donut.colors, true);
        renderChart('db-casino-donut', data.casino.donut.series, data.casino.donut.labels, data.casino.donut.colors, true);
        renderChart('db-bonus-donut', data.bonus.donut.series, data.bonus.donut.labels, data.bonus.donut.colors, true);
    };

    var applyPayload = function(payload) {
        if (!payload) return;
        applyKpis([].concat(payload.kpiCards || [], payload.affiliateCards || []));
        applyOps(payload.operationQueue || [], payload.openOperations || 0);
        applyQuick(payload.quickActions || []);
        applyTxTable('deposit', payload.depositRows || []);
        applyTxTable('withdraw', payload.withdrawRows || []);
        applyOnlineUsers(payload.onlineUserRows || [], payload.onlineUsers);
        applyChartsFromPayload(payload);
        lastFetchAt = Date.now();
        setLiveStatus(true, 'şimdi');
        if (shell && payload.generated_at) {
            shell.setAttribute('data-generated-at', String(payload.generated_at));
        }
    };

    var poll = function() {
        if (fetching || document.visibilityState === 'hidden' || !liveUrl) return;
        fetching = true;
        var qs = window.location.search || '';
        var url = liveUrl + qs;
        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function(json) {
                if (!json || !json.ok) throw new Error('bad payload');
                applyPayload(json.data || {});
            })
            .catch(function() {
                setLiveStatus(false, 'yeniden bağlanıyor');
            })
            .finally(function() {
                fetching = false;
            });
    };

    var startPoll = function() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(function() {
            tickAge();
            poll();
        }, POLL_MS);
        setInterval(tickAge, 1000);
    };

    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            poll();
        }
    });

    var boot = function(attempt) {
        if (typeof Chart === 'undefined') {
            if (attempt >= 50) {
                document.querySelectorAll('.db-chart-wrap').forEach(function(el) {
                    el.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--t-muted);font-size:12px;font-weight:700">Grafik yüklenemedi</div>';
                });
                startPoll();
                return;
            }
            setTimeout(function() { boot(attempt + 1); }, 100);
            return;
        }
        initCharts();
        startPoll();
        setTimeout(poll, 2500);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { boot(0); });
    } else {
        boot(0);
    }
})();
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function(){
    var points = <?= json_encode($visitorMapPoints, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var el = document.getElementById('db-visitor-map');
    if (!el || typeof L === 'undefined' || !Array.isArray(points) || !points.length) {
        return;
    }

    var isDark = (document.documentElement.getAttribute('data-theme') || 'light') === 'dark';
    var map = L.map(el, {
        scrollWheelZoom: false,
        worldCopyJump: true,
        zoomControl: true,
        attributionControl: true
    }).setView([20, 0], 2);

    L.tileLayer(isDark
        ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
        : 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap &copy; CARTO',
        subdomains: 'abcd'
    }).addTo(map);

    var bounds = [];
    points.forEach(function(p) {
        var lat = Number(p.lat || 0);
        var lon = Number(p.lon || 0);
        if (!isFinite(lat) || !isFinite(lon)) return;
        var isCountry = p.type === 'country';
        var visitors = Math.max(1, Number(p.visitors || 1));
        var marker = L.circleMarker([lat, lon], {
            radius: isCountry ? Math.min(22, 8 + Math.log10(visitors) * 7) : 5,
            color: isCountry ? '#1d4ed8' : '#0891b2',
            weight: 1.5,
            fillColor: isCountry ? '#3b82f6' : '#22d3ee',
            fillOpacity: 0.8
        }).addTo(map);

        var html = '<strong>' + String(p.label || 'Nokta') + '</strong>';
        if (isCountry) {
            html += '<br>' + visitors.toLocaleString('tr-TR') + ' ziyaret';
        }
        if (p.country && !isCountry) {
            html += '<br>' + String(p.country);
        }
        if (p.ip) {
            html += '<br>' + String(p.ip);
        }
        if (p.at) {
            html += '<br>' + String(p.at);
        }
        marker.bindPopup(html);
        bounds.push([lat, lon]);
    });

    if (bounds.length) {
        map.fitBounds(bounds, { padding: [32, 32], maxZoom: 5 });
    }
    setTimeout(function(){ map.invalidateSize(); }, 180);
})();
</script>

<?php if ($showFlashModal): ?>
<script>
(function(){ var msg=<?= json_encode($flash, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>; if(msg&&typeof Toastify!=='undefined'){Toastify({text:msg,duration:5000,close:true,gravity:'top',position:'right',style:{background:'var(--success)'}}).showToast();} })();
</script>
<?php endif; ?>
