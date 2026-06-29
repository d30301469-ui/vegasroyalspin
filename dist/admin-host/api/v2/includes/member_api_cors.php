<?php
/**
 * Member API CORS — vegasroyalspin.com → api.bo-nexthub.site doğrudan çağrılar için.
 * .env yüklenmeden önce çalışır; ALLOWED_URL_HOSTS + deploy_domains fallback kullanır.
 */
if (!function_exists('member_api_cors_project_root')) {
    function member_api_cors_project_root(): string
    {
        static $root = null;
        if (is_string($root) && $root !== '') {
            return $root;
        }

        $candidates = [];
        foreach ([3, 4, 2] as $depth) {
            $candidate = dirname(__DIR__, $depth);
            if ($candidate !== '' && $candidate !== '.' && $candidate !== DIRECTORY_SEPARATOR) {
                $candidates[] = rtrim(str_replace('\\', '/', $candidate), '/');
            }
        }

        foreach ($candidates as $candidate) {
            if (
                is_readable($candidate . '/.env')
                || is_readable($candidate . '/config/deploy_domains.php')
                || is_readable($candidate . '/api/v2/index.php')
            ) {
                return $root = $candidate;
            }
        }

        return $root = rtrim(str_replace('\\', '/', dirname(__DIR__, 3)), '/');
    }
}

if (!function_exists('member_api_cors_bootstrap_env')) {
    function member_api_cors_bootstrap_env(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $root = member_api_cors_project_root();
        if (is_readable($root . '/config/env.php')) {
            require_once $root . '/config/env.php';
            if (function_exists('frontend_load_dotenv')) {
                frontend_load_dotenv($root);
            }
        } elseif (is_readable($root . '/app/Core/AdminInstallGate.php')) {
            require_once $root . '/app/Core/AdminInstallGate.php';
            AdminInstallGate::loadEnv($root);
        }

        if (!function_exists('deploy_domain') && is_readable($root . '/config/deploy_domains.php')) {
            require_once $root . '/config/deploy_domains.php';
        }
    }
}

if (!function_exists('member_api_env_value')) {
    function member_api_env_value(string $key): string
    {
        $value = getenv($key);
        if ($value !== false && trim((string) $value) !== '') {
            return trim((string) $value);
        }
        if (isset($_ENV[$key]) && trim((string) $_ENV[$key]) !== '') {
            return trim((string) $_ENV[$key]);
        }
        if (isset($_SERVER[$key]) && trim((string) $_SERVER[$key]) !== '') {
            return trim((string) $_SERVER[$key]);
        }
        if (defined($key)) {
            return trim((string) constant($key));
        }

        return '';
    }
}

if (!function_exists('member_api_allowed_origins')) {
    /** @return list<string> */
    function member_api_allowed_origins(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        member_api_cors_bootstrap_env();

        $origins = [];
        foreach (['ALLOWED_URL_HOSTS', 'DEFAULT_ALLOWED_URL_HOSTS', 'PUBLIC_URL_HOSTS'] as $envKey) {
            foreach (array_filter(array_map('trim', explode(',', member_api_env_value($envKey)))) as $host) {
                $host = strtolower($host);
                if ($host === '') {
                    continue;
                }
                $origins[] = 'https://' . $host;
                $origins[] = 'http://' . $host;
            }
        }

        if ($origins === [] && function_exists('deploy_domain')) {
            foreach (array_filter(array_map('trim', explode(',', deploy_domain('default_allowed_url_hosts')))) as $host) {
                $host = strtolower($host);
                if ($host === '') {
                    continue;
                }
                $origins[] = 'https://' . $host;
                $origins[] = 'http://' . $host;
            }
        }

        if (function_exists('deploy_frontend_host_variants')) {
            foreach (array_filter(array_map('trim', explode(',', deploy_frontend_host_variants()))) as $host) {
                $host = strtolower($host);
                if ($host === '') {
                    continue;
                }
                $origins[] = 'https://' . $host;
                $origins[] = 'http://' . $host;
            }
        }

        foreach (['FRONTEND_URL', 'FRONTEND_FALLBACK_URL', 'SITE_URL'] as $key) {
            $url = member_api_env_value($key);
            if ($url !== '' && preg_match('#^https?://#i', $url)) {
                $origins[] = rtrim($url, '/');
            }
        }

        if (function_exists('deploy_domain')) {
            foreach (['frontend_url', 'mobile_url', 'frontend_fallback_url'] as $deployKey) {
                $url = trim(deploy_domain($deployKey));
                if ($url !== '' && preg_match('#^https?://#i', $url)) {
                    $origins[] = rtrim($url, '/');
                }
            }
        }

        // LOCAL_URL_HOSTS: geliştirim ortamına özgü host listesi (ör. vegasroyalspin.test,m.vegasroyalspin.test)
        foreach (array_filter(array_map('trim', explode(',', member_api_env_value('LOCAL_URL_HOSTS')))) as $host) {
            $host = strtolower($host);
            if ($host !== '') {
                $origins[] = 'https://' . $host;
                $origins[] = 'http://' . $host;
            }
        }

        $cache = array_values(array_unique(array_filter($origins)));

        return $cache;
    }
}

if (!function_exists('member_api_apply_cors')) {
    function member_api_apply_cors(): void
    {
        $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        $allowed = member_api_allowed_origins();

        $originAllowed = $origin !== '' && in_array($origin, $allowed, true);

        // Geliştirim modu: APP_ENV production değilse *.test originleri otomatik izin ver (Laragon/Herd)
        // .test TLD prodüksiyonda hiçbir zaman origin olarak gelmez; APP_ENV boşsa da güvenli.
        if (!$originAllowed && $origin !== '') {
            $env = strtolower(member_api_env_value('APP_ENV'));
            if (!in_array($env, ['production', 'prod'], true)) {
                $originHost = strtolower((string) (parse_url($origin, PHP_URL_HOST) ?: ''));
                if ($originHost !== '' && str_ends_with($originHost, '.test')) {
                    $originAllowed = true;
                }
            }
        }

        if ($originAllowed) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-CSRF-Token, X-Requested-With, X-Metropol-Member-Jwt, X-Frontend-Trust, X-Member-Proxy-User-Id');
        header('Access-Control-Max-Age: 86400');

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'OPTIONS') {
            http_response_code($originAllowed ? 204 : 403);
            exit;
        }
    }
}

member_api_apply_cors();
