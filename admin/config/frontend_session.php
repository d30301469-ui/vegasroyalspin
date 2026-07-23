<?php

declare(strict_types=1);

/**
 * Frontend oturum (API proxy + sayfalar) — cookie domain, Secure, SameSite.
 * /api/* hızlı yolu core/bootstrap.php atladığı için proxy buradan başlatır.
 */
if (!function_exists('metropol_frontend_configure_session_security')) {
    function metropol_frontend_configure_session_security(): void
    {
        ini_set('session.use_strict_mode', '1');

        $isHttps = function_exists('metropol_request_is_https')
            ? metropol_request_is_https()
            : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

        $params = session_get_cookie_params();
        $httpHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $cookieDomain = trim((string) (getenv('SESSION_COOKIE_DOMAIN') ?: ''));
        if ($cookieDomain === '' && function_exists('deploy_session_cookie_domain_for_host')) {
            $cookieDomain = deploy_session_cookie_domain_for_host($httpHost);
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => (string) ($params['path'] ?? '/'),
            'domain' => $cookieDomain !== '' ? $cookieDomain : (string) ($params['domain'] ?? ''),
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('metropol_frontend_session_start')) {
    function metropol_frontend_session_start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }
        if (!function_exists('deploy_session_cookie_domain_for_host')) {
            require_once __DIR__ . '/deploy_domains.php';
        }
        $cloudflare = __DIR__ . '/cloudflare.php';
        if (is_readable($cloudflare)) {
            require_once $cloudflare;
        }
        metropol_frontend_configure_session_security();
        // Use a dedicated session name so frontend and admin sessions never
        // collide. Admin uses ADMINSESSID; frontend uses FRONTSESSID.
        // Without this, both share the same cookie on .vegasroyalspin.com
        // and login state from one overwrites the other.
        $frontendSessionName = trim((string) (getenv('FRONTEND_SESSION_NAME') ?: 'FRONTSESSID'));
        if (session_name() !== $frontendSessionName) {
            session_name($frontendSessionName);
        }
        session_start();
    }
}

if (!function_exists('metropol_frontend_session_write_close')) {
    function metropol_frontend_session_write_close(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}
