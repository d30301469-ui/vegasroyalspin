<?php

declare(strict_types=1);

/**
 * Sunucu tanisi — Apache split-deploy sorunlarini tespit eder.
 * https://DOMAIN/diagnose.php
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$root = __DIR__;
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$isBackend = str_contains($host, 'bo-nexthub') || str_contains($host, 'nexthub');

if (!$isBackend) {
    require_once $root . '/services/SplitDeployDiagnostics.php';
    $result = SplitDeployDiagnostics::runFrontend($root, true);
    $result['host'] = $host;
    $result['checks']['document_root'] = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
    $serverSoft = strtolower((string) ($_SERVER['SERVER_SOFTWARE'] ?? ''));
    if (str_contains($serverSoft, 'nginx')) {
        $result['ok'] = false;
        $result['hints'][] = 'Sunucu nginx raporluyor — proje Apache-only: aaPanel nginx Stop, Apache 80/443 dinlesin (deploy/aapanel/APACHE-ONLY-TR.md)';
    } elseif (!str_contains($serverSoft, 'apache')) {
        $result['hints'][] = 'Beklenen web sunucusu: Apache + mod_rewrite + AllowOverride All';
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$started = microtime(true);
$result = [
    'ok' => true,
    'role' => 'backend',
    'host' => $host,
    'php' => PHP_VERSION,
    'time' => gmdate('c'),
    'checks' => [],
    'hints' => [],
];

$result['checks']['document_root'] = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
$result['checks']['env'] = is_readable($root . '/.env') ? 'ok' : 'missing';
if ($result['checks']['env'] === 'missing') {
    $result['ok'] = false;
    $result['hints'][] = 'https://bo-nexthub.site/install';
}

$result['elapsed_ms'] = (int) round((microtime(true) - $started) * 1000);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
