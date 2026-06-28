<?php

/**
 * Sayfalı liste GET parametreleri (deposit_history, withdraw_history, game_history vb.).
 */
final class ApiListQuery
{
    /** deposit_history / withdraw_history ortak status filtresi (api.md). */
    public const MEMBER_DEPOSIT_WITHDRAW_STATUSES = ['pending', 'confirmed', 'rejected', 'completed'];

    /**
     * @param array<string, mixed> $get
     * @return array{page: int, per_page: int}
     */
    public static function pagePerPage(array $get, int $defaultPerPage = 20, int $maxPerPage = 50): array
    {
        $page = isset($get['page']) ? (int) $get['page'] : 1;
        $page = max(1, $page);

        $perPage = isset($get['per_page']) ? (int) $get['per_page'] : $defaultPerPage;
        $perPage = max(1, min($maxPerPage, $perPage));

        return [
            'page'      => $page,
            'per_page'  => $perPage,
        ];
    }

    /**
     * @param list<string> $allowed Küçük harf token listesi
     */
    public static function optionalLowerToken(array $get, string $key, array $allowed): ?string
    {
        $v = isset($get[$key]) ? strtolower(trim((string) $get[$key])) : '';

        return in_array($v, $allowed, true) ? $v : null;
    }

    /**
     * @param array{page: int, per_page: int, status: ?string} $norm
     * @return array<string, string|int>
     */
    public static function backendQueryPageOptionalStatus(array $norm): array
    {
        $out = [
            'page'      => $norm['page'],
            'per_page'  => $norm['per_page'],
        ];
        if ($norm['status'] !== null) {
            $out['status'] = $norm['status'];
        }

        return $out;
    }

    /**
     * Yatırım / çekim geçmişi: sayfa + isteğe bağlı status (aynı sorgu şeması).
     *
     * @param list<string> $allowedStatus
     * @return array{page: int, per_page: int, status: string|null}
     */
    public static function normalizeMemberDepositWithdrawHistory(array $get, array $allowedStatus = self::MEMBER_DEPOSIT_WITHDRAW_STATUSES): array
    {
        $pp = self::pagePerPage($get);

        return [
            'page'      => $pp['page'],
            'per_page'  => $pp['per_page'],
            'status'    => self::optionalLowerToken($get, 'status', $allowedStatus),
        ];
    }

    /**
     * Boş string ve null anahtarları atar (GET query relay).
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public static function stripEmptyQueryValues(array $query): array
    {
        $q = [];
        foreach ($query as $k => $v) {
            if ($v === '' || $v === null) {
                continue;
            }
            $q[$k] = $v;
        }

        return $q;
    }
}
