<?php

/**
 * Üye API GET payment_methods — kimlik doğrulamasız (public) zarf proxy.
 */
final class ApiPaymentMethods
{
    /**
     * Backend zarfını olduğu gibi döndürür; bağlantı/parse hatasında null.
     *
     * @return array<string, mixed>|null
     */
    public static function fetchEnvelope(): ?array
    {
        return ApiMemberApi::relayGet(
            MemberApiPaths::PAYMENT_METHODS,
            [],
            15,
            null,
            null,
            ApiMemberApi::acceptNonEmptyResponse()
        );
    }
}
