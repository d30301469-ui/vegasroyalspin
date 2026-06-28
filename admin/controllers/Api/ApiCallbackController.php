<?php

/**
 * Ödeme/çekim callback API controller (eski api/index.php).
 */
class ApiCallbackController
{
    public function index(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if (!filter_var((string) getenv('LEGACY_PAYMENT_CALLBACK_ENABLED'), FILTER_VALIDATE_BOOLEAN)) {
            http_response_code(410);
            echo json_encode(['status' => 'error', 'message' => 'LEGACY_CALLBACK_DISABLED']);
            return;
        }

        $raw = file_get_contents('php://input');
        $rawBody = is_string($raw) ? $raw : '';
        $data = json_decode($rawBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Geçersiz JSON verisi']);
            return;
        }

        if (!$this->verifyCallback($rawBody, $data)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'INVALID_SIGNATURE']);
            return;
        }

        $process = $data['Process'] ?? '';
        $islem = $data['islem'] ?? '';

        require_once SERVICE_PATH . '/PaymentCallbackService.php';
        $service = new PaymentCallbackService();

        if ($process === 'WithdrawalReturn') {
            $result = $service->handleWithdrawalReturn($data);
        } elseif ($islem === 'yatirimsonuc') {
            $result = $service->handleDepositResult($data);
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Geçersiz işlem türü']);
            return;
        }

        $httpCode = $result['http'] ?? 200;
        unset($result['http']);
        http_response_code($httpCode);
        echo json_encode($result);
    }

    private function verifyCallback(string $rawBody, array $data): bool
    {
        $secret = trim((string) getenv('LEGACY_PAYMENT_CALLBACK_SECRET'));
        if ($secret === '') {
            return false;
        }

        $signature = trim((string) (
            $_SERVER['HTTP_X_LEGACY_PAYMENT_SIGNATURE']
            ?? $_SERVER['HTTP_X_CALLBACK_SIGNATURE']
            ?? ''
        ));
        if ($signature !== '') {
            $signature = preg_replace('/^sha256=/i', '', $signature) ?? $signature;
            return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
        }

        $token = trim((string) (
            $_SERVER['HTTP_X_LEGACY_PAYMENT_TOKEN']
            ?? $_SERVER['HTTP_X_CALLBACK_TOKEN']
            ?? $data['callback_token']
            ?? ''
        ));

        return $token !== '' && hash_equals($secret, $token);
    }
}
