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
        frontend_session_start();
    }
}

$csrfKey = (string) (getenv('CSRF_TOKEN_KEY') ?: (defined('SITE_CSRF_KEY') ? SITE_CSRF_KEY : 'site_csrf_token'));
if (empty($_SESSION[$csrfKey]) || !is_string($_SESSION[$csrfKey])) {
    $_SESSION[$csrfKey] = isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token'])
        ? $_SESSION['csrf_token']
        : bin2hex(random_bytes(32));
}
$_SESSION['csrf_token'] = $_SESSION[$csrfKey];

if (!function_exists('frontend_member_logged_in') && is_readable(BASE_PATH . '/config/member_api_public.php')) {
    require_once BASE_PATH . '/config/member_api_public.php';
}
if (function_exists('frontend_sanitize_member_session')) {
    frontend_sanitize_member_session();
}
$loggedIn = function_exists('frontend_member_logged_in')
    ? frontend_member_logged_in()
    : (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true);
$headerInitialBalance = 0.0;
$headerLoyaltyBadge = [
    'name' => 'Bronze',
    'code' => 'bronze',
    'icon_url' => '/assets/images/loyalty/badges/bronze.svg',
    'initial' => 'B',
    'points' => 0,
    'redeemable_points' => 0,
    'progress_percent' => 0,
];
if ($loggedIn && !(defined('SPORTSBOOK_LIGHTWEIGHT_LAYOUT') && SPORTSBOOK_LIGHTWEIGHT_LAYOUT)) {
    // SSR balance + loyalty backend'e 2 seri HTTP atıyordu (15s timeout x2 → 30s+).
    // Client poll zaten güncelliyor; header için kısa timeout + 60sn session cache.
    $headerMemberUid = (int) ($_SESSION['user_id'] ?? 0);
    $headerMemberNow = time();
    $headerMemberCache = $_SESSION['__header_member_cache'] ?? null;
    $headerMemberCacheValid = is_array($headerMemberCache)
        && (int) ($headerMemberCache['uid'] ?? 0) === $headerMemberUid
        && ($headerMemberNow - (int) ($headerMemberCache['ts'] ?? 0)) < 60;

    if ($headerMemberCacheValid) {
        $headerInitialBalance = (float) ($headerMemberCache['balance'] ?? 0.0);
        if (is_array($headerMemberCache['badge'] ?? null)) {
            $headerLoyaltyBadge = $headerMemberCache['badge'];
        }
    } else {
        $prevBadge = is_array($headerMemberCache['badge'] ?? null) ? $headerMemberCache['badge'] : $headerLoyaltyBadge;
        $prevBalance = is_numeric($headerMemberCache['balance'] ?? null)
            ? (float) $headerMemberCache['balance']
            : 0.0;

        if (!class_exists('MemberViewDataService', false)) {
            require_once BASE_PATH . '/services/MemberViewDataService.php';
        }
        // Hard cap so a hung admin API cannot exhaust php-fpm workers.
        $headerInitialBalance = MemberViewDataService::balanceForSession(2);
        if ($headerInitialBalance <= 0.0 && $prevBalance > 0.0) {
            $headerInitialBalance = $prevBalance;
        }

        if (!class_exists('ApiLoyalty', false)) {
            require_once BASE_PATH . '/api/bootstrap.php';
        }
        if (class_exists('ApiLoyalty')) {
            $headerLoyaltyBadge = ApiLoyalty::publicBadgeForUser($headerMemberUid, 2);
            // Keep last known badge if API timed out and returned Bronze default.
            if (
                (string) ($headerLoyaltyBadge['code'] ?? '') === 'bronze'
                && (int) ($headerLoyaltyBadge['points'] ?? 0) === 0
                && (string) ($prevBadge['code'] ?? '') !== ''
                && (
                    (string) ($prevBadge['code'] ?? '') !== 'bronze'
                    || (int) ($prevBadge['points'] ?? 0) > 0
                )
            ) {
                $headerLoyaltyBadge = $prevBadge;
            }
        }
        $_SESSION['__header_member_cache'] = [
            'uid' => $headerMemberUid,
            'ts' => $headerMemberNow,
            'balance' => $headerInitialBalance,
            'badge' => $headerLoyaltyBadge,
        ];
    }
}
