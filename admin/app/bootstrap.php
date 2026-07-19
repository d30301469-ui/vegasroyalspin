<?php

declare(strict_types=1);

require_once __DIR__ . '/Core/AdminPaths.php';
admin_paths_bootstrap();

if (session_status() === PHP_SESSION_NONE) {
    $adminSessionName = trim((string) (getenv('ADMIN_SESSION_NAME') ?: 'ADMINSESSID'));
    if ($adminSessionName === '') {
        $adminSessionName = 'ADMINSESSID';
    }
    if (session_name() !== $adminSessionName) {
        session_name($adminSessionName);
    }

    $cloudflareConfig = admin_project_path('config/cloudflare.php');
    if (admin_is_readable_file($cloudflareConfig)) {
        require_once $cloudflareConfig;
    }
    if (!function_exists('maltabet_configure_session_security')) {
        function maltabet_configure_session_security(): void
        {
            ini_set('session.use_strict_mode', '1');

            $isHttps = function_exists('metropol_request_is_https')
                ? metropol_request_is_https()
                : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
            $params = session_get_cookie_params();
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => (string) ($params['path'] ?? '/'),
                'domain' => (string) ($params['domain'] ?? ''),
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    maltabet_configure_session_security();
    session_start();
}

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    $isHttpsForHsts = function_exists('metropol_request_is_https')
        ? metropol_request_is_https()
        : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    if ($isHttpsForHsts) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// Sağlam autoloader: manuel require sırası/eksikliği kaynaklı "Class not found" hatalarını önler.
$adminAutoloader = __DIR__ . '/Core/AdminAutoloader.php';
if (!function_exists('admin_register_autoloader') && is_readable($adminAutoloader)) {
    require_once $adminAutoloader;
}
if (function_exists('admin_register_autoloader')) {
    admin_register_autoloader(ADMIN_APP_PATH, defined('METROPOL_ROOT') ? METROPOL_ROOT : admin_project_root());
}

// Admin/backend host: panel ve API her zaman açılabilmeli. Provider secret'ları panelden
// yönetildiği için bootstrap sırasında (config/app.php) zorunlu secret assertion'ları atlanır;
// gerçek doğrulama ilgili callback handler'larında (imza/secret) çalışmaya devam eder.
if (!defined('METROPOL_ADMIN_PANEL')) {
    define('METROPOL_ADMIN_PANEL', true);
}

$rootConfig = admin_project_path('config/app.php');
if (admin_is_readable_file($rootConfig)) {
    require_once $rootConfig;
}
$rootDbConfig = admin_project_path('config/db.php');
if (admin_is_readable_file($rootDbConfig)) {
    require_once $rootDbConfig;
}

require_once ADMIN_APP_PATH . '/Config/admin.php';
require_once ADMIN_APP_PATH . '/Core/AdminDatabase.php';
require_once ADMIN_APP_PATH . '/Services/AdminSiteContext.php';
admin_require_project_file('services/MegaPayzService.php');
admin_require_project_file('services/BgamingService.php');
admin_require_project_file('services/DrakonService.php');
admin_require_project_file('services/SportsbookService.php');
admin_require_project_file('services/MemberKycService.php');
admin_require_project_file('services/MemberNotificationService.php');
admin_require_project_file('services/SupportTicketService.php');
admin_require_project_file('services/ComplianceService.php');
$isProduction = in_array(strtolower(trim((string) getenv('APP_ENV'))), ['production', 'prod'], true);
if (!$isProduction && (string) getenv('METROPOL_RUNTIME_PROVIDER_BOOTSTRAP') === '1') {
    try {
        MegaPayzService::bootstrap(AdminDatabase::pdo());
        BgamingService::bootstrap(AdminDatabase::pdo());
        DrakonService::bootstrap(AdminDatabase::pdo());
    } catch (Throwable) {
    }
}
require_once ADMIN_APP_PATH . '/Core/ErrorHandler.php';
\App\Core\ErrorHandler::register();

require_once ADMIN_APP_PATH . '/Core/AdminRequest.php';
require_once ADMIN_APP_PATH . '/Core/AdminRouter.php';
require_once ADMIN_APP_PATH . '/Core/AdminRoutePermission.php';
require_once ADMIN_APP_PATH . '/Core/AdminController.php';
require_once ADMIN_APP_PATH . '/Repositories/AdminTableRepository.php';
require_once ADMIN_APP_PATH . '/Services/AdminAuth.php';
require_once ADMIN_APP_PATH . '/Services/AdminDataRedactor.php';
require_once ADMIN_APP_PATH . '/Services/AdminFieldPresenter.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminAuthController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminDashboardController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminBackofficeSuiteController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminTableController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminModuleController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminMegaPayzController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminBgamingController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminDrakonController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminSportsbookController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminFooterController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminSiteSettingsController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminMobileMenuController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminHomepageSectionsController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminUserController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminPermissionController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminCommunicationController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminSupportController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminMemberNotificationController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminKycController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminComplianceController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminReportController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminSystemController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminPromotionController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminPromocodeRequestController.php';
require_once ADMIN_APP_PATH . '/Controllers/AdminBonusClaimController.php';
