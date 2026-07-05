#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Frontend .env doğrulama ve otomatik düzeltme (split deploy).
 * Usage:
 *   php deploy/aapanel/fix-frontend-env.php [/path/to/vegasroyalspin.com]
 *   php deploy/aapanel/fix-frontend-env.php --from-example [/path]
 */

$root = dirname(__DIR__, 2);
$fromExample = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--from-example') {
        $fromExample = true;
        continue;
    }
    if (trim($arg) !== '' && !str_starts_with($arg, '-')) {
        $root = rtrim(str_replace('\\', '/', $arg), '/');
    }
}

$envFile = $root . '/.env';
$exampleFile = $root . '/ENV.example';

if (!is_readable($envFile)) {
    if ($fromExample && is_readable($exampleFile)) {
        copy($exampleFile, $envFile);
        echo "Created .env from ENV.example\n";
    } else {
        fwrite(STDERR, "No .env at {$envFile}\n");
        fwrite(STDERR, "Run: cp ENV.example .env && php deploy/aapanel/fix-frontend-env.php --from-example\n");
        fwrite(STDERR, "Or: https://vegasroyalspin.com/install\n");
        exit(1);
    }
}

require_once $root . '/config/cloudflare.php';
require_once $root . '/app/Services/FrontendInstaller.php';
require_once $root . '/app/Services/InstallEnvBuilder.php';
require_once $root . '/config/deploy_domains.php';

putenv('CLOUDFLARE_SSL=1');
putenv('ORIGIN_HTTP=1');

$defaults = InstallEnvBuilder::buildFrontendEnv([
    'frontend_url' => deploy_domain('frontend_url'),
    'backend_url' => deploy_domain('backend_url'),
    'app_key' => 'CHANGE-ME-32-CHARS-MINIMUM-FRONTEND!!',
    'member_jwt_secret' => 'CHANGE-ME-SAME-ON-BOTH-HOSTS-32-CHARS!!',
    'frontend_cms_purge_secret' => 'CHANGE-ME-SAME-ON-BOTH-HOSTS-PURGE-SECRET!!',
    'session_cookie_domain' => deploy_domain('session_cookie_domain'),
]);

$originKeys = ['SITE_URL', 'FRONTEND_URL', 'FRONTEND_FALLBACK_URL'];
$backendKeys = ['BACKEND_URL', 'BACKEND_FALLBACK_URL', 'BACKEND_API_BASE_URL', 'API_BACKEND_MAIN_BASE_URL', 'API_BACKEND_FALLBACK_BASE_URL'];
$patchKeys = array_merge(
    $originKeys,
    $backendKeys,
    ['FRONTEND_API_ONLY', 'FRONTEND_DIRECT_MEMBER_API', 'FRONTEND_CMS_PURGE_SECRET', 'MEMBER_JWT_SECRET', 'APP_ENV', 'CLOUDFLARE_SSL', 'ORIGIN_HTTP', 'API_BACKEND_INTERNAL_BASE_URL', 'API_BACKEND_INTERNAL_HOST', 'BACKEND_HOST', 'PUBLIC_URL_HOSTS', 'ALLOWED_URL_HOSTS', 'DEFAULT_ALLOWED_URL_HOSTS', 'SESSION_COOKIE_DOMAIN']
);

$lines = file($envFile, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    fwrite(STDERR, "Cannot read .env\n");
    exit(1);
}

/** @var array<string, string> $values */
$values = [];
foreach ($lines as $line) {
    $trimmed = trim((string) $line);
    if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
        continue;
    }
    [$key, $value] = explode('=', $trimmed, 2);
    $key = trim($key);
    $value = trim($value, " \t\"'");
    if ($key !== '') {
        $values[$key] = $value;
    }
}

$updates = [];

foreach ($originKeys as $key) {
    if (!isset($values[$key]) || trim($values[$key]) === '') {
        $updates[$key] = $defaults[$key];
        continue;
    }
    $normalized = FrontendInstaller::normalizeSiteOrigin($values[$key]);
    $coerced = function_exists('metropol_coerce_public_https_url')
        ? metropol_coerce_public_https_url($normalized)
        : $normalized;
    if ($coerced !== $values[$key]) {
        $updates[$key] = $coerced;
    } elseif ($normalized !== $values[$key]) {
        $updates[$key] = $normalized;
    }
}

foreach ($backendKeys as $key) {
    if (!isset($values[$key]) || trim($values[$key]) === '') {
        $updates[$key] = $defaults[$key];
        continue;
    }
    $host = strtolower((string) (parse_url($values[$key], PHP_URL_HOST) ?: ''));
    if (in_array($host, ['localhost', '127.0.0.1'], true) || str_ends_with($host, '.test')) {
        $updates[$key] = $defaults[$key];
        continue;
    }
    $coerced = function_exists('metropol_coerce_public_https_url')
        ? metropol_coerce_public_https_url($values[$key])
        : $values[$key];
    if ($coerced !== $values[$key]) {
        $updates[$key] = $coerced;
    }
}

$backendUrl = $values['BACKEND_URL'] ?? $defaults['BACKEND_URL'];
$mainBackendHost = strtolower((string) (parse_url($backendUrl, PHP_URL_HOST) ?: ''));
$apiPublic = InstallEnvBuilder::resolveApiPublicBaseUrl($backendUrl);
$apiFallback = InstallEnvBuilder::resolveApiFallbackBaseUrl($backendUrl);
foreach (['API_BACKEND_MAIN_BASE_URL', 'BACKEND_API_BASE_URL', 'API_PUBLIC_BASE_URL'] as $apiKey) {
    $current = trim($values[$apiKey] ?? '');
    $host = strtolower((string) (parse_url($current, PHP_URL_HOST) ?: ''));
    if ($current === '' || ($mainBackendHost !== '' && $host === $mainBackendHost)) {
        $updates[$apiKey] = $apiPublic;
    }
}
if (!isset($values['API_BACKEND_FALLBACK_BASE_URL']) || trim($values['API_BACKEND_FALLBACK_BASE_URL']) === '') {
    $updates['API_BACKEND_FALLBACK_BASE_URL'] = $apiFallback;
}

foreach (['FRONTEND_API_ONLY', 'FRONTEND_DIRECT_MEMBER_API', 'APP_ENV', 'CLOUDFLARE_SSL', 'ORIGIN_HTTP'] as $key) {
    if (!isset($values[$key]) || trim($values[$key]) === '') {
        $updates[$key] = $defaults[$key] ?? '1';
    }
}
$updates['FRONTEND_DIRECT_MEMBER_API'] = '0';
$updates['FRONTEND_API_ONLY'] = '1';

$apiOnly = ($values['FRONTEND_API_ONLY'] ?? $updates['FRONTEND_API_ONLY'] ?? $defaults['FRONTEND_API_ONLY'] ?? '0') === '1';
if ($apiOnly) {
    $updates['API_BACKEND_INTERNAL_BASE_URL'] = '';
    $updates['API_BACKEND_INTERNAL_HOST'] = '';
    $internalCache = $root . '/storage/cache/backend_internal_base.json';
    if (is_file($internalCache)) {
        unlink($internalCache);
        echo "Cleared backend_internal_base.json (split-deploy: member API uses public backend)\n";
    }
}

if (
    !$apiOnly
    && (!isset($values['API_BACKEND_INTERNAL_BASE_URL']) || trim($values['API_BACKEND_INTERNAL_BASE_URL']) === '')
    && is_readable($root . '/services/BackendConnectivityProbe.php')
) {
    require_once $root . '/services/BackendConnectivityProbe.php';
    $backendHost = trim($values['BACKEND_HOST'] ?? '');
    if ($backendHost === '') {
        $backendHost = $mainBackendHost !== '' ? $mainBackendHost : 'admin.vegasroyalspin.com';
    }
    $detected = BackendConnectivityProbe::detectInternalConfig($backendHost);
    if ($detected !== null) {
        $updates['API_BACKEND_INTERNAL_BASE_URL'] = $detected['internal_base'];
        $updates['API_BACKEND_INTERNAL_HOST'] = $detected['internal_host'];
    }
}

if ($updates === []) {
    echo "Frontend .env looks OK.\n";
    $circuitPath = $root . '/storage/cache/cms_api_circuit.json';
    if (is_file($circuitPath)) {
        unlink($circuitPath);
        echo "Cleared CMS API circuit cache (slider/backend fetch)\n";
    }
} else {
    copy($envFile, $envFile . '.bak.' . date('YmdHis'));

    $out = [];
$patched = $updates;
foreach ($lines as $line) {
    $trimmed = trim((string) $line);
    if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
        $out[] = $line;
        continue;
    }
    [$key, ] = explode('=', $trimmed, 2);
    $key = trim($key);
    if ($key !== '' && array_key_exists($key, $patched)) {
        $value = $patched[$key];
        unset($patched[$key]);
        if ($value === '' && in_array($key, ['API_BACKEND_INTERNAL_BASE_URL', 'API_BACKEND_INTERNAL_HOST'], true)) {
            echo "Remove {$key}\n";
            continue;
        }
        $escaped = str_contains($value, ' ') || str_contains($value, '#') || str_contains($value, '"')
            ? '"' . str_replace('"', '\\"', $value) . '"'
            : $value;
        $out[] = $key . '=' . $escaped;
        echo "Fix {$key}={$value}\n";
        continue;
    }
    $out[] = $line;
}

foreach ($patched as $key => $value) {
    if ($value === '' && in_array($key, ['API_BACKEND_INTERNAL_BASE_URL', 'API_BACKEND_INTERNAL_HOST'], true)) {
        continue;
    }
    $escaped = str_contains($value, ' ') || str_contains($value, '#') || str_contains($value, '"')
        ? '"' . str_replace('"', '\\"', $value) . '"'
        : $value;
    $out[] = $key . '=' . $escaped;
    echo "Add {$key}={$value}\n";
}

file_put_contents($envFile, implode("\n", $out) . "\n");

$circuitPath = $root . '/storage/cache/cms_api_circuit.json';
if (is_file($circuitPath)) {
    unlink($circuitPath);
    echo "Cleared CMS API circuit cache (slider/backend fetch)\n";
}
echo "Updated .env (backup created).\n";
}

foreach (['MEMBER_JWT_SECRET', 'FRONTEND_CMS_PURGE_SECRET'] as $secretKey) {
    $secretVal = trim($values[$secretKey] ?? $updates[$secretKey] ?? '');
    if ($secretVal === '' || str_contains($secretVal, 'CHANGE-ME')) {
        fwrite(STDERR, "WARNING: {$secretKey} eksik veya placeholder — backend ile AYNI değer olmalı (balance 401).\n");
    }
}
