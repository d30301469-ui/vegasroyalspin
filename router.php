<?php
/**
 * Router for PHP built-in development server.
 * TÃ¼m istekleri front controller (index.php) Ã¼zerinden yÃ¶netir.
 *
 * KullanÄ±m: php -S localhost:8080 router.php
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

// Dahili PHP dosyalarÄ±na doÄŸrudan eriÅŸimi engelle (statik dosyalar serbest)
$blockedDirs = ['/core/', '/controllers/', '/config/', '/views/', '/partials/', '/pages/', '/repositories/', '/services/', '/mobile/views/'];
foreach ($blockedDirs as $dir) {
    if (strpos($uri, $dir) === 0 && preg_match('/\.php$/i', $uri)) {
        http_response_code(403);
        echo '403 Forbidden';
        return true;
    }
}

// config/env.php'deki metropol_is_backend_host() merkezi host kontrolÃ¼nÃ¼ yÃ¼kle.
if (!function_exists('metropol_is_backend_host')) {
    require_once __DIR__ . '/config/env.php';
}
$trimmedUri = rtrim($uri, '/');
$host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
$isBackendHost = metropol_is_backend_host($host);
if (!$isBackendHost && preg_match('/^(?:admin|api)\.(?:localhost|[^.]+\.(?:test|local))$/', $host) === 1) {
    $isBackendHost = true;
}
if (str_starts_with($trimmedUri, '/admin/api/v2') && !$isBackendHost) {
    http_response_code(404);
    echo '404 Not Found';
    return true;
}

// API isteklerinde public ve backend yÃ¼zeyleri ayrÄ± front controller kullanÄ±r.
if (strpos($uri, '/api/v2') === 0 || strpos($uri, '/api/member/') === 0 || strpos($uri, '/api/content/') === 0) {
    require $isBackendHost ? __DIR__ . '/admin/index.php' : __DIR__ . '/public/index.php';
    return true;
}

if ($isBackendHost) {
    $adminStaticFile = __DIR__ . '/admin' . $uri;
    if (is_file($adminStaticFile) && strtolower(pathinfo($adminStaticFile, PATHINFO_EXTENSION)) !== 'php') {
        $contentType = function_exists('mime_content_type') ? mime_content_type($adminStaticFile) : false;
        if (is_string($contentType) && $contentType !== '') {
            header('Content-Type: ' . $contentType);
        }
        readfile($adminStaticFile);
        return true;
    }

    require __DIR__ . '/admin/index.php';
    return true;
}

if (($trimmedUri === '/admin' || str_starts_with($trimmedUri, '/admin/'))
    && !file_exists(__DIR__ . $uri)
) {
    require __DIR__ . '/admin/index.php';
    return true;
}
// Eski api-gates callback URL'i â†’ front controller (casino callback)
$trimmedForLegacy = rtrim($uri, '/');
if ($trimmedForLegacy === '/api-gates') {
    require __DIR__ . '/index.php';
    return true;
}

// GerÃ§ek dosya veya klasÃ¶r varsa doÄŸrudan sun
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Ã–zel API endpoint'leri
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

// TÃ¼m diÄŸer istekler â†’ front controller
require __DIR__ . '/index.php';
return true;
