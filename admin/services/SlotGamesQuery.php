<?php

require_once __DIR__ . '/BackendApiClient.php';

if (!defined('ADMIN_APP_PATH')) {
    define('ADMIN_APP_PATH', dirname(__DIR__) . '/admin/app');
}

if (!class_exists('CasinoAggregatorService', false)) {
    $aggregatorServicePath = is_file(__DIR__ . '/CasinoAggregatorService.php')
        ? __DIR__ . '/CasinoAggregatorService.php'
        : dirname(__DIR__) . '/services/CasinoAggregatorService.php';
    if (is_file($aggregatorServicePath)) {
        require_once $aggregatorServicePath;
    }
}

final class SlotGamesQuery
{
    public const GAMES_PATH = 'games.php';

    private const CACHE_TTL_SEC = 120;
    private const CACHE_STALE_SEC = 86400;

    /**
     * API satırını şablon / slot.js için ortak forma çevirir.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function mapApiRowToLegacy(array $row): array
    {
        $provider = self::normalizeProviderLabel($row['provider'] ?? '');
        $existingCover = trim((string) ($row['cover'] ?? $row['image_url'] ?? ''));
        $existingFallbacks = is_array($row['cover_fallbacks'] ?? null)
            ? $row['cover_fallbacks']
            : (is_array($row['image_fallbacks'] ?? null) ? $row['image_fallbacks'] : []);

        if ($existingCover !== '' && $existingFallbacks !== []) {
            $media = [
                'cover'           => $existingCover,
                'cover_fallbacks' => $existingFallbacks,
                'image_fallbacks' => $existingFallbacks,
            ];
        } else {
            $media = class_exists('CasinoAggregatorService', false)
                ? CasinoAggregatorService::hydrateGameMedia($row)
                : [
                    'cover'           => self::normalizeGameImage($row),
                    'cover_fallbacks' => [],
                    'image_fallbacks' => [],
                ];
        }

        return [
            'id'            => (string) ($row['id'] ?? ''),
            'game_id'       => (string) ($row['game_id'] ?? ''),
            'game_name'     => self::normalizeGameName($row['name'] ?? $row['game_name'] ?? ''),
            'cover'         => (string) ($media['cover'] ?? ''),
            'cover_fallbacks' => is_array($media['cover_fallbacks'] ?? null) ? $media['cover_fallbacks'] : [],
            'has_demo'      => !empty($row['has_demo']),
            'provider_code' => (string) ($row['provider_code'] ?? ''),
            'provider'      => $provider,
            'source'        => (string) ($row['source'] ?? ''),
        ];
    }

    /**
     * Provider test entries are operational data, not public catalogue games.
     * Keep them in the backend/admin catalogue, but never expose them on the
     * public frontend.
     *
     * @param array<string, mixed> $row
     */
    public static function isFrontendHiddenGame(array $row): bool
    {
        $values = [
            $row['name'] ?? $row['game_name'] ?? $row['title'] ?? '',
            $row['game_id'] ?? $row['game_identifier'] ?? $row['identifier'] ?? $row['slug'] ?? '',
        ];

        foreach ($values as $value) {
            $candidate = strtolower(trim((string) $value));
            if ($candidate !== '' && preg_match('/(?:^|[^a-z0-9])acceptance[\s:_-]*test(?:$|[^a-z0-9])/i', $candidate) === 1) {
                return true;
            }
        }

        return false;
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

    public static function liveCasinoPage(string $searchTerm, array $providers, int $limit, int $page, string $sort = '', array $extraQuery = []): array
    {
        // Deprecated path: live casino is owned by LiveCasinoQuery.
        $livePath = __DIR__ . '/LiveCasinoQuery.php';
        if (is_file($livePath)) {
            require_once $livePath;
        }
        if (class_exists('LiveCasinoQuery', false)) {
            return LiveCasinoQuery::page($searchTerm, $providers, $limit, $page, $sort, $extraQuery);
        }

        return self::gamesPage(1, $searchTerm, $providers, $limit, $page, $sort, $extraQuery);
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
            $query['providers'] = $cleanProviders;
            $query['provider'] = $cleanProviders[0];
        }

        $local = self::localGamesPage($query, $limit, $page, trim($searchTerm) !== '' || $cleanProviders !== []);
        if ($local !== null) {
            $local['apiError'] = false;
            return $local;
        }

        $cacheKey = 'games:' . sha1(json_encode($query, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        $cached = self::cacheRead($cacheKey);
        if ($cached !== null && empty($cached['_stale'])) {
            unset($cached['_stale'], $cached['_cached_at']);
            return $cached;
        }

        // Lobby: kısa timeout; asla 30s askıda kalmasın.
        $timeout = $gameType === 1 ? 4 : 6;
        $j = BackendApiClient::request('GET', BackendApiClient::SVC_GAMES, self::GAMES_PATH, $query, null, $timeout);
        if ($j === null) {
            if ($cached !== null) {
                unset($cached['_stale'], $cached['_cached_at']);
                $cached['apiError'] = false;
                return $cached;
            }
            $base = self::emptyPageResult($limit, $page);
            $base['apiError'] = true;
            return $base;
        }

        $catalogOrder = trim($searchTerm) !== '' || $cleanProviders !== [];
        $out = self::normalizeGamesResponse($j, $limit, $page, $catalogOrder);
        $out['apiError'] = false;
        self::cacheWrite($cacheKey, $out);
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
            if (is_array($row) && !self::isFrontendHiddenGame($row)) {
                $rows[] = $row;
            }
        }
        self::sortApiGameRows($rows, $catalogOrderAfterPopular);

        $mapped = [];
        foreach ($rows as $row) {
            $mapped[] = self::mapApiRowToLegacy($row);
        }

        $p           = isset($data['pagination']) && is_array($data['pagination']) ? $data['pagination'] : [];
        $total       = max(0, (int) ($p['total'] ?? 0) - max(0, count($gamesRaw) - count($rows)));
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
        return self::providersForGameType(0);
    }

    public static function providersForGameType(int $gameType, ?string $category = null): array
    {
        $local = self::localProviders($gameType);
        if ($local !== []) {
            return $local;
        }

        $cacheKey = 'providers:' . $gameType;
        $cached = self::cacheRead($cacheKey);
        if (is_array($cached) && isset($cached['providers']) && empty($cached['_stale'])) {
            return array_values(array_filter(array_map('strval', $cached['providers'])));
        }

        $j = BackendApiClient::request(
            'GET',
            BackendApiClient::SVC_GAMES,
            'games_provider.php',
            ['game_type' => $gameType],
            null,
            $gameType === 1 ? 3 : 5
        );
        if ($j === null) {
            if (is_array($cached) && isset($cached['providers'])) {
                return array_values(array_filter(array_map('strval', $cached['providers'])));
            }
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
                $providers[] = self::normalizeProviderLabel((string) $row['provider_name']);
            }
        }
        $providers = array_values(array_unique(array_filter($providers)));
        sort($providers, SORT_NATURAL | SORT_FLAG_CASE);
        self::cacheWrite($cacheKey, ['providers' => $providers]);
        return $providers;
    }

    private static function cacheDir(): string
    {
        $base = defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__);

        return rtrim(str_replace('\\', '/', $base), '/') . '/storage/cache/games';
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function cacheRead(string $key): ?array
    {
        $path = self::cacheDir() . '/' . preg_replace('/[^a-zA-Z0-9:_-]+/', '_', $key) . '.json';
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['_cached_at'])) {
            return null;
        }
        $age = time() - (int) $decoded['_cached_at'];
        if ($age > self::CACHE_STALE_SEC) {
            return null;
        }
        $decoded['_stale'] = $age > self::CACHE_TTL_SEC;

        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function cacheWrite(string $key, array $payload): void
    {
        $dir = self::cacheDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $path = $dir . '/' . preg_replace('/[^a-zA-Z0-9:_-]+/', '_', $key) . '.json';
        $payload['_cached_at'] = time();
        unset($payload['_stale']);
        @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    public static function purgeCache(): void
    {
        $dir = self::cacheDir();
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            @unlink($file);
        }
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
            $gameType = (int) ($query['game_type'] ?? $query['filter_game_type'] ?? 0);
            // Yerel katalog: slot (0) ve canlı casino (1) aggregator oyunlarını da içerir.
            $catalog = self::combinedCatalogPage($pdo, $query, $limit, $page);
            $j = ['success' => true, 'data' => $catalog];
            return self::normalizeGamesResponse($j, $limit, $page, $catalogOrderAfterPopular);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * BGaming catalog page filtered by game_type.
     *
     * BGaming games are all slots (game_type 0), so the live-casino lobby
     * (game_type 1) returns an empty result set.
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
        $providerList = [];
        if (isset($query['providers']) && is_array($query['providers'])) {
            $providerList = array_values(array_filter(array_map(static fn ($x): string => trim((string) $x), $query['providers']), static fn (string $x): bool => $x !== '' && strtolower($x) !== 'hepsi'));
        } elseif ($provider !== '' && strtolower($provider) !== 'hepsi') {
            $providerList = [$provider];
        }
        $onlyFeatured = (string) ($query['is_featured'] ?? '') === '1';
        // Optional source restriction: 'bgaming' shows only the direct BGaming
        // catalog. Empty means all sources.
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
                    CAST('' AS CHAR) AS image_fallbacks,
                    is_featured AS is_featured,
                    'bgaming' AS source,
                    CAST(id AS CHAR) AS row_id,
                    CAST('' AS CHAR) AS raw_payload
                FROM bgaming_games
                WHERE is_active = 1";
        }
        $aggGameType = $gameType === 1 ? 2 : 1;
        if ($source === '' || $source === 'aggregator') {
            if ($gameType === 1 && class_exists('CasinoAggregatorService', false)) {
                static $liveGamesRepaired = false;
                if (!$liveGamesRepaired) {
                    $liveGamesRepaired = true;
                    try {
                        CasinoAggregatorService::repairGameTypesFromPayload($pdo);
                    } catch (Throwable) {
                    }
                }
            }
            $typeClause = "g.game_type = {$aggGameType}";
            if (class_exists('CasinoAggregatorService', false)) {
                $liveMatch = CasinoAggregatorService::liveVendorSqlMatch('g.vendor_code');
                if ($gameType === 1) {
                    $typeClause = "(g.game_type = {$aggGameType} OR {$liveMatch})";
                } else {
                    // Keep live brands out of the slot lobby.
                    $typeClause = "(g.game_type = {$aggGameType} AND NOT {$liveMatch})";
                }
            }
            $union[] = "SELECT
                    CONCAT('aggregator:', g.vendor_code, ':', g.game_code) AS game_id,
                    g.game_name AS name,
                    COALESCE(NULLIF(v.vendor_name, ''), g.vendor_code) AS provider,
                    g.vendor_code AS provider_code,
                    COALESCE(NULLIF(g.image_url, ''), '') AS image_url,
                    CAST('' AS CHAR) AS image_fallbacks,
                    g.is_featured AS is_featured,
                    'aggregator' AS source,
                    CAST(g.id AS CHAR) AS row_id,
                    CAST('' AS CHAR) AS raw_payload
                FROM casino_aggregator_games g
                INNER JOIN casino_aggregator_vendors v ON v.vendor_code = g.vendor_code
                WHERE g.is_active = 1 AND v.is_active = 1 AND {$typeClause}";
        }
        if ($source === '' || $source === 'gamingsoft') {
            if (!class_exists('GamingSoftService', false)) {
                $gsServicePath = is_file(__DIR__ . '/GamingSoftService.php')
                    ? __DIR__ . '/GamingSoftService.php'
                    : dirname(__DIR__) . '/services/GamingSoftService.php';
                if (is_readable($gsServicePath)) {
                    require_once $gsServicePath;
                }
            }
            $gsTypeExpr = "CASE
                WHEN UPPER(TRIM(g.game_type)) IN ('LIVE_CASINO','LIVE_CASINO_PREMIUM','LC','LIVE','LIVE CASINO','LIVE-CASINO')
                  OR UPPER(TRIM(g.game_type)) LIKE 'LIVE\\_CASINO%'
                  OR UPPER(TRIM(g.game_type)) LIKE '%LIVE\\_CASINO%'
                  OR UPPER(TRIM(g.game_type)) LIKE '%LIVE CASINO%'
                THEN 2 ELSE 1 END";
            $gsTypeClause = $gameType === 1
                ? "({$gsTypeExpr}) = 2"
                : "({$gsTypeExpr}) = 1";
            $union[] = "SELECT
                    CONCAT('gamingsoft:', g.product_code, ':', g.game_code) AS game_id,
                    g.game_name AS name,
                    COALESCE(NULLIF(p.provider, ''), NULLIF(p.product_name, ''), CAST(g.product_code AS CHAR)) AS provider,
                    CAST(g.product_code AS CHAR) AS provider_code,
                    COALESCE(NULLIF(g.image_url, ''), '') AS image_url,
                    CAST('' AS CHAR) AS image_fallbacks,
                    g.is_featured AS is_featured,
                    'gamingsoft' AS source,
                    CAST(g.id AS CHAR) AS row_id,
                    CAST('' AS CHAR) AS raw_payload
                FROM gamingsoft_games g
                LEFT JOIN gamingsoft_products p ON p.product_code = g.product_code
                WHERE g.is_active = 1 AND {$gsTypeClause}";
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
        if ($providerList !== []) {
            $filterTerms = $providerList;
            if (class_exists('CasinoAggregatorService', false)) {
                try {
                    $expanded = CasinoAggregatorService::expandProviderFilter($pdo, $providerList);
                    $filterTerms = array_values(array_unique(array_merge($expanded['names'], $expanded['codes'])));
                } catch (Throwable) {
                }
            }
            $providerPlaceholders = [];
            $codePlaceholders = [];
            foreach ($filterTerms as $idx => $providerName) {
                $providerKey = ':provider' . $idx;
                $codeKey = ':provider_code' . $idx;
                $providerPlaceholders[] = $providerKey;
                $codePlaceholders[] = $codeKey;
                $params[$providerKey] = $providerName;
                $params[$codeKey] = $providerName;
            }
            $where[] = '(provider IN (' . implode(', ', $providerPlaceholders) . ') OR provider_code IN (' . implode(', ', $codePlaceholders) . '))';
        }
        if ($onlyFeatured) {
            $where[] = 'is_featured = 1';
        }
        // Acceptance Test is retained for provider/admin diagnostics only.
        $where[] = "LOWER(name) NOT LIKE '%acceptance%test%'";
        $where[] = "LOWER(game_id) NOT LIKE '%acceptance%test%'";
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM {$unionSql}{$whereSql}");
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $rowsStmt = $pdo->prepare(
            "SELECT game_id, name, provider, provider_code, image_url, image_fallbacks, is_featured, source, row_id, raw_payload
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
            $provider = SlotGamesQuery::normalizeProviderLabel($r['provider'] ?? '');
            $imageUrl = SlotGamesQuery::normalizeGameImage($r);
            $name = SlotGamesQuery::normalizeGameName($r['name'] ?? '');
            $media = class_exists('CasinoAggregatorService', false)
                ? CasinoAggregatorService::hydrateGameMedia($r)
                : ['cover' => $imageUrl, 'cover_fallbacks' => [], 'image_fallbacks' => []];

            return [
                'id'            => (string) ($r['row_id'] ?? ''),
                'game_id'       => (string) ($r['game_id'] ?? ''),
                'name'          => $name,
                'cover'         => (string) ($media['cover'] ?? $imageUrl),
                'image_url'     => (string) ($media['cover'] ?? $imageUrl),
                'cover_fallbacks' => is_array($media['cover_fallbacks'] ?? null) ? $media['cover_fallbacks'] : [],
                'image_fallbacks' => is_array($media['image_fallbacks'] ?? null) ? $media['image_fallbacks'] : [],
                'provider'      => $provider,
                'provider_code' => (string) ($r['provider_code'] ?? ''),
                'is_featured'   => $featured,
                'is_popular'    => $featured === 1,
                'has_demo'      => true,
                'source'        => (string) ($r['source'] ?? ''),
                'raw_payload'   => (string) ($r['raw_payload'] ?? ''),
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

        $providers = [];
        $seen = [];
        try {
            $pdo = AdminDatabase::pdo();
            if ($gameType === 0) {
                $rows = $pdo->query(
                    "SELECT DISTINCT provider AS provider_name
                     FROM bgaming_games
                     WHERE is_active = 1 AND provider <> ''
                     ORDER BY provider_name ASC"
                )->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    if (is_array($row) && !empty($row['provider_name'])) {
                        $name = self::normalizeProviderLabel((string) $row['provider_name']);
                        if (!isset($seen[$name])) {
                            $seen[$name] = true;
                            $providers[] = $name;
                        }
                    }
                }
            }
            $aggType = $gameType === 1 ? 2 : 1;
            if ($gameType === 1 && class_exists('CasinoAggregatorService', false)) {
                static $liveTypesRepaired = false;
                if (!$liveTypesRepaired) {
                    $liveTypesRepaired = true;
                    try {
                        CasinoAggregatorService::repairGameTypesFromPayload($pdo);
                    } catch (Throwable) {
                    }
                }
            }
            $liveExtra = '';
            if ($gameType === 1 && class_exists('CasinoAggregatorService', false)) {
                $liveExtra = ' OR ' . CasinoAggregatorService::liveVendorSqlMatch('g.vendor_code');
            }
            $aggStmt = $pdo->prepare(
                "SELECT DISTINCT COALESCE(NULLIF(v.vendor_name, ''), v.vendor_code) AS provider_name
                 FROM casino_aggregator_vendors v
                 INNER JOIN casino_aggregator_games g ON g.vendor_code = v.vendor_code
                 WHERE v.is_active = 1 AND g.is_active = 1 AND (g.game_type = :type{$liveExtra})
                 ORDER BY provider_name ASC"
            );
            $aggStmt->execute([':type' => $aggType]);
            foreach ($aggStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!is_array($row) || empty($row['provider_name'])) {
                    continue;
                }
                $name = self::normalizeProviderLabel((string) $row['provider_name']);
                if (!isset($seen[$name])) {
                    $seen[$name] = true;
                    $providers[] = $name;
                }
            }
            $gsTypeExpr = "CASE
                WHEN UPPER(TRIM(g.game_type)) IN ('LIVE_CASINO','LIVE_CASINO_PREMIUM')
                  OR UPPER(TRIM(g.game_type)) LIKE 'LIVE\\_CASINO%'
                  OR UPPER(TRIM(g.game_type)) LIKE '%LIVE\\_CASINO%'
                THEN 2 ELSE 1 END";
            $gsWanted = $gameType === 1 ? 2 : 1;
            $gsStmt = $pdo->prepare(
                "SELECT DISTINCT COALESCE(NULLIF(p.provider, ''), NULLIF(p.product_name, ''), CAST(g.product_code AS CHAR)) AS provider_name
                 FROM gamingsoft_games g
                 LEFT JOIN gamingsoft_products p ON p.product_code = g.product_code
                 WHERE g.is_active = 1 AND ({$gsTypeExpr}) = :type
                 ORDER BY provider_name ASC"
            );
            $gsStmt->execute([':type' => $gsWanted]);
            foreach ($gsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!is_array($row) || empty($row['provider_name'])) {
                    continue;
                }
                $name = self::normalizeProviderLabel((string) $row['provider_name']);
                if (!isset($seen[$name])) {
                    $seen[$name] = true;
                    $providers[] = $name;
                }
            }
        } catch (Throwable) {
            return [];
        }
        $providers = array_values(array_unique(array_filter($providers)));
        sort($providers, SORT_NATURAL | SORT_FLAG_CASE);
        return $providers;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function normalizeGameImage(array $row): string
    {
        if (class_exists('CasinoAggregatorService', false)) {
            $resolved = CasinoAggregatorService::resolveGameImage($row);
            if ($resolved !== '') {
                return $resolved;
            }
        }

        $image = self::normalizeLocalizedValue($row['image_url'] ?? $row['thumbnail_url'] ?? $row['cover'] ?? '');
        if (class_exists('CasinoAggregatorService', false) && !CasinoAggregatorService::isUsableMediaUrl($image)) {
            return CasinoAggregatorService::extractMediaUrl($image);
        }

        return $image;
    }

    private static function normalizeGameName(mixed $value): string
    {
        return self::normalizeLocalizedValue($value);
    }

    private static function normalizeProviderLabel(mixed $value): string
    {
        return self::normalizeLocalizedValue($value);
    }

    private static function normalizeLocalizedValue(mixed $value): string
    {
        if (class_exists('CasinoAggregatorService', false)) {
            return CasinoAggregatorService::resolveLocalizedLabel($value);
        }
        return trim((string) $value);
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
