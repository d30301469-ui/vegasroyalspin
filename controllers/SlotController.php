<?php

require_once SERVICE_PATH . '/SlotGamesQuery.php';
require_once SERVICE_PATH . '/ProviderDisplayBadgeMap.php';
require_once SERVICE_PATH . '/ProviderLogoSvgMap.php';
require_once SERVICE_PATH . '/CasinoAggregatorService.php';

class SlotController extends Controller
{
    public function index(): void
    {
        $searchTerm        = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
        $selectedProviders = CasinoAggregatorService::canonicalizeProviders(
            CasinoAggregatorService::providersFromQuery(),
            SlotGamesQuery::allProviders()
        );
        $currentSort       = isset($_GET['sort']) ? trim((string) $_GET['sort']) : '';
        $viewParam         = isset($_GET['view']) ? trim((string) $_GET['view']) : '';
        $limit             = 30;
        $page              = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

        $slotSourceExtra = ['source' => 'aggregator'];
        $slotDesktopLobby = true;
        $lobbyMode = $currentSort === ''
            && $searchTerm === ''
            && $selectedProviders === []
            && $viewParam !== 'all';

        $lobbyBundle = null;
        if ($lobbyMode) {
            $lobbyBundle = SlotGamesQuery::slotLobbyBundle($slotSourceExtra);
            $result = is_array($lobbyBundle['main'] ?? null) ? $lobbyBundle['main'] : SlotGamesQuery::slotsPage(
                $searchTerm,
                $selectedProviders,
                $limit,
                $page,
                $currentSort === 'all' ? '' : $currentSort,
                $slotSourceExtra
            );
            $allUniqueProviders = is_array($lobbyBundle['allUniqueProviders'] ?? null)
                ? $lobbyBundle['allUniqueProviders']
                : [];
        } else {
            $result = SlotGamesQuery::slotsPage(
                $searchTerm,
                $selectedProviders,
                $limit,
                $page,
                $currentSort === 'all' ? '' : $currentSort,
                $slotSourceExtra
            );
            $allUniqueProviders = array_values(array_filter(
                SlotGamesQuery::allProviders(),
                static function (string $provider): bool {
                    return stripos($provider, 'bgaming') === false && stripos($provider, 'b gaming') === false;
                }
            ));
            sort($allUniqueProviders, SORT_NATURAL | SORT_FLAG_CASE);
        }
        $games = is_array($result['games'] ?? null) ? array_values($result['games']) : [];

        $totalSlots     = (int) ($result['total'] ?? count($games));
        $perPage        = (int) ($result['perPage'] ?? $limit);
        $currentPage    = (int) ($result['page'] ?? $page);
        $hasNext        = !empty($result['hasNext']);
        $loadedCount    = ($currentPage - 1) * $perPage + count($games);
        $remainingGames = max(0, $totalSlots - $loadedCount);
        $showLoadMore   = $hasNext && $remainingGames > 0;
        $nextPage       = $currentPage + 1;
        $apiError       = !empty($result['apiError']) || ($lobbyBundle !== null && !empty($lobbyBundle['apiError']));

        $providerBadges = $this->getProviderBadges();
        $slotApiParams  = ['source' => 'aggregator'];
        $slotGameType   = 0;
        $slotShowActionButtons = true;
        $slotEmptyTitle = 'Slot oyunu bulunamadı';
        $slotEmptyText  = 'Arama teriminizi değiştirmeyi veya filtreleri temizlemeyi deneyin.';

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

        if ($lobbyMode && $lobbyBundle !== null) {
            $popularResult = is_array($lobbyBundle['popular'] ?? null) ? $lobbyBundle['popular'] : [];
            $lobbyPopularGames = is_array($popularResult['games'] ?? null)
                ? array_values($popularResult['games'])
                : [];
            $lobbyPopularTotal = (int) ($popularResult['total'] ?? count($lobbyPopularGames));

            $lobbyHighWinsProviders = is_array($lobbyBundle['highWinsProviders'] ?? null)
                ? $lobbyBundle['highWinsProviders']
                : [];
            $lobbyTournamentsProviders = is_array($lobbyBundle['tournamentsProviders'] ?? null)
                ? $lobbyBundle['tournamentsProviders']
                : [];

            $highWinsResult = is_array($lobbyBundle['highWins'] ?? null) ? $lobbyBundle['highWins'] : [];
            $lobbyHighWinsGames = is_array($highWinsResult['games'] ?? null)
                ? array_values($highWinsResult['games'])
                : [];
            $lobbyHighWinsTotal = (int) ($highWinsResult['total'] ?? count($lobbyHighWinsGames));

            $tournamentsResult = is_array($lobbyBundle['tournaments'] ?? null) ? $lobbyBundle['tournaments'] : [];
            $lobbyTournamentsGames = is_array($tournamentsResult['games'] ?? null)
                ? array_values($tournamentsResult['games'])
                : [];
            $lobbyTournamentsTotal = (int) ($tournamentsResult['total'] ?? count($lobbyTournamentsGames));

            $moreResult = is_array($lobbyBundle['more'] ?? null) ? $lobbyBundle['more'] : [];
            $lobbyMoreGames = is_array($moreResult['games'] ?? null)
                ? array_values($moreResult['games'])
                : [];
            $lobbyMoreTotal = (int) ($moreResult['total'] ?? $totalSlots);
            if ($lobbyMoreGames === [] && $lobbyGames !== []) {
                $lobbyMoreGames = $lobbyGames;
                $lobbyMoreTotal = $totalSlots;
            }

            $liveResult = is_array($lobbyBundle['live'] ?? null) ? $lobbyBundle['live'] : [];
            $lobbyLiveGames = is_array($liveResult['games'] ?? null)
                ? array_values($liveResult['games'])
                : [];
            $lobbyLiveTotal = (int) ($liveResult['total'] ?? count($lobbyLiveGames));
            $apiError = !empty($lobbyBundle['apiError']) || $apiError;
        }

        $this->view('pages/slot', compact(
            'searchTerm', 'selectedProviders', 'currentSort',
            'limit', 'page', 'currentPage', 'nextPage', 'games', 'allUniqueProviders',
            'totalSlots', 'remainingGames', 'showLoadMore', 'providerBadges',
            'perPage', 'hasNext', 'slotApiParams', 'slotShowActionButtons', 'slotGameType', 'apiError',
            'slotEmptyTitle', 'slotEmptyText',
            'slotDesktopLobby', 'lobbyMode', 'lobbyGames', 'lobbyPopularGames', 'lobbyHighWinsGames',
            'lobbyTournamentsGames', 'lobbyMoreGames', 'lobbyLiveGames', 'lobbyPopularTotal',
            'lobbyHighWinsTotal', 'lobbyTournamentsTotal', 'lobbyMoreTotal',
            'lobbyHighWinsProviders', 'lobbyTournamentsProviders',
            'lobbyLiveTotal', 'viewParam'
        ));
    }

    private function getProviderBadges(): array
    {
        return [
            'pragmatic'       => ['EN İYİ', 'JACKPOT', 'SICAK'],
            'pgsoft'          => ['SICAK'],
            'spribe'          => ['JACKPOT', 'SICAK'],
            'hacksaw'         => ['EN İYİ', 'SICAK'],
            'nolimitcity-A'   => ['JACKPOT'],
            'evoplay'         => ['EN İYİ'],
            'play-son'        => [],
            'booming'         => ['JACKPOT'],
            'quickspin'       => ['EN İYİ', 'SICAK'],
            'amusnet'         => ['JACKPOT'],
            'egt-digital'     => ['JACKPOT'],
            'egtdigital'      => ['JACKPOT'],
            'voltent'         => ['JACKPOT'],
            'popok'           => ['PROMOSYON'],
            'popok-gaming'    => ['PROMOSYON'],
            'habanero'        => ['ÖZEL'],
        ];
    }
}
