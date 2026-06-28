<?php

/**
 * Üye JWT ile GET withdraw_history — zarf backend ile aynı (api.md).
 * Sorgu yapısı deposit_history ile aynı: page, per_page, status.
 */
final class ApiWithdrawHistory
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
            MemberApiPaths::WITHDRAW_HISTORY,
            $memberJwt,
            $q,
            25
        );
    }
}
