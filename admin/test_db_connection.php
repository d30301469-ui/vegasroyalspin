<?php
declare(strict_types=1);

chdir(__DIR__);

$_SERVER['HTTP_HOST'] = 'api.vegasroyalspin.test';
$_SERVER['REQUEST_URI'] = '/api/v2/game-launch';
$_SERVER['REQUEST_METHOD'] = 'POST';

require_once __DIR__ . '/app/Core/AdminInstallGate.php';
AdminInstallGate::loadEnv(__DIR__);
echo 'After AdminInstallGate::loadEnv:' . PHP_EOL;
echo '  getenv DB_DATABASE = ' . var_export(getenv('DB_DATABASE'), true) . PHP_EOL;
echo '  $_ENV DB_DATABASE = ' . var_export($_ENV['DB_DATABASE'] ?? 'NOT_SET', true) . PHP_EOL;

require_once __DIR__ . '/app/Core/AdminPaths.php';
admin_paths_bootstrap();
echo 'After admin_paths_bootstrap:' . PHP_EOL;
echo '  getenv DB_DATABASE = ' . var_export(getenv('DB_DATABASE'), true) . PHP_EOL;
echo '  ADMIN_APP_PATH = ' . ADMIN_APP_PATH . PHP_EOL;

require_once ADMIN_APP_PATH . '/Core/AdminDatabase.php';
try {
    $pdo = AdminDatabase::pdo();
    echo 'AdminDatabase::pdo() connected OK' . PHP_EOL;
    $row = $pdo->query('SELECT DATABASE() as db')->fetch();
    echo 'Connected database: ' . $row['db'] . PHP_EOL;
} catch (Throwable $e) {
    echo 'AdminDatabase::pdo() ERROR: ' . $e->getMessage() . PHP_EOL;
}

// Now simulate what bootstrap_api.php does
echo PHP_EOL . '-- Simulating bootstrap_api.php flow --' . PHP_EOL;
$rootConfig = admin_project_path('config/bootstrap_api.php');
if (!function_exists('admin_is_readable_file')) {
    function admin_is_readable_file(string $path): bool { return is_file($path) && is_readable($path); }
}
echo 'rootConfig = ' . $rootConfig . PHP_EOL;
echo 'readable = ' . (admin_is_readable_file($rootConfig) ? 'yes' : 'no') . PHP_EOL;
