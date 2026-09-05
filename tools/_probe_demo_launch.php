<?php
$payload = json_encode([
    'game_id' => 'aggregator:casino-spinomenal:46515',
    'mode' => 'fun',
    'demo' => true,
    'isDemo' => true,
    'open_mode' => 'redirect',
    'platform' => 'MOBILE',
    'channel' => 'mobile',
    'lang' => 'tr',
], JSON_UNESCAPED_SLASHES);

$ch = curl_init('https://admin.vegasroyalspin.com/api/v2/game-launch');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Origin: https://m.vegasroyalspin119.com',
        'Referer: https://m.vegasroyalspin119.com/play?game_id=aggregator:casino-spinomenal:46515&mode=fun&demo=1',
    ],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)',
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
echo "PUBLIC_HTTP=$code ERR=$err\n";
echo $body, "\n\n";

// Also hit via localhost Host header if vhost exists
$ch2 = curl_init('http://127.0.0.1/api/v2/game-launch');
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Host: admin.vegasroyalspin.com',
    ],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 60,
]);
$body2 = curl_exec($ch2);
$code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
echo "LOCAL_HTTP=$code2\n";
echo $body2, "\n";

// Recent error logs
foreach ([
    '/www/wwwlogs/admin.vegasroyalspin.com-error_log',
    '/www/wwwlogs/admin.vegasroyalspin.com.error.log',
] as $log) {
    if (is_file($log)) {
        echo "\nLOG $log\n";
        $lines = @file($log);
        if (is_array($lines)) {
            $slice = array_slice($lines, -40);
            foreach ($slice as $line) {
                if (stripos($line, 'launch') !== false || stripos($line, 'INVALID') !== false || stripos($line, 'aggregator') !== false || stripos($line, 'GetGameUrl') !== false) {
                    echo $line;
                }
            }
        }
    }
}
