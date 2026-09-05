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
$err = curl_error($ch);
curl_close($ch);
echo "http=$code err=$err len=" . strlen((string)$raw) . "\n";
$j = json_decode((string)$raw, true);
if (!is_array($j)) {
    echo substr((string)$raw, 0, 800), "\n";
    exit(1);
}
echo "keys=", implode(',', array_keys($j)), "\n";
if (isset($j['data']) && is_array($j['data'])) {
    echo "data_keys=", implode(',', array_keys($j['data'])), "\n";
    echo "total=", ($j['data']['total'] ?? '?'), " games_count=", count($j['data']['games'] ?? []), "\n";
}
if (!empty($j['message'])) echo "message=", $j['message'], "\n";
if (!empty($j['success'])) echo "success=", json_encode($j['success']), "\n";
$games = $j['data']['games'] ?? $j['games'] ?? [];
if ($games === [] && isset($j['data']['items'])) $games = $j['data']['items'];
foreach (array_slice($games, 0, 15) as $i => $g) {
    echo ($i + 1) . '. ' . ($g['name'] ?? '?') . ' | ' . ($g['provider'] ?? '') . ' | ' . ($g['game_id'] ?? '') . "\n";
}
if ($games === []) {
    echo "RAW=", substr((string)$raw, 0, 1000), "\n";
}
