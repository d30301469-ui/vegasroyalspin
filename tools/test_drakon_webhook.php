<?php
declare(strict_types=1);
define('METROPOL_DRAKON_WEBHOOK', true);
$_SERVER['REQUEST_URI'] = '/drakon_api';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'admin.vegasroyalspin.test';

chdir(__DIR__ . '/../admin');
require __DIR__ . '/../admin/app/Core/AdminPaths.php';
admin_paths_bootstrap();
require admin_panel_paths()['panel_app'] . '/bootstrap_api.php';
echo "bootstrap OK\n";

$pdo = AdminDatabase::pdo();
echo "PDO OK\n";

require __DIR__ . '/../admin/services/DrakonService.php';
echo "DrakonService loaded\n";

$r = DrakonService::verifyWebhookRequest($pdo, '', $_SERVER, true);
echo "verifyWebhookRequest: " . json_encode($r) . "\n";
