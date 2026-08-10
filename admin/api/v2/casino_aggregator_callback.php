<?php

declare(strict_types=1);

/**
 * Casino Aggregator seamless wallet callback endpoint.
 *
 * URL : POST /api/v2/casino-aggregator-wallet
 * Body: { method: GetBalance | ChangeBalance | UpdateDetail, ... }
 */

// Wallet callbacks are machine-to-machine; never open an admin PHP session.
if (!defined('APP_API_NO_SESSION')) {
    define('APP_API_NO_SESSION', true);
}

require_once __DIR__ . '/bootstrap.php';
admin_require_project_file('services/CasinoAggregatorService.php');
// Best-effort: ChangeBalance wagering hooks need this loaded in the wallet worker.
try {
    admin_require_project_file('services/WageringService.php');
} catch (Throwable) {
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 2, 'msg' => 'METHOD_NOT_ALLOWED'], $jsonFlags);
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
    echo json_encode(['status' => 13, 'msg' => 'INVALID_PARAMETER'], $jsonFlags);
    exit;
}

$signature = (string) (
    $_SERVER['HTTP_X_SIGNATURE']
    ?? $_SERVER['HTTP_X_SIGN']
    ?? $_SERVER['HTTP_X_CALLBACK_SIGNATURE']
    ?? ''
);

try {
    $pdo = AdminDatabase::pdo();
    $result = CasinoAggregatorService::wallet($pdo, $payload, $rawBody, $signature);
    http_response_code((int) ($result['status'] ?? 200));
    echo json_encode($result['body'] ?? ['status' => 1, 'msg' => 'INTERNAL_ERROR'], $jsonFlags);
} catch (Throwable) {
    http_response_code(500);
    echo json_encode(['status' => 1, 'msg' => 'INTERNAL_ERROR'], $jsonFlags);
}

exit;
