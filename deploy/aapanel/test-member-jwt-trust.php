#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Frontend → backend üye JWT trust hattı testi (SSH).
 * Usage: php deploy/aapanel/test-member-jwt-trust.php [user_id] [/path/to/frontend]
 */

$root = dirname(__DIR__, 2);
$userId = 1;
foreach (array_slice($argv, 1) as $arg) {
    if (ctype_digit(trim($arg))) {
        $userId = (int) $arg;
        continue;
    }
    if (trim($arg) !== '' && !str_starts_with($arg, '-')) {
        $root = rtrim(str_replace('\\', '/', $arg), '/');
    }
}

require_once $root . '/config/bootstrap_api.php';
require_once $root . '/services/BackendApiClient.php';

$secret = function_exists('metropol_frontend_trust_secret')
    ? metropol_frontend_trust_secret()
    : trim((string) (getenv('FRONTEND_CMS_PURGE_SECRET') ?: ''));

echo "Frontend root: {$root}\n";
echo "User ID: {$userId}\n";
echo 'FRONTEND_CMS_PURGE_SECRET: ' . ($secret === '' ? 'MISSING' : (str_contains($secret, 'CHANGE-ME') ? 'PLACEHOLDER' : 'set (' . strlen($secret) . ' chars)')) . "\n";
echo 'MEMBER_JWT_SECRET: ' . (trim((string) (getenv('MEMBER_JWT_SECRET') ?: '')) === '' ? 'MISSING' : 'set') . "\n";
echo 'Outbound base: ' . BackendApiClient::effectiveOutboundMainBaseUrl() . "\n";
echo 'Public base: ' . BackendApiClient::effectiveMainBaseUrl() . "\n";

if ($secret === '' || $userId <= 0) {
    fwrite(STDERR, "Cannot test without purge secret and user_id.\n");
    exit(1);
}

$trust = hash_hmac('sha256', 'member-jwt:' . $userId, $secret);
$bases = array_values(array_unique(array_filter([
    BackendApiClient::effectiveOutboundMainBaseUrl(),
    BackendApiClient::effectiveMainBaseUrl(),
])));

$ok = false;
foreach ($bases as $base) {
    echo "\nPOST {$base}/internal/frontend-member-jwt ...\n";
    $result = BackendApiClient::proxyHttp(
        'POST',
        $base,
        'internal/frontend-member-jwt',
        [],
        json_encode(['user_id' => $userId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'application/json',
        null,
        12,
        ['X-Frontend-Trust: ' . $trust]
    );
    if ($result === null) {
        echo "  null response\n";
        continue;
    }
    if (!empty($result['transport_error'])) {
        echo '  transport error: ' . ($result['error_message'] ?? 'unknown') . "\n";
        continue;
    }
    $status = (int) ($result['status'] ?? 0);
    echo "  HTTP {$status}\n";
    echo '  body: ' . substr((string) ($result['body'] ?? ''), 0, 400) . "\n";
    if ($status === 200) {
        $ok = true;
    }
}

exit($ok ? 0 : 1);
