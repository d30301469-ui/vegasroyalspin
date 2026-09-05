<?php
if (!defined('APP_API_NO_SESSION')) {
    // Legacy dedicated api.* hosts are JWT-stateless.
    // admin.* must keep its admin session because this front controller also serves panel APIs.
    // Frontend public hosts must NEVER start ADMINSESSID here — member pages use FRONTSESSID.
    $__apiBootstrapHost = strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]);
    $__isApiHost = str_starts_with($__apiBootstrapHost, 'api.');
    $__isAdminHost = str_starts_with($__apiBootstrapHost, 'admin.');
    if ($__isApiHost) {
        define('APP_API_NO_SESSION', true);
    } elseif (!$__isAdminHost && $__apiBootstrapHost !== '') {
        // Misrouted /api/v2 from a public frontend host (e.g. vegasroyalspin119.com).
        define('APP_API_NO_SESSION', true);
        define('APP_MEMBER_API_USE_FRONTEND_SESSION', true);
    } else {
        define('APP_API_NO_SESSION', false);
    }
    unset($__apiBootstrapHost, $__isApiHost, $__isAdminHost);
}
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Europe/Istanbul');
}
require_once __DIR__ . '/../../app/Core/AdminPaths.php';
admin_paths_bootstrap();
require_once admin_panel_paths()['panel_app'] . '/bootstrap_api.php';

if (defined('APP_MEMBER_API_USE_FRONTEND_SESSION') && APP_MEMBER_API_USE_FRONTEND_SESSION) {
    $__frontendSession = admin_project_path('config/frontend_session.php');
    if (is_string($__frontendSession) && is_readable($__frontendSession)) {
        require_once $__frontendSession;
        if (function_exists('frontend_session_start')) {
            frontend_session_start();
        }
    }
    unset($__frontendSession);
}
