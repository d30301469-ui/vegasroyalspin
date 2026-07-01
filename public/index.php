<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if ($requestPath === '/install' || str_starts_with($requestPath, '/install/')) {
    require $root . '/install.php';
    exit;
}

$installGate = $root . '/app/Core/FrontendInstallGate.php';
if (is_readable($installGate)) {
    require_once $installGate;
    FrontendInstallGate::loadEnv($root);
    if (!FrontendInstallGate::isInstalled($root)) {
        header('Location: /install');
        exit;
    }
}
if (str_starts_with($requestPath, '/api/')) {
    require_once $root . '/services/frontend_api_dispatch.php';
    metropol_handle_public_api_request((string) ($_SERVER['REQUEST_URI'] ?? '/'));
}

// Drakon webhook proxy: /drakon_api on frontend host → proxy to admin backend.
// (Apache catch-all routes here; router.php proxy only runs on PHP built-in server.)
if ($requestPath === '/drakon_api' || rtrim($requestPath, '/') === '/drakon_api') {
    require_once $root . '/config/env.php';
    if (function_exists('metropol_proxy_drakon_webhook')) {
        metropol_proxy_drakon_webhook();
    }
    if (!headers_sent()) {
        http_response_code(502);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => 'DRAKON_PROXY_UNAVAILABLE'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $root . '/app/bootstrap.php';

use App\Core\Request;
use App\Core\Router;

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/frontend_session.php';
    metropol_frontend_session_start();
}

$router = new Router();
$routes = require CONFIG_PATH . '/routes.php';
$routes($router);
$router->dispatch(new Request());
