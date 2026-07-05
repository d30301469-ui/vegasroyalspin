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
    // 0 = slot lobby (BGaming + Drakon casino), 1 = live casino (Drakon live).
    $gameType = (int) ($_GET['game_type'] ?? $_GET['filter_game_type'] ?? 0) === 1 ? 1 : 0;
    if ($route === 'live-casino/providers') {
        $gameType = 1;
    }
    $providers = [];
    try {
        $union = [];
        if ($gameType === 0) {
            // BGaming catalogue is slot-only.
            $union[] = "SELECT DISTINCT provider AS provider_code, provider AS provider_name
                FROM bgaming_games
                WHERE is_active = 1 AND provider <> ''";
        }
        $union[] = "SELECT DISTINCT provider_name AS provider_code, provider_name
            FROM drakon_games
            WHERE is_active = 1 AND game_type = {$gameType} AND provider_name <> ''";
        $sql   = 'SELECT provider_code, provider_name FROM (' . implode(' UNION ', $union) . ') p ORDER BY provider_name ASC';
        $pStmt = $pdo->query($sql);
        $providers = $pStmt ? $pStmt->fetchAll(PDO::FETCH_ASSOC) : [];
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
    // 0 = slot lobby (BGaming + Drakon casino), 1 = live casino (Drakon live).
    $gameType = (int) ($_GET['game_type'] ?? $_GET['filter_game_type'] ?? 0) === 1 ? 1 : 0;
    $page     = max(1, (int) ($_GET['page'] ?? 1));
    $limit    = min(200, max(1, (int) ($_GET['limit'] ?? $_GET['per_page'] ?? 30)));
    $offset   = ($page - 1) * $limit;
    $search   = trim((string) ($_GET['search'] ?? $_GET['q'] ?? ''));
    $provider = trim((string) ($_GET['provider'] ?? $_GET['provider_code'] ?? ''));
    $onlyFeatured = (string) ($_GET['is_featured'] ?? '') === '1'
        || in_array(strtolower((string) ($_GET['sort'] ?? '')), ['popular', 'liked'], true);

    $union = [];
    if ($gameType === 0) {
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
    $drakonGameTypeWhere = $gameType === 1
        ? "(COALESCE(game_type, 0) = 1 OR LOWER(COALESCE(type, '')) = 'live')"
        : "(COALESCE(game_type, 0) <> 1 AND LOWER(COALESCE(type, '')) <> 'live')";

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
        WHERE is_active = 1 AND {$drakonGameTypeWhere}";
    $unionSql = '(' . implode(' UNION ALL ', $union) . ') AS catalog';

    $where  = [];
    $params = [];
    if ($search !== '') {
        $where[]            = '(name LIKE :search OR provider LIKE :search2)';
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
        $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $featured = (int) ($r['is_featured'] ?? 0);
            $allGames[] = [
                'id'            => (string) ($r['row_id'] ?? ''),
                'game_id'       => (string) ($r['game_id'] ?? ''),
                'name'          => (string) ($r['name'] ?? ''),
                'title'         => (string) ($r['name'] ?? ''),
                'image_url'     => (string) ($r['image_url'] ?? ''),
                'thumbnail_url' => (string) ($r['image_url'] ?? ''),
                'provider'      => (string) ($r['provider'] ?? ''),
                'provider_code' => (string) ($r['provider_code'] ?? ''),
                'is_featured'   => $featured,
                'is_popular'    => $featured === 1,
                'has_demo'      => true,
                'category'      => $gameType === 1 ? 'live-casino' : 'slots',
                'game_type'     => $gameType,
                'source'        => (string) ($r['source'] ?? ''),
            ];
        }
    } catch (Throwable) {}

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
    DrakonService::bootstrap($pdo);
    BgamingService::bootstrap($pdo);

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

    try {
        $where = ['t.user_id = :uid'];
        if ($source === 'live_casino') {
            $where[] = "(COALESCE(g.type, '') = 'live' OR COALESCE(g.game_type, 0) = 1)";
        } elseif ($source === 'slot') {
            $where[] = "(COALESCE(g.type, '') <> 'live' AND COALESCE(g.game_type, 0) <> 1)";
        }

        $stmt = $pdo->prepare(
            "SELECT
                t.id,
                t.transaction_id,
                t.related_transaction_id,
                t.session_id,
                t.round_id,
                t.game_id,
                COALESCE(NULLIF(t.game_name, ''), g.game_name, t.game_id) AS game_name,
                COALESCE(g.provider_code, '') AS provider_code,
                COALESCE(NULLIF(t.provider_name, ''), g.provider_name, '') AS provider_name,
                COALESCE(g.type, 'casino') AS game_category,
                COALESCE(g.game_type, 0) AS game_type,
                t.txn_type,
                t.status,
                t.bet_amount,
                t.win_amount,
                t.after_balance AS balance_after,
                t.created_at
             FROM drakon_transactions t
             LEFT JOIN drakon_games g ON g.game_id = t.game_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY t.id DESC
             LIMIT {$fetchLimit}"
        );
        $stmt->execute([':uid' => $userId]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $category = ((string) ($row['game_category'] ?? 'casino') === 'live' || (int) ($row['game_type'] ?? 0) === 1)
                ? 'live_casino'
                : 'slot';

            $rows[] = [
                'id' => 'drakon:' . (string) ($row['id'] ?? ''),
                'history_id' => 'drakon:' . (string) ($row['id'] ?? ''),
                'transactionId' => (string) ($row['transaction_id'] ?? ''),
                'transaction_id' => (string) ($row['transaction_id'] ?? ''),
                'providerTxnId' => (string) ($row['transaction_id'] ?? ''),
                'provider_txn_id' => (string) ($row['transaction_id'] ?? ''),
                'relatedTransactionId' => (string) ($row['related_transaction_id'] ?? ''),
                'related_transaction_id' => (string) ($row['related_transaction_id'] ?? ''),
                'sessionToken' => (string) ($row['session_id'] ?? ''),
                'session_id' => (string) ($row['session_id'] ?? ''),
                'roundId' => (string) ($row['round_id'] ?? ''),
                'round_id' => (string) ($row['round_id'] ?? ''),
                'gameId' => (string) ($row['game_id'] ?? ''),
                'game_id' => (string) ($row['game_id'] ?? ''),
                'gameName' => (string) ($row['game_name'] ?? ''),
                'game_name' => (string) ($row['game_name'] ?? ''),
                'providerCode' => (string) ($row['provider_code'] ?? ''),
                'provider_code' => (string) ($row['provider_code'] ?? ''),
                'providerName' => (string) ($row['provider_name'] ?? ''),
                'provider_name' => (string) ($row['provider_name'] ?? ''),
                'category' => $category,
                'source' => $category,
                'txnType' => (string) ($row['txn_type'] ?? 'bet'),
                'txn_type' => (string) ($row['txn_type'] ?? 'bet'),
                'status' => (string) ($row['status'] ?? ''),
                'betAmount' => (float) ($row['bet_amount'] ?? 0),
                'bet_amount' => (float) ($row['bet_amount'] ?? 0),
                'winAmount' => (float) ($row['win_amount'] ?? 0),
                'win_amount' => (float) ($row['win_amount'] ?? 0),
                'balanceAfter' => (float) ($row['balance_after'] ?? 0),
                'balance_after' => (float) ($row['balance_after'] ?? 0),
                'createdAt' => (string) ($row['created_at'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'wallet' => 'casino',
            ];
        }
    } catch (Throwable) {}

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
        UNION ALL
        SELECT CONCAT('drakon:', game_id) AS game_id, game_name, provider_name AS provider_code,
               provider_name, 'slot' AS game_category,
               COALESCE(image_url, banner, '') AS image_url, 'drakon' AS source
        FROM drakon_games
        WHERE game_name LIKE :q3 OR provider_name LIKE :q4
        ORDER BY game_name ASC
        LIMIT :lim OFFSET :off
    ");
    $stmtB->bindValue(':q', $like);
    $stmtB->bindValue(':q2', $like);
    $stmtB->bindValue(':q3', $like);
    $stmtB->bindValue(':q4', $like);
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
    } else {
        $bgamingPeriodSql = match ($period) {
            'week' => ' AND t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
            'month' => ' AND t.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)',
            'all' => '',
            default => ' AND t.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)',
        };
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
                          'bgaming' AS source
                      FROM bgaming_transactions t
                      LEFT JOIN users u ON u.id = t.user_id
                      LEFT JOIN bgaming_games g ON g.identifier = t.game_identifier
                      WHERE t.txn_type IN ('win', 'promo_win', 'freespins_win') AND t.amount > 0{$bgamingPeriodSql}
                  UNION ALL
                  SELECT
                          u.username,
                          dt.user_id,
                          dt.game_id,
                          COALESCE(dt.game_name, dt.game_id) AS game_name,
                          COALESCE(NULLIF(dt.provider_name, ''), 'Drakon') AS provider_name,
                          COALESCE(dt.image_url, '') AS image_url,
                          COALESCE(dt.image_url, '') AS banner,
                          dt.win_amount AS win_amount,
                          dt.created_at,
                          dt.id AS sort_id,
                          'drakon' AS source
                      FROM drakon_transactions dt
                      LEFT JOIN users u ON u.id = dt.user_id
                      WHERE dt.txn_type = 'win' AND dt.win_amount > 0{$bgamingPeriodSql}
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
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    $isLiveRoute = in_array($route, ['favorite_live_casino.php', 'favorite-live-casino'], true);

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS drakon_favorite_games (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT NOT NULL,
                game_id VARCHAR(100) NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_drakon_fav_user_game (user_id, game_id),
                KEY idx_drakon_fav_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable) {
        // Storage bootstrap best-effort; queries below return the final outcome.
    }

    $normalizeFavoriteGameId = static function (mixed $value): string {
        $gameId = trim((string) $value);
        if ($gameId === '') {
            return '';
        }
        if (str_starts_with($gameId, 'drakon:')) {
            return substr($gameId, strlen('drakon:'));
        }

        return $gameId;
    };

    $categoryWhereSql = $isLiveRoute
        ? "(COALESCE(g.game_type, 0) = 1 OR LOWER(COALESCE(g.type, '')) = 'live')"
        : "(COALESCE(g.game_type, 0) <> 1 AND LOWER(COALESCE(g.type, '')) <> 'live')";

    if ($method === 'GET') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $total = 0;
        $games = [];
        try {
            $countStmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM drakon_favorite_games f
                 INNER JOIN drakon_games g ON g.game_id = f.game_id
                 WHERE f.user_id = :uid
                   AND g.is_active = 1
                   AND {$categoryWhereSql}"
            );
            $countStmt->execute([':uid' => $userId]);
            $total = (int) $countStmt->fetchColumn();

            $listStmt = $pdo->prepare(
                "SELECT
                    f.id,
                    f.game_id,
                    g.game_name,
                    g.provider_name,
                    COALESCE(NULLIF(g.image_url, ''), NULLIF(g.banner, ''), '') AS image_url,
                    g.game_type,
                    g.type
                 FROM drakon_favorite_games f
                 INNER JOIN drakon_games g ON g.game_id = f.game_id
                 WHERE f.user_id = :uid
                   AND g.is_active = 1
                   AND {$categoryWhereSql}
                 ORDER BY f.id DESC
                 LIMIT :limit OFFSET :offset"
            );
            $listStmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $listStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $listStmt->execute();
            $rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $gid = (string) ($row['game_id'] ?? '');
                $prefixed = $gid !== '' ? 'drakon:' . $gid : '';
                $isLive = ((int) ($row['game_type'] ?? 0) === 1) || strtolower((string) ($row['type'] ?? '')) === 'live';
                $games[] = [
                    'id' => (string) ($row['id'] ?? ''),
                    'game_id' => $prefixed,
                    'name' => (string) ($row['game_name'] ?? ''),
                    'game_name' => (string) ($row['game_name'] ?? ''),
                    'provider' => (string) ($row['provider_name'] ?? ''),
                    'provider_name' => (string) ($row['provider_name'] ?? ''),
                    'image_url' => (string) ($row['image_url'] ?? ''),
                    'thumbnail_url' => (string) ($row['image_url'] ?? ''),
                    'game_type' => $isLive ? 1 : 0,
                    'category' => $isLive ? 'live-casino' : 'slots',
                    'source' => 'drakon',
                ];
            }
        } catch (Throwable $e) {
            $memberEnvelope(500, [
                'success' => false,
                'code' => 500,
                'message' => 'Favori oyunlar alınamadı.',
                'meta' => ['reason' => $e->getMessage()],
            ]);
        }

        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 1;
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
                    'has_next' => ($offset + $limit) < $total,
                    'has_prev' => $offset > 0,
                ],
            ],
        ]);
    }

    if ($method === 'POST') {
        $input = $memberInput($payload);
        $gameId = $normalizeFavoriteGameId($input['game_id'] ?? $input['gameId'] ?? $_GET['game_id'] ?? '');
        if ($gameId === '') {
            $memberEnvelope(422, [
                'success' => false,
                'code' => 422,
                'message' => 'game_id zorunludur.',
            ]);
        }

        try {
            $existsStmt = $pdo->prepare(
                "SELECT g.game_id
                 FROM drakon_games g
                 WHERE g.game_id = :gid
                   AND g.is_active = 1
                   AND {$categoryWhereSql}
                 LIMIT 1"
            );
            $existsStmt->execute([':gid' => $gameId]);
            $exists = $existsStmt->fetchColumn();
            if ($exists === false) {
                $memberEnvelope(422, [
                    'success' => false,
                    'code' => 422,
                    'message' => 'Oyun bu favori kategorisinde bulunamadı.',
                ]);
            }

            $insertStmt = $pdo->prepare(
                'INSERT INTO drakon_favorite_games (user_id, game_id) VALUES (:uid, :gid)
                 ON DUPLICATE KEY UPDATE id = id'
            );
            $insertStmt->execute([':uid' => $userId, ':gid' => $gameId]);
            $alreadyFavorite = $insertStmt->rowCount() === 0;

            $memberEnvelope(200, [
                'success' => true,
                'code' => 200,
                'message' => $alreadyFavorite ? 'Oyun zaten favorilerde.' : 'Favorilere eklendi.',
                'data' => [
                    'favorited' => true,
                    'already_favorite' => $alreadyFavorite,
                    'game_id' => 'drakon:' . $gameId,
                ],
            ]);
        } catch (Throwable $e) {
            $memberEnvelope(500, [
                'success' => false,
                'code' => 500,
                'message' => 'Favori kaydedilemedi.',
                'meta' => ['reason' => $e->getMessage()],
            ]);
        }
    }

    if ($method === 'DELETE') {
        $input = $memberInput($payload);
        $gameId = $normalizeFavoriteGameId($input['game_id'] ?? $input['gameId'] ?? $_GET['game_id'] ?? '');
        if ($gameId === '') {
            $memberEnvelope(422, [
                'success' => false,
                'code' => 422,
                'message' => 'game_id zorunludur.',
            ]);
        }

        try {
            $delStmt = $pdo->prepare('DELETE FROM drakon_favorite_games WHERE user_id = :uid AND game_id = :gid');
            $delStmt->execute([':uid' => $userId, ':gid' => $gameId]);

            $memberEnvelope(200, [
                'success' => true,
                'code' => 200,
                'message' => 'Favorilerden kaldırıldı.',
                'data' => [
                    'favorited' => false,
                    'removed' => $delStmt->rowCount() > 0,
                    'game_id' => 'drakon:' . $gameId,
                ],
            ]);
        } catch (Throwable $e) {
            $memberEnvelope(500, [
                'success' => false,
                'code' => 500,
                'message' => 'Favori silinemedi.',
                'meta' => ['reason' => $e->getMessage()],
            ]);
        }
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
    }
    try {
        $gameId = trim((string) ($input['game_id'] ?? $input['gameId'] ?? $input['gameid'] ?? ''));

        // Some catalogue links pass the bare provider game id without a
        // "drakon:" / "bgaming:" prefix. Resolve the owning provider from the
        // database so the launch still routes correctly.
        if ($gameId !== '' && !DrakonService::ownsGameId($gameId) && !BgamingService::ownsGameId($gameId)) {
            $resolvePdo = AdminDatabase::pdo();
            try {
                $dStmt = $resolvePdo->prepare('SELECT 1 FROM drakon_games WHERE game_id = :g LIMIT 1');
                $dStmt->execute([':g' => $gameId]);
                if ($dStmt->fetchColumn()) {
                    $gameId = DrakonService::GAME_ID_PREFIX . $gameId;
                } else {
                    $bStmt = $resolvePdo->prepare('SELECT 1 FROM bgaming_games WHERE identifier = :g LIMIT 1');
                    $bStmt->execute([':g' => $gameId]);
                    if ($bStmt->fetchColumn()) {
                        $gameId = 'bgaming:' . $gameId;
                    }
                }
            } catch (Throwable) {
            }
            $input['game_id'] = $gameId;
        }

        if (DrakonService::ownsGameId($gameId)) {
            $result   = DrakonService::launch(AdminDatabase::pdo(), $user, $input);
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
        $httpCode = !empty($result['success']) ? 200 : (int) ($result['code'] ?? 422);
        if ($httpCode >= 500 && $httpCode !== 503) {
            $httpCode = 422;
        }
        $memberEnvelope($httpCode, $result);
    } catch (Throwable $exception) {
        $memberEnvelope(422, [
            'success' => false,
            'code' => 422,
            'message' => 'BGaming oyun başlatma hatası: ' . $exception->getMessage(),
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
