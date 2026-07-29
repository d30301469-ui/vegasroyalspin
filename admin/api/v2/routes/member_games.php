<?php
/**
 * Üye API modülü — index.php tarafından include edilir.
 *
 * Variables injected by the including kernel (member_api_kernel.php).
 * The ??= assignments are no-ops when the file is properly included.
 *
 * @var string $method
 * @var string $route
 * @var array{query: array<string,mixed>, body: array<string,mixed>} $payload
 * @var \Closure(int, array<string,mixed>): void $memberEnvelope
 * @var \Closure(array<string,mixed>): array<string,mixed> $memberInput
 * @var \Closure(int, string, array<string,mixed>): void $error
 * @var \Closure(): void $requireAuth
 * @var \Closure(): int $memberRequireLogin
 * @var \Closure(\PDO, int): ?array<string,mixed> $memberUserById
 */

$method ??= 'GET';
$route ??= '';
$payload ??= ['query' => [], 'body' => []];
$memberEnvelope ??= static function (int $s, array $b): void { http_response_code($s); echo json_encode($b); exit; };
$memberInput ??= static fn (array $p): array => $p['body'] ?? [];
$error ??= static function (int $s, string $m, array $t = []): void { http_response_code($s); echo json_encode(['success' => false, 'code' => $s, 'message' => $m, 'meta' => $t]); exit; };
$requireAuth ??= static function (): void {};
$memberRequireLogin ??= static fn (): int => 0;
$memberUserById ??= static fn (\PDO $p, int $id): ?array => null;

if ($method === 'GET' && in_array($route, ['games_provider.php', 'casino/providers', 'live-casino/providers', 'games-provider', 'games_provider.php'], true)) {
    $pdo = AdminDatabase::pdo();
    admin_require_project_file('services/CasinoAggregatorService.php');
    // 0 = slot lobby, 1 = live casino.
    $gameType = (int) ($_GET['game_type'] ?? $_GET['filter_game_type'] ?? 0) === 1 ? 1 : 0;
    if ($route === 'live-casino/providers') {
        $gameType = 1;
    }
    $providers = [];
    try {
        if ($gameType === 1) {
            admin_require_project_file('services/GscPlusService.php');
            admin_require_project_file('services/LiveCasinoQuery.php');
            $providerExtra = [
                'force_local' => true,
                'currency' => strtoupper(trim((string) ($_GET['currency'] ?? ''))),
            ];
            if (array_key_exists('gsc_only', $_GET)) {
                $providerExtra['gsc_only'] = $_GET['gsc_only'];
            } elseif (GscPlusService::liveLobbyGscOnly()) {
                $providerExtra['gsc_only'] = 1;
            }
            foreach (LiveCasinoQuery::providers($providerExtra) as $name) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }
                $providers[] = [
                    'provider_code' => $name,
                    'provider_name' => $name,
                ];
            }
        } elseif ($gameType === 0) {
            // BGaming catalogue is slot-only.
            $sql = "SELECT DISTINCT provider AS provider_code, provider AS provider_name
                FROM bgaming_games
                WHERE is_active = 1 AND provider <> ''
                ORDER BY provider_name ASC";
            $pStmt = $pdo->query($sql);
            $providers = $pStmt ? $pStmt->fetchAll(PDO::FETCH_ASSOC) : [];
            $aggStmt = $pdo->prepare(
                "SELECT DISTINCT v.vendor_code AS provider_code, v.vendor_name AS provider_name
                 FROM casino_aggregator_vendors v
                 INNER JOIN casino_aggregator_games g ON g.vendor_code = v.vendor_code
                 WHERE v.is_active = 1 AND g.is_active = 1 AND g.game_type = 1
                   AND NOT (" . CasinoAggregatorService::liveVendorSqlMatch('g.vendor_code') . ")
                 ORDER BY v.vendor_name ASC"
            );
            $aggStmt->execute();
            $seen = [];
            foreach ($providers as $row) {
                $seen[(string) ($row['provider_code'] ?? '')] = true;
            }
            foreach ($aggStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $code = (string) ($row['provider_code'] ?? '');
                if ($code === '' || isset($seen[$code])) {
                    continue;
                }
                $seen[$code] = true;
                $row['provider_name'] = CasinoAggregatorService::resolveLocalizedLabel($row['provider_name'] ?? '') ?: $code;
                $providers[] = $row;
            }
        }
    } catch (Throwable) {}
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Oyun sağlayıcıları',
        'data' => ['providers' => $providers],
    ]);
}

if ($method === 'GET' && $route === 'casino/categories') {
    $items = [
        ['key' => 'slots', 'name' => 'Slot Oyunları'],
        ['key' => 'live-casino', 'name' => 'Canlı Casino'],
        ['key' => 'table-games', 'name' => 'Masa Oyunları'],
        ['key' => 'tv-games', 'name' => 'TV Oyunları'],
        ['key' => 'popular', 'name' => 'Popüler'],
        ['key' => 'new', 'name' => 'Yeni Oyunlar'],
    ];
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Casino kategorileri',
        'data' => [
            'items' => $items,
            'categories' => $items,
            'total' => count($items),
        ],
        'meta' => ['resource' => 'casino/categories'],
    ]);
}





if ($method === 'GET' && in_array($route, ['games.php', 'games'], true)) {
    $pdo      = AdminDatabase::pdo();
    admin_require_project_file('services/CasinoAggregatorService.php');
    // 0 = slot lobby (BGaming), 1 = live casino.
    $gameType = (int) ($_GET['game_type'] ?? $_GET['filter_game_type'] ?? 0) === 1 ? 1 : 0;
    $page     = max(1, (int) ($_GET['page'] ?? 1));
    $limit    = min(200, max(1, (int) ($_GET['limit'] ?? $_GET['per_page'] ?? 30)));
    $offset   = ($page - 1) * $limit;
    $search   = trim((string) ($_GET['search'] ?? $_GET['q'] ?? ''));
    $provider = trim((string) ($_GET['provider'] ?? $_GET['provider_code'] ?? ''));
    $providerList = [];
    if (isset($_GET['providers']) && is_array($_GET['providers'])) {
        $providerList = array_values(array_filter(array_map(static fn ($x): string => trim((string) $x), $_GET['providers']), static fn (string $x): bool => $x !== '' && strtolower($x) !== 'hepsi'));
    } elseif ($provider !== '' && strtolower($provider) !== 'hepsi') {
        $providerList = [$provider];
    }
    $onlyFeatured = (string) ($_GET['is_featured'] ?? '') === '1'
        || in_array(strtolower((string) ($_GET['sort'] ?? '')), ['popular', 'liked'], true);
    $source = strtolower(trim((string) ($_GET['source'] ?? '')));
    $sort = strtolower(trim((string) ($_GET['sort'] ?? '')));

    // Live casino catalogue is owned by LiveCasinoQuery (not SlotGamesQuery).
    if ($gameType === 1 || in_array($source, ['livecasino', 'live', 'live_casino'], true)) {
        admin_require_project_file('services/GscPlusService.php');
        admin_require_project_file('services/LiveCasinoQuery.php');
        $liveExtra = [
            // Always hit local DB on the admin API host — never recurse via BackendApiClient.
            'force_local' => true,
            'source' => in_array($source, ['aggregator'], true) ? $source : '',
            'currency' => strtoupper(trim((string) ($_GET['currency'] ?? ''))),
        ];
        if (array_key_exists('gsc_only', $_GET)) {
            $liveExtra['gsc_only'] = $_GET['gsc_only'];
        } elseif (GscPlusService::liveLobbyGscOnly()) {
            $liveExtra['gsc_only'] = 1;
        }
        $liveResult = LiveCasinoQuery::page(
            $search,
            $providerList,
            $limit,
            $page,
            $onlyFeatured ? 'popular' : $sort,
            $liveExtra
        );
        $games = is_array($liveResult['games'] ?? null) ? $liveResult['games'] : [];
        $total = (int) ($liveResult['total'] ?? count($games));
        $totalPages = (int) ($liveResult['totalPages'] ?? ($total > 0 ? (int) ceil($total / $limit) : 0));
        $memberEnvelope(200, [
            'success' => true,
            'code'    => 200,
            'message' => 'Oyun listesi',
            'data'    => [
                'games'       => $games,
                'items'       => $games,
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'perPage'     => $limit,
                'total_pages' => $totalPages,
                'pagination'  => [
                    'page'       => $page,
                    'perPage'    => $limit,
                    'limit'      => $limit,
                    'offset'     => $offset,
                    'total'      => $total,
                    'totalPages' => $totalPages,
                    'hasNext'    => !empty($liveResult['hasNext']),
                    'hasPrev'    => $page > 1,
                ],
            ],
        ]);
    }

    // IMPORTANT: Serve from local DB only. Never call SlotGamesQuery here —
    // that class HTTP-calls this same games.php endpoint and recurses until 503.
    //
    // Also: never UNION branches that mix JSON columns (raw_payload / image_fallbacks)
    // with VARCHAR literals — MySQL rejects the mix and the old catch{} returned an empty list.
    $tableHasColumn = static function (PDO $pdo, string $table, string $column): bool {
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
    };

    $branches = [];
    if ($gameType === 0 && ($source === '' || $source === 'bgaming')) {
        $titleCol = $tableHasColumn($pdo, 'bgaming_games', 'title')
            ? 'title'
            : ($tableHasColumn($pdo, 'bgaming_games', 'name') ? 'name' : '');
        $providerCol = $tableHasColumn($pdo, 'bgaming_games', 'provider')
            ? 'provider'
            : ($tableHasColumn($pdo, 'bgaming_games', 'producer') ? 'producer' : '');
        $imageCol = $tableHasColumn($pdo, 'bgaming_games', 'thumbnail_url')
            ? 'thumbnail_url'
            : ($tableHasColumn($pdo, 'bgaming_games', 'image_url') ? 'image_url' : '');
        $featuredExpr = $tableHasColumn($pdo, 'bgaming_games', 'is_featured') ? 'g.is_featured' : '0';
        $nameExpr = $titleCol !== '' ? "COALESCE(NULLIF(g.{$titleCol}, ''), g.identifier)" : 'g.identifier';
        $providerExpr = $providerCol !== '' ? "COALESCE(NULLIF(g.{$providerCol}, ''), 'BGaming')" : "'BGaming'";
        $providerCodeExpr = $providerCol !== '' ? "COALESCE(NULLIF(g.{$providerCol}, ''), 'bgaming')" : "'bgaming'";
        $imageExpr = $imageCol !== '' ? "COALESCE(NULLIF(g.{$imageCol}, ''), '')" : "CAST('' AS CHAR)";

        $branches[] = "SELECT
                CONCAT('bgaming:', g.identifier) AS game_id,
                {$nameExpr} AS name,
                {$providerExpr} AS provider,
                {$providerCodeExpr} AS provider_code,
                {$imageExpr} AS image_url,
                CAST('' AS CHAR) AS image_fallbacks,
                {$featuredExpr} AS is_featured,
                'bgaming' AS source,
                CAST(g.id AS CHAR) AS row_id,
                CAST('' AS CHAR) AS raw_payload
            FROM bgaming_games g
            WHERE g.is_active = 1";
    }

    // Frontend slot lobby keeps serving aggregator games, while dedicated BGaming
    // pages use `source=bgaming` to fetch only BGaming rows.
    $aggGameType = $gameType === 1 ? 2 : 1;
    if ($source === '' || $source === 'aggregator') {
        if ($gameType === 1) {
            try {
                CasinoAggregatorService::repairGameTypesFromPayload($pdo);
            } catch (Throwable) {
            }
        }
        // Historical aggregator slot rows can be stored as game_type=0.
        $typeClause = $gameType === 1 ? "g.game_type = {$aggGameType}" : "(g.game_type IN (0, {$aggGameType}))";
        $liveMatch = CasinoAggregatorService::liveVendorSqlMatch('g.vendor_code');
        if ($gameType === 1) {
            $typeClause = "(g.game_type = {$aggGameType} OR {$liveMatch})";
        } else {
            $typeClause = "((g.game_type IN (0, {$aggGameType})) AND NOT {$liveMatch})";
        }
        $branches[] = "SELECT
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
    if ($branches === []) {
        $memberEnvelope(200, [
            'success' => true,
            'code'    => 200,
            'message' => 'Oyun listesi',
            'data'    => [
                'games'       => [],
                'items'       => [],
                'total'       => 0,
                'page'        => $page,
                'limit'       => $limit,
                'perPage'     => $limit,
                'total_pages' => 0,
                'pagination'  => [
                    'page'       => $page,
                    'perPage'    => $limit,
                    'limit'      => $limit,
                    'offset'     => $offset,
                    'total'      => 0,
                    'totalPages' => 0,
                    'hasNext'    => false,
                    'hasPrev'    => $offset > 0,
                ],
            ],
        ]);
    }

    $unionSql = '(' . implode(' UNION ALL ', $branches) . ') AS catalog';

    $where  = [];
    $params = [];
    if ($search !== '') {
        $where[]            = '(name LIKE :search OR provider LIKE :search2)';
        $params[':search']  = '%' . $search . '%';
        $params[':search2'] = '%' . $search . '%';
    }
    if ($providerList !== []) {
        $filterTerms = $providerList;
        try {
            $expanded = CasinoAggregatorService::expandProviderFilter($pdo, $providerList);
            $filterTerms = array_values(array_unique(array_merge($expanded['names'], $expanded['codes'])));
        } catch (Throwable) {
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
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

    $total = 0;
    $allGames = [];
    try {
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
        $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $featured = (int) ($r['is_featured'] ?? 0);
            $providerName = CasinoAggregatorService::resolveLocalizedLabel($r['provider'] ?? '');
            $gameName = CasinoAggregatorService::resolveLocalizedLabel($r['name'] ?? '');
            $media = CasinoAggregatorService::hydrateGameMedia($r);
            $imageUrl = (string) ($media['cover'] ?? '');
            $imageFallbacks = is_array($media['cover_fallbacks'] ?? null) ? $media['cover_fallbacks'] : [];
            $gameIdStr = (string) ($r['game_id'] ?? '');
            $allGames[] = [
                'id'            => (string) ($r['row_id'] ?? ''),
                'game_id'       => $gameIdStr,
                'product_code'  => 0,
                'game_code'     => '',
                'name'          => $gameName,
                'title'         => $gameName,
                'cover'         => $imageUrl,
                'image_url'     => $imageUrl,
                'thumbnail_url' => $imageUrl,
                'image_fallbacks' => $imageFallbacks,
                'cover_fallbacks' => $imageFallbacks,
                'provider'      => $providerName,
                'provider_code' => (string) ($r['provider_code'] ?? ''),
                'is_featured'   => $featured,
                'is_popular'    => $featured === 1,
                'has_demo'      => true,
                'category'      => $gameType === 1 ? 'live-casino' : 'slots',
                'game_type'     => $gameType,
                'source'        => (string) ($r['source'] ?? ''),
            ];
        }
    } catch (Throwable $e) {
        error_log('member_games catalogue union error: ' . $e->getMessage());
    }
    $totalPages = $total > 0 ? (int) ceil($total / $limit) : 1;
    $memberEnvelope(200, [
        'success' => true,
        'code'    => 200,
        'message' => 'Oyun listesi',
        'data'    => [
            'games'       => $allGames,
            'items'       => $allGames,
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'perPage'     => $limit,
            'total_pages' => $totalPages,
            'pagination'  => [
                'page'       => $page,
                'perPage'    => $limit,
                'limit'      => $limit,
                'offset'     => $offset,
                'total'      => $total,
                'totalPages' => $totalPages,
                'hasNext'    => ($offset + $limit) < $total,
                'hasPrev'    => $offset > 0,
            ],
        ],
    ]);
}

if ($method === 'GET' && ($route === 'game_history.php' || $route === 'casino_game_history.php')) {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    BgamingService::bootstrap($pdo);
    admin_require_project_file('services/CasinoAggregatorService.php');
    CasinoAggregatorService::bootstrap($pdo);

    $source = strtolower(trim((string) ($_GET['source'] ?? $_GET['category'] ?? $_GET['game_type'] ?? 'all')));
    if (in_array($source, ['live', 'livecasino'], true)) {
        $source = 'live_casino';
    }
    if (!in_array($source, ['all', 'slot', 'live_casino'], true)) {
        $source = 'all';
    }

    $limit = min(200, max(1, (int) ($_GET['limit'] ?? $_GET['per_page'] ?? 100)));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $offset = max(0, (int) ($_GET['offset'] ?? (($page - 1) * $limit)));
    $fetchLimit = min(400, max($limit + $offset, 100));

    $rows = [];

    if ($source !== 'live_casino') {
        try {
            $stmt = $pdo->prepare(
                "SELECT
                    t.id,
                    t.action_id,
                    t.original_action_id,
                    t.casino_tx_id,
                    t.session_id,
                    t.round_id,
                    t.game_identifier,
                    COALESCE(g.title, t.game_identifier) AS game_name,
                    COALESCE(NULLIF(g.provider, ''), 'BGaming') AS provider_name,
                    COALESCE(NULLIF(g.provider, ''), 'bgaming') AS provider_code,
                    COALESCE(NULLIF(g.category, ''), 'slot') AS game_category,
                    t.txn_type,
                    t.amount,
                    t.after_balance,
                    COALESCE(t.processed_at, t.created_at) AS created_at
                 FROM bgaming_transactions t
                 LEFT JOIN bgaming_games g ON g.identifier = t.game_identifier
                 WHERE t.user_id = :uid
                 ORDER BY t.id DESC
                 LIMIT {$fetchLimit}"
            );
            $stmt->execute([':uid' => $userId]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rawTxnType = strtolower((string) ($row['txn_type'] ?? 'bet'));
                $normalizedTxnType = match ($rawTxnType) {
                    'win', 'promo_win', 'freespins_win' => 'win',
                    'rollback' => 'refund',
                    default => 'bet',
                };
                $amount = (float) ($row['amount'] ?? 0);

                $rows[] = [
                    'id' => 'bgaming:' . (string) ($row['id'] ?? ''),
                    'history_id' => 'bgaming:' . (string) ($row['id'] ?? ''),
                    'transactionId' => (string) ($row['casino_tx_id'] ?? ''),
                    'transaction_id' => (string) ($row['casino_tx_id'] ?? ''),
                    'providerTxnId' => (string) ($row['action_id'] ?? ''),
                    'provider_txn_id' => (string) ($row['action_id'] ?? ''),
                    'relatedTransactionId' => (string) ($row['original_action_id'] ?? ''),
                    'related_transaction_id' => (string) ($row['original_action_id'] ?? ''),
                    'sessionToken' => (string) ($row['session_id'] ?? ''),
                    'session_id' => (string) ($row['session_id'] ?? ''),
                    'roundId' => (string) ($row['round_id'] ?? ''),
                    'round_id' => (string) ($row['round_id'] ?? ''),
                    'gameId' => (string) ($row['game_identifier'] ?? ''),
                    'game_id' => (string) ($row['game_identifier'] ?? ''),
                    'gameName' => (string) ($row['game_name'] ?? ''),
                    'game_name' => (string) ($row['game_name'] ?? ''),
                    'providerCode' => (string) ($row['provider_code'] ?? 'bgaming'),
                    'provider_code' => (string) ($row['provider_code'] ?? 'bgaming'),
                    'providerName' => (string) ($row['provider_name'] ?? 'BGaming'),
                    'provider_name' => (string) ($row['provider_name'] ?? 'BGaming'),
                    'category' => 'slot',
                    'source' => 'slot',
                    'txnType' => $normalizedTxnType,
                    'txn_type' => $normalizedTxnType,
                    'status' => 'completed',
                    'betAmount' => $normalizedTxnType === 'bet' ? $amount : 0.0,
                    'bet_amount' => $normalizedTxnType === 'bet' ? $amount : 0.0,
                    'winAmount' => $normalizedTxnType !== 'bet' ? $amount : 0.0,
                    'win_amount' => $normalizedTxnType !== 'bet' ? $amount : 0.0,
                    'balanceAfter' => (float) ($row['after_balance'] ?? 0),
                    'balance_after' => (float) ($row['after_balance'] ?? 0),
                    'createdAt' => (string) ($row['created_at'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'wallet' => 'casino',
                ];
            }
        } catch (Throwable) {}
    }

    if ($source === 'all' || $source === 'slot' || $source === 'live_casino') {
        $aggTypeSql = match ($source) {
            'slot' => ' AND COALESCE(g.game_type, 1) = 1',
            'live_casino' => ' AND COALESCE(g.game_type, 1) = 2',
            default => '',
        };
        try {
            $aggStmt = $pdo->prepare(
                "SELECT
                    t.id,
                    t.txn_code,
                    t.pair_code,
                    t.wager_id,
                    t.round_id,
                    t.vendor_code,
                    t.game_code,
                    COALESCE(g.game_name, t.game_code) AS game_name,
                    COALESCE(NULLIF(v.vendor_name, ''), t.vendor_code) AS provider_name,
                    COALESCE(g.game_type, 1) AS game_type,
                    t.txn_type,
                    t.amount,
                    t.after_balance,
                    t.created_at
                 FROM casino_aggregator_transactions t
                 LEFT JOIN casino_aggregator_games g
                    ON g.vendor_code = t.vendor_code AND g.game_code = t.game_code
                 LEFT JOIN casino_aggregator_vendors v ON v.vendor_code = t.vendor_code
                 WHERE t.user_id = :uid{$aggTypeSql}
                 ORDER BY t.id DESC
                 LIMIT {$fetchLimit}"
            );
            $aggStmt->execute([':uid' => $userId]);
            foreach ($aggStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $rows[] = CasinoAggregatorService::buildMemberGameHistoryRow($row);
            }
        } catch (Throwable) {
        }
    }

    usort($rows, static function (array $left, array $right): int {
        return strtotime((string) ($right['created_at'] ?? '')) <=> strtotime((string) ($left['created_at'] ?? ''));
    });

    $total = count($rows);
    $pageRows = array_slice($rows, $offset, $limit);

    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Oyun geçmişi',
        'data' => [
            'items' => $pageRows,
            'transactions' => $pageRows,
            'total' => $total,
            'source' => $source,
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
        ],
    ]);
}

if ($method === 'GET' && ($route === 'games/recently-played' || $route === 'games/recently-played.php')) {
    $memberRequireLogin();
    $memberEnvelope(200, [
        'success' => true,
        'code'    => 200,
        'message' => 'Son oynanan oyunlar',
        'data'    => ['items' => [], 'total' => 0],
    ]);
}

if ($method === 'GET' && ($route === 'games/search' || $route === 'games/search.php')) {
    $pdo    = AdminDatabase::pdo();
    $q      = trim((string) ($_GET['q'] ?? $_GET['search'] ?? ''));
    $limit  = min(100, max(1, (int) ($_GET['limit'] ?? 30)));
    $page   = max(1, (int) ($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;
    if ($q === '') {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Arama terimi gereklidir']);
    }
    $like = '%' . $q . '%';
    $stmtB = $pdo->prepare("
        SELECT CONCAT('bgaming:', identifier) AS game_id, name AS game_name, producer AS provider_code,
               producer AS provider_name, '' AS game_category,
               COALESCE(thumbnail_url, '') AS image_url, 'bgaming' AS source
        FROM bgaming_games
        WHERE name LIKE :q OR producer LIKE :q2
        ORDER BY game_name ASC
        LIMIT :lim OFFSET :off
    ");
    $stmtB->bindValue(':q', $like);
    $stmtB->bindValue(':q2', $like);
    $stmtB->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmtB->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmtB->execute();
    $games = array_map(static function (array $row): array {
        return [
            'game_id'       => (string) ($row['game_id'] ?? ''),
            'game_name'     => (string) ($row['game_name'] ?? ''),
            'provider_code' => (string) ($row['provider_code'] ?? ''),
            'provider_name' => (string) ($row['provider_name'] ?? ''),
            'category'      => (string) ($row['game_category'] ?? 'slot'),
            'image_url'     => (string) ($row['image_url'] ?? ''),
            'source'        => (string) ($row['source'] ?? ''),
        ];
    }, $stmtB->fetchAll(PDO::FETCH_ASSOC));
    $memberEnvelope(200, [
        'success' => true,
        'code'    => 200,
        'message' => 'Arama sonuçları',
        'data'    => ['items' => $games, 'total' => count($games), 'query' => $q],
    ]);
}

if ($method === 'GET' && in_array($route, ['winners.php', 'winners'], true)) {
    $pdo = AdminDatabase::pdo();
    BgamingService::bootstrap($pdo);
    admin_require_project_file('services/CasinoAggregatorService.php');
    CasinoAggregatorService::bootstrap($pdo);
    $emptyWinners = static function (string $tab, string $period) use ($memberEnvelope): void {
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => $tab === 'top' ? 'En çok kazananlar' : 'Kazananlar',
            'data' => [
                'winners' => [],
                'items' => [],
                'total' => 0,
                'tab' => $tab,
                'winners_tab' => $tab,
                'period' => $period,
                'winners_period' => $period,
            ],
        ]);
    };
    try {
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
    $tab = ($_GET['winners_tab'] ?? $_GET['tab'] ?? 'recent') === 'top' ? 'top' : 'recent';
    $period = (string) ($_GET['winners_period'] ?? $_GET['period'] ?? 'day');
    if (!in_array($period, ['day', 'week', 'month', 'all'], true)) {
        $period = 'day';
    }
    if ($tab === 'recent') {
        $bgamingPeriodSql = '';
        $aggregatorPeriodSql = '';
    } else {
        $bgamingPeriodSql = match ($period) {
            'week' => ' AND t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
            'month' => ' AND t.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)',
            'all' => '',
            default => ' AND t.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)',
        };
        $aggregatorPeriodSql = $bgamingPeriodSql;
    }

    $winnerSql = "SELECT *
                  FROM (
                      SELECT
                          u.username,
                          t.user_id,
                          t.game_identifier AS game_id,
                          COALESCE(g.title, t.game_identifier) AS game_name,
                          COALESCE(NULLIF(g.provider, ''), 'BGaming') AS provider_name,
                          COALESCE(g.thumbnail_url, '') AS image_url,
                          COALESCE(g.thumbnail_url, '') AS banner,
                          t.amount AS win_amount,
                          t.created_at AS created_at,
                          t.id AS sort_id,
                          'bgaming' AS source,
                          NULL AS raw_payload
                      FROM bgaming_transactions t
                      LEFT JOIN users u ON u.id = t.user_id
                      LEFT JOIN bgaming_games g ON g.identifier = t.game_identifier
                      WHERE t.txn_type IN ('win', 'promo_win', 'freespins_win') AND t.amount > 0{$bgamingPeriodSql}
                      UNION ALL
                      SELECT
                          u.username,
                          t.user_id,
                          CONCAT('aggregator:', t.vendor_code, ':', t.game_code) AS game_id,
                          COALESCE(g.game_name, t.game_code) AS game_name,
                          COALESCE(NULLIF(v.vendor_name, ''), t.vendor_code) AS provider_name,
                          COALESCE(NULLIF(g.image_url, ''), '') AS image_url,
                          COALESCE(NULLIF(g.image_url, ''), '') AS banner,
                          ABS(t.amount) AS win_amount,
                          t.created_at AS created_at,
                          t.id AS sort_id,
                          'aggregator' AS source,
                          g.raw_payload AS raw_payload
                      FROM casino_aggregator_transactions t
                      LEFT JOIN users u ON u.id = t.user_id
                      LEFT JOIN casino_aggregator_games g
                          ON g.vendor_code = t.vendor_code AND g.game_code = t.game_code
                      LEFT JOIN casino_aggregator_vendors v ON v.vendor_code = t.vendor_code
                      WHERE t.txn_type = 'win' AND t.amount > 0{$aggregatorPeriodSql}
                  ) winners_union";

    $maskUsername = static function (mixed $value): string {
        $username = (string) ($value ?: 'Uye');
        return $username !== ''
            ? substr($username, 0, 2) . str_repeat('*', max(3, strlen($username) - 2))
            : 'Uye***';
    };

    if ($tab === 'top') {
        $stmt = $pdo->prepare($winnerSql . ' ORDER BY created_at DESC, sort_id DESC LIMIT 2000');
        $stmt->execute();
        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $row = CasinoAggregatorService::normalizeWinnerDisplayRow($row);
            $key = (string) ((int) ($row['user_id'] ?? 0) > 0 ? $row['user_id'] : ($row['username'] ?? 'guest'));
            if (!isset($grouped[$key])) {
                $grouped[$key] = $row;
                $grouped[$key]['total_win_amount'] = 0.0;
                $grouped[$key]['last_win_at'] = (string) ($row['created_at'] ?? '');
                $grouped[$key]['last_game_name'] = (string) ($row['game_name'] ?? '');
                $grouped[$key]['last_provider_name'] = (string) ($row['provider_name'] ?? '');
            }
            $grouped[$key]['total_win_amount'] += (float) ($row['win_amount'] ?? 0);
        }
        $groupedRows = array_values($grouped);
        usort($groupedRows, static fn (array $a, array $b): int => (float) ($b['total_win_amount'] ?? 0) <=> (float) ($a['total_win_amount'] ?? 0));
        $groupedRows = array_slice($groupedRows, 0, $limit);

        $rows = array_map(static function (array $row) use ($maskUsername): array {
            $username = (string) ($row['username'] ?? 'Uye');
            $masked = $maskUsername($username);
            return [
                'player' => $masked,
                'user_mask' => $masked,
                'totalWinAmount' => (float) ($row['total_win_amount'] ?? 0),
                'total_win_amount' => (float) ($row['total_win_amount'] ?? 0),
                'lastWinAt' => (string) ($row['last_win_at'] ?? ''),
                'last_win_at' => (string) ($row['last_win_at'] ?? ''),
                'gameName' => (string) ($row['last_game_name'] ?? $row['game_name'] ?? ''),
                'game_name' => (string) ($row['last_game_name'] ?? $row['game_name'] ?? ''),
                'providerName' => (string) ($row['last_provider_name'] ?? $row['provider_name'] ?? ''),
                'provider_name' => (string) ($row['last_provider_name'] ?? $row['provider_name'] ?? ''),
                'gameImageUrl' => (string) ($row['image_url'] ?? ''),
                'game_image_url' => (string) ($row['image_url'] ?? ''),
                'game_image' => (string) ($row['image_url'] ?? ''),
                'image_url' => (string) ($row['image_url'] ?? ''),
                'thumbnail_url' => (string) ($row['image_url'] ?? ''),
                'banner' => (string) ($row['image_url'] ?? ''),
                'cover' => (string) ($row['image_url'] ?? ''),
                'source' => (string) ($row['source'] ?? ''),
            ];
        }, $groupedRows);
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'En çok kazananlar',
            'data' => [
                'winners' => $rows,
                'items' => $rows,
                'total' => count($rows),
                'tab' => 'top',
                'winners_tab' => 'top',
                'period' => $period,
                'winners_period' => $period,
            ],
        ]);
    }
    $stmt = $pdo->prepare($winnerSql . ' ORDER BY created_at DESC, sort_id DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = array_map(static function (array $row) use ($maskUsername): array {
        $row = CasinoAggregatorService::normalizeWinnerDisplayRow($row);
        $username = (string) ($row['username'] ?? 'Uye');
        $masked = $maskUsername($username);
        return [
            'player' => $masked,
            'user_mask' => $masked,
            'gameName' => (string) ($row['game_name'] ?? ''),
            'game_name' => (string) ($row['game_name'] ?? ''),
            'providerName' => (string) ($row['provider_name'] ?? ''),
            'provider_name' => (string) ($row['provider_name'] ?? ''),
            'gameId' => (string) ($row['game_id'] ?? ''),
            'game_id' => (string) ($row['game_id'] ?? ''),
            'gameImageUrl' => (string) ($row['image_url'] ?? ''),
            'game_image_url' => (string) ($row['image_url'] ?? ''),
            'game_image' => (string) ($row['image_url'] ?? ''),
            'image_url' => (string) ($row['image_url'] ?? ''),
            'thumbnail_url' => (string) ($row['image_url'] ?? ''),
            'banner' => (string) ($row['banner'] ?? ''),
            'cover' => (string) ($row['image_url'] ?? ''),
            'winAmount' => (float) ($row['win_amount'] ?? 0),
            'win_amount' => (float) ($row['win_amount'] ?? 0),
            'amount' => (float) ($row['win_amount'] ?? 0),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'source' => (string) ($row['source'] ?? ''),
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Kazananlar',
        'data' => [
            'winners' => $rows,
            'items' => $rows,
            'total' => count($rows),
            'tab' => 'recent',
            'winners_tab' => 'recent',
            'period' => $period,
            'winners_period' => $period,
        ],
    ]);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), '42S02')) {
            $emptyWinners($tab ?? 'recent', $period ?? 'day');
        }
        throw $e;
    } catch (Throwable $e) {
        $emptyWinners($tab ?? 'recent', $period ?? 'day');
    }
}
if (in_array($route, ['favorite_slots.php', 'favorite_live_casino.php', 'favorite-slots', 'favorite-live-casino'], true)) {
    $memberRequireLogin();

    if ($method === 'GET') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Favori oyunlar',
            'data' => [
                'items' => [],
                'games' => [],
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => 0,
                    'total_pages' => 1,
                    'has_next' => false,
                    'has_prev' => false,
                ],
            ],
        ]);
    }

    if ($method === 'POST') {
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Favorilere eklendi.',
            'data' => ['favorited' => true, 'already_favorite' => false],
        ]);
    }

    if ($method === 'DELETE') {
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Favorilerden kaldırıldı.',
            'data' => ['favorited' => false, 'removed' => false],
        ]);
    }

    $memberEnvelope(405, [
        'success' => false,
        'code' => 405,
        'message' => 'Method desteklenmiyor.',
    ]);
}
if ($method === 'GET' && in_array($route, ['freespins.php', 'me/freespins', 'profile/freespins'], true)) {
    $memberRequireLogin();
    $tab = trim((string) ($_GET['tab'] ?? ''));
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Freespin listesi',
        'data' => ['items' => [], 'total' => 0, 'tab' => $tab ?: 'yeni'],
    ]);
}

if ($method === 'POST' && in_array($route, ['game_launch.php', 'game-launch'], true)) {
    $input = $memberInput($payload);
    $requestedOpenMode = strtolower(trim((string) ($input['open_mode'] ?? '')));
    if (!in_array($requestedOpenMode, ['iframe', 'redirect'], true)) {
        $requestedOpenMode = '';
    }

    $normalizeLaunchResult = static function (array $result, string $fallbackOpenMode): array {
        if (empty($result['success'])) {
            return $result;
        }

        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        $resolvedOpenMode = strtolower(trim((string) ($data['open_mode'] ?? ($result['open_mode'] ?? $fallbackOpenMode))));
        if (!in_array($resolvedOpenMode, ['iframe', 'redirect'], true)) {
            $resolvedOpenMode = 'iframe';
        }

        $data['open_mode'] = $resolvedOpenMode;
        $result['data'] = $data;
        $result['open_mode'] = $resolvedOpenMode;

        return $result;
    };

    $mode = strtolower(trim((string) ($input['mode'] ?? 'real')));
    $isDemo = in_array($mode, ['fun', 'demo'], true) || !empty($input['demo']) || !empty($input['isDemo']);
    if ($isDemo) {
        $input['mode'] = 'fun';
    }
    $user = null;
    if (!$isDemo) {
        $userId = $memberRequireLogin();
        $user = $memberUserById(AdminDatabase::pdo(), $userId);
        if (!is_array($user)) {
            $memberEnvelope(404, [
                'success' => false,
                'code' => 404,
                'message' => 'Kullanıcı bulunamadı.',
            ]);
        }

        // Kullanıcının oyun başlatırken seçtiği bakiye modu (ana/bonus) — çevrim
        // takibinin hangi bonusa işleneceğini belirler (bkz. WageringService).
        // Best-effort: bu adım asla oyun başlatmayı engellememeli, bu yüzden
        // kendi try/catch'i içinde (aşağıdaki launch try/catch'inin dışında
        // olsa bile hiçbir Throwable dışarı sızmaz).
        try {
            admin_require_project_file('services/WageringService.php');
            $walletChoice = strtolower(trim((string) ($input['wallet'] ?? 'main')));
            WageringService::setActiveWalletMode(
                AdminDatabase::pdo(),
                (int) ($user['id'] ?? 0),
                $walletChoice === 'bonus' ? 'bonus' : 'main'
            );
        } catch (Throwable $walletModeException) {
            error_log('[game-launch] setActiveWalletMode failed: ' . $walletModeException->getMessage());
        }
    }
    try {
        $gameId = trim((string) ($input['game_id'] ?? $input['gameId'] ?? $input['gameid'] ?? ''));

        // Some catalogue links pass the bare provider game id without a
        // "bgaming:" / "aggregator:" prefix. Resolve the owning provider from the
        // database so the launch still routes correctly.
        admin_require_project_file('services/CasinoAggregatorService.php');
        admin_require_project_file('services/GscPlusService.php');
        if (
            $gameId !== ''
            && !BgamingService::ownsGameId($gameId)
            && !CasinoAggregatorService::ownsGameId($gameId)
            && !GscPlusService::ownsGameId($gameId)
        ) {
            $resolvePdo = AdminDatabase::pdo();
            try {
                $bStmt = $resolvePdo->prepare('SELECT 1 FROM bgaming_games WHERE identifier = :g LIMIT 1');
                $bStmt->execute([':g' => $gameId]);
                if ($bStmt->fetchColumn()) {
                    $gameId = 'bgaming:' . $gameId;
                }
            } catch (Throwable) {
            }
            if (!CasinoAggregatorService::ownsGameId($gameId) && !GscPlusService::ownsGameId($gameId)) {
                try {
                    $parts = explode(':', $gameId, 2);
                    if (count($parts) === 2) {
                        $aggStmt = $resolvePdo->prepare(
                            'SELECT 1 FROM casino_aggregator_games WHERE vendor_code = :v AND game_code = :g LIMIT 1'
                        );
                        $aggStmt->execute([':v' => $parts[0], ':g' => $parts[1]]);
                        if ($aggStmt->fetchColumn()) {
                            $gameId = CasinoAggregatorService::buildGameId($parts[0], $parts[1]);
                        } elseif (ctype_digit($parts[0])) {
                            $gscStmt = $resolvePdo->prepare(
                                'SELECT 1 FROM gsc_games WHERE product_code = :p AND game_code = :g LIMIT 1'
                            );
                            $gscStmt->execute([':p' => (int) $parts[0], ':g' => $parts[1]]);
                            if ($gscStmt->fetchColumn()) {
                                $gameId = GscPlusService::buildGameId((int) $parts[0], $parts[1]);
                            }
                        }
                    }
                } catch (Throwable) {
                }
            }
            $input['game_id'] = $gameId;
        }

        if (GscPlusService::ownsGameId($gameId)) {
            $result = GscPlusService::launch(AdminDatabase::pdo(), $user, $input);
            $result = $normalizeLaunchResult($result, $requestedOpenMode);
            $httpCode = !empty($result['success']) ? 200 : (int) ($result['code'] ?? 422);
            if ($httpCode >= 500 && $httpCode !== 503) {
                $httpCode = 422;
            }
            $memberEnvelope($httpCode, $result);
        }

        if (CasinoAggregatorService::ownsGameId($gameId)) {
            $result = CasinoAggregatorService::launch(AdminDatabase::pdo(), $user, $input);
            $result = $normalizeLaunchResult($result, $requestedOpenMode);
            $httpCode = !empty($result['success']) ? 200 : (int) ($result['code'] ?? 422);
            if ($httpCode >= 500 && $httpCode !== 503) {
                $httpCode = 422;
            }
            $memberEnvelope($httpCode, $result);
        }

        if (!BgamingService::ownsGameId($gameId)) {
            $memberEnvelope(404, [
                'success' => false,
                'code' => 404,
                'message' => 'Oyun sağlayıcısı desteklenmiyor.',
                'error' => 'provider_not_found',
            ]);
        }
        $result = BgamingService::launch(AdminDatabase::pdo(), $user, $input);
        $result = $normalizeLaunchResult($result, $requestedOpenMode);
        $httpCode = !empty($result['success']) ? 200 : (int) ($result['code'] ?? 422);
        if ($httpCode >= 500 && $httpCode !== 503) {
            $httpCode = 422;
        }
        $memberEnvelope($httpCode, $result);
    } catch (Throwable $exception) {
        $providerLabel = 'Oyun';
        $launchGameId = trim((string) ($input['game_id'] ?? $input['gameId'] ?? $input['gameid'] ?? ''));
        if ($launchGameId !== '') {
            if (class_exists('GscPlusService', false) && GscPlusService::ownsGameId($launchGameId)) {
                $providerLabel = 'GSC+';
            } elseif (CasinoAggregatorService::ownsGameId($launchGameId)) {
                $providerLabel = 'Casino Aggregator';
            } elseif (BgamingService::ownsGameId($launchGameId)) {
                $providerLabel = 'BGaming';
            }
        }
        $memberEnvelope(422, [
            'success' => false,
            'code' => 422,
            'message' => $providerLabel . ' oyun başlatma hatası: ' . $exception->getMessage(),
            'error' => $exception->getMessage(),
        ]);
    }
}

if ($method === 'GET' && in_array($route, ['profile/spor_bet_detail.php', 'profile/game_history_detail.php'], true)) {
    require_once __DIR__ . '/../includes/profile_detail_html.php';
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();

    if ($route === 'profile/spor_bet_detail.php') {
        $betId = (int) ($_GET['bet_id'] ?? 0);
        member_profile_render_spor_bet_detail($pdo, $userId, $betId);
    }

    $historyId = trim((string) ($_GET['history_id'] ?? ''));
    member_profile_render_game_history_detail($pdo, $userId, $historyId);
}
