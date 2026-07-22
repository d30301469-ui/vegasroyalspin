<?php



require_once SERVICE_PATH . '/SlotGamesQuery.php';
require_once SERVICE_PATH . '/ProviderDisplayBadgeMap.php';



class SlotController extends Controller

{

    public function index(): void

    {

        $searchTerm        = isset($_GET['search']) ? trim($_GET['search']) : '';

        $selectedProviders = isset($_GET['providers']) ? (array) $_GET['providers'] : [];

        $currentSort       = isset($_GET['sort']) ? trim($_GET['sort']) : '';

        $limit             = 30;

        $page              = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;



        $result             = SlotGamesQuery::slotsPage($searchTerm, $selectedProviders, $limit, $page, $currentSort, []);

        $games              = array_values(array_filter($result['games'], static function (array $game): bool {
            $provider = strtolower(trim((string) ($game['provider'] ?? $game['provider_code'] ?? '')));
            $source = strtolower(trim((string) ($game['source'] ?? '')));

            return $provider !== 'bgaming' && $source !== 'bgaming';
        }));

        $allUniqueProviders = array_values(array_filter(SlotGamesQuery::allProviders(), static function (string $provider): bool {
            return stripos($provider, 'bgaming') === false && stripos($provider, 'b gaming') === false;
        }));

        $totalSlots         = $result['total'];

        $perPage            = $result['perPage'];

        $currentPage        = $result['page'];

        $hasNext            = $result['hasNext'];

        $loadedCount        = ($currentPage - 1) * $perPage + count($games);

        $remainingGames     = max(0, $totalSlots - $loadedCount);

        $showLoadMore       = $hasNext && $remainingGames > 0;

        $nextPage           = $currentPage + 1;

        $providerBadges = $this->getProviderBadges();

        $slotApiParams = [];

        $slotGameType = 0;

        $slotShowActionButtons = true;

        $apiError = !empty($result['apiError']);

        sort($allUniqueProviders);

        $this->view('pages/slot', compact(

            'searchTerm', 'selectedProviders', 'currentSort',

            'limit', 'page', 'currentPage', 'nextPage', 'games', 'allUniqueProviders',

            'totalSlots', 'remainingGames', 'showLoadMore', 'providerBadges',

            'perPage', 'hasNext', 'slotApiParams', 'slotShowActionButtons', 'slotGameType', 'apiError'

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

