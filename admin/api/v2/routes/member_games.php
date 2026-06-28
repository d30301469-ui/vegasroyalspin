<?php
/** Üye API modülü — index.php tarafından include edilir. */
if ($method === 'GET' && in_array($route, ['games_provider.php', 'casino/providers', 'live-casino/providers', 'games-provider', 'games_provider.php'], true)) {
    $pdo = AdminDatabase::pdo();
    DrakonService::bootstrap($pdo);
    $rows = DrakonService::providers($pdo, $_GET);
    $providers = [];
    foreach ($rows as $row) {
        $providers[] = [
            'provider_code' => (string) ($row['provider_code'] ?? ''),
            'provider_name' => (string) ($row['provider_name'] ?? ''),
            'name' => (string) ($row['provider_name'] ?? ''),
            'code' => (string) ($row['provider_code'] ?? ''),
            'rtp' => isset($row['rtp']) ? (float) $row['rtp'] : null,
            'game_type' => (int) ($row['game_type'] ?? 0),
        ];
    }
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

if ($method === 'POST' && $route === 'drakon_sync_providers.php') {
    $requireAuth();
    if (!AdminAuth::can('drakon-settings')) {
        $memberEnvelope(403, ['success' => false, 'code' => 403, 'message' => 'Yetkisiz işlem.']);
    }
    if (!AdminAuth::verifyCsrf((string) ($payload['body']['_token'] ?? $payload['query']['_token'] ?? ''))) {
        $memberEnvelope(419, ['success' => false, 'code' => 419, 'message' => 'CSRF doğrulaması başarısız.']);
    }
    try {
        $result = DrakonService::syncProviders(AdminDatabase::pdo());
        $memberEnvelope(200, ['success' => true, 'code' => 200, 'message' => 'Provider sync tamamlandı.', 'data' => $result]);
    } catch (Throwable $exception) {
        $memberEnvelope(503, ['success' => false, 'code' => 503, 'message' => $exception->getMessage()]);
    }
}

if ($method === 'POST' && $route === 'drakon_sync_games.php') {
    $requireAuth();
    if (!AdminAuth::can('drakon-settings')) {
        $memberEnvelope(403, ['success' => false, 'code' => 403, 'message' => 'Yetkisiz işlem.']);
    }
    if (!AdminAuth::verifyCsrf((string) ($payload['body']['_token'] ?? $payload['query']['_token'] ?? ''))) {
        $memberEnvelope(419, ['success' => false, 'code' => 419, 'message' => 'CSRF doğrulaması başarısız.']);
    }
    try {
        $result = DrakonService::syncGames(AdminDatabase::pdo());
        $memberEnvelope(200, ['success' => true, 'code' => 200, 'message' => 'Oyun sync tamamlandı.', 'data' => $result]);
    } catch (Throwable $exception) {
        $memberEnvelope(503, ['success' => false, 'code' => 503, 'message' => $exception->getMessage()]);
    }
}

if (in_array($method, ['GET', 'POST'], true) && $route === 'drakon_campaigns.php') {
    $requireAuth();
    if (!AdminAuth::can('drakon-settings')) {
        $memberEnvelope(403, ['success' => false, 'code' => 403, 'message' => 'Yetkisiz işlem.']);
    }
    if ($method === 'POST' && !AdminAuth::verifyCsrf((string) ($payload['body']['_token'] ?? $payload['query']['_token'] ?? ''))) {
        $memberEnvelope(419, ['success' => false, 'code' => 419, 'message' => 'CSRF doğrulaması başarısız.']);
    }
    $input = $memberInput($payload);
    $action = strtolower(trim((string) ($input['action'] ?? $_GET['action'] ?? 'list')));
    $campaignPayload = array_diff_key($input, array_flip(['action', '_token', 'idempotency_key', 'currency_code']));
    $campaignCode = trim((string) ($input['campaign_code'] ?? $_GET['campaign_code'] ?? ''));
    $campaignPlayers = $campaignPayload['players'] ?? $campaignPayload['player'] ?? $campaignPayload['user_id'] ?? $campaignPayload['user_ids'] ?? [];
    if (is_string($campaignPlayers)) {
        $campaignPlayers = str_contains($campaignPlayers, ',') ? explode(',', $campaignPlayers) : [$campaignPlayers];
    } elseif (!is_array($campaignPlayers)) {
        $campaignPlayers = [$campaignPlayers];
    }
    $campaignPlayers = array_values(array_filter(array_map(static fn($player): string => trim((string) $player), $campaignPlayers), static fn(string $player): bool => $player !== ''));
    $idempotencyKey = trim((string) ($input['idempotency_key'] ?? ''));
    if ($idempotencyKey === '' && $campaignCode !== '') {
        $idempotencyKey = $action . '-' . $campaignCode;
        if ($campaignPlayers !== []) {
            $idempotencyKey .= '-' . substr(hash('sha256', implode(',', $campaignPlayers)), 0, 12);
        }
    }
    if ($idempotencyKey === '') {
        $idempotencyKey = $action . '-' . bin2hex(random_bytes(8));
    }
    $limitQuery = array_intersect_key($_GET, array_flip(['vendors', 'games']));
    $listQuery = array_intersect_key($_GET, array_flip(['vendor', 'status', 'active', 'per_page']));
    try {
        if (in_array($action, ['detail', 'cancel', 'add-player', 'add-players', 'remove-player', 'remove-players'], true) && $campaignCode === '') {
            $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'campaign_code zorunludur.']);
        }
        $response = match ($action) {
            'vendors' => DrakonService::campaignRequest(AdminDatabase::pdo(), 'GET', '/campaigns/vendors'),
            'vendor-limits', 'limits' => DrakonService::campaignRequest(AdminDatabase::pdo(), 'GET', '/campaigns/vendors/limits', [], $limitQuery),
            'create' => DrakonService::campaignRequest(AdminDatabase::pdo(), 'POST', '/campaigns/create', $campaignPayload, [], $idempotencyKey),
            'detail' => DrakonService::campaignRequest(AdminDatabase::pdo(), 'GET', '/campaigns/' . rawurlencode($campaignCode)),
            'cancel' => DrakonService::campaignRequest(AdminDatabase::pdo(), 'POST', '/campaigns/' . rawurlencode($campaignCode) . '/cancel', [], [], $idempotencyKey),
            'add-player', 'add-players' => DrakonService::campaignRequest(AdminDatabase::pdo(), 'POST', '/campaigns/' . rawurlencode($campaignCode) . '/players/add', ['players' => $campaignPlayers], [], $idempotencyKey),
            'remove-player', 'remove-players' => DrakonService::campaignRequest(AdminDatabase::pdo(), 'POST', '/campaigns/' . rawurlencode($campaignCode) . '/players/remove', ['players' => $campaignPlayers], [], $idempotencyKey),
            default => DrakonService::campaignRequest(AdminDatabase::pdo(), 'GET', '/campaigns/list', [], $listQuery),
        };
        $memberEnvelope(200, ['success' => true, 'code' => 200, 'message' => 'Drakon campaign API', 'data' => $response]);
    } catch (Throwable $exception) {
        $memberEnvelope(503, ['success' => false, 'code' => 503, 'message' => $exception->getMessage()]);
    }
}

if ($method === 'GET' && in_array($route, ['games.php', 'games'], true)) {
    $pdo = AdminDatabase::pdo();
    $source = strtolower(trim((string) ($_GET['source'] ?? '')));
    $provider = strtolower(trim((string) ($_GET['provider'] ?? $_GET['provider_code'] ?? '')));
    $search = trim((string) ($_GET['search'] ?? ''));
    $sort = strtolower(trim((string) ($_GET['sort'] ?? $_GET['category'] ?? '')));
    $sourceDrakon = in_array($source, ['drakon', 'casino', 'slot', 'slots'], true);
    $tvOnly = in_array($source, ['bgaming', 'tv'], true)
        || (!$sourceDrakon && $provider === 'bgaming')
        || in_array($sort, ['tv', 'tv-games', 'tv_oyunlari'], true);
    if ($tvOnly) {
        $catalog = BgamingService::games($pdo, $_GET);
    } else {
        $catalog = DrakonService::games($pdo, $_GET);
    }
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Oyun listesi',
        'data' => $catalog,
    ]);
}

if ($method === 'GET' && ($route === 'game_history.php' || $route === 'casino_game_history.php')) {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    DrakonService::bootstrap($pdo);
    $source = strtolower(trim((string) ($_GET['source'] ?? $_GET['category'] ?? $_GET['game_type'] ?? '')));
    if ($route === 'casino_game_history.php' && ($source === '' || $source === 'all')) {
        $source = 'all';
    }
    $historyId = trim((string) ($_GET['id'] ?? ''));
    $where = ['t.user_id = :user_id'];
    $params = ['user_id' => $userId];
    if ($historyId !== '') {
        $where[] = 'CAST(t.id AS CHAR) = :history_id';
        $params['history_id'] = $historyId;
    }
    if (in_array($source, ['slot', 'slots', 'casino'], true)) {
        $where[] = "(COALESCE(g.type, 'casino') = 'casino' AND COALESCE(g.game_type, 0) = 0)";
    } elseif (in_array($source, ['live', 'live_casino', 'livecasino'], true)) {
        $where[] = "(COALESCE(g.type, '') = 'live' OR COALESCE(g.game_type, 0) = 1)";
    }
    $stmt = $pdo->prepare("SELECT
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
                           LIMIT 100");
    $stmt->execute($params);
    $rows = array_map(static function (array $row): array {
        $category = ((string) ($row['game_category'] ?? 'casino') === 'live' || (int) ($row['game_type'] ?? 0) === 1)
            ? 'live_casino'
            : 'slot';
        return [
            'id' => (string) ($row['id'] ?? ''),
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
            'providerName' => (string) ($row['provider_name'] ?? ''),
            'provider_name' => (string) ($row['provider_name'] ?? ''),
            'providerCode' => (string) ($row['provider_code'] ?? ''),
            'provider_code' => (string) ($row['provider_code'] ?? ''),
            'source' => $category,
            'category' => $category,
            'wallet' => 'main',
            'txnType' => (string) ($row['txn_type'] ?? ''),
            'txn_type' => (string) ($row['txn_type'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'betAmount' => (float) ($row['bet_amount'] ?? 0),
            'bet_amount' => (float) ($row['bet_amount'] ?? 0),
            'winAmount' => (float) ($row['win_amount'] ?? 0),
            'win_amount' => (float) ($row['win_amount'] ?? 0),
            'balanceAfter' => $row['balance_after'] ?? null,
            'balance_after' => $row['balance_after'] ?? null,
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Oyun geçmişi',
        'data' => [
            'items' => $rows,
            'transactions' => $rows,
            'total' => count($rows),
            'source' => $source,
        ],
    ]);
}

if ($method === 'GET' && ($route === 'games/recently-played' || $route === 'games/recently-played.php')) {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    $limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
    // Fetch last N distinct games the user played, most recent first
    $stmt = $pdo->prepare("
        SELECT
            t.game_id,
            COALESCE(NULLIF(t.game_name, ''), g.game_name, t.game_id) AS game_name,
            COALESCE(g.provider_code, '')  AS provider_code,
            COALESCE(NULLIF(t.provider_name, ''), g.provider_name, '') AS provider_name,
            COALESCE(g.type, 'casino')     AS game_category,
            COALESCE(g.game_type, 0)       AS game_type,
            COALESCE(g.image_url, '')      AS image_url,
            MAX(t.created_at)              AS last_played_at
        FROM drakon_transactions t
        LEFT JOIN drakon_games g ON g.game_id = t.game_id
        WHERE t.user_id = :uid AND t.txn_type IN ('bet', 'win')
        GROUP BY t.game_id, t.game_name, t.provider_name
        ORDER BY last_played_at DESC
        LIMIT :lim
    ");
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $games = array_map(static function (array $row): array {
        $category = ((string) ($row['game_category'] ?? 'casino') === 'live' || (int) ($row['game_type'] ?? 0) === 1)
            ? 'live_casino' : 'slot';
        return [
            'game_id'       => (string) ($row['game_id'] ?? ''),
            'game_name'     => (string) ($row['game_name'] ?? ''),
            'provider_code' => (string) ($row['provider_code'] ?? ''),
            'provider_name' => (string) ($row['provider_name'] ?? ''),
            'category'      => $category,
            'image_url'     => (string) ($row['image_url'] ?? ''),
            'last_played_at'=> (string) ($row['last_played_at'] ?? ''),
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    $memberEnvelope(200, [
        'success' => true,
        'code'    => 200,
        'message' => 'Son oynanan oyunlar',
        'data'    => ['items' => $games, 'total' => count($games)],
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
    // Search Drakon games
    $stmtD = $pdo->prepare("
        SELECT game_id, game_name, provider_code, provider_name, type AS game_category,
               image_url, is_active, 'drakon' AS source
        FROM drakon_games
        WHERE is_active = 1 AND (game_name LIKE :q OR provider_name LIKE :q2 OR provider_code LIKE :q3)
        ORDER BY game_name ASC
        LIMIT :lim OFFSET :off
    ");
    $stmtD->bindValue(':q', $like);
    $stmtD->bindValue(':q2', $like);
    $stmtD->bindValue(':q3', $like);
    $stmtD->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmtD->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmtD->execute();
    $drakonGames = $stmtD->fetchAll(PDO::FETCH_ASSOC);

    // Search BGaming games
    $stmtB = $pdo->prepare("
        SELECT identifier AS game_id, name AS game_name, producer AS provider_code,
               producer AS provider_name, '' AS game_category,
               '' AS image_url, 1 AS is_active, 'bgaming' AS source
        FROM bgaming_games
        WHERE name LIKE :q OR producer LIKE :q2
        ORDER BY name ASC
        LIMIT :lim OFFSET :off
    ");
    $stmtB->bindValue(':q', $like);
    $stmtB->bindValue(':q2', $like);
    $stmtB->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmtB->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmtB->execute();
    $bgamingGames = $stmtB->fetchAll(PDO::FETCH_ASSOC);

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
    }, array_merge($drakonGames, $bgamingGames));

    $memberEnvelope(200, [
        'success' => true,
        'code'    => 200,
        'message' => 'Arama sonuçları',
        'data'    => ['items' => $games, 'total' => count($games), 'query' => $q],
    ]);
}

if ($method === 'GET' && $route === 'winners.php') {
    $pdo = AdminDatabase::pdo();
    DrakonService::bootstrap($pdo);
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
        $drakonPeriodSql = '';
        $bgamingPeriodSql = '';
    } else {
        $drakonPeriodSql = match ($period) {
            'week' => ' AND t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
            'month' => ' AND t.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)',
            'all' => '',
            default => ' AND t.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)',
        };
        $bgamingPeriodSql = match ($period) {
            'week' => ' AND COALESCE(t.processed_at, t.created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
            'month' => ' AND COALESCE(t.processed_at, t.created_at) >= DATE_SUB(NOW(), INTERVAL 1 MONTH)',
            'all' => '',
            default => ' AND COALESCE(t.processed_at, t.created_at) >= DATE_SUB(NOW(), INTERVAL 1 DAY)',
        };
    }

    $winnerSql = "SELECT *
                  FROM (
                      SELECT
                          u.username,
                          t.user_id,
                          t.game_id,
                          COALESCE(g.game_name, t.game_id) AS game_name,
                          COALESCE(NULLIF(g.provider_name, ''), 'Drakon') AS provider_name,
                          COALESCE(NULLIF(g.image_url, ''), NULLIF(g.banner, ''), '') AS image_url,
                          COALESCE(NULLIF(g.banner, ''), NULLIF(g.image_url, ''), '') AS banner,
                          t.win_amount,
                          t.created_at,
                          t.id AS sort_id,
                          'drakon' AS source
                      FROM drakon_transactions t
                      LEFT JOIN users u ON u.id = t.user_id
                      LEFT JOIN drakon_games g ON g.game_id = t.game_id
                      WHERE t.txn_type = 'win' AND t.win_amount > 0{$drakonPeriodSql}
                      UNION ALL
                      SELECT
                          u.username,
                          t.user_id,
                          t.game_identifier AS game_id,
                          COALESCE(g.title, t.game_identifier) AS game_name,
                          COALESCE(NULLIF(g.provider, ''), 'BGaming') AS provider_name,
                          COALESCE(g.thumbnail_url, '') AS image_url,
                          COALESCE(g.thumbnail_url, '') AS banner,
                          t.amount AS win_amount,
                          COALESCE(t.processed_at, t.created_at) AS created_at,
                          t.id AS sort_id,
                          'bgaming' AS source
                      FROM bgaming_transactions t
                      LEFT JOIN users u ON u.id = t.user_id
                      LEFT JOIN bgaming_games g ON g.identifier = t.game_identifier
                      WHERE t.txn_type IN ('win', 'promo_win', 'freespins_win') AND t.amount > 0{$bgamingPeriodSql}
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
            $emptyWinners($tab, $period);
        }
        throw $e;
    } catch (Throwable $e) {
        $emptyWinners($tab ?? 'recent', $period ?? 'day');
    }
}
if (in_array($route, ['favorite_slots.php', 'favorite_live_casino.php', 'favorite-slots', 'favorite-live-casino'], true)) {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    DrakonService::bootstrap($pdo);
    $gameType = in_array($route, ['favorite_live_casino.php', 'favorite-live-casino'], true) ? 'live' : 'casino';
    if ($method === 'GET') {
        $stmt = $pdo->prepare("SELECT f.id, f.game_id, g.game_name AS name, g.provider_name AS provider, g.image_url, g.image_url AS thumbnail_url
                               FROM drakon_favorite_games f
                               LEFT JOIN drakon_games g ON g.game_id = f.game_id
                               WHERE f.user_id = :user_id AND (g.type = :type_a OR g.type IS NULL)
                               ORDER BY f.id DESC");
        $stmt->execute(['user_id' => $userId, 'type_a' => $gameType]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = count($rows);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, (int) ($_GET['limit'] ?? 50));
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Favori oyunlar',
            'data' => [
                'items' => $rows,
                // Frontend drawer sözleşmesi için geriye uyumlu alanlar
                'games' => $rows,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => $limit > 0 ? (int) ceil($total / $limit) : 1,
                    'has_next' => false,
                    'has_prev' => false,
                ],
            ],
        ]);
    }
    $input = $memberInput($payload);
    $gameId = trim((string) ($input['game_id'] ?? $input['id'] ?? $_GET['game_id'] ?? $_GET['id'] ?? ''));
    if ($gameId === '') {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'game_id zorunludur.']);
    }
    $exists = $pdo->prepare('SELECT id FROM drakon_favorite_games WHERE user_id = :user_id AND game_id = :game_id LIMIT 1');
    $exists->execute(['user_id' => $userId, 'game_id' => $gameId]);
    $row = $exists->fetch(PDO::FETCH_ASSOC);
    if ($method === 'DELETE') {
        if (is_array($row)) {
            $pdo->prepare('DELETE FROM drakon_favorite_games WHERE id = :id')->execute(['id' => (int) $row['id']]);
        }
        $memberEnvelope(200, ['success' => true, 'code' => 200, 'message' => 'Favorilerden kaldırıldı.', 'data' => ['favorited' => false]]);
    }
    if (is_array($row)) {
        $memberEnvelope(200, ['success' => true, 'code' => 200, 'message' => 'Oyun zaten favorilerde.', 'data' => ['favorited' => true, 'already_favorite' => true]]);
    }
    $pdo->prepare('INSERT INTO drakon_favorite_games (user_id, game_id, created_at) VALUES (:user_id, :game_id, NOW())')
        ->execute(['user_id' => $userId, 'game_id' => $gameId]);
    $memberEnvelope(200, ['success' => true, 'code' => 200, 'message' => 'Favorilere eklendi.', 'data' => ['favorited' => true]]);
}
if ($method === 'GET' && in_array($route, ['freespins.php', 'me/freespins', 'profile/freespins'], true)) {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    $tab = trim((string) ($_GET['tab'] ?? ''));
    $where = ['p.user_id = :user_id', "p.status <> 'removed'"];
    $params = ['user_id' => $userId];
    if ($tab === 'aktif') {
        $where[] = 'c.active = 1';
        $where[] = "(c.expires_at IS NULL OR c.expires_at = 0 OR c.expires_at >= UNIX_TIMESTAMP())";
        $where[] = "c.status NOT IN ('canceled', 'cancelled')";
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT p.status AS player_status,
                    p.created_at AS assigned_at,
                    c.campaign_code,
                    c.vendor,
                    c.currency_code,
                    c.freespins_per_player,
                    c.begins_at,
                    c.expires_at,
                    c.active,
                    c.status
             FROM drakon_campaign_players p
             INNER JOIN drakon_campaigns c ON c.campaign_code = p.campaign_code
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY p.id DESC
             LIMIT 50'
        );
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        $items = [];
    }
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Freespin listesi',
        'data' => ['items' => $items, 'total' => count($items), 'tab' => $tab ?: 'yeni'],
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
        $result = BgamingService::ownsGameId($gameId)
            ? BgamingService::launch(AdminDatabase::pdo(), $user, $input)
            : DrakonService::launch(AdminDatabase::pdo(), $user, $input);
        $httpCode = !empty($result['success']) ? 200 : (int) ($result['code'] ?? 422);
        if ($httpCode >= 500 && $httpCode !== 503) {
            $httpCode = 422;
        }
        $memberEnvelope($httpCode, $result);
    } catch (Throwable $exception) {
        $gameId = trim((string) ($input['game_id'] ?? $input['gameId'] ?? $input['gameid'] ?? ''));
        $isBgamingLaunch = class_exists('BgamingService', false) && BgamingService::ownsGameId($gameId);
        $message = $isBgamingLaunch
            ? 'BGaming oyun başlatma hatası: ' . $exception->getMessage()
            : 'Drakon oyun başlatma hatası: ' . $exception->getMessage();
        $memberEnvelope(422, [
            'success' => false,
            'code' => 422,
            'message' => $message,
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
