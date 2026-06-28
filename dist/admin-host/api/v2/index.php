<?php

require_once __DIR__ . '/includes/member_api_cors.php';

require_once __DIR__ . '/bootstrap.php';
admin_require_project_file('services/MemberJwtService.php');
admin_require_project_file('services/MemberAccountService.php');
admin_require_project_file('services/MemberKycService.php');
admin_require_project_file('services/MemberNotificationService.php');
admin_require_project_file('services/SupportTicketService.php');
admin_require_project_file('services/ComplianceService.php');
admin_require_project_file('services/ComplianceMonitorService.php');
admin_require_project_file('services/MegaPayzService.php');
admin_require_project_file('services/DrakonService.php');
admin_require_project_file('services/BgamingService.php');

header('Content-Type: application/json; charset=UTF-8');

require __DIR__ . '/includes/member_api_kernel.php';

if (defined('METROPOL_API_V2_INTERNAL') && METROPOL_API_V2_INTERNAL) {
    require __DIR__ . '/includes/admin_routes.php';
    goto admin_api_dispatch;
}
require __DIR__ . '/includes/member_route_loader.php';

require __DIR__ . '/includes/admin_routes.php';

admin_api_dispatch:
foreach ($routes as [$routeMethod, $routePattern, $handler]) {
    if ($routeMethod !== $method) {
        continue;
    }
    $params = $extractRouteParams($routePattern, $route);
    if (!is_array($params)) {
        continue;
    }
    try {
        $handler($params, $payload);
    } catch (Throwable $exception) {
        $debug = (string) (getenv('APP_DEBUG') ?: '') === '1'
            || (defined('APP_DEBUG') && APP_DEBUG);
        $meta = $debug ? ['reason' => $exception->getMessage()] : [];
        $error(500, 'Beklenmeyen API hatası.', $meta);
    }
}

$error(404, 'API endpoint bulunamadı.', ['method' => $method, 'route' => $route]);
