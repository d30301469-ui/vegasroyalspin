<?php

declare(strict_types=1);

/**
 * Production smoke checklist — run on server after deploy.
 *
 * Same-server note: public HTTPS from SSH often times out (hairpin NAT).
 * Default mode uses Apache loopback: http://127.0.0.1 + Host header.
 *
 * Usage:
 *   php scripts/live-probe-checklist.php
 *   php scripts/live-probe-checklist.php --public    # also test public HTTPS (may timeout on same VM)
 *   FRONTEND_URL=https://vegasroyalspin.com BACKEND_URL=https://bo-nexthub.site php scripts/live-probe-checklist.php
 */

$root = dirname(__DIR__);
require_once $root . '/services/BackendConnectivityProbe.php';

$argvList = $argv ?? [];
$includePublic = in_array('--public', $argvList, true);
$publicOnly = in_array('--public-only', $argvList, true);
if ($publicOnly) {
    $includePublic = true;
}

live_probe_load_env($root);

$frontendPublic = rtrim(getenv('FRONTEND_URL') ?: getenv('SITE_URL') ?: 'https://vegasroyalspin.com', '/');
$backendPublic = rtrim(getenv('BACKEND_URL') ?: getenv('BACKEND_FALLBACK_URL') ?: 'https://bo-nexthub.site', '/');
$frontendHost = live_probe_host($frontendPublic) ?: 'vegasroyalspin.com';
$frontendBaseHost = preg_replace('/^(?:www|m)\./', '', $frontendHost) ?: $frontendHost;
$mobileHost = 'm.' . $frontendBaseHost;
$backendHost = trim((string) (getenv('API_BACKEND_INTERNAL_HOST') ?: getenv('BACKEND_HOST') ?: ''));
if ($backendHost === '') {
    $backendHost = live_probe_host($backendPublic) ?: 'bo-nexthub.site';
}

$loopbackOrigin = trim((string) (getenv('LIVE_PROBE_LOOPBACK') ?: getenv('API_BACKEND_INTERNAL_LOOPBACK') ?: ''));
$loopbackOrigin = rtrim($loopbackOrigin, '/');

/**
 * @return list<int>
 */
function live_probe_candidate_ports(): array
{
    $ports = [80, 443, 8080, 8088, 8290];
    $raw = trim((string) (getenv('LIVE_PROBE_PORTS') ?: ''));
    if ($raw !== '') {
        foreach (explode(',', $raw) as $part) {
            $p = (int) trim($part);
            if ($p > 0 && $p <= 65535) {
                $ports[] = $p;
            }
        }
    }

    return array_values(array_unique($ports));
}

function live_probe_port_open(string $host, int $port, float $timeout = 2.0): bool
{
    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if (is_resource($fp)) {
        fclose($fp);

        return true;
    }

    return false;
}

function live_probe_lan_ip(): string
{
    if (function_exists('shell_exec')) {
        $out = trim((string) @shell_exec('hostname -I 2>/dev/null'));
        foreach (preg_split('/\s+/', $out) ?: [] as $ip) {
            $ip = trim($ip);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) && !str_starts_with($ip, '127.')) {
                return $ip;
            }
        }
    }

    return '';
}

/**
 * @return list<string>
 */
function live_probe_origin_candidates(?string $forcedOrigin): array
{
    if (is_string($forcedOrigin) && $forcedOrigin !== '') {
        return [rtrim($forcedOrigin, '/')];
    }

    $bindHosts = array_values(array_unique(array_filter(['127.0.0.1', live_probe_lan_ip()])));
    $origins = [];

    foreach ($bindHosts as $bindHost) {
        foreach (live_probe_candidate_ports() as $port) {
            if (!live_probe_port_open($bindHost, $port, 2.0)) {
                continue;
            }
            $scheme = in_array($port, [443, 8443, 8290], true) ? 'https' : 'http';
            $origin = $scheme . '://' . $bindHost;
            if (!in_array($port, [80, 443], true)) {
                $origin .= ':' . $port;
            }
            $origins[] = $origin;
        }
    }

    if ($origins === []) {
        $origins[] = 'http://127.0.0.1';
        $origins[] = 'https://127.0.0.1';
    }

    return array_values(array_unique($origins));
}

function live_probe_discover_origin(string $vhostHost, array $candidates): ?string
{
    foreach ($candidates as $origin) {
        $probe = live_probe_http(rtrim($origin, '/') . '/ping.php', ['Host: ' . $vhostHost], 6);
        $json = live_probe_json($probe);
        if ($probe['ok'] && is_array($json)) {
            return rtrim($origin, '/');
        }
        if ($probe['http'] >= 300 && $probe['http'] < 400) {
            continue;
        }
        if (is_string($probe['body']) && (str_contains($probe['body'], '"pong"') || str_contains($probe['body'], '"ok"'))) {
            return rtrim($origin, '/');
        }
    }

    return null;
}

function live_probe_load_env(string $root): void
{
    if (is_readable($root . '/config/env.php')) {
        require_once $root . '/config/env.php';
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', $root);
        }
        if (function_exists('frontend_load_dotenv')) {
            frontend_load_dotenv($root);

            return;
        }
    }

    $envFile = $root . '/.env';
    if (!is_readable($envFile)) {
        return;
    }

    foreach (file($envFile, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim((string) $line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if ($key === '' || getenv($key) !== false) {
            continue;
        }
        $value = trim($value, " \t\"'");
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

function live_probe_host(string $url): string
{
    return strtolower(trim((string) (parse_url($url, PHP_URL_HOST) ?: '')));
}

/**
 * @param list<string> $extraHeaders
 * @return array{ok: bool, http: int, error: string, body: string}
 */
function live_probe_http(string $url, array $extraHeaders = [], int $timeout = 12): array
{
    if (class_exists('BackendConnectivityProbe', false)) {
        $raw = BackendConnectivityProbe::curl($url, $extraHeaders, $timeout);
        if (is_array($raw)) {
            return [
                'ok' => !empty($raw['ok']),
                'http' => (int) ($raw['http'] ?? $raw['status'] ?? 0),
                'error' => trim((string) ($raw['error'] ?? $raw['message'] ?? '')),
                'body' => is_string($raw['body'] ?? null)
                    ? $raw['body']
                    : (is_string($raw['response'] ?? null) ? $raw['response'] : ''),
            ];
        }
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'http' => 0, 'error' => 'curl_missing', 'body' => ''];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(6, $timeout),
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $extraHeaders),
    ]);
    if (str_starts_with(strtolower($url), 'https://')) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    if (defined('CURL_IPRESOLVE_V4')) {
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    }

    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = trim((string) curl_error($ch));
    curl_close($ch);

    $bodyStr = is_string($body) ? $body : '';
    $ok = $http >= 200 && $http < 500;

    return [
        'ok' => $ok,
        'http' => $http,
        'error' => $ok ? '' : ($err !== '' ? $err : 'http_' . $http),
        'body' => $bodyStr,
    ];
}

/**
 * @return array<string, mixed>|null
 */
function live_probe_json(array $probe): ?array
{
    $body = trim($probe['body']);
    if ($body === '') {
        return null;
    }

    $decoded = json_decode($body, true);

    return is_array($decoded) ? $decoded : null;
}

/**
 * @param callable(?array): bool $expect
 * @return array{ok: bool, via: string, probe: array{ok: bool, http: int, error: string, body: string}, detail: string}
 */
function live_probe_run(
    string $label,
    string $path,
    string $loopbackHost,
    string $loopbackOrigin,
    ?string $publicBase,
    callable $expect,
    bool $tryPublic
): array {
    $path = '/' . ltrim($path, '/');
    $loopbackUrl = rtrim($loopbackOrigin, '/') . $path;
    $loopbackProbe = live_probe_http($loopbackUrl, ['Host: ' . $loopbackHost], 12);
    $loopbackJson = live_probe_json($loopbackProbe);
    if ($loopbackProbe['ok'] && $expect($loopbackJson)) {
        return [
            'ok' => true,
            'via' => 'loopback',
            'probe' => $loopbackProbe,
            'detail' => "loopback Host:{$loopbackHost}",
        ];
    }

    if ($tryPublic && is_string($publicBase) && $publicBase !== '') {
        $publicProbe = live_probe_http(rtrim($publicBase, '/') . $path, [], 10);
        $publicJson = live_probe_json($publicProbe);
        if ($publicProbe['ok'] && $expect($publicJson)) {
            return [
                'ok' => true,
                'via' => 'public',
                'probe' => $publicProbe,
                'detail' => 'public HTTPS',
            ];
        }

        $detail = 'loopback: ' . live_probe_error_detail($loopbackProbe, $loopbackJson);
        $detail .= ' | public: ' . live_probe_error_detail($publicProbe, $publicJson);

        return [
            'ok' => false,
            'via' => 'failed',
            'probe' => $publicProbe,
            'detail' => $detail,
        ];
    }

    return [
        'ok' => false,
        'via' => 'failed',
        'probe' => $loopbackProbe,
        'detail' => 'loopback: ' . live_probe_error_detail($loopbackProbe, $loopbackJson),
    ];
}

function live_probe_error_detail(array $probe, ?array $json): string
{
    $detail = $probe['error'] !== '' ? $probe['error'] : 'http_' . $probe['http'];
    if ($json === null && trim($probe['body']) !== '') {
        $snippet = preg_replace('/\s+/', ' ', trim($probe['body']));
        if (is_string($snippet) && $snippet !== '') {
            $detail .= ' | body: ' . substr($snippet, 0, 100);
        }
    } elseif ($json === null) {
        $detail .= ' | empty_or_non_json_body';
    }

    return $detail;
}

/**
 * @param callable(?array): bool $expect
 * @return array{ok: bool, via: string, probe: array{ok: bool, http: int, error: string, body: string}, detail: string}
 */
function live_probe_run_public_only(
    string $path,
    ?string $publicBase,
    callable $expect
): array {
    if (!is_string($publicBase) || $publicBase === '') {
        return [
            'ok' => false,
            'via' => 'failed',
            'probe' => ['ok' => false, 'http' => 0, 'error' => 'public_base_missing', 'body' => ''],
            'detail' => 'public base URL missing',
        ];
    }

    $path = '/' . ltrim($path, '/');
    $publicProbe = live_probe_http(rtrim($publicBase, '/') . $path, [], 12);
    $publicJson = live_probe_json($publicProbe);
    if ($publicProbe['ok'] && $expect($publicJson)) {
        return [
            'ok' => true,
            'via' => 'public',
            'probe' => $publicProbe,
            'detail' => 'public HTTPS',
        ];
    }

    return [
        'ok' => false,
        'via' => 'failed',
        'probe' => $publicProbe,
        'detail' => 'public: ' . live_probe_error_detail($publicProbe, $publicJson),
    ];
}

/** @var list<array{label: string, path: string, host: string, public: string|null, expect: callable}> */
$checks = [
    [
        'label' => 'backend-ping',
        'path' => '/ping.php',
        'host' => $backendHost,
        'public' => $backendPublic,
        'expect' => static fn (?array $json): bool => is_array($json),
    ],
    [
        'label' => 'backend-health',
        'path' => '/health.php',
        'host' => $backendHost,
        'public' => $backendPublic,
        'expect' => static fn (?array $json): bool => is_array($json) && in_array($json['role'] ?? '', ['backend', 'admin'], true),
    ],
    [
        'label' => 'backend-site-settings',
        'path' => '/api/v2/site_settings.php',
        'host' => $backendHost,
        'public' => $backendPublic,
        'expect' => static fn (?array $json): bool => is_array($json)
            && BackendConnectivityProbe::isBackendSiteSettingsEnvelope($json),
    ],
    [
        'label' => 'backend-mobile-menu',
        'path' => '/api/v2/content/mobile-menu',
        'host' => $backendHost,
        'public' => $backendPublic,
        'expect' => static fn (?array $json): bool => is_array($json)
            && !empty($json['success'])
            && is_array($json['data']['mobile_menu'] ?? null),
    ],
    [
        'label' => 'frontend-ping',
        'path' => '/ping.php',
        'host' => $frontendHost,
        'public' => $frontendPublic,
        'expect' => static fn (?array $json): bool => is_array($json),
    ],
    [
        'label' => 'frontend-health',
        'path' => '/health.php',
        'host' => $frontendHost,
        'public' => $frontendPublic,
        'expect' => static fn (?array $json): bool => is_array($json) && ($json['role'] ?? '') === 'frontend',
    ],
    [
        'label' => 'mobile-ping',
        'path' => '/ping.php',
        'host' => $mobileHost,
        'public' => 'https://' . $mobileHost,
        'expect' => static fn (?array $json): bool => is_array($json),
    ],
    [
        'label' => 'mobile-health',
        'path' => '/health.php',
        'host' => $mobileHost,
        'public' => 'https://' . $mobileHost,
        'expect' => static fn (?array $json): bool => is_array($json) && ($json['role'] ?? '') === 'frontend',
    ],
    [
        'label' => 'frontend-sliders',
        'path' => '/api/v2/content/sliders?category=home',
        'host' => $frontendHost,
        'public' => $frontendPublic,
        'expect' => static fn (?array $json): bool => is_array($json) && array_key_exists('success', $json),
    ],
    [
        'label' => 'mobile-sliders',
        'path' => '/api/v2/content/sliders?category=home',
        'host' => $mobileHost,
        'public' => 'https://' . $mobileHost,
        'expect' => static fn (?array $json): bool => is_array($json) && array_key_exists('success', $json),
    ],
    [
        'label' => 'frontend-auth-session',
        'path' => '/api/v2/auth/session',
        'host' => $frontendHost,
        'public' => $frontendPublic,
        'expect' => static fn (?array $json): bool => is_array($json) && array_key_exists('success', $json),
    ],
];

$fail = 0;
$loopbackOk = 0;
$originCandidates = live_probe_origin_candidates($loopbackOrigin !== '' ? $loopbackOrigin : null);
$frontendLoopback = live_probe_discover_origin($frontendHost, $originCandidates);
$backendLoopback = live_probe_discover_origin($backendHost, $originCandidates);
$loopbackOrigin = $frontendLoopback ?? $backendLoopback ?? ($originCandidates[0] ?? 'http://127.0.0.1');

echo "=== Live probe checklist ===\n";
echo "Mode: " . ($publicOnly
    ? 'public HTTPS only'
    : ($includePublic ? 'loopback + public HTTPS' : 'loopback (default, same-server safe)')) . "\n";
echo "Origin candidates: " . implode(', ', $originCandidates) . "\n";
echo "Frontend loopback: " . ($frontendLoopback ?? 'NOT FOUND') . "\n";
echo "Backend loopback:  " . ($backendLoopback ?? 'NOT FOUND') . "\n";
echo "Frontend host: {$frontendHost}\n";
echo "Backend host:  {$backendHost}\n\n";

if (!$publicOnly && $frontendLoopback === null && $backendLoopback === null) {
    fwrite(STDERR, "No working loopback origin (Apache/Nginx not responding on local ports).\n\n");
    fwrite(STDERR, "Run full diagnostic:\n  php scripts/diagnose-web-stack.php\n\n");
    fwrite(STDERR, "aaPanel Apache-only:\n");
    fwrite(STDERR, "  1. App Store → Apache: Start\n");
    fwrite(STDERR, "  2. App Store → Nginx: Stop (or disable site reverse proxy)\n");
    fwrite(STDERR, "  3. Website → both domains: Running, PHP 8.1+\n");
    fwrite(STDERR, "  4. ss -tlnp | grep -E ':80|:443|:8080'\n");
    fwrite(STDERR, "  5. From your PC: curl https://vegasroyalspin.com/ping.php\n");
    exit(1);
}

foreach ($checks as $spec) {
    if ($publicOnly) {
        $result = live_probe_run_public_only(
            $spec['path'],
            $spec['public'],
            $spec['expect']
        );
        if (!$result['ok']) {
            $fail++;
            echo "FAIL  {$spec['label']} — {$result['detail']}\n";
            continue;
        }
        echo "OK    {$spec['label']} — http={$result['probe']['http']} via {$result['via']} ({$result['detail']})\n";
        continue;
    }

    $origin = str_contains($spec['label'], 'backend-')
        ? ($backendLoopback ?? $loopbackOrigin)
        : ($frontendLoopback ?? $loopbackOrigin);

    $result = live_probe_run(
        $spec['label'],
        $spec['path'],
        $spec['host'],
        $origin,
        $spec['public'],
        $spec['expect'],
        $includePublic
    );

    if (!$result['ok']) {
        $fail++;
        echo "FAIL  {$spec['label']} — {$result['detail']}\n";
        continue;
    }

    if ($result['via'] === 'loopback') {
        $loopbackOk++;
    }

    echo "OK    {$spec['label']} — http={$result['probe']['http']} via {$result['via']} ({$result['detail']})\n";
}

echo "\n";
if ($fail > 0) {
    fwrite(STDERR, "{$fail} probe(s) failed.\n\n");
    fwrite(STDERR, "Manual loopback checks:\n");
    fwrite(STDERR, "  curl -sS -H \"Host: {$backendHost}\" {$loopbackOrigin}/ping.php\n");
    fwrite(STDERR, "  curl -sS -H \"Host: {$frontendHost}\" {$loopbackOrigin}/ping.php\n");
    fwrite(STDERR, "  curl -sS -H \"Host: {$backendHost}\" {$loopbackOrigin}/api/v2/site_settings.php\n\n");
    fwrite(STDERR, "Frontend .env (same VM):\n");
    fwrite(STDERR, "  API_BACKEND_INTERNAL_BASE_URL={$loopbackOrigin}/api/v2\n");
    fwrite(STDERR, "  API_BACKEND_INTERNAL_HOST={$backendHost}\n");
    fwrite(STDERR, "  php deploy/aapanel/fix-frontend-env.php\n");
    fwrite(STDERR, "  php scripts/diagnose-web-stack.php\n");
    exit(1);
}

echo "All live probes passed ({$loopbackOk} via loopback).\n";
if (!$includePublic) {
    echo "Note: Public HTTPS from SSH often times out on the same server (hairpin). Use --public to test external URLs.\n";
}
exit(0);
