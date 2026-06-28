<?php

/**
 * Üye gelen kutusu (admin → member_inbox_messages) — GET, JWT yok; api.md.
 */
final class ApiMemberInboxMessages
{
    /**
     * Backend zarfı; bağlantı/yanıt yoksa null.
     *
     * @return array<string, mixed>|null
     */
    public static function fetchEnvelope(): ?array
    {
        return ApiMemberApi::relayGet(
            MemberApiPaths::MEMBER_INBOX_MESSAGES,
            [],
            30,
            null,
            null,
            ApiMemberApi::acceptNonEmptyResponse()
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function fetchMessages(): array
    {
        $env  = self::fetchEnvelope();
        $list = ApiEnvelope::listFromData($env, 'messages');

        return $list ?? [];
    }

    /**
     * Başarısız veya hatalı zarf için boş liste ile 200 zarfı (api.md).
     *
     * @return array<string, mixed>
     */
    public static function emptySuccessEnvelope(): array
    {
        return [
            'success' => true,
            'code'    => 200,
            'message' => 'Mesajlar getirildi.',
            'data'    => [
                'messages' => [],
            ],
        ];
    }
}
