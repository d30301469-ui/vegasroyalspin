<?php

declare(strict_types=1);

use App\Core\Database;

if (!class_exists('AdminDatabase', false)) {
    final class AdminDatabase
    {
        public static function pdo(): PDO
        {
            return Database::pdo();
        }
    }
}

if (!class_exists('AdminAuth', false)) {
    final class AdminAuth
    {
        public static function check(): bool { return false; }
        public static function can(string $permissionKey): bool { return false; }
        public static function user(): array { return []; }
        public static function csrfToken(): string { return ''; }
        public static function verifyCsrf(?string $token): bool { return false; }
    }
}

require_once BASE_PATH . '/services/MemberJwtService.php';
require_once BASE_PATH . '/services/MegaPayzService.php';
require_once BASE_PATH . '/services/DrakonService.php';
require_once BASE_PATH . '/services/BgamingService.php';
require_once BASE_PATH . '/api/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$csrfKey = 'metropolcasino_csrf_token';
if (empty($_SESSION[$csrfKey]) || !is_string($_SESSION[$csrfKey])) {
    $_SESSION[$csrfKey] = isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token'])
        ? $_SESSION['csrf_token']
        : bin2hex(random_bytes(32));
}
$_SESSION['csrf_token'] = $_SESSION[$csrfKey];

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$route = trim((string) ($_GET['route'] ?? ''), '/');
if ($route === '') {
    $uriPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $prefix = '/api/v2/';
    $position = strpos($uriPath, $prefix);
    if ($position !== false) {
        $route = trim(substr($uriPath, $position + strlen($prefix)), '/');
    }
}

$routeAliases = [
    'auth/email-verification' => 'email_verification.php',
    'auth/verify-email' => 'email_verification.php',
    'auth/email/verify' => 'email_verification.php',
    'auth/2fa/enable' => 'two_factor.php',
    'auth/2fa/verify' => 'two_factor.php',
    'announcements' => 'announcements.php',
    'balance' => 'balance.php',
    'config' => 'site_settings.php',
    'profile-detail' => 'profile_detail.php',
    'profile/update' => 'profile_update.php',
    'account/profile' => 'profile_detail.php',
    'account/detail' => 'profile_detail.php',
    'account/update' => 'profile_update.php',
    'user/profile' => 'profile_detail.php',
    'user/update' => 'profile_update.php',
    'active-bonus' => 'active_bonus.php',
    'password-update' => 'password_update.php',
    'account/password' => 'password_update.php',
    'account/password-update' => 'password_update.php',
    'user/password' => 'password_update.php',
    'two-factor' => 'two_factor.php',
    'account/two-factor' => 'two_factor.php',
    'bonus-claim' => 'bonus_claim.php',
    'bonus-claims-me' => 'bonus_claims_me.php',
    'bonuses' => 'bonus_claims_me.php',
    'bonuses/active' => 'active_bonus.php',
    'bonuses/history' => 'bonus_claims_me.php',
    'bonuses/wagering-progress' => 'active_bonus.php',
    'bonus/use-code' => 'bonus_use_code.php',
    'referrals' => 'referrals.php',
    'promocodes' => 'promocodes.php',
    'payments/methods' => 'payment_methods.php',
    'withdraw-payment' => 'withdraw_payment.php',
    'payment-methods' => 'payment_methods.php',
    'deposit-payment' => 'deposit_payment.php',
    'deposit-history' => 'deposit_history.php',
    'withdraw-history' => 'withdraw_history.php',
    'wallet/balance' => 'balance.php',
    'wallet/summary' => 'balance.php',
    'promocode-request' => 'promocode_request.php',
    'account-freeze' => 'account_freeze.php',
    'account-unfreeze' => 'account_unfreeze.php',
    'favorite-slots' => 'favorite_slots.php',
    'favorite-live-casino' => 'favorite_live_casino.php',
    'footer' => 'footer.php',
    'content/footer.php' => 'footer.php',
    'content/footer-pages.php' => 'footer_pages.php',
    'content/homepage-sections.php' => 'homepage_sections.php',
    'content/mobile-menu.php' => 'mobile-menu.php',
    'content/promotions.php' => 'content/promotions',
    'promotions' => 'content/promotions',
    'content/sliders.php' => 'sliders.php',
    'game-launch' => 'game_launch.php',
    'game-history' => 'game_history.php',
    'games' => 'games.php',
    'casino/games' => 'games.php',
    'casino/games/search' => 'games.php',
    'casino/providers' => 'games_provider.php',
    'casino/recent-games' => 'game_history.php',
    'casino/favorite-games' => 'favorite_slots.php',
    'live-casino/providers' => 'games_provider.php',
    'live-casino/tables' => 'games.php',
    'profile/casino-game-history' => 'casino_game_history.php',
    'profile/casino_game_history.php' => 'casino_game_history.php',
    'profile/spor-bet-detail' => 'profile/spor_bet_detail.php',
    'profile/spor_bet_detail.php' => 'profile/spor_bet_detail.php',
    'profile/game-history-detail' => 'profile/game_history_detail.php',
    'profile/game_history_detail.php' => 'profile/game_history_detail.php',
    'winners' => 'winners.php',
    'track-visit' => 'track_visit.php',
    'site-settings' => 'site_settings.php',
    'member-inbox-messages' => 'member_inbox_messages.php',
    'games-provider' => 'games_provider.php',
    'sports' => 'sports.php',
    'sports-launch' => 'sports_launch.php',
    'leagues' => 'sports_leagues.php',
    'loyalty' => 'loyalty.php',
    'loyalty/me' => 'loyalty.php',
    'events' => 'sports_events.php',
    'events/live' => 'sports_events.php',
    'events/upcoming' => 'sports_events.php',
    'odds' => 'sports_markets.php',
    'odds/changes' => 'sports_markets.php',
    'bets/history' => 'sports_events.php',
    'bets/open' => 'sports_events.php',
    'bets/settled' => 'sports_events.php',
    'sports-events' => 'sports_events.php',
    'sports-leagues' => 'sports_leagues.php',
    'sports-markets' => 'sports_markets.php',
    'footer-pages' => 'footer_pages.php',
];
if (isset($routeAliases[$route])) {
    $route = $routeAliases[$route];
}

if (in_array($route, ['profile/spor_bet_detail.php', 'profile/game_history_detail.php'], true)) {
    $profilePartial = $route === 'profile/spor_bet_detail.php'
        ? BASE_PATH . '/pages/profile/get_spor_bet_details.php'
        : BASE_PATH . '/pages/profile/get_game_history_details.php';
    if (!is_file($profilePartial)) {
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<div class="alert alert-danger">Detay endpointi bulunamadı.</div>';
        exit;
    }

    header('Content-Type: text/html; charset=UTF-8');
    $previousCwd = getcwd();
    chdir(BASE_PATH . '/pages/profile');
    try {
        require $profilePartial;
    } finally {
        if (is_string($previousCwd) && $previousCwd !== '') {
            chdir($previousCwd);
        }
    }
    exit;
}

$bodyRaw = file_get_contents('php://input');
$body = [];
if (is_string($bodyRaw) && trim($bodyRaw) !== '') {
    $decoded = json_decode($bodyRaw, true);
    if (is_array($decoded)) {
        $body = $decoded;
    }
}

$payload = [
    'query' => $_GET,
    'body' => $body,
];

$json = static function (int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

$error = static function (int $status, string $message, array $meta = []) use ($json): void {
    $json($status, [
        'success' => false,
        'ok' => false,
        'code' => $status,
        'message' => $message,
        'data' => null,
        'meta' => $meta,
    ]);
};

$success = static function (array $data = [], array $meta = []) use ($json): void {
    $json(200, [
        'success' => true,
        'ok' => true,
        'code' => 200,
        'message' => 'OK',
        'data' => $data,
        'meta' => $meta,
    ]);
};

$professionalEmpty = static function (string $resource, array $extraData = [], array $extraMeta = []) use ($success): void {
    $success(array_replace([
        'items' => [],
        'total' => 0,
        'resource' => $resource,
    ], $extraData), array_replace([
        'status' => 'not_configured',
        'message' => 'Bu profesyonel API yüzeyi hazır; veri kaynağı yapılandırıldığında canlı veri dönecektir.',
    ], $extraMeta));
};

$professionalAccepted = static function (string $resource, array $extraData = [], array $extraMeta = []) use ($success): void {
    $success(array_replace([
        'accepted' => true,
        'resource' => $resource,
    ], $extraData), array_replace([
        'status' => 'queued_or_not_configured',
        'message' => 'İstek standart API sözleşmesiyle kabul edildi; ilgili operasyonel altyapı yapılandırılmalıdır.',
    ], $extraMeta));
};

$requireAuth = static function () use ($error): void {
    if (!AdminAuth::check()) {
        $error(401, 'Admin oturumu bulunamadı.');
    }
};

$requirePermission = static function (string $permissionKey) use ($requireAuth, $error): void {
    $requireAuth();
    if (!AdminAuth::can($permissionKey)) {
        $error(403, 'Bu işlem için yetkiniz yok.');
    }
};

$extractRouteParams = static function (string $pattern, string $value): ?array {
    $patternParts = explode('/', trim($pattern, '/'));
    $valueParts = explode('/', trim($value, '/'));
    if (count($patternParts) !== count($valueParts)) {
        return null;
    }
    $params = [];
    foreach ($patternParts as $index => $part) {
        $segment = $valueParts[$index] ?? '';
        if (str_starts_with($part, '{') && str_ends_with($part, '}')) {
            $name = trim($part, '{}');
            $params[$name] = $segment;
            continue;
        }
        if ($part !== $segment) {
            return null;
        }
    }

    return $params;
};

$getInput = static function (array $payload, string $key, mixed $default = null): mixed {
    if (array_key_exists($key, $payload['body'] ?? [])) {
        return $payload['body'][$key];
    }
    if (array_key_exists($key, $payload['query'] ?? [])) {
        return $payload['query'][$key];
    }

    return $default;
};

$validateCsrf = static function (array $payload) use ($error): void {
    $token = (string) ($payload['body']['_token'] ?? $payload['query']['_token'] ?? '');
    if (!AdminAuth::verifyCsrf($token)) {
        $error(419, 'CSRF doğrulaması başarısız.');
    }
};

/**
 * Native member v2 endpoints (legacy bağımlılığı olmadan).
 */
$memberInput = static function (array $payload) use ($bodyRaw): array {
    $data = [];
    if (is_array($payload['body'] ?? null)) {
        $data = $payload['body'];
    }
    if ($_POST !== []) {
        $data = array_merge($data, $_POST);
    }
    if ($data !== []) {
        return $data;
    }
    if (is_string($bodyRaw) && trim($bodyRaw) !== '') {
        $decoded = json_decode($bodyRaw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        parse_str($bodyRaw, $asForm);
        if (is_array($asForm)) {
            return $asForm;
        }
    }
    return [];
};

$memberUserById = static function (PDO $pdo, int $userId): ?array {
    if ($userId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return null;
    }

    // Hassas/iç alanları üye API yanıtlarından ayıkla (şifre hash'i, doğrulama
    // jetonları, 2FA sırrı vb. asla dışarı sızdırılmamalı).
    foreach ([
        'password', 'password_hash', 'pass', 'remember_token', 'verify_token',
        'reset_token', 'reset_password_token', 'email_verify_token',
        'two_factor_secret', '2fa_secret', 'totp_secret', 'api_token', 'api_key',
        'security_pin', 'pin_code',
    ] as $sensitive) {
        unset($row[$sensitive]);
    }

    foreach (['phone', 'city', 'country', 'address', 'dob', 'gender', 'identity_number', 'status'] as $field) {
        if (!array_key_exists($field, $row)) {
            $row[$field] = '';
        }
    }
    foreach (['balance', 'bonus_balance'] as $field) {
        if (!array_key_exists($field, $row)) {
            $row[$field] = 0;
        }
    }

    return $row;
};

$memberEnvelope = static function (int $status, array $body) use ($json): void {
    $json($status, $body);
};

$memberPasswordMatches = static function (string $plain, string $stored): bool {
    $stored = trim($stored);
    if ($plain === '' || $stored === '') {
        return false;
    }
    if (password_verify($plain, $stored)) {
        return true;
    }
    $lower = strtolower($stored);
    if (strlen($stored) === 32 && ctype_xdigit($stored)) {
        return hash_equals($lower, md5($plain));
    }
    if (strlen($stored) === 40 && ctype_xdigit($stored)) {
        return hash_equals($lower, sha1($plain));
    }

    return false;
};

$memberPasswordNeedsUpgrade = static function (string $stored): bool {
    $stored = trim($stored);
    if ($stored === '') {
        return false;
    }
    if (strlen($stored) === 32 && ctype_xdigit($stored)) {
        return true;
    }
    if (strlen($stored) === 40 && ctype_xdigit($stored)) {
        return true;
    }

    return password_get_info($stored)['algo'] === 0 || password_needs_rehash($stored, PASSWORD_DEFAULT);
};

$memberJwtEnsureTable = static function (PDO $pdo): void {
    static $ready = false;
    if ($ready) {
        return;
    }
    MemberJwtService::ensureTable($pdo);
    $ready = true;
};

$memberJwtSecret = static function (): string {
    static $secret = null;
    if (is_string($secret) && $secret !== '') {
        return $secret;
    }
    $secret = MemberJwtService::secret();
    return $secret;
};

$memberJwtB64Enc = static function (string $raw): string {
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
};

$memberJwtB64Dec = static function (string $raw): ?string {
    $padLen = 4 - (strlen($raw) % 4);
    if ($padLen > 0 && $padLen < 4) {
        $raw .= str_repeat('=', $padLen);
    }
    $decoded = base64_decode(strtr($raw, '-_', '+/'), true);
    return is_string($decoded) ? $decoded : null;
};

$memberJwtIssue = static function (PDO $pdo, array $user, int $ttl = 2592000) use ($memberJwtEnsureTable, $memberJwtSecret, $memberJwtB64Enc): string {
    $uid = (int) ($user['id'] ?? 0);
    $uname = (string) ($user['username'] ?? '');
    $email = (string) ($user['email'] ?? '');
    $now = time();
    $exp = $now + max(300, $ttl);
    $jti = bin2hex(random_bytes(16));

    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $payload = [
        'iss' => 'maltabet-api-v2',
        'sub' => (string) $uid,
        'uid' => $uid,
        'username' => $uname,
        'email' => $email,
        'iat' => $now,
        'exp' => $exp,
        'jti' => $jti,
    ];

    $segments = [
        $memberJwtB64Enc(json_encode($header, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        $memberJwtB64Enc(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
    ];
    $signingInput = implode('.', $segments);
    $sig = hash_hmac('sha256', $signingInput, $memberJwtSecret(), true);
    $segments[] = $memberJwtB64Enc($sig);
    $jwt = implode('.', $segments);

    try {
        $memberJwtEnsureTable($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO member_jwt_tokens
            (jti, user_id, token_hash, issued_at, expires_at, ip_address, user_agent)
            VALUES
            (:jti, :user_id, :token_hash, NOW(), :expires_at, :ip_address, :user_agent)'
        );
        $stmt->execute([
            'jti' => $jti,
            'user_id' => $uid,
            'token_hash' => hash('sha256', $jwt),
            'expires_at' => date('Y-m-d H:i:s', $exp),
            'ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    } catch (Throwable) {
        // Same-origin desktop APIs can still authenticate through the PHP session.
    }

    return $jwt;
};

$memberJwtExtractBearer = static function (): string {
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($header === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            $header = (string) ($headers['Authorization'] ?? $headers['authorization'] ?? '');
        }
    }
    if (preg_match('/^\s*Bearer\s+(.+)\s*$/i', $header, $m) === 1) {
        return trim((string) ($m[1] ?? ''));
    }
    if (!empty($_SESSION['member_jwt'])) {
        return (string) $_SESSION['member_jwt'];
    }
    return '';
};

$memberJwtHasBearerHeader = static function (): bool {
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($header === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            $header = (string) ($headers['Authorization'] ?? $headers['authorization'] ?? '');
        }
    }

    return preg_match('/^\s*Bearer\s+.+\s*$/i', $header) === 1;
};

$memberJwtValidate = static function (PDO $pdo, string $jwt) use ($memberJwtEnsureTable, $memberJwtSecret, $memberJwtB64Dec): ?array {
    if ($jwt === '') {
        return null;
    }
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return null;
    }
    [$h, $p, $s] = $parts;
    $headerJson = $memberJwtB64Dec($h);
    $payloadJson = $memberJwtB64Dec($p);
    $sigRaw = $memberJwtB64Dec($s);
    if (!is_string($headerJson) || !is_string($payloadJson) || !is_string($sigRaw)) {
        return null;
    }
    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);
    if (!is_array($header) || !is_array($payload)) {
        return null;
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        return null;
    }
    $expected = hash_hmac('sha256', $h . '.' . $p, $memberJwtSecret(), true);
    if (!hash_equals($expected, $sigRaw)) {
        return null;
    }
    $exp = (int) ($payload['exp'] ?? 0);
    $uid = (int) ($payload['uid'] ?? $payload['sub'] ?? 0);
    $jti = trim((string) ($payload['jti'] ?? ''));
    if ($uid <= 0 || $jti === '' || $exp < time()) {
        return null;
    }

    try {
        $memberJwtEnsureTable($pdo);
        $stmt = $pdo->prepare(
            'SELECT id FROM member_jwt_tokens
             WHERE jti = :jti AND user_id = :user_id AND token_hash = :token_hash
               AND revoked_at IS NULL AND expires_at >= NOW()
             LIMIT 1'
        );
        $stmt->execute([
            'jti' => $jti,
            'user_id' => $uid,
            'token_hash' => hash('sha256', $jwt),
        ]);
        $rowId = (int) $stmt->fetchColumn();
    } catch (Throwable) {
        return null;
    }
    if ($rowId <= 0) {
        return null;
    }

    try {
        $touch = $pdo->prepare('UPDATE member_jwt_tokens SET last_seen_at = NOW() WHERE id = :id');
        $touch->execute(['id' => $rowId]);
    } catch (Throwable) {
        // Last-seen updates are observational and must not reject a valid token.
    }

    return [
        'user_id' => $uid,
        'jti' => $jti,
        'payload' => $payload,
    ];
};

$memberJwtRequireUserId = static function (PDO $pdo) use ($memberEnvelope, $memberJwtExtractBearer, $memberJwtValidate): int {
    $token = $memberJwtExtractBearer();
    $sessionUserId = !empty($_SESSION['loggedin']) ? (int) ($_SESSION['user_id'] ?? 0) : 0;
    if ($token === '' && !empty($_SESSION['loggedin']) && (int) ($_SESSION['user_id'] ?? 0) > 0) {
        try {
            $token = MemberJwtService::ensureSessionToken($pdo);
        } catch (Throwable) {
            $token = '';
        }
    }
    try {
        $auth = $memberJwtValidate($pdo, $token);
    } catch (Throwable) {
        $auth = null;
    }
    if (!is_array($auth) || (int) ($auth['user_id'] ?? 0) <= 0) {
        if ($sessionUserId > 0) {
            return $sessionUserId;
        }
        $memberEnvelope(401, [
            'success' => false,
            'code' => 401,
            'error' => 'UNAUTHORIZED',
            'message' => 'Geçersiz veya süresi dolmuş JWT token.',
        ]);
    }
    if ($token !== '') {
        $_SESSION['member_jwt'] = $token;
    }
    return (int) $auth['user_id'];
};

$memberJwtOptionalUserId = static function (PDO $pdo) use ($memberJwtExtractBearer, $memberJwtValidate): ?int {
    $sessionUserId = !empty($_SESSION['loggedin']) ? (int) ($_SESSION['user_id'] ?? 0) : 0;
    if ($sessionUserId > 0) {
        return $sessionUserId;
    }
    $token = $memberJwtExtractBearer();
    if ($token === '') {
        return null;
    }
    try {
        $auth = $memberJwtValidate($pdo, $token);
    } catch (Throwable) {
        $auth = null;
    }
    $userId = is_array($auth) ? (int) ($auth['user_id'] ?? 0) : 0;
    if ($userId <= 0) {
        return null;
    }
    $_SESSION['member_jwt'] = $token;
    return $userId;
};

$memberJwtRevokeCurrent = static function (PDO $pdo) use ($memberJwtExtractBearer, $memberJwtEnsureTable): void {
    $token = $memberJwtExtractBearer();
    if ($token === '' && !empty($_SESSION['member_jwt'])) {
        $token = (string) $_SESSION['member_jwt'];
    }
    if ($token === '') {
        return;
    }
    $memberJwtEnsureTable($pdo);
    $stmt = $pdo->prepare('UPDATE member_jwt_tokens SET revoked_at = NOW() WHERE token_hash = :token_hash AND revoked_at IS NULL');
    $stmt->execute(['token_hash' => hash('sha256', $token)]);
};

$memberRequireLogin = static function () use ($memberJwtRequireUserId): int {
    $pdo = AdminDatabase::pdo();
    return $memberJwtRequireUserId($pdo);
};

$memberStateChangingRoutes = [
    'profile_update.php' => true,
    'profile/update' => true,
    'me' => true,
    'me/preferences' => true,
    'account/update' => true,
    'user/update' => true,
    'password_update.php' => true,
    'account/password' => true,
    'account/password-update' => true,
    'user/password' => true,
    'two_factor.php' => true,
    'auth/2fa/enable' => true,
    'auth/2fa/verify' => true,
    'bonus_claim.php' => true,
    'bonus_use_code.php' => true,
    'bonuses/active' => true,
    'promocode_request.php' => true,
    'deposits' => true,
    'deposit_payment.php' => true,
    'withdrawals' => true,
    'withdraw_payment.php' => true,
    'wallet/transfer' => true,
    'payment.php' => true,
    'account_freeze.php' => true,
    'account_unfreeze.php' => true,
    'favorite_slots.php' => true,
    'favorite_live_casino.php' => true,
    'casino/favorite-games' => true,
    'game_launch.php' => true,
    'bets/place' => true,
    'bets/validate' => true,
    'kyc/documents' => true,
    'kyc/address-verification' => true,
    'kyc/source-of-funds' => true,
    'notifications/read-all' => true,
    'notifications/settings' => true,
    'responsible-gaming/cool-off' => true,
    'responsible-gaming/limits' => true,
    'responsible-gaming/self-exclusion' => true,
    'support/tickets' => true,
];
$memberStateChangingPatterns = [
    '~^bets/[^/]+/(cashout|cancel)$~',
    '~^bonuses/[^/]+/(claim|cancel)$~',
    '~^casino/favorite-games/[^/]+$~',
    '~^casino/games/[^/]+/launch$~',
    '~^live-casino/tables/[^/]+/launch$~',
    '~^me/security-sessions/[^/]+$~',
    '~^notifications/[^/]+/read$~',
    '~^support/tickets/[^/]+/messages$~',
    '~^withdrawals/[^/]+/cancel$~',
];
$memberRouteRequiresCsrf = static function (string $route) use ($memberStateChangingRoutes, $memberStateChangingPatterns): bool {
    if (isset($memberStateChangingRoutes[$route])) {
        return true;
    }
    foreach ($memberStateChangingPatterns as $pattern) {
        if (preg_match($pattern, $route) === 1) {
            return true;
        }
    }

    return false;
};

if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
    && $memberRouteRequiresCsrf($route)
    && !$memberJwtHasBearerHeader()
) {
    $csrf = (string) (
        $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? ($payload['body']['_token'] ?? null)
        ?? ($payload['body']['csrf_token'] ?? null)
        ?? ($payload['query']['_token'] ?? null)
        ?? ($payload['query']['csrf_token'] ?? null)
        ?? ''
    );
    $known = isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
    if ($known === '' || $csrf === '' || !hash_equals($known, $csrf)) {
        $memberEnvelope(403, [
            'success' => false,
            'code' => 403,
            'message' => 'CSRF doğrulaması başarısız.',
        ]);
    }
}

if ($method === 'POST' && ($route === 'login.php' || $route === 'auth/login')) {
    $input = $memberInput($payload);
    $login = trim((string) ($input['login'] ?? $input['username'] ?? $input['email'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    if ($login === '' || $password === '') {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Kullanıcı adı/e-posta ve şifre zorunludur.']);
    }
    $pdo = AdminDatabase::pdo();
    $stmt = $pdo->prepare('SELECT id, username, email, password, name, surname FROM users WHERE username = :username OR email = :email LIMIT 1');
    $stmt->execute(['username' => $login, 'email' => $login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($user)) {
        $memberEnvelope(401, ['success' => false, 'code' => 401, 'message' => 'Kullanıcı adı veya şifre hatalı.']);
    }
    $hash = (string) ($user['password'] ?? '');
    if (!$memberPasswordMatches($password, $hash)) {
        $memberEnvelope(401, ['success' => false, 'code' => 401, 'message' => 'Kullanıcı adı veya şifre hatalı.']);
    }
    if ($memberPasswordNeedsUpgrade($hash)) {
        try {
            $pdo->prepare('UPDATE users SET password = :password, password_changed_at = NOW() WHERE id = :id')
                ->execute([
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'id' => (int) ($user['id'] ?? 0),
                ]);
        } catch (Throwable) {
            // Login should not fail just because a legacy hash could not be upgraded.
        }
    }
    $_SESSION['loggedin'] = true;
    $_SESSION['user_id'] = (int) ($user['id'] ?? 0);
    $_SESSION['username'] = (string) ($user['username'] ?? $login);
    $_SESSION['email'] = (string) ($user['email'] ?? '');
    unset($_SESSION['login_error']);
    $jwt = '';
    try {
        $jwt = $memberJwtIssue($pdo, $user);
        $_SESSION['member_jwt'] = $jwt;
    } catch (Throwable) {
        unset($_SESSION['member_jwt']);
    }
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Giriş başarılı.',
        'data' => [
            'token' => $jwt,
            'user_id' => (int) ($user['id'] ?? 0),
            'user' => [
                'id' => (int) ($user['id'] ?? 0),
                'username' => (string) ($user['username'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
                'name' => trim((string) (($user['name'] ?? '') . ' ' . ($user['surname'] ?? ''))),
            ],
        ],
    ]);
}

if ($method === 'POST' && ($route === 'register.php' || $route === 'auth/register')) {
    $input = $memberInput($payload);
    $username = trim((string) ($input['username'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $passwordConfirmation = (string) ($input['password_confirmation'] ?? $input['confirm_password'] ?? '');
    $firstName = trim((string) ($input['first_name'] ?? $input['firstName'] ?? $input['name'] ?? ''));
    $surname = trim((string) ($input['surname'] ?? ''));
    $country = strtoupper(trim((string) ($input['country'] ?? 'TR')));
    $city = trim((string) ($input['city'] ?? ''));
    $dob = trim((string) ($input['birth_date'] ?? $input['dob'] ?? ''));
    $genderRaw = trim((string) ($input['gender'] ?? ''));
    $phoneRaw = trim((string) ($input['phone'] ?? ''));
    $phoneCode = preg_replace('/\D+/', '', (string) ($input['phone_country_code'] ?? ''));
    $tc = preg_replace('/\D+/', '', (string) ($input['tc'] ?? $input['tcKimlik'] ?? $input['identity_number'] ?? ''));
    $address = trim((string) ($input['address'] ?? ''));
    $bonusCode = trim((string) ($input['bonus_code'] ?? $input['bonusCode'] ?? ''));

    $errors = [];
    if ($username === '') {
        $errors['username'] = 'Kullanıcı adı gerekli.';
    }
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Geçerli bir e-posta girin.';
    }
    if ($password === '') {
        $errors['password'] = 'Şifre gerekli.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Şifre en az 6 karakter olmalıdır.';
    }
    if ($passwordConfirmation !== '' && $password !== $passwordConfirmation) {
        $errors['password_confirmation'] = 'Şifreler eşleşmiyor.';
    }
    if ($firstName === '') {
        $errors['first_name'] = 'Ad gerekli.';
    }
    if ($surname === '') {
        $errors['surname'] = 'Soyad gerekli.';
    }
    if ($city === '') {
        $errors['city'] = 'Şehir gerekli.';
    }
    if ($dob === '') {
        $errors['birth_date'] = 'Doğum tarihi gerekli.';
    }
    if ($genderRaw === '') {
        $errors['gender'] = 'Cinsiyet gerekli.';
    }
    $phoneDigits = (string) preg_replace('/\D+/', '', $phoneRaw);
    if ($phoneCode !== '' && str_starts_with($phoneDigits, (string) $phoneCode)) {
        $phoneDigits = substr($phoneDigits, strlen((string) $phoneCode));
    }
    $phoneDigits = ltrim($phoneDigits, '0');
    if (strlen($phoneDigits) < 10) {
        $errors['phone'] = 'Telefon en az 10 rakam olmalıdır.';
    }
    if ($country === 'TR' && strlen((string) $tc) !== 11) {
        $errors['tc'] = 'Türkiye için 11 haneli T.C. kimlik numarası gerekli.';
    }
    if ($errors !== []) {
        $memberEnvelope(400, [
            'success' => false,
            'code' => 400,
            'error' => 'VALIDATION_ERROR',
            'message' => 'Doğrulama hatası',
            'errors' => $errors,
        ]);
    }

    $genderMap = [
        'erkek' => 'Erkek',
        'kadın' => 'Kadın',
        'kadin' => 'Kadın',
        'diğer' => 'Diğer',
        'diger' => 'Diğer',
        'male' => 'Erkek',
        'female' => 'Kadın',
        'other' => 'Diğer',
    ];
    $genderKey = mb_strtolower($genderRaw, 'UTF-8');
    $gender = $genderMap[$genderKey] ?? 'Erkek';

    $pdo = AdminDatabase::pdo();
    $dup = $pdo->prepare('SELECT username, email FROM users WHERE username = :username OR email = :email LIMIT 1');
    $dup->execute(['username' => $username, 'email' => $email]);
    $exists = $dup->fetch(PDO::FETCH_ASSOC);
    if (is_array($exists)) {
        $dupErrors = [];
        if (strcasecmp((string) ($exists['username'] ?? ''), $username) === 0) {
            $dupErrors['username'] = 'Bu kullanıcı adı zaten kayıtlı.';
        }
        if (strcasecmp((string) ($exists['email'] ?? ''), $email) === 0) {
            $dupErrors['email'] = 'Bu e-posta zaten kayıtlı.';
        }
        $memberEnvelope(409, [
            'success' => false,
            'code' => 409,
            'error' => 'DUPLICATE_USER',
            'message' => 'Kullanıcı adı veya e-posta zaten kayıtlı.',
            'errors' => $dupErrors,
        ]);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $baseReferral = preg_replace('/[^a-z0-9]/i', '', strtolower($username));
    $baseReferral = is_string($baseReferral) && $baseReferral !== '' ? substr($baseReferral, 0, 18) : 'user';
    $referralCode = '';
    for ($i = 0; $i < 6; $i++) {
        $candidate = strtoupper($baseReferral . substr(bin2hex(random_bytes(4)), 0, 8));
        $check = $pdo->prepare('SELECT 1 FROM users WHERE referral_code = :code LIMIT 1');
        $check->execute(['code' => $candidate]);
        if (!$check->fetchColumn()) {
            $referralCode = $candidate;
            break;
        }
    }
    $insert = $pdo->prepare(
        'INSERT INTO users
        (name, surname, username, email, identity_number, gender, dob, phone, city, country, password, bonus_code, referral_code, address, password_changed_at, created_at)
        VALUES
        (:name, :surname, :username, :email, :identity_number, :gender, :dob, :phone, :city, :country, :password, :bonus_code, :referral_code, :address, NOW(), NOW())'
    );
    $insert->execute([
        'name' => $firstName,
        'surname' => $surname,
        'username' => $username,
        'email' => $email,
        'identity_number' => $tc !== '' ? $tc : '00000000000',
        'gender' => $gender,
        'dob' => $dob,
        'phone' => $phoneDigits,
        'city' => $city,
        'country' => $country,
        'password' => $passwordHash,
        'bonus_code' => $bonusCode !== '' ? $bonusCode : null,
        'referral_code' => $referralCode !== '' ? $referralCode : null,
        'address' => $address !== '' ? $address : null,
    ]);
    $userId = (int) $pdo->lastInsertId();
    $_SESSION['loggedin'] = true;
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['email'] = $email;
    unset($_SESSION['login_error']);
    $jwt = '';
    try {
        $jwt = $memberJwtIssue($pdo, [
            'id' => $userId,
            'username' => $username,
            'email' => $email,
        ]);
        $_SESSION['member_jwt'] = $jwt;
    } catch (Throwable) {
        unset($_SESSION['member_jwt']);
    }
    $memberEnvelope(201, [
        'success' => true,
        'code' => 201,
        'message' => 'Kayıt başarılı. Hoş geldiniz!',
        'data' => [
            'token' => $jwt,
            'user_id' => $userId,
            'user' => [
                'id' => $userId,
                'username' => $username,
                'email' => $email,
                'name' => trim($firstName . ' ' . $surname),
            ],
        ],
    ]);
}

if ($method === 'GET' && ($route === 'session.php' || $route === 'auth/session')) {
    $pdo = AdminDatabase::pdo();
    $userId = $memberJwtRequireUserId($pdo);
    $sessionToken = (string) ($_SESSION['member_jwt'] ?? '');
    if ($sessionToken === '' && !empty($_SESSION['loggedin']) && $userId > 0) {
        try {
            $sessionToken = $memberJwtIssue($pdo, [
                'id' => $userId,
                'username' => (string) ($_SESSION['username'] ?? ''),
                'email' => (string) ($_SESSION['email'] ?? ''),
            ]);
            $_SESSION['member_jwt'] = $sessionToken;
        } catch (Throwable) {
            $sessionToken = '';
        }
    }
    $user = $memberUserById($pdo, $userId);
    if (!$user) {
        $memberEnvelope(401, [
            'success' => false,
            'code' => 401,
            'error' => 'UNAUTHORIZED',
            'message' => 'Geçersiz veya süresi dolmuş token',
        ]);
    }
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Oturum aktif.',
        'data' => [
            'token' => $sessionToken,
            'user_id' => (int) ($user['id'] ?? 0),
            'user' => $user,
        ],
    ]);
}

if ($method === 'POST' && ($route === 'logout.php' || $route === 'auth/logout')) {
    $pdo = AdminDatabase::pdo();
    $memberJwtRevokeCurrent($pdo);
    $csrf = $_SESSION['csrf_token'] ?? null;
    $ref = $_SESSION['referral_code'] ?? null;
    $_SESSION = [];
    if ($csrf !== null) {
        $_SESSION['csrf_token'] = $csrf;
    }
    if ($ref !== null) {
        $_SESSION['referral_code'] = $ref;
    }
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Çıkış başarılı. Güle güle!',
        'data' => new stdClass(),
    ]);
}

if ($method === 'POST' && ($route === 'forgot_password.php' || $route === 'auth/forgot-password')) {
    $input = $memberInput($payload);
    $email = trim((string) ($input['email'] ?? ''));
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $memberEnvelope(422, [
            'success' => false,
            'code' => 422,
            'message' => 'Geçerli bir e-posta adresi girin.',
        ]);
    }
    $pdo = AdminDatabase::pdo();
    $userStmt = $pdo->prepare('SELECT id, email FROM users WHERE email = :email LIMIT 1');
    $userStmt->execute(['email' => $email]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($user)) {
        $token = bin2hex(random_bytes(32));
        $pdo->prepare('UPDATE users SET verify_token = :token WHERE id = :id')
            ->execute(['token' => $token, 'id' => (int) ($user['id'] ?? 0)]);
    }
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Eğer e-posta sistemde kayıtlıysa şifre sıfırlama bağlantısı gönderilecektir.',
    ]);
}

if ($method === 'POST' && ($route === 'reset_password.php' || $route === 'auth/reset-password')) {
    $input = $memberInput($payload);
    $token = trim((string) ($input['token'] ?? $input['reset_token'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $passwordConfirmation = (string) ($input['password_confirmation'] ?? $input['confirm_password'] ?? '');
    if ($token === '') {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Sıfırlama anahtarı gerekli.']);
    }
    if ($password === '' || strlen($password) < 6) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Şifre en az 6 karakter olmalıdır.']);
    }
    if ($passwordConfirmation !== '' && $password !== $passwordConfirmation) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Şifre tekrarı eşleşmiyor.']);
    }
    $pdo = AdminDatabase::pdo();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE verify_token = :token LIMIT 1');
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($user)) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Geçersiz veya süresi dolmuş token.']);
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE users SET password = :password, password_changed_at = NOW(), verify_token = NULL WHERE id = :id')
        ->execute(['password' => $hash, 'id' => (int) ($user['id'] ?? 0)]);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Şifreniz başarıyla güncellendi.',
    ]);
}

if ($method === 'POST' && ($route === 'password_reset.php' || $route === 'auth/password-reset')) {
    $input = $memberInput($payload);
    $action = strtolower(trim((string) ($input['action'] ?? '')));
    if ($action === 'request' || $action === 'forgot') {
        $email = trim((string) ($input['email'] ?? ''));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Geçerli bir e-posta adresi girin.']);
        }
        $pdo = AdminDatabase::pdo();
        $userStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $userStmt->execute(['email' => $email]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($user)) {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare('UPDATE users SET verify_token = :token WHERE id = :id')
                ->execute(['token' => $token, 'id' => (int) ($user['id'] ?? 0)]);
        }
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Eğer e-posta sistemde kayıtlıysa şifre sıfırlama bağlantısı gönderilecektir.',
        ]);
    }

    if ($action === 'confirm' || $action === 'reset') {
        $token = trim((string) ($input['token'] ?? $input['reset_token'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $passwordConfirmation = (string) ($input['password_confirmation'] ?? $input['confirm_password'] ?? '');
        if ($token === '') {
            $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Sıfırlama anahtarı gerekli.']);
        }
        if ($password === '' || strlen($password) < 6) {
            $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Şifre en az 6 karakter olmalıdır.']);
        }
        if ($passwordConfirmation !== '' && $password !== $passwordConfirmation) {
            $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Şifre tekrarı eşleşmiyor.']);
        }
        $pdo = AdminDatabase::pdo();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE verify_token = :token LIMIT 1');
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($user)) {
            $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Geçersiz veya süresi dolmuş token.']);
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password = :password, password_changed_at = NOW(), verify_token = NULL WHERE id = :id')
            ->execute(['password' => $hash, 'id' => (int) ($user['id'] ?? 0)]);
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Şifreniz başarıyla güncellendi.',
        ]);
    }

    $memberEnvelope(422, [
        'success' => false,
        'code' => 422,
        'message' => 'Geçersiz action. request|forgot|confirm|reset kullanın.',
    ]);
}

if (($route === 'call_me_request.php' || $route === 'call-me-request') && in_array($method, ['GET', 'POST'], true)) {
    $pdo = AdminDatabase::pdo();
    $callerUserId = null;
    $callerUsername = '';
    $resolvedId = $memberJwtOptionalUserId($pdo);
    if (($resolvedId ?? 0) > 0) {
        $callerUserId = $resolvedId;
        $caller = $memberUserById($pdo, $resolvedId);
        $callerUsername = is_array($caller) ? (string) ($caller['username'] ?? '') : '';
    }
    if ($method === 'GET') {
        // Aranma taleplerinin listelenmesi yalnızca yönetim panelinde yapılır.
        // Public uçtan kişisel veri (ad/telefon) sızdırmamak için listeleme kapalı.
        $memberEnvelope(405, [
            'success' => false,
            'code' => 405,
            'message' => 'Bu uç yalnızca aranma talebi oluşturmak için kullanılabilir.',
        ]);
    }
    $input = $memberInput($payload);
    $fullName = trim((string) ($input['full_name'] ?? $input['name'] ?? ''));
    $phone = trim((string) ($input['phone'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $preferredTime = trim((string) ($input['preferred_time'] ?? $input['preferredTime'] ?? ''));
    $message = trim((string) ($input['message'] ?? ''));
    if ($fullName === '' || $phone === '') {
        $memberEnvelope(422, [
            'success' => false,
            'code' => 422,
            'message' => 'Ad soyad ve telefon zorunludur.',
        ]);
    }
    $insert = $pdo->prepare(
        'INSERT INTO call_me_requests
        (user_id, full_name, username, phone, email, preferred_time, message, status, ip_address, user_agent, created_at, updated_at)
        VALUES
        (:user_id, :full_name, :username, :phone, :email, :preferred_time, :message, :status, :ip_address, :user_agent, NOW(), NOW())'
    );
    $insert->execute([
        'user_id' => $callerUserId,
        'full_name' => $fullName,
        'username' => $callerUsername,
        'phone' => $phone,
        'email' => $email !== '' ? $email : null,
        'preferred_time' => $preferredTime !== '' ? $preferredTime : null,
        'message' => $message !== '' ? $message : null,
        'status' => 'pending',
        'ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
    ]);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Aranma talebiniz alınmıştır.',
        'data' => ['id' => (int) $pdo->lastInsertId()],
    ]);
}

if (($route === 'promotions.php' || $route === 'content/promotions') && in_array($method, ['GET', 'POST'], true)) {
    $pdo = AdminDatabase::pdo();
    $viewerUserId = $memberJwtOptionalUserId($pdo);
    if ($method === 'GET') {
        $category = trim((string) ($_GET['category'] ?? ''));
        $now = date('Y-m-d H:i:s');
        $where = ["status = 'active'", '(start_date IS NULL OR start_date <= :now_start)', '(end_date IS NULL OR end_date >= :now_end)'];
        $params = ['now_start' => $now, 'now_end' => $now];
        if ($category !== '') {
            $where[] = 'type = :category';
            $params['category'] = $category;
        }
        $sql = 'SELECT id, title, description, long_description, type, terms, image_url, general_rules
                FROM promotions WHERE ' . implode(' AND ', $where) . ' ORDER BY sort_order ASC, id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $promotions = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $promotions[] = [
                'id' => (int) ($row['id'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'long_description' => (string) ($row['long_description'] ?? ''),
                'category' => (string) ($row['type'] ?? ''),
                'terms' => (string) ($row['terms'] ?? ''),
                'image_url' => (string) ($row['image_url'] ?? ''),
                'general_rules' => (string) ($row['general_rules'] ?? ''),
            ];
        }
        $hasConfirmedDeposit = false;
        if (($viewerUserId ?? 0) > 0) {
            MegaPayzService::bootstrap($pdo);
            $check = $pdo->prepare("SELECT COUNT(*) FROM megapayz_transactions WHERE user_id = :user_id AND type = 'deposit' AND status = 'confirmed'");
            $check->execute(['user_id' => (int) $viewerUserId]);
            $hasConfirmedDeposit = (int) $check->fetchColumn() > 0;
        }
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Promosyonlar başarıyla alındı',
            'data' => [
                'category' => $category !== '' ? $category : null,
                'total' => count($promotions),
                'promotions' => $promotions,
                'claimPolicy' => [
                    'requiresConfirmedDeposit' => true,
                    'depositRequiredMessage' => 'Bu bonustan faydalanabilmeniz için yatırım yapmanız gerekmektedir.',
                ],
                'viewer' => ['hasConfirmedDeposit' => $hasConfirmedDeposit],
            ],
        ]);
    }

    $userId = $memberRequireLogin();
    $input = $memberInput($payload);
    $promotionId = (int) ($input['promotionId'] ?? $input['promotion_id'] ?? 0);
    if ($promotionId <= 0) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'promotionId zorunludur.']);
    }
    $promotionStmt = $pdo->prepare("SELECT id, title, type, bonus_type, bonus_amount, wagering_multiplier FROM promotions WHERE id = :id AND status = 'active' LIMIT 1");
    $promotionStmt->execute(['id' => $promotionId]);
    $promotion = $promotionStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($promotion)) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Promosyon bulunamadı.']);
    }
    $existing = $pdo->prepare("SELECT id FROM bonus_claim_requests WHERE user_id = :user_id AND promotion_id = :promotion_id AND status = 'pending' LIMIT 1");
    $existing->execute(['user_id' => $userId, 'promotion_id' => $promotionId]);
    $existingRow = $existing->fetch(PDO::FETCH_ASSOC);
    $replacedPending = false;
    if (is_array($existingRow)) {
        $pdo->prepare('DELETE FROM bonus_claim_requests WHERE id = :id')->execute(['id' => (int) $existingRow['id']]);
        $replacedPending = true;
    }
    $requestedAmount = round((float) ($promotion['bonus_amount'] ?? 0), 2);
    $wagering = round((float) ($promotion['wagering_multiplier'] ?? 1), 2);
    $userMessage = trim((string) ($input['message'] ?? ''));
    $insertClaim = $pdo->prepare(
        "INSERT INTO bonus_claim_requests
        (user_id, promotion_id, bonus_name, category, promotion_type, requested_amount, wagering_multiplier, user_message, status, created_at)
        VALUES
        (:user_id, :promotion_id, :bonus_name, :category, :promotion_type, :requested_amount, :wagering_multiplier, :user_message, 'pending', NOW())"
    );
    $insertClaim->execute([
        'user_id' => $userId,
        'promotion_id' => (int) ($promotion['id'] ?? 0),
        'bonus_name' => (string) ($promotion['title'] ?? ''),
        'category' => (string) ($promotion['type'] ?? ''),
        'promotion_type' => (string) ($promotion['bonus_type'] ?? ''),
        'requested_amount' => number_format($requestedAmount, 2, '.', ''),
        'wagering_multiplier' => number_format($wagering, 2, '.', ''),
        'user_message' => $userMessage !== '' ? $userMessage : null,
    ]);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Bonus talebi oluşturuldu',
        'data' => [
            'requestId' => (string) $pdo->lastInsertId(),
            'requestedAmount' => $requestedAmount,
            'message' => 'Bonus talebiniz alındı, incelenmeyi bekliyor.',
            'replacedPending' => $replacedPending,
        ],
    ]);
}

if ($method === 'GET' && ($route === 'balance.php' || $route === 'account/balance')) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    $user = $memberUserById($pdo, $userId);
    if (!$user) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Kullanıcı bulunamadı.']);
    }
    $mainBalance = (float) ($user['balance'] ?? 0);
    $bonusBalance = (float) ($user['bonus_balance'] ?? 0);
    $totalBalance = round($mainBalance + $bonusBalance, 2);
    $formatBalance = static function (float $amount): string {
        return number_format($amount, 2, ',', '.') . ' ₺';
    };
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Bakiye bilgisi',
        'data' => [
            'balance' => [
                'balance' => $mainBalance,
                'bonus_balance' => $bonusBalance,
                'total_balance' => $totalBalance,
                'formatted' => $formatBalance($mainBalance),
                'bonus_formatted' => $formatBalance($bonusBalance),
                'total_formatted' => $formatBalance($totalBalance),
                'currency' => 'TRY',
                'currency_symbol' => '₺',
            ],
            'amount' => $mainBalance,
            'bonus_balance' => $bonusBalance,
            'total_balance' => $totalBalance,
            'ana_bakiye' => $mainBalance,
            'bonus_bakiye' => $bonusBalance,
            'toplam_bonus' => $bonusBalance,
        ],
    ]);
}

if ($method === 'GET' && in_array($route, ['loyalty.php', 'loyalty/me', 'loyalty/levels'], true)) {
    $pdo = AdminDatabase::pdo();
    require_once BASE_PATH . '/api/bootstrap.php';
    ApiLoyalty::ensureStorage($pdo);

    if ($route === 'loyalty/levels') {
        $stmt = $pdo->query(
            'SELECT code, name, min_points, cashback_rate, weekly_bonus_amount, icon_url, color_hex, sort_order
             FROM loyalty_levels
             WHERE is_active = 1
             ORDER BY min_points ASC, sort_order ASC, id ASC'
        );
        $levels = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Sadakat seviyeleri',
            'data' => [
                'levels' => array_map(static fn (array $level): array => [
                    'code' => (string) ($level['code'] ?? ''),
                    'name' => (string) ($level['name'] ?? ''),
                    'min_points' => (int) ($level['min_points'] ?? 0),
                    'cashback_rate' => (float) ($level['cashback_rate'] ?? 0),
                    'weekly_bonus_amount' => (float) ($level['weekly_bonus_amount'] ?? 0),
                    'icon_url' => (string) ($level['icon_url'] ?? ''),
                    'color_hex' => (string) ($level['color_hex'] ?? ''),
                    'sort_order' => (int) ($level['sort_order'] ?? 0),
                ], $levels),
            ],
        ]);
    }

    $userId = $memberRequireLogin();
    $loyalty = ApiLoyalty::fetchForUser($userId);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Sadakat bilgisi',
        'data' => $loyalty + ['badge' => ApiLoyalty::publicBadgeForUser($userId)],
    ]);
}

if ($method === 'GET' && in_array($route, ['me', 'profile_detail.php', 'profile/detail', 'account/profile', 'account/detail', 'user/profile'], true)) {
    $pdo = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    $user = $memberUserById($pdo, $userId);
    if (!$user) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Kullanıcı bulunamadı.']);
    }
    $deposits = MegaPayzService::history($pdo, $userId, 'deposit', ['limit' => 25]);
    $withdrawals = MegaPayzService::history($pdo, $userId, 'withdraw', ['limit' => 25]);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Profil detayı',
        'data' => [
            'user' => $user,
            'deposits' => $deposits['items'],
            'withdrawals' => $withdrawals['items'],
        ],
    ]);
}

if (in_array($method, ['POST', 'PUT', 'PATCH'], true) && in_array($route, ['me', 'profile_update.php', 'profile/update', 'account/update', 'user/update'], true)) {
    $pdo = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    $input = $memberInput($payload);

    // Eski frontend alan adlarını da destekle (first_name, tc, birth_date vb.)
    $aliases = [
        'first_name' => 'name',
        'firstName' => 'name',
        'last_name' => 'surname',
        'lastName' => 'surname',
        'family_name' => 'surname',
        'profile_email' => 'email',
        'profile_phone' => 'phone',
        'mobile' => 'phone',
        'birth_date' => 'dob',
        'birthday' => 'dob',
        'date_of_birth' => 'dob',
        'tc' => 'identity_number',
        'tc_no' => 'identity_number',
        'identityNumber' => 'identity_number',
        'identity' => 'identity_number',
    ];
    foreach ($aliases as $from => $to) {
        if (!array_key_exists($to, $input) && array_key_exists($from, $input)) {
            $input[$to] = $input[$from];
        }
    }

    $allowed = ['name', 'surname', 'email', 'phone', 'city', 'country', 'address', 'dob', 'gender', 'identity_number'];
    $data = [];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $input)) {
            $data[$field] = trim((string) $input[$field]);
        }
    }

    if (isset($data['gender']) && $data['gender'] !== '') {
        $g = function_exists('mb_strtolower')
            ? mb_strtolower($data['gender'], 'UTF-8')
            : strtolower($data['gender']);
        $genderMap = [
            'male' => 'Erkek',
            'erkek' => 'Erkek',
            'female' => 'Kadın',
            'kadin' => 'Kadın',
            'kadın' => 'Kadın',
            'other' => 'Diğer',
            'diger' => 'Diğer',
            'diğer' => 'Diğer',
        ];
        $data['gender'] = $genderMap[$g] ?? $data['gender'];
    }

    $currentPassword = trim((string) ($input['current_password'] ?? ''));
    if ($currentPassword !== '') {
        $pwdStmt = $pdo->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
        $pwdStmt->execute(['id' => $userId]);
        $hash = (string) $pwdStmt->fetchColumn();
        if (!$memberPasswordMatches($currentPassword, $hash)) {
            $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Mevcut şifre hatalı.']);
        }
    }

    if (isset($data['email']) && $data['email'] !== '' && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Geçerli bir e-posta adresi girin.']);
    }
    if (isset($data['email'])) {
        $dup = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email AND id <> :id');
        $dup->execute(['email' => $data['email'], 'id' => $userId]);
        if ((int) $dup->fetchColumn() > 0) {
            $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Bu e-posta başka bir kullanıcıya ait.']);
        }
    }
    if ($data === []) {
        $memberEnvelope(200, ['success' => true, 'code' => 200, 'message' => 'Güncellenecek alan yok.', 'data' => ['updated' => false]]);
    }
    $set = [];
    foreach (array_keys($data) as $field) {
        $set[] = $field . ' = :' . $field;
    }
    $data['id'] = $userId;
    $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id');
    $stmt->execute($data);
    $user = $memberUserById($pdo, $userId);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Profil güncellendi.',
        'data' => ['updated' => true, 'user' => $user],
    ]);
}

if ($method === 'GET' && ($route === 'deposit_history.php' || $route === 'history/deposits')) {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    $history = MegaPayzService::history($pdo, $userId, 'deposit', $_GET);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Yatırım geçmişi',
        'data' => [
            'items' => $history['items'],
            'deposits' => $history['items'],
            'pagination' => $history['pagination'],
        ],
    ]);
}

if ($method === 'GET' && ($route === 'withdraw_history.php' || $route === 'history/withdrawals')) {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    $history = MegaPayzService::history($pdo, $userId, 'withdraw', $_GET);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Çekim geçmişi',
        'data' => [
            'items' => $history['items'],
            'withdrawals' => $history['items'],
            'pagination' => $history['pagination'],
        ],
    ]);
}

if ($method === 'GET' && ($route === 'bonus_claims_me.php' || $route === 'bonus/claims/me')) {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
    $stmt = $pdo->prepare('SELECT id, promotion_id, bonus_name, category, requested_amount, wagering_multiplier, status, created_at, processed_at FROM bonus_claim_requests WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit');
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = array_map(static function (array $row): array {
        $wagering = $row['wagering_multiplier'] ?? null;
        return [
            'id' => (int) ($row['id'] ?? 0),
            'promotionId' => (int) ($row['promotion_id'] ?? 0),
            'bonusName' => (string) ($row['bonus_name'] ?? ''),
            'category' => (string) ($row['category'] ?? ''),
            'requestedAmount' => (float) ($row['requested_amount'] ?? 0),
            'wageringMultiplier' => $wagering !== null ? (float) $wagering : null,
            'wageringMultiplierLabel' => $wagering !== null ? rtrim(rtrim(number_format((float) $wagering, 2, '.', ''), '0'), '.') . 'x' : null,
            'status' => (string) ($row['status'] ?? ''),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'processedAt' => (string) ($row['processed_at'] ?? ''),
            'rejectReason' => null,
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Bonus taleplerim',
        'data' => [
            'items' => $rows,
            'claims' => $rows,
        ],
    ]);
}

if ($method === 'GET' && ($route === 'payment_methods.php' || $route === 'payment/methods')) {
    $items = MegaPayzService::methods(AdminDatabase::pdo());
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Ödeme yöntemleri',
        'data' => [
            'payment_methods' => $items,
            'methods' => $items,
            'currency' => 'TRY',
        ],
    ]);
}

if ($method === 'GET' && $route === 'site_settings.php') {
    $pdo = AdminDatabase::pdo();
    require_once BASE_PATH . '/api/bootstrap.php';
    $stmt = $pdo->query('SELECT * FROM site_ayarlar ORDER BY id ASC LIMIT 1');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $settings = is_array($row) ? $row : [];
    $publicSettings = class_exists('ApiSiteSettings') ? ApiSiteSettings::normalizePublicSettings($settings) : $settings;
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Site ayarları',
        'data' => $publicSettings !== [] ? $publicSettings : new stdClass(),
    ]);
}

if ($method === 'GET' && $route === 'announcements.php') {
    $pdo = AdminDatabase::pdo();
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("SELECT id, title, description, type, icon_type, priority, created_at
                           FROM announcements
                           WHERE is_active = 1
                             AND (start_date IS NULL OR start_date <= :now_start)
                             AND (end_date IS NULL OR end_date >= :now_end)
                           ORDER BY priority DESC, id DESC
                           LIMIT 100");
    $stmt->execute(['now_start' => $now, 'now_end' => $now]);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Duyurular',
        'data' => ['announcements' => $stmt->fetchAll(PDO::FETCH_ASSOC)],
    ]);
}

if ($method === 'GET' && $route === 'member_inbox_messages.php') {
    $pdo = AdminDatabase::pdo();
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("SELECT id, title, body, link_url, priority, created_at, created_at AS updated_at
                           FROM member_inbox_messages
                           WHERE is_active = 1
                             AND (starts_at IS NULL OR starts_at <= :now_start)
                             AND (ends_at IS NULL OR ends_at >= :now_end)
                           ORDER BY priority DESC, id DESC
                           LIMIT 100");
    $stmt->execute(['now_start' => $now, 'now_end' => $now]);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Mesajlar',
        'data' => ['messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)],
    ]);
}

if ($method === 'GET' && $route === 'games_provider.php') {
    $pdo = AdminDatabase::pdo();
    DrakonService::bootstrap($pdo);
    $rows = DrakonService::providers($pdo, $_GET);
    $providers = [];
    foreach ($rows as $row) {
        $providers[] = [
            'provider_code' => (string) ($row['provider_code'] ?? ''),
            'provider_name' => (string) ($row['provider_name'] ?? ''),
            'name' => (string) ($row['provider_name'] ?? ''),
            'code' => (string) ($row['provider_code'] ?? ''),
            'rtp' => isset($row['rtp']) ? (float) $row['rtp'] : null,
            'game_type' => (int) ($row['game_type'] ?? 0),
        ];
    }
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Oyun sağlayıcıları',
        'data' => ['providers' => $providers],
    ]);
}
if ($method === 'GET' && $route === 'games.php') {
    $pdo = AdminDatabase::pdo();
    $source = strtolower(trim((string) ($_GET['source'] ?? '')));
    $provider = strtolower(trim((string) ($_GET['provider'] ?? $_GET['provider_code'] ?? '')));
    $search = trim((string) ($_GET['search'] ?? ''));
    $sort = strtolower(trim((string) ($_GET['sort'] ?? $_GET['category'] ?? '')));
    $sourceDrakon = in_array($source, ['drakon', 'casino', 'slot', 'slots'], true);
    $tvOnly = in_array($source, ['bgaming', 'tv'], true)
        || (!$sourceDrakon && $provider === 'bgaming')
        || in_array($sort, ['tv', 'tv-games', 'tv_oyunlari'], true);
    if ($tvOnly) {
        $catalog = BgamingService::games($pdo, $_GET);
    } else {
        $catalog = DrakonService::games($pdo, $_GET);
    }
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Oyun listesi',
        'data' => $catalog,
    ]);
}

if ($method === 'GET' && ($route === 'game_history.php' || $route === 'casino_game_history.php')) {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    DrakonService::bootstrap($pdo);
    $source = strtolower(trim((string) ($_GET['source'] ?? $_GET['category'] ?? $_GET['game_type'] ?? '')));
    if ($route === 'casino_game_history.php' && ($source === '' || $source === 'all')) {
        $source = 'all';
    }
    $historyId = trim((string) ($_GET['id'] ?? ''));
    $where = ['t.user_id = :user_id'];
    $params = ['user_id' => $userId];
    if ($historyId !== '') {
        $where[] = 'CAST(t.id AS CHAR) = :history_id';
        $params['history_id'] = $historyId;
    }
    if (in_array($source, ['slot', 'slots', 'casino'], true)) {
        $where[] = "(COALESCE(g.type, 'casino') = 'casino' AND COALESCE(g.game_type, 0) = 0)";
    } elseif (in_array($source, ['live', 'live_casino', 'livecasino'], true)) {
        $where[] = "(COALESCE(g.type, '') = 'live' OR COALESCE(g.game_type, 0) = 1)";
    }
    $stmt = $pdo->prepare("SELECT
                               t.id,
                               t.transaction_id,
                               t.related_transaction_id,
                               t.session_id,
                               t.round_id,
                               t.game_id,
                               COALESCE(NULLIF(t.game_name, ''), g.game_name, t.game_id) AS game_name,
                               COALESCE(g.provider_code, '') AS provider_code,
                               COALESCE(NULLIF(t.provider_name, ''), g.provider_name, '') AS provider_name,
                               COALESCE(g.type, 'casino') AS game_category,
                               COALESCE(g.game_type, 0) AS game_type,
                               t.txn_type,
                               t.status,
                               t.bet_amount,
                               t.win_amount,
                               t.after_balance AS balance_after,
                               t.created_at
                           FROM drakon_transactions t
                           LEFT JOIN drakon_games g ON g.game_id = t.game_id
                           WHERE " . implode(' AND ', $where) . "
                           ORDER BY t.id DESC
                           LIMIT 100");
    $stmt->execute($params);
    $rows = array_map(static function (array $row): array {
        $category = ((string) ($row['game_category'] ?? 'casino') === 'live' || (int) ($row['game_type'] ?? 0) === 1)
            ? 'live_casino'
            : 'slot';
        return [
            'id' => (string) ($row['id'] ?? ''),
            'transactionId' => (string) ($row['transaction_id'] ?? ''),
            'transaction_id' => (string) ($row['transaction_id'] ?? ''),
            'providerTxnId' => (string) ($row['transaction_id'] ?? ''),
            'provider_txn_id' => (string) ($row['transaction_id'] ?? ''),
            'relatedTransactionId' => (string) ($row['related_transaction_id'] ?? ''),
            'related_transaction_id' => (string) ($row['related_transaction_id'] ?? ''),
            'sessionToken' => (string) ($row['session_id'] ?? ''),
            'session_id' => (string) ($row['session_id'] ?? ''),
            'roundId' => (string) ($row['round_id'] ?? ''),
            'round_id' => (string) ($row['round_id'] ?? ''),
            'gameId' => (string) ($row['game_id'] ?? ''),
            'game_id' => (string) ($row['game_id'] ?? ''),
            'gameName' => (string) ($row['game_name'] ?? ''),
            'game_name' => (string) ($row['game_name'] ?? ''),
            'providerName' => (string) ($row['provider_name'] ?? ''),
            'provider_name' => (string) ($row['provider_name'] ?? ''),
            'providerCode' => (string) ($row['provider_code'] ?? ''),
            'provider_code' => (string) ($row['provider_code'] ?? ''),
            'source' => $category,
            'category' => $category,
            'wallet' => 'main',
            'txnType' => (string) ($row['txn_type'] ?? ''),
            'txn_type' => (string) ($row['txn_type'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'betAmount' => (float) ($row['bet_amount'] ?? 0),
            'bet_amount' => (float) ($row['bet_amount'] ?? 0),
            'winAmount' => (float) ($row['win_amount'] ?? 0),
            'win_amount' => (float) ($row['win_amount'] ?? 0),
            'balanceAfter' => $row['balance_after'] ?? null,
            'balance_after' => $row['balance_after'] ?? null,
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Oyun geçmişi',
        'data' => [
            'items' => $rows,
            'transactions' => $rows,
            'total' => count($rows),
            'source' => $source,
        ],
    ]);
}

if ($method === 'GET' && $route === 'winners.php') {
    $pdo = AdminDatabase::pdo();
    DrakonService::bootstrap($pdo);
    BgamingService::bootstrap($pdo);
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
    $tab = ($_GET['winners_tab'] ?? $_GET['tab'] ?? 'recent') === 'top' ? 'top' : 'recent';
    $period = (string) ($_GET['winners_period'] ?? $_GET['period'] ?? 'day');
    if (!in_array($period, ['day', 'week', 'month', 'all'], true)) {
        $period = 'day';
    }
    $drakonPeriodSql = match ($period) {
        'week' => ' AND t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
        'month' => ' AND t.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)',
        'all' => '',
        default => ' AND t.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)',
    };
    $bgamingPeriodSql = match ($period) {
        'week' => ' AND COALESCE(t.processed_at, t.created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
        'month' => ' AND COALESCE(t.processed_at, t.created_at) >= DATE_SUB(NOW(), INTERVAL 1 MONTH)',
        'all' => '',
        default => ' AND COALESCE(t.processed_at, t.created_at) >= DATE_SUB(NOW(), INTERVAL 1 DAY)',
    };

    $winnerSql = "SELECT *
                  FROM (
                      SELECT
                          u.username,
                          t.user_id,
                          t.game_id,
                          COALESCE(g.game_name, t.game_id) AS game_name,
                          COALESCE(NULLIF(g.provider_name, ''), 'Drakon') AS provider_name,
                          COALESCE(NULLIF(g.image_url, ''), NULLIF(g.banner, ''), '') AS image_url,
                          COALESCE(NULLIF(g.banner, ''), NULLIF(g.image_url, ''), '') AS banner,
                          t.win_amount,
                          t.created_at,
                          t.id AS sort_id,
                          'drakon' AS source
                      FROM drakon_transactions t
                      LEFT JOIN users u ON u.id = t.user_id
                      LEFT JOIN drakon_games g ON g.game_id = t.game_id
                      WHERE t.txn_type = 'win' AND t.win_amount > 0{$drakonPeriodSql}
                      UNION ALL
                      SELECT
                          u.username,
                          t.user_id,
                          t.game_identifier AS game_id,
                          COALESCE(g.title, t.game_identifier) AS game_name,
                          COALESCE(NULLIF(g.provider, ''), 'BGaming') AS provider_name,
                          COALESCE(g.thumbnail_url, '') AS image_url,
                          COALESCE(g.thumbnail_url, '') AS banner,
                          t.amount AS win_amount,
                          COALESCE(t.processed_at, t.created_at) AS created_at,
                          t.id AS sort_id,
                          'bgaming' AS source
                      FROM bgaming_transactions t
                      LEFT JOIN users u ON u.id = t.user_id
                      LEFT JOIN bgaming_games g ON g.identifier = t.game_identifier
                      WHERE t.txn_type IN ('win', 'promo_win', 'freespins_win') AND t.amount > 0{$bgamingPeriodSql}
                  ) winners_union";

    $maskUsername = static function (mixed $value): string {
        $username = (string) ($value ?: 'Uye');
        return $username !== ''
            ? substr($username, 0, 2) . str_repeat('*', max(3, strlen($username) - 2))
            : 'Uye***';
    };

    if ($tab === 'top') {
        $stmt = $pdo->prepare($winnerSql . ' ORDER BY created_at DESC, sort_id DESC LIMIT 2000');
        $stmt->execute();
        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (string) ((int) ($row['user_id'] ?? 0) > 0 ? $row['user_id'] : ($row['username'] ?? 'guest'));
            if (!isset($grouped[$key])) {
                $grouped[$key] = $row;
                $grouped[$key]['total_win_amount'] = 0.0;
                $grouped[$key]['last_win_at'] = (string) ($row['created_at'] ?? '');
                $grouped[$key]['last_game_name'] = (string) ($row['game_name'] ?? '');
                $grouped[$key]['last_provider_name'] = (string) ($row['provider_name'] ?? '');
            }
            $grouped[$key]['total_win_amount'] += (float) ($row['win_amount'] ?? 0);
        }
        $groupedRows = array_values($grouped);
        usort($groupedRows, static fn (array $a, array $b): int => (float) ($b['total_win_amount'] ?? 0) <=> (float) ($a['total_win_amount'] ?? 0));
        $groupedRows = array_slice($groupedRows, 0, $limit);

        $rows = array_map(static function (array $row) use ($maskUsername): array {
            $username = (string) ($row['username'] ?? 'Uye');
            $masked = $maskUsername($username);
            return [
                'player' => $masked,
                'user_mask' => $masked,
                'totalWinAmount' => (float) ($row['total_win_amount'] ?? 0),
                'total_win_amount' => (float) ($row['total_win_amount'] ?? 0),
                'lastWinAt' => (string) ($row['last_win_at'] ?? ''),
                'last_win_at' => (string) ($row['last_win_at'] ?? ''),
                'gameName' => (string) ($row['last_game_name'] ?? $row['game_name'] ?? ''),
                'game_name' => (string) ($row['last_game_name'] ?? $row['game_name'] ?? ''),
                'providerName' => (string) ($row['last_provider_name'] ?? $row['provider_name'] ?? ''),
                'provider_name' => (string) ($row['last_provider_name'] ?? $row['provider_name'] ?? ''),
                'gameImageUrl' => (string) ($row['image_url'] ?? ''),
                'game_image_url' => (string) ($row['image_url'] ?? ''),
                'game_image' => (string) ($row['image_url'] ?? ''),
                'image_url' => (string) ($row['image_url'] ?? ''),
                'thumbnail_url' => (string) ($row['image_url'] ?? ''),
                'banner' => (string) ($row['image_url'] ?? ''),
                'cover' => (string) ($row['image_url'] ?? ''),
                'source' => (string) ($row['source'] ?? ''),
            ];
        }, $groupedRows);
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'En çok kazananlar',
            'data' => [
                'winners' => $rows,
                'items' => $rows,
                'total' => count($rows),
                'tab' => 'top',
                'winners_tab' => 'top',
                'period' => $period,
                'winners_period' => $period,
            ],
        ]);
    }
    $stmt = $pdo->prepare($winnerSql . ' ORDER BY created_at DESC, sort_id DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = array_map(static function (array $row) use ($maskUsername): array {
        $username = (string) ($row['username'] ?? 'Uye');
        $masked = $maskUsername($username);
        return [
            'player' => $masked,
            'user_mask' => $masked,
            'gameName' => (string) ($row['game_name'] ?? ''),
            'game_name' => (string) ($row['game_name'] ?? ''),
            'providerName' => (string) ($row['provider_name'] ?? ''),
            'provider_name' => (string) ($row['provider_name'] ?? ''),
            'gameId' => (string) ($row['game_id'] ?? ''),
            'game_id' => (string) ($row['game_id'] ?? ''),
            'gameImageUrl' => (string) ($row['image_url'] ?? ''),
            'game_image_url' => (string) ($row['image_url'] ?? ''),
            'game_image' => (string) ($row['image_url'] ?? ''),
            'image_url' => (string) ($row['image_url'] ?? ''),
            'thumbnail_url' => (string) ($row['image_url'] ?? ''),
            'banner' => (string) ($row['banner'] ?? ''),
            'cover' => (string) ($row['image_url'] ?? ''),
            'winAmount' => (float) ($row['win_amount'] ?? 0),
            'win_amount' => (float) ($row['win_amount'] ?? 0),
            'amount' => (float) ($row['win_amount'] ?? 0),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'source' => (string) ($row['source'] ?? ''),
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Kazananlar',
        'data' => [
            'winners' => $rows,
            'items' => $rows,
            'total' => count($rows),
            'tab' => 'recent',
            'winners_tab' => 'recent',
            'period' => $period,
            'winners_period' => $period,
        ],
    ]);
}

if (in_array($method, ['GET', 'POST'], true) && $route === 'track_visit.php') {
    $pdo = AdminDatabase::pdo();
    $ip = (string) ($_SERVER['HTTP_CLIENT_IP'] ?? '');
    if ($ip === '' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim((string) ($parts[0] ?? ''));
    }
    if ($ip === '') {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }
    $input = $memberInput($payload);
    $countryCode = trim((string) ($input['country_code'] ?? $input['countryCode'] ?? ''));
    $countryName = trim((string) ($input['country_name'] ?? $input['country'] ?? ''));
    $region = trim((string) ($input['region'] ?? ''));
    $city = trim((string) ($input['city'] ?? ''));
    $lat = isset($input['lat']) ? (float) $input['lat'] : null;
    $lon = isset($input['lon']) ? (float) $input['lon'] : null;
    $stmt = $pdo->prepare(
        'INSERT INTO visitor_logs
        (ip, ip_address, country_code, country_name, region, city, lat, lon, user_agent, referer, created_at)
        VALUES
        (:ip, :ip_address, :country_code, :country_name, :region, :city, :lat, :lon, :user_agent, :referer, NOW())'
    );
    $stmt->execute([
        'ip' => $ip !== '' ? $ip : '0.0.0.0',
        'ip_address' => $ip !== '' ? $ip : null,
        'country_code' => $countryCode !== '' ? $countryCode : null,
        'country_name' => $countryName !== '' ? $countryName : null,
        'region' => $region !== '' ? $region : null,
        'city' => $city !== '' ? $city : null,
        'lat' => $lat,
        'lon' => $lon,
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        'referer' => substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500),
    ]);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Ziyaret kaydedildi.',
        'data' => [
            'id' => (string) $pdo->lastInsertId(),
            'country' => $countryName,
            'countryCode' => $countryCode,
        ],
    ]);
}

if ($method === 'GET' && $route === 'active_bonus.php') {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    try {
        $stmt = $pdo->prepare("SELECT id, name, category, initial_amount, current_bonus_balance, wagering_requirement, wagering_target, total_bet_amount, is_complete, status, granted_at, deadline
                               FROM user_active_bonuses
                               WHERE user_id = :user_id AND status = 'active'
                               ORDER BY id DESC");
        $stmt->execute(['user_id' => $userId]);
        $items = array_map(static function (array $row): array {
            $current = (float) ($row['current_bonus_balance'] ?? $row['initial_amount'] ?? 0);
            $target = (float) ($row['wagering_target'] ?? 0);
            $bet = (float) ($row['total_bet_amount'] ?? 0);
            $progress = $target > 0 ? min(100, max(0, ($bet / $target) * 100)) : null;
            return [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'displayName' => (string) ($row['name'] ?? ''),
                'category' => (string) ($row['category'] ?? ''),
                'amount' => (float) ($row['initial_amount'] ?? 0),
                'initialAmount' => (float) ($row['initial_amount'] ?? 0),
                'currentBonusBalance' => $current,
                'wageringRequirement' => (float) ($row['wagering_requirement'] ?? 0),
                'wageringRequirementLabel' => rtrim(rtrim(number_format((float) ($row['wagering_requirement'] ?? 0), 2, '.', ''), '0'), '.') . 'x',
                'wageringTarget' => $target,
                'totalBetAmount' => $bet,
                'remainingBet' => max(0, $target - $bet),
                'progress' => $progress,
                'isComplete' => (bool) ($row['is_complete'] ?? false),
                'status' => (string) ($row['status'] ?? ''),
                'grantedAt' => (string) ($row['granted_at'] ?? ''),
                'deadline' => (string) ($row['deadline'] ?? ''),
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable) {
        $items = [];
    }
    $activeBonus = $items[0] ?? null;
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Aktif bonuslar',
        'data' => [
            'items' => $items,
            'hasActiveBonus' => $activeBonus !== null,
            'bonus' => $activeBonus,
        ],
    ]);
}

if ($route === 'favorite_slots.php' || $route === 'favorite_live_casino.php') {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    DrakonService::bootstrap($pdo);
    $gameType = $route === 'favorite_slots.php' ? 'casino' : 'live';
    if ($method === 'GET') {
        $stmt = $pdo->prepare("SELECT f.id, f.game_id, g.game_name AS name, g.provider_name AS provider, g.image_url, g.image_url AS thumbnail_url
                               FROM drakon_favorite_games f
                               LEFT JOIN drakon_games g ON g.game_id = f.game_id
                               WHERE f.user_id = :user_id AND (g.type = :type_a OR g.type IS NULL)
                               ORDER BY f.id DESC");
        $stmt->execute(['user_id' => $userId, 'type_a' => $gameType]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = count($rows);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, (int) ($_GET['limit'] ?? 50));
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Favori oyunlar',
            'data' => [
                'items' => $rows,
                // Frontend drawer sözleşmesi için geriye uyumlu alanlar
                'games' => $rows,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => $limit > 0 ? (int) ceil($total / $limit) : 1,
                    'has_next' => false,
                    'has_prev' => false,
                ],
            ],
        ]);
    }
    $input = $memberInput($payload);
    $gameId = trim((string) ($input['game_id'] ?? $input['id'] ?? $_GET['game_id'] ?? $_GET['id'] ?? ''));
    if ($gameId === '') {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'game_id zorunludur.']);
    }
    $exists = $pdo->prepare('SELECT id FROM drakon_favorite_games WHERE user_id = :user_id AND game_id = :game_id LIMIT 1');
    $exists->execute(['user_id' => $userId, 'game_id' => $gameId]);
    $row = $exists->fetch(PDO::FETCH_ASSOC);
    if ($method === 'DELETE') {
        if (is_array($row)) {
            $pdo->prepare('DELETE FROM drakon_favorite_games WHERE id = :id')->execute(['id' => (int) $row['id']]);
        }
        $memberEnvelope(200, ['success' => true, 'code' => 200, 'message' => 'Favorilerden kaldırıldı.', 'data' => ['favorited' => false]]);
    }
    if (is_array($row)) {
        $memberEnvelope(200, ['success' => true, 'code' => 200, 'message' => 'Oyun zaten favorilerde.', 'data' => ['favorited' => true, 'already_favorite' => true]]);
    }
    $pdo->prepare('INSERT INTO drakon_favorite_games (user_id, game_id, created_at) VALUES (:user_id, :game_id, NOW())')
        ->execute(['user_id' => $userId, 'game_id' => $gameId]);
    $memberEnvelope(200, ['success' => true, 'code' => 200, 'message' => 'Favorilere eklendi.', 'data' => ['favorited' => true]]);
}

if ($method === 'POST' && in_array($route, ['password_update.php', 'account/password', 'account/password-update', 'user/password'], true)) {
    $userId = $memberRequireLogin();
    $input = $memberInput($payload);
    $currentPassword = (string) ($input['current_password'] ?? $input['old_password'] ?? $input['currentPassword'] ?? $input['oldPassword'] ?? '');
    $newPassword = (string) ($input['password'] ?? $input['new_password'] ?? $input['newPassword'] ?? '');
    $confirmPassword = (string) ($input['password_confirmation'] ?? $input['confirm_password'] ?? $input['passwordConfirmation'] ?? $input['confirmPassword'] ?? '');
    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Tüm şifre alanları zorunludur.']);
    }
    if (strlen($newPassword) < 6) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Yeni şifre en az 6 karakter olmalıdır.']);
    }
    if ($newPassword !== $confirmPassword) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Şifre tekrarı eşleşmiyor.']);
    }
    $pdo = AdminDatabase::pdo();
    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $hash = (string) $stmt->fetchColumn();
    if (!$memberPasswordMatches($currentPassword, $hash)) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Mevcut şifre hatalı.']);
    }
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE users SET password = :password, password_changed_at = NOW() WHERE id = :id')
        ->execute(['password' => $newHash, 'id' => $userId]);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Şifre güncellendi.',
        'data' => [
            'updated' => true,
            'redirect' => null,
        ],
    ]);
}

if (in_array($method, ['GET', 'POST'], true) && $route === 'two_factor.php') {
    $userId = $memberRequireLogin();
    if ($method === 'GET') {
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'İki aşamalı doğrulama durumu',
            'data' => ['enabled' => !empty($_SESSION['twofa_enabled'])],
        ]);
    }
    $input = $memberInput($payload);
    $enabledRaw = $input['enabled'] ?? $input['twofa_enabled'] ?? $input['twoFactorEnabled'] ?? false;
    $enabled = in_array($enabledRaw, [true, 1, '1', 'true', 'on', 'yes'], true);
    $_SESSION['twofa_enabled'] = $enabled;
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => $enabled ? 'İki aşamalı doğrulama etkinleştirildi.' : 'İki aşamalı doğrulama kapatıldı.',
        'data' => [
            'user_id' => $userId,
            'enabled' => $enabled,
        ],
        'enabled' => $enabled,
    ]);
}

if ($method === 'POST' && ($route === 'account_freeze.php' || $route === 'account_unfreeze.php')) {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    if ($route === 'account_freeze.php') {
        $input = $memberInput($payload);
        $password = (string) ($input['password'] ?? $input['current_password'] ?? $input['currentPassword'] ?? '');
        if ($password === '') {
            $memberEnvelope(422, [
                'success' => false,
                'code' => 422,
                'message' => 'Hesabınızı dondurmak için şifrenizi girin.',
                'data' => ['errors' => ['password' => ['Şifre zorunludur.']]],
            ]);
        }
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $hash = (string) $stmt->fetchColumn();
        if (!$memberPasswordMatches($password, $hash)) {
            $memberEnvelope(422, [
                'success' => false,
                'code' => 422,
                'message' => 'Şifre hatalı.',
                'data' => ['errors' => ['password' => ['Şifre hatalı.']]],
            ]);
        }
        $pdo->prepare('INSERT INTO user_account_freeze (user_id, frozen_at) VALUES (:user_id, NOW()) ON DUPLICATE KEY UPDATE frozen_at = VALUES(frozen_at)')
            ->execute(['user_id' => $userId]);
        $memberJwtRevokeCurrent($pdo);
        $csrf = $_SESSION['csrf_token'] ?? null;
        $_SESSION = [];
        if ($csrf !== null) {
            $_SESSION['csrf_token'] = $csrf;
        }
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Hesap donduruldu.',
            'data' => ['redirect' => '/login?account_frozen=1'],
        ]);
    }
    $pdo->prepare('DELETE FROM user_account_freeze WHERE user_id = :user_id')->execute(['user_id' => $userId]);
    $memberEnvelope(200, ['success' => true, 'code' => 200, 'message' => 'Hesap dondurması kaldırıldı.']);
}

if ($method === 'GET' && $route === 'promocodes.php') {
    $pdo = AdminDatabase::pdo();
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare('SELECT id, kod, miktar, son_gecerlilik_tarihi, kullanim_limiti, mevcut_kullanim
                           FROM promocodes
                           WHERE son_gecerlilik_tarihi >= :now
                           ORDER BY son_gecerlilik_tarihi ASC, id DESC');
    $stmt->execute(['now' => $now]);
    $rows = array_map(static function (array $row): array {
        $limit = (int) ($row['kullanim_limiti'] ?? 0);
        $used = (int) ($row['mevcut_kullanim'] ?? 0);
        return [
            'id' => (int) ($row['id'] ?? 0),
            'kod' => (string) ($row['kod'] ?? ''),
            'code' => (string) ($row['kod'] ?? ''),
            'miktar' => (float) ($row['miktar'] ?? 0),
            'amount' => (float) ($row['miktar'] ?? 0),
            'son_gecerlilik_tarihi' => (string) ($row['son_gecerlilik_tarihi'] ?? ''),
            'expiresAt' => (string) ($row['son_gecerlilik_tarihi'] ?? ''),
            'kullanim_limiti' => $limit,
            'usageLimit' => $limit,
            'mevcut_kullanim' => $used,
            'currentUses' => $used,
            'remainingUses' => $limit > 0 ? max(0, $limit - $used) : null,
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Promocode listesi',
        'data' => ['promocodes' => $rows, 'items' => $rows],
    ]);
}

if ($method === 'POST' && $route === 'promocode_request.php') {
    $userId = $memberRequireLogin();
    $input = $memberInput($payload);
    $promocodeId = (int) ($input['promocode_id'] ?? $input['promocodeId'] ?? $input['id'] ?? 0);
    $userMessage = trim((string) ($input['message'] ?? $input['user_message'] ?? ''));
    if ($promocodeId <= 0) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'promocode_id zorunludur.']);
    }
    $pdo = AdminDatabase::pdo();
    $promo = $pdo->prepare('SELECT id, kod, miktar FROM promocodes WHERE id = :id LIMIT 1');
    $promo->execute(['id' => $promocodeId]);
    $row = $promo->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Promocode bulunamadı.']);
    }
    $insert = $pdo->prepare(
        "INSERT INTO promocode_requests
        (user_id, promocode_id, promocode_code, amount, user_message, status, created_at, updated_at)
        VALUES
        (:user_id, :promocode_id, :promocode_code, :amount, :user_message, 'pending', NOW(), NOW())"
    );
    $insert->execute([
        'user_id' => $userId,
        'promocode_id' => (int) ($row['id'] ?? 0),
        'promocode_code' => (string) ($row['kod'] ?? ''),
        'amount' => number_format((float) ($row['miktar'] ?? 0), 2, '.', ''),
        'user_message' => $userMessage !== '' ? $userMessage : null,
    ]);
    $memberEnvelope(200, ['success' => true, 'code' => 200, 'message' => 'Promocode talebi oluşturuldu.', 'data' => ['requestId' => (string) $pdo->lastInsertId()]]);
}

if ($method === 'POST' && $route === 'bonus_use_code.php') {
    $userId = $memberRequireLogin();
    $input = $memberInput($payload);
    $code = trim((string) ($input['kod'] ?? $input['code'] ?? $input['promocode'] ?? ''));
    if ($code === '') {
        $memberEnvelope(422, ['success' => false, 'status' => 'error', 'code' => 422, 'message' => 'Promosyon kodu zorunludur.', 'mesaj' => 'Promosyon kodu zorunludur.']);
    }
    $pdo = AdminDatabase::pdo();
    $now = date('Y-m-d H:i:s');
    $promo = $pdo->prepare('SELECT id, kod, miktar, kullanim_limiti, mevcut_kullanim, son_gecerlilik_tarihi
                            FROM promocodes
                            WHERE kod = :kod AND son_gecerlilik_tarihi >= :now
                            LIMIT 1');
    $promo->execute(['kod' => $code, 'now' => $now]);
    $row = $promo->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        $memberEnvelope(404, ['success' => false, 'status' => 'error', 'code' => 404, 'message' => 'Promosyon kodu bulunamadı veya süresi dolmuş.', 'mesaj' => 'Promosyon kodu bulunamadı veya süresi dolmuş.']);
    }
    $limit = (int) ($row['kullanim_limiti'] ?? 0);
    $used = (int) ($row['mevcut_kullanim'] ?? 0);
    if ($limit > 0 && $used >= $limit) {
        $memberEnvelope(422, ['success' => false, 'status' => 'error', 'code' => 422, 'message' => 'Promosyon kodu kullanım limiti dolmuş.', 'mesaj' => 'Promosyon kodu kullanım limiti dolmuş.']);
    }
    $exists = $pdo->prepare("SELECT id FROM promocode_requests WHERE user_id = :user_id AND promocode_id = :promocode_id AND status IN ('pending','approved') LIMIT 1");
    $exists->execute(['user_id' => $userId, 'promocode_id' => (int) $row['id']]);
    if ($exists->fetch(PDO::FETCH_ASSOC)) {
        $memberEnvelope(409, ['success' => false, 'status' => 'error', 'code' => 409, 'message' => 'Bu promosyon kodu için zaten talebiniz var.', 'mesaj' => 'Bu promosyon kodu için zaten talebiniz var.']);
    }
    $insert = $pdo->prepare(
        "INSERT INTO promocode_requests
        (user_id, promocode_id, promocode_code, amount, user_message, status, created_at, updated_at)
        VALUES
        (:user_id, :promocode_id, :promocode_code, :amount, :user_message, 'pending', NOW(), NOW())"
    );
    $insert->execute([
        'user_id' => $userId,
        'promocode_id' => (int) $row['id'],
        'promocode_code' => (string) $row['kod'],
        'amount' => number_format((float) ($row['miktar'] ?? 0), 2, '.', ''),
        'user_message' => 'Site promosyon kodu kullanımı',
    ]);
    $memberEnvelope(200, [
        'success' => true,
        'status' => 'success',
        'code' => 200,
        'message' => 'Promosyon kodu talebiniz alındı.',
        'mesaj' => 'Promosyon kodu talebiniz alındı.',
        'data' => ['requestId' => (string) $pdo->lastInsertId()],
    ]);
}

if ($method === 'GET' && $route === 'referrals.php') {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    $stmt = $pdo->prepare('SELECT id, username, email, name, surname, referral_code FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($user)) {
        $memberEnvelope(404, ['success' => false, 'status' => 'error', 'code' => 404, 'message' => 'Kullanıcı bulunamadı.']);
    }
    $referralCode = trim((string) ($user['referral_code'] ?? ''));
    if ($referralCode === '') {
        $base = preg_replace('/[^a-z0-9]/i', '', strtolower((string) ($user['username'] ?? 'user')));
        $base = is_string($base) && $base !== '' ? substr($base, 0, 18) : 'user';
        for ($i = 0; $i < 6; $i++) {
            $candidate = strtoupper($base . substr(bin2hex(random_bytes(4)), 0, 8));
            $check = $pdo->prepare('SELECT 1 FROM users WHERE referral_code = :code LIMIT 1');
            $check->execute(['code' => $candidate]);
            if (!$check->fetchColumn()) {
                $referralCode = $candidate;
                break;
            }
        }
        if ($referralCode !== '') {
            $pdo->prepare('UPDATE users SET referral_code = :code WHERE id = :id')->execute(['code' => $referralCode, 'id' => $userId]);
        }
    }
    $referredUsers = [];
    try {
        $refStmt = $pdo->prepare('SELECT id, name AS first_name, surname, username, email, created_at
                                  FROM users
                                  WHERE referred_by_affiliate_id = :user_id
                                  ORDER BY created_at DESC
                                  LIMIT 100');
        $refStmt->execute(['user_id' => $userId]);
        $referredUsers = $refStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        $referredUsers = [];
    }
    $memberEnvelope(200, [
        'success' => true,
        'status' => 'success',
        'code' => 200,
        'message' => 'Referans bilgileri',
        'referral_code' => $referralCode,
        'referred_users' => $referredUsers,
        'data' => [
            'referral_code' => $referralCode,
            'referred_users' => $referredUsers,
        ],
    ]);
}

if ($method === 'POST' && $route === 'bonus_claim.php') {
    $userId = $memberRequireLogin();
    $input = $memberInput($payload);
    $promotionId = (int) ($input['promotionId'] ?? $input['promotion_id'] ?? 0);
    $pdo = AdminDatabase::pdo();
    $promo = null;
    if ($promotionId > 0) {
        $promotion = $pdo->prepare("SELECT id, title, type, bonus_type, bonus_amount, wagering_multiplier FROM promotions WHERE id = :id AND status = 'active' LIMIT 1");
        $promotion->execute(['id' => $promotionId]);
        $promo = $promotion->fetch(PDO::FETCH_ASSOC);
    } else {
        $title = trim((string) ($input['bonusTitle'] ?? $input['bonusTuru'] ?? $input['title'] ?? ''));
        if ($title === '') {
            $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'promotionId veya bonusTitle zorunludur.']);
        }
        $normalizeTitle = static function (string $value): string {
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $value = str_replace(['İ', 'I', 'ı'], ['i', 'i', 'i'], $value);
            $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
            return preg_replace('/[^a-z0-9%]+/u', '', $value) ?: '';
        };
        $wantedTitle = $normalizeTitle($title);
        $promotion = $pdo->query("SELECT id, title, type, bonus_type, bonus_amount, wagering_multiplier FROM promotions WHERE status = 'active' ORDER BY sort_order ASC, id ASC");
        foreach ($promotion->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($wantedTitle !== '' && $normalizeTitle((string) ($row['title'] ?? '')) === $wantedTitle) {
                $promo = $row;
                break;
            }
        }
    }
    if (!is_array($promo)) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Promosyon bulunamadı.']);
    }
    $insert = $pdo->prepare(
        "INSERT INTO bonus_claim_requests
        (user_id, promotion_id, bonus_name, category, promotion_type, requested_amount, wagering_multiplier, user_message, status, created_at)
        VALUES
        (:user_id, :promotion_id, :bonus_name, :category, :promotion_type, :requested_amount, :wagering_multiplier, :user_message, 'pending', NOW())"
    );
    $insert->execute([
        'user_id' => $userId,
        'promotion_id' => (int) ($promo['id'] ?? 0),
        'bonus_name' => (string) ($promo['title'] ?? ''),
        'category' => (string) ($promo['type'] ?? ''),
        'promotion_type' => (string) ($promo['bonus_type'] ?? ''),
        'requested_amount' => number_format((float) ($promo['bonus_amount'] ?? 0), 2, '.', ''),
        'wagering_multiplier' => number_format((float) ($promo['wagering_multiplier'] ?? 1), 2, '.', ''),
        'user_message' => trim((string) ($input['message'] ?? '')) ?: null,
    ]);
    $memberEnvelope(200, ['success' => true, 'code' => 200, 'message' => 'Bonus talebi oluşturuldu', 'data' => ['requestId' => (string) $pdo->lastInsertId()]]);
}

if (in_array($method, ['GET', 'POST'], true) && ($route === 'deposit_payment.php' || $route === 'withdraw_payment.php' || $route === 'payment.php')) {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    $userStmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $userStmt->execute(['id' => $userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($user)) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Kullanıcı bulunamadı.']);
    }
    if ($method === 'GET') {
        $memberEnvelope(200, MegaPayzService::withdrawForm($pdo, $user));
    }
    $input = $memberInput($payload);
    $amount = round((float) str_replace(',', '.', (string) ($input['amount'] ?? '0')), 2);
    $methodKey = trim((string) ($input['payment_method_id'] ?? $input['payment_method'] ?? $input['method'] ?? 'wallet'));
    if ($amount <= 0) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Geçerli bir tutar girin.']);
    }
    if ($route === 'withdraw_payment.php') {
        $fields = is_array($input['input_fields'] ?? null) ? $input['input_fields'] : [];
        $account = trim((string) ($input['account_number'] ?? $input['account'] ?? ''));
        if ($account !== '') {
            $fields['account'] = $account;
        }
        $result = MegaPayzService::createWithdraw($pdo, $user, $methodKey, $amount, $fields);
        $memberEnvelope(!empty($result['success']) ? 200 : 422, $result);
    }
    $result = MegaPayzService::createDeposit($pdo, $user, $methodKey, $amount);
    $memberEnvelope(!empty($result['success']) ? 200 : 422, $result);
}

if ($method === 'POST' && $route === 'sports_launch.php') {
    $userId = $memberRequireLogin();
    $user = $memberUserById(AdminDatabase::pdo(), $userId);
    if (!is_array($user)) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Kullanıcı bulunamadı.']);
    }
    $apiKey = trim((string) getenv('OKKO_SPORTS_API_KEY'));
    $apiSecret = trim((string) getenv('OKKO_SPORTS_API_SECRET'));
    if ($apiKey === '' || $apiSecret === '') {
        $memberEnvelope(503, ['success' => false, 'code' => 503, 'message' => 'Spor servisi yapılandırması eksik.']);
    }
    $input = $memberInput($payload);
    $type = trim((string) ($input['type'] ?? 'match'));
    $lang = trim((string) ($input['lang'] ?? 'tr'));
    $sportsPayload = [
        'api_key' => $apiKey,
        'api_secret' => $apiSecret,
        'user_id' => (string) ($user['id'] ?? $userId),
        'username' => (string) ($user['username'] ?? ''),
        'balance' => (string) max(0.01, (float) ($user['ana_bakiye'] ?? $user['balance'] ?? 0)),
        'type' => $type !== '' ? $type : 'match',
        'lang' => $lang !== '' ? $lang : 'tr',
        'currency' => 'TRY',
        'country' => 'TR',
        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
        'timestamp' => time(),
    ];
    $sportsLaunchUrl = trim((string) (getenv('OKKO_SPORTS_LAUNCH_URL') ?: (defined('OKKO_SPORTS_LAUNCH_URL') ? OKKO_SPORTS_LAUNCH_URL : 'https://my.okkogaming.com/spor-launch')));
    $ch = curl_init($sportsLaunchUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($sportsPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if (!is_string($response) || $response === '') {
        $memberEnvelope(503, ['success' => false, 'code' => 503, 'message' => 'Spor sistemine bağlanılamıyor.', 'error' => $curlError]);
    }
    $decoded = json_decode($response, true);
    if ($httpCode !== 200 || !is_array($decoded) || ($decoded['success'] ?? false) !== true || empty($decoded['iframe_url'])) {
        $memberEnvelope(503, [
            'success' => false,
            'code' => 503,
            'message' => 'Spor sistemi geçici olarak hizmet veremiyor.',
            'providerStatus' => $httpCode,
        ]);
    }
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Spor launch hazır',
        'data' => [
            'iframe_url' => (string) $decoded['iframe_url'],
            'type' => $sportsPayload['type'],
            'lang' => $sportsPayload['lang'],
        ],
    ]);
}

if ($method === 'GET' && in_array($route, ['sports.php', 'sports_events.php', 'sports_leagues.php', 'sports_markets.php'], true)) {
    $memberEnvelope(200, ['success' => true, 'code' => 200, 'message' => 'Spor verisi', 'data' => ['items' => []]]);
}

if ($method === 'POST' && $route === 'game_launch.php') {
    $input = $memberInput($payload);
    $mode = strtolower(trim((string) ($input['mode'] ?? 'real')));
    $isDemo = in_array($mode, ['fun', 'demo'], true) || !empty($input['demo']) || !empty($input['isDemo']);
    $user = null;
    if (!$isDemo) {
        $userId = $memberRequireLogin();
        $user = $memberUserById(AdminDatabase::pdo(), $userId);
    }
    try {
        $gameId = trim((string) ($input['game_id'] ?? $input['gameId'] ?? $input['gameid'] ?? ''));
        $result = BgamingService::ownsGameId($gameId)
            ? BgamingService::launch(AdminDatabase::pdo(), $user, $input)
            : DrakonService::launch(AdminDatabase::pdo(), $user, $input);
        $memberEnvelope(!empty($result['success']) ? 200 : (int) ($result['code'] ?? 422), $result);
    } catch (Throwable $exception) {
        $gameId = trim((string) ($input['game_id'] ?? $input['gameId'] ?? $input['gameid'] ?? ''));
        $isBgamingLaunch = class_exists('BgamingService', false) && BgamingService::ownsGameId($gameId);
        $message = $isBgamingLaunch
            ? 'BGaming oyun başlatma hatası: ' . $exception->getMessage()
            : 'Drakon oyun başlatma hatası: ' . $exception->getMessage();
        $memberEnvelope(503, [
            'success' => false,
            'code' => 503,
            'message' => $message,
            'error' => $exception->getMessage(),
        ]);
    }
}

if (in_array($method, ['GET', 'POST'], true) && $route === 'email_verification.php') {
    $input = $memberInput($payload);
    if ($method === 'GET') {
        $input = array_merge($input, $_GET);
    }
    $action = strtolower(trim((string) ($input['action'] ?? 'request')));
    if ($action === '' && (trim((string) ($input['token'] ?? '')) !== '' || trim((string) ($input['verification_token'] ?? '')) !== '')) {
        $action = 'confirm';
    }
    if (!in_array($action, ['request', 'resend', 'confirm', 'verify'], true)) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Geçersiz action.']);
    }
    if (in_array($action, ['request', 'resend'], true)) {
        $email = trim((string) ($input['email'] ?? ''));
        if ($email === '') {
            $optionalUserId = $memberJwtOptionalUserId(AdminDatabase::pdo());
            if (($optionalUserId ?? 0) > 0) {
                $user = $memberUserById(AdminDatabase::pdo(), (int) $optionalUserId);
                $email = is_array($user) ? trim((string) ($user['email'] ?? '')) : '';
            }
        }
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Geçerli bir e-posta adresi girin.']);
        }
        $token = bin2hex(random_bytes(32));
        $pdo = AdminDatabase::pdo();
        $stmt = $pdo->prepare('UPDATE users SET verify_token = :token WHERE email = :email');
        $stmt->execute(['token' => $token, 'email' => $email]);
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Doğrulama e-postası gönderildi.',
            'data' => ['sent' => true],
        ]);
    }
    $token = trim((string) ($input['token'] ?? $input['verification_token'] ?? ''));
    if ($token === '') {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Token zorunludur.']);
    }
    $pdo = AdminDatabase::pdo();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE verify_token = :token LIMIT 1');
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($user)) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Geçersiz token.']);
    }
    $pdo->prepare('UPDATE users SET is_verified = 1, verify_token = NULL WHERE id = :id')->execute(['id' => (int) ($user['id'] ?? 0)]);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'E-posta doğrulandı.',
        'data' => ['verified' => true],
    ]);
}
// Frontend sliderı için native fallback (legacy bootstrap yoksa bile çalışır).
if (in_array($method, ['GET', 'POST', 'PUT'], true) && ($route === 'content/footer' || $route === 'footer.php')) {
    try {
        require_once BASE_PATH . '/api/bootstrap.php';

        if ($method === 'GET') {
            $footer = ApiFooter::fetch();
            $memberEnvelope(200, [
                'success' => true,
                'code' => 200,
                'message' => 'Footer ayarları',
                'data' => ['footer' => $footer],
            ]);
        }

        $requireAuth();
        $validateCsrf($payload);

        $input = is_array($payload['body'] ?? null) ? $payload['body'] : [];
        $incoming = $input['payload'] ?? $input['footer'] ?? $input;
        if (is_string($incoming)) {
            $decoded = json_decode($incoming, true);
            $incoming = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($incoming)) {
            $error(422, 'Footer payload JSON formatında olmalıdır.');
        }
        unset($incoming['_token']);
        unset($incoming['name'], $incoming['is_active']);

        $current = ApiFooter::fetch();
        $footer = ApiFooter::normalize(array_replace($current, $incoming));
        $name = trim((string) ($input['name'] ?? 'default')) ?: 'default';
        $isActive = (int) ($input['is_active'] ?? 1) === 1 ? 1 : 0;

        $pdo = AdminDatabase::pdo();
        if ($isActive === 1) {
            $pdo->exec('UPDATE footer_settings SET is_active = 0');
        }
        $encodedPayload = json_encode($footer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encodedPayload)) {
            $error(422, 'Footer payload JSON olarak kaydedilemedi.');
        }
        $existing = $pdo->prepare('SELECT id FROM footer_settings WHERE name = :name ORDER BY id DESC LIMIT 1');
        $existing->execute(['name' => $name]);
        $existingId = (int) $existing->fetchColumn();
        if ($existingId > 0) {
            $stmt = $pdo->prepare('UPDATE footer_settings SET payload = :payload, is_active = :is_active WHERE id = :id');
            $stmt->execute([
                'payload' => $encodedPayload,
                'is_active' => $isActive,
                'id' => $existingId,
            ]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO footer_settings (name, payload, is_active) VALUES (:name, :payload, :is_active)');
            $stmt->execute([
                'name' => $name,
                'payload' => $encodedPayload,
                'is_active' => $isActive,
            ]);
        }

        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Footer ayarları güncellendi',
            'data' => ['footer' => $footer],
        ]);
    } catch (Throwable $exception) {
        $error(500, 'Footer verisi işlenemedi.', ['reason' => $exception->getMessage()]);
    }
}

if (in_array($method, ['GET', 'POST', 'PUT'], true) && ($route === 'content/mobile-menu' || $route === 'mobile-menu.php')) {
    try {
        require_once BASE_PATH . '/api/bootstrap.php';

        if ($method === 'GET') {
            $menu = ApiMobileMenu::fetch();
            $memberEnvelope(200, [
                'success' => true,
                'code' => 200,
                'message' => 'Mobil menü ayarları',
                'data' => ['mobile_menu' => $menu],
            ]);
        }

        $requireAuth();
        $validateCsrf($payload);

        $input = is_array($payload['body'] ?? null) ? $payload['body'] : [];
        $incoming = $input['payload'] ?? $input['mobile_menu'] ?? $input;
        if (is_string($incoming)) {
            $decoded = json_decode($incoming, true);
            $incoming = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($incoming)) {
            $error(422, 'Mobil menü payload JSON formatında olmalıdır.');
        }
        unset($incoming['_token']);
        unset($incoming['name'], $incoming['is_active']);

        $current = ApiMobileMenu::fetch();
        $menu = ApiMobileMenu::normalize(array_replace($current, $incoming));
        $name = trim((string) ($input['name'] ?? 'default')) ?: 'default';
        $isActive = (int) ($input['is_active'] ?? 1) === 1 ? 1 : 0;

        $pdo = AdminDatabase::pdo();
        if ($isActive === 1) {
            $pdo->exec('UPDATE mobile_menu_settings SET is_active = 0');
        }
        $encodedPayload = json_encode($menu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encodedPayload)) {
            $error(422, 'Mobil menü payload JSON olarak kaydedilemedi.');
        }
        $existing = $pdo->prepare('SELECT id FROM mobile_menu_settings WHERE name = :name ORDER BY id DESC LIMIT 1');
        $existing->execute(['name' => $name]);
        $existingId = (int) $existing->fetchColumn();
        if ($existingId > 0) {
            $stmt = $pdo->prepare('UPDATE mobile_menu_settings SET payload = :payload, is_active = :is_active WHERE id = :id');
            $stmt->execute([
                'payload' => $encodedPayload,
                'is_active' => $isActive,
                'id' => $existingId,
            ]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO mobile_menu_settings (name, payload, is_active) VALUES (:name, :payload, :is_active)');
            $stmt->execute([
                'name' => $name,
                'payload' => $encodedPayload,
                'is_active' => $isActive,
            ]);
        }

        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Mobil menü ayarları güncellendi',
            'data' => ['mobile_menu' => $menu],
        ]);
    } catch (Throwable $exception) {
        $error(500, 'Mobil menü verisi işlenemedi.', ['reason' => $exception->getMessage()]);
    }
}

if ($method === 'GET' && ($route === 'content/footer-pages' || $route === 'footer_pages.php')) {
    try {
        require_once BASE_PATH . '/api/bootstrap.php';
        $slug = trim((string) ($_GET['slug'] ?? ''));
        if ($slug !== '') {
            $page = ApiFooterPages::findBySlug($slug);
            if ($page === null) {
                $memberEnvelope(404, [
                    'success' => false,
                    'code' => 404,
                    'message' => 'Footer sayfası bulunamadı',
                ]);
            }
            $memberEnvelope(200, [
                'success' => true,
                'code' => 200,
                'message' => 'Footer sayfası',
                'data' => ['page' => $page],
            ]);
        }

        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Footer sayfaları',
            'data' => ['pages' => ApiFooterPages::allActive()],
        ]);
    } catch (Throwable $exception) {
        $error(500, 'Footer sayfaları alınamadı.', ['reason' => $exception->getMessage()]);
    }
}

if ($method === 'GET' && ($route === 'content/homepage-sections' || $route === 'homepage_sections.php')) {
    try {
        require_once BASE_PATH . '/api/bootstrap.php';
        $surface = trim((string) ($_GET['surface'] ?? 'all'));
        $sectionKey = trim((string) ($_GET['section_key'] ?? ''));
        $sections = ApiHomepageSections::fetch([
            'surface' => $surface,
            'section_key' => $sectionKey,
        ]);

        $memberEnvelope(200, [
            'success' => true,
            'ok' => true,
            'code' => 200,
            'message' => 'Ana sayfa vitrin alanları',
            'data' => [
                'surface' => $surface !== '' ? $surface : 'all',
                'section_key' => $sectionKey !== '' ? $sectionKey : null,
                'total' => count($sections),
                'sections' => $sections,
            ],
        ]);
    } catch (Throwable $exception) {
        $error(500, 'Ana sayfa vitrin verisi alınamadı.', ['reason' => $exception->getMessage()]);
    }
}

if ($method === 'GET' && ($route === 'content/sliders' || $route === 'sliders.php')) {
    try {
        require_once BASE_PATH . '/api/bootstrap.php';
        $category = ApiSliders::normalizeCategory((string) ($_GET['category'] ?? $_GET['page'] ?? ''));
        $surface = ApiSliders::normalizeSurface((string) ($_GET['surface'] ?? 'all'));
        $query = [];
        if ($category !== '') {
            $query['category'] = $category;
        }
        if ($surface !== 'all') {
            $query['surface'] = $surface;
        }
        $sliders = ApiSliders::fetchFromDatabase($query);
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'code' => 200,
            'message' => 'Slider listesi',
            'data' => [
                'category' => $category !== '' ? $category : null,
                'surface' => $surface,
                'categories' => ApiSliders::categories(),
                'total' => count($sliders),
                'sliders' => $sliders,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    } catch (Throwable $exception) {
        $error(500, 'Slider verisi alınamadı.', ['reason' => $exception->getMessage()]);
    }
}

if ($method === 'GET' && in_array($route, ['currencies', 'countries', 'languages', 'maintenance-status'], true)) {
    $reference = match ($route) {
        'currencies' => [
            'items' => [['code' => 'TRY', 'symbol' => '₺', 'name' => 'Turkish Lira', 'default' => true]],
            'default' => 'TRY',
        ],
        'countries' => [
            'items' => [['code' => 'TR', 'name' => 'Türkiye', 'default' => true]],
            'default' => 'TR',
        ],
        'languages' => [
            'items' => [['code' => 'tr', 'name' => 'Türkçe', 'default' => true]],
            'default' => 'tr',
        ],
        default => [
            'maintenance' => false,
            'status' => 'online',
        ],
    };
    $success($reference, ['resource' => $route]);
}

if ($method === 'POST' && $route === 'auth/refresh') {
    $pdo = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    $user = $memberUserById($pdo, $userId);
    if (!is_array($user)) {
        $memberEnvelope(404, ['success' => false, 'ok' => false, 'code' => 404, 'message' => 'Kullanıcı bulunamadı.']);
    }
    $token = $memberJwtIssue($pdo, $user);
    $_SESSION['member_jwt'] = $token;
    $success([
        'token' => $token,
        'token_type' => 'Bearer',
        'user' => $user,
    ], ['resource' => 'auth/refresh']);
}

if (in_array($method, ['GET', 'PATCH', 'PUT'], true) && $route === 'me/preferences') {
    $memberRequireLogin();
    if ($method === 'GET') {
        $success([
            'preferences' => [
                'language' => 'tr',
                'currency' => 'TRY',
                'odds_format' => 'decimal',
                'marketing_opt_in' => false,
            ],
        ], ['resource' => 'me/preferences']);
    }
    $professionalAccepted('me/preferences', ['updated' => false]);
}

if ($method === 'POST' && $route === 'auth/verify-phone') {
    $memberRequireLogin();
    $professionalAccepted('auth/verify-phone', ['verified' => false]);
}

if ($method === 'GET' && preg_match('~^pages/([^/]+)$~', $route, $m) === 1) {
    $professionalEmpty('page', [
        'slug' => urldecode((string) $m[1]),
        'page' => null,
    ]);
}

if ($method === 'GET' && $route === 'casino/categories') {
    $items = [
        ['key' => 'slots', 'name' => 'Slot Oyunları'],
        ['key' => 'live-casino', 'name' => 'Canlı Casino'],
        ['key' => 'table-games', 'name' => 'Masa Oyunları'],
        ['key' => 'tv-games', 'name' => 'TV Oyunları'],
        ['key' => 'popular', 'name' => 'Popüler'],
        ['key' => 'new', 'name' => 'Yeni Oyunlar'],
    ];
    $success(['items' => $items, 'categories' => $items, 'total' => count($items)], ['resource' => 'casino/categories']);
}

if (preg_match('~^casino/games/([^/]+)(/launch)?$~', $route, $m) === 1
    || preg_match('~^live-casino/tables/([^/]+)(/launch)?$~', $route, $m) === 1
) {
    $gameId = urldecode((string) $m[1]);
    $isLaunch = ($m[2] ?? '') === '/launch';
    if ($isLaunch && $method === 'POST') {
        $input = $memberInput($payload);
        $input['game_id'] = $input['game_id'] ?? $input['gameId'] ?? $gameId;
        $mode = strtolower(trim((string) ($input['mode'] ?? 'real')));
        $isDemo = in_array($mode, ['fun', 'demo'], true) || !empty($input['demo']) || !empty($input['isDemo']);
        $user = null;
        if (!$isDemo) {
            $userId = $memberRequireLogin();
            $user = $memberUserById(AdminDatabase::pdo(), $userId);
        }
        try {
            $result = BgamingService::ownsGameId((string) $input['game_id'])
                ? BgamingService::launch(AdminDatabase::pdo(), $user, $input)
                : DrakonService::launch(AdminDatabase::pdo(), $user, $input);
            $memberEnvelope(!empty($result['success']) ? 200 : (int) ($result['code'] ?? 422), $result);
        } catch (Throwable $exception) {
            $memberEnvelope(503, [
                'success' => false,
                'ok' => false,
                'code' => 503,
                'message' => 'Oyun başlatma hatası: ' . $exception->getMessage(),
            ]);
        }
    }
    if (!$isLaunch && $method === 'GET') {
        $success([
            'game' => ['id' => $gameId, 'game_id' => $gameId],
        ], [
            'resource' => 'casino/game',
            'status' => 'catalog_lookup_available_via_casino_games',
        ]);
    }
}

if ($route === 'deposits') {
    if ($method === 'GET') {
        $userId = $memberRequireLogin();
        $history = MegaPayzService::history(AdminDatabase::pdo(), $userId, 'deposit', $_GET);
        $success(['items' => $history['items'], 'deposits' => $history['items']], $history['pagination'] ?? []);
    }
    if ($method === 'POST') {
        $userId = $memberRequireLogin();
        $pdo = AdminDatabase::pdo();
        $user = $memberUserById($pdo, $userId);
        if (!is_array($user)) {
            $memberEnvelope(404, ['success' => false, 'ok' => false, 'code' => 404, 'message' => 'Kullanıcı bulunamadı.']);
        }
        $input = $memberInput($payload);
        $amount = round((float) str_replace(',', '.', (string) ($input['amount'] ?? '0')), 2);
        $methodKey = trim((string) ($input['payment_method_id'] ?? $input['payment_method'] ?? $input['method'] ?? 'wallet'));
        $result = MegaPayzService::createDeposit($pdo, $user, $methodKey, $amount);
        $memberEnvelope(!empty($result['success']) ? 200 : 422, $result);
    }
}

if ($route === 'withdrawals') {
    if ($method === 'GET') {
        $userId = $memberRequireLogin();
        $history = MegaPayzService::history(AdminDatabase::pdo(), $userId, 'withdraw', $_GET);
        $success(['items' => $history['items'], 'withdrawals' => $history['items']], $history['pagination'] ?? []);
    }
    if ($method === 'POST') {
        $userId = $memberRequireLogin();
        $pdo = AdminDatabase::pdo();
        $user = $memberUserById($pdo, $userId);
        if (!is_array($user)) {
            $memberEnvelope(404, ['success' => false, 'ok' => false, 'code' => 404, 'message' => 'Kullanıcı bulunamadı.']);
        }
        $input = $memberInput($payload);
        $amount = round((float) str_replace(',', '.', (string) ($input['amount'] ?? '0')), 2);
        $methodKey = trim((string) ($input['payment_method_id'] ?? $input['payment_method'] ?? $input['method'] ?? 'wallet'));
        $fields = is_array($input['input_fields'] ?? null) ? $input['input_fields'] : [];
        $result = MegaPayzService::createWithdraw($pdo, $user, $methodKey, $amount, $fields);
        $memberEnvelope(!empty($result['success']) ? 200 : 422, $result);
    }
}

if ($method === 'GET' && preg_match('~^(deposits|withdrawals)/([^/]+)$~', $route, $m) === 1) {
    $userId = $memberRequireLogin();
    $type = (string) $m[1] === 'deposits' ? 'deposit' : 'withdraw';
    $history = MegaPayzService::history(AdminDatabase::pdo(), $userId, $type, ['limit' => 100]);
    $id = urldecode((string) $m[2]);
    foreach ($history['items'] ?? [] as $item) {
        if ((string) ($item['id'] ?? '') === $id || (string) ($item['trx'] ?? '') === $id) {
            $success(['transaction' => $item], ['resource' => $type]);
        }
    }
    $memberEnvelope(404, ['success' => false, 'ok' => false, 'code' => 404, 'message' => 'İşlem bulunamadı.']);
}

if ($method === 'POST' && preg_match('~^withdrawals/([^/]+)/cancel$~', $route, $m) === 1) {
    $memberRequireLogin();
    $professionalAccepted('withdrawal_cancel', ['id' => urldecode((string) $m[1]), 'cancelled' => false]);
}

if ($method === 'GET' && $route === 'wallet/transactions') {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    $deposits = MegaPayzService::history($pdo, $userId, 'deposit', ['limit' => 25]);
    $withdrawals = MegaPayzService::history($pdo, $userId, 'withdraw', ['limit' => 25]);
    $items = array_merge($deposits['items'] ?? [], $withdrawals['items'] ?? []);
    usort($items, static fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
    $success(['items' => array_slice($items, 0, 50), 'total' => count($items)], ['resource' => 'wallet/transactions']);
}

if ($method === 'GET' && preg_match('~^wallet/transactions/([^/]+)$~', $route, $m) === 1) {
    $memberRequireLogin();
    $professionalEmpty('wallet/transaction', ['id' => urldecode((string) $m[1]), 'transaction' => null]);
}

if ($method === 'POST' && $route === 'wallet/transfer') {
    $memberRequireLogin();
    $professionalAccepted('wallet/transfer', ['transferred' => false]);
}

if ($route === 'support/live-chat/token' && in_array($method, ['GET', 'POST'], true)) {
    $userId = $memberJwtOptionalUserId($pdo ?? AdminDatabase::pdo());
    $supportUrl = defined('LIVE_SUPPORT_URL') ? (string) LIVE_SUPPORT_URL : '';
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Canlı destek bağlantısı hazır.',
        'data' => [
            // Üçüncü taraf canlı destek widget'ı kendi oturumunu açar; burada
            // yalnızca bağlantı ve üye bağlamı döndürülür (ayrı bir token yok).
            'token' => null,
            'provider' => 'live-chat',
            'url' => $supportUrl,
            'authenticated' => ($userId ?? 0) > 0,
            'user_id' => ($userId ?? 0) > 0 ? (int) $userId : null,
        ],
    ]);
}

if ($method === 'GET' && in_array($route, ['kyc/status', 'me/limits', 'me/security-sessions', 'notifications', 'notifications/settings', 'responsible-gaming/limits', 'responsible-gaming/activity', 'support/tickets'], true)) {
    $memberRequireLogin();
    $professionalEmpty($route);
}

if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
    && (str_starts_with($route, 'kyc/')
        || str_starts_with($route, 'notifications/')
        || str_starts_with($route, 'responsible-gaming/')
        || str_starts_with($route, 'support/tickets')
        || str_starts_with($route, 'me/security-sessions/'))
) {
    $memberRequireLogin();
    $professionalAccepted($route);
}

if ($method === 'GET' && (preg_match('~^support/tickets/([^/]+)$~', $route) === 1 || preg_match('~^me/security-sessions/([^/]+)$~', $route) === 1)) {
    $memberRequireLogin();
    $professionalEmpty($route);
}

if (preg_match('~^casino/favorite-games/([^/]+)$~', $route, $m) === 1) {
    $memberRequireLogin();
    if ($method === 'POST') {
        $professionalAccepted('casino/favorite-games', ['game_id' => urldecode((string) $m[1]), 'favorite' => true]);
    }
    if ($method === 'DELETE') {
        $professionalAccepted('casino/favorite-games', ['game_id' => urldecode((string) $m[1]), 'favorite' => false]);
    }
}

if ($method === 'POST' && in_array($route, ['bets/validate', 'bets/place'], true)) {
    $memberRequireLogin();
    $professionalAccepted($route, ['bet' => null]);
}

if (preg_match('~^bets/([^/]+)(/(cashout|cancel))?$~', $route, $m) === 1) {
    $memberRequireLogin();
    if ($method === 'GET') {
        $professionalEmpty('bet', ['id' => urldecode((string) $m[1]), 'bet' => null]);
    }
    if ($method === 'POST') {
        $professionalAccepted('bet/' . (string) ($m[3] ?? 'action'), ['id' => urldecode((string) $m[1])]);
    }
}

if ($method === 'GET' && (preg_match('~^(events|leagues)/([^/]+)(/markets)?$~', $route) === 1 || preg_match('~^sports/([^/]+)/countries$~', $route) === 1)) {
    $professionalEmpty($route);
}

if ($method === 'POST' && preg_match('~^bonuses/([^/]+)/(claim|cancel)$~', $route, $m) === 1) {
    $memberRequireLogin();
    $professionalAccepted('bonus/' . (string) $m[2], ['id' => urldecode((string) $m[1])]);
}

$error(404, 'API endpoint bulunamadı.', ['method' => $method, 'route' => $route]);
