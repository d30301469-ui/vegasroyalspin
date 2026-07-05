#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * P1 - BGaming / MegaPayz yapilandirma + callback URL canlilik testi.
 *
 * Backend SSH:
 *   php deploy/aapanel/probe-p1-providers.php --backend [/path/to/admin.vegasroyalspin.com]
 *
 * Yerel veya frontend (sadece HTTP probe):
 *   php deploy/aapanel/probe-p1-providers.php [--url=https://admin.vegasroyalspin.com]
 */

$root = dirname(__DIR__, 2);
$isBackend = in_array('--backend', $argv, true);
$baseUrlOverride = '';

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--url=')) {
        $baseUrlOverride = rtrim(substr($arg, 6), '/');
        continue;
    }
    if (trim($arg) === '' || str_starts_with($arg, '-')) {
        continue;
    }
    $root = rtrim(str_replace('\\', '/', $arg), '/');
}

$fail = 0;
$warn = 0;

$line = static function (string $level, string $msg) use (&$fail, &$warn): void {
    if ($level === 'FAIL') {
        $fail++;
    } elseif ($level === 'WARN') {
        $warn++;
    }
    echo '[' . $level . '] ' . $msg . PHP_EOL;
};

$probeHttp = static function (string $method, string $url, ?string $body = null, array $headers = []) use ($line): array {
    if (!function_exists('curl_init')) {
        $line('FAIL', "cURL yok â€” {$method} {$url}");
        return ['http' => 0, 'body' => '', 'error' => 'curl_missing'];
    }
    $ch = curl_init($url);
    $hdrs = array_merge(['Accept: application/json'], $headers);
    if ($body !== null) {
        $hdrs[] = 'Content-Type: application/json';
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $hdrs,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    if (defined('CURL_IPRESOLVE_V4')) {
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    }
    $respBody = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return [
        'http' => $http,
        'body' => is_string($respBody) ? $respBody : '',
        'error' => $err,
    ];
};

require_once $root . '/config/deploy_domains.php';

$backendUrl = $baseUrlOverride !== ''
    ? $baseUrlOverride
    : rtrim((string) (getenv('BACKEND_URL') ?: deploy_domain('backend_url')), '/');

if ($backendUrl === '') {
    $backendUrl = 'https://admin.vegasroyalspin.com';
}

echo "Metropol P1 Provider Probe\n";
echo "Root: {$root}\n";
echo "Backend URL: {$backendUrl}\n\n";

$pdo = null;
if ($isBackend || is_file($root . '/admin/app/Core/AdminDatabase.php') || is_file($root . '/app/Core/AdminDatabase.php')) {
    $bootstrapCandidates = [
        $root . '/app/Core/AdminPaths.php',
        $root . '/admin/app/Core/AdminPaths.php',
    ];
    foreach ($bootstrapCandidates as $candidate) {
        if (!is_readable($candidate)) {
            continue;
        }
        require_once $candidate;
        admin_paths_bootstrap();
        if (!class_exists('AdminDatabase', false)) {
            require_once admin_project_path('app/Core/AdminDatabase.php');
        }
        try {
            $pdo = AdminDatabase::pdo();
        } catch (Throwable $e) {
            $line('WARN', 'DB baÄŸlantÄ±sÄ± yok: ' . $e->getMessage());
        }
        break;
    }
}

// â”€â”€â”€ BGaming â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

echo "=== BGaming ===\n";
$expectedWallet = $backendUrl . '/api/v2/bgaming-wallet';
$bgConfig = null;
if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query('SELECT server_id, casino_id, api_base_url, wallet_secret, wallet_url, is_active FROM bgaming_config WHERE id = 1 LIMIT 1');
        $bgConfig = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $line('WARN', 'bgaming_config okunamadÄ±: ' . $e->getMessage());
    }
}

if (!is_array($bgConfig)) {
    $line('WARN', 'bgaming_config kaydÄ± yok (migration / admin panel)');
} else {
    $active = (int) ($bgConfig['is_active'] ?? 0) === 1;
    $line($active ? 'OK' : 'WARN', 'is_active=' . ($active ? '1' : '0'));
    foreach (['server_id', 'api_base_url', 'wallet_secret'] as $key) {
        $val = trim((string) ($bgConfig[$key] ?? ''));
        $line($val !== '' ? 'OK' : ($active ? 'FAIL' : 'WARN'), "{$key}: " . ($val !== '' ? 'set' : 'BOÅž'));
    }
    $walletUrl = trim((string) ($bgConfig['wallet_url'] ?? ''));
    if ($walletUrl === '') {
        $line($active ? 'FAIL' : 'WARN', 'wallet_url boÅŸ â€” BGaming paneline: ' . $expectedWallet);
    } elseif (!str_contains(strtolower($walletUrl), 'bgaming-wallet')) {
        $line('FAIL', "wallet_url hatalÄ±: {$walletUrl} (beklenen: .../api/v2/bgaming-wallet)");
    } elseif (!str_contains($walletUrl, parse_url($backendUrl, PHP_URL_HOST) ?: 'bo-nexthub')) {
        $line('WARN', "wallet_url backend host ile uyuÅŸmuyor: {$walletUrl}");
    } else {
        $line('OK', "wallet_url: {$walletUrl}");
    }
    echo "  Panel â†’ Wallet URL: {$expectedWallet}\n";
}

$bgHealth = $probeHttp('GET', $expectedWallet);
if ($bgHealth['http'] === 200 && str_contains($bgHealth['body'], '"status"')) {
    $line('OK', "GET {$expectedWallet} â†’ HTTP 200 (health)");
} elseif ($bgHealth['http'] === 404) {
    $line('FAIL', "GET {$expectedWallet} â†’ 404 (rewrite / deploy eksik)");
} elseif ($bgHealth['http'] >= 500) {
    $line('FAIL', "GET {$expectedWallet} â†’ HTTP {$bgHealth['http']}");
} else {
    $line('WARN', "GET {$expectedWallet} â†’ HTTP {$bgHealth['http']}");
}

$bgBalance = $probeHttp('POST', $expectedWallet . '/balance', '{}');
if ($bgBalance['http'] === 404) {
    $line('FAIL', 'POST bgaming-wallet/balance â†’ 404');
} elseif (in_array($bgBalance['http'], [400, 401, 403, 422], true)) {
    $line('OK', "POST bgaming-wallet/balance â†’ HTTP {$bgBalance['http']} (endpoint eriÅŸilebilir, imza bekleniyor)");
} else {
    $line('WARN', "POST bgaming-wallet/balance â†’ HTTP {$bgBalance['http']}");
}

// â”€â”€â”€ MegaPayz â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

echo "\n=== MegaPayz ===\n";
$mpCallback = $backendUrl . '/api/v2/megapayz-callback';
$mpConfig = null;
if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("SELECT sid, private_key, api_base_url, is_active FROM megapayz_config WHERE code = 'default' LIMIT 1");
        $mpConfig = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $line('WARN', 'megapayz_config okunamadÄ±: ' . $e->getMessage());
    }
}

if (!is_array($mpConfig)) {
    $line('WARN', 'megapayz_config kaydÄ± yok');
} else {
    $active = (int) ($mpConfig['is_active'] ?? 0) === 1;
    $line($active ? 'OK' : 'WARN', 'is_active=' . ($active ? '1' : '0'));
    foreach (['sid', 'private_key'] as $key) {
        $val = trim((string) ($mpConfig[$key] ?? ''));
        $line($val !== '' ? 'OK' : ($active ? 'FAIL' : 'WARN'), "{$key}: " . ($val !== '' ? 'set' : 'BOÅž'));
    }
    echo "  Panel â†’ Callback URL: {$mpCallback}\n";
}

$mpToken = trim((string) (getenv('MEGAPAYZ_CALLBACK_TOKEN') ?: ''));
if ($mpToken !== '') {
    $line('OK', 'MEGAPAYZ_CALLBACK_TOKEN tanÄ±mlÄ± (callback testi token gerektirir)');
} else {
    $line('OK', 'MEGAPAYZ_CALLBACK_TOKEN boÅŸ (callback IP/token doÄŸrulamasÄ± kapalÄ±)');
}

$mpGet = $probeHttp('GET', $mpCallback);
if ($mpGet['http'] === 404) {
    $line('FAIL', "GET {$mpCallback} â†’ 404");
} elseif ($mpGet['http'] === 405) {
    $line('OK', "GET {$mpCallback} â†’ 405 (POST bekleniyor â€” route OK)");
} else {
    $line('WARN', "GET {$mpCallback} â†’ HTTP {$mpGet['http']}");
}

$mpHeaders = $mpToken !== '' ? ['X-MegaPayz-Callback-Token: ' . $mpToken] : [];
$mpPost = $probeHttp('POST', $mpCallback, json_encode(['trx' => 'probe-p1', 'status' => 'test'], JSON_UNESCAPED_UNICODE), $mpHeaders);
if ($mpPost['http'] === 404) {
    $line('FAIL', "POST {$mpCallback} â†’ 404 (rewrite eksik)");
} elseif (in_array($mpPost['http'], [200, 403], true)) {
    $line('OK', "POST {$mpCallback} â†’ HTTP {$mpPost['http']} (callback endpoint eriÅŸilebilir)");
} else {
    $line('WARN', "POST {$mpCallback} â†’ HTTP {$mpPost['http']}");
}

// â”€â”€â”€ Ã–zet â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

echo "\n=== Ã–ZET ===\n";
echo "FAIL: {$fail}  WARN: {$warn}\n";
echo "\nProvider panel URL'leri (backend'e iÅŸaret etmeli):\n";
echo "  BGaming wallet : {$expectedWallet}\n";
echo "  MegaPayz callback: {$mpCallback}\n";
echo "\nSonraki adÄ±m: php deploy/aapanel/probe-member-flow.php --login USER --password PASS\n";

exit($fail > 0 ? 1 : 0);
