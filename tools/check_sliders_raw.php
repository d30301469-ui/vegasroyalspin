<?php
define('BASE_PATH', dirname(__DIR__));
define('API_PATH', BASE_PATH . '/api');
require_once BASE_PATH . '/api/bootstrap.php';

// Check all sliders with full image URLs
$pdo = ApiCmsRemote::pdo();
$stmt = $pdo->query("SELECT id, title, category, status, desktop_path, mobile_path, start_date, end_date FROM sliders ORDER BY category, id");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Total sliders in DB: " . count($rows) . "\n\n";
foreach ($rows as $r) {
    echo "ID={$r['id']} cat={$r['category']} status={$r['status']} title={$r['title']}\n";
    echo "  start={$r['start_date']} end={$r['end_date']}\n";
    echo "  desktop=" . substr($r['desktop_path'] ?? '', 0, 100) . "\n";
    echo "  mobile=" . substr($r['mobile_path'] ?? '', 0, 100) . "\n\n";
}
