<?php

declare(strict_types=1);

require_once __DIR__ . '/BackendApiClient.php';

/**
 * Live casino catalogue query (Casino Aggregator only).
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
            return self::pageViaApi($searchTerm, $providerList, $limit, $page, $sort, $source);
        }

        $local = self::pageFromDatabase($searchTerm, $providerList, $limit, $page, $sort);
        if ($local !== null) {
            return $local;
        }

        if ($forceLocal) {
            return self::emptyResult($limit, $page, true);
        }

        return self::pageViaApi($searchTerm, $providerList, $limit, $page, $sort, $source);
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
     * @param list<string> $providerList
     * @return array<string, mixed>|null
     */
    private static function pageFromDatabase(
        string $searchTerm,
        array $providerList,
        int $limit,
        int $page,
        string $sort
    ): ?array {
        try {
            self::ensureDependencies();
            $pdo = self::pdo();
            if (!self::tableExists($pdo, 'casino_aggregator_games') || !self::tableExists($pdo, 'casino_aggregator_vendors')) {
                return self::emptyResult($limit, $page);
            }

            $offset = ($page - 1) * $limit;
            $params = [];
            $where = ["g.is_active = 1", "v.is_active = 1"];
            $liveMatch = class_exists('CasinoAggregatorService', false)
                ? CasinoAggregatorService::liveVendorSqlMatch('g.vendor_code')
                : '0';
            $where[] = "(g.game_type = 2 OR {$liveMatch})";

            if ($searchTerm !== '') {
                $where[] = '(g.game_name LIKE :search OR v.vendor_name LIKE :search2 OR g.game_code LIKE :search3)';
                $params[':search'] = '%' . $searchTerm . '%';
                $params[':search2'] = '%' . $searchTerm . '%';
                $params[':search3'] = '%' . $searchTerm . '%';
            }

            if ($providerList !== []) {
                $providerClauses = [];
                foreach ($providerList as $idx => $provider) {
                    $pk = ':provider_' . $idx;
                    $ck = ':provider_code_' . $idx;
                    $providerClauses[] = "(v.vendor_name = {$pk} OR g.vendor_code = {$ck})";
                    $params[$pk] = $provider;
                    $params[$ck] = $provider;
                }
                $where[] = '(' . implode(' OR ', $providerClauses) . ')';
            }

            $whereSql = ' WHERE ' . implode(' AND ', $where);
            $countStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM casino_aggregator_games g
                 INNER JOIN casino_aggregator_vendors v ON v.vendor_code = g.vendor_code
                 {$whereSql}"
            );
            foreach ($params as $k => $v) {
                $countStmt->bindValue($k, $v);
            }
            $countStmt->execute();
            $total = (int) $countStmt->fetchColumn();

            $order = strtolower(trim($sort)) === 'popular' ? 'g.is_featured DESC, g.game_name ASC' : 'g.is_featured DESC, g.game_name ASC';
            $rowsStmt = $pdo->prepare(
                "SELECT
                    CONCAT('aggregator:', g.vendor_code, ':', g.game_code) AS game_id,
                    g.game_name AS name,
                    COALESCE(NULLIF(v.vendor_name, ''), g.vendor_code) AS provider,
                    g.vendor_code AS provider_code,
                    COALESCE(NULLIF(g.image_url, ''), '') AS image_url,
                    CAST('' AS CHAR) AS image_fallbacks,
                    g.is_featured AS is_featured,
                    'aggregator' AS source
                 FROM casino_aggregator_games g
                 INNER JOIN casino_aggregator_vendors v ON v.vendor_code = g.vendor_code
                 {$whereSql}
                 ORDER BY {$order}
                 LIMIT :limit OFFSET :offset"
            );
            foreach ($params as $k => $v) {
                $rowsStmt->bindValue($k, $v);
            }
            $rowsStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $rowsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $rowsStmt->execute();
            $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);

            $games = [];
            foreach ($rows as $row) {
                $mapped = self::mapRow(is_array($row) ? $row : []);
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
     * @return array<string, mixed>
     */
    private static function pageViaApi(
        string $searchTerm,
        array $providerList,
        int $limit,
        int $page,
        string $sort,
        string $source
    ): array {
        $query = [
            'game_type' => 1,
            'page' => $page,
            'limit' => $limit,
            'force_local' => 1,
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
        if ($source === 'aggregator') {
            $query['source'] = 'aggregator';
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

    /** @return list<string>|null */
    private static function providersFromDatabase(): ?array
    {
        try {
            self::ensureDependencies();
            $pdo = self::pdo();
            if (!self::tableExists($pdo, 'casino_aggregator_vendors') || !self::tableExists($pdo, 'casino_aggregator_games')) {
                return [];
            }
            $liveMatch = class_exists('CasinoAggregatorService', false)
                ? CasinoAggregatorService::liveVendorSqlMatch('g.vendor_code')
                : '0';
            $stmt = $pdo->query(
                "SELECT DISTINCT COALESCE(NULLIF(v.vendor_name, ''), g.vendor_code) AS provider_name
                 FROM casino_aggregator_games g
                 INNER JOIN casino_aggregator_vendors v ON v.vendor_code = g.vendor_code
                 WHERE g.is_active = 1 AND v.is_active = 1
                   AND (g.game_type = 2 OR {$liveMatch})
                 ORDER BY provider_name ASC"
            );
            $providers = [];
            foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
                $name = trim((string) ($row['provider_name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $providers[] = class_exists('CasinoAggregatorService', false)
                    ? CasinoAggregatorService::resolveLocalizedLabel($name)
                    : $name;
            }
            $providers = array_values(array_unique(array_filter($providers)));
            sort($providers, SORT_NATURAL | SORT_FLAG_CASE);
            return $providers;
        } catch (Throwable $e) {
            error_log('[LiveCasino] provider query failed: ' . $e->getMessage());
            return null;
        }
    }

    /** @return list<string> */
    private static function providersViaApi(): array
    {
        $j = BackendApiClient::request('GET', BackendApiClient::SVC_GAMES, self::PROVIDERS_PATH, ['source' => 'livecasino'], null, 8);
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
        if (class_exists('CasinoAggregatorService', false)) {
            $provider = CasinoAggregatorService::resolveLocalizedLabel($provider);
        }
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

        return [
            'id' => $gameId,
            'game_id' => $gameId,
            'game_name' => $name,
            'name' => $name,
            'cover' => $imageUrl,
            'cover_fallbacks' => $fallbacks,
            'image_url' => $imageUrl,
            'has_demo' => false,
            'provider_code' => (string) ($row['provider_code'] ?? ''),
            'provider' => $provider,
            'source' => (string) ($row['source'] ?? 'aggregator'),
            'is_featured' => !empty($row['is_featured']) ? 1 : 0,
            'support_currency' => '',
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
        if (!class_exists('CasinoAggregatorService', false)) {
            $path = dirname(__DIR__) . '/services/CasinoAggregatorService.php';
            if (is_file($path)) {
                require_once $path;
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
}
