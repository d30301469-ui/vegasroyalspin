<?php
$envCandidates = [
    '/www/wwwroot/vegasroyalspin.com/admin/.env',
    '/www/wwwroot/vegasroyalspin.com/.env',
];
$env = [];
foreach ($envCandidates as $envFile) {
    if (!is_readable($envFile)) {
        echo "skip $envFile\n";
        continue;
    }
    echo "using $envFile\n";
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if ($v !== '' && ($v[0] === '"' || $v[0] === "'")) {
            $v = trim($v, "\"'");
        }
        $env[$k] = $v;
    }
    break;
}
if ($env === []) {
    fwrite(STDERR, "no env\n");
    exit(1);
}

$host = $env['DB_HOST'] ?? '127.0.0.1';
$db = $env['DB_DATABASE'] ?? '';
$user = $env['DB_USERNAME'] ?? '';
$pass = $env['DB_PASSWORD'] ?? '';
echo "db=$db user=$user\n";

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$root = '/www/wwwroot/admin.vegasroyalspin.com';
require_once $root . '/shared/runtime.php';
require_once $root . '/shared/services/CasinoAggregatorService.php';

$users = $pdo->query("SELECT id, username, banned, balance, bonus_balance FROM users WHERE COALESCE(banned,0)=0 ORDER BY id DESC LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
echo "users=", json_encode($users, JSON_UNESCAPED_UNICODE), "\n";

$gameId = 'aggregator:casino-spinomenal:46515';
$input = [
    'game_id' => $gameId,
    'mode' => 'fun',
    'demo' => true,
    'isDemo' => true,
    'open_mode' => 'redirect',
    'platform' => 'MOBILE',
    'channel' => 'mobile',
    'lang' => 'tr',
    'home_url' => 'https://m.vegasroyalspin119.com',
];

function summarize(array $r): array {
    return [
        'success' => $r['success'] ?? null,
        'code' => $r['code'] ?? null,
        'message' => $r['message'] ?? null,
        'raw' => $r['raw'] ?? null,
        'url' => $r['data']['game_url'] ?? $r['game_url'] ?? null,
    ];
}

echo "=== GUEST DEMO ===\n";
echo json_encode(summarize(CasinoAggregatorService::launch($pdo, null, $input)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";

if (!empty($users[0])) {
    echo "=== LOGGED DEMO ===\n";
    echo json_encode(summarize(CasinoAggregatorService::launch($pdo, $users[0], $input)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
}

$ref = new ReflectionClass('CasinoAggregatorService');
$req = $ref->getMethod('request');
$req->setAccessible(true);
$cfg = CasinoAggregatorService::config($pdo);
$parsed = CasinoAggregatorService::parseGameId($gameId);
$guestCode = 'gdemo' . substr(bin2hex(random_bytes(6)), 0, 10);

echo "=== CreateUser $guestCode ===\n";
try {
    echo json_encode($req->invoke(null, $pdo, [
        'method' => 'CreateUser',
        'token' => (string) $cfg['api_token'],
        'agentCode' => (string) $cfg['agent_code'],
        'userCode' => $guestCode,
    ]), JSON_UNESCAPED_UNICODE), "\n";
} catch (Throwable $e) {
    echo "ERR ", $e->getMessage(), "\n";
}

echo "=== GetGameUrl guest+CreateUser isDemo=true ===\n";
try {
    echo json_encode($req->invoke(null, $pdo, [
        'method' => 'GetGameUrl',
        'token' => (string) $cfg['api_token'],
        'agentCode' => (string) $cfg['agent_code'],
        'userCode' => $guestCode,
        'nickName' => 'guest',
        'nickname' => 'guest',
        'vendorCode' => $parsed['vendor_code'],
        'gameCode' => $parsed['game_code'],
        'currencyCode' => strtoupper((string) ($cfg['currency'] ?? 'TRY')),
        'language' => 'tr',
        'channel' => 'mobile',
        'isDemo' => true,
        'homeUrl' => 'https://m.vegasroyalspin119.com',
    ], 25), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
} catch (Throwable $e) {
    echo "ERR ", $e->getMessage(), "\n";
}

if (!empty($users[0])) {
    echo "=== GetGameUrl logged isDemo=true ===\n";
    try {
        echo json_encode($req->invoke(null, $pdo, [
            'method' => 'GetGameUrl',
            'token' => (string) $cfg['api_token'],
            'agentCode' => (string) $cfg['agent_code'],
            'userCode' => (string) $users[0]['id'],
            'nickName' => (string) $users[0]['username'],
            'nickname' => (string) $users[0]['username'],
            'vendorCode' => $parsed['vendor_code'],
            'gameCode' => $parsed['game_code'],
            'currencyCode' => strtoupper((string) ($cfg['currency'] ?? 'TRY')),
            'language' => 'tr',
            'channel' => 'mobile',
            'isDemo' => true,
            'homeUrl' => 'https://m.vegasroyalspin119.com',
        ], 25), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    } catch (Throwable $e) {
        echo "ERR ", $e->getMessage(), "\n";
    }

    echo "=== GetGameUrl logged isDemo=false ===\n";
    try {
        echo json_encode($req->invoke(null, $pdo, [
            'method' => 'GetGameUrl',
            'token' => (string) $cfg['api_token'],
            'agentCode' => (string) $cfg['agent_code'],
            'userCode' => (string) $users[0]['id'],
            'nickName' => (string) $users[0]['username'],
            'nickname' => (string) $users[0]['username'],
            'vendorCode' => $parsed['vendor_code'],
            'gameCode' => $parsed['game_code'],
            'currencyCode' => strtoupper((string) ($cfg['currency'] ?? 'TRY')),
            'language' => 'tr',
            'channel' => 'mobile',
            'isDemo' => false,
            'homeUrl' => 'https://m.vegasroyalspin119.com',
        ], 25), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    } catch (Throwable $e) {
        echo "ERR ", $e->getMessage(), "\n";
    }
}
