<?php
/**
 * Header öncesi ortam: BASE_PATH, oturum, CSRF.
 * Sadece header.php tarafından dahil edilir.
 */
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
    if (!defined('VIEW_PATH')) {
        define('VIEW_PATH', BASE_PATH . '/views');
    }
    if (file_exists(BASE_PATH . '/core/bootstrap.php')) {
        require_once BASE_PATH . '/core/bootstrap.php';
    }
}

if (session_status() == PHP_SESSION_NONE) {
    if (is_readable(BASE_PATH . '/config/frontend_session.php')) {
        require_once BASE_PATH . '/config/frontend_session.php';
        metropol_frontend_session_start();
    } else {
        session_start();
    }
}

$csrfKey = 'vegasroyalspin_csrf_token';
if (empty($_SESSION[$csrfKey]) || !is_string($_SESSION[$csrfKey])) {
    $_SESSION[$csrfKey] = isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token'])
        ? $_SESSION['csrf_token']
        : bin2hex(random_bytes(32));
}
$_SESSION['csrf_token'] = $_SESSION[$csrfKey];

if (!function_exists('metropol_frontend_member_logged_in') && is_readable(BASE_PATH . '/config/member_api_public.php')) {
    require_once BASE_PATH . '/config/member_api_public.php';
}
if (function_exists('metropol_frontend_sanitize_member_session')) {
    metropol_frontend_sanitize_member_session();
}
$loggedIn = function_exists('metropol_frontend_member_logged_in')
    ? metropol_frontend_member_logged_in()
    : (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true);
$headerInitialBalance = 0.0;
$headerLoyaltyBadge = [
    'name' => 'Bronze',
    'code' => 'bronze',
    'icon_url' => '/content/images/loyalty_points/bronze.png',
    'initial' => 'B',
    'points' => 0,
    'redeemable_points' => 0,
    'progress_percent' => 0,
];
if ($loggedIn) {
    // SSR balance + loyalty backend'e 2 seri HTTP atıyordu. İlk paint dışında bu
    // değerleri client-side poll (5sn) zaten güncelliyor; bu yüzden kısa ömürlü
    // (8sn) session cache ile hızlı sayfa geçişlerinde tekrar eden çağrıları
    // engelliyoruz.
    $headerMemberUid = (int) ($_SESSION['user_id'] ?? 0);
    $headerMemberNow = time();
    $headerMemberCache = $_SESSION['__header_member_cache'] ?? null;
    $headerMemberCacheValid = is_array($headerMemberCache)
        && (int) ($headerMemberCache['uid'] ?? 0) === $headerMemberUid
        && ($headerMemberNow - (int) ($headerMemberCache['ts'] ?? 0)) < 8;

    if ($headerMemberCacheValid) {
        $headerInitialBalance = (float) ($headerMemberCache['balance'] ?? 0.0);
        if (is_array($headerMemberCache['badge'] ?? null)) {
            $headerLoyaltyBadge = $headerMemberCache['badge'];
        }
    } else {
        if (!class_exists('MemberViewDataService', false)) {
            require_once BASE_PATH . '/services/MemberViewDataService.php';
        }
        $headerInitialBalance = MemberViewDataService::balanceForSession();
        if (!class_exists('ApiLoyalty', false)) {
            require_once BASE_PATH . '/api/bootstrap.php';
        }
        if (class_exists('ApiLoyalty')) {
            $headerLoyaltyBadge = ApiLoyalty::publicBadgeForUser($headerMemberUid);
        }
        $_SESSION['__header_member_cache'] = [
            'uid' => $headerMemberUid,
            'ts' => $headerMemberNow,
            'balance' => $headerInitialBalance,
            'badge' => $headerLoyaltyBadge,
        ];
    }
}
