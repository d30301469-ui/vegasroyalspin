<?php

require_once SERVICE_PATH . '/BackendApiClient.php';

class ApiSearchController
{
    public function advancedSearch(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        header('Content-Type: text/html; charset=utf-8');

        if (
            $_SERVER['REQUEST_METHOD'] !== 'POST' ||
            !isset($_POST['action']) ||
            $_POST['action'] !== 'advanced_search'
        ) {
            http_response_code(400);
            echo '<div class="no-games">Geçersiz istek.</div>';
            return;
        }

        $searchTerm = $_POST['search'] ?? '';
        $provider   = $_POST['provider'] ?? 'hepsi';
        $sort       = $_POST['sort'] ?? 'name_asc';

        $j = BackendApiClient::request('GET', BackendApiClient::SVC_GAMES, '/games/search', [
            'search'   => $searchTerm,
            'provider' => $provider,
            'sort'     => $sort,
        ]);

        if ($j === null) {
            echo '<div class="no-games">Oyun listesi şu an kullanılamıyor.</div>';
            return;
        }

        $u     = BackendApiClient::unwrap($j);
        $games = $u['games'] ?? $j['games'] ?? [];

        if (count($games) > 0) {
            echo '<div class="games-grid">';
            foreach ($games as $game) {
                $cover = ($game['cover'] ?? '') ?: './assets/images/default-game.jpg';
                $name  = htmlspecialchars($game['game_name'] ?? '', ENT_QUOTES, 'UTF-8');
                $url   = './games/launch.php?game_id=' . htmlspecialchars($game['game_id'] ?? '', ENT_QUOTES, 'UTF-8');

                echo '
                <div class="game-card" onclick="openIframe(\'' . $url . '\')">
                    <img src="' . htmlspecialchars($cover, ENT_QUOTES, 'UTF-8') . '" alt="' . $name . '">
                    <div class="game-name">' . $name . '</div>
                </div>';
            }
            echo '</div>';
        } else {
            echo '<div class="no-games">Arama kriterlerinize uygun oyun bulunamadı.</div>';
        }
    }
}
