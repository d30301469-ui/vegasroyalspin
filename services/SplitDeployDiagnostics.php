<?php

declare(strict_types=1);

/**
 * Shared frontend split-deploy diagnostics (health.php + diagnose.php).
 */
final class SplitDeployDiagnostics
{
    /**
     * @return array<string, mixed>
     */
    public static function runFrontend(string $root, bool $includeApiProbes = true): array
    {
        $started = microtime(true);
        $result = [
            'ok' => true,
            'role' => 'frontend',
            'php' => PHP_VERSION,
            'time' => gmdate('c'),
            'checks' => [],
            'hints' => [],
        ];

        $hasEnv = is_readable($root . '/.env');
        $result['checks']['env_file'] = $hasEnv ? 'ok' : 'missing';
        $result['checks']['install_lock'] = is_file($root . '/storage/install.lock') ? 'ok' : 'missing';

        if (!$hasEnv) {
            $result['ok'] = false;
            $result['hints'][] = 'https://vegasroyalspin.com/install — veya: cp ENV.example .env && php deploy/aapanel/fix-cloudflare-env.php';
        }

        if ($hasEnv) {
            $parsed = self::parseEnvFile($root . '/.env');
            $required = [
                'FRONTEND_API_ONLY', 'SITE_URL', 'BACKEND_URL', 'API_BACKEND_MAIN_BASE_URL',
                'APP_KEY', 'MEMBER_JWT_SECRET',
            ];
            $missing = [];
            foreach ($required as $key) {
                if (!isset($parsed[$key]) || trim((string) $parsed[$key]) === '') {
                    $missing[] = $key;
                }
            }
            $result['checks']['env_keys_in_file'] = $missing === [] ? 'ok' : 'missing:' . implode(',', $missing);
            if ($missing !== []) {
                $result['ok'] = false;
                $result['hints'][] = '.env dosyasinda eksik anahtarlar: ' . implode(', ', $missing);
                $result['hints'][] = 'SSH: php deploy/aapanel/fix-frontend-env.php && MEMBER_JWT_SECRET backend .env ile ayni olmali';
                $result['hints'][] = 'Veya: storage/install.lock silin, /install acin';
            }
            if (
                isset($parsed['MEMBER_JWT_SECRET'])
                && str_contains(strtolower((string) $parsed['MEMBER_JWT_SECRET']), 'change-me')
            ) {
                $result['hints'][] = 'MEMBER_JWT_SECRET hala CHANGE-ME — bo-nexthub.site .env degerini kopyalayin';
            }
        }

        if (is_readable($root . '/config/env.php')) {
            require_once $root . '/config/env.php';
            if (!defined('BASE_PATH')) {
                define('BASE_PATH', $root);
            }
            frontend_load_dotenv($root);
        }

        $result['checks']['frontend_api_only'] = function_exists('frontend_is_api_only') && frontend_is_api_only() ? 'yes' : 'no';
        if ($result['checks']['frontend_api_only'] !== 'yes') {
            $result['ok'] = false;
            $result['hints'][] = 'FRONTEND_API_ONLY=1 — /install çalıştırın';
        }

        $siteUrl = function_exists('frontend_env_string')
            ? trim(frontend_env_string('SITE_URL', frontend_env_string('FRONTEND_URL')))
            : trim((string) (getenv('SITE_URL') ?: getenv('FRONTEND_URL') ?: ''));
        $sitePath = $siteUrl !== '' ? rtrim((string) (parse_url($siteUrl, PHP_URL_PATH) ?: ''), '/') : '';
        $result['checks']['site_url'] = $siteUrl !== '' ? $siteUrl : 'unset';
        $result['checks']['site_url_path'] = ($sitePath !== '' && $sitePath !== '/') ? 'invalid:' . $sitePath : 'ok';
        if ($result['checks']['site_url_path'] !== 'ok') {
            $result['ok'] = false;
            $result['hints'][] = 'SITE_URL sadece domain olmali (path yok)';
        }

        $backendUrl = function_exists('frontend_env_string')
            ? trim(frontend_env_string('BACKEND_URL', frontend_env_string('BACKEND_FALLBACK_URL')))
            : trim((string) (getenv('BACKEND_URL') ?: getenv('BACKEND_FALLBACK_URL') ?: ''));
        $result['checks']['backend_url'] = $backendUrl !== '' ? $backendUrl : 'unset';

        $jwt = function_exists('frontend_env_string')
            ? trim(frontend_env_string('MEMBER_JWT_SECRET'))
            : trim((string) (getenv('MEMBER_JWT_SECRET') ?: ''));
        $result['checks']['session_cookie_domain'] = function_exists('frontend_env_string')
            ? trim(frontend_env_string('SESSION_COOKIE_DOMAIN', ''))
            : trim((string) (getenv('SESSION_COOKIE_DOMAIN') ?: ''));
        if ($result['checks']['session_cookie_domain'] === '') {
            $result['hints'][] = 'SESSION_COOKIE_DOMAIN=.vegasroyalspin.com — login/API oturumu www ve m. alt alanlarında paylaşılır';
        }

        if (is_readable($root . '/config/member_api_public.php')) {
            require_once $root . '/config/member_api_public.php';
        }
        if (function_exists('metropol_frontend_direct_member_api')) {
            $result['checks']['frontend_direct_member_api'] = metropol_frontend_direct_member_api() ? 'yes' : 'no';
        }

        $result['checks']['member_jwt'] = strlen($jwt) >= 32 && !str_contains(strtolower($jwt), 'change-me') ? 'ok' : 'invalid';
        if ($result['checks']['member_jwt'] !== 'ok') {
            $result['ok'] = false;
            $result['hints'][] = 'MEMBER_JWT_SECRET backend ile ayni olmali (32+ karakter)';
        }

        $backendBase = function_exists('frontend_env_string')
            ? trim(frontend_env_string('API_BACKEND_MAIN_BASE_URL', frontend_env_string('BACKEND_API_BASE_URL')))
            : trim((string) (getenv('API_BACKEND_MAIN_BASE_URL') ?: getenv('BACKEND_API_BASE_URL') ?: ''));
        $result['checks']['api_backend_env'] = $backendBase !== '' ? $backendBase : 'unset';

        if (is_readable($root . '/config/bootstrap_api.php')) {
            require_once $root . '/config/bootstrap_api.php';
            require_once $root . '/services/BackendApiClient.php';
            $resolved = defined('API_BACKEND_MAIN_BASE_URL') ? (string) API_BACKEND_MAIN_BASE_URL : '';
            $result['checks']['api_backend_resolved'] = $resolved !== '' ? $resolved : 'empty';
            $backendBase = $resolved !== '' ? $resolved : $backendBase;
            $result['checks']['api_backend_outbound'] = BackendApiClient::effectiveOutboundMainBaseUrl();
            $internalEnv = function_exists('frontend_env_string')
                ? trim(frontend_env_string('API_BACKEND_INTERNAL_BASE_URL'))
                : trim((string) (getenv('API_BACKEND_INTERNAL_BASE_URL') ?: ''));
            $result['checks']['api_backend_internal'] = $internalEnv !== '' ? $internalEnv : 'unset';
        }

        if ($includeApiProbes && $backendBase !== '' && is_readable($root . '/services/BackendConnectivityProbe.php')) {
            require_once $root . '/services/BackendConnectivityProbe.php';
            $backendHost = strtolower((string) (parse_url($backendUrl !== '' ? $backendUrl : $backendBase, PHP_URL_HOST) ?: 'bo-nexthub.site'));
            $connectivity = BackendConnectivityProbe::run($backendBase, $backendHost);
            $result['checks']['backend_dns'] = $connectivity['backend_dns'] ?? '';
            $result['checks']['backend_probes'] = $connectivity['probes'] ?? [];
            if (!empty($connectivity['suggested_internal_base'])) {
                $result['checks']['suggested_internal_base'] = $connectivity['suggested_internal_base'];
            }

            $usable = !empty($connectivity['ok']);
            $result['checks']['backend_reachable'] = $usable
                ? 'ok'
                : 'fail:' . (string) ($connectivity['probes']['public_https']['error'] ?? 'unreachable');

            if (!$usable) {
                $result['ok'] = false;
            }
            foreach ($connectivity['hints'] ?? [] as $hint) {
                $result['hints'][] = $hint;
            }

            if ($usable) {
                $outbound = (string) ($result['checks']['api_backend_outbound'] ?? $backendBase);
                $headers = [];
                if (
                    str_starts_with($outbound, 'http://127.0.0.1')
                    && class_exists('BackendApiClient', false)
                ) {
                    $headers = BackendApiClient::applyOutboundHostHeader([]);
                }
                foreach ([
                    'winners' => '/winners?limit=1',
                    'announcements' => '/announcements?action=all',
                    'site_settings' => '/site_settings.php',
                    'cms_sliders' => '/content/sliders?category=home',
                    'cms_footer' => '/content/footer',
                ] as $label => $path) {
                    $probe = BackendConnectivityProbe::curl(rtrim($outbound, '/') . $path, $headers, 4);
                    $key = 'backend_' . $label . '_probe';
                    $result['checks'][$key] = $probe['ok'] ? 'ok:http_' . $probe['http'] : 'fail:' . $probe['error'];
                    if (!$probe['ok']) {
                        $result['ok'] = false;
                    }
                }

                $cmsCacheDir = $root . '/storage/cache/cms';
                $result['checks']['cms_cache_dir'] = is_dir($cmsCacheDir) && is_writable($cmsCacheDir)
                    ? 'writable'
                    : (is_dir($cmsCacheDir) ? 'not_writable' : 'missing');
                if ($result['checks']['cms_cache_dir'] !== 'writable') {
                    @mkdir($cmsCacheDir, 0755, true);
                    $result['checks']['cms_cache_dir'] = is_writable($cmsCacheDir) ? 'writable' : 'not_writable';
                }

                $mediaOrigin = '';
                if (defined('API_BACKEND_MAIN_BASE_URL')) {
                    $parts = parse_url((string) API_BACKEND_MAIN_BASE_URL);
                    $scheme = (string) ($parts['scheme'] ?? 'https');
                    $host = (string) ($parts['host'] ?? '');
                    if ($host !== '') {
                        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
                        $mediaOrigin = $scheme . '://' . $host . $port;
                    }
                }
                $result['checks']['backend_media_origin'] = $mediaOrigin !== '' ? $mediaOrigin : 'unset';

                if (function_exists('metropol_should_skip_remote_backend')) {
                    $result['checks']['cms_skip_remote_backend'] = metropol_should_skip_remote_backend() ? 'yes' : 'no';
                }
                if (function_exists('metropol_cms_api_circuit_is_open')) {
                    $result['checks']['cms_api_circuit_open'] = metropol_cms_api_circuit_is_open() ? 'yes' : 'no';
                }
            }
        }

        if (is_readable($root . '/api/CmsRemote.php')) {
            require_once $root . '/api/CmsRemote.php';
            $log = ApiCmsRemote::fetchLog();
            if ($log !== []) {
                $result['checks']['cms_fetch_sources'] = $log;
                if (ApiCmsRemote::usingFallback()) {
                    $result['hints'][] = 'CMS fallback kullanildi (default/stale/skipped) — backend CMS probe ve .env loopback kontrol edin';
                }
            }
        }

        $result['elapsed_ms'] = (int) round((microtime(true) - $started) * 1000);

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private static function parseEnvFile(string $path): array
    {
        if (!is_readable($path)) {
            return [];
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return [];
        }
        $out = [];
        foreach ($lines as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $trimmed, 2);
            $key = trim($key);
            $value = trim($value, " \t\"'");
            if ($key !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
