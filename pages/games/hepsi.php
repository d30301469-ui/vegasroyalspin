<?php
/**
 * Birleşik "Hepsi" oyun listesi - type (casino|live) parametresi ile.
 * Drakon katalog listesi, arama ve load more.
 */
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../services/BackendApiClient.php';

$type = isset($_GET['type']) ? trim($_GET['type']) : 'live';
$type = in_array($type, ['casino', 'live']) ? $type : 'live';

$searchTerm = isset($_GET['search']) ? (string) $_GET['search'] : '';
$initialLimit = $type === 'casino' ? 36 : 20;
$j = BackendApiClient::request('GET', BackendApiClient::SVC_GAMES, 'games.php', [
    'game_type' => $type === 'live' ? 1 : 0,
    'search' => $searchTerm,
    'offset' => 0,
    'limit' => $initialLimit,
]);
$games = [];
if ($j !== null) {
    $u = BackendApiClient::unwrap($j);
    $games = $u['games'] ?? $j['games'] ?? [];
}
$games = array_map(static function (array $game): array {
    return [
        'game_id' => (string) ($game['game_id'] ?? ''),
        'game_name' => (string) ($game['game_name'] ?? $game['name'] ?? ''),
        'cover' => (string) ($game['image_url'] ?? $game['thumbnail_url'] ?? $game['cover'] ?? ''),
        'provider_name' => (string) ($game['provider_name'] ?? $game['provider'] ?? ''),
    ];
}, array_filter($games, 'is_array'));
$initialOffset = $initialLimit;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oyunlar</title>
    <style>
        body { color: white; font-family: Arial, Helvetica, sans-serif; }
        .search-bar { margin: 20px auto; text-align: center; position: relative; max-width: 600px; }
        .search-bar input { width: 100%; padding: 12px 50px; font-size: 18px; border-radius: 3px; border: 1px solid #007bff; background-color: rgba(255,255,255,0.8); color: #333; }
        .search-bar input:focus { outline: none; background-color: rgba(255,255,255,1); border-color: #0056b3; }
        .search-bar button { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); background: transparent; border: none; cursor: pointer; color: #007bff; font-size: 24px; }
        .search-bar button:hover { color: #0056b3; }
        .game-grid { display: grid; grid-template-columns: repeat(5, 1fr); grid-gap: 20px; margin: 20px; }
        .game-item { text-align: center; color: white; }
        .game-item img { width: 100%; height: auto; cursor: pointer; border-radius: 10px; transition: 0.3s; }
        .game-item img:hover { transform: scale(1.05); }
        .button-container { display: flex; justify-content: center; }
        .btn { background-color: red; color: white; padding: 10px 200px; border: none; border-radius: 5px; cursor: pointer; transition: background-color 0.3s; }
        .btn:hover { background-color: darkred; }
        @media (max-width: 768px) { .game-grid { grid-template-columns: repeat(3, 1fr); } .btn { width: 100%; padding: 15px; } }
    </style>
</head>
<body>
<?php if ($type === 'casino'): ?>
<div class="search-bar">
    <form method="GET" action="">
        <input type="hidden" name="type" value="casino">
        <input type="text" id="provider-input" name="search" placeholder="Oyun adını yazın..." value="<?= htmlspecialchars($searchTerm ?? '', ENT_QUOTES); ?>">
        <button type="submit"><i class="fas fa-search"></i></button>
    </form>
</div>
<?php endif; ?>
<div class="game-grid" id="game-grid">
    <?php if (empty($games)): ?>
        <p>Hiç oyun bulunamadı.</p>
    <?php else: ?>
        <?php foreach ($games as $game): ?>
            <div class="game-item" data-provider="<?= htmlspecialchars((string) ($game['provider_name'] ?? ''), ENT_QUOTES); ?>">
                <img src="<?= htmlspecialchars((string) $game['cover'], ENT_QUOTES); ?>" alt="<?= htmlspecialchars((string) $game['game_name'], ENT_QUOTES); ?>" width="200" height="200"
                     onclick="window.location.href='/play?game_id=<?= rawurlencode((string) $game['game_id']); ?>&mode=real&wallet=main'">
                <p><?= htmlspecialchars((string) $game['game_name'], ENT_QUOTES); ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<div class="button-container">
    <button id="show-more-btn" class="btn" onclick="loadMoreGames()">Daha Fazla Oyun Göster</button>
</div>
<br><br><br>
<script>
(function() {
    const type = '<?= $type ?>';
    let offset = <?= $initialOffset ?>;
    const limit = 20;
    let loading = false;
    const loadMoreUrl = '/api/v2/games?game_type=' + (type === 'live' ? '1' : '0');

    window.loadMoreGames = function() {
        if (loading) return;
        loading = true;
        document.getElementById('show-more-btn').innerText = 'Yükleniyor...';
        document.getElementById('show-more-btn').disabled = true;

        let url = loadMoreUrl + '&offset=' + offset + '&limit=' + limit;
        if (type === 'casino') {
            const searchEl = document.getElementById('provider-input');
            if (searchEl) url += '&search=' + encodeURIComponent(searchEl.value);
        }

        const xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.onload = function() {
            document.getElementById('show-more-btn').innerText = 'Daha Fazla Oyun Göster';
            document.getElementById('show-more-btn').disabled = false;
            if (this.status === 200) {
                const json = JSON.parse(this.responseText);
                const data = json && json.data ? json.data : {};
                const newGames = Array.isArray(data.games) ? data.games.map(function(game) {
                    return {
                        game_id: game.game_id || '',
                        game_name: game.game_name || game.name || '',
                        cover: game.image_url || game.thumbnail_url || game.cover || '',
                        provider_name: game.provider_name || game.provider || ''
                    };
                }) : [];
                const gameGrid = document.getElementById('game-grid');
                if (newGames.length === 0) {
                    if (window.MaltabetToast) MaltabetToast.info('Artık daha fazla oyun yok.');
                    else alert('Artık daha fazla oyun yok.');
                } else {
                    var esc = function(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); };
                    var html = newGames.map(function(game) {
                        var prov = game.provider_name ? ' data-provider="' + esc(game.provider_name) + '"' : '';
                        return '<div class="game-item"' + prov + '><img src="' + esc(game.cover) + '" alt="' + esc(game.game_name) + '" width="200" height="200" onclick="window.location.href=\'/play?game_id=' + encodeURIComponent(game.game_id) + '&mode=real&wallet=main\'"><p>' + esc(game.game_name) + '</p></div>';
                    }).join('');
                    gameGrid.insertAdjacentHTML('beforeend', html);
                    offset += newGames.length;
                }
            } else {
                if (window.MaltabetToast) MaltabetToast.error('Oyunları yüklerken bir hata oluştu. Lütfen tekrar deneyin.');
                else alert('Oyunları yüklerken bir hata oluştu. Lütfen tekrar deneyin.');
            }
            loading = false;
        };
        xhr.onerror = function() {
            document.getElementById('show-more-btn').innerText = 'Daha Fazla Oyun Göster';
            document.getElementById('show-more-btn').disabled = false;
            loading = false;
            if (window.MaltabetToast) MaltabetToast.error('AJAX isteği başarısız oldu.');
            else alert('AJAX isteği başarısız oldu.');
        };
        xhr.send();
    };
})();
</script>
</body>
</html>
