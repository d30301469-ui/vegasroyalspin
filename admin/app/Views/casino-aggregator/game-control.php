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
    .ca-card { border: 1px solid var(--border); border-radius: 14px; background: var(--bg-card); padding: 14px; margin-bottom: 14px; }
    .ca-help { color: var(--t-muted); font-size: 13px; }
    .ca-inline { display:flex; gap:8px; flex-wrap:wrap; align-items:end; }
    .ca-inline .field { margin-bottom:0; min-width:140px; }
    .ca-table { width:100%; border-collapse: collapse; font-size: 12.5px; table-layout: auto; }
    .ca-table th, .ca-table td {
        text-align:left; padding:8px 8px; border-bottom:1px solid var(--border); vertical-align: middle;
    }
    .ca-table th { color: var(--t-muted); font-weight:600; font-size:11px; }
    .ca-mono { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12px; }
    .ca-num { font-variant-numeric: tabular-nums; }
    .ca-empty { color: var(--t-muted); }
    .ca-ops { display:flex; flex-wrap:nowrap; gap:6px; align-items:center; }
    .ca-ops select.input {
        height: 30px; padding: 0 8px; border-radius: 8px; font-size: 12px; width: auto; min-width: 72px; max-width: 110px;
    }
    .ca-ops .ca-id { max-width: 120px; min-width: 90px; }
    .ca-ops .btn { height: 30px; padding: 0 10px; border-radius: 8px; font-size: 12px; }
    .ca-sep { width:1px; height:22px; background: var(--border); flex:0 0 auto; margin: 0 2px; }
    .ca-count { color: var(--t-muted); font-size: 12px; }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Game Control</span>
        <h1 class="hero-title">Canlı <span class="accent">Oyuncular</span></h1>
        <p class="hero-sub">RTP seç → Apply · callId seç → Cancel</p>
    </div>
</section>

<?php if ($flash !== ''): ?><div class="alert alert--info" style="margin-bottom:12px"><?= $text($flash) ?></div><?php endif; ?>
<?php if (!$isActive): ?><div class="alert alert--warning" style="margin-bottom:12px">Aggregator pasif.</div><?php endif; ?>

<form class="ca-card" method="get" action="<?= $text(AdminAuth::url('/casino-aggregator/game-control')) ?>">
    <div class="ca-inline">
        <div class="field">
            <label class="field-label" for="vendor_code">Vendor</label>
            <select id="vendor_code" class="input" name="vendor_code">
                <option value="">— seç —</option>
                <?php foreach ($vendors as $v): ?>
                    <?php $code = (string) ($v['vendor_code'] ?? ''); ?>
                    <option value="<?= $text($code) ?>" <?= $code === $vendorCode ? 'selected' : '' ?>><?= $text(($v['vendor_name'] ?? $code) . ' (' . $code . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn--primary" type="submit">Yenile</button>
    </div>
</form>

<?php if ($playersError !== ''): ?>
    <div class="alert alert--warning" style="margin-bottom:12px"><?= $text($playersError) ?></div>
<?php endif; ?>

<div class="ca-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <strong>Aktif oyuncular</strong>
        <?php if ($vendorCode !== ''): ?><span class="ca-count"><?= count($players) ?> · <?= $text($vendorCode) ?></span><?php endif; ?>
    </div>

    <?php if ($vendorCode === ''): ?>
        <p class="ca-help" style="margin:0">Vendor seçin.</p>
    <?php elseif ($players === []): ?>
        <p class="ca-help" style="margin:0">Oyuncu yok.</p>
    <?php else: ?>
        <table class="ca-table">
            <thead>
            <tr>
                <th>User</th>
                <th>Game</th>
                <th>Bet</th>
                <th>Balance</th>
                <th>Call</th>
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
                $pTypeRaw = (string) ($p['requestType'] ?? '0');
                $callOpts = is_array($p['_call_options'] ?? null) ? $p['_call_options'] : [];
                $callValues = is_array($callOpts['calls'] ?? null) ? $callOpts['calls'] : [];
                $callType = (string) ($p['_call_type'] ?? CasinoAggregatorService::normalizeCallType($pTypeRaw));
                $callErr = trim((string) ($callOpts['error'] ?? ''));
                $userApplyLogs = $logsByUser[$pUser] ?? [];
                $hasCalls = $callValues !== [];
                ?>
                <tr>
                    <td class="ca-mono"><?= $text($pUser) ?></td>
                    <td class="ca-mono" title="<?= $text($pTypeRaw) ?>"><?= $text($pGame) ?></td>
                    <td class="ca-num"><?= $text(rtrim(rtrim(number_format($pBet, 2, '.', ''), '0'), '.') ?: '0') ?></td>
                    <td class="ca-num"><?= $pBal === null || $pBal === '' ? '<span class="ca-empty">—</span>' : $text(number_format((float) $pBal, 2, '.', '')) ?></td>
                    <td>
                        <div class="ca-ops ca-call-panel"
                             data-vendor="<?= $text($pVendor) ?>"
                             data-game="<?= $text($pGame) ?>"
                             data-request-type="<?= $text($pTypeRaw) ?>">

                            <form method="post" action="<?= $text(AdminAuth::url('/casino-aggregator/call-apply')) ?>" class="ca-apply-form ca-ops" style="margin:0">
                                <input type="hidden" name="_token" value="<?= $text($csrf) ?>">
                                <input type="hidden" name="vendor_code" value="<?= $text($pVendor) ?>">
                                <input type="hidden" name="game_code" value="<?= $text($pGame) ?>">
                                <input type="hidden" name="user_code" value="<?= $text($pUser) ?>">
                                <input type="hidden" name="currency_code" value="<?= $text($pCur) ?>">
                                <input type="hidden" name="bet_amount" value="<?= $text((string) $pBet) ?>">
                                <input type="hidden" class="ca-call-type-hidden" name="call_type" value="<?= $text($callType) ?>">

                                <select class="input ca-call-type" title="Tip" aria-label="callType">
                                    <option value="0" <?= $callType === '0' ? 'selected' : '' ?>>Base</option>
                                    <option value="1" <?= $callType === '1' ? 'selected' : '' ?>>Free</option>
                                </select>

                                <select name="call_rtp" class="input ca-call-rtp" title="RTP" aria-label="callRtp" <?= $hasCalls ? '' : 'disabled' ?> required>
                                    <?php if (!$hasCalls): ?>
                                        <option value=""><?= $callErr !== '' ? 'Hata' : 'RTP' ?></option>
                                    <?php else: ?>
                                        <?php foreach ($callValues as $rtp): ?>
                                            <option value="<?= $text((string) $rtp) ?>"><?= $text((string) $rtp) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>

                                <button class="btn btn--primary btn--xs ca-apply-btn" type="submit" <?= $hasCalls ? '' : 'disabled' ?>>Apply</button>
                            </form>

                            <span class="ca-sep" aria-hidden="true"></span>

                            <form method="post" action="<?= $text(AdminAuth::url('/casino-aggregator/call-cancel')) ?>" class="ca-cancel-form ca-ops" style="margin:0">
                                <input type="hidden" name="_token" value="<?= $text($csrf) ?>">
                                <input type="hidden" name="vendor_code" value="<?= $text($pVendor) ?>">
                                <input type="hidden" name="game_code" value="<?= $text($pGame) ?>">
                                <input type="hidden" name="user_code" value="<?= $text($pUser) ?>">
                                <input type="hidden" name="currency_code" value="<?= $text($pCur) ?>">
                                <input type="hidden" name="bet_amount" value="<?= $text((string) $pBet) ?>">

                                <select name="call_rtp" class="input ca-cancel-rtp" title="RTP" aria-label="cancelRtp" <?= $hasCalls ? '' : 'disabled' ?> required>
                                    <?php if (!$hasCalls): ?>
                                        <option value="">RTP</option>
                                    <?php else: ?>
                                        <?php foreach ($callValues as $rtp): ?>
                                            <option value="<?= $text((string) $rtp) ?>"><?= $text((string) $rtp) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>

                                <select name="call_id" class="input ca-cancel-id ca-id" title="callId" aria-label="callId" required>
                                    <option value="">ID</option>
                                    <?php foreach ($userApplyLogs as $log): ?>
                                        <?php
                                        $cid = (int) ($log['call_id'] ?? 0);
                                        if ($cid <= 0) {
                                            continue;
                                        }
                                        ?>
                                        <option value="<?= $cid ?>" data-rtp="<?= $text((string) ($log['call_rtp'] ?? '')) ?>">#<?= $cid ?></option>
                                    <?php endforeach; ?>
                                </select>

                                <button class="btn btn--ghost btn--xs" type="submit">Cancel</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<details class="ca-card">
    <summary style="cursor:pointer;font-weight:600">Call history</summary>
    <form method="get" action="<?= $text(AdminAuth::url('/casino-aggregator/game-control')) ?>" style="margin-top:12px">
        <input type="hidden" name="load_history" value="1">
        <?php if ($vendorCode !== ''): ?><input type="hidden" name="vendor_code" value="<?= $text($vendorCode) ?>"><?php endif; ?>
        <div class="ca-inline">
            <div class="field">
                <label class="field-label">Vendor</label>
                <select class="input" name="hist_vendor">
                    <option value="">—</option>
                    <?php foreach ($vendors as $v): ?>
                        <?php $code = (string) ($v['vendor_code'] ?? ''); ?>
                        <option value="<?= $text($code) ?>" <?= $code === $histVendor ? 'selected' : '' ?>><?= $text($code) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Başlangıç</label>
                <input class="input" type="text" name="start_time" value="<?= $text($startTime) ?>">
            </div>
            <div class="field">
                <label class="field-label">Bitiş</label>
                <input class="input" type="text" name="end_time" value="<?= $text($endTime) ?>">
            </div>
            <button class="btn btn--ghost" type="submit">Yükle</button>
        </div>
    </form>
    <?php if ($historyError !== ''): ?><div class="alert alert--warning" style="margin-top:10px"><?= $text($historyError) ?></div><?php endif; ?>
    <?php if ($history !== []): ?>
        <table class="ca-table" style="margin-top:10px">
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
    <?php endif; ?>
</details>

<?php if ($callLogs !== []): ?>
<details class="ca-card">
    <summary style="cursor:pointer;font-weight:600">Yerel call log</summary>
    <table class="ca-table" style="margin-top:10px">
        <thead><tr><th>action</th><th>user</th><th>id</th><th>rtp</th><th>money</th><th>at</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($callLogs, 0, 20) as $log): ?>
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
</details>
<?php endif; ?>

<script>
(function () {
    var url = <?= json_encode($callListUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var token = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>;

    function fill(sel, calls, emptyLabel) {
        if (!sel) return;
        sel.innerHTML = '';
        if (!calls.length) {
            var o = document.createElement('option');
            o.value = '';
            o.textContent = emptyLabel || 'RTP';
            sel.appendChild(o);
            sel.disabled = true;
            return;
        }
        calls.forEach(function (v) {
            var o = document.createElement('option');
            o.value = String(v);
            o.textContent = String(v);
            sel.appendChild(o);
        });
        sel.disabled = false;
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
                if (!data || !data.ok) throw new Error((data && data.message) || 'GetCallList hata');
                var calls = Array.isArray(data.calls) ? data.calls : [];
                fill(panel.querySelector('.ca-call-rtp'), calls, 'RTP');
                fill(panel.querySelector('.ca-cancel-rtp'), calls, 'RTP');
                var btn = panel.querySelector('.ca-apply-btn');
                if (btn) btn.disabled = !calls.length;
            });
    }

    document.querySelectorAll('.ca-call-panel').forEach(function (panel) {
        var typeSel = panel.querySelector('.ca-call-type');
        var cancelId = panel.querySelector('.ca-cancel-id');
        var cancelRtp = panel.querySelector('.ca-cancel-rtp');
        if (typeSel) {
            typeSel.addEventListener('change', function () {
                var hidden = panel.querySelector('.ca-call-type-hidden');
                if (hidden) hidden.value = typeSel.value;
                loadCalls(panel).catch(function (e) { alert(e.message || 'Hata'); });
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
