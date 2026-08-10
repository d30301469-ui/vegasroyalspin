<?php

$events = is_array($events ?? null) ? $events : [];
$eventsByDay = is_array($eventsByDay ?? null) ? $eventsByDay : [];
$counts = is_array($counts ?? null) ? $counts : [];
$filter = (string) ($filter ?? 'all');
$month = (string) ($month ?? date('Y-m'));
$prevMonth = (string) ($prevMonth ?? date('Y-m', strtotime('-1 month')));
$nextMonth = (string) ($nextMonth ?? date('Y-m', strtotime('+1 month')));
$monthLabel = (string) ($monthLabel ?? date('F Y'));
$today = (string) ($today ?? date('Y-m-d'));
$monthStart = (string) ($monthStart ?? ($month . '-01'));

$text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$baseUrl = AdminAuth::url('/reports/calendar');
$monthUrl = static function (string $targetMonth, string $kind = 'all') use ($baseUrl): string {
    $query = http_build_query([
        'month' => $targetMonth,
        'kind' => $kind,
    ]);

    return $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . $query;
};

$monthTs = strtotime($monthStart) ?: time();
$daysInMonth = (int) date('t', $monthTs);
$startWeekday = (int) date('N', $monthTs);
$monthNameOnly = trim((string) preg_replace('/\s+\d{4}$/', '', $monthLabel));
$yearOnly = date('Y', $monthTs);
$todayDay = ((int) date('n', $monthTs) === (int) date('n') && (int) date('Y', $monthTs) === (int) date('Y'))
    ? (int) date('j')
    : 0;

$weekdaysShort = ['P', 'S', 'Ç', 'P', 'C', 'C', 'P'];
$weekdaysFull = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'];

$kindMeta = [
    'all' => ['label' => 'Tümü', 'chip' => 'work', 'tone' => 'primary', 'check' => 'var(--primary)'],
    'promosyon' => ['label' => 'Promosyon', 'chip' => 'personal', 'tone' => 'purple', 'check' => 'var(--purple)'],
    'duyuru' => ['label' => 'Duyuru', 'chip' => 'work', 'tone' => 'primary', 'check' => 'var(--primary)'],
    'kyc' => ['label' => 'KYC', 'chip' => 'team', 'tone' => 'success', 'check' => 'var(--success)'],
    'yatirim' => ['label' => 'Yatırım', 'chip' => 'finance', 'tone' => 'warning', 'check' => 'var(--warning)'],
    'cekim' => ['label' => 'Çekim', 'chip' => 'holiday', 'tone' => 'danger', 'check' => 'var(--danger)'],
];

$chipClassFor = static function (string $kindKey) use ($kindMeta): string {
    return (string) ($kindMeta[$kindKey]['chip'] ?? 'work');
};

$eventDays = [];
foreach ($eventsByDay as $dayKey => $dayEvents) {
    if (!is_array($dayEvents) || $dayEvents === []) {
        continue;
    }
    $eventDays[(int) substr((string) $dayKey, -2)] = true;
}

$upcoming = array_values(array_filter(
    $events,
    static function (array $event) use ($today): bool {
        $day = (string) ($event['day'] ?? '');

        return $day >= $today;
    }
));
if ($upcoming === []) {
    $upcoming = $events;
}
$upcoming = array_slice($upcoming, 0, 10);

$heroDate = trim($monthLabel) . ' · ' . count($events) . ' kayıt';
?>
<style>
    /* Theme calendar refinements for operational clarity */
    .cal-shell { align-items: start; }
    .cal-main-body { display:flex; flex-direction:column; min-height:640px; }
    .cal-cell.is-selected { background:var(--primary-soft); }
    .cal-cell.is-selected .cal-day-num { box-shadow:0 0 0 2px color-mix(in oklab, var(--primary) 35%, transparent); }
    .cal-agenda-panel { display:none; flex:1; overflow:auto; padding:8px 16px 16px; }
    .cal-agenda-panel.is-on { display:block; }
    .cal-month-panel.is-off { display:none; }
    .cal-agenda-group { margin-top:14px; }
    .cal-agenda-group-title {
        color:var(--t-light); font-family:JetBrains Mono,monospace; font-size:10px; font-weight:700;
        letter-spacing:.08em; margin:0 0 8px; text-transform:uppercase;
    }
    .cal-empty {
        background:var(--bg-muted); border:1px dashed var(--border); border-radius:10px;
        color:var(--t-muted); font-size:12.5px; font-weight:600; margin:18px; padding:22px; text-align:center;
    }
    .cal-list-item.is-active { background:var(--bg-hover); }
    .cal-list-item.is-active .cal-list-name { color:var(--t-base); font-weight:700; }
    .cal-filter-note { color:var(--t-muted); font-size:11.5px; line-height:1.4; margin:0; }
    .cal-chip.holiday { background:var(--danger-soft); border-left-color:var(--danger); color:var(--danger); }
    .cal-chip.solid.holiday { background:var(--danger); border-left-color:var(--danger); color:#fff; }
    @media (max-width:1100px) {
        .cal-shell { grid-template-columns:1fr; }
        .cal-rail { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
        .cal-quickadd { grid-column:1 / -1; }
    }
    @media (max-width:720px) {
        .cal-rail { grid-template-columns:1fr; }
        .cal-toolbar { flex-wrap:wrap; }
        .cal-month { font-size:18px; }
        .cal-grid { grid-auto-rows:minmax(88px,auto); }
    }
</style>

<section class="admin-surface">
<section class="hero cal-hero">
    <div class="hero-text">
        <span class="eyebrow" id="heroDate"><?= $text(date('d.m.Y · l')) ?></span>
        <h1 class="hero-title"><?= $text($monthNameOnly) ?> <span class="accent"><?= $text($yearOnly) ?></span></h1>
        <p class="hero-sub">
            <strong><?= $text((string) (int) ($counts['all'] ?? 0)) ?> operasyon kaydı</strong>
            bu ayda · promosyon, duyuru, KYC, yatırım ve çekim akışını tek takvimde yönetin.
            <?= $filter !== 'all' ? 'Aktif filtre: <strong>' . $text($kindMeta[$filter]['label'] ?? $filter) . '</strong>.' : '' ?>
        </p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/promotions')) ?>">Promosyonlar</a>
        <a class="btn btn--primary" href="<?= $text(AdminAuth::url('/module?key=announcements')) ?>">Duyurular</a>
    </div>
</section>

<section class="cal-shell" aria-label="Operasyon takvimi">
    <aside class="cal-rail">
        <a class="cal-quickadd" href="<?= $text(AdminAuth::url('/table/create?name=announcements&module=announcements')) ?>">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Hızlı duyuru ekle
        </a>

        <div class="cal-rail-card">
            <div class="cal-rail-head">
                <div class="cal-rail-title"><?= $text($monthLabel) ?></div>
            </div>
            <div class="mini-cal-grid" aria-hidden="true">
                <?php foreach ($weekdaysShort as $wd): ?>
                    <div class="mini-cal-wd"><?= $text($wd) ?></div>
                <?php endforeach; ?>
                <?php for ($i = 1; $i < $startWeekday; $i++): ?>
                    <div class="mini-cal-day is-other"></div>
                <?php endfor; ?>
                <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                    <?php
                    $dayKey = sprintf('%s-%02d', $month, $day);
                    $isToday = $day === $todayDay;
                    $hasEvent = isset($eventDays[$day]);
                    ?>
                    <a
                        class="mini-cal-day <?= $isToday ? 'is-today' : '' ?> <?= $hasEvent ? 'has-event' : '' ?>"
                        href="#day-<?= $text($dayKey) ?>"
                        data-cal-jump="<?= $text($dayKey) ?>"
                        title="<?= $text((string) $day) ?>"
                    ><?= $text((string) $day) ?></a>
                <?php endfor; ?>
            </div>
            <p class="cal-filter-note">Noktalı günlerde operasyon kaydı vardır. Ana takvimde detayları görün.</p>
        </div>

        <div class="cal-rail-card">
            <div class="cal-rail-head">
                <div class="cal-rail-title">Takvim kaynakları</div>
            </div>
            <div class="cal-list">
                <?php foreach ($kindMeta as $key => $meta): ?>
                    <?php $isOn = $filter === $key; ?>
                    <a class="cal-list-item <?= $isOn ? 'is-active' : '' ?>" href="<?= $text($monthUrl($month, $key)) ?>">
                        <span class="cal-list-check <?= $isOn ? '' : 'is-off' ?>" style="color:<?= $text($meta['check']) ?>"></span>
                        <span class="cal-list-name"><?= $text($meta['label']) ?></span>
                        <span class="cal-list-count"><?= $text((string) (int) ($counts[$key] ?? 0)) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="cal-rail-card">
            <div class="cal-rail-head">
                <div class="cal-rail-title">Yaklaşan / son kayıtlar</div>
            </div>
            <div class="upc-list">
                <?php if ($upcoming === []): ?>
                    <div class="cal-empty" style="margin:0;padding:16px">Bu filtrede gösterilecek kayıt yok.</div>
                <?php else: ?>
                    <?php foreach ($upcoming as $event): ?>
                        <?php
                        $ts = strtotime((string) ($event['starts_at'] ?? '')) ?: false;
                        $dayKey = (string) ($event['day'] ?? '');
                        $isEventToday = $dayKey === $today;
                        $kindKey = (string) ($event['kind_key'] ?? 'all');
                        ?>
                        <a class="upc-item" href="#day-<?= $text($dayKey) ?>" data-cal-jump="<?= $text($dayKey) ?>" style="text-decoration:none;color:inherit">
                            <div class="upc-date <?= $isEventToday ? 'is-today' : '' ?>">
                                <div class="day"><?= $ts ? $text(date('d', $ts)) : '--' ?></div>
                                <span class="mo"><?= $ts ? $text(date('m', $ts)) : '---' ?></span>
                            </div>
                            <div class="upc-meta">
                                <div class="upc-title"><?= $text($event['title'] ?? 'Etkinlik') ?></div>
                                <div class="upc-time">
                                    <span class="dot" style="background:<?= $text($kindMeta[$kindKey]['check'] ?? 'var(--primary)') ?>"></span>
                                    <span class="mono"><?= $ts ? $text(date('H:i', $ts)) : '--:--' ?></span>
                                    <span>· <?= $text($event['kind'] ?? '') ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </aside>

    <section class="cal-main">
        <div class="cal-toolbar">
            <div class="cal-toolbar-left">
                <div class="cal-month"><?= $text($monthNameOnly) ?> <span class="yr"><?= $text($yearOnly) ?></span></div>
                <div class="cal-nav">
                    <a class="cal-nav-btn" href="<?= $text($monthUrl($prevMonth, $filter)) ?>" aria-label="Önceki ay">
                        <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
                    </a>
                    <a class="cal-nav-btn" href="<?= $text($monthUrl($nextMonth, $filter)) ?>" aria-label="Sonraki ay">
                        <svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
                    </a>
                </div>
                <a class="cal-today-btn" href="<?= $text($monthUrl(date('Y-m'), $filter)) ?>">Bugün</a>
            </div>
            <div class="cal-toolbar-right">
                <div class="cal-views" role="tablist" aria-label="Görünüm">
                    <button class="cal-view-tab is-active" type="button" data-cal-view="month">Ay</button>
                    <button class="cal-view-tab" type="button" data-cal-view="agenda">Ajanda</button>
                </div>
            </div>
        </div>

        <div class="cal-main-body">
            <div class="cal-month-panel" data-cal-panel="month">
                <div class="cal-weekdays">
                    <?php foreach ($weekdaysFull as $wd): ?>
                        <div><?= $text($wd) ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="cal-grid">
                    <?php for ($i = 1; $i < $startWeekday; $i++): ?>
                        <div class="cal-cell is-other"><div class="cal-day-num"></div></div>
                    <?php endfor; ?>

                    <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                        <?php
                        $dayKey = sprintf('%s-%02d', $month, $day);
                        $dayEvents = is_array($eventsByDay[$dayKey] ?? null) ? $eventsByDay[$dayKey] : [];
                        $visible = array_slice($dayEvents, 0, 3);
                        $extra = max(0, count($dayEvents) - count($visible));
                        $weekday = (($startWeekday - 1 + $day - 1) % 7) + 1;
                        $isWeekend = $weekday >= 6;
                        $isToday = $day === $todayDay;
                        ?>
                        <div
                            class="cal-cell <?= $isToday ? 'is-today' : '' ?> <?= $isWeekend ? 'is-weekend' : '' ?>"
                            id="day-<?= $text($dayKey) ?>"
                            data-cal-day="<?= $text($dayKey) ?>"
                        >
                            <div class="cal-day-num"><?= $text((string) $day) ?></div>
                            <div class="cal-chips">
                                <?php foreach ($visible as $event): ?>
                                    <?php
                                    $ts = strtotime((string) ($event['starts_at'] ?? '')) ?: false;
                                    $kindKey = (string) ($event['kind_key'] ?? 'all');
                                    $chip = $chipClassFor($kindKey);
                                    $title = (string) ($event['title'] ?? 'Etkinlik');
                                    $tip = trim(($event['kind'] ?? '') . ' · ' . $title . (((string) ($event['detail'] ?? '') !== '') ? ' · ' . $event['detail'] : ''));
                                    ?>
                                    <div class="cal-chip <?= $text($chip) ?>" title="<?= $text($tip) ?>">
                                        <span class="cal-chip-time"><?= $ts ? $text(date('H:i', $ts)) : '' ?></span>
                                        <span class="cal-chip-title"><?= $text($title) ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($extra > 0): ?>
                                    <div class="cal-chip-more">+<?= $text((string) $extra) ?> daha</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endfor; ?>

                    <?php
                    $filled = ($startWeekday - 1) + $daysInMonth;
                    $trail = (7 - ($filled % 7)) % 7;
                    for ($i = 0; $i < $trail; $i++):
                    ?>
                        <div class="cal-cell is-other"><div class="cal-day-num"></div></div>
                    <?php endfor; ?>
                </div>

                <?php if ($events === []): ?>
                    <div class="cal-empty">Bu ay ve seçili kaynak için operasyon kaydı bulunamadı.</div>
                <?php endif; ?>
            </div>

            <div class="cal-agenda-panel" data-cal-panel="agenda">
                <?php if ($events === []): ?>
                    <div class="cal-empty">Ajandada gösterilecek kayıt yok.</div>
                <?php else: ?>
                    <?php
                    $agendaGroups = [];
                    foreach ($events as $event) {
                        $day = (string) ($event['day'] ?? '');
                        if ($day === '') {
                            continue;
                        }
                        $agendaGroups[$day][] = $event;
                    }
                    ?>
                    <?php foreach ($agendaGroups as $dayKey => $dayEvents): ?>
                        <?php
                        $dayTs = strtotime($dayKey) ?: false;
                        $groupLabel = $dayTs
                            ? date('d.m.Y', $dayTs) . ' · ' . $weekdaysFull[((int) date('N', $dayTs)) - 1]
                            : $dayKey;
                        ?>
                        <div class="cal-agenda-group" id="agenda-<?= $text($dayKey) ?>">
                            <div class="cal-agenda-group-title"><?= $text($groupLabel) ?></div>
                            <div class="upc-list">
                                <?php foreach ($dayEvents as $event): ?>
                                    <?php
                                    $ts = strtotime((string) ($event['starts_at'] ?? '')) ?: false;
                                    $kindKey = (string) ($event['kind_key'] ?? 'all');
                                    ?>
                                    <div class="upc-item">
                                        <div class="upc-date <?= $dayKey === $today ? 'is-today' : '' ?>">
                                            <div class="day"><?= $ts ? $text(date('d', $ts)) : '--' ?></div>
                                            <span class="mo"><?= $ts ? $text(date('m', $ts)) : '---' ?></span>
                                        </div>
                                        <div class="upc-meta">
                                            <div class="upc-title"><?= $text($event['title'] ?? 'Etkinlik') ?></div>
                                            <div class="upc-time">
                                                <span class="dot" style="background:<?= $text($kindMeta[$kindKey]['check'] ?? 'var(--primary)') ?>"></span>
                                                <span class="mono"><?= $ts ? $text(date('H:i', $ts)) : '--:--' ?></span>
                                                <span>· <?= $text($event['kind'] ?? '') ?></span>
                                                <?php if (trim((string) ($event['detail'] ?? '')) !== ''): ?>
                                                    <span>· <?= $text($event['detail']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
</section>
</section>

<script>
(function () {
    var root = document.querySelector('.cal-shell');
    if (!root) return;

    var tabs = root.querySelectorAll('[data-cal-view]');
    var monthPanel = root.querySelector('[data-cal-panel="month"]');
    var agendaPanel = root.querySelector('[data-cal-panel="agenda"]');

    var setView = function (view) {
        tabs.forEach(function (tab) {
            tab.classList.toggle('is-active', tab.getAttribute('data-cal-view') === view);
        });
        if (monthPanel) monthPanel.classList.toggle('is-off', view !== 'month');
        if (agendaPanel) agendaPanel.classList.toggle('is-on', view === 'agenda');
    };

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            setView(tab.getAttribute('data-cal-view') || 'month');
        });
    });

    var selectDay = function (dayKey) {
        if (!dayKey) return;
        root.querySelectorAll('.cal-cell.is-selected').forEach(function (el) {
            el.classList.remove('is-selected');
        });
        var cell = root.querySelector('.cal-cell[data-cal-day="' + dayKey + '"]');
        if (cell) {
            cell.classList.add('is-selected');
            cell.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        var agenda = document.getElementById('agenda-' + dayKey);
        if (agenda && agendaPanel && agendaPanel.classList.contains('is-on')) {
            agenda.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    root.querySelectorAll('[data-cal-jump]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            var dayKey = el.getAttribute('data-cal-jump') || '';
            if (!dayKey) return;
            // Keep hash navigation, also highlight.
            selectDay(dayKey);
        });
    });

    root.querySelectorAll('.cal-cell[data-cal-day]').forEach(function (cell) {
        cell.addEventListener('click', function () {
            selectDay(cell.getAttribute('data-cal-day') || '');
        });
    });

    if (window.location.hash.indexOf('#day-') === 0) {
        selectDay(window.location.hash.slice(5));
    }
})();
</script>
