<?php

/**
 * Üye JWT ile GET game_history — zarfı backend ile aynı döner (api.md).
 */
final class ApiGameHistory
{
    private const ALLOWED_STATUS = ['completed', 'pending', 'failed', 'cancelled', 'rejected'];
    private const ALLOWED_TXN    = ['bet', 'win', 'cancel', 'adjustment'];

    /**
     * @param array<string, mixed> $get
     * @return array{page: int, per_page: int, status: string|null, txn_type: string|null, source: string|null}
     */
    public static function normalizeQuery(array $get): array
    {
        $pp = ApiListQuery::pagePerPage($get);

        $status = ApiListQuery::optionalLowerToken($get, 'status', self::ALLOWED_STATUS);

        $txnRaw = $get['txn_type'] ?? $get['type'] ?? '';
        $txnRaw = strtolower(trim((string) $txnRaw));
        $txnType = in_array($txnRaw, self::ALLOWED_TXN, true) ? $txnRaw : null;

        $source = isset($get['source']) ? trim((string) $get['source']) : '';
        $source = $source !== '' ? $source : null;

        $q = [
            'page'      => $pp['page'],
            'per_page'  => $pp['per_page'],
            'status'    => $status,
            'txn_type'  => $txnType,
            'source'    => $source,
        ];

        if (isset($get['id']) && (string) $get['id'] !== '') {
            $q['id'] = (string) $get['id'];
        }

        return $q;
    }

    /**
     * Backend sorgu parametreleri (null alanlar gönderilmez).
     *
     * @param array{page: int, per_page: int, status: ?string, txn_type: ?string, source: ?string, id?: string} $norm
     * @return array<string, string|int>
     */
    public static function backendQueryParams(array $norm): array
    {
        $out = [
            'page'      => $norm['page'],
            'per_page'  => $norm['per_page'],
        ];
        if ($norm['status'] !== null) {
            $out['status'] = $norm['status'];
        }
        if ($norm['txn_type'] !== null) {
            $out['txn_type'] = $norm['txn_type'];
        }
        if ($norm['source'] !== null) {
            $out['source'] = $norm['source'];
        }
        if (isset($norm['id']) && $norm['id'] !== '') {
            $out['id'] = $norm['id'];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function fetchEnvelope(string $memberJwt, array $queryNorm): ?array
    {
        $q = self::backendQueryParams($queryNorm);

        return ApiMemberApi::relayGetWithMemberJwt(
            MemberApiPaths::GAME_HISTORY,
            $memberJwt,
            $q,
            25
        );
    }

    /**
     * Tek kayıt: önce id ile istek, yoksa sayfaları tarar.
     *
     * @return array<string, mixed>|null
     */
    public static function findTransactionById(string $memberJwt, string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $env = self::fetchEnvelope($memberJwt, self::normalizeQuery(['id' => $id, 'page' => 1, 'per_page' => 50]));
        $row = self::firstMatchingTransaction($env, $id);
        if ($row !== null) {
            return $row;
        }

        for ($page = 1; $page <= 5; $page++) {
            $env = self::fetchEnvelope($memberJwt, self::normalizeQuery(['page' => $page, 'per_page' => 50]));
            $row = self::firstMatchingTransaction($env, $id);
            if ($row !== null) {
                return $row;
            }
            $pagination = is_array($env['data']['pagination'] ?? null) ? $env['data']['pagination'] : [];
            if (empty($pagination['hasNext'])) {
                break;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $env
     */
    private static function firstMatchingTransaction(?array $env, string $id): ?array
    {
        if ($env === null || empty($env['success'])) {
            return null;
        }
        $data = $env['data'] ?? [];
        if (!is_array($data)) {
            return null;
        }
        $list = $data['transactions'] ?? $data['items'] ?? [];
        if (!is_array($list)) {
            return null;
        }
        foreach ($list as $t) {
            if (!is_array($t)) {
                continue;
            }
            if ((string) ($t['id'] ?? '') === $id) {
                return $t;
            }
        }

        return null;
    }
}
