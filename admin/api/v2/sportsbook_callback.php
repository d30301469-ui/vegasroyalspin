<?php

declare(strict_types=1);

/**
 * Sportsbook (BetBy) seamless wallet callback endpoint.
 *
 * URL : POST /api/v2/sportsbook-wallet
 * Body: { method: GetBalance | ChangeBalance | UpdateDetail, ... }
 * Signature (Ed25519) verified inside SportsbookService against the configured
 * public key. Host-independent (providers call the bare backend domain).
 */

require_once __DIR__ . '/bootstrap.php';
admin_require_project_file('services/SportsbookService.php');

if (!function_exists('sportsbook_callback_log_debug')) {
    function sportsbook_callback_log_debug(string $stage, array $context = []): void
    {
        $base = dirname(__DIR__, 3);
        $dir  = $base . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = json_encode([
            'ts' => gmdate('Y-m-d H:i:s'),
            'stage' => $stage,
            'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
            'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            'ctx' => $context,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($line)) {
            @file_put_contents($dir . '/sportsbook-callback-debug.log', $line . PHP_EOL, FILE_APPEND);
        }
    }
}

header('Content-Type: application/json; charset=UTF-8');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 2, 'msg' => 'METHOD_NOT_ALLOWED']);
    exit;
}

$rawBody = (string) file_get_contents('php://input');
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    if ($_POST !== []) {
        $payload = $_POST;
    } elseif (trim($rawBody) !== '') {
        parse_str($rawBody, $parsed);
        if (is_array($parsed) && $parsed !== []) {
            $payload = $parsed;
        }
    }
}

if (!is_array($payload)) {
    http_response_code(400);
    sportsbook_callback_log_debug('invalid_payload', ['raw' => substr($rawBody, 0, 1000)]);
    echo json_encode(['status' => 13, 'msg' => 'INVALID_PARAMETER']);
    exit;
}

$signature = (string) (
    $_SERVER['HTTP_X_SIGNATURE']
    ?? $_SERVER['HTTP_X_SIGN']
    ?? $_SERVER['HTTP_X_CALLBACK_SIGNATURE']
    ?? $_SERVER['HTTP_X_REQUEST_SIGN']
    ?? $_SERVER['HTTP_X_BETBY_SIGNATURE']
    ?? ''
);

try {
    $pdo    = AdminDatabase::pdo();
    $result = SportsbookService::wallet($pdo, $payload, $rawBody, $signature);

    sportsbook_callback_log_debug('handled', [
        'method' => (string) ($payload['method'] ?? $payload['action'] ?? ''),
        'status' => (int) ($result['status'] ?? 200),
        'body' => is_array($result['body'] ?? null) ? $result['body'] : [],
        'has_signature' => $signature !== '',
    ]);

    http_response_code((int) ($result['status'] ?? 200));
    echo json_encode($result['body'] ?? ['status' => 1, 'msg' => 'INTERNAL_ERROR']);
} catch (Throwable $e) {
    sportsbook_callback_log_debug('exception', ['message' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['status' => 1, 'msg' => 'INTERNAL_ERROR']);
}

exit;
