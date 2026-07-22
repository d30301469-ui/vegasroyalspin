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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($requestPath, ['/', '/sportsbook_api', '/api/v2/sportsbook-wallet', '/api/v2/sportsbook-wallet/'], true)) {
    $rawBody = (string) file_get_contents('php://input');
    $payload = json_decode($rawBody, true);
    if (!is_array($payload) && $_POST !== []) {
        $payload = $_POST;
    }
    if (!is_array($payload) && trim($rawBody) !== '') {
        parse_str($rawBody, $parsed);
        if (is_array($parsed) && $parsed !== []) {
            $payload = $parsed;
        }
    }
    $method = trim((string) ($payload['method'] ?? $payload['action'] ?? ''));
    $method = strtolower(str_replace(['_', ' '], '', $method));
    if (in_array($method, ['getbalance', 'changebalance', 'updatedetail'], true)) {
        require_once $root . '/admin/api/v2/sportsbook_callback.php';
        exit;
    }
}

if (str_starts_with($requestPath, '/api/')) {
    require_once $root . '/services/frontend_api_dispatch.php';
    metropol_handle_public_api_request((string) ($_SERVER['REQUEST_URI'] ?? '/'));
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
