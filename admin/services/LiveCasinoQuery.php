<?php

declare(strict_types=1);

/**
 * Dedicated live-casino catalogue query (GSC+ GamingSoft + Casino Aggregator live).
 * Do not use SlotGamesQuery for /livecasino — this service owns that surface.
 */
final class LiveCasinoQuery
{
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

        return str_starts_with($t, 'LIVE_CASINO') || str_contains($t, 'LIVE_CASINO');
    }

    /**
     * SQL predicate: column holds a GSC+ live casino type.
     */
    public static function gamingSoftLiveSql(string $column = 'g.game_type'): string
    {
        return '('
            . "UPPER(TRIM({$column})) IN ('LIVE_CASINO','LIVE_CASINO_PREMIUM')"
            . " OR UPPER(TRIM({$column})) LIKE 'LIVE\\_CASINO%'"
            . " OR UPPER(TRIM({$column})) LIKE '%LIVE\\_CASINO%'"
            . ')';
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
        $offset = ($page - 1) * $limit;
        $searchTerm = trim($searchTerm);
        $cleanProviders = array_values(array_filter(array_map(
            static fn ($x): string => trim((string) $x),
            $providers
        ), static fn (string $x): bool => $x !== ''));

        $currency = strtoupper(trim((string) ($extraQuery['currency'] ?? '')));
        $source = strtolower(trim((string) ($extraQuery['source'] ?? '')));
        if ($source === 'live' || $source === 'livecasino') {
            $source = '';
        }

        try {
            $pdo = self::pdo();
            self::ensureDependencies();

            $union = [];
            if ($source === '' || $source === 'gamingsoft' || $source === 'gsc' || $source === 'gsc+') {
                $union[] = self::gamingSoftGamesSelectSql();
                $union[] = self::gamingSoftLobbyProductsSelectSql();
            }
            if ($source === '' || $source === 'aggregator') {
                $union[] = self::aggregatorLiveSelectSql();
            }

            if ($union === []) {
                return self::emptyResult($limit, $page);
            }

            $catalogSql = '(' . implode(' UNION ALL ', $union) . ') AS catalog';
            $where = ['1=1'];
            $params = [];

            if ($searchTerm !== '') {
                $where[] = '(name LIKE :search OR provider LIKE :search2 OR game_id LIKE :search3)';
                $params[':search'] = '%' . $searchTerm . '%';
                $params[':search2'] = '%' . $searchTerm . '%';
                $params[':search3'] = '%' . $searchTerm . '%';
            }

            if ($cleanProviders !== []) {
                $namePh = [];
                $codePh = [];
                foreach ($cleanProviders as $i => $providerName) {
                    $nk = ':pn' . $i;
                    $ck = ':pc' . $i;
                    $namePh[] = $nk;
                    $codePh[] = $ck;
                    $params[$nk] = $providerName;
                    $params[$ck] = $providerName;
                }
                $where[] = '(provider IN (' . implode(',', $namePh) . ') OR provider_code IN (' . implode(',', $codePh) . '))';
            }

            if ($currency !== '') {
                $where[] = '(support_currency = \'\' OR support_currency IS NULL OR support_currency = :cur OR support_currency LIKE :curlike)';
                $params[':cur'] = $currency;
                $params[':curlike'] = '%' . $currency . '%';
            }

            if ($sort === 'popular') {
                $where[] = 'is_featured = 1';
            }

            $whereSql = ' WHERE ' . implode(' AND ', $where);
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $catalogSql . $whereSql);
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $orderSql = $searchTerm !== '' || $cleanProviders !== []
                ? ' ORDER BY name ASC'
                : ' ORDER BY is_featured DESC, name ASC';

            $listSql = 'SELECT * FROM ' . $catalogSql . $whereSql . $orderSql . ' LIMIT :limit OFFSET :offset';
            $listStmt = $pdo->prepare($listSql);
            foreach ($params as $key => $value) {
                $listStmt->bindValue($key, $value);
            }
            $listStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $listStmt->execute();
            $rows = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $games = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $mapped = self::mapRow($row);
                if ($mapped === null) {
                    continue;
                }
                $games[] = $mapped;
            }

            $totalPages = $total > 0 ? (int) ceil($total / $limit) : 0;

            return [
                'games'      => $games,
                'items'      => $games,
                'total'      => $total,
                'page'       => $page,
                'perPage'    => $limit,
                'hasNext'    => ($offset + $limit) < $total,
                'totalPages' => $totalPages,
                'apiError'   => false,
            ];
        } catch (Throwable $e) {
            error_log('LiveCasinoQuery::page error: ' . $e->getMessage());

            return self::emptyResult($limit, $page, true);
        }
    }

    /** @return list<string> */
    public static function providers(): array
    {
        try {
            $pdo = self::pdo();
            self::ensureDependencies();
            $names = [];

            $gsLive = self::gamingSoftLiveSql('g.game_type');
            $gsStmt = $pdo->query(
                "SELECT DISTINCT COALESCE(NULLIF(p.provider, ''), NULLIF(p.product_name, ''), CAST(g.product_code AS CHAR)) AS provider_name
                 FROM gamingsoft_games g
                 LEFT JOIN gamingsoft_products p ON p.product_code = g.product_code
                 WHERE g.is_active = 1 AND {$gsLive}
                 ORDER BY provider_name ASC"
            );
            foreach (($gsStmt ? $gsStmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
                $name = self::normalizeProviderLabel((string) ($row['provider_name'] ?? ''));
                if ($name !== '') {
                    $names[$name] = $name;
                }
            }

            $prodLive = self::gamingSoftLiveSql('p.game_type');
            $prodStmt = $pdo->query(
                "SELECT DISTINCT COALESCE(NULLIF(p.provider, ''), NULLIF(p.product_name, ''), CAST(p.product_code AS CHAR)) AS provider_name
                 FROM gamingsoft_products p
                 WHERE p.is_active = 1 AND {$prodLive}
                 ORDER BY provider_name ASC"
            );
            foreach (($prodStmt ? $prodStmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
                $name = self::normalizeProviderLabel((string) ($row['provider_name'] ?? ''));
                if ($name !== '') {
                    $names[$name] = $name;
                }
            }

            if (class_exists('CasinoAggregatorService', false)) {
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
            error_log('LiveCasinoQuery::providers error: ' . $e->getMessage());

            return [];
        }
    }

    public static function purgeCache(): void
    {
        // Reserved for future file/redis cache; no-op for now.
    }

    private static function gamingSoftGamesSelectSql(): string
    {
        $live = self::gamingSoftLiveSql('g.game_type');

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
                    COALESCE(g.raw_payload, '') AS raw_payload
                FROM gamingsoft_games g
                LEFT JOIN gamingsoft_products p ON p.product_code = g.product_code
                WHERE g.is_active = 1 AND {$live}";
    }

    /**
     * Lobby tiles for live products that have no synced individual games yet.
     */
    private static function gamingSoftLobbyProductsSelectSql(): string
    {
        $live = self::gamingSoftLiveSql('p.game_type');

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
                    '' AS raw_payload
                FROM gamingsoft_products p
                WHERE p.is_active = 1
                  AND {$live}
                  AND NOT EXISTS (
                      SELECT 1 FROM gamingsoft_games g
                      WHERE g.product_code = p.product_code
                        AND g.is_active = 1
                        AND g.game_code <> '__lobby__'
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
                    COALESCE(g.raw_payload, '') AS raw_payload
                FROM casino_aggregator_games g
                INNER JOIN casino_aggregator_vendors v ON v.vendor_code = g.vendor_code
                WHERE g.is_active = 1 AND v.is_active = 1
                  AND (g.game_type = 2 OR {$liveMatch})";
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

        return [
            'id'              => $gameId,
            'game_id'         => $gameId,
            'game_name'       => $name,
            'name'            => $name,
            'cover'           => $imageUrl,
            'cover_fallbacks' => $fallbacks,
            'image_url'       => $imageUrl,
            'has_demo'        => false,
            'provider_code'   => (string) ($row['provider_code'] ?? ''),
            'provider'        => $provider,
            'source'          => (string) ($row['source'] ?? 'gamingsoft'),
            'is_featured'     => !empty($row['is_featured']) ? 1 : 0,
            'support_currency'=> (string) ($row['support_currency'] ?? ''),
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
            'games'      => [],
            'items'      => [],
            'total'      => 0,
            'page'       => $page,
            'perPage'    => $limit,
            'hasNext'    => false,
            'totalPages' => 0,
            'apiError'   => $apiError,
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

    private static function pdo(): PDO
    {
        if (class_exists('AdminDatabase', false)) {
            return AdminDatabase::pdo();
        }
        if (defined('ADMIN_APP_PATH') && is_file(ADMIN_APP_PATH . '/Core/AdminDatabase.php')) {
            require_once ADMIN_APP_PATH . '/Core/AdminDatabase.php';
            if (class_exists('AdminDatabase', false)) {
                return AdminDatabase::pdo();
            }
        }
        throw new RuntimeException('AdminDatabase bulunamadı.');
    }
}
