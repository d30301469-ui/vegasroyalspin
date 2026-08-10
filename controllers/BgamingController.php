<?php

require_once SERVICE_PATH . '/BgamingGamesQuery.php';
require_once SERVICE_PATH . '/ProviderDisplayBadgeMap.php';
require_once SERVICE_PATH . '/ProviderLogoSvgMap.php';

/**
 * Dedicated BGaming page (direct SoftSwiss).
 * Own view/JS/CSS (pages/bgaming + bgaming.js + bc-cm622-bgaming.css).
 * Catalogue from BgamingGamesQuery — never SlotGamesQuery / pages/slot.
 */
class BgamingController extends Controller
{
    public function index(): void
    {
        $searchTerm = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
        $currentSort = isset($_GET['sort']) ? trim((string) $_GET['sort']) : '';
        $viewParam = isset($_GET['view']) ? trim((string) $_GET['view']) : '';
        $limit = 30;
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $sortForQuery = ($currentSort === 'all') ? '' : $currentSort;
        $selectedProviders = [];

        $result = BgamingGamesQuery::page($searchTerm, $limit, $page, $sortForQuery);
        $games = is_array($result['games'] ?? null) ? array_values($result['games']) : [];
        $pagination = is_array($result['pagination'] ?? null) ? $result['pagination'] : [];

        $loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
        $totalSlots = (int) ($pagination['total'] ?? $result['total'] ?? count($games));
        $perPage = (int) ($pagination['perPage'] ?? $result['perPage'] ?? $limit);
        $currentPage = (int) ($pagination['page'] ?? $result['page'] ?? $page);
        $hasNext = !empty($pagination['hasNext'] ?? $result['hasNext']);
        $loadedCount = ($currentPage - 1) * $perPage + count($games);
        $remainingGames = max(0, $totalSlots - $loadedCount);
        $showLoadMore = $hasNext && $remainingGames > 0;
        $nextPage = $currentPage + 1;
        $apiError = !empty($result['apiError']);

        $providerBadges = [];
        $allUniqueProviders = ['BGaming'];
        $slotEmptyTitle = 'BGaming oyunu bulunamadı';
        $slotEmptyText = 'Admin panelinden BGaming oyun sync çalıştırın veya arama terimini değiştirin.';
        $slotPageBaseUrl = '/bgaming';
        $slotApiParams = ['source' => 'bgaming'];
        $slotGameType = 0;
        $slotShowActionButtons = true;
        $slotHideProviders = true;
        $sliderApiCategory = 'bgaming';

        $slotMobileOriginalNav = false;
        $slotDesktopLobby = true;
        $lobbyMode = $currentSort === ''
            && $searchTerm === ''
            && $selectedProviders === []
            && $viewParam !== 'all';

        $lobbyGames = [];
        $lobbyPopularGames = [];
        $lobbyHighWinsGames = [];
        $lobbyTournamentsGames = [];
        $lobbyMoreGames = [];
        $lobbyPopularTotal = 0;
        $lobbyHighWinsTotal = 0;
        $lobbyTournamentsTotal = 0;
        $lobbyMoreTotal = 0;
        $lobbyHighWinsProviders = [];
        $lobbyTournamentsProviders = [];

        if ($lobbyMode) {
            $lobbyGames = array_slice($games, 0, 24);

            $popularResult = BgamingGamesQuery::page('', 18, 1, 'popular');
            $lobbyPopularGames = is_array($popularResult['games'] ?? null)
                ? array_values($popularResult['games'])
                : [];
            $lobbyPopularTotal = (int) ($popularResult['total'] ?? count($lobbyPopularGames));

            // "new" fills the high-wins carousel when there is no multi-vendor catalogue.
            $newResult = BgamingGamesQuery::page('', 24, 1, 'new');
            $lobbyHighWinsGames = is_array($newResult['games'] ?? null)
                ? array_values($newResult['games'])
                : [];
            $lobbyHighWinsTotal = (int) ($newResult['total'] ?? count($lobbyHighWinsGames));

            $moreResult = BgamingGamesQuery::page('', 24, 2, '');
            $lobbyMoreGames = is_array($moreResult['games'] ?? null)
                ? array_values($moreResult['games'])
                : [];
            $lobbyMoreTotal = (int) ($moreResult['total'] ?? $totalSlots);
            if ($lobbyMoreGames === [] && $lobbyGames !== []) {
                $lobbyMoreGames = $lobbyGames;
                $lobbyMoreTotal = $totalSlots;
            }
        }

        $this->view('pages/bgaming', compact(
            'searchTerm',
            'selectedProviders',
            'currentSort',
            'limit',
            'page',
            'currentPage',
            'nextPage',
            'games',
            'allUniqueProviders',
            'totalSlots',
            'remainingGames',
            'showLoadMore',
            'providerBadges',
            'perPage',
            'hasNext',
            'slotApiParams',
            'slotShowActionButtons',
            'slotGameType',
            'apiError',
            'slotEmptyTitle',
            'slotEmptyText',
            'slotPageBaseUrl',
            'slotHideProviders',
            'slotDesktopLobby',
            'slotMobileOriginalNav',
            'lobbyMode',
            'lobbyGames',
            'lobbyPopularGames',
            'lobbyHighWinsGames',
            'lobbyTournamentsGames',
            'lobbyMoreGames',
            'lobbyPopularTotal',
            'lobbyHighWinsTotal',
            'lobbyTournamentsTotal',
            'lobbyMoreTotal',
            'lobbyHighWinsProviders',
            'lobbyTournamentsProviders',
            'viewParam',
            'sliderApiCategory',
            'loggedIn'
        ));
    }
}
