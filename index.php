<?php
/**
 * Front Controller - TГјm istekler buradan geГ§er.
 */

@set_time_limit(120);

$frontendPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$frontendPath = '/' . trim(is_string($frontendPath) ? $frontendPath : '/', '/');

if ($frontendPath === '/install' || str_starts_with($frontendPath, '/install/')) {
    require __DIR__ . '/install.php';
    exit;
}

if ($frontendPath === '/install-complete.php' || $frontendPath === '/install-complete.html') {
    header('Location: /install/complete', true, 302);
    exit;
}

if (isset($_GET['installed']) && (string) $_GET['installed'] === '1') {
    header('Location: /install/complete', true, 302);
    exit;
}

$installGateFile = __DIR__ . '/app/Core/FrontendInstallGate.php';
if (!is_readable($installGateFile)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><meta charset="utf-8"><div style="font-family:sans-serif;max-width:680px;margin:40px auto;padding:0 20px">';
    echo '<h1>Eksik dosyalar</h1><p><code>app/Core/FrontendInstallGate.php</code> bulunamadı. Zip\'i site <strong>köküne</strong> açın (<code>/www/wwwroot/vegasroyalspin.com/</code>), DocumentRoot <code>public/</code> olmamalı.</p>';
    echo '</div>';
    exit;
}
require_once $installGateFile;
FrontendInstallGate::loadEnv(__DIR__);
if (!FrontendInstallGate::isInstalled(__DIR__)) {
    header('Location: /install');
    exit;
}

$__isRegisterAjaxCheck = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_POST['ajax_check'])
    && (string) $_POST['ajax_check'] === 'true';
if ($__isRegisterAjaxCheck) {
    require_once __DIR__ . '/services/register_ajax_check.php';
    metropol_handle_register_ajax_check();
}

if (str_starts_with($frontendPath, '/api/')) {
    require_once __DIR__ . '/services/frontend_api_dispatch.php';
    metropol_handle_public_api_request((string) ($_SERVER['REQUEST_URI'] ?? '/'));
}

try {
    require_once __DIR__ . '/core/bootstrap.php';
} catch (Throwable $bootstrapException) {
    if (is_readable(__DIR__ . '/config/env.php')) {
        require_once __DIR__ . '/config/env.php';
    }
    if (function_exists('metropol_render_frontend_boot_error')) {
        metropol_render_frontend_boot_error($bootstrapException);
    } else {
        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<h1>Site hatası</h1><pre>' . htmlspecialchars($bootstrapException->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    }
    exit;
}

require __DIR__ . '/core/legacy_dispatch.php';
