<?php

declare(strict_types=1);

/**
 * Global oturum standardı — üye (FRONTSESSID) ve admin (ADMINSESSID) ayrımı.
 *
 * Kurallar:
 * - Cookie adları asla kesişmez.
 * - Cookie domain / Secure / SameSite tek kaynaktan gelir.
 * - Üye logout admin anahtarlarını silmez.
 */

if (!function_exists('app_session_is_https')) {
    function app_session_is_https(): bool
    {
        if (function_exists('request_is_https')) {
            return request_is_https();
        }

        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}

if (!function_exists('app_session_cookie_domain')) {
    function app_session_cookie_domain(): string
    {
        if (function_exists('admin_session_cookie_domain')) {
            $adminDomain = admin_session_cookie_domain();
            if ($adminDomain !== '') {
                return $adminDomain;
            }
        }

        $configured = trim((string) (getenv('SESSION_COOKIE_DOMAIN') ?: ''));
        if ($configured === '' && function_exists('deploy_session_cookie_domain_for_host')) {
            $configured = deploy_session_cookie_domain_for_host((string) ($_SERVER['HTTP_HOST'] ?? ''));
        }

        $domain = ltrim(strtolower($configured), '.');
        $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
        if ($domain === '' || $host === '') {
            return '';
        }

        return ($host === $domain || str_ends_with($host, '.' . $domain)) ? $configured : '';
    }
}

if (!function_exists('app_admin_session_name')) {
    function app_admin_session_name(): string
    {
        $name = trim((string) (getenv('ADMIN_SESSION_NAME') ?: 'ADMINSESSID'));

        return $name !== '' ? $name : 'ADMINSESSID';
    }
}

if (!function_exists('app_frontend_session_name')) {
    function app_frontend_session_name(): string
    {
        $name = trim((string) (getenv('FRONTEND_SESSION_NAME') ?: 'FRONTSESSID'));
        if ($name === '' || strcasecmp($name, app_admin_session_name()) === 0) {
            return 'FRONTSESSID';
        }

        return $name;
    }
}

if (!function_exists('app_session_cookie_params')) {
    /**
     * @return array{lifetime:int,path:string,domain:string,secure:bool,httponly:bool,samesite:string}
     */
    function app_session_cookie_params(): array
    {
        $params = session_get_cookie_params();
        $path = (string) ($params['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }
        $domain = app_session_cookie_domain();

        return [
            'lifetime' => 0,
            'path' => $path,
            'domain' => $domain !== '' ? $domain : (string) ($params['domain'] ?? ''),
            'secure' => app_session_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}

if (!function_exists('app_session_expire_cookie')) {
    /**
     * @param array<string, mixed> $baseOptions
     */
    function app_session_expire_cookie(string $name, array $baseOptions, string $domain = ''): void
    {
        if (headers_sent() || $name === '') {
            return;
        }
        $options = $baseOptions;
        $options['expires'] = time() - 3600;
        $options['domain'] = $domain;
        setcookie($name, '', $options);
    }
}

if (!function_exists('app_session_adopt_legacy_cookies')) {
    /**
     * @param list<string> $legacyNames
     */
    function app_session_adopt_legacy_cookies(string $canonicalName, array $legacyNames): void
    {
        $params = app_session_cookie_params();
        $expireBase = [
            'path' => $params['path'],
            'secure' => $params['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        foreach ($legacyNames as $legacyName) {
            if ($legacyName === '' || $legacyName === $canonicalName || empty($_COOKIE[$legacyName])) {
                continue;
            }
            if (empty($_COOKIE[$canonicalName])) {
                $_COOKIE[$canonicalName] = (string) $_COOKIE[$legacyName];
            }
            if (!headers_sent()) {
                app_session_expire_cookie($legacyName, $expireBase, '');
                if ($params['domain'] !== '') {
                    app_session_expire_cookie($legacyName, $expireBase, $params['domain']);
                }
            }
            unset($_COOKIE[$legacyName]);
        }
    }
}

if (!function_exists('app_session_migrate_to_cookie_domain')) {
    function app_session_migrate_to_cookie_domain(string $sessionName, string $flagKey): void
    {
        $canonicalDomain = app_session_cookie_domain();
        if ($canonicalDomain === '' || !empty($_SESSION[$flagKey]) || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $params = app_session_cookie_params();
        if (!headers_sent()) {
            setcookie($sessionName, session_id(), [
                'expires' => 0,
                'path' => $params['path'],
                'domain' => $canonicalDomain,
                'secure' => $params['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            app_session_expire_cookie($sessionName, [
                'path' => $params['path'],
                'secure' => $params['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ], '');
        }
        $_SESSION[$flagKey] = true;
    }
}

if (!function_exists('app_admin_session_keys')) {
    /** @return list<string> */
    function app_admin_session_keys(): array
    {
        return [
            'bo_backoffice_admin_user',
            'bo_backoffice_admin_csrf',
            'admin_last_activity',
            'admin_superadmin_perms_synced',
            'admin_cookie_domain_migrated',
        ];
    }
}

if (!function_exists('app_member_session_keys')) {
    /** @return list<string> */
    function app_member_session_keys(): array
    {
        return [
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
            'login_error',
        ];
    }
}

if (!function_exists('app_clear_member_session_keys')) {
    /**
     * Üye oturum anahtarlarını siler; admin / CSRF / referral korunur.
     *
     * @param list<string> $preserveExtra
     */
    function app_clear_member_session_keys(array $preserveExtra = []): void
    {
        $preserve = array_fill_keys(array_merge(
            app_admin_session_keys(),
            ['csrf_token', 'referral_code', 'app_csrf_token', 'site_csrf_token', 'frontend_cookie_domain_migrated'],
            $preserveExtra
        ), true);

        foreach (app_member_session_keys() as $key) {
            if (!isset($preserve[$key])) {
                unset($_SESSION[$key]);
            }
        }

        if (function_exists('frontend_clear_member_restore_cookie')) {
            frontend_clear_member_restore_cookie();
        }
    }
}

if (!function_exists('app_session_has_admin_user')) {
    function app_session_has_admin_user(): bool
    {
        $user = $_SESSION['bo_backoffice_admin_user'] ?? null;

        return is_array($user) && !empty($user['id']) && !empty($user['username']);
    }
}
