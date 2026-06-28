<?php

declare(strict_types=1);

/**
 * Sunucu tanisi — Apache split-deploy sorunlarini tespit eder.
 * https://DOMAIN/diagnose.php
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$started = microtime(true);
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$isBackend = str_contains($host, 'bo-nexthub') || str_contains($host, 'nexthub');

$result = [
    'ok' => true,
    'role' => $isBackend ? 'backend' : 'frontend',
    'host' => $host,
    'php' => PHP_VERSION,
    'time' => gmdate('c'),
    'checks' => [],
    'hints' => [],
];

$root = __DIR__;
$result['checks']['document_root'] = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
$result['checks']['script'] = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');

$serverSoft = strtolower((string) ($_SERVER['SERVER_SOFTWARE'] ?? ''));
$result['checks']['server_software'] = $serverSoft !== '' ? $serverSoft : 'unknown';
if (str_contains($serverSoft, 'nginx')) {
    $result['ok'] = false;
    $result['hints'][] = 'Sunucu nginx raporluyor — proje Apache-only: nginx Stop, Apache 80/443 (deploy/aapanel/APACHE-ONLY-TR.md)';
} elseif ($serverSoft !== '' && !str_contains($serverSoft, 'apache')) {
    $result['hints'][] = 'Beklenen web sunucusu: Apache + mod_rewrite + AllowOverride All';
}

if (is_readable($root . '/config/env.php')) {
    require_once $root . '/config/env.php';
    if (!defined('BASE_PATH')) {
        define('BASE_PATH', $root);
    }
    frontend_load_dotenv($root);
}

if (!$isBackend) {
    $result['checks']['frontend_api_only'] = function_exists('frontend_is_api_only') && frontend_is_api_only() ? 'yes' : 'no';
    $backendApi = trim((string) (getenv('API_BACKEND_MAIN_BASE_URL') ?: getenv('BACKEND_API_BASE_URL') ?: ''));
    $result['checks']['api_backend_main'] = $backendApi !== '' ? $backendApi : 'unset';

    if ($backendApi !== '' && function_exists('curl_init')) {
        $probe = rtrim($backendApi, '/') . '/content/sliders?category=home';
        $ch = curl_init($probe);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        if (defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $result['checks']['backend_sliders_probe'] = $code >= 200 && $code < 500
            ? 'ok:http_' . $code
            : 'fail:' . ($err !== '' ? $err : 'http_' . $code);
        if (!str_starts_with((string) $result['checks']['backend_sliders_probe'], 'ok:')) {
            $result['ok'] = false;
            $result['hints'][] = 'Frontend backend\'e ulasamiyor. once bo-nexthub.site/ping.php calissin.';
        }
    }
} else {
    if (is_readable($root . '/.env')) {
        $result['checks']['env'] = 'ok';
    } else {
        $result['checks']['env'] = 'missing';
        $result['ok'] = false;
        $result['hints'][] = 'https://bo-nexthub.site/install';
    }
}

$result['hints'][] = 'Apache-only: AllowOverride All, mod_rewrite, site .htaccess zip surumu';
$result['hints'][] = 'Test: curl -sS https://' . ($host !== '' ? $host : 'DOMAIN') . '/ping.php';

$result['elapsed_ms'] = (int) round((microtime(true) - $started) * 1000);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
