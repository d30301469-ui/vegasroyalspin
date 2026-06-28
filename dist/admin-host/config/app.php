<?php

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once __DIR__ . '/env.php';

frontend_load_dotenv(BASE_PATH);

if (!defined('VIEW_PATH')) {
    define('VIEW_PATH', BASE_PATH . '/views');
}
if (!defined('CONTROLLER_PATH')) {
    define('CONTROLLER_PATH', BASE_PATH . '/controllers');
}
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', BASE_PATH . '/config');
}
if (!defined('CORE_PATH')) {
    define('CORE_PATH', BASE_PATH . '/core');
}
if (!defined('SERVICE_PATH')) {
    define('SERVICE_PATH', BASE_PATH . '/services');
}
if (!defined('REPOSITORY_PATH')) {
    define('REPOSITORY_PATH', BASE_PATH . '/repositories');
}
if (!defined('API_PATH')) {
    define('API_PATH', BASE_PATH . '/api');
}

require_once __DIR__ . '/deploy_domains.php';

if (!function_exists('frontend_env_value')) {
    /**
     * @param list<string> $keys
     */
    function frontend_env_value(array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            $value = getenv($key);
            if ($value !== false && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return $default;
    }
}

if (!function_exists('frontend_resolve_site_url')) {
    function frontend_resolve_site_url(): string
    {
        foreach (['SITE_URL', 'APP_URL'] as $key) {
            $value = getenv($key);
            if ($value !== false && trim((string) $value) !== '') {
                return rtrim(trim((string) $value), '/');
            }
        }

        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '') {
            if (function_exists('metropol_public_url_scheme')) {
                $scheme = metropol_public_url_scheme('http');
            } else {
                $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
                $scheme = ($https !== '' && $https !== 'off') ? 'https' : 'http';
            }

            $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
            $scriptDir = $scriptDir === '/' || $scriptDir === '.' ? '' : '/' . trim($scriptDir, '/');

            return $scheme . '://' . $host . $scriptDir;
        }

        return rtrim(frontend_env_value(['FRONTEND_FALLBACK_URL'], deploy_domain('frontend_fallback_url')), '/');
    }
}

if (!function_exists('frontend_is_local_request')) {
    function frontend_is_local_request(): bool
    {
        $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
        if ($host === '') {
            return false;
        }

        return $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1'
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.local');
    }
}

if (!function_exists('frontend_open_config_pdo')) {
    /** Shared PDO factory used by frontend_domain_setting() and frontend_provider_config_row(). */
    function frontend_open_config_pdo(): PDO
    {
        $host     = frontend_env_value(['DATABASE_HOST', 'DB_HOST', 'ADMIN_DB_HOST'], '127.0.0.1');
        $port     = (int) frontend_env_value(['DATABASE_PORT', 'DB_PORT', 'ADMIN_DB_PORT'], '3306');
        $database = frontend_env_value(['DATABASE_NAME', 'DATABASE_DATABASE', 'DB_DATABASE', 'ADMIN_DB_DATABASE'], 'metropol_db');
        $username = frontend_env_value(['DATABASE_USERNAME', 'DB_USERNAME', 'ADMIN_DB_USERNAME'], 'root');
        $password = frontend_env_value(['DATABASE_PASSWORD', 'DB_PASSWORD', 'ADMIN_DB_PASSWORD'], '');
        $charset  = frontend_env_value(['DATABASE_CHARSET', 'DB_CHARSET', 'ADMIN_DB_CHARSET'], 'utf8mb4');
        $options  = function_exists('metropol_pdo_options') ? metropol_pdo_options() : [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        return new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset),
            $username,
            $password,
            $options
        );
    }
}

if (!function_exists('frontend_domain_setting')) {
    function frontend_domain_setting(string $key, string $default = ''): string
    {
        if (!frontend_database_allowed() || frontend_is_local_request()) {
            return $default;
        }

        static $settings = null;
        if ($settings === null) {
            $settings = [];
            try {
                $pdo  = frontend_open_config_pdo();
                $stmt = $pdo->query('SELECT frontend_url, backend_url, backend_api_base_url, allowed_url_hosts FROM site_ayarlar ORDER BY id ASC LIMIT 1');
                $row  = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
                $settings = is_array($row) ? $row : [];
            } catch (Throwable) {
                $settings = [];
            }
        }

        $value = trim((string) ($settings[$key] ?? ''));
        return $value !== '' ? $value : $default;
    }
}

if (!function_exists('frontend_provider_config_row')) {
    /**
     * @return array<string, mixed>
     */
    function frontend_provider_config_row(string $table): array
    {
        if (!frontend_database_allowed()) {
            return [];
        }

        static $rows = [];
        if (isset($rows[$table])) {
            return is_array($rows[$table]) ? $rows[$table] : [];
        }

        try {
            $allowedTables = ['drakon_config', 'bgaming_config', 'megapayz_config'];
            if (!in_array($table, $allowedTables, true)) {
                return $rows[$table] = [];
            }

            $pdo  = frontend_open_config_pdo();
            $stmt = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY id ASC LIMIT 1');
            $row  = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            return $rows[$table] = is_array($row) ? $row : [];
        } catch (Throwable) {
            return $rows[$table] = [];
        }
    }
}

if (!function_exists('frontend_assert_active_provider_secret')) {
    function frontend_assert_active_provider_secret(string $provider, string $envKey, string $table, string $column, int $minLength = 8): void
    {
        if (!frontend_app_is_production()) {
            return;
        }

        $row = frontend_provider_config_row($table);
        if ((int) ($row['is_active'] ?? 0) !== 1) {
            return;
        }

        $value = frontend_env_value([$envKey]);
        if ($value === '') {
            $value = trim((string) ($row[$column] ?? ''));
        }

        if (strlen($value) < $minLength || frontend_env_is_placeholder_secret($value)) {
            throw new RuntimeException(sprintf(
                'Production requires a configured non-placeholder %s secret for active %s.',
                $envKey,
                $provider
            ));
        }
    }
}

if (!defined('SITE_URL')) {
    define('SITE_URL', frontend_resolve_site_url());
}
if (metropol_should_run_production_assertions()) {
    frontend_assert_production_url('SITE_URL', (string) SITE_URL);
}

if (!function_exists('frontend_default_public_url')) {
    function frontend_default_public_url(): string
    {
        $publicFallback = frontend_env_value(['FRONTEND_FALLBACK_URL'], deploy_domain('frontend_fallback_url'));
        $site = defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : rtrim($publicFallback, '/');
        $host = strtolower((string) (parse_url($site, PHP_URL_HOST) ?: ''));
        $backendFallbackHost = strtolower((string) (parse_url(frontend_env_value(['BACKEND_FALLBACK_URL'], deploy_domain('backend_url')), PHP_URL_HOST) ?: ''));
        $adminHosts = function_exists('deploy_backend_hosts')
            ? deploy_backend_hosts()
            : array_filter(array_map(static function (string $value): string {
            $host = parse_url($value, PHP_URL_HOST);
            $host = is_string($host) && $host !== '' ? $host : $value;
            return strtolower(preg_replace('/:\d+$/', '', trim($host)) ?? '');
        }, [
            frontend_env_value(['ADMIN_URL_HOST']),
            frontend_env_value(['BACKEND_HOST']),
            frontend_env_value(['BACKEND_URL']),
        ]));
        if ($host !== '' && $host === $backendFallbackHost) {
            return rtrim($publicFallback, '/');
        }
        if ($host !== '' && in_array($host, array_unique($adminHosts), true)) {
            return rtrim($publicFallback, '/');
        }

        return $site;
    }
}

if (!function_exists('frontend_default_backend_url')) {
    function frontend_default_backend_url(): string
    {
        return rtrim(frontend_env_value(['BACKEND_FALLBACK_URL'], deploy_domain('backend_url')), '/');
    }
}

if (!defined('FRONTEND_URL')) {
    define('FRONTEND_URL', rtrim((string) (getenv('FRONTEND_URL') ?: frontend_domain_setting('frontend_url', frontend_default_public_url())), '/'));
}
if (metropol_should_run_production_assertions()) {
    frontend_assert_production_url('FRONTEND_URL', (string) FRONTEND_URL);
}

if (!defined('BACKEND_URL')) {
    define('BACKEND_URL', rtrim((string) (getenv('BACKEND_URL') ?: getenv('ADMIN_URL') ?: frontend_domain_setting('backend_url', frontend_default_backend_url())), '/'));
}
if (metropol_should_run_production_assertions()) {
    frontend_assert_production_url('BACKEND_URL', (string) BACKEND_URL);
}

if (!defined('BACKEND_HOST')) {
    $backendHost = parse_url((string) BACKEND_URL, PHP_URL_HOST);
    $backendFallbackHost = parse_url(frontend_env_value(['BACKEND_FALLBACK_URL'], 'https://bo-nexthub.site'), PHP_URL_HOST);
    define('BACKEND_HOST', strtolower((string) ($backendHost ?: $backendFallbackHost ?: '')));
}

if (!defined('BACKEND_API_BASE_URL')) {
    define('BACKEND_API_BASE_URL', rtrim((string) (getenv('BACKEND_API_BASE_URL') ?: frontend_domain_setting('backend_api_base_url', BACKEND_URL . '/api/v2')), '/'));
}
if (metropol_should_run_production_assertions()) {
    frontend_assert_production_url('BACKEND_API_BASE_URL', (string) BACKEND_API_BASE_URL);
}

if (!defined('ALLOWED_URL_HOSTS')) {
    define('ALLOWED_URL_HOSTS', (string) (getenv('ALLOWED_URL_HOSTS') ?: frontend_domain_setting('allowed_url_hosts', frontend_env_value(['DEFAULT_ALLOWED_URL_HOSTS'], deploy_domain('default_allowed_url_hosts')))));
}

if (!defined('PUBLIC_URL_HOSTS')) {
    define('PUBLIC_URL_HOSTS', (string) (getenv('PUBLIC_URL_HOSTS') ?: frontend_env_value(['PUBLIC_URL_HOSTS'], deploy_domain('public_url_hosts'))));
}

if (metropol_should_run_production_assertions()) {
    frontend_assert_production_secret('APP_KEY');
    frontend_assert_production_secret('MEMBER_JWT_SECRET');
    frontend_assert_production_disabled_flag('ALLOW_RUNTIME_MIGRATIONS');
    frontend_assert_production_disabled_flag('METROPOL_RUNTIME_PROVIDER_BOOTSTRAP');
    if (!frontend_is_api_only() && !defined('METROPOL_ADMIN_PANEL')) {
        frontend_assert_active_provider_secret('Drakon', 'DRAKON_CALLBACK_SECRET', 'drakon_config', 'callback_secret');
        frontend_assert_active_provider_secret('BGaming', 'BGAMING_WALLET_SECRET', 'bgaming_config', 'wallet_secret');
        frontend_assert_active_provider_secret('MegaPayz', 'MEGAPAYZ_PRIVATE_KEY', 'megapayz_config', 'private_key');
    }
    frontend_assert_split_frontend_has_no_database_credentials();
}

if (!defined('LIVE_SUPPORT_URL')) {
    define('LIVE_SUPPORT_URL', rtrim(frontend_env_value(['LIVE_SUPPORT_URL'], 'https://direct.lc.chat/19301899/'), '/') . '/');
}

if (!defined('TELEGRAM_URL')) {
    define('TELEGRAM_URL', rtrim(frontend_env_value(['TELEGRAM_URL'], 'https://t.me'), '/'));
}

if (!defined('OKKO_SPORTS_LAUNCH_URL')) {
    define('OKKO_SPORTS_LAUNCH_URL', frontend_env_value(['OKKO_SPORTS_LAUNCH_URL'], 'https://my.okkogaming.com/spor-launch'));
}

if (!defined('MEGAPAYZ_LOGO_BASE_URL')) {
    define('MEGAPAYZ_LOGO_BASE_URL', rtrim(frontend_env_value(['MEGAPAYZ_LOGO_BASE_URL'], 'https://docs.megapayz.com/images'), '/'));
}
