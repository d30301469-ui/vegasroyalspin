<?php

/**
 * Üye JWT ile promocodes listesi ve promocode_request (api.md).
 */
final class ApiMemberPromo
{
    /**
     * @return array<string, mixed>|null
     */
    public static function fetchPromocodes(string $bearerJwt): ?array
    {
        return ApiMemberApi::relayGetWithMemberJwt(
            MemberApiPaths::PROMOCODES,
            $bearerJwt,
            [],
            20
        );
    }

    /**
     * @param array<string, mixed>|null $jsonBody POST gövdesi (decode edilmiş); GET için null
     * @return array<string, mixed>|null
     */
    public static function forwardPromocodeRequest(string $method, string $bearerJwt, array $getQuery, ?array $jsonBody): ?array
    {
        $auth = ApiMemberApi::bearerAuthorizationHeader($bearerJwt);
        if ($auth === null) {
            return null;
        }
        $method = strtoupper($method);

        $query = [];
        foreach (['promocodeId', 'promocode_id', 'message'] as $k) {
            if (isset($getQuery[$k]) && $getQuery[$k] !== '') {
                $query[$k] = $getQuery[$k];
            }
        }

        $out = ApiMemberApi::firstSuccessfulMemberPath(
            ApiBases::forMemberApi(),
            MemberApiPaths::PROMOCODE_REQUEST,
            static function (string $base, string $path) use ($method, $query, $jsonBody, $auth): ?array {
                if ($method === 'GET') {
                    return ApiClient::getWithBase($base, $path, $query, 20, $auth);
                }
                if ($method === 'POST') {
                    return ApiClient::postWithBase($base, $path, $jsonBody ?? [], 20, $auth);
                }

                return null;
            },
            static fn (?array $r): bool => $r !== null
        );

        return is_array($out) ? $out : null;
    }
}
