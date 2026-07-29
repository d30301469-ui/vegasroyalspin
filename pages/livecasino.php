<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/frontend_session.php';
    metropol_frontend_session_start();
}
require_once __DIR__ . '/../core/bootstrap.php';
require_once SERVICE_PATH . '/LiveCasinoQuery.php';
require_once SERVICE_PATH . '/ProviderDisplayBadgeMap.php';
require_once SERVICE_PATH . '/CasinoAggregatorService.php';

$searchTerm = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$selectedProviders = array_values(array_filter(array_map(
    static fn ($provider): string => CasinoAggregatorService::resolveLocalizedLabel(trim((string) $provider)),
    isset($_GET['providers']) ? (array) $_GET['providers'] : []
), static fn (string $provider): bool => $provider !== ''));
$currentSort = isset($_GET['sort']) ? trim((string) $_GET['sort']) : '';
$limit = 30;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

// VGY1 staging live lobby: GSC+ contracted LC only (aggregator off).
// Default lists all staging currencies; ?currency=IDR narrows for IDR-only tests.
$liveLobbyExtra = [
    'gsc_only' => 1,
];
$currencyOverride = strtoupper(trim((string) ($_GET['currency'] ?? '')));
if ($currencyOverride !== '') {
    $liveLobbyExtra['currency'] = $currencyOverride;
}

$result = LiveCasinoQuery::page($searchTerm, $selectedProviders, $limit, $page, $currentSort, $liveLobbyExtra);
$games = is_array($result['games'] ?? null) ? $result['games'] : [];
$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;

$allUniqueProviders = LiveCasinoQuery::providers($liveLobbyExtra);
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
    'evolution' => ['EN IYI'],
    'evo' => ['EN IYI'],
    'ezugi' => ['OZEL'],
    'creedroomz' => ['OZEL'],
    'vivo' => ['SICAK'],
    'sagaming' => ['SICAK'],
    'sa gaming' => ['SICAK'],
    'playtech' => ['EN IYI'],
    'netent' => ['SICAK'],
    'dreamgaming' => ['SICAK'],
    'dream gaming' => ['SICAK'],
    'allbet' => ['OZEL'],
    'wm casino' => ['SICAK'],
    'wm' => ['SICAK'],
    'big gaming' => ['SICAK'],
    'biggaming' => ['SICAK'],
    'vimplay' => ['OZEL'],
    'astar' => ['SICAK'],
];

$slotPageBaseUrl = '/livecasino';
$slotPageTitle = 'CANLI CASINO';
$slotGameType = 1;
$slotEmptyTitle = 'Canlı casino oyunu bulunamadı';
$slotEmptyText = 'Arama teriminizi değiştirmeyi veya filtreleri temizlemeyi deneyin.';
// Load-more / filters go through LiveCasinoQuery via API source=livecasino
$slotApiParams = [
    'source' => 'livecasino',
    'gsc_only' => 1,
];
if (!empty($liveLobbyExtra['currency'])) {
    $slotApiParams['currency'] = $liveLobbyExtra['currency'];
}
$slotLobbyBanner = 'GSC+ VGY1 staging'
    . (!empty($liveLobbyExtra['currency']) ? (' · ' . $liveLobbyExtra['currency']) : ' · IDR öncelikli')
    . ' (site bakiyesi TRY görünebilir; launch/wallet ürün currency’si ile gider)';
$sliderApiCategory = 'live_casino';
$slotShowActionButtons = true;
$slotHideProviders = false;

$mobileLiveCasinoView = defined('MOBILE_PATH') ? MOBILE_PATH . '/views/pages/livecasino.php' : '';
if (defined('SURFACE') && SURFACE === 'mobile' && $mobileLiveCasinoView !== '' && is_file($mobileLiveCasinoView)) {
    require $mobileLiveCasinoView;
    return;
}

require VIEW_PATH . '/pages/slot.php';
