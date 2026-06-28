<?php

$modules = is_array($modules ?? null) ? $modules : [];
?>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Sistem · Arayüz</span>
        <h1 class="hero-title">UI <span class="accent">bileşenleri</span></h1>
        <p class="hero-sub">Admin panelinde kullanılan alert, badge, progress, tabs, accordion ve modal kalıpları.</p>
    </div>
</section>
<div class="grid">
    <section class="col-12 card"><div class="card-head"><div class="card-title-wrap"><span class="eyebrow">Feedback</span><h2 class="card-title">Alerts</h2></div></div><div class="alert primary"><span class="ico"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg></span><div class="body"><div class="title">Admin UI</div>Tüm modüller tek yönetim paneli altında çalışır.</div></div><div class="alert success"><span class="ico"><svg viewBox="0 0 24 24"><path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><path d="m22 4-10 10-3-3"/></svg></span><div class="body"><div class="title">DB bağlı</div><?= htmlspecialchars((string) ($site['site_name'] ?? 'Site'), ENT_QUOTES, 'UTF-8') ?> veritabanı modülleri aktif.</div></div></section>
    <section class="col-6 card"><div class="card-head"><div class="card-title-wrap"><span class="eyebrow">Indicators</span><h2 class="card-title">Badges</h2></div></div><div class="demo-row"><span class="badge">Default</span><span class="badge primary">Primary</span><span class="badge success">Success</span><span class="badge warning">Warning</span><span class="badge danger">Danger</span><span class="badge info">Info</span><span class="badge purple">Purple</span><span class="badge solid">Solid</span></div></section>
    <section class="col-6 card"><div class="card-head"><div class="card-title-wrap"><span class="eyebrow">Identity</span><h2 class="card-title">Avatar group</h2></div></div><div class="demo-row"><div class="avatar-group"><?php foreach (array_slice($modules, 0, 5) as $module): ?><span class="av ma-<?= rand(1, 6) ?>"><?= htmlspecialchars(strtoupper(substr((string) ($module['title'] ?? 'M'), 0, 2)), ENT_QUOTES, 'UTF-8') ?></span><?php endforeach; ?><span class="av more">+<?= max(0, count($modules) - 5) ?></span></div></div></section>
    <section class="col-6 card"><div class="card-head"><div class="card-title-wrap"><span class="eyebrow">Status</span><h2 class="card-title">Progress bars</h2></div></div><div style="display:flex;flex-direction:column;gap:14px"><div><div style="display:flex;justify-content:space-between;font-size:11.5px;color:var(--t-muted);margin-bottom:6px"><span>Module completion</span><strong style="color:var(--t-base);font-family:'JetBrains Mono',monospace">100%</strong></div><div class="progress"><div class="progress-fill gradient" style="width:100%"></div></div></div></div></section>
    <section class="col-6 card"><div class="card-head"><div class="card-title-wrap"><span class="eyebrow">Navigation</span><h2 class="card-title">Tabs</h2></div></div><div class="tabs"><?php foreach (array_slice($modules, 0, 4, true) as $key => $module): ?><a class="tab <?= $key === array_key_first($modules) ? 'is-active' : '' ?>" href="<?= htmlspecialchars(AdminAuth::url('/module?key=' . rawurlencode((string) $key)), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($module['title'] ?? $key), ENT_QUOTES, 'UTF-8') ?></a><?php endforeach; ?></div></section>
</div>
