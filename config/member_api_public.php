<?php

declare(strict_types=1);

/**
 * Public member API base URL (api.bo-nexthub.site) — browser + server-side CMS.
 */
if (!function_exists('metropol_normalize_member_api_public_url')) {
    /**
     * Tarayıcı üye API'si her zaman api.* subdomain üzerinden (bo-nexthub.site değil).
     */
    function metropol_normalize_member_api_public_url(string $url): string
    {
        $url = rtrim(trim($url), '/');
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        if ($host === '' || str_starts_with($host, 'api.')) {
            return $url;
        }

        $apiHost = '';
        $backendHost = '';
        if (function_exists('deploy_domain')) {
            $apiHost = strtolower((string) (parse_url(deploy_domain('api_public_base_url'), PHP_URL_HOST) ?: deploy_domain('api_subdomain_host')));
            $backendHost = strtolower((string) (parse_url(deploy_domain('backend_url'), PHP_URL_HOST) ?: ''));
        }

        $rewrite = false;
        if ($backendHost !== '' && $host === $backendHost && $apiHost !== '' && $host !== $apiHost) {
            $rewrite = true;
        } elseif ($apiHost !== '' && $host !== $apiHost) {
            $builderPath = dirname(__DIR__) . '/app/Services/InstallEnvBuilder.php';
            if (is_readable($builderPath)) {
                require_once $builderPath;
                if (class_exists('InstallEnvBuilder', false)) {
                    $expected = strtolower(InstallEnvBuilder::resolveApiHost($host));
                    if ($expected !== '' && $expected !== $host && str_starts_with($expected, 'api.')) {
                        $apiHost = $expected;
                        $rewrite = true;
                    }
                }
            }
        }

        if (!$rewrite || $apiHost === '') {
            return $url;
        }

        $scheme = (string) (parse_url($url, PHP_URL_SCHEME) ?: 'https');
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/api/v2');
        if (!str_contains($path, '/api/v2')) {
            $path = '/api/v2';
        }

        return rtrim($scheme . '://' . $apiHost . $path, '/');
    }
}

if (!function_exists('metropol_csp_connect_src_directive')) {
    function metropol_csp_connect_src_directive(): string
    {
        $sources = [
            "'self'",
            'wss://*.sptpub.com',
            'https://*.sptpub.com',
            'https://cdnjs.cloudflare.com',
            'https://cdn.jsdelivr.net',
            'https://*.google-analytics.com',
            'https://analytics.google.com',
            'https://*.analytics.google.com',
            'https://www.google.com',
            'https://*.googletagmanager.com',
            'https://stats.g.doubleclick.net',
            'https://*.livechatinc.com',
            'wss://*.livechatinc.com',
            'https://*.livechat.com',
            'wss://*.livechat.com',
            'https://*.livechat-static.com',
            'https://static.cloudflareinsights.com',
            'https://iceexchange.sptpub.com',
            'https://challenges.cloudflare.com',
        ];

        $urlCandidates = [];
        if (function_exists('frontend_env_string') && function_exists('metropol_member_api_public_base')) {
            $urlCandidates[] = metropol_member_api_public_base();
        }
        foreach (['API_BACKEND_MAIN_BASE_URL', 'API_BACKEND_FALLBACK_BASE_URL', 'API_PUBLIC_BASE_URL'] as $const) {
            if (defined($const)) {
                $urlCandidates[] = (string) constant($const);
            }
        }
        if (function_exists('deploy_domain')) {
            $urlCandidates[] = deploy_domain('api_public_base_url');
            $urlCandidates[] = deploy_domain('backend_url');
            $urlCandidates[] = deploy_domain('frontend_url');
            $urlCandidates[] = deploy_domain('mobile_url');
        }
        if (function_exists('deploy_backend_hosts')) {
            foreach (deploy_backend_hosts() as $host) {
                $sources[] = 'https://' . $host;
                $sources[] = 'wss://' . $host;
            }
        }
        foreach ($urlCandidates as $candidate) {
            $parsedCandidate = parse_url((string) $candidate);
            $host = strtolower((string) ($parsedCandidate['host'] ?? ''));
            if ($host !== '') {
                $scheme = strtolower((string) ($parsedCandidate['scheme'] ?? 'https'));
                if ($scheme === 'http') {
                    $sources[] = 'http://' . $host;
                    $sources[] = 'ws://' . $host;
                }
                $sources[] = 'https://' . $host;
                $sources[] = 'wss://' . $host;
            }
        }

        return 'connect-src ' . implode(' ', array_values(array_unique($sources)));
    }
}

if (!function_exists('metropol_member_api_public_base')) {
    function metropol_member_api_public_base(): string
    {
        static $base = null;
        if (is_string($base)) {
            return $base;
        }

        $candidates = [
            frontend_env_string('API_PUBLIC_BASE_URL', ''),
            frontend_env_string('API_BACKEND_MAIN_BASE_URL', ''),
            frontend_env_string('BACKEND_API_BASE_URL', ''),
        ];

        foreach ($candidates as $candidate) {
            $candidate = rtrim(trim($candidate), '/');
            if ($candidate !== '' && preg_match('#^https?://#i', $candidate)) {
                $base = metropol_normalize_member_api_public_url($candidate);

                return $base;
            }
        }

        if (function_exists('deploy_domain')) {
            $base = metropol_normalize_member_api_public_url(
                rtrim(deploy_domain('api_public_base_url', deploy_domain('backend_api_base_url')), '/')
            );

            return $base;
        }

        $base = '';

        return $base;
    }
}

if (!function_exists('metropol_frontend_trust_secret')) {
    function metropol_frontend_trust_secret(): string
    {
        return frontend_env_string('FRONTEND_CMS_PURGE_SECRET', '');
    }
}

if (!function_exists('metropol_frontend_direct_member_api')) {
    function metropol_frontend_direct_member_api(): bool
    {
        if (!function_exists('frontend_is_api_only') || !frontend_is_api_only()) {
            return false;
        }

        $flag = frontend_env_string('FRONTEND_DIRECT_MEMBER_API', '1');

        return !in_array(strtolower($flag), ['0', 'false', 'off', 'no'], true);
    }
}

if (!function_exists('metropol_frontend_member_logged_in')) {
    /**
     * Split-deploy frontend: oturum yalnızca geçerli üye JWT ile sayılır.
     */
    function metropol_frontend_member_logged_in(): bool
    {
        $loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
        if (!$loggedIn) {
            return false;
        }
        if (function_exists('frontend_is_api_only') && frontend_is_api_only()) {
            if (!empty($_SESSION['member_jwt'])) {
                return true;
            }

            return (int) ($_SESSION['user_id'] ?? 0) > 0;
        }

        return true;
    }
}

if (!function_exists('metropol_frontend_clear_member_session')) {
    function metropol_frontend_clear_member_session(): void
    {
        foreach ([
            'loggedin',
            'user_id',
            'username',
            'email',
            'ana_bakiye',
            'first_name',
            'surname',
            'member_jwt',
            '__header_member_cache',
            '__member_jwt_proxy_synced',
        ] as $key) {
            unset($_SESSION[$key]);
        }
    }
}

if (!function_exists('metropol_frontend_sanitize_member_session')) {
    /**
     * API-only frontend: loggedin bayrağı var ama JWT yoksa oturumu temizle (401 döngüsünü keser).
     */
    function metropol_frontend_sanitize_member_session(): void
    {
        if (!function_exists('frontend_is_api_only') || !frontend_is_api_only()) {
            return;
        }
        if (empty($_SESSION['loggedin'])) {
            return;
        }
        if (!empty($_SESSION['member_jwt'])) {
            return;
        }
        if ((int) ($_SESSION['user_id'] ?? 0) > 0) {
            return;
        }
        foreach (['loggedin', 'user_id', 'username', 'email', 'ana_bakiye', 'first_name', 'surname', '__header_member_cache'] as $key) {
            unset($_SESSION[$key]);
        }
    }
}

if (!function_exists('metropol_member_api_layout_vars')) {
    /** @return array<string, mixed> */
    function metropol_member_api_layout_vars(): array
    {
        if (!defined('API_BACKEND_MAIN_BASE_URL') && is_readable(__DIR__ . '/bootstrap_api.php')) {
            require_once __DIR__ . '/bootstrap_api.php';
        }

        $base = function_exists('metropol_member_api_public_base')
            ? metropol_member_api_public_base()
            : metropol_normalize_member_api_public_url(
                rtrim((string) (defined('API_BACKEND_MAIN_BASE_URL') ? API_BACKEND_MAIN_BASE_URL : ''), '/')
            );

        $direct = function_exists('metropol_frontend_direct_member_api') && metropol_frontend_direct_member_api();

        return [
            '__MEMBER_API_BASE__' => $base,
            '__FRONTEND_DIRECT_MEMBER_API__' => $direct,
            '__SITE_SETTINGS_API__' => $direct && $base !== ''
                ? $base . '/site-settings'
                : '/api/v2/site-settings',
        ];
    }
}
