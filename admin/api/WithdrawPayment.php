<?php

/**
 * Üye JWT ile çekim formu / çekim talebi (GET|POST withdraw_payment.php, api v2).
 */
final class ApiWithdrawPayment
{
    /**
     * @return array<string, mixed>|null
     */
    public static function fetch(string $bearerJwt): ?array
    {
        return ApiMemberApi::relayGetWithMemberJwt(
            MemberApiPaths::WITHDRAW_PAYMENT,
            $bearerJwt,
            [],
            25
        );
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>|null
     */
    public static function submit(string $bearerJwt, array $body): ?array
    {
        return ApiMemberApi::relayPostWithMemberJwt(
            MemberApiPaths::WITHDRAW_PAYMENT,
            $bearerJwt,
            $body,
            30
        );
    }
}
