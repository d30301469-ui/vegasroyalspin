<?php

declare(strict_types=1);

final class ApiBgamingWalletController
{
    public function health(): void
    {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'ok',
            'provider' => 'bgaming',
            'wallet_url' => '/bgaming-wallet',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function balance(): void
    {
        $this->handle('balance');
    }

    public function play(): void
    {
        $this->handle('play');
    }

    public function rollback(): void
    {
        $this->handle('rollback');
    }

    public function freespinsFinish(): void
    {
        $this->handle('freespins/finish');
    }

    public function promoBet(): void
    {
        $this->handle('promo/bet');
    }

    public function promoWin(): void
    {
        $this->handle('promo/win');
    }

    public function promoRollback(): void
    {
        $this->handle('promo/rollback');
    }

    public function tokenRotation(): void
    {
        $this->handle('auth/token_rotation');
    }

    private function handle(string $endpoint): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'METHOD_NOT_ALLOWED'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $raw = file_get_contents('php://input');
        $rawBody = is_string($raw) ? $raw : '';
        $payload = $rawBody !== '' ? json_decode($rawBody, true) : [];
        if (!is_array($payload)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'INVALID_JSON'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $signature = (string) ($_SERVER['HTTP_X_REQUEST_SIGN'] ?? $_SERVER['HTTP_X_REQUEST_SIGNATURE'] ?? '');
        $requestId = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
        if ($requestId !== '' && empty($payload['request_id']) && empty($payload['nonce'])) {
            $payload['request_id'] = $requestId;
        }
        $result = BgamingService::wallet(AdminDatabase::pdo(), $endpoint, $payload, $rawBody, $signature);

        http_response_code((int) ($result['status'] ?? 200));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result['body'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
