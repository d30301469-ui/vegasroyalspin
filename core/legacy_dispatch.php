<?php
/**
 * Legacy front-controller dispatch — URI çözümleme ve route eşleştirme.
 * Bootstrap (core/bootstrap.php) tamamlandıktan sonra çalışır.
 * Hem index.php (doğrudan giriş) hem de LegacyPublicController (PSR-4 router köprüsü)
 * tarafından include edilir; startup kontrolleri burada tekrar çalışmaz.
 *
 * global bildirimi: PSR-4 router köprüsü üzerinden geldiğinde (LegacyPublicController
 * legacyRequire metodu) bu dosya bir method scope içinde include edilir; bootstrap'ta
 * global olarak atanan değişkenlere erişmek için açık global bildirimi gerekir.
 */
global $ayar, $loggedIn, $siteMeta, $siteBranding, $siteContactLinks, $siteSettingsPayload;

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
$isAdminHost = function_exists('metropol_is_backend_host')
    ? metropol_is_backend_host($host)
    : in_array($host, array_filter(array_map('trim', [
        defined('BACKEND_HOST') ? (string) BACKEND_HOST : '',
        getenv('ADMIN_URL_HOST') ?: '',
        getenv('BACKEND_HOST') ?: '',
        parse_url((string) (getenv('BACKEND_URL') ?: ''), PHP_URL_HOST) ?: '',
        parse_url((string) (getenv('BACKEND_FALLBACK_URL') ?: ''), PHP_URL_HOST) ?: '',
        'bo-nexthub.site', 'api.bo-nexthub.site',
    ])), true);
$isDrakonWebhookPath = strpos($uri, '/drakon_api') === 0
    || strpos($uri, '/drakon_callback') === 0
    || strpos($uri, '/drakon-callback') === 0
    || strpos($uri, '/api/v2/drakon_callback') === 0
    || strpos($uri, '/admin/api/v2/drakon_callback') === 0;
if (!$isAdminHost && $isDrakonWebhookPath) {
    if (function_exists('metropol_proxy_drakon_webhook')) {
        metropol_proxy_drakon_webhook();
    }
}
if (!$isAdminHost && (
    strpos($uri, '/callbacks/') === 0
    || $uri === '/callbacks'
    || strpos($uri, '/bgaming-wallet') === 0
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($uri, '/api/') !== 0 && (
    isset($_POST['register_submit']) ||
    (isset($_POST['ajax_check']) && $_POST['ajax_check'] === 'true')
)) {
    require_once CONTROLLER_PATH . '/Api/ApiAuthController.php';
    (new ApiAuthController())->register();
    exit;
}

// ─── Tanımlı Route'lar ────────────────────────────────────────
// [URI => [ControllerSınıfı, metot]]
$routes = [
    '/'                      => ['HomeController',   'index'],
    '/slot'                  => ['SlotController',   'index'],
    '/beni-ara'              => ['BeniAraController', 'index'],
    '/login'                 => ['AuthController',   'login'],
    '/register'              => ['AuthController',   'register'],
    '/reset-password'        => ['AuthController',   'resetPasswordPage'],
    '/logout'                => ['AuthController',   'logout'],
    '/payment/megapayz'      => ['PaymentController', 'megapayzDeposit'],
    '/api'                   => ['ApiCallbackController', 'index'],
    '/api-gates'             => ['ApiCasinoCallbackController', 'index'],
    // Üye + CMS public API → metropol_handle_public_api_request() → PublicApiV2Dispatcher
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

// ─── Route Eşleştirme ────────────────────────────────────────
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

// ─── Legacy Fallback: Henüz dönüştürülmemiş sayfalar ─────────
$segments = explode('/', trim($uri, '/'));

$cmsFooterSlugs = ['gizlilik-politikasi', 'genel-sartlar'];
if ($uri === '/promosyonlar') {
    header('Location: /promotions', true, 301);
    exit;
}

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
    $legacyFile = BASE_PATH . '/pages/' . $segments[0] . '/' . $segments[1] . '.php';
    if (file_exists($legacyFile)) {
        require $legacyFile;
        exit;
    }
}

if (count($segments) === 1 && $segments[0] !== '') {
    $slugPath = '/' . $segments[0];
    $legacyFile = BASE_PATH . '/pages/' . $segments[0] . '.php';
    $legacyIndex = BASE_PATH . '/pages/' . $segments[0] . '/index.php';
    $isKnownRoute = isset($routes[$slugPath]);
    $hasLegacy = file_exists($legacyFile) || file_exists($legacyIndex);

    if (!$isKnownRoute && (in_array($segments[0], $cmsFooterSlugs, true) || !$hasLegacy)) {
        require_once CONTROLLER_PATH . '/FooterPageController.php';
        (new FooterPageController())->show((string) $segments[0]);
        exit;
    }

    if (file_exists($legacyFile)) {
        require $legacyFile;
        exit;
    }
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
