<?php
declare(strict_types=1);

$root = '/www/wwwroot/admin.vegasroyalspin.com';
chdir($root);
require_once $root . '/app/Core/AdminPaths.php';
admin_paths_bootstrap();

echo "ADMIN_DB_DATABASE=" . (getenv('ADMIN_DB_DATABASE') ?: ($_ENV['ADMIN_DB_DATABASE'] ?? '')) . "\n";
echo "DB_DATABASE=" . (getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? '')) . "\n";
echo "DB_USER=" . (getenv('DB_USERNAME') ?: ($_ENV['DB_USERNAME'] ?? '')) . "\n";
echo "env_files=";
foreach (['/.env', '/env', '/.env.local'] as $f) {
    echo (is_file($root . $f) ? $f . ' ' : '');
}
echo "\n";

require_once $root . '/app/Core/AdminDatabase.php';
$pdo = AdminDatabase::pdo();
echo "DB ok\n";

$a = $pdo->query("SELECT id, referral_code, status FROM affiliates WHERE UPPER(referral_code) = 'MT2864' LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "affiliates=" . json_encode($a, JSON_UNESCAPED_UNICODE) . "\n";

$g = $pdo->query(
    "SELECT identifier, title, api_freespins, is_active
     FROM bgaming_games
     WHERE identifier LIKE '%Lucky%Clover%' OR title LIKE '%Lucky Clover%' OR identifier = 'AllLuckyClover'
     LIMIT 30"
)->fetchAll(PDO::FETCH_ASSOC);
echo "games=" . json_encode($g, JSON_UNESCAPED_UNICODE) . "\n";
