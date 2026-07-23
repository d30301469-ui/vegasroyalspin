<?php

$countryData = is_array($countryData ?? null) ? $countryData : [];
$recentVisitors = is_array($recentVisitors ?? null) ? $recentVisitors : [];
$totalVisitors = (int) ($totalVisitors ?? 0);
$uniqueCountries = (int) ($uniqueCountries ?? 0);
$number = static fn ($value): string => number_format((float) $value, 0, ',', '.');
$maxVisitors = 0;
foreach ($countryData as $c) {
    $maxVisitors = max($maxVisitors, (int) ($c['visitors'] ?? 0));
}
?>
<style>
    .geomap-grid { display:grid; grid-template-columns:minmax(0,1fr) 380px; gap:16px; }
    .geomap-card { background:var(--bg-card); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow-card); padding:16px; min-width:0; }
    .geomap-card-head { align-items:center; display:flex; justify-content:space-between; margin-bottom:14px; }
    .geomap-card-title { color:var(--t-base); font-size:14px; font-weight:900; margin:0; }
    .geomap-kpi-row { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:16px; }
    .geomap-kpi { background:var(--bg-muted); border-radius:10px; padding:12px 14px; text-align:center; }
    .geomap-kpi-value { color:var(--t-base); font-family:'Inter Tight',Inter,sans-serif; font-size:24px; font-weight:900; letter-spacing:-.03em; }
    .geomap-kpi-label { color:var(--t-muted); font-size:10px; font-weight:800; margin-top:4px; text-transform:uppercase; }
    .geomap-bars { display:flex; flex-direction:column; gap:7px; max-height:520px; overflow-y:auto; padding-right:4px; }
    .geomap-bar-row { align-items:center; display:grid; gap:10px; grid-template-columns:24px minmax(0,1fr) 70px 52px; }
    .geomap-bar-flag { font-size:16px; line-height:1; text-align:center; }
    .geomap-bar-label { color:var(--t-muted); font-size:12px; font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .geomap-bar-track { background:var(--bg-muted); border-radius:4px; height:10px; overflow:hidden; }
    .geomap-bar-fill { background:linear-gradient(90deg,var(--primary),#818cf8); border-radius:4px; height:100%; transition:width .4s; }
    .geomap-bar-value { color:var(--t-base); font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:700; text-align:right; }
    .geomap-bar-pct { color:var(--t-muted); font-size:10px; text-align:right; }
    .geomap-list { display:flex; flex-direction:column; gap:6px; max-height:560px; overflow-y:auto; }
    .geomap-list-item { background:var(--bg-muted); border-radius:8px; padding:10px 12px; }
    .geomap-list-loc { color:var(--t-base); font-size:12px; font-weight:800; }
    .geomap-list-meta { color:var(--t-muted); font-size:10px; margin-top:3px; }
    .geomap-empty { color:var(--t-muted); font-size:13px; padding:32px 20px; text-align:center; }
    @media (max-width:1000px) { .geomap-grid { grid-template-columns:1fr; } }
</style>

<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Raporlar · Coğrafi</span>
        <h1 class="hero-title">Oyuncu <span class="accent">Haritası</span></h1>
        <p class="hero-sub">Ziyaretçi IP/GeoIP verilerinden ülke ve şehir bazlı dağılım. Visitor log tablosundan canlı okunur.</p>
    </div>
</div>

<div class="geomap-kpi-row">
    <div class="geomap-kpi">
        <div class="geomap-kpi-value"><?= htmlspecialchars($number($totalVisitors), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="geomap-kpi-label">Toplam Ziyaret</div>
    </div>
    <div class="geomap-kpi">
        <div class="geomap-kpi-value"><?= htmlspecialchars($number($uniqueCountries), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="geomap-kpi-label">Ülke</div>
    </div>
    <div class="geomap-kpi">
        <div class="geomap-kpi-value"><?= htmlspecialchars($number(count($countryData)), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="geomap-kpi-label">Aktif Ülke</div>
    </div>
</div>

<div class="geomap-grid">
    <div class="geomap-card">
        <div class="geomap-card-head">
            <h2 class="geomap-card-title">Ülkelere Göre Ziyaretçi Dağılımı</h2>
            <span style="color:var(--t-muted);font-size:11px"><?= count($countryData) ?> ülke</span>
        </div>
        <?php if (empty($countryData)): ?>
            <div class="geomap-empty">Henüz ziyaretçi kaydı yok. Siteye giriş yapıldıkça bu panel dolacaktır.</div>
        <?php else: ?>
        <div class="geomap-bars">
            <?php foreach ($countryData as $c): ?>
                <?php $v = (int) ($c['visitors'] ?? 0); $pct = $maxVisitors > 0 ? round(($v / $maxVisitors) * 100, 1) : 0; ?>
                <div class="geomap-bar-row">
                    <div class="geomap-bar-flag"><?= htmlspecialchars((string) ($c['country_code'] ?? '🌍'), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="geomap-bar-label"><?= htmlspecialchars((string) ($c['country_name'] ?? 'Bilinmiyor'), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="geomap-bar-track">
                        <div class="geomap-bar-fill" style="width:<?= $pct ?>%"></div>
                    </div>
                    <div class="geomap-bar-value"><?= htmlspecialchars($number($v), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="geomap-card">
        <div class="geomap-card-head">
            <h2 class="geomap-card-title">Son Ziyaretçiler</h2>
            <span style="color:var(--t-muted);font-size:11px">son 100</span>
        </div>
        <?php if (empty($recentVisitors)): ?>
            <div class="geomap-empty">Henüz ziyaretçi kaydı yok.</div>
        <?php else: ?>
        <div class="geomap-list">
            <?php foreach ($recentVisitors as $v): ?>
                <div class="geomap-list-item">
                    <div class="geomap-list-loc">
                        <?= htmlspecialchars(trim((string) ($v['city'] ?? '') . ($v['city'] && $v['region'] ? ', ' : '') . (string) ($v['region'] ?? '')), ENT_QUOTES, 'UTF-8') ?: htmlspecialchars((string) ($v['country_name'] ?? 'Bilinmiyor'), ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div class="geomap-list-meta">
                        <?= htmlspecialchars((string) ($v['country_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($v['ip_address'] ?? '-'), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(date('d.m H:i', strtotime((string) ($v['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</section>
            </div>
        </div>
    </section>
    <section class="col-4 card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Top locations</span>
                <h2 class="card-title">Visitor logs</h2>
            </div>
        </div>
        <table class="table">
            <thead><tr><th>Konum</th><th style="text-align:right">Ziyaret</th></tr></thead>
            <tbody>
            <?php foreach ($locations as $location): ?>
                <tr>
                    <td class="cell-name"><?= htmlspecialchars((string) ($location['country_name'] ?? '') . ' · ' . (string) ($location['city'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="cell-price pos"><?= (int) ($location['total'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>
