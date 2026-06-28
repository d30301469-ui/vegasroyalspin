<?php

/**
 * Üye API: GET/POST call_me_request (api.md, MemberApiPaths::CALL_ME_REQUEST).
 */
final class ApiCallMeRequest
{
    private const ACTIONS = ['info', 'usage', 'help'];

    /**
     * Tam JSON zarfı (success, code, message, data) veya bağlantı hatasında null.
     *
     * @return array<string, mixed>|null
     */
    public static function fetchEnvelope(?string $memberJwt, string $action = 'info'): ?array
    {
        $action = strtolower(trim($action));
        if ($action === '') {
            $action = 'info';
        }
        if (!in_array($action, self::ACTIONS, true)) {
            return null;
        }

        $auth = ApiMemberApi::bearerAuthorizationHeader($memberJwt);

        return ApiMemberApi::relayGet(
            MemberApiPaths::CALL_ME_REQUEST,
            ['action' => $action],
            30,
            $auth
        );
    }

    /**
     * data.call_me_request veya [].
     *
     * @return array<string, mixed>
     */
    public static function dataSlice(?array $envelope): array
    {
        if (!is_array($envelope)) {
            return [];
        }
        $data = $envelope['data'] ?? [];
        if (!is_array($data)) {
            return [];
        }
        $block = $data['call_me_request'] ?? null;

        return is_array($block) ? $block : [];
    }

    /**
     * @param array<string, mixed> $body fullName, phone, reason, username?, message?
     * @return array<string, mixed>|null
     */
    public static function submit(array $body, ?string $memberJwt): ?array
    {
        $auth = ApiMemberApi::bearerAuthorizationHeader($memberJwt);

        return ApiMemberApi::relayPost(
            MemberApiPaths::CALL_ME_REQUEST,
            $body,
            30,
            $auth
        );
    }
}
