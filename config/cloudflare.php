<?php

declare(strict_types=1);

/**
 * Cloudflare edge SSL: visitors use HTTPS; origin (aaPanel) stays HTTP on port 80.
 * Public .env URLs must be https://; outbound PHP uses API_BACKEND_INTERNAL_* loopback when set.
 */

if (!function_exists('metropol_cloudflare_ssl_enabled')) {
    function metropol_cloudflare_ssl_enabled(): bool
    {
        if (!function_exists('frontend_env_bool')) {
            foreach (['CLOUDFLARE_SSL', 'TRUST_CLOUDFLARE_SSL'] as $key) {
                $value = getenv($key);
                if ($value !== false && in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true)) {
                    return true;
                }
            }

            return false;
        }

        return frontend_env_bool('CLOUDFLARE_SSL')
            || frontend_env_bool('TRUST_CLOUDFLARE_SSL');
    }
}

if (!function_exists('metropol_origin_http_only')) {
    function metropol_origin_http_only(): bool
    {
        if (!function_exists('frontend_env_bool')) {
            $value = getenv('ORIGIN_HTTP');
            if ($value !== false && in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            return metropol_cloudflare_ssl_enabled();
        }

        return frontend_env_bool('ORIGIN_HTTP') || metropol_cloudflare_ssl_enabled();
    }
}

if (!function_exists('metropol_request_is_https')) {
    /**
     * TLS at the browser (direct HTTPS or Cloudflare X-Forwarded-Proto / CF-Visitor).
     */
    function metropol_request_is_https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        $forwarded = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        if ($forwarded === 'https') {
            return true;
        }

        $cfVisitor = trim((string) ($_SERVER['HTTP_CF_VISITOR'] ?? ''));
        if ($cfVisitor !== '' && str_contains($cfVisitor, '"scheme":"https"')) {
            return true;
        }

        if (metropol_cloudflare_ssl_enabled() && !empty($_SERVER['HTTP_CF_RAY'])) {
            return true;
        }

        return false;
    }
}

if (!function_exists('metropol_public_url_scheme')) {
    /**
     * Scheme for SITE_URL / BACKEND_URL in .env and install wizards.
     */
    function metropol_public_url_scheme(string $fallback = 'https'): string
    {
        if (metropol_cloudflare_ssl_enabled()) {
            return 'https';
        }

        return metropol_request_is_https() ? 'https' : $fallback;
    }
}

if (!function_exists('metropol_is_local_dev_host')) {
    function metropol_is_local_dev_host(string $host): bool
    {
        $host = strtolower(trim($host));
        if ($host === '' || $host === 'localhost' || $host === '127.0.0.1') {
            return true;
        }

        return str_ends_with($host, '.test') || str_ends_with($host, '.local');
    }
}

if (!function_exists('metropol_coerce_public_https_url')) {
    /**
     * Upgrade public http:// origins to https:// when Cloudflare edge SSL is enabled.
     * Leaves loopback / .test hosts unchanged.
     */
    function metropol_coerce_public_https_url(string $url): string
    {
        $url = trim($url);
        if ($url === '' || !metropol_cloudflare_ssl_enabled()) {
            return $url;
        }

        if (!preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return $url;
        }

        $host = strtolower((string) $parts['host']);
        if (metropol_is_local_dev_host($host)) {
            return $url;
        }

        if (strtolower((string) ($parts['scheme'] ?? '')) === 'https') {
            return $url;
        }

        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

        return 'https://' . (string) $parts['host'] . $port;
    }
}

if (!function_exists('metropol_build_public_origin_url')) {
    function metropol_build_public_origin_url(string $httpHost, string $fallbackScheme = 'https'): string
    {
        $httpHost = preg_replace('/:\d+$/', '', trim($httpHost)) ?: 'localhost';
        $scheme = metropol_public_url_scheme($fallbackScheme);

        return $scheme . '://' . $httpHost;
    }
}

if (!function_exists('metropol_cloudflare_client_ip')) {
    function metropol_cloudflare_client_ip(): string
    {
        $candidates = [
            (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''),
            (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ];
        foreach ($candidates as $candidate) {
            $candidate = trim(explode(',', $candidate)[0] ?? '');
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        return '';
    }
}
