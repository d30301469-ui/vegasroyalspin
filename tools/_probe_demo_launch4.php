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

$guest = 'guest_' . substr(bin2hex(random_bytes(8)), 0, 16);
$demoSys = 'sysdemo_' . substr(bin2hex(random_bytes(4)), 0, 8);

$cases = [
    'guest omit isDemo' => ['userCode' => $guest, 'nick' => 'guest', 'extra' => []],
    'guest isDemo false' => ['userCode' => $guest, 'nick' => 'guest', 'extra' => ['isDemo' => false]],
    'guest CreateUser then omit' => ['userCode' => $guest . 'b', 'nick' => 'guest', 'create' => true, 'extra' => []],
    'sysdemo CreateUser then omit' => ['userCode' => $demoSys, 'nick' => 'Demo', 'create' => true, 'extra' => []],
    'logged omit (control)' => ['userCode' => (string)$user['id'], 'nick' => (string)$user['username'], 'extra' => []],
];

foreach ($cases as $label => $c) {
    usleep(700000);
    if (!empty($c['create'])) {
        echo "--- CreateUser {$c['userCode']} ---\n";
        try {
            echo json_encode($req->invoke(null, $pdo, [
                'method' => 'CreateUser',
                'token' => (string)$cfg['api_token'],
                'agentCode' => (string)$cfg['agent_code'],
                'userCode' => $c['userCode'],
            ], 15), JSON_UNESCAPED_UNICODE), "\n";
        } catch (Throwable $e) {
            echo 'ERR ' . $e->getMessage() . "\n";
        }
        usleep(1100000);
    }
    echo "=== $label ===\n";
    try {
        $payload = [
            'method' => 'GetGameUrl',
            'token' => (string)$cfg['api_token'],
            'agentCode' => (string)$cfg['agent_code'],
            'userCode' => $c['userCode'],
            'nickName' => $c['nick'],
            'nickname' => $c['nick'],
            'vendorCode' => 'casino-spinomenal',
            'gameCode' => '46515',
            'currencyCode' => strtoupper((string)($cfg['currency'] ?? 'TRY')),
            'language' => 'tr',
            'channel' => 'mobile',
            'homeUrl' => 'https://m.vegasroyalspin119.com',
        ] + $c['extra'];
        $r = $req->invoke(null, $pdo, $payload, 25);
        echo json_encode([
            'status' => $r['status'] ?? null,
            'msg' => $r['msg'] ?? null,
            'url' => $r['launchUrl'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    } catch (Throwable $e) {
        echo 'ERR ' . $e->getMessage() . "\n";
    }
}

echo "=== api_mode ===\n";
echo json_encode([
    'api_mode' => $cfg['api_mode'] ?? null,
    'agent' => $cfg['agent_code'] ?? null,
    'base' => $cfg['api_base_url'] ?? null,
], JSON_UNESCAPED_UNICODE), "\n";
