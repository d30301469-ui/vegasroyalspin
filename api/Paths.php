<?php

/**
 * api.md üye uçları — dosya adı (pathAlternatesForBase ile birlikte kullanın).
 * Tam yol base köküne göre ApiMemberApi::pathAlternatesForBase ile üretilir.
 */
final class MemberApiPaths
{
    public const LOGIN             = 'login.php';
    public const REGISTER          = 'register.php';
    public const SESSION           = 'session.php';
    public const LOGOUT            = 'logout.php';
    public const FORGOT_PASSWORD   = 'forgot_password.php';
    public const RESET_PASSWORD    = 'reset_password.php';
    public const PASSWORD_RESET    = 'password_reset.php';
    public const EMAIL_VERIFICATION = 'email_verification.php';
    public const SLIDERS           = 'sliders.php';
    public const WINNERS           = 'winners.php';
    public const GAMES_PROVIDER    = 'games_provider.php';
    public const GAME_HISTORY      = 'game_history.php';
    public const DEPOSIT_HISTORY   = 'deposit_history.php';
    public const CALL_ME_REQUEST   = 'call_me_request.php';
    public const PROMOTIONS        = 'promotions.php';
    public const PROMOCODES        = 'promocodes.php';
    public const PROMOCODE_REQUEST = 'promocode_request.php';
    public const ANNOUNCEMENTS     = 'announcements.php';
    public const MEMBER_INBOX_MESSAGES = 'member_inbox_messages.php';
    public const BALANCE             = 'balance.php';
    public const LOYALTY             = 'loyalty.php';
    public const ACTIVE_BONUS        = 'active_bonus.php';
    public const FAVORITE_SLOTS       = 'favorite_slots.php';
    public const FAVORITE_LIVE_CASINO = 'favorite_live_casino.php';
    public const PROFILE_DETAIL       = 'profile_detail.php';
    public const PROFILE_UPDATE       = 'profile_update.php';
    public const PASSWORD_UPDATE      = 'password_update.php';
    public const ACCOUNT_FREEZE       = 'account_freeze.php';
    public const BONUS_CLAIMS_ME      = 'bonus_claims_me.php';
    public const PAYMENT_METHODS      = 'payment_methods.php';
    public const DEPOSIT_PAYMENT      = 'deposit_payment.php';
    public const WITHDRAW_PAYMENT     = 'withdraw_payment.php';
    public const WITHDRAW_HISTORY     = 'withdraw_history.php';
    public const GAME_LAUNCH          = 'game_launch.php';
}
