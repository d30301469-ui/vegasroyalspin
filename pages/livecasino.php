<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/frontend_session.php';
    frontend_session_start();
}
require_once __DIR__ . '/../core/bootstrap.php';
require_once SERVICE_PATH . '/SlotGamesQuery.php';
require_once SERVICE_PATH . '/ProviderDisplayBadgeMap.php';
require_once SERVICE_PATH . '/ProviderLogoSvgMap.php';
require_once SERVICE_PATH . '/CasinoAggregatorService.php';

$searchTerm = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$currentSort = isset($_GET['sort']) ? trim((string) $_GET['sort']) : '';
$viewParam = isset($_GET['view']) ? trim((string) $_GET['view']) : '';
$limit = 30;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

// CANLI CASINO = Casino Aggregator live vendors (Evolution, Pragmatic Live, Ezugi, …).
$liveLobbyExtra = [
    'source' => 'aggregator',
];

$allUniqueProviders = SlotGamesQuery::providersForGameType(1);
$selectedProviders = CasinoAggregatorService::canonicalizeProviders(
    CasinoAggregatorService::providersFromQuery(),
    $allUniqueProviders
);

$result = SlotGamesQuery::gamesPage(
    1,
    $searchTerm,
    $selectedProviders,
    $limit,
    $page,
    $currentSort === 'all' ? '' : $currentSort,
    $liveLobbyExtra
);
$games = is_array($result['games'] ?? null) ? array_values($result['games']) : [];
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
    'pragmatic' => ['EN İYİ', 'SICAK'],
    'pragmaticplay' => ['EN İYİ', 'SICAK'],
    'pragmatic play live' => ['EN İYİ', 'SICAK'],
    'pragmatic blackjack' => ['SICAK'],
    'pragmatic blackjack2' => ['SICAK'],
    'evolution' => ['EN İYİ', 'SICAK'],
    'ezugi' => ['SICAK'],
    'sagaming' => ['SICAK'],
    'sa gaming' => ['SICAK'],
    'vivo' => ['SICAK'],
    'vivo live' => ['SICAK'],
];

$slotPageBaseUrl = '/livecasino';
$slotPageTitle = 'CANLI CASINO';
$slotGameType = 1;
$slotPageIsLive = true;
$slotEmptyTitle = 'Oyun bulunamadı';
$slotEmptyText = 'Arama teriminizi değiştirmeyi veya filtreleri temizlemeyi deneyin.';
$slotApiParams = [
    'source' => 'aggregator',
];
$sliderApiCategory = 'live_casino';
$slotShowActionButtons = true;
$slotHideProviders = false;

// Desktop CM622 lobby; mobile pages override via $slotMobileOriginalNav.
$slotDesktopLobby = true;
$lobbyMode = $currentSort === ''
    && $searchTerm === ''
    && $selectedProviders === []
    && $viewParam !== 'all';

$lobbyGames = $lobbyMode ? array_slice($games, 0, 24) : [];
$lobbyPopularGames = [];
$lobbyHighWinsGames = [];
$lobbyTournamentsGames = [];
$lobbyMoreGames = [];
$lobbyLiveGames = [];
$lobbyPopularTotal = 0;
$lobbyHighWinsTotal = 0;
$lobbyTournamentsTotal = 0;
$lobbyMoreTotal = 0;
$lobbyLiveTotal = 0;
$lobbyHighWinsProviders = [];
$lobbyTournamentsProviders = [];

$lobbySectionTitles = [
    'primary' => 'CANLI CASİNO',
    'popular' => 'Show Oyunları',
    'highWins' => 'Rulet',
    'tournaments' => 'Blackjack',
    'more' => 'OYUNLAR',
];
$lobbySectionHrefs = [
    'primary' => $slotPageBaseUrl . '?view=all',
    'popular' => $slotPageBaseUrl . '?sort=game-show',
    'highWins' => $slotPageBaseUrl . '?sort=roulette',
    'tournaments' => $slotPageBaseUrl . '?sort=blackjack',
    'more' => $slotPageBaseUrl . '?view=all',
];

if ($lobbyMode) {
    $showResult = SlotGamesQuery::gamesPage(1, '', [], 18, 1, 'game-show', $liveLobbyExtra);
    $lobbyPopularGames = is_array($showResult['games'] ?? null) ? array_values($showResult['games']) : [];
    $lobbyPopularTotal = (int) ($showResult['total'] ?? count($lobbyPopularGames));

    $rouletteResult = SlotGamesQuery::gamesPage(1, '', [], 24, 1, 'roulette', $liveLobbyExtra);
    $lobbyHighWinsGames = is_array($rouletteResult['games'] ?? null) ? array_values($rouletteResult['games']) : [];
    $lobbyHighWinsTotal = (int) ($rouletteResult['total'] ?? count($lobbyHighWinsGames));

    $blackjackResult = SlotGamesQuery::gamesPage(1, '', [], 24, 1, 'blackjack', $liveLobbyExtra);
    $lobbyTournamentsGames = is_array($blackjackResult['games'] ?? null) ? array_values($blackjackResult['games']) : [];
    $lobbyTournamentsTotal = (int) ($blackjackResult['total'] ?? count($lobbyTournamentsGames));

    $moreResult = SlotGamesQuery::gamesPage(1, '', [], 24, 2, '', $liveLobbyExtra);
    $lobbyMoreGames = is_array($moreResult['games'] ?? null) ? array_values($moreResult['games']) : [];
    $lobbyMoreTotal = (int) ($moreResult['total'] ?? $totalSlots);
    if ($lobbyMoreGames === [] && $lobbyGames !== []) {
        $lobbyMoreGames = $lobbyGames;
        $lobbyMoreTotal = $totalSlots;
    }
}

$mobileLiveCasinoView = defined('MOBILE_PATH') ? MOBILE_PATH . '/views/pages/livecasino.php' : '';
if (defined('SURFACE') && SURFACE === 'mobile' && $mobileLiveCasinoView !== '' && is_file($mobileLiveCasinoView)) {
    require $mobileLiveCasinoView;
    return;
}

require VIEW_PATH . '/pages/slot.php';
