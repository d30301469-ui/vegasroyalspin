<?php

/**
 * Public member API path helper. Only /api/v2 endpoints are generated.
 */
final class ApiMemberApi
{
    /**
     * @param list<string> $paths
     * @return list<string>
     */
    private static function uniquePaths(array $paths): array
    {
        $out = [];
        foreach ($paths as $p) {
            $v = trim((string) $p);
            if ($v === '' || $v[0] !== '/') {
                continue;
            }
            if (!in_array($v, $out, true)) {
                $out[] = $v;
            }
        }

        return $out;
    }

    /**
     * @return list<string> Her eleman '/' ile başlayan path
     */
    public static function pathAlternatesForBase(string $baseUrl, string $endpointFile): array
    {
        $base = rtrim($baseUrl, '/');
        $name = ltrim($endpointFile, '/');
        $noext = preg_replace('/\.php$/i', '', $name);
        $with  = '/' . $name;
        $clean = '/' . $noext;
        $prefV2With = '/api/v2/' . $name;
        $prefV2Clean = '/api/v2/' . $noext;

        if ($base !== '' && preg_match('#/api/v2$#', $base)) {
            // Base already ends with /api/v2 — do not append /api/v2/ again (avoids …/api/v2/api/v2/…).
            return self::uniquePaths([$with, $clean]);
        }

        return self::uniquePaths([$prefV2With, $prefV2Clean, $with, $clean]);
    }

    /**
     * Ham token veya tam "Bearer …" — Authorization değeri; boşsa null.
     */
    public static function bearerAuthorizationHeader(?string $memberJwt): ?string
    {
        if ($memberJwt === null) {
            return null;
        }
        $j = trim($memberJwt);
        if ($j === '') {
            return null;
        }

        return stripos($j, 'bearer ') === 0 ? $j : 'Bearer ' . $j;
    }

    /**
     * Relay $accept: yanıt null değil ve boş dizi değil (decode edge-case).
     *
     * @return callable(?array): bool
     */
    public static function acceptNonEmptyResponse(): callable
    {
        return static fn (?array $r): bool => $r !== null && $r !== [];
    }

    /**
     * Sırayla taban ve path alternatifleri dener; $accept($result) true olunca döner.
     *
     * @param list<string> $bases
     * @param callable(string $base, string $path): mixed $request
     * @param callable(mixed): bool $accept
     */
    public static function firstSuccessfulMemberPath(
        array $bases,
        string $endpointFile,
        callable $request,
        callable $accept
    ): mixed {
        foreach ($bases as $base) {
            foreach (self::pathAlternatesForBase($base, $endpointFile) as $path) {
                $result = $request($base, $path);
                if ($accept($result)) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * @param list<string>|null $bases null = ApiBases::forMemberApi()
     * @param callable(?array): bool|null $accept null = yanıt !== null
     * @return array<string, mixed>|null
     */
    public static function relayGet(
        string $endpointFile,
        array $query,
        int $timeout,
        ?string $authorizationHeader = null,
        ?array $bases = null,
        ?callable $accept = null
    ): ?array {
        $baseList = $bases ?? ApiBases::forMemberApi();
        $acceptFn = $accept ?? static fn (?array $r): bool => $r !== null;
        $out      = self::firstSuccessfulMemberPath(
            $baseList,
            $endpointFile,
            static function (string $base, string $path) use ($query, $timeout, $authorizationHeader): ?array {
                return ApiClient::getWithBase($base, $path, $query, $timeout, $authorizationHeader);
            },
            static function ($r) use ($acceptFn): bool {
                return $acceptFn($r);
            }
        );

        return is_array($out) ? $out : null;
    }

    /**
     * @param list<string>|null $bases
     * @param callable(?array): bool|null $accept
     * @return array<string, mixed>|null
     */
    public static function relayPost(
        string $endpointFile,
        array $body,
        int $timeout,
        ?string $authorizationHeader = null,
        ?array $bases = null,
        ?callable $accept = null
    ): ?array {
        $baseList = $bases ?? ApiBases::forMemberApi();
        $acceptFn = $accept ?? static fn (?array $r): bool => $r !== null;
        $out      = self::firstSuccessfulMemberPath(
            $baseList,
            $endpointFile,
            static function (string $base, string $path) use ($body, $timeout, $authorizationHeader): ?array {
                return ApiClient::postWithBase($base, $path, $body, $timeout, $authorizationHeader);
            },
            static function ($r) use ($acceptFn): bool {
                return $acceptFn($r);
            }
        );

        return is_array($out) ? $out : null;
    }

    /**
     * @param list<string>|null $bases
     * @param callable(?array): bool|null $accept
     * @return array<string, mixed>|null
     */
    public static function relayDelete(
        string $endpointFile,
        array $query,
        ?array $body,
        int $timeout,
        ?string $authorizationHeader = null,
        ?array $bases = null,
        ?callable $accept = null
    ): ?array {
        $baseList = $bases ?? ApiBases::forMemberApi();
        $acceptFn = $accept ?? static fn (?array $r): bool => $r !== null;
        $out      = self::firstSuccessfulMemberPath(
            $baseList,
            $endpointFile,
            static function (string $base, string $path) use ($query, $body, $timeout, $authorizationHeader): ?array {
                return ApiClient::deleteWithBase($base, $path, $query, $body, $timeout, $authorizationHeader);
            },
            static function ($r) use ($acceptFn): bool {
                return $acceptFn($r);
            }
        );

        return is_array($out) ? $out : null;
    }

    /**
     * @param list<string>|null $bases
     * @param callable(?array): bool|null $accept
     * @return array<string, mixed>|null
     */
    public static function relayGetWithMemberJwt(
        string $endpointFile,
        string $memberJwt,
        array $query = [],
        int $timeout = 20,
        ?array $bases = null,
        ?callable $accept = null
    ): ?array {
        $auth = self::bearerAuthorizationHeader($memberJwt);
        if ($auth === null) {
            return null;
        }

        return self::relayGet($endpointFile, $query, $timeout, $auth, $bases, $accept);
    }

    /**
     * @param list<string>|null $bases
     * @return array<string, mixed>|null
     */
    public static function relayPostWithMemberJwt(
        string $endpointFile,
        string $memberJwt,
        array $body,
        int $timeout,
        ?array $bases = null,
        ?callable $accept = null
    ): ?array {
        $auth = self::bearerAuthorizationHeader($memberJwt);
        if ($auth === null) {
            return null;
        }

        return self::relayPost($endpointFile, $body, $timeout, $auth, $bases, $accept);
    }
}
