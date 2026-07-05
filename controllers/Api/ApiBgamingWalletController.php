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
        if ($endpoint === 'auth/token_rotation') {
            $replayError = $this->tokenRotationReplayError($payload);
            if ($replayError !== null) {
                http_response_code(409);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($replayError, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
        }
        $result = BgamingService::wallet(AdminDatabase::pdo(), $endpoint, $payload, $rawBody, $signature);

        http_response_code((int) ($result['status'] ?? 200));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result['body'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Token rotation changes the shared wallet secret, so it gets a replay guard
     * on top of BGaming's request signature.
     *
     * @return array<string, string>|null
     */
    private function tokenRotationReplayError(array $payload): ?array
    {
        $rotationDatetime = trim((string) ($payload['rotation_datetime'] ?? ''));
        if ($rotationDatetime === '') {
            return ['code' => 'STALE_REQUEST', 'message' => 'Token rotation timestamp is missing or stale'];
        }

        $rotationAt = strtotime($rotationDatetime);
        if ($rotationAt === false || $rotationAt < (time() - 31 * 86400)) {
            return ['code' => 'STALE_REQUEST', 'message' => 'Token rotation timestamp is missing or stale'];
        }

        $nonce = trim((string) ($payload['nonce'] ?? $payload['request_id'] ?? ''));
        if ($nonce === '') {
            return ['code' => 'MISSING_NONCE', 'message' => 'Token rotation nonce is required'];
        }

        try {
            $stmt = AdminDatabase::pdo()->prepare(
                "SELECT COUNT(*)
                 FROM bgaming_wallet_logs
                 WHERE endpoint = 'auth/token_rotation' AND request_payload LIKE :needle"
            );
            $stmt->execute(['needle' => '%"nonce":"' . str_replace(['%', '_'], ['\\%', '\\_'], $nonce) . '"%']);
            if ((int) $stmt->fetchColumn() > 0) {
                return ['code' => 'REPLAYED_REQUEST', 'message' => 'Token rotation nonce was already used'];
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
