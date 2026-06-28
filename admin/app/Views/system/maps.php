<?php

$locations = is_array($locations ?? null) ? $locations : [];
$mode = (string) ($mode ?? 'vector');
?>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Sistem · Haritalar</span>
        <h1 class="hero-title"><?= $mode === 'google' ? 'Google' : 'Vector' ?> <span class="accent">harita</span></h1>
        <p class="hero-sub">Tema map ekranı `visitor_logs` coğrafi verisine bağlandı. Harita kartı ve lokasyon listesi admin panel içinde tek yapıdan çalışır.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/google-maps'), ENT_QUOTES, 'UTF-8') ?>">Google Maps</a>
        <a class="btn btn--primary" href="<?= htmlspecialchars(AdminAuth::url('/vector-maps'), ENT_QUOTES, 'UTF-8') ?>">Vector Maps</a>
    </div>
</section>

<div class="grid">
    <section class="col-8 card" style="min-height:420px">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Geography</span>
                <h2 class="card-title">Visitor map</h2>
            </div>
            <span class="card-action"><?= count($locations) ?> locations</span>
        </div>
        <div style="flex:1;min-height:320px;border:1px solid var(--border);border-radius:18px;background:radial-gradient(circle at 25% 25%, rgba(99,102,241,.22), transparent 30%), radial-gradient(circle at 75% 55%, rgba(16,185,129,.18), transparent 28%), var(--bg-muted);display:grid;place-items:center;color:var(--t-muted);text-align:center;padding:24px">
            <div>
                <div style="font-family:'Inter Tight',sans-serif;font-size:34px;font-weight:800;color:var(--t-base);letter-spacing:-.04em"><?= htmlspecialchars(strtoupper($mode), ENT_QUOTES, 'UTF-8') ?></div>
                <div>Map provider placeholder · visitor_logs lat/lon data ready</div>
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
