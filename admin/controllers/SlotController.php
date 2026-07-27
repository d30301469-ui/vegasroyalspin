<?php

require_once SERVICE_PATH . '/SlotGamesQuery.php';
require_once SERVICE_PATH . '/ProviderDisplayBadgeMap.php';
require_once SERVICE_PATH . '/CasinoAggregatorService.php';

class SlotController extends Controller
{
    public function index(): void
    {
        $searchTerm        = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
        $selectedProviders = array_values(array_filter(array_map(
            static fn ($provider): string => CasinoAggregatorService::resolveLocalizedLabel(trim((string) $provider)),
            isset($_GET['providers']) ? (array) $_GET['providers'] : []
        ), static fn (string $provider): bool => $provider !== ''));
        $currentSort       = isset($_GET['sort']) ? trim((string) $_GET['sort']) : '';
        $limit             = 30;
        $page              = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

        $result = SlotGamesQuery::slotsPage($searchTerm, $selectedProviders, $limit, $page, $currentSort);
        $games = array_values(array_filter(
            is_array($result['games'] ?? null) ? $result['games'] : [],
            static function (array $game): bool {
                $provider = strtolower(trim((string) ($game['provider'] ?? $game['provider_code'] ?? '')));
                $source = strtolower(trim((string) ($game['source'] ?? '')));

                return $provider !== 'bgaming' && $source !== 'bgaming';
            }
        ));

        $allUniqueProviders = array_values(array_filter(
            SlotGamesQuery::allProviders(),
            static function (string $provider): bool {
                return stripos($provider, 'bgaming') === false && stripos($provider, 'b gaming') === false;
            }
        ));
        sort($allUniqueProviders, SORT_NATURAL | SORT_FLAG_CASE);

        $totalSlots     = (int) ($result['total'] ?? count($games));
        $perPage        = (int) ($result['perPage'] ?? $limit);
        $currentPage    = (int) ($result['page'] ?? $page);
        $hasNext        = !empty($result['hasNext']);
        $loadedCount    = ($currentPage - 1) * $perPage + count($games);
        $remainingGames = max(0, $totalSlots - $loadedCount);
        $showLoadMore   = $hasNext && $remainingGames > 0;
        $nextPage       = $currentPage + 1;
        $apiError       = !empty($result['apiError']);

        $providerBadges = $this->getProviderBadges();
        $slotApiParams  = [];
        $slotGameType   = 0;
        $slotShowActionButtons = true;
        $slotEmptyTitle = 'Slot oyunu bulunamadı';
        $slotEmptyText  = 'Arama teriminizi değiştirmeyi veya filtreleri temizlemeyi deneyin.';

        $this->view('pages/slot', compact(
            'searchTerm', 'selectedProviders', 'currentSort',
            'limit', 'page', 'currentPage', 'nextPage', 'games', 'allUniqueProviders',
            'totalSlots', 'remainingGames', 'showLoadMore', 'providerBadges',
            'perPage', 'hasNext', 'slotApiParams', 'slotShowActionButtons', 'slotGameType', 'apiError',
            'slotEmptyTitle', 'slotEmptyText'
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
