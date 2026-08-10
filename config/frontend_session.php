<?php

declare(strict_types=1);

/**
 * Üye (frontend) oturumu — FRONTSESSID.
 * Admin paneli ADMINSESSID kullanır; iki cookie asla birleşmez.
 */
require_once __DIR__ . '/session_global.php';

if (!function_exists('frontend_configure_session_security')) {
    function frontend_configure_session_security(): void
    {
        ini_set('session.use_strict_mode', '1');
        if (!function_exists('deploy_session_cookie_domain_for_host')) {
            $deploy = __DIR__ . '/deploy_domains.php';
            if (is_readable($deploy)) {
                require_once $deploy;
            }
        }
        $cloudflare = __DIR__ . '/cloudflare.php';
        if (is_readable($cloudflare)) {
            require_once $cloudflare;
        }
        session_set_cookie_params(app_session_cookie_params());
    }
}

if (!function_exists('frontend_session_start')) {
    function frontend_session_start(): void
    {
        $frontendSessionName = app_frontend_session_name();
        $adminSessionName = app_admin_session_name();

        if (session_status() !== PHP_SESSION_NONE) {
            // Admin oturumu açıkken üye anahtarlarını ADMINSESSID'ye yazma.
            if (session_name() === $adminSessionName) {
                return;
            }
            return;
        }

        frontend_configure_session_security();
        if (session_name() !== $frontendSessionName) {
            session_name($frontendSessionName);
        }

        app_session_adopt_legacy_cookies($frontendSessionName, []);

        session_start();
        app_session_migrate_to_cookie_domain($frontendSessionName, 'frontend_cookie_domain_migrated');
    }
}

if (!function_exists('frontend_session_write_close')) {
    function frontend_session_write_close(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}

if (!function_exists('frontend_member_restore_cookie_name')) {
    function frontend_member_restore_cookie_name(): string
    {
        $name = trim((string) (getenv('FRONTEND_MEMBER_RESTORE_COOKIE') ?: 'app_member_restore'));

        return $name !== '' ? $name : 'app_member_restore';
    }
}

if (!function_exists('frontend_member_restore_cookie_options')) {
    /** @return array{expires:int,path:string,domain:string,secure:bool,httponly:bool,samesite:string} */
    function frontend_member_restore_cookie_options(int $expiresAt): array
    {
        $params = app_session_cookie_params();

        return [
            'expires' => $expiresAt,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => true,
            'samesite' => $params['samesite'],
        ];
    }
}

if (!function_exists('frontend_set_member_restore_cookie')) {
    function frontend_set_member_restore_cookie(string $jwt, int $ttl = 2592000): void
    {
        $token = trim($jwt);
        if ($token === '') {
            frontend_clear_member_restore_cookie();
            return;
        }

        if (headers_sent()) {
            return;
        }

        setcookie(
            frontend_member_restore_cookie_name(),
            $token,
            frontend_member_restore_cookie_options(time() + max(300, $ttl))
        );
    }
}

if (!function_exists('frontend_clear_member_restore_cookie')) {
    function frontend_clear_member_restore_cookie(): void
    {
        if (headers_sent()) {
            return;
        }

        $opts = frontend_member_restore_cookie_options(time() - 3600);
        setcookie(frontend_member_restore_cookie_name(), '', $opts);
        // Legacy restore cookie names.
        foreach (['metropol_member_restore', 'app_member_restore'] as $legacyName) {
            if ($legacyName === frontend_member_restore_cookie_name()) {
                continue;
            }
            setcookie($legacyName, '', $opts);
            if ($opts['domain'] !== '') {
                $hostOnly = $opts;
                $hostOnly['domain'] = '';
                setcookie($legacyName, '', $hostOnly);
            }
        }

        foreach (['app_member_jwt', 'metropol_member_jwt'] as $legacyJwtCookie) {
            setcookie($legacyJwtCookie, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => $opts['domain'],
                'secure' => $opts['secure'],
                'httponly' => false,
                'samesite' => $opts['samesite'],
            ]);
        }
    }
}

if (!function_exists('metropol_frontend_session_start')) {
    function metropol_frontend_session_start(): void
    {
        frontend_session_start();
    }
}
