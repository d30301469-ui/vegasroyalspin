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

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = is_string($uri) ? $uri : '/';
$scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
$scriptDir = ($scriptDir === '/' || $scriptDir === '.') ? '' : '/' . trim($scriptDir, '/');
if ($scriptDir !== '' && ($uri === $scriptDir || strpos($uri, $scriptDir . '/') === 0)) {
    $uri = substr($uri, strlen($scriptDir));
    $uri = $uri === '' ? '/' : $uri;
}
$apiRoute = isset($_GET['api_route']) && is_string($_GET['api_route']) ? $_GET['api_route'] : '';
if ($apiRoute !== '' && strpos($apiRoute, '/api/') === 0) {
    $uri = $apiRoute;
}
$uri = rtrim($uri, '/') ?: '/';

if (function_exists('frontend_database_allowed') && !frontend_database_allowed() && frontend_uri_is_backend_only($uri)) {
    frontend_emit_backend_only_response();
}

$host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
$adminHostCandidates = array_merge(
    [
        defined('BACKEND_HOST') ? (string) BACKEND_HOST : '',
        getenv('ADMIN_URL_HOST') ?: '',
        getenv('BACKEND_HOST') ?: '',
        parse_url((string) (getenv('BACKEND_URL') ?: ''), PHP_URL_HOST) ?: '',
        parse_url((string) (getenv('BACKEND_FALLBACK_URL') ?: ''), PHP_URL_HOST) ?: '',
    ],
    function_exists('deploy_backend_hosts') ? deploy_backend_hosts() : ['bo-nexthub.site', 'api.bo-nexthub.site']
);
$adminHosts = [];
foreach ($adminHostCandidates as $candidate) {
    $candidateHost = strtolower(preg_replace('/:\d+$/', '', trim((string) $candidate)) ?? '');
    if ($candidateHost !== '') {
        $adminHosts[] = $candidateHost;
    }
}
$isAdminHost = in_array($host, array_unique($adminHosts), true);
if (!$isAdminHost && (
    strpos($uri, '/callbacks/') === 0
    || $uri === '/callbacks'
    || strpos($uri, '/bgaming-wallet') === 0
    || strpos($uri, '/api/v2/drakon_callback') === 0
    || strpos($uri, '/admin/api/v2/drakon_callback') === 0
    || strpos($uri, '/drakon_callback') === 0
    || strpos($uri, '/drakon_api') === 0
    || strpos($uri, '/drakon-callback') === 0
    || strpos($uri, '/api/v2/bgaming') === 0
    || strpos($uri, '/api/v2/bgaming-wallet') === 0
    || strpos($uri, '/api/v2/megapayz') === 0
)) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'code' => 404,
        'error' => 'BACKEND_CALLBACK_ONLY',
        'message' => 'Provider callback endpointleri sadece backend hostunda calisir.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (ayar_bakim_modu_active($ayar) && !maintenance_request_uri_allowed($uri)) {
    http_response_code(503);
    require VIEW_PATH . '/pages/maintenance.php';
    exit;
}

// KayД±t modalД± veya ajax check: POST ile aynД± sayfaya gelince (controller'a yГ¶nlendir)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($uri, '/api/') !== 0 && (
    isset($_POST['register_submit']) ||
    (isset($_POST['ajax_check']) && $_POST['ajax_check'] === 'true')
)) {
    require_once CONTROLLER_PATH . '/Api/ApiAuthController.php';
    (new ApiAuthController())->register();
    exit;
}

// в”Ђв”Ђв”Ђ TanД±mlД± Route'lar в”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђ
// [URI => [ControllerSД±nД±fД±, metot]]
$routes = [
    '/'                      => ['HomeController',   'index'],
    '/slot'                  => ['SlotController',   'index'],
    '/beni-ara'              => ['BeniAraController', 'index'],
    '/login'                 => ['AuthController',   'login'],
    '/register'              => ['AuthController',   'register'],
    '/reset-password'        => ['AuthController',   'resetPasswordPage'],
    '/logout'                => ['AuthController',   'logout'],
    '/payment/megapayz'      => ['PaymentController', 'megapayzDeposit'],
    '/api/balanceapi'        => ['ApiBalanceController', 'index'],
    '/api/balanceapi.php'   => ['ApiBalanceController', 'index'],
    '/api'                   => ['ApiCallbackController', 'index'],
    '/api/index.php'        => ['ApiCallbackController', 'index'],
    '/api/casino-callback'   => ['ApiCasinoCallbackController', 'index'],
    '/api-gates'             => ['ApiCasinoCallbackController', 'index'],
    // Гњye + CMS public API в†’ metropol_handle_public_api_request() в†’ PublicApiV2Dispatcher
    '/search_handler.php'    => ['ApiSearchController', 'advancedSearch'],
    '/track_visit.php'       => ['ApiTrackVisitController', 'index'],
    '/slot_api.php'          => ['ApiSlotController', 'index'],
    '/signup_tracker.php'    => ['ApiSignupTrackerController', 'index'],
    '/bgaming-wallet'                  => ['ApiBgamingWalletController', 'health'],
    '/bgaming-wallet/balance'          => ['ApiBgamingWalletController', 'balance'],
    '/bgaming-wallet/play'             => ['ApiBgamingWalletController', 'play'],
    '/bgaming-wallet/rollback'         => ['ApiBgamingWalletController', 'rollback'],
    '/bgaming-wallet/freespins/finish' => ['ApiBgamingWalletController', 'freespinsFinish'],
    '/bgaming-wallet/promo/bet'        => ['ApiBgamingWalletController', 'promoBet'],
    '/bgaming-wallet/promo/win'        => ['ApiBgamingWalletController', 'promoWin'],
    '/bgaming-wallet/promo/rollback'   => ['ApiBgamingWalletController', 'promoRollback'],
    '/bgaming-wallet/auth/token_rotation' => ['ApiBgamingWalletController', 'tokenRotation'],
    '/game/launch'           => ['GameController', 'launch'],
];

// в”Ђв”Ђв”Ђ Route EЕџleЕџtirme в”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђ
if (isset($routes[$uri])) {
    [$controllerName, $method] = $routes[$uri];
    if (!frontend_database_allowed() && frontend_controller_is_backend_only($controllerName)) {
        frontend_emit_backend_only_response();
    }
    $isApi = (strpos($controllerName, 'Api') === 0);
    $controllerFile = $isApi
        ? CONTROLLER_PATH . '/Api/' . $controllerName . '.php'
        : CONTROLLER_PATH . '/' . $controllerName . '.php';
    require_once $controllerFile;
    $controller = new $controllerName();
    $controller->$method();
    exit;
}

// в”Ђв”Ђв”Ђ Legacy Fallback: HenГјz dГ¶nГјЕџtГјrГјlmemiЕџ sayfalar в”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђ
$segments = explode('/', trim($uri, '/'));

// Yasal sayfalar ve promosyon alias — CMS/footer_pages üzerinden tema içinde açılır.
$cmsFooterSlugs = ['gizlilik-politikasi', 'genel-sartlar'];
if ($uri === '/promosyonlar') {
    header('Location: /promotions', true, 301);
    exit;
}

// Footer statik sayfalar: /footer?slug=... veya /footer/slug
if ($uri === '/footer' || (isset($segments[0]) && $segments[0] === 'footer' && count($segments) <= 2)) {
    require_once CONTROLLER_PATH . '/FooterPageController.php';
    $footerSlug = trim((string) ($_GET['slug'] ?? ''));
    if ($footerSlug === '' && count($segments) === 2) {
        $footerSlug = (string) $segments[1];
    }
    (new FooterPageController())->show($footerSlug);
    exit;
}

if (count($segments) === 2) {
    $legacyFile = __DIR__ . '/pages/' . $segments[0] . '/' . $segments[1] . '.php';
    if (file_exists($legacyFile)) {
        require $legacyFile;
        exit;
    }
}

if (count($segments) === 1 && $segments[0] !== '') {
    $slugPath = '/' . $segments[0];
    $legacyFile = __DIR__ . '/pages/' . $segments[0] . '.php';
    $legacyIndex = __DIR__ . '/pages/' . $segments[0] . '/index.php';
    $isKnownRoute = isset($routes[$slugPath]);
    $hasLegacy = file_exists($legacyFile) || file_exists($legacyIndex);

    // Yasal sayfalar ve bilinmeyen slug'lar footer_pages CMS'den açılır.
    if (!$isKnownRoute && (in_array($segments[0], $cmsFooterSlugs, true) || !$hasLegacy)) {
        require_once CONTROLLER_PATH . '/FooterPageController.php';
        (new FooterPageController())->show((string) $segments[0]);
        exit;
    }

    $legacyFile = __DIR__ . '/pages/' . $segments[0] . '.php';
    if (file_exists($legacyFile)) {
        require $legacyFile;
        exit;
    }
    $legacyIndex = __DIR__ . '/pages/' . $segments[0] . '/index.php';
    if (file_exists($legacyIndex)) {
        require $legacyIndex;
        exit;
    }
}

http_response_code(404);
header('Content-Type: text/html; charset=UTF-8');
echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><title>404</title></head><body>';
echo '<h1>404 - Sayfa bulunamadı</h1>';
echo '</body></html>';

