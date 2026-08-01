<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/frontend_session.php';
    metropol_frontend_session_start();
}
require_once __DIR__ . '/../core/bootstrap.php';
require_once SERVICE_PATH . '/LiveCasinoQuery.php';
require_once SERVICE_PATH . '/ProviderDisplayBadgeMap.php';
require_once SERVICE_PATH . '/ProviderLogoSvgMap.php';
require_once SERVICE_PATH . '/CasinoAggregatorService.php';

$searchTerm = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$currentSort = isset($_GET['sort']) ? trim((string) $_GET['sort']) : '';
$limit = 30;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

$liveLobbyExtra = [
    'source' => 'gsc',
    'gsc_only' => 1,
];
$currencyOverride = strtoupper(trim((string) ($_GET['currency'] ?? '')));
if ($currencyOverride !== '' && $currencyOverride !== 'ALL' && $currencyOverride !== '*') {
    $liveLobbyExtra['currency'] = $currencyOverride;
}

$allUniqueProviders = LiveCasinoQuery::providers($liveLobbyExtra);
$selectedProviders = CasinoAggregatorService::canonicalizeProviders(
    CasinoAggregatorService::providersFromQuery(),
    $allUniqueProviders
);

$result = LiveCasinoQuery::page($searchTerm, $selectedProviders, $limit, $page, $currentSort, $liveLobbyExtra);
$games = is_array($result['games'] ?? null) ? $result['games'] : [];
$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
sort($allUniqueProviders, SORT_NATURAL | SORT_FLAG_CASE);

$totalSlots = (int) ($result['total'] ?? count($games));
$perPage = (int) ($result['perPage'] ?? $limit);
$currentPage = (int) ($result['page'] ?? $page);
$hasNext = !empty($result['hasNext']);
$loadedCount = ($currentPage - 1) * $perPage + count($games);
$remainingGames = max(0, $totalSlots - $loadedCount);
$showLoadMore = $hasNext && $remainingGames > 0;
$nextPage = $currentPage + 1;
$apiError = !empty($result['apiError']);

$providerBadges = [
    'pragmatic' => ['EN IYI', 'SICAK'],
    'pragmaticplay' => ['EN IYI', 'SICAK'],
    'sagaming' => ['SICAK'],
    'sa gaming' => ['SICAK'],
    'astar' => ['SICAK'],
    'dreamgaming' => ['SICAK'],
    'dream gaming' => ['SICAK'],
    'hacksaw' => ['SICAK'],
    'habanero' => ['SICAK'],
    'cq9' => ['EN IYI'],
    'boominggames' => ['SICAK'],
    'booming games' => ['SICAK'],
    'advantplay' => ['OZEL'],
    'advant play' => ['OZEL'],
    'uuslots' => ['SICAK'],
    'epicwin' => ['SICAK'],
    'fachai' => ['SICAK'],
    'fa chai' => ['SICAK'],
    'gaming panda' => ['OZEL'],
    'wow gaming' => ['SICAK'],
    'live22' => ['SICAK'],
    'live 22' => ['SICAK'],
    'yfg' => ['SICAK'],
    'evoplay' => ['SICAK'],
    'bigpot' => ['SICAK'],
];

$slotPageBaseUrl = '/livecasino';
$slotPageTitle = 'CANLI CASINO';
$slotGameType = 1;
$slotEmptyTitle = 'Oyun bulunamadı';
$slotEmptyText = 'Arama teriminizi değiştirmeyi veya filtreleri temizlemeyi deneyin.';
// Load-more / filters: GSC+ IDR staging (live + slots)
$slotApiParams = [
    'source' => 'gsc',
    'gsc_only' => 1,
];
if (!empty($liveLobbyExtra['currency'])) {
    $slotApiParams['currency'] = $liveLobbyExtra['currency'];
}
$sliderApiCategory = 'live_casino';
$slotShowActionButtons = true;
$slotHideProviders = false;

$mobileLiveCasinoView = defined('MOBILE_PATH') ? MOBILE_PATH . '/views/pages/livecasino.php' : '';
if (defined('SURFACE') && SURFACE === 'mobile' && $mobileLiveCasinoView !== '' && is_file($mobileLiveCasinoView)) {
    require $mobileLiveCasinoView;
    return;
}

require VIEW_PATH . '/pages/slot.php';
