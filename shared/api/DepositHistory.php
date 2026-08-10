<?php

/**
 * Üye JWT ile GET deposit_history — backend zarfı aynen iletilir.
 */
final class ApiDepositHistory
{
    /**
     * @param array<string, mixed> $get
     * @return array{page: int, per_page: int, status: string|null}
     */
    public static function normalizeQuery(array $get): array
    {
        return ApiListQuery::normalizeMemberDepositWithdrawHistory($get);
    }

    /**
     * @param array{page: int, per_page: int, status: ?string} $norm
     * @return array<string, string|int>
     */
    public static function backendQueryParams(array $norm): array
    {
        return ApiListQuery::backendQueryPageOptionalStatus($norm);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function fetchEnvelope(string $memberJwt, array $queryNorm): ?array
    {
        $q = self::backendQueryParams($queryNorm);

        return ApiMemberApi::relayGetWithMemberJwt(
            MemberApiPaths::DEPOSIT_HISTORY,
            $memberJwt,
            $q,
            25
        );
    }
}
