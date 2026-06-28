#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * P1 — Üye akışı: login → session → balance → (opsiyonel) game-launch / deposit form.
 *
 * Frontend SSH:
 *   php deploy/aapanel/probe-member-flow.php --login USER --password PASS [/path/to/frontend]
 *
 * Opsiyonlar:
 *   --game-id=bgaming:ElvisFrog   Demo/real oyun testi
 *   --demo                        Demo mod (giriş gerekmez)
 *   --skip-game                   Oyun launch atla
 *   --skip-wallet                 Deposit form atla
 */

$root = dirname(__DIR__, 2);
$login = '';
$password = '';
$gameId = '';
$demo = in_array('--demo', $argv, true);
$skipGame = in_array('--skip-game', $argv, true);
$skipWallet = in_array('--skip-wallet', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--login=')) {
        $login = substr($arg, 8);
        continue;
    }
    if (str_starts_with($arg, '--password=')) {
        $password = substr($arg, 11);
        continue;
    }
    if (str_starts_with($arg, '--game-id=')) {
        $gameId = substr($arg, 10);
        continue;
    }
    if ($arg === '--login' || $arg === '--password' || $arg === '--game-id') {
        continue;
    }
    if (trim($arg) === '' || str_starts_with($arg, '-')) {
        continue;
    }
    $root = rtrim(str_replace('\\', '/', $arg), '/');
}

// --login USER --password PASS (ayrı argüman)
for ($i = 1, $n = count($argv); $i < $n; $i++) {
    if ($argv[$i] === '--login' && isset($argv[$i + 1])) {
        $login = $argv[$i + 1];
    }
    if ($argv[$i] === '--password' && isset($argv[$i + 1])) {
        $password = $argv[$i + 1];
    }
    if ($argv[$i] === '--game-id' && isset($argv[$i + 1])) {
        $gameId = $argv[$i + 1];
    }
}

if (!$demo && ($login === '' || $password === '')) {
    fwrite(STDERR, "Usage: php deploy/aapanel/probe-member-flow.php --login USER --password PASS [--demo] [--game-id=bgaming:...]\n");
    exit(1);
}

require_once $root . '/config/bootstrap_api.php';
require_once $root . '/services/BackendApiClient.php';

$fail = 0;
$step = 0;

$line = static function (string $level, string $msg) use (&$fail): void {
    if ($level === 'FAIL') {
        $fail++;
    }
    echo '[' . $level . '] ' . $msg . PHP_EOL;
};

$apiCall = static function (
    string $method,
    string $route,
    ?string $body = null,
    ?string $bearer = null,
    array $extraHeaders = []
) use ($line): ?array {
    $bases = BackendApiClient::memberApiOutboundBaseCandidates();
    $auth = $bearer !== null && $bearer !== '' ? 'Bearer ' . $bearer : null;
    foreach ($bases as $base) {
        $result = BackendApiClient::proxyHttp(
            $method,
            $base,
            $route,
            [],
            $body,
            $body !== null ? 'application/json' : null,
            $auth,
            20,
            $extraHeaders
        );
        if ($result === null || !empty($result['transport_error'])) {
            continue;
        }
        return $result + ['base' => $base];
    }
    $line('FAIL', "{$method} {$route} — tüm backend adayları ulaşılamaz");
    return null;
};

$decode = static function (?array $result): ?array {
    if ($result === null) {
        return null;
    }
    $json = json_decode((string) ($result['body'] ?? ''), true);
    return is_array($json) ? $json : null;
};

echo "Metropol P1 Member Flow Probe\n";
echo "Frontend root: {$root}\n";
echo "Demo mode: " . ($demo ? 'yes' : 'no') . "\n\n";

$bases = BackendApiClient::memberApiOutboundBaseCandidates();
echo "API bases:\n";
foreach ($bases as $base) {
    echo "  - {$base}\n";
}
echo "\n";

$token = '';
$userId = 0;

if (!$demo) {
    $step++;
    echo "=== Step {$step}: POST auth/login ===\n";
    $payload = json_encode(['login' => $login, 'password' => $password], JSON_UNESCAPED_UNICODE);
    $loginResult = $apiCall('POST', 'auth/login', $payload);
    if ($loginResult === null) {
        exit(1);
    }
    $status = (int) ($loginResult['status'] ?? 0);
    $data = $decode($loginResult);
    echo "  HTTP {$status} via " . ($loginResult['base'] ?? '?') . "\n";
    if ($status !== 200 || empty($data['success'])) {
        $msg = is_array($data) ? (string) ($data['message'] ?? '') : substr((string) ($loginResult['body'] ?? ''), 0, 200);
        $line('FAIL', 'Login başarısız: ' . $msg);
        exit(1);
    }
    $token = trim((string) ($data['data']['token'] ?? ''));
    $userId = (int) ($data['data']['user_id'] ?? 0);
    if ($token === '') {
        $line('FAIL', 'Login yanıtında JWT token yok');
        exit(1);
    }
    $line('OK', "Login başarılı — user_id={$userId}, token=" . strlen($token) . ' chars');

    $step++;
    echo "\n=== Step {$step}: GET auth/session ===\n";
    $sessionResult = $apiCall('GET', 'auth/session', null, $token);
    if ($sessionResult === null) {
        exit(1);
    }
    $status = (int) ($sessionResult['status'] ?? 0);
    $data = $decode($sessionResult);
    echo "  HTTP {$status}\n";
    if ($status !== 200 || empty($data['success'])) {
        $line('FAIL', 'Session doğrulanamadı (HTTP ' . $status . ')');
    } else {
        $line('OK', 'Session aktif — ' . (string) ($data['data']['user']['username'] ?? ''));
        $refreshed = trim((string) ($data['data']['token'] ?? ''));
        if ($refreshed !== '') {
            $token = $refreshed;
        }
    }

    $step++;
    echo "\n=== Step {$step}: GET balance ===\n";
    $balanceResult = $apiCall('GET', 'balance', null, $token);
    if ($balanceResult === null) {
        exit(1);
    }
    $status = (int) ($balanceResult['status'] ?? 0);
    $data = $decode($balanceResult);
    echo "  HTTP {$status}\n";
    if ($status !== 200 || empty($data['success'])) {
        $line('FAIL', 'Balance alınamadı (HTTP ' . $status . ')');
    } else {
        $balance = $data['data']['balance'] ?? $data['data']['amount'] ?? $data['data'] ?? null;
        $line('OK', 'Balance: ' . (is_scalar($balance) ? (string) $balance : json_encode($balance, JSON_UNESCAPED_UNICODE)));
    }

    if (!$skipWallet) {
        $step++;
        echo "\n=== Step {$step}: GET deposit_payment (MegaPayz form) ===\n";
        $depositResult = $apiCall('GET', 'deposit_payment.php', null, $token);
        if ($depositResult === null) {
            exit(1);
        }
        $status = (int) ($depositResult['status'] ?? 0);
        $data = $decode($depositResult);
        echo "  HTTP {$status}\n";
        if ($status === 200) {
            $methods = $data['data']['methods'] ?? $data['data']['payment_methods'] ?? null;
            $count = is_array($methods) ? count($methods) : 0;
            $line('OK', "Deposit form erişilebilir ({$count} yöntem)");
        } elseif ($status === 503) {
            $line('WARN', 'MegaPayz pasif veya yapılandırılmamış');
        } else {
            $line('WARN', 'Deposit form HTTP ' . $status);
        }
    }
}

if (!$skipGame) {
    $step++;
    echo "\n=== Step {$step}: POST game-launch" . ($demo ? ' (demo)' : '') . " ===\n";
    $launchBody = ['mode' => $demo ? 'fun' : 'real'];
    if ($gameId !== '') {
        $launchBody['game_id'] = $gameId;
    } elseif ($demo) {
        $launchBody['game_id'] = 'bgaming:ElvisFrogInVegas';
    } else {
        $launchBody['game_id'] = 'bgaming:ElvisFrogInVegas';
        $line('WARN', 'Varsayılan game_id kullanılıyor: bgaming:ElvisFrogInVegas (--game-id ile değiştirin)');
    }
    $launchAuth = (!$demo && $token !== '') ? $token : null;
    $launchResult = $apiCall(
        'POST',
        'game-launch',
        json_encode($launchBody, JSON_UNESCAPED_UNICODE),
        $launchAuth
    );
    if ($launchResult === null) {
        exit(1);
    }
    $status = (int) ($launchResult['status'] ?? 0);
    $data = $decode($launchResult);
    echo "  HTTP {$status}\n";
    echo '  body: ' . substr((string) ($launchResult['body'] ?? ''), 0, 300) . "\n";
    if ($status === 200 && !empty($data['success'])) {
        $url = $data['data']['url'] ?? $data['data']['game_url'] ?? $data['data']['launch_url'] ?? '';
        $line('OK', 'Oyun launch başarılı' . ($url !== '' ? ' — URL alındı' : ''));
    } elseif ($status === 403) {
        $line('FAIL', 'CSRF / yetki hatası (403) — P0 game-launch proxy düzeltmesi deploy edildi mi?');
    } elseif ($status === 422 || $status === 503) {
        $msg = is_array($data) ? (string) ($data['message'] ?? $data['error'] ?? '') : '';
        $line('WARN', "Launch reddedildi (HTTP {$status}): {$msg}");
    } else {
        $line('FAIL', "Launch beklenmeyen yanıt HTTP {$status}");
    }
}

echo "\n=== ÖZET ===\n";
echo "FAIL: {$fail}\n";
exit($fail > 0 ? 1 : 0);
