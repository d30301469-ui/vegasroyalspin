<?php

/**
 * POST /api/v2/account_freeze.php — hesap dondurma, Bearer JWT (zarf + data.errors).
 */
final class ApiAccountFreeze
{
    /**
     * @param array<string, mixed> $body en az password
     * @return array<string, mixed>|null
     */
    public static function submitEnvelope(string $memberJwt, array $body, int $timeout = 25): ?array
    {
        return ApiMemberApi::relayPostWithMemberJwt(
            MemberApiPaths::ACCOUNT_FREEZE,
            $memberJwt,
            $body,
            $timeout
        );
    }
}
