<?php

/**
 * Kazananlar — public member API winners proxy; zarfı backend ile aynı döner.
 */
class ApiWinnersController
{
    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $query = ApiWinners::normalizeQuery($_GET);
        $env   = ApiWinners::fetchEnvelope($query);

        if ($env !== null) {
            echo json_encode($env, JSON_UNESCAPED_UNICODE);
            return;
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'code'    => 200,
            'message' => 'Kazananlar servisi şu anda kullanılamıyor; boş liste döndürüldü.',
            'data'    => [
                'winners'        => [],
                'total'          => 0,
                'tab'            => $query['winners_tab'],
                'winners_tab'    => $query['winners_tab'],
                'period'         => $query['winners_period'],
                'winners_period' => $query['winners_period'],
            ],
        ], JSON_UNESCAPED_UNICODE);
    }
}
