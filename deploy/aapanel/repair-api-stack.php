#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Tek komutla API stack onarımı (frontend veya backend sunucusunda).
 *
 * Usage:
 *   php deploy/aapanel/repair-api-stack.php [/path/to/site]
 *
 * Yapar:
 * - Circuit cache temizler
 * - Loopback cache temizler
 * - Frontend ise .env loopback ayarlarını otomatik doldurur
 * - Backend bağlantı testi yapar
 */

$root = dirname(__DIR__, 2);
foreach (array_slice($argv, 1) as $arg) {
    if (trim($arg) !== '' && !str_starts_with($arg, '-')) {
        $root = rtrim(str_replace('\\', '/', $arg), '/');
    }
}

echo "Repair API stack: {$root}\n";

$cacheFiles = [
    $root . '/storage/cache/cms_api_circuit.json',
    $root . '/storage/cache/member_api_circuit.json',
    $root . '/storage/cache/backend_internal_base.json',
];

foreach ($cacheFiles as $file) {
    if (is_file($file)) {
        unlink($file);
        echo "Deleted cache: {$file}\n";
    }
}

$isFrontend = is_readable($root . '/config/bootstrap_api.php')
    && !is_readable($root . '/app/bootstrap.php');

if ($isFrontend) {
    $fixScript = $root . '/deploy/aapanel/fix-frontend-env.php';
    if (is_readable($fixScript)) {
        passthru('php ' . escapeshellarg($fixScript) . ' ' . escapeshellarg($root), $code);
        if ($code !== 0) {
            echo "fix-frontend-env returned {$code}\n";
        }
    }
}

if (is_readable($root . '/config/env.php')) {
    require_once $root . '/config/env.php';
    if (!defined('BASE_PATH')) {
        define('BASE_PATH', $root);
    }
    if (function_exists('frontend_load_dotenv')) {
        frontend_load_dotenv($root);
    }
}

if (is_readable($root . '/services/SplitDeployDiagnostics.php')) {
    require_once $root . '/services/SplitDeployDiagnostics.php';
    $diag = SplitDeployDiagnostics::runFrontend($root, true);
    echo json_encode($diag, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit(!empty($diag['ok']) ? 0 : 1);
}

if (is_readable($root . '/services/BackendApiClient.php')) {
    require_once $root . '/config/backend_api.php';
    require_once $root . '/services/BackendApiClient.php';
    $base = BackendApiClient::effectiveOutboundMainBaseUrl();
    $headers = BackendApiClient::applyOutboundHostHeader([]);
    echo "Outbound base: {$base}\n";
    echo "Host headers: " . implode(', ', $headers) . "\n";
    $url = rtrim($base, '/') . '/site_settings.php';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(['Accept: application/json'], $headers));
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    echo "Probe {$url} => HTTP {$http}" . ($err !== '' ? " ({$err})" : '') . "\n";
    exit($http >= 200 && $http < 400 ? 0 : 1);
}

echo "Nothing to diagnose.\n";
exit(0);
