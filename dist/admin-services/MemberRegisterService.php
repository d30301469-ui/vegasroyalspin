<?php

require_once __DIR__ . '/BackendApiClient.php';
require_once __DIR__ . '/MemberLoginService.php';

/**
 * Üye API: POST /register.php (JSON envelope, api.md).
 */
final class MemberRegisterService
{
    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>|null
     */
    public static function register(array $body): ?array
    {
        return BackendApiClient::request('POST', BackendApiClient::SVC_MAIN, '/register.php', [], $body);
    }

    public static function succeeded(?array $res): bool
    {
        return MemberLoginService::envelopeSucceeded($res, 201);
    }

    public static function applySession(array $res, string $usernameFallback): void
    {
        MemberLoginService::applySession($res, $usernameFallback);
    }

    public static function failureMessage(?array $res): string
    {
        return MemberLoginService::envelopeMessageOr($res, 'Kayıt başarısız.');
    }
}
