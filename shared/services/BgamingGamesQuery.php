<?php

declare(strict_types=1);

/**
 * BGaming catalogue — direct SoftSwiss integration, dedicated /bgaming page.
 * Independent from SlotGamesQuery (Casino Aggregator).
 */
final class BgamingGamesQuery
{
    public const GAMES_PATH = 'games.php';

    /**
     * @return array{
     *   games: array<int, array<string, mixed>>,
     *   total: int,
     *   page: int,
     *   perPage: int,
     *   hasNext: bool,
     *   totalPages: int,
     *   apiError: bool,
     *   pagination: array<string, mixed>
     * }
     */
    public static function page(
        string $searchTerm = '',
        int $limit = 30,
        int $page = 1,
        string $sort = '',
        array $extraQuery = []
    ): array {
        $limit = min(100, max(1, $limit));
        $page = max(1, $page);
        $sort = strtolower(trim($sort)) === 'all' ? '' : $sort;
        $forceLocal = !empty($extraQuery['force_local'])
            || (defined('APP_ADMIN_PANEL') && APP_ADMIN_PANEL);

        // Public frontend is API-only in production — same pattern as LiveCasinoQuery.
        if (!$forceLocal && self::shouldUseRemoteApi()) {
            return self::pageViaApi($searchTerm, $limit, $page, $sort);
        }

        $pdo = self::pdo();
        if ($pdo !== null) {
            try {
                return self::catalogPage($pdo, $searchTerm, $limit, $page, $sort);
            } catch (Throwable) {
                // fall through
            }
        }

        if ($forceLocal) {
            $empty = self::emptyResult($limit, $page);
            $empty['apiError'] = true;
            return $empty;
        }

        return self::pageViaApi($searchTerm, $limit, $page, $sort);
    }

    /**
     * @return list<array{provider_code: string, provider_name: string}>
     */
    public static function providers(): array
    {
        $pdo = self::pdo();
        if ($pdo === null) {
            return [];
        }

        $cols = self::catalogColumns($pdo);
        $providerCol = $cols['providerCol'];
        if ($providerCol === '') {
            return [['provider_code' => 'bgaming', 'provider_name' => 'BGaming']];
        }

        try {
            $stmt = $pdo->query(
                "SELECT DISTINCT {$providerCol} AS provider_code, {$providerCol} AS provider_name
                 FROM bgaming_games
                 WHERE is_active = 1 AND {$providerCol} <> ''
                 ORDER BY provider_name ASC"
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            $out = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $code = trim((string) ($row['provider_code'] ?? ''));
                if ($code === '') {
                    continue;
                }
                $out[] = [
                    'provider_code' => $code,
                    'provider_name' => $code,
                ];
            }

            return $out !== [] ? $out : [['provider_code' => 'bgaming', 'provider_name' => 'BGaming']];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Shape used by /api/v2/games envelope (member API).
     *
     * @return array{
     *   games: array<int, array<string, mixed>>,
     *   items: array<int, array<string, mixed>>,
     *   pagination: array<string, mixed>
     * }
     */
    public static function apiCatalog(
        string $searchTerm = '',
        int $limit = 30,
        int $page = 1,
        string $sort = ''
    ): array {
        // Member API host always reads local DB — never recurse via BackendApiClient.
        $result = self::page($searchTerm, $limit, $page, $sort, ['force_local' => true]);
        $games = [];
        foreach ($result['games'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = (string) ($row['game_name'] ?? $row['name'] ?? '');
            $cover = (string) ($row['cover'] ?? $row['image_url'] ?? '');
            $fallbacks = is_array($row['cover_fallbacks'] ?? null) ? $row['cover_fallbacks'] : [];
            $featured = (int) ($row['is_featured'] ?? (!empty($row['is_popular']) ? 1 : 0));
            $games[] = [
                'id'              => (string) ($row['id'] ?? ''),
                'game_id'         => (string) ($row['game_id'] ?? ''),
                'product_code'    => 0,
                'game_code'       => '',
                'name'            => $name,
                'title'           => $name,
                'cover'           => $cover,
                'image_url'       => $cover,
                'thumbnail_url'   => $cover,
                'image_fallbacks' => $fallbacks,
                'cover_fallbacks' => $fallbacks,
                'provider'        => (string) ($row['provider'] ?? 'BGaming'),
                'provider_code'   => (string) ($row['provider_code'] ?? 'bgaming'),
                'is_featured'     => $featured,
                'is_popular'      => $featured === 1,
                'has_demo'        => !empty($row['has_demo']),
                'category'        => 'slots',
                'game_type'       => 0,
                'source'          => 'bgaming',
            ];
        }

        $pagination = is_array($result['pagination'] ?? null) ? $result['pagination'] : [
            'page'       => $result['page'],
            'perPage'    => $result['perPage'],
            'limit'      => $result['perPage'],
            'offset'     => ($result['page'] - 1) * $result['perPage'],
            'total'      => $result['total'],
            'totalPages' => $result['totalPages'],
            'hasNext'    => $result['hasNext'],
            'hasPrev'    => $result['page'] > 1,
        ];

        return [
            'games'      => $games,
            'items'      => $games,
            'pagination' => $pagination,
        ];
    }

    private static function shouldUseRemoteApi(): bool
    {
        return function_exists('frontend_database_allowed') && !frontend_database_allowed();
    }

    private static function envelopeOk(?array $json): bool
    {
        if (!is_array($json)) {
            return false;
        }

        return !empty($json['success']) || (int) ($json['code'] ?? 0) === 200;
    }

    /**
     * @return array{
     *   games: array<int, array<string, mixed>>,
     *   total: int,
     *   page: int,
     *   perPage: int,
     *   hasNext: bool,
     *   totalPages: int,
     *   apiError: bool,
     *   pagination: array<string, mixed>
     * }
     */
    private static function pageViaApi(
        string $searchTerm,
        int $limit,
        int $page,
        string $sort
    ): array {
        if (!class_exists('BackendApiClient', false) && defined('SERVICE_PATH')) {
            $clientPath = SERVICE_PATH . '/BackendApiClient.php';
            if (is_file($clientPath)) {
                require_once $clientPath;
            }
        }
        if (!class_exists('BackendApiClient', false)) {
            $empty = self::emptyResult($limit, $page);
            $empty['apiError'] = true;
            return $empty;
        }

        $query = [
            'game_type' => 0,
            'page' => $page,
            'limit' => $limit,
            'source' => 'bgaming',
            'force_local' => 1,
        ];
        if ($searchTerm !== '') {
            $query['search'] = $searchTerm;
        }
        if ($sort !== '') {
            $query['sort'] = $sort;
        }

        $j = BackendApiClient::request('GET', BackendApiClient::SVC_GAMES, self::GAMES_PATH, $query, null, 8);
        if (!self::envelopeOk($j)) {
            $empty = self::emptyResult($limit, $page);
            $empty['apiError'] = true;
            return $empty;
        }

        $u = BackendApiClient::unwrap($j);
        if (!is_array($u)) {
            $empty = self::emptyResult($limit, $page);
            $empty['apiError'] = true;
            return $empty;
        }

        $items = is_array($u['games'] ?? null)
            ? $u['games']
            : (is_array($u['items'] ?? null) ? $u['items'] : []);
        $games = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = self::normalizeText($item['name'] ?? $item['game_name'] ?? $item['title'] ?? '');
            $gameId = (string) ($item['game_id'] ?? '');
            if (self::isHidden($name, $gameId)) {
                continue;
            }
            $cover = (string) ($item['cover'] ?? $item['image_url'] ?? $item['thumbnail_url'] ?? '');
            $fallbacks = is_array($item['cover_fallbacks'] ?? null)
                ? $item['cover_fallbacks']
                : (is_array($item['image_fallbacks'] ?? null) ? $item['image_fallbacks'] : []);
            $featured = (int) ($item['is_featured'] ?? 0);
            $games[] = [
                'id'              => (string) ($item['id'] ?? ''),
                'game_id'         => $gameId,
                'game_name'       => $name,
                'name'            => $name,
                'cover'           => $cover,
                'image_url'       => $cover,
                'cover_fallbacks' => $fallbacks,
                'image_fallbacks' => $fallbacks,
                'has_demo'        => array_key_exists('has_demo', $item) ? !empty($item['has_demo']) : true,
                'provider_code'   => (string) ($item['provider_code'] ?? 'bgaming'),
                'provider'        => self::normalizeText($item['provider'] ?? '') ?: 'BGaming',
                'is_featured'     => $featured,
                'is_popular'      => $featured === 1 || !empty($item['is_popular']),
                'source'          => 'bgaming',
            ];
        }

        $pagination = is_array($u['pagination'] ?? null) ? $u['pagination'] : [];
        $total = (int) ($pagination['total'] ?? $u['total'] ?? count($games));
        $perPage = (int) ($pagination['perPage'] ?? $u['perPage'] ?? $u['limit'] ?? $limit);
        if ($perPage < 1) {
            $perPage = $limit;
        }
        $pageNo = (int) ($pagination['page'] ?? $u['page'] ?? $page);
        $totalPages = (int) ($pagination['totalPages'] ?? $u['total_pages'] ?? ($total > 0 ? (int) ceil($total / $perPage) : 0));
        $hasNext = !empty($pagination['hasNext']) || !empty($u['hasNext']) || ($pageNo * $perPage) < $total;

        return [
            'games'      => $games,
            'total'      => $total,
            'page'       => $pageNo > 0 ? $pageNo : $page,
            'perPage'    => $perPage,
            'hasNext'    => $hasNext,
            'totalPages' => $totalPages,
            'apiError'   => false,
            'pagination' => [
                'page'       => $pageNo > 0 ? $pageNo : $page,
                'perPage'    => $perPage,
                'limit'      => $perPage,
                'offset'     => max(0, (($pageNo > 0 ? $pageNo : $page) - 1) * $perPage),
                'total'      => $total,
                'totalPages' => $totalPages,
                'hasNext'    => $hasNext,
                'hasPrev'    => ($pageNo > 0 ? $pageNo : $page) > 1,
            ],
        ];
    }

    private static function pdo(): ?PDO
    {
        if (function_exists('frontend_database_allowed') && !frontend_database_allowed()) {
            return null;
        }

        if (!class_exists('AdminDatabase', false)) {
            if (defined('ADMIN_APP_PATH') && is_file(ADMIN_APP_PATH . '/Core/AdminDatabase.php')) {
                require_once ADMIN_APP_PATH . '/Core/AdminDatabase.php';
            }
        }
        if (!class_exists('AdminDatabase', false)) {
            return null;
        }

        try {
            return AdminDatabase::pdo();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{
     *   games: array<int, array<string, mixed>>,
     *   total: int,
     *   page: int,
     *   perPage: int,
     *   hasNext: bool,
     *   totalPages: int,
     *   apiError: bool,
     *   pagination: array<string, mixed>
     * }
     */
    private static function catalogPage(
        PDO $pdo,
        string $searchTerm,
        int $limit,
        int $page,
        string $sort
    ): array {
        $offset = ($page - 1) * $limit;
        $search = trim($searchTerm);
        $sortKey = strtolower(trim($sort));
        if ($sortKey === 'all') {
            $sortKey = '';
        }

        $cols = self::catalogColumns($pdo);
        $fromSql = "FROM bgaming_games g WHERE g.is_active = 1";

        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = "({$cols['nameExpr']} LIKE :search OR {$cols['providerExpr']} LIKE :search2 OR g.identifier LIKE :search3)";
            $params[':search'] = '%' . $search . '%';
            $params[':search2'] = '%' . $search . '%';
            $params[':search3'] = '%' . $search . '%';
        }

        $preferFeatured = ($sortKey === 'popular' || $sortKey === 'liked');
        if ($preferFeatured && $cols['hasFeatured']) {
            // Soft preference via ORDER BY only — do not require is_featured=1.
        }

        // Hide SoftSwiss acceptance-test titles from the public lobby.
        $where[] = "LOWER({$cols['nameExpr']}) NOT LIKE '%acceptance%test%'";
        $where[] = "LOWER(g.identifier) NOT LIKE '%acceptance%test%'";

        $whereSql = $where === [] ? '' : ' AND ' . implode(' AND ', $where);

        $orderBy = "{$cols['featuredExpr']} DESC, {$cols['nameExpr']} ASC";
        if ($sortKey === 'new') {
            $orderBy = "g.id DESC, {$cols['featuredExpr']} DESC, {$cols['nameExpr']} ASC";
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) {$fromSql}{$whereSql}");
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT
                CONCAT('bgaming:', g.identifier) AS game_id,
                {$cols['nameExpr']} AS name,
                {$cols['providerExpr']} AS provider,
                {$cols['providerCodeExpr']} AS provider_code,
                {$cols['imageExpr']} AS image_url,
                {$cols['featuredExpr']} AS is_featured,
                CAST(g.id AS CHAR) AS row_id
            {$fromSql}{$whereSql}
            ORDER BY {$orderBy}
            LIMIT :limit OFFSET :offset";

        $rowsStmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $rowsStmt->bindValue($k, $v);
        }
        $rowsStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $rowsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $rowsStmt->execute();
        $items = $rowsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $games = [];
        foreach ($items as $r) {
            if (!is_array($r)) {
                continue;
            }
            $mapped = self::mapRow($r);
            if ($mapped !== null) {
                $games[] = $mapped;
            }
        }

        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 0;
        $hasNext = ($offset + $limit) < $total;
        $pagination = [
            'page'       => $page,
            'perPage'    => $limit,
            'limit'      => $limit,
            'offset'     => $offset,
            'total'      => $total,
            'totalPages' => $totalPages,
            'hasNext'    => $hasNext,
            'hasPrev'    => $offset > 0,
        ];

        return [
            'games'      => $games,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $limit,
            'hasNext'    => $hasNext,
            'totalPages' => $totalPages,
            'apiError'   => false,
            'pagination' => $pagination,
        ];
    }

    /**
     * @param array<string, mixed> $r
     * @return array<string, mixed>|null
     */
    private static function mapRow(array $r): ?array
    {
        $name = self::normalizeText($r['name'] ?? '');
        $gameId = (string) ($r['game_id'] ?? '');
        if ($name === '' && $gameId === '') {
            return null;
        }
        if (self::isHidden($name, $gameId)) {
            return null;
        }

        $provider = self::normalizeText($r['provider'] ?? '') ?: 'BGaming';
        $imageUrl = self::normalizeText($r['image_url'] ?? '');
        $featured = (int) ($r['is_featured'] ?? 0);

        $cover = $imageUrl;
        $fallbacks = [];
        if (!class_exists('CasinoAggregatorService', false) && defined('SERVICE_PATH')) {
            $aggPath = SERVICE_PATH . '/CasinoAggregatorService.php';
            if (is_file($aggPath)) {
                require_once $aggPath;
            }
        }
        if (class_exists('CasinoAggregatorService', false)) {
            $media = CasinoAggregatorService::hydrateGameMedia([
                'image_url' => $imageUrl,
                'source'    => 'bgaming',
                'name'      => $name,
            ]);
            $cover = (string) ($media['cover'] ?? $imageUrl);
            $fallbacks = is_array($media['cover_fallbacks'] ?? null) ? $media['cover_fallbacks'] : [];
            if ($fallbacks === [] && $cover !== '' && method_exists('CasinoAggregatorService', 'expandFormatFallbacks')) {
                $fallbacks = CasinoAggregatorService::expandFormatFallbacks([$cover]);
                if ($fallbacks !== []) {
                    $cover = (string) $fallbacks[0];
                }
            }
        }

        return [
            'id'              => (string) ($r['row_id'] ?? ''),
            'game_id'         => $gameId,
            'game_name'       => $name,
            'name'            => $name,
            'cover'           => $cover,
            'image_url'       => $cover,
            'cover_fallbacks' => $fallbacks,
            'image_fallbacks' => $fallbacks,
            'has_demo'        => true,
            'provider_code'   => (string) ($r['provider_code'] ?? 'bgaming'),
            'provider'        => $provider,
            'is_featured'     => $featured,
            'is_popular'      => $featured === 1,
            'source'          => 'bgaming',
        ];
    }

    private static function isHidden(string $name, string $gameId): bool
    {
        foreach ([$name, $gameId] as $value) {
            $candidate = strtolower(trim($value));
            if ($candidate !== '' && preg_match('/(?:^|[^a-z0-9])acceptance[\s:_-]*test(?:$|[^a-z0-9])/i', $candidate) === 1) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeText(mixed $value): string
    {
        if (is_array($value)) {
            foreach (['tr', 'en', 'default'] as $key) {
                if (isset($value[$key]) && is_scalar($value[$key]) && trim((string) $value[$key]) !== '') {
                    return trim((string) $value[$key]);
                }
            }
            foreach ($value as $item) {
                if (is_scalar($item) && trim((string) $item) !== '') {
                    return trim((string) $item);
                }
            }

            return '';
        }

        return trim((string) $value);
    }

    /**
     * @return array{
     *   nameExpr: string,
     *   providerExpr: string,
     *   providerCodeExpr: string,
     *   imageExpr: string,
     *   featuredExpr: string,
     *   providerCol: string,
     *   hasFeatured: bool
     * }
     */
    private static function catalogColumns(PDO $pdo): array
    {
        $titleCol = self::tableHasColumn($pdo, 'bgaming_games', 'title')
            ? 'title'
            : (self::tableHasColumn($pdo, 'bgaming_games', 'name') ? 'name' : '');
        $providerCol = self::tableHasColumn($pdo, 'bgaming_games', 'provider')
            ? 'provider'
            : (self::tableHasColumn($pdo, 'bgaming_games', 'producer') ? 'producer' : '');
        $imageCol = self::tableHasColumn($pdo, 'bgaming_games', 'thumbnail_url')
            ? 'thumbnail_url'
            : (self::tableHasColumn($pdo, 'bgaming_games', 'image_url') ? 'image_url' : '');
        $hasFeatured = self::tableHasColumn($pdo, 'bgaming_games', 'is_featured');

        return [
            'nameExpr' => $titleCol !== ''
                ? "COALESCE(NULLIF(g.{$titleCol}, ''), g.identifier)"
                : 'g.identifier',
            'providerExpr' => $providerCol !== ''
                ? "COALESCE(NULLIF(g.{$providerCol}, ''), 'BGaming')"
                : "'BGaming'",
            'providerCodeExpr' => $providerCol !== ''
                ? "COALESCE(NULLIF(g.{$providerCol}, ''), 'bgaming')"
                : "'bgaming'",
            'imageExpr' => $imageCol !== ''
                ? "COALESCE(NULLIF(g.{$imageCol}, ''), '')"
                : "CAST('' AS CHAR)",
            'featuredExpr' => $hasFeatured ? 'g.is_featured' : '0',
            'providerCol' => $providerCol,
            'hasFeatured' => $hasFeatured,
        ];
    }

    private static function tableHasColumn(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];
        $key = strtolower($table . '.' . $column);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT 1
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND COLUMN_NAME = :column
                 LIMIT 1'
            );
            $stmt->execute([
                ':table' => $table,
                ':column' => $column,
            ]);
            $cache[$key] = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }

    /**
     * @return array{
     *   games: array<int, array<string, mixed>>,
     *   total: int,
     *   page: int,
     *   perPage: int,
     *   hasNext: bool,
     *   totalPages: int,
     *   apiError: bool,
     *   pagination: array<string, mixed>
     * }
     */
    private static function emptyResult(int $limit, int $page): array
    {
        return [
            'games'      => [],
            'total'      => 0,
            'page'       => $page,
            'perPage'    => $limit,
            'hasNext'    => false,
            'totalPages' => 0,
            'apiError'   => false,
            'pagination' => [
                'page'       => $page,
                'perPage'    => $limit,
                'limit'      => $limit,
                'offset'     => ($page - 1) * $limit,
                'total'      => 0,
                'totalPages' => 0,
                'hasNext'    => false,
                'hasPrev'    => $page > 1,
            ],
        ];
    }
}
