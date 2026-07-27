<?php
/**
 * EGT VIP aggregator oyun görsellerini DB'de PNG uzantısına çevirir.
 *
 * Kullanım: php tools/egt_vip_images_to_png.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';

require_once $root . '/config/env.php';
frontend_load_dotenv($root);
$dbConfig = require $root . '/config/database.php';

if (empty($dbConfig['host']) || !empty($dbConfig['disabled'])) {
    $adminDbConfig = require $root . '/admin/config/database.php';
    if (is_array($adminDbConfig) && !empty($adminDbConfig['host'])) {
        $dbConfig = $adminDbConfig;
    }
}

if (empty($dbConfig['host'])) {
    $dbConfig['host'] = '127.0.0.1';
    $dbConfig['port'] = (int) ($dbConfig['port'] ?? 3306);
    $dbConfig['database'] = $dbConfig['database'] ?? 'vegasroyalspin';
    $dbConfig['username'] = $dbConfig['username'] ?? 'root';
    $dbConfig['password'] = $dbConfig['password'] ?? '';
    $dbConfig['charset'] = $dbConfig['charset'] ?? 'utf8mb4';
}

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $dbConfig['host'],
    (int) ($dbConfig['port'] ?? 3306),
    $dbConfig['database'],
    $dbConfig['charset'] ?? 'utf8mb4'
);
$pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

require_once $root . '/services/CasinoAggregatorService.php';

echo "DB: {$dbConfig['database']}@{$dbConfig['host']}\n";
$result = CasinoAggregatorService::repairEgtVipImagesToPng($pdo);
echo 'EGT VIP scanned: ' . (int) ($result['scanned'] ?? 0) . "\n";
echo 'EGT VIP updated to PNG: ' . (int) ($result['updated'] ?? 0) . "\n";

$sample = $pdo->query(
    "SELECT vendor_code, game_code, image_url
     FROM casino_aggregator_games
     WHERE LOWER(REPLACE(REPLACE(vendor_code, '-', ''), '_', '')) LIKE '%egtvip%'
     LIMIT 5"
)->fetchAll();

foreach ($sample as $row) {
    echo ($row['vendor_code'] ?? '') . ' / ' . ($row['game_code'] ?? '') . ' => ' . ($row['image_url'] ?? '') . "\n";
}
