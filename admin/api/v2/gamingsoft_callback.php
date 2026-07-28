<?php

declare(strict_types=1);

/**
 * Gaming Soft / GSC+ seamless wallet callback endpoint.
 *
 * Callback URL (provider panel): https://admin.vegasroyalspin.com/api/v2/gamingsoft-wallet
 * Full paths:
 *   POST /api/v2/gamingsoft-wallet/v1/api/seamless/balance
 *   POST /api/v2/gamingsoft-wallet/v1/api/seamless/withdraw
 *   POST /api/v2/gamingsoft-wallet/v1/api/seamless/deposit
 *   POST /api/v2/gamingsoft-wallet/v1/api/seamless/pushbetdata
 */

require_once __DIR__ . '/bootstrap.php';
admin_require_project_file('services/GamingSoftService.php');
admin_require_project_file('services/WageringService.php');

header('Content-Type: application/json; charset=UTF-8');

$endpoint = strtolower(trim((string) ($_GET['endpoint'] ?? $_GET['route'] ?? ''), '/'));
if ($endpoint === '') {
    $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
    if (preg_match('#/gamingsoft-wallet(?:\.php)?/?(.*)$#i', $path, $m)) {
        $endpoint = strtolower(trim((string) ($m[1] ?? ''), '/'));
    }
}
$endpoint = preg_replace('#^(?:api/)?v2/#', '', $endpoint) ?? $endpoint;
$endpoint = preg_replace('#^gamingsoft-wallet(?:\.php)?/#', '', $endpoint) ?? $endpoint;
$endpoint = preg_replace('#^v1/api/seamless/#', '', $endpoint) ?? $endpoint;
$endpoint = preg_replace('#^api/seamless/#', '', $endpoint) ?? $endpoint;
$endpoint = preg_replace('#^seamless/#', '', $endpoint) ?? $endpoint;

if ($endpoint === '' || $endpoint === 'health') {
    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'provider' => 'gamingsoft',
        'wallet_url' => '/api/v2/gamingsoft-wallet',
        'endpoints' => [
            'balance' => '/api/v2/gamingsoft-wallet/v1/api/seamless/balance',
            'withdraw' => '/api/v2/gamingsoft-wallet/v1/api/seamless/withdraw',
            'deposit' => '/api/v2/gamingsoft-wallet/v1/api/seamless/deposit',
            'pushbetdata' => '/api/v2/gamingsoft-wallet/v1/api/seamless/pushbetdata',
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['code' => 999, 'message' => 'METHOD_NOT_ALLOWED'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$rawBody = '';
$payload = null;

// When routed via api/v2/index.php, member_api_kernel already read php://input.
$forwardedPayload = $GLOBALS['GAMINGSOFT_WALLET_PAYLOAD'] ?? null;
$forwardedRaw = (string) ($GLOBALS['GAMINGSOFT_WALLET_RAW'] ?? '');
unset($GLOBALS['GAMINGSOFT_WALLET_PAYLOAD'], $GLOBALS['GAMINGSOFT_WALLET_RAW']);

if (is_array($forwardedPayload) && ($forwardedPayload !== [] || trim($forwardedRaw) !== '')) {
    $payload = $forwardedPayload;
    $rawBody = $forwardedRaw;
}

if (!is_array($payload) || $payload === []) {
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
}

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['code' => 999, 'message' => 'INVALID_JSON'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $result = GamingSoftService::wallet(AdminDatabase::pdo(), $endpoint, $payload, $rawBody);
    http_response_code((int) ($result['status'] ?? 200));
    echo json_encode($result['body'] ?? ['code' => 999, 'message' => 'Internal Server Error'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('GamingSoft callback fatal: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['code' => 999, 'message' => 'Internal Server Error'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

exit;
