<?php

/**
 * CMS / admin medya yolu → tarayıcıda kullanılabilir URL (split deploy).
 */
function cms_asset_url(string $path): string
{
    if (!class_exists('ApiMediaUrl', false) && defined('API_PATH') && is_readable(API_PATH . '/MediaUrl.php')) {
        require_once API_PATH . '/MediaUrl.php';
    } elseif (!class_exists('ApiMediaUrl', false) && is_readable(dirname(__DIR__) . '/api/MediaUrl.php')) {
        require_once dirname(__DIR__) . '/api/MediaUrl.php';
    }

    return class_exists('ApiMediaUrl', false) ? ApiMediaUrl::resolve($path) : '/' . ltrim($path, '/');
}

/**
 * View dosya yolunu döndürür.
 */
function view_path(string $path): string
{
    return VIEW_PATH . '/' . $path . '.php';
}

/**
 * Asset URL'si oluşturur (cache busting ile).
 */
function asset_url(string $path): string
{
    static $versionCache = [];
    static $basePath = null;

    $path = trim($path);
    if ($path === '') {
        return '/?v=1';
    }
    if (preg_match('#^/+https?://#i', $path) === 1) {
        $path = ltrim($path, '/');
    }
    if (preg_match('#^https?://#i', $path) === 1 || str_starts_with($path, '//')) {
        return $path;
    }

    $relative = ltrim($path, '/');

    if (!array_key_exists($relative, $versionCache)) {
        $fullPath = BASE_PATH . '/' . $relative;
        if (file_exists($fullPath)) {
            $hash = @md5_file($fullPath);
            if ($hash !== false) {
                $versionCache[$relative] = substr($hash, 0, 12) . '-' . (string) filesize($fullPath);
            } else {
                $versionCache[$relative] = (string) (@filemtime($fullPath) ?: '1');
            }
        } else {
            $versionCache[$relative] = '1';
        }
    }

    if ($basePath === null) {
        $basePath = '';
        if (defined('SITE_URL')) {
            $sitePath = (string) (parse_url((string) SITE_URL, PHP_URL_PATH) ?: '');
            $basePath = $sitePath === '/' ? '' : rtrim($sitePath, '/');
        }
    }

    return $basePath . '/' . $relative . '?v=' . $versionCache[$relative];
}

/**
 * Güvenli HTML escape.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * İstek mobil surface üzerinde mi?
 */
function isMobile(): bool
{
    return defined('SURFACE') && SURFACE === 'mobile';
}

/**
 * Site ayarından bakım modu açık mı ($ayar bootstrap ile gelir).
 */
function ayar_bakim_modu_active(array $ayar): bool
{
    if (!array_key_exists('bakim_modu', $ayar)) {
        return false;
    }
    $v = $ayar['bakim_modu'];
    if ($v === true || $v === 1 || $v === '1') {
        return true;
    }
    if (is_string($v) && strtolower($v) === 'true') {
        return true;
    }

    return false;
}

/**
 * Bakım modundayken erişime izin verilen path'ler (giriş ve gerekli API).
 *
 * @param non-empty-string $uri parse_url path, örn. /login
 */
function maintenance_request_uri_allowed(string $uri): bool
{
    static $exact = [
        '/login',
        '/api/auth/login',
        '/api/auth/login.php',
        '/api/auth/session',
        '/api/auth/session.php',
        '/api/site_settings',
        '/api/site_settings.php',
        '/api/site-settings',
        '/api/site-settings.php',
        '/api/v2/site-settings',
        '/api/v2/site_settings.php',
        '/api/announcements',
        '/api/announcements.php',
        '/api/v2/announcements',
        '/api/v2/announcements.php',
    ];

    if (in_array($uri, $exact, true)) {
        return true;
    }

    return false;
}

/**
 * TLS üzerinden mi geliyor (reverse proxy dahil)?
 */
function request_is_https(): bool
{
    if (function_exists('metropol_request_is_https')) {
        return metropol_request_is_https();
    }

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/**
 * Masaüstü hostundan m.{ana_domain} HTTP Host değerini üretir.
 * Zaten m.* ise veya host boşsa null.
 */
function mobile_http_host_from_desktop(string $httpHost): ?string
{
    $httpHost = trim($httpHost);
    if ($httpHost === '') {
        return null;
    }

    $hostname = $httpHost;
    $port = '';
    if (preg_match('/^(\[[^\]]+\])(:\d+)?$/', $httpHost, $m)) {
        $hostname = $m[1];
        $port = $m[2] ?? '';
    } elseif (preg_match('/^([^:]+):(\d+)$/', $httpHost, $m) && strpos($httpHost, ']') === false) {
        $hostname = $m[1];
        $port = ':' . $m[2];
    }

    $label = strtolower($hostname);
    if (str_starts_with($label, 'm.')) {
        return null;
    }
    $core = str_starts_with($label, 'www.') ? substr($label, 4) : $label;

    return 'm.' . $core . $port;
}

/**
 * Masaüstü yüzeyindeyken mobil site kökü (şema + host, path yok).
 */
function mobile_site_base_url(): ?string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $mobileHost = mobile_http_host_from_desktop($host);
    if ($mobileHost === null) {
        return null;
    }
    $scheme = request_is_https() ? 'https' : 'http';

    return $scheme . '://' . $mobileHost;
}
