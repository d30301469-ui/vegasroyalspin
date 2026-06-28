<?php

$events = is_array($events ?? null) ? $events : [];
$today = (int) date('j');
$monthName = date('F');
$year = date('Y');
$eventDays = [];
foreach ($events as $event) {
    $date = strtotime((string) ($event['starts_at'] ?? ''));
    if ($date !== false && date('Ym', $date) === date('Ym')) {
        $eventDays[(int) date('j', $date)] = true;
    }
}
?>
<section class="admin-surface">
<section class="hero cal-hero">
    <div class="hero-text">
        <span class="eyebrow" id="heroDate"><?= htmlspecialchars(date('l · F d · Y'), ENT_QUOTES, 'UTF-8') ?></span>
        <h1 class="hero-title"><?= htmlspecialchars($monthName, ENT_QUOTES, 'UTF-8') ?> <span class="accent"><?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?></span></h1>
        <p class="hero-sub"><strong><?= count($events) ?> etkinlik</strong> · promosyon, duyuru, KYC ve ödeme hareketleri takvim akışına bağlandı.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/promotions'), ENT_QUOTES, 'UTF-8') ?>">Promosyonlar</a>
        <a class="btn btn--primary" href="<?= htmlspecialchars(AdminAuth::url('/module?key=announcements'), ENT_QUOTES, 'UTF-8') ?>">Duyurular</a>
    </div>
</section>

<section class="cal-shell" aria-label="Takvim">
    <aside class="cal-rail">
        <a class="cal-quickadd" href="<?= htmlspecialchars(AdminAuth::url('/table/create?name=announcements&module=announcements'), ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> Hızlı etkinlik ekle</a>
        <div class="cal-rail-card">
            <div class="cal-rail-head"><div class="cal-rail-title"><?= htmlspecialchars($monthName . ' ' . $year, ENT_QUOTES, 'UTF-8') ?></div></div>
            <div class="mini-cal-grid">
                <?php foreach (['P','S','Ç','P','C','C','P'] as $wd): ?><div class="mini-cal-wd"><?= $wd ?></div><?php endforeach; ?>
                <?php for ($day = 1; $day <= (int) date('t'); $day++): ?>
                    <div class="mini-cal-day <?= $day === $today ? 'is-today' : '' ?> <?= isset($eventDays[$day]) ? 'has-event' : '' ?>"><?= $day ?></div>
                <?php endfor; ?>
            </div>
        </div>
        <div class="cal-rail-card">
            <div class="cal-rail-head"><div class="cal-rail-title">Takvimlerim</div></div>
            <div class="cal-list">
                <?php foreach (['Promosyon', 'Duyuru', 'KYC', 'Yatırım'] as $index => $name): ?>
                    <div class="cal-list-item"><span class="cal-list-check" style="color:var(--<?= ['purple','primary','success','info'][$index] ?>)"></span> <span class="cal-list-name"><?= $name ?></span> <span class="cal-list-count"><?= count(array_filter($events, static fn (array $event): bool => ($event['kind'] ?? '') === $name)) ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="cal-rail-card">
            <div class="cal-rail-head"><div class="cal-rail-title">Yaklaşan</div></div>
            <div class="upc-list">
                <?php foreach (array_slice($events, 0, 8) as $event): ?>
                    <?php $ts = strtotime((string) ($event['starts_at'] ?? '')); ?>
                    <div class="upc-item">
                        <div class="upc-date <?= $ts !== false && date('Y-m-d', $ts) === date('Y-m-d') ? 'is-today' : '' ?>"><div class="day"><?= $ts !== false ? date('d', $ts) : '--' ?></div><span class="mo"><?= $ts !== false ? date('M', $ts) : '---' ?></span></div>
                        <div class="upc-meta"><div class="upc-title"><?= htmlspecialchars((string) ($event['title'] ?? 'Etkinlik'), ENT_QUOTES, 'UTF-8') ?></div><div class="upc-time"><span class="dot" style="background:var(--primary)"></span> <span class="mono"><?= $ts !== false ? date('H:i', $ts) : '--:--' ?></span> <span>· <?= htmlspecialchars((string) ($event['kind'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </aside>
    <section class="cal-main">
        <div class="cal-toolbar"><div class="cal-toolbar-left"><div class="cal-month"><?= htmlspecialchars($monthName, ENT_QUOTES, 'UTF-8') ?> <span class="yr"><?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?></span></div></div><div class="cal-views"><button class="cal-view-tab" type="button">Gün</button><button class="cal-view-tab" type="button">Hafta</button><button class="cal-view-tab is-active" type="button">Ay</button><button class="cal-view-tab" type="button">Ajanda</button></div></div>
        <div data-fc style="flex:1;min-height:540px"></div>
    </section>
</section>
</section>
