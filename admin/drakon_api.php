<?php

declare(strict_types=1);

/**
 * Drakon resmi webhook — POST https://siteniz.com/drakon_api
 * Agent panel site URL: https://bo-nexthub.site (Drakon otomatik /drakon_api ekler)
 */
define('METROPOL_DRAKON_WEBHOOK', true);

require_once __DIR__ . '/app/Core/AdminPaths.php';
admin_paths_bootstrap();
require_once admin_panel_paths()['panel_app'] . '/bootstrap_api.php';
$controllerPath = admin_panel_paths()['install_root'] . '/controllers/Api/ApiDrakonController.php';
if (!is_file($controllerPath) || !is_readable($controllerPath)) {
	throw new RuntimeException(sprintf('Required admin webhook controller not found: %s', $controllerPath));
}
require_once $controllerPath;

(new ApiDrakonController())->index();
