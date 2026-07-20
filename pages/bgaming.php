<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/frontend_session.php';
    metropol_frontend_session_start();
}
require_once __DIR__ . '/../core/bootstrap.php';
require_once SERVICE_PATH . '/SlotGamesQuery.php';
require_once SERVICE_PATH . '/ProviderDisplayBadgeMap.php';

$searchTerm = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$selectedProviders = isset($_GET['providers']) ? (array) $_GET['providers'] : ['bgaming'];
$currentSort = isset($_GET['sort']) ? trim((string) $_GET['sort']) : '';
$limit = 30;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

$result = SlotGamesQuery::gamesPage(0, $searchTerm, $selectedProviders, $limit, $page, $currentSort, ['source' => 'bgaming']);
$games = $result['games'] ?? [];
$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$allUniqueProviders = ['bgaming'];
$totalSlots = (int) ($result['total'] ?? count($games));
$perPage = (int) ($result['perPage'] ?? $limit);
$currentPage = (int) ($result['page'] ?? $page);
$hasNext = !empty($result['hasNext']);
$loadedCount = ($currentPage - 1) * $perPage + count($games);
$remainingGames = max(0, $totalSlots - $loadedCount);
$showLoadMore = $hasNext && $remainingGames > 0;
$nextPage = $currentPage + 1;
$apiError = !empty($result['apiError']);

$providerBadges = ['bgaming' => ['SICAK']];
$slotPageBaseUrl = '/bgaming';
$slotPageTitle = 'BGAMING';
$slotGameType = 0;
$slotEmptyTitle = 'BGaming oyunu bulunamadı';
$slotEmptyText = 'Admin panelinden BGaming oyun sync çalıştırın veya arama terimini değiştirin.';
$slotApiParams = ['source' => 'bgaming'];
$sliderApiCategory = 'bgaming';
$slotShowActionButtons = true;
$slotHideProviders = true;

$mobileBgamingView = defined('MOBILE_PATH') ? MOBILE_PATH . '/views/pages/bgaming.php' : '';
if (defined('SURFACE') && SURFACE === 'mobile' && $mobileBgamingView !== '' && is_file($mobileBgamingView)) {
    require $mobileBgamingView;
    return;
}

require VIEW_PATH . '/pages/slot.php';
