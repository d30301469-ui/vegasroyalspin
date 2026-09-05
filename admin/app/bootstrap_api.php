<?php

declare(strict_types=1);

/**
 * Lightweight bootstrap for /api/v2/* — skips eager admin controller loading.
 */
if (!defined('APP_ADMIN_PANEL')) {
    define('APP_ADMIN_PANEL', true);
}
if (!defined('APP_API_V2_BOOTSTRAP')) {
    define('APP_API_V2_BOOTSTRAP', true);
}

require_once __DIR__ . '/Core/AdminPaths.php';
admin_paths_bootstrap();
require_once __DIR__ . '/Core/AdminSessionBootstrap.php';
// Cloudflare IP helper must load even when API is sessionless (APP_API_NO_SESSION):
// member JWT writes otherwise fall back to REMOTE_ADDR=127.0.0.1 behind the frontend proxy.
$__cfConfig = function_exists('admin_project_path') ? admin_project_path('config/cloudflare.php') : '';
if (is_string($__cfConfig) && $__cfConfig !== '' && is_readable($__cfConfig)) {
    require_once $__cfConfig;
}
unset($__cfConfig);
admin_session_bootstrap(!(defined('APP_API_NO_SESSION') && APP_API_NO_SESSION));

$adminAutoloader = __DIR__ . '/Core/AdminAutoloader.php';
if (!function_exists('admin_register_autoloader') && is_readable($adminAutoloader)) {
    require_once $adminAutoloader;
}
if (function_exists('admin_register_autoloader')) {
    admin_register_autoloader(ADMIN_APP_PATH, defined('APP_ROOT') ? APP_ROOT : admin_project_root());
}

$rootConfig = admin_project_path('config/bootstrap_api.php');
if (!admin_is_readable_file($rootConfig)) {
    $rootConfig = admin_project_path('config/app.php');
}
if (admin_is_readable_file($rootConfig)) {
    require_once $rootConfig;
}

require_once ADMIN_APP_PATH . '/Config/admin.php';
require_once ADMIN_APP_PATH . '/Core/AdminDatabase.php';
require_once ADMIN_APP_PATH . '/Core/ErrorHandler.php';
\App\Core\ErrorHandler::register();
if (!(defined('APP_API_NO_SESSION') && APP_API_NO_SESSION)) {
    require_once ADMIN_APP_PATH . '/Services/AdminAuth.php';
    admin_session_restore();
}
