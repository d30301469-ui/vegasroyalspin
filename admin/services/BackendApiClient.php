<?php

require_once dirname(__DIR__) . '/config/backend_api.php';

/**
 * Tüm backend HTTP çağrıları. base URL boşsa null döner (çağıran taraf varsayılan kullanır).
 */
final class BackendApiClient
{
    public const SVC_MAIN = 'main';
    public const SVC_AFFILIATE = 'affiliate';
    public const SVC_CASINO_WALLET = 'casino_wallet';
    public const SVC_PAYMENT_CALLBACK = 'payment_callback';
    public const SVC_GAMES = 'games';

    /**
     * Ana API tabanı: MAIN doluysa o; boşsa SLIDER tabanı (slider ile aynı yedek, api.md örnek kök).
     */
    public static function effectiveMainBaseUrl(): string
    {
        $main = defined('API_BACKEND_MAIN_BASE_URL')
            ? rtrim((string) API_BACKEND_MAIN_BASE_URL, '/')
            : '';
        if ($main !== '') {
            return $main;
        }
        $slider = defined('API_BACKEND_SLIDER_BASE_URL')
            ? rtrim((string) API_BACKEND_SLIDER_BASE_URL, '/')
            : '';

        return $slider;
    }

    /**
     * Outbound HTTP from PHP (frontend → backend). Uses internal loopback when configured.
     */
    public static function effectiveOutboundMainBaseUrl(): string
    {
        if (defined('API_BACKEND_INTERNAL_BASE_URL')) {
            $internal = rtrim(trim((string) API_BACKEND_INTERNAL_BASE_URL), '/');
            if ($internal !== '') {
                return $internal;
            }
        }

        $detected = self::detectCachedInternalBaseUrl();
        if ($detected !== '') {
            return $detected;
        }

        return self::effectiveMainBaseUrl();
    }

    /**
     * Split-deploy (API-only frontend): üye JWT / balance / login doğrudan public backend'e gider.
     * 127.0.0.1 loopback aynı VM'deki frontend proxy'ye düşer → oturum/JWT kaybolur → 401.
     */
    public static function effectiveMemberApiOutboundBaseUrl(): string
    {
        if (function_exists('frontend_is_api_only') && frontend_is_api_only()) {
            $public = self::effectiveMainBaseUrl();
            if ($public !== '') {
                return $public;
            }
        }

        return self::effectiveOutboundMainBaseUrl();
    }

    /**
     * Split-deploy proxy: sırayla public api → main backend → loopback dene.
     *
     * @return list<string>
     */
    public static function memberApiOutboundBaseCandidates(): array
    {
        $candidates = [];
        $add = static function (string $url) use (&$candidates): void {
            $url = rtrim(trim($url), '/');
            if ($url === '' || in_array($url, $candidates, true)) {
                return;
            }
            $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
            if ($host === '' || str_ends_with($host, '.test') || in_array($host, ['localhost', '127.0.0.1'], true)) {
                return;
            }
            $candidates[] = $url;
        };

        if (function_exists('frontend_is_api_only') && frontend_is_api_only()) {
            $add(self::effectiveMemberApiOutboundBaseUrl());
            $add(self::effectiveMainBaseUrl());
            if (defined('API_BACKEND_FALLBACK_BASE_URL')) {
                $add((string) API_BACKEND_FALLBACK_BASE_URL);
            }
            if (function_exists('deploy_domain')) {
                $add(deploy_domain('api_public_base_url'));
            }
            if (defined('BACKEND_URL')) {
                $add(rtrim((string) BACKEND_URL, '/') . '/api/v2');
            }
        } else {
            $add(self::effectiveMemberApiOutboundBaseUrl());
            $add(self::effectiveMainBaseUrl());
            $add(self::effectiveOutboundMainBaseUrl());
        }

        return $candidates;
    }

    private static function internalBaseCachePath(): string
    {
        $base = defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__);

        return rtrim(str_replace('\\', '/', $base), '/') . '/storage/cache/backend_internal_base.json';
    }

    private static function detectCachedInternalBaseUrl(): string
    {
        static $memory = null;
        if (is_string($memory)) {
            return $memory;
        }

        $path = self::internalBaseCachePath();
        if (is_readable($path)) {
            $cached = json_decode((string) @file_get_contents($path), true);
            if (is_array($cached)) {
                $savedAt = (int) ($cached['saved_at'] ?? 0);
                $url = rtrim(trim((string) ($cached['internal_base'] ?? '')), '/');
                if ($url !== '' && $savedAt > 0 && (time() - $savedAt) < 3600) {
                    $memory = $url;

                    return $url;
                }
            }
        }

        if (!is_readable(dirname(__DIR__) . '/services/BackendConnectivityProbe.php')) {
            $memory = '';

            return '';
        }
        require_once dirname(__DIR__) . '/services/BackendConnectivityProbe.php';

        $backendHost = defined('API_BACKEND_INTERNAL_HOST')
            ? trim((string) API_BACKEND_INTERNAL_HOST)
            : '';
        if ($backendHost === '' && defined('BACKEND_HOST')) {
            $backendHost = trim((string) BACKEND_HOST);
        }
        if ($backendHost === '' && defined('BACKEND_URL')) {
            $backendHost = strtolower((string) (parse_url((string) BACKEND_URL, PHP_URL_HOST) ?: ''));
        }
        if ($backendHost === '') {
            $public = self::effectiveMainBaseUrl();
            $backendHost = strtolower((string) (parse_url($public, PHP_URL_HOST) ?: 'bo-nexthub.site'));
        }
        if (str_starts_with($backendHost, 'api.')) {
            $backendHost = substr($backendHost, 4);
        }

        $detected = BackendConnectivityProbe::detectInternalConfig($backendHost);
        if ($detected === null) {
            $memory = '';

            return '';
        }

        $url = rtrim(trim((string) ($detected['internal_base'] ?? '')), '/');
        if ($url === '') {
            $memory = '';

            return '';
        }

        $dir = dirname($path);
        $canWriteCache = false;
        if (is_dir($dir)) {
            $canWriteCache = is_writable($dir) && (!file_exists($path) || is_writable($path));
        } else {
            $parentDir = dirname($dir);
            if (is_dir($parentDir) && is_writable($parentDir)) {
                $created = @mkdir($dir, 0755, true);
                $canWriteCache = $created && is_writable($dir);
            }
        }
        if ($canWriteCache) {
            $payload = json_encode([
                'saved_at' => time(),
                'internal_base' => $url,
                'internal_host' => (string) ($detected['internal_host'] ?? $backendHost),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($payload)) {
                @file_put_contents($path, $payload, LOCK_EX);
            }
        }

        $memory = $url;

        return $url;
    }

    /**
     * Host header for loopback requests (auto-detected internal base uses cached host).
     */
    public static function effectiveOutboundHostHeader(): string
    {
        if (defined('API_BACKEND_INTERNAL_HOST')) {
            $host = trim((string) API_BACKEND_INTERNAL_HOST);
            if ($host !== '') {
                return strtolower(preg_replace('/:\d+$/', '', $host) ?? '');
            }
        }

        $path = self::internalBaseCachePath();
        if (is_readable($path)) {
            $cached = json_decode((string) @file_get_contents($path), true);
            if (is_array($cached)) {
                $host = trim((string) ($cached['internal_host'] ?? ''));
                if ($host !== '') {
                    return strtolower(preg_replace('/:\d+$/', '', $host) ?? '');
                }
            }
        }

        foreach ([
            defined('BACKEND_HOST') ? trim((string) BACKEND_HOST) : '',
            parse_url(defined('BACKEND_URL') ? (string) BACKEND_URL : '', PHP_URL_HOST) ?: '',
        ] as $candidate) {
            $host = strtolower(preg_replace('/:\d+$/', '', trim((string) $candidate)) ?? '');
            if ($host !== '' && !str_starts_with($host, 'api.')) {
                return $host;
            }
        }

        $public = self::effectiveMainBaseUrl();
        $host = strtolower((string) (parse_url($public, PHP_URL_HOST) ?: 'bo-nexthub.site'));
        if (str_starts_with($host, 'api.')) {
            $host = substr($host, 4);
        }

        return $host !== '' ? $host : 'bo-nexthub.site';
    }

    /**
     * @param list<string> $headers
     * @return list<string>
     */
    public static function applyOutboundHostHeader(array $headers): array
    {
        $outbound = self::effectiveOutboundMainBaseUrl();
        if (!self::isLoopbackBaseUrl($outbound)) {
            return $headers;
        }

        $host = self::effectiveOutboundHostHeader();
        if ($host === '') {
            $public = self::effectiveMainBaseUrl();
            $host = strtolower((string) (parse_url($public, PHP_URL_HOST) ?: ''));
        }
        if ($host === '') {
            return $headers;
        }

        $hasHost = false;
        foreach ($headers as $headerLine) {
            if (stripos((string) $headerLine, 'Host:') === 0) {
                $hasHost = true;
                break;
            }
        }
        if (!$hasHost) {
            $headers[] = 'Host: ' . $host;
        }

        return $headers;
    }

    private static function isLoopbackBaseUrl(string $base): bool
    {
        $host = strtolower((string) (parse_url($base, PHP_URL_HOST) ?: ''));

        return in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    }

    private static function baseUrl(string $which): string
    {
        $mainEffective = self::effectiveOutboundMainBaseUrl();
        $games = API_BACKEND_GAMES_BASE_URL !== ''
            ? API_BACKEND_GAMES_BASE_URL
            : $mainEffective;

        return rtrim(match ($which) {
            self::SVC_MAIN => $mainEffective,
            self::SVC_AFFILIATE => API_BACKEND_AFFILIATE_BASE_URL,
            self::SVC_CASINO_WALLET => API_BACKEND_CASINO_WALLET_BASE_URL,
            self::SVC_PAYMENT_CALLBACK => API_BACKEND_PAYMENT_CALLBACK_BASE_URL !== ''
                ? API_BACKEND_PAYMENT_CALLBACK_BASE_URL
                : $mainEffective,
            self::SVC_GAMES => $games,
            default => '',
        }, '/');
    }

    /** Özel CA yolu (backend_api.php) veya paket cacert.pem; yoksa sistem CA (Linux vb.). */
    private static function resolvePrimaryCainfo(): ?string
    {
        if (defined('API_BACKEND_CURL_CAINFO') && API_BACKEND_CURL_CAINFO !== '' && is_readable(API_BACKEND_CURL_CAINFO)) {
            return API_BACKEND_CURL_CAINFO;
        }
        $caBundle = dirname(__DIR__) . '/config/cacert.pem';
        if (is_readable($caBundle)) {
            return $caBundle;
        }

        return null;
    }

    /**
     * Paket CA ile doğrulama başarısız olunca (eski cacert, ara sertifika vb.) sistem deposuyla bir kez daha dene.
     *
     * @see https://curl.se/libcurl/c/libcurl-errors.html
     */
    private static function shouldRetrySslWithoutBundle(int $curlErrno): bool
    {
        return in_array($curlErrno, [
            60, // CURLE_SSL_CACERT (peer verify)
            77, // CURLE_SSL_CACERT_BADFILE
            35, // CURLE_SSL_CONNECT_ERROR
            51, // CURLE_PEER_FAILED_VERIFICATION
            58, // CURLE_SSL_CERTPROBLEM
        ], true);
    }

    private static function applyCurlTransportOptions($ch, int $timeout): void
    {
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        $connect = min($timeout, max(2, (int) round($timeout / 2)));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect);
        if (defined('CURL_HTTP_VERSION_2TLS')) {
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2TLS);
        } elseif (defined('CURL_HTTP_VERSION_1_1')) {
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        }
        if (defined('CURLOPT_TCP_KEEPALIVE')) {
            curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
        }
        if (defined('CURLOPT_DNS_CACHE_TIMEOUT')) {
            curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 120);
        }
        if (defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
    }

    /**
     * SSL için CA dosyası başarısız olursa sistem CA ile yeniden dener (paylaşımlı hosting / güncel zincir uyumu).
     *
     * @param callable(resource):void $configure CURLOPT_CUSTOMREQUEST, POSTFIELDS, HTTPHEADER vb.
     * @return array{0: string|false, 1: int} ham gövde ve curl_errno
     */
    private static function execCurlWithOptionalSslRetry(string $url, int $timeout, callable $configure): array
    {
        $primaryCa = self::resolvePrimaryCainfo();
        $caAttempts = $primaryCa !== null ? [$primaryCa, null] : [null];
        $raw = false;
        $errno = 0;
        foreach ($caAttempts as $cainfo) {
            $ch = curl_init($url);
            self::applyCurlTransportOptions($ch, $timeout);
            if ($cainfo !== null) {
                curl_setopt($ch, CURLOPT_CAINFO, $cainfo);
            }
            $configure($ch);
            $raw = curl_exec($ch);
            $errno = (int) curl_errno($ch);
            curl_close($ch);
            if ($raw !== false && $raw !== '') {
                return [$raw, $errno];
            }
            if ($cainfo === null || !self::shouldRetrySslWithoutBundle($errno)) {
                return [$raw, $errno];
            }
        }

        return [$raw, $errno];
    }

    /**
     * Mutlak base (örn. https://bo.betco.pro/api/v2) + path ile JSON isteği.
     * MAIN boşken slider vb. için kullanılır.
     *
     * @return array<string, mixed>|null
     */
    public static function requestWithBase(
        string $method,
        string $absoluteBaseUrl,
        string $path,
        array $query = [],
        $body = null,
        int $timeout = 30,
        ?string $authorizationHeader = null
    ): ?array {
        $base = rtrim($absoluteBaseUrl, '/');
        if ($base === '') {
            return null;
        }

        return self::executeJsonRequest($base, $method, $path, $query, $body, $timeout, $authorizationHeader);
    }

    /**
     * Üye JWT (Bearer) ile ana API — session.php, logout.php vb.
     *
     * @return array<string, mixed>|null
     */
    public static function requestWithMemberBearer(
        string $method,
        string $which,
        string $path,
        string $bearerJwt,
        array $query = [],
        $body = null,
        int $timeout = 15
    ): ?array {
        $jwt = trim($bearerJwt);
        $base = self::baseUrl($which);
        if ($base === '' || $jwt === '') {
            return null;
        }

        return self::executeJsonRequest($base, $method, $path, $query, $body, $timeout, 'Bearer ' . $jwt);
    }

    /**
     * Mutlak base + üye Bearer JWT (ör. public member API uçları, farklı MAIN/SLIDER tabanları).
     *
     * @return array<string, mixed>|null
     */
    public static function requestWithBaseAndMemberBearer(
        string $method,
        string $absoluteBaseUrl,
        string $path,
        string $bearerJwt,
        array $query = [],
        $body = null,
        int $timeout = 15
    ): ?array {
        $jwt = trim($bearerJwt);
        $base = rtrim($absoluteBaseUrl, '/');
        if ($base === '' || $jwt === '') {
            return null;
        }

        return self::executeJsonRequest($base, $method, $path, $query, $body, $timeout, 'Bearer ' . $jwt);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function executeJsonRequest(
        string $baseNoTrailingSlash,
        string $method,
        string $path,
        array $query,
        $body,
        int $timeout,
        ?string $authorizationHeader = null
    ): ?array {
        $path = '/' . ltrim($path, '/');
        $url  = $baseNoTrailingSlash . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $headers = ['Accept: application/json'];
        if ($authorizationHeader !== null && $authorizationHeader !== '') {
            $headers[] = 'Authorization: ' . $authorizationHeader;
        } elseif (API_BACKEND_AUTH_HEADER !== '') {
            $headers[] = 'Authorization: ' . API_BACKEND_AUTH_HEADER;
        }

        $methodU = strtoupper($method);
        $headersForCurl = self::applyOutboundHostHeader($headers);
        if ($body !== null) {
            $headersForCurl[] = 'Content-Type: application/json';
        }
        $bodyPayload = null;
        if ($body !== null) {
            $bodyPayload = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE);
        }

        [$raw, ] = self::execCurlWithOptionalSslRetry($url, $timeout, function ($ch) use ($methodU, $headersForCurl, $bodyPayload) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $methodU);
            if ($bodyPayload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyPayload);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headersForCurl);
        });

        if ($raw === false || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function request(
        string $method,
        string $which,
        string $path,
        array $query = [],
        $body = null,
        int $timeout = 30
    ): ?array {
        $base = self::baseUrl($which);
        if ($base === '') {
            return null;
        }

        return self::executeJsonRequest($base, $method, $path, $query, $body, $timeout, null);
    }

    /**
     * Ham JSON yanıt (casino seamless proxy). Başarısızda null.
     */
    public static function forwardPostJson(string $which, string $path, string $rawJson, int $timeout = 60): ?string
    {
        $base = self::baseUrl($which);
        if ($base === '') {
            return null;
        }

        $url = $base . '/' . ltrim($path, '/');
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        if (API_BACKEND_AUTH_HEADER !== '') {
            $headers[] = 'Authorization: ' . API_BACKEND_AUTH_HEADER;
        }
        $headers = self::applyOutboundHostHeader($headers);

        [$raw, ] = self::execCurlWithOptionalSslRetry($url, $timeout, function ($ch) use ($headers, $rawJson) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $rawJson);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        });

        return ($raw === false || $raw === '') ? null : $raw;
    }

    /**
     * @param array<string, mixed>|null $json
     * @return array<string, mixed>
     */
    public static function unwrap(?array $json): array
    {
        if ($json === null) {
            return [];
        }
        if (isset($json['data']) && is_array($json['data'])) {
            return $json['data'];
        }
        return $json;
    }

    /**
     * Transparent HTTP proxy for member API routes (frontend → backend).
     *
     * @return array{status: int, body: string, content_type: string|null}|null
     */
    public static function proxyHttp(
        string $method,
        string $absoluteBaseUrl,
        string $path,
        array $query = [],
        ?string $rawBody = null,
        ?string $contentType = null,
        ?string $authorizationHeader = null,
        int $timeout = 60,
        array $extraHeaders = [],
        bool $useBackendAuthFallback = true
    ): ?array {
        $base = rtrim($absoluteBaseUrl, '/');
        if ($base === '') {
            return null;
        }

        $path = trim($path, '/');
        $url = $base . ($path !== '' ? '/' . $path : '');
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $headers = ['Accept: application/json'];
        if ($authorizationHeader !== null && $authorizationHeader !== '') {
            $headers[] = 'Authorization: ' . $authorizationHeader;
        } elseif ($useBackendAuthFallback && API_BACKEND_AUTH_HEADER !== '') {
            $headers[] = 'Authorization: ' . API_BACKEND_AUTH_HEADER;
        }
        $headers = self::applyOutboundHostHeader($headers);
        foreach ($extraHeaders as $headerLine) {
            $headerLine = trim((string) $headerLine);
            if ($headerLine !== '') {
                $headers[] = $headerLine;
            }
        }

        $methodU = strtoupper($method);
        $headersForCurl = $headers;
        if ($rawBody !== null && $rawBody !== '') {
            $headersForCurl[] = 'Content-Type: ' . ($contentType !== null && $contentType !== ''
                ? $contentType
                : 'application/json');
        }

        $bodyPayload = ($rawBody !== null && $rawBody !== '') ? $rawBody : null;

        $primaryCa = self::resolvePrimaryCainfo();
        $caAttempts = $primaryCa !== null ? [$primaryCa, null] : [null];
        $raw = false;
        $errno = 0;
        $status = 502;
        $responseContentType = null;
        $lastCurlError = '';

        foreach ($caAttempts as $cainfo) {
            $ch = curl_init($url);
            self::applyCurlTransportOptions($ch, $timeout);
            if ($cainfo !== null) {
                curl_setopt($ch, CURLOPT_CAINFO, $cainfo);
            }
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $methodU);
            if ($bodyPayload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyPayload);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headersForCurl);
            $raw = curl_exec($ch);
            $errno = (int) curl_errno($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $responseContentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            if ($raw === false) {
                $lastCurlError = (string) curl_error($ch);
            }
            curl_close($ch);

            if ($raw !== false) {
                break;
            }
            if ($cainfo === null || !self::shouldRetrySslWithoutBundle($errno)) {
                break;
            }
        }

        if ($raw === false) {
            $message = 'Backend API request failed.';
            if ($lastCurlError !== '') {
                $message = 'Backend bağlantı hatası: ' . $lastCurlError;
            } elseif ($errno !== 0) {
                $message = 'Backend bağlantı hatası (curl ' . $errno . ').';
            }

            return [
                'status' => 502,
                'body' => json_encode([
                    'success' => false,
                    'ok' => false,
                    'code' => 502,
                    'message' => $message,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'content_type' => 'application/json; charset=UTF-8',
                'transport_error' => true,
                'error_message' => $message,
            ];
        }

        $contentTypeOut = is_string($responseContentType) && $responseContentType !== ''
            ? $responseContentType
            : null;

        return [
            'status' => $status > 0 ? $status : 502,
            'body' => (string) $raw,
            'content_type' => $contentTypeOut,
        ];
    }
}
