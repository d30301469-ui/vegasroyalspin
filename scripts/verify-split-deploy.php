<?php

declare(strict_types=1);

/**
 * Split-deploy sanity check (run before uploading zips).
 *
 * Usage: php scripts/verify-split-deploy.php
 */

$root = dirname(__DIR__);
$errors = [];
$warnings = [];

$requiredFrontendEnv = [
    'APP_ENV' => 'production',
    'FRONTEND_API_ONLY' => '1',
    'BACKEND_URL' => 'https://bo-nexthub.site',
    'BACKEND_API_BASE_URL' => 'https://bo-nexthub.site/api/v2',
    'API_BACKEND_MAIN_BASE_URL' => 'https://bo-nexthub.site/api/v2',
    'DEFAULT_ALLOWED_URL_HOSTS' => 'vegasroyalspin.com',
    'MEMBER_JWT_SECRET' => 'CHANGE-ME-SAME-ON-BOTH-HOSTS-32-CHARS!!',
    'SESSION_COOKIE_DOMAIN' => '.vegasroyalspin.com',
];

$frontendExample = $root . '/deploy/env/frontend.vegasroyalspin.env.example';
$backendExample = $root . '/deploy/env/backend.env.example';

foreach ([$frontendExample, $backendExample] as $file) {
    if (!is_file($file)) {
        $errors[] = 'Missing env example: ' . str_replace($root . '/', '', $file);
    }
}

$content = is_file($frontendExample) ? (string) file_get_contents($frontendExample) : '';
foreach ($requiredFrontendEnv as $key => $needle) {
    if ($content === '' || !str_contains($content, $key . '=')) {
        $errors[] = "Frontend env example missing {$key}";
    }
}

$backendContent = is_file($backendExample) ? (string) file_get_contents($backendExample) : '';
if ($backendContent !== '' && str_contains($backendContent, 'FRONTEND_API_ONLY=1')) {
    $errors[] = 'Backend env example must not set FRONTEND_API_ONLY=1';
}

foreach ([
    'api/CmsRemote.php',
    'config/bootstrap_api.php',
    'config/deploy_domains.php',
    'config/env.php',
    'config/app.php',
    'app/Core/FrontendInstallGate.php',
    'app/Services/FrontendInstaller.php',
    'app/Services/Api/PublicMemberApiDispatcher.php',
    'app/Views/install/wizard.php',
    'services/PublicApiV2Dispatcher.php',
    'services/BackendMemberApiProxy.php',
    'controllers/Api/ApiMemberV2BridgeController.php',
    'admin/app/Views/install/wizard.php',
    'admin/install.php',
    'install.php',
    'admin/api/v2/index.php',
    'admin/api/v2/bootstrap.php',
    'admin/app/bootstrap_api.php',
    'admin/api/v2/includes/member_api_kernel.php',
    'deploy/apache/vegasroyalspin.com.htaccess',
    'deploy/apache/bo-nexthub.site.htaccess',
] as $rel) {
    if (!is_file($root . '/' . $rel)) {
        $errors[] = 'Missing required file: ' . $rel;
    }
}

$appPhp = $root . '/config/app.php';
if (is_file($appPhp)) {
    $appSrc = (string) file_get_contents($appPhp);
    if (!str_contains($appSrc, "require_once __DIR__ . '/env.php'")
        || !str_contains($appSrc, 'frontend_load_dotenv(BASE_PATH)')
        || strpos($appSrc, "require_once __DIR__ . '/env.php'") > strpos($appSrc, 'frontend_load_dotenv(BASE_PATH)')) {
        $errors[] = 'config/app.php must require env.php before frontend_load_dotenv()';
    }
    if (!str_contains($appSrc, 'metropol_should_run_production_assertions()')) {
        $errors[] = 'config/app.php must guard production assertions';
    }
}

$routes = is_file($root . '/config/routes.php') ? (string) file_get_contents($root . '/config/routes.php') : '';
if ($routes !== '' && !str_contains($routes, 'class_exists')) {
    $errors[] = 'config/routes.php must guard callback controllers with class_exists()';
}

$apiIndex = $root . '/admin/api/v2/index.php';
if (is_file($apiIndex) && str_contains((string) file_get_contents($apiIndex), 'declare(strict_types=1)')) {
    $errors[] = 'admin/api/v2/index.php must not declare strict_types (included entry)';
}

$kernel = $root . '/admin/api/v2/includes/member_api_kernel.php';
if (is_file($kernel)) {
    $kernelSrc = (string) file_get_contents($kernel);
    if (str_starts_with($kernelSrc, "\xEF\xBB\xBF")) {
        $errors[] = 'UTF-8 BOM in member_api_kernel.php';
    }
    if (!str_contains($kernelSrc, "header('Content-Type: application/json")) {
        $warnings[] = 'member_api_kernel $json should set Content-Type';
    }
}

if (!is_file($root . '/admin/app/Core/AdminInstallGate.php')
    || !str_contains((string) file_get_contents($root . '/admin/app/Core/AdminInstallGate.php'), 'csrfTokenPath')) {
    $errors[] = 'AdminInstallGate missing file-based CSRF helpers';
}

if (!is_file($root . '/public/index.php')
    || !str_contains((string) file_get_contents($root . '/public/index.php'), 'FrontendInstallGate')) {
    $errors[] = 'public/index.php must redirect to /install when not installed';
}

$publicDispatcher = $root . '/services/PublicApiV2Dispatcher.php';
if (is_file($publicDispatcher)) {
    $dispatcherSrc = (string) file_get_contents($publicDispatcher);
    if (!str_contains($dispatcherSrc, 'ensureDispatcherLoaded')) {
        $errors[] = 'PublicApiV2Dispatcher must load PublicMemberApiDispatcher before isAllowed()';
    }
    if (!str_contains($dispatcherSrc, 'BackendMemberApiProxy::forward')) {
        $errors[] = 'PublicApiV2Dispatcher must proxy API-only requests to backend';
    }
}

require_once $root . '/app/Services/Api/PublicMemberApiDispatcher.php';
if (!\App\Services\Api\PublicMemberApiDispatcher::isAllowed('content/sliders')) {
    $errors[] = 'PublicMemberApiDispatcher must allow content/sliders route';
}

$auditScript = $root . '/scripts/audit-public-apis.php';
if (!is_file($auditScript)) {
    $warnings[] = 'Missing scripts/audit-public-apis.php';
} else {
    $auditOut = [];
    $auditCode = 0;
    exec(PHP_BINARY . ' ' . escapeshellarg($auditScript) . ' 2>&1', $auditOut, $auditCode);
    if ($auditCode !== 0) {
        $errors[] = 'audit-public-apis.php failed: ' . implode(' | ', $auditOut);
    }
}

$packageScript = $root . '/scripts/package-frontend-server.php';
if (is_file($packageScript)) {
    $src = (string) file_get_contents($packageScript);
    if (!str_contains($src, 'vegasroyalspin.com.htaccess')) {
        $warnings[] = 'Frontend package should use deploy/apache/vegasroyalspin.com.htaccess';
    }
    if (!str_contains($src, 'ApiBgamingWalletController.php')) {
        $warnings[] = 'Frontend package script should exclude ApiBgamingWalletController.php';
    }
}

$adminPackage = $root . '/scripts/package-admin-server.php';
if (is_file($adminPackage)) {
    $src = (string) file_get_contents($adminPackage);
    if (!str_contains($src, 'FrontendInstallController.php')) {
        $warnings[] = 'Admin package should exclude FrontendInstallController.php';
    }
    if (!str_contains($src, 'backend.env.example')) {
        $warnings[] = 'Admin package should ship deploy/env/backend.env.example';
    }
}

if ($errors !== []) {
    fwrite(STDERR, "FAIL:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

foreach ([
    'dist/admin-host/vendor/autoload.php' => 'Admin bundle vendor/',
    'dist/frontend-host/vendor/autoload.php' => 'Frontend bundle vendor/',
] as $rel => $label) {
    if (is_dir($root . '/dist') && !is_file($root . '/' . $rel)) {
        $warnings[] = "{$label} missing — run: php scripts/build-split-hosts.php";
    }
}

echo "OK: split-deploy configuration looks ready.\n";
if ($warnings !== []) {
    echo "Warnings:\n- " . implode("\n- ", $warnings) . "\n";
}

exit(0);
