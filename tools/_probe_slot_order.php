<?php
$url = 'https://admin.vegasroyalspin.com/api/v2/games?source=aggregator&limit=15&page=1';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Accept: application/json', 'Origin: https://m.vegasroyalspin119.com'],
    CURLOPT_TIMEOUT => 25,
    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
]);
$raw = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "http=$code\n";
$j = json_decode((string)$raw, true);
$games = $j['data']['games'] ?? $j['games'] ?? [];
foreach (array_slice($games, 0, 15) as $i => $g) {
    echo ($i + 1) . '. ' . ($g['name'] ?? '?') . ' | ' . ($g['provider'] ?? '') . "\n";
}
