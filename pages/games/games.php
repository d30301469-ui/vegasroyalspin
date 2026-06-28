<?php
/**
 * Birleşik oyun listesi - type (casino|live) ve provider parametreleri ile.
 * Kullanım: games.php?type=casino&provider=pgsoft  veya  games.php?type=live&provider=pragmatic
 */
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../services/BackendApiClient.php';

$type = isset($_GET['type']) ? trim($_GET['type']) : 'casino';
$type = in_array($type, ['casino', 'live']) ? $type : 'casino';

$provider = isset($_GET['provider']) ? trim($_GET['provider']) : '';
$provider = preg_replace('/[^a-zA-Z0-9\-]/', '', $provider);
if ($provider === 'hepsi') {
    $provider = '';
}

$query = [
    'game_type' => $type === 'live' ? 1 : 0,
    'limit' => 120,
    'page' => 1,
];
if ($provider !== '') {
    $query['provider'] = $provider;
}
$j = BackendApiClient::request('GET', BackendApiClient::SVC_GAMES, 'games.php', $query);
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
    ];
}, array_filter($games, 'is_array'));
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oyunlar</title>
    <style>
        body { background-color: #000; color: white; }
        .game-grid { display: grid; grid-template-columns: repeat(5, 1fr); grid-gap: 20px; margin: 20px; }
        .game-item { text-align: center; color: white; }
        .game-item img { width: 100%; height: auto; cursor: pointer; border-radius: 10px; transition: 0.3s; }
        .game-item img:hover { transform: scale(1.05); }
        .game-item p { color: white; margin-top: 10px; }
        .btn { background-color: red; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; transition: background-color 0.3s; }
        .btn:hover { background-color: darkred; }
        @media (max-width: 768px) { .game-grid { grid-template-columns: repeat(3, 1fr); } .btn { width: 100%; padding: 15px; } }
        @media (min-width: 769px) { .btn { width: auto; } }
    </style>
</head>
<body>
<div class="game-grid" id="game-grid">
    <?php foreach ($games as $game): ?>
        <div class="game-item">
            <a href="/play?game_id=<?= rawurlencode((string) $game['game_id']); ?>&mode=real&wallet=main">
                <img src="<?= htmlspecialchars((string) $game['cover'], ENT_QUOTES); ?>" alt="<?= htmlspecialchars((string) $game['game_name'], ENT_QUOTES); ?>">
                <p><?= htmlspecialchars((string) $game['game_name'], ENT_QUOTES); ?></p>
            </a>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>
