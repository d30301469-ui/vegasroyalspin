<?php

/**
 * POST /api/v2/password_update.php — oturumlu üye, Bearer JWT (zarf; başarıda yeni token).
 */
final class ApiPasswordUpdate
{
    /**
     * @param array<string, mixed> $body current_password, password, password_confirmation (api.md)
     *
     * @return array<string, mixed>|null
     */
    public static function submitEnvelope(string $memberJwt, array $body, int $timeout = 25): ?array
    {
        return ApiMemberApi::relayPostWithMemberJwt(
            MemberApiPaths::PASSWORD_UPDATE,
            $memberJwt,
            $body,
            $timeout
        );
    }
}
