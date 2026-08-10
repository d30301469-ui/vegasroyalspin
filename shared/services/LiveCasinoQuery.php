<?php

declare(strict_types=1);

require_once __DIR__ . '/BackendApiClient.php';

/**
 * Canlı Casino catalogue — GSC+ IDR staging products (live casino + slots).
 */
final class LiveCasinoQuery
{
    public const GAMES_PATH = 'games.php';
    public const PROVIDERS_PATH = 'games_provider.php';

    /** @return list<string> */
    public static function liveGameTypeSqlValues(): array
    {
        // Live casino lobby is GSC+ live tables only. Slot titles stay on /slot (aggregator).
        return ['LIVE_CASINO', 'LIVE_CASINO_PREMIUM'];
    }

    /**
     * Title needles per live-casino category tab (CM622 /live-casino/home chips).
     *
     * Category tabs are resolved from the game title. Tabs may overlap (an
     * "XXXtreme Lightning Roulette" is both a roulette and a show table); that
     * is preferred over dropping a table from every tab.
     */
    private const CATEGORY_NEEDLES = [
        'roulette' => ['roulette', 'roulete', 'ruleta', 'roleta', 'rulet'],
        'blackjack' => ['blackjack', 'black jack', 'blackjak'],
        'baccarat' => ['baccarat', 'baccara', 'bacarat', 'bakara', 'dragon tiger', 'bac bo'],
        'poker' => [
            'poker', 'hold\'em', 'holdem', 'texas hold', 'casino hold', 'three card',
            'caribbean stud', 'ultimate texas', 'casino stud',
        ],
        'game-show' => [
            'game show', 'crazy time', 'funky time', 'lightning storm', 'lightning dice',
            'monopoly', 'dream catcher', 'mega wheel', 'mega ball', 'deal or no deal',
            'football studio', 'crazy coin flip', 'candyland', 'cash or crash',
            'gonzo', 'wonderland', 'balloon race', 'imperial quest', 'side bet city',
            'spin a win', 'wheel of', 'boom city', 'treasure hunt', 'lucky 6',
            'crazy balls', 'powerball', 'extra chic', 'ice fishing',
        ],
        'asian' => [
            'sic bo', 'sicbo', 'fan tan', 'fantan', 'teen patti', 'andar', 'bahar',
            'asia', 'asian', 'chinese', 'thai', 'vietnam', 'hong kong', 'shanghai',
            'dragon tiger', 'super sic', 'nihtan', 'sedie',
        ],
        'turkish' => [
            'turkish', 'turkce', 'turkiye', 'istanbul',
            'turkish roulette', 'turkish blackjack', 'turkish baccarat',
        ],
        'farsi' => [
            'farsi', 'persian', 'iran', 'tehran', 'farsi roulette', 'farsi blackjack',
        ],
        'indian' => [
            'indian', 'hindi', 'india', 'mumbai', 'delhi',
            'indian roulette', 'indian blackjack', 'teen patti',
        ],
        'brazilian' => [
            'brazil', 'brazilian', 'brasil', 'portugu', 'sao paulo',
            'brazilian roulette', 'brazilian blackjack',
        ],
        'dutch' => [
            'dutch', 'neder', 'holland', 'amsterdam', 'dutch roulette', 'dutch blackjack',
        ],
        'arabic' => [
            'arabic', 'arab', 'dubai', 'saudi', 'arabic roulette', 'arabic blackjack',
        ],
    ];

    /**
     * SQL predicate for a live-casino category tab, or '' when the tab is not a
     * category (empty tab, `popular`, unknown values) and must not filter.
     */
    public static function liveCategorySqlMatch(string $category, string $nameColumn = 'name'): string
    {
        $needles = self::CATEGORY_NEEDLES[strtolower(trim($category))] ?? null;
        if ($needles === null) {
            return '';
        }

        // Identifiers only (never user input). Quote needles for SQL string literals
        // (e.g. hold'em → hold''em) — do not inject raw apostrophes into LIKE.
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $nameColumn)) {
            return '';
        }

        $matches = [];
        foreach ($needles as $needle) {
            $needle = strtolower(trim((string) $needle));
            if ($needle === '') {
                continue;
            }
            $escaped = str_replace("'", "''", $needle);
            $matches[] = 'LOWER(' . $nameColumn . ") LIKE '%" . $escaped . "%'";
        }

        return $matches === [] ? '' : '(' . implode(' OR ', $matches) . ')';
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
        if ($t === 'SLOT' || $t === 'SLOTS') {
            return true;
        }

        return in_array($t, ['LC', 'LIVE', 'LIVE CASINO', 'LIVE-CASINO'], true)
            || str_starts_with($t, 'LIVE_CASINO')
            || str_contains($t, 'LIVE_CASINO')
            || str_contains($t, 'LIVE CASINO');
    }

    /** SQL IN-list for lobby game_type filter (quoted). */
    private static function lobbyGameTypeSqlIn(): string
    {
        $parts = [];
        foreach (self::liveGameTypeSqlValues() as $type) {
            $parts[] = "'" . str_replace("'", "''", strtoupper(trim($type))) . "'";
        }

        return $parts === [] ? "'LIVE_CASINO','LIVE_CASINO_PREMIUM','SLOT'" : implode(',', $parts);
    }

    /**
     * @param list<string> $providers
     * @return array{games: array<int, array<string, mixed>>, items: array<int, array<string, mixed>>, total: int, page: int, perPage: int, hasNext: bool, totalPages: int, apiError: bool}
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
        $providerList = array_values(array_filter(array_map(
            static fn ($x): string => trim((string) $x),
            $providers
        ), static fn (string $x): bool => $x !== ''));

        $source = strtolower(trim((string) ($extraQuery['source'] ?? '')));
        if ($source === 'live' || $source === 'livecasino' || $source === 'live_casino') {
            $source = '';
        }

        $forceLocal = !empty($extraQuery['force_local'])
            || (defined('APP_ADMIN_PANEL') && APP_ADMIN_PANEL);

        if (!$forceLocal && self::shouldUseRemoteApi()) {
            return self::pageViaApi($searchTerm, $providerList, $limit, $page, $sort, $source, $extraQuery);
        }

        $local = self::pageFromDatabase($searchTerm, $providerList, $limit, $page, $sort, $extraQuery);
        if ($local !== null) {
            return $local;
        }

        if ($forceLocal) {
            return self::emptyResult($limit, $page, true);
        }

        return self::pageViaApi($searchTerm, $providerList, $limit, $page, $sort, $source, $extraQuery);
    }

    /** @return list<string> */
    public static function providers(array $extraQuery = []): array
    {
        $forceLocal = !empty($extraQuery['force_local'])
            || (defined('APP_ADMIN_PANEL') && APP_ADMIN_PANEL);

        if (!$forceLocal && self::shouldUseRemoteApi()) {
            $viaApi = self::providersViaApi($extraQuery);
            if ($viaApi !== []) {
                return $viaApi;
            }
            // games.php often works when games_provider.php times out — harvest names.
            return self::providersViaGamesHarvest($extraQuery);
        }

        $local = self::providersFromDatabase($extraQuery);
        if ($local !== null && $local !== []) {
            return $local;
        }

        if ($forceLocal) {
            return $local ?? [];
        }

        $viaApi = self::providersViaApi($extraQuery);

        return $viaApi !== [] ? $viaApi : self::providersViaGamesHarvest($extraQuery);
    }

    public static function purgeCache(): void
    {
        // no-op
    }

    private static function shouldUseRemoteApi(): bool
    {
        return function_exists('frontend_database_allowed') && !frontend_database_allowed();
    }

    /**
     * BackendApiClient::request() returns the decoded member envelope as-is and
     * never adds an "ok" flag, so success has to be read from the envelope the
     * same way BackendConnectivityProbe does.
     *
     * @param array<string, mixed>|null $json
     */
    private static function envelopeOk(?array $json): bool
    {
        if (!is_array($json)) {
            return false;
        }

        return !empty($json['success']) || (int) ($json['code'] ?? 0) === 200;
    }

    /**
     * Branch queries share one parameter bag, so only the placeholders a branch
     * actually contains may be bound. The lookahead keeps :search from matching
     * :search2.
     */
    private static function sqlUsesParam(string $sql, string $param): bool
    {
        return (bool) preg_match('/' . preg_quote($param, '/') . '(?![0-9A-Za-z_])/', $sql);
    }

    /**
     * @param list<string> $providerList
     * @param array<string, mixed> $extraQuery
     * @return array<string, mixed>|null
     */
    private static function pageFromDatabase(
        string $searchTerm,
        array $providerList,
        int $limit,
        int $page,
        string $sort,
        array $extraQuery = []
    ): ?array {
        try {
            self::ensureDependencies();
            $pdo = self::pdo();

            $hasGsc = self::tableExists($pdo, 'gsc_games');
            if (!$hasGsc) {
                return self::emptyResult($limit, $page);
            }

            $offset = ($page - 1) * $limit;
            $branches = [];
            $params = [];
            $currencyPrefer = strtoupper(trim((string) ($extraQuery['currency'] ?? '')));
            if ($currencyPrefer === '' || $currencyPrefer === 'ALL' || $currencyPrefer === '*') {
                // Prefer the operator wallet currency so lobby tiles match launchable rows.
                if (class_exists('GscPlusService', false)) {
                    try {
                        $currencyPrefer = strtoupper(GscPlusService::configCurrency(GscPlusService::config($pdo)));
                    } catch (Throwable) {
                        $currencyPrefer = '';
                    }
                }
            }
            $lobbyCurrencies = class_exists('GscPlusService', false)
                ? GscPlusService::stagingLobbyCurrencyFilter($currencyPrefer !== '' ? $currencyPrefer : null)
                : ['TRY', 'IDR', 'IDR2', 'CNY', 'VND', 'VND2'];
            $liveProductCodes = class_exists('GscPlusService', false)
                ? GscPlusService::stagingLiveProductCodes()
                : [];

            $hasGscGameType = self::columnExists($pdo, 'gsc_games', 'game_type');
            $hasProductCurrency = self::columnExists($pdo, 'gsc_games', 'product_currency');
            $gameTypeInSql = self::lobbyGameTypeSqlIn();
            $buildGscWhere = static function (
                bool $restrictProducts,
                bool $restrictCurrency
            ) use (
                $hasGscGameType,
                $hasProductCurrency,
                $gameTypeInSql,
                $liveProductCodes,
                $lobbyCurrencies,
                &$params
            ): array {
                $gscWhere = [
                    'g.is_active = 1',
                    "(CASE WHEN COALESCE(g.entry_type, 1) = 2 THEN g.game_code = '_lobby' ELSE g.game_code <> '_lobby' END)",
                ];
                if ($hasGscGameType) {
                    $gscWhere[] = 'UPPER(g.game_type) IN (' . $gameTypeInSql . ')';
                }
                if ($restrictProducts && $liveProductCodes !== []) {
                    $codePlaceholders = [];
                    foreach ($liveProductCodes as $idx => $code) {
                        $ck = ':gsc_live_pc_' . $idx;
                        $codePlaceholders[] = $ck;
                        $params[$ck] = (int) $code;
                    }
                    $gscWhere[] = 'g.product_code IN (' . implode(',', $codePlaceholders) . ')';
                }
                if ($restrictCurrency && $hasProductCurrency && $lobbyCurrencies !== []) {
                    $curPlaceholders = [];
                    foreach ($lobbyCurrencies as $idx => $cur) {
                        $ck = ':gsc_live_cur_' . $idx;
                        $curPlaceholders[] = $ck;
                        $params[$ck] = $cur;
                    }
                    $gscWhere[] = 'UPPER(TRIM(g.product_currency)) IN (' . implode(',', $curPlaceholders) . ')';
                }

                return $gscWhere;
            };

            // Prefer staging IDR contract filters; fall back to all active GSC live rows
            // when the filtered catalogue is empty (common before a full sync).
            $gscWhere = $buildGscWhere(true, true);
            $probeSql = 'SELECT COUNT(*) FROM gsc_games g WHERE ' . implode(' AND ', $gscWhere);
            try {
                $probe = $pdo->prepare($probeSql);
                foreach ($params as $k => $v) {
                    if (self::sqlUsesParam($probeSql, $k)) {
                        $probe->bindValue($k, $v);
                    }
                }
                $probe->execute();
                if ((int) $probe->fetchColumn() === 0) {
                    $params = [];
                    $gscWhere = $buildGscWhere(false, false);
                }
            } catch (Throwable) {
                $params = [];
                $gscWhere = $buildGscWhere(false, false);
            }
            if ($searchTerm !== '') {
                $gscWhere[] = '(g.game_name LIKE :gsc_search OR g.provider LIKE :gsc_search2 OR g.game_code LIKE :gsc_search3)';
                $params[':gsc_search'] = '%' . $searchTerm . '%';
                $params[':gsc_search2'] = '%' . $searchTerm . '%';
                $params[':gsc_search3'] = '%' . $searchTerm . '%';
            }
            if ($providerList !== []) {
                $gscProviderClauses = [];
                $matchIdx = 0;
                foreach ($providerList as $provider) {
                    $provider = trim((string) $provider);
                    if ($provider === '') {
                        continue;
                    }
                    // Expand URL slugs (SA-Gaming) into exact DB label variants
                    // without collation-sensitive REPLACE() expressions.
                    $variants = [$provider];
                    if (str_contains($provider, '-')) {
                        $variants[] = str_replace('-', ' ', $provider);
                    }
                    if (str_contains($provider, ' ')) {
                        $variants[] = str_replace(' ', '-', $provider);
                    }
                    $compact = preg_replace('/[\s\-_]+/', '', $provider);
                    if (is_string($compact) && $compact !== '' && $compact !== $provider) {
                        $variants[] = $compact;
                    }
                    if (class_exists('CasinoAggregatorService', false)) {
                        $key = CasinoAggregatorService::providerMatchKey($provider);
                        // Prefer canonical spaced label when known from this request batch.
                        foreach ($providerList as $other) {
                            if (CasinoAggregatorService::providerMatchKey((string) $other) === $key) {
                                $variants[] = (string) $other;
                                $variants[] = str_replace('-', ' ', (string) $other);
                            }
                        }
                    }
                    $variants = array_values(array_unique(array_filter(array_map(
                        static fn ($v): string => trim((string) $v),
                        $variants
                    ), static fn (string $v): bool => $v !== '')));

                    foreach ($variants as $variant) {
                        $pk = ':gsc_provider_' . $matchIdx;
                        $nk = ':gsc_provider_name_' . $matchIdx;
                        $ck = ':gsc_provider_code_' . $matchIdx;
                        // Force one collation — gsc_games strings are often utf8mb4_bin while
                        // the session/connection uses unicode_ci (Illegal mix on '=').
                        $gscProviderClauses[] = '(g.provider COLLATE utf8mb4_unicode_ci = '
                            . $pk . ' OR CAST(g.product_code AS CHAR) COLLATE utf8mb4_unicode_ci = '
                            . $ck . ' OR g.product_name COLLATE utf8mb4_unicode_ci = ' . $nk . ')';
                        $params[$pk] = $variant;
                        $params[$nk] = $variant;
                        $params[$ck] = $variant;
                        $matchIdx++;
                    }
                }
                if ($gscProviderClauses !== []) {
                    $gscWhere[] = '(' . implode(' OR ', $gscProviderClauses) . ')';
                }
            }
            $gscWhereSql = ' WHERE ' . implode(' AND ', $gscWhere);
            $currencySelect = self::columnExists($pdo, 'gsc_games', 'product_currency')
                ? 'COALESCE(NULLIF(g.product_currency, \'\'), \'\')'
                : 'CAST(\'\' AS CHAR)';
            $langIconSelect = self::columnExists($pdo, 'gsc_games', 'lang_icon')
                ? 'COALESCE(NULLIF(g.lang_icon, \'\'), \'\')'
                : 'CAST(\'\' AS CHAR)';
            $rawPayloadSelect = self::columnExists($pdo, 'gsc_games', 'raw_payload')
                ? 'COALESCE(NULLIF(g.raw_payload, \'\'), \'\')'
                : 'CAST(\'\' AS CHAR)';
            $branches['gsc'] = "SELECT
                CONCAT('gsc:', g.product_code, ':', g.game_code) AS game_id,
                g.game_name AS name,
                COALESCE(NULLIF(g.provider, ''), NULLIF(g.product_name, ''), CAST(g.product_code AS CHAR)) AS provider,
                CAST(g.product_code AS CHAR) AS provider_code,
                COALESCE(NULLIF(g.image_url, ''), '') AS image_url,
                {$langIconSelect} AS lang_icon,
                {$rawPayloadSelect} AS raw_payload,
                g.is_featured AS is_featured,
                {$currencySelect} AS product_currency,
                'gsc' AS source
             FROM gsc_games g
             {$gscWhereSql}";

            if ($branches === []) {
                return self::emptyResult($limit, $page);
            }

            // Conditions that apply to every source are evaluated on the wrapped
            // branch, where each catalogue already exposes the same column names.
            $outer = [
                // Acceptance Test tables are provider diagnostics, not playable
                // lobby entries (the slot lobby drops them the same way).
                "LOWER(name) NOT LIKE '%acceptance%test%'",
                "LOWER(game_id) NOT LIKE '%acceptance%test%'",
                // Product "Lobby" shells are navigation hubs, not playable tables.
                "LOWER(name) NOT LIKE '%lobby%'",
            ];
            $categorySql = self::liveCategorySqlMatch($sort);
            if ($categorySql !== '') {
                $outer[] = $categorySql;
            }
            $outerSql = ' WHERE ' . implode(' AND ', $outer);

            $countOne = static function (string $sql, string $where) use ($pdo, $params): int {
                $stmt = $pdo->prepare("SELECT COUNT(DISTINCT game_id) FROM ({$sql}) AS catalog{$where}");
                foreach ($params as $k => $v) {
                    if (self::sqlUsesParam($sql, $k)) {
                        $stmt->bindValue($k, $v);
                    }
                }
                $stmt->execute();

                return (int) $stmt->fetchColumn();
            };

            // "Popüler"/"En Beğenilen" narrows to featured tables, but only when an
            // operator actually flagged some: is_featured defaults to 0 on every
            // synced row, so an unconditional filter would empty the tab.
            $featuredWhere = '';
            if (in_array(strtolower(trim($sort)), ['popular', 'liked', 'featured'], true)) {
                $featuredCount = 0;
                foreach ($branches as $sql) {
                    try {
                        $featuredCount += $countOne($sql, $outerSql . ' AND is_featured = 1');
                    } catch (Throwable) {
                    }
                }
                if ($featuredCount > 0) {
                    $featuredWhere = ' AND is_featured = 1';
                }
            }
            $outerSql .= $featuredWhere;

            // Each source is queried on its own instead of through one UNION: a
            // single broken branch (missing column, collation mismatch between the
            // two tables) used to make the whole lobby report zero games.
            $total = 0;
            $rows = [];
            $failed = 0;
            // Enough rows per branch to order the merged result correctly up to
            // the requested page. Because every branch is ordered by the same key,
            // the merged prefix of length $cap is the true global prefix.
            $cap = $offset + $limit;

            foreach ($branches as $label => $sql) {
                try {
                    // COUNT(DISTINCT game_id) mirrors the de-duplication below, so
                    // the reported total matches what the lobby can actually page
                    // through. It must stay independent of the fetched window: it
                    // is what tells the client another page exists.
                    $total += $countOne($sql, $outerSql);

                    $rowsStmt = $pdo->prepare(
                        "SELECT * FROM ({$sql}) AS catalog{$outerSql}
                         ORDER BY is_featured DESC, name ASC
                         LIMIT :cap"
                    );
                    foreach ($params as $k => $v) {
                        if (self::sqlUsesParam($sql, $k)) {
                            $rowsStmt->bindValue($k, $v);
                        }
                    }
                    $rowsStmt->bindValue(':cap', max(1, $cap), PDO::PARAM_INT);
                    $rowsStmt->execute();
                    foreach ($rowsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $rows[] = is_array($row) ? $row : [];
                    }
                } catch (Throwable $e) {
                    $failed++;
                    error_log("[LiveCasino] {$label} branch failed: " . $e->getMessage());
                }
            }

            if ($failed === count($branches)) {
                return null;
            }

            usort($rows, static function (array $a, array $b): int {
                $featured = (int) ($b['is_featured'] ?? 0) <=> (int) ($a['is_featured'] ?? 0);
                if ($featured !== 0) {
                    return $featured;
                }

                // Prefer Pragmatic Live in the lobby ordering (product 1006), then
                // other contracted live providers alphabetically by name.
                $providerRank = static function (array $row): int {
                    $code = (int) ($row['provider_code'] ?? $row['product_code'] ?? 0);
                    if ($code === 0) {
                        $gid = strtolower(trim((string) ($row['game_id'] ?? '')));
                        if (preg_match('/^gsc:(\d+)/', $gid, $m)) {
                            $code = (int) $m[1];
                        }
                    }
                    return match ($code) {
                        1006 => 0, // Pragmatic Play
                        1185 => 1, // SA Gaming
                        1220 => 2, // Astar
                        default => 3,
                    };
                };
                $byProvider = $providerRank($a) <=> $providerRank($b);
                if ($byProvider !== 0) {
                    return $byProvider;
                }

                return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            });

            // Provider sync can leave duplicate rows for the same logical live game
            // (same product/game/type/currency). Keep one to avoid unstable launches.
            $seen = [];
            $dedupedRows = [];
            foreach ($rows as $row) {
                $key = strtolower(trim((string) ($row['game_id'] ?? '')))
                    . '|' . strtoupper(trim((string) ($row['game_type'] ?? '')))
                    . '|' . strtoupper(trim((string) ($row['product_currency'] ?? '')))
                    . '|' . strtolower(trim((string) ($row['source'] ?? '')));
                if ($key === '|||') {
                    $dedupedRows[] = $row;
                    continue;
                }
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $dedupedRows[] = $row;
            }
            $rows = $dedupedRows;
            // $total deliberately stays the counted catalogue size. Overwriting it
            // with count($rows) capped the lobby at the fetched window, so with a
            // single contributing source hasNext went false on page 1 and the live
            // catalogue stopped at the first page.
            $total = max($total, count($rows));

            $games = [];
            foreach (array_slice($rows, $offset, $limit) as $row) {
                $mapped = self::mapRow($row);
                if ($mapped !== null) {
                    $games[] = $mapped;
                }
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
            // A swallowed failure here is indistinguishable from an empty catalogue,
            // which hid a total live-lobby outage; leave a trace.
            error_log('[LiveCasino] page query failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @param list<string> $providerList
     * @param array<string, mixed> $extraQuery
     * @return array<string, mixed>
     */
    private static function pageViaApi(
        string $searchTerm,
        array $providerList,
        int $limit,
        int $page,
        string $sort,
        string $source,
        array $extraQuery = []
    ): array {
        $query = [
            'game_type' => 1,
            'page' => $page,
            'limit' => $limit,
            'force_local' => 1,
            'source' => 'gsc',
            'gsc_only' => 1,
        ];
        if ($searchTerm !== '') {
            $query['search'] = $searchTerm;
        }
        if ($providerList !== []) {
            $query['providers'] = $providerList;
            $query['provider'] = $providerList[0];
        }
        if ($sort !== '') {
            $query['sort'] = $sort;
        }
        $currency = strtoupper(trim((string) ($extraQuery['currency'] ?? '')));
        if ($currency !== '') {
            $query['currency'] = $currency;
        }

        $j = BackendApiClient::request('GET', BackendApiClient::SVC_GAMES, self::GAMES_PATH, $query, null, 8);
        if (!self::envelopeOk($j)) {
            return self::emptyResult($limit, $page, true);
        }

        $u = BackendApiClient::unwrap($j);
        if (!is_array($u)) {
            return self::emptyResult($limit, $page, true);
        }
        $items = is_array($u['games'] ?? null) ? $u['games'] : (is_array($u['items'] ?? null) ? $u['items'] : []);
        $games = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $games[] = $item;
        }
        $total = (int) ($u['total'] ?? count($games));
        $perPage = (int) ($u['perPage'] ?? $u['limit'] ?? $limit);
        $pageNo = (int) ($u['page'] ?? $page);
        $totalPages = (int) ($u['totalPages'] ?? ($total > 0 ? (int) ceil($total / max(1, $perPage)) : 0));

        return [
            'games' => $games,
            'items' => $games,
            'total' => $total,
            'page' => $pageNo,
            'perPage' => $perPage,
            'hasNext' => !empty($u['hasNext']) || ($pageNo * $perPage) < $total,
            'totalPages' => $totalPages,
            'apiError' => false,
        ];
    }

    /**
     * @param array<string, mixed> $extraQuery
     * @return list<string>|null
     */
    private static function providersFromDatabase(array $extraQuery = []): ?array
    {
        try {
            self::ensureDependencies();
            $pdo = self::pdo();
            $providers = [];
            if (!self::tableExists($pdo, 'gsc_games')) {
                return [];
            }

            $liveProductCodes = class_exists('GscPlusService', false)
                ? GscPlusService::stagingLiveProductCodes()
                : [];
            $currencyPrefer = strtoupper(trim((string) ($extraQuery['currency'] ?? '')));
            if ($currencyPrefer === '' || $currencyPrefer === 'ALL' || $currencyPrefer === '*') {
                if (class_exists('GscPlusService', false)) {
                    try {
                        $currencyPrefer = strtoupper(GscPlusService::configCurrency(GscPlusService::config($pdo)));
                    } catch (Throwable) {
                        $currencyPrefer = '';
                    }
                }
            }
            $lobbyCurrencies = class_exists('GscPlusService', false)
                ? GscPlusService::stagingLobbyCurrencyFilter($currencyPrefer !== '' ? $currencyPrefer : null)
                : ['TRY', 'IDR', 'IDR2', 'CNY', 'VND', 'VND2'];
            $where = [
                'g.is_active = 1',
                "(CASE WHEN COALESCE(g.entry_type, 1) = 2 THEN g.game_code = '_lobby' ELSE g.game_code <> '_lobby' END)",
                'UPPER(g.game_type) IN (' . self::lobbyGameTypeSqlIn() . ')',
                "UPPER(TRIM(COALESCE(NULLIF(g.status, ''), 'ACTIVATED'))) IN ('ACTIVATED','ACTIVAT')",
            ];
            $params = [];
            if ($liveProductCodes !== []) {
                $ph = [];
                foreach ($liveProductCodes as $idx => $code) {
                    $ck = ':pc_' . $idx;
                    $ph[] = $ck;
                    $params[$ck] = (int) $code;
                }
                $where[] = 'g.product_code IN (' . implode(',', $ph) . ')';
            }
            if (self::columnExists($pdo, 'gsc_games', 'product_currency') && $lobbyCurrencies !== []) {
                $ph = [];
                foreach ($lobbyCurrencies as $idx => $cur) {
                    $ck = ':cur_' . $idx;
                    $ph[] = $ck;
                    $params[$ck] = $cur;
                }
                $where[] = 'UPPER(TRIM(g.product_currency)) IN (' . implode(',', $ph) . ')';
            }
            $sql = 'SELECT DISTINCT COALESCE(NULLIF(g.provider, \'\'), NULLIF(g.product_name, \'\'), CAST(g.product_code AS CHAR)) AS provider_name
                 FROM gsc_games g
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY provider_name ASC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $name = trim((string) ($row['provider_name'] ?? ''));
                if ($name !== '') {
                    $providers[] = $name;
                }
            }

            $providers = array_values(array_unique(array_filter($providers)));
            sort($providers, SORT_NATURAL | SORT_FLAG_CASE);
            return $providers;
        } catch (Throwable $e) {
            error_log('[LiveCasino] provider query failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @param array<string, mixed> $extraQuery
     * @return list<string>
     */
    private static function providersViaApi(array $extraQuery = []): array
    {
        // Live-casino providers come from GSC+ IDR lobby rows.
        $query = ['source' => 'gsc', 'game_type' => 1, 'gsc_only' => 1];
        $currency = strtoupper(trim((string) ($extraQuery['currency'] ?? '')));
        if ($currency !== '') {
            $query['currency'] = $currency;
        }
        foreach ([self::PROVIDERS_PATH, 'games-provider', 'live-casino/providers'] as $path) {
            $j = BackendApiClient::request('GET', BackendApiClient::SVC_GAMES, $path, $query, null, 12);
            if (!self::envelopeOk($j)) {
                continue;
            }
            $u = BackendApiClient::unwrap($j);
            if (!is_array($u)) {
                continue;
            }
            $items = is_array($u['providers'] ?? null) ? $u['providers'] : (is_array($u['items'] ?? null) ? $u['items'] : []);
            $providers = [];
            foreach ($items as $item) {
                $name = is_array($item)
                    ? trim((string) ($item['provider_name'] ?? $item['name'] ?? $item['provider_code'] ?? ''))
                    : trim((string) $item);
                if ($name !== '') {
                    $providers[] = $name;
                }
            }
            $providers = array_values(array_unique($providers));
            if ($providers !== []) {
                sort($providers, SORT_NATURAL | SORT_FLAG_CASE);

                return $providers;
            }
        }

        return [];
    }

    /**
     * Distinct provider labels from the games catalogue (frontend API-only hosts).
     *
     * @param array<string, mixed> $extraQuery
     * @return list<string>
     */
    private static function providersViaGamesHarvest(array $extraQuery = []): array
    {
        $seen = [];
        $out = [];
        for ($page = 1; $page <= 15; $page++) {
            $result = self::pageViaApi('', [], 100, $page, '', 'gsc', $extraQuery);
            $games = is_array($result['games'] ?? null) ? $result['games'] : [];
            if ($games === []) {
                break;
            }
            foreach ($games as $game) {
                if (!is_array($game)) {
                    continue;
                }
                $name = trim((string) ($game['provider'] ?? $game['provider_code'] ?? ''));
                if ($name === '' || isset($seen[$name])) {
                    continue;
                }
                $seen[$name] = true;
                $out[] = $name;
            }
            if (empty($result['hasNext'])) {
                break;
            }
        }
        sort($out, SORT_NATURAL | SORT_FLAG_CASE);

        return $out;
    }

    /** @param array<string, mixed> $row */
    private static function mapRow(array $row): ?array
    {
        $gameId = trim((string) ($row['game_id'] ?? ''));
        $name = trim((string) ($row['name'] ?? ''));
        if ($gameId === '' || $name === '') {
            return null;
        }

        $provider = trim((string) ($row['provider'] ?? ''));
        $source = strtolower(trim((string) ($row['source'] ?? '')));
        if ($source !== 'gsc') {
            $source = 'gsc';
        }
        $imageUrl = trim((string) ($row['image_url'] ?? ''));
        $fallbacks = [];
        if (class_exists('GscPlusService', false) && method_exists('GscPlusService', 'hydrateGameMedia')) {
            $media = GscPlusService::hydrateGameMedia($row);
            $imageUrl = trim((string) ($media['cover'] ?? $imageUrl));
            $fallbacks = is_array($media['cover_fallbacks'] ?? null) ? $media['cover_fallbacks'] : [];
        }

        return [
            'id' => $gameId,
            'game_id' => $gameId,
            'game_type' => 1,
            'game_name' => $name,
            'name' => $name,
            'cover' => $imageUrl,
            'cover_fallbacks' => $fallbacks,
            'image_fallbacks' => $fallbacks,
            'image_url' => $imageUrl,
            'has_demo' => false,
            'provider_code' => (string) ($row['provider_code'] ?? ''),
            'provider' => $provider,
            'source' => $source,
            'is_featured' => !empty($row['is_featured']) ? 1 : 0,
            'support_currency' => strtoupper(trim((string) ($row['product_currency'] ?? $row['support_currency'] ?? ''))),
            'product_currency' => strtoupper(trim((string) ($row['product_currency'] ?? ''))),
        ];
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
        foreach ([
            'GscPlusService' => 'GscPlusService.php',
        ] as $class => $file) {
            if (class_exists($class, false)) {
                continue;
            }
            foreach ([
                __DIR__ . '/' . $file,
                dirname(__DIR__) . '/services/' . $file,
                dirname(__DIR__, 2) . '/services/' . $file,
            ] as $path) {
                if (is_file($path)) {
                    require_once $path;
                    break;
                }
            }
        }
    }

    private static function pdo(): PDO
    {
        if (class_exists('AdminDatabase', false)) {
            return AdminDatabase::pdo();
        }
        if (class_exists('Database', false)) {
            return Database::pdo();
        }
        throw new RuntimeException('Database unavailable');
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        static $cache = [];
        $key = strtolower($table);
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        try {
            // SHOW does not accept placeholders on every MySQL build ("error near '?'"),
            // and the swallowed failure made every table look missing, which emptied
            // the lobby. INFORMATION_SCHEMA is a plain SELECT and always binds.
            $stmt = $pdo->prepare(
                'SELECT 1
                 FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
                 LIMIT 1'
            );
            $stmt->execute([':t' => $table]);
            $cache[$key] = (bool) $stmt->fetchColumn();
            return $cache[$key];
        } catch (Throwable $e) {
            error_log("[LiveCasino] table probe failed for {$table}: " . $e->getMessage());
            $cache[$key] = false;
            return false;
        }
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];
        $key = strtolower($table . '.' . $column);
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT 1
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
                 LIMIT 1'
            );
            $stmt->execute([':t' => $table, ':c' => $column]);
            $cache[$key] = (bool) $stmt->fetchColumn();
            return $cache[$key];
        } catch (Throwable $e) {
            error_log("[LiveCasino] column probe failed for {$table}.{$column}: " . $e->getMessage());
            $cache[$key] = false;
            return false;
        }
    }
}
