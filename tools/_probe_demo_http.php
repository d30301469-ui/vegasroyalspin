<?php
$url = 'https://admin.vegasroyalspin.com/api/v2/game-launch';
$body = json_encode([
    'game_id' => 'aggregator:casino-spinomenal:46515',
    'mode' => 'fun',
    'demo' => true,
    'isDemo' => true,
    'open_mode' => 'redirect',
    'platform' => 'MOBILE',
    'channel' => 'mobile',
    'lang' => 'tr',
    'home_url' => 'https://m.vegasroyalspin119.com',
    'demo_guest_key' => 'http-probe-' . bin2hex(random_bytes(3)),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Origin: https://m.vegasroyalspin119.com',
        'Referer: https://m.vegasroyalspin119.com/play',
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
]);
$raw = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);
echo "http=$code err=$err\n";
$j = json_decode((string)$raw, true);
if (!is_array($j)) {
    echo substr((string)$raw, 0, 800), "\n";
    exit;
}
echo json_encode([
    'success' => $j['success'] ?? null,
    'code' => $j['code'] ?? null,
    'message' => $j['message'] ?? null,
    'mode' => $j['data']['mode'] ?? null,
    'url' => $j['data']['game_url'] ?? $j['game_url'] ?? null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
