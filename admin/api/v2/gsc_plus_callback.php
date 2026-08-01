<?php

declare(strict_types=1);

/**
 * GSC+ seamless wallet callback endpoint.
 *
 * Callback URL (provider panel):
 *   https://admin.vegasroyalspin.com/api/v2/gsc-plus-wallet
 *
 * Full paths:
 *   POST /api/v2/gsc-plus-wallet/v1/api/seamless/balance
 *   POST /api/v2/gsc-plus-wallet/v1/api/seamless/withdraw
 *   POST /api/v2/gsc-plus-wallet/v1/api/seamless/deposit
 *   POST /api/v2/gsc-plus-wallet/v1/api/seamless/pushbetdata
 */

require_once __DIR__ . '/bootstrap.php';
admin_require_project_file('services/GscPlusService.php');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Sign, Authorization');

$walletBase = '/api/v2/gsc-plus-wallet';

$endpoint = strtolower(trim((string) ($_GET['endpoint'] ?? $_GET['route'] ?? ''), '/'));
if ($endpoint === '') {
    $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
    $path = rawurldecode($path);
    $path = preg_replace('#\s+/#', '/', $path) ?? $path;
    $path = preg_replace('#/\s+#', '/', $path) ?? $path;
    if (preg_match('#/(?:gsc-plus-wallet|gsc_plus_wallet)(?:\.php)?\s*/?(.*)$#i', $path, $m)) {
        $endpoint = strtolower(trim((string) ($m[1] ?? ''), '/'));
    }
}
$endpoint = rawurldecode($endpoint);
$endpoint = preg_replace('#\s+/#', '/', $endpoint) ?? $endpoint;
$endpoint = preg_replace('#/\s+#', '/', $endpoint) ?? $endpoint;
$endpoint = preg_replace('#^(?:api/)?v2/#', '', $endpoint) ?? $endpoint;
$endpoint = preg_replace('#^index\.php/#', '', $endpoint) ?? $endpoint;
$endpoint = preg_replace('#^(?:gsc-plus-wallet|gsc_plus_wallet)(?:\.php)?/#', '', $endpoint) ?? $endpoint;
$endpoint = preg_replace('#^(?:gsc-wallet(?:\.php)?|gsc_wallet(?:\.php)?|gscplus(?:\.php)?)/#', '', $endpoint) ?? $endpoint;
$endpoint = preg_replace('#^v1/api/seamless/#', '', $endpoint) ?? $endpoint;
$endpoint = preg_replace('#^api/seamless/#', '', $endpoint) ?? $endpoint;
$endpoint = preg_replace('#^seamless/#', '', $endpoint) ?? $endpoint;
$endpoint = trim($endpoint, "/ \t");

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($endpoint === '' || $endpoint === 'health') {
    $catalog = [];
    try {
        $catalog = GscPlusService::catalogStatus(AdminDatabase::pdo());
    } catch (Throwable $e) {
        $catalog = ['error' => $e->getMessage()];
    }
    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'provider' => 'gsc-plus',
        'wallet_url' => $walletBase,
        'endpoints' => [
            'balance' => $walletBase . '/v1/api/seamless/balance',
            'withdraw' => $walletBase . '/v1/api/seamless/withdraw',
            'deposit' => $walletBase . '/v1/api/seamless/deposit',
            'pushbetdata' => $walletBase . '/v1/api/seamless/pushbetdata',
        ],
        'catalog' => $catalog,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$allowed = ['balance', 'withdraw', 'deposit', 'pushbetdata', 'synccatalog'];
if (!in_array($endpoint, $allowed, true)) {
    http_response_code(404);
    echo json_encode(['code' => 999, 'message' => 'NOT_FOUND'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['code' => 999, 'message' => 'METHOD_NOT_ALLOWED'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$rawBody = '';
$payload = null;

// When routed via api/v2/index.php, member_api_kernel may already have read php://input.
$forwardedPayload = $GLOBALS['GSC_PLUS_WALLET_PAYLOAD'] ?? null;
$forwardedRaw = (string) ($GLOBALS['GSC_PLUS_WALLET_RAW'] ?? '');
unset($GLOBALS['GSC_PLUS_WALLET_PAYLOAD'], $GLOBALS['GSC_PLUS_WALLET_RAW']);

if (is_array($forwardedPayload) && ($forwardedPayload !== [] || trim($forwardedRaw) !== '')) {
    $payload = $forwardedPayload;
    $rawBody = $forwardedRaw;
}

if (!is_array($payload) || $payload === []) {
    $raw = file_get_contents('php://input');
    $rawBody = is_string($raw) ? $raw : '';
    $decoded = $rawBody !== '' ? json_decode($rawBody, true) : null;
    $payload = is_array($decoded) ? $decoded : $_POST;
}
if (!is_array($payload)) {
    $payload = [];
}

$pickPayloadString = static function (array $src, array $keys): string {
    foreach ($keys as $key) {
        if (!array_key_exists($key, $src)) {
            continue;
        }
        $value = trim((string) $src[$key]);
        if ($value !== '') {
            return $value;
        }
    }
    return '';
};

// Normalize top-level field aliases seen in provider payloads/docs.
if (!isset($payload['operator_code'])) {
    $alias = $pickPayloadString($payload, ['Operator_code', 'operatorCode']);
    if ($alias !== '') {
        $payload['operator_code'] = $alias;
    }
}
if (!isset($payload['request_time'])) {
    $alias = $pickPayloadString($payload, ['Request_time', 'requestTime']);
    if ($alias !== '') {
        $payload['request_time'] = $alias;
    }
}
if (!isset($payload['currency'])) {
    $alias = $pickPayloadString($payload, ['Currency']);
    if ($alias !== '') {
        $payload['currency'] = $alias;
    }
}
if (!isset($payload['batch_requests']) && isset($payload['Batch_Requests']) && is_array($payload['Batch_Requests'])) {
    $payload['batch_requests'] = $payload['Batch_Requests'];
}
if (!isset($payload['wagers']) && isset($payload['Wagers']) && is_array($payload['Wagers'])) {
    $payload['wagers'] = $payload['Wagers'];
}

// GSC/clients may send signature in Sign/X-Sign/Signature headers.
$headerSign = '';
foreach (['HTTP_SIGN', 'REDIRECT_HTTP_SIGN', 'HTTP_X_SIGN', 'HTTP_SIGNATURE', 'REDIRECT_HTTP_SIGNATURE'] as $serverKey) {
    $candidate = trim((string) ($_SERVER[$serverKey] ?? ''));
    if ($candidate !== '') {
        $headerSign = $candidate;
        break;
    }
}
if ($headerSign === '' && function_exists('getallheaders')) {
    $headers = getallheaders();
    if (is_array($headers)) {
        foreach ($headers as $name => $value) {
            if (!is_string($name)) {
                continue;
            }
            if (
                strcasecmp($name, 'Sign') !== 0
                && strcasecmp($name, 'X-Sign') !== 0
                && strcasecmp($name, 'Signature') !== 0
            ) {
                continue;
            }
            $headerSign = trim((string) $value);
            if ($headerSign !== '') {
                break;
            }
        }
    }
}

// Signature resolution for GSC official testcase + real traffic:
// - Docs put `sign` in JSON body; prefer body when both are well-formed.
// - "Request With Invalid Sign" appends "-invalid" (body and/or Sign header).
//   Never let a valid counterpart mask a malformed/invalid candidate — that
//   previously returned deposit batch code 0 instead of 1004.
$isBadSign = static function (string $sign): bool {
    $sign = strtolower(trim($sign));
    if ($sign === '') {
        return false;
    }
    if (str_contains($sign, 'invalid')) {
        return true;
    }
    return preg_match('/^[a-f0-9]{32}$/', $sign) !== 1;
};

$bodySign = '';
if (array_key_exists('sign', $payload)) {
    $bodySign = trim((string) $payload['sign']);
}
if ($bodySign === '') {
    $bodySign = $pickPayloadString($payload, ['Sign', 'signature', 'Signature']);
}

if ($bodySign !== '' && $isBadSign($bodySign)) {
    $payload['sign'] = $bodySign;
} elseif ($headerSign !== '' && $isBadSign($headerSign)) {
    $payload['sign'] = $headerSign;
} elseif ($bodySign !== '') {
    $payload['sign'] = $bodySign;
} elseif ($headerSign !== '') {
    $payload['sign'] = $headerSign;
}

if ($endpoint === 'synccatalog') {
    try {
        $pdo = AdminDatabase::pdo();
        $cfg = GscPlusService::config($pdo);
        $secretKey = (string) ($cfg['secret_key'] ?? '');
        $operatorCode = (string) ($cfg['operator_code'] ?? '');
        if (
            $secretKey === ''
            || $operatorCode === ''
            || !GscPlusService::verifyCallbackSign($payload, 'synccatalog', $secretKey, $operatorCode)
        ) {
            http_response_code(200);
            echo json_encode(['code' => 1004, 'message' => 'API signature is invalid'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        $newCurrency = strtoupper(trim((string) ($payload['currency'] ?? '')));
        if ($newCurrency !== '' && GscPlusService::isSupportedCurrency($newCurrency)) {
            GscPlusService::updateConfig($pdo, ['currency' => $newCurrency, 'is_active' => 1]);
        }
        set_time_limit(300);
        $result = GscPlusService::syncLiveCasinoCatalog($pdo);
        http_response_code(200);
        echo json_encode(['code' => 0, 'message' => ''] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(200);
        echo json_encode(['code' => 999, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    exit;
}

try {
    $result = GscPlusService::wallet(AdminDatabase::pdo(), $endpoint, $payload, $rawBody);
    http_response_code((int) ($result['status'] ?? 200));
    echo json_encode($result['body'] ?? ['code' => 999, 'message' => 'Internal Server Error'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
} catch (Throwable $e) {
    error_log('[GSC+] callback: ' . $e->getMessage());
    http_response_code(200);
    echo json_encode(['code' => 999, 'message' => 'Internal Server Error'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
