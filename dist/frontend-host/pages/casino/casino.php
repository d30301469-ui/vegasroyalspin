<?php
/**
 * Tekil casino sayfası - ?default= ile varsayılan sağlayıcı (platipus, pragmatic, evolution, vb.)
 * Örnek: casino.php?default=platipus | casino.php?default=evolution
 */
$defaultProvider = isset($_GET['default']) ? trim($_GET['default']) : 'platipus';
$defaultProvider = preg_replace('/[^a-zA-Z0-9\-]/', '', $defaultProvider);
if ($defaultProvider === '') {
    $defaultProvider = 'platipus';
}

require_once __DIR__ . '/../../views/layouts/head_full.php';
include __DIR__ . '/../../views/partials/header.php';
?>
</div>

<?php
require_once __DIR__ . '/../../services/BackendApiClient.php';

function searchGames($searchTerm)
{
    $j = BackendApiClient::request('GET', BackendApiClient::SVC_GAMES, '/games/search', [
        'search'   => $searchTerm,
        'provider' => 'hepsi',
        'sort'     => 'name_asc',
    ]);
    if ($j === null) {
        return [];
    }
    $u = BackendApiClient::unwrap($j);
    return $u['games'] ?? $j['games'] ?? [];
}

function getGameCount()
{
    $j = BackendApiClient::request('GET', BackendApiClient::SVC_GAMES, '/games/search', [
        'search'   => '',
        'provider' => 'hepsi',
        'limit'    => 1,
        'offset'   => 0,
    ]);
    if ($j === null) {
        return 0;
    }
    return (int) ($j['total'] ?? BackendApiClient::unwrap($j)['total'] ?? 0);
}

$total_games = getGameCount();
?>

<style>
    .iframe-popup {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 80%;
        height: 80%;
        background-color: white;
        border-radius: 10px;
        border: 1px solid #ccc;
        z-index: 9999;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    .iframe-popup iframe {
        width: 100%;
        height: 100%;
        border: none;
    }

    .overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        z-index: 9998;
    }

    .flex-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

   .vendor-item {
    display: inline-block;
    margin-right: 10px;
    text-align: center;
    cursor: pointer;
    transition: transform 0.2s, width 0.2s;
    width: 150px;
    height: 120px;
    overflow: hidden;
    position: relative;
    color: #000;
    font-size: 12px;
}

    .vendor-item img {
        width: 100%;
        height: auto;
        display: block;
    }

    .vendor-item .type {
        display: block;
        margin-top: 5px;
        color: #fff;
    }

    .vendor-item:hover {
        transform: scale(1.05);
    }

    .vendor-item.active {
        transform: scale(1.1);
        width: 100px;
        background-color: rgba(0, 0, 0, 0.5);
        border-radius: 5px;
    }

    .horizontal-scroll {
        overflow-x: auto;
        white-space: nowrap;
        padding: 10px 0;
    }

@media (max-width: 768px) {
    .vendor-item {
        width: 130px;
        height: 120px;
    }

        .vendor-item .type {
            font-size: 10px;
        }
    }
</style>

<script>
    function openIframe(url) {
        const iframePopup = document.getElementById('iframePopup');
        const overlay = document.getElementById('overlay');
        const iframe = document.getElementById('gameIframe');
        iframe.src = url;
        iframePopup.style.display = 'block';
        overlay.style.display = 'block';
    }

    function closeIframe() {
        const iframePopup = document.getElementById('iframePopup');
        const overlay = document.getElementById('overlay');
        const iframe = document.getElementById('gameIframe');
        iframe.src = '';
        iframePopup.style.display = 'none';
        overlay.style.display = 'none';
    }
</script>

<div class="overlay" id="overlay" onclick="closeIframe()"></div>
<div class="iframe-popup" id="iframePopup">
    <button onclick="closeIframe()" style="position: absolute; top: 10px; right: 10px;">Kapat</button>
    <iframe id="gameIframe" src=""></iframe>
</div>

<div class="horizontal-scroll" id="vendorsList">
    <div class="vendor-item vendorId-0 active" onclick="loadGames('hepsi', this)">
        <img src="https://garsbet.com/provider/images/hepsi.jpg" alt="Hepsi" width="120" height="60" />
        <span class="type">Hepsi</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('pragmatic', this)">
        <img src="https://garsbet.com/provider/images/pragmatic.jpg" alt="pragmatic" width="120" height="60" />
        <span class="type">PRAGMATİC</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('joingames', this)">
        <img src="https://garsbet.com/provider/images/joingames.jpg" alt="joingames" width="120" height="60" />
        <span class="type">JOİN GAMES</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('macawgaming', this)">
        <img src="https://garsbet.com/provider/images/macawgaming.jpg" alt="macawgaming" />
        <span class="type">Macaw Gaming</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('boominggames', this)">
        <img src="https://garsbet.com/provider/images/boominggames.jpg" alt="boominggames" width="120" height="60" />
        <span class="type">Booming Games</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('armadillostudios', this)">
        <img src="https://garsbet.com/provider/images/armadillostudios.jpg" alt="armadillostudios" />
        <span class="type">Armadillo Studios</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('bgaming', this)">
        <img src="https://garsbet.com/provider/images/bgaming.jpg" alt="bgaming" width="120" height="60" />
        <span class="type">B GAMiNG</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('adlunam', this)">
        <img src="https://garsbet.com/provider/images/adlunam.jpg" alt="adlunam" />
        <span class="type">AD Lunam</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('1x2', this)">
        <img src="https://garsbet.com/provider/images/1x2.jpg" alt="1x2" width="120" height="60" />
        <span class="type">1x2 Gaming</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('prospectgaming', this)">
        <img src="https://garsbet.com/provider/images/prospectgaming.jpg" alt="prospectgaming" />
        <span class="type">Prospect Gaming</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('jvl', this)">
        <img src="https://garsbet.com/provider/images/jvl.jpg" alt="jvl" width="120" height="60" />
        <span class="type">JVL</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('irondog', this)">
        <img src="https://garsbet.com/provider/images/irondog.jpg" alt="irondog" />
        <span class="type">İron Dog Studio</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('bigtimegaming', this)">
        <img src="https://garsbet.com/provider/images/bigtimegaming.jpg" alt="bigtimegaming" width="120" height="60" />
        <span class="type">Big Time Gaming</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('amigogaming', this)">
        <img src="https://garsbet.com/provider/images/amigogaming.jpg" alt="amigogaming" />
        <span class="type">Amigo Gaming</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('kagaming', this)">
        <img src="https://garsbet.com/provider/images/kagaming.jpg" alt="kagaming" width="120" height="60" />
        <span class="type">KA Gaming</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('netgame', this)">
        <img src="https://garsbet.com/provider/images/netgame.jpg" alt="netgame" />
        <span class="type">Net Game</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('rubyplay', this)">
        <img src="https://garsbet.com/provider/images/rubyplay.jpg" alt="rubyplay" width="120" height="60" />
        <span class="type">Ruby Play</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('pateplay', this)">
        <img src="https://garsbet.com/provider/images/pateplay.jpg" alt="pateplay" />
        <span class="type">Pateplay</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('nolimitcity', this)">
        <img src="https://garsbet.com/provider/images/nolimitcity.jpg" alt="nolimitcity" width="120" height="60" />
        <span class="type">Nolimit City</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('evoplay', this)">
        <img src="https://garsbet.com/provider/images/evoplay.jpg" alt="evoplay" />
        <span class="type">Evoplay</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('slotopia', this)">
        <img src="https://garsbet.com/provider/images/slotopia.jpg" alt="slotopia" width="120" height="60" />
        <span class="type">Slotopia</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('habanero', this)">
        <img src="https://garsbet.com/provider/images/habanero.jpg" alt="habanero" />
        <span class="type">Habanero</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('bigpotgaming', this)">
        <img src="https://garsbet.com/provider/images/bigpotgaming.jpg" alt="bigpotgaming" width="120" height="60" />
        <span class="type">Bigpot Gaming</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('skywind', this)">
        <img src="https://garsbet.com/provider/images/skywind.jpg" alt="skywind" />
        <span class="type">Skywind Group</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('caletagaming', this)">
        <img src="https://garsbet.com/provider/images/caletagaming.jpg" alt="caletagaming" width="120" height="60" />
        <span class="type">Caleta Gaming</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('7mojos', this)">
        <img src="https://garsbet.com/provider/images/7mojos.jpg" alt="7mojos" />
        <span class="type">7 Mojos</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('hacksaw', this)">
        <img src="https://garsbet.com/provider/images/hacksaw.jpg" alt="hacksaw" width="120" height="60" />
        <span class="type">Hacksaw Gaming</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('backseat', this)">
        <img src="https://garsbet.com/provider/images/backseat.jpg" alt="backseat" />
        <span class="type">Backseat Gaming</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('bullshark', this)">
        <img src="https://garsbet.com/provider/images/bullshark.jpg" alt="bullshark" width="120" height="60" />
        <span class="type">Bullshark Games</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('popok', this)">
        <img src="https://garsbet.com/provider/images/popok.jpg" alt="popok" />
        <span class="type">Popok Gaming</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('platipus', this)">
        <img src="https://garsbet.com/provider/images/platipus.jpg" alt="platipus" width="120" height="60" />
        <span class="type">Platipus</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('mancala', this)">
        <img src="https://garsbet.com/provider/images/mancala.jpg" alt="mancala" />
        <span class="type">Mancala Gaming</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('retrogames', this)">
        <img src="https://garsbet.com/provider/images/retrogames.jpg" alt="retrogames" width="120" height="60" />
        <span class="type">Retro Gaming</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('spinomenal', this)">
        <img src="https://garsbet.com/provider/images/spinomenal.jpg" alt="spinomenal" />
        <span class="type">Spinomenal</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('fils', this)">
        <img src="https://garsbet.com/provider/images/fils.jpg" alt="fils" width="120" height="60" />
        <span class="type">Fils Game</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('smartsoft', this)">
        <img src="https://garsbet.com/provider/images/smartsoft.jpg" alt="smartsoft" />
        <span class="type">Smartsoft Gaming</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('onetouch', this)">
        <img src="https://garsbet.com/provider/images/onetouch.jpg" alt="onetouch" width="120" height="60" />
        <span class="type">One Touch</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('spearhead', this)">
        <img src="https://garsbet.com/provider/images/spearhead.jpg" alt="spearhead" />
        <span class="type">Spearhead Studios</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('betsoft', this)">
        <img src="https://garsbet.com/provider/images/betsoft.jpg" alt="betsoft" width="120" height="60" />
        <span class="type">Betsoft</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('pgsoft', this)">
        <img src="https://garsbet.com/provider/images/pgsoft.jpg" alt="pgsoft" />
        <span class="type">PG Soft</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('platingaming', this)">
        <img src="https://garsbet.com/provider/images/platingaming.jpg" alt="platingaming" width="120" height="60" />
        <span class="type">Platin Gaming</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('7777', this)">
        <img src="https://garsbet.com/provider/images/7777.jpg" alt="7777" />
        <span class="type">7777 Gaming</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('micro-gaming', this)">
        <img src="https://garsbet.com/provider/images/micro-gaming.jpg" alt="micro-gaming" width="120" height="60" />
        <span class="type">Microgaming</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('gamzix', this)">
        <img src="https://garsbet.com/provider/images/gamzix.jpg" alt="gamzix" />
        <span class="type">GAMZİX</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('redrake', this)">
        <img src="https://garsbet.com/provider/images/redrake.jpg" alt="redrake" width="120" height="60" />
        <span class="type">Red Rake</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('aviatrix', this)">
        <img src="https://garsbet.com/provider/images/aviatrix.jpg" alt="aviatrix" />
        <span class="type">Aviatrix</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('waz-dan', this)">
        <img src="https://garsbet.com/provider/images/waz-dan.jpg" alt="waz-dan" width="120" height="60" />
        <span class="type">Wazdan</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('galaxsys', this)">
        <img src="https://garsbet.com/provider/images/galaxsys.jpg" alt="galaxsys" width="120" height="60" />
        <span class="type">Galaxsys</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('ctinteractive', this)">
        <img src="https://garsbet.com/provider/images/ctinteractive.jpg" alt="ctinteractive" width="120" height="60" />
        <span class="type">CT Gaming</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('3oaks', this)">
        <img src="https://garsbet.com/provider/images/3oaks.jpg" alt="3oaks" width="120" height="60" />
        <span class="type">3 Oaks</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('play-son', this)">
        <img src="https://garsbet.com/provider/images/play-son.jpg" alt="play-son" width="120" height="60" />
        <span class="type">Playson</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('fazi', this)">
        <img src="https://garsbet.com/provider/images/fazi.jpg" alt="fazi" />
        <span class="type">Fazi</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('redstone', this)">
        <img src="https://garsbet.com/provider/images/redstone.jpg" alt="redstone" width="120" height="60" />
        <span class="type">Redstone</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('tiptop', this)">
        <img src="https://garsbet.com/provider/images/tiptop.jpg" alt="tiptop" />
        <span class="type">TipTop</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('spribe-gh', this)">
        <img src="https://garsbet.com/provider/images/spribe-gh.jpg" alt="spribe-gh" width="120" height="60" />
        <span class="type">Spribe</span>
    </div>
    <div class="vendor-item vendorId-156" onclick="loadGames('redtiger-gh', this)">
        <img src="https://garsbet.com/provider/images/redtiger-gh.jpg" alt="redtiger-gh" />
        <span class="type">Red Tiger</span>
    </div>
    <div class="vendor-item vendorId-115" onclick="loadGames('netent-gh', this)">
        <img src="https://garsbet.com/provider/images/netent-gh.jpg" alt="netent-gh" width="120" height="60" />
        <span class="type">NetEnt</span>
    </div>
</div>

<script>
const BetcoShared = window.BetcoAuthShared || {};
function casinoApiUrl(path) {
    return BetcoShared.apiUrl ? BetcoShared.apiUrl(path) : path;
}
function loadGames(provider, element) {
    const isHepsi = (provider === 'hepsi');
    const filePath = isHepsi
        ? casinoApiUrl('/pages/games/hepsi.php?type=casino')
        : casinoApiUrl(`/pages/games/games.php?type=casino&provider=${encodeURIComponent(provider)}`);
    fetch(filePath)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.text();
        })
        .then(data => {
            document.getElementById('gamesContainer').innerHTML = data;
            updateActiveVendor(element);
        })
        .catch(error => {
            console.error('Error loading the games:', error);
        });
}

function updateActiveVendor(selectedElement) {
    document.querySelectorAll('.vendor-item').forEach(item => item.classList.remove('active'));
    if (selectedElement) selectedElement.classList.add('active');
}
</script>

<div id="gamesContainer">
<?php
$liveDefaults = ['evolution', 'vivo', 'sagaming'];
if (in_array($defaultProvider, $liveDefaults)) {
    $_GET['type'] = 'live';
    $_GET['provider'] = $defaultProvider;
    include __DIR__ . '/../games/games.php';
} else {
    $_GET['type'] = 'casino';
    if ($defaultProvider !== 'platipus') {
        $_GET['provider'] = $defaultProvider;
    }
    include __DIR__ . '/../games/games.php';
}
?>
</div>

</body>
</html>
