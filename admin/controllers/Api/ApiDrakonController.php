<?php

declare(strict_types=1);

admin_require_project_file('services/DrakonService.php');

final class ApiDrakonController
{
    public function index(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }

        $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (in_array($requestMethod, ['GET', 'HEAD'], true)) {
            $verification = DrakonService::verifyWebhookRequest(AdminDatabase::pdo(), '', $_SERVER, true);
            if (empty($verification['valid'])) {
                DrakonService::logWebhookVerificationFailure(['method' => 'health'], $verification, $_SERVER);
                http_response_code((int) ($verification['code'] ?? 403));
                echo json_encode([
                    'status' => false,
                    'error' => (string) ($verification['error'] ?? 'UNAUTHORIZED_WEBHOOK'),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }

            echo json_encode([
                'status' => true,
                'service' => 'drakon_webhook',
                'endpoint' => DrakonService::webhookPublicUrl(),
                'methods' => ['account_details', 'user_balance', 'transaction_bet', 'transaction_win', 'refund'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($requestMethod !== 'POST') {
            http_response_code(405);
            echo json_encode(['status' => false, 'error' => 'METHOD_NOT_ALLOWED'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $raw = $GLOBALS['drakon_root_raw_body'] ?? file_get_contents('php://input');
        $rawBody = is_string($raw) ? $raw : '';
        $jsonBody = preg_replace('/^\xEF\xBB\xBF/', '', $rawBody) ?? $rawBody;
        $payload = trim($jsonBody) !== '' ? json_decode($jsonBody, true) : null;
        if (!is_array($payload) && $_POST !== []) {
            $payload = $_POST;
        }
        if (!is_array($payload) && trim($rawBody) !== '') {
            parse_str($rawBody, $formPayload);
            if (is_array($formPayload) && $formPayload !== [] && isset($formPayload['method'])) {
                $payload = $formPayload;
            }
        }
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['status' => false, 'error' => 'INVALID_JSON'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $verificationBody = trim($rawBody) !== ''
            ? $rawBody
            : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $verification = DrakonService::verifyWebhookRequest(
            AdminDatabase::pdo(),
            is_string($verificationBody) ? $verificationBody : '',
            $_SERVER
        );
        if (empty($verification['valid'])) {
            DrakonService::logWebhookVerificationFailure($payload, $verification, $_SERVER);
            http_response_code((int) ($verification['code'] ?? 403));
            echo json_encode([
                'status' => false,
                'error' => (string) ($verification['error'] ?? 'UNAUTHORIZED_WEBHOOK'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $response = DrakonService::webhook(AdminDatabase::pdo(), $payload);

        http_response_code(200);
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        exit;
    }
}
