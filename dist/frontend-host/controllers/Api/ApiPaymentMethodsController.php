<?php

/**
 * GET /api/v2/payment-methods — backend ödeme yöntemleri proxy (public zarf).
 */
class ApiPaymentMethodsController
{
    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'code'    => 405,
                'message' => 'Yalnızca GET desteklenir.',
                'data'    => ['payment_methods' => [], 'currency' => 'TRY'],
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $env = ApiPaymentMethods::fetchEnvelope();
        if ($env !== null) {
            echo json_encode($env, JSON_UNESCAPED_UNICODE);
            return;
        }

        http_response_code(503);
        echo json_encode([
            'success' => false,
            'code'    => 503,
            'message' => 'Ödeme yöntemleri servisi şu anda kullanılamıyor.',
            'data'    => [
                'payment_methods' => [],
                'currency'        => 'TRY',
            ],
        ], JSON_UNESCAPED_UNICODE);
    }
}
