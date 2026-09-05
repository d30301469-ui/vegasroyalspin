<?php

declare(strict_types=1);

/**
 * Distro (operator-sportsbook) wallet webhook.
 *
 * URL : POST /api/v2/sportsbook-distro-hook
 * HMAC: X-Distro-Signature = sha256(raw body, sportsbook_config.callback_secret)
 */

if (!defined('APP_API_NO_SESSION')) {
    define('APP_API_NO_SESSION', true);
}

require_once __DIR__ . '/bootstrap.php';
admin_require_project_file('services/SportsbookService.php');

header('Content-Type: application/json; charset=UTF-8');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$rawBody = (string) file_get_contents('php://input');
$signature = trim((string) ($_SERVER['HTTP_X_DISTRO_SIGNATURE'] ?? ''));
$event = trim((string) ($_SERVER['HTTP_X_DISTRO_EVENT'] ?? ''));

try {
    $result = SportsbookService::distroHook(AdminDatabase::pdo(), $rawBody, $signature, $event);
} catch (Throwable $e) {
    error_log('sportsbook-distro-hook: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'internal']);
    exit;
}

http_response_code((int) ($result['code'] ?? 200));
echo json_encode(is_array($result['body'] ?? null) ? $result['body'] : ['ok' => false], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
