<?php

declare(strict_types=1);

$backendPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$backendPath = '/' . trim(is_string($backendPath) ? $backendPath : '/', '/');

if ($backendPath === '/install' || str_starts_with($backendPath, '/install/')) {
    require __DIR__ . '/install.php';
    exit;
}

$installGate = __DIR__ . '/app/Core/AdminInstallGate.php';
if (!is_readable($installGate)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><meta charset="utf-8"><div style="font-family:sans-serif;max-width:680px;margin:40px auto;padding:0 20px">';
    echo '<h1>Eksik dosyalar</h1><p><code>app/</code> klasörü sunucuda bulunamadı.</p></div>';
    exit;
}
require_once $installGate;
AdminInstallGate::loadEnv(__DIR__);
if (!AdminInstallGate::isInstalled(__DIR__)) {
    header('Location: /install');
    exit;
}

/**
 * Webhook + member API: lightweight bootstrap only (no session, no admin controllers).
 */
$isLightweightRoute = preg_match('#^/api/v2/(?:bgaming-wallet|bgaming)(?:/.*)?$#', $backendPath) === 1
    || $backendPath === '/api/v2/megapayz-callback'
    || $backendPath === '/MegaPayz/deposit'
    || $backendPath === '/megapayz/deposit'
    || $backendPath === '/api/v2/casino-callback'
    || $backendPath === '/api/v2/sportsbook-wallet'
    || str_starts_with($backendPath, '/api/v2/sportsbook-wallet/')
    || $backendPath === '/sportsbook_api'
    || str_starts_with($backendPath, '/sportsbook_api/')
    || $backendPath === '/api/v2/internal'
    || str_starts_with($backendPath, '/api/v2/internal/')
    || $backendPath === '/api/v2'
    || str_starts_with($backendPath, '/api/v2/')
    || in_array($backendPath, ['/ping.php', '/health.php'], true);

if ($isLightweightRoute) {
    if (preg_match('#^/api/v2/(?:bgaming-wallet|bgaming)(?:/(.*))?$#', $backendPath, $bgamingMatch)) {
        $_GET['endpoint'] = trim((string) ($bgamingMatch[1] ?? ''), '/');
        require __DIR__ . '/api/v2/bgaming_callback.php';
        exit;
    }
    if (in_array($backendPath, ['/api/v2/megapayz-callback', '/MegaPayz/deposit', '/megapayz/deposit'], true)) {
        require_once __DIR__ . '/app/Core/AdminPaths.php';
        admin_paths_bootstrap();
        require_once admin_panel_paths()['panel_app'] . '/bootstrap_api.php';
        admin_require_project_file('services/MegaPayzService.php');

        header('Content-Type: application/json; charset=UTF-8');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo json_encode(['status' => false, 'code' => 405, 'message' => 'METHOD_NOT_ALLOWED'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        $transport = MegaPayzService::verifyCallbackTransport($_SERVER);
        if (empty($transport['valid'])) {
            http_response_code((int) ($transport['code'] ?? 403));
            echo json_encode([
                'status' => false,
                'code' => (int) ($transport['code'] ?? 403),
                'message' => (string) ($transport['error'] ?? 'FORBIDDEN'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $raw = file_get_contents('php://input');
        $payload = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($payload)) {
            $payload = $_POST;
        }
        $megaResult = MegaPayzService::handleUnifiedCallback(AdminDatabase::pdo(), $payload);
        $megaCode = (int) ($megaResult['code'] ?? 200);
        if ($megaCode === 99999 || empty($megaResult['status'])) {
            http_response_code(in_array($megaCode, [400, 403, 404, 405, 422], true) ? $megaCode : 422);
        } elseif ($megaCode >= 400 && $megaCode < 600) {
            http_response_code($megaCode);
        }
        echo json_encode($megaResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($backendPath === '/api/v2/sportsbook-wallet'
        || str_starts_with($backendPath, '/api/v2/sportsbook-wallet/')
        || $backendPath === '/sportsbook_api'
        || str_starts_with($backendPath, '/sportsbook_api/')
    ) {
        require_once __DIR__ . '/app/Core/AdminPaths.php';
        admin_paths_bootstrap();
        require_once admin_panel_paths()['panel_app'] . '/bootstrap_api.php';
        require __DIR__ . '/api/v2/sportsbook_callback.php';
        exit;
    }
    if ($backendPath === '/api/v2/casino-callback') {
        require_once __DIR__ . '/app/Core/AdminPaths.php';
        admin_paths_bootstrap();
        require_once admin_panel_paths()['panel_app'] . '/bootstrap_api.php';
        admin_require_project_file('controllers/Api/ApiCasinoCallbackController.php');
        (new ApiCasinoCallbackController())->index();
        exit;
    }
    if ($backendPath === '/api/v2/internal' || str_starts_with($backendPath, '/api/v2/internal/')) {
        $internalSuffix = trim(substr($backendPath, strlen('/api/v2/internal')), '/');
        if ($internalSuffix === '') {
            $_GET['route'] = 'health';
        } elseif (in_array($internalSuffix, ['health', 'metrics'], true)) {
            $_GET['route'] = 'internal/' . $internalSuffix;
        } elseif (str_starts_with($internalSuffix, 'jobs/')) {
            $_GET['route'] = 'internal/' . $internalSuffix;
        } else {
            $_GET['route'] = $internalSuffix;
        }
        define('METROPOL_API_V2_INTERNAL', true);
        require __DIR__ . '/api/v2/internal.php';
        exit;
    }
    if ($backendPath === '/api/v2' || str_starts_with($backendPath, '/api/v2/')) {
        $_GET['route'] = trim(substr($backendPath, strlen('/api/v2')), '/');
        require __DIR__ . '/api/v2/index.php';
        exit;
    }
    if ($backendPath === '/ping.php' && is_file(__DIR__ . '/ping.php')) {
        require __DIR__ . '/ping.php';
        exit;
    }
    if ($backendPath === '/health.php' && is_file(__DIR__ . '/health.php')) {
        require __DIR__ . '/health.php';
        exit;
    }
}

$bootstrap = __DIR__ . '/app/bootstrap.php';
if (!is_readable($bootstrap)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><meta charset="utf-8"><div style="font-family:sans-serif;max-width:680px;margin:40px auto;padding:0 20px">';
    echo '<h1>Eksik dosya: app/bootstrap.php</h1></div>';
    exit;
}
require_once $bootstrap;

$router = new AdminRouter();

$router->get('/', [AdminDashboardController::class, 'index']);
$router->get('/dashboard', [AdminDashboardController::class, 'index']);
$router->get('/backoffice-suite', [AdminBackofficeSuiteController::class, 'index']);
$router->get('/login', [AdminAuthController::class, 'login']);
$router->post('/dashboard/cache-purge', [AdminDashboardController::class, 'purgeCaches']);
$router->post('/login', [AdminAuthController::class, 'authenticate']);
$router->post('/logout', [AdminAuthController::class, 'logout']);
$router->get('/tables', [AdminTableController::class, 'index']);
$router->get('/datatable', [AdminTableController::class, 'index']);
$router->get('/table', [AdminTableController::class, 'show']);
$router->get('/table/create', [AdminTableController::class, 'create']);
$router->get('/forms', [AdminSystemController::class, 'forms']);
$router->get('/signup', [AdminSystemController::class, 'signup']);
$router->post('/signup', [AdminSystemController::class, 'storeAdmin']);
$router->post('/signup/store', [AdminSystemController::class, 'storeAdmin']);
$router->post('/table/store', [AdminTableController::class, 'store']);
$router->get('/table/view', [AdminTableController::class, 'viewRecord']);
$router->get('/table/edit', [AdminTableController::class, 'edit']);
$router->post('/table/update', [AdminTableController::class, 'update']);
$router->post('/table/delete', [AdminTableController::class, 'delete']);
$router->get('/module', [AdminModuleController::class, 'show']);
$router->post('/module', [AdminModuleController::class, 'update']);
$router->get('/bgaming/settings', [AdminBgamingController::class, 'settings']);
$router->post('/bgaming/settings', [AdminBgamingController::class, 'updateSettings']);
$router->post('/bgaming/sync-games', [AdminBgamingController::class, 'syncGames']);
$router->get('/bgaming/campaigns', [AdminBgamingController::class, 'campaigns']);
$router->get('/bgaming/campaigns/assignments', [AdminBgamingController::class, 'campaignAssignments']);
$router->post('/bgaming/campaigns/store', [AdminBgamingController::class, 'storeCampaign']);
$router->post('/bgaming/campaigns/assign', [AdminBgamingController::class, 'assignCampaign']);
$router->get('/bgaming/freespins', [AdminBgamingController::class, 'freespins']);
$router->post('/bgaming/freespins/issue', [AdminBgamingController::class, 'issueFreespins']);
$router->post('/bgaming/freespins/sync', [AdminBgamingController::class, 'syncFreespinStatus']);
$router->post('/bgaming/freespins/cancel', [AdminBgamingController::class, 'cancelFreespin']);
$router->get('/sportsbook/settings', [AdminSportsbookController::class, 'settings']);
$router->post('/sportsbook/settings', [AdminSportsbookController::class, 'updateSettings']);
$router->get('/megapayz/settings', [AdminMegaPayzController::class, 'settings']);
$router->post('/megapayz/settings', [AdminMegaPayzController::class, 'updateSettings']);
$router->get('/megapayz/methods', [AdminMegaPayzController::class, 'methods']);
$router->post('/megapayz/methods', [AdminMegaPayzController::class, 'updateMethods']);
$router->post('/megapayz/withdraw/approve', [AdminMegaPayzController::class, 'approveWithdraw']);
$router->post('/megapayz/withdraw/reject', [AdminMegaPayzController::class, 'rejectWithdraw']);
$router->get('/footer', [AdminFooterController::class, 'legacyRedirect']);
$router->post('/footer', [AdminFooterController::class, 'update']);
$router->get('/site-settings', [AdminSiteSettingsController::class, 'edit']);
$router->post('/site-settings', [AdminSiteSettingsController::class, 'update']);
$router->get('/mobile-menu', [AdminMobileMenuController::class, 'edit']);
$router->post('/mobile-menu', [AdminMobileMenuController::class, 'update']);
$router->get('/homepage-sections', [AdminHomepageSectionsController::class, 'edit']);
$router->post('/homepage-sections', [AdminHomepageSectionsController::class, 'update']);
$router->get('/user', [AdminUserController::class, 'detail']);
$router->get('/user/create', [AdminUserController::class, 'create']);
$router->post('/user/store', [AdminUserController::class, 'store']);
$router->get('/user/edit', [AdminUserController::class, 'edit']);
$router->post('/user/update', [AdminUserController::class, 'update']);
$router->post('/user/balance-adjust', [AdminUserController::class, 'balanceAdjust']);
$router->post('/user/note/store', [AdminUserController::class, 'storeNote']);
$router->get('/promotions', [AdminPromotionController::class, 'index']);
$router->get('/promotion/create', [AdminPromotionController::class, 'create']);
$router->post('/promotion/store', [AdminPromotionController::class, 'store']);
$router->get('/promotion/edit', [AdminPromotionController::class, 'edit']);
$router->post('/promotion/update', [AdminPromotionController::class, 'update']);
$router->post('/promotion/delete', [AdminPromotionController::class, 'delete']);
$router->get('/promotion/claims', [AdminPromotionController::class, 'claims']);
$router->post('/bonus/assign', [AdminPromotionController::class, 'assignBonus']);
$router->post('/bonus/revoke', [AdminPromotionController::class, 'revokeBonus']);
$router->post('/promocode-request/approve', [AdminPromocodeRequestController::class, 'approve']);
$router->post('/promocode-request/reject', [AdminPromocodeRequestController::class, 'reject']);
$router->post('/bonus-claim/approve', [AdminBonusClaimController::class, 'approve']);
$router->post('/bonus-claim/reject', [AdminBonusClaimController::class, 'reject']);
$router->post('/bonus-claim/reset-all', [AdminBonusClaimController::class, 'resetAll']);
$router->post('/module/reset-pending-transactions', [AdminModuleController::class, 'resetPendingTransactions']);
$router->get('/reports/financial', [AdminReportController::class, 'financial']);
$router->get('/compliance/audit-log', [AdminComplianceController::class, 'auditLog']);
$router->get('/permissions', [AdminPermissionController::class, 'index']);
$router->post('/permissions', [AdminPermissionController::class, 'update']);
$router->get('/email', [AdminCommunicationController::class, 'email']);
$router->get('/email/settings', [AdminCommunicationController::class, 'settings']);
$router->post('/email/settings', [AdminCommunicationController::class, 'saveSettings']);
$router->post('/email/settings/test', [AdminCommunicationController::class, 'testMail']);
$router->get('/compose', [AdminCommunicationController::class, 'compose']);
$router->post('/compose', [AdminCommunicationController::class, 'send']);
$router->get('/chat', [AdminCommunicationController::class, 'chat']);
$router->get('/support/tickets', [AdminSupportController::class, 'tickets']);
$router->get('/support/ticket', [AdminSupportController::class, 'ticket']);
$router->post('/support/reply', [AdminSupportController::class, 'reply']);
$router->post('/support/close', [AdminSupportController::class, 'close']);
$router->get('/notifications', [AdminMemberNotificationController::class, 'index']);
$router->post('/notifications/send', [AdminMemberNotificationController::class, 'send']);
$router->get('/kyc/review', [AdminKycController::class, 'review']);
$router->post('/kyc/approve', [AdminKycController::class, 'approve']);
$router->post('/kyc/reject', [AdminKycController::class, 'reject']);
$router->get('/compliance/aml-alerts', [AdminComplianceController::class, 'amlAlerts']);
$router->get('/compliance/risk-alerts', [AdminComplianceController::class, 'riskAlerts']);
$router->post('/compliance/aml/resolve', [AdminComplianceController::class, 'resolveAml']);
$router->post('/compliance/risk/resolve', [AdminComplianceController::class, 'resolveRisk']);
$router->get('/reports/calendar', [AdminReportController::class, 'calendar']);
$router->get('/reports/charts', [AdminReportController::class, 'charts']);
$router->get('/reports/geomap', [AdminSystemController::class, 'googleMaps']);
$router->get('/compliance/risk-analysis', [AdminRiskController::class, 'index']);
$router->get('/signin', [AdminAuthController::class, 'login']);

$path = AdminRequest::path();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$permission = AdminRoutePermission::resolve($path);
if ($permission !== null) {
    if (!AdminAuth::check()) {
        header('Location: ' . AdminAuth::url('/login'));
        exit;
    }
    if (!AdminAuth::can($permission)) {
        http_response_code(403);
        (new AdminController())->view('errors/403', [
            'title' => 'Erişim engellendi',
            'errorMessage' => 'Bu işlem için gerekli yetkiye sahip değilsiniz.',
        ], 'app');
        exit;
    }
}

$router->dispatch($path, $method);
