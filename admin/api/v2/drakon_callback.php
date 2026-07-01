<?php

declare(strict_types=1);

if (!defined('METROPOL_DRAKON_WEBHOOK')) {
    define('METROPOL_DRAKON_WEBHOOK', true);
}

require_once __DIR__ . '/bootstrap.php';

$basePath = admin_project_root();

if (getenv('DRAKON_WEBHOOK_ACCESS_LOG') === '1') {
    @file_put_contents(
        $basePath . '/storage/logs/drakon_callback_hits.log',
        '[' . date('Y-m-d H:i:s') . '] ' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . ($_SERVER['REQUEST_URI'] ?? '') . PHP_EOL,
        FILE_APPEND
    );
}

$controllerPath = dirname(__DIR__, 2) . '/controllers/Api/ApiDrakonController.php';
if (!is_file($controllerPath) || !is_readable($controllerPath)) {
    throw new RuntimeException(sprintf('Required admin webhook controller not found: %s', $controllerPath));
}
require_once $controllerPath;
(new ApiDrakonController())->index();
