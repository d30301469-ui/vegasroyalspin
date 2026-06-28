<?php

declare(strict_types=1);

define('METROPOL_INSTALL_WIZARD', true);

@set_time_limit(120);

if (session_status() === PHP_SESSION_NONE) {
    if (is_readable(__DIR__ . '/config/cloudflare.php')) {
        require_once __DIR__ . '/config/cloudflare.php';
    }
    $https = function_exists('metropol_request_is_https')
        ? metropol_request_is_https()
        : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * @param non-empty-string $message
 */
function frontend_install_fail(string $message, int $code = 500): void
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: text/html; charset=UTF-8');
    }

    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><title>Kurulum hatası</title></head><body style="font-family:Inter,sans-serif;padding:24px;max-width:720px;margin:0 auto">';
    echo '<h1>Frontend kurulum hatası</h1>';
    echo '<pre style="white-space:pre-wrap;background:#f5f5f5;padding:16px;border-radius:8px">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</pre>';
    echo '</body></html>';
    exit;
}

try {
    $root = __DIR__;
    if (is_readable($root . '/config/env.php')) {
        require_once $root . '/config/env.php';
    }
    $files = [
        '/app/Core/FrontendInstallGate.php',
        '/app/Services/FrontendInstaller.php',
        '/app/Controllers/FrontendInstallController.php',
        '/app/Views/install/wizard.php',
        '/app/Views/install/complete.php',
    ];
    foreach ($files as $rel) {
        if (!is_readable($root . $rel)) {
            frontend_install_fail('Eksik dosya: ' . $rel);
        }
    }

    require_once $root . '/app/Core/FrontendInstallGate.php';
    require_once $root . '/app/Services/FrontendInstaller.php';
    require_once $root . '/app/Controllers/FrontendInstallController.php';

    $controller = new FrontendInstallController($root);
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $installPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/install'), PHP_URL_PATH);
    $installPath = is_string($installPath) && $installPath !== '' ? $installPath : '/install';
    $controller->dispatch($method, $installPath);
} catch (Throwable $exception) {
    frontend_install_fail($exception->getMessage() . "\n\n" . $exception->getFile() . ':' . $exception->getLine());
}
