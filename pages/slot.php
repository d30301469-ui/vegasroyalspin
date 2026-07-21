<?php
// En üstte, BOM veya boşluk olmadan session başlat
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/frontend_session.php';
    metropol_frontend_session_start();
}

// Hata raporlama
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../services/SlotGamesQuery.php';
require_once __DIR__ . '/../services/ProviderDisplayBadgeMap.php';

function getAllUniqueProviders()
{
    return SlotGamesQuery::allProviders();
}

// Form verilerini al
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$selectedProviders = isset($_GET['providers']) ? (array)$_GET['providers'] : [];
$currentSort = isset($_GET['sort']) ? trim($_GET['sort']) : '';
$limit = 30;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

$result = SlotGamesQuery::slotsPage($searchTerm, $selectedProviders, $limit, $page, $currentSort);
$games = $result['games'];
$allUniqueProviders = array_values(array_filter(getAllUniqueProviders(), static function (string $provider): bool {
    return stripos($provider, 'bgaming') === false && stripos($provider, 'b gaming') === false;
}));
$slotApiParams = [];
$totalSlots = $result['total'];
$perPage = $result['perPage'];
$currentPage = $result['page'];
$hasNext = $result['hasNext'];
$nextPage = $currentPage + 1;
$loadedCount = ($currentPage - 1) * $perPage + count($games);
$remainingGames = max(0, $totalSlots - $loadedCount);
$showLoadMore = $hasNext && $remainingGames > 0;

// Sağlayıcı badge'leri (EN İYİ, JACKPOT, SICAK vb.) – referans providerListRow
$providerBadges = [
    'pragmatic'     => ['EN İYİ', 'JACKPOT', 'SICAK'],
    'pgsoft'        => ['SICAK'],
    'spribe'        => ['JACKPOT', 'SICAK'],
    'hacksaw'       => ['EN İYİ', 'SICAK'],
    'nolimitcity-A' => ['JACKPOT'],
    'evoplay'       => ['EN İYİ'],
    'play-son'      => [],
    'booming'       => ['JACKPOT'],
    'quickspin'     => ['EN İYİ', 'SICAK'],
];

sort($allUniqueProviders);

$providerBadgeBlockClass = static function (string $badge): string {
    $normalized = mb_strtolower($badge, 'UTF-8');
    if (str_contains($normalized, 'jackpot')) {
        return 'badge-jackpot';
    }
    if (str_contains($normalized, 'sıcak') || str_contains($normalized, 'sicak')) {
        return 'badge-hot';
    }
    if (str_contains($normalized, 'promosyon')) {
        return 'badge-promo';
    }
    if (str_contains($normalized, 'özel') || str_contains($normalized, 'ozel')) {
        return 'badge-exclusive';
    }
    if (str_contains($normalized, 'ortak')) {
        return 'badge-ortak';
    }
    if (str_contains($normalized, 'iyi') || str_contains($normalized, 'top')) {
        return 'badge-top';
    }
    return '';
};

$slotCategoryItems = [
    ['sort' => '', 'slug' => 'all-games1', 'id' => '-1', 'title' => 'Tüm Oyunlar', 'icon' => 'bc-i-all-games1', 'href' => '/slot'],
    ['sort' => 'liked', 'slug' => 'topslots', 'id' => '93', 'title' => 'En Beğenilen Oyunlar', 'icon' => 'bc-i-topslots', 'href' => '/slot?sort=liked'],
    ['sort' => 'popular', 'slug' => 'populargames', 'id' => '95', 'title' => 'Popüler Oyunlar', 'icon' => 'bc-i-populargames', 'href' => '/slot?sort=popular'],
    ['sort' => 'new', 'slug' => 'new', 'id' => '65', 'title' => 'Yeni Oyunlar', 'icon' => 'bc-i-new', 'href' => '/slot?sort=new'],
    ['sort' => 'jackpots', 'slug' => 'jackpots', 'id' => '59', 'title' => 'Jackpotlar', 'icon' => 'bc-i-jackpots', 'href' => '/slot?sort=jackpots'],
    ['sort' => 'bonus-buy', 'slug' => 'buybonus', 'id' => '247', 'title' => 'Bonus Satın Alma Oyunları', 'icon' => 'bc-i-buybonus', 'href' => '/slot?sort=bonus-buy'],
    ['sort' => 'video', 'slug' => 'videoslots', 'id' => '51', 'title' => 'Video Slotları', 'icon' => 'bc-i-videoslots', 'href' => '/slot?sort=video'],
    ['sort' => 'special', 'slug' => 'newyear', 'id' => '619', 'title' => 'Yılbaşı Özel', 'icon' => 'bc-i-newyear', 'href' => '/slot?sort=special'],
    ['sort' => 'crash', 'slug' => 'crashgames', 'id' => '406', 'title' => 'Uçak Oyunları', 'icon' => 'bc-i-crashgames', 'href' => '/slot?sort=crash'],
    ['sort' => 'freespin', 'slug' => 'buyfeature', 'id' => '274', 'title' => 'Free Spin Satın Alma Oyunları', 'icon' => 'bc-i-buyfeature', 'href' => '/slot?sort=freespin'],
    ['sort' => 'instant', 'slug' => 'instantwin', 'id' => '46', 'title' => 'Anında Kazanç', 'icon' => 'bc-i-instantwin', 'href' => '/slot?sort=instant'],
    ['sort' => 'table', 'slug' => 'tablegames', 'id' => '94', 'title' => 'Masa Oyunları', 'icon' => 'bc-i-tablegames', 'href' => '/slot?sort=table'],
    ['sort' => 'slots', 'slug' => 'slots', 'id' => '57', 'title' => 'Slots', 'icon' => 'bc-i-slots', 'href' => '/slot?sort=slots'],
];

$renderProviderBtn = function ($provider) use ($providerBadges, $selectedProviders, $providerBadgeBlockClass) {
    $badgeSlug = ProviderDisplayBadgeMap::slugForDisplay($provider);
    $badges = $badgeSlug !== null ? array_slice($providerBadges[$badgeSlug] ?? [], 0, 1) : [];
    $badge = $badges[0] ?? '';
    $active = in_array($provider, $selectedProviders, true);
    $esc = htmlspecialchars($provider, ENT_QUOTES, 'UTF-8');
    $badgeClass = $badge !== '' ? $providerBadgeBlockClass($badge) : '';
    $normalizedKey = preg_replace('/[^a-z0-9]+/i', '', mb_strtolower((string) $provider, 'UTF-8')) ?: '';

    if ($normalizedKey === 'pragmatic' || $normalizedKey === 'pragmaticplay' || $normalizedKey === 'pragmatics') {
        $svgLogo = '<span class="CMSIconSVGWrapper provider-logo-svg provider-logo-svg--pragmaticplay"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 53 24" fill="currentColor"><path d="M43.9141 7.26074C46.1336 7.26074 48.2625 8.14256 49.832 9.71191C51.4016 11.2815 52.2832 13.4111 52.2832 15.6309C52.2831 17.2861 51.7926 18.904 50.873 20.2803C49.9534 21.6566 48.6465 22.7298 47.1172 23.3633C45.5879 23.9967 43.9048 24.1618 42.2812 23.8389C40.6578 23.5159 39.1665 22.7192 37.9961 21.5488C36.8257 20.3784 36.028 18.8871 35.7051 17.2637C35.3821 15.6402 35.5482 13.957 36.1816 12.4277C36.8151 10.8984 37.8883 9.59153 39.2646 8.67188C40.641 7.75227 42.2588 7.26076 43.9141 7.26074ZM46.7793 8.71191C45.4106 8.14504 43.9042 7.99704 42.4512 8.28613C40.9981 8.57526 39.6637 9.28924 38.6162 10.3369C37.5688 11.3845 36.8553 12.7189 36.5664 14.1719C36.2775 15.625 36.426 17.1313 36.9932 18.5C37.5603 19.8685 38.5202 21.0385 39.752 21.8613C40.9839 22.6842 42.4326 23.1233 43.9141 23.123C45.9004 23.1227 47.8055 22.3334 49.21 20.9287C50.6143 19.5241 51.4032 17.619 51.4033 15.6328C51.4033 14.1513 50.9637 12.7025 50.1406 11.4707C49.3176 10.2391 48.1479 9.27878 46.7793 8.71191ZM13.7178 13.6514C14.2551 13.6514 14.7029 13.7838 15.0605 14.0479C15.4181 14.312 15.6702 14.6948 15.7705 15.1279H15.0029C14.9074 14.8882 14.7385 14.6849 14.5205 14.5469C14.2825 14.3979 14.0054 14.3219 13.7246 14.3291C13.4588 14.3248 13.1967 14.3927 12.9668 14.5264C12.7419 14.6596 12.5595 14.8551 12.4424 15.0889C12.3122 15.3561 12.2492 15.6511 12.2578 15.9482C12.2493 16.2556 12.3148 16.5604 12.4492 16.8369C12.5685 17.0751 12.7569 17.2721 12.9893 17.4023C13.2372 17.537 13.5158 17.6057 13.7979 17.6006C14.1519 17.6068 14.4954 17.4805 14.7617 17.2471C15.0296 17.0152 15.1922 16.6969 15.248 16.292H13.5898V15.7549H15.8594V16.4512C15.8115 16.7756 15.6902 17.0849 15.5049 17.3555C15.3168 17.6287 15.0639 17.8519 14.7695 18.0049C14.4465 18.1707 14.0876 18.2537 13.7246 18.2471C13.3173 18.2554 12.915 18.1539 12.5605 17.9531C12.2283 17.7616 11.9578 17.4791 11.7812 17.1387C11.6004 16.7673 11.5069 16.3593 11.5068 15.9463C11.5068 15.5332 11.6004 15.1253 11.7812 14.7539C11.9571 14.4147 12.2266 14.1332 12.5576 13.9424C12.9115 13.7437 13.312 13.643 13.7178 13.6514ZM32.6777 13.6445C33.2312 13.645 33.6918 13.7853 34.0586 14.0664C34.4282 14.3528 34.6895 14.7565 34.7998 15.2109H34.0264C33.9276 14.9479 33.7502 14.7216 33.5186 14.5625C33.2656 14.3967 32.9673 14.3126 32.665 14.3223C32.4114 14.3182 32.1612 14.3874 31.9453 14.5205C31.7297 14.6587 31.5574 14.8547 31.4473 15.0859C31.3274 15.3556 31.2646 15.6473 31.2646 15.9424C31.2647 16.2375 31.3274 16.5292 31.4473 16.7988C31.5572 17.0304 31.7294 17.227 31.9453 17.3652C32.1612 17.4984 32.4114 17.5666 32.665 17.5625C32.9672 17.5723 33.2655 17.4888 33.5186 17.3232C33.749 17.1656 33.9262 16.9413 34.0264 16.6807H34.7998C34.6903 17.1344 34.4287 17.5369 34.0586 17.8213C33.6922 18.1007 33.2317 18.2412 32.6777 18.2412C32.2812 18.2487 31.8905 18.1472 31.5469 17.9492C31.2205 17.7574 30.9562 17.4758 30.7861 17.1377C30.6091 16.7652 30.5166 16.3577 30.5166 15.9453C30.5166 15.533 30.6091 15.1254 30.7861 14.7529C30.9568 14.4145 31.2211 14.1322 31.5469 13.9385C31.8899 13.7388 32.2809 13.637 32.6777 13.6445ZM1.53418 13.6895C2.05422 13.6895 2.44518 13.8133 2.70703 14.0605C2.96888 14.3079 3.10078 14.647 3.10254 15.0771C3.1025 15.5036 2.96854 15.8393 2.7002 16.084C2.43181 16.3287 2.04397 16.4511 1.53711 16.4512H0.728516V18.2031H0V13.6895H1.53418ZM5.33105 13.6895C5.8464 13.6895 6.2382 13.8151 6.50488 14.0664C6.77151 14.3177 6.90474 14.6506 6.9043 15.0645C6.90429 15.4159 6.80458 15.7056 6.60645 15.9336C6.40831 16.1616 6.12295 16.3067 5.75 16.3682L6.93652 18.2031H6.11133L4.98633 16.4004H4.46777V18.2031H3.73926V13.6895H5.33105ZM11.3789 18.2031H10.5986L10.2539 17.2246H8.34863L8.00293 18.2031H7.22363L8.88574 13.7598H9.72266L11.3789 18.2031ZM18.6787 17.3076L20.123 13.7598H21.0059V18.2031H20.2695V14.873L18.9854 18.2031H18.3467L17.0547 14.873V18.2031H16.3262V13.7598H17.208L18.6787 17.3076ZM25.6973 18.2031H24.917L24.5723 17.2246H22.6699L22.3252 18.2031H21.543L23.2041 13.7598H24.042L25.6973 18.2031ZM28.8164 14.2773H27.5576V18.2031H26.8223V14.2773H25.5693V13.6895H28.8164V14.2773ZM29.9355 18.2031H29.207V13.6895H29.9355V18.2031ZM39.0439 13.6895C39.5631 13.6895 39.9541 13.8134 40.2168 14.0605C40.4795 14.3079 40.6114 14.647 40.6123 15.0771C40.6123 15.5036 40.4783 15.8393 40.21 16.084C39.9416 16.3287 39.5538 16.4511 39.0469 16.4512H38.2383V18.2031H37.5098V13.6895H39.0439ZM41.8623 17.6338H43.4229V18.2031H41.1338V13.6895H41.8623V17.6338ZM47.8848 18.2031H47.1045L46.7588 17.2246H44.8545L44.5088 18.2031H43.7285L45.3916 13.7598H46.2285L47.8848 18.2031ZM48.9463 15.7803L49.9814 13.6895H50.8105L49.3086 16.5596V18.2031H48.5742V16.5596L47.0664 13.6895H47.9102L48.9463 15.7803ZM8.55273 16.6621H10.0488L9.30078 14.5537L8.55273 16.6621ZM22.8721 16.6621H24.3682L23.6201 14.5537L22.8721 16.6621ZM45.0586 16.6621H46.5547L45.8066 14.5537L45.0586 16.6621ZM4.46777 15.8818H5.27246C5.86096 15.8817 6.15474 15.6222 6.1543 15.1025C6.15427 14.8558 6.08305 14.6632 5.94043 14.5244C5.79748 14.3861 5.57543 14.3165 5.27246 14.3164H4.46777V15.8818ZM0.728516 15.8564H1.45801C2.0579 15.8564 2.35788 15.5967 2.3584 15.0771C2.3584 14.8216 2.28714 14.6266 2.14453 14.4922C2.00178 14.3578 1.77288 14.2906 1.45801 14.291H0.728516V15.8564ZM38.2383 15.8564H38.9668C39.5691 15.8564 39.87 15.5968 39.8701 15.0771C39.8701 14.8216 39.7989 14.6266 39.6562 14.4922C39.5135 14.3577 39.2842 14.2906 38.9688 14.291H38.2383V15.8564ZM45.0107 9.35059C47.7786 9.17859 49.2606 12.5422 46.4512 12.7168C43.5793 12.8952 42.413 9.51204 45.0107 9.35059ZM39.1494 0C40.7713 1.98166 42.0205 5.22363 42.0205 5.22363C42.0205 5.22363 41.6752 2.91986 43.6621 1.41797C42.6143 3.59667 43.1927 5.84608 43.3555 6.37988C41.9109 6.42997 40.5044 6.85602 39.2744 7.61523C39.0912 7.04191 38.2905 4.90402 36.2266 3.71777C38.6996 3.83459 39.7182 5.90553 39.7305 5.93066C39.721 5.89057 38.9319 2.53577 39.1494 0Z"></path></svg></span>';
    } elseif ($normalizedKey === 'playson') {
        $svgLogo = '<span class="CMSIconSVGWrapper provider-logo-svg provider-logo-svg--playson"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 67 14" fill="currentColor"><path d="m10.07,6.57c-1.02-.52-2-1.02-2.98-1.51-1.58-.79-3.16-1.58-4.74-2.36-.49-.24-.5-.23-.5.32,0,3.04,0,6.08,0,9.13,0,.65-.26.98-.79,1.05-.61.08-1.06-.31-1.06-.96,0-1.26,0-2.52,0-3.78,0-2.43,0-4.86,0-7.28C0,.12.64-.27,1.55.19c3.56,1.78,7.13,3.56,10.69,5.34.14.07.28.15.42.22.34.17.53.45.54.83,0,.39-.18.7-.54.88-2.55,1.28-5.09,2.56-7.65,3.81-.75.37-1.43-.07-1.43-.89,0-1.23,0-2.46,0-3.69,0-.41.14-.73.53-.92.35-.17.67-.11.98.09.3.2.36.5.36.83,0,.63,0,1.26,0,1.89,0,.25.05.36.32.22,1.36-.69,2.73-1.37,4.09-2.06.07-.03.12-.09.22-.17Zm37.45-2.89c-1.48-.11-2.06.54-1.97,1.97.06,1.02.52,1.53,1.56,1.53.98,0,1.95.02,2.93,0,.48-.01.58.19.58.62,0,.42-.12.6-.57.59-1.2-.02-2.39,0-3.59,0-.45,0-.74.22-.74.55,0,.34.29.59.71.59,1.31,0,2.62,0,3.92,0,.5,0,.91-.2,1.15-.62.39-.67.36-1.4.08-2.09-.27-.66-.88-.8-1.53-.8-.95,0-1.89-.02-2.84.01-.44.02-.53-.15-.52-.55.01-.37,0-.63.51-.62,1.2.03,2.39.01,3.59,0,.47,0,.75-.23.74-.59,0-.37-.27-.57-.76-.57-.63,0-1.26,0-1.89,0-.46,0-.92.03-1.37,0Zm8,0c-.3,0-.6,0-.9,0-.73.03-1.31.62-1.31,1.34,0,1.04,0,2.08,0,3.12,0,.78.56,1.35,1.34,1.36,1.15.02,2.3.02,3.45,0,.81-.01,1.38-.57,1.4-1.36.02-1.02.02-2.05,0-3.07-.02-.81-.63-1.38-1.43-1.39-.55,0-1.1,0-1.65,0-.3,0-.6,0-.9,0Zm2.38,1.16c.31,0,.46.09.46.43-.01.88-.01,1.76,0,2.64,0,.36-.16.46-.49.46-.99-.01-1.98-.01-2.97,0-.32,0-.45-.11-.44-.44.01-.9.01-1.79,0-2.69,0-.31.12-.42.42-.41.5.01,1.01,0,1.51,0,.5,0,1.01,0,1.51,0Zm4.7,1.04c1.2,1.11,2.41,2.22,3.61,3.33.23.22.48.39.8.24.3-.14.35-.41.34-.72-.01-.93,0-1.86,0-2.79,0-.55.01-1.1,0-1.65-.01-.35-.2-.61-.58-.61-.39,0-.56.24-.56.61,0,.17,0,.35,0,.52,0,.9,0,1.81,0,2.8-.14-.12-.21-.18-.28-.24-1.23-1.13-2.45-2.26-3.67-3.4-.24-.22-.48-.39-.8-.24-.28.14-.35.42-.34.72,0,1.06,0,2.11,0,3.17,0,.41,0,.82,0,1.23,0,.38.19.65.59.65.4,0,.58-.25.57-.66-.01-.38,0-.76,0-1.13,0-.67,0-1.34,0-2.11.17.15.25.21.33.28Zm-45.1.72c0,.77,0,1.54,0,2.32,0,.36.19.58.54.59.36.01.59-.2.61-.58.01-.31,0-.63,0-.95,0-.45.11-.42.42-.42.93,0,1.86.02,2.79,0,.84,0,1.36-.49,1.43-1.32.03-.39.03-.79,0-1.18-.06-.88-.6-1.38-1.47-1.38-1.2,0-2.4,0-3.59,0-.55,0-.72.17-.73.74,0,.73,0,1.45,0,2.18h0Zm1.15-1.75c.44,0,1.27,0,1.71,0,.47,0,.94,0,1.42,0,.25,0,.36.1.37.37.07,1.16.07,1.16-1.06,1.16-.68,0-1.74,0-2.42,0m16.3-.52c.63,1.09,1.26,2.18,1.89,3.27.22.37.57.5.87.3.32-.21.37-.49.18-.82-.89-1.52-1.77-3.05-2.67-4.56-.31-.53-.78-.5-1.1.03-.28.47-.56.95-.84,1.42-.59,1.02-1.18,2.04-1.78,3.06-.2.34-.18.63.15.86.3.2.63.08.86-.29.03-.05.06-.11.09-.17.67-1.15,1.34-2.3,2.06-3.52.12.19.2.3.27.42Zm5.47,3.13c.04.33.24.51.57.51.33,0,.53-.18.57-.51.02-.27.04-.54.01-.8-.05-.43.11-.74.4-1.05.71-.77,1.39-1.58,2.08-2.38.33-.38.33-.68.03-.94-.27-.24-.58-.18-.89.17-.64.72-1.28,1.43-1.89,2.17-.24.29-.37.28-.6,0-.61-.72-1.23-1.43-1.86-2.14-.13-.15-.25-.33-.48-.35-.24-.02-.43.05-.56.27-.16.29-.1.54.11.78.66.76,1.31,1.53,1.99,2.27.35.39.66.76.52,1.21,0,.34-.02.56,0,.78Zm-15.59-.28c0,.63.16.79.77.8,1.24,0,2.49,0,3.73,0,.48,0,.71-.19.71-.56,0-.38-.21-.57-.69-.58-.94,0-1.89-.02-2.83,0-.41.01-.57-.07-.55-.53.04-1.12.01-2.23.01-3.35,0-.53-.19-.81-.56-.82-.39-.01-.58.26-.58.84,0,.69,0,1.38,0,2.08,0,.71,0,1.42,0,2.12Z"></path></svg></span>';
    } elseif ($normalizedKey === 'egtdigital' || $normalizedKey === 'egt' || $normalizedKey === 'egtdigitalslots') {
        $svgLogo = '<span class="CMSIconSVGWrapper provider-logo-svg provider-logo-svg--egtdigital"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 768 768" fill="currentColor"><path d="M349.243 1.442c-90.545 10.030-171.894 48.198-235.136 110.324-64.634 63.799-101.409 140.413-111.996 233.464-11.701 103.916 25.91 219.813 96.116 295.313 59.62 64.077 126.205 101.966 210.898 120.075 41.232 8.914 108.653 8.914 149.885 0 118.961-25.352 214.798-98.345 268.010-204.212 55.998-111.439 54.605-239.872-4.179-351.868-53.491-102.244-153.228-175.516-269.125-197.803-23.68-4.457-83.579-7.522-104.474-5.293zM443.965 26.236c49.869 8.079 95.002 24.516 135.677 49.312 23.123 13.93 57.948 40.397 57.948 44.018 0 1.114-11.702 2.786-25.63 3.9-190.282 13.651-397.001 82.186-554.687 183.596-16.437 10.587-30.646 18.666-31.203 18.109-2.507-2.229 10.309-47.083 20.059-71.6 38.725-96.952 119.518-173.845 219.813-210.062 18.109-6.407 62.962-16.994 83.3-19.501 21.452-2.785 72.157-1.672 94.723 2.229zM654.307 143.804c37.889 42.626 67.421 103.916 81.071 166.601 6.407 30.089 9.751 79.4 6.964 105.309l-2.229 18.666-26.188 15.601c-140.97 83.022-321.223 134.841-468.043 134.841-90.266 0-150.164-16.159-184.989-50.148-19.78-19.223-26.467-35.103-26.188-61.291 0.278-37.332 22.009-76.893 64.634-119.797 22.566-22.288 70.764-61.291 76.336-61.291 1.114 0 2.229 27.58 2.229 61.291v61.291h111.439v-25.073h-80.793v-36.218h69.649v-25.073h-69.928l0.835-17.551 0.835-17.273 76.614-1.672v-24.516l-71.042-1.672 20.895-11.98c98.066-55.998 229.564-100.294 349.639-117.847 53.769-7.801 54.605-7.522 68.257 7.801zM733.985 458.619c-1.115 5.85-5.293 20.616-9.194 32.875-39.004 120.632-137.348 213.962-255.752 243.494-96.952 24.238-199.196 6.686-282.776-48.476-17.273-11.422-50.705-39.004-52.376-43.182-0.278-1.114 16.437-2.785 37.332-3.622 169.944-8.357 386.693-78.007 531.841-171.058 16.994-10.865 31.203-20.059 31.76-20.059 0.557-0.278 0 4.457-0.836 10.03z"></path><path d="M353.979 269.452c-19.502 6.964-33.71 18.944-42.346 35.66-5.85 11.423-6.964 17.273-6.964 37.332 0 20.895 0.835 25.352 7.522 36.775 10.03 16.437 24.795 28.695 42.346 34.546 19.223 6.407 52.098 4.179 70.207-5.015l13.652-6.686 1.672-62.405h-28.138v44.018l-10.03 3.343c-21.731 7.244-44.576 0.278-57.948-17.551-7.801-10.587-9.193-39.004-2.507-52.098 13.094-25.073 46.804-32.317 72.157-15.044l10.309 7.244 9.751-10.03c11.702-11.701 10.587-14.487-12.259-25.63-20.338-10.03-47.083-11.701-67.421-4.457z"></path><path d="M459.289 281.153v13.93h47.361v119.797h30.646v-119.797h47.361v-27.86h-125.369v13.93z"></path><path d="M299.375 449.704c-12.816 4.457-12.537 4.179-16.994 16.994-7.243 21.452 5.85 40.118 28.138 40.118 18.944 0 23.402-3.343 23.402-18.109 0-10.587-0.836-12.537-5.572-12.537-4.457 0-5.572 1.95-5.572 9.472 0 7.244-1.393 9.751-5.85 10.865-21.173 5.572-33.432-22.009-15.602-35.382 6.686-5.015 8.636-5.293 17.552-1.95 7.243 2.507 10.865 2.786 13.094 0.557 8.079-8.079-16.716-15.323-32.596-10.03z"></path><path d="M177.906 477.563v29.253h16.716c27.859 0 39.004-8.636 39.004-30.646 0-9.194-1.672-13.373-8.079-19.781-7.522-7.522-9.751-8.079-27.859-8.079h-19.78v29.253zM216.909 459.454c13.094 13.094 3.065 33.432-16.716 33.432h-11.144v-39.004h11.144c7.522 0 13.094 1.95 16.716 5.572z"></path><path d="M250.341 477.563v29.253h13.93v-58.505h-13.93v29.253z"></path><path d="M353.422 477.563c0 27.302 0.278 29.253 5.572 29.253s5.572-1.95 5.572-29.253c0-27.302-0.278-29.253-5.572-29.253s-5.572 1.95-5.572 29.253z"></path><path d="M378.496 452.212c0 2.507 3.343 4.457 9.194 5.015l8.914 0.836 0.836 24.238c0.836 22.288 1.393 24.516 6.129 24.516 5.014 0 5.572-1.95 5.572-25.073v-25.073h9.751c6.686 0 9.751-1.393 9.751-4.179 0-3.343-4.736-4.179-25.073-4.179-20.059 0-25.073 0.836-25.073 3.9z"></path><path d="M444.245 474.221c-5.85 13.93-11.423 27.302-11.98 28.975-0.836 2.229 0.836 3.622 4.457 3.622 3.9 0 6.964-2.507 8.636-6.964 2.229-6.129 4.179-6.964 16.716-6.964s14.487 0.836 16.716 6.964c1.672 4.457 4.736 6.964 8.636 6.964 6.686 0 7.244 1.672-7.801-34.267-9.193-21.452-11.144-24.238-17.552-24.238-6.129 0-8.079 3.065-17.83 25.91zM467.368 468.091c4.457 12.816 3.9 13.652-5.293 13.652-4.457 0-8.357-1.115-8.357-2.507 0-5.014 6.129-19.781 8.357-19.781 1.115 0 3.622 3.9 5.293 8.636z"></path><path d="M506.65 477.563v29.253h20.895c16.437 0 20.895-0.836 20.895-3.9s-4.179-4.457-14.487-5.015l-14.766-0.836-0.836-24.516c-0.835-22.009-1.393-24.238-6.129-24.238-5.294 0-5.572 1.95-5.572 29.253z"></path></svg></span>';
    } elseif ($normalizedKey === 'habanero') {
        $svgLogo = '<span class="CMSIconSVGWrapper provider-logo-svg provider-logo-svg--habanero"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 87 18" fill="currentColor"><path d="M83.774 8.79h-.497V7.343h-.47v-.435h1.468v.435h-.5v1.449zm3.226 0h-.492l.036-1.443h-.014l-.463 1.444h-.43l-.476-1.444h-.017l.042 1.444h-.492V6.907h.788l.355 1.183h.02l.335-1.183h.807v1.884H87zm-61.424 5.861h-2.099v-3.124h-2.72v3.124h-2.1V6.698h2.1v3.005h2.72V6.698h2.099v7.953zm3.415-1.371-.378 1.371h-2.199l2.364-7.953h3.203l2.4 7.953H32.23l-.414-1.371H28.99zm1.419-4.997-.97 3.459h1.962l-.992-3.46zm4.81-1.585h4.249c.71.008 1.276.145 1.698.411.421.267.632.809.632 1.628 0 .461-.06.86-.182 1.198-.12.338-.466.555-1.036.65v.06c.531.055.888.226 1.071.512.183.286.276.692.276 1.217 0 .89-.174 1.492-.521 1.806-.348.314-.982.471-1.903.471H35.22V6.698zm3.43 3.184c.03.007.062.011.093.011h.082c.25-.007.463-.042.638-.101.175-.06.263-.252.263-.578 0-.286-.049-.483-.146-.59-.098-.108-.287-.161-.568-.161h-1.697v1.419h1.335zm.058 3.017c.327.008.599-.022.814-.09.214-.067.322-.296.322-.685 0-.374-.084-.594-.252-.662-.168-.067-.415-.102-.743-.102h-1.534V12.9h1.393zm6.215.38-.378 1.372h-2.198l2.364-7.953h3.203l2.4 7.953h-2.152l-.414-1.371h-2.825zm1.418-4.996-.969 3.459h1.962l-.993-3.46zM58.7 14.65h-3.625l-2.248-6.116h-.096l.096 6.116h-2.093V6.698h3.6l2.248 6.045h.084l-.084-6.045H58.7v7.953zm3.197-4.77h3.409v1.479h-3.41v1.538h3.722v1.753h-5.87V6.698h5.798V8.45h-3.65v1.43zm4.56-3.183h4.049c1.028.008 1.764.18 2.205.518.442.338.663 1.04.663 2.104 0 .597-.066 1.08-.198 1.45-.132.369-.573.634-1.32.792v.072c.499.032.866.18 1.103.447.237.266.356.642.356 1.127v1.443H71.12V13.59c0-.318-.062-.571-.186-.757-.125-.187-.364-.28-.718-.28h-1.602v2.098h-2.157V6.698h-.002zm3.868 4.03c.394.009.633-.079.717-.262.084-.183.127-.445.127-.787 0-.39-.044-.676-.133-.859-.088-.183-.361-.274-.82-.274l-1.602-.012v2.194h1.711zm3.89-.478a9.696 9.696 0 0 1 .105-1.432c.15-.88.551-1.45 1.205-1.708a6.034 6.034 0 0 1 2.115-.412h.236c1.363 0 2.316.23 2.86.689.543.46.814 1.4.814 2.822 0 1.453-.16 2.556-.478 3.31-.32.755-1.365 1.132-3.137 1.132a13.515 13.515 0 0 1-.791-.024 4.85 4.85 0 0 1-1.69-.365c-.528-.22-.886-.66-1.075-1.32a3.581 3.581 0 0 1-.142-.813c-.016-.275-.024-.55-.024-.825v-.59l.001-.464zm2.162.43v.471c0 .558.059.982.177 1.273.118.29.504.436 1.158.436.725 0 1.184-.116 1.377-.348.193-.231.289-.681.289-1.349v-.283c0-.094.004-.192.012-.294v-.565c0-.526-.075-.912-.225-1.155-.15-.244-.508-.365-1.075-.365-.82 0-1.306.11-1.46.33-.153.22-.238.824-.253 1.814v.047-.012zM6.774 3.14c.192 0 .353.135.39.314l.008.08v1.772c0 .218.178.394.398.394.175 0 .347-.13.39-.313l.01-.082v-.628c0-.218.176-.394.396-.394.193 0 .353.135.39.315l.008.08v1.646c0 .218.179.394.398.394a.396.396 0 0 0 .39-.315l.008-.08v-.02c0-.219.179-.395.398-.395.193 0 .353.135.39.315l.008.08v.982c0 .218.18.394.399.394.184 0 .34-.125.385-.294l.012-.074v-.199c0-.218.18-.394.399-.394.193 0 .353.135.39.315l.008.08v3.14l-.002.02v4.316L5.974 18 0 14.589V7.767l.007-.608c0-.218.178-.394.398-.394.192 0 .353.135.39.315l.008.08v.608c0 .218.178.394.398.394a.396.396 0 0 0 .39-.315l.008-.079V5.914c0-.218.178-.394.398-.394.192 0 .353.135.39.315l.008.08v.664c0 .218.178.394.398.394a.397.397 0 0 0 .39-.315l.008-.08V4.06c0-.218.178-.394.398-.394.193 0 .353.135.39.315l.008.08v1.645c0 .218.178.394.398.394a.397.397 0 0 0 .39-.314l.008-.08V4.522c0-.219.179-.395.399-.395.192 0 .352.135.39.315l.008.08v.552c0 .218.178.394.397.394a.397.397 0 0 0 .39-.314l.008-.08v-1.54c0-.219.179-.394.399-.394zM5.035 8.985H3.16v4.424h1.876v-1.413h1.876v1.413h1.877V8.985H6.911v1.487H5.035V8.985zm3.35-6.055c.233 0 .42.19.42.424l-.001-.005-.006.072a.421.421 0 0 1-.337.34l-.075.006a.42.42 0 0 1-.42-.423v.005l.007-.071a.422.422 0 0 1 .337-.34zM5.242 1.465c.203 0 .372.132.41.308l.01.078v1.113c0 .213-.188.385-.42.385a.412.412 0 0 1-.41-.308l-.01-.077V1.85c0-.214.188-.386.42-.386zM6.708.42c.203 0 .372.132.411.307l.009.078v1.113c0 .213-.187.385-.42.385a.412.412 0 0 1-.41-.307l-.009-.078V.804c0-.213.188-.385.42-.385zM5.241 0a.42.42 0 0 1 .42.423L5.66.418l-.007.073a.42.42 0 0 1-.336.34L5.24.836a.421.421 0 0 1-.42-.423v.004l.007-.07a.422.422 0 0 1 .338-.341z"></path></svg></span>';
    } elseif ($normalizedKey === 'iconic21' || $normalizedKey === 'iconic21slots') {
        $svgLogo = '<span class="CMSIconSVGWrapper provider-logo-svg provider-logo-svg--iconic21"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 727.05 253.89" fill="currentColor"><path d="m474.44,142.97l13.91,110.91-56.08-87.25-44.45,30.24,13.87-46.37-91.69-10.92,98.21-35.38L395.73,0l55.5,81.1,44.96-31.27-14.46,47.67,90.97,10.74-98.25,34.72Zm-195.06,19.63c4.92,8.35,7.38,17.78,7.38,28.26s-2.46,19.91-7.38,28.26c-4.92,8.35-11.88,14.92-20.89,19.68-9.01,4.77-19.43,7.15-31.27,7.15s-22.27-2.38-31.27-7.15c-9.01-4.77-15.97-11.33-20.89-19.68-4.92-8.35-7.38-17.77-7.38-28.26s2.46-19.91,7.38-28.26c4.92-8.35,11.88-14.91,20.89-19.68,9.01-4.77,19.43-7.15,31.27-7.15s22.27,2.38,31.27,7.15c9.01,4.77,15.97,11.33,20.89,19.68Zm-29.35,28.26c0-5.12-.94-9.6-2.82-13.43-1.88-3.84-4.54-6.81-7.98-8.92-3.44-2.11-7.44-3.16-12.01-3.16s-8.57,1.05-12,3.16c-3.44,2.11-6.1,5.08-7.98,8.92-1.88,3.84-2.82,8.32-2.82,13.43s.94,9.6,2.82,13.43c1.88,3.84,4.54,6.81,7.98,8.92,3.44,2.11,7.44,3.16,12,3.16s8.57-1.05,12.01-3.16c3.44-2.11,6.1-5.08,7.98-8.92,1.88-3.84,2.82-8.32,2.82-13.43ZM0,242.35h34.62v-103.86H0v103.86Zm91.75-74.1c3.44-2.18,7.51-3.27,12.23-3.27,3.51,0,6.75.69,9.71,2.07,2.96,1.38,5.37,3.38,7.22,5.98,1.86,2.61,2.96,5.69,3.31,9.26h35c-.5-9.18-3.16-17.28-7.98-24.31-4.82-7.02-11.4-12.48-19.76-16.37-8.35-3.89-17.95-5.83-28.79-5.83s-21.21,2.38-30.07,7.15c-8.86,4.77-15.77,11.34-20.74,19.72-4.97,8.38-7.45,17.79-7.45,28.22s2.48,19.85,7.45,28.22c4.97,8.38,11.88,14.95,20.74,19.72,8.86,4.77,18.88,7.15,30.07,7.15s20.43-1.94,28.79-5.83c8.35-3.89,14.94-9.34,19.76-16.37,4.82-7.02,7.48-15.13,7.98-24.31h-35c-.35,3.56-1.46,6.65-3.31,9.26-1.86,2.61-4.26,4.6-7.22,5.98-2.96,1.38-6.2,2.07-9.71,2.07-4.72,0-8.79-1.09-12.23-3.27-3.44-2.18-6.06-5.23-7.86-9.14-1.81-3.91-2.71-8.4-2.71-13.47s.9-9.56,2.71-13.47c1.81-3.91,4.43-6.96,7.86-9.15Zm300.95,35.79l-20.15,13.71,6.98-23.34,10.93-36.51-25.11-2.99v43.49l-25.99-46.58-30.38-3.62-14.99-1.78v95.95h31.16v-58.96l32.84,58.96h38.51v-40.91l-3.82,2.6Zm37.1-25.25l-14.34,9.76v53.8h34.62v-32.01l-20.28-31.55Zm197.72,31.73c2.1-1.61,5.07-3.47,8.88-5.57,6.57-3.61,11.84-7.15,15.8-10.61,3.97-3.47,6.89-7.2,8.77-11.22,1.88-4.01,2.83-8.57,2.83-13.69,0-6.82-1.49-12.76-4.48-17.8-2.99-5.05-7.35-8.93-13.1-11.67-5.75-2.74-12.71-4.1-20.89-4.1-8.68,0-16.19,1.63-22.54,4.89-6.35,3.26-11.24,7.78-14.68,13.55-3.44,5.77-5.28,12.31-5.53,19.64h31.69c-.1-3.91.86-6.91,2.86-8.99,2.01-2.08,4.6-3.12,7.75-3.12,1.7,0,3.2.31,4.48.94,1.28.63,2.27,1.52,2.97,2.67.7,1.15,1.05,2.53,1.05,4.14s-.38,3.08-1.13,4.41c-.76,1.33-2.12,2.76-4.1,4.28-1.98,1.53-4.78,3.28-8.39,5.23-10.09,5.47-17.77,10.98-23.03,16.52-4.87,5.13-8.31,10.74-10.3,16.82-.17.5-.32,1-.46,1.51-1.85,6.47-2.78,14.47-2.79,24.01h79.63v-25.51h-40.6c.21-.6.48-1.17.82-1.73.88-1.46,2.37-2.98,4.48-4.59Zm76.13-72.03c-1.46,4.97-3.56,9.08-6.32,12.34-2.76,3.26-6.26,5.75-10.5,7.45s-9.34,2.68-15.32,2.94v29.2c4.57.1,8.84-.39,12.83-1.47,3.89-1.05,7.31-2.65,10.27-4.78v58.18h32.44v-103.86h-23.41Zm-194.02,29.77c3.44-2.18,7.51-3.27,12.23-3.27,3.51,0,6.75.69,9.71,2.07,2.96,1.38,5.37,3.38,7.23,5.98,1.86,2.61,2.96,5.69,3.31,9.26h35c-.5-9.18-3.16-17.28-7.98-24.31-4.82-7.02-11.4-12.48-19.76-16.37-8.3-3.86-17.84-5.81-28.59-5.83l-36.87,13.03,11.59,92.38c7.58,3.18,15.95,4.77,25.09,4.77,10.84,0,20.43-1.94,28.79-5.83,8.35-3.89,14.94-9.34,19.76-16.37,4.82-7.02,7.48-15.13,7.98-24.31h-35c-.35,3.56-1.46,6.65-3.31,9.26-1.86,2.61-4.27,4.6-7.23,5.98-2.96,1.38-6.2,2.07-9.71,2.07-4.72,0-8.79-1.09-12.23-3.27-3.44-2.18-6.06-5.23-7.87-9.14-1.81-3.91-2.71-8.4-2.71-13.47s.9-9.56,2.71-13.47c1.81-3.91,4.43-6.96,7.87-9.15Z"></path></svg></span>';
    } else {
        $svgLabel = htmlspecialchars(mb_strtoupper((string) $provider, 'UTF-8'), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $svgLogo = '<span class="CMSIconSVGWrapper provider-logo-svg provider-logo-svg--fallback"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 220 24" fill="currentColor" role="img" aria-label="' . $svgLabel . '"><text x="110" y="18" text-anchor="middle" font-size="17" font-weight="700" font-family="Arial, sans-serif">' . $svgLabel . '</text></svg></span>';
    }

    return '<div title="' . $esc . '" class="providerItemsInner sidebar-provider-item' . ($active ? ' active' : '') . '" role="button" tabindex="0" data-provider="' . $esc . '"><span class="providerBadgeBlock ' . htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') . '" data-badge="' . htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') . '"></span><div class="providerItemsBtn has-provider-icon">' . $svgLogo . '<span class="provider-list-row">' . htmlspecialchars($provider, ENT_QUOTES, 'UTF-8') . '</span></div></div>';
};

$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$slotPlayTarget = static function (array $game) use ($loggedIn): string {
    $gid = (string) ($game['game_id'] ?? '');
    $play = '/play?game_id=' . rawurlencode($gid) . '&mode=real&wallet=main';
    if ($loggedIn) {
        return $play;
    }
    return '#login';
};
$slotDemoHref = static function (array $game): string {
    $gid = (string) ($game['game_id'] ?? '');

    return '/play?game_id=' . rawurlencode($gid) . '&mode=fun';
};
?>

<?php require_once __DIR__ . '/../views/layouts/head_full.php'; ?>
<?php include __DIR__ . '/../views/partials/header.php'; ?>

<div class="slot-page-root">
<!-- Slot üst: Slider -->
<section class="slot-top-section" aria-label="Slot sayfası üst alan">
    <?php $sliderApiCategory = 'slots'; include __DIR__ . '/../views/partials/slider.php'; ?>
    <div class="slot-below-hero">
        <div class="slot-jackpot-wrap">
            <?php include __DIR__ . '/../views/partials/jackpot.php'; ?>
        </div>
        <div class="slot-winners-wrap">
            <?php include __DIR__ . '/../views/partials/winners.php'; ?>
        </div>
    </div>
</section>

<div class="casino-container">
    <div class="casinoProviderContent slots-filter-and-games" id="slotsFilterAndGames">
        <div class="casinoCategoryChooserContainer slots-category-filters category-tabs-wrapper" id="slotsCategoryFilters">
        <div tabindex="0" class="horizontalSliderWrapper horizontalItemsExpanded scroll-start">
        <i class="horizontalSliderNav bc-i-small-arrow-left cat-tab-arrow cat-tab-arrow-left" id="catArrowLeft"></i>
        <div class="horizontalSliderRow category-tabs-scroll" id="categoryTabsScroll" style="transform: translateX(0px);">
            <?php foreach ($slotCategoryItems as $category): ?>
                <?php $isActive = $category['sort'] === $currentSort || ($category['sort'] === '' && $currentSort === ''); ?>
                <a href="<?= htmlspecialchars($category['href'], ENT_QUOTES, 'UTF-8') ?>"
                   class="horizontalCategoryItemWrp <?= $isActive ? 'active ' : '' ?><?= htmlspecialchars($category['slug'], ENT_QUOTES, 'UTF-8') ?> cat-tab<?= $isActive ? ' active' : '' ?>"
                   data-id="<?= htmlspecialchars($category['id'], ENT_QUOTES, 'UTF-8') ?>"
                   data-sort="<?= htmlspecialchars($category['sort'], ENT_QUOTES, 'UTF-8') ?>">
                    <div data-id="<?= htmlspecialchars($category['id'], ENT_QUOTES, 'UTF-8') ?>"
                         title="<?= htmlspecialchars($category['title'], ENT_QUOTES, 'UTF-8') ?>"
                         data-badge=""
                         class="horizontalCategoryItem">
                        <i class="bc-i-default-icon <?= htmlspecialchars($category['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                        <div class="horCatItemTitleWrp"><p class="horCatItemTitle"><?= htmlspecialchars($category['title'], ENT_QUOTES, 'UTF-8') ?></p></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <i class="horizontalSliderNav bc-i-small-arrow-right cat-tab-arrow cat-tab-arrow-right" id="catArrowRight"></i>
        </div>
        </div>

        <div class="casinoProviderRow">
            <p class="casinoProviderBlockTitle" id="lineSidebarToggleLabel" title="Sağlayıcıları aç/kapat"><i class="casinoProviderBlockIcon bc-i-small-arrow-left" id="lineSidebarToggleIcon"></i><span>Sağlayıcılar</span></p>
            <p class="casinoGameListTitle">OYUNLAR</p>
        </div>

        <div class="casinoProviderAndGame slots-layout" id="slotsLayout">
            <!-- ds-drawer-wrapper: tam ekran overlay + blur arka plan -->
            <div class="ds-drawer-wrapper providers-drawer-wrapper casinoProviderBlock providers-sidebar" id="providersSidebar" role="presentation">
              <div class="ds-drawer-spring" aria-modal="true">
                <div class="ds-drawer ds-drawer--contained">
                  <!-- Drag handle + başlık -->
                  <div class="ds-drawer__header">
                    <div class="ds-drawer__drag-handle" id="providerSheetDragHandle">
                      <div class="ds-drawer__drag-bar"></div>
                    </div>
                    <div class="ds-drawer__title-bar">
                      <div class="ds-drawer__title-content">
                        <span class="ds-drawer__title-text">Sağlayıcılar</span>
                      </div>
                    </div>
                  </div>
                  <!-- Kaydırılabilir içerik -->
                  <div class="ds-drawer__content" data-scroll-lock-scrollable="">
                    <div class="casinoProvidersPopupContent">
                      <!-- Arama -->
                      <div class="casinoProvidersPopupContent__search">
                        <div class="providerSearchRow sidebar-search provider-search-bar">
                          <div class="searchInputWrp active">
                            <input type="text" placeholder="Sağlayıcı Ara" id="providerSearchInput" class="searchInput provider-search-input" autocomplete="off">
                            <p class="searchInputIcon bc-i-search provider-search-btn" id="providerSearchClearBtn" title="Sağlayıcı ara" aria-label="Sağlayıcı ara"><i id="providerSearchClearBtnIcon" aria-hidden="true"></i></p>
                          </div>
                        </div>
                      </div>
                      <!-- Sağlayıcı listesi -->
                      <div class="casinoProvidersPopupContent__underSearchContent">
                        <div class="casinoProvidersPopupContent__section">
                          <div class="casinoProvidersPopupContent__titleToggleContainer">
                            <span class="casinoProvidersPopupContent__sectionTitle">Tüm Sağlayıcılar</span>
                            <div class="casinoProvidersPopupContent__viewToggle">
                              <i class="bc-i-view-list provider-sheet-grid-btn" id="providerSheetGridBtn" title="Modül görünümü" aria-label="Modül görünümü"></i>
                            </div>
                          </div>
                          <div class="casinoProvidersPopupContent__grid" id="sidebarProvidersList">
                            <div class="providerItemsHolder module">
                            <div title="Tümü" class="providerItemsInner sidebar-provider-item <?= empty($selectedProviders) && empty($searchTerm) ? 'active' : '' ?>" role="button" tabindex="0" data-provider-all="1"><span class="providerBadgeBlock " data-badge=""></span><div class="providerItemsBtn">TÜMÜ</div></div>
                            <?php
                            foreach ($allUniqueProviders as $provider) {
                                echo $renderProviderBtn($provider);
                            }
                            ?>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Footer: Sıfırla + FİLTRE -->
                  <div class="casinoProvidersPopupContent__footer">
                    <button type="button" class="ds-btn provider-sheet-reset" id="providerSheetResetBtn"><span class="btn__label">Sıfırla</span></button>
                    <button type="button" class="ds-btn provider-sheet-apply" id="providerSheetApplyBtn"><span class="btn__label">FİLTRE</span></button>
                  </div>
                </div>
              </div>
            </div>

            <div class="casinoGameListBlock games-main" id="gamesScrollContainer">
                <div class="casinoGameListBlockHeader">
                    <div class="casinoTitleSearch">
                        <button type="button" class="mobile-sidebar-toggle" id="mobileSidebarToggle" title="Tüm Sağlayıcılar" aria-label="Tüm sağlayıcıları aç">
                            <span class="mobile-sidebar-toggle__pill">
                                <i class="fas fa-filter" aria-hidden="true"></i>
                                <span class="mobile-sidebar-toggle__pill-text">Sağlayıcılar</span>
                            </span>
                            <span class="mobile-sidebar-toggle__count" id="mobileSidebarToggleCount" aria-hidden="true"></span>
                        </button>
                        <div class="selectedProviderBlock"></div>
                        <div class="casinoInputWrp games-search-expand<?= !empty($searchTerm) ? ' is-expanded' : '' ?>"
                             id="gamesSearchExpand"
                             role="search"
                             aria-expanded="<?= !empty($searchTerm) ? 'true' : 'false' ?>">
                            <div class="searchInputWrp active games-search-bar">
                                <input type="text"
                                       class="searchInput games-search-input"
                                       placeholder="Oyun Ara"
                                       id="searchModalInput"
                                       value="<?= htmlspecialchars($searchTerm, ENT_QUOTES); ?>">
                                <p class="searchInputIcon bc-i-search games-search-btn games-search-icon-btn" id="searchClearBtn" title="Aramayı temizle" aria-label="Aramayı temizle"><i id="searchClearBtnIcon" aria-hidden="true"></i></p>
                            </div>
                        </div>
                        <div class="tooltipIconWrapper sort-toggle-wrap" id="sortToggleWrap">
                            <button type="button" class="bc-i-sort iconButtonBlock sort-toggle-btn" id="sortToggleBtn" title="Sıralama" aria-label="Sıralama"></button>
                        </div>
                    </div>
                </div>
                <button type="button" class="all-games-btn iconButtonBlock" id="allGamesBtn" title="Tüm Oyunlar" aria-label="Tüm Oyunlar" hidden></button>
                <button type="button" class="view-module-btn iconButtonBlock" id="viewModuleBtn" title="Modül görünümü" aria-label="Modül görünümü" aria-pressed="false" hidden></button>
                <button class="random-game-btn" id="randomGameBtn" title="Rastgele Oyun Oyna" aria-label="Rastgele Oyun Oyna">Rastgele Oyun Oyna</button>

                <!-- Active Filters (AJAX güncellemesi için her zaman DOM'da) -->
                <div class="active-filters-row" id="active-filters-row" style="<?= (empty($searchTerm) && empty($selectedProviders)) ? 'display:none' : '' ?>">
                <?php if (!empty($searchTerm)): ?>
                    <div class="active-filter-tag">
                        <span>"<?= htmlspecialchars($searchTerm) ?>"</span>
                        <span class="remove" onclick="removeSearch()">×</span>
                    </div>
                <?php endif; ?>
                <?php foreach ($selectedProviders as $provider): ?>
                    <div class="active-filter-tag">
                        <span><?= htmlspecialchars($provider) ?></span>
                        <span class="remove" onclick='removeFilter(<?= json_encode($provider, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>)'>×</span>
                    </div>
                <?php endforeach; ?>
                <?php if (!empty($searchTerm) || !empty($selectedProviders)): ?>
                <button class="clear-all-filters-btn" onclick="clearAllUrlFilters()">Temizle</button>
                <?php endif; ?>
                </div>

                <!-- 4) Oyun listesi alanı (tek blok, isim: slot-oyun-listesi) -->
                <div class="casinoGameItemWrp slot-oyun-listesi slots-games-container" id="casino_games_container" aria-label="Oyun listesi">
                    <div class="casinoCategoryGames">
                <?php if (empty($games)): ?>
                    <div class="empty-state">
                        <i class="fas fa-gamepad"></i>
                        <h3>Slot oyunu bulunamadı</h3>
                        <p>Arama teriminizi değiştirmeyi veya filtreleri temizlemeyi deneyin</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($games as $game): ?>
                        <?php
                        $playHref = $slotPlayTarget($game);
                        $demoHref = $slotDemoHref($game);
                        $playHrefJson = htmlspecialchars(
                            json_encode($playHref, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        $mobileOpenPlayJsBody = 'var m=(document.body&&document.body.classList.contains(&quot;mobile-site&quot;))||(((navigator.maxTouchPoints||0)&gt;0)&&window.matchMedia&&window.matchMedia(&quot;(max-width: 1024px)&quot;).matches);if(m){try{var p=new URL(nu,window.location.origin);p.searchParams.set(&quot;open_mode&quot;,&quot;redirect&quot;);nu=p.pathname+p.search+p.hash;}catch(e){nu+=(nu.indexOf(&quot;?&quot;)===-1?&quot;?&quot;:&quot;&amp;&quot;)+&quot;open_mode=redirect&quot;;}var a=document.createElement(&quot;a&quot;);a.href=nu;a.target=&quot;_blank&quot;;a.rel=&quot;noopener noreferrer&quot;;a.style.display=&quot;none&quot;;document.body.appendChild(a);a.click();a.remove();if(document.visibilityState===&quot;hidden&quot;){return;}}window.location.href=nu;';
                        // Play navigation is routed through MaltabetWalletPicker (main/bonus wallet choice modal)
                        // when available, falling back to direct navigation otherwise.
                        $mobileOpenPlayJs = 'var u=' . $playHrefJson . ';function __navPlay(nu){' . $mobileOpenPlayJsBody . '}if(window.MaltabetWalletPicker&&typeof window.MaltabetWalletPicker.launch===&quot;function&quot;){window.MaltabetWalletPicker.launch(u,__navPlay);}else{__navPlay(u);}';
                        $openLoginJs = 'if (typeof window.__openLoginModal === &quot;function&quot;) { window.__openLoginModal(); } else { var loginBtn = document.getElementById(&quot;Giris&quot;); if (loginBtn) loginBtn.click(); }';
                        ?>
                        <div class="casinoGameItemContent " data-catalog-id="<?= htmlspecialchars((string)($game['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-game-id="<?= htmlspecialchars((string)($game['game_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" onclick="<?= $loggedIn ? $mobileOpenPlayJs : $openLoginJs ?>">
                            <span class="providerBadgeBlock " data-badge=""></span>
                            <div class="casinoGameItem ">
                                <img alt="<?= htmlspecialchars($game['game_name'], ENT_QUOTES); ?>"
                                     loading="eager"
                                     src="<?= htmlspecialchars($game['cover'], ENT_QUOTES); ?>"
                                     data-src="<?= htmlspecialchars($game['cover'], ENT_QUOTES); ?>"
                                     class="casinoGameItemImage"
                                     title="<?= htmlspecialchars($game['game_name'], ENT_QUOTES); ?>"
                                     style="aspect-ratio: 44 / 31;"
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDMwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjMwMCIgaGVpZ2h0PSIyMDAiIHJ4PSI4IiBmaWxsPSIjMWExMTJlIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGRvbWluYW50LWJhc2VsaW5lPSJtaWRkbGUiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZpbGw9IiM2NjYiIGZvbnQtZmFtaWx5PSJzYW5zLXNlcmlmIiBmb250LXNpemU9IjE0Ij5ObyBJbWFnZTwvdGV4dD48L3N2Zz4='">
                                <i class="casinoGameItemFavBc bc-i-favorite "></i>
                                <div class="game-overlay">
                                    <div class="game-overlay-top"></div>
                                    <div class="game-title-wrap">
                                        <p class="game-title-text"><?= htmlspecialchars((string) ($game['game_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div class="game-actions">
                                        <a class="play-btn" href="<?= htmlspecialchars($playHref, ENT_QUOTES, 'UTF-8') ?>" onclick="event.stopPropagation();<?= $loggedIn ? ' event.preventDefault(); ' . $mobileOpenPlayJs : ' event.preventDefault(); ' . $openLoginJs ?>">OYNA</a>
                                        <a class="demo-btn" href="<?= htmlspecialchars($demoHref, ENT_QUOTES, 'UTF-8') ?>" onclick="event.stopPropagation()">DEMO</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                    </div>
                    <!-- Infinite scroll tetikleyici (IntersectionObserver için görünmez ama layout'ta yer kaplar) -->
                    <div id="load-more-sentinel" aria-hidden="true" style="height:1px;min-height:1px;opacity:0;pointer-events:none;overflow:hidden;"></div>
                </div>
            </div>
        </div>
    </div>
    <!-- Oyunlar scrollbar’ı sitenin en sağında (site scroll’u gibi) -->
    <div class="slot-games-scrollbar-rail" id="slotGamesScrollbarRail" aria-hidden="true">
        <div class="slot-games-scrollbar-thumb" id="slotGamesScrollbarThumb"></div>
    </div>
</div>
</div>

<script>
window.SLOT_CONFIG = {
    apiEndpoint: '/api/v2/games',
    apiAdapter: 'member_api_games',
    gameType: 0,
    currentPage: <?= (int)$currentPage ?>,
    nextPage: <?= (int)$nextPage ?>,
    pageSize: <?= (int)$perPage ?>,
    loggedIn: <?= $loggedIn ? 'true' : 'false' ?>,
    search: <?= json_encode($searchTerm) ?>,
    providers: <?= json_encode($selectedProviders) ?>,
    sort: <?= json_encode($currentSort) ?>,
    apiParams: <?= json_encode($slotApiParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    actionButtons: true,
    totalSlots: <?= (int)$totalSlots ?>,
    remainingGames: <?= (int)$remainingGames ?>,
    showLoadMore: <?= $showLoadMore ? 'true' : 'false' ?>
};
</script>
<script src="/assets/js/jackpot.js"></script>
<script src="/assets/js/winners.js?v=<?= urlencode((string) (is_file(BASE_PATH . '/assets/js/winners.js') ? filemtime(BASE_PATH . '/assets/js/winners.js') : time())) ?>"></script>
<?php
$slotJsPath = BASE_PATH . '/assets/js/slot.js';
$slotJsVer = (string) ((is_file($slotJsPath) ? filemtime($slotJsPath) : time()) . '-' . (is_file($slotJsPath) ? filesize($slotJsPath) : '0'));
?>
<script src="/assets/js/slot.js?v=<?= rawurlencode($slotJsVer) ?>"></script>