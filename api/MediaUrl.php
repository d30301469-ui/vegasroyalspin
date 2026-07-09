<?php

declare(strict_types=1);

/**
 * CMS medya yollarını split-deploy için çözümler.
 * Admin yüklemeleri (/uploads, /storage/uploads) backend host'ta kalır;
 * frontend statik dosyalar (/assets) site kökünde kalır.
 */
final class ApiMediaUrl
{
    public static function ensureLoaded(): void
    {
        if (class_exists(self::class, false)) {
            return;
        }
        $path = defined('API_PATH')
            ? rtrim((string) API_PATH, '/') . '/MediaUrl.php'
            : dirname(__DIR__) . '/api/MediaUrl.php';
        if (is_readable($path)) {
            require_once $path;
        }
    }

    public static function resolve(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return self::rewriteAbsoluteUrl($path);
        }
        if (str_starts_with($path, '//')) {
            return 'https:' . $path;
        }

        $normalized = str_replace('\\', '/', $path);
        if ($normalized[0] !== '/') {
            $normalized = '/' . $normalized;
        }

        $normalized = self::normalizeLegacyMediaPath($normalized);

        // Legacy links may contain a stale /public prefix.
        if (preg_match('#^/public(?:/|$)#i', $normalized) === 1) {
            $normalized = substr($normalized, 7);
            $normalized = $normalized === '' ? '/' : $normalized;
        }

        if (self::isBackendHostedPath($normalized)) {
            $backend = self::backendOrigin();
            if ($backend !== '') {
                return $backend . $normalized;
            }
        }

        $sitePath = '';
        if (defined('SITE_URL')) {
            $sitePath = (string) (parse_url((string) SITE_URL, PHP_URL_PATH) ?: '');
            $sitePath = self::normalizePublicPrefix($sitePath);
            $sitePath = $sitePath === '/' ? '' : rtrim($sitePath, '/');
        }

        return $sitePath . $normalized;
    }

    /**
     * @param array<string, mixed> $slider
     * @return array<string, mixed>
     */
    public static function resolveSliderRow(array $slider): array
    {
        foreach ([
            'desktopImageUrl',
            'mobileImageUrl',
            'imageUrl',
            'desktop_image_url',
            'mobile_image_url',
            'desktop_path',
            'mobile_path',
            'image_url',
        ] as $key) {
            if (!isset($slider[$key]) || !is_string($slider[$key])) {
                continue;
            }
            $slider[$key] = self::resolve($slider[$key]);
        }

        return $slider;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function resolveAuthSliderRow(array $row): array
    {
        foreach (['mediaPath', 'media_path'] as $key) {
            if (!isset($row[$key]) || !is_string($row[$key])) {
                continue;
            }
            $row[$key] = self::resolve($row[$key]);
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function resolveHomepagePayload(array $payload): array
    {
        if (isset($payload['image_url']) && is_string($payload['image_url'])) {
            $payload['image_url'] = self::resolve($payload['image_url']);
        }

        if (!isset($payload['items']) || !is_array($payload['items'])) {
            return $payload;
        }

        foreach ($payload['items'] as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            if (isset($item['image_url']) && is_string($item['image_url'])) {
                $item['image_url'] = self::resolve($item['image_url']);
            }
            $payload['items'][$index] = $item;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $promotion
     * @return array<string, mixed>
     */
    public static function resolvePromotionRow(array $promotion): array
    {
        if (isset($promotion['image_url']) && is_string($promotion['image_url'])) {
            $promotion['image_url'] = self::resolvePromotionImage($promotion['image_url']);
        }

        return $promotion;
    }

    public static function resolvePromotionImage(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path)) {
            $absolutePath = (string) (parse_url($path, PHP_URL_PATH) ?? '');
            if ($absolutePath !== '') {
                $normalized = self::normalizeLegacyMediaPath($absolutePath);
                if (self::isBackendHostedPath($normalized)) {
                    return self::frontendMediaPath($normalized);
                }
            }
        }

        $normalized = self::normalizeLegacyMediaPath(str_replace('\\', '/', $path));
        if ($normalized !== '' && $normalized[0] !== '/') {
            $normalized = '/' . $normalized;
        }

        if (self::isBackendHostedPath($normalized)) {
            return self::frontendMediaPath($normalized);
        }

        return self::resolve($path);
    }

    /**
     * CMS/ayar JSON içindeki eski mutlak URL'leri (admin.metropolcasino.test vb.) düzeltir.
     *
     * @return array<string, mixed>|list<mixed>|scalar|null
     */
    public static function rewriteDeep(mixed $value): mixed
    {
        if (is_string($value)) {
            if (preg_match('#^https?://#i', $value) || str_starts_with($value, '/')) {
                return self::resolve($value);
            }
            if (str_contains($value, '://') || str_contains($value, '.test/') || str_contains($value, '.local/')) {
                return preg_replace_callback(
                    '#https?://[^\s"\'<>]+#i',
                    static fn (array $match): string => self::resolve($match[0]),
                    $value
                ) ?? $value;
            }

            return $value;
        }
        if (!is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::rewriteDeep($item);
        }

        return $value;
    }

    private static function isBackendHostedPath(string $path): bool
    {
        $lower = strtolower($path);

        return str_starts_with($lower, '/uploads/')
            || str_starts_with($lower, '/storage/uploads/')
            || str_starts_with($lower, '/admin/uploads/');
    }

    private static function backendOrigin(): string
    {
        $candidates = [
            getenv('BACKEND_URL') ?: '',
            getenv('BACKEND_FALLBACK_URL') ?: '',
            defined('BACKEND_URL') ? (string) BACKEND_URL : '',
        ];

        if (function_exists('deploy_domain')) {
            $candidates[] = deploy_domain('backend_url');
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && preg_match('#^https?://#i', $candidate)) {
                return rtrim($candidate, '/');
            }
        }

        if (defined('API_BACKEND_MAIN_BASE_URL')) {
            $base = trim((string) API_BACKEND_MAIN_BASE_URL);
            if ($base !== '' && preg_match('#^https?://#i', $base)) {
                $parts = parse_url($base);
                $scheme = (string) ($parts['scheme'] ?? 'https');
                $host = (string) ($parts['host'] ?? '');
                if ($host !== '') {
                    $port = isset($parts['port']) ? ':' . $parts['port'] : '';

                    return $scheme . '://' . $host . $port;
                }
            }
        }

        return '';
    }

    private static function rewriteAbsoluteUrl(string $url): string
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        if ($host === '' || !self::isStaleDevHost($host)) {
            return $url;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        if ($path === '') {
            $path = '/';
        }
        $path = self::normalizeLegacyMediaPath($path);

        $query = parse_url($url, PHP_URL_QUERY);
        $fragment = parse_url($url, PHP_URL_FRAGMENT);

        // Frontend statik dosyaları (logo, /assets, /content) göreli yola çevir — cross-origin CORS/loopback hatası olmaz.
        if (!self::isBackendHostedPath($path)) {
            $relative = $path;
            if (is_string($query) && $query !== '') {
                $relative .= '?' . $query;
            }
            if (is_string($fragment) && $fragment !== '') {
                $relative .= '#' . $fragment;
            }

            return $relative;
        }

        $origin = self::backendOrigin();
        if ($origin === '') {
            return $url;
        }

        $out = rtrim($origin, '/') . $path;
        if (is_string($query) && $query !== '') {
            $out .= '?' . $query;
        }
        if (is_string($fragment) && $fragment !== '') {
            $out .= '#' . $fragment;
        }

        return $out;
    }

    private static function isStaleDevHost(string $host): bool
    {
        // Trusted media CDN hostlarini mutlak birak: admin uploads host'una rewrite edilmemeli.
        if (preg_match('/^icons\.casinomilyon\d+\.com$/i', $host) === 1) {
            return false;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }
        if (str_ends_with($host, '.test') || str_ends_with($host, '.local')) {
            return true;
        }

        if (function_exists('deploy_stale_url_hosts')) {
            return in_array($host, deploy_stale_url_hosts(), true);
        }

        return false;
    }

    private static function normalizePublicPrefix(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path === '/') {
            return '';
        }

        $normalized = '/' . trim($path, '/');
        $lower = strtolower($normalized);
        if ($lower === '/public') {
            return '';
        }

        if (str_ends_with($lower, '/public')) {
            $normalized = substr($normalized, 0, -7) ?: '/';
        }

        return $normalized === '/' ? '' : rtrim($normalized, '/');
    }

    private static function normalizeLegacyMediaPath(string $path): string
    {
        $normalized = '/' . ltrim(str_replace('\\', '/', $path), '/');
        $lower = strtolower($normalized);

        if (str_starts_with($lower, '/storage/uploads/')) {
            return '/uploads/' . ltrim(substr($normalized, strlen('/storage/uploads/')), '/');
        }
        if (str_starts_with($lower, '/admin/uploads/')) {
            return '/uploads/' . ltrim(substr($normalized, strlen('/admin/uploads/')), '/');
        }

        if (str_starts_with($lower, '/storage/medias/')) {
            return '/uploads/medias/' . ltrim(substr($normalized, strlen('/storage/medias/')), '/');
        }
        if (str_starts_with($lower, '/storage/media/')) {
            return '/uploads/media/' . ltrim(substr($normalized, strlen('/storage/media/')), '/');
        }

        return $normalized;
    }

    private static function frontendMediaPath(string $path): string
    {
        $sitePath = '';
        if (defined('SITE_URL')) {
            $sitePath = (string) (parse_url((string) SITE_URL, PHP_URL_PATH) ?: '');
            $sitePath = self::normalizePublicPrefix($sitePath);
            $sitePath = $sitePath === '/' ? '' : rtrim($sitePath, '/');
        }

        return $sitePath . $path;
    }

    private static function frontendOrigin(): string
    {
        $candidates = [
            getenv('SITE_URL') ?: '',
            getenv('FRONTEND_URL') ?: '',
            defined('SITE_URL') ? (string) SITE_URL : '',
        ];

        if (function_exists('deploy_domain')) {
            $candidates[] = deploy_domain('mobile_url');
            $candidates[] = deploy_domain('frontend_url');
        }

        $requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($requestHost !== '' && !in_array($requestHost, ['localhost', '127.0.0.1'], true)) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
                ? 'https'
                : 'http';
            $candidates[] = $scheme . '://' . $requestHost;
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && preg_match('#^https?://#i', $candidate)) {
                $parts = parse_url($candidate);
                $scheme = (string) ($parts['scheme'] ?? 'https');
                $host = (string) ($parts['host'] ?? '');
                if ($host !== '') {
                    $port = isset($parts['port']) ? ':' . $parts['port'] : '';

                    return rtrim($scheme . '://' . $host . $port, '/');
                }
            }
        }

        return '';
    }
}
