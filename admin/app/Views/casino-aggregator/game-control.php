<?php

$configRow = is_array($configRow ?? null) ? $configRow : [];
$vendors = is_array($vendors ?? null) ? $vendors : [];
$players = is_array($players ?? null) ? $players : [];
$history = is_array($history ?? null) ? $history : [];
$callLogs = is_array($callLogs ?? null) ? $callLogs : [];
$logUserMap = is_array($logUserMap ?? null) ? $logUserMap : [];
$logGameMap = is_array($logGameMap ?? null) ? $logGameMap : [];
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

$localUserLabel = static function (?array $profile, string $fallbackCode) use ($text): string {
    if (!is_array($profile)) {
        return $text($fallbackCode !== '' ? $fallbackCode : '—');
    }
    $full = trim((string) ($profile['full_name'] ?? ''));
    if ($full === '') {
        $full = trim((string) ($profile['name'] ?? '') . ' ' . (string) ($profile['surname'] ?? ''));
    }
    if ($full === '') {
        $full = trim((string) ($profile['username'] ?? ''));
    }
    if ($full === '') {
        $full = $fallbackCode !== '' ? $fallbackCode : '—';
    }
    return $text($full);
};

$localGameLabel = static function (?array $game, string $fallbackCode) use ($text): string {
    if (!is_array($game)) {
        return $text($fallbackCode !== '' ? $fallbackCode : '—');
    }
    $name = trim((string) ($game['game_name'] ?? ''));
    if ($name === '') {
        $name = $fallbackCode !== '' ? $fallbackCode : '—';
    }
    return $text($name);
};

$statusBadge = static function (mixed $status): array {
    $raw = trim((string) ($status ?? ''));
    $lower = function_exists('mb_strtolower') ? mb_strtolower($raw, 'UTF-8') : strtolower($raw);
    if ($raw === '' || $raw === '0') {
        return ['badge', $raw !== '' ? $raw : '—'];
    }
    if (str_contains($lower, 'cancel') || str_contains($lower, 'fail') || $raw === '2') {
        return ['badge danger', $raw];
    }
    if (str_contains($lower, 'pending') || str_contains($lower, 'wait') || $raw === '0') {
        return ['badge warning', $raw];
    }
    if (str_contains($lower, 'success') || str_contains($lower, 'done') || str_contains($lower, 'complete') || $raw === '1') {
        return ['badge success', $raw];
    }

    return ['badge info', $raw];
};

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
    .gc-toolbar { display:flex; gap:10px; flex-wrap:wrap; align-items:end; }
    .gc-toolbar .field { margin-bottom:0; min-width:180px; }
    .gc-ops { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
    .gc-ops select.input {
        height: 32px; padding: 0 8px; border-radius: 8px; font-size: 12px;
        width: auto; min-width: 76px; max-width: 140px;
    }
    .gc-ops .gc-id { max-width: 128px; min-width: 96px; }
    .gc-ops .gc-money {
        height: 32px; padding: 0 8px; border-radius: 8px; font-size: 12px;
        width: 96px; min-width: 84px; max-width: 110px;
    }
    .gc-ops .btn { height: 32px; padding: 0 10px; font-size: 12px; }
    .gc-sep { width:1px; height:22px; background: var(--border); flex:0 0 auto; margin: 0 2px; }
    .gc-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; }
    .gc-num { font-variant-numeric: tabular-nums; }
    .gc-muted { color: var(--t-muted); font-size: 13px; }
    .gc-user-name { font-weight: 600; line-height: 1.25; }
    .gc-user-meta { display:flex; flex-wrap:wrap; gap:6px; align-items:center; margin-top:4px; color: var(--t-muted); font-size: 12px; }
    .gc-details summary { cursor:pointer; list-style:none; display:flex; align-items:center; gap:10px; }
    .gc-details summary::-webkit-details-marker { display:none; }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Oyunlar · Casino Aggregator</span>
        <h1 class="hero-title">Game <span class="accent">Control</span></h1>
        <p class="hero-sub">Canlı oyunculara CallApply / CallCancel — oran seç, kazanç miktarını gir, uygula.</p>
    </div>
    <div class="hero-actions">
        <span class="badge dot <?= $isActive ? 'success' : 'danger' ?>"><?= $isActive ? 'Aggregator aktif' : 'Aggregator pasif' ?></span>
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/casino-aggregator/settings')) ?>">Aggregator Ayarları</a>
    </div>
</section>

<?php if ($playersError !== ''): ?>
    <script type="application/json" data-admin-toast><?= json_encode(['type' => 'error', 'message' => $playersError], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endif; ?>
<?php if ($historyError !== ''): ?>
    <script type="application/json" data-admin-toast><?= json_encode(['type' => 'error', 'message' => $historyError], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endif; ?>

<section class="card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">Filtre</span>
            <h2 class="card-title">Vendor seçimi</h2>
        </div>
        <?php if ($vendorCode !== ''): ?>
            <span class="badge dot info"><?= $text($vendorCode) ?></span>
        <?php endif; ?>
    </div>
    <form method="get" action="<?= $text(AdminAuth::url('/casino-aggregator/game-control')) ?>">
        <div class="gc-toolbar">
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
</section>

<section class="card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">GetCurrentPlayers</span>
            <h2 class="card-title">Aktif oyuncular</h2>
        </div>
        <?php if ($vendorCode !== ''): ?>
            <span class="badge dot <?= $players === [] ? 'warning' : 'success' ?>"><?= count($players) ?> oyuncu</span>
        <?php endif; ?>
    </div>

    <?php if ($vendorCode === ''): ?>
        <p class="gc-muted" style="margin:0">Listeyi görmek için vendor seçin.</p>
    <?php elseif ($players === []): ?>
        <p class="gc-muted" style="margin:0">Bu vendor için aktif oyuncu yok.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Kullanıcı</th>
                    <th>Oyun</th>
                    <th>Tip</th>
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
                    $typeBadge = $callType === '1' ? 'badge purple' : 'badge primary';
                    $typeLabel = $callType === '1' ? 'Free' : 'Base';
                    $localUser = is_array($p['_local_user'] ?? null) ? $p['_local_user'] : null;
                    $localUsername = is_array($localUser) ? trim((string) ($localUser['username'] ?? '')) : '';
                    $localId = is_array($localUser) ? (int) ($localUser['id'] ?? 0) : 0;
                    $localGame = is_array($p['_local_game'] ?? null) ? $p['_local_game'] : null;
                    $gameName = trim((string) ($p['_game_name'] ?? ($localGame['game_name'] ?? '')));
                    ?>
                    <tr>
                        <td>
                            <div class="gc-user-name"><?= $localUserLabel($localUser, $pUser) ?></div>
                            <div class="gc-user-meta">
                                <?php if ($localUsername !== ''): ?>
                                    <span class="gc-mono">@<?= $text($localUsername) ?></span>
                                <?php endif; ?>
                                <?php if ($localId > 0): ?>
                                    <span class="badge">#<?= $localId ?></span>
                                <?php elseif ($pUser !== ''): ?>
                                    <span class="gc-mono"><?= $text($pUser) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="gc-user-name"><?= $gameName !== '' ? $text($gameName) : $text($pGame) ?></div>
                            <div class="gc-user-meta">
                                <?php if ($pGame !== ''): ?>
                                    <span class="gc-mono"><?= $text($pGame) ?></span>
                                <?php endif; ?>
                                <?php if ($pCur !== ''): ?>
                                    <span class="badge"><?= $text($pCur) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="<?= $typeBadge ?>" title="<?= $text($pTypeRaw) ?>"><?= $typeLabel ?></span>
                            <?php if ($callErr !== ''): ?>
                                <span class="badge danger" title="<?= $text($callErr) ?>">RTP yok</span>
                            <?php elseif ($hasCalls): ?>
                                <span class="badge success"><?= count($callValues) ?> RTP</span>
                            <?php else: ?>
                                <span class="badge warning">Bekliyor</span>
                            <?php endif; ?>
                        </td>
                        <td class="gc-num"><?= $text(rtrim(rtrim(number_format($pBet, 2, '.', ''), '0'), '.') ?: '0') ?></td>
                        <td class="gc-num"><?= $pBal === null || $pBal === '' ? '<span class="gc-muted">—</span>' : $text(number_format((float) $pBal, 2, '.', '')) ?></td>
                        <td>
                            <div class="gc-ops ca-call-panel"
                                 data-vendor="<?= $text($pVendor) ?>"
                                 data-game="<?= $text($pGame) ?>"
                                 data-request-type="<?= $text($pTypeRaw) ?>">

                                <form method="post" action="<?= $text(AdminAuth::url('/casino-aggregator/call-apply')) ?>" class="ca-apply-form gc-ops" style="margin:0">
                                    <input type="hidden" name="_token" value="<?= $text($csrf) ?>">
                                    <input type="hidden" name="vendor_code" value="<?= $text($pVendor) ?>">
                                    <input type="hidden" name="game_code" value="<?= $text($pGame) ?>">
                                    <input type="hidden" name="user_code" value="<?= $text($pUser) ?>">
                                    <input type="hidden" name="currency_code" value="<?= $text($pCur) ?>">
                                    <input type="hidden" class="ca-bet-amount" name="bet_amount" value="<?= $text((string) $pBet) ?>">
                                    <input type="hidden" class="ca-call-type-hidden" name="call_type" value="<?= $text($callType) ?>">

                                    <select class="input ca-call-type" title="Tip" aria-label="callType">
                                        <option value="0" <?= $callType === '0' ? 'selected' : '' ?>>Base</option>
                                        <option value="1" <?= $callType === '1' ? 'selected' : '' ?>>Free</option>
                                    </select>

                                    <select name="call_rtp" class="input ca-call-rtp" title="Oran (callRtp)" aria-label="callRtp" <?= $hasCalls ? '' : 'disabled' ?> required>
                                        <?php if (!$hasCalls): ?>
                                            <option value=""><?= $callErr !== '' ? 'Hata' : 'Oran' ?></option>
                                        <?php else: ?>
                                            <?php foreach ($callValues as $rtp): ?>
                                                <?php
                                                $rtpVal = (float) $rtp;
                                                $estMoney = round($pBet * $rtpVal, 2);
                                                ?>
                                                <option value="<?= $text((string) $rtp) ?>" data-money="<?= $text((string) $estMoney) ?>">
                                                    ×<?= $text(rtrim(rtrim(number_format($rtpVal, 4, '.', ''), '0'), '.') ?: '0') ?> → <?= $text(number_format($estMoney, 2, '.', '')) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>

                                    <input class="input gc-money ca-money-amount" type="number" name="money_amount" step="0.01" min="0.01"
                                           title="Kazanç miktarı" aria-label="Kazanç miktarı" placeholder="Kazanç"
                                           value="<?= $hasCalls && $pBet > 0 ? $text((string) round($pBet * (float) $callValues[0], 2)) : '' ?>"
                                           <?= $hasCalls ? 'required' : 'disabled' ?>>

                                    <button class="btn btn--primary btn--sm ca-apply-btn" type="submit" <?= $hasCalls ? '' : 'disabled' ?>>Apply</button>
                                </form>

                                <span class="gc-sep" aria-hidden="true"></span>

                                <form method="post" action="<?= $text(AdminAuth::url('/casino-aggregator/call-cancel')) ?>" class="ca-cancel-form gc-ops" style="margin:0">
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

                                    <select name="call_id" class="input ca-cancel-id gc-id" title="callId" aria-label="callId" required>
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

                                    <button class="btn btn--ghost btn--sm" type="submit">Cancel</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<details class="card gc-details">
    <summary class="card-head" style="border-bottom:0;margin:0">
        <div class="card-title-wrap">
            <span class="eyebrow">GetCallHistory</span>
            <h2 class="card-title" style="margin:0">Call history</h2>
        </div>
        <?php if ($history !== []): ?>
            <span class="badge dot info"><?= count($history) ?> kayıt</span>
        <?php endif; ?>
    </summary>
    <div style="padding-top:4px">
        <form method="get" action="<?= $text(AdminAuth::url('/casino-aggregator/game-control')) ?>">
            <input type="hidden" name="load_history" value="1">
            <?php if ($vendorCode !== ''): ?><input type="hidden" name="vendor_code" value="<?= $text($vendorCode) ?>"><?php endif; ?>
            <div class="gc-toolbar">
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

        <?php if ($history !== []): ?>
            <div class="table-wrap" style="margin-top:14px">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kullanıcı</th>
                        <th>Oyun</th>
                        <th>RTP</th>
                        <th>Bet</th>
                        <th>Durum</th>
                        <th>Oluşturma</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $h): if (!is_array($h)) continue; ?>
                        <?php
                        [$badgeClass, $badgeLabel] = $statusBadge($h['status'] ?? '');
                        $hUser = (string) ($h['userCode'] ?? '');
                        $hLocal = is_array($h['_local_user'] ?? null) ? $h['_local_user'] : null;
                        $hGame = (string) ($h['gameCode'] ?? '');
                        $hLocalGame = is_array($h['_local_game'] ?? null) ? $h['_local_game'] : null;
                        $hGameName = trim((string) ($h['_game_name'] ?? ($hLocalGame['game_name'] ?? '')));
                        ?>
                        <tr>
                            <td class="gc-mono"><?= $text((string) ($h['id'] ?? '')) ?></td>
                            <td>
                                <div class="gc-user-name"><?= $localUserLabel($hLocal, $hUser) ?></div>
                                <?php if ($hUser !== ''): ?>
                                    <div class="gc-user-meta"><span class="gc-mono"><?= $text($hUser) ?></span></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="gc-user-name"><?= $hGameName !== '' ? $text($hGameName) : $text($hGame) ?></div>
                                <?php if ($hGame !== ''): ?>
                                    <div class="gc-user-meta"><span class="gc-mono"><?= $text($hGame) ?></span></div>
                                <?php endif; ?>
                            </td>
                            <td class="gc-num"><span class="badge"><?= $text((string) ($h['rtp'] ?? '')) ?></span></td>
                            <td class="gc-num"><?= $text((string) ($h['betAmount'] ?? '')) ?></td>
                            <td><span class="<?= $text($badgeClass) ?>"><?= $text($badgeLabel) ?></span></td>
                            <td><?= $text((string) ($h['createdAt'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (isset($_GET['load_history'])): ?>
            <p class="gc-muted" style="margin:14px 0 0">Kayıt bulunamadı.</p>
        <?php endif; ?>
    </div>
</details>

<?php if ($callLogs !== []): ?>
<details class="card gc-details">
    <summary class="card-head" style="border-bottom:0;margin:0">
        <div class="card-title-wrap">
            <span class="eyebrow">Yerel</span>
            <h2 class="card-title" style="margin:0">Call log</h2>
        </div>
        <span class="badge dot info"><?= min(20, count($callLogs)) ?> / <?= count($callLogs) ?></span>
    </summary>
    <div class="table-wrap" style="margin-top:4px">
        <table class="data-table">
            <thead>
            <tr>
                <th>İşlem</th>
                <th>Kullanıcı</th>
                <th>Oyun</th>
                <th>Call ID</th>
                <th>RTP</th>
                <th>Money</th>
                <th>Zaman</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach (array_slice($callLogs, 0, 20) as $log): ?>
                <?php
                $action = (string) ($log['action'] ?? '');
                $actionBadge = match ($action) {
                    'CallApply' => 'badge success',
                    'CallCancel' => 'badge warning',
                    default => 'badge',
                };
                $logUser = (string) ($log['user_code'] ?? '');
                $logLocal = is_array($logUserMap[$logUser] ?? null) ? $logUserMap[$logUser] : null;
                $logVendor = (string) ($log['vendor_code'] ?? '');
                $logGame = (string) ($log['game_code'] ?? '');
                $logGameKey = $logVendor . '|' . $logGame;
                $logLocalGame = is_array($logGameMap[$logGameKey] ?? null) ? $logGameMap[$logGameKey] : null;
                $logGameName = trim((string) ($logLocalGame['game_name'] ?? ''));
                ?>
                <tr>
                    <td><span class="<?= $actionBadge ?>"><?= $text($action) ?></span></td>
                    <td>
                        <div class="gc-user-name"><?= $localUserLabel($logLocal, $logUser) ?></div>
                        <?php if ($logUser !== ''): ?>
                            <div class="gc-user-meta"><span class="gc-mono"><?= $text($logUser) ?></span></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="gc-user-name"><?= $localGameLabel($logLocalGame, $logGame) ?></div>
                        <?php if ($logGame !== ''): ?>
                            <div class="gc-user-meta"><span class="gc-mono"><?= $text($logGame) ?></span></div>
                        <?php endif; ?>
                    </td>
                    <td class="gc-mono"><?= $text((string) ($log['call_id'] ?? '')) ?></td>
                    <td class="gc-num"><?= $text((string) ($log['call_rtp'] ?? '')) ?></td>
                    <td class="gc-num"><?= $text((string) ($log['money_amount'] ?? '')) ?></td>
                    <td><?= $text((string) ($log['created_at'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</details>
<?php endif; ?>

<script>
(function () {
    var url = <?= json_encode($callListUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var token = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>;

    function toast(type, message) {
        if (window.AdminToast && typeof window.AdminToast[type] === 'function') {
            window.AdminToast[type](message);
            return;
        }
        if (window.AdminToast && typeof window.AdminToast.show === 'function') {
            window.AdminToast.show({ type: type, message: message });
        }
    }

    function panelBet(panel) {
        var betInput = panel.querySelector('.ca-bet-amount');
        var bet = betInput ? parseFloat(betInput.value || '0') : 0;
        return Number.isFinite(bet) ? bet : 0;
    }

    function formatRatio(v) {
        var n = Number(v);
        if (!Number.isFinite(n)) return String(v);
        return String(n);
    }

    function formatMoney(v) {
        var n = Number(v);
        if (!Number.isFinite(n)) return '0.00';
        return n.toFixed(2);
    }

    function syncMoneyFromRtp(panel) {
        var rtpSel = panel.querySelector('.ca-call-rtp');
        var moneyInput = panel.querySelector('.ca-money-amount');
        if (!rtpSel || !moneyInput) return;
        var opt = rtpSel.options[rtpSel.selectedIndex];
        if (!opt || !opt.value) {
            moneyInput.value = '';
            return;
        }
        var money = opt.getAttribute('data-money');
        if (money == null || money === '') {
            money = formatMoney(panelBet(panel) * parseFloat(opt.value || '0'));
        }
        moneyInput.value = money;
    }

    function fillRtp(sel, calls, bet, emptyLabel, withMoneyLabels) {
        if (!sel) return;
        sel.innerHTML = '';
        if (!calls.length) {
            var empty = document.createElement('option');
            empty.value = '';
            empty.textContent = emptyLabel || 'Oran';
            sel.appendChild(empty);
            sel.disabled = true;
            return;
        }
        calls.forEach(function (v) {
            var rtp = Number(v);
            var money = Number.isFinite(rtp) ? Math.round(bet * rtp * 100) / 100 : 0;
            var o = document.createElement('option');
            o.value = String(v);
            o.setAttribute('data-money', formatMoney(money));
            o.textContent = withMoneyLabels
                ? ('×' + formatRatio(v) + ' → ' + formatMoney(money))
                : formatRatio(v);
            sel.appendChild(o);
        });
        sel.disabled = false;
    }

    function setMoneyEnabled(panel, enabled) {
        var moneyInput = panel.querySelector('.ca-money-amount');
        var btn = panel.querySelector('.ca-apply-btn');
        if (moneyInput) {
            moneyInput.disabled = !enabled;
            moneyInput.required = enabled;
            if (!enabled) moneyInput.value = '';
        }
        if (btn) btn.disabled = !enabled;
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
                if (!data || !data.ok) throw new Error((data && data.message) || 'GetCallList başarısız');
                var calls = Array.isArray(data.calls) ? data.calls : [];
                var bet = panelBet(panel);
                fillRtp(panel.querySelector('.ca-call-rtp'), calls, bet, 'Oran', true);
                fillRtp(panel.querySelector('.ca-cancel-rtp'), calls, bet, 'Oran', false);
                setMoneyEnabled(panel, calls.length > 0);
                if (calls.length) {
                    syncMoneyFromRtp(panel);
                } else {
                    toast('warning', 'Bu tip için oran listesi boş.');
                }
            });
    }

    document.querySelectorAll('.ca-call-panel').forEach(function (panel) {
        var typeSel = panel.querySelector('.ca-call-type');
        var rtpSel = panel.querySelector('.ca-call-rtp');
        var applyForm = panel.querySelector('.ca-apply-form');
        var cancelId = panel.querySelector('.ca-cancel-id');
        var cancelRtp = panel.querySelector('.ca-cancel-rtp');

        if (typeSel) {
            typeSel.addEventListener('change', function () {
                var hidden = panel.querySelector('.ca-call-type-hidden');
                if (hidden) hidden.value = typeSel.value;
                loadCalls(panel).catch(function (e) {
                    toast('error', e.message || 'GetCallList hatası');
                });
            });
        }

        if (rtpSel) {
            rtpSel.addEventListener('change', function () {
                syncMoneyFromRtp(panel);
            });
        }

        if (applyForm) {
            applyForm.addEventListener('submit', function (event) {
                var moneyInput = panel.querySelector('.ca-money-amount');
                var money = moneyInput ? parseFloat(moneyInput.value || '0') : 0;
                if (!Number.isFinite(money) || money <= 0) {
                    event.preventDefault();
                    toast('error', 'Kullanıcıya verilecek kazanç miktarı zorunludur.');
                    if (moneyInput) moneyInput.focus();
                }
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
