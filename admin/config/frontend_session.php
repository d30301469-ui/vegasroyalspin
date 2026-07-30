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

if (!function_exists('metropol_frontend_member_restore_cookie_name')) {
    function metropol_frontend_member_restore_cookie_name(): string
    {
        $name = trim((string) (getenv('FRONTEND_MEMBER_RESTORE_COOKIE') ?: 'metropol_member_restore'));

        return $name !== '' ? $name : 'metropol_member_restore';
    }
}

if (!function_exists('metropol_frontend_member_restore_cookie_options')) {
    /** @return array{expires:int,path:string,domain:string,secure:bool,httponly:bool,samesite:string} */
    function metropol_frontend_member_restore_cookie_options(int $expiresAt): array
    {
        $params = session_get_cookie_params();

        return [
            'expires' => $expiresAt,
            'path' => (string) ($params['path'] ?? '/'),
            'domain' => (string) ($params['domain'] ?? ''),
            'secure' => (bool) ($params['secure'] ?? true),
            'httponly' => true,
            'samesite' => (string) ($params['samesite'] ?? 'Lax'),
        ];
    }
}

if (!function_exists('metropol_frontend_set_member_restore_cookie')) {
    function metropol_frontend_set_member_restore_cookie(string $jwt, int $ttl = 2592000): void
    {
        $token = trim($jwt);
        if ($token === '') {
            metropol_frontend_clear_member_restore_cookie();
            return;
        }

        setcookie(
            metropol_frontend_member_restore_cookie_name(),
            $token,
            metropol_frontend_member_restore_cookie_options(time() + max(300, $ttl))
        );
    }
}

if (!function_exists('metropol_frontend_clear_member_restore_cookie')) {
    function metropol_frontend_clear_member_restore_cookie(): void
    {
        setcookie(
            metropol_frontend_member_restore_cookie_name(),
            '',
            metropol_frontend_member_restore_cookie_options(time() - 3600)
        );

        // Cleanup older JS-managed fallback cookie too.
        setcookie('metropol_member_jwt', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => (string) (session_get_cookie_params()['domain'] ?? ''),
            'secure' => (bool) (session_get_cookie_params()['secure'] ?? true),
            'httponly' => false,
            'samesite' => (string) (session_get_cookie_params()['samesite'] ?? 'Lax'),
        ]);
    }
}
