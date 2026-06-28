#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Cloudflare edge SSL + origin HTTP (aaPanel) — .env normalizer.
 *
 * - Sets CLOUDFLARE_SSL=1, ORIGIN_HTTP=1
 * - Public URLs → https:// (SITE_URL, BACKEND_URL, API_BACKEND_*)
 * - Detects API_BACKEND_INTERNAL_* loopback on same server
 *
 * Usage:
 *   php deploy/aapanel/fix-cloudflare-env.php [/path/to/site]
 *   php deploy/aapanel/fix-cloudflare-env.php --from-example [/path]
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
        exit(1);
    }
}

require_once $root . '/config/cloudflare.php';
require_once $root . '/config/deploy_domains.php';
if (is_readable($root . '/app/Services/FrontendInstaller.php')) {
    require_once $root . '/app/Services/FrontendInstaller.php';
}

$isFrontend = is_readable($root . '/app/Core/FrontendInstallGate.php')
    && !is_readable($root . '/services/MegaPayzService.php');
$isBackend = is_readable($root . '/app/Core/AdminInstallGate.php')
    || is_readable($root . '/services/MegaPayzService.php');

$publicUrlKeys = ['SITE_URL', 'FRONTEND_URL', 'FRONTEND_FALLBACK_URL', 'BACKEND_URL', 'BACKEND_FALLBACK_URL'];
$apiKeys = ['BACKEND_API_BASE_URL', 'API_BACKEND_MAIN_BASE_URL', 'API_BACKEND_FALLBACK_BASE_URL'];

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

putenv('CLOUDFLARE_SSL=1');
putenv('ORIGIN_HTTP=1');
$_ENV['CLOUDFLARE_SSL'] = '1';
$_ENV['ORIGIN_HTTP'] = '1';

$updates = [
    'CLOUDFLARE_SSL' => '1',
    'ORIGIN_HTTP' => '1',
];

if ($isFrontend && (!isset($values['FRONTEND_API_ONLY']) || trim($values['FRONTEND_API_ONLY']) === '')) {
    $updates['FRONTEND_API_ONLY'] = '1';
}

foreach (array_merge($publicUrlKeys, $apiKeys) as $key) {
    if (!isset($values[$key]) || trim($values[$key]) === '') {
        continue;
    }
    $normalized = class_exists('FrontendInstaller', false)
        ? FrontendInstaller::normalizeSiteOrigin($values[$key])
        : $values[$key];
    $coerced = metropol_coerce_public_https_url($normalized);
    if ($coerced !== $values[$key]) {
        $updates[$key] = $coerced;
    }
}

if (
    $isFrontend
    && (!isset($values['API_BACKEND_INTERNAL_BASE_URL']) || trim($values['API_BACKEND_INTERNAL_BASE_URL']) === '')
    && is_readable($root . '/services/BackendConnectivityProbe.php')
) {
    require_once $root . '/services/BackendConnectivityProbe.php';
    $backendHost = trim($values['BACKEND_HOST'] ?? '');
    if ($backendHost === '') {
        $backendHost = strtolower((string) (parse_url($values['BACKEND_URL'] ?? deploy_domain('backend_url'), PHP_URL_HOST) ?: 'bo-nexthub.site'));
    }
    $detected = BackendConnectivityProbe::detectInternalConfig($backendHost);
    if ($detected !== null) {
        $updates['API_BACKEND_INTERNAL_BASE_URL'] = $detected['internal_base'];
        $updates['API_BACKEND_INTERNAL_HOST'] = $detected['internal_host'];
    }
}

if ($updates === []) {
    echo "Cloudflare .env already OK.\n";
    exit(0);
}

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
    $escaped = str_contains($value, ' ') || str_contains($value, '#') || str_contains($value, '"')
        ? '"' . str_replace('"', '\\"', $value) . '"'
        : $value;
    $out[] = $key . '=' . $escaped;
    echo "Add {$key}={$value}\n";
}

file_put_contents($envFile, implode("\n", $out) . "\n");
echo "Cloudflare .env updated (backup created).\n";
echo "aaPanel: Force HTTPS OFF, SSL/Let's Encrypt not required on origin.\n";
echo "Cloudflare: SSL/TLS Flexible (or Full strict + Origin Certificate), Always Use HTTPS ON.\n";
