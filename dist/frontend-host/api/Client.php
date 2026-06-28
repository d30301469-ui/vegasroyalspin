<?php

/**
 * BackendApiClient üzerinde tek giriş noktası (servis sabitleri burada kullanılır).
 */
final class ApiClient
{
    private static function ensureBackendClient(): void
    {
        if (class_exists('BackendApiClient', false)) {
            return;
        }

        $path = (defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__)) . '/services/BackendApiClient.php';
        if (is_readable($path)) {
            require_once $path;
        }
    }

    /**
     * Split frontend: public HTTPS base yerine internal loopback + Host header kullan.
     */
    private static function resolveRequestBase(string $absoluteBaseUrl): string
    {
        $base = rtrim(trim($absoluteBaseUrl), '/');
        if ($base === '') {
            self::ensureBackendClient();

            return class_exists('BackendApiClient', false)
                ? BackendApiClient::effectiveOutboundMainBaseUrl()
                : '';
        }

        if (!function_exists('frontend_is_api_only') || !frontend_is_api_only()) {
            return $base;
        }

        self::ensureBackendClient();
        if (!class_exists('BackendApiClient', false)) {
            return $base;
        }

        $outbound = BackendApiClient::effectiveOutboundMainBaseUrl();

        return $outbound !== '' ? $outbound : $base;
    }

    /**
     * @param array<string, string|int|float|bool|null> $query
     * @return array<string, mixed>|null
     */
    public static function mainGet(string $path, array $query = [], int $timeout = 30): ?array
    {
        return BackendApiClient::request('GET', BackendApiClient::SVC_MAIN, $path, $query, null, $timeout);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>|null
     */
    public static function mainPost(string $path, array $body = [], int $timeout = 30): ?array
    {
        return BackendApiClient::request('POST', BackendApiClient::SVC_MAIN, $path, [], $body, $timeout);
    }

    /**
     * @param array<string, string|int|float|bool|null> $query
     * @return array<string, mixed>|null
     */
    public static function gamesGet(string $path, array $query = [], int $timeout = 30): ?array
    {
        return BackendApiClient::request('GET', BackendApiClient::SVC_GAMES, $path, $query, null, $timeout);
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function gamesPost(string $path, array $body = [], int $timeout = 30): ?array
    {
        return BackendApiClient::request('POST', BackendApiClient::SVC_GAMES, $path, [], $body, $timeout);
    }

    /**
     * @param array<string, string|int|float|bool|null> $query
     * @return array<string, mixed>|null
     */
    public static function affiliateGet(string $path, array $query = [], int $timeout = 30): ?array
    {
        return BackendApiClient::request('GET', BackendApiClient::SVC_AFFILIATE, $path, $query, null, $timeout);
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function affiliatePost(string $path, array $body = [], int $timeout = 30): ?array
    {
        return BackendApiClient::request('POST', BackendApiClient::SVC_AFFILIATE, $path, [], $body, $timeout);
    }

    /**
     * @param array<string, string|int|float|bool|null> $query
     */
    public static function casinoWalletGet(string $path, array $query = [], int $timeout = 30): ?array
    {
        return BackendApiClient::request('GET', BackendApiClient::SVC_CASINO_WALLET, $path, $query, null, $timeout);
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function casinoWalletPost(string $path, array $body = [], int $timeout = 30): ?array
    {
        return BackendApiClient::request('POST', BackendApiClient::SVC_CASINO_WALLET, $path, [], $body, $timeout);
    }

    /**
     * @param array<string, string|int|float|bool|null> $query
     * @return array<string, mixed>|null
     */
    public static function getWithBase(
        string $absoluteBaseUrl,
        string $path,
        array $query = [],
        int $timeout = 30,
        ?string $authorizationHeader = null
    ): ?array {
        return BackendApiClient::requestWithBase(
            'GET',
            self::resolveRequestBase($absoluteBaseUrl),
            $path,
            $query,
            null,
            $timeout,
            $authorizationHeader
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function postWithBase(
        string $absoluteBaseUrl,
        string $path,
        array $body = [],
        int $timeout = 30,
        ?string $authorizationHeader = null
    ): ?array {
        return BackendApiClient::requestWithBase(
            'POST',
            self::resolveRequestBase($absoluteBaseUrl),
            $path,
            [],
            $body,
            $timeout,
            $authorizationHeader
        );
    }

    /**
     * @param array<string, string|int|float|bool|null> $query
     * @param array<string, mixed>|null $body Gövde (JSON); null = gönderme
     */
    public static function deleteWithBase(
        string $absoluteBaseUrl,
        string $path,
        array $query = [],
        ?array $body = null,
        int $timeout = 30,
        ?string $authorizationHeader = null
    ): ?array {
        return BackendApiClient::requestWithBase(
            'DELETE',
            self::resolveRequestBase($absoluteBaseUrl),
            $path,
            $query,
            $body,
            $timeout,
            $authorizationHeader
        );
    }

    /**
     * @param array<string, mixed>|null $json
     * @return array<string, mixed>
     */
    public static function unwrap(?array $json): array
    {
        return BackendApiClient::unwrap($json);
    }
}
