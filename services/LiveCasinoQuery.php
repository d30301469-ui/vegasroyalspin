<?php

declare(strict_types=1);

require_once __DIR__ . '/BackendApiClient.php';

/**
 * Live casino catalogue query — Drakon live games only (`drakon_games`).
 */
final class LiveCasinoQuery
{
    public const GAMES_PATH = 'games.php';
    public const PROVIDERS_PATH = 'games_provider.php';

    /** @return list<string> */
    public static function liveGameTypeSqlValues(): array
    {
        return ['LIVE_CASINO', 'LIVE_CASINO_PREMIUM'];
    }

    /**
     * Title needles per live-casino category tab (`?sort=roulette|blackjack|
     * baccarat|game-show`, see views/pages/slot.php).
     *
     * Drakon's /games/all feed carries no table-type field, so the tab has to
     * be resolved from the game title. Tabs may overlap (an "XXXtreme Lightning
     * Roulette" is both a roulette and a show table); that is preferred over
     * dropping a table from every tab.
     */
    private const CATEGORY_NEEDLES = [
        'roulette' => ['roulette', 'roulete', 'ruleta', 'roleta', 'rulet'],
        'blackjack' => ['blackjack', 'black jack', 'blackjak'],
        'baccarat' => ['baccarat', 'baccara', 'bacarat', 'bakara', 'dragon tiger', 'bac bo'],
        'game-show' => [
            'game show', 'crazy time', 'funky time', 'lightning storm', 'lightning dice',
            'monopoly', 'dream catcher', 'mega wheel', 'mega ball', 'deal or no deal',
            'football studio', 'crazy coin flip', 'candyland', 'cash or crash',
            'gonzo', 'wonderland', 'balloon race', 'imperial quest', 'side bet city',
            'spin a win', 'wheel of', 'boom city', 'treasure hunt', 'lucky 6',
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

        $matches = [];
        foreach ($needles as $needle) {
            $matches[] = 'LOWER(' . $nameColumn . ") LIKE '%" . $needle . "%'";
        }

        return '(' . implode(' OR ', $matches) . ')';
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

        return in_array($t, ['LC', 'LIVE', 'LIVE CASINO', 'LIVE-CASINO'], true)
            || str_starts_with($t, 'LIVE_CASINO')
            || str_contains($t, 'LIVE_CASINO')
            || str_contains($t, 'LIVE CASINO');
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
            || (defined('METROPOL_ADMIN_PANEL') && METROPOL_ADMIN_PANEL);

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
            || (defined('METROPOL_ADMIN_PANEL') && METROPOL_ADMIN_PANEL);

        if (!$forceLocal && self::shouldUseRemoteApi()) {
            return self::providersViaApi($extraQuery);
        }

        $local = self::providersFromDatabase($extraQuery);
        if ($local !== null) {
            return $local;
        }

        if ($forceLocal) {
            return [];
        }

        return self::providersViaApi($extraQuery);
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

            // Live casino is Drakon-only — casino_aggregator_games is never listed here.
            if (!self::tableExists($pdo, 'drakon_games')) {
                return self::emptyResult($limit, $page);
            }

            $offset = ($page - 1) * $limit;
            $branches = [];
            $params = [];

            $drakonWhere = [
                'g.is_active = 1',
                self::drakonLiveSql('g'),
            ];

            if ($searchTerm !== '') {
                $drakonWhere[] = '(g.game_name LIKE :drakon_search OR g.provider_name LIKE :drakon_search2 OR g.game_code LIKE :drakon_search3)';
                $params[':drakon_search'] = '%' . $searchTerm . '%';
                $params[':drakon_search2'] = '%' . $searchTerm . '%';
                $params[':drakon_search3'] = '%' . $searchTerm . '%';
            }

            if ($providerList !== []) {
                $providerClauses = [];
                foreach ($providerList as $idx => $provider) {
                    $pk = ':drakon_provider_' . $idx;
                    $ck = ':drakon_provider_code_' . $idx;
                    $providerClauses[] = "(g.provider_name = {$pk} OR g.provider_code = {$ck})";
                    $params[$pk] = $provider;
                    $params[$ck] = $provider;
                }
                $drakonWhere[] = '(' . implode(' OR ', $providerClauses) . ')';
            }

            $drakonWhereSql = ' WHERE ' . implode(' AND ', $drakonWhere);
            $branches['drakon'] = "SELECT
                CONCAT('drakon:', g.game_id) AS game_id,
                g.game_name AS name,
                COALESCE(NULLIF(g.provider_name, ''), NULLIF(g.provider_code, ''), 'Drakon') AS provider,
                COALESCE(NULLIF(g.provider_code, ''), NULLIF(g.provider_name, ''), 'drakon') AS provider_code,
                COALESCE(NULLIF(g.image_url, ''), NULLIF(g.banner, ''), '') AS image_url,
                CAST('' AS CHAR) AS image_fallbacks,
                g.is_featured AS is_featured,
                CAST('' AS CHAR) AS product_currency,
                'drakon' AS source
             FROM drakon_games g
             {$drakonWhereSql}";

            // Conditions that apply to every source are evaluated on the wrapped
            // branch, where each catalogue already exposes the same column names.
            $outer = [
                // Acceptance Test tables are provider diagnostics, not playable
                // lobby entries (the slot lobby drops them the same way).
                "LOWER(name) NOT LIKE '%acceptance%test%'",
                "LOWER(game_id) NOT LIKE '%acceptance%test%'",
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
            'source' => 'drakon',
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

            if (self::tableExists($pdo, 'drakon_games')) {
                $stmt = $pdo->query(
                    "SELECT DISTINCT COALESCE(NULLIF(provider_name, ''), provider_code) AS provider_name
                     FROM drakon_games
                     WHERE is_active = 1
                       AND " . self::drakonLiveSql() . "
                       AND COALESCE(NULLIF(provider_name, ''), provider_code) <> ''
                     ORDER BY provider_name ASC"
                );
                foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
                    $name = trim((string) ($row['provider_name'] ?? ''));
                    if ($name !== '') {
                        $providers[] = $name;
                    }
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
        // Live-casino providers come from Drakon live rows only.
        $query = ['source' => 'drakon', 'game_type' => 1];
        $currency = strtoupper(trim((string) ($extraQuery['currency'] ?? '')));
        if ($currency !== '') {
            $query['currency'] = $currency;
        }
        $j = BackendApiClient::request('GET', BackendApiClient::SVC_GAMES, self::PROVIDERS_PATH, $query, null, 8);
        if (!self::envelopeOk($j)) {
            return [];
        }
        $u = BackendApiClient::unwrap($j);
        if (!is_array($u)) {
            return [];
        }
        $items = is_array($u['providers'] ?? null) ? $u['providers'] : (is_array($u['items'] ?? null) ? $u['items'] : []);
        $providers = [];
        foreach ($items as $item) {
            // The route answers with {provider_code, provider_name} objects; older
            // callers returned plain names.
            $name = is_array($item)
                ? trim((string) ($item['provider_name'] ?? $item['name'] ?? $item['provider_code'] ?? ''))
                : trim((string) $item);
            if ($name !== '') {
                $providers[] = $name;
            }
        }
        $providers = array_values(array_unique($providers));
        sort($providers, SORT_NATURAL | SORT_FLAG_CASE);
        return $providers;
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
        $source = 'drakon';
        $imageUrl = trim((string) ($row['image_url'] ?? ''));
        $fallbacks = [];

        return [
            'id' => $gameId,
            'game_id' => $gameId,
            'game_type' => 1,
            'game_name' => $name,
            'name' => $name,
            'cover' => $imageUrl,
            'cover_fallbacks' => $fallbacks,
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
        if (class_exists('DrakonService', false)) {
            return;
        }
        foreach ([
            __DIR__ . '/DrakonService.php',
            dirname(__DIR__) . '/services/DrakonService.php',
            dirname(__DIR__, 2) . '/services/DrakonService.php',
        ] as $path) {
            if (is_file($path)) {
                require_once $path;
                break;
            }
        }
    }

    /**
     * Live predicate for drakon_games. DrakonService classifies from the provider
     * label, which is the only category signal Drakon's feed carries; the literal
     * fallback covers hosts where the service file is absent.
     */
    private static function drakonLiveSql(string $tableAlias = ''): string
    {
        if (class_exists('DrakonService', false)) {
            return DrakonService::liveGameSqlMatch($tableAlias);
        }
        $p = $tableAlias !== '' ? rtrim($tableAlias, '.') . '.' : '';

        return "(LOWER(COALESCE({$p}provider_name, '')) LIKE '%live%'"
            . " OR LOWER(COALESCE({$p}type, '')) = 'live')";
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
