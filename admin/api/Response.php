<?php

declare(strict_types=1);

/**
 * Birleşik API JSON zarfı — public member API ve admin internal API için ortak sözleşme.
 *
 * Success: { success, ok, code, message?, data, meta? }
 * Error:   { success, ok, code, message, error?, errors?, meta? }
 */
final class ApiResponse
{
    public static function send(int $status, array $body): never
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        http_response_code($status);
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(array $data = [], int $code = 200, string $message = '', array $meta = []): never
    {
        $body = [
            'success' => true,
            'ok' => true,
            'code' => $code,
            'data' => $data,
        ];
        if ($message !== '') {
            $body['message'] = $message;
        }
        if ($meta !== []) {
            $body['meta'] = $meta;
        }
        self::send($code, $body);
    }

    public static function error(int $code, string $message, array $meta = [], ?string $errorCode = null, ?array $errors = null): never
    {
        $body = [
            'success' => false,
            'ok' => false,
            'code' => $code,
            'message' => $message,
        ];
        if ($errorCode !== null && $errorCode !== '') {
            $body['error'] = $errorCode;
        }
        if (is_array($errors) && $errors !== []) {
            $body['errors'] = $errors;
        }
        if ($meta !== []) {
            $body['meta'] = $meta;
        }
        self::send($code, $body);
    }
}
