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

// Bonus tablo sifirlama (internal admin endpoint)
if ($normalizedRoute === 'internal/reset-bonus-claims') {
    // Admin oturumu VEYA shared secret ile erisilebilir
    $authorized = false;
    if (AdminAuth::check()) {
        $authorized = true;
    } else {
        // Shared secret header kontrolu
        $purgeSecret = trim((string) (getenv('FRONTEND_CMS_PURGE_SECRET') ?: ''));
        $provided = trim((string) ($_SERVER['HTTP_X_RESET_SECRET'] ?? ''));
        if ($purgeSecret !== '' && $provided !== '' && hash_equals($purgeSecret, $provided)) {
            $authorized = true;
        }
    }

    if (!$authorized) {
        http_response_code(401);
        echo json_encode(['success' => false, 'code' => 401, 'message' => 'Admin oturumu veya X-Reset-Secret header gerekli.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = AdminDatabase::pdo();

    if ($method === 'GET') {
        $counts = [];
        foreach (['bonus_claim_requests', 'user_active_bonuses', 'promocode_requests'] as $t) {
            try {
                $counts[$t] = (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            } catch (Throwable) {
                $counts[$t] = -1;
            }
        }
        try {
            $counts['users_bonus_balance'] = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE bonus_balance > 0')->fetchColumn();
        } catch (Throwable) {
            $counts['users_bonus_balance'] = -1;
        }

        echo json_encode([
            'success' => true,
            'code' => 200,
            'message' => 'Mevcut bonus tablo durumu',
            'data' => $counts,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
        $confirm = trim((string) ($body['confirm'] ?? ''));

        if ($confirm !== 'RESET_ALL_BONUS_CLAIMS') {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'code' => 422,
                'message' => 'Onay kodu gerekli. Body: {"confirm": "RESET_ALL_BONUS_CLAIMS"}',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $pdo->beginTransaction();
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            $pdo->exec('TRUNCATE TABLE bonus_claim_requests');
            $pdo->exec('TRUNCATE TABLE user_active_bonuses');
            $pdo->exec('TRUNCATE TABLE promocode_requests');
            $updated = $pdo->exec("UPDATE users SET bonus_balance = 0, active_wallet_mode = 'main' WHERE bonus_balance > 0 OR active_wallet_mode = 'bonus'");
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            $pdo->commit();

            AdminAuth::writeLog(AdminAuth::userName(), 'reset_bonus_claims', 'system', 'success');
            echo json_encode([
                'success' => true,
                'code' => 200,
                'message' => 'Tum bonus talepleri sifirlandi.',
                'data' => [
                    'tables_cleared' => ['bonus_claim_requests', 'user_active_bonuses', 'promocode_requests'],
                    'users_updated' => $updated,
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'code' => 500,
                'message' => 'Hata: ' . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'code' => 405, 'message' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Pending/failed/rejected transaction cleanup (internal admin endpoint)
if ($normalizedRoute === 'internal/reset-pending-transactions') {
    $authorized = false;
    if (AdminAuth::check()) {
        $authorized = true;
    } else {
        $purgeSecret = trim((string) (getenv('FRONTEND_CMS_PURGE_SECRET') ?: ''));
        $provided = trim((string) ($_SERVER['HTTP_X_RESET_SECRET'] ?? ''));
        if ($purgeSecret !== '' && $provided !== '' && hash_equals($purgeSecret, $provided)) {
            $authorized = true;
        }
    }

    if (!$authorized) {
        http_response_code(401);
        echo json_encode(['success' => false, 'code' => 401, 'message' => 'Admin oturumu veya X-Reset-Secret header gerekli.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = AdminDatabase::pdo();

    if ($method === 'GET') {
        $counts = [];
        $targetStatuses = "'pending','failed','rejected'";
        $counts['deposit_pending'] = (int) $pdo->query("SELECT COUNT(*) FROM megapayz_transactions WHERE type='deposit' AND status IN ({$targetStatuses})")->fetchColumn();
        $counts['withdraw_pending'] = (int) $pdo->query("SELECT COUNT(*) FROM megapayz_transactions WHERE type='withdraw' AND status IN ({$targetStatuses})")->fetchColumn();
        $counts['callbacks'] = (int) $pdo->query("SELECT COUNT(*) FROM megapayz_callbacks")->fetchColumn();
        $counts['total_remaining'] = (int) $pdo->query("SELECT COUNT(*) FROM megapayz_transactions WHERE status NOT IN ({$targetStatuses})")->fetchColumn();

        echo json_encode([
            'success' => true,
            'code' => 200,
            'message' => 'Mevcut bekleyen/basarisiz islem durumu.',
            'data' => $counts,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
        $confirm = trim((string) ($body['confirm'] ?? ''));

        if ($confirm !== 'RESET_ALL_PENDING_TX') {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'code' => 422,
                'message' => 'Onay kodu gerekli. Body: {"confirm": "RESET_ALL_PENDING_TX"}',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $pdo->beginTransaction();
            $targetStatuses = "'pending','failed','rejected'";
            $deletedTx = $pdo->exec("DELETE FROM megapayz_transactions WHERE status IN ({$targetStatuses})");
            $deletedCallbacks = $pdo->exec('DELETE FROM megapayz_callbacks');
            $pdo->commit();

            // ALTER TABLE causes implicit commit in MySQL — must run outside transaction
            // Renumber remaining approved rows to start from 1
            $pdo->exec("SET @new_id = 0");
            $pdo->exec("UPDATE megapayz_transactions SET id = (@new_id := @new_id + 1) ORDER BY id");
            $maxId = (int) $pdo->query("SELECT COALESCE(MAX(id), 0) FROM megapayz_transactions")->fetchColumn();
            $pdo->exec("ALTER TABLE megapayz_transactions AUTO_INCREMENT = " . ($maxId + 1));
            $pdo->exec('ALTER TABLE megapayz_callbacks AUTO_INCREMENT = 1');

            AdminAuth::writeLog(AdminAuth::userName(), 'reset_pending_transactions', 'system', 'success');
            echo json_encode([
                'success' => true,
                'code' => 200,
                'message' => 'Tum bekleyen ve basarisiz islemler temizlendi, IDler sifirlandi.',
                'data' => [
                    'deleted_transactions' => $deletedTx,
                    'deleted_callbacks' => $deletedCallbacks,
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'code' => 500,
                'message' => 'Hata: ' . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'code' => 405, 'message' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Provider callbacks can arrive via /api/v2/sportsbook-wallet aliases.
// Handle them here before member/admin route module dispatch to avoid 404.
if ($method === 'POST' && in_array($route, ['sportsbook-wallet', 'sportsbook_wallet', 'sportsbook-wallet.php', 'sportsbook_callback', 'sportsbook-callback'], true)) {
    require __DIR__ . '/sportsbook_callback.php';
    exit;
}

$megaPayzRoute = strtolower(trim((string) $route, '/'));
if ($method === 'POST' && in_array($megaPayzRoute, ['megapayz-callback', 'megapayz/deposit'], true)) {
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

    $megaResult = MegaPayzService::handleUnifiedCallback(AdminDatabase::pdo(), $payload['body'] ?? []);
    $megaCode = (int) ($megaResult['code'] ?? 200);
    if ($megaCode === 99999 || empty($megaResult['status'])) {
        http_response_code(in_array($megaCode, [400, 403, 404, 405, 422], true) ? $megaCode : 422);
    } elseif ($megaCode >= 400 && $megaCode < 600) {
        http_response_code($megaCode);
    }

    echo json_encode($megaResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
