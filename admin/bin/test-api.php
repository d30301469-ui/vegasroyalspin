<?php

declare(strict_types=1);

/**
 * Backend API smoke test — CLI: php bin/test-api.php [base-url]
 * Örnek: php bin/test-api.php https://bo-nexthub.site
 *
 * HTTP kodu + JSON gövde doğrulaması yapar (PHP Fatal / HTML yanıtları FAIL sayılır).
 */

$base = rtrim($argv[1] ?? 'https://bo-nexthub.site', '/');

/** @var list<array{0:string,1:string,2:?string,3:list<int>,4:string}> */
$tests = [
    // ── Sağlık ──────────────────────────────────────────────────────────
    ['GET', '/health.php', null, [200], 'json'],
    // ── Public CMS / içerik ─────────────────────────────────────────────
    ['GET', '/api/v2/site_settings.php', null, [200], 'json_success'],
    ['GET', '/api/v2/announcements.php', null, [200], 'json_success'],
    ['GET', '/api/v2/member_inbox_messages.php', null, [200], 'json_success'],
    ['GET', '/api/v2/sliders.php', null, [200], 'json_success'],
    ['GET', '/api/v2/promotions.php', null, [200], 'json_success'],
    ['GET', '/api/v2/footer.php', null, [200], 'json_success'],
    ['GET', '/api/v2/mobile-menu.php', null, [200], 'json_success'],
    ['GET', '/api/v2/content/footer', null, [200], 'json_success'],
    ['GET', '/api/v2/content/sliders', null, [200], 'json_success'],
    ['GET', '/api/v2/content/promotions', null, [200], 'json_success'],
    ['GET', '/api/v2/content/mobile-menu', null, [200], 'json_success'],
    ['GET', '/api/v2/content/homepage-sections', null, [200], 'json_success'],
    ['GET', '/api/v2/content/footer-pages', null, [200], 'json_success'],
    ['GET', '/api/v2/content/auth-sliders', null, [200], 'json_success'],
    // ── Oyun / finans public ─────────────────────────────────────────────
    ['GET', '/api/v2/games.php', null, [200], 'json_success'],
    ['GET', '/api/v2/games_provider.php', null, [200], 'json_success'],
    ['GET', '/api/v2/winners.php', null, [200], 'json_success'],
    ['GET', '/api/v2/payment_methods.php', null, [200], 'json_success'],
    ['GET', '/api/v2/payment/methods', null, [200], 'json_success'],
    ['GET', '/api/v2/promocodes.php', null, [200], 'json_success'],
    ['GET', '/api/v2/sports', null, [200], 'json_success'],
    ['GET', '/api/v2/sports/meta', null, [200], 'json_success'],
    // ── Auth (korumasız / hatalı giriş) ─────────────────────────────────
    ['GET', '/api/v2/auth/session', null, [200, 401], 'json'],
    ['POST', '/api/v2/auth/login', '{"email":"invalid@test.com","password":"wrong"}', [401, 422, 503], 'json'],
    ['POST', '/api/v2/auth/register', '{"username":"x","email":"bad","password":"1"}', [400, 422], 'json'],
    ['POST', '/api/v2/auth/refresh', '{}', [401], 'json'],
    // ── Auth gerekli (401 beklenir) ─────────────────────────────────────
    ['GET', '/api/v2/loyalty.php', null, [401], 'json'],
    ['GET', '/api/v2/balance.php', null, [401], 'json'],
    ['GET', '/api/v2/account/balance', null, [401], 'json'],
    ['GET', '/api/v2/profile_detail.php', null, [401], 'json'],
    ['GET', '/api/v2/me', null, [401], 'json'],
    ['GET', '/api/v2/me/preferences', null, [401], 'json'],
    ['GET', '/api/v2/me/limits', null, [401], 'json'],
    ['GET', '/api/v2/me/security-sessions', null, [401], 'json'],
    ['GET', '/api/v2/kyc/status', null, [401], 'json'],
    ['GET', '/api/v2/notifications', null, [401], 'json'],
    ['GET', '/api/v2/support/tickets', null, [401], 'json'],
    ['GET', '/api/v2/affiliate/summary', null, [401], 'json'],
    ['GET', '/api/v2/referrals.php', null, [401], 'json'],
    ['GET', '/api/v2/active_bonus.php', null, [401], 'json'],
    ['GET', '/api/v2/deposit-history', null, [401], 'json'],
    ['GET', '/api/v2/withdraw-history', null, [401], 'json'],
    ['GET', '/api/v2/game_history.php', null, [401], 'json'],
    ['GET', '/api/v2/favorite-slots', null, [401], 'json'],
    // ── Provider callbacks (imza/veri olmadan reddedilmeli) ────────────
    ['POST', '/api/v2/megapayz-callback', '{"test":1}', [403, 400, 422], 'json'],
    ['POST', '/api/v2/casino-callback', '{}', [400, 403], 'json'],
    ['GET', '/api/v2/bgaming-wallet', null, [200], 'json'],
    ['POST', '/api/v2/bgaming-wallet/balance', '{}', [400, 403, 422], 'json'],
    // ── Admin internal (oturum yok → 401) ──────────────────────────────
    ['GET', '/api/v2/internal/health', null, [200, 401], 'json'],
    ['GET', '/api/v2/internal/dashboard/summary', null, [401], 'json'],
    ['GET', '/api/v2/internal/users', null, [401], 'json'],
    ['GET', '/api/v2/internal/compliance/aml-alerts', null, [401], 'json'],
    ['GET', '/api/v2/internal/support/tickets', null, [401], 'json'],
    // ── Engagement public POST ───────────────────────────────────────────
    ['POST', '/api/v2/track_visit.php', '{"country_code":"TR","country_name":"Turkey"}', [200], 'json_success'],
];

$pass = 0;
$fail = 0;
$rows = [];

$analyzeBody = static function (string $response, int $code, string $mode): array {
    if ($response === '') {
        return ['ok' => $mode === 'json' && in_array($code, [401, 403, 405], true), 'snippet' => '(empty body)'];
    }
    if (stripos($response, '<b>Fatal error</b>') !== false || stripos($response, '<b>Warning</b>') !== false && stripos($response, 'Undefined variable') !== false) {
        return ['ok' => false, 'snippet' => 'PHP error in response'];
    }
    if (stripos($response, '<!doctype html') !== false || stripos($response, '<html') !== false) {
        return ['ok' => false, 'snippet' => 'HTML response (expected JSON)'];
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return ['ok' => $mode === 'json', 'snippet' => substr(preg_replace('/\s+/', ' ', $response) ?? $response, 0, 90)];
    }
    $snippet = (string) ($decoded['message'] ?? $decoded['msg'] ?? ((($decoded['success'] ?? null) === false) ? 'error' : 'ok'));
    if ($mode === 'json_success' && $code === 200) {
        $success = ($decoded['success'] ?? $decoded['ok'] ?? null) === true;
        return ['ok' => $success, 'snippet' => $success ? $snippet : ('success=false: ' . $snippet)];
    }
    return ['ok' => true, 'snippet' => $snippet !== '' ? $snippet : 'json ok'];
};

foreach ($tests as [$method, $path, $body, $expectedCodes, $mode]) {
    $url = $base . $path;
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = $body;
        $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
    }
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $responseStr = is_string($response) ? $response : '';
    if ($err !== '') {
        $analysis = ['ok' => false, 'snippet' => 'CURL: ' . $err];
    } else {
        $analysis = $analyzeBody($responseStr, $code, $mode);
    }

    $codeOk = in_array($code, $expectedCodes, true);
    $ok = $codeOk && $analysis['ok'];
    if ($ok) {
        $pass++;
        $status = 'PASS';
    } else {
        $fail++;
        $status = 'FAIL';
    }

    $detail = $analysis['snippet'];
    if (!$codeOk) {
        $detail = "HTTP {$code} (expected " . implode('|', $expectedCodes) . ') · ' . $detail;
    }

    $rows[] = [
        'status' => $status,
        'method' => $method,
        'path' => $path,
        'code' => $code,
        'snippet' => $detail,
    ];
}

echo "API Smoke Test — {$base}\n";
echo str_repeat('=', 80) . "\n";
printf("%-6s %-6s %-44s %5s  %s\n", 'STAT', 'METH', 'PATH', 'HTTP', 'MESSAGE');
echo str_repeat('-', 80) . "\n";
foreach ($rows as $row) {
    printf(
        "%-6s %-6s %-44s %5d  %s\n",
        $row['status'],
        $row['method'],
        $row['path'],
        $row['code'],
        $row['snippet']
    );
}
echo str_repeat('-', 80) . "\n";
echo "PASS: {$pass}  FAIL: {$fail}  TOTAL: " . count($rows) . "\n";
if ($fail > 0) {
    echo "\nSunucuda migration gerekebilir: php bin/install.php --migrate\n";
}
exit($fail > 0 ? 1 : 0);
