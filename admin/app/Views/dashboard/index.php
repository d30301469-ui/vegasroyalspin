<?php

declare(strict_types=1);

$money    = static fn ($value): string => '₺' . number_format((float) $value, 2, ',', '.');
$number   = static fn ($value): string => number_format((float) $value, 0, ',', '.');
$shortMoney = static fn ($value): string => '₺' . number_format((float) $value, 2, ',', '.');

$cards         = isset($kpiCards) && is_array($kpiCards) ? $kpiCards : [];
$sportStats    = isset($sportStats) && is_array($sportStats) ? $sportStats : [];
$casinoStats   = isset($casinoStats) && is_array($casinoStats) ? $casinoStats : [];
$bonusStats    = isset($bonusStats) && is_array($bonusStats) ? $bonusStats : [];
$depositRows   = isset($depositRows) && is_array($depositRows) ? $depositRows : [];
$withdrawRows  = isset($withdrawRows) && is_array($withdrawRows) ? $withdrawRows : [];
$opQueue       = isset($operationQueue) && is_array($operationQueue) ? $operationQueue : [];
$contentSystem = isset($contentSystem) && is_array($contentSystem) ? $contentSystem : [];
$recentLogs    = isset($recentLogs) && is_array($recentLogs) ? $recentLogs : [];
$topCountries  = isset($topCountries) && is_array($topCountries) ? $topCountries : [];
$tasks         = isset($tasks) && is_array($tasks) ? $tasks : [];
$quickActions  = isset($quickActions) && is_array($quickActions) ? $quickActions : [];
$healthItems   = isset($healthItems) && is_array($healthItems) ? $healthItems : [];
$selectedPeriod = (string) ($selectedPeriod ?? 'month');
$dateFrom      = (string) ($dateFrom ?? date('Y-m-01'));
$dateTo        = (string) ($dateTo ?? date('Y-m-d'));
$flash         = (string) ($flash ?? '');

$periodUrl = static fn (string $period): string => AdminAuth::url('/dashboard?period=' . rawurlencode($period));

$formatKpi = static function (array $card) use ($money, $number): string {
    return ($card['type'] ?? 'number') === 'money'
        ? $money((float) ($card['value'] ?? 0))
        : $number((float) ($card['value'] ?? 0));
};

$chartColors = ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316', '#eab308', '#94a3b8', '#ec4899'];

$buildDonutSeries = static function (array $stats) use (&$chartColors): array {
    $series = [];
    $labels = (array) ($stats['labels'] ?? []);
    $values = array_map('floatval', (array) ($stats['values'] ?? []));
    $colors = (array) ($stats['donut_colors'] ?? $chartColors);
    foreach ($labels as $i => $label) {
        $series[] = (float) ($values[$i] ?? 0);
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

$formatStatValue = static function (float $value, string $format) use ($shortMoney, $number): string {
    return match ($format) {
        'number'  => $number($value),
        'percent' => number_format($value, 2, ',', '.') . '%',
        default   => $shortMoney($value),
    };
};

$kpiColorMap = [
    'success' => ['bg' => 'var(--success-soft)', 'fg' => 'var(--success)'],
    'danger'  => ['bg' => 'var(--danger-soft)',  'fg' => 'var(--danger)'],
    'primary' => ['bg' => 'var(--primary-soft)', 'fg' => 'var(--primary)'],
    'warning' => ['bg' => 'var(--warning-soft)', 'fg' => 'var(--warning)'],
    'purple'  => ['bg' => 'var(--purple-soft)',  'fg' => 'var(--purple)'],
    'info'    => ['bg' => 'var(--info-soft)',    'fg' => 'var(--info)'],
];

$opColors = ['primary' => '#3b82f6', 'danger' => '#ef4444', 'warning' => '#f59e0b', 'info' => '#06b6d4', 'purple' => '#8b5cf6', 'success' => '#22c55e'];

$showFlashModal = $flash !== '';
?>
<style>
.db-shell { display:flex; flex-direction:column; gap:12px; }
.db-head { align-items:center; display:flex; gap:14px; justify-content:space-between; margin-bottom:4px; flex-wrap:wrap; }
.db-title { color:var(--t-base); font-family:'Inter Tight',Inter,sans-serif; font-size:15px; font-weight:900; margin:0; white-space:nowrap; }
.db-filters { align-items:center; display:flex; flex-wrap:wrap; gap:6px; }
.db-filter { background:var(--bg-card); border:1px solid var(--border); border-radius:6px; color:var(--t-muted); cursor:pointer; display:inline-flex; font-size:11.5px; font-weight:700; line-height:1.2; padding:6px 10px; text-decoration:none; transition:.14s; }
.db-filter:hover { border-color:var(--primary); color:var(--t-base); }
.db-filter.is-on { background:var(--primary); border-color:var(--primary); color:#fff; }
.db-date { background:var(--bg-card); border:1px solid var(--border); border-radius:6px; color:var(--t-base); font-size:11.5px; font-weight:700; height:30px; padding:0 7px; }
.db-btn { background:var(--primary); border:1px solid var(--primary); border-radius:6px; color:#fff; cursor:pointer; font-size:11.5px; font-weight:800; height:30px; padding:0 10px; white-space:nowrap; }
.db-btn.danger { background:var(--danger); border-color:var(--danger); }
.db-kpi-grid { display:grid; gap:10px; grid-template-columns:repeat(4,minmax(0,1fr)); }
.db-kpi { background:var(--bg-card); border:1px solid var(--border); border-radius:10px; box-shadow:var(--shadow-card); padding:14px 15px; position:relative; }
.db-kpi.wide { grid-column:span 2; }
.db-kpi-top { align-items:center; display:flex; gap:8px; justify-content:space-between; margin-bottom:12px; }
.db-kpi-label { align-items:center; color:var(--t-muted); display:flex; font-size:11.5px; font-weight:800; gap:7px; }
.db-kpi-icon { border-radius:6px; display:grid; flex-shrink:0; height:26px; place-items:center; width:26px; }
.db-kpi-icon svg { fill:none; height:15px; stroke:currentColor; stroke-linecap:round; stroke-linejoin:round; stroke-width:1.8; width:15px; }
.db-kpi-val { color:var(--t-base); font-family:'Inter Tight',Inter,sans-serif; font-size:24px; font-weight:900; letter-spacing:-.03em; line-height:1; }
.db-kpi-sub { color:var(--t-light); font-size:10.5px; font-weight:700; margin-top:8px; }
.db-col2 { display:grid; gap:10px; grid-template-columns:repeat(2,minmax(0,1fr)); }
.db-col3 { display:grid; gap:10px; grid-template-columns:repeat(3,minmax(0,1fr)); }
.db-panel { background:var(--bg-card); border:1px solid var(--border); border-radius:10px; box-shadow:var(--shadow-card); padding:12px 14px; }
.db-panel.full { grid-column:1/-1; }
.db-panel-head { align-items:center; border-bottom:1px solid var(--border-soft); display:flex; gap:10px; justify-content:space-between; margin:0 -14px 12px; padding:0 14px 10px; }
.db-panel-title { color:var(--t-base); font-size:13px; font-weight:900; margin:0; }
.db-link { color:var(--primary); font-size:11px; font-weight:800; text-decoration:none; }
.db-link:hover { text-decoration:underline; }
.db-chart-wrap { min-height:220px; }
.db-chart-wrap.tall { min-height:260px; }
.db-empty { background:var(--bg-muted); border:1px dashed var(--border); border-radius:8px; color:var(--t-muted); font-size:11px; font-weight:700; margin-bottom:10px; padding:10px 12px; }
.db-tabs { display:flex; flex-wrap:wrap; gap:5px; margin-bottom:12px; }
.db-tab { background:transparent; border:0; border-radius:5px; color:var(--t-muted); cursor:pointer; font-size:10px; font-weight:800; padding:5px 8px; transition:.14s; }
.db-tab:hover { background:var(--bg-hover); color:var(--t-base); }
.db-tab.on { background:var(--primary); color:#fff; }
.db-bar { align-items:center; display:grid; gap:8px; grid-template-columns:100px minmax(0,1fr) 78px; margin:8px 0; }
.db-bar-label { color:var(--t-muted); font-size:11px; text-align:right; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.db-bar-track { background:var(--bg-muted); border-radius:3px; height:11px; overflow:hidden; }
.db-bar-fill { border-radius:3px; height:100%; min-width:2px; transition:width .3s; }
.db-bar-val { color:var(--t-muted); font-family:'JetBrains Mono',monospace; font-size:10px; }
.db-ops { display:flex; flex-direction:column; gap:6px; }
.db-op { align-items:center; background:var(--bg-muted); border-radius:8px; display:flex; gap:10px; padding:10px 12px; text-decoration:none; transition:.14s; }
.db-op:hover { background:var(--bg-hover); transform:translateY(-1px); }
.db-op-dot { border-radius:50%; flex-shrink:0; height:10px; width:10px; }
.db-op-label { color:var(--t-base); flex:1; font-size:12px; font-weight:700; }
.db-op-badge { background:var(--bg-card); border-radius:6px; color:var(--t-muted); font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:800; padding:3px 8px; }
.db-table { border-collapse:collapse; width:100%; }
.db-table th { border-bottom:1px solid var(--border); color:var(--t-light); font-size:9px; font-weight:700; padding:6px 7px; text-align:left; text-transform:uppercase; letter-spacing:.04em; }
.db-table td { border-bottom:1px solid var(--border-soft); color:var(--t-muted); font-size:11.5px; padding:7px; }
.db-table tr:last-child td { border-bottom:0; }
.db-ok { color:var(--success); font-weight:800; }
.db-bad { color:var(--danger); font-weight:800; }
.db-ck { align-items:center; display:flex; gap:8px; padding:6px 0; }
.db-ck-icon { align-items:center; background:var(--success-soft); border-radius:50%; color:var(--success); display:flex; flex-shrink:0; height:22px; justify-content:center; width:22px; }
.db-ck-icon.off { background:var(--danger-soft); color:var(--danger); }
.db-ck-label { color:var(--t-base); font-size:12px; font-weight:700; }
.db-ck-meta { color:var(--t-muted); font-size:10.5px; }
.db-log { border-bottom:1px solid var(--border-soft); padding:8px 0; }
.db-log:last-child { border-bottom:0; }
.db-log-who { color:var(--t-base); font-size:11.5px; font-weight:700; }
.db-log-what { color:var(--t-muted); font-size:10.5px; margin-top:2px; }
@media (max-width:1280px) { .db-kpi-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .db-col2,.db-col3 { grid-template-columns:1fr; } }
@media (max-width:760px) { .db-head { flex-direction:column; align-items:flex-start; } .db-kpi-grid { grid-template-columns:1fr; } .db-kpi.wide { grid-column:auto; } .db-bar { grid-template-columns:70px minmax(0,1fr) 60px; } }
</style>

<section class="db-shell">
    <div class="db-head">
        <h1 class="db-title">Gösterge Paneli</h1>
        <div class="db-filters">
            <?php foreach (['yesterday' => 'Dün', 'today' => 'Bugün', 'week' => 'Bu Hafta', 'month' => 'Bu Ay', 'prev_month' => 'Geçen Ay'] as $p => $l): ?>
                <a class="db-filter <?= $selectedPeriod === $p ? 'is-on' : '' ?>" href="<?= htmlspecialchars($periodUrl($p)) ?>"><?= $l ?></a>
            <?php endforeach; ?>
            <form method="get" action="<?= htmlspecialchars(AdminAuth::url('/dashboard')) ?>" style="display:flex;gap:5px;align-items:center">
                <input type="hidden" name="period" value="custom">
                <input class="db-date" type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" title="Başlangıç">
                <input class="db-date" type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" title="Bitiş">
                <button class="db-btn <?= $selectedPeriod === 'custom' ? 'is-on' : '' ?>" type="submit">Uygula</button>
            </form>
            <form method="post" action="<?= htmlspecialchars(AdminAuth::url('/dashboard/cache-purge')) ?>" onsubmit="return confirm('Tüm CMS/API önbellekleri temizlensin mi?')" style="display:inline">
                <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken()) ?>">
                <button class="db-btn danger" type="submit">Önbellek Temizle</button>
            </form>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="db-kpi-grid">
        <?php foreach ($cards as $card):
            $st = (string) ($card['status'] ?? 'primary');
            $c = $kpiColorMap[$st] ?? $kpiColorMap['primary'];
        ?>
        <div class="db-kpi <?= !empty($card['wide']) ? 'wide' : '' ?>">
            <div class="db-kpi-top">
                <span class="db-kpi-label">
                    <span class="db-kpi-icon" style="background:<?= $c['bg'] ?>;color:<?= $c['fg'] ?>">
                        <svg viewBox="0 0 24 24"><path d="M12 20V10M18 20V4M6 20v-4"/></svg>
                    </span>
                    <?= htmlspecialchars((string) ($card['label'] ?? '')) ?>
                </span>
            </div>
            <div class="db-kpi-val"><?= htmlspecialchars($formatKpi($card)) ?></div>
            <div class="db-kpi-sub"><?= htmlspecialchars($number($card['count'] ?? 0)) ?> kayıt</div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Charts Row: Sport + Casino -->
    <div class="db-col2">
        <?php foreach (['sport' => 'Spor', 'casino' => 'Casino'] as $key => $title): $d = $chartData[$key]; ?>
        <div class="db-panel" data-chart-panel="<?= $key ?>">
            <div class="db-panel-head">
                <h2 class="db-panel-title"><?= $title ?> İstatistikleri</h2>
                <a class="db-link" href="<?= htmlspecialchars(AdminAuth::url($d['module'])) ?>">Modül →</a>
            </div>
            <?php if ($d['empty']): ?><div class="db-empty"><?= htmlspecialchars($d['empty']) ?></div><?php endif; ?>
            <?php if (!empty($d['tabs'])): ?>
            <div class="db-tabs">
                <?php foreach ($d['tabs'] as $tab): ?>
                <button class="db-tab <?= $tab === $d['active'] ? 'on' : '' ?>" data-tab="<?= htmlspecialchars($tab) ?>"><?= htmlspecialchars($tab) ?></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="db-chart-wrap" id="db-<?= $key ?>-donut"></div>
            <?php if (!empty($d['bars']['labels'])): ?>
            <?php $maxVal = max(array_map('floatval', $d['bars']['values'])) ?: 1; ?>
            <?php foreach ($d['bars']['labels'] as $i => $label): $v = (float) ($d['bars']['values'][$i] ?? 0); $f = (string) ($d['bars']['formats'][$i] ?? 'money'); ?>
            <div class="db-bar">
                <span class="db-bar-label" title="<?= htmlspecialchars($label) ?>"><?= htmlspecialchars($label) ?></span>
                <div class="db-bar-track"><span class="db-bar-fill" style="width:<?= max(1, min(100, ($maxVal>0?($v/$maxVal)*100:1))) ?>%;background:<?= $chartColors[$i%10] ?>"></span></div>
                <span class="db-bar-val"><?= htmlspecialchars($formatStatValue($v, $f)) ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Bonus + Operations Row -->
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
                <button class="db-tab <?= $tab === $chartData['bonus']['active'] ? 'on' : '' ?>" data-tab="<?= htmlspecialchars($tab) ?>"><?= htmlspecialchars($tab) ?></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="db-chart-wrap tall" id="db-bonus-donut"></div>
        </div>

        <div class="db-panel">
            <div class="db-panel-head"><h2 class="db-panel-title">Operasyon Kuyruğu</h2></div>
            <div class="db-ops">
                <?php foreach ($opQueue as $op): $cls = (string) ($op['class'] ?? 'primary'); ?>
                <a class="db-op" href="<?= htmlspecialchars(AdminAuth::url((string) ($op['url'] ?? '#'))) ?>">
                    <span class="db-op-dot" style="background:<?= htmlspecialchars($opColors[$cls] ?? '#3b82f6') ?>"></span>
                    <span class="db-op-label"><?= htmlspecialchars((string) ($op['label'] ?? '')) ?></span>
                    <span class="db-op-badge"><?= (int) ($op['value'] ?? 0) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Health + Recent Logs -->
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

    <!-- Transaction Tables -->
    <div class="db-col2">
        <?php foreach ([['title'=>'Son Yatırımlar','rows'=>$depositRows,'url'=>'/module?key=deposits'],['title'=>'Son Çekimler','rows'=>$withdrawRows,'url'=>'/module?key=withdrawals']] as $tbl): ?>
        <div class="db-panel">
            <div class="db-panel-head">
                <h2 class="db-panel-title"><?= $tbl['title'] ?></h2>
                <a class="db-link" href="<?= htmlspecialchars(AdminAuth::url($tbl['url'])) ?>">Tümü →</a>
            </div>
            <div style="overflow-x:auto">
            <table class="db-table">
                <thead><tr><th>Tarih</th><th>Oyuncu</th><th>Miktar</th><th>Durum</th></tr></thead>
                <tbody>
                <?php foreach ((array) $tbl['rows'] as $r): $stTx = strtolower((string) ($r['status'] ?? '')); ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($r['created_at'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) ($r['fullname'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars($shortMoney($r['amount'] ?? 0)) ?></td>
                    <td><span class="<?= in_array($stTx,['confirmed','completed','approved','success'],true)?'db-ok':'db-bad' ?>"><?= htmlspecialchars((string) ($r['status'] ?? '-')) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($tbl['rows'])): ?><tr><td colspan="4" class="db-empty">Henüz işlem yok.</td></tr><?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<script>
(function(){
    var theme = function() {
        return document.documentElement.getAttribute('data-theme') || 'light';
    };

    var labelColor = function() {
        return theme() === 'dark' ? '#94a3b8' : '#64748b';
    };

    var textColor = function() {
        return theme() === 'dark' ? '#f1f5f9' : '#0f172a';
    };

    var apexOptions = function(series, labels, colors) {
        return {
            series: series,
            chart: {
                type: 'donut',
                height: 240,
                toolbar: { show: false },
                animations: { enabled: true, speed: 500 }
            },
            labels: labels,
            colors: colors,
            stroke: { width: 2, colors: [theme() === 'dark' ? '#1e293b' : '#ffffff'] },
            plotOptions: {
                pie: {
                    donut: {
                        size: '62%',
                        labels: {
                            show: true,
                            name: { show: true, fontSize: '12px', fontWeight: 700, color: labelColor() },
                            value: { show: true, fontSize: '18px', fontWeight: 900, color: textColor() },
                            total: { show: true, showAlways: true, fontSize: '13px', fontWeight: 800, label: 'Toplam', color: labelColor() }
                        }
                    }
                }
            },
            legend: { show: false },
            dataLabels: { enabled: false },
            tooltip: { theme: theme(), style: { fontSize: '12px' } },
            noData: { text: 'Veri yok', style: { color: labelColor(), fontSize: '14px' } }
        };
    };

    var renderChart = function(elId, series, labels, colors) {
        var el = document.getElementById(elId);
        if (!el) return;
        var total = series.reduce(function(a,b){ return a+b; }, 0);
        if (total === 0) {
            el.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;min-height:200px;color:var(--t-muted);font-size:12px;font-weight:700">Henüz veri yok</div>';
            return;
        }
        if (el._apex) { el._apex.destroy(); }
        el._apex = new ApexCharts(el, apexOptions(series, labels, colors));
        el._apex.render();
    };

    var data = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    var initCharts = function() {
        ['sport', 'casino', 'bonus'].forEach(function(key) {
            var d = data[key];
            if (!d) return;
            renderChart('db-' + key + '-donut', d.donut.series, d.donut.labels, d.donut.colors);
        });
    };

    var boot = function() {
        if (typeof ApexCharts === 'undefined') {
            setTimeout(boot, 100);
            return;
        }
        initCharts();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>

<?php if ($showFlashModal): ?>
<script>
(function(){ var msg=<?= json_encode($flash, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>; if(msg&&typeof Toastify!=='undefined'){Toastify({text:msg,duration:5000,close:true,gravity:'top',position:'right',style:{background:'var(--success)'}}).showToast();} })();
</script>
<?php endif; ?>
