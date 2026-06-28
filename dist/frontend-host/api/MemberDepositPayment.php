<?php

/**
 * Üye JWT ile yatırım yönlendirmesi: GET / POST deposit_payment.php (api.md, zarf).
 */
final class ApiMemberDepositPayment
{
    private const TIMEOUT_GET  = 20;
    private const TIMEOUT_POST = 45;

    /**
     * GET — kullanım bilgisi / zarf (hint, create_deposit şeması, ilgili URL'ler).
     *
     * @return array<string, mixed>|null
     */
    public static function get(string $bearerJwt): ?array
    {
        return ApiMemberApi::relayGetWithMemberJwt(
            MemberApiPaths::DEPOSIT_PAYMENT,
            $bearerJwt,
            [],
            self::TIMEOUT_GET
        );
    }

    /**
     * POST — tutar + payment_method_id veya method (+ provider).
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>|null
     */
    public static function create(string $bearerJwt, array $body): ?array
    {
        return ApiMemberApi::relayPostWithMemberJwt(
            MemberApiPaths::DEPOSIT_PAYMENT,
            $bearerJwt,
            $body,
            self::TIMEOUT_POST
        );
    }
}
