<?php
$url = 'https://admin.vegasroyalspin.com/api/v2/games?source=aggregator&limit=36&page=1';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Accept: application/json', 'Origin: https://m.vegasroyalspin119.com'],
    CURLOPT_TIMEOUT => 25,
    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
]);
$raw = curl_exec($ch);
curl_close($ch);
$j = json_decode((string)$raw, true);
$games = $j['data']['games'] ?? [];
$pp = 0; $egt = 0;
foreach ($games as $i => $g) {
    $p = strtolower((string)($g['provider'] ?? ''));
    if (str_contains($p, 'pragmatic')) $pp++;
    if (str_contains($p, 'egt')) $egt++;
    echo ($i+1).'. '.($g['name']??'?').' | '.($g['provider']??'')."\n";
}
echo "summary pp=$pp egt=$egt total=".count($games)."\n";
