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
    // 0 = slot lobby (Casino Aggregator), 1 = live casino (GSC+).
    // Note: kernel aliases live-casino/providers → games_provider.php, so also
    // detect the original path from REQUEST_URI.
    $gameType = (int) ($_GET['game_type'] ?? $_GET['filter_game_type'] ?? 0) === 1 ? 1 : 0;
    $requestUri = strtolower((string) ($_SERVER['REQUEST_URI'] ?? ''));
    if (
        $route === 'live-casino/providers'
        || str_contains($requestUri, 'live-casino/providers')
        || str_contains($requestUri, 'live_casino/providers')
    ) {
        $gameType = 1;
    }
    $source = strtolower(trim((string) ($_GET['source'] ?? '')));
    $providers = [];
    try {
        if ($gameType === 1 && $source === 'gsc') {
            admin_require_project_file('services/LiveCasinoQuery.php');
            $providerExtra = [
                'force_local' => true,
                'source' => 'gsc',
                'gsc_only' => 1,
                'currency' => strtoupper(trim((string) ($_GET['currency'] ?? ''))),
            ];
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
        } elseif ($gameType === 1 || in_array($source, ['livecasino', 'live', 'live_casino'], true)) {
            // CANLI CASINO: all Casino Aggregator live vendors.
            admin_require_project_file('services/SlotGamesQuery.php');
            foreach (SlotGamesQuery::providersForGameType(1) as $name) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }
                $providers[] = [
                    'provider_code' => $name,
                    'provider_name' => $name,
                ];
            }
        } elseif ($source === 'bgaming') {
            admin_require_project_file('services/BgamingGamesQuery.php');
            $providers = BgamingGamesQuery::providers();
        } else {
            // Slot lobby default: Casino Aggregator vendors only.
            $aggStmt = $pdo->prepare(
                "SELECT DISTINCT v.vendor_code AS provider_code, v.vendor_name AS provider_name
                 FROM casino_aggregator_vendors v
                 INNER JOIN casino_aggregator_games g ON g.vendor_code = v.vendor_code
                 WHERE v.is_active = 1 AND g.is_active = 1 AND g.game_type IN (0, 1)
                   AND NOT (" . CasinoAggregatorService::liveVendorSqlMatch('g.vendor_code') . ")
                 ORDER BY v.vendor_name ASC"
            );
            $aggStmt->execute();
            foreach ($aggStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $code = (string) ($row['provider_code'] ?? '');
                if ($code === '') {
                    continue;
                }
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
    // 0 = slot lobby (Casino Aggregator), 1 = live casino (GSC+).
    $gameType = (int) ($_GET['game_type'] ?? $_GET['filter_game_type'] ?? 0) === 1 ? 1 : 0;
    $page     = max(1, (int) ($_GET['page'] ?? 1));
    $limit    = min(200, max(1, (int) ($_GET['limit'] ?? $_GET['per_page'] ?? 30)));
    $offset   = ($page - 1) * $limit;
    $search   = trim((string) ($_GET['search'] ?? $_GET['q'] ?? ''));
    // Accept providers=Name, providers=A,B, and legacy providers[]=A&providers[]=B.
    $providerList = CasinoAggregatorService::providersFromQuery();
    $onlyFeatured = (string) ($_GET['is_featured'] ?? '') === '1'
        || in_array(strtolower((string) ($_GET['sort'] ?? '')), ['popular', 'liked'], true);
    $source = strtolower(trim((string) ($_GET['source'] ?? '')));
    $sort = strtolower(trim((string) ($_GET['sort'] ?? '')));

    // Explicit GSC+ live catalog (legacy override via source=gsc).
    if ($gameType === 1 && $source === 'gsc') {
        admin_require_project_file('services/LiveCasinoQuery.php');
        $liveExtra = [
            'force_local' => true,
            'source' => 'gsc',
            'gsc_only' => true,
            'currency' => strtoupper(trim((string) ($_GET['currency'] ?? ''))),
        ];
        if ($providerList !== []) {
            $providerList = CasinoAggregatorService::canonicalizeProviders(
                $providerList,
                LiveCasinoQuery::providers($liveExtra)
            );
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

    // CANLI CASINO default: all Casino Aggregator live games → aggregator:{vendor}:{game}.
    if ($gameType === 1 || in_array($source, ['livecasino', 'live', 'live_casino'], true)) {
        admin_require_project_file('services/SlotGamesQuery.php');
        $liveAggExtra = ['source' => 'aggregator'];
        if ($providerList !== []) {
            $providerList = CasinoAggregatorService::canonicalizeProviders(
                $providerList,
                SlotGamesQuery::providersForGameType(1)
            );
        }
        $liveResult = SlotGamesQuery::gamesPage(
            1,
            $search,
            $providerList,
            $limit,
            $page,
            $onlyFeatured ? 'popular' : $sort,
            $liveAggExtra
        );
        $games = is_array($liveResult['games'] ?? null) ? $liveResult['games'] : [];
        $pagination = is_array($liveResult['pagination'] ?? null) ? $liveResult['pagination'] : [];
        $total = (int) ($liveResult['total'] ?? ($pagination['total'] ?? count($games)));
        $totalPages = (int) ($pagination['totalPages'] ?? ($total > 0 ? (int) ceil($total / $limit) : 0));
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
                    'hasNext'    => !empty($liveResult['hasNext']) || !empty($pagination['hasNext']),
                    'hasPrev'    => $page > 1,
                ],
            ],
        ]);
    }

    // Dedicated /bgaming page — direct SoftSwiss catalogue (BgamingGamesQuery).
    if ($gameType === 0 && $source === 'bgaming') {
        admin_require_project_file('services/BgamingGamesQuery.php');
        $catalog = BgamingGamesQuery::apiCatalog(
            $search,
            $limit,
            $page,
            $sort === 'all' ? '' : $sort
        );
        $games = is_array($catalog['games'] ?? null) ? $catalog['games'] : [];
        $pagination = is_array($catalog['pagination'] ?? null) ? $catalog['pagination'] : [];
        $total = (int) ($pagination['total'] ?? count($games));
        $totalPages = (int) ($pagination['totalPages'] ?? ($total > 0 ? (int) ceil($total / $limit) : 0));
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
                    'hasNext'    => !empty($pagination['hasNext']),
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
    // Slot lobby default = Casino Aggregator (source empty or aggregator).
    // BGaming is served above via BgamingGamesQuery.
    if ($gameType === 0 && ($source === '' || $source === 'aggregator')) {
        $liveMatch = CasinoAggregatorService::liveVendorSqlMatch('g.vendor_code');
        $typeClause = "((g.game_type IN (0, 1)) AND NOT {$liveMatch})";
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
        $where[]            = '(LOWER(CONVERT(COALESCE(name, \'\') USING utf8mb4)) COLLATE utf8mb4_unicode_ci LIKE :search OR LOWER(CONVERT(COALESCE(provider, \'\') USING utf8mb4)) COLLATE utf8mb4_unicode_ci LIKE :search2)';
        $needle = '%' . mb_strtolower($search, 'UTF-8') . '%';
        $params[':search']  = $needle;
        $params[':search2'] = $needle;
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

        admin_require_project_file('services/SlotGamesQuery.php');
        $sortKey = strtolower(trim((string) ($_GET['sort'] ?? '')));
        $orderBy = SlotGamesQuery::catalogOrderBySql($sortKey, $onlyFeatured);

        $rowsStmt = $pdo->prepare(
            "SELECT game_id, name, provider, provider_code, image_url, image_fallbacks, is_featured, source, row_id, raw_payload
             FROM {$unionSql}{$whereSql}
             ORDER BY {$orderBy}
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
                // Live-dealer tables are streamed from a studio and have no demo
                // build; offering one only produces a provider launch error.
                'has_demo'      => $gameType !== 1,
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
    admin_require_project_file('services/GscPlusService.php');

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

    if ($source === 'all' || $source === 'live_casino') {
        try {
            $gscStmt = $pdo->prepare(
                "SELECT t.id, t.transaction_id, t.round_id, t.product_code, t.game_code, t.action,
                        COALESCE(g.game_name, t.game_code, CAST(t.product_code AS CHAR)) AS game_name,
                        COALESCE(NULLIF(g.provider, ''), NULLIF(g.product_name, ''), CAST(t.product_code AS CHAR)) AS provider_name,
                        CAST(t.product_code AS CHAR) AS provider_code,
                        t.amount, t.bet_amount, t.prize_amount, t.after_balance, t.created_at
                 FROM gsc_transactions t
                 LEFT JOIN gsc_games g
                    ON g.product_code = t.product_code AND g.game_code = t.game_code
                 WHERE t.user_id = :uid
                 ORDER BY t.id DESC
                 LIMIT {$fetchLimit}"
            );
            $gscStmt->execute([':uid' => $userId]);
            foreach ($gscStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $action = strtoupper(trim((string) ($row['action'] ?? '')));
                $normalizedTxnType = in_array($action, ['SETTLED', 'WIN', 'BONUS', 'PRIZE'], true)
                    ? 'win'
                    : (in_array($action, ['CANCEL', 'ROLLBACK', 'REFUND', 'PRESERVE_REFUND'], true) ? 'refund' : 'bet');
                $amount = abs((float) ($row['amount'] ?? 0));
                $betAmount = abs((float) ($row['bet_amount'] ?? 0));
                $winAmount = abs((float) ($row['prize_amount'] ?? 0));
                $productCode = (int) ($row['product_code'] ?? 0);
                $gameCode = trim((string) ($row['game_code'] ?? ''));
                $gameId = $productCode > 0 && $gameCode !== ''
                    ? GscPlusService::buildGameId($productCode, $gameCode)
                    : ('gsc:' . $productCode);
                $rows[] = [
                    'id' => 'gsc:' . (string) ($row['id'] ?? ''),
                    'history_id' => 'gsc:' . (string) ($row['id'] ?? ''),
                    'transactionId' => (string) ($row['transaction_id'] ?? ''),
                    'transaction_id' => (string) ($row['transaction_id'] ?? ''),
                    'providerTxnId' => (string) ($row['transaction_id'] ?? ''),
                    'provider_txn_id' => (string) ($row['transaction_id'] ?? ''),
                    'roundId' => (string) ($row['round_id'] ?? ''),
                    'round_id' => (string) ($row['round_id'] ?? ''),
                    'gameId' => $gameId,
                    'game_id' => $gameId,
                    'gameName' => (string) ($row['game_name'] ?? ''),
                    'game_name' => (string) ($row['game_name'] ?? ''),
                    'providerCode' => (string) ($row['provider_code'] ?? ''),
                    'provider_code' => (string) ($row['provider_code'] ?? ''),
                    'providerName' => (string) ($row['provider_name'] ?? 'GSC+'),
                    'provider_name' => (string) ($row['provider_name'] ?? 'GSC+'),
                    'category' => 'live_casino',
                    'source' => 'live_casino',
                    'txnType' => $normalizedTxnType,
                    'txn_type' => $normalizedTxnType,
                    'status' => 'completed',
                    'betAmount' => $normalizedTxnType === 'bet' ? ($betAmount > 0 ? $betAmount : $amount) : 0.0,
                    'bet_amount' => $normalizedTxnType === 'bet' ? ($betAmount > 0 ? $betAmount : $amount) : 0.0,
                    'winAmount' => $normalizedTxnType !== 'bet' ? ($winAmount > 0 ? $winAmount : $amount) : 0.0,
                    'win_amount' => $normalizedTxnType !== 'bet' ? ($winAmount > 0 ? $winAmount : $amount) : 0.0,
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
    admin_require_project_file('services/CasinoAggregatorService.php');
    $q      = trim((string) ($_GET['q'] ?? $_GET['search'] ?? ''));
    $limit  = min(100, max(1, (int) ($_GET['limit'] ?? 30)));
    $page   = max(1, (int) ($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;
    if ($q === '') {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Arama terimi gereklidir']);
    }
    $like = '%' . $q . '%';
    $liveMatch = CasinoAggregatorService::liveVendorSqlMatch('g.vendor_code');
    // CONVERT+COLLATE on every string branch — utf8mb4_bin vs unicode_ci mixes break UNION.
    $stmtB = $pdo->prepare("
        SELECT CONVERT(CONCAT('aggregator:', g.vendor_code, ':', g.game_code) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS game_id,
               CONVERT(g.game_name USING utf8mb4) COLLATE utf8mb4_unicode_ci AS game_name,
               CONVERT(g.vendor_code USING utf8mb4) COLLATE utf8mb4_unicode_ci AS provider_code,
               CONVERT(COALESCE(NULLIF(v.vendor_name, ''), g.vendor_code) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS provider_name,
               CONVERT('slot' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS game_category,
               CONVERT(COALESCE(NULLIF(g.image_url, ''), '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS image_url,
               CONVERT('aggregator' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source
        FROM casino_aggregator_games g
        INNER JOIN casino_aggregator_vendors v ON v.vendor_code = g.vendor_code
        WHERE g.is_active = 1 AND v.is_active = 1
          AND g.game_type IN (0, 1) AND NOT ({$liveMatch})
          AND (g.game_name LIKE :q OR v.vendor_name LIKE :q2 OR g.vendor_code LIKE :q3)
        UNION ALL
        SELECT CONVERT(CONCAT('gsc:', g.product_code, ':', g.game_code) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS game_id,
               CONVERT(g.game_name USING utf8mb4) COLLATE utf8mb4_unicode_ci AS game_name,
               CONVERT(CAST(g.product_code AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS provider_code,
               CONVERT(COALESCE(NULLIF(g.provider, ''), NULLIF(g.product_name, ''), CAST(g.product_code AS CHAR)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS provider_name,
               CONVERT('live_casino' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS game_category,
               CONVERT(COALESCE(NULLIF(g.image_url, ''), '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS image_url,
               CONVERT('gsc' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source
        FROM gsc_games g
        WHERE g.is_active = 1
          AND UPPER(g.game_type) IN ('LIVE_CASINO','LIVE_CASINO_PREMIUM')
          AND (g.game_name LIKE :q4 OR g.provider LIKE :q5 OR g.game_code LIKE :q6)
        ORDER BY game_name ASC
        LIMIT :lim OFFSET :off
    ");
    $stmtB->bindValue(':q', $like);
    $stmtB->bindValue(':q2', $like);
    $stmtB->bindValue(':q3', $like);
    $stmtB->bindValue(':q4', $like);
    $stmtB->bindValue(':q5', $like);
    $stmtB->bindValue(':q6', $like);
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
        $gscPeriodSql = '';
    } else {
        $bgamingPeriodSql = match ($period) {
            'week' => ' AND t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
            'month' => ' AND t.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)',
            'all' => '',
            default => ' AND t.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)',
        };
        $aggregatorPeriodSql = $bgamingPeriodSql;
        $gscPeriodSql = $bgamingPeriodSql;
    }

    $winnerSql = "SELECT *
                  FROM (
                      SELECT
                          CONVERT(COALESCE(u.username, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS username,
                          t.user_id,
                          CONVERT(CONCAT('bgaming:', COALESCE(t.game_identifier, '')) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS game_id,
                          CONVERT(COALESCE(g.title, t.game_identifier, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS game_name,
                          CONVERT(COALESCE(NULLIF(g.provider, ''), 'BGaming') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS provider_name,
                          CONVERT(COALESCE(g.thumbnail_url, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS image_url,
                          CONVERT(COALESCE(g.thumbnail_url, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS banner,
                          t.amount AS win_amount,
                          t.created_at AS created_at,
                          t.id AS sort_id,
                          CONVERT('bgaming' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source,
                          CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS raw_payload
                      FROM bgaming_transactions t
                      LEFT JOIN users u ON u.id = t.user_id
                      LEFT JOIN bgaming_games g ON g.identifier = t.game_identifier
                      WHERE t.txn_type IN ('win', 'promo_win', 'freespins_win') AND t.amount > 0{$bgamingPeriodSql}
                      UNION ALL
                      SELECT
                          CONVERT(COALESCE(u.username, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS username,
                          t.user_id,
                          CONVERT(CONCAT('aggregator:', t.vendor_code, ':', t.game_code) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS game_id,
                          CONVERT(COALESCE(g.game_name, t.game_code, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS game_name,
                          CONVERT(COALESCE(NULLIF(v.vendor_name, ''), t.vendor_code, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS provider_name,
                          CONVERT(COALESCE(NULLIF(g.image_url, ''), '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS image_url,
                          CONVERT(COALESCE(NULLIF(g.image_url, ''), '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS banner,
                          ABS(t.amount) AS win_amount,
                          t.created_at AS created_at,
                          t.id AS sort_id,
                          CONVERT('aggregator' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source,
                          CONVERT(COALESCE(CAST(g.raw_payload AS CHAR), '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS raw_payload
                      FROM casino_aggregator_transactions t
                      LEFT JOIN users u ON u.id = t.user_id
                      LEFT JOIN casino_aggregator_games g
                          ON g.vendor_code = t.vendor_code AND g.game_code = t.game_code
                      LEFT JOIN casino_aggregator_vendors v ON v.vendor_code = t.vendor_code
                      WHERE t.txn_type = 'win' AND t.amount > 0{$aggregatorPeriodSql}
                      UNION ALL
                      SELECT
                          CONVERT(COALESCE(u.username, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS username,
                          t.user_id,
                          CONVERT(CONCAT('gsc:', t.product_code, ':', COALESCE(t.game_code, '')) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS game_id,
                          CONVERT(COALESCE(g.game_name, t.game_code, CAST(t.product_code AS CHAR), '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS game_name,
                          CONVERT(COALESCE(NULLIF(g.provider, ''), NULLIF(g.product_name, ''), CAST(t.product_code AS CHAR), '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS provider_name,
                          CONVERT(COALESCE(NULLIF(g.image_url, ''), '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS image_url,
                          CONVERT(COALESCE(NULLIF(g.image_url, ''), '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS banner,
                          ABS(COALESCE(NULLIF(t.prize_amount, 0), t.amount)) AS win_amount,
                          t.created_at AS created_at,
                          t.id AS sort_id,
                          CONVERT('gsc' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source,
                          CONVERT(COALESCE(CAST(g.raw_payload AS CHAR), '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS raw_payload
                      FROM gsc_transactions t
                      LEFT JOIN users u ON u.id = t.user_id
                      LEFT JOIN gsc_games g
                          ON g.product_code = t.product_code AND g.game_code = t.game_code
                      WHERE UPPER(t.action) IN ('SETTLED', 'WIN', 'BONUS', 'PRIZE')
                        AND (COALESCE(t.prize_amount, 0) > 0 OR COALESCE(t.amount, 0) > 0)
                        {$gscPeriodSql}
                  ) winners_union";

    $maskUsername = static function (mixed $value): string {
        $username = (string) ($value ?: 'Uye');
        return $username !== ''
            ? substr($username, 0, 2) . str_repeat('*', max(3, strlen($username) - 2))
            : 'Uye***';
    };

    $normalizeWinnerRowSafe = static function (array $row): array {
        try {
            $row = CasinoAggregatorService::normalizeWinnerDisplayRow($row);
        } catch (Throwable) {
            // Keep SQL row usable even if media hydration fails.
        }
        $source = strtolower(trim((string) ($row['source'] ?? '')));
        $gameId = trim((string) ($row['game_id'] ?? ''));
        if ($source === 'bgaming' && $gameId !== '' && !str_starts_with(strtolower($gameId), 'bgaming:')) {
            $gameId = 'bgaming:' . $gameId;
            $row['game_id'] = $gameId;
        }
        $cover = trim((string) ($row['image_url'] ?? $row['cover'] ?? ''));
        $fallbacks = [];
        if (is_array($row['cover_fallbacks'] ?? null)) {
            $fallbacks = array_values(array_filter(array_map('strval', $row['cover_fallbacks'])));
        } elseif (is_array($row['image_fallbacks'] ?? null)) {
            $fallbacks = array_values(array_filter(array_map('strval', $row['image_fallbacks'])));
        }
        if ($cover !== '' && class_exists('CasinoAggregatorService', false)) {
            $png = CasinoAggregatorService::rewriteMediaUrlToPng($cover);
            if ($png !== '' && $png !== $cover) {
                array_unshift($fallbacks, $png);
                $cover = $png;
            }
            if ($fallbacks === []) {
                $fallbacks = CasinoAggregatorService::expandFormatFallbacks([$cover]);
            } else {
                $fallbacks = CasinoAggregatorService::expandFormatFallbacks(array_merge([$cover], $fallbacks));
            }
        }
        if ($cover === '' && $fallbacks !== []) {
            $cover = (string) $fallbacks[0];
        }
        $row['image_url'] = $cover;
        $row['banner'] = $cover;
        $row['cover'] = $cover;
        $row['cover_fallbacks'] = $fallbacks;
        $row['image_fallbacks'] = $fallbacks;
        return $row;
    };

    $mapWinnerRecent = static function (array $row) use ($maskUsername, $normalizeWinnerRowSafe): array {
        $row = $normalizeWinnerRowSafe($row);
        $username = (string) ($row['username'] ?? 'Uye');
        $masked = $maskUsername($username);
        $cover = (string) ($row['cover'] ?? $row['image_url'] ?? '');
        $fallbacks = is_array($row['cover_fallbacks'] ?? null) ? array_values($row['cover_fallbacks']) : [];
        return [
            'player' => $masked,
            'user_mask' => $masked,
            'gameName' => (string) ($row['game_name'] ?? ''),
            'game_name' => (string) ($row['game_name'] ?? ''),
            'providerName' => (string) ($row['provider_name'] ?? ''),
            'provider_name' => (string) ($row['provider_name'] ?? ''),
            'gameId' => (string) ($row['game_id'] ?? ''),
            'game_id' => (string) ($row['game_id'] ?? ''),
            'gameImageUrl' => $cover,
            'game_image_url' => $cover,
            'game_image' => $cover,
            'image_url' => $cover,
            'thumbnail_url' => $cover,
            'banner' => $cover,
            'cover' => $cover,
            'cover_fallbacks' => $fallbacks,
            'image_fallbacks' => $fallbacks,
            'winAmount' => (float) ($row['win_amount'] ?? 0),
            'win_amount' => (float) ($row['win_amount'] ?? 0),
            'amount' => (float) ($row['win_amount'] ?? 0),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'source' => (string) ($row['source'] ?? ''),
        ];
    };

    $interleaveWinnerSources = static function (array $rows, int $limit): array {
        if ($rows === [] || $limit <= 0) {
            return [];
        }
        $buckets = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $source = strtolower(trim((string) ($row['source'] ?? 'other')));
            if ($source === '') {
                $source = 'other';
            }
            $buckets[$source][] = $row;
        }
        $order = ['aggregator', 'bgaming', 'gsc'];
        foreach (array_keys($buckets) as $source) {
            if (!in_array($source, $order, true)) {
                $order[] = $source;
            }
        }
        $out = [];
        $cursor = [];
        foreach ($order as $source) {
            $cursor[$source] = 0;
        }
        while (count($out) < $limit) {
            $added = false;
            foreach ($order as $source) {
                $i = $cursor[$source] ?? 0;
                if (!isset($buckets[$source][$i])) {
                    continue;
                }
                $out[] = $buckets[$source][$i];
                $cursor[$source] = $i + 1;
                $added = true;
                if (count($out) >= $limit) {
                    break;
                }
            }
            if (!$added) {
                break;
            }
        }

        return $out;
    };

    if ($tab === 'top') {
        $stmt = $pdo->prepare($winnerSql . ' ORDER BY created_at DESC, sort_id DESC LIMIT 2000');
        $stmt->execute();
        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $row = $normalizeWinnerRowSafe($row);
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
            $cover = (string) ($row['cover'] ?? $row['image_url'] ?? '');
            $fallbacks = is_array($row['cover_fallbacks'] ?? null) ? array_values($row['cover_fallbacks']) : [];
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
                'gameImageUrl' => $cover,
                'game_image_url' => $cover,
                'game_image' => $cover,
                'image_url' => $cover,
                'thumbnail_url' => $cover,
                'banner' => $cover,
                'cover' => $cover,
                'cover_fallbacks' => $fallbacks,
                'image_fallbacks' => $fallbacks,
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
    $poolLimit = max($limit * 8, 80);
    $poolLimit = min(500, $poolLimit);
    $stmt = $pdo->prepare($winnerSql . ' ORDER BY created_at DESC, sort_id DESC LIMIT :limit');
    $stmt->bindValue(':limit', $poolLimit, PDO::PARAM_INT);
    $stmt->execute();
    $poolRows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (is_array($row)) {
            $poolRows[] = $row;
        }
    }
    $selected = $interleaveWinnerSources($poolRows, $limit);
    // Fallback: if interleave somehow empty, keep chronological slice.
    if ($selected === [] && $poolRows !== []) {
        $selected = array_slice($poolRows, 0, $limit);
    }
    $rows = array_map($mapWinnerRecent, $selected);
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
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    $kind = in_array($route, ['favorite_live_casino.php', 'favorite-live-casino'], true) ? 'live' : 'slot';

    if ($method === 'GET') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;
        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM member_game_favorites WHERE user_id = :user_id AND kind = :kind'
        );
        $countStmt->execute(['user_id' => $userId, 'kind' => $kind]);
        $total = (int) $countStmt->fetchColumn();
        $listStmt = $pdo->prepare(
            'SELECT game_id, game_name, image_url, provider, created_at
             FROM member_game_favorites
             WHERE user_id = :user_id AND kind = :kind
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset'
        );
        $listStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $listStmt->bindValue(':kind', $kind);
        $listStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $listStmt->execute();
        $games = array_map(static function (array $row): array {
            return [
                'game_id' => (string) ($row['game_id'] ?? ''),
                'name' => (string) ($row['game_name'] ?? ''),
                'game_name' => (string) ($row['game_name'] ?? ''),
                'image_url' => (string) ($row['image_url'] ?? ''),
                'cover' => (string) ($row['image_url'] ?? ''),
                'provider' => (string) ($row['provider'] ?? ''),
                'created_at' => $row['created_at'] ?? null,
            ];
        }, $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        $totalPages = max(1, (int) ceil($total / $limit));
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Favori oyunlar',
            'data' => [
                'items' => $games,
                'games' => $games,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1,
                ],
            ],
        ]);
    }

    if ($method === 'POST') {
        $input = $memberInput($payload);
        $gameId = trim((string) ($input['game_id'] ?? $input['gameId'] ?? ''));
        if ($gameId === '') {
            $memberEnvelope(422, [
                'success' => false,
                'code' => 422,
                'message' => 'Oyun kimliği zorunludur.',
            ]);
        }
        $existsStmt = $pdo->prepare(
            'SELECT 1 FROM member_game_favorites
             WHERE user_id = :user_id AND kind = :kind AND game_id = :game_id LIMIT 1'
        );
        $existsStmt->execute(['user_id' => $userId, 'kind' => $kind, 'game_id' => $gameId]);
        $alreadyFavorite = (bool) $existsStmt->fetchColumn();
        $saveStmt = $pdo->prepare(
            'INSERT INTO member_game_favorites
                (user_id, kind, game_id, game_name, image_url, provider)
             VALUES
                (:user_id, :kind, :game_id, :game_name, :image_url, :provider)
             ON DUPLICATE KEY UPDATE
                game_name = VALUES(game_name),
                image_url = VALUES(image_url),
                provider = VALUES(provider),
                updated_at = CURRENT_TIMESTAMP'
        );
        $saveStmt->execute([
            'user_id' => $userId,
            'kind' => $kind,
            'game_id' => substr($gameId, 0, 120),
            'game_name' => substr(trim((string) ($input['game_name'] ?? $input['name'] ?? '')), 0, 255),
            'image_url' => substr(trim((string) ($input['image_url'] ?? $input['cover'] ?? '')), 0, 500),
            'provider' => substr(trim((string) ($input['provider'] ?? '')), 0, 120),
        ]);
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Favorilere eklendi.',
            'data' => ['favorited' => true, 'already_favorite' => $alreadyFavorite],
        ]);
    }

    if ($method === 'DELETE') {
        $input = $memberInput($payload);
        $gameId = trim((string) ($_GET['game_id'] ?? $_GET['gameId'] ?? $input['game_id'] ?? $input['gameId'] ?? ''));
        if ($gameId === '') {
            $memberEnvelope(422, [
                'success' => false,
                'code' => 422,
                'message' => 'Oyun kimliği zorunludur.',
            ]);
        }
        $deleteStmt = $pdo->prepare(
            'DELETE FROM member_game_favorites
             WHERE user_id = :user_id AND kind = :kind AND game_id = :game_id'
        );
        $deleteStmt->execute(['user_id' => $userId, 'kind' => $kind, 'game_id' => $gameId]);
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Favorilerden kaldırıldı.',
            'data' => ['favorited' => false, 'removed' => $deleteStmt->rowCount() > 0],
        ]);
    }

    $memberEnvelope(405, [
        'success' => false,
        'code' => 405,
        'message' => 'Method desteklenmiyor.',
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
        $input['demo'] = true;
        $input['isDemo'] = true;
    }
    $user = null;
    if ($isDemo) {
        // Demo is public — never pass the real user to GetGameUrl (numeric userCode
        // would debit real/agent balances). Optional JWT id is kept only for demo
        // wallet callback routing when the provider echoes the member id.
        try {
            if (isset($memberJwtOptionalUserId) && is_callable($memberJwtOptionalUserId)) {
                $optionalUserId = (int) ($memberJwtOptionalUserId(AdminDatabase::pdo()) ?? 0);
                if ($optionalUserId > 0) {
                    $input['demo_member_id'] = $optionalUserId;
                }
            }
        } catch (Throwable) {
        }
        $user = null;
    } else {
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
        // "gsc:" / "bgaming:" / "aggregator:" prefix. Resolve the owning provider
        // from the database so the launch still routes correctly.
        admin_require_project_file('services/CasinoAggregatorService.php');
        admin_require_project_file('services/GscPlusService.php');
        if (
            $gameId !== ''
            && !GscPlusService::ownsGameId($gameId)
            && !BgamingService::ownsGameId($gameId)
            && !CasinoAggregatorService::ownsGameId($gameId)
        ) {
            $resolvePdo = AdminDatabase::pdo();
            try {
                $parts = explode(':', $gameId, 2);
                if (count($parts) === 2 && ctype_digit($parts[0])) {
                    $gscStmt = $resolvePdo->prepare(
                        'SELECT 1 FROM gsc_games WHERE product_code = :p AND game_code = :g LIMIT 1'
                    );
                    $gscStmt->execute([':p' => (int) $parts[0], ':g' => $parts[1]]);
                    if ($gscStmt->fetchColumn()) {
                        $gameId = GscPlusService::buildGameId((int) $parts[0], $parts[1]);
                    }
                }
            } catch (Throwable) {
            }
            try {
                $bStmt = $resolvePdo->prepare('SELECT 1 FROM bgaming_games WHERE identifier = :g LIMIT 1');
                $bStmt->execute([':g' => $gameId]);
                if ($bStmt->fetchColumn()) {
                    $gameId = 'bgaming:' . $gameId;
                }
            } catch (Throwable) {
            }
            if (!CasinoAggregatorService::ownsGameId($gameId)) {
                try {
                    $parts = explode(':', $gameId, 2);
                    if (count($parts) === 2) {
                        $aggStmt = $resolvePdo->prepare(
                            'SELECT 1 FROM casino_aggregator_games WHERE vendor_code = :v AND game_code = :g LIMIT 1'
                        );
                        $aggStmt->execute([':v' => $parts[0], ':g' => $parts[1]]);
                        if ($aggStmt->fetchColumn()) {
                            $gameId = CasinoAggregatorService::buildGameId($parts[0], $parts[1]);
                        }
                    }

                    // Favorites/legacy links may send bare game_code without prefix.
                    if (!CasinoAggregatorService::ownsGameId($gameId)) {
                        $bareCode = trim((string) $gameId);
                        if ($bareCode !== '' && strpos($bareCode, ':') === false) {
                            $aggByCode = $resolvePdo->prepare(
                                'SELECT vendor_code, game_code
                                 FROM casino_aggregator_games
                                 WHERE game_code = :g
                                 ORDER BY is_active DESC, id DESC
                                 LIMIT 1'
                            );
                            $aggByCode->execute([':g' => $bareCode]);
                            $aggRow = $aggByCode->fetch(PDO::FETCH_ASSOC);
                            if (is_array($aggRow)) {
                                $vendor = trim((string) ($aggRow['vendor_code'] ?? ''));
                                $code = trim((string) ($aggRow['game_code'] ?? ''));
                                if ($vendor !== '' && $code !== '') {
                                    $gameId = CasinoAggregatorService::buildGameId($vendor, $code);
                                }
                            }
                        }
                    }
                } catch (Throwable) {
                }
            }
            $input['game_id'] = $gameId;
        }

        if ($isDemo && GscPlusService::ownsGameId($gameId)) {
            $memberEnvelope(422, [
                'success' => false,
                'code' => 422,
                'error' => 'gsc_demo_unsupported',
                'message' => 'Canlı casino oyunları demo modunda açılamaz. Giriş yaparak oynayın.',
            ]);
        }

        if (GscPlusService::ownsGameId($gameId)) {
            $result = GscPlusService::launch(AdminDatabase::pdo(), $user, $input);
            $result = $normalizeLaunchResult($result, $requestedOpenMode !== '' ? $requestedOpenMode : 'redirect');
            $httpCode = !empty($result['success']) ? 200 : (int) ($result['code'] ?? 422);
            if ($httpCode >= 500 && $httpCode !== 503) {
                $httpCode = 422;
            }
            $memberEnvelope($httpCode, $result);
        }

        if (CasinoAggregatorService::ownsGameId($gameId)) {
            $result = CasinoAggregatorService::launch(AdminDatabase::pdo(), $user, $input);
            $result = $normalizeLaunchResult(
                $result,
                $requestedOpenMode !== '' ? $requestedOpenMode : 'iframe'
            );
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
        // Desktop play shell expects iframe; callers may still override via open_mode.
        $result = $normalizeLaunchResult(
            $result,
            $requestedOpenMode !== '' ? $requestedOpenMode : 'iframe'
        );
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
        error_log('[game-launch] ' . $providerLabel . ' failed: ' . $exception->getMessage());
        $memberEnvelope(422, [
            'success' => false,
            'code' => 422,
            'message' => $providerLabel . ' oyunu şu an başlatılamadı. Lütfen tekrar deneyin.',
        ]);
    }
}

if ($method === 'GET' && in_array($route, ['profile/spor_bet_detail.php', 'profile/game_history_detail.php'], true)) {
    require_once __DIR__ . '/../includes/profile_detail_html.php';
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();

    if ($route === 'profile/spor_bet_detail.php') {
        $betId = (int) ($_GET['bet_id'] ?? 0);
        admin_member_profile_render_spor_bet_detail($pdo, $userId, $betId);
    }

    $historyId = trim((string) ($_GET['history_id'] ?? ''));
    admin_member_profile_render_game_history_detail($pdo, $userId, $historyId);
}
