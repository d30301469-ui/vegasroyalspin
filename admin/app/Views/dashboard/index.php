<?php

$money = static fn ($value): string => '₺' . number_format((float) $value, 2, ',', '.');
$number = static fn ($value): string => number_format((float) $value, 0, ',', '.');
$shortMoney = static fn ($value): string => '₺' . number_format((float) $value, 2, ',', '.');
$kpiCards = isset($kpiCards) && is_array($kpiCards) ? $kpiCards : [];
$sportStats = isset($sportStats) && is_array($sportStats) ? $sportStats : [];
$casinoStats = isset($casinoStats) && is_array($casinoStats) ? $casinoStats : [];
$bonusStats = isset($bonusStats) && is_array($bonusStats) ? $bonusStats : [];
$depositRows = isset($depositRows) && is_array($depositRows) ? $depositRows : [];
$withdrawRows = isset($withdrawRows) && is_array($withdrawRows) ? $withdrawRows : [];
$selectedPeriod = (string) ($selectedPeriod ?? 'month');
$dateFrom = (string) ($dateFrom ?? date('Y-m-01'));
$dateTo = (string) ($dateTo ?? date('Y-m-d'));
$flash = (string) ($flash ?? '');
$periodUrl = static fn (string $period): string => AdminAuth::url('/dashboard?period=' . rawurlencode($period));
$formatKpiValue = static function (array $card) use ($money, $number): string {
    return ($card['type'] ?? 'number') === 'money'
        ? $money((float) ($card['value'] ?? 0))
        : $number((float) ($card['value'] ?? 0));
};
$formatStatValue = static function (float $value, string $format) use ($shortMoney, $number): string {
    return match ($format) {
        'number' => $number($value),
        'percent' => number_format($value, 2, ',', '.') . '%',
        default => $shortMoney($value),
    };
};
$kpiIcon = static fn (string $icon): string => match ($icon) {
    'deposit' => '<rect x="4" y="6" width="16" height="12" rx="3"/><path d="M8 10h8M9 14h3"/><path d="M17 8.5v-2a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v2"/>',
    'withdraw' => '<path d="M12 3 5 6v6c0 4.4 2.8 7.2 7 9 4.2-1.8 7-4.6 7-9V6l-7-3z"/><path d="M12 8v6"/><path d="m9.5 11.5 2.5 2.5 2.5-2.5"/>',
    'adjust-up' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M2 12h3M19 12h3M4.9 19.1 7 17M17 7l2.1-2.1"/><path d="M12 15V9M9.5 11.5 12 9l2.5 2.5"/>',
    'adjust-down' => '<path d="M4 7h11a5 5 0 1 1 0 10H9"/><path d="m7 11-3-4 3-4"/><path d="M12 10v6M9.5 13.5 12 16l2.5-2.5"/>',
    'players' => '<path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M21 21v-2a3.5 3.5 0 0 0-3-3.46"/><path d="M16.5 3.6a4 4 0 0 1 0 6.8"/>',
    'new-players' => '<rect x="5" y="4" width="14" height="16" rx="3"/><path d="M9 9h6M9 13h3"/><path d="M17 17h4M19 15v4"/>',
    'login-users' => '<circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/><path d="M16 13h5M19 10l3 3-3 3"/>',
    'active-players' => '<circle cx="9" cy="8" r="4"/><path d="M3 21a6 6 0 0 1 12 0"/><path d="m16 11 2 2 4-5"/>',
    'wallet' => '<path d="M4 7h14a3 3 0 0 1 3 3v7a3 3 0 0 1-3 3H4a2 2 0 0 1-2-2V6a2 2 0 0 0 2 1z"/><path d="M4 7V5a2 2 0 0 1 2-2h11"/><circle cx="17" cy="14" r="1.2"/>',
    'bonus' => '<rect x="3" y="8" width="18" height="12" rx="2"/><path d="M12 8v12M3 12h18"/><path d="M12 8H8.5a2.5 2.5 0 1 1 0-5C11 3 12 8 12 8z"/><path d="M12 8h3.5a2.5 2.5 0 1 0 0-5C13 3 12 8 12 8z"/>',
    default => '<path d="M12 20V10M18 20V4M6 20v-4"/>',
};
$chartPayload = [
    'sport' => $sportStats,
    'casino' => $casinoStats,
    'bonus' => $bonusStats,
];
$showFlashModal = $flash !== '';
?>
<style>
    .bw-dashboard { display:flex; flex-direction:column; gap:12px; }
    .bw-head { align-items:center; display:flex; gap:16px; justify-content:space-between; margin-bottom:4px; }
    .bw-title { color:var(--t-base); font-family:'Inter Tight',Inter,sans-serif; font-size:14px; font-weight:800; margin:0; }
    .bw-filters { align-items:center; display:flex; flex-wrap:wrap; gap:8px; }
    .bw-filter { background:var(--bg-card); border:1px solid var(--border); border-radius:7px; color:var(--t-muted); display:inline-flex; font-size:12px; font-weight:700; line-height:1.2; padding:7px 11px; }
    .bw-filter.is-active, .bw-filter-submit.is-active { background:var(--primary); border-color:var(--primary); color:#fff; }
    .bw-custom-filter { align-items:center; display:flex; gap:6px; }
    .bw-date-input { background:var(--bg-card); border:1px solid var(--border); border-radius:7px; color:var(--t-base); font-size:12px; font-weight:700; height:31px; padding:0 8px; }
    .bw-filter-submit { background:var(--primary); border:1px solid var(--primary); border-radius:7px; color:#fff; font-size:12px; font-weight:800; height:31px; padding:0 10px; }
    .bw-kpi-grid { display:grid; gap:10px; grid-template-columns:repeat(4,minmax(0,1fr)); }
    .bw-kpi { background:var(--bg-card); border:1px solid var(--border); border-radius:8px; box-shadow:var(--shadow-card); min-height:94px; padding:14px 16px; position:relative; }
    .bw-kpi.is-wide { grid-column:span 2; }
    .bw-kpi-top { align-items:center; display:flex; gap:9px; justify-content:space-between; margin-bottom:12px; }
    .bw-kpi-label { align-items:center; color:var(--t-muted); display:flex; font-size:12px; font-weight:800; gap:8px; min-width:0; }
    .bw-kpi-label span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .bw-kpi-icon { border-radius:7px; display:grid; flex:0 0 auto; height:28px; place-items:center; width:28px; }
    .bw-kpi-icon svg { fill:none; height:16px; stroke:currentColor; stroke-linecap:round; stroke-linejoin:round; stroke-width:1.85; width:16px; }
    .bw-kpi-icon.success { background:var(--success-soft); color:var(--success); }
    .bw-kpi-icon.danger { background:var(--danger-soft); color:var(--danger); }
    .bw-kpi-icon.primary { background:var(--primary-soft); color:var(--primary); }
    .bw-kpi-icon.warning { background:var(--warning-soft); color:var(--warning); }
    .bw-kpi-icon.purple { background:var(--purple-soft); color:var(--purple); }
    .bw-kpi-icon.info { background:var(--info-soft); color:var(--info); }
    .bw-kpi-refresh { color:var(--primary); opacity:.75; }
    .bw-kpi-refresh svg { fill:none; height:13px; stroke:currentColor; stroke-width:2; width:13px; }
    .bw-kpi-value { color:var(--t-base); font-family:'Inter Tight',Inter,sans-serif; font-size:26px; font-weight:900; letter-spacing:-.035em; line-height:1; }
    .bw-kpi-sub { color:var(--t-light); font-size:11px; font-weight:700; margin-top:8px; }
    .bw-chart-grid { display:grid; gap:10px; grid-template-columns:repeat(2,minmax(0,1fr)); }
    .bw-panel { background:var(--bg-card); border:1px solid var(--border); border-radius:8px; box-shadow:var(--shadow-card); min-width:0; padding:12px; }
    .bw-panel.is-full { grid-column:1 / -1; }
    .bw-panel-head { align-items:center; border-bottom:1px solid var(--border-soft); display:flex; gap:12px; justify-content:space-between; margin:0 -12px 12px; padding:0 12px 10px; }
    .bw-panel-title { color:var(--t-base); font-size:13px; font-weight:900; margin:0; }
    .bw-panel-tools { display:flex; gap:5px; }
    .bw-tool { background:var(--bg-muted); border:0; border-radius:5px; color:var(--t-muted); cursor:pointer; display:grid; height:24px; place-items:center; text-decoration:none; transition:.16s ease; width:24px; }
    .bw-tool:hover { background:var(--primary-soft); color:var(--primary); transform:translateY(-1px); }
    .bw-tool.is-active { background:var(--primary-soft); color:var(--primary); }
    .bw-tool svg { fill:none; height:13px; stroke:currentColor; stroke-width:2; width:13px; }
    .bw-panel.is-expanded { grid-column:1 / -1; }
    .bw-panel.is-expanded .bw-chart-body { grid-template-columns:230px minmax(0,1fr); }
    .bw-chart-body { display:grid; gap:16px; grid-template-columns:160px minmax(0,1fr); min-height:280px; }
    .bw-panel.is-full .bw-chart-body { grid-template-columns:210px minmax(0,1fr); }
    .bw-donut { align-self:center; min-height:180px; position:relative; }
    .bw-donut canvas { height:210px !important; width:100% !important; }
    .bw-panel.is-full .bw-donut canvas { height:230px !important; }
    .bw-bars { align-self:center; min-width:0; }
    .bw-tabs { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:14px; }
    .bw-tab { background:transparent; border:0; border-radius:5px; color:var(--t-muted); cursor:pointer; font-size:10px; font-weight:800; padding:5px 9px; transition:.16s ease; }
    .bw-tab:hover { background:var(--bg-hover); color:var(--t-base); }
    .bw-tab.is-active { background:var(--primary); color:#fff; }
    .bw-bar-row { align-items:center; display:grid; gap:8px; grid-template-columns:100px minmax(0,1fr) 76px; margin:8px 0; }
    .bw-bar-label { color:var(--t-muted); font-size:11px; text-align:right; }
    .bw-bar-track { background:var(--bg-muted); border-radius:4px; height:13px; overflow:hidden; }
    .bw-bar-fill { border-radius:4px; display:block; height:100%; min-width:2px; }
    .bw-bar-value { color:var(--t-muted); font-family:'JetBrains Mono',monospace; font-size:10px; }
    .bw-empty { background:var(--bg-muted); border:1px dashed var(--border); border-radius:8px; color:var(--t-muted); font-size:11px; font-weight:700; margin-bottom:12px; padding:10px 12px; }
    .bw-table-grid { display:grid; gap:10px; grid-template-columns:repeat(2,minmax(0,1fr)); }
    .bw-table { border-collapse:collapse; width:100%; }
    .bw-table th { border-bottom:1px solid var(--border); color:var(--t-light); font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:700; padding:7px 6px; text-align:left; }
    .bw-table td { border-bottom:1px solid var(--border-soft); color:var(--t-muted); font-size:11px; padding:7px 6px; }
    .bw-table tr:last-child td { border-bottom:0; }
    .bw-link { color:var(--primary); font-weight:800; }
    .bw-status-ok { color:var(--success); font-weight:800; }
    .bw-status-bad { color:var(--danger); font-weight:800; }
    .bw-cache-modal-backdrop {
        position: fixed;
        inset: 0;
        z-index: 140;
        display: grid;
        place-items: center;
        padding: 16px;
        background: rgba(15, 23, 42, .44);
        backdrop-filter: blur(6px);
    }
    .bw-cache-modal {
        width: min(360px, 100%);
        border: 1px solid var(--border);
        border-radius: 16px;
        background: var(--bg-card);
        box-shadow: 0 24px 70px rgba(0,0,0,.28);
        overflow: hidden;
    }
    .bw-cache-modal-head {
        align-items: center;
        display: flex;
        justify-content: space-between;
        gap: 10px;
        padding: 14px 16px 10px;
        border-bottom: 1px solid var(--border-soft);
    }
    .bw-cache-modal-title {
        margin: 0;
        color: var(--t-base);
        font-size: 14px;
        font-weight: 900;
    }
    .bw-cache-modal-close {
        align-items: center;
        appearance: none;
        background: var(--bg-muted);
        border: 1px solid var(--border);
        border-radius: 999px;
        color: var(--t-base);
        cursor: pointer;
        display: inline-flex;
        height: 30px;
        justify-content: center;
        width: 30px;
    }
    .bw-cache-modal-body {
        padding: 14px 16px 16px;
        color: var(--t-muted);
        font-size: 12px;
        font-weight: 700;
        line-height: 1.45;
    }
    .bw-cache-modal-actions {
        display: flex;
        justify-content: flex-end;
        padding: 0 16px 16px;
    }
    .bw-cache-modal-actions .bw-filter-submit {
        min-width: 96px;
    }
    @media (max-width:1280px) {
        .bw-kpi-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .bw-chart-grid, .bw-table-grid { grid-template-columns:1fr; }
    }
    @media (max-width:760px) {
        .bw-head { align-items:flex-start; flex-direction:column; }
        .bw-kpi-grid { grid-template-columns:1fr; }
        .bw-kpi.is-wide { grid-column:auto; }
        .bw-chart-body, .bw-panel.is-full .bw-chart-body { grid-template-columns:1fr; }
        .bw-bar-row { grid-template-columns:84px minmax(0,1fr) 64px; }
    }
</style>

<section class="bw-dashboard">
    <div class="bw-head">
        <h1 class="bw-title">Backoffice</h1>
        <div class="bw-filters" aria-label="Dashboard tarih filtreleri">
            <a class="bw-filter <?= $selectedPeriod === 'yesterday' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($periodUrl('yesterday'), ENT_QUOTES, 'UTF-8') ?>">Dün</a>
            <a class="bw-filter <?= $selectedPeriod === 'today' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($periodUrl('today'), ENT_QUOTES, 'UTF-8') ?>">Bugün</a>
            <a class="bw-filter <?= $selectedPeriod === 'week' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($periodUrl('week'), ENT_QUOTES, 'UTF-8') ?>">Bu Hafta</a>
            <a class="bw-filter <?= $selectedPeriod === 'month' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($periodUrl('month'), ENT_QUOTES, 'UTF-8') ?>">Bu Ay</a>
            <a class="bw-filter <?= $selectedPeriod === 'prev_month' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($periodUrl('prev_month'), ENT_QUOTES, 'UTF-8') ?>">Önceki Ay</a>
            <form class="bw-custom-filter" method="get" action="<?= htmlspecialchars(AdminAuth::url('/dashboard'), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="period" value="custom">
                <input class="bw-date-input admin-date-input" type="date" name="date_from" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>" aria-label="Başlangıç tarihi">
                <input class="bw-date-input admin-date-input" type="date" name="date_to" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>" aria-label="Bitiş tarihi">
                <button class="bw-filter-submit <?= $selectedPeriod === 'custom' ? 'is-active' : '' ?>" type="submit">Özel Tarih Belirle</button>
            </form>
            <form class="bw-custom-filter" method="post" action="<?= htmlspecialchars(AdminAuth::url('/dashboard/cache-purge'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Tüm CMS/API önbellekleri temizlensin mi?')">
                <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <button class="bw-filter-submit" type="submit" style="background:var(--danger);border-color:var(--danger)">Tüm Önbelleği Temizle</button>
            </form>
        </div>
    </div>

    <?php if ($showFlashModal): ?>
        <div class="bw-cache-modal-backdrop" data-cache-modal-backdrop>
            <div class="bw-cache-modal" role="dialog" aria-modal="true" aria-labelledby="cacheModalTitle">
                <div class="bw-cache-modal-head">
                    <h2 class="bw-cache-modal-title" id="cacheModalTitle">Temizlik tamamlandı</h2>
                    <button class="bw-cache-modal-close" type="button" data-cache-modal-close aria-label="Kapat">×</button>
                </div>
                <div class="bw-cache-modal-body"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="bw-cache-modal-actions">
                    <button class="bw-filter-submit" type="button" data-cache-modal-close>Tamam</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <section class="bw-kpi-grid" aria-label="Dashboard metrikleri">
        <?php foreach ($kpiCards as $card): ?>
            <?php
            $status = (string) ($card['status'] ?? 'primary');
            $icon = (string) ($card['icon'] ?? $status);
            ?>
            <article class="bw-kpi <?= !empty($card['wide']) ? 'is-wide' : '' ?>">
                <div class="bw-kpi-top">
                    <div class="bw-kpi-label">
                        <span class="bw-kpi-icon <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><?= $kpiIcon($icon) ?></svg></span>
                        <span><?= htmlspecialchars((string) ($card['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <span class="bw-kpi-refresh"><svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg></span>
                </div>
                <div class="bw-kpi-value"><?= htmlspecialchars($formatKpiValue($card), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="bw-kpi-sub"><?= htmlspecialchars($number($card['count'] ?? 0), ENT_QUOTES, 'UTF-8') ?> Oyuncu</div>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="bw-chart-grid">
        <?php foreach ([['key' => 'sport', 'title' => 'Spor İstatistikleri', 'data' => $sportStats], ['key' => 'casino', 'title' => 'Casino İstatistikleri', 'data' => $casinoStats]] as $panel): ?>
            <article class="bw-panel" data-dashboard-panel="<?= htmlspecialchars($panel['key'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="bw-panel-head">
                    <h2 class="bw-panel-title"><?= htmlspecialchars($panel['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <div class="bw-panel-tools">
                        <button class="bw-tool" type="button" data-dashboard-expand aria-label="Paneli genişlet"><svg viewBox="0 0 24 24"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg></button>
                        <button class="bw-tool is-active" type="button" data-dashboard-refresh aria-label="Verileri yenile"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 4v8l6 3"/></svg></button>
                        <a class="bw-tool" href="<?= htmlspecialchars(AdminAuth::url((string) ($panel['data']['module_url'] ?? '/dashboard')), ENT_QUOTES, 'UTF-8') ?>" aria-label="İlgili modülü aç"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></a>
                    </div>
                </div>
                <div class="bw-chart-body">
                    <div class="bw-donut"><canvas id="bw-<?= htmlspecialchars($panel['key'], ENT_QUOTES, 'UTF-8') ?>-donut"></canvas></div>
                    <div class="bw-bars">
                        <div class="bw-tabs">
                            <?php foreach (($panel['data']['tabs'] ?? []) as $index => $tab): ?>
                                <button class="bw-tab <?= (string) $tab === (string) ($panel['data']['active_tab'] ?? '') ? 'is-active' : '' ?>" type="button" data-dashboard-tab="<?= htmlspecialchars((string) $tab, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $tab, ENT_QUOTES, 'UTF-8') ?></button>
                            <?php endforeach; ?>
                        </div>
                        <?php if ((string) ($panel['data']['empty_message'] ?? '') !== ''): ?>
                            <div class="bw-empty"><?= htmlspecialchars((string) $panel['data']['empty_message'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <?php
                        $values = array_map('floatval', (array) ($panel['data']['values'] ?? []));
                        $max = max($values !== [] ? $values : [1]);
                        $colors = ['#3b82f6', '#22c55e', '#f59e0b', '#94a3b8', '#ef4444', '#eab308', '#06b6d4', '#f97316', '#8b5cf6'];
                        ?>
                        <?php foreach ((array) ($panel['data']['labels'] ?? []) as $index => $label): ?>
                            <?php $value = (float) ($values[$index] ?? 0); ?>
                            <div class="bw-bar-row" data-dashboard-row>
                                <div class="bw-bar-label"><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="bw-bar-track"><span class="bw-bar-fill" style="width:<?= $max > 0 ? max(1, min(100, ($value / $max) * 100)) : 1 ?>%;background:<?= htmlspecialchars($colors[$index % count($colors)], ENT_QUOTES, 'UTF-8') ?>"></span></div>
                                <div class="bw-bar-value"><?= htmlspecialchars($formatStatValue($value, (string) (($panel['data']['formats'] ?? [])[$index] ?? 'money')), ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>

        <article class="bw-panel is-full" data-dashboard-panel="bonus">
            <div class="bw-panel-head">
                <h2 class="bw-panel-title">Bonus İstatistikleri</h2>
                <div class="bw-panel-tools">
                    <button class="bw-tool" type="button" data-dashboard-expand aria-label="Paneli genişlet"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4z"/></svg></button>
                    <button class="bw-tool is-active" type="button" data-dashboard-refresh aria-label="Verileri yenile"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 4v8l6 3"/></svg></button>
                    <a class="bw-tool" href="<?= htmlspecialchars(AdminAuth::url((string) ($bonusStats['module_url'] ?? '/module?key=active-bonuses')), ENT_QUOTES, 'UTF-8') ?>" aria-label="İlgili modülü aç"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></a>
                </div>
            </div>
            <div class="bw-chart-body">
                <div class="bw-donut"><canvas id="bw-bonus-donut"></canvas></div>
                <div class="bw-bars">
                    <div class="bw-tabs">
                        <?php foreach (($bonusStats['tabs'] ?? []) as $index => $tab): ?>
                            <button class="bw-tab <?= (string) $tab === (string) ($bonusStats['active_tab'] ?? '') ? 'is-active' : '' ?>" type="button" data-dashboard-tab="<?= htmlspecialchars((string) $tab, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $tab, ENT_QUOTES, 'UTF-8') ?></button>
                        <?php endforeach; ?>
                    </div>
                    <?php if ((string) ($bonusStats['empty_message'] ?? '') !== ''): ?>
                        <div class="bw-empty"><?= htmlspecialchars((string) $bonusStats['empty_message'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <?php
                    $bonusValues = array_map('floatval', (array) ($bonusStats['values'] ?? []));
                    $bonusMax = max($bonusValues !== [] ? $bonusValues : [1]);
                    ?>
                    <?php foreach ((array) ($bonusStats['labels'] ?? []) as $index => $label): ?>
                        <?php $value = (float) ($bonusValues[$index] ?? 0); ?>
                        <div class="bw-bar-row" data-dashboard-row>
                            <div class="bw-bar-label"><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="bw-bar-track"><span class="bw-bar-fill" style="width:<?= $bonusMax > 0 ? max(1, min(100, ($value / $bonusMax) * 100)) : 1 ?>%;background:<?= htmlspecialchars(['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316'][$index % 7], ENT_QUOTES, 'UTF-8') ?>"></span></div>
                            <div class="bw-bar-value"><?= htmlspecialchars($formatStatValue($value, (string) (($bonusStats['formats'] ?? [])[$index] ?? 'money')), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </article>
    </section>

    <section class="bw-table-grid">
        <?php foreach ([['title' => 'Para Yatırma İşlemleri', 'rows' => $depositRows, 'url' => '/module?key=deposits'], ['title' => 'Para Çekme İşlemleri', 'rows' => $withdrawRows, 'url' => '/module?key=withdrawals']] as $table): ?>
            <article class="bw-panel">
                <div class="bw-panel-head">
                    <h2 class="bw-panel-title"><?= htmlspecialchars($table['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <a class="bw-link" href="<?= htmlspecialchars(AdminAuth::url($table['url']), ENT_QUOTES, 'UTF-8') ?>">Tümünü Gör</a>
                </div>
                <div class="table-scroll">
                    <table class="bw-table">
                        <thead>
                        <tr><th>Talep Tarihi</th><th>Sağlayıcı</th><th>Oyuncu</th><th>Soyadı</th><th>Miktar</th><th>Durum</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ((array) $table['rows'] as $row): ?>
                            <?php
                            $fullname = trim((string) ($row['fullname'] ?? ''));
                            $parts = array_values(array_filter(preg_split('/\s+/', $fullname) ?: [], static fn (string $p): bool => $p !== ''));
                            $firstName = $parts[0] ?? '-';
                            $lastName = $parts[1] ?? '';
                            $displayName = $firstName . ($lastName !== '' ? ' ' . $lastName : '');
                            $status = strtolower((string) ($row['status'] ?? ''));
                            ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($row['created_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['method'] ?? 'System'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="bw-link"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars($lastName !== '' ? $lastName : '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($shortMoney($row['amount'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="<?= in_array($status, ['confirmed', 'completed', 'success', 'tamamlandı', 'approved'], true) ? 'bw-status-ok' : 'bw-status-bad' ?>"><?= htmlspecialchars((string) ($row['status'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ((array) $table['rows'] === []): ?>
                            <tr><td colspan="6">Henüz işlem kaydı yok.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</section>

<script>
    window.__NH_DASHBOARD_CHARTS__ = <?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    (function () {
        var backdrop = document.querySelector('[data-cache-modal-backdrop]');
        if (!backdrop) {
            return;
        }

        var closeButtons = backdrop.querySelectorAll('[data-cache-modal-close]');
        var closeModal = function () {
            backdrop.remove();
            document.body.classList.remove('has-admin-modal');
        };

        document.body.classList.add('has-admin-modal');
        closeButtons.forEach(function (button) {
            button.addEventListener('click', closeModal);
        });
        backdrop.addEventListener('click', function (event) {
            if (event.target === backdrop) {
                closeModal();
            }
        });
        window.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        }, { once: true });
    })();
</script>
