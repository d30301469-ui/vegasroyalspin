<?php
if (!defined('APP_API_NO_SESSION')) {
    // Legacy dedicated api.* hosts are JWT-stateless.
    // admin.* must keep its admin session because this front controller also serves panel APIs.
    $__apiBootstrapHost = strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]);
    define('APP_API_NO_SESSION', str_starts_with($__apiBootstrapHost, 'api.'));
    unset($__apiBootstrapHost);
}
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Europe/Istanbul');
}
require_once __DIR__ . '/../../app/Core/AdminPaths.php';
admin_paths_bootstrap();
require_once admin_panel_paths()['panel_app'] . '/bootstrap_api.php';
