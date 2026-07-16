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

        try {
            $pdo = AdminDatabase::pdo();
            $catalog = self::combinedCatalogPage($pdo, $query, $limit, $page);
            $j = ['success' => true, 'data' => $catalog];
            return self::normalizeGamesResponse($j, $limit, $page, $catalogOrderAfterPopular);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Combined BGaming + Drakon catalog page filtered by game_type.
     *
     * BGaming games are all slots (game_type 0). Drakon games carry a real
     * game_type column (0 = slot/casino, 1 = live casino). The two sources are
     * merged with a single UNION so search, provider filtering and pagination
     * stay correct across both providers.
     *
     * @param array<string, mixed> $query
     * @return array{games: array<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    private static function combinedCatalogPage(PDO $pdo, array $query, int $limit, int $page): array
    {
        $gameType = (int) ($query['game_type'] ?? $query['filter_game_type'] ?? 0);
        $gameType = $gameType === 1 ? 1 : 0;
        $limit    = min(200, max(1, $limit));
        $page     = max(1, $page);
        $offset   = ($page - 1) * $limit;

        $search       = trim((string) ($query['search'] ?? ''));
        $provider     = trim((string) ($query['provider'] ?? $query['provider_code'] ?? ''));
        $onlyFeatured = (string) ($query['is_featured'] ?? '') === '1';
        // Optional source restriction: 'bgaming' shows only the direct BGaming
        // catalog, 'drakon' only the Drakon catalog. Empty means both providers.
        // Required because Drakon (aggregator) also carries BGaming-branded games,
        // so filtering by provider name alone would leak Drakon rows onto the
        // dedicated /bgaming page.
        $source       = strtolower(trim((string) ($query['source'] ?? '')));

        $union = [];
        // BGaming catalog is slot-only; include only on the slot lobby.
        if ($gameType === 0 && ($source === '' || $source === 'bgaming')) {
            $union[] = "SELECT
                    CONCAT('bgaming:', identifier) AS game_id,
                    title AS name,
                    provider AS provider,
                    provider AS provider_code,
                    COALESCE(NULLIF(thumbnail_url, ''), '') AS image_url,
                    is_featured AS is_featured,
                    'bgaming' AS source,
                    CAST(id AS CHAR) AS row_id
                FROM bgaming_games
                WHERE is_active = 1";
        }
        if ($source === '' || $source === 'drakon') {
            $union[] = "SELECT
                    CONCAT('drakon:', game_id) AS game_id,
                    game_name AS name,
                    provider_name AS provider,
                    provider_name AS provider_code,
                    COALESCE(NULLIF(image_url, ''), NULLIF(banner, ''), '') AS image_url,
                    is_featured AS is_featured,
                    'drakon' AS source,
                    CAST(id AS CHAR) AS row_id
                FROM drakon_games
                WHERE is_active = 1 AND game_type = {$gameType}";
        }

        if ($union === []) {
            return [
                'games' => [],
                'items' => [],
                'pagination' => [
                    'page'       => $page,
                    'perPage'    => $limit,
                    'limit'      => $limit,
                    'offset'     => $offset,
                    'total'      => 0,
                    'totalPages' => 0,
                    'hasNext'    => false,
                    'hasPrev'    => $offset > 0,
                ],
            ];
        }

        $unionSql = '(' . implode(' UNION ALL ', $union) . ') AS catalog';

        $where  = [];
        $params = [];
        if ($search !== '') {
            $where[]           = '(name LIKE :search OR provider LIKE :search2)';
            $params[':search']  = '%' . $search . '%';
            $params[':search2'] = '%' . $search . '%';
        }
        if ($provider !== '' && strtolower($provider) !== 'hepsi') {
            $where[]             = 'provider = :provider';
            $params[':provider'] = $provider;
        }
        if ($onlyFeatured) {
            $where[] = 'is_featured = 1';
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM {$unionSql}{$whereSql}");
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $rowsStmt = $pdo->prepare(
            "SELECT game_id, name, provider, provider_code, image_url, is_featured, source, row_id
             FROM {$unionSql}{$whereSql}
             ORDER BY is_featured DESC, name ASC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            $rowsStmt->bindValue($k, $v);
        }
        $rowsStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $rowsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $rowsStmt->execute();
        $items = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);

        $games = array_map(static function (array $r): array {
            $featured = (int) ($r['is_featured'] ?? 0);
            return [
                'id'            => (string) ($r['row_id'] ?? ''),
                'game_id'       => (string) ($r['game_id'] ?? ''),
                'name'          => (string) ($r['name'] ?? ''),
                'image_url'     => (string) ($r['image_url'] ?? ''),
                'provider'      => (string) ($r['provider'] ?? ''),
                'provider_code' => (string) ($r['provider_code'] ?? ''),
                'is_featured'   => $featured,
                'is_popular'    => $featured === 1,
                'has_demo'      => true,
                'source'        => (string) ($r['source'] ?? ''),
            ];
        }, is_array($items) ? $items : []);

        return [
            'games' => $games,
            'items' => $games,
            'pagination' => [
                'page'       => $page,
                'perPage'    => $limit,
                'limit'      => $limit,
                'offset'     => $offset,
                'total'      => $total,
                'totalPages' => $total > 0 ? (int) ceil($total / $limit) : 0,
                'hasNext'    => ($offset + $limit) < $total,
                'hasPrev'    => $offset > 0,
            ],
        ];
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

        try {
            $pdo = AdminDatabase::pdo();
            $gt  = $gameType === 1 ? 1 : 0;
            $union = [];
            // BGaming providers are slot-only.
            if ($gt === 0) {
                $union[] = "SELECT DISTINCT provider AS provider_name
                    FROM bgaming_games
                    WHERE is_active = 1 AND provider <> ''";
            }
            $union[] = "SELECT DISTINCT provider_name
                FROM drakon_games
                WHERE is_active = 1 AND game_type = {$gt} AND provider_name <> ''";
            $sql  = implode(' UNION ', $union);
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
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
