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
    .ca-table { width:100%; border-collapse: collapse; font-size: 13px; }
    .ca-table th, .ca-table td { text-align:left; padding:8px 6px; border-bottom:1px solid var(--border); vertical-align: top; }
    .ca-call-panel { display:flex; flex-direction:column; gap:8px; min-width:280px; }
    .ca-call-row { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
    .ca-call-row label { font-size:11px; color:var(--t-muted); min-width:54px; }
    .ca-call-row .input, .ca-call-row select.input { min-width:120px; padding:6px 8px; }
    .ca-inline { display:flex; gap:10px; flex-wrap:wrap; align-items:end; }
    .ca-inline .field { margin-bottom:0; min-width:160px; }
    .ca-muted { color:var(--t-muted); font-size:12px; }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Game Control API v1.0.0</span>
        <h1 class="hero-title">Canlı <span class="accent">Oyuncular</span> & Call</h1>
        <p class="hero-sub">GetCallList oranları dropdown’dan seçilir → CallApply / CallCancel</p>
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
    <div style="font-weight:700;margin-bottom:12px">Aktif oyuncular</div>
    <?php if ($vendorCode === ''): ?>
        <p class="ca-help" style="margin:0">Vendor seçin.</p>
    <?php elseif ($players === []): ?>
        <p class="ca-help" style="margin:0">Oyuncu yok veya liste boş.</p>
    <?php else: ?>
        <table class="ca-table">
            <thead>
            <tr>
                <th>userCode</th><th>game</th><th>bet</th><th>balance</th><th>targetRtp</th><th>type</th><th>Call (dropdown)</th>
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
                $pTypeRaw = (string) ($p['requestType'] ?? '0');
                $callOpts = is_array($p['_call_options'] ?? null) ? $p['_call_options'] : [];
                $callValues = is_array($callOpts['calls'] ?? null) ? $callOpts['calls'] : [];
                $callType = (string) ($p['_call_type'] ?? CasinoAggregatorService::normalizeCallType($pTypeRaw));
                $callErr = trim((string) ($callOpts['error'] ?? ''));
                $userApplyLogs = $logsByUser[$pUser] ?? [];
                ?>
                <tr data-player-row>
                    <td><?= $text($pUser) ?></td>
                    <td><?= $text($pGame) ?></td>
                    <td><?= $text((string) $pBet) ?></td>
                    <td><?= $text((string) ($p['balance'] ?? '')) ?></td>
                    <td><?= $text((string) ($p['targetRtp'] ?? '')) ?></td>
                    <td>
                        <div><?= $text($pTypeRaw) ?></div>
                        <div class="ca-muted">callType=<?= $text($callType) ?></div>
                    </td>
                    <td>
                        <div class="ca-call-panel"
                             data-vendor="<?= $text($pVendor) ?>"
                             data-game="<?= $text($pGame) ?>"
                             data-request-type="<?= $text($pTypeRaw) ?>">

                            <div class="ca-call-row">
                                <label>callType</label>
                                <select class="input ca-call-type" name="call_type_ui">
                                    <option value="0" <?= $callType === '0' ? 'selected' : '' ?>>0 — Base spin</option>
                                    <option value="1" <?= $callType === '1' ? 'selected' : '' ?>>1 — Free spin</option>
                                    <?php if ($pTypeRaw !== '' && $pTypeRaw !== '0' && $pTypeRaw !== '1'): ?>
                                        <option value="<?= $text($pTypeRaw) ?>" <?= $callType === $pTypeRaw ? 'selected' : '' ?>><?= $text($pTypeRaw) ?> (raw)</option>
                                    <?php endif; ?>
                                </select>
                                <button type="button" class="btn btn--xs ca-reload-calls">GetCallList</button>
                            </div>

                            <form method="post" action="<?= $text(AdminAuth::url('/casino-aggregator/call-apply')) ?>" class="ca-apply-form">
                                <input type="hidden" name="_token" value="<?= $text($csrf) ?>">
                                <input type="hidden" name="vendor_code" value="<?= $text($pVendor) ?>">
                                <input type="hidden" name="game_code" value="<?= $text($pGame) ?>">
                                <input type="hidden" name="user_code" value="<?= $text($pUser) ?>">
                                <input type="hidden" name="currency_code" value="<?= $text($pCur) ?>">
                                <input type="hidden" name="bet_amount" value="<?= $text((string) $pBet) ?>">
                                <input type="hidden" class="ca-call-type-hidden" name="call_type" value="<?= $text($callType) ?>">
                                <div class="ca-call-row">
                                    <label>callRtp</label>
                                    <select name="call_rtp" class="input ca-call-rtp" <?= $callValues === [] ? 'disabled' : '' ?> required>
                                        <?php if ($callValues === []): ?>
                                            <option value="">— GetCallList boş —</option>
                                        <?php else: ?>
                                            <?php foreach ($callValues as $rtp): ?>
                                                <option value="<?= $text((string) $rtp) ?>"><?= $text((string) $rtp) ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <button class="btn btn--xs btn--primary ca-apply-btn" type="submit" <?= $callValues === [] ? 'disabled' : '' ?>>CallApply</button>
                                </div>
                                <?php if ($callErr !== '' && $callValues === []): ?>
                                    <div class="ca-muted ca-call-err"><?= $text($callErr) ?></div>
                                <?php endif; ?>
                            </form>

                            <form method="post" action="<?= $text(AdminAuth::url('/casino-aggregator/call-cancel')) ?>" class="ca-cancel-form">
                                <input type="hidden" name="_token" value="<?= $text($csrf) ?>">
                                <input type="hidden" name="vendor_code" value="<?= $text($pVendor) ?>">
                                <input type="hidden" name="game_code" value="<?= $text($pGame) ?>">
                                <input type="hidden" name="user_code" value="<?= $text($pUser) ?>">
                                <input type="hidden" name="currency_code" value="<?= $text($pCur) ?>">
                                <input type="hidden" name="bet_amount" value="<?= $text((string) $pBet) ?>">
                                <div class="ca-call-row">
                                    <label>cancel</label>
                                    <select name="call_rtp" class="input ca-cancel-rtp" <?= $callValues === [] ? 'disabled' : '' ?> required>
                                        <?php if ($callValues === []): ?>
                                            <option value="">RTP…</option>
                                        <?php else: ?>
                                            <?php foreach ($callValues as $rtp): ?>
                                                <option value="<?= $text((string) $rtp) ?>"><?= $text((string) $rtp) ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <select name="call_id" class="input ca-cancel-id" required>
                                        <option value="">callId…</option>
                                        <?php foreach ($userApplyLogs as $log): ?>
                                            <?php
                                            $cid = (int) ($log['call_id'] ?? 0);
                                            if ($cid <= 0) {
                                                continue;
                                            }
                                            $label = '#' . $cid . ' rtp=' . (string) ($log['call_rtp'] ?? '') . ' @ ' . (string) ($log['created_at'] ?? '');
                                            ?>
                                            <option value="<?= $cid ?>" data-rtp="<?= $text((string) ($log['call_rtp'] ?? '')) ?>"><?= $text($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn--xs" type="submit">CallCancel</button>
                                </div>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
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
    <table class="ca-table">
        <thead><tr><th>id</th><th>user</th><th>game</th><th>rtp</th><th>bet</th><th>status</th><th>created</th></tr></thead>
        <tbody>
        <?php foreach ($history as $h): if (!is_array($h)) continue; ?>
            <tr>
                <td><?= $text((string) ($h['id'] ?? '')) ?></td>
                <td><?= $text((string) ($h['userCode'] ?? '')) ?></td>
                <td><?= $text((string) ($h['gameCode'] ?? '')) ?></td>
                <td><?= $text((string) ($h['rtp'] ?? '')) ?></td>
                <td><?= $text((string) ($h['betAmount'] ?? '')) ?></td>
                <td><?= $text((string) ($h['status'] ?? '')) ?></td>
                <td><?= $text((string) ($h['createdAt'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($callLogs !== []): ?>
<div class="ca-card">
    <div style="font-weight:700;margin-bottom:12px">Yerel call log</div>
    <table class="ca-table">
        <thead><tr><th>action</th><th>user</th><th>callId</th><th>rtp</th><th>money</th><th>at</th></tr></thead>
        <tbody>
        <?php foreach ($callLogs as $log): ?>
            <tr>
                <td><?= $text((string) ($log['action'] ?? '')) ?></td>
                <td><?= $text((string) ($log['user_code'] ?? '')) ?></td>
                <td><?= $text((string) ($log['call_id'] ?? '')) ?></td>
                <td><?= $text((string) ($log['call_rtp'] ?? '')) ?></td>
                <td><?= $text((string) ($log['money_amount'] ?? '')) ?></td>
                <td><?= $text((string) ($log['created_at'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<script>
(function () {
    var url = <?= json_encode($callListUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var token = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>;

    function fillRtpSelects(panel, calls) {
        var applySelect = panel.querySelector('.ca-call-rtp');
        var cancelSelect = panel.querySelector('.ca-cancel-rtp');
        var applyBtn = panel.querySelector('.ca-apply-btn');
        var err = panel.querySelector('.ca-call-err');
        [applySelect, cancelSelect].forEach(function (sel) {
            if (!sel) return;
            sel.innerHTML = '';
            if (!calls.length) {
                var empty = document.createElement('option');
                empty.value = '';
                empty.textContent = '— GetCallList boş —';
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
        });
        if (applyBtn) applyBtn.disabled = !calls.length;
        if (err) err.style.display = calls.length ? 'none' : '';
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
                    var found = false;
                    Array.prototype.forEach.call(typeSel.options, function (opt) {
                        if (opt.value === String(data.call_type)) found = true;
                    });
                    if (!found) {
                        var opt = document.createElement('option');
                        opt.value = String(data.call_type);
                        opt.textContent = String(data.call_type);
                        typeSel.appendChild(opt);
                    }
                    typeSel.value = String(data.call_type);
                    if (hidden) hidden.value = String(data.call_type);
                }
                fillRtpSelects(panel, Array.isArray(data.calls) ? data.calls : []);
                if ((!data.calls || !data.calls.length) && data.error) {
                    var err = panel.querySelector('.ca-call-err');
                    if (err) { err.textContent = data.error; err.style.display = ''; }
                }
            });
    }

    document.querySelectorAll('.ca-call-panel').forEach(function (panel) {
        var reloadBtn = panel.querySelector('.ca-reload-calls');
        var typeSel = panel.querySelector('.ca-call-type');
        var cancelId = panel.querySelector('.ca-cancel-id');
        var cancelRtp = panel.querySelector('.ca-cancel-rtp');

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
                if (rtp && cancelRtp) {
                    Array.prototype.forEach.call(cancelRtp.options, function (o) {
                        if (o.value === rtp) cancelRtp.value = rtp;
                    });
                }
            });
        }
    });
})();
</script>
