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
    'demo_guest_key' => 'probe-' . bin2hex(random_bytes(4)),
];

$r = CasinoAggregatorService::launch($pdo, null, $input);
echo "=== launch guest demo ===\n";
echo json_encode([
    'success' => $r['success'] ?? null,
    'code' => $r['code'] ?? null,
    'message' => $r['message'] ?? null,
    'mode' => $r['data']['mode'] ?? null,
    'url' => $r['data']['game_url'] ?? $r['game_url'] ?? null,
    'raw' => $r['raw'] ?? null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";

$sess = $pdo->query("SELECT id, user_code, LEFT(request_payload,400) req, LEFT(launch_url,120) url FROM casino_aggregator_sessions ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo "=== last session ===\n";
echo json_encode($sess, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";

if (!empty($sess['user_code'])) {
    $ref = new ReflectionClass('CasinoAggregatorService');
    $m = $ref->getMethod('walletGetBalance');
    $m->setAccessible(true);
    $bal = $m->invoke(null, $pdo, ['userCode' => $sess['user_code']]);
    echo "=== demo GetBalance ===\n";
    echo json_encode($bal, JSON_UNESCAPED_UNICODE), "\n";
}
