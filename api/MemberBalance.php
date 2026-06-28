<?php

/**
 * Üye JWT ile bakiye (GET balance.php, api.md).
 */
final class ApiMemberBalance
{
    /**
     * @return array<string, mixed>|null
     */
    public static function fetch(string $bearerJwt): ?array
    {
        return ApiMemberApi::relayGetWithMemberJwt(
            MemberApiPaths::BALANCE,
            $bearerJwt,
            [],
            12
        );
    }
}
