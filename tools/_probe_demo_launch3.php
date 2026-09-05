<?php
$envFile = '/www/wwwroot/vegasroyalspin.com/admin/.env';
$env = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim(trim($v), "\"'");
}
$pdo = new PDO(
    'mysql:host=' . ($env['DB_HOST'] ?? '127.0.0.1') . ';dbname=' . ($env['DB_DATABASE'] ?? '') . ';charset=utf8mb4',
    $env['DB_USERNAME'] ?? '',
    $env['DB_PASSWORD'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$root = '/www/wwwroot/admin.vegasroyalspin.com';
require_once $root . '/shared/runtime.php';
require_once $root . '/shared/services/CasinoAggregatorService.php';

$ref = new ReflectionClass('CasinoAggregatorService');
$req = $ref->getMethod('request');
$req->setAccessible(true);
$cfg = CasinoAggregatorService::config($pdo);
$user = $pdo->query("SELECT id, username FROM users WHERE COALESCE(banned,0)=0 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$uid = (string) $user['id'];
$nick = (string) $user['username'];
$vendor = 'casino-spinomenal';
$game = '46515';
$base = [
    'method' => 'GetGameUrl',
    'token' => (string) $cfg['api_token'],
    'agentCode' => (string) $cfg['agent_code'],
    'userCode' => $uid,
    'nickName' => $nick,
    'nickname' => $nick,
    'vendorCode' => $vendor,
    'gameCode' => $game,
    'currencyCode' => strtoupper((string) ($cfg['currency'] ?? 'TRY')),
    'language' => 'tr',
    'channel' => 'mobile',
    'homeUrl' => 'https://m.vegasroyalspin119.com',
];

$cases = [
    'omit isDemo' => [],
    'isDemo bool true' => ['isDemo' => true],
    'isDemo bool false' => ['isDemo' => false],
    'isDemo int 1' => ['isDemo' => 1],
    'isDemo int 0' => ['isDemo' => 0],
    'isDemo str true' => ['isDemo' => 'true'],
    'isDemo str false' => ['isDemo' => 'false'],
    'isDemo str 1' => ['isDemo' => '1'],
    'demo true' => ['demo' => true],
    'mode fun' => ['mode' => 'fun'],
];

foreach ($cases as $label => $extra) {
    usleep(650000);
    echo "=== $label ===\n";
    try {
        $r = $req->invoke(null, $pdo, $base + $extra, 25);
        echo json_encode([
            'status' => $r['status'] ?? null,
            'msg' => $r['msg'] ?? null,
            'url' => $r['launchUrl'] ?? null,
            'mode_in_url' => (is_string($r['launchUrl'] ?? null) && preg_match('/mode=([^&]+)/', $r['launchUrl'], $m)) ? $m[1] : null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    } catch (Throwable $e) {
        echo 'ERR ' . $e->getMessage() . "\n";
    }
}

echo "=== GetAgentSettings ===\n";
try {
    echo json_encode($req->invoke(null, $pdo, [
        'method' => 'GetAgentSettings',
        'token' => (string) $cfg['api_token'],
        'agentCode' => (string) $cfg['agent_code'],
    ], 25), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
} catch (Throwable $e) {
    echo 'ERR ' . $e->getMessage() . "\n";
}

// Try another vendor known to work (EGT / pragmatic if present)
$alts = $pdo->query("SELECT vendor_code, game_code, game_name FROM casino_aggregator_games WHERE is_active=1 AND vendor_code IN ('egt','casino-egt','pragmatic','casino-pragmatic','pragmaticplay','bgaming','casino-bgaming') ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "alts=", json_encode($alts, JSON_UNESCAPED_UNICODE), "\n";
foreach ($alts as $alt) {
    usleep(650000);
    echo "=== alt demo {$alt['vendor_code']}:{$alt['game_code']} ===\n";
    try {
        $r = $req->invoke(null, $pdo, [
            'method' => 'GetGameUrl',
            'token' => (string) $cfg['api_token'],
            'agentCode' => (string) $cfg['agent_code'],
            'userCode' => $uid,
            'nickName' => $nick,
            'nickname' => $nick,
            'vendorCode' => $alt['vendor_code'],
            'gameCode' => $alt['game_code'],
            'currencyCode' => strtoupper((string) ($cfg['currency'] ?? 'TRY')),
            'language' => 'tr',
            'channel' => 'mobile',
            'isDemo' => true,
            'homeUrl' => 'https://m.vegasroyalspin119.com',
        ], 25);
        echo json_encode(['status'=>$r['status']??null,'msg'=>$r['msg']??null,'url'=>$r['launchUrl']??null], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), "\n";
    } catch (Throwable $e) {
        echo 'ERR ' . $e->getMessage() . "\n";
    }
}
