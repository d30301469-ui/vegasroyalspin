<?php

declare(strict_types=1);

/**
 * Drakon Casino webhook endpoint.
 *
 * URL : POST /drakon_api  OR  POST /api/v2/drakon_callback
 * Forwarded via ngrok → vegasroyalspin.test → admin/index.php lightweight route.
 */

require_once __DIR__ . '/bootstrap.php';
admin_require_project_file('services/DrakonService.php');

header('Content-Type: application/json; charset=UTF-8');

// Only POST allowed
if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => false, 'error' => 'METHOD_NOT_ALLOWED']);
    exit;
}

// Read body
$rawBody = (string) file_get_contents('php://input');
$payload = json_decode($rawBody, true);

// Fallback: some provider setups post form-encoded bodies instead of JSON.
if (!is_array($payload) && !empty($_POST)) {
    $payload = $_POST;
}

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['status' => false, 'error' => 'INVALID_JSON']);
    exit;
}

try {
    $pdo = AdminDatabase::pdo();

    $result = DrakonService::handleWebhook($pdo, $payload);

    http_response_code((int) ($result['status'] ?? 200));
    echo json_encode($result['body'] ?? ['status' => false]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'error' => 'SERVER_ERROR']);
}

exit;
