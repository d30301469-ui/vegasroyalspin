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
require_once '/www/wwwroot/admin.vegasroyalspin.com/shared/runtime.php';
require_once '/www/wwwroot/admin.vegasroyalspin.com/shared/services/CasinoAggregatorService.php';
require_once '/www/wwwroot/admin.vegasroyalspin.com/shared/services/SlotGamesQuery.php';

$liveMatch = CasinoAggregatorService::liveVendorSqlMatch('g.vendor_code');
$typeClause = "((g.game_type IN (0, 1)) AND NOT {$liveMatch})";
$unionSql = "(SELECT
        CONCAT('aggregator:', g.vendor_code, ':', g.game_code) AS game_id,
        g.game_name AS name,
        COALESCE(NULLIF(v.vendor_name, ''), g.vendor_code) AS provider,
        g.vendor_code AS provider_code,
        COALESCE(NULLIF(g.image_url, ''), '') AS image_url,
        CAST('' AS CHAR) AS image_fallbacks,
        g.is_featured AS is_featured,
        'aggregator' AS source,
        CAST(g.id AS CHAR) AS row_id,
        CAST('' AS CHAR) AS raw_payload
    FROM casino_aggregator_games g
    INNER JOIN casino_aggregator_vendors v ON v.vendor_code = g.vendor_code
    WHERE g.is_active = 1 AND v.is_active = 1 AND {$typeClause}) AS catalog";

$orderBy = SlotGamesQuery::catalogOrderBySql('', false);
echo "orderByLen=", strlen($orderBy), "\n";
$sql = "SELECT game_id, name, provider, provider_code, is_featured
        FROM {$unionSql}
        ORDER BY {$orderBy}
        LIMIT 15";
try {
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    echo "rows=", count($rows), "\n";
    foreach ($rows as $i => $g) {
        echo ($i+1).'. '.$g['name'].' | '.$g['provider']."\n";
    }
} catch (Throwable $e) {
    echo "ERR: ", $e->getMessage(), "\n";
}

echo "=== simple featured order ===\n";
try {
    $rows = $pdo->query("SELECT game_id, name, provider FROM {$unionSql} ORDER BY is_featured DESC, name ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $i => $g) echo ($i+1).'. '.$g['name'].' | '.$g['provider']."\n";
} catch (Throwable $e) {
    echo "ERR2: ", $e->getMessage(), "\n";
}

echo "=== tail log ===\n";
passthru("tail -n 30 /www/wwwlogs/admin.vegasroyalspin.com.error.log 2>/dev/null | grep -i 'member_games\\|catalogue\\|SQL' | tail -n 15");
