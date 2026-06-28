#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Backend .env — ALLOWED_URL_HOSTS + API_PUBLIC_BASE_URL doğrulama (CORS için kritik).
 * Usage: php deploy/aapanel/fix-backend-env.php [/path/to/bo-nexthub.site]
 */

$root = dirname(__DIR__, 2);
foreach (array_slice($argv, 1) as $arg) {
    if (trim($arg) !== '' && !str_starts_with($arg, '-')) {
        $root = rtrim(str_replace('\\', '/', $arg), '/');
    }
}

$envFile = $root . '/.env';
if (!is_readable($envFile)) {
    fwrite(STDERR, "No .env at {$envFile}\n");
    exit(1);
}

require_once $root . '/config/deploy_domains.php';
require_once $root . '/app/Services/InstallEnvBuilder.php';

$frontendUrl = deploy_domain('frontend_url');
$backendUrl = deploy_domain('backend_url');
$defaults = InstallEnvBuilder::buildBackendEnv([
    'root' => $root,
    'frontend_url' => $frontendUrl,
    'backend_url' => $backendUrl,
    'app_key' => 'CHANGE-ME-32-CHARS-MINIMUM-BACKEND!!',
    'member_jwt_secret' => 'CHANGE-ME-SAME-ON-BOTH-HOSTS-32-CHARS!!',
    'frontend_cms_purge_secret' => 'CHANGE-ME-SAME-ON-BOTH-HOSTS-PURGE-SECRET!!',
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_database' => 'metropol_db',
    'db_username' => 'root',
    'db_password' => '',
]);

$patchKeys = [
    'ALLOWED_URL_HOSTS',
    'DEFAULT_ALLOWED_URL_HOSTS',
    'PUBLIC_URL_HOSTS',
    'API_PUBLIC_BASE_URL',
    'API_BACKEND_MAIN_BASE_URL',
    'BACKEND_API_BASE_URL',
    'FRONTEND_URL',
    'SITE_URL',
    'DRAKON_SITE_ENDPOINT',
    'MEMBER_JWT_SECRET',
    'FRONTEND_CMS_PURGE_SECRET',
];

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
$allowedDefault = $defaults['ALLOWED_URL_HOSTS'] ?? deploy_allowed_url_hosts($frontendUrl, $backendUrl);

foreach ($patchKeys as $key) {
    $current = trim($values[$key] ?? '');
    $expected = trim($defaults[$key] ?? '');
    if ($expected === '') {
        continue;
    }
    if ($current === '') {
        $updates[$key] = $expected;
        continue;
    }
    if (in_array($key, ['ALLOWED_URL_HOSTS', 'DEFAULT_ALLOWED_URL_HOSTS', 'PUBLIC_URL_HOSTS'], true)) {
        $missingFrontend = false;
        foreach (['vegasroyalspin.com', 'www.vegasroyalspin.com', 'm.vegasroyalspin.com'] as $host) {
            if (!str_contains(strtolower($current), $host)) {
                $missingFrontend = true;
                break;
            }
        }
        if ($missingFrontend) {
            $updates[$key] = $allowedDefault;
        }
    }
}

$apiPublic = InstallEnvBuilder::resolveApiPublicBaseUrl($backendUrl);
foreach (['API_PUBLIC_BASE_URL', 'API_BACKEND_MAIN_BASE_URL', 'BACKEND_API_BASE_URL'] as $apiKey) {
    $host = strtolower((string) (parse_url($values[$apiKey] ?? '', PHP_URL_HOST) ?: ''));
    if (($values[$apiKey] ?? '') === '' || !str_starts_with($host, 'api.')) {
        $updates[$apiKey] = $apiPublic;
    }
}

$drakonSite = rtrim($backendUrl, '/');
if (trim($values['DRAKON_SITE_ENDPOINT'] ?? '') === '' || str_contains(strtolower((string) ($values['DRAKON_SITE_ENDPOINT'] ?? '')), 'vegasroyalspin')) {
    $updates['DRAKON_SITE_ENDPOINT'] = $drakonSite;
}

if ($updates === []) {
    echo "Backend .env looks OK (CORS hosts configured).\n";
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
        if (isset($patched[$key])) {
            $out[] = $key . '=' . $patched[$key];
            unset($patched[$key]);
            continue;
        }
        $out[] = $line;
    }
    foreach ($patched as $key => $value) {
        $out[] = $key . '=' . $value;
    }

    file_put_contents($envFile, implode(PHP_EOL, $out) . PHP_EOL);

    echo "Backend .env updated (CORS / API URLs):\n";
    foreach ($updates as $key => $value) {
        echo "  {$key}={$value}\n";
    }
}

foreach (['MEMBER_JWT_SECRET', 'FRONTEND_CMS_PURGE_SECRET'] as $secretKey) {
    $secretVal = trim($values[$secretKey] ?? $updates[$secretKey] ?? '');
    if ($secretVal === '' || str_contains($secretVal, 'CHANGE-ME')) {
        fwrite(STDERR, "WARNING: {$secretKey} eksik veya placeholder — frontend ile AYNI değer olmalı (balance 401).\n");
    }
}

fwrite(STDERR, "TIP: JWT tablosu için: php deploy/aapanel/ensure-member-jwt-table.php {$root}\n");
