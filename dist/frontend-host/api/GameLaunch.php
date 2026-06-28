<?php

/**
 * Üye API POST game_launch.php — api.md /api/v2/game-launch.
 */
final class ApiGameLaunch
{
    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>|null
     */
    public static function post(array $body, ?string $memberJwt, int $timeout = 60): ?array
    {
        $auth = null;
        if ($memberJwt !== null && trim($memberJwt) !== '') {
            $auth = ApiMemberApi::bearerAuthorizationHeader($memberJwt);
        }

        return ApiMemberApi::relayPost(
            MemberApiPaths::GAME_LAUNCH,
            $body,
            $timeout,
            $auth,
            null,
            static fn (?array $r): bool => $r !== null
        );
    }
}
