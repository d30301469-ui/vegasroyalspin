<?php



/**

 * Ortak HTTP / zarf yardımcıları. Tek include: require_once API_PATH . '/bootstrap.php';

 * (tercihen config/app.php önce yüklensin: BASE_PATH, API_PATH; BackendApiClient yüklenmiş olabilir.)

 *

 * Yalnızca çekirdek dosyalar doğrudan yüklenir; modül sınıfları ilk kullanımda autoload ile gelir.

 */

if (!defined('BASE_PATH')) {

    define('BASE_PATH', dirname(__DIR__));

}

if (!defined('API_PATH')) {

    define('API_PATH', __DIR__);

}



if (!class_exists(BackendApiClient::class, false)) {

    require_once BASE_PATH . '/services/BackendApiClient.php';

}



require_once __DIR__ . '/Envelope.php';

require_once __DIR__ . '/Bases.php';

require_once __DIR__ . '/Paths.php';

require_once __DIR__ . '/ListQuery.php';

require_once __DIR__ . '/MemberApi.php';

require_once __DIR__ . '/Client.php';

require_once __DIR__ . '/functions.php';



spl_autoload_register(static function (string $class): void {

    static $map = [

        'ApiAnnouncements'           => 'MemberAnnouncements.php',

        'ApiCallMeRequest'           => 'CallMeRequest.php',

        'ApiDepositHistory'          => 'DepositHistory.php',

        'ApiGameHistory'             => 'GameHistory.php',

        'ApiGamesProvider'           => 'GamesProvider.php',

        'ApiGameLaunch'            => 'GameLaunch.php',

        'ApiMemberActiveBonus'     => 'MemberActiveBonus.php',

        'ApiMemberBalance'         => 'MemberBalance.php',

        'ApiMemberBonusClaims'     => 'MemberBonusClaims.php',

        'ApiMemberDepositPayment'  => 'MemberDepositPayment.php',

        'ApiMemberFavorite'        => 'MemberFavorite.php',

        'ApiMemberInboxMessages'     => 'MemberInboxMessages.php',

        'ApiMemberPromo'           => 'MemberPromo.php',

        'ApiPaymentMethods'          => 'PaymentMethods.php',

        'ApiProfileDetail'           => 'ProfileDetail.php',

        'ApiProfileUpdate'           => 'ProfileUpdate.php',

        'ApiPasswordUpdate'          => 'PasswordUpdate.php',

        'ApiAccountFreeze'           => 'AccountFreeze.php',

        'ApiPromotions'              => 'Promotions.php',

        'ApiSliders'                 => 'Sliders.php',

        'ApiCmsRemote'               => 'CmsRemote.php',

        'ApiAuthSliders'             => 'AuthSliders.php',

        'ApiFooter'                  => 'Footer.php',

        'ApiJackpot'                 => 'Jackpot.php',

        'ApiMobileMenu'              => 'MobileMenu.php',

        'ApiHomepageSections'        => 'HomepageSections.php',

        'ApiFooterPages'             => 'FooterPages.php',

        'ApiSiteSettings'            => 'SiteSettings.php',

        'ApiMediaUrl'                => 'MediaUrl.php',

        'ApiLoyalty'                 => 'Loyalty.php',

        'ApiWinners'                 => 'Winners.php',

        'ApiWithdrawHistory'         => 'WithdrawHistory.php',

        'ApiWithdrawPayment'       => 'WithdrawPayment.php',

    ];



    if (isset($map[$class])) {

        require_once __DIR__ . '/' . $map[$class];

    }

});


