<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$started = microtime(true);
$result = [
    'ok' => true,
    'role' => 'backend',
    'php' => PHP_VERSION,
    'time' => gmdate('c'),
    'document_root' => (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''),
    'checks' => [],
    'hints' => [],
];

$root = __DIR__;
$envFile = $root . '/.env';
$result['checks']['env_file'] = is_readable($envFile) ? 'ok' : 'missing';

$dbOk = false;
$dbError = '';
$pdo = null;
if (is_readable($envFile)) {
    if (!function_exists('metropol_pdo_options')) {
        $envHelper = $root . '/config/env.php';
        if (is_readable($envHelper)) {
            require_once $envHelper;
        }
    }

    $env = [];
    foreach (file($envFile, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim((string) $line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value, " \t\"'");
    }

    $host = $env['DB_HOST'] ?? $env['ADMIN_DB_HOST'] ?? '127.0.0.1';
    $port = (int) ($env['DB_PORT'] ?? $env['ADMIN_DB_PORT'] ?? 3306);
    $database = $env['DB_DATABASE'] ?? $env['ADMIN_DB_DATABASE'] ?? '';
    $username = $env['DB_USERNAME'] ?? $env['ADMIN_DB_USERNAME'] ?? 'root';
    $password = $env['DB_PASSWORD'] ?? $env['ADMIN_DB_PASSWORD'] ?? '';

    if ($database !== '') {
        try {
            $options = function_exists('metropol_pdo_options')
                ? metropol_pdo_options()
                : [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ];
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database),
                $username,
                $password,
                $options
            );
            $dbOk = (int) $pdo->query('SELECT 1')->fetchColumn() === 1;
        } catch (Throwable $e) {
            $dbError = $e->getMessage();
        }
    } else {
        $dbError = 'DB_DATABASE not configured';
    }
}

$result['checks']['database'] = $dbOk ? 'ok' : ($dbError !== '' ? 'error' : 'skipped');
if ($dbError !== '') {
    $result['checks']['database_error'] = $dbError;
}
if (!$dbOk && is_readable($envFile)) {
    $result['ok'] = false;
}

if ($pdo instanceof PDO) {
    try {
        $jwtTable = $pdo->query("SHOW TABLES LIKE 'member_jwt_tokens'")->fetchColumn();
        $result['checks']['member_jwt_tokens_table'] = $jwtTable !== false ? 'ok' : 'missing';
        if ($jwtTable === false) {
            $result['ok'] = false;
            $result['hints'][] = 'member_jwt_tokens tablosu eksik — /install veya migration çalıştırın';
        }
    } catch (Throwable $e) {
        $result['checks']['member_jwt_tokens_table'] = 'error';
    }
}

$apiBase = '';
if (isset($env) && is_array($env)) {
    $apiBase = rtrim(trim((string) ($env['API_PUBLIC_BASE_URL'] ?? $env['API_BACKEND_MAIN_BASE_URL'] ?? '')), '/');
}
$result['checks']['api_public_base'] = $apiBase !== '' ? $apiBase : 'unset';

if ($apiBase !== '' && is_readable($root . '/services/BackendConnectivityProbe.php')) {
    require_once $root . '/services/BackendConnectivityProbe.php';
    $probe = BackendConnectivityProbe::curl($apiBase . '/site_settings.php', [], 6);
    $result['checks']['api_site_settings'] = $probe['ok'] ? 'ok:http_' . $probe['http'] : 'fail:' . $probe['error'];
    if (!$probe['ok']) {
        $result['ok'] = false;
    }

    $frontendOrigin = 'https://vegasroyalspin.com';
    if (!empty($env['FRONTEND_URL'])) {
        $frontendOrigin = rtrim((string) $env['FRONTEND_URL'], '/');
    }
    $corsProbe = BackendConnectivityProbe::curl($apiBase . '/auth/login', [
        'X-Custom-Method: OPTIONS',
        'Origin: ' . $frontendOrigin,
        'Access-Control-Request-Method: POST',
    ], 6);
    $result['checks']['api_cors_origin'] = $frontendOrigin;
}

$result['elapsed_ms'] = (int) round((microtime(true) - $started) * 1000);

http_response_code($result['ok'] ? 200 : 503);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
