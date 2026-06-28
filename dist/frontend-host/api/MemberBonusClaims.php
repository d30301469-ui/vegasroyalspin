<?php

/**
 * Üye JWT ile bonus talep listesi (GET bonus_claims_me.php, api.md).
 */
final class ApiMemberBonusClaims
{
    /**
     * @return array<string, mixed>|null
     */
    public static function fetch(string $bearerJwt, int $limit = 20): ?array
    {
        $limit = max(1, min(50, $limit));

        return ApiMemberApi::relayGetWithMemberJwt(
            MemberApiPaths::BONUS_CLAIMS_ME,
            $bearerJwt,
            ['limit' => $limit],
            20
        );
    }
}
