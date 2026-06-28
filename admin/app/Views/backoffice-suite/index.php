<?php

$modules = is_array($modules ?? null) ? $modules : [];
$summary = is_array($summary ?? null) ? $summary : [];
$liveMetrics = is_array($liveMetrics ?? null) ? $liveMetrics : [];
$referenceScreens = is_array($referenceScreens ?? null) ? $referenceScreens : [];
$number = static fn ($value): string => number_format((float) $value, 0, ',', '.');
$statusClass = static fn (string $status): string => match ($status) {
    'ready' => 'ok',
    'partial' => 'warn',
    default => 'plan',
};
?>
<style>
    .suite-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; margin-bottom:20px; }
    .suite-layout { display:grid; grid-template-columns:minmax(0,1fr) 360px; gap:20px; align-items:start; }
    .suite-module-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
    .suite-card { background:var(--bg-card); border:1px solid var(--border); border-radius:16px; box-shadow:var(--shadow-card); padding:18px; min-width:0; }
    .suite-card-head { align-items:flex-start; display:flex; gap:12px; justify-content:space-between; margin-bottom:12px; }
    .suite-title { color:var(--t-base); font-family:'Inter Tight',Inter,sans-serif; font-size:18px; font-weight:800; letter-spacing:-.025em; line-height:1.15; margin:0; }
    .suite-copy { color:var(--t-muted); font-size:13px; line-height:1.55; margin:0 0 14px; }
    .suite-meta { display:grid; gap:8px; margin-top:14px; }
    .suite-meta-row { align-items:flex-start; display:grid; gap:8px; grid-template-columns:96px 1fr; }
    .suite-meta-label { color:var(--t-light); font-size:10px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
    .suite-meta-value { color:var(--t-base); font-size:12px; line-height:1.4; overflow-wrap:anywhere; }
    .suite-badge { border-radius:999px; font-size:10px; font-weight:900; letter-spacing:.06em; padding:5px 9px; text-transform:uppercase; white-space:nowrap; }
    .suite-badge.ok { background:var(--success-soft); color:var(--success); }
    .suite-badge.warn { background:var(--warning-soft); color:var(--warning); }
    .suite-badge.plan { background:var(--purple-soft); color:var(--purple); }
    .suite-kpi { background:var(--bg-card); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow-card); padding:16px; }
    .suite-kpi span { color:var(--t-light); display:block; font-size:10px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
    .suite-kpi strong { color:var(--t-base); display:block; font-family:'Inter Tight',Inter,sans-serif; font-size:30px; font-weight:800; letter-spacing:-.04em; line-height:1; margin-top:8px; }
    .suite-side { display:grid; gap:20px; position:sticky; top:20px; }
    .suite-list { display:grid; gap:10px; }
    .suite-list-item { border:1px solid var(--border-soft); border-radius:12px; padding:12px; }
    .suite-list-name { color:var(--t-base); font-size:13px; font-weight:800; }
    .suite-list-meta { color:var(--t-muted); font-size:12px; margin-top:4px; overflow-wrap:anywhere; }
    .suite-actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:14px; }
    @media (max-width:1280px) {
        .suite-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .suite-layout { grid-template-columns:1fr; }
        .suite-side { position:static; }
    }
    @media (max-width:760px) {
        .suite-grid, .suite-module-grid { grid-template-columns:1fr; }
        .suite-meta-row { grid-template-columns:1fr; }
    }
</style>

<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Nexthub modül haritası</span>
        <h1 class="hero-title">Backoffice <span class="accent">Suite</span></h1>
        <p class="hero-sub">Nexthub backoffice ekranları, mevcut admin temasındaki gerçek modüllerle eşleştirildi. Bu sayfa kopya görsel kullanmadan yapı, kapsam ve eksik modülleri takip etmek için kuruldu.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--primary" href="<?= htmlspecialchars(AdminAuth::url('/dashboard'), ENT_QUOTES, 'UTF-8') ?>">Canlı dashboard</a>
        <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/module?key=users'), ENT_QUOTES, 'UTF-8') ?>">Üye yönetimi</a>
    </div>
</div>

<section class="suite-grid" aria-label="Backoffice suite özetleri">
    <?php foreach ($summary as $item): ?>
        <article class="suite-kpi">
            <span><?= htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            <strong><?= htmlspecialchars($number($item['value'] ?? 0), ENT_QUOTES, 'UTF-8') ?></strong>
        </article>
    <?php endforeach; ?>
</section>

<section class="suite-layout">
    <div class="suite-module-grid">
        <?php foreach ($modules as $module): ?>
            <?php
            $status = (string) ($module['status'] ?? 'planned');
            $route = (string) ($module['localRoute'] ?? '/dashboard');
            ?>
            <article class="suite-card">
                <div class="suite-card-head">
                    <h2 class="suite-title"><?= htmlspecialchars((string) ($module['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                    <span class="suite-badge <?= htmlspecialchars($statusClass($status), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($module['statusText'] ?? 'Planlandı'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <p class="suite-copy"><?= htmlspecialchars((string) ($module['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <div class="suite-meta">
                    <div class="suite-meta-row">
                        <div class="suite-meta-label">Referans</div>
                        <div class="suite-meta-value"><?= htmlspecialchars((string) ($module['reference'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="suite-meta-row">
                        <div class="suite-meta-label">Bizde</div>
                        <div class="suite-meta-value"><a class="card-action" href="<?= htmlspecialchars(AdminAuth::url($route), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($module['localLabel'] ?? 'Modülü aç'), ENT_QUOTES, 'UTF-8') ?></a></div>
                    </div>
                    <div class="suite-meta-row">
                        <div class="suite-meta-label">Sıradaki iş</div>
                        <div class="suite-meta-value"><?= htmlspecialchars((string) ($module['nextStep'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <aside class="suite-side">
        <article class="card admin-compact-card">
            <div class="card-head">
                <div class="card-title-wrap">
                    <span class="eyebrow">Canlı veri</span>
                    <h2 class="card-title">Mevcut panel sinyali</h2>
                </div>
            </div>
            <div class="suite-list">
                <?php foreach ($liveMetrics as $metric): ?>
                    <div class="suite-list-item">
                        <div class="suite-list-name"><?= htmlspecialchars((string) ($metric['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="suite-list-meta"><?= htmlspecialchars($number($metric['value'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>

        <article class="card admin-compact-card">
            <div class="card-head">
                <div class="card-title-wrap">
                    <span class="eyebrow">Çıkarılan görseller</span>
                    <h2 class="card-title">Referans ekran listesi</h2>
                </div>
            </div>
            <div class="suite-list">
                <?php foreach ($referenceScreens as $screen): ?>
                    <div class="suite-list-item">
                        <div class="suite-list-name"><?= htmlspecialchars((string) ($screen['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="suite-list-meta"><?= htmlspecialchars((string) ($screen['area'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($screen['file'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="suite-actions">
                <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/dashboard'), ENT_QUOTES, 'UTF-8') ?>">Paneli aç</a>
            </div>
        </article>
    </aside>
</section>
</section>
