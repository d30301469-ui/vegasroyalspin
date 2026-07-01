<?php
declare(strict_types=1);
define('METROPOL_DRAKON_WEBHOOK', true);
chdir(__DIR__ . '/../admin');
require __DIR__ . '/../admin/app/Core/AdminPaths.php';
admin_paths_bootstrap();
require admin_panel_paths()['panel_app'] . '/bootstrap_api.php';
$pdo = AdminDatabase::pdo();
$deleted = $pdo->exec('DELETE FROM drakon_access_tokens');
echo 'Cleared ' . $deleted . ' cached token(s)' . PHP_EOL;

// Also show current drakon_config
$row = $pdo->query("SELECT agent_token, agent_secret, api_base_url, site_endpoint, is_active FROM drakon_config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo 'Config: ' . json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
