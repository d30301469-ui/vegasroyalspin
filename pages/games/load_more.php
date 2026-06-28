<?php
/**
 * Birleşik load more API - type (casino|live) parametresi ile.
 */
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../services/BackendApiClient.php';

$type = isset($_GET['type']) ? trim($_GET['type']) : 'live';
$type = in_array($type, ['casino', 'live']) ? $type : 'live';

$offset        = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
$limit         = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
$provider_game = isset($_GET['provider_game']) ? $_GET['provider_game'] : null;

header('Content-Type: application/json; charset=utf-8');

$j = BackendApiClient::request('GET', BackendApiClient::SVC_GAMES, '/games/load-more', [
    'type'          => $type,
    'offset'        => $offset,
    'limit'         => $limit,
    'provider_game' => $provider_game,
]);

$games = [];
if ($j !== null) {
    $u     = BackendApiClient::unwrap($j);
    $games = $u['games'] ?? $j['games'] ?? [];
}
echo json_encode($games);
