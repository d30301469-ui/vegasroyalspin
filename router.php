<?php
/**
 * Router for PHP built-in development server.
 * Tüm istekleri front controller (index.php) üzerinden yönetir.
 *
 * Kullanım: php -S localhost:8080 router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if (preg_match('~(^|/)\.(?!well-known(?:/|$))~', $uri) === 1
    || preg_match('~(^|/)[^/]+\.(?:log|sql|sqlite|db|bak|backup|old|orig|save|swp|tmp|env|ini|conf|config|pem|key|crt|p12|pfx|ps1|sh|bat|cmd|py|zip|tar|gz|tgz|7z|rar)$~i', $uri) === 1
) {
    http_response_code(403);
    echo '403 Forbidden';
    return true;
}

foreach (['/app/', '/admin/app/', '/config/', '/database/', '/docs/', '/logs/', '/repositories/', '/scripts/', '/services/', '/storage/', '/tests/', '/tools/', '/vendor/'] as $dir) {
    if (strpos($uri, $dir) === 0) {
        http_response_code(403);
        echo '403 Forbidden';
        return true;
    }
}

// Dahili PHP dosyalarına doğrudan erişimi engelle (statik dosyalar serbest)
$blockedDirs = ['/core/', '/controllers/', '/config/', '/views/', '/partials/', '/pages/', '/repositories/', '/services/', '/mobile/views/'];
foreach ($blockedDirs as $dir) {
    if (strpos($uri, $dir) === 0 && preg_match('/\.php$/i', $uri)) {
        http_response_code(403);
        echo '403 Forbidden';
        return true;
    }
}

// Drakon callback admin/backend API altında yaşar; frontend callback route'u yoktur.
// config/env.php'deki metropol_is_backend_host() merkezi host kontrolünü yükle.
if (!function_exists('metropol_is_backend_host')) {
    require_once __DIR__ . '/config/env.php';
}
$trimmedForDrakon = rtrim($uri, '/');
$host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
$isBackendHost = metropol_is_backend_host($host);
if (str_starts_with($trimmedForDrakon, '/admin/api/v2') && !$isBackendHost) {
    http_response_code(404);
    echo '404 Not Found';
    return true;
}

if (in_array($trimmedForDrakon, ['/api/v2/drakon_callback', '/api/v2/drakon_callback.php', '/api/v2/drakon_callback/drakon_api', '/admin/api/v2/drakon_callback', '/admin/api/v2/drakon_callback.php', '/admin/api/v2/drakon_callback/drakon_api'], true)) {
    if (!$isBackendHost) {
        http_response_code(404);
        echo '404 Not Found';
        return true;
    }
    require __DIR__ . '/admin/api/v2/drakon_callback.php';
    return true;
}
if (in_array($trimmedForDrakon, ['/drakon_api', '/drakon_api/drakon_api', '/drakon_api/drakon_api.php', '/drakon_callback', '/drakon_callback/drakon_api', '/drakon_callback.php', '/drakon-callback'], true)) {
    if ($isBackendHost) {
        require __DIR__ . '/admin/api/v2/drakon_callback.php';
        return true;
    }
    if (is_readable(__DIR__ . '/config/env.php')) {
        require_once __DIR__ . '/config/env.php';
    }
    if (function_exists('metropol_proxy_drakon_webhook')) {
        metropol_proxy_drakon_webhook();
    }
    http_response_code(404);
    echo '404 Not Found';
    return true;
}

// API isteklerinde public ve backend yüzeyleri ayrı front controller kullanır.
if (strpos($uri, '/api/v2') === 0 || strpos($uri, '/api/member/') === 0 || strpos($uri, '/api/content/') === 0) {
    require $isBackendHost ? __DIR__ . '/admin/index.php' : __DIR__ . '/public/index.php';
    return true;
}

// Eski api-gates callback URL'i → front controller (casino callback)
$trimmedForLegacy = rtrim($uri, '/');
if ($trimmedForLegacy === '/api-gates') {
    require __DIR__ . '/index.php';
    return true;
}

// Gerçek dosya veya klasör varsa doğrudan sun
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Özel API endpoint'leri (drakon artık index.php route ile; gold_api, tbs2 aynı)
$specialRoutes = [
    '/gold_api'            => '/gold_api/api.php',
    '/tbs2'                => '/tbs2/api.php',
];

$trimmedUri = rtrim($uri, '/');

foreach ($specialRoutes as $pattern => $target) {
    if ($trimmedUri === $pattern) {
        require __DIR__ . $target;
        return true;
    }
}

// Signup tracker: /r/{ref}
if (preg_match('#^/r/([a-zA-Z0-9_-]+)$#', $trimmedUri, $matches)) {
    $_GET['ref'] = $matches[1];
    require __DIR__ . '/signup_tracker.php';
    return true;
}

// Tüm diğer istekler → front controller
require __DIR__ . '/index.php';
return true;
