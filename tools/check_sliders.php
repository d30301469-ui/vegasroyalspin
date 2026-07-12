<?php
define('BASE_PATH', dirname(__DIR__));
define('API_PATH', BASE_PATH . '/api');
require_once BASE_PATH . '/api/bootstrap.php';

echo "=== HOME sliders ===\n";
$sliders = ApiSliders::fetchFromDatabase(['category' => 'home']);
echo "DB sliders for home: " . count($sliders) . "\n";
foreach ($sliders as $s) {
    echo "  id={$s['id']} title={$s['title']}\n";
    echo "    desktop=" . substr($s['desktopImageUrl'], 0, 90) . "\n";
    echo "    mobile=" . substr($s['mobileImageUrl'], 0, 90) . "\n";
}

echo "\n=== ALL sliders ===\n";
$all = ApiSliders::fetchFromDatabase([]);
echo "Total: " . count($all) . "\n";
foreach ($all as $s) {
    echo "  cat={$s['category']} id={$s['id']} title={$s['title']}\n";
}

echo "\n=== FETCH (with remote fallback) for home ===\n";
$_SERVER['HTTP_HOST'] = 'm.vegasroyalspin.com';
$fetched = ApiSliders::fetchForCategory('home');
echo "Fetched: " . count($fetched) . "\n";
foreach ($fetched as $s) {
    echo "  id={$s['id']} cat={$s['category']} title={$s['title']}\n";
    echo "    mobileImageUrl=" . substr($s['mobileImageUrl'], 0, 90) . "\n";
}
