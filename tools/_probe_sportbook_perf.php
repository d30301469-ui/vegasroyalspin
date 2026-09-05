<?php
declare(strict_types=1);

$envFile = '/www/wwwroot/vegasroyalspin.com/admin/.env';
$e = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || ($line[0] ?? '') === '#' || !str_contains($line, '=')) {
        continue;
    }
    [$k, $v] = explode('=', $line, 2);
    $e[trim($k)] = trim($v, " \t\"'");
}
$pdo = new PDO(
    'mysql:host=' . ($e['DB_HOST'] ?? '127.0.0.1') . ';dbname=' . $e['DB_DATABASE'] . ';charset=utf8mb4',
    $e['DB_USERNAME'] ?? '',
    $e['DB_PASSWORD'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "=== sportsbook_config ===\n";
$row = $pdo->query('SELECT is_active, api_base_url, api_mode, LEFT(api_token, 10) AS tok, currency, lang FROM sportsbook_config WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";

echo "\n=== recent sessions ===\n";
foreach ($pdo->query('SELECT id, user_id, channel, vendor_code, LEFT(launch_url, 70) AS url, created_at FROM sportsbook_sessions ORDER BY id DESC LIMIT 10') as $x) {
    echo json_encode($x, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== wallet log durations (ms) ===\n";
foreach ($pdo->query('SELECT method, http_status, duration_ms, created_at FROM sportsbook_wallet_logs ORDER BY id DESC LIMIT 15') as $x) {
    echo json_encode($x, JSON_UNESCAPED_UNICODE) . "\n";
}

$logPaths = [
    '/www/wwwlogs/vegasroyalspin.com.log',
    '/www/wwwlogs/vegasroyalspin119.com.log',
    '/www/wwwlogs/access.log',
];
$hits = [];
foreach ($logPaths as $path) {
    if (!is_readable($path)) {
        continue;
    }
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        continue;
    }
    fseek($fh, max(0, filesize($path) - 8 * 1024 * 1024));
    while (($line = fgets($fh)) !== false) {
        if (stripos($line, 'sportbook') === false && stripos($line, 'sportsbook-launch') === false) {
            continue;
        }
        $hits[] = trim($line);
    }
    fclose($fh);
}
$hits = array_slice($hits, -80);
echo "\n=== access log tail (" . count($hits) . " lines) ===\n";
foreach ($hits as $line) {
    echo $line . "\n";
}

$errPaths = [
    '/www/wwwlogs/vegasroyalspin.com.error.log',
    '/www/wwwlogs/vegasroyalspin119.com.error.log',
];
echo "\n=== error log sportbook ===\n";
foreach ($errPaths as $path) {
    if (!is_readable($path)) {
        continue;
    }
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        continue;
    }
    fseek($fh, max(0, filesize($path) - 4 * 1024 * 1024));
    while (($line = fgets($fh)) !== false) {
        if (stripos($line, 'sport') === false && stripos($line, 'distro') === false) {
            continue;
        }
        echo trim($line) . "\n";
    }
    fclose($fh);
}

// Timed launch smoke (guest)
echo "\n=== guest launch timing ===\n";
$t0 = microtime(true);
$ch = curl_init('http://127.0.0.1/api/v2/sportsbook-launch');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'Host: vegasroyalspin119.com'],
    CURLOPT_POSTFIELDS => '{"lang":"tr","channel":"desktop"}',
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 5,
]);
$raw = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);
$ms = (int) round((microtime(true) - $t0) * 1000);
echo "HTTP=$code ms=$ms err=" . ($err ?: '-') . "\n";
if (is_string($raw)) {
    $j = json_decode($raw, true);
    if (is_array($j)) {
        $d = is_array($j['data'] ?? null) ? $j['data'] : $j;
        echo 'provider=' . ($d['provider'] ?? '-') . ' ok=' . (($j['success'] ?? false) ? '1' : '0') . "\n";
        echo 'msg=' . substr((string) ($j['message'] ?? ''), 0, 120) . "\n";
    } else {
        echo substr($raw, 0, 200) . "\n";
    }
}
