<?php

/**
 * Member API GET winners — ApiBases + path alternates (api.md: /winners.php, /winners).
 */
final class ApiWinners
{
    /**
     * @param array<string, mixed> $get $_GET veya benzeri
     * @return array{winners_tab: string, winners_period: string, limit: int}
     */
    public static function normalizeQuery(array $get): array
    {
        $tab = $get['winners_tab'] ?? $get['tab'] ?? 'recent';
        $tab = ($tab === 'top') ? 'top' : 'recent';

        $period = $get['winners_period'] ?? $get['period'] ?? 'day';
        if (!in_array($period, ['day', 'week', 'month', 'all'], true)) {
            $period = 'day';
        }

        $limit = isset($get['limit']) ? (int) $get['limit'] : ($tab === 'top' ? 20 : 40);
        $limit = max(1, min(100, $limit));

        return [
            'winners_tab'     => $tab,
            'winners_period'  => $period,
            'limit'           => $limit,
        ];
    }

    /**
     * Backend zarfını olduğu gibi döndürür; bağlantı/parse hatasında null.
     *
     * @param array{winners_tab: string, winners_period: string, limit: int} $query
     * @return array<string, mixed>|null
     */
    public static function fetchEnvelope(array $query): ?array
    {
        $q = [
            'winners_tab'    => $query['winners_tab'],
            'winners_period' => $query['winners_period'],
            'limit'          => $query['limit'],
        ];

        return ApiMemberApi::relayGet(MemberApiPaths::WINNERS, $q, 30);
    }
}
