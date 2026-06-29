<?php
if (!defined('METROPOL_API_NO_SESSION')) {
    // API subdomain hosts (api.* prefix) are stateless JWT-only.
    // All other hosts (frontend, admin) use PHP session for auth state persistence.
    $__apiBootstrapHost = strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]);
    define('METROPOL_API_NO_SESSION', str_starts_with($__apiBootstrapHost, 'api.'));
    unset($__apiBootstrapHost);
}
require_once __DIR__ . '/../../app/Core/AdminPaths.php';
admin_paths_bootstrap();
require_once admin_panel_paths()['panel_app'] . '/bootstrap_api.php';
