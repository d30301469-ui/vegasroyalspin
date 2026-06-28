<?php

declare(strict_types=1);

/**
 * Lightweight bootstrap for /api/v2/* — skips eager admin controller loading.
 */
if (!defined('METROPOL_ADMIN_PANEL')) {
    define('METROPOL_ADMIN_PANEL', true);
}
if (!defined('METROPOL_API_V2_BOOTSTRAP')) {
    define('METROPOL_API_V2_BOOTSTRAP', true);
}

require_once __DIR__ . '/Core/AdminPaths.php';
admin_paths_bootstrap();

if (session_status() === PHP_SESSION_NONE
    && !(defined('METROPOL_DRAKON_WEBHOOK') && METROPOL_DRAKON_WEBHOOK)
    && !(defined('METROPOL_API_NO_SESSION') && METROPOL_API_NO_SESSION)) {
    ini_set('session.use_strict_mode', '1');
    $cloudflareConfig = admin_project_path('config/cloudflare.php');
    if (admin_is_readable_file($cloudflareConfig)) {
        require_once $cloudflareConfig;
    }
    $isHttps = function_exists('metropol_request_is_https')
        ? metropol_request_is_https()
        : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => (string) ($params['path'] ?? '/'),
        'domain' => (string) ($params['domain'] ?? ''),
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/Core/AdminAutoloader.php';
admin_register_autoloader(ADMIN_APP_PATH, defined('METROPOL_ROOT') ? METROPOL_ROOT : admin_project_root());

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
if (!(defined('METROPOL_DRAKON_WEBHOOK') && METROPOL_DRAKON_WEBHOOK)
    && !(defined('METROPOL_API_NO_SESSION') && METROPOL_API_NO_SESSION)) {
    require_once ADMIN_APP_PATH . '/Services/AdminAuth.php';
}
