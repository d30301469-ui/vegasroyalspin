#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Frontend → backend login zinciri testi (sunucuda çalıştırın).
 * Usage: php deploy/aapanel/probe-login-chain.php [/path/to/frontend-root]
 */

$root = dirname(__DIR__, 2);
foreach (array_slice($argv, 1) as $arg) {
    if (trim($arg) !== '' && !str_starts_with($arg, '-')) {
        $root = rtrim(str_replace('\\', '/', $arg), '/');
    }
}

require_once $root . '/config/bootstrap_api.php';
require_once $root . '/services/BackendApiClient.php';

$bases = BackendApiClient::memberApiOutboundBaseCandidates();
echo "Outbound base candidates:\n";
foreach ($bases as $base) {
    echo "  - {$base}\n";
}

$payload = json_encode(['login' => 'probe-invalid', 'password' => 'probe-invalid'], JSON_UNESCAPED_UNICODE);
foreach ($bases as $base) {
    echo "\nPOST {$base}/auth/login\n";
    $result = BackendApiClient::proxyHttp('POST', $base, 'auth/login', [], $payload, 'application/json', null, 15, []);
    if ($result === null) {
        echo "  null result\n";
        continue;
    }
    if (!empty($result['transport_error'])) {
        echo "  TRANSPORT ERROR: " . ($result['error_message'] ?? 'unknown') . "\n";
        continue;
    }
    echo '  HTTP ' . (int) ($result['status'] ?? 0) . "\n";
    echo '  body: ' . substr((string) ($result['body'] ?? ''), 0, 200) . "\n";
    if ((int) ($result['status'] ?? 0) === 401) {
        echo "  OK — backend reachable (401 expected for bad credentials)\n";
        exit(0);
    }
    if ((int) ($result['status'] ?? 0) === 200) {
        echo "  OK — backend reachable\n";
        exit(0);
    }
}

fwrite(STDERR, "FAIL — no backend returned 401/200 for login probe\n");
exit(1);
