<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/frontend_session.php';
    metropol_frontend_session_start();
}
require_once __DIR__ . '/../core/bootstrap.php';
require_once SERVICE_PATH . '/SlotGamesQuery.php';
require_once SERVICE_PATH . '/ProviderDisplayBadgeMap.php';

$searchTerm = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$selectedProviders = ['pragmatic-virtual'];
$currentSort = isset($_GET['sort']) ? trim((string) $_GET['sort']) : '';
$limit = 30;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

$result = SlotGamesQuery::gamesPage(1, $searchTerm, $selectedProviders, $limit, $page, $currentSort);
$games = $result['games'] ?? [];
$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$allUniqueProviders = ['pragmatic-virtual'];
$totalSlots = (int) ($result['total'] ?? count($games));
$perPage = (int) ($result['perPage'] ?? $limit);
$currentPage = (int) ($result['page'] ?? $page);
$hasNext = !empty($result['hasNext']);
$loadedCount = ($currentPage - 1) * $perPage + count($games);
$remainingGames = max(0, $totalSlots - $loadedCount);
$showLoadMore = $hasNext && $remainingGames > 0;
$nextPage = $currentPage + 1;
$providerBadges = ['pragmatic-virtual' => ['SICAK']];
$slotPageBaseUrl = '/sanal-sporlar';
$slotPageTitle = 'SANAL SPORLAR';
$slotEmptyTitle = 'Pragmatic Play Virtual oyunu bulunamadı';
$slotEmptyText = 'Admin panelinden Drakon oyun sync çalıştırın veya arama terimini değiştirin.';
$slotApiParams = ['provider' => 'pragmatic-virtual'];
$slotGameType = 1;

require VIEW_PATH . '/pages/slot.php';
