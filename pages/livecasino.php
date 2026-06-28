<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../core/bootstrap.php';
require_once SERVICE_PATH . '/SlotGamesQuery.php';
require_once SERVICE_PATH . '/ProviderDisplayBadgeMap.php';

$searchTerm = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$selectedProviders = isset($_GET['providers']) ? (array) $_GET['providers'] : [];
$currentSort = isset($_GET['sort']) ? trim((string) $_GET['sort']) : '';
$limit = 30;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

$result = SlotGamesQuery::gamesPage(1, $searchTerm, $selectedProviders, $limit, $page, $currentSort, ['source' => 'drakon']);
$games = $result['games'] ?? [];
$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$allUniqueProviders = SlotGamesQuery::providersForGameType(1, 'live_casino');
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
    'evolution' => ['EN IYI'],
    'vivo' => ['SICAK'],
    'sagaming' => ['SICAK'],
    'ezugi' => ['OZEL'],
    'creedroomz' => ['OZEL'],
];
sort($allUniqueProviders, SORT_NATURAL | SORT_FLAG_CASE);

$slotPageBaseUrl = '/livecasino';
$slotPageTitle = 'CANLI CASINO';
$slotGameType = 1;
$slotEmptyTitle = 'Canlı casino oyunu bulunamadı';
$slotEmptyText = 'Arama teriminizi değiştirmeyi veya filtreleri temizlemeyi deneyin.';
$slotApiParams = ['source' => 'drakon'];
$sliderApiCategory = 'live_casino';
$slotShowActionButtons = true;
$slotHideProviders = false;

require VIEW_PATH . '/pages/slot.php';
