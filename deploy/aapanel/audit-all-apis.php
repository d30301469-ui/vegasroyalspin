#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Frontend + backend API zinciri tam denetim.
 * Usage:
 *   php deploy/aapanel/audit-all-apis.php [frontend-root]
 *   php deploy/aapanel/audit-all-apis.php --backend [backend-root]
 */

$mode = 'frontend';
$root = dirname(__DIR__, 2);
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--backend') {
        $mode = 'backend';
        continue;
    }
    if (trim($arg) !== '' && !str_starts_with($arg, '-')) {
        $root = rtrim(str_replace('\\', '/', $arg), '/');
    }
}

$fail = 0;
$warn = 0;
$ok = 0;

$line = static function (string $level, string $msg) use (&$fail, &$warn, &$ok): void {
    if ($level === 'FAIL') {
        $fail++;
        fwrite(STDERR, "FAIL  {$msg}\n");
    } elseif ($level === 'WARN') {
        $warn++;
        echo "WARN  {$msg}\n";
    } else {
        $ok++;
        echo "OK    {$msg}\n";
    }
};

echo "=== API audit ({$mode}) root={$root} ===\n\n";

$envPath = $root . '/.env';
if (!is_readable($envPath)) {
    $line('FAIL', '.env missing');
    exit(1);
}

require_once $root . '/config/env.php';
frontend_load_dotenv($root);

$secrets = [
    'MEMBER_JWT_SECRET' => trim(frontend_env_string('MEMBER_JWT_SECRET', '')),
    'FRONTEND_CMS_PURGE_SECRET' => trim(frontend_env_string('FRONTEND_CMS_PURGE_SECRET', '')),
];
foreach ($secrets as $key => $val) {
    if ($val === '' || str_contains($val, 'CHANGE-ME')) {
        $line('FAIL', "{$key} missing or placeholder");
    } elseif (strlen($val) < 32) {
        $line('WARN', "{$key} shorter than 32 chars");
    } else {
        $line('OK', "{$key} set");
    }
}

if ($mode === 'frontend') {
    require_once $root . '/config/bootstrap_api.php';
    require_once $root . '/services/BackendApiClient.php';

    $apiOnly = function_exists('frontend_is_api_only') && frontend_is_api_only();
    $line($apiOnly ? 'OK' : 'WARN', 'FRONTEND_API_ONLY=' . ($apiOnly ? '1' : '0'));

    $direct = function_exists('frontend_env_bool') && frontend_env_bool('FRONTEND_DIRECT_MEMBER_API');
    $line($direct ? 'WARN' : 'OK', 'FRONTEND_DIRECT_MEMBER_API=' . ($direct ? '1 (browser hits api subdomain)' : '0 (proxy only)'));

    if (function_exists('frontend_database_allowed') && frontend_database_allowed()) {
        $line('WARN', 'frontend_database_allowed=true — split frontend should be API-only');
    } else {
        $line('OK', 'No direct MySQL on frontend');
    }

    $main = defined('API_BACKEND_MAIN_BASE_URL') ? (string) API_BACKEND_MAIN_BASE_URL : '';
    $host = strtolower((string) (parse_url($main, PHP_URL_HOST) ?: ''));
    if ($main === '') {
        $line('FAIL', 'API_BACKEND_MAIN_BASE_URL empty');
    } elseif (str_starts_with($host, 'api.')) {
        $line('OK', "API_BACKEND_MAIN_BASE_URL={$main}");
    } else {
        $line('WARN', "API_BACKEND_MAIN_BASE_URL host should be api.* — got {$main}");
    }

    if (defined('API_BACKEND_FALLBACK_BASE_URL') && API_BACKEND_FALLBACK_BASE_URL !== '') {
        $line('OK', 'API_BACKEND_FALLBACK_BASE_URL=' . API_BACKEND_FALLBACK_BASE_URL);
    } else {
        $line('WARN', 'API_BACKEND_FALLBACK_BASE_URL constant not defined');
    }

    $internal = defined('API_BACKEND_INTERNAL_BASE_URL') ? trim((string) API_BACKEND_INTERNAL_BASE_URL) : '';
    if ($apiOnly && $internal !== '' && str_contains($internal, '127.0.0.1')) {
        $line('WARN', "API_BACKEND_INTERNAL_BASE_URL={$internal} — member API must use public URL, not loopback");
    }

    $jwtFile = $root . '/services/MemberJwtVerify.php';
    $line(is_file($jwtFile) ? 'OK' : 'FAIL', 'MemberJwtVerify on frontend (signature check)');

    $mediaFile = $root . '/api/MediaUrl.php';
    $line(is_file($mediaFile) ? 'OK' : 'FAIL', 'api/MediaUrl.php present');

    $bases = BackendApiClient::memberApiOutboundBaseCandidates();
    echo "\nOutbound candidates:\n";
    foreach ($bases as $base) {
        echo "  - {$base}\n";
    }

    $routes = ['site_settings.php', 'auth/login', 'auth/session', 'balance', 'loyalty', 'announcements'];
    echo "\nHTTP probe (invalid login expects 401):\n";
    foreach ($routes as $route) {
        $method = $route === 'auth/login' ? 'POST' : 'GET';
        $body = $method === 'POST'
            ? json_encode(['login' => 'audit-probe', 'password' => 'x'], JSON_UNESCAPED_UNICODE)
            : null;
        $got = false;
        foreach ($bases as $base) {
            $result = BackendApiClient::proxyHttp(
                $method,
                $base,
                $route,
                [],
                $body,
                $body !== null ? 'application/json' : null,
                null,
                12,
                []
            );
            if ($result === null || !empty($result['transport_error'])) {
                continue;
            }
            $status = (int) ($result['status'] ?? 0);
            $label = $status >= 200 && $status < 500 ? 'OK' : 'WARN';
            if ($route === 'auth/login' && $status === 401) {
                $label = 'OK';
            }
            if ($route === 'auth/session' && $status === 401) {
                $label = 'OK';
            }
            if ($route === 'balance' && $status === 401) {
                $label = 'OK';
            }
            if ($status === 502 || $status === 503) {
                $label = 'FAIL';
            }
            $line($label, "{$method} {$route} via {$base} → HTTP {$status}");
            $got = true;
            break;
        }
        if (!$got) {
            $line('FAIL', "{$method} {$route} — all backends unreachable");
        }
    }
} else {
    $tableFile = $root . '/admin/database/migrations/2026_06_05_000001_create_member_jwt_tokens.php';
    $line(is_file($tableFile) ? 'OK' : 'FAIL', 'member_jwt_tokens migration file');

    $htaccess = $root . '/admin/.htaccess';
    if (is_readable($htaccess) && str_contains((string) file_get_contents($htaccess), 'HTTP_AUTHORIZATION')) {
        $line('OK', 'admin/.htaccess Authorization pass-through');
    } else {
        $line('FAIL', 'admin/.htaccess missing Authorization pass-through');
    }

    $kernel = $root . '/admin/api/v2/includes/member_api_kernel.php';
    if (is_readable($kernel) && str_contains((string) file_get_contents($kernel), 'memberFrontendTrustUserId')) {
        $line('OK', 'Frontend trust auth in kernel');
    } else {
        $line('WARN', 'Frontend trust auth not found in kernel');
    }

    if (is_file($root . '/deploy/aapanel/ensure-member-jwt-table.php')) {
        $line('OK', 'ensure-member-jwt-table.php deploy script');
    }

    require_once $root . '/config/deploy_domains.php';
    require_once $root . '/services/BackendApiClient.php';
    $apiBase = trim(frontend_env_string('API_PUBLIC_BASE_URL', ''));
    if ($apiBase === '' && function_exists('deploy_domain')) {
        $apiBase = trim((string) deploy_domain('api_public_base_url'));
    }
    if ($apiBase === '') {
        $apiBase = 'https://api.vegasroyalspin.com/api/v2';
    }

    echo "\nBackend HTTP probe ({$apiBase}):\n";
    $routes = [
        ['POST', 'auth/login', json_encode(['login' => 'audit-probe', 'password' => 'x'], JSON_UNESCAPED_UNICODE)],
        ['GET', 'auth/session', null],
        ['GET', 'balance', null],
    ];
    foreach ($routes as [$method, $route, $body]) {
        $result = BackendApiClient::proxyHttp(
            $method,
            $apiBase,
            $route,
            [],
            $body,
            $body !== null ? 'application/json' : null,
            null,
            15,
            []
        );
        if ($result === null || !empty($result['transport_error'])) {
            $line('FAIL', "{$method} {$route} — transport: " . ($result['error_message'] ?? 'unreachable'));
            continue;
        }
        $status = (int) ($result['status'] ?? 0);
        $label = 'OK';
        if ($status === 502 || $status === 503) {
            $label = 'FAIL';
        } elseif ($route === 'auth/login' && $status !== 401) {
            $label = $status === 200 ? 'WARN' : 'WARN';
        } elseif (in_array($route, ['auth/session', 'balance'], true) && $status !== 401) {
            $label = 'WARN';
        }
        $line($label, "{$method} {$route} → HTTP {$status}");
        if ($route === 'auth/login' && $status === 503) {
            echo "       → member_jwt_tokens veya MEMBER_JWT_SECRET sorunu; php deploy/aapanel/ensure-member-jwt-table.php\n";
        }
    }
}

echo "\n=== Summary: {$ok} OK, {$warn} WARN, {$fail} FAIL ===\n";
exit($fail > 0 ? 1 : 0);
