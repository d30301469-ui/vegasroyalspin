<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/frontend_session.php';
    metropol_frontend_session_start();
}
require_once __DIR__ . '/../core/bootstrap.php';
require_once SERVICE_PATH . '/SlotGamesQuery.php';
require_once SERVICE_PATH . '/ProviderDisplayBadgeMap.php';

$searchTerm = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$selectedProviders = isset($_GET['providers']) ? (array) $_GET['providers'] : [];
$currentSort = isset($_GET['sort']) ? trim((string) $_GET['sort']) : '';
$limit = 30;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

// Live casino entegrasyonu yok — sayfa bilerek boş.
$games = [];
$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$allUniqueProviders = [];
$totalSlots = 0;
$perPage = $limit;
$currentPage = $page;
$hasNext = false;
$remainingGames = 0;
$showLoadMore = false;
$nextPage = $currentPage + 1;
$apiError = false;

$providerBadges = [
    'pragmatic' => ['EN IYI', 'SICAK'],
    'evolution' => ['EN IYI'],
    'vivo' => ['SICAK'],
    'sagaming' => ['SICAK'],
    'ezugi' => ['OZEL'],
    'creedroomz' => ['OZEL'],
];

$slotPageBaseUrl = '/livecasino';
$slotPageTitle = 'CANLI CASINO';
$slotGameType = 1;
$slotEmptyTitle = 'Canlı casino oyunu bulunamadı';
$slotEmptyText = 'Canlı casino entegrasyonu henüz aktif değil.';
$slotApiParams = [];
$sliderApiCategory = 'live_casino';
$slotShowActionButtons = true;
$slotHideProviders = false;

$mobileLiveCasinoView = defined('MOBILE_PATH') ? MOBILE_PATH . '/views/pages/livecasino.php' : '';
if (defined('SURFACE') && SURFACE === 'mobile' && $mobileLiveCasinoView !== '' && is_file($mobileLiveCasinoView)) {
    require $mobileLiveCasinoView;
    return;
}

require VIEW_PATH . '/pages/slot.php';
