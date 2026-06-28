<?php

declare(strict_types=1);

require_once SERVICE_PATH . '/PublicApiV2Dispatcher.php';

/**
 * Site kökü /api/v2/* isteklerini public API boundary üzerinden yürütür.
 */
class ApiMemberV2BridgeController
{
    private function forward(string $route): void
    {
        PublicApiV2Dispatcher::dispatch($route);
    }

    private function forwardProfilePartial(string $file): void
    {
        $path = BASE_PATH . '/pages/profile/' . $file;
        if (!is_file($path)) {
            http_response_code(404);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<div class="alert alert-danger">Detay endpointi bulunamadı.</div>';
            return;
        }

        header('Content-Type: text/html; charset=UTF-8');
        $previousCwd = getcwd();
        chdir(BASE_PATH . '/pages/profile');
        try {
            require $path;
        } finally {
            if (is_string($previousCwd) && $previousCwd !== '') {
                chdir($previousCwd);
            }
        }
    }

    public function profileDetail(): void
    {
        $this->forward('profile/detail');
    }

    public function authLogin(): void
    {
        $this->forward('auth/login');
    }

    public function authRegister(): void
    {
        $this->forward('auth/register');
    }

    public function authSession(): void
    {
        $this->forward('auth/session');
    }

    public function authLogout(): void
    {
        $this->forward('auth/logout');
    }

    public function authForgotPassword(): void
    {
        $this->forward('auth/forgot-password');
    }

    public function authResetPassword(): void
    {
        $this->forward('auth/reset-password');
    }

    public function authPasswordReset(): void
    {
        $this->forward('auth/password-reset');
    }

    public function authEmailVerification(): void
    {
        $this->forward('email_verification.php');
    }

    public function profileUpdate(): void
    {
        $this->forward('profile/update');
    }

    public function balance(): void
    {
        $this->forward('balance.php');
    }

    public function loyalty(): void
    {
        $this->forward('loyalty.php');
    }

    public function loyaltyLevels(): void
    {
        $this->forward('loyalty/levels');
    }

    public function activeBonus(): void
    {
        $this->forward('active_bonus.php');
    }

    public function bonusClaim(): void
    {
        $this->forward('bonus_claim.php');
    }

    public function bonusUseCode(): void
    {
        $this->forward('bonus_use_code.php');
    }

    public function passwordUpdate(): void
    {
        $this->forward('password_update.php');
    }

    public function twoFactor(): void
    {
        $this->forward('two_factor.php');
    }

    public function bonusClaimsMe(): void
    {
        $this->forward('bonus_claims_me.php');
    }

    public function withdrawPayment(): void
    {
        $this->forward('withdraw_payment.php');
    }

    public function paymentMethods(): void
    {
        $this->forward('payment_methods.php');
    }

    public function depositPayment(): void
    {
        $this->forward('deposit_payment.php');
    }

    public function depositHistory(): void
    {
        $this->forward('deposit_history.php');
    }

    public function withdrawHistory(): void
    {
        $this->forward('withdraw_history.php');
    }

    public function promocodes(): void
    {
        $this->forward('promocodes.php');
    }

    public function referrals(): void
    {
        $this->forward('referrals.php');
    }

    public function promocodeRequest(): void
    {
        $this->forward('promocode_request.php');
    }

    public function accountFreeze(): void
    {
        $this->forward('account_freeze.php');
    }

    public function favoriteSlots(): void
    {
        $this->forward('favorite_slots.php');
    }

    public function favoriteLiveCasino(): void
    {
        $this->forward('favorite_live_casino.php');
    }

    public function gameLaunch(): void
    {
        $this->forward('game_launch.php');
    }

    public function games(): void
    {
        $this->forward('games.php');
    }

    public function gameHistory(): void
    {
        $this->forward('game_history.php');
    }

    public function casinoGameHistory(): void
    {
        $this->forward('casino_game_history.php');
    }

    public function profileSporBetDetail(): void
    {
        $this->forwardProfilePartial('get_spor_bet_details.php');
    }

    public function profileGameHistoryDetail(): void
    {
        $this->forwardProfilePartial('get_game_history_details.php');
    }

    public function winners(): void
    {
        $this->forward('winners.php');
    }

    public function trackVisit(): void
    {
        $this->forward('track_visit.php');
    }

    public function siteSettings(): void
    {
        $this->forward('site_settings.php');
    }

    public function announcements(): void
    {
        $this->forward('announcements.php');
    }

    public function memberInboxMessages(): void
    {
        $this->forward('member_inbox_messages.php');
    }

    public function gamesProvider(): void
    {
        $this->forward('games_provider.php');
    }

    public function sportsLaunch(): void
    {
        $this->forward('sports_launch.php');
    }

    public function payment(): void
    {
        $this->forward('payment.php');
    }

    public function accountUnfreeze(): void
    {
        $this->forward('account_unfreeze.php');
    }

    public function callMeRequest(): void
    {
        $this->forward('call-me-request');
    }

    public function promotions(): void
    {
        $this->forward('content/promotions');
    }

    public function sliders(): void
    {
        $this->forward('content/sliders');
    }

    public function footer(): void
    {
        $this->forward('content/footer');
    }

    public function footerPages(): void
    {
        $this->forward('content/footer-pages');
    }

    public function homepageSections(): void
    {
        $this->forward('content/homepage-sections');
    }

    public function mobileMenu(): void
    {
        $this->forward('content/mobile-menu');
    }

    public function sports(): void
    {
        $this->forward('sports.php');
    }

    public function sportsEvents(): void
    {
        $this->forward('sports_events.php');
    }

    public function sportsLeagues(): void
    {
        $this->forward('sports_leagues.php');
    }

    public function sportsMarkets(): void
    {
        $this->forward('sports_markets.php');
    }
}
