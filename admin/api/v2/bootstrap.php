<?php
if (!defined('METROPOL_API_NO_SESSION')) {
    define('METROPOL_API_NO_SESSION', true);
}
require_once __DIR__ . '/../../app/Core/AdminPaths.php';
admin_paths_bootstrap();
require_once admin_panel_paths()['panel_app'] . '/bootstrap_api.php';
