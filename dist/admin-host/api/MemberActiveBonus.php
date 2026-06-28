<?php

/**
 * Üye JWT ile aktif bonus (GET active_bonus.php, api.md).
 */
final class ApiMemberActiveBonus
{
    /**
     * @return array<string, mixed>|null
     */
    public static function fetch(string $bearerJwt): ?array
    {
        return ApiMemberApi::relayGetWithMemberJwt(
            MemberApiPaths::ACTIVE_BONUS,
            $bearerJwt,
            [],
            15
        );
    }
}
