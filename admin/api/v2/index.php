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
admin_require_project_file('services/BgamingService.php');
admin_require_project_file('services/SportsbookService.php');

header('Content-Type: application/json; charset=UTF-8');

require __DIR__ . '/includes/member_api_kernel.php';

// Frontend domain can rewrite /api/v2/* to this backend entrypoint in split deploy.
// Handle internal CMS purge here as well so admin cache-notify works globally.
$normalizedRoute = strtolower(trim((string) $route, '/'));
if (in_array($normalizedRoute, ['internal/cms-cache-purge', 'internal/cms_cache_purge.php'], true)) {
    require_once BASE_PATH . '/services/PublicApiV2Dispatcher.php';
    PublicApiV2Dispatcher::dispatch($route);
    exit;
}

// Provider callbacks can arrive via /api/v2/sportsbook-wallet aliases.
// Handle them here before member/admin route module dispatch to avoid 404.
if ($method === 'POST' && in_array($route, ['sportsbook-wallet', 'sportsbook_wallet', 'sportsbook-wallet.php', 'sportsbook_callback', 'sportsbook-callback'], true)) {
    require __DIR__ . '/sportsbook_callback.php';
    exit;
}

// BGaming callbacks can come via multiple route formats depending on edge rewrites.
// Normalize route and request URI to avoid false 404 on wallet actions (e.g. balance/play).
$routeLower = trim(strtolower($route), '/');
$uriPathLower = trim(strtolower((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH)), '/');
$bgamingPathRegex = '#(?:^|/)(bgaming-wallet(?:\.php)?|bgaming_wallet(?:\.php)?|bgaming-callback(?:\.php)?|bgaming_callback(?:\.php)?|bgaming(?:\.php)?)(?:/|$)#';

$isBgamingCallbackRoute = false;
$bgamingEndpoint = $route;

if ($method === 'POST') {
    $routeCandidates = [$routeLower, $uriPathLower];
    $bgamingMatches = [];
    foreach ($routeCandidates as $candidate) {
        if ($candidate === '') {
            continue;
        }
        if (!preg_match($bgamingPathRegex, $candidate, $m, PREG_OFFSET_CAPTURE)) {
            continue;
        }
        $match = $m[1][0] ?? '';
        $offset = (int) ($m[1][1] ?? -1);
        if ($match === '' || $offset < 0) {
            continue;
        }
        $tail = trim((string) substr($candidate, $offset), '/');
        if ($tail !== '') {
            $bgamingMatches[] = $tail;
        }
    }

    if ($bgamingMatches !== []) {
        usort($bgamingMatches, static function (string $a, string $b): int {
            $depthA = substr_count($a, '/');
            $depthB = substr_count($b, '/');
            if ($depthA !== $depthB) {
                return $depthB <=> $depthA;
            }
            return strlen($b) <=> strlen($a);
        });
        $isBgamingCallbackRoute = true;
        $bgamingEndpoint = $bgamingMatches[0];
    }
}

if ($isBgamingCallbackRoute) {
    $_GET['endpoint'] = $bgamingEndpoint;
    require __DIR__ . '/bgaming_callback.php';
    exit;
}

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
