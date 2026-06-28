<?php

require_once __DIR__ . '/BackendApiClient.php';

if (!defined('ADMIN_APP_PATH')) {
    define('ADMIN_APP_PATH', dirname(__DIR__) . '/admin/app');
}

final class SlotGamesQuery
{
    public const GAMES_PATH = 'games.php';

    /**
     * API satırını şablon / slot.js için ortak forma çevirir.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function mapApiRowToLegacy(array $row): array
    {
        return [
            'id'            => (string) ($row['id'] ?? ''),
            'game_id'       => (string) ($row['game_id'] ?? ''),
            'game_name'     => (string) ($row['name'] ?? ''),
            'cover'         => (string) ($row['image_url'] ?? ''),
            'has_demo'      => !empty($row['has_demo']),
            'provider_code' => (string) ($row['provider_code'] ?? ''),
            'provider'      => (string) ($row['provider'] ?? ''),
            'source'        => (string) ($row['source'] ?? ''),
        ];
    }

    /**
     * @return array{
     *   games: array<int, array<string, mixed>>,
     *   total: int,
     *   page: int,
     *   perPage: int,
     *   hasNext: bool,
     *   totalPages: int,
     *   apiError: bool
     * }
     */
    public static function slotsPage(string $searchTerm, array $providers, int $limit, int $page, string $sort = '', array $extraQuery = []): array
    {
        return self::gamesPage(0, $searchTerm, $providers, $limit, $page, $sort, $extraQuery);
    }

    /**
     * @return array{
     *   games: array<int, array<string, mixed>>,
     *   total: int,
     *   page: int,
     *   perPage: int,
     *   hasNext: bool,
     *   totalPages: int,
     *   apiError: bool
     * }
     */
    public static function gamesPage(int $gameType, string $searchTerm, array $providers, int $limit, int $page, string $sort = '', array $extraQuery = []): array
    {
        $limit = min(100, max(1, $limit));
        $page  = max(1, $page);

        $query = [
            'search'             => $searchTerm,
            'limit'              => $limit,
            'page'               => $page,
            'game_type'          => $gameType,
            'filter_game_type'   => $gameType,
        ];

        if ($sort === 'popular') {
            $query['is_featured'] = '1';
        }

        if ($extraQuery !== []) {
            foreach ($extraQuery as $key => $value) {
                if ($value !== null && $value !== '') {
                    $query[(string) $key] = $value;
                }
            }
        }

        $cleanProviders = array_values(array_filter(array_map(static fn ($x): string => trim((string) $x), $providers), static fn (string $x): bool => $x !== ''));
        if ($cleanProviders !== []) {
            // Yeni katalog endpoint'i tek provider filtresi destekler; UI tarafında tek seçim aktif tutulur.
            $query['provider'] = $cleanProviders[0];
        }

        $local = self::localGamesPage($query, $limit, $page, trim($searchTerm) !== '' || $cleanProviders !== []);
        if ($local !== null) {
            $local['apiError'] = false;
            return $local;
        }

        $j = BackendApiClient::request('GET', BackendApiClient::SVC_GAMES, self::GAMES_PATH, $query);
        if ($j === null) {
            $base = self::emptyPageResult($limit, $page);
            $base['apiError'] = true;
            return $base;
        }

        $catalogOrder = trim($searchTerm) !== '' || $cleanProviders !== [];
        $out = self::normalizeGamesResponse($j, $limit, $page, $catalogOrder);
        $out['apiError'] = false;
        return $out;
    }

    /**
     * Tek sayfadaki ham API satırlarını gösterim sırasına göre düzenler (ideal sıra backend’de de üretilebilir).
     * Her zaman: is_popular önce. Sonrası: $catalogOrder true ise isim (katalog), değilse featured_order (1,2,…; null son).
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private static function sortApiGameRows(array &$rows, bool $catalogOrderAfterPopular): void
    {
        usort($rows, static function (array $a, array $b) use ($catalogOrderAfterPopular): int {
            $popA = self::rowIsPopular($a);
            $popB = self::rowIsPopular($b);
            if ($popA !== $popB) {
                return $popA ? -1 : 1;
            }
            if ($catalogOrderAfterPopular) {
                $nameA = (string) ($a['name'] ?? '');
                $nameB = (string) ($b['name'] ?? '');
                $cmp = strnatcasecmp($nameA, $nameB);
                if ($cmp !== 0) {
                    return $cmp;
                }
            } else {
                $ordA = self::featuredOrderRank($a['featured_order'] ?? null);
                $ordB = self::featuredOrderRank($b['featured_order'] ?? null);
                if ($ordA !== $ordB) {
                    return $ordA <=> $ordB;
                }
            }

            return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
        });
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function rowIsPopular(array $row): bool
    {
        $v = $row['is_popular'] ?? null;
        if ($v === true || $v === 1 || $v === '1' || $v === 'true') {
            return true;
        }

        return false;
    }

    /**
     * @param mixed $featuredOrder
     */
    private static function featuredOrderRank($featuredOrder): int
    {
        if ($featuredOrder === null || $featuredOrder === '') {
            return PHP_INT_MAX;
        }

        return (int) $featuredOrder;
    }

    /**
     * @return array{games: array, total: int, page: int, perPage: int, hasNext: bool, totalPages: int}
     */
    private static function emptyPageResult(int $requestedLimit, int $requestedPage): array
    {
        return [
            'games'      => [],
            'total'      => 0,
            'page'       => $requestedPage,
            'perPage'    => $requestedLimit,
            'hasNext'    => false,
            'totalPages' => 0,
        ];
    }

    /**
     * @param array<string, mixed>|null $j
     * @return array{games: array, total: int, page: int, perPage: int, hasNext: bool, totalPages: int}
     */
    public static function normalizeGamesResponse(?array $j, int $requestedLimit, int $requestedPage, bool $catalogOrderAfterPopular = false): array
    {
        $empty = [
            'games'      => [],
            'total'      => 0,
            'page'       => $requestedPage,
            'perPage'    => $requestedLimit,
            'hasNext'    => false,
            'totalPages' => 0,
        ];

        if ($j === null) {
            return $empty;
        }

        $success = $j['success'] ?? null;
        if ($success !== true && $success !== 1 && $success !== '1' && $success !== 'true') {
            return $empty;
        }

        $data = isset($j['data']) && is_array($j['data']) ? $j['data'] : [];
        $gamesRaw = $data['games'] ?? [];
        if (!is_array($gamesRaw)) {
            $gamesRaw = [];
        }

        $rows = [];
        foreach ($gamesRaw as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
        self::sortApiGameRows($rows, $catalogOrderAfterPopular);

        $mapped = [];
        foreach ($rows as $row) {
            $mapped[] = self::mapApiRowToLegacy($row);
        }

        $p           = isset($data['pagination']) && is_array($data['pagination']) ? $data['pagination'] : [];
        $total       = (int) ($p['total'] ?? 0);
        $perPage     = (int) ($p['perPage'] ?? $requestedLimit);
        if ($perPage < 1) {
            $perPage = $requestedLimit;
        }
        $pageRet     = (int) ($p['page'] ?? $requestedPage);
        $hasNext     = !empty($p['hasNext']);
        $totalPages  = (int) ($p['totalPages'] ?? 0);

        return [
            'games'      => $mapped,
            'total'      => $total,
            'page'       => $pageRet > 0 ? $pageRet : $requestedPage,
            'perPage'    => $perPage,
            'hasNext'    => $hasNext,
            'totalPages' => $totalPages,
        ];
    }

    public static function allProviders(): array
    {
        $local = self::localProviders(0);
        if ($local !== []) {
            return $local;
        }

        $j = BackendApiClient::request('GET', BackendApiClient::SVC_GAMES, 'games_provider.php', ['game_type' => 0]);
        if ($j === null) {
            return [];
        }
        $u   = BackendApiClient::unwrap($j);
        $raw = $u['providers'] ?? $j['providers'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $providers = [];
        foreach ($raw as $row) {
            if (is_string($row) && $row !== '') {
                $providers[] = $row;
            } elseif (is_array($row) && !empty($row['provider_name'])) {
                $providers[] = $row['provider_name'];
            }
        }

        return $providers;
    }

    public static function providersForGameType(int $gameType, ?string $category = null): array
    {
        $local = self::localProviders($gameType);
        if ($local !== []) {
            return $local;
        }

        $j = BackendApiClient::request('GET', BackendApiClient::SVC_GAMES, 'games_provider.php', ['game_type' => $gameType]);
        if ($j === null) {
            return [];
        }
        $u = BackendApiClient::unwrap($j);
        $raw = $u['providers'] ?? $j['providers'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $providers = [];
        foreach ($raw as $row) {
            if (is_string($row) && $row !== '') {
                $providers[] = $row;
            } elseif (is_array($row) && !empty($row['provider_name'])) {
                $providers[] = (string) $row['provider_name'];
            }
        }
        $providers = array_values(array_unique(array_filter($providers)));
        sort($providers, SORT_NATURAL | SORT_FLAG_CASE);
        return $providers;
    }

    private static function localGamesPage(array $query, int $limit, int $page, bool $catalogOrderAfterPopular): ?array
    {
        if (function_exists('frontend_database_allowed') && !frontend_database_allowed()) {
            return null;
        }

        if (!class_exists('AdminDatabase', false)) {
            if (is_file(ADMIN_APP_PATH . '/Core/AdminDatabase.php')) {
                require_once ADMIN_APP_PATH . '/Core/AdminDatabase.php';
            }
        }
        if (!class_exists('AdminDatabase', false)) {
            return null;
        }

        require_once __DIR__ . '/DrakonService.php';
        require_once __DIR__ . '/BgamingService.php';

        try {
            $pdo = AdminDatabase::pdo();
            $source = strtolower(trim((string) ($query['source'] ?? '')));
            $provider = strtolower(trim((string) ($query['provider'] ?? $query['provider_code'] ?? '')));
            $sort = strtolower(trim((string) ($query['sort'] ?? $query['category'] ?? '')));
            $tvOnly = in_array($source, ['bgaming', 'tv'], true)
                || in_array($provider, ['bgaming'], true)
                || in_array($sort, ['tv', 'tv-games', 'tv_oyunlari'], true);

            if ($tvOnly) {
                $catalog = BgamingService::games($pdo, $query);
            } else {
                $catalog = DrakonService::games($pdo, $query);
            }
            $j = ['success' => true, 'data' => $catalog];
            return self::normalizeGamesResponse($j, $limit, $page, $catalogOrderAfterPopular);
        } catch (Throwable) {
            return null;
        }
    }

    private static function localProviders(int $gameType): array
    {
        if (function_exists('frontend_database_allowed') && !frontend_database_allowed()) {
            return [];
        }

        if (!class_exists('AdminDatabase', false)) {
            if (is_file(ADMIN_APP_PATH . '/Core/AdminDatabase.php')) {
                require_once ADMIN_APP_PATH . '/Core/AdminDatabase.php';
            }
        }
        if (!class_exists('AdminDatabase', false)) {
            return [];
        }

        require_once __DIR__ . '/DrakonService.php';

        try {
            $pdo = AdminDatabase::pdo();
            $rows = DrakonService::providers($pdo, ['game_type' => $gameType]);
        } catch (Throwable) {
            return [];
        }
        $providers = [];
        foreach ($rows as $row) {
            if (is_array($row) && !empty($row['provider_name'])) {
                $providers[] = (string) $row['provider_name'];
            }
        }
        $providers = array_values(array_unique(array_filter($providers)));
        sort($providers, SORT_NATURAL | SORT_FLAG_CASE);
        return $providers;
    }

    public static function winnersPool(int $limit = 200): array
    {
        $j = BackendApiClient::request('GET', BackendApiClient::SVC_GAMES, '/slots/winners-pool', ['limit' => $limit]);
        if ($j === null) {
            return [];
        }
        $u = BackendApiClient::unwrap($j);
        return $u['games'] ?? $j['games'] ?? [];
    }
}
