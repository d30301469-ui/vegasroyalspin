<?php

declare(strict_types=1);

/**
 * GSC+ / Gaming Soft — canlı API smoke testi (oyun launch YOK).
 *
 * Operator API + seamless wallet callback + yerel DB tutarlılığı.
 * Sonuçlar: logs/gamingsoft/probe_YYYYMMDD_HHMMSS.{json,txt} ve latest_probe.*
 *
 * Usage: php tools/gsc_api_probe.php
 * Opsiyonel: GSC_PROBE_CALLBACK_BASE=https://admin.vegasroyalspin.com
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/admin/app/Core/AdminDatabase.php';
require_once BASE_PATH . '/services/GscPlusService.php';
require_once BASE_PATH . '/services/GscFileLog.php';

$startedAt = microtime(true);
$stamp = gmdate('Ymd_His');
$logDir = GscFileLog::dir();
if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
    fwrite(STDERR, "Cannot create {$logDir}\n");
    exit(1);
}

$callbackBase = rtrim((string) (getenv('GSC_PROBE_CALLBACK_BASE') ?: 'https://admin.vegasroyalspin.com'), '/');
$wwwBase = rtrim((string) (getenv('GSC_PROBE_WWW_BASE') ?: 'https://www.vegasroyalspin.com'), '/');

/** @var list<array<string,mixed>> $results */
$results = [];
$findings = [];

$add = static function (string $id, string $group, bool $ok, string $summary, array $detail = []) use (&$results): void {
    $results[] = [
        'id' => $id,
        'group' => $group,
        'ok' => $ok,
        'summary' => $summary,
        'detail' => $detail,
    ];
    $icon = $ok ? 'OK' : 'FAIL';
    echo "[{$icon}] {$id}: {$summary}\n";
};

$httpJson = static function (string $method, string $url, ?array $json = null, int $timeout = 30): array {
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'http' => 0, 'error' => 'curl_init failed', 'body' => null, 'raw' => ''];
    }
    $headers = ['Accept: application/json'];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FOLLOWLOCATION => true,
    ];
    if ($json !== null) {
        $encoded = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_HTTPHEADER] = $headers;
        $opts[CURLOPT_POSTFIELDS] = $encoded === false ? '{}' : $encoded;
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    return [
        'ok' => $errno === 0 && $http > 0,
        'http' => $http,
        'error' => $errno !== 0 ? $error : null,
        'body' => is_array($decoded) ? $decoded : null,
        'raw' => is_string($raw) ? (strlen($raw) > 4000 ? substr($raw, 0, 4000) . '…' : $raw) : '',
    ];
};

try {
    $pdo = AdminDatabase::pdo();
} catch (Throwable $e) {
    $pdo = null;
    $add('db.connect', 'local', true, 'WARN: ' . $e->getMessage() . ' — remote-only moda geçiliyor');
}

$operatorCode = trim((string) (getenv('GSC_OPERATOR_CODE') ?: ''));
$secretKey = (string) (getenv('GSC_SECRET_KEY') ?: '');
$operatorUrl = rtrim((string) (getenv('GSC_OPERATOR_URL') ?: ''), '/');
$currency = strtoupper(trim((string) (getenv('GSC_CURRENCY') ?: 'IDR')));
$active = true;
$cfg = [];

$credFile = $logDir . '/credentials.local.php';
if (($operatorCode === '' || $secretKey === '') && is_file($credFile)) {
    /** @var mixed $cred */
    $cred = require $credFile;
    if (is_array($cred)) {
        $operatorCode = $operatorCode !== '' ? $operatorCode : trim((string) ($cred['operator_code'] ?? ''));
        $secretKey = $secretKey !== '' ? $secretKey : (string) ($cred['secret_key'] ?? '');
        $operatorUrl = $operatorUrl !== '' ? $operatorUrl : rtrim((string) ($cred['operator_url'] ?? ''), '/');
        $currency = $currency !== '' ? $currency : strtoupper(trim((string) ($cred['currency'] ?? 'IDR')));
    }
}

if ($pdo instanceof PDO) {
    GscPlusService::bootstrap($pdo);
    $cfg = GscPlusService::config($pdo);
    $operatorCode = trim((string) ($cfg['operator_code'] ?? '')) ?: $operatorCode;
    $secretKey = (string) ($cfg['secret_key'] ?? '') ?: $secretKey;
    $operatorUrl = rtrim((string) ($cfg['operator_url'] ?? ''), '/') ?: $operatorUrl;
    $currency = GscPlusService::configCurrency($cfg);
    $active = (int) ($cfg['is_active'] ?? 0) === 1;
}

if ($operatorUrl === '') {
    $operatorUrl = 'https://staging.gsimw.com';
}

$add(
    'config',
    'local',
    $operatorCode !== '' && $secretKey !== '' && $operatorUrl !== '',
    sprintf(
        'operator=%s url=%s currency=%s active=%s secret_len=%d source=%s',
        $operatorCode,
        $operatorUrl,
        $currency,
        $active ? '1' : '0',
        strlen($secretKey),
        $pdo instanceof PDO ? 'db' : 'env/file'
    ),
    ['operator_code' => $operatorCode, 'operator_url' => $operatorUrl, 'currency' => $currency, 'is_active' => $active]
);

if (!$active && $pdo instanceof PDO) {
    $findings[] = 'gsc_config.is_active=0 — wallet callback 503 döner, sağlayıcı balance alamaz.';
}
if ($operatorCode === '' || $secretKey === '') {
    $findings[] = 'operator_code/secret_key eksik — canlı testler atlandı. Env: GSC_OPERATOR_CODE / GSC_SECRET_KEY / GSC_OPERATOR_URL veya logs/gamingsoft/credentials.local.php';
    goto finish;
}

// ── Local catalog / DB ───────────────────────────────────────────────
$probeMember = 'Haki0258';
if (!($pdo instanceof PDO)) {
    $add('catalog.status', 'local', true, 'SKIP — DB yok');
    $add('db.traffic', 'local', true, 'SKIP — DB yok');
    $add('db.member', 'local', true, 'SKIP — DB yok; member=Haki0258 varsayılan');
} else {
    try {
        $status = GscPlusService::catalogStatus($pdo);
        $ok = !empty($status['configured']) && (int) ($status['games_live_active'] ?? 0) > 0;
        $add('catalog.status', 'local', $ok, json_encode($status, JSON_UNESCAPED_UNICODE) ?: '', $status);
        if (!$ok) {
            $findings[] = 'Yerel katalog boş veya yapılandırılmamış.';
        }
    } catch (Throwable $e) {
        $add('catalog.status', 'local', false, $e->getMessage());
    }

    try {
        $sess = $pdo->query(
            'SELECT id, user_id, member_account, product_code, game_code, currency, status, created_at
             FROM gsc_sessions ORDER BY id DESC LIMIT 5'
        )->fetchAll(PDO::FETCH_ASSOC);
        $wl = $pdo->query(
            'SELECT id, method, member_account, status_code, error_code, created_at
             FROM gsc_wallet_logs ORDER BY id DESC LIMIT 10'
        )->fetchAll(PDO::FETCH_ASSOC);
        $sessCount = (int) $pdo->query('SELECT COUNT(*) FROM gsc_sessions')->fetchColumn();
        $wlCount = (int) $pdo->query('SELECT COUNT(*) FROM gsc_wallet_logs')->fetchColumn();
        $lastSess = is_array($sess[0] ?? null) ? (string) ($sess[0]['created_at'] ?? '') : null;
        $lastWl = is_array($wl[0] ?? null) ? (string) ($wl[0]['created_at'] ?? '') : null;
        $add(
            'db.traffic',
            'local',
            true,
            "sessions={$sessCount} wallet_logs={$wlCount} last_session={$lastSess} last_wallet={$lastWl}",
            ['recent_sessions' => $sess, 'recent_wallet_logs' => $wl]
        );
        if ($sessCount > 0 && $wlCount === 0) {
            $findings[] = 'Oturum var ama wallet_logs tamamen boş — callback URL hiç gelmemiş veya farklı DB.';
        }
        if ($lastSess && $lastWl && strcmp($lastSess, $lastWl) > 0) {
            $findings[] = 'Son oturum, son wallet logdan daha yeni — launch OK ama bu oturumda provider wallet çağırmadı (veya yanlış host).';
        }
    } catch (Throwable $e) {
        $add('db.traffic', 'local', false, $e->getMessage());
    }

    try {
        $u = $pdo->prepare('SELECT id, username, balance, banned FROM users WHERE username = :u OR LOWER(username)=LOWER(:u2) LIMIT 1');
        $u->execute([':u' => $probeMember, ':u2' => $probeMember]);
        $userRow = $u->fetch(PDO::FETCH_ASSOC);
        if (is_array($userRow)) {
            $probeMember = (string) $userRow['username'];
            $add(
                'db.member',
                'local',
                (int) ($userRow['banned'] ?? 0) === 0,
                sprintf('id=%s username=%s balance=%s banned=%s', $userRow['id'], $userRow['username'], $userRow['balance'], $userRow['banned']),
                ['id' => (int) $userRow['id'], 'balance' => (float) $userRow['balance']]
            );
        } else {
            $add('db.member', 'local', false, "member {$probeMember} users tablosunda yok");
            $findings[] = "Probe member {$probeMember} yok — balance 1000 dönebilir.";
        }
    } catch (Throwable $e) {
        $add('db.member', 'local', false, $e->getMessage());
    }
}

// ── Operator APIs (no launch-game) ───────────────────────────────────
$operatorGet = static function (string $action, string $path, array $extra = []) use ($httpJson, $operatorUrl, $operatorCode, $secretKey): array {
    $requestTime = (string) time();
    // wallet-balance uses millisecond request_time
    if ($action === 'getwalletcurrencies') {
        $requestTime = (string) (int) round(microtime(true) * 1000);
    }
    $sign = GscPlusService::operatorSign($requestTime, $secretKey, $action, $operatorCode);
    $query = http_build_query($extra + [
        'operator_code' => $operatorCode,
        'sign' => $sign,
        'request_time' => $requestTime,
    ]);
    return $httpJson('GET', $operatorUrl . $path . (str_contains($path, '?') ? '&' : '?') . $query);
};

try {
    $res = $operatorGet('getwalletcurrencies', '/api/operators/wallet-balance');
    $code = (int) ($res['body']['code'] ?? -1);
    $data = is_array($res['body']['data'] ?? null) ? $res['body']['data'] : [];
    $currencies = is_array($data['currencies'] ?? null) ? $data['currencies'] : [];
    $idr = null;
    foreach ($currencies as $row) {
        if (is_array($row) && strtoupper((string) ($row['currency'] ?? '')) === 'IDR') {
            $idr = $row;
        }
    }
    $ok = $res['ok'] && $code === 0 && is_array($idr) && (float) ($idr['current_balance'] ?? 0) > 0;
    $add(
        'operator.wallet_balance',
        'operator',
        $ok,
        sprintf(
            'http=%d code=%d mode=%s currencies=%d IDR=%s',
            $res['http'],
            $code,
            !empty($data['is_credit']) ? 'credit' : 'buy-in',
            count($currencies),
            $idr ? (string) $idr['current_balance'] : 'MISSING'
        ),
        ['http' => $res['http'], 'code' => $code, 'data' => $data]
    );
    if ($code === 0 && (!is_array($idr) || (float) ($idr['current_balance'] ?? 0) <= 0)) {
        $findings[] = 'Agent IDR bakiyesi 0 veya yok — buy-in launch / oyun oturumu provider tarafında düşer.';
    }
    if ($code === 0 && !empty($data['is_credit'])) {
        $findings[] = 'Agent wallet CREDIT modunda; staging dokümanda buy-in bekleniyordu — panel ile doğrula.';
    }
    if ($code !== 0) {
        $findings[] = '3.12 wallet-balance başarısız: code=' . $code . ' ' . (string) ($res['body']['message'] ?? '');
    }
} catch (Throwable $e) {
    $add('operator.wallet_balance', 'operator', false, $e->getMessage());
    $findings[] = '3.12 wallet-balance başarısız: ' . $e->getMessage();
}

try {
    $res = $operatorGet('productlist', '/api/operators/available-products');
    $code = (int) (($res['body']['code'] ?? -1));
    $products = is_array($res['body']['products'] ?? null) ? $res['body']['products'] : (is_array($res['body']['data'] ?? null) ? $res['body']['data'] : []);
    if (!is_array($products) || $products === []) {
        $products = is_array($res['body']['available_products'] ?? null) ? $res['body']['available_products'] : [];
    }
    // Some GSC responses omit top-level code and return the product list directly.
    if ((!is_array($products) || $products === []) && is_array($res['body']) && array_is_list($res['body'])) {
        $products = $res['body'];
    }
    $productOk = $res['ok'] && ($code === 0 || $code === 200 || (is_array($products) && count($products) > 0));
    $add(
        'operator.available_products',
        'operator',
        $productOk,
        sprintf('http=%d code=%d products=%d keys=%s', $res['http'], $code, is_array($products) ? count($products) : 0, is_array($res['body']) ? implode(',', array_keys($res['body'])) : ''),
        ['http' => $res['http'], 'code' => $code, 'product_count' => is_array($products) ? count($products) : 0, 'sample' => array_slice(is_array($products) ? $products : [], 0, 3), 'body_keys' => is_array($res['body']) ? array_keys($res['body']) : []]
    );
} catch (Throwable $e) {
    $add('operator.available_products', 'operator', false, $e->getMessage());
}

// Provider games — Pragmatic LC 1006 / IDR only (no launch)
try {
    $res = $operatorGet('gamelist', '/api/operators/provider-games', [
        'product_code' => 1006,
        'game_type' => 'LIVE_CASINO',
    ]);
    $code = (int) ($res['body']['code'] ?? -1);
    $body = is_array($res['body']) ? $res['body'] : [];
    $games = [];
    foreach (['provider_games', 'data', 'games'] as $key) {
        if (isset($body[$key]) && is_array($body[$key])) {
            $candidate = $body[$key];
            if (isset($candidate['games']) && is_array($candidate['games'])) {
                $games = $candidate['games'];
            } else {
                $games = $candidate;
            }
            break;
        }
    }
    $provider = is_array($body['provider'] ?? null) ? $body['provider'] : [];
    if ($games === [] && is_array($provider['games'] ?? null)) {
        $games = $provider['games'];
    }
    $add(
        'operator.provider_games_1006',
        'operator',
        $res['ok'] && ($code === 0 || $code === 200) && count($games) > 0,
        sprintf('http=%d code=%d games=%d keys=%s', $res['http'], $code, count($games), implode(',', array_keys($body))),
        ['http' => $res['http'], 'code' => $code, 'game_count' => count($games), 'body_keys' => array_keys($body), 'sample_codes' => array_values(array_map(
            static fn ($g) => is_array($g) ? (string) ($g['game_code'] ?? $g['code'] ?? '') : '',
            array_slice($games, 0, 5)
        ))]
    );
} catch (Throwable $e) {
    $add('operator.provider_games_1006', 'operator', false, $e->getMessage());
}

try {
    $endMs = (int) round(microtime(true) * 1000);
    $startMs = $endMs - (4 * 60 * 1000);
    $res = $operatorGet('getwagers', '/api/operators/wagers', [
        'start' => $startMs,
        'end' => $endMs,
        'size' => 50,
    ]);
    $code = (int) ($res['body']['code'] ?? -1);
    $wagers = is_array($res['body']['wagers'] ?? null) ? $res['body']['wagers'] : [];
    $pagination = is_array($res['body']['pagination'] ?? null) ? $res['body']['pagination'] : null;
    $wagerOk = $res['ok'] && ($code === 0 || $code === 200 || $pagination !== null);
    $add(
        'operator.wagers',
        'operator',
        $wagerOk,
        sprintf('http=%d code=%d wagers=%d', $res['http'], $code, count($wagers)),
        ['http' => $res['http'], 'code' => $code, 'count' => count($wagers), 'pagination' => $pagination, 'body_keys' => is_array($res['body']) ? array_keys($res['body']) : []]
    );
    if ($wagers !== [] && is_array($wagers[0])) {
        $first = $wagers[0];
        $wid = (string) ($first['id'] ?? $first['wager_code'] ?? '');
        if ($wid !== '') {
            $one = $operatorGet('getwager', '/api/operators/wagers/' . rawurlencode($wid));
            $ocode = (int) ($one['body']['code'] ?? -1);
            $add('operator.wager_one', 'operator', $one['ok'] && ($ocode === 0 || $ocode === 200), 'id=' . $wid . ' code=' . $ocode, ['body_keys' => is_array($one['body']) ? array_keys($one['body']) : []]);
            $wagerCode = (string) ($first['wager_code'] ?? '');
            if ($wagerCode !== '') {
                $hist = $operatorGet('gamehistory', '/api/operators/' . rawurlencode($wagerCode) . '/game-history');
                $hcode = (int) ($hist['body']['code'] ?? -1);
                $content = (string) ($hist['body']['content'] ?? $hist['body']['url'] ?? '');
                $add('operator.game_history', 'operator', $hist['ok'] && ($hcode === 0 || $content !== ''), 'code=' . $hcode . ' content_len=' . strlen($content), ['preview' => substr($content, 0, 120)]);
            } else {
                $add('operator.game_history', 'operator', true, 'SKIP — wager_code yok');
            }
        }
    } else {
        $add('operator.wager_one', 'operator', true, 'SKIP — son 4 dk wager yok');
        $add('operator.game_history', 'operator', true, 'SKIP — son 4 dk wager yok');
    }
} catch (Throwable $e) {
    $add('operator.wagers', 'operator', false, $e->getMessage());
}

try {
    $res = $operatorGet('getbetscales', '/api/operators/get-bet-scales', [
        'currency' => $currency,
        'product_code' => 1006,
        'game_type' => 'LIVE_CASINO',
        'bet_game_list' => '225',
        'channel_code' => 'gscp',
    ]);
    $code = (int) ($res['body']['code'] ?? -1);
    $add(
        'operator.bet_scales',
        'operator',
        $res['ok'] && ($code === 0 || $code === 200),
        sprintf('http=%d code=%d msg=%s', $res['http'], $code, (string) ($res['body']['message'] ?? '')),
        ['http' => $res['http'], 'code' => $code, 'body' => $res['body']]
    );
} catch (Throwable $e) {
    $add('operator.bet_scales', 'operator', false, $e->getMessage());
}

try {
    $res = $operatorGet('getplayersfrb', '/api/operators/get-player-frb', [
        'member_account' => $probeMember,
        'currency' => $currency,
        'product_code' => 1006,
        'game_type' => 'LIVE_CASINO',
        'channel_code' => 'gscp',
    ]);
    $code = (int) ($res['body']['code'] ?? -1);
    // code 2 = no active FRB — healthy empty result for this member.
    $frbOk = $res['ok'] && ($code === 0 || $code === 200 || $code === 2);
    $add(
        'operator.player_frb',
        'operator',
        $frbOk,
        sprintf('http=%d code=%d', $res['http'], $code),
        ['http' => $res['http'], 'code' => $code, 'message' => (string) ($res['body']['message'] ?? '')]
    );
} catch (Throwable $e) {
    $add('operator.player_frb', 'operator', false, $e->getMessage());
}

// Explicitly skipped (mutates state / starts game)
$add('operator.launch_game', 'operator', true, 'SKIP — oyun başlatılmadı (bilinçli)');
$add('operator.superlobby', 'operator', true, 'SKIP — oyun başlatılmadı (bilinçli)');
$add('operator.create_free_round', 'operator', true, 'SKIP — yazma API atlandı');
$add('operator.recharge_order', 'operator', true, 'SKIP — yazma API atlandı');

// ── Live callback hosts ──────────────────────────────────────────────
foreach (
    [
        'callback.health_admin' => $callbackBase . '/api/v2/gamingsoft-wallet/health',
        'callback.health_www' => $wwwBase . '/api/v2/gamingsoft-wallet/health',
    ] as $id => $url
) {
    $res = $httpJson('GET', $url, null, 20);
    $ok = $res['ok'] && $res['http'] === 200 && is_array($res['body']) && ($res['body']['status'] ?? '') === 'ok';
    $add($id, 'callback', $ok, sprintf('http=%d error=%s', $res['http'], (string) ($res['error'] ?? '')), [
        'url' => $url,
        'http' => $res['http'],
        'body' => $res['body'],
        'raw_preview' => $res['body'] === null ? substr((string) $res['raw'], 0, 200) : null,
    ]);
    if ($id === 'callback.health_www' && !$ok) {
        // Informational — www is not the configured callback host.
        $results[count($results) - 1]['ok'] = true;
        $results[count($results) - 1]['summary'] = sprintf('EXPECTED FAIL http=%d — callback www kullanılmamalı', $res['http']);
        $findings[] = "www wallet health FAIL (http={$res['http']}) — callback URL asla www olmamalı; admin kullan.";
    }
    if ($id === 'callback.health_admin' && !$ok) {
        $findings[] = 'admin wallet health FAIL — GSC panel callback kırık, wallet_logs gelmez.';
    }
}

$walletPost = static function (string $endpoint, array $payload) use ($httpJson, $callbackBase): array {
    $url = $callbackBase . '/api/v2/gamingsoft-wallet/v1/api/seamless/' . $endpoint;
    return $httpJson('POST', $url, $payload) + ['url' => $url];
};

// Invalid sign — proves route + signature gate (no balance change)
foreach (['balance', 'withdraw', 'deposit', 'pushbetdata'] as $ep) {
    $payload = [
        'operator_code' => $operatorCode,
        'request_time' => (string) time(),
        'sign' => 'deadbeef',
        'currency' => $currency,
    ];
    if ($ep === 'pushbetdata') {
        $payload['wagers'] = [];
    } else {
        $payload['batch_requests'] = [[
            'member_account' => $probeMember,
            'product_code' => 1006,
            'transactions' => $ep === 'balance' ? [] : [[
                'id' => 'probe-invalid-' . $ep,
                'amount' => 0.01,
            ]],
        ]];
    }
    $res = $walletPost($ep, $payload);
    $code = (int) ($res['body']['code'] ?? (($res['body']['data'][0]['code'] ?? -1)));
    $ok = $res['ok'] && $res['http'] === 200 && $code === 1004;
    $add(
        'callback.invalid_sign_' . $ep,
        'callback',
        $ok,
        sprintf('http=%d code=%d', $res['http'], $code),
        ['http' => $res['http'], 'body' => $res['body']]
    );
    if (!$ok && ($res['http'] === 0 || $res['http'] >= 500)) {
        $findings[] = "Callback {$ep} erişilemiyor (http={$res['http']}).";
    }
}

// Signed balance — known member (read-only)
$rt = (string) time();
$balPayload = [
    'operator_code' => $operatorCode,
    'currency' => $currency,
    'request_time' => $rt,
    'sign' => GscPlusService::callbackSign($operatorCode, $rt, 'getbalance', $secretKey),
    'batch_requests' => [[
        'member_account' => $probeMember,
        'product_code' => 1006,
    ]],
];
$res = $walletPost('balance', $balPayload);
$code = (int) ($res['body']['code'] ?? -1);
$rowCode = (int) ($res['body']['data'][0]['code'] ?? $code);
$ok = $res['ok'] && $res['http'] === 200 && $code === 0 && $rowCode === 0;
$add(
    'callback.signed_balance_member',
    'callback',
    $ok,
    sprintf('http=%d top=%d row=%d member=%s', $res['http'], $code, $rowCode, $probeMember),
    ['http' => $res['http'], 'body' => $res['body']]
);
if (!$ok) {
    $findings[] = "Signed balance({$probeMember}) başarısız top={$code} row={$rowCode} — seamless wallet üye/bakiye sorunu.";
}

// Signed balance — missing member → expect 1000 in data
$rt = (string) time();
$missing = 'gsc_probe_missing_' . $stamp;
$missPayload = [
    'operator_code' => $operatorCode,
    'currency' => $currency,
    'request_time' => $rt,
    'sign' => GscPlusService::callbackSign($operatorCode, $rt, 'getbalance', $secretKey),
    'batch_requests' => [[
        'member_account' => $missing,
        'product_code' => 1006,
    ]],
];
$res = $walletPost('balance', $missPayload);
$code = (int) ($res['body']['code'] ?? -1);
$rowCode = (int) ($res['body']['data'][0]['code'] ?? -1);
$ok = $res['ok'] && $res['http'] === 200 && $rowCode === 1000;
$add(
    'callback.signed_balance_missing',
    'callback',
    $ok,
    sprintf('http=%d top=%d row=%d (expect row 1000)', $res['http'], $code, $rowCode),
    ['http' => $res['http'], 'body' => $res['body']]
);

// Signed empty pushbetdata
$rt = (string) time();
$pushPayload = [
    'operator_code' => $operatorCode,
    'request_time' => $rt,
    'sign' => GscPlusService::callbackSign($operatorCode, $rt, 'pushbetdata', $secretKey),
    'wagers' => [],
];
$res = $walletPost('pushbetdata', $pushPayload);
$code = (int) ($res['body']['code'] ?? -1);
$ok = $res['ok'] && $res['http'] === 200 && $code === 0;
$add(
    'callback.signed_pushbetdata_empty',
    'callback',
    $ok,
    sprintf('http=%d code=%d', $res['http'], $code),
    ['http' => $res['http'], 'body' => $res['body']]
);

// Deposit/withdraw: invalid path already tested; skip real money moves
$add('callback.signed_deposit', 'callback', true, 'SKIP — gerçek para hareketi yok (invalid_sign ile route doğrulandı)');
$add('callback.signed_withdraw', 'callback', true, 'SKIP — gerçek para hareketi yok (invalid_sign ile route doğrulandı)');

finish:
$failCount = count(array_filter($results, static fn ($r) => empty($r['ok'])));
$passCount = count($results) - $failCount;
$elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

$report = [
    'generated_at' => gmdate('c'),
    'elapsed_ms' => $elapsedMs,
    'callback_base' => $callbackBase,
    'www_base' => $wwwBase,
    'pass' => $passCount,
    'fail' => $failCount,
    'findings' => $findings,
    'results' => $results,
];

$jsonPath = $logDir . '/probe_' . $stamp . '.json';
$txtPath = $logDir . '/probe_' . $stamp . '.txt';
$latestJson = $logDir . '/latest_probe.json';
$latestTxt = $logDir . '/latest_probe.txt';

file_put_contents($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
copy($jsonPath, $latestJson);

$lines = [];
$lines[] = 'GSC+ API PROBE (no launch-game)';
$lines[] = 'generated: ' . $report['generated_at'];
$lines[] = "pass={$passCount} fail={$failCount} elapsed_ms={$elapsedMs}";
$lines[] = 'callback_base: ' . $callbackBase;
$lines[] = str_repeat('-', 72);
foreach ($results as $r) {
    $lines[] = sprintf('[%s] %s — %s', !empty($r['ok']) ? 'OK  ' : 'FAIL', $r['id'], $r['summary']);
}
$lines[] = str_repeat('-', 72);
$lines[] = 'FINDINGS:';
if ($findings === []) {
    $lines[] = '  (yok — kritik yapısal hata işaretlenmedi; oyun-içi Un-Authorized ayrı provider oturum konusu olabilir)';
} else {
    foreach ($findings as $f) {
        $lines[] = '  * ' . $f;
    }
}
$txt = implode(PHP_EOL, $lines) . PHP_EOL;
file_put_contents($txtPath, $txt);
copy($txtPath, $latestTxt);

GscFileLog::write('probe', 'api_probe_finished', [
    'pass' => $passCount,
    'fail' => $failCount,
    'findings' => $findings,
    'json' => $jsonPath,
]);

echo "\n{$txt}\n";
echo "Logs:\n  {$txtPath}\n  {$jsonPath}\n  {$latestTxt}\n";

exit($failCount > 0 ? 2 : 0);
