<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Response;

final class PublicMemberApiDispatcher
{
    /**
     * Browser-facing member/content API routes only. Provider callbacks and
     * admin sync actions must stay on backend-only routes.
     *
     * @var array<string, true>
     */
    private const ALLOWED_ROUTES = [
        'account-freeze' => true,
        'account-unfreeze' => true,
        'account_freeze.php' => true,
        'account_unfreeze.php' => true,
        'account/detail' => true,
        'account/password' => true,
        'account/password-update' => true,
        'account/profile' => true,
        'account/two-factor' => true,
        'account/update' => true,
        'account/balance' => true,
        'active-bonus' => true,
        'active_bonus.php' => true,
        'announcements' => true,
        'announcements.php' => true,
        'auth/forgot-password' => true,
        'auth/login' => true,
        'auth/logout' => true,
        'auth/password-reset' => true,
        'auth/refresh' => true,
        'auth/register' => true,
        'auth/reset-password' => true,
        'auth/session' => true,
        'auth/email-verification' => true,
        'auth/email/verify' => true,
        'auth/verify-email' => true,
        'auth/verify-phone' => true,
        'auth/2fa/enable' => true,
        'auth/2fa/verify' => true,
        'balance' => true,
        'balance.php' => true,
        'bonuses' => true,
        'bonuses/active' => true,
        'bonuses/history' => true,
        'bonuses/wagering-progress' => true,
        'bonus-claim' => true,
        'bonus-claims-me' => true,
        'bonus/use-code' => true,
        'bonus_claim.php' => true,
        'bonus_claims_me.php' => true,
        'bonus_use_code.php' => true,
        'call-me-request' => true,
        'casino_game_history.php' => true,
        'casino/categories' => true,
        'casino/favorite-games' => true,
        'casino/games' => true,
        'casino/games/search' => true,
        'casino/providers' => true,
        'casino/recent-games' => true,
        'config' => true,
        'countries' => true,
        'content/footer' => true,
        'content/footer.php' => true,
        'content/footer-pages' => true,
        'content/footer-pages.php' => true,
        'content/homepage-sections' => true,
        'content/homepage-sections.php' => true,
        'content/mobile-menu' => true,
        'content/mobile-menu.php' => true,
        'content/promotions' => true,
        'content/promotions.php' => true,
        'content/auth-sliders' => true,
        'content/auth-sliders.php' => true,
        'auth_sliders.php' => true,
        'content/sliders' => true,
        'content/sliders.php' => true,
        'deposit-history' => true,
        'deposit-payment' => true,
        'deposit_history.php' => true,
        'deposit_payment.php' => true,
        'deposits' => true,
        'email_verification.php' => true,
        'favorite-live-casino' => true,
        'favorite-slots' => true,
        'favorite_live_casino.php' => true,
        'favorite_slots.php' => true,
        'footer.php' => true,
        'footer' => true,
        'freespins.php' => true,
        'me/freespins' => true,
        'profile/freespins' => true,
        'footer_pages.php' => true,
        'game-history' => true,
        'game-launch' => true,
        'game_history.php' => true,
        'game_launch.php' => true,
        'games-provider' => true,
        'games' => true,
        'games.php' => true,
        'games_provider.php' => true,
        'homepage_sections.php' => true,
        'kyc/status' => true,
        'kyc/documents' => true,
        'kyc/address-verification' => true,
        'kyc/source-of-funds' => true,
        'currencies' => true,
        'languages' => true,
        'live-casino/providers' => true,
        'live-casino/tables' => true,
        'loyalty' => true,
        'loyalty.php' => true,
        'loyalty/me' => true,
        'loyalty/levels' => true,
        'maintenance-status' => true,
        'me' => true,
        'me/limits' => true,
        'me/preferences' => true,
        'me/security-sessions' => true,
        'member-inbox-messages' => true,
        'member_inbox_messages.php' => true,
        'mobile-menu.php' => true,
        'notifications' => true,
        'notifications/read-all' => true,
        'notifications/settings' => true,
        'password-update' => true,
        'password_update.php' => true,
        'payments/methods' => true,
        'payment-methods' => true,
        'payment.php' => true,
        'payment/methods' => true,
        'payment_methods.php' => true,
        'profile/casino-game-history' => true,
        'profile/casino_game_history.php' => true,
        'profile-detail' => true,
        'profile/detail' => true,
        'profile/game-history-detail' => true,
        'profile/game_history_detail.php' => true,
        'profile/spor-bet-detail' => true,
        'profile/spor_bet_detail.php' => true,
        'profile/update' => true,
        'profile_detail.php' => true,
        'profile_update.php' => true,
        'promotions' => true,
        'promocode-request' => true,
        'promocode_request.php' => true,
        'promocodes' => true,
        'promocodes.php' => true,
        'referrals' => true,
        'referrals.php' => true,
        'responsible-gaming/activity' => true,
        'responsible-gaming/cool-off' => true,
        'responsible-gaming/limits' => true,
        'responsible-gaming/self-exclusion' => true,
        'site-settings' => true,
        'site_settings.php' => true,
        'sliders.php' => true,
        'sportsbook-launch' => true,
        'sportsbook/launch' => true,
        'sportsbook/history' => true,
        'sportsbook_launch.php' => true,
        'sportsbook_history.php' => true,
        'support/live-chat/token' => true,
        'support/tickets' => true,
        'track-visit' => true,
        'track_visit.php' => true,
        'two-factor' => true,
        'two_factor.php' => true,
        'user/password' => true,
        'user/profile' => true,
        'user/update' => true,
        'winners' => true,
        'winners.php' => true,
        'history/deposits' => true,
        'history/withdrawals' => true,
        'bonus/claims/me' => true,
        'affiliate/summary' => true,
        'affiliate.php' => true,
        'games/recently-played' => true,
        'games/recently-played.php' => true,
        'games/search' => true,
        'notifications/count' => true,
        'notifications/unread-count' => true,
        'loyalty/history' => true,
        'loyalty/points-history' => true,
        'loyalty/redeem' => true,
        'loyalty/redeem-points' => true,
        'withdraw-history' => true,
        'withdraw-payment' => true,
        'withdraw_history.php' => true,
        'withdraw_payment.php' => true,
        'withdrawals' => true,
        'wallet/balance' => true,
        'wallet/summary' => true,
        'wallet/transactions' => true,
        'wallet/transfer' => true,
    ];

    /**
     * Dynamic browser-facing routes with path parameters.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTE_PATTERNS = [
        '~^bets/[^/]+$~',
        '~^bets/[^/]+/(cashout|cancel)$~',
        '~^bonuses/[^/]+/(claim|cancel)$~',
        '~^casino/favorite-games/[^/]+$~',
        '~^casino/games/[^/]+$~',
        '~^casino/games/[^/]+/launch$~',
        '~^deposits/[^/]+$~',
        '~^events/[^/]+$~',
        '~^events/[^/]+/markets$~',
        '~^leagues/[^/]+$~',
        '~^live-casino/tables/[^/]+$~',
        '~^live-casino/tables/[^/]+/launch$~',
        '~^me/security-sessions/[^/]+$~',
        '~^notifications/[^/]+/read$~',
        '~^pages/[^/]+$~',
        '~^sports/[^/]+/countries$~',
        '~^support/tickets/[^/]+$~',
        '~^support/tickets/[^/]+/messages$~',
        '~^support/tickets/[^/]+/(close|reopen)$~',
        '~^login\\.php$~',
        '~^register\\.php$~',
        '~^session\\.php$~',
        '~^logout\\.php$~',
        '~^balance\\.php$~',
        '~^profile_detail\\.php$~',
        '~^profile_update\\.php$~',
        '~^wallet/transactions/[^/]+$~',
        '~^withdrawals/[^/]+$~',
        '~^withdrawals/[^/]+/cancel$~',
    ];

    public static function dispatch(string $route): void
    {
        $route = trim($route, '/');
        if ($route === '' || !self::isAllowed($route)) {
            Response::json([
                'success' => false,
                'ok' => false,
                'code' => 404,
                'message' => 'Public API route not found.',
            ], 404);
            return;
        }

        $_GET['route'] = $route;
        if (self::shouldUseAdminRouteModules()) {
            require dirname(__DIR__, 3) . '/admin/api/v2/member_local.php';
            return;
        }

        Response::json([
            'success' => false,
            'ok' => false,
            'code' => 503,
            'message' => 'Member API route modules are unavailable on this host.',
            'meta' => [
                'hint' => 'Deploy admin/api/v2 route files or unset MEMBER_API_USE_ROUTE_MODULES=0.',
            ],
        ], 503);
    }

    public static function isAllowed(string $route): bool
    {
        if (isset(self::ALLOWED_ROUTES[$route])) {
            return true;
        }
        foreach (self::ALLOWED_ROUTE_PATTERNS as $pattern) {
            if (preg_match($pattern, $route) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Monorepo: admin/api/v2 route modülleri — tek kaynak (varsayılan açık).
     * Kapatmak için: MEMBER_API_USE_ROUTE_MODULES=0
     */
    private static function shouldUseAdminRouteModules(): bool
    {
        $base = dirname(__DIR__, 3);
        $entry = $base . '/admin/api/v2/member_local.php';
        $kernel = $base . '/admin/api/v2/includes/member_api_kernel.php';
        $loader = $base . '/admin/api/v2/includes/member_route_loader.php';

        if (!is_file($entry) || !is_file($kernel) || !is_file($loader)) {
            return false;
        }

        $flag = getenv('MEMBER_API_USE_ROUTE_MODULES');
        if ($flag !== false && in_array(strtolower(trim((string) $flag)), ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return true;
    }
}
