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
admin_require_project_file('controllers/Api/ApiDrakonController.php');

(new ApiDrakonController())->index();
