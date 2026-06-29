<?php

require_once SERVICE_PATH . '/SlotGamesQuery.php';

class ApiSlotController extends Controller
{
    public function index(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            require_once __DIR__ . '/../../config/frontend_session.php';
            metropol_frontend_session_start();
        }

        header('Content-Type: application/json; charset=utf-8');

        $searchTerm        = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
        $selectedProviders = isset($_GET['providers']) ? (array) $_GET['providers'] : [];
        $limit             = isset($_GET['limit']) ? min(100, max(1, (int) $_GET['limit'])) : 30;
        $sort              = isset($_GET['sort']) ? trim((string) $_GET['sort']) : '';

        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        if (!isset($_GET['page']) && isset($_GET['offset'])) {
            $off = max(0, (int) $_GET['offset']);
            $page = intdiv($off, $limit) + 1;
        }

        $result = SlotGamesQuery::slotsPage($searchTerm, $selectedProviders, $limit, $page, $sort);

        if (!empty($result['apiError'])) {
            http_response_code(503);
            echo json_encode([
                'ok'             => false,
                'error'          => 'API yanıt vermedi',
                'games'          => [],
                'totalSlots'     => 0,
                'remainingGames' => 0,
                'showLoadMore'   => false,
                'nextPage'       => $page + 1,
                'page'           => $page,
                'perPage'        => $limit,
            ]);
            return;
        }

        $games       = $result['games'];
        $totalSlots  = $result['total'];
        $perPage     = $result['perPage'];
        $pageRet     = $result['page'];
        $hasNext     = $result['hasNext'];
        $loadedCount = ($pageRet - 1) * $perPage + count($games);
        $remaining   = max(0, $totalSlots - $loadedCount);
        $showLoadMore = $hasNext && $remaining > 0;
        $nextPage    = $pageRet + 1;

        echo json_encode([
            'ok'             => true,
            'games'          => $games,
            'totalSlots'     => $totalSlots,
            'remainingGames' => $remaining,
            'showLoadMore'   => $showLoadMore,
            'nextPage'       => $nextPage,
            'page'           => $pageRet,
            'perPage'        => $perPage,
        ]);
    }
}
