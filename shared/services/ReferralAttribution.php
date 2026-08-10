<?php

declare(strict_types=1);

require_once __DIR__ . '/BackendApiClient.php';

/**
 * Frontend tarafında referans kodunu yakalar, saklar ve backend ortaklık servisine iletir.
 *
 * Oturum çerezi API host'una taşınmadığı için (split deploy, APP_API_NO_SESSION)
 * kod ayrıca uzun ömürlü bir çerezde tutulur ve kayıt isteğine gövde alanı olarak eklenir.
 */
final class ReferralAttribution
{
    public const COOKIE = 'vrs_ref';
    private const TTL_DAYS = 30;

    public static function normalize(string $code): string
    {
        $code = trim($code);
        if ($code === '' || strlen($code) > 64) {
            return '';
        }

        return preg_match('/^[A-Za-z0-9_-]+$/', $code) === 1 ? $code : '';
    }

    /** Kodu oturuma ve çereze yazar. */
    public static function remember(string $code): void
    {
        $code = self::normalize($code);
        if ($code === '') {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['referral_code'] = $code;
        }

        if (headers_sent()) {
            return;
        }

        $params = [
            'expires' => time() + (self::TTL_DAYS * 86400),
            'path' => '/',
            'secure' => self::isHttps(),
            'httponly' => false,
            'samesite' => 'Lax',
        ];
        $domain = self::cookieDomain();
        if ($domain !== '') {
            $params['domain'] = $domain;
        }

        setcookie(self::COOKIE, $code, $params);
        $_COOKIE[self::COOKIE] = $code;
    }

    /** Oturum veya çerezden bilinen referans kodunu döner. */
    public static function current(): string
    {
        $session = session_status() === PHP_SESSION_ACTIVE ? (string) ($_SESSION['referral_code'] ?? '') : '';
        $code = self::normalize($session);
        if ($code !== '') {
            return $code;
        }

        return self::normalize((string) ($_COOKIE[self::COOKIE] ?? ''));
    }

    /**
     * URL'de ?ref= varsa yakalar. Her sayfa yüklemesinde çağrılabilir.
     * Kod ilk kez görülüyorsa tıklama olarak da kaydedilir; aynı kodla gelen
     * sonraki sayfa gezintileri tekrar sayılmaz.
     */
    public static function captureFromRequest(): string
    {
        $code = self::normalize((string) ($_GET['ref'] ?? ''));
        if ($code === '') {
            return self::current();
        }

        $isNew = $code !== self::current();
        self::remember($code);

        if ($isNew) {
            self::trackClick($code, self::clientIp(), self::currentUrl());
        }

        return $code;
    }

    private static function clientIp(): string
    {
        if (function_exists('cloudflare_client_ip')) {
            $ip = trim((string) cloudflare_client_ip());
            if ($ip !== '') {
                return $ip;
            }
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    private static function currentUrl(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            return '';
        }

        return (self::isHttps() ? 'https://' : 'http://') . $host . (string) ($_SERVER['REQUEST_URI'] ?? '/');
    }

    /** Tıklamayı ortaklık servisine bildirir. Servis yapılandırılmamışsa sessizce geçer. */
    public static function trackClick(string $code, string $ip = '', string $landingUrl = ''): void
    {
        $code = self::normalize($code);
        if ($code === '') {
            return;
        }

        try {
            BackendApiClient::request('POST', BackendApiClient::SVC_AFFILIATE, '/affiliate/track-click', [], [
                'referral_code' => $code,
                'ip' => $ip !== '' ? $ip : '0.0.0.0',
                'landing_url' => $landingUrl,
                'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'referrer' => (string) ($_SERVER['HTTP_REFERER'] ?? ''),
                'country' => (string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''),
            ], 4);
        } catch (Throwable $e) {
            error_log('[ReferralAttribution::trackClick] ' . $e->getMessage());
        }
    }

    private static function isHttps(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off') {
            return true;
        }

        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    private static function cookieDomain(): string
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '' && function_exists('deploy_session_cookie_domain_for_host')) {
            $configured = trim((string) deploy_session_cookie_domain_for_host($host));
            if ($configured !== '') {
                return $configured;
            }
        }

        $host = (string) preg_replace('/:\d+$/', '', $host);
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return '';
        }

        $parts = explode('.', $host);
        if (count($parts) < 3) {
            return '';
        }

        // www / m gibi alt alan adları arasında paylaşılabilmesi için kök alan adına yaz.
        return '.' . implode('.', array_slice($parts, -2));
    }
}
