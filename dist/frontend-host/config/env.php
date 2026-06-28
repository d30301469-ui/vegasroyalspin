<?php

declare(strict_types=1);

if (!function_exists('frontend_load_dotenv')) {
    /**
     * Production split-frontend: .env must be loaded before config/app.php (install.lock alone is not enough).
     */
    function frontend_load_dotenv(?string $root = null): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }

        $root = rtrim(str_replace('\\', '/', $root ?? (defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__))), '/');

        $frontendGateFile = $root . '/app/Core/FrontendInstallGate.php';
        if (is_readable($frontendGateFile)) {
            require_once $frontendGateFile;
            $includeExample = (defined('METROPOL_INSTALL_WIZARD') && METROPOL_INSTALL_WIZARD)
                || (defined('METROPOL_ALLOW_ENV_EXAMPLE') && METROPOL_ALLOW_ENV_EXAMPLE);
            FrontendInstallGate::loadEnv($root, $includeExample);
            $loaded = true;

            return;
        }

        $adminGateFile = $root . '/app/Core/AdminInstallGate.php';
        if (is_readable($adminGateFile)) {
            require_once $adminGateFile;
            AdminInstallGate::loadEnv($root);
            $loaded = true;

            return;
        }

        foreach ([$root . '/.env', $root . '/ENV.example'] as $file) {
            if (!is_readable($file)) {
                continue;
            }
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                if ($key === '' || (getenv($key) !== false && trim((string) getenv($key)) !== '')) {
                    continue;
                }
                $value = trim($value);
                if (
                    (str_starts_with($value, '"') && str_ends_with($value, '"'))
                    || (str_starts_with($value, "'") && str_ends_with($value, "'"))
                ) {
                    $value = substr($value, 1, -1);
                }
                if (function_exists('putenv')) {
                    @putenv($key . '=' . $value);
                }
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }

        $loaded = true;
    }
}

if (!function_exists('frontend_app_env')) {
    function frontend_app_env(): string
    {
        $env = getenv('APP_ENV');
        $env = $env === false ? 'development' : trim((string) $env);

        return $env !== '' ? strtolower($env) : 'development';
    }
}

if (!function_exists('frontend_app_is_production')) {
    function frontend_app_is_production(): bool
    {
        return in_array(frontend_app_env(), ['production', 'prod'], true);
    }
}

if (!function_exists('frontend_env_raw')) {
    /**
     * Read env var from getenv, $_ENV, or $_SERVER (aaPanel often disables putenv).
     */
    function frontend_env_raw(string $key): ?string
    {
        if (function_exists('getenv')) {
            $value = getenv($key);
            if ($value !== false && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }
        if (isset($_ENV[$key]) && trim((string) $_ENV[$key]) !== '') {
            return trim((string) $_ENV[$key]);
        }
        if (isset($_SERVER[$key]) && trim((string) $_SERVER[$key]) !== '') {
            return trim((string) $_SERVER[$key]);
        }

        return null;
    }
}

if (!function_exists('frontend_env_string')) {
    function frontend_env_string(string $key, string $default = ''): string
    {
        $value = frontend_env_raw($key);

        return $value !== null ? $value : $default;
    }
}

if (!function_exists('frontend_env_bool')) {
    function frontend_env_bool(string $key, bool $default = false): bool
    {
        $value = frontend_env_raw($key);
        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('frontend_is_api_only')) {
    /**
     * Frontend is deployed separately and talks to the admin/backend host only over HTTP API.
     */
    function frontend_is_api_only(): bool
    {
        if (frontend_env_bool('FRONTEND_API_ONLY')) {
            return true;
        }

        if (!frontend_app_is_production()) {
            return false;
        }

        foreach (['DB_HOST', 'DATABASE_HOST', 'ADMIN_DB_HOST', 'DB_DATABASE', 'DATABASE_NAME', 'ADMIN_DB_DATABASE'] as $key) {
            if (trim(frontend_env_string($key)) !== '') {
                return false;
            }
        }

        return trim(frontend_env_string('BACKEND_API_BASE_URL')) !== ''
            || trim(frontend_env_string('API_BACKEND_MAIN_BASE_URL')) !== '';
    }
}

if (!function_exists('frontend_has_database_credentials')) {
    function frontend_has_database_credentials(): bool
    {
        foreach ([
            'DB_HOST', 'DATABASE_HOST', 'ADMIN_DB_HOST',
            'DB_DATABASE', 'DATABASE_NAME', 'ADMIN_DB_DATABASE',
            'DB_USERNAME', 'DATABASE_USERNAME', 'ADMIN_DB_USERNAME',
            'DB_PASSWORD', 'DATABASE_PASSWORD', 'ADMIN_DB_PASSWORD',
        ] as $key) {
            if (trim(frontend_env_string($key)) !== '') {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('frontend_database_allowed')) {
    /**
     * Direct MySQL/PDO is allowed only on the admin/backend host (or local monorepo dev).
     */
    function frontend_database_allowed(): bool
    {
        if (defined('METROPOL_ADMIN_PANEL') && METROPOL_ADMIN_PANEL) {
            return true;
        }

        if (frontend_env_bool('FRONTEND_API_ONLY')) {
            return false;
        }

        if (frontend_app_is_production()) {
            return false;
        }

        $adminDb = (defined('BASE_PATH') ? rtrim((string) BASE_PATH, '/\\') : '') . '/admin/app/Core/AdminDatabase.php';
        if (!is_file($adminDb)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('frontend_assert_split_frontend_has_no_database_credentials')) {
    function frontend_assert_split_frontend_has_no_database_credentials(): void
    {
        if (!frontend_app_is_production()) {
            return;
        }

        if (defined('METROPOL_ADMIN_PANEL') && METROPOL_ADMIN_PANEL) {
            return;
        }

        if (!frontend_has_database_credentials()) {
            return;
        }

        throw new RuntimeException(
            'Production frontend host must not define DB_* or DATABASE_* credentials. Database access is admin/backend only.'
        );
    }
}

if (!function_exists('frontend_forbid_database_access')) {
    function frontend_forbid_database_access(string $context = 'this host'): void
    {
        if (!frontend_database_allowed()) {
            throw new RuntimeException(
                'Direct database access is disabled on ' . $context . '. Use the backend HTTP API.'
            );
        }
    }
}

if (!function_exists('frontend_env_is_placeholder_secret')) {
    function frontend_env_is_placeholder_secret(string $value): bool
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return true;
        }

        foreach (['change-me', 'changeme', 'example', 'placeholder', 'default'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('frontend_assert_production_secret')) {
    function frontend_assert_production_secret(string $key, int $minLength = 32): void
    {
        if (!frontend_app_is_production()) {
            return;
        }

        $value = frontend_env_string($key);
        if (strlen($value) < $minLength || frontend_env_is_placeholder_secret($value)) {
            throw new RuntimeException(sprintf(
                'Production requires a strong non-placeholder %s value with at least %d characters.',
                $key,
                $minLength
            ));
        }
    }
}

if (!function_exists('frontend_assert_production_url')) {
    function frontend_assert_production_url(string $key, string $value): void
    {
        if (!frontend_app_is_production()) {
            return;
        }

        $host = strtolower((string) (parse_url($value, PHP_URL_HOST) ?: ''));
        if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || str_ends_with($host, '.test')) {
            throw new RuntimeException(sprintf('Production %s must resolve to a public host.', $key));
        }
    }
}

if (!function_exists('frontend_assert_production_disabled_flag')) {
    function frontend_assert_production_disabled_flag(string $key): void
    {
        if (!frontend_app_is_production()) {
            return;
        }

        $value = getenv($key);
        $normalized = $value === false ? '' : strtolower(trim((string) $value));

        if (!in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            throw new RuntimeException(sprintf('Production requires %s=0.', $key));
        }
    }
}

if (!function_exists('frontend_uri_is_backend_only')) {
    /**
     * Provider callbacks, wallet webhooks and admin sync routes must never run on the frontend host.
     */
    function frontend_uri_is_backend_only(string $uri): bool
    {
        $uri = rtrim($uri, '/') ?: '/';

        $prefixes = [
            '/callbacks',
            '/bgaming-wallet',
            '/api/casino-callback',
            '/api-gates',
            '/api/v2/drakon_callback',
            '/api/v2/bgaming',
            '/api/v2/bgaming-wallet',
            '/api/v2/megapayz',
            '/drakon_callback',
            '/drakon_api',
            '/drakon-callback',
            '/admin/api/v2/drakon_callback',
            '/megapayz-callback',
            '/bgaming_callback',
            '/casino-callback',
        ];

        foreach ($prefixes as $prefix) {
            if ($uri === $prefix || str_starts_with($uri, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('frontend_controller_is_backend_only')) {
    /**
     * @param class-string|null $controllerName
     */
    function frontend_controller_is_backend_only(?string $controllerName): bool
    {
        if ($controllerName === null || $controllerName === '') {
            return false;
        }

        return in_array($controllerName, [
            'ApiBgamingWalletController',
            'ApiCasinoCallbackController',
            'ApiCallbackController',
        ], true);
    }
}

if (!function_exists('metropol_is_install_wizard')) {
    function metropol_is_install_wizard(): bool
    {
        if (defined('METROPOL_INSTALL_WIZARD') && METROPOL_INSTALL_WIZARD) {
            return true;
        }

        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $path = '/' . trim($path, '/');
        if ($path === '/install' || str_starts_with($path, '/install/')) {
            return true;
        }

        $script = basename(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? '')));

        return $script === 'install.php';
    }
}

if (!function_exists('metropol_should_run_production_assertions')) {
    function metropol_should_run_production_assertions(): bool
    {
        if (!frontend_app_is_production()) {
            return false;
        }
        if (metropol_is_install_wizard()) {
            return false;
        }
        if (defined('METROPOL_API_V2_BOOTSTRAP') && METROPOL_API_V2_BOOTSTRAP) {
            return false;
        }
        if (defined('METROPOL_SKIP_PRODUCTION_ASSERTIONS') && METROPOL_SKIP_PRODUCTION_ASSERTIONS) {
            return false;
        }

        return true;
    }
}

if (!function_exists('metropol_render_frontend_boot_error')) {
    function metropol_render_frontend_boot_error(Throwable $exception): void
    {
        if (!headers_sent()) {
            http_response_code(503);
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store');
        }

        $message = trim($exception->getMessage());
        $hints = [
            'ping.php ve install-probe.php çalışıyor mu kontrol edin.',
            'Zip site köküne açılmış olmalı: /www/wwwroot/vegasroyalspin.com/index.php',
            'aaPanel: Force HTTPS KAPAT, origin HTTP :80 (Cloudflare SSL).',
            'SSH: cd /www/wwwroot/vegasroyalspin.com && php deploy/aapanel/fix-cloudflare-env.php',
            'MEMBER_JWT_SECRET backend ile aynı ve en az 32 karakter olmalı.',
            'Frontend .env içinde DB_* satırı olmamalı.',
        ];

        echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><title>Site yapılandırma hatası</title>';
        echo '<style>body{font-family:Inter,Segoe UI,sans-serif;background:#0f172a;margin:0;padding:24px;color:#e2e8f0}';
        echo '.card{max-width:720px;margin:0 auto;background:#fff;color:#111827;border-radius:14px;padding:24px}';
        echo 'h1{margin:0 0 12px;font-size:20px;color:#dc2626}pre{background:#f3f4f6;padding:12px;border-radius:8px;white-space:pre-wrap;font-size:13px}';
        echo 'ul{font-size:13px;line-height:1.55} a{color:#6d28d9}</style></head><body><div class="card">';
        echo '<h1>Site şu an açılamıyor (HTTP 500)</h1>';
        echo '<p>Yapılandırma veya PHP-FPM hatası. Aşağıdaki mesajı kontrol edin:</p>';
        echo '<pre>' . htmlspecialchars($message !== '' ? $message : $exception->getFile() . ':' . $exception->getLine(), ENT_QUOTES, 'UTF-8') . '</pre>';
        echo '<ul>';
        foreach ($hints as $hint) {
            echo '<li>' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        echo '</ul><p><a href="/ping.php">ping.php</a> · <a href="/install-probe.php">install-probe.php</a> · <a href="/install-status.php">install-status.php</a> · <a href="/install">/install</a></p>';
        echo '</div></body></html>';
    }
}

if (!function_exists('metropol_register_early_error_handler')) {
    function metropol_register_early_error_handler(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;

        $handlerFile = (defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__)) . '/app/Core/ErrorHandler.php';
        if (is_readable($handlerFile)) {
            require_once $handlerFile;
            if (class_exists(\App\Core\ErrorHandler::class)) {
                \App\Core\ErrorHandler::register();
            }
        }
    }
}

if (!function_exists('frontend_remote_http_timeout')) {
    /**
     * Split frontend: keep page render fast when backend is slow or down.
     */
    function frontend_remote_http_timeout(): int
    {
        $custom = (int) frontend_env_string('FRONTEND_REMOTE_TIMEOUT', '');
        if ($custom > 0) {
            return min(30, $custom);
        }

        if (function_exists('frontend_is_api_only') && frontend_is_api_only()) {
            return 4;
        }

        return 12;
    }
}

if (!function_exists('frontend_cms_http_timeout')) {
    /**
     * SSR CMS reads (sliders, footer, homepage sections, …). Same budget as remote timeout.
     */
    function frontend_cms_http_timeout(): int
    {
        return frontend_remote_http_timeout();
    }
}

if (!function_exists('frontend_cms_cache_ttl')) {
    function frontend_cms_cache_ttl(): int
    {
        $custom = (int) frontend_env_string('FRONTEND_CMS_CACHE_TTL', '');
        if ($custom > 0) {
            return min(3600, $custom);
        }

        if (function_exists('frontend_is_api_only') && frontend_is_api_only()) {
            return 600;
        }

        return 120;
    }
}

if (!function_exists('frontend_cms_cache_stale_max_age')) {
    function frontend_cms_cache_stale_max_age(): int
    {
        $custom = (int) frontend_env_string('FRONTEND_CMS_CACHE_STALE_MAX', '');
        if ($custom > 0) {
            return min(604800, $custom);
        }

        return 86400;
    }
}

if (!function_exists('frontend_api_proxy_timeout')) {
    /**
     * Browser-facing /api/v2/* proxy to backend (curl from frontend host).
     */
    function frontend_api_proxy_timeout(): int
    {
        $custom = (int) frontend_env_string('FRONTEND_API_PROXY_TIMEOUT', '');
        if ($custom > 0) {
            return min(60, $custom);
        }

        if (frontend_is_api_only()) {
            return 30;
        }

        return 30;
    }
}

if (!function_exists('metropol_backend_reachability_cache_path')) {
    function metropol_backend_reachability_cache_path(): string
    {
        $base = defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__);

        return rtrim(str_replace('\\', '/', $base), '/') . '/storage/cache/backend_reachability.json';
    }
}

if (!function_exists('metropol_backend_internal_probe_request')) {
    /**
     * @return array{url: string, headers: list<string>}|null
     */
    function metropol_backend_internal_probe_request(): ?array
    {
        $internal = trim((string) (getenv('API_BACKEND_INTERNAL_BASE_URL') ?: ''));
        if ($internal === '') {
            $host = trim((string) (getenv('BACKEND_HOST') ?: ''));
            if ($host === '') {
                $backendUrl = trim((string) (getenv('BACKEND_URL') ?: getenv('BACKEND_FALLBACK_URL') ?: ''));
                $host = strtolower((string) (parse_url($backendUrl, PHP_URL_HOST) ?: ''));
            }
            if ($host === '' || !is_readable(dirname(__DIR__) . '/services/BackendConnectivityProbe.php')) {
                return null;
            }
            require_once dirname(__DIR__) . '/services/BackendConnectivityProbe.php';
            $detected = BackendConnectivityProbe::detectInternalConfig($host);
            if ($detected === null) {
                return null;
            }
            $internal = $detected['internal_base'];
            $host = $detected['internal_host'];
        } else {
            $host = trim((string) (getenv('API_BACKEND_INTERNAL_HOST') ?: getenv('BACKEND_HOST') ?: ''));
            if ($host === '') {
                $backendUrl = trim((string) (getenv('BACKEND_URL') ?: ''));
                $host = strtolower((string) (parse_url($backendUrl, PHP_URL_HOST) ?: 'bo-nexthub.site'));
            }
        }

        $origin = preg_replace('#/api/v2.*$#', '', rtrim($internal, '/')) ?: rtrim($internal, '/');

        return [
            'url' => rtrim($origin, '/') . '/ping.php',
            'headers' => $host !== '' ? ['Host: ' . $host] : [],
        ];
    }
}

if (!function_exists('metropol_backend_probe_url')) {
    function metropol_backend_probe_url(): string
    {
        foreach (['BACKEND_URL', 'BACKEND_FALLBACK_URL', 'API_BACKEND_MAIN_BASE_URL'] as $key) {
            $value = trim((string) (getenv($key) ?: ''));
            if ($value === '') {
                continue;
            }
            if (str_contains($value, '/api/')) {
                $value = preg_replace('#/api/.*$#', '', $value) ?? $value;
            }

            return rtrim($value, '/') . '/health.php';
        }

        return '';
    }
}

if (!function_exists('metropol_backend_is_reachable')) {
    /**
     * Split frontend: one quick probe per request; cache down-state briefly to avoid 504 storms.
     */
    function metropol_backend_is_reachable(int $timeoutSeconds = 2): bool
    {
        static $state = null;
        if ($state !== null) {
            return $state;
        }

        if (!function_exists('frontend_is_api_only') || !frontend_is_api_only()) {
            $state = true;

            return true;
        }

        $cachePath = metropol_backend_reachability_cache_path();
        if (is_readable($cachePath)) {
            $raw = @file_get_contents($cachePath);
            $payload = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($payload)) {
                $savedAt = (int) ($payload['saved_at'] ?? 0);
                $reachable = !empty($payload['reachable']);
                $age = time() - $savedAt;
                $ttl = $reachable ? 20 : 45;
                if ($savedAt > 0 && $age >= 0 && $age <= $ttl) {
                    $state = $reachable;

                    return $state;
                }
            }
        }

        $internalRequest = metropol_backend_internal_probe_request();
        if (is_array($internalRequest) && ($internalRequest['url'] ?? '') !== '') {
            $state = metropol_http_probe_ok(
                (string) $internalRequest['url'],
                $timeoutSeconds,
                (array) ($internalRequest['headers'] ?? [])
            );
            metropol_write_backend_reachability_cache($state);

            return $state;
        }

        $probeUrl = metropol_backend_probe_url();
        if ($probeUrl === '') {
            $state = false;
            metropol_write_backend_reachability_cache(false);

            return false;
        }

        $timeoutSeconds = max(1, min(5, $timeoutSeconds));
        $state = metropol_http_probe_ok($probeUrl, $timeoutSeconds);
        metropol_write_backend_reachability_cache($state);

        return $state;
    }
}

if (!function_exists('metropol_write_backend_reachability_cache')) {
    function metropol_write_backend_reachability_cache(bool $reachable): void
    {
        $path = metropol_backend_reachability_cache_path();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($path, json_encode([
            'saved_at' => time(),
            'reachable' => $reachable,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}

if (!function_exists('metropol_http_probe_ok')) {
    /**
     * @param list<string> $extraHeaders
     */
    function metropol_http_probe_ok(string $url, int $timeoutSeconds = 2, array $extraHeaders = []): bool
    {
        if (!function_exists('curl_init')) {
            $ctx = stream_context_create([
                'http' => ['timeout' => $timeoutSeconds, 'ignore_errors' => true],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $body = @file_get_contents($url, false, $ctx);

            return is_string($body) && $body !== '';
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => min($timeoutSeconds, 2),
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $extraHeaders),
        ]);
        if (defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($body) || $body === '' || $code <= 0 || $code >= 500) {
            return false;
        }

        $trimmed = ltrim($body);
        if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
            $decoded = json_decode($body, true);

            return is_array($decoded) && (!empty($decoded['ok']) || !empty($decoded['success']) || isset($decoded['checks']));
        }

        return true;
    }
}

if (!function_exists('metropol_cms_api_circuit_cache_path')) {
    function metropol_cms_api_circuit_cache_path(): string
    {
        $base = defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__);

        return rtrim(str_replace('\\', '/', $base), '/') . '/storage/cache/cms_api_circuit.json';
    }
}

if (!function_exists('metropol_cms_api_circuit_seconds')) {
    function metropol_cms_api_circuit_seconds(): int
    {
        $custom = (int) frontend_env_string('FRONTEND_CMS_CIRCUIT_SECONDS', '8');
        if ($custom <= 0) {
            return 0;
        }

        return min(60, $custom);
    }
}

if (!function_exists('metropol_cms_api_circuit_is_open')) {
    /**
     * Short cooldown after a failed backend API attempt (avoids 504 storms without blocking on health.php).
     */
    function metropol_cms_api_circuit_is_open(): bool
    {
        if (!function_exists('frontend_is_api_only') || !frontend_is_api_only()) {
            return false;
        }
        if (frontend_env_string('FRONTEND_FORCE_REMOTE', '') === '1') {
            return false;
        }

        $ttl = metropol_cms_api_circuit_seconds();
        if ($ttl <= 0) {
            return false;
        }

        $path = metropol_cms_api_circuit_cache_path();
        if (!is_readable($path)) {
            return false;
        }
        $payload = json_decode((string) @file_get_contents($path), true);
        if (!is_array($payload)) {
            return false;
        }
        $openedAt = (int) ($payload['opened_at'] ?? 0);
        if ($openedAt <= 0) {
            return false;
        }

        return (time() - $openedAt) < $ttl;
    }
}

if (!function_exists('metropol_cms_api_mark_failure')) {
    function metropol_cms_api_mark_failure(): void
    {
        if (!function_exists('frontend_is_api_only') || !frontend_is_api_only()) {
            return;
        }
        if (metropol_cms_api_circuit_seconds() <= 0) {
            return;
        }

        $path = metropol_cms_api_circuit_cache_path();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($path, json_encode([
            'opened_at' => time(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}

if (!function_exists('metropol_cms_api_mark_success')) {
    function metropol_cms_api_mark_success(): void
    {
        $path = metropol_cms_api_circuit_cache_path();
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/** Shared backend API circuit (CMS SSR + member proxy). */
if (!function_exists('metropol_backend_api_circuit_is_open')) {
    function metropol_backend_api_circuit_is_open(): bool
    {
        return metropol_cms_api_circuit_is_open();
    }
}

if (!function_exists('metropol_backend_api_mark_failure')) {
    function metropol_backend_api_mark_failure(): void
    {
        metropol_cms_api_mark_failure();
    }
}

if (!function_exists('metropol_backend_api_mark_success')) {
    function metropol_backend_api_mark_success(): void
    {
        metropol_cms_api_mark_success();
        metropol_member_api_mark_success();
    }
}

if (!function_exists('metropol_member_api_circuit_cache_path')) {
    function metropol_member_api_circuit_cache_path(): string
    {
        $base = defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__);

        return rtrim(str_replace('\\', '/', $base), '/') . '/storage/cache/member_api_circuit.json';
    }
}

if (!function_exists('metropol_member_api_circuit_seconds')) {
    function metropol_member_api_circuit_seconds(): int
    {
        $custom = (int) frontend_env_string('FRONTEND_MEMBER_API_CIRCUIT_SECONDS', '0');
        if ($custom <= 0) {
            return 0;
        }

        return min(30, $custom);
    }
}

if (!function_exists('metropol_member_api_circuit_is_open')) {
    function metropol_member_api_circuit_is_open(): bool
    {
        if (!function_exists('frontend_is_api_only') || !frontend_is_api_only()) {
            return false;
        }
        if (frontend_env_string('FRONTEND_FORCE_REMOTE', '') === '1') {
            return false;
        }

        $ttl = metropol_member_api_circuit_seconds();
        if ($ttl <= 0) {
            return false;
        }

        $path = metropol_member_api_circuit_cache_path();
        if (!is_readable($path)) {
            return false;
        }
        $payload = json_decode((string) @file_get_contents($path), true);
        if (!is_array($payload)) {
            return false;
        }
        $openedAt = (int) ($payload['opened_at'] ?? 0);
        if ($openedAt <= 0) {
            return false;
        }

        return (time() - $openedAt) < $ttl;
    }
}

if (!function_exists('metropol_member_api_mark_failure')) {
    function metropol_member_api_mark_failure(): void
    {
        if (!function_exists('frontend_is_api_only') || !frontend_is_api_only()) {
            return;
        }
        if (metropol_member_api_circuit_seconds() <= 0) {
            return;
        }

        $path = metropol_member_api_circuit_cache_path();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($path, json_encode([
            'opened_at' => time(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}

if (!function_exists('metropol_member_api_mark_success')) {
    function metropol_member_api_mark_success(): void
    {
        $path = metropol_member_api_circuit_cache_path();
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

if (!function_exists('metropol_notify_frontend_cms_purge')) {
    function metropol_notify_frontend_cms_purge(?string $prefix = null): void
    {
        $service = (defined('BASE_PATH') ? rtrim((string) BASE_PATH, '/\\') : dirname(__DIR__)) . '/services/FrontendCmsCachePurge.php';
        if (!is_readable($service)) {
            $service = dirname(__DIR__) . '/services/FrontendCmsCachePurge.php';
        }
        if (is_readable($service)) {
            require_once $service;
        }
        if (class_exists('FrontendCmsCachePurge', false)) {
            FrontendCmsCachePurge::notify($prefix);
        }
    }
}

if (!function_exists('metropol_should_skip_remote_backend')) {
    /**
     * When true, SSR CMS skips live backend HTTP and uses stale cache / defaults only.
     * Default: API failure circuit (not health.php). Legacy: FRONTEND_SKIP_REMOTE_ON_HEALTH_FAIL=1
     */
    function metropol_should_skip_remote_backend(): bool
    {
        if (!function_exists('frontend_is_api_only') || !frontend_is_api_only()) {
            return false;
        }
        if (frontend_env_string('FRONTEND_FORCE_REMOTE', '') === '1') {
            return false;
        }
        if (trim((string) (getenv('API_BACKEND_INTERNAL_BASE_URL') ?: '')) !== '') {
            return metropol_cms_api_circuit_is_open();
        }

        if (frontend_env_string('FRONTEND_SKIP_REMOTE_ON_HEALTH_FAIL', '') === '1') {
            return !metropol_backend_is_reachable();
        }

        return metropol_cms_api_circuit_is_open();
    }
}

if (!function_exists('metropol_pdo_connect_timeout')) {
    function metropol_pdo_connect_timeout(): int
    {
        if (function_exists('frontend_env_string')) {
            $custom = (int) frontend_env_string('DB_CONNECT_TIMEOUT', '3');
            if ($custom > 0) {
                return min(30, $custom);
            }
        }

        return 3;
    }
}

if (!function_exists('metropol_pdo_options')) {
    /**
     * @return array<int, mixed>
     */
    function metropol_pdo_options(bool $emulatePrepares = false): array
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        if (!$emulatePrepares) {
            $options[PDO::ATTR_EMULATE_PREPARES] = false;
        }
        if (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')) {
            $options[PDO::MYSQL_ATTR_CONNECT_TIMEOUT] = metropol_pdo_connect_timeout();
        }

        return $options;
    }
}

if (!function_exists('frontend_emit_backend_only_response')) {
    function frontend_emit_backend_only_response(): never
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(404);
        }

        echo json_encode([
            'success' => false,
            'code' => 404,
            'error' => 'BACKEND_CALLBACK_ONLY',
            'message' => 'Provider callback endpointleri sadece backend hostunda calisir.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$cloudflareConfig = __DIR__ . '/cloudflare.php';
if (is_readable($cloudflareConfig)) {
    require_once $cloudflareConfig;
}
