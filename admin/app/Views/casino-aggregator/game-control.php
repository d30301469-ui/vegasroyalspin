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
?>
<style>
    .ca-card { border: 1px solid var(--border); border-radius: 18px; background: var(--bg-card); padding: 18px; box-shadow: var(--shadow-card); margin-bottom: 18px; }
    .ca-help { color: var(--t-muted); font-size: 13px; line-height: 1.45; }
    .ca-table { width:100%; border-collapse: collapse; font-size: 13px; }
    .ca-table th, .ca-table td { text-align:left; padding:8px 6px; border-bottom:1px solid var(--border); vertical-align: top; }
    .ca-row-actions { display:flex; flex-wrap:wrap; gap:6px; }
    .ca-inline { display:flex; gap:10px; flex-wrap:wrap; align-items:end; }
    .ca-inline .field { margin-bottom:0; min-width:160px; }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Game Control API v1.0.0</span>
        <h1 class="hero-title">Canlı <span class="accent">Oyuncular</span> & Call</h1>
        <p class="hero-sub">GetCurrentPlayers → GetCallList → CallApply / CallCancel · GetCallHistory</p>
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
                <th>userCode</th><th>game</th><th>bet</th><th>balance</th><th>targetRtp</th><th>type</th><th>Call</th>
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
                $pType = (string) ($p['requestType'] ?? '0');
                ?>
                <tr>
                    <td><?= $text($pUser) ?></td>
                    <td><?= $text($pGame) ?></td>
                    <td><?= $text((string) $pBet) ?></td>
                    <td><?= $text((string) ($p['balance'] ?? '')) ?></td>
                    <td><?= $text((string) ($p['targetRtp'] ?? '')) ?></td>
                    <td><?= $text($pType) ?></td>
                    <td>
                        <div class="ca-row-actions">
                            <button type="button" class="btn btn--xs ca-load-calls"
                                data-vendor="<?= $text($pVendor) ?>"
                                data-game="<?= $text($pGame) ?>"
                                data-type="<?= $text($pType) ?>"
                                data-user="<?= $text($pUser) ?>"
                                data-currency="<?= $text($pCur) ?>"
                                data-bet="<?= $text((string) $pBet) ?>">GetCallList</button>
                            <form method="post" action="<?= $text(AdminAuth::url('/casino-aggregator/call-apply')) ?>" class="ca-apply-form" style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;">
                                <input type="hidden" name="_token" value="<?= $text($csrf) ?>">
                                <input type="hidden" name="vendor_code" value="<?= $text($pVendor) ?>">
                                <input type="hidden" name="game_code" value="<?= $text($pGame) ?>">
                                <input type="hidden" name="user_code" value="<?= $text($pUser) ?>">
                                <input type="hidden" name="currency_code" value="<?= $text($pCur) ?>">
                                <input type="hidden" name="call_type" value="<?= $text($pType) ?>">
                                <input type="hidden" name="bet_amount" value="<?= $text((string) $pBet) ?>">
                                <select name="call_rtp" class="input ca-call-select" style="min-width:100px;padding:4px 8px;" disabled>
                                    <option value="">RTP…</option>
                                </select>
                                <button class="btn btn--xs btn--primary" type="submit" disabled>CallApply</button>
                            </form>
                            <form method="post" action="<?= $text(AdminAuth::url('/casino-aggregator/call-cancel')) ?>" style="display:flex;gap:4px;align-items:center;">
                                <input type="hidden" name="_token" value="<?= $text($csrf) ?>">
                                <input type="hidden" name="vendor_code" value="<?= $text($pVendor) ?>">
                                <input type="hidden" name="game_code" value="<?= $text($pGame) ?>">
                                <input type="hidden" name="user_code" value="<?= $text($pUser) ?>">
                                <input type="hidden" name="currency_code" value="<?= $text($pCur) ?>">
                                <input type="hidden" name="bet_amount" value="<?= $text((string) $pBet) ?>">
                                <input class="input" type="text" name="call_rtp" placeholder="rtp" style="width:70px;padding:4px 8px;">
                                <input class="input" type="text" name="call_id" placeholder="callId" style="width:70px;padding:4px 8px;" required>
                                <button class="btn btn--xs" type="submit">CallCancel</button>
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
        <thead><tr><th>action</th><th>user</th><th>callId</th><th>money</th><th>at</th></tr></thead>
        <tbody>
        <?php foreach ($callLogs as $log): ?>
            <tr>
                <td><?= $text((string) ($log['action'] ?? '')) ?></td>
                <td><?= $text((string) ($log['user_code'] ?? '')) ?></td>
                <td><?= $text((string) ($log['call_id'] ?? '')) ?></td>
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
    document.querySelectorAll('.ca-load-calls').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('td');
            if (!row) return;
            var form = row.querySelector('.ca-apply-form');
            var select = row.querySelector('.ca-call-select');
            var applyBtn = form ? form.querySelector('button[type="submit"]') : null;
            var body = new FormData();
            body.append('_token', token);
            body.append('vendor_code', btn.getAttribute('data-vendor') || '');
            body.append('game_code', btn.getAttribute('data-game') || '');
            body.append('call_type', btn.getAttribute('data-type') || '0');
            btn.disabled = true;
            fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    btn.disabled = false;
                    if (!data || !data.ok || !Array.isArray(data.calls)) {
                        alert((data && data.message) || 'GetCallList başarısız');
                        return;
                    }
                    if (!select) return;
                    select.innerHTML = '';
                    data.calls.forEach(function (v) {
                        var opt = document.createElement('option');
                        opt.value = String(v);
                        opt.textContent = String(v);
                        select.appendChild(opt);
                    });
                    select.disabled = data.calls.length === 0;
                    if (applyBtn) applyBtn.disabled = data.calls.length === 0;
                })
                .catch(function (e) {
                    btn.disabled = false;
                    alert(e && e.message ? e.message : 'Ağ hatası');
                });
        });
    });
})();
</script>
