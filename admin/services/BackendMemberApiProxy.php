<?php

declare(strict_types=1);

            if (function_exists('metropol_frontend_set_member_restore_cookie')) {
                metropol_frontend_set_member_restore_cookie((string) $_SESSION['member_jwt']);
            }
require_once __DIR__ . '/BackendApiClient.php';

/**
 * Frontend API-only deployment: forwards allowed public member routes to the backend host.
 */
final class BackendMemberApiProxy
{
    private static string $lastJwtSyncHint = '';

    private static function ensureFrontendSession(): void
    {
        if (!function_exists('metropol_frontend_session_start')) {
            require_once dirname(__DIR__) . '/config/frontend_session.php';
        }
    }

    public static function forward(string $route): void
    {
        self::ensureFrontendSession();
        $route = trim($route, '/');
        $softRoute = self::isSoftPublicRoute($route);
        $sessionRoute = self::isSessionRoute($route);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        $query = $_GET;
        unset($query['route'], $query['api_route']);
        $cacheableRoute = self::cacheablePublicRouteKey($route, $method, $query);

        $routeNorm = strtolower(trim($route, '/'));
        $criticalRoute = self::isCriticalMemberRoute($routeNorm);

        if (
            !$criticalRoute
            && function_exists('metropol_member_api_circuit_is_open')
            && metropol_member_api_circuit_is_open()
        ) {
            if ($cacheableRoute !== null) {
                $cached = self::readCachedPublicResponse($cacheableRoute, true);
                if ($cached !== null) {
                    self::emitCachedPublicResponse($cached);
                }
            }
            if ($sessionRoute) {
                self::jsonSessionUnavailable();
            }
            if ($softRoute) {
                self::jsonFallback(self::softFallbackPayload($route));
            }
            self::jsonError(503, 'Backend API geçici olarak kullanılamıyor (circuit açık). Birkaç saniye sonra tekrar deneyin.', [
                'circuit' => 'open',
                'retry_after_seconds' => function_exists('metropol_member_api_circuit_seconds')
                    ? metropol_member_api_circuit_seconds()
                    : 5,
                'hint' => 'Frontend .env: API_BACKEND_INTERNAL_BASE_URL=http://127.0.0.1/api/v2 ve API_BACKEND_INTERNAL_HOST=bo-backoffice.site',
            ]);
        }

        $base = BackendApiClient::effectiveMemberApiOutboundBaseUrl();
        if ($base === '') {
            self::jsonError(503, 'Backend API base URL is not configured.', [
                'hint' => 'Frontend .env: API_BACKEND_MAIN_BASE_URL=https://api.bo-backoffice.site/api/v2',
                'check' => '/health.php',
            ]);
        }
        $baseCandidates = BackendApiClient::memberApiOutboundBaseCandidates();
        if ($baseCandidates === []) {
            $baseCandidates = [$base];
        }

        [$rawBody, $contentType] = self::resolveProxyBody($method);

        $sessionOnlyAuth = self::isSessionRoute($route);
        $authorization = (self::shouldSkipProxyAuthorization($routeNorm, $method)
            || self::isPublicDemoGameLaunch($routeNorm, $method, $rawBody))
            ? null
            : self::resolveProxyAuthorization($sessionOnlyAuth, false, $routeNorm);

        $timeout = function_exists('frontend_api_proxy_timeout')
            ? frontend_api_proxy_timeout()
            : 30;

        if (preg_match('#^(?:game[-_/]?launch|game_launch)(?:\.php)?$#', $routeNorm) === 1) {
            $timeout = max($timeout, 45);
        } elseif (in_array($routeNorm, [
            'balance',
            'balance.php',
            'auth/session',
            'session.php',
            'loyalty',
            'loyalty.php',
        ], true)) {
            $timeout = min($timeout, 10);
        } elseif (in_array($routeNorm, [
            'auth/login',
            'login.php',
            'auth/register',
            'register.php',
        ], true)) {
            $timeout = max($timeout, 25);
        }
        if ($cacheableRoute !== null) {
            $timeout = min($timeout, 12);
        }

        $outboundHeaders = self::buildProxyOutboundHeaders($routeNorm, $authorization);

        $lastError = 'Backend API request failed.';
        $lastBackend = $base;
        foreach ($baseCandidates as $baseTry) {
            $lastBackend = $baseTry;
            foreach (self::routeAlternates($route) as $candidate) {
                $result = BackendApiClient::proxyHttp(
                    $method,
                    $baseTry,
                    $candidate,
                    $query,
                    $rawBody,
                    $contentType,
                    $authorization,
                    $timeout,
                    $outboundHeaders,
                    false
                );

                if ($result === null) {
                    continue;
                }

                if (!empty($result['transport_error'])) {
                    $lastError = (string) ($result['error_message'] ?? $lastError);
                    break;
                }

                if ($result['status'] === 401 && self::isMemberAuthProxyRoute($routeNorm) && !self::shouldSkipProxyAuthorization($routeNorm, $method)) {
                    unset($_SESSION['__member_jwt_proxy_synced']);
                    $refreshed = self::resolveProxyAuthorization($sessionOnlyAuth, true, $routeNorm);
                    if ($refreshed !== $authorization && $refreshed !== null && $refreshed !== '') {
                        $authorization = $refreshed;
                        $outboundHeaders = self::buildProxyOutboundHeaders($routeNorm, $authorization);
                        $result = BackendApiClient::proxyHttp(
                            $method,
                            $baseTry,
                            $candidate,
                            $query,
                            $rawBody,
                            $contentType,
                            $authorization,
                            $timeout,
                            $outboundHeaders,
                            false
                        );
                        if ($result === null) {
                            continue;
                        }
                    }
                }

                if ($result['status'] === 404) {
                    $lastError = 'Backend API route not found.';
                    continue;
                }

                if ($result['status'] >= 502 && count($baseCandidates) > 1) {
                    $lastError = 'Backend HTTP ' . (int) $result['status'];
                    break;
                }

                try {
                    self::maybeApplyAuthSession($route, $method, $result);
                    self::maybeClearAuthSession($route, $method, $result);
                } catch (Throwable $sessionError) {
                    error_log('[BackendMemberApiProxy] session sync: ' . $sessionError->getMessage());
                }

                if (function_exists('metropol_member_api_mark_success')) {
                    metropol_member_api_mark_success();
                }

                if (!headers_sent()) {
                    http_response_code($result['status']);
                    header('Content-Type: ' . ($result['content_type'] ?? 'application/json; charset=UTF-8'));
                    if ($cacheableRoute !== null) {
                        header('X-Metropol-Cache: bypass');
                        header('Cache-Control: ' . self::publicCacheControlHeader());
                    }
                    if (self::isMemberAuthProxyRoute($routeNorm) && self::$lastJwtSyncHint !== '') {
                        header('X-Metropol-Jwt-Sync: ' . self::$lastJwtSyncHint);
                    }
                    $proxyHost = strtolower((string) (parse_url($baseTry, PHP_URL_HOST) ?: ''));
                    if ($proxyHost !== '') {
                        header('X-Metropol-Proxy-Backend: ' . $proxyHost);
                    }
                }

                if ($cacheableRoute !== null) {
                    self::writeCachedPublicResponse($cacheableRoute, [
                        'status' => (int) ($result['status'] ?? 200),
                        'content_type' => (string) ($result['content_type'] ?? 'application/json; charset=UTF-8'),
                        'body' => (string) ($result['body'] ?? ''),
                    ]);
                }

                $encodedBody = self::rewriteStaleUrlsInJsonBody(
                    (string) ($result['body'] ?? ''),
                    (string) ($result['content_type'] ?? 'application/json; charset=UTF-8')
                );
                echo $encodedBody !== '' ? $encodedBody : (string) ($result['body'] ?? '');
                exit;
            }
        }

        if (function_exists('metropol_member_api_mark_failure')) {
            metropol_member_api_mark_failure();
        }

        if ($cacheableRoute !== null) {
            $cached = self::readCachedPublicResponse($cacheableRoute, true);
            if ($cached !== null) {
                self::emitCachedPublicResponse($cached);
            }
        }

        if ($sessionRoute) {
            self::jsonSessionUnavailable();
        }

        if ($softRoute) {
            self::jsonFallback(self::softFallbackPayload($route));
        }

        self::jsonError(502, $lastError, [
            'backend' => $lastBackend,
            'backends_tried' => $baseCandidates,
            'hint' => '502: backend erişilemiyor — .env: API_BACKEND_MAIN_BASE_URL=https://api.bo-backoffice.site/api/v2',
        ]);
    }

    private static function shouldSkipProxyAuthorization(string $routeNorm, string $method): bool
    {
        if (strtoupper($method) !== 'POST') {
            return false;
        }

        return in_array($routeNorm, [
            'auth/login',
            'login.php',
            'auth/register',
            'register.php',
            'auth/forgot-password',
            'auth/reset-password',
            'auth/password-reset',
            'forgot_password.php',
            'reset_password.php',
            'password_reset.php',
        ], true);
    }

    private static function isPublicDemoGameLaunch(string $routeNorm, string $method, ?string $rawBody): bool
    {
        if (strtoupper($method) !== 'POST') {
            return false;
        }
        if (!preg_match('#^(?:game[-_/]?launch|game_launch)(?:\.php)?$#', $routeNorm)) {
            return false;
        }
        $body = json_decode((string) ($rawBody ?? ''), true);
        if (!is_array($body)) {
            return false;
        }
        $mode = strtolower(trim((string) ($body['mode'] ?? 'real')));

        return in_array($mode, ['fun', 'demo'], true) || !empty($body['demo']) || !empty($body['isDemo']);
    }

    private static function isCriticalMemberRoute(string $routeNorm): bool
    {
        if (preg_match('#^(?:game[-_/]?launch|game_launch)(?:\.php)?$#', $routeNorm) === 1) {
            return true;
        }

        return in_array($routeNorm, [
            'auth/login',
            'auth/register',
            'auth/session',
            'login.php',
            'register.php',
            'session.php',
            'balance',
            'balance.php',
            'loyalty',
            'loyalty.php',
        ], true);
    }

    private static function isMemberAuthProxyRoute(string $routeNorm): bool
    {
        if (in_array($routeNorm, [
            'balance',
            'balance.php',
            'loyalty',
            'loyalty.php',
            'auth/session',
            'session.php',
            'me',
            'me/index',
            'auth/refresh',
            'profile',
            'profile.php',
            'game-launch',
            'game_launch.php',
        ], true)) {
            return true;
        }

        if (preg_match('#^(?:game[-_/]?launch|game_launch)(?:\.php)?$#', $routeNorm) === 1) {
            return true;
        }

        return str_starts_with($routeNorm, 'account/');
    }

    private static function isSoftPublicRoute(string $route): bool
    {
        $normalized = strtolower(trim($route, '/'));

        return in_array($normalized, [
            'winners',
            'winners.php',
            'announcements',
            'announcements.php',
            'track-visit',
            'track_visit',
            'track_visit.php',
            'track-visit.php',
            'content/sliders',
            'content/sliders.php',
            'content/mobile-menu',
            'content/mobile-menu.php',
            'content/promotions',
            'content/promotions.php',
            'content/homepage-sections',
            'content/homepage-sections.php',
        ], true);
    }

    /**
     * @param array<string, mixed> $query
     */
    private static function cacheablePublicRouteKey(string $route, string $method, array $query): ?string
    {
        if (strtoupper($method) !== 'GET') {
            return null;
        }

        $normalized = strtolower(trim($route, '/'));
        $allowed = [
            'winners',
            'winners.php',
            'announcements',
            'announcements.php',
            'site-settings',
            'site_settings.php',
            'promotions',
            'promotions.php',
            'games-provider',
            'games_provider.php',
            'content/footer',
            'content/footer.php',
            'content/footer-pages',
            'content/footer-pages.php',
            'content/auth-sliders',
            'content/auth-sliders.php',
            'content/sliders',
            'content/sliders.php',
            'content/mobile-menu',
            'content/mobile-menu.php',
            'content/promotions',
            'content/promotions.php',
            'content/homepage-sections',
            'content/homepage-sections.php',
        ];
        if (!in_array($normalized, $allowed, true)) {
            return null;
        }

        ksort($query);
        $queryString = $query === [] ? '' : http_build_query($query);

        return sha1($normalized . '|' . $queryString);
    }

    private static function isSessionRoute(string $route): bool
    {
        $normalized = strtolower(trim($route, '/'));

        return in_array($normalized, [
            'auth/session',
            'session.php',
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    private static function softFallbackPayload(string $route): array
    {
        $normalized = strtolower(trim($route, '/'));
        if (str_contains($normalized, 'announcement')) {
            return [
                'success' => true,
                'ok' => true,
                'code' => 200,
                'message' => 'fallback',
                'data' => ['announcements' => []],
            ];
        }
        if (str_contains($normalized, 'track')) {
            return [
                'success' => true,
                'ok' => true,
                'code' => 200,
                'message' => 'fallback',
                'data' => [],
            ];
        }
        if (str_contains($normalized, 'content/sliders')) {
            return [
                'success' => true,
                'ok' => true,
                'code' => 200,
                'message' => 'fallback',
                'data' => ['sliders' => []],
            ];
        }
        if (str_contains($normalized, 'content/mobile-menu')) {
            return [
                'success' => true,
                'ok' => true,
                'code' => 200,
                'message' => 'fallback',
                'data' => ['mobile_menu' => []],
            ];
        }

        return [
            'success' => true,
            'ok' => true,
            'code' => 200,
            'message' => 'fallback',
            'data' => ['winners' => []],
        ];
    }

    /**
     * PHP multipart/form-data isteklerinde php://input boş olur; $_POST kullanılır.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private static function resolveProxyBody(string $method): array
    {
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return [null, null];
        }

        $rawBody = (string) file_get_contents('php://input');
        $contentType = trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''))[0]);

        if ($rawBody === '' && $_POST !== []) {
            $rawBody = json_encode($_POST, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $contentType = 'application/json';

            return [$rawBody, $contentType];
        }

        if ($rawBody !== '') {
            return [$rawBody, $contentType !== '' ? $contentType : 'application/json'];
        }

        return [null, null];
    }

    /**
     * @return list<string>
     */
    private static function forwardRequestHeaders(string $routeNorm = ''): array
    {
        $headers = [];
        $csrf = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if ($csrf !== '') {
            $headers[] = 'X-CSRF-Token: ' . $csrf;
        }

        // Forward JWT from browser localStorage when PHP session doesn't
        // persist across load-balanced server instances.
        $browserJwt = trim((string) ($_SERVER['HTTP_X_METROPOL_MEMBER_JWT'] ?? ''));
        if ($browserJwt !== '') {
            $headers[] = 'X-Metropol-Member-Jwt: ' . $browserJwt;
        }

        if ($routeNorm !== '' && self::isMemberAuthProxyRoute($routeNorm)) {
            $headers = array_merge($headers, self::buildFrontendProxyTrustHeaders());
        }

        return $headers;
    }

    /**
     * HMAC trust headers for split-host proxy (session on frontend, API on backend).
     *
     * @return list<string>
     */
    private static function buildFrontendProxyTrustHeaders(): array
    {
        require_once dirname(__DIR__) . '/config/frontend_session.php';
        if (session_status() !== PHP_SESSION_ACTIVE) {
            metropol_frontend_session_start();
        }
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if (empty($_SESSION['loggedin']) || $userId <= 0) {
            return [];
        }
        $secret = function_exists('metropol_frontend_trust_secret')
            ? metropol_frontend_trust_secret()
            : trim((string) (getenv('FRONTEND_CMS_PURGE_SECRET') ?: ''));
        if ($secret === '' && defined('FRONTEND_CMS_PURGE_SECRET')) {
            $secret = trim((string) FRONTEND_CMS_PURGE_SECRET);
        }
        if ($secret === '' || str_contains($secret, 'CHANGE-ME')) {
            return [];
        }

        $trust = hash_hmac('sha256', 'member-proxy:' . $userId, $secret);

        return [
            'X-Member-Proxy-User-Id: ' . $userId,
            'X-Frontend-Trust: ' . $trust,
        ];
    }

    /**
     * @return list<string>
     */
    private static function buildProxyOutboundHeaders(string $routeNorm, ?string $authorization): array
    {
        $headers = self::forwardRequestHeaders($routeNorm);
        if ($authorization !== null && $authorization !== '') {
            if (preg_match('/^\s*Bearer\s+(.+)\s*$/i', $authorization, $matches) === 1) {
                $token = trim((string) ($matches[1] ?? ''));
                if ($token !== '') {
                    $headers[] = 'X-Metropol-Member-Jwt: ' . $token;
                }
            }
        }

        return $headers;
    }

    /**
     * @param array{status: int, body: string, content_type: string|null, transport_error?: bool} $result
     */
    private static function maybeApplyAuthSession(string $route, string $method, array $result): void
    {
        if ($method !== 'POST' || !empty($result['transport_error'])) {
            return;
        }

        $normalized = strtolower(trim($route, '/'));
        if (!in_array($normalized, ['auth/login', 'auth/register', 'login.php', 'register.php'], true)) {
            return;
        }

        $status = (int) ($result['status'] ?? 0);
        if ($status < 200 || $status >= 300) {
            return;
        }

        $decoded = json_decode((string) ($result['body'] ?? ''), true);
        if (!is_array($decoded) || empty($decoded['success'])) {
            return;
        }

        if (!is_file(BASE_PATH . '/services/MemberLoginService.php')) {
            return;
        }

        require_once BASE_PATH . '/services/MemberLoginService.php';
        self::ensureFrontendSession();

        $fallback = self::loginFallbackFromRequest($decoded);
        metropol_frontend_session_start();
        MemberLoginService::applySession($decoded, $fallback);
        $data = BackendApiClient::unwrap($decoded);
        $token = trim((string) ($data['token'] ?? ''));
        if ($token !== '' && empty($_SESSION['member_jwt'])) {
            $_SESSION['member_jwt'] = $token;
            $_SESSION['loggedin'] = true;
            if (!empty($data['user_id'])) {
                $_SESSION['user_id'] = (int) $data['user_id'];
            }
        }
        if (!empty($_SESSION['member_jwt'])) {
            $_SESSION['__member_jwt_proxy_synced'] = true;
            try {
                session_regenerate_id(true);
            } catch (Throwable) {
                // Bazı hostlarda session handler regenerate reddedebilir; giriş yanıtı bozulmasın.
            }
        }
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        metropol_frontend_session_write_close();
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private static function loginFallbackFromRequest(array $decoded): string
    {
        $fromPost = trim((string) ($_POST['username'] ?? $_POST['login'] ?? ''));
        if ($fromPost !== '') {
            return $fromPost;
        }
        $body = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
        $user = is_array($body['user'] ?? null) ? $body['user'] : [];

        return trim((string) ($user['username'] ?? ''));
    }

    /**
     * @param array{status: int, body: string, content_type: string|null, transport_error?: bool} $result
     */
    private static function maybeClearAuthSession(string $route, string $method, array $result): void
    {
        if ($method !== 'POST' || !empty($result['transport_error'])) {
            return;
        }

        $normalized = strtolower(trim($route, '/'));
        if (!in_array($normalized, ['auth/logout', 'logout.php'], true)) {
            return;
        }

        $status = (int) ($result['status'] ?? 0);
        if ($status < 200 || $status >= 300) {
            return;
        }

        $decoded = json_decode((string) ($result['body'] ?? ''), true);
        if (!is_array($decoded) || empty($decoded['success'])) {
            return;
        }

        require_once dirname(__DIR__) . '/config/frontend_session.php';
        metropol_frontend_session_start();
        $csrf = $_SESSION['csrf_token'] ?? null;
        $ref = $_SESSION['referral_code'] ?? null;
        $_SESSION = [];
        if (is_string($csrf) && $csrf !== '') {
            $_SESSION['csrf_token'] = $csrf;
        }
        if ($ref !== null) {
            $_SESSION['referral_code'] = $ref;
        }
        if (function_exists('metropol_frontend_clear_member_restore_cookie')) {
            metropol_frontend_clear_member_restore_cookie();
        }
        metropol_frontend_session_write_close();
    }

    /**
     * @return list<string>
     */
    private static function routeAlternates(string $route): array
    {
        $route = trim($route, '/');
        if ($route === '') {
            return [''];
        }

        $candidates = [$route];
        $underscore = str_replace('-', '_', $route);
        if ($underscore !== $route) {
            $candidates[] = $underscore;
        }

        $noExt = preg_replace('/\.php$/i', '', $route) ?? $route;
        if ($noExt !== $route) {
            $candidates[] = $noExt;
        }

        if (!str_contains($route, '/')) {
            if (!str_ends_with(strtolower($route), '.php')) {
                $candidates[] = $route . '.php';
                if ($underscore !== $route) {
                    $candidates[] = $underscore . '.php';
                }
            }
            $hyphen = str_replace('_', '-', $noExt);
            if ($hyphen !== $route && $hyphen !== $noExt) {
                $candidates[] = $hyphen;
                $candidates[] = $hyphen . '.php';
            }
        }

        $out = [];
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate, '/');
            if ($candidate !== '' && !in_array($candidate, $out, true)) {
                $out[] = $candidate;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $extra
     */
    private static function jsonError(int $status, string $message, array $extra = []): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=UTF-8');
        }

        echo json_encode(array_merge([
            'success' => false,
            'ok' => false,
            'code' => $status,
            'message' => $message,
        ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function jsonFallback(array $payload): never
    {
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function jsonSessionUnavailable(): never
    {
        if (!headers_sent()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=UTF-8');
        }

        echo json_encode([
            'success' => false,
            'ok' => false,
            'code' => 401,
            'error' => 'UNAUTHORIZED',
            'message' => 'Session unavailable.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function publicCacheDir(): string
    {
        $base = defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__);

        return rtrim(str_replace('\\', '/', $base), '/') . '/storage/cache/public_api_proxy';
    }

    /**
     * @return array{ttl: int, stale: int}
     */
    private static function publicCacheTtls(): array
    {
        $ttl = (int) (getenv('FRONTEND_PUBLIC_API_CACHE_TTL') ?: 20);
        $stale = (int) (getenv('FRONTEND_PUBLIC_API_CACHE_STALE') ?: 300);
        $ttl = max(5, min(300, $ttl));
        $stale = max($ttl, min(3600, $stale));

        return ['ttl' => $ttl, 'stale' => $stale];
    }

    private static function publicCacheControlHeader(): string
    {
        $ttls = self::publicCacheTtls();

        return 'public, max-age=' . (int) $ttls['ttl']
            . ', stale-while-revalidate=' . (int) $ttls['stale']
            . ', stale-if-error=' . (int) $ttls['stale'];
    }

    /**
     * @param array{status:int,content_type:string,body:string} $payload
     */
    private static function writeCachedPublicResponse(string $cacheKey, array $payload): void
    {
        $dir = self::publicCacheDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $body = trim((string) ($payload['body'] ?? ''));
        if ($body === '') {
            return;
        }

        $path = $dir . '/' . $cacheKey . '.json';
        @file_put_contents($path, json_encode([
            'saved_at' => time(),
            'status' => (int) ($payload['status'] ?? 200),
            'content_type' => (string) ($payload['content_type'] ?? 'application/json; charset=UTF-8'),
            'body' => (string) ($payload['body'] ?? ''),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    /**
     * @return array{status:int,content_type:string,body:string,cache_state:string}|null
     */
    private static function readCachedPublicResponse(string $cacheKey, bool $allowStale): ?array
    {
        $path = self::publicCacheDir() . '/' . $cacheKey . '.json';
        if (!is_readable($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        $savedAt = (int) ($decoded['saved_at'] ?? 0);
        $status = (int) ($decoded['status'] ?? 200);
        $contentType = (string) ($decoded['content_type'] ?? 'application/json; charset=UTF-8');
        $body = (string) ($decoded['body'] ?? '');
        if ($savedAt <= 0 || $body === '') {
            return null;
        }

        $age = time() - $savedAt;
        $ttls = self::publicCacheTtls();
        if ($age <= $ttls['ttl']) {
            return [
                'status' => $status > 0 ? $status : 200,
                'content_type' => $contentType,
                'body' => $body,
                'cache_state' => 'hit',
            ];
        }
        if ($allowStale && $age <= $ttls['stale']) {
            return [
                'status' => $status > 0 ? $status : 200,
                'content_type' => $contentType,
                'body' => $body,
                'cache_state' => 'stale',
            ];
        }

        return null;
    }

    /**
     * @param array{status:int,content_type:string,body:string,cache_state:string} $cached
     */
    private static function emitCachedPublicResponse(array $cached): never
    {
        if (!headers_sent()) {
            http_response_code((int) ($cached['status'] ?? 200));
            header('Content-Type: ' . (string) ($cached['content_type'] ?? 'application/json; charset=UTF-8'));
            header('X-Metropol-Cache: ' . (string) ($cached['cache_state'] ?? 'hit'));
            header('Cache-Control: ' . self::publicCacheControlHeader());
        }

        echo (string) ($cached['body'] ?? '');
        exit;
    }

    private static function resolveProxyAuthorization(bool $sessionOnlyAuth, bool $forceRefresh = false, string $routeNorm = ''): ?string
    {
        require_once dirname(__DIR__) . '/config/frontend_session.php';
        metropol_frontend_session_start();
        if (function_exists('metropol_frontend_sanitize_member_session')) {
            if (!is_readable(dirname(__DIR__) . '/config/member_api_public.php')) {
                require_once dirname(__DIR__) . '/config/member_api_public.php';
            }
            metropol_frontend_sanitize_member_session();
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $loggedIn = !empty($_SESSION['loggedin']) && $userId > 0;
        $jwt = trim((string) ($_SESSION['member_jwt'] ?? ''));
        $signatureOk = $jwt !== '' && self::memberJwtSignatureValid($jwt);
        $memberAuthRoute = $routeNorm !== '' && self::isMemberAuthProxyRoute($routeNorm);
        $proxySynced = !empty($_SESSION['__member_jwt_proxy_synced']);
        self::$lastJwtSyncHint = $loggedIn ? 'session' : 'guest';

        if ($loggedIn && $memberAuthRoute && ($forceRefresh || !$proxySynced || $jwt === '' || !$signatureOk)) {
            $fresh = self::issueMemberJwtViaInternalTrust($userId);
            if ($fresh !== '') {
                $_SESSION['member_jwt'] = $fresh;
                $_SESSION['loggedin'] = true;
                $_SESSION['__member_jwt_proxy_synced'] = true;
                $jwt = $fresh;
                $signatureOk = true;
                self::$lastJwtSyncHint = 'refreshed';
            } else {
                unset($_SESSION['member_jwt'], $_SESSION['__member_jwt_proxy_synced']);
                $jwt = '';
                $signatureOk = false;
                if (self::$lastJwtSyncHint === 'session') {
                    self::$lastJwtSyncHint = 'refresh-failed';
                }
            }
        } elseif ($loggedIn && ($forceRefresh || $jwt === '' || !$signatureOk)) {
            $fresh = self::issueMemberJwtViaInternalTrust($userId);
            if ($fresh !== '') {
                $_SESSION['member_jwt'] = $fresh;
                $_SESSION['loggedin'] = true;
                $_SESSION['__member_jwt_proxy_synced'] = true;
                $jwt = $fresh;
                $signatureOk = true;
                self::$lastJwtSyncHint = 'refreshed';
            } elseif (!$signatureOk) {
                unset($_SESSION['member_jwt'], $_SESSION['__member_jwt_proxy_synced']);
                $jwt = '';
                self::$lastJwtSyncHint = 'invalid-signature';
            }
        }

        metropol_frontend_session_write_close();

        if (($jwt === '' || !$signatureOk) && $memberAuthRoute) {
            $inbound = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
            if (preg_match('/^\s*Bearer\s+(.+)\s*$/i', $inbound, $m) === 1) {
                $candidate = trim((string) ($m[1] ?? ''));
                if ($candidate !== '' && self::memberJwtSignatureValid($candidate)) {
                    $jwt = $candidate;
                    $signatureOk = true;
                    self::$lastJwtSyncHint = 'inbound-bearer';
                }
            }
        }

        if ($jwt !== '' && $signatureOk) {
            return 'Bearer ' . $jwt;
        }

        return null;
    }

    private static function memberJwtSignatureValid(string $jwt): bool
    {
        if ($jwt === '') {
            return false;
        }
        if (is_file(BASE_PATH . '/services/MemberJwtVerify.php')) {
            require_once BASE_PATH . '/services/MemberJwtVerify.php';
            if (class_exists('MemberJwtVerify', false)) {
                return MemberJwtVerify::signatureValid($jwt);
            }
        }
        if (is_file(BASE_PATH . '/services/MemberJwtService.php')) {
            require_once BASE_PATH . '/services/MemberJwtService.php';
            if (class_exists('MemberJwtService', false)) {
                return MemberJwtService::signatureValid($jwt);
            }
        }

        return strlen($jwt) > 20;
    }

    private static function issueMemberJwtViaInternalTrust(int $userId): string
    {
        if ($userId <= 0) {
            self::$lastJwtSyncHint = 'no-user-id';

            return '';
        }
        $secret = function_exists('metropol_frontend_trust_secret')
            ? metropol_frontend_trust_secret()
            : trim((string) (getenv('FRONTEND_CMS_PURGE_SECRET') ?: ''));
        if ($secret === '' && defined('FRONTEND_CMS_PURGE_SECRET')) {
            $secret = trim((string) FRONTEND_CMS_PURGE_SECRET);
        }
        if ($secret === '' || str_contains($secret, 'CHANGE-ME')) {
            self::$lastJwtSyncHint = 'purge-secret-missing';

            return '';
        }

        $trust = hash_hmac('sha256', 'member-jwt:' . $userId, $secret);
        $base = BackendApiClient::effectiveMemberApiOutboundBaseUrl();
        if ($base === '') {
            self::$lastJwtSyncHint = 'backend-base-missing';

            return '';
        }

        $payload = json_encode(['user_id' => $userId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $result = BackendApiClient::proxyHttp(
            'POST',
            $base,
            'internal/frontend-member-jwt',
            [],
            $payload,
            'application/json',
            null,
            12,
            ['X-Frontend-Trust: ' . $trust],
            false
        );
        if ($result === null) {
            self::$lastJwtSyncHint = 'transport-null';

            return '';
        }
        if (!empty($result['transport_error'])) {
            self::$lastJwtSyncHint = 'transport-error';

            return '';
        }
        $status = (int) ($result['status'] ?? 0);
        if ($status !== 200) {
            self::$lastJwtSyncHint = 'trust-http-' . $status;

            return '';
        }

        $decoded = json_decode((string) ($result['body'] ?? ''), true);
        if (!is_array($decoded) || empty($decoded['success'])) {
            self::$lastJwtSyncHint = 'trust-bad-body';

            return '';
        }
        $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
        $token = trim((string) ($data['token'] ?? ''));
        if ($token !== '') {
            self::$lastJwtSyncHint = 'refreshed';

            return $token;
        }

        return '';
    }

    private static function rewriteStaleUrlsInJsonBody(string $body, string $contentType): string
    {
        if ($body === '' || !str_contains(strtolower($contentType), 'json')) {
            return $body;
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return $body;
        }
        if (!class_exists('ApiMediaUrl', false) && is_readable(BASE_PATH . '/api/MediaUrl.php')) {
            require_once BASE_PATH . '/api/MediaUrl.php';
        }
        if (!class_exists('ApiMediaUrl', false)) {
            return $body;
        }

        return json_encode(
            ApiMediaUrl::rewriteDeep($decoded),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: $body;
    }
}
