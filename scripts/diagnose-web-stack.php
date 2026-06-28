<?php

declare(strict_types=1);

/**
 * Web stack diagnostic — why loopback/public probes fail on aaPanel VPS.
 *
 * Usage (on server):
 *   php scripts/diagnose-web-stack.php
 */

$root = dirname(__DIR__);

/**
 * @return list<int>
 */
function dws_env_ports(): array
{
    $raw = trim((string) (getenv('LIVE_PROBE_PORTS') ?: ''));
    $ports = [80, 443, 8080, 8088, 8888, 7828, 8290];
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

function dws_port_open(string $host, int $port, float $timeout = 2.0): bool
{
    $errno = 0;
    $errstr = '';
    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if (is_resource($fp)) {
        fclose($fp);

        return true;
    }

    return false;
}

function dws_primary_lan_ip(): string
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
 * @param list<string> $headers
 * @return array{ok: bool, http: int, error: string, body: string, redirect: string}
 */
function dws_http_get(string $url, array $headers = [], int $timeout = 8, bool $followRedirects = false): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'http' => 0, 'error' => 'curl_missing', 'body' => '', 'redirect' => ''];
    }

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(4, $timeout),
        CURLOPT_FOLLOWLOCATION => $followRedirects,
        CURLOPT_MAXREDIRS => $followRedirects ? 3 : 0,
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
    ];
    if (str_starts_with(strtolower($url), 'https://')) {
        $opts[CURLOPT_SSL_VERIFYPEER] = false;
        $opts[CURLOPT_SSL_VERIFYHOST] = 0;
    }
    if (defined('CURL_IPRESOLVE_V4')) {
        $opts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
    }
    curl_setopt_array($ch, $opts);

    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirect = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    $err = trim((string) curl_error($ch));
    curl_close($ch);

    $bodyStr = is_string($body) ? $body : '';
    $ok = $http >= 200 && $http < 400;

    return [
        'ok' => $ok,
        'http' => $http,
        'error' => $ok ? '' : ($err !== '' ? $err : 'http_' . $http),
        'body' => $bodyStr,
        'redirect' => $redirect,
    ];
}

/**
 * Raw HTTP/1.1 — no curl, no redirect follow.
 *
 * @return array{ok: bool, http: int, error: string, body: string, headers: string}
 */
function dws_raw_http(
    string $bindHost,
    int $port,
    string $path,
    string $vhostHost,
    bool $tls,
    float $timeout = 5.0
): array {
    $path = '/' . ltrim($path, '/');
    $target = ($tls ? 'ssl://' : 'tcp://') . $bindHost . ':' . $port;
    $errno = 0;
    $errstr = '';

    $context = null;
    if ($tls) {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'SNI_enabled' => true,
                'peer_name' => $vhostHost,
            ],
        ]);
    }

    $fp = @stream_socket_client($target, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!is_resource($fp)) {
        return ['ok' => false, 'http' => 0, 'error' => "connect: {$errstr} ({$errno})", 'body' => '', 'headers' => ''];
    }

    stream_set_timeout($fp, (int) ceil($timeout));
    $request = "GET {$path} HTTP/1.1\r\n"
        . "Host: {$vhostHost}\r\n"
        . "Connection: close\r\n"
        . "Accept: application/json\r\n"
        . "\r\n";
    fwrite($fp, $request);

    $raw = '';
    while (!feof($fp)) {
        $chunk = fread($fp, 8192);
        if ($chunk === false || $chunk === '') {
            break;
        }
        $raw .= $chunk;
        if (strlen($raw) > 65536) {
            break;
        }
    }
    fclose($fp);

    if ($raw === '') {
        return ['ok' => false, 'http' => 0, 'error' => 'empty_response', 'body' => '', 'headers' => ''];
    }

    $parts = preg_split("/\r\n\r\n|\n\n/", $raw, 2);
    $headers = is_array($parts) ? (string) ($parts[0] ?? '') : '';
    $body = is_array($parts) ? (string) ($parts[1] ?? '') : '';
    $http = 0;
    if (preg_match('/HTTP\/\d\.\d\s+(\d{3})/', $headers, $m)) {
        $http = (int) $m[1];
    }

    $ok = $http >= 200 && $http < 400 && (str_contains($body, '"ok"') || str_contains($body, '"pong"') || str_contains($body, '"success"'));

    return [
        'ok' => $ok,
        'http' => $http,
        'error' => $ok ? '' : ($http > 0 ? 'http_' . $http : 'bad_response'),
        'body' => $body,
        'headers' => $headers,
    ];
}

/**
 * @return list<string>
 */
function dws_origins_for_port(string $bindHost, int $port): array
{
    $scheme = in_array($port, [443, 8443, 8290], true) ? 'https' : 'http';
    $origin = $scheme . '://' . $bindHost;
    if (!in_array($port, [80, 443], true)) {
        $origin .= ':' . $port;
    }

    return [$origin];
}

function dws_load_env(string $root): void
{
    if (!is_readable($root . '/config/env.php')) {
        return;
    }
    require_once $root . '/config/env.php';
    if (!defined('BASE_PATH')) {
        define('BASE_PATH', $root);
    }
    if (function_exists('frontend_load_dotenv')) {
        frontend_load_dotenv($root);
    }
}

function dws_ping_json_ok(string $body): bool
{
    $decoded = json_decode(trim($body), true);

    return is_array($decoded) && (!empty($decoded['ok']) || !empty($decoded['pong']) || !empty($decoded['success']));
}

dws_load_env($root);

$frontendHost = strtolower(trim((string) (parse_url(getenv('FRONTEND_URL') ?: getenv('SITE_URL') ?: 'https://vegasroyalspin.com', PHP_URL_HOST) ?: 'vegasroyalspin.com')));
$backendHost = strtolower(trim((string) (getenv('API_BACKEND_INTERNAL_HOST') ?: getenv('BACKEND_HOST') ?: parse_url(getenv('BACKEND_URL') ?: 'https://bo-nexthub.site', PHP_URL_HOST) ?: 'bo-nexthub.site')));

$bindHosts = array_values(array_unique(array_filter(['127.0.0.1', dws_primary_lan_ip()])));
$ports = dws_env_ports();
$envFile = $root . '/.env';
$envExample = $root . '/ENV.example';
$hasEnv = is_readable($envFile);

echo "=== Web stack diagnostic ===\n";
echo "Project root: {$root}\n";
echo "Frontend host: {$frontendHost}\n";
echo "Backend host:  {$backendHost}\n\n";

echo "[0] Frontend .env\n";
if ($hasEnv) {
    echo "  OK  .env exists\n";
} else {
    echo "  MISSING  .env — frontend API/proxy/health calismaz\n";
    echo "  Fix:\n";
    echo "    cd {$root}\n";
    echo "    # Tercih: tarayicida https://vegasroyalspin.com/install\n";
    echo "    # veya: php scripts/reset-for-install.php && systemctl restart httpd\n";
    if (is_readable($envExample)) {
        echo "    cp ENV.example .env && php deploy/aapanel/fix-frontend-env.php\n";
    }
    echo "    # MEMBER_JWT_SECRET = bo-nexthub.site ile ayni olmali\n";
}

$installLock = $root . '/storage/install.lock';
$installGateFile = $root . '/app/Core/FrontendInstallGate.php';
echo "\n[0b] Install wizard state\n";
if (is_file($installLock)) {
    echo "  WARN  storage/install.lock exists — /install atlanir (guncelleme modu)\n";
    echo "  Fix: php scripts/reset-for-install.php [--env]\n";
} elseif (is_readable($installGateFile)) {
    require_once $installGateFile;
    FrontendInstallGate::loadEnv($root);
    $installed = FrontendInstallGate::isInstalled($root);
    echo '  ' . ($installed ? 'SKIP' : 'NEED') . '  isInstalled()=' . ($installed ? 'true (wizard gizli)' : 'false (wizard acilmali)') . "\n";
    if ($installed && !$hasEnv) {
        echo "  WARN  lock yok ama isInstalled true — eski .env veya otomatik legacy lock\n";
    }
} else {
    echo "  FAIL  FrontendInstallGate.php missing — zip eksik veya yanlis dizin\n";
}

$indexAtRoot = is_file($root . '/index.php');
$indexInPublic = is_file($root . '/public/index.php');
if ($indexInPublic && !$indexAtRoot) {
    echo "  FAIL  Sadece public/index.php var — DocumentRoot yanlis olabilir\n";
} elseif ($indexInPublic && $indexAtRoot) {
    echo "  OK  index.php site kokunde (DocumentRoot = {$root} olmali, public/ degil)\n";
}

echo "\n[1] Files on disk\n";
foreach ([
    'frontend ping' => $root . '/ping.php',
    'frontend health' => $root . '/health.php',
    'frontend ENV.example' => $envExample,
    'backend ping (same VM?)' => '/www/wwwroot/bo-nexthub.site/ping.php',
] as $label => $path) {
    echo '  ' . (is_string($path) && $path !== '' && is_file($path) ? 'OK' : 'MISSING') . "  {$label} → {$path}\n";
}

echo "\n[2] PHP CLI sanity\n";
if (is_file($root . '/ping.php')) {
    $src = (string) file_get_contents($root . '/ping.php');
    echo '  ' . (str_contains($src, 'pong') || str_contains($src, 'ok') ? 'OK' : 'WARN') . '  ping.php readable (' . strlen($src) . " bytes)\n";
} else {
    echo "  FAIL  ping.php missing\n";
}

echo "\n[3] TCP listen (fsockopen)\n";
$openPorts = [];
foreach ($bindHosts as $bindHost) {
    foreach ($ports as $port) {
        $open = dws_port_open($bindHost, $port, 2.0);
        echo '  ' . ($open ? 'OPEN ' : 'closed') . "  {$bindHost}:{$port}\n";
        if ($open) {
            $openPorts[] = [$bindHost, $port];
        }
    }
}

echo "\n[4] HTTP probe — curl WITHOUT redirect follow (hairpin safe)\n";
$workingOrigins = [];
foreach ($openPorts as [$bindHost, $port]) {
    foreach (dws_origins_for_port($bindHost, $port) as $origin) {
        foreach ([$frontendHost => 'frontend', $backendHost => 'backend'] as $host => $role) {
            $url = rtrim($origin, '/') . '/ping.php';
            $probe = dws_http_get($url, ['Host: ' . $host], 6, false);
            $status = ($probe['ok'] || dws_ping_json_ok($probe['body'])) ? 'OK' : 'FAIL';
            echo "  {$status}  {$role} {$url} Host:{$host} → http={$probe['http']}";
            if ($probe['http'] >= 300 && $probe['http'] < 400) {
                echo ' REDIRECT (public HTTPS hedefi loopback timeout yapabilir)';
            } elseif (!$probe['ok']) {
                echo ' ' . ($probe['error'] !== '' ? $probe['error'] : 'non_json');
            }
            echo "\n";
            if ($probe['ok'] || dws_ping_json_ok($probe['body'])) {
                $workingOrigins[$role] = ['origin' => $origin, 'host' => $host, 'bind' => $bindHost, 'port' => $port];
            }
        }
    }
}

echo "\n[5] Raw socket HTTP (bypasses PHP curl)\n";
foreach ($openPorts as [$bindHost, $port]) {
    $tls = in_array($port, [443, 8443, 8290], true);
    foreach ([$frontendHost => 'frontend', $backendHost => 'backend'] as $host => $role) {
        $raw = dws_raw_http($bindHost, $port, '/ping.php', $host, $tls, 5.0);
        $status = $raw['ok'] ? 'OK' : 'FAIL';
        echo "  {$status}  {$role} " . ($tls ? 'https' : 'http') . "://{$bindHost}:{$port}/ping.php Host:{$host} → http={$raw['http']}";
        if (!$raw['ok'] && $raw['http'] >= 300 && $raw['http'] < 400) {
            if (preg_match('/Location:\s*(.+)/i', $raw['headers'], $m)) {
                echo ' → ' . trim(substr($m[1], 0, 80));
            }
        } elseif (!$raw['ok']) {
            echo ' ' . $raw['error'];
        }
        echo "\n";
        if ($raw['ok']) {
            $scheme = $tls ? 'https' : 'http';
            $origin = $scheme . '://' . $bindHost;
            if (!in_array($port, [80, 443], true)) {
                $origin .= ':' . $port;
            }
            $workingOrigins[$role] = ['origin' => $origin, 'host' => $host, 'bind' => $bindHost, 'port' => $port];
        }
    }
}

echo "\n[6] System curl (if installed)\n";
if (function_exists('shell_exec')) {
    foreach ([$frontendHost, $backendHost] as $host) {
        $cmd = 'curl -sS -m 5 --max-redirs 0 -H ' . escapeshellarg('Host: ' . $host)
            . ' http://127.0.0.1/ping.php 2>&1';
        $out = trim((string) @shell_exec($cmd));
        $ok = dws_ping_json_ok($out) || str_contains($out, '"pong"');
        echo '  ' . ($ok ? 'OK' : 'FAIL') . "  Host:{$host} → " . substr(preg_replace('/\s+/', ' ', $out) ?: '(empty)', 0, 120) . "\n";
    }
} else {
    echo "  shell_exec disabled\n";
}

echo "\n[7] Process listening on :80 / :443\n";
if (function_exists('shell_exec')) {
    $httpdCount = (int) trim((string) @shell_exec('pgrep -c httpd 2>/dev/null || pgrep -c apache2 2>/dev/null || echo 0'));
    if ($httpdCount > 0) {
        $level = $httpdCount > 40 ? 'WARNING' : 'OK';
        echo "  {$level}  httpd/apache worker count: {$httpdCount}\n";
        if ($httpdCount > 40) {
            echo "  >> Too many Apache workers — requests may hang (TCP open, HTTP timeout).\n";
            echo "  >> Fix: aaPanel → Apache → Restart  OR  systemctl restart httpd\n";
            echo "  >> Then: tail -50 /www/wwwlogs/vegasroyalspin.com-error_log\n";
        }
    }

    $ss = trim((string) @shell_exec('ss -tlnp 2>/dev/null | grep -E ":80|:443" | head -5'));
    if ($ss !== '') {
        foreach (explode("\n", $ss) as $line) {
            echo '  ' . trim($line) . "\n";
        }
        if (str_contains($ss, 'nginx') && str_contains($ss, 'apache')) {
            echo "  >> WARNING: both nginx and apache on 80/443 — use Apache-only (stop nginx)\n";
        }
    } else {
        echo "  (ss not available)\n";
    }
}

echo "\n[8] Recommended .env (same VM)\n";
if (!$hasEnv) {
    echo "  FIRST: create .env (see section [0])\n";
}
if (isset($workingOrigins['backend'])) {
    $w = $workingOrigins['backend'];
    $apiBase = rtrim($w['origin'], '/') . '/api/v2';
    echo "  API_BACKEND_INTERNAL_BASE_URL={$apiBase}\n";
    echo "  API_BACKEND_INTERNAL_HOST={$w['host']}\n";
    echo "  LIVE_PROBE_LOOPBACK={$w['origin']}\n";
} else {
    echo "  No working loopback yet.\n";
    echo "  If [6] system curl OK but [4] fails: PHP curl misconfigured — use system curl or fix php-curl.\n";
    echo "  If all fail but port OPEN: PHP-FPM hang — check /www/wwwlogs/*.error_log and aaPanel PHP-FPM restart.\n";
    echo "  If http=301/302: disable forced HTTPS redirect for loopback or use https://127.0.0.1 + LIVE_PROBE_LOOPBACK.\n";
}

echo "\n[9] aaPanel checklist\n";
echo "  - cp ENV.example .env && php deploy/aapanel/fix-frontend-env.php\n";
echo "  - App Store → Apache: Running | Nginx: Stopped\n";
echo "  - Website → both domains Running, PHP 8.1+\n";
echo "  - From PC: curl -sS https://vegasroyalspin.com/ping.php\n";

$exitOk = $hasEnv && isset($workingOrigins['frontend'], $workingOrigins['backend']);

exit($exitOk ? 0 : 1);
