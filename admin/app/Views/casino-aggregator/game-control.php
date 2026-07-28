<?php

$configRow = is_array($configRow ?? null) ? $configRow : [];
$vendors = is_array($vendors ?? null) ? $vendors : [];
$players = is_array($players ?? null) ? $players : [];
$history = is_array($history ?? null) ? $history : [];
$callLogs = is_array($callLogs ?? null) ? $callLogs : [];
$flash = trim((string) ($flash ?? ''));
$vendorCode = trim((string) ($vendorCode ?? ''));
$playersError = trim((string) ($playersError ?? ''));
$historyError = trim((string) ($historyError ?? ''));
$histVendor = trim((string) ($histVendor ?? ''));
$startTime = trim((string) ($startTime ?? ''));
$endTime = trim((string) ($endTime ?? ''));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$isActive = !empty($configRow['is_active']) && $configRow['is_active'] !== '0';
$csrf = AdminAuth::csrfToken();
$callListUrl = AdminAuth::url('/casino-aggregator/call-list');

$logsByUser = [];
foreach ($callLogs as $log) {
    if (!is_array($log) || (string) ($log['action'] ?? '') !== 'CallApply') {
        continue;
    }
    $uid = (string) ($log['user_code'] ?? '');
    if ($uid === '') {
        continue;
    }
    $logsByUser[$uid][] = $log;
}
?>
<style>
    .ca-card { border: 1px solid var(--border); border-radius: 18px; background: var(--bg-card); padding: 18px; box-shadow: var(--shadow-card); margin-bottom: 18px; }
    .ca-help { color: var(--t-muted); font-size: 13px; line-height: 1.45; }
    .ca-inline { display:flex; gap:10px; flex-wrap:wrap; align-items:end; }
    .ca-inline .field { margin-bottom:0; min-width:160px; }
    .ca-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .ca-table { width:100%; border-collapse: collapse; font-size: 13px; min-width: 960px; }
    .ca-table th {
        text-align:left; padding:10px 12px; border-bottom:1px solid var(--border);
        color: var(--t-muted); font-weight:600; font-size:12px; letter-spacing:.02em; white-space:nowrap;
    }
    .ca-table td {
        text-align:left; padding:12px; border-bottom:1px solid var(--border);
        vertical-align: middle; white-space: nowrap;
    }
    .ca-table tbody tr:hover { background: color-mix(in srgb, var(--bg) 65%, transparent); }
    .ca-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12.5px; }
    .ca-num { font-variant-numeric: tabular-nums; font-weight: 600; }
    .ca-empty { color: var(--t-muted); }
    .ca-type-pill {
        display:inline-flex; align-items:center; max-width:140px;
        padding:4px 8px; border-radius:999px; border:1px solid var(--border);
        background: var(--bg); color: var(--t-muted); font-size:11px;
        overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
    }
    .ca-actions {
        display:grid; grid-template-columns: auto minmax(88px, 110px) auto auto;
        gap: 8px; align-items: center; min-width: 420px;
    }
    .ca-actions + .ca-actions { margin-top: 8px; padding-top: 8px; border-top: 1px dashed var(--border); }
    .ca-actions .input,
    .ca-actions select.input {
        width: 100%; min-width: 0; height: 34px; padding: 0 10px;
        border-radius: 10px; font-size: 12.5px;
    }
    .ca-actions .btn { height: 34px; padding: 0 12px; border-radius: 10px; white-space: nowrap; }
    .ca-actions .ca-span-2 { grid-column: span 2; }
    .ca-tag {
        display:inline-flex; align-items:center; height:22px; padding:0 8px;
        border-radius:999px; font-size:11px; font-weight:600;
        background: color-mix(in srgb, var(--bg) 80%, var(--border));
        color: var(--t-muted);
    }
    .ca-tag--ok { background: color-mix(in srgb, #16a34a 18%, transparent); color: #15803d; }
    .ca-tag--warn { background: color-mix(in srgb, #d97706 18%, transparent); color: #b45309; }
    .ca-call-err { grid-column: 1 / -1; color:#b45309; font-size:11px; margin:0; }
    @media (max-width: 1100px) {
        .ca-actions { grid-template-columns: 1fr 1fr; min-width: 280px; }
        .ca-actions .ca-span-2 { grid-column: span 1; }
    }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Game Control API v1.0.0</span>
        <h1 class="hero-title">Canlı <span class="accent">Oyuncular</span> & Call</h1>
        <p class="hero-sub">Liste yapısı aynı — call kontrolleri kompakt satırda.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/casino-aggregator/agent-settings')) ?>">Agent Settings</a>
    </div>
</section>

<?php if ($flash !== ''): ?><div class="alert alert--info" style="margin-bottom:16px"><?= $text($flash) ?></div><?php endif; ?>
<?php if (!$isActive): ?><div class="alert alert--warning" style="margin-bottom:16px">Aggregator pasif.</div><?php endif; ?>

<form class="ca-card" method="get" action="<?= $text(AdminAuth::url('/casino-aggregator/game-control')) ?>">
    <div class="ca-inline">
        <div class="field">
            <label class="field-label" for="vendor_code">vendorCode</label>
            <select id="vendor_code" class="input" name="vendor_code">
                <option value="">— seç —</option>
                <?php foreach ($vendors as $v): ?>
                    <?php $code = (string) ($v['vendor_code'] ?? ''); ?>
                    <option value="<?= $text($code) ?>" <?= $code === $vendorCode ? 'selected' : '' ?>><?= $text(($v['vendor_name'] ?? $code) . ' (' . $code . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn--primary" type="submit">GetCurrentPlayers</button>
    </div>
</form>

<?php if ($playersError !== ''): ?>
    <div class="alert alert--warning" style="margin-bottom:16px"><?= $text($playersError) ?></div>
<?php endif; ?>

<div class="ca-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px;flex-wrap:wrap;">
        <div style="font-weight:700">Aktif oyuncular</div>
        <?php if ($vendorCode !== ''): ?>
            <span class="ca-tag"><?= count($players) ?> oyuncu · <?= $text($vendorCode) ?></span>
        <?php endif; ?>
    </div>
    <?php if ($vendorCode === ''): ?>
        <p class="ca-help" style="margin:0">Vendor seçin.</p>
    <?php elseif ($players === []): ?>
        <p class="ca-help" style="margin:0">Oyuncu yok veya liste boş.</p>
    <?php else: ?>
        <div class="ca-table-wrap">
        <table class="ca-table">
            <thead>
            <tr>
                <th>User</th>
                <th>Game</th>
                <th>Bet</th>
                <th>Balance</th>
                <th>Target RTP</th>
                <th>Type</th>
                <th>Apply</th>
                <th>Cancel</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($players as $p): ?>
                <?php if (!is_array($p)) { continue; } ?>
                <?php
                $pUser = (string) ($p['userCode'] ?? '');
                $pGame = (string) ($p['gameCode'] ?? '');
                $pVendor = (string) ($p['vendorCode'] ?? $vendorCode);
                $pCur = (string) ($p['currencyCode'] ?? ($configRow['currency'] ?? 'TRY'));
                $pBet = (float) ($p['betAmount'] ?? 0);
                $pBal = $p['balance'] ?? null;
                $pTarget = trim((string) ($p['targetRtp'] ?? ''));
                $pTypeRaw = (string) ($p['requestType'] ?? '0');
                $callOpts = is_array($p['_call_options'] ?? null) ? $p['_call_options'] : [];
                $callValues = is_array($callOpts['calls'] ?? null) ? $callOpts['calls'] : [];
                $callType = (string) ($p['_call_type'] ?? CasinoAggregatorService::normalizeCallType($pTypeRaw));
                $callErr = trim((string) ($callOpts['error'] ?? ''));
                $userApplyLogs = $logsByUser[$pUser] ?? [];
                $hasCalls = $callValues !== [];
                ?>
                <tr data-player-row>
                    <td class="ca-mono"><?= $text($pUser) ?></td>
                    <td class="ca-mono"><?= $text($pGame) ?></td>
                    <td class="ca-num"><?= $text(number_format($pBet, 2, '.', '')) ?></td>
                    <td class="ca-num"><?= $pBal === null || $pBal === '' ? '<span class="ca-empty">—</span>' : $text(number_format((float) $pBal, 2, '.', '')) ?></td>
                    <td class="ca-num"><?= $pTarget === '' ? '<span class="ca-empty">—</span>' : $text($pTarget) ?></td>
                    <td><span class="ca-type-pill" title="<?= $text($pTypeRaw) ?>"><?= $text($pTypeRaw !== '' ? $pTypeRaw : '—') ?></span></td>
                    <td>
                        <div class="ca-call-panel"
                             data-vendor="<?= $text($pVendor) ?>"
                             data-game="<?= $text($pGame) ?>"
                             data-request-type="<?= $text($pTypeRaw) ?>">
                            <form method="post" action="<?= $text(AdminAuth::url('/casino-aggregator/call-apply')) ?>" class="ca-apply-form ca-actions">
                                <input type="hidden" name="_token" value="<?= $text($csrf) ?>">
                                <input type="hidden" name="vendor_code" value="<?= $text($pVendor) ?>">
                                <input type="hidden" name="game_code" value="<?= $text($pGame) ?>">
                                <input type="hidden" name="user_code" value="<?= $text($pUser) ?>">
                                <input type="hidden" name="currency_code" value="<?= $text($pCur) ?>">
                                <input type="hidden" name="bet_amount" value="<?= $text((string) $pBet) ?>">
                                <input type="hidden" class="ca-call-type-hidden" name="call_type" value="<?= $text($callType) ?>">

                                <select class="input ca-call-type" name="call_type_ui" title="callType" aria-label="callType">
                                    <option value="0" <?= $callType === '0' ? 'selected' : '' ?>>Base (0)</option>
                                    <option value="1" <?= $callType === '1' ? 'selected' : '' ?>>Free (1)</option>
                                    <?php if ($pTypeRaw !== '' && $pTypeRaw !== '0' && $pTypeRaw !== '1'): ?>
                                        <option value="<?= $text($pTypeRaw) ?>" <?= $callType === $pTypeRaw ? 'selected' : '' ?>>Raw</option>
                                    <?php endif; ?>
                                </select>

                                <select name="call_rtp" class="input ca-call-rtp" title="callRtp" aria-label="callRtp" <?= $hasCalls ? '' : 'disabled' ?> required>
                                    <?php if (!$hasCalls): ?>
                                        <option value="">RTP yok</option>
                                    <?php else: ?>
                                        <?php foreach ($callValues as $rtp): ?>
                                            <option value="<?= $text((string) $rtp) ?>"><?= $text((string) $rtp) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>

                                <button type="button" class="btn btn--ghost btn--xs ca-reload-calls" title="GetCallList">Liste</button>
                                <button class="btn btn--primary btn--xs ca-apply-btn" type="submit" <?= $hasCalls ? '' : 'disabled' ?>>Apply</button>

                                <?php if ($callErr !== '' && !$hasCalls): ?>
                                    <p class="ca-call-err"><?= $text($callErr) ?></p>
                                <?php elseif ($hasCalls): ?>
                                    <span class="ca-tag ca-tag--ok" style="grid-column:1/-1;justify-self:start"><?= count($callValues) ?> oran hazır</span>
                                <?php endif; ?>
                            </form>
                        </div>
                    </td>
                    <td>
                        <form method="post" action="<?= $text(AdminAuth::url('/casino-aggregator/call-cancel')) ?>" class="ca-cancel-form ca-actions">
                            <input type="hidden" name="_token" value="<?= $text($csrf) ?>">
                            <input type="hidden" name="vendor_code" value="<?= $text($pVendor) ?>">
                            <input type="hidden" name="game_code" value="<?= $text($pGame) ?>">
                            <input type="hidden" name="user_code" value="<?= $text($pUser) ?>">
                            <input type="hidden" name="currency_code" value="<?= $text($pCur) ?>">
                            <input type="hidden" name="bet_amount" value="<?= $text((string) $pBet) ?>">

                            <select name="call_rtp" class="input ca-cancel-rtp" title="callRtp" aria-label="cancel callRtp" <?= $hasCalls ? '' : 'disabled' ?> required>
                                <?php if (!$hasCalls): ?>
                                    <option value="">RTP</option>
                                <?php else: ?>
                                    <?php foreach ($callValues as $rtp): ?>
                                        <option value="<?= $text((string) $rtp) ?>"><?= $text((string) $rtp) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>

                            <select name="call_id" class="input ca-cancel-id ca-span-2" title="callId" aria-label="callId" required>
                                <option value="">callId seç</option>
                                <?php foreach ($userApplyLogs as $log): ?>
                                    <?php
                                    $cid = (int) ($log['call_id'] ?? 0);
                                    if ($cid <= 0) {
                                        continue;
                                    }
                                    $label = '#' . $cid . ' · ' . (string) ($log['call_rtp'] ?? '');
                                    ?>
                                    <option value="<?= $cid ?>" data-rtp="<?= $text((string) ($log['call_rtp'] ?? '')) ?>"><?= $text($label) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <button class="btn btn--ghost btn--xs" type="submit">Cancel</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<form class="ca-card" method="get" action="<?= $text(AdminAuth::url('/casino-aggregator/game-control')) ?>">
    <input type="hidden" name="load_history" value="1">
    <div style="font-weight:700;margin-bottom:12px">GetCallHistory (UTC+0)</div>
    <div class="ca-inline">
        <div class="field">
            <label class="field-label">vendor</label>
            <select class="input" name="hist_vendor">
                <option value="">—</option>
                <?php foreach ($vendors as $v): ?>
                    <?php $code = (string) ($v['vendor_code'] ?? ''); ?>
                    <option value="<?= $text($code) ?>" <?= $code === $histVendor ? 'selected' : '' ?>><?= $text($code) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field-label">startTime</label>
            <input class="input" type="text" name="start_time" value="<?= $text($startTime) ?>">
        </div>
        <div class="field">
            <label class="field-label">endTime</label>
            <input class="input" type="text" name="end_time" value="<?= $text($endTime) ?>">
        </div>
        <button class="btn btn--ghost" type="submit">Yükle</button>
    </div>
    <?php if ($vendorCode !== ''): ?><input type="hidden" name="vendor_code" value="<?= $text($vendorCode) ?>"><?php endif; ?>
</form>

<?php if ($historyError !== ''): ?><div class="alert alert--warning" style="margin-bottom:16px"><?= $text($historyError) ?></div><?php endif; ?>

<?php if ($history !== []): ?>
<div class="ca-card">
    <div style="font-weight:700;margin-bottom:12px">Call history</div>
    <div class="ca-table-wrap">
    <table class="ca-table">
        <thead><tr><th>id</th><th>user</th><th>game</th><th>rtp</th><th>bet</th><th>status</th><th>created</th></tr></thead>
        <tbody>
        <?php foreach ($history as $h): if (!is_array($h)) continue; ?>
            <tr>
                <td class="ca-mono"><?= $text((string) ($h['id'] ?? '')) ?></td>
                <td class="ca-mono"><?= $text((string) ($h['userCode'] ?? '')) ?></td>
                <td class="ca-mono"><?= $text((string) ($h['gameCode'] ?? '')) ?></td>
                <td class="ca-num"><?= $text((string) ($h['rtp'] ?? '')) ?></td>
                <td class="ca-num"><?= $text((string) ($h['betAmount'] ?? '')) ?></td>
                <td><?= $text((string) ($h['status'] ?? '')) ?></td>
                <td><?= $text((string) ($h['createdAt'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<?php if ($callLogs !== []): ?>
<div class="ca-card">
    <div style="font-weight:700;margin-bottom:12px">Yerel call log</div>
    <div class="ca-table-wrap">
    <table class="ca-table">
        <thead><tr><th>action</th><th>user</th><th>callId</th><th>rtp</th><th>money</th><th>at</th></tr></thead>
        <tbody>
        <?php foreach ($callLogs as $log): ?>
            <tr>
                <td><?= $text((string) ($log['action'] ?? '')) ?></td>
                <td class="ca-mono"><?= $text((string) ($log['user_code'] ?? '')) ?></td>
                <td class="ca-mono"><?= $text((string) ($log['call_id'] ?? '')) ?></td>
                <td class="ca-num"><?= $text((string) ($log['call_rtp'] ?? '')) ?></td>
                <td class="ca-num"><?= $text((string) ($log['money_amount'] ?? '')) ?></td>
                <td><?= $text((string) ($log['created_at'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    var url = <?= json_encode($callListUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var token = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>;

    function fillSelect(sel, calls, emptyLabel) {
        if (!sel) return;
        sel.innerHTML = '';
        if (!calls.length) {
            var empty = document.createElement('option');
            empty.value = '';
            empty.textContent = emptyLabel || 'RTP yok';
            sel.appendChild(empty);
            sel.disabled = true;
            return;
        }
        calls.forEach(function (v) {
            var opt = document.createElement('option');
            opt.value = String(v);
            opt.textContent = String(v);
            sel.appendChild(opt);
        });
        sel.disabled = false;
    }

    function syncPanel(panel, calls) {
        var row = panel.closest('tr');
        var applySelect = panel.querySelector('.ca-call-rtp');
        var applyBtn = panel.querySelector('.ca-apply-btn');
        var cancelSelect = row ? row.querySelector('.ca-cancel-rtp') : null;
        var err = panel.querySelector('.ca-call-err');
        var tag = panel.querySelector('.ca-tag');
        fillSelect(applySelect, calls, 'RTP yok');
        fillSelect(cancelSelect, calls, 'RTP');
        if (applyBtn) applyBtn.disabled = !calls.length;
        if (err) err.style.display = calls.length ? 'none' : '';
        if (tag) {
            if (calls.length) {
                tag.textContent = calls.length + ' oran hazır';
                tag.className = 'ca-tag ca-tag--ok';
                tag.style.display = '';
            } else {
                tag.style.display = 'none';
            }
        }
    }

    function loadCalls(panel) {
        var typeSel = panel.querySelector('.ca-call-type');
        var hidden = panel.querySelector('.ca-call-type-hidden');
        var type = typeSel ? typeSel.value : '0';
        if (hidden) hidden.value = type;
        var body = new FormData();
        body.append('_token', token);
        body.append('vendor_code', panel.getAttribute('data-vendor') || '');
        body.append('game_code', panel.getAttribute('data-game') || '');
        body.append('call_type', type);
        body.append('request_type', panel.getAttribute('data-request-type') || type);
        return fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    throw new Error((data && data.message) || 'GetCallList başarısız');
                }
                if (typeSel && data.call_type) {
                    typeSel.value = String(data.call_type);
                    if (hidden) hidden.value = String(data.call_type);
                }
                syncPanel(panel, Array.isArray(data.calls) ? data.calls : []);
                if ((!data.calls || !data.calls.length) && data.error) {
                    var err = panel.querySelector('.ca-call-err');
                    if (!err) {
                        err = document.createElement('p');
                        err.className = 'ca-call-err';
                        panel.querySelector('.ca-apply-form').appendChild(err);
                    }
                    err.textContent = data.error;
                    err.style.display = '';
                }
            });
    }

    document.querySelectorAll('.ca-call-panel').forEach(function (panel) {
        var reloadBtn = panel.querySelector('.ca-reload-calls');
        var typeSel = panel.querySelector('.ca-call-type');
        var row = panel.closest('tr');
        var cancelId = row ? row.querySelector('.ca-cancel-id') : null;
        var cancelRtp = row ? row.querySelector('.ca-cancel-rtp') : null;

        if (reloadBtn) {
            reloadBtn.addEventListener('click', function () {
                reloadBtn.disabled = true;
                loadCalls(panel).catch(function (e) {
                    alert(e && e.message ? e.message : 'Ağ hatası');
                }).finally(function () {
                    reloadBtn.disabled = false;
                });
            });
        }
        if (typeSel) {
            typeSel.addEventListener('change', function () {
                var hidden = panel.querySelector('.ca-call-type-hidden');
                if (hidden) hidden.value = typeSel.value;
                loadCalls(panel).catch(function (e) {
                    alert(e && e.message ? e.message : 'Ağ hatası');
                });
            });
        }
        if (cancelId && cancelRtp) {
            cancelId.addEventListener('change', function () {
                var opt = cancelId.options[cancelId.selectedIndex];
                var rtp = opt ? opt.getAttribute('data-rtp') : '';
                if (!rtp) return;
                Array.prototype.forEach.call(cancelRtp.options, function (o) {
                    if (o.value === rtp) cancelRtp.value = rtp;
                });
            });
        }
    });
})();
</script>
