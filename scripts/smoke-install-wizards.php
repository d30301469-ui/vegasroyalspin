<?php

declare(strict_types=1);

/**
 * Local smoke test for install wizard classes (no HTTP).
 *
 * Usage: php scripts/smoke-install-wizards.php
 */

$root = dirname(__DIR__);
$errors = [];

define('METROPOL_INSTALL_WIZARD', true);
require_once $root . '/config/env.php';

foreach ([
    'admin/app/Core/AdminInstallGate.php',
    'admin/app/Services/AdminInstaller.php',
    'admin/app/Controllers/AdminInstallController.php',
    'admin/app/Views/install/wizard.php',
    'admin/app/Views/install/complete.php',
    'app/Core/FrontendInstallGate.php',
    'app/Services/FrontendInstaller.php',
    'app/Services/InstallEnvBuilder.php',
    'app/Controllers/FrontendInstallController.php',
    'app/Views/install/wizard.php',
    'app/Views/install/complete.php',
    'config/bootstrap_api.php',
] as $rel) {
    if (!is_file($root . '/' . $rel)) {
        $errors[] = 'Missing: ' . $rel;
    }
}

require_once $root . '/admin/app/Core/AdminInstallGate.php';
require_once $root . '/admin/app/Services/AdminInstaller.php';
require_once $root . '/app/Core/FrontendInstallGate.php';
require_once $root . '/app/Services/InstallEnvBuilder.php';

if (!function_exists('frontend_load_dotenv')) {
    $errors[] = 'frontend_load_dotenv() missing from config/env.php';
}

if (!function_exists('metropol_should_run_production_assertions')) {
    $errors[] = 'metropol_should_run_production_assertions() missing';
}

$tmp = sys_get_temp_dir() . '/metropol-install-smoke-' . bin2hex(random_bytes(4));
mkdir($tmp . '/storage', 0755, true);
mkdir($tmp . '/config', 0755, true);
copy($root . '/config/env.php', $tmp . '/config/env.php');
copy($root . '/config/deploy_domains.php', $tmp . '/config/deploy_domains.php');

if (!FrontendInstallGate::verifyCsrfToken($tmp, null)) {
    $token = FrontendInstallGate::csrfToken($tmp);
    if (!FrontendInstallGate::verifyCsrfToken($tmp, $token)) {
        $errors[] = 'FrontendInstallGate CSRF verify failed';
    }
}

$hosts = AdminInstaller::hostListsForUrls('https://vegasroyalspin.com', 'bo-nexthub.site');
if ($hosts[0] === '' || !str_contains($hosts[0], 'vegasroyalspin.com')) {
    $errors[] = 'AdminInstaller::hostListsForUrls public hosts invalid';
}
if ($hosts[1] === '' || !str_contains($hosts[1], 'api.bo-nexthub.site')) {
    $errors[] = 'AdminInstaller::hostListsForUrls allowed hosts missing api subdomain';
}

$backendEnv = InstallEnvBuilder::buildBackendEnv([
    'root' => $root,
    'backend_host' => 'bo-nexthub.site',
    'backend_url' => 'https://bo-nexthub.site',
    'frontend_url' => 'https://vegasroyalspin.com',
    'app_key' => str_repeat('a', 32),
    'member_jwt_secret' => str_repeat('b', 32),
    'frontend_cms_purge_secret' => str_repeat('c', 32),
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_database' => 'metropol_db',
    'db_username' => 'metropol_user',
    'db_password' => 'secret',
]);
if (($backendEnv['API_PUBLIC_BASE_URL'] ?? '') !== 'https://api.bo-nexthub.site/api/v2') {
    $errors[] = 'InstallEnvBuilder backend API_PUBLIC_BASE_URL invalid';
}
if (($backendEnv['API_BACKEND_FALLBACK_BASE_URL'] ?? '') !== 'https://bo-nexthub.site/api/v2') {
    $errors[] = 'InstallEnvBuilder backend API_BACKEND_FALLBACK_BASE_URL invalid';
}

$frontendEnv = InstallEnvBuilder::buildFrontendEnv([
    'frontend_url' => 'https://vegasroyalspin.com',
    'backend_url' => 'https://bo-nexthub.site',
    'app_key' => str_repeat('a', 32),
    'member_jwt_secret' => str_repeat('b', 32),
    'frontend_cms_purge_secret' => str_repeat('c', 32),
    'session_cookie_domain' => '.vegasroyalspin.com',
]);
if (!in_array((string) ($frontendEnv['FRONTEND_DIRECT_MEMBER_API'] ?? ''), ['0', '1'], true)) {
    $errors[] = 'InstallEnvBuilder frontend missing FRONTEND_DIRECT_MEMBER_API';
}
if (($frontendEnv['API_BACKEND_MAIN_BASE_URL'] ?? '') !== 'https://api.bo-nexthub.site/api/v2') {
    $errors[] = 'InstallEnvBuilder frontend API_BACKEND_MAIN_BASE_URL invalid';
}
if (($frontendEnv['API_PUBLIC_BASE_URL'] ?? '') !== 'https://api.bo-nexthub.site/api/v2') {
    $errors[] = 'InstallEnvBuilder frontend API_PUBLIC_BASE_URL invalid';
}
if (($frontendEnv['FRONTEND_DIRECT_MEMBER_API'] ?? '') !== '0') {
    $errors[] = 'InstallEnvBuilder frontend FRONTEND_DIRECT_MEMBER_API must be 0';
}
if (isset($frontendEnv['API_BACKEND_INTERNAL_BASE_URL'])) {
    $errors[] = 'InstallEnvBuilder frontend must not set API_BACKEND_INTERNAL_BASE_URL';
}

$finalized = InstallEnvBuilder::finalizeSplitFrontendEnv($frontendEnv);
$feErrors = InstallEnvBuilder::validateSplitFrontendEnv($finalized);
if ($feErrors !== []) {
    $errors[] = 'validateSplitFrontendEnv failed: ' . implode(', ', $feErrors);
}

$finalBackend = InstallEnvBuilder::finalizeBackendEnv($backendEnv);
$beErrors = InstallEnvBuilder::validateBackendEnv($finalBackend);
if ($beErrors !== []) {
    $errors[] = 'validateBackendEnv failed: ' . implode(', ', $beErrors);
}

@array_map('unlink', glob($tmp . '/storage/*') ?: []);
@rmdir($tmp . '/storage');
@rmdir($tmp . '/config');
@rmdir($tmp);

if ($errors !== []) {
    fwrite(STDERR, "FAIL:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "OK: install wizard smoke test passed\n";
