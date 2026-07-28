<?php

declare(strict_types=1);

require_once __DIR__ . '/BackendApiClient.php';

/**
 * Dedicated live-casino catalogue query (GSC+ GamingSoft + Casino Aggregator live).
 * Do not use SlotGamesQuery for /livecasino — this service owns that surface.
 *
 * On API-only frontends (FRONTEND_API_ONLY), falls back to BackendApiClient
 * the same way SlotGamesQuery does for slots.
 */
final class LiveCasinoQuery
{
    public const GAMES_PATH = 'games.php';
    public const PROVIDERS_PATH = 'games_provider.php';

    private const CACHE_TTL_SEC = 90;

    /** @return list<string> */
    public static function liveGameTypeSqlValues(): array
    {
        return ['LIVE_CASINO', 'LIVE_CASINO_PREMIUM'];
    }

    public static function isLiveGameType(string $gameType): bool
    {
        $t = strtoupper(trim($gameType));
        if ($t === '') {
            return false;
        }
        if (in_array($t, self::liveGameTypeSqlValues(), true)) {
            return true;
        }
        if (in_array($t, ['LC', 'LIVE', 'LIVE CASINO', 'LIVE-CASINO'], true)) {
            return true;
        }

        return str_starts_with($t, 'LIVE_CASINO')
            || str_contains($t, 'LIVE_CASINO')
            || str_contains($t, 'LIVE CASINO');
    }

    /**
     * SQL predicate: column holds a GSC+ live casino type.
     */
    public static function gamingSoftLiveSql(string $column = 'g.game_type'): string
    {
        return '('
            . "UPPER(TRIM({$column})) IN ('LIVE_CASINO','LIVE_CASINO_PREMIUM','LC','LIVE','LIVE CASINO','LIVE-CASINO')"
            . " OR UPPER(TRIM({$column})) LIKE 'LIVE\\_CASINO%'"
            . " OR UPPER(TRIM({$column})) LIKE '%LIVE\\_CASINO%'"
            . " OR UPPER(TRIM({$column})) LIKE '%LIVE CASINO%'"
            . ')';
    }

    /** SQL predicate: column holds a non-live GSC+ type (slot, sports, etc.). */
    public static function gamingSoftNonLiveSql(string $column = 'g.game_type'): string
    {
        return '('
            . "UPPER(TRIM({$column})) IN ('SLOT','SLOTS','SL','FISHING','FISH','SPORT_BOOK','SPORTSBOOK','POKER','LOTTERY','OTHER','VIRTUAL_SPORT','ESPORT','EGAME','ARCADE','TABLE','TABLE_GAMES')"
            . " OR UPPER(TRIM({$column})) LIKE 'SLOT%'"
            . ')';
    }

    private static function gamingSoftCatalogCurrencySql(): string
    {
        self::ensureDependencies();
        $currency = GamingSoftService::STAGING_CURRENCY;
        try {
            if (class_exists('GamingSoftService', false) && class_exists('AdminDatabase', false)) {
                $currency = GamingSoftService::catalogCurrency(AdminDatabase::pdo());
            }
        } catch (Throwable) {
        }

        return preg_replace('/[^A-Z0-9]/', '', strtoupper($currency)) ?: GamingSoftService::STAGING_CURRENCY;
    }

    /**
     * @param list<string> $providers
     * @return array{
     *   games: array<int, array<string, mixed>>,
     *   total: int,
     *   page: int,
     *   perPage: int,
     *   hasNext: bool,
     *   totalPages: int,
     *   apiError: bool,
     *   items?: array<int, array<string, mixed>>
     * }
     */
    public static function page(
        string $searchTerm = '',
        array $providers = [],
        int $limit = 30,
        int $page = 1,
        string $sort = '',
        array $extraQuery = []
    ): array {
        $limit = min(100, max(1, $limit));
        $page = max(1, $page);
        $searchTerm = trim($searchTerm);
        $cleanProviders = array_values(array_filter(array_map(
            static fn ($x): string => trim((string) $x),
            $providers
        ), static fn (string $x): bool => $x !== ''));

        $currency = strtoupper(trim((string) ($extraQuery['currency'] ?? '')));
        $source = strtolower(trim((string) ($extraQuery['source'] ?? '')));
        if ($source === 'live' || $source === 'livecasino' || $source === 'live_casino') {
            $source = '';
        }

        $forceLocal = !empty($extraQuery['force_local'])
            || (defined('METROPOL_ADMIN_PANEL') && METROPOL_ADMIN_PANEL);

        if (!$forceLocal && self::shouldUseRemoteApi()) {
            return self::pageViaApi($searchTerm, $cleanProviders, $limit, $page, $sort, $currency, $source);
        }

        $local = self::pageFromDatabase($searchTerm, $cleanProviders, $limit, $page, $sort, $currency, $source);
        if ($local !== null) {
            return $local;
        }

        if ($forceLocal) {
            return self::emptyResult($limit, $page, true);
        }

        return self::pageViaApi($searchTerm, $cleanProviders, $limit, $page, $sort, $currency, $source);
    }

    /** @return list<string> */
    public static function providers(array $extraQuery = []): array
    {
        $forceLocal = !empty($extraQuery['force_local'])
            || (defined('METROPOL_ADMIN_PANEL') && METROPOL_ADMIN_PANEL);

        if (!$forceLocal && self::shouldUseRemoteApi()) {
            return self::providersViaApi();
        }

        $local = self::providersFromDatabase();
        if ($local !== null) {
            return $local;
        }

        if ($forceLocal) {
            return [];
        }

        return self::providersViaApi();
    }

    public static function purgeCache(): void
    {
        // Reserved for future file/redis cache; no-op for now.
    }

    private static function shouldUseRemoteApi(): bool
    {
        return function_exists('frontend_database_allowed') && !frontend_database_allowed();
    }

    /**
     * @param list<string> $cleanProviders
     * @return array<string, mixed>|null
     */
    private static function pageFromDatabase(
        string $searchTerm,
        array $cleanProviders,
        int $limit,
        int $page,
        string $sort,
        string $currency,
        string $source
    ): ?array {
        $offset = ($page - 1) * $limit;

        try {
            $pdo = self::pdo();
            self::ensureDependencies();

            // IMPORTANT: Do NOT UNION branches that mix JSON columns with VARCHAR literals.
            // MySQL rejects that mix and the API used to swallow the error as an empty list.
            $selects = [];
            $hasGsGames = self::tableExists($pdo, 'gamingsoft_games');
            $hasGsProducts = self::tableExists($pdo, 'gamingsoft_products');
            $hasAggGames = self::tableExists($pdo, 'casino_aggregator_games');
            $hasAggVendors = self::tableExists($pdo, 'casino_aggregator_vendors');

            if ($source === '' || $source === 'gamingsoft' || $source === 'gsc' || $source === 'gsc+') {
                if ($hasGsGames) {
                    $selects[] = self::gamingSoftGamesSelectSql();
                }
                if ($hasGsProducts) {
                    $selects[] = self::gamingSoftLobbyProductsSelectSql();
                }
            }
            if (($source === '' || $source === 'aggregator') && $hasAggGames && $hasAggVendors) {
                $selects[] = self::aggregatorLiveSelectSql();
            }

            if ($selects === []) {
                return self::emptyResult($limit, $page);
            }

            $rows = [];
            $seenIds = [];
            foreach ($selects as $selectSql) {
                try {
                    $stmt = $pdo->query($selectSql);
                    foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $gid = trim((string) ($row['game_id'] ?? ''));
                        if ($gid === '' || isset($seenIds[$gid])) {
                            continue;
                        }
                        $seenIds[$gid] = true;
                        $rows[] = $row;
                    }
                } catch (Throwable $branchError) {
                    error_log('LiveCasinoQuery branch error: ' . $branchError->getMessage());
                }
            }

            $searchLower = mb_strtolower($searchTerm);
            $providerSet = [];
            foreach ($cleanProviders as $providerName) {
                $providerSet[mb_strtolower($providerName)] = true;
            }

            $filtered = [];
            foreach ($rows as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                $provider = trim((string) ($row['provider'] ?? ''));
                $gameId = trim((string) ($row['game_id'] ?? ''));
                $providerCode = trim((string) ($row['provider_code'] ?? ''));
                $supportCurrency = trim((string) ($row['support_currency'] ?? ''));
                $featured = !empty($row['is_featured']);

                if ($searchLower !== '') {
                    $hay = mb_strtolower($name . ' ' . $provider . ' ' . $gameId);
                    if (!str_contains($hay, $searchLower)) {
                        continue;
                    }
                }

                if ($providerSet !== []) {
                    $ok = isset($providerSet[mb_strtolower($provider)])
                        || isset($providerSet[mb_strtolower($providerCode)]);
                    if (!$ok) {
                        continue;
                    }
                }

                if ($currency !== '') {
                    if ($supportCurrency !== ''
                        && strcasecmp($supportCurrency, $currency) !== 0
                        && !str_contains(strtoupper($supportCurrency), $currency)
                    ) {
                        continue;
                    }
                }

                if ($sort === 'popular' && !$featured) {
                    continue;
                }

                $filtered[] = $row;
            }

            $catalogOrder = $searchTerm !== '' || $cleanProviders !== [];
            usort($filtered, static function (array $a, array $b) use ($catalogOrder): int {
                if (!$catalogOrder) {
                    $fa = !empty($a['is_featured']) ? 1 : 0;
                    $fb = !empty($b['is_featured']) ? 1 : 0;
                    if ($fa !== $fb) {
                        return $fb <=> $fa;
                    }
                }
                $cmp = strnatcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                if ($cmp !== 0) {
                    return $cmp;
                }

                return strcmp((string) ($a['game_id'] ?? ''), (string) ($b['game_id'] ?? ''));
            });

            $total = count($filtered);
            $slice = array_slice($filtered, $offset, $limit);

            $games = [];
            foreach ($slice as $row) {
                $mapped = self::mapRow($row);
                if ($mapped === null) {
                    continue;
                }
                $games[] = $mapped;
            }

            $totalPages = $total > 0 ? (int) ceil($total / $limit) : 0;

            return [
                'games' => $games,
                'items' => $games,
                'total' => $total,
                'page' => $page,
                'perPage' => $limit,
                'hasNext' => ($offset + $limit) < $total,
                'totalPages' => $totalPages,
                'apiError' => false,
            ];
        } catch (Throwable $e) {
            error_log('LiveCasinoQuery::pageFromDatabase error: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * @param list<string> $cleanProviders
     * @return array<string, mixed>
     */
    private static function pageViaApi(
        string $searchTerm,
        array $cleanProviders,
        int $limit,
        int $page,
        string $sort,
        string $currency,
        string $source
    ): array {
        $query = [
            'game_type' => 1,
            'page' => $page,
            'limit' => $limit,
            // Backend LiveCasinoQuery must hit DB, not recurse into another frontend.
            'force_local' => 1,
        ];
        if ($searchTerm !== '') {
            $query['search'] = $searchTerm;
        }
        if ($cleanProviders !== []) {
            $query['providers'] = $cleanProviders;
            $query['provider'] = $cleanProviders[0];
        }
        if ($sort !== '') {
            $query['sort'] = $sort;
        }
        if ($currency !== '') {
            $query['currency'] = $currency;
        }
        if (in_array($source, ['gamingsoft', 'gsc', 'gsc+', 'aggregator'], true)) {
            $query['source'] = $source;
        }

        $j = BackendApiClient::request('GET', BackendApiClient::SVC_GAMES, self::GAMES_PATH, $query, null, 8);
        if ($j === null) {
            return self::emptyResult($limit, $page, true);
        }

        $success = $j['success'] ?? null;
        if ($success !== true && $success !== 1 && $success !== '1' && $success !== 'true') {
            return self::emptyResult($limit, $page, true);
        }

        $data = isset($j['data']) && is_array($j['data']) ? $j['data'] : [];
        $gamesRaw = $data['games'] ?? $data['items'] ?? [];
        if (!is_array($gamesRaw)) {
            $gamesRaw = [];
        }

        $games = [];
        foreach ($gamesRaw as $row) {
            if (!is_array($row)) {
                continue;
            }
            // API already returns mapped rows; normalise key aliases for the slot template.
            $gameId = trim((string) ($row['game_id'] ?? $row['id'] ?? ''));
            $name = trim((string) ($row['game_name'] ?? $row['name'] ?? ''));
            if ($gameId === '' || $name === '') {
                continue;
            }
            $cover = trim((string) ($row['cover'] ?? $row['image_url'] ?? ''));
            $fallbacks = is_array($row['cover_fallbacks'] ?? null)
                ? $row['cover_fallbacks']
                : (is_array($row['image_fallbacks'] ?? null) ? $row['image_fallbacks'] : []);
            $games[] = [
                'id' => $gameId,
                'game_id' => $gameId,
                'game_name' => $name,
                'name' => $name,
                'cover' => $cover,
                'cover_fallbacks' => $fallbacks,
                'image_url' => $cover,
                'has_demo' => !empty($row['has_demo']),
                'provider_code' => (string) ($row['provider_code'] ?? ''),
                'provider' => (string) ($row['provider'] ?? ''),
                'source' => (string) ($row['source'] ?? 'gamingsoft'),
                'is_featured' => !empty($row['is_featured']) ? 1 : 0,
                'support_currency' => (string) ($row['support_currency'] ?? ''),
            ];
        }

        $pagination = isset($data['pagination']) && is_array($data['pagination']) ? $data['pagination'] : [];
        $total = max(0, (int) ($pagination['total'] ?? $data['total'] ?? count($games)));
        $perPage = max(1, (int) ($pagination['perPage'] ?? $pagination['limit'] ?? $data['perPage'] ?? $limit));
        $pageRet = max(1, (int) ($pagination['page'] ?? $data['page'] ?? $page));
        $hasNext = array_key_exists('hasNext', $pagination)
            ? !empty($pagination['hasNext'])
            : (($pageRet * $perPage) < $total);
        $totalPages = (int) ($pagination['totalPages'] ?? $data['total_pages'] ?? ($total > 0 ? (int) ceil($total / $perPage) : 0));

        return [
            'games' => $games,
            'items' => $games,
            'total' => $total,
            'page' => $pageRet,
            'perPage' => $perPage,
            'hasNext' => $hasNext,
            'totalPages' => $totalPages,
            'apiError' => false,
        ];
    }

    /** @return list<string>|null */
    private static function providersFromDatabase(): ?array
    {
        try {
            $pdo = self::pdo();
            self::ensureDependencies();
            $names = [];

            if (self::tableExists($pdo, 'gamingsoft_games')) {
                $gsLive = self::gamingSoftLiveSql('g.game_type');
                $prodLive = self::gamingSoftLiveSql('p.game_type');
                $gsNonLive = self::gamingSoftNonLiveSql('g.game_type');
                $gsCurrency = self::gamingSoftCatalogCurrencySql();
                $gsStmt = $pdo->query(
                    "SELECT DISTINCT COALESCE(NULLIF(p.provider, ''), NULLIF(p.product_name, ''), CAST(g.product_code AS CHAR)) AS provider_name
                     FROM gamingsoft_games g
                     INNER JOIN gamingsoft_products p
                        ON p.product_code = g.product_code
                       AND p.is_active = 1
                       AND UPPER(TRIM(p.currency)) = '{$gsCurrency}'
                     WHERE g.is_active = 1
                       AND ({$gsLive} OR {$prodLive})
                       AND NOT ({$gsNonLive} AND NOT {$prodLive})
                     ORDER BY provider_name ASC"
                );
                foreach (($gsStmt ? $gsStmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
                    $name = self::normalizeProviderLabel((string) ($row['provider_name'] ?? ''));
                    if ($name !== '') {
                        $names[$name] = $name;
                    }
                }
            }

            if (self::tableExists($pdo, 'gamingsoft_products')) {
                $prodLive = self::gamingSoftLiveSql('p.game_type');
                $gsCurrency = self::gamingSoftCatalogCurrencySql();
                $prodStmt = $pdo->query(
                    "SELECT DISTINCT COALESCE(NULLIF(p.provider, ''), NULLIF(p.product_name, ''), CAST(p.product_code AS CHAR)) AS provider_name
                     FROM gamingsoft_products p
                     WHERE p.is_active = 1
                       AND UPPER(TRIM(p.currency)) = '{$gsCurrency}'
                       AND {$prodLive}
                     ORDER BY provider_name ASC"
                );
                foreach (($prodStmt ? $prodStmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
                    $name = self::normalizeProviderLabel((string) ($row['provider_name'] ?? ''));
                    if ($name !== '') {
                        $names[$name] = $name;
                    }
                }
            }

            if (
                class_exists('CasinoAggregatorService', false)
                && self::tableExists($pdo, 'casino_aggregator_vendors')
                && self::tableExists($pdo, 'casino_aggregator_games')
            ) {
                $liveMatch = CasinoAggregatorService::liveVendorSqlMatch('g.vendor_code');
                $aggStmt = $pdo->query(
                    "SELECT DISTINCT COALESCE(NULLIF(v.vendor_name, ''), v.vendor_code) AS provider_name
                     FROM casino_aggregator_vendors v
                     INNER JOIN casino_aggregator_games g ON g.vendor_code = v.vendor_code
                     WHERE v.is_active = 1 AND g.is_active = 1
                       AND (g.game_type = 2 OR {$liveMatch})
                     ORDER BY provider_name ASC"
                );
                foreach (($aggStmt ? $aggStmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
                    $name = self::normalizeProviderLabel((string) ($row['provider_name'] ?? ''));
                    if ($name !== '') {
                        $names[$name] = $name;
                    }
                }
            }

            $out = array_values($names);
            sort($out, SORT_NATURAL | SORT_FLAG_CASE);

            return $out;
        } catch (Throwable $e) {
            error_log('LiveCasinoQuery::providersFromDatabase error: ' . $e->getMessage());

            return null;
        }
    }

    /** @return list<string> */
    private static function providersViaApi(): array
    {
        $j = BackendApiClient::request(
            'GET',
            BackendApiClient::SVC_GAMES,
            self::PROVIDERS_PATH,
            ['game_type' => 1, 'force_local' => 1],
            null,
            5
        );
        if ($j === null) {
            return [];
        }

        $u = BackendApiClient::unwrap($j);
        $raw = $u['providers'] ?? $u['items'] ?? $j['providers'] ?? [];
        if (!is_array($raw)) {
            $data = isset($j['data']) && is_array($j['data']) ? $j['data'] : [];
            $raw = $data['providers'] ?? $data['items'] ?? [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $providers = [];
        foreach ($raw as $row) {
            if (is_string($row) && $row !== '') {
                $providers[] = self::normalizeProviderLabel($row);
            } elseif (is_array($row)) {
                $name = trim((string) ($row['provider_name'] ?? $row['provider'] ?? $row['provider_code'] ?? ''));
                if ($name !== '') {
                    $providers[] = self::normalizeProviderLabel($name);
                }
            }
        }

        $providers = array_values(array_unique(array_filter($providers)));
        sort($providers, SORT_NATURAL | SORT_FLAG_CASE);

        return $providers;
    }

    private static function gamingSoftGamesSelectSql(): string
    {
        $liveGame = self::gamingSoftLiveSql('g.game_type');
        $liveProduct = self::gamingSoftLiveSql('p.game_type');
        $nonLiveGame = self::gamingSoftNonLiveSql('g.game_type');
        $gsCurrency = self::gamingSoftCatalogCurrencySql();

        // Live casino page: only GSC+ live products/tables — never slot catalogue rows.
        return "SELECT
                    CONCAT('gamingsoft:', g.product_code, ':', g.game_code) AS game_id,
                    g.game_name AS name,
                    COALESCE(NULLIF(p.provider, ''), NULLIF(p.product_name, ''), CAST(g.product_code AS CHAR)) AS provider,
                    CAST(g.product_code AS CHAR) AS provider_code,
                    COALESCE(NULLIF(g.image_url, ''), '') AS image_url,
                    COALESCE(g.support_currency, '') AS support_currency,
                    g.is_featured AS is_featured,
                    'gamingsoft' AS source,
                    CAST(g.id AS CHAR) AS row_id,
                    g.game_code AS gs_game_code,
                    '' AS raw_payload
                FROM gamingsoft_games g
                INNER JOIN gamingsoft_products p
                    ON p.product_code = g.product_code
                   AND p.is_active = 1
                   AND UPPER(TRIM(p.currency)) = '{$gsCurrency}'
                WHERE g.is_active = 1
                  AND g.game_code <> '__lobby__'
                  AND ({$liveGame} OR {$liveProduct})
                  AND NOT ({$nonLiveGame} AND NOT {$liveProduct})";
    }

    /**
     * Lobby tiles for live products that have no synced individual games yet.
     */
    private static function gamingSoftLobbyProductsSelectSql(): string
    {
        $live = self::gamingSoftLiveSql('p.game_type');
        $gsCurrency = self::gamingSoftCatalogCurrencySql();

        return "SELECT
                    CONCAT('gamingsoft:', p.product_code, ':__lobby__') AS game_id,
                    CONCAT(COALESCE(NULLIF(p.product_name, ''), NULLIF(p.provider, ''), CAST(p.product_code AS CHAR)), ' Lobby') AS name,
                    COALESCE(NULLIF(p.provider, ''), NULLIF(p.product_name, ''), CAST(p.product_code AS CHAR)) AS provider,
                    CAST(p.product_code AS CHAR) AS provider_code,
                    '' AS image_url,
                    COALESCE(p.currency, '') AS support_currency,
                    0 AS is_featured,
                    'gamingsoft' AS source,
                    CONCAT('product-', CAST(p.id AS CHAR)) AS row_id,
                    '__lobby__' AS gs_game_code,
                    '' AS raw_payload
                FROM gamingsoft_products p
                WHERE p.is_active = 1
                  AND UPPER(TRIM(p.currency)) = '{$gsCurrency}'
                  AND {$live}
                  AND NOT EXISTS (
                      SELECT 1 FROM gamingsoft_games g
                      WHERE g.product_code = p.product_code
                        AND g.is_active = 1
                        AND g.game_code <> '__lobby__'
                        AND (" . self::gamingSoftLiveSql('g.game_type') . ")
                  )
                  AND NOT EXISTS (
                      SELECT 1 FROM gamingsoft_games g2
                      WHERE g2.product_code = p.product_code
                        AND g2.game_code = '__lobby__'
                        AND g2.is_active = 1
                  )";
    }

    private static function aggregatorLiveSelectSql(): string
    {
        $liveMatch = class_exists('CasinoAggregatorService', false)
            ? CasinoAggregatorService::liveVendorSqlMatch('g.vendor_code')
            : '0';
        $vendorLiveMatch = class_exists('CasinoAggregatorService', false)
            ? CasinoAggregatorService::liveVendorSqlMatch('v.vendor_code')
            : '0';

        return "SELECT
                    CONCAT('aggregator:', g.vendor_code, ':', g.game_code) AS game_id,
                    g.game_name AS name,
                    COALESCE(NULLIF(v.vendor_name, ''), g.vendor_code) AS provider,
                    g.vendor_code AS provider_code,
                    COALESCE(NULLIF(g.image_url, ''), '') AS image_url,
                    '' AS support_currency,
                    g.is_featured AS is_featured,
                    'aggregator' AS source,
                    CAST(g.id AS CHAR) AS row_id,
                    '' AS raw_payload
                FROM casino_aggregator_games g
                INNER JOIN casino_aggregator_vendors v ON v.vendor_code = g.vendor_code
                WHERE g.is_active = 1 AND v.is_active = 1
                  AND (
                      g.game_type = 2
                      OR COALESCE(v.game_type, 1) = 2
                      OR {$liveMatch}
                      OR {$vendorLiveMatch}
                  )";
    }

    /** @param array<string, mixed> $row */
    private static function mapRow(array $row): ?array
    {
        $gameId = trim((string) ($row['game_id'] ?? ''));
        $name = trim((string) ($row['name'] ?? ''));
        if ($gameId === '' || $name === '') {
            return null;
        }
        if (preg_match('/(?:^|[^a-z0-9])acceptance[\s:_-]*test(?:$|[^a-z0-9])/i', strtolower($name)) === 1) {
            return null;
        }

        $provider = self::normalizeProviderLabel((string) ($row['provider'] ?? ''));
        $imageUrl = trim((string) ($row['image_url'] ?? ''));
        $fallbacks = [];

        if (class_exists('CasinoAggregatorService', false)) {
            $media = CasinoAggregatorService::hydrateGameMedia([
                'image_url' => $imageUrl,
                'image_fallbacks' => $row['image_fallbacks'] ?? null,
                'raw_payload' => $row['raw_payload'] ?? null,
            ]);
            $imageUrl = (string) ($media['cover'] ?? $imageUrl);
            $fallbacks = is_array($media['cover_fallbacks'] ?? null) ? $media['cover_fallbacks'] : [];
        }

        $parsedGs = class_exists('GamingSoftService', false) ? GamingSoftService::parseGameId($gameId) : null;
        $productCode = is_array($parsedGs) ? (int) ($parsedGs['product_code'] ?? 0) : 0;
        $providerGameCode = trim((string) ($row['gs_game_code'] ?? ''));
        if ($providerGameCode === '' && is_array($parsedGs)) {
            $providerGameCode = (string) ($parsedGs['game_code'] ?? '');
        }

        return [
            'id' => $gameId,
            'game_id' => $gameId,
            'product_code' => $productCode > 0 ? (string) $productCode : (string) ($row['provider_code'] ?? ''),
            'game_code' => $providerGameCode,
            'game_name' => $name,
            'name' => $name,
            'cover' => $imageUrl,
            'cover_fallbacks' => $fallbacks,
            'image_url' => $imageUrl,
            'has_demo' => false,
            'provider_code' => (string) ($row['provider_code'] ?? ''),
            'provider' => $provider,
            'source' => (string) ($row['source'] ?? 'gamingsoft'),
            'is_featured' => !empty($row['is_featured']) ? 1 : 0,
            'support_currency' => (string) ($row['support_currency'] ?? ''),
        ];
    }

    private static function normalizeProviderLabel(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (class_exists('CasinoAggregatorService', false)) {
            $resolved = CasinoAggregatorService::resolveLocalizedLabel($value);
            if ($resolved !== '') {
                return $resolved;
            }
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private static function emptyResult(int $limit, int $page, bool $apiError = false): array
    {
        return [
            'games' => [],
            'items' => [],
            'total' => 0,
            'page' => $page,
            'perPage' => $limit,
            'hasNext' => false,
            'totalPages' => 0,
            'apiError' => $apiError,
        ];
    }

    private static function ensureDependencies(): void
    {
        if (!class_exists('CasinoAggregatorService', false)) {
            $path = dirname(__DIR__) . '/services/CasinoAggregatorService.php';
            if (!is_file($path)) {
                $path = __DIR__ . '/CasinoAggregatorService.php';
            }
            if (is_file($path)) {
                require_once $path;
            }
        }
        if (!class_exists('GamingSoftService', false)) {
            $path = dirname(__DIR__) . '/services/GamingSoftService.php';
            if (!is_file($path)) {
                $path = __DIR__ . '/GamingSoftService.php';
            }
            if (is_file($path)) {
                require_once $path;
            }
        }
        if (class_exists('GamingSoftService', false) && class_exists('AdminDatabase', false)) {
            try {
                GamingSoftService::bootstrap(AdminDatabase::pdo());
            } catch (Throwable) {
            }
        }
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
        if ($table === '') {
            return false;
        }
        try {
            $pdo->query('SELECT 1 FROM `' . $table . '` LIMIT 0');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private static function pdo(): PDO
    {
        if (class_exists('AdminDatabase', false)) {
            return AdminDatabase::pdo();
        }
        $adminApp = defined('ADMIN_APP_PATH')
            ? ADMIN_APP_PATH
            : dirname(__DIR__) . '/admin/app';
        if (is_file($adminApp . '/Core/AdminDatabase.php')) {
            require_once $adminApp . '/Core/AdminDatabase.php';
            if (class_exists('AdminDatabase', false)) {
                return AdminDatabase::pdo();
            }
        }
        throw new RuntimeException('AdminDatabase bulunamadı.');
    }
}
