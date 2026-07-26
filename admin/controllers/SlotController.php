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

        // Softswiss/slot entegrasyonu yok — sayfa bilerek boş.
        // Aktif slot kataloğu yalnızca BGaming (/bgaming).
        $games              = [];

        $allUniqueProviders = [];

        $totalSlots         = 0;

        $perPage            = $limit;

        $currentPage        = $page;

        $hasNext            = false;

        $remainingGames     = 0;

        $showLoadMore       = false;

        $nextPage           = $currentPage + 1;

        $providerBadges = $this->getProviderBadges();

        $slotApiParams = [];

        $slotGameType = 0;

        $slotShowActionButtons = true;

        $apiError = false;

        $slotEmptyTitle = 'Slot oyunu bulunamadı';

        $slotEmptyText = 'Slot sağlayıcı entegrasyonu henüz aktif değil. BGaming oyunları için /bgaming sayfasını kullanın.';

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

