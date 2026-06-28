<?php

declare(strict_types=1);

/**
 * Split-host kurulum sihirbazları için production .env değerleri.
 */
final class InstallEnvBuilder
{
    public static function ensureDeployDomainsLoaded(string $root): void
    {
        $file = rtrim(str_replace('\\', '/', $root), '/') . '/config/deploy_domains.php';
        if (is_readable($file)) {
            require_once $file;
        }
    }

    public static function resolveApiHost(string $backendHost): string
    {
        $backendHost = strtolower(preg_replace('/:\d+$/', '', trim($backendHost)) ?? '');
        if ($backendHost === '') {
            return 'api.bo-nexthub.site';
        }

        if (function_exists('deploy_domain')) {
            $configured = strtolower((string) (parse_url(deploy_domain('api_public_base_url'), PHP_URL_HOST) ?: deploy_domain('api_subdomain_host')));
            if ($configured !== '') {
                return $configured;
            }
        }

        $base = preg_replace('/^api\./', '', $backendHost);

        return 'api.' . $base;
    }

    public static function resolveApiPublicBaseUrl(string $backendUrl): string
    {
        if (function_exists('deploy_domain')) {
            $configured = trim(deploy_domain('api_public_base_url'));
            if ($configured !== '') {
                return rtrim($configured, '/');
            }
        }

        $backendUrl = rtrim(trim($backendUrl), '/');
        $scheme = (string) (parse_url($backendUrl, PHP_URL_SCHEME) ?: 'https');
        $apiHost = self::resolveApiHost((string) (parse_url($backendUrl, PHP_URL_HOST) ?: 'bo-nexthub.site'));

        return rtrim($scheme . '://' . $apiHost . '/api/v2', '/');
    }

    public static function resolveApiFallbackBaseUrl(string $backendUrl): string
    {
        return rtrim(rtrim(trim($backendUrl), '/') . '/api/v2', '/');
    }

    /**
     * @return array{0: string, 1: string} [public_hosts, allowed_hosts]
     */
    public static function hostLists(string $frontendUrl, string $backendHost): array
    {
        if (function_exists('deploy_allowed_url_hosts') && function_exists('deploy_frontend_host_variants')) {
            $backendUrl = str_contains($backendHost, '://')
                ? rtrim($backendHost, '/')
                : 'https://' . preg_replace('/:\d+$/', '', $backendHost);

            return [
                deploy_frontend_host_variants($frontendUrl),
                deploy_allowed_url_hosts($frontendUrl, $backendUrl),
            ];
        }

        $frontendHost = strtolower((string) (parse_url($frontendUrl, PHP_URL_HOST) ?: ''));
        $backendHost = strtolower(preg_replace('/:\d+$/', '', $backendHost) ?? '');
        $base = $frontendHost;
        if (str_starts_with($base, 'www.')) {
            $base = substr($base, 4);
        }
        if (str_starts_with($base, 'm.')) {
            $base = substr($base, 2);
        }

        $public = array_values(array_filter(array_unique([
            $frontendHost,
            $base !== '' ? $base : '',
            $base !== '' ? 'www.' . $base : '',
            $base !== '' ? 'm.' . $base : '',
        ])));
        $allowed = array_values(array_filter(array_unique(array_merge(
            $public,
            [$backendHost, self::resolveApiHost($backendHost)]
        ))));

        return [implode(',', $public), implode(',', $allowed)];
    }

    /**
     * @param array{
     *   root: string,
     *   backend_host: string,
     *   backend_url: string,
     *   frontend_url: string,
     *   app_key: string,
     *   member_jwt_secret: string,
     *   frontend_cms_purge_secret: string,
     *   db_host: string,
     *   db_port: string,
     *   db_database: string,
     *   db_username: string,
     *   db_password: string,
     *   app_env?: string,
     *   live_support_url?: string
     * } $input
     * @return array<string, string>
     */
    public static function buildBackendEnv(array $input): array
    {
        self::ensureDeployDomainsLoaded($input['root']);

        $backendUrl = rtrim($input['backend_url'], '/');
        $frontendUrl = rtrim($input['frontend_url'], '/');
        $backendHost = strtolower(preg_replace('/:\d+$/', '', $input['backend_host']) ?? '');
        [$publicHosts, $allowedHosts] = self::hostLists($frontendUrl, $backendHost);
        $apiPublic = self::resolveApiPublicBaseUrl($backendUrl);
        $apiFallback = self::resolveApiFallbackBaseUrl($backendUrl);
        $liveSupport = trim((string) ($input['live_support_url'] ?? 'https://direct.lc.chat/19301899/'));
        if ($liveSupport !== '' && !str_ends_with($liveSupport, '/')) {
            $liveSupport .= '/';
        }

        return [
            'APP_ENV' => trim((string) ($input['app_env'] ?? 'production')),
            'APP_DEBUG' => 'false',
            'APP_KEY' => $input['app_key'],
            'CLOUDFLARE_SSL' => '1',
            'ORIGIN_HTTP' => '1',
            'METROPOL_ROOT' => rtrim(str_replace('\\', '/', $input['root']), '/'),
            'ADMIN_URL_PREFIX' => '',
            'BACKEND_HOST' => $backendHost,
            'BACKEND_URL' => $backendUrl,
            'BACKEND_FALLBACK_URL' => $backendUrl,
            'API_PUBLIC_BASE_URL' => $apiPublic,
            'BACKEND_API_BASE_URL' => $apiPublic,
            'API_BACKEND_MAIN_BASE_URL' => $apiPublic,
            'API_BACKEND_FALLBACK_BASE_URL' => $apiFallback,
            'SITE_URL' => $frontendUrl,
            'FRONTEND_URL' => $frontendUrl,
            'FRONTEND_FALLBACK_URL' => $frontendUrl,
            'PUBLIC_URL_HOSTS' => $publicHosts,
            'ALLOWED_URL_HOSTS' => $allowedHosts,
            'DEFAULT_ALLOWED_URL_HOSTS' => $allowedHosts,
            'MEMBER_JWT_SECRET' => $input['member_jwt_secret'],
            'FRONTEND_CMS_PURGE_SECRET' => $input['frontend_cms_purge_secret'],
            'LIVE_SUPPORT_URL' => $liveSupport,
            'DB_HOST' => $input['db_host'],
            'DB_PORT' => $input['db_port'],
            'DB_DATABASE' => $input['db_database'],
            'DB_USERNAME' => $input['db_username'],
            'DB_PASSWORD' => $input['db_password'],
            'ALLOW_RUNTIME_MIGRATIONS' => '0',
            'METROPOL_RUNTIME_PROVIDER_BOOTSTRAP' => '0',
        ];
    }

    /**
     * @param array{
     *   frontend_url: string,
     *   backend_url: string,
     *   app_key: string,
     *   member_jwt_secret: string,
     *   frontend_cms_purge_secret: string,
     *   session_cookie_domain: string,
     *   live_support_url?: string,
     *   telegram_url?: string,
     *   whatsapp_url?: string,
     *   api_backend_internal_base_url?: string,
     *   api_backend_internal_host?: string
     * } $input
     * @return array<string, string>
     */
    public static function buildFrontendEnv(array $input): array
    {
        $frontendUrl = rtrim($input['frontend_url'], '/');
        $backendUrl = rtrim($input['backend_url'], '/');
        $backendHost = strtolower((string) (parse_url($backendUrl, PHP_URL_HOST) ?: ''));
        [$publicHosts, $allowedHosts] = self::hostLists($frontendUrl, $backendHost);
        $apiPublic = self::resolveApiPublicBaseUrl($backendUrl);
        $apiFallback = self::resolveApiFallbackBaseUrl($backendUrl);
        $liveSupport = trim((string) ($input['live_support_url'] ?? 'https://direct.lc.chat/19301899/'));
        if ($liveSupport !== '' && !str_ends_with($liveSupport, '/')) {
            $liveSupport .= '/';
        }

        $env = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_KEY' => $input['app_key'],
            'CLOUDFLARE_SSL' => '1',
            'ORIGIN_HTTP' => '1',
            'FRONTEND_API_ONLY' => '1',
            'FRONTEND_DIRECT_MEMBER_API' => '0',
            'FRONTEND_MEMBER_API_CIRCUIT_SECONDS' => '0',
            'FRONTEND_API_PROXY_TIMEOUT' => '60',
            'SITE_URL' => $frontendUrl,
            'FRONTEND_URL' => $frontendUrl,
            'FRONTEND_FALLBACK_URL' => $frontendUrl,
            'SESSION_COOKIE_DOMAIN' => $input['session_cookie_domain'],
            'BACKEND_HOST' => $backendHost,
            'BACKEND_URL' => $backendUrl,
            'BACKEND_FALLBACK_URL' => $backendUrl,
            'BACKEND_API_BASE_URL' => $apiPublic,
            'API_BACKEND_MAIN_BASE_URL' => $apiPublic,
            'API_BACKEND_FALLBACK_BASE_URL' => $apiFallback,
            'API_PUBLIC_BASE_URL' => $apiPublic,
            'PUBLIC_URL_HOSTS' => $publicHosts,
            'ALLOWED_URL_HOSTS' => $allowedHosts,
            'DEFAULT_ALLOWED_URL_HOSTS' => $allowedHosts,
            'MEMBER_JWT_SECRET' => $input['member_jwt_secret'],
            'FRONTEND_CMS_PURGE_SECRET' => $input['frontend_cms_purge_secret'],
            'LIVE_SUPPORT_URL' => $liveSupport,
            'TELEGRAM_URL' => rtrim(trim((string) ($input['telegram_url'] ?? 'https://t.me')), '/'),
            'WHATSAPP_URL' => trim((string) ($input['whatsapp_url'] ?? '')),
            'ALLOW_RUNTIME_MIGRATIONS' => '0',
            'METROPOL_RUNTIME_PROVIDER_BOOTSTRAP' => '0',
        ];

        return self::finalizeSplitFrontendEnv($env);
    }

    /**
     * Split-deploy frontend: üye API her zaman public api.* subdomain — loopback yasak.
     *
     * @param array<string, string> $env
     * @return array<string, string>
     */
    public static function finalizeSplitFrontendEnv(array $env): array
    {
        $backendUrl = rtrim(trim((string) ($env['BACKEND_URL'] ?? '')), '/');
        if ($backendUrl !== '') {
            $apiPublic = self::resolveApiPublicBaseUrl($backendUrl);
            $apiFallback = self::resolveApiFallbackBaseUrl($backendUrl);
            $env['API_PUBLIC_BASE_URL'] = $apiPublic;
            $env['API_BACKEND_MAIN_BASE_URL'] = $apiPublic;
            $env['BACKEND_API_BASE_URL'] = $apiPublic;
            $env['API_BACKEND_FALLBACK_BASE_URL'] = $apiFallback;
        }

        $env['FRONTEND_API_ONLY'] = '1';
        $env['FRONTEND_DIRECT_MEMBER_API'] = '0';
        $env['FRONTEND_MEMBER_API_CIRCUIT_SECONDS'] = $env['FRONTEND_MEMBER_API_CIRCUIT_SECONDS'] ?? '0';
        $env['FRONTEND_API_PROXY_TIMEOUT'] = $env['FRONTEND_API_PROXY_TIMEOUT'] ?? '60';
        $env['ALLOW_RUNTIME_MIGRATIONS'] = '0';
        $env['METROPOL_RUNTIME_PROVIDER_BOOTSTRAP'] = '0';

        unset($env['API_BACKEND_INTERNAL_BASE_URL'], $env['API_BACKEND_INTERNAL_HOST']);

        return $env;
    }

    /**
     * @param array<string, string> $env
     * @return array<string, string>
     */
    public static function finalizeBackendEnv(array $env): array
    {
        $backendUrl = rtrim(trim((string) ($env['BACKEND_URL'] ?? '')), '/');
        if ($backendUrl !== '') {
            $apiPublic = self::resolveApiPublicBaseUrl($backendUrl);
            $apiFallback = self::resolveApiFallbackBaseUrl($backendUrl);
            $env['API_PUBLIC_BASE_URL'] = $apiPublic;
            $env['API_BACKEND_MAIN_BASE_URL'] = $apiPublic;
            $env['BACKEND_API_BASE_URL'] = $apiPublic;
            $env['API_BACKEND_FALLBACK_BASE_URL'] = $apiFallback;
        }

        $env['ALLOW_RUNTIME_MIGRATIONS'] = $env['ALLOW_RUNTIME_MIGRATIONS'] ?? '0';
        $env['METROPOL_RUNTIME_PROVIDER_BOOTSTRAP'] = $env['METROPOL_RUNTIME_PROVIDER_BOOTSTRAP'] ?? '0';

        return $env;
    }

    /**
     * @param array<string, string> $env
     * @return list<string>
     */
    public static function validateSplitFrontendEnv(array $env): array
    {
        $errors = [];
        foreach ([
            'SITE_URL',
            'FRONTEND_URL',
            'BACKEND_URL',
            'API_PUBLIC_BASE_URL',
            'API_BACKEND_MAIN_BASE_URL',
            'MEMBER_JWT_SECRET',
            'FRONTEND_CMS_PURGE_SECRET',
            'SESSION_COOKIE_DOMAIN',
        ] as $key) {
            if (trim((string) ($env[$key] ?? '')) === '') {
                $errors[] = $key . ' eksik';
            }
        }

        foreach (['MEMBER_JWT_SECRET', 'FRONTEND_CMS_PURGE_SECRET', 'APP_KEY'] as $secretKey) {
            $value = trim((string) ($env[$secretKey] ?? ''));
            if ($value === '' || str_contains(strtolower($value), 'change-me') || strlen($value) < 32) {
                $errors[] = $secretKey . ' geçersiz (min. 32 karakter, CHANGE-ME olmamalı)';
            }
        }

        if ((string) ($env['FRONTEND_API_ONLY'] ?? '') !== '1') {
            $errors[] = 'FRONTEND_API_ONLY=1 olmalı';
        }
        if ((string) ($env['FRONTEND_DIRECT_MEMBER_API'] ?? '') !== '0') {
            $errors[] = 'FRONTEND_DIRECT_MEMBER_API=0 olmalı (tarayıcı proxy kullanır)';
        }

        $apiHost = strtolower((string) (parse_url((string) ($env['API_BACKEND_MAIN_BASE_URL'] ?? ''), PHP_URL_HOST) ?: ''));
        if ($apiHost !== '' && !str_starts_with($apiHost, 'api.')) {
            $errors[] = 'API_BACKEND_MAIN_BASE_URL api.* subdomain olmalı (örn. api.bo-nexthub.site)';
        }

        if (trim((string) ($env['API_BACKEND_INTERNAL_BASE_URL'] ?? '')) !== '') {
            $errors[] = 'Split frontend: API_BACKEND_INTERNAL_BASE_URL tanımlanmamalı';
        }

        return $errors;
    }

    /**
     * @param array<string, string> $env
     * @return list<string>
     */
    public static function validateBackendEnv(array $env): array
    {
        $errors = [];
        foreach ([
            'BACKEND_URL',
            'API_PUBLIC_BASE_URL',
            'API_BACKEND_MAIN_BASE_URL',
            'MEMBER_JWT_SECRET',
            'FRONTEND_CMS_PURGE_SECRET',
            'DB_HOST',
            'DB_DATABASE',
            'DB_USERNAME',
        ] as $key) {
            if (trim((string) ($env[$key] ?? '')) === '') {
                $errors[] = $key . ' eksik';
            }
        }

        foreach (['MEMBER_JWT_SECRET', 'FRONTEND_CMS_PURGE_SECRET', 'APP_KEY'] as $secretKey) {
            $value = trim((string) ($env[$secretKey] ?? ''));
            if ($value === '' || str_contains(strtolower($value), 'change-me') || strlen($value) < 32) {
                $errors[] = $secretKey . ' geçersiz (min. 32 karakter)';
            }
        }

        $apiHost = strtolower((string) (parse_url((string) ($env['API_BACKEND_MAIN_BASE_URL'] ?? ''), PHP_URL_HOST) ?: ''));
        if ($apiHost !== '' && !str_starts_with($apiHost, 'api.')) {
            $errors[] = 'API_BACKEND_MAIN_BASE_URL api.* subdomain olmalı';
        }

        return $errors;
    }
}
