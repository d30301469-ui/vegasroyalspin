<?php
/** Member API v2 kernel — route parsing, JWT helpers, CSRF. Included by index.php and member_local.php. */

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$route = trim((string) ($_GET['route'] ?? ''), '/');
if ($route === 'index.php' && isset($_SERVER['QUERY_STRING']) && is_string($_SERVER['QUERY_STRING'])) {
    if (preg_match_all('/(?:^|&)route=([^&]*)/', $_SERVER['QUERY_STRING'], $routeMatches) > 0
        && !empty($routeMatches[1])) {
        foreach (array_reverse($routeMatches[1]) as $routeCandidate) {
            $routeCandidate = trim(urldecode((string) $routeCandidate), '/');
            if ($routeCandidate !== '' && $routeCandidate !== 'index.php') {
                $route = $routeCandidate;
                break;
            }
        }
    }
}
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
    'balance' => 'balance.php',
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
    'bonus-claims-me' => 'bonus_claims_me.php',
    'bonus/use-code' => 'bonus_use_code.php',
    'referrals' => 'referrals.php',
    'affiliate/summary' => 'affiliate.php',
    'withdraw-payment' => 'withdraw_payment.php',
    'payment-methods' => 'payment_methods.php',
    'deposit-payment' => 'deposit_payment.php',
    'deposit-history' => 'deposit_history.php',
    'withdraw-history' => 'withdraw_history.php',
    'promocode-request' => 'promocode_request.php',
    'account-freeze' => 'account_freeze.php',
    'account-unfreeze' => 'account_unfreeze.php',
    'favorite-slots' => 'favorite_slots.php',
    'favorite-slots.php' => 'favorite_slots.php',
    'favorite-live-casino' => 'favorite_live_casino.php',
    'game-launch' => 'game_launch.php',
    'game-history' => 'game_history.php',
    'profile/casino-game-history' => 'casino_game_history.php',
    'winners' => 'winners.php',
    'track-visit' => 'track_visit.php',
    'announcements' => 'announcements.php',
    'sliders' => 'sliders.php',
    'content/sliders' => 'content/sliders',
    'content/sliders.php' => 'sliders.php',
    'content/footer' => 'content/footer',
    'content/footer.php' => 'content/footer',
    'footer' => 'footer.php',
    'content/footer-pages' => 'footer_pages.php',
    'content/footer-pages.php' => 'footer_pages.php',
    'content/footer_pages' => 'footer_pages.php',
    'content/footer_pages.php' => 'footer_pages.php',
    'footer-pages' => 'footer_pages.php',
    'footer-pages.php' => 'footer_pages.php',
    'content/mobile-menu' => 'content/mobile-menu',
    'content/mobile-menu.php' => 'content/mobile-menu',
    'content/mobile_menu' => 'content/mobile-menu',
    'content/mobile_menu.php' => 'content/mobile-menu',
    'mobile-menu' => 'mobile-menu.php',
    'mobile_menu' => 'mobile-menu.php',
    'mobile_menu.php' => 'mobile-menu.php',
    'content/promotions' => 'content/promotions',
    'content/promotions.php' => 'content/promotions',
    'promotions' => 'promotions.php',
    'content/auth-sliders' => 'content/auth-sliders',
    'content/auth-sliders.php' => 'content/auth-sliders',
    'content/auth_sliders' => 'content/auth-sliders',
    'content/auth_sliders.php' => 'content/auth-sliders',
    'auth-sliders' => 'auth_sliders.php',
    'auth-sliders.php' => 'auth_sliders.php',
    'content/homepage-sections' => 'content/homepage-sections',
    'content/homepage-sections.php' => 'homepage_sections.php',
    'content/homepage_sections' => 'homepage_sections.php',
    'content/homepage_sections.php' => 'homepage_sections.php',
    'homepage-sections' => 'homepage_sections.php',
    'homepage-sections.php' => 'homepage_sections.php',
    'homepage_sections.php' => 'homepage_sections.php',
    'site-settings' => 'site_settings.php',
    'config' => 'site_settings.php',
    'member-inbox-messages' => 'member_inbox_messages.php',
    'bonus-claim' => 'bonus_claim.php',
    'bonuses' => 'bonus_claims_me.php',
    'bonuses/active' => 'active_bonus.php',
    'bonuses/history' => 'bonus_claims_me.php',
    'bonuses/wagering-progress' => 'active_bonus.php',
    'payments/methods' => 'payment_methods.php',
    'wallet/balance' => 'balance.php',
    'wallet/summary' => 'balance.php',
    'footer-pages.php' => 'footer_pages.php',
    'profile/game-history-detail' => 'profile/game_history_detail.php',
    'profile/game_history_detail.php' => 'profile/game_history_detail.php',
    'profile/spor-bet-detail' => 'profile/spor_bet_detail.php',
    'profile/spor_bet_detail.php' => 'profile/spor_bet_detail.php',
    'games-provider' => 'games_provider.php',
    'footer-pages' => 'footer_pages.php',
    'me/freespins' => 'freespins.php',
    'profile/freespins' => 'freespins.php',
    'loyalty' => 'loyalty.php',
    'loyalty/me' => 'loyalty.php',
    'sportsbook/launch' => 'sportsbook_launch.php',
    'sportsbook-launch' => 'sportsbook_launch.php',
    'sportsbook/history' => 'sportsbook_history.php',
    'promocodes' => 'promocodes.php',
    'footer-pages' => 'footer_pages.php',
    'kyc/status' => 'kyc/status',
    'notifications' => 'notifications',
    'games' => 'games.php',
    'casino/games' => 'games.php',
    'casino/providers' => 'games_provider.php',
    'casino/recent-games' => 'game_history.php',
    'slot-games' => 'games.php',
    'live-casino/games' => 'games.php',
    'live-casino/providers' => 'games_provider.php',
    'live-casino/tables' => 'games.php',
    'profile/casino_game_history.php' => 'casino_game_history.php',
];
if (isset($routeAliases[$route])) {
    $route = $routeAliases[$route];
}

if (!function_exists('member_api_require_provider_services')) {
    function member_api_require_provider_services(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        admin_require_project_file('services/MegaPayzService.php');
        admin_require_project_file('services/BgamingService.php');
        $loaded = true;
    }
}

$bodyRaw = file_get_contents('php://input');
$body = [];
$contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
if (is_string($bodyRaw) && trim($bodyRaw) !== '') {
    if ($contentType === 'application/json' || $contentType === '') {
        $decoded = json_decode($bodyRaw, true);
        if (is_array($decoded)) {
            $body = $decoded;
        }
    }
    // form-encoded body вЂ” only accept for non-JSON content types to prevent type confusion
    if ($body === [] && in_array($contentType, ['application/x-www-form-urlencoded', 'multipart/form-data'], true)) {
        $body = $_POST;
    }
}

$payload = [
    'query' => $_GET,
    'body' => $body,
];

$json = static function (int $status, array $data): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

$error = static function (int $status, string $message, array $meta = []) use ($json): void {
    // Üye API zarfı (success+code) ile parite; eski tüketiciler için ok/message/meta korunur.
    $json($status, [
        'success' => false,
        'ok' => false,
        'code' => $status,
        'message' => $message,
        'meta' => $meta,
    ]);
};

$success = static function (array $data = [], array $meta = []) use ($json): void {
    $json(200, [
        'success' => true,
        'ok' => true,
        'code' => 200,
        'data' => $data,
        'meta' => $meta,
    ]);
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

$requireAnyPermission = static function (array $permissionKeys) use ($requireAuth, $error): void {
    $requireAuth();
    foreach ($permissionKeys as $permissionKey) {
        if (AdminAuth::can((string) $permissionKey)) {
            return;
        }
    }
    $error(403, 'Bu işlem için yetkiniz yok.');
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

$moduleMap = static function (): array {
    $config = require ADMIN_APP_PATH . '/Config/admin.php';
    $modules = is_array($config['modules'] ?? null) ? $config['modules'] : [];

    return $modules;
};

$repo = new AdminTableRepository();

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

    // Hassas/iç alanları üye API yanıtlarından ayıkla.
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
    if (!array_key_exists('balance', $row) && array_key_exists('ana_bakiye', $row)) {
        $row['balance'] = $row['ana_bakiye'];
    }
    if (!array_key_exists('bonus_balance', $row) && array_key_exists('bonus_bakiye', $row)) {
        $row['bonus_balance'] = $row['bonus_bakiye'];
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

// Public runtime ile birebir parite: bcrypt + eski (md5/sha1) hash'leri kabul et.
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

    return false;
};

$memberJwtEnsureTable = static function (PDO $pdo): void {
    MemberJwtService::ensureTable($pdo);
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

$memberJwtIssue = static function (PDO $pdo, array $user, int $ttl = 2592000): string {
    return MemberJwtService::issue($pdo, $user, $ttl);
};

$memberFrontendTrustUserId = static function (string $scope = 'member-proxy'): int {
    $secret = trim((string) (getenv('FRONTEND_CMS_PURGE_SECRET') ?: ''));
    if ($secret === '' && defined('FRONTEND_CMS_PURGE_SECRET')) {
        $secret = trim((string) FRONTEND_CMS_PURGE_SECRET);
    }
    if ($secret === '' || str_contains($secret, 'CHANGE-ME')) {
        return 0;
    }
    $userId = (int) ($_SERVER['HTTP_X_MEMBER_PROXY_USER_ID'] ?? 0);
    $trust = trim((string) ($_SERVER['HTTP_X_FRONTEND_TRUST'] ?? ''));
    if ($userId <= 0 || $trust === '') {
        return 0;
    }
    $expected = hash_hmac('sha256', $scope . ':' . $userId, $secret);
    if (!hash_equals($expected, $trust)) {
        return 0;
    }

    return $userId;
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
    $altJwt = trim((string) ($_SERVER['HTTP_X_METROPOL_MEMBER_JWT'] ?? $_SERVER['REDIRECT_HTTP_X_METROPOL_MEMBER_JWT'] ?? ''));
    if ($altJwt !== '') {
        return $altJwt;
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
    if (preg_match('/^\s*Bearer\s+.+\s*$/i', $header) === 1) {
        return true;
    }

    return trim((string) ($_SERVER['HTTP_X_METROPOL_MEMBER_JWT'] ?? $_SERVER['REDIRECT_HTTP_X_METROPOL_MEMBER_JWT'] ?? '')) !== '';
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

    $memberJwtEnsureTable($pdo);
    $stmt = $pdo->prepare(
        'SELECT id, last_seen_at FROM member_jwt_tokens
         WHERE jti = :jti AND user_id = :user_id AND token_hash = :token_hash
           AND revoked_at IS NULL AND expires_at >= NOW()
         LIMIT 1'
    );
    $stmt->execute([
        'jti' => $jti,
        'user_id' => $uid,
        'token_hash' => hash('sha256', $jwt),
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $rowId = (int) ($row['id'] ?? 0);
    if ($rowId <= 0) {
        if (MemberJwtService::backfillTrackedToken($pdo, $jwt, $payload)) {
            $stmt->execute([
                'jti' => $jti,
                'user_id' => $uid,
                'token_hash' => hash('sha256', $jwt),
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $rowId = (int) ($row['id'] ?? 0);
        }
        if ($rowId <= 0) {
            return null;
        }
    }

    // Throttle last_seen_at writes: read-heavy API does not need a write on
    // every request. Only touch when stale (>5 min) to cut InnoDB write load.
    $lastSeen = (string) ($row['last_seen_at'] ?? '');
    $lastSeenTs = $lastSeen !== '' ? strtotime($lastSeen) : 0;
    if ($lastSeenTs === false || $lastSeenTs <= 0 || (time() - $lastSeenTs) > 300) {
        $touch = $pdo->prepare('UPDATE member_jwt_tokens SET last_seen_at = NOW() WHERE id = :id');
        $touch->execute(['id' => $rowId]);
    }

    return [
        'user_id' => $uid,
        'jti' => $jti,
        'payload' => $payload,
    ];
};

/**
 * Bearer/JWT ile doğrulanan istekte PHP session'ı ('loggedin', 'user_id', 'username',
 * 'email') senkron tutar. Bu olmadan JS tarafı (localStorage JWT) kullanıcıyı
 * "giriş yapılmış" sanırken, $_SESSION['loggedin']'e bakan sayfalar (profil vb.)
 * oturumu düşmüş görüp '/' adresine yönlendiriyor — döngüsel "oturum yenileme"
 * uyarılarının kök nedeni buydu.
 */
$memberJwtSyncPhpSession = static function (PDO $pdo, int $userId) use ($memberUserById): void {
    if (defined('METROPOL_API_NO_SESSION') && METROPOL_API_NO_SESSION) {
        return;
    }
    if ($userId <= 0) {
        return;
    }
    $alreadySynced = !empty($_SESSION['loggedin']) && (int) ($_SESSION['user_id'] ?? 0) === $userId;
    if ($alreadySynced) {
        return;
    }
    $_SESSION['loggedin'] = true;
    $_SESSION['user_id'] = $userId;
    try {
        $user = $memberUserById($pdo, $userId);
    } catch (Throwable) {
        $user = null;
    }
    if (is_array($user)) {
        $_SESSION['username'] = (string) ($user['username'] ?? ($_SESSION['username'] ?? ''));
        $_SESSION['email'] = (string) ($user['email'] ?? ($_SESSION['email'] ?? ''));
    }
};

$memberJwtRequireUserId = static function (PDO $pdo) use ($memberEnvelope, $memberJwtExtractBearer, $memberJwtValidate, $memberJwtHasBearerHeader, $memberFrontendTrustUserId, $memberJwtSyncPhpSession): int {
    $token = $memberJwtExtractBearer();
    $bearerProvided = $memberJwtHasBearerHeader();
    $sessionUserId = !empty($_SESSION['loggedin']) ? (int) ($_SESSION['user_id'] ?? 0) : 0;
    if ($token === '' && !$bearerProvided && !empty($_SESSION['loggedin']) && (int) ($_SESSION['user_id'] ?? 0) > 0) {
        if (!(defined('METROPOL_API_NO_SESSION') && METROPOL_API_NO_SESSION)) {
            try {
                $token = MemberJwtService::ensureSessionToken($pdo);
            } catch (Throwable) {
                $token = '';
            }
        }
    }
    try {
        $auth = $memberJwtValidate($pdo, $token);
    } catch (Throwable) {
        $auth = null;
    }
    if (!is_array($auth) || (int) ($auth['user_id'] ?? 0) <= 0) {
        $trustedUid = $memberFrontendTrustUserId('member-proxy');
        if ($trustedUid > 0) {
            if (!headers_sent()) {
                header('X-Metropol-Auth-Mode: frontend-trust');
            }
            $memberJwtSyncPhpSession($pdo, $trustedUid);

            return $trustedUid;
        }
        if (
            !$bearerProvided
            && $sessionUserId > 0
            && !(defined('METROPOL_API_NO_SESSION') && METROPOL_API_NO_SESSION)
        ) {
            return $sessionUserId;
        }
        $memberEnvelope(401, [
            'success' => false,
            'code' => 401,
            'error' => 'UNAUTHORIZED',
            'message' => 'Geçersiz veya süresi dolmuş JWT token.',
        ]);
    }
    if ($token !== '' && !(defined('METROPOL_API_NO_SESSION') && METROPOL_API_NO_SESSION)) {
        $_SESSION['member_jwt'] = $token;
    }
    $memberJwtSyncPhpSession($pdo, (int) $auth['user_id']);
    return (int) $auth['user_id'];
};

$memberJwtOptionalUserId = static function (PDO $pdo) use ($memberJwtExtractBearer, $memberJwtValidate, $memberJwtHasBearerHeader, $memberJwtSyncPhpSession): ?int {
    $token = $memberJwtExtractBearer();
    if ($token !== '' || $memberJwtHasBearerHeader()) {
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
        if (!(defined('METROPOL_API_NO_SESSION') && METROPOL_API_NO_SESSION)) {
            $_SESSION['member_jwt'] = $token;
        }
        $memberJwtSyncPhpSession($pdo, $userId);

        return $userId;
    }

    $sessionUserId = !empty($_SESSION['loggedin']) ? (int) ($_SESSION['user_id'] ?? 0) : 0;

    return $sessionUserId > 0 ? $sessionUserId : null;
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

// NOT: Bearer taşıyan sunucu-sunucu çağrılarında bu kontrol atlanır; yalnızca çerez/
// oturum tabanlı, Bearer'sız state-changing isteklere CSRF zorunluluğu uygular.
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
    'game-launch' => true,
    'sportsbook/launch' => true,
    'sportsbook-launch' => true,
    'kyc/documents' => true,
    'kyc/address-verification' => true,
    'kyc/source-of-funds' => true,
    'notifications/read-all' => true,
    'notifications/settings' => true,
        'responsible-gaming/limits' => true,
        'responsible-gaming/cool-off' => true,
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

$memberIsPublicDemoGameLaunch = static function (string $route) use ($payload): bool {
    if (!in_array($route, ['game_launch.php', 'game-launch'], true)) {
        return false;
    }
    $body = is_array($payload['body'] ?? null) ? $payload['body'] : [];
    $mode = strtolower(trim((string) ($body['mode'] ?? 'real')));
    return in_array($mode, ['fun', 'demo'], true) || !empty($body['demo']) || !empty($body['isDemo']);
};

// Split-deploy backend (METROPOL_API_NO_SESSION): CSRF oturumu frontend'te; API host stateless.
// Oyun launch / ödeme vb. JWT veya X-Frontend-Trust ile korunur — boş $_SESSION['csrf_token'] 403 üretmesin.
$memberApiUsesSessionCsrf = !(defined('METROPOL_API_NO_SESSION') && METROPOL_API_NO_SESSION);

if ($memberApiUsesSessionCsrf
    && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
    && $memberRouteRequiresCsrf($route)
    && !$memberJwtHasBearerHeader()
    && !$memberIsPublicDemoGameLaunch($route)
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
