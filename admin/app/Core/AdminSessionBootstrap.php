<?php

declare(strict_types=1);

/**
 * Admin oturum standardı (panel + API).
 * Cookie: ADMINSESSID — üye FRONTSESSID ile asla paylaşılmaz.
 */
$__sessionGlobal = function_exists('admin_project_path')
    ? admin_project_path('config/session_global.php')
    : dirname(__DIR__, 3) . '/config/session_global.php';
if (is_readable($__sessionGlobal)) {
    require_once $__sessionGlobal;
}
unset($__sessionGlobal);

if (!function_exists('admin_session_name')) {
    function admin_session_name(): string
    {
        return function_exists('app_admin_session_name')
            ? app_admin_session_name()
            : (trim((string) (getenv('ADMIN_SESSION_NAME') ?: 'ADMINSESSID')) ?: 'ADMINSESSID');
    }
}

if (!function_exists('admin_session_is_https')) {
    function admin_session_is_https(): bool
    {
        return function_exists('app_session_is_https')
            ? app_session_is_https()
            : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    }
}

if (!function_exists('admin_session_expire_cookie')) {
    /**
     * @param array<string, mixed> $baseOptions
     */
    function admin_session_expire_cookie(string $name, array $baseOptions, string $domain = ''): void
    {
        if (function_exists('app_session_expire_cookie')) {
            app_session_expire_cookie($name, $baseOptions, $domain);

            return;
        }
        if (headers_sent() || $name === '') {
            return;
        }
        $options = $baseOptions;
        $options['expires'] = time() - 3600;
        $options['domain'] = $domain;
        setcookie($name, '', $options);
    }
}

if (!function_exists('admin_session_bootstrap')) {
    function admin_session_bootstrap(bool $enabled = true): void
    {
        if (!$enabled || session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $adminSessionName = admin_session_name();
        // Üye cookie adı ile çakışmayı engelle.
        if (function_exists('app_frontend_session_name') && $adminSessionName === app_frontend_session_name()) {
            $adminSessionName = 'ADMINSESSID';
        }
        if (session_name() !== $adminSessionName) {
            session_name($adminSessionName);
        }

        $cloudflareConfig = function_exists('admin_project_path')
            ? admin_project_path('config/cloudflare.php')
            : '';
        if ($cloudflareConfig !== '' && function_exists('admin_is_readable_file') && admin_is_readable_file($cloudflareConfig)) {
            require_once $cloudflareConfig;
        }

        ini_set('session.use_strict_mode', '1');

        if (function_exists('app_session_cookie_params')) {
            session_set_cookie_params(app_session_cookie_params());
            $params = app_session_cookie_params();
        } else {
            $isHttps = admin_session_is_https();
            $cookieParams = session_get_cookie_params();
            $sessionDomain = function_exists('admin_session_cookie_domain') ? admin_session_cookie_domain() : '';
            $params = [
                'lifetime' => 0,
                'path' => (string) ($cookieParams['path'] ?? '/') ?: '/',
                'domain' => $sessionDomain,
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ];
            session_set_cookie_params($params);
        }

        if (function_exists('app_session_adopt_legacy_cookies')) {
            app_session_adopt_legacy_cookies($adminSessionName, ['VRSADMINSESSID']);
        } else {
            // Minimal fallback
            if (!empty($_COOKIE['VRSADMINSESSID']) && empty($_COOKIE[$adminSessionName])) {
                $_COOKIE[$adminSessionName] = (string) $_COOKIE['VRSADMINSESSID'];
            }
        }

        session_start();

        if (function_exists('app_session_migrate_to_cookie_domain')) {
            app_session_migrate_to_cookie_domain($adminSessionName, 'admin_cookie_domain_migrated');
        }
    }
}

if (!function_exists('admin_session_restore')) {
    function admin_session_restore(): void
    {
        if (defined('APP_API_NO_SESSION') && APP_API_NO_SESSION) {
            return;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if (!class_exists('AdminAuth', true)) {
            return;
        }
        AdminAuth::restorePersistentLogin();
    }
}
