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
    && !(defined('METROPOL_API_NO_SESSION') && METROPOL_API_NO_SESSION)) {
    ini_set('session.use_strict_mode', '1');
    // Match admin panel session name so AdminAuth::check() works across all paths
    $__apiSessionName = trim((string) (getenv('ADMIN_SESSION_NAME') ?: 'ADMINSESSID'));
    if (session_name() !== $__apiSessionName) {
        session_name($__apiSessionName);
    }
    unset($__apiSessionName);
    $cloudflareConfig = admin_project_path('config/cloudflare.php');
    if (admin_is_readable_file($cloudflareConfig)) {
        require_once $cloudflareConfig;
    }
    $isHttps = function_exists('metropol_request_is_https')
        ? metropol_request_is_https()
        : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $params = session_get_cookie_params();
    // Resolve SESSION_COOKIE_DOMAIN early (env not yet loaded at this point)
    $__sessionDomain = trim((string) (getenv('SESSION_COOKIE_DOMAIN') ?: ''));
    if ($__sessionDomain === '') {
        $__envFile = admin_project_path('.env');
        if (admin_is_readable_file($__envFile)) {
            foreach (file($__envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $__line) {
                if (strncmp($__line, 'SESSION_COOKIE_DOMAIN=', 22) === 0) {
                    $__sessionDomain = trim(substr($__line, 22), " \t\"'");
                    break;
                }
            }
        }
        unset($__envFile, $__line);
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => (string) ($params['path'] ?? '/'),
        'domain' => $__sessionDomain !== '' ? $__sessionDomain : (string) ($params['domain'] ?? ''),
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($__sessionDomain, $params, $isHttps, $cloudflareConfig);
    session_start();
}

$adminAutoloader = __DIR__ . '/Core/AdminAutoloader.php';
if (!function_exists('admin_register_autoloader') && is_readable($adminAutoloader)) {
    require_once $adminAutoloader;
}
if (function_exists('admin_register_autoloader')) {
    admin_register_autoloader(ADMIN_APP_PATH, defined('METROPOL_ROOT') ? METROPOL_ROOT : admin_project_root());
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
if (!(defined('METROPOL_API_NO_SESSION') && METROPOL_API_NO_SESSION)) {
    require_once ADMIN_APP_PATH . '/Services/AdminAuth.php';
}
