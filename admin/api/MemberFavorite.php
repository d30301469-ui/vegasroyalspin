<?php

/**
 * Üye JWT ile favorite_slots / favorite_live_casino (api.md) — backend'e iletir.
 */
final class ApiMemberFavorite
{
    /**
     * @param array<string, mixed> $getQuery
     * @param array<string, mixed>|null $jsonBody POST/DELETE gövdesi (decode edilmiş)
     * @return array<string, mixed>|null
     */
    public static function forward(string $endpointFile, string $method, string $bearerJwt, array $getQuery, ?array $jsonBody): ?array
    {
        $auth = ApiMemberApi::bearerAuthorizationHeader($bearerJwt);
        if ($auth === null) {
            return null;
        }
        $method = strtoupper($method);

        $out = ApiMemberApi::firstSuccessfulMemberPath(
            ApiBases::forMemberApiWithGames(),
            $endpointFile,
            static function (string $base, string $path) use ($method, $getQuery, $jsonBody, $auth): ?array {
                if ($method === 'GET') {
                    $query = [];
                    foreach (['page', 'limit'] as $k) {
                        if (isset($getQuery[$k]) && $getQuery[$k] !== '') {
                            $query[$k] = $getQuery[$k];
                        }
                    }

                    return ApiClient::getWithBase($base, $path, $query, 25, $auth);
                }
                if ($method === 'POST') {
                    $body = is_array($jsonBody) ? $jsonBody : [];

                    return ApiClient::postWithBase($base, $path, $body, 25, $auth);
                }
                if ($method === 'DELETE') {
                    $delQuery = self::mergeGameIdParams($getQuery, $jsonBody);
                    $delBody  = $delQuery === [] && is_array($jsonBody) && $jsonBody !== [] ? $jsonBody : null;

                    return ApiClient::deleteWithBase($base, $path, $delQuery, $delBody, 25, $auth);
                }

                return null;
            },
            static fn (?array $r): bool => $r !== null
        );

        return is_array($out) ? $out : null;
    }

    /**
     * @param array<string, mixed> $getQuery
     * @param array<string, mixed>|null $jsonBody
     * @return array<string, string|int|float|bool|null>
     */
    private static function mergeGameIdParams(array $getQuery, ?array $jsonBody): array
    {
        $q = [];
        foreach (['game_id', 'gameId'] as $k) {
            if (isset($getQuery[$k]) && $getQuery[$k] !== '') {
                $q[$k] = $getQuery[$k];
            }
        }
        if ($jsonBody !== null) {
            foreach (['game_id', 'gameId'] as $k) {
                if (isset($jsonBody[$k]) && $jsonBody[$k] !== '' && !isset($q['game_id']) && !isset($q['gameId'])) {
                    $q[$k] = $jsonBody[$k];
                }
            }
        }

        return $q;
    }
}
