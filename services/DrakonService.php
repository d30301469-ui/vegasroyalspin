<?php

declare(strict_types=1);

final class DrakonService
{
    private const API_BASE        = 'https://gator.drakon.casino/api/v1';
    public  const GAME_ID_PREFIX  = 'drakon:';

    // ─── Schema ──────────────────────────────────────────────────────────────

    public static function bootstrap(PDO $pdo): void
    {
        if ((string) getenv('METROPOL_RUNTIME_PROVIDER_BOOTSTRAP') !== '1'
            || !self::runtimeSchemaChangesAllowed()) {
            return;
        }
        self::createSchema($pdo);
        self::ensureDefaultConfig($pdo);
    }

    private static function runtimeSchemaChangesAllowed(): bool
    {
        $env = strtolower(trim((string) (getenv('APP_ENV') ?: 'production')));
        return in_array($env, ['development', 'local', 'dev', 'testing', 'test'], true)
            || (string) getenv('ALLOW_RUNTIME_MIGRATIONS') === '1';
    }

    public static function createSchema(PDO $pdo): void
    {
        if (!self::runtimeSchemaChangesAllowed()) {
            throw new RuntimeException('Runtime provider schema changes are disabled in production.');
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS drakon_config (
                id                   TINYINT UNSIGNED NOT NULL DEFAULT 1,
                agent_code           VARCHAR(100) NOT NULL DEFAULT '',
                agent_token          VARCHAR(255) NOT NULL DEFAULT '',
                agent_secret         VARCHAR(255) NOT NULL DEFAULT '',
                currency             CHAR(3) NOT NULL DEFAULT 'TRY',
                lang                 VARCHAR(8) NOT NULL DEFAULT 'tr',
                access_token         TEXT NULL,
                token_expires_at     DATETIME NULL,
                is_active            TINYINT(1) NOT NULL DEFAULT 0,
                api_base_url         VARCHAR(255) NOT NULL DEFAULT '" . self::API_BASE . "',
                site_endpoint        VARCHAR(255) NOT NULL DEFAULT '',
                home_url             VARCHAR(255) NOT NULL DEFAULT '',
                callback_secret      VARCHAR(255) NOT NULL DEFAULT '',
                callback_allowed_ips TEXT NULL,
                last_auth_at         DATETIME NULL,
                created_at           TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at           TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS drakon_providers (
                id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                provider_code VARCHAR(100) NOT NULL,
                provider_name VARCHAR(255) NOT NULL DEFAULT '',
                rtp           DECIMAL(5,2) NULL,
                is_active     TINYINT(1) NOT NULL DEFAULT 1,
                synced_at     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_drakon_provider_code (provider_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS drakon_games (
                id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                game_id       VARCHAR(100) NOT NULL,
                game_code     VARCHAR(100) NULL,
                game_name     VARCHAR(255) NOT NULL DEFAULT '',
                provider_code VARCHAR(100) NULL,
                provider_name VARCHAR(190) NOT NULL DEFAULT '',
                rtp           DECIMAL(6,2) NULL,
                banner        TEXT NULL,
                image_url     VARCHAR(500) NULL,
                type          VARCHAR(50) NOT NULL DEFAULT 'casino',
                game_type     TINYINT NOT NULL DEFAULT 0,
                is_active     TINYINT(1) NOT NULL DEFAULT 1,
                is_featured   TINYINT(1) NOT NULL DEFAULT 0,
                raw_payload   JSON NULL,
                synced_at     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                created_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_drakon_game_id (game_id),
                KEY idx_drakon_games_provider (provider_name),
                KEY idx_drakon_games_active (is_active),
                KEY idx_drakon_games_type (game_type),
                KEY idx_drakon_games_name (game_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS drakon_transactions (
                id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id       INT UNSIGNED NOT NULL,
                username      VARCHAR(100) NULL,
                user_full_name VARCHAR(255) NULL,
                transaction_id VARCHAR(200) NOT NULL,
                round_id      VARCHAR(200) NULL,
                session_id    VARCHAR(200) NULL,
                game_id       VARCHAR(100) NULL,
                game_name     VARCHAR(255) NULL,
                provider_name VARCHAR(100) NULL,
                image_url     VARCHAR(500) NULL,
                txn_type      ENUM('bet','win','refund') NOT NULL,
                wallet_source ENUM('balance','bonus_balance') NOT NULL DEFAULT 'balance',
                bet_amount    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                win_amount    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                amount        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                before_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                after_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                status        VARCHAR(20) NOT NULL DEFAULT 'ok',
                created_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_drakon_txn_id (transaction_id),
                KEY idx_drakon_txns_user (user_id),
                KEY idx_drakon_txns_round (round_id),
                KEY idx_drakon_txns_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS drakon_webhook_logs (
                id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                method         VARCHAR(50) NULL,
                user_id        INT UNSIGNED NULL,
                transaction_id VARCHAR(200) NULL,
                http_status    SMALLINT NOT NULL DEFAULT 200,
                error_code     VARCHAR(50) NULL,
                request_payload  MEDIUMTEXT NULL,
                response_payload MEDIUMTEXT NULL,
                duration_ms    SMALLINT UNSIGNED NULL,
                created_at     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_drakon_wlog_method (method),
                KEY idx_drakon_wlog_user (user_id),
                KEY idx_drakon_wlog_txn (transaction_id),
                KEY idx_drakon_wlog_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private static function ensureDefaultConfig(PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO drakon_config
                (id, agent_code, agent_token, agent_secret, currency, lang, is_active, api_base_url, site_endpoint, callback_secret)
             VALUES (1, '', '', '', 'TRY', 'tr', 0, :api, '', '')"
        );
        $stmt->execute([':api' => self::API_BASE]);
    }

    // ─── Config ───────────────────────────────────────────────────────────────

    public static function config(PDO $pdo): array
    {
        try {
            $row = $pdo->query("SELECT * FROM drakon_config WHERE id = 1 LIMIT 1")?->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Lazily reconcile drakon_config columns added after the initial migration
     * (e.g. home_url), so admin saves and reads work on existing installs.
     */
    private static function ensureConfigColumns(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        try {
            $cols = self::tableColumns($pdo, 'drakon_config');
            if ($cols === []) {
                return;
            }
            if (!in_array('home_url', $cols, true)) {
                $pdo->exec("ALTER TABLE drakon_config ADD COLUMN home_url VARCHAR(255) NOT NULL DEFAULT '' AFTER site_endpoint");
            }
            $done = true;
        } catch (Throwable $e) {
            error_log('[DrakonService] ensureConfigColumns failed: ' . $e->getMessage());
        }
    }

    /**
     * Lazily adds wallet_source to drakon_transactions on existing installs, so
     * bet/win/refund can record which users column (balance vs bonus_balance)
     * actually funded the round. Safe in production — only adds a missing column.
     */
    private static function ensureTransactionColumns(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        try {
            $cols = self::tableColumns($pdo, 'drakon_transactions');
            if ($cols === []) {
                return;
            }
            if (!in_array('wallet_source', $cols, true)) {
                $pdo->exec("ALTER TABLE drakon_transactions ADD COLUMN wallet_source ENUM('balance','bonus_balance') NOT NULL DEFAULT 'balance' AFTER txn_type");
            }
            $done = true;
        } catch (Throwable $e) {
            error_log('[DrakonService] ensureTransactionColumns failed: ' . $e->getMessage());
        }
    }

    public static function updateConfig(PDO $pdo, array $data): void
    {
        self::ensureConfigColumns($pdo);
        $allowed = ['agent_code', 'agent_token', 'agent_secret', 'currency', 'lang', 'api_base_url', 'site_endpoint', 'home_url'];
        $sets    = [];
        $params  = [];
        foreach ($allowed as $key) {
            // Preserve existing secret/token if submitted value is empty
            if (in_array($key, ['agent_secret', 'agent_token'], true) && trim((string) ($data[$key] ?? '')) === '') {
                continue;
            }
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $sets[]            = "`{$key}` = :{$key}";
            // Trim to avoid accidental copy/paste whitespace or newlines corrupting
            // the base64(agent_token:agent_secret) auth header (causes INVALID_AGENT/
            // INVALID_TOKEN on every attempt even though the credentials "look" right).
            $params[":{$key}"] = trim((string) $data[$key]);
        }
        // is_active checkbox: absent means unchecked (0)
        $sets[]               = 'is_active = :is_active';
        $params[':is_active'] = (!empty($data['is_active']) && $data['is_active'] !== '0') ? 1 : 0;
        // Clear cached token when credentials change
        if (!empty($data['agent_token']) || !empty($data['agent_secret'])) {
            $sets[] = "access_token = ''";
            $sets[] = 'token_expires_at = NULL';
        }
        if ($sets === []) {
            return;
        }
        $sql  = 'UPDATE drakon_config SET ' . implode(', ', $sets) . ' WHERE id = 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    public static function isConfigured(PDO $pdo): bool
    {
        $cfg = self::config($pdo);
        return !empty($cfg['agent_code'])
            && !empty($cfg['agent_token'])
            && !empty($cfg['agent_secret']);
    }

    private static function apiBase(array $cfg = []): string
    {
        $base = rtrim(trim((string) ($cfg['api_base_url'] ?? '')), '/');
        if ($base === '') {
            $base = self::API_BASE;
        }

        // Known-stale/decommissioned host: this project's drakon_config was seeded at
        // one point with "gator.drakonapi.tech" (an older/reseller endpoint). That
        // host no longer accepts connections ("refused to connect" both server-side
        // for API calls and client-side when the browser is redirected to the actual
        // game session URL). Silently correct to the official documented host so a
        // stale saved value can't keep breaking auth/launch; this does NOT touch the
        // stored DB value, so Admin > Drakon > Settings should still be updated to
        // avoid confusion, but functionality no longer depends on that being done.
        $host = parse_url($base, PHP_URL_HOST);
        if (is_string($host) && stripos($host, 'drakonapi.tech') !== false) {
            error_log('[DrakonService] Ignoring stale api_base_url (' . $base . '); falling back to ' . self::API_BASE);
            $base = self::API_BASE;
        }

        return $base;
    }

    // ─── Authentication ───────────────────────────────────────────────────────

    public static function getToken(PDO $pdo): string
    {
        $cfg     = self::config($pdo);
        $expires = !empty($cfg['token_expires_at']) ? (int) strtotime((string) $cfg['token_expires_at']) : 0;
        if (!empty($cfg['access_token']) && $expires > time() + 60) {
            return (string) $cfg['access_token'];
        }
        return self::authenticate($pdo, $cfg);
    }

    private static function authenticate(PDO $pdo, array $cfg): string
    {
        // Trim defensively even if the stored value already has stray whitespace
        // (e.g. saved before this fix, or edited directly in the DB) — a trailing
        // newline/space silently breaks the base64(agent_token:agent_secret) header.
        $agentCode   = trim((string) ($cfg['agent_code'] ?? ''));
        $agentToken  = trim((string) ($cfg['agent_token'] ?? ''));
        $agentSecret = trim((string) ($cfg['agent_secret'] ?? ''));
        if ($agentToken === '' || $agentSecret === '') {
            throw new RuntimeException('Drakon agent_token ve agent_secret yapılandırılmamış.');
        }

        // Exactly per Drakon's official integration guide, section 2 ("Kimlik Doğrulama"):
        // POST /auth/authentication, Authorization: Bearer base64(agent_token:agent_secret),
        // no request body.
        $url     = self::apiBase($cfg) . '/auth/authentication';
        $headers = [
            'Authorization: Bearer ' . base64_encode($agentToken . ':' . $agentSecret),
            'Accept: application/json',
        ];

        $response = self::httpRequest('POST', $url, [], $headers);
        $token    = self::extractAccessToken($response);
        if ($token !== '') {
            $stmt = $pdo->prepare(
                "UPDATE drakon_config
                    SET access_token = :tok, token_expires_at = :exp, last_auth_at = NOW()
                  WHERE id = 1"
            );
            $stmt->execute([
                ':tok' => $token,
                ':exp' => date('Y-m-d H:i:s', time() + 3000),
            ]);

            return $token;
        }

        throw new RuntimeException('Drakon kimlik doğrulama başarısız: ' . json_encode([
            'response' => self::redactWebhookPayload($response),
            // Masked lengths only (never the actual secret) — helps tell "config is
            // empty/truncated/has stray whitespace" apart from "credentials are simply
            // wrong/revoked on Drakon's side" without leaking anything sensitive.
            'config_diagnostic' => [
                'agent_code_len'   => strlen($agentCode),
                'agent_token_len'  => strlen($agentToken),
                'agent_secret_len' => strlen($agentSecret),
                'api_base'         => self::apiBase($cfg),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function extractAccessToken(array $response): string
    {
        foreach ([
            $response['access_token'] ?? null,
            $response['token'] ?? null,
            $response['data']['access_token'] ?? null,
            $response['data']['token'] ?? null,
        ] as $candidate) {
            $token = trim((string) $candidate);
            if ($token !== '') {
                return $token;
            }
        }

        return '';
    }

    // ─── Sync ────────────────────────────────────────────────────────────────

    public static function syncProviders(PDO $pdo): array
    {
        $cfg      = self::config($pdo);
        $token    = self::getToken($pdo);
        $response = self::httpRequest('GET', self::apiBase($cfg) . '/games/provider', [], [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ], 60);
        $providers = is_array($response['providers'] ?? null) ? $response['providers'] : [];
        $count     = 0;

        // The live table historically carries both a legacy code/name pair and a
        // canonical provider_code/provider_name pair. Insert into whichever
        // columns actually exist so the sync works across schema revisions.
        $cols      = self::tableColumns($pdo, 'drakon_providers');
        $hasCode   = in_array('code', $cols, true);
        $hasName   = in_array('name', $cols, true);
        $hasPCode  = in_array('provider_code', $cols, true);
        $hasPName  = in_array('provider_name', $cols, true);
        $hasRtp    = in_array('rtp', $cols, true);
        $hasSynced = in_array('synced_at', $cols, true);

        $insert = [];
        $values = [];
        $update = [];
        if ($hasCode)   { $insert[] = 'code';          $values[] = ':code';  $update[] = 'code = VALUES(code)'; }
        if ($hasName)   { $insert[] = 'name';          $values[] = ':name';  $update[] = 'name = VALUES(name)'; }
        if ($hasPCode)  { $insert[] = 'provider_code'; $values[] = ':pcode'; $update[] = 'provider_code = VALUES(provider_code)'; }
        if ($hasPName)  { $insert[] = 'provider_name'; $values[] = ':pname'; $update[] = 'provider_name = VALUES(provider_name)'; }
        if ($hasRtp)    { $insert[] = 'rtp';           $values[] = ':rtp';   $update[] = 'rtp = VALUES(rtp)'; }
        if ($hasSynced) { $insert[] = 'synced_at';     $values[] = 'NOW()';  $update[] = 'synced_at = NOW()'; }

        if ($insert === []) {
            return ['count' => 0, 'providers' => $providers];
        }

        $sql = 'INSERT INTO drakon_providers (' . implode(', ', $insert) . ')
                VALUES (' . implode(', ', $values) . ')';
        if ($update !== []) {
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $update);
        }
        $stmt = $pdo->prepare($sql);

        foreach ($providers as $provider) {
            $code = trim((string) ($provider['code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $name = (string) ($provider['name'] ?? $code);
            $params = [];
            if ($hasCode)  { $params[':code']  = $code; }
            if ($hasName)  { $params[':name']  = $name; }
            if ($hasPCode) { $params[':pcode'] = $code; }
            if ($hasPName) { $params[':pname'] = $name; }
            if ($hasRtp)   { $params[':rtp']   = isset($provider['rtp']) ? (float) $provider['rtp'] : null; }
            $stmt->execute($params);
            $count++;
        }
        return ['count' => $count, 'providers' => $providers];
    }

    public static function syncGames(PDO $pdo): array
    {
        $cfg      = self::config($pdo);
        $token    = self::getToken($pdo);
        $response = self::httpRequest('GET', self::apiBase($cfg) . '/games/all', [], [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ], 180);
        $apiGames = is_array($response['games'] ?? null) ? $response['games'] : [];
        $count    = 0;

        $cols          = self::tableColumns($pdo, 'drakon_games');
        $hasProviderCode = in_array('provider_code', $cols, true);
        $hasType         = in_array('type', $cols, true);
        $hasGameType     = in_array('game_type', $cols, true);

        // Build a column-aware upsert. game_type/type are only set on INSERT
        // (kept out of the UPDATE clause) so any manual retyping in the admin
        // panel survives re-syncs.
        $insert = ['game_id', 'game_code', 'game_name', 'provider_name', 'rtp', 'banner', 'image_url', 'synced_at'];
        $values = [':game_id', ':game_code', ':game_name', ':provider_name', ':rtp', ':banner', ':img', 'NOW()'];
        $update = [
            'game_code     = VALUES(game_code)',
            'game_name     = VALUES(game_name)',
            'provider_name = VALUES(provider_name)',
            'rtp           = VALUES(rtp)',
            'banner        = VALUES(banner)',
            'image_url     = VALUES(image_url)',
            'synced_at     = NOW()',
        ];
        if ($hasProviderCode) {
            $insert[] = 'provider_code';
            $values[] = ':provider_code';
            $update[] = 'provider_code = VALUES(provider_code)';
        }
        if ($hasType) {
            $insert[] = 'type';
            $values[] = ':type';
        }
        if ($hasGameType) {
            $insert[] = 'game_type';
            $values[] = ':game_type';
        }

        $sql = 'INSERT INTO drakon_games (' . implode(', ', $insert) . ')
                VALUES (' . implode(', ', $values) . ')
                ON DUPLICATE KEY UPDATE ' . implode(', ', $update);
        $stmt = $pdo->prepare($sql);

        foreach ($apiGames as $game) {
            $gameId = trim((string) ($game['game_id'] ?? ''));
            if ($gameId === '') {
                continue;
            }
            $banner   = (string) ($game['banner'] ?? '');
            $provider = (string) ($game['provider_game'] ?? '');
            $isLive   = self::isLiveProviderName($provider);
            $params = [
                ':game_id'       => $gameId,
                ':game_code'     => (string) ($game['game_code'] ?? $gameId),
                ':game_name'     => (string) ($game['game_name'] ?? $gameId),
                ':provider_name' => $provider,
                ':rtp'           => isset($game['rtp']) ? (float) $game['rtp'] : null,
                ':banner'        => $banner,
                ':img'           => $banner,
            ];
            if ($hasProviderCode) {
                $params[':provider_code'] = $provider;
            }
            if ($hasType) {
                $params[':type'] = $isLive ? 'live' : 'casino';
            }
            if ($hasGameType) {
                $params[':game_type'] = $isLive ? 1 : 0;
            }
            $stmt->execute($params);
            $count++;
        }
        return ['count' => $count];
    }

    /**
     * Best-effort live-casino detection from a Drakon provider label. Used to
     * classify NEW games on sync; existing rows keep their stored game_type.
     */
    private static function isLiveProviderName(string $provider): bool
    {
        $p = strtolower(trim($provider));
        if ($p === '') {
            return false;
        }
        if (str_contains($p, 'live')) {
            return true;
        }
        $needles = [
            'evolution', 'ezugi', 'pragmatic-bj', 'pragmatic-live', 'pragmatic-virtual',
            'sagaming', 'vivo', 'creedz', 'creedroomz', '7mojos', 'tvbet', 'iconic21',
            'imagine', 'yeebet', 'skywind', 'betgames', 'lucky-streak', 'xpg', 'oncasino',
            'pateplay-live', 'vimplay', 'micro-gaming-live',
        ];
        foreach ($needles as $needle) {
            if (str_contains($p, $needle)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return list<string> lowercase column names for the given table
     */
    private static function tableColumns(PDO $pdo, string $table): array
    {
        try {
            $rows = $pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_COLUMN);
            return is_array($rows) ? array_map(static fn ($c): string => strtolower((string) $c), $rows) : [];
        } catch (Throwable) {
            return [];
        }
    }

    // ─── Catalog ─────────────────────────────────────────────────────────────

    public static function ownsGameId(string $gameId): bool
    {
        return str_starts_with($gameId, self::GAME_ID_PREFIX);
    }

    public static function games(PDO $pdo, array $query = []): array
    {
        $limit    = min(200, max(1, (int) ($query['limit'] ?? 30)));
        $page     = max(1, (int) ($query['page'] ?? 1));
        $offset   = ($page - 1) * $limit;
        $search   = trim((string) ($query['search'] ?? $query['q'] ?? ''));
        $provider = trim((string) ($query['provider'] ?? ''));

        $where  = ['is_active = 1'];
        $params = [];

        // Slot lobby (game_type 0) vs live casino (game_type 1). Only apply the
        // filter when the caller explicitly requests a type so that unfiltered
        // callers still receive the full catalogue.
        $gameTypeRaw = $query['game_type'] ?? $query['filter_game_type'] ?? null;
        $gameType = null;
        if ($gameTypeRaw !== null && $gameTypeRaw !== '') {
            $gameType = (int) $gameTypeRaw === 1 ? 1 : 0;
            $where[]           = 'game_type = :game_type';
            $params[':game_type'] = $gameType;
        }

        if ($search !== '') {
            $where[]           = '(game_name LIKE :search OR provider_name LIKE :search2)';
            $params[':search']  = '%' . $search . '%';
            $params[':search2'] = '%' . $search . '%';
        }
        if ($provider !== '') {
            $where[]            = 'provider_name = :provider';
            $params[':provider'] = $provider;
        }

        $whereSql   = 'WHERE ' . implode(' AND ', $where);
        $stmtTotal  = $pdo->prepare("SELECT COUNT(*) FROM drakon_games {$whereSql}");
        $stmtTotal->execute($params);
        $total = (int) $stmtTotal->fetchColumn();

        $stmtRows = $pdo->prepare(
            "SELECT game_id, game_name, provider_name, rtp, banner, image_url, is_active, game_type, type
             FROM drakon_games {$whereSql}
             ORDER BY game_name ASC
             LIMIT :lim OFFSET :off"
        );
        foreach ($params as $k => $v) {
            $stmtRows->bindValue($k, $v);
        }
        $stmtRows->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmtRows->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmtRows->execute();
        $rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC);

        $games = array_map(static function (array $row): array {
            $gameId = (string) ($row['game_id'] ?? '');
            $img    = (string) ($row['image_url'] ?? '') !== ''
                ? (string) $row['image_url']
                : (string) ($row['banner'] ?? '');
            $isLive = (int) ($row['game_type'] ?? 0) === 1;
            return [
                'id'            => DrakonService::GAME_ID_PREFIX . $gameId,
                'identifier'    => DrakonService::GAME_ID_PREFIX . $gameId,
                'game_id'       => DrakonService::GAME_ID_PREFIX . $gameId,
                'game_code'     => $gameId,
                'name'          => (string) ($row['game_name'] ?? ''),
                'title'         => (string) ($row['game_name'] ?? ''),
                'producer'      => (string) ($row['provider_name'] ?? ''),
                'provider'      => (string) ($row['provider_name'] ?? ''),
                'category'      => $isLive ? 'live-casino' : 'slots',
                'game_type'     => $isLive ? 1 : 0,
                'type'          => (string) ($row['type'] ?? ($isLive ? 'live' : 'casino')),
                'thumbnail_url' => $img,
                'image_url'     => $img,
                'banner'        => $img,
                'is_active'     => (bool) ($row['is_active'] ?? true),
                'source'        => 'drakon',
            ];
        }, is_array($rows) ? $rows : []);

        return [
            'games'       => $games,
            'items'       => $games,
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => $total > 0 ? (int) ceil($total / $limit) : 1,
        ];
    }

    // ─── Game Launch ─────────────────────────────────────────────────────────

    public static function launch(PDO $pdo, ?array $user, array $input): array
    {
        $cfg = self::config($pdo);
        if (empty($cfg['is_active'])) {
            return ['success' => false, 'code' => 503, 'message' => 'Drakon entegrasyonu aktif değil.'];
        }

        $rawGameId = trim((string) ($input['game_id'] ?? $input['gameId'] ?? ''));
        $gameId    = self::ownsGameId($rawGameId)
            ? substr($rawGameId, strlen(self::GAME_ID_PREFIX))
            : $rawGameId;

        if ($gameId === '') {
            return ['success' => false, 'code' => 422, 'message' => 'game_id gereklidir.'];
        }

        $mode = strtolower(trim((string) ($input['mode'] ?? 'real')));
        $mode = in_array($mode, ['fun', 'demo'], true) ? 'fun' : 'real';

        $agentCode  = (string) ($cfg['agent_code'] ?? '');
        $agentToken = (string) ($cfg['agent_token'] ?? '');
        $currency   = strtoupper(trim((string) ($cfg['currency'] ?? 'TRY')));
        $lang       = strtolower(trim((string) ($input['lang'] ?? ($cfg['lang'] ?? 'tr'))));

        if ($agentCode === '' || $agentToken === '') {
            return ['success' => false, 'code' => 503, 'message' => 'Drakon agent yapılandırılmamış.'];
        }

        $params = [
            'agent_code'  => $agentCode,
            'agent_token' => $agentToken,
            'game_id'     => $gameId,
            'currency'    => $currency,
            'lang'        => $lang,
            'mode'        => $mode,
        ];

        if ($mode === 'real' && is_array($user)) {
            $userId   = (int) ($user['id'] ?? 0);
            $userName = trim((string) ($user['name'] ?? $user['username'] ?? ''));
            if ($userId > 0) {
                $params['user_id']   = (string) $userId;
                $params['user_name'] = $userName ?: 'user_' . $userId;
            }
        } elseif ($mode === 'fun') {
            // Drakon requires a user id even for demo/fun launches.
            $params['user_id']   = 'demo';
            $params['user_name'] = 'Demo Player';
        }

        try {
            $token    = self::getToken($pdo);
            $response = self::httpRequest('GET', self::apiBase($cfg) . '/games/game_launch', $params, [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ]);
        } catch (Throwable $e) {
            return ['success' => false, 'code' => 503, 'message' => 'Drakon API bağlantı hatası: ' . $e->getMessage()];
        }

        $responseData = is_array($response['data'] ?? null) ? $response['data'] : [];
        $launchOptions = is_array($responseData['launch_options'] ?? null) ? $responseData['launch_options'] : [];
        $gameUrl = trim((string) (
            $response['game_url']
            ?? $responseData['game_url']
            ?? $response['launch_url']
            ?? $responseData['launch_url']
            ?? $response['url']
            ?? $responseData['url']
            ?? $launchOptions['game_url']
            ?? $launchOptions['launch_url']
            ?? $launchOptions['url']
            ?? ''
        ));
        if ($gameUrl === '') {
            $errorCode = strtoupper(trim((string) ($response['error'] ?? $responseData['error'] ?? '')));
            if ($errorCode === 'FUN_MODE_NOT_AVAILABLE') {
                return [
                    'success' => false,
                    'code'    => 422,
                    'error'   => 'fun_mode_not_available',
                    'message' => 'Bu oyun demo modunu desteklemiyor. Lütfen giriş yaparak gerçek modda oynayın.',
                    'raw'     => $response,
                ];
            }

            return [
                'success' => false,
                'code'    => 422,
                'message' => 'Drakon oyun URL döndürmedi.',
                'raw'     => $response,
            ];
        }

        return [
            'success'  => true,
            'code'     => 200,
            'message'  => 'Oyun başlatıldı.',
            'data'     => [
                'game_url'       => $gameUrl,
                'launch_url'     => $gameUrl,
                'mode'           => $mode,
                'home_url'       => trim((string) ($cfg['home_url'] ?? '')),
                'launch_options' => ['game_url' => $gameUrl],
            ],
            'game_url' => $gameUrl,
        ];
    }

    // ─── Free Spin / Campaign API ────────────────────────────────────────────

    /**
     * Idempotent, lazy creation of the campaign/free-spin tables. Safe to call
     * on every request (CREATE TABLE IF NOT EXISTS) so the feature works even
     * on production hosts where runtime provider bootstrap is disabled.
     */
    public static function ensureCampaignSchema(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        try {
            // Canonical schema is authored by
            // database/migrations/*_create_provider_tables.php. These CREATE IF
            // NOT EXISTS statements mirror that schema for fresh/dev hosts; the
            // ALTERs below reconcile older migration-created tables that lack the
            // extra display columns this service writes, so INSERTs never fail
            // silently.
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS drakon_campaigns (
                    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    campaign_code        VARCHAR(190) NOT NULL,
                    vendor               VARCHAR(100) NOT NULL DEFAULT '',
                    currency_code        CHAR(3) NULL,
                    freespins_per_player INT NOT NULL DEFAULT 0,
                    total_bet            VARCHAR(50) NULL,
                    game_ids             VARCHAR(500) NULL,
                    begins_at            BIGINT NULL,
                    expires_at           BIGINT NULL,
                    active               TINYINT(1) NOT NULL DEFAULT 1,
                    status               VARCHAR(40) NOT NULL DEFAULT 'active',
                    request_id           VARCHAR(190) NULL,
                    payload              JSON NULL,
                    remote_response      JSON NULL,
                    created_at           TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at           TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_drakon_campaign_code (campaign_code)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS drakon_campaign_players (
                    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    campaign_code   VARCHAR(190) NOT NULL,
                    user_id         INT NOT NULL,
                    status          VARCHAR(40) NOT NULL DEFAULT 'assigned',
                    remote_response JSON NULL,
                    created_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_drakon_campaign_player (campaign_code, user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            // Reconcile migration-created tables missing the display columns.
            $campaignCols = self::tableColumns($pdo, 'drakon_campaigns');
            if ($campaignCols !== []) {
                if (!in_array('total_bet', $campaignCols, true)) {
                    $pdo->exec('ALTER TABLE drakon_campaigns ADD COLUMN total_bet VARCHAR(50) NULL AFTER freespins_per_player');
                }
                if (!in_array('game_ids', $campaignCols, true)) {
                    $pdo->exec('ALTER TABLE drakon_campaigns ADD COLUMN game_ids VARCHAR(500) NULL AFTER total_bet');
                }
                if (!in_array('request_id', $campaignCols, true)) {
                    $pdo->exec('ALTER TABLE drakon_campaigns ADD COLUMN request_id VARCHAR(190) NULL AFTER status');
                }
            }
            $done = true;
        } catch (Throwable $e) {
            // Leave $done false so a later call can retry; surface the reason.
            error_log('[DrakonService] ensureCampaignSchema failed: ' . $e->getMessage());
        }
    }

    /**
     * Perform an authenticated Campaign API request and normalise the envelope.
     *
     * @param array<string, mixed> $params
     * @param list<string>         $extraHeaders
     * @return array{success: bool, code: int, message: string, error_code: string, data: mixed, meta: array<string,mixed>, raw: array<string,mixed>}
     */
    private static function campaignRequest(
        PDO $pdo,
        string $method,
        string $path,
        array $params = [],
        array $extraHeaders = []
    ): array {
        $cfg     = self::config($pdo);
        $token   = self::getToken($pdo);
        $headers = array_merge([
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ], $extraHeaders);

        $response = self::httpRequest($method, self::apiBase($cfg) . $path, $params, $headers, 45);

        $status = $response['status'] ?? null;
        $ok     = $status === true || $status === 'success' || $status === 1;

        $message = (string) ($response['message'] ?? ($ok ? 'İşlem başarılı.' : 'İşlem başarısız.'));

        if (!$ok) {
            // Surface Laravel-style field validation errors so the operator sees
            // exactly which field failed instead of a generic "Validation failed".
            $errors = $response['errors']
                ?? (is_array($response['data'] ?? null) ? ($response['data']['errors'] ?? null) : null);
            if (is_array($errors) && $errors !== []) {
                $flat = [];
                foreach ($errors as $field => $fieldErrors) {
                    $detail = is_array($fieldErrors)
                        ? implode(', ', array_map('strval', $fieldErrors))
                        : (string) $fieldErrors;
                    $flat[] = $field . ': ' . $detail;
                }
                if ($flat !== []) {
                    $message .= ' — ' . implode(' | ', $flat);
                }
            }
            error_log(sprintf(
                '[DrakonService] campaign %s %s failed (%s): req=%s res=%s',
                $method,
                $path,
                (string) ($response['error_code'] ?? $response['error'] ?? ''),
                json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                substr((string) json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 1200)
            ));
        }

        return [
            'success'    => $ok,
            'code'       => $ok ? 200 : 422,
            'message'    => $message,
            'error_code' => (string) ($response['error_code'] ?? $response['error'] ?? ''),
            'data'       => $response['data'] ?? [],
            'meta'       => is_array($response['meta'] ?? null) ? $response['meta'] : [],
            'raw'        => $response,
        ];
    }

    /**
     * List vendors available for campaigns for the authenticated agent.
     */
    public static function campaignVendors(PDO $pdo): array
    {
        return self::campaignRequest($pdo, 'GET', '/campaigns/vendors');
    }

    /**
     * List allowed total_bet / freespin limits for vendors/games.
     *
     * @param array{vendors?: string, games?: string} $filters
     */
    public static function campaignVendorLimits(PDO $pdo, array $filters = []): array
    {
        $params = [];
        $vendors = trim((string) ($filters['vendors'] ?? ''));
        $games   = trim((string) ($filters['games'] ?? ''));
        if ($vendors !== '') {
            $params['vendors'] = $vendors;
        }
        if ($games !== '') {
            $params['games'] = $games;
        }
        return self::campaignRequest($pdo, 'GET', '/campaigns/vendors/limits', $params);
    }

    /**
     * Create a free-spin campaign and persist it locally for admin auditing.
     *
     * @param array<string, mixed> $input
     */
    public static function createCampaign(PDO $pdo, array $input): array
    {
        self::ensureCampaignSchema($pdo);

        $campaignCode = trim((string) ($input['campaign_code'] ?? ''));
        $vendor       = trim((string) ($input['vendor'] ?? ''));
        $freespins    = (int) ($input['freespins_per_player'] ?? 0);
        $gameId       = trim((string) ($input['game_id'] ?? ''));
        // Operators may paste the catalogue id ("drakon:17000"); the campaign API
        // expects the bare provider game id.
        if (self::ownsGameId($gameId)) {
            $gameId = substr($gameId, strlen(self::GAME_ID_PREFIX));
        }
        $totalBet     = str_replace(',', '.', trim((string) ($input['total_bet'] ?? '')));
        $beginsAt     = self::toUnixTimestamp($input['begins_at'] ?? '');
        $expiresAt    = self::toUnixTimestamp($input['expires_at'] ?? '');

        if ($campaignCode === '' || $vendor === '' || $freespins <= 0 || $gameId === '' || $totalBet === '') {
            return [
                'success' => false,
                'code'    => 422,
                'message' => 'Kampanya kodu, sağlayıcı, oyun, total_bet ve freespin sayısı zorunludur.',
            ];
        }
        if ($beginsAt <= 0 || $expiresAt <= 0 || $expiresAt <= $beginsAt) {
            return [
                'success' => false,
                'code'    => 422,
                'message' => 'Başlangıç ve bitiş zamanları geçerli olmalı (bitiş > başlangıç).',
            ];
        }

        $players = self::normalizePlayerList($input['players'] ?? '');

        $payload = [
            'campaign_code'        => $campaignCode,
            'vendor'               => $vendor,
            'freespins_per_player' => $freespins,
            'begins_at'            => $beginsAt,
            'expires_at'           => $expiresAt,
            'games'                => [
                ['game_id' => (int) $gameId, 'total_bet' => $totalBet],
            ],
        ];
        if ($players !== []) {
            $payload['players'] = $players;
        }

        $result = self::campaignRequest(
            $pdo,
            'POST',
            '/campaigns/create',
            $payload,
            [
                'Idempotency-Key: create-' . $campaignCode,
                'X-Request-Id: create-' . $campaignCode . '-' . time(),
            ]
        );

        if (!$result['success']) {
            return $result;
        }

        $data = is_array($result['data']) ? $result['data'] : [];
        $isActive = (string) ($data['status'] ?? 'active') === 'canceled' ? 0 : 1;
        if (array_key_exists('active', $data)) {
            $isActive = !empty($data['active']) ? 1 : 0;
        }
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO drakon_campaigns
                    (campaign_code, vendor, currency_code, freespins_per_player, total_bet, game_ids,
                     begins_at, expires_at, status, active, request_id, payload, remote_response)
                 VALUES
                    (:code, :vendor, :currency, :freespins, :total_bet, :games,
                     :begins_at, :expires_at, :status, :active, :request_id, :payload, :remote)
                 ON DUPLICATE KEY UPDATE
                    vendor = VALUES(vendor),
                    currency_code = VALUES(currency_code),
                    freespins_per_player = VALUES(freespins_per_player),
                    total_bet = VALUES(total_bet),
                    game_ids = VALUES(game_ids),
                    begins_at = VALUES(begins_at),
                    expires_at = VALUES(expires_at),
                    status = VALUES(status),
                    active = VALUES(active),
                    request_id = VALUES(request_id),
                    payload = VALUES(payload),
                    remote_response = VALUES(remote_response)'
            );
            $stmt->execute([
                ':code'       => $campaignCode,
                ':vendor'     => $vendor,
                ':currency'   => (string) ($data['currency_code'] ?? ''),
                ':freespins'  => $freespins,
                ':total_bet'  => $totalBet,
                ':games'      => (string) $gameId,
                ':begins_at'  => $beginsAt,
                ':expires_at' => $expiresAt,
                ':status'     => (string) ($data['status'] ?? 'active'),
                ':active'     => $isActive,
                ':request_id' => (string) ($result['meta']['request_id'] ?? ''),
                ':payload'    => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':remote'     => json_encode($result['raw'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            self::persistCampaignPlayers($pdo, $campaignCode, $players, 'assigned');
        } catch (Throwable $e) {
            // Local persistence is best-effort; log so a schema mismatch is visible.
            error_log('[DrakonService] createCampaign local persist failed: ' . $e->getMessage());
        }

        $result['campaign_code'] = $campaignCode;
        return $result;
    }

    /**
     * Fetch campaigns for the authenticated agent from Drakon.
     *
     * @param array<string, mixed> $filters
     */
    public static function listRemoteCampaigns(PDO $pdo, array $filters = []): array
    {
        $params = [];
        foreach (['vendor', 'status', 'active', 'per_page'] as $key) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $params[$key] = $value;
            }
        }
        return self::campaignRequest($pdo, 'GET', '/campaigns/list', $params);
    }

    /**
     * Fetch a single campaign detail.
     */
    public static function getCampaign(PDO $pdo, string $campaignCode): array
    {
        $campaignCode = trim($campaignCode);
        if ($campaignCode === '') {
            return ['success' => false, 'code' => 422, 'message' => 'Kampanya kodu gereklidir.'];
        }
        return self::campaignRequest($pdo, 'GET', '/campaigns/' . rawurlencode($campaignCode));
    }

    /**
     * Cancel a campaign and mark it inactive locally.
     */
    public static function cancelCampaign(PDO $pdo, string $campaignCode): array
    {
        self::ensureCampaignSchema($pdo);
        $campaignCode = trim($campaignCode);
        if ($campaignCode === '') {
            return ['success' => false, 'code' => 422, 'message' => 'Kampanya kodu gereklidir.'];
        }

        $result = self::campaignRequest(
            $pdo,
            'POST',
            '/campaigns/' . rawurlencode($campaignCode) . '/cancel',
            [],
            ['Idempotency-Key: cancel-' . $campaignCode]
        );

        if ($result['success']) {
            try {
                $stmt = $pdo->prepare(
                    "UPDATE drakon_campaigns SET status = 'canceled', active = 0 WHERE campaign_code = :code"
                );
                $stmt->execute([':code' => $campaignCode]);
            } catch (Throwable) {
            }
        }
        return $result;
    }

    /**
     * Assign players to a campaign and persist them locally.
     *
     * @param string|array<int, string> $players
     */
    public static function addCampaignPlayers(PDO $pdo, string $campaignCode, string|array $players): array
    {
        self::ensureCampaignSchema($pdo);
        $campaignCode = trim($campaignCode);
        $list = self::normalizePlayerList($players);
        if ($campaignCode === '' || $list === []) {
            return ['success' => false, 'code' => 422, 'message' => 'Kampanya kodu ve en az bir oyuncu ID gereklidir.'];
        }

        $result = self::campaignRequest(
            $pdo,
            'POST',
            '/campaigns/' . rawurlencode($campaignCode) . '/players/add',
            ['players' => $list],
            [
                'Idempotency-Key: add-' . $campaignCode . '-' . implode('-', $list),
            ]
        );

        if ($result['success']) {
            self::persistCampaignPlayers($pdo, $campaignCode, $list, 'assigned');
        }
        return $result;
    }

    /**
     * Remove players from a campaign and mark them locally.
     *
     * @param string|array<int, string> $players
     */
    public static function removeCampaignPlayers(PDO $pdo, string $campaignCode, string|array $players): array
    {
        self::ensureCampaignSchema($pdo);
        $campaignCode = trim($campaignCode);
        $list = self::normalizePlayerList($players);
        if ($campaignCode === '' || $list === []) {
            return ['success' => false, 'code' => 422, 'message' => 'Kampanya kodu ve en az bir oyuncu ID gereklidir.'];
        }

        $result = self::campaignRequest(
            $pdo,
            'POST',
            '/campaigns/' . rawurlencode($campaignCode) . '/players/remove',
            ['players' => $list],
            [
                'Idempotency-Key: remove-' . $campaignCode . '-' . implode('-', $list),
            ]
        );

        if ($result['success']) {
            try {
                $in = implode(',', array_fill(0, count($list), '?'));
                $stmt = $pdo->prepare(
                    "UPDATE drakon_campaign_players SET status = 'removed'
                     WHERE campaign_code = ? AND user_id IN ($in)"
                );
                $stmt->execute(array_merge([$campaignCode], array_map('intval', $list)));
            } catch (Throwable $e) {
                error_log('[DrakonService] removeCampaignPlayers local update failed: ' . $e->getMessage());
            }
        }
        return $result;
    }

    /**
     * Locally stored campaigns for admin listing.
     *
     * @return list<array<string, mixed>>
     */
    public static function localCampaigns(PDO $pdo, int $limit = 100): array
    {
        self::ensureCampaignSchema($pdo);
        try {
            $limit = max(1, min(500, $limit));
            $stmt = $pdo->query(
                'SELECT c.*, (
                    SELECT COUNT(*) FROM drakon_campaign_players p
                    WHERE p.campaign_code = c.campaign_code AND p.status = \'assigned\'
                 ) AS player_count
                 FROM drakon_campaigns c
                 ORDER BY c.id DESC
                 LIMIT ' . $limit
            );
            $rows = $stmt?->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param string|array<int, string> $players
     * @return list<string>
     */
    private static function normalizePlayerList(string|array $players): array
    {
        if (is_string($players)) {
            $players = preg_split('/[\s,;]+/', $players) ?: [];
        }
        $clean = [];
        foreach ($players as $player) {
            $id = trim((string) $player);
            if ($id !== '' && !in_array($id, $clean, true)) {
                $clean[] = $id;
            }
        }
        return $clean;
    }

    /**
     * @param list<string> $players
     */
    private static function persistCampaignPlayers(PDO $pdo, string $campaignCode, array $players, string $status): void
    {
        if ($players === []) {
            return;
        }
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO drakon_campaign_players (campaign_code, user_id, status)
                 VALUES (:code, :user, :status)
                 ON DUPLICATE KEY UPDATE status = VALUES(status)'
            );
            foreach ($players as $player) {
                $stmt->execute([':code' => $campaignCode, ':user' => (int) $player, ':status' => $status]);
            }
        } catch (Throwable $e) {
            error_log('[DrakonService] persistCampaignPlayers failed: ' . $e->getMessage());
        }
    }

    private static function toUnixTimestamp(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return 0;
        }
        if (ctype_digit($raw)) {
            return (int) $raw;
        }
        $ts = strtotime($raw);
        return $ts !== false ? $ts : 0;
    }

    // ─── Webhook ─────────────────────────────────────────────────────────────

    // Per the Drakon integration guide the webhook is a plain JSON endpoint that
    // dispatches on the `method` field with no transport-level authentication
    // (no signature, no IP allowlist). Security relies on an unguessable/private
    // callback URL as recommended by the vendor; correctness (idempotency, row
    // locking, balance checks) is enforced per-method below.

    public static function handleWebhook(PDO $pdo, array $payload): array
    {
        $start   = microtime(true);
        $method  = strtolower(trim((string) ($payload['method'] ?? '')));
        $userId  = null;
        $txnId   = null;
        $status  = 200;
        $errCode = null;

        try {
            $result = match ($method) {
                'account_details' => self::webhookAccountDetails($pdo, $payload),
                'user_balance'    => self::webhookUserBalance($pdo, $payload),
                'transaction_bet' => self::webhookBet($pdo, $payload),
                'transaction_win' => self::webhookWin($pdo, $payload),
                'refund'          => self::webhookRefund($pdo, $payload),
                default           => self::webhookError(400, 'INVALID_METHOD'),
            };

            $status  = (int) ($result['__http_status'] ?? 200);
            $errCode = isset($result['error']) ? (string) $result['error'] : null;
            $userId  = isset($result['__user_id']) ? (int) $result['__user_id'] : null;
            $txnId   = isset($result['__txn_id'])  ? (string) $result['__txn_id'] : null;
        } catch (Throwable $e) {
            $status  = 500;
            $errCode = 'SERVER_ERROR';
            $result  = ['status' => false, 'error' => 'SERVER_ERROR'];
        }

        // Strip internal meta keys before returning to Drakon
        unset($result['__http_status'], $result['__user_id'], $result['__txn_id']);

        $duration = (int) round((microtime(true) - $start) * 1000);
        try {
            self::logWebhook(
                $pdo,
                $method,
                $userId,
                $txnId,
                $status,
                $errCode,
                $duration,
                $payload,
                is_array($result) ? $result : null
            );
        } catch (Throwable) {}

        return ['status' => $status, 'body' => $result];
    }

    // ─── Webhook handlers ────────────────────────────────────────────────────

    private static function webhookAccountDetails(PDO $pdo, array $p): array
    {
        $userId = trim((string) ($p['user_id'] ?? ''));
        if ($userId === '') {
            return self::webhookError(200, 'INVALID_USER');
        }

        $stmt = $pdo->prepare(
            "SELECT id, email, name, surname, username, created_at
             FROM users WHERE id = :id AND banned = 0 LIMIT 1"
        );
        $stmt->execute([':id' => (int) $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($user)) {
            return self::webhookError(200, 'INVALID_USER');
        }

        $fullName = trim(trim((string) ($user['name'] ?? '')) . ' ' . trim((string) ($user['surname'] ?? '')));
        if ($fullName === '') {
            $fullName = (string) ($user['username'] ?? 'User');
        }

        // Doc expects ISO-8601 UTC (e.g. 2026-03-25T10:00:00Z); MySQL stores a
        // plain "Y-m-d H:i:s" string, so normalise it.
        $createdRaw = trim((string) ($user['created_at'] ?? ''));
        $createdTs  = $createdRaw !== '' ? strtotime($createdRaw) : false;
        $date       = gmdate('Y-m-d\TH:i:s\Z', $createdTs !== false ? $createdTs : time());

        return [
            '__http_status' => 200,
            '__user_id'     => (int) $user['id'],
            'email'         => (string) ($user['email'] ?? ''),
            'name_jogador'  => $fullName,
            'date'          => $date,
        ];
    }

    private static function webhookUserBalance(PDO $pdo, array $p): array
    {
        $userId = trim((string) ($p['user_id'] ?? ''));
        if ($userId === '') {
            return ['__http_status' => 200, 'status' => 0, 'error' => 'INVALID_USER'];
        }

        $stmt = $pdo->prepare("SELECT id, balance, bonus_balance FROM users WHERE id = :id AND banned = 0 LIMIT 1");
        $stmt->execute([':id' => (int) $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($user)) {
            return ['__http_status' => 200, 'status' => 0, 'error' => 'INVALID_USER'];
        }

        $walletColumn = WageringService::walletSourceColumn($pdo, (int) $user['id']);
        return [
            '__http_status' => 200,
            '__user_id'     => (int) $user['id'],
            'status'        => 1,
            'balance'       => round((float) ($user[$walletColumn] ?? 0), 2),
        ];
    }

    private static function webhookBet(PDO $pdo, array $p): array
    {
        $userId    = (int) ($p['user_id'] ?? 0);
        $txnId     = trim((string) ($p['transaction_id'] ?? ''));
        $roundId   = trim((string) ($p['round_id'] ?? ''));
        $sessionId = trim((string) ($p['session_id'] ?? ''));
        $gameId    = trim((string) ($p['game'] ?? ''));
        $bet       = round((float) ($p['bet'] ?? 0), 2);

        if ($userId <= 0) {
            return ['__http_status' => 200, '__txn_id' => $txnId, 'status' => false, 'error' => 'INVALID_USER'];
        }
        if ($txnId === '') {
            return ['__http_status' => 200, 'status' => false, 'error' => 'INVALID_TRANSACTION'];
        }

        self::ensureTransactionColumns($pdo);
        $walletColumn = WageringService::walletSourceColumn($pdo, $userId);

        // Idempotency check
        $existStmt = $pdo->prepare("SELECT id FROM drakon_transactions WHERE transaction_id = :txn LIMIT 1");
        $existStmt->execute([':txn' => $txnId]);
        if ($existStmt->fetch()) {
            $balStmt = $pdo->prepare("SELECT {$walletColumn} FROM users WHERE id = :id LIMIT 1");
            $balStmt->execute([':id' => $userId]);
            return ['__http_status' => 200, '__user_id' => $userId, '__txn_id' => $txnId,
                    'status' => true, 'balance' => round((float) ($balStmt->fetchColumn() ?: 0), 2)];
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT id, username, balance, bonus_balance FROM users WHERE id = :id AND banned = 0 LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($user)) {
                $pdo->rollBack();
                return ['__http_status' => 200, '__txn_id' => $txnId, 'status' => false, 'error' => 'INVALID_USER'];
            }

            $beforeBalance = round((float) ($user[$walletColumn] ?? 0), 2);
            if ($beforeBalance < $bet) {
                $pdo->rollBack();
                return ['__http_status' => 200, '__user_id' => $userId, '__txn_id' => $txnId,
                        'status' => false, 'error' => 'NO_BALANCE'];
            }

            $afterBalance = round($beforeBalance - $bet, 2);
            $pdo->prepare("UPDATE users SET {$walletColumn} = :bal WHERE id = :id")
                ->execute([':bal' => $afterBalance, ':id' => $userId]);
            WageringService::registerBet($pdo, $userId, $bet);

            $gameRow = self::findGameRow($pdo, $gameId);
            $pdo->prepare(
                "INSERT INTO drakon_transactions
                    (user_id, username, user_full_name, transaction_id, round_id, session_id,
                     game_id, game_name, provider_name, image_url,
                     txn_type, wallet_source, bet_amount, win_amount, amount, before_balance, after_balance, status)
                 VALUES (:uid, :uname, :ufull, :txn, :round, :sess,
                         :gid, :gname, :pname, :img,
                         'bet', :wallet_source, :bet, 0, :bet2, :before, :after, 'ok')"
            )->execute([
                ':uid'   => $userId,
                ':uname' => (string) ($user['username'] ?? ''),
                ':ufull' => self::userFullName($pdo, $userId),
                ':txn'   => $txnId,
                ':round' => $roundId,
                ':sess'  => $sessionId,
                ':gid'   => $gameId,
                ':gname' => (string) ($gameRow['game_name'] ?? $gameId),
                ':pname' => (string) ($gameRow['provider_name'] ?? ''),
                ':img'   => (string) ($gameRow['banner'] ?? ''),
                ':wallet_source' => $walletColumn,
                ':bet'   => $bet,
                ':bet2'  => $bet,
                ':before'=> $beforeBalance,
                ':after' => $afterBalance,
            ]);

            $pdo->commit();
            return ['__http_status' => 200, '__user_id' => $userId, '__txn_id' => $txnId,
                    'status' => true, 'balance' => $afterBalance];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Race condition: duplicate key → already processed
            if (str_contains($e->getMessage(), '1062') || str_contains($e->getMessage(), 'Duplicate')) {
                $balStmt = $pdo->prepare("SELECT {$walletColumn} FROM users WHERE id = :id LIMIT 1");
                $balStmt->execute([':id' => $userId]);
                return ['__http_status' => 200, '__user_id' => $userId, '__txn_id' => $txnId,
                        'status' => true, 'balance' => round((float) ($balStmt->fetchColumn() ?: 0), 2)];
            }
            throw $e;
        }
    }

    private static function webhookWin(PDO $pdo, array $p): array
    {
        $userId    = (int) ($p['user_id'] ?? 0);
        $txnId     = trim((string) ($p['transaction_id'] ?? ''));
        $roundId   = trim((string) ($p['round_id'] ?? ''));
        $sessionId = trim((string) ($p['session_id'] ?? ''));
        $gameId    = trim((string) ($p['game'] ?? ''));
        $win       = round((float) ($p['win'] ?? 0), 2);
        $bet       = round((float) ($p['bet'] ?? 0), 2);

        if ($userId <= 0) {
            return ['__http_status' => 200, '__txn_id' => $txnId, 'status' => false, 'error' => 'INVALID_USER'];
        }
        if ($txnId === '') {
            return ['__http_status' => 200, 'status' => false, 'error' => 'INVALID_TRANSACTION'];
        }

        self::ensureTransactionColumns($pdo);
        $walletColumn = WageringService::walletSourceColumn($pdo, $userId);

        // Idempotency
        $existStmt = $pdo->prepare("SELECT id FROM drakon_transactions WHERE transaction_id = :txn LIMIT 1");
        $existStmt->execute([':txn' => $txnId]);
        if ($existStmt->fetch()) {
            $balStmt = $pdo->prepare("SELECT {$walletColumn} FROM users WHERE id = :id LIMIT 1");
            $balStmt->execute([':id' => $userId]);
            return ['__http_status' => 200, '__user_id' => $userId, '__txn_id' => $txnId,
                    'status' => true, 'balance' => round((float) ($balStmt->fetchColumn() ?: 0), 2)];
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT id, username, balance, bonus_balance FROM users WHERE id = :id AND banned = 0 LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($user)) {
                $pdo->rollBack();
                return ['__http_status' => 200, '__txn_id' => $txnId, 'status' => false, 'error' => 'INVALID_USER'];
            }
            if ($win <= 0) {
                $pdo->rollBack();
                return ['__http_status' => 200, '__txn_id' => $txnId, 'status' => false, 'error' => 'NO_AMOUNT'];
            }

            $beforeBalance = round((float) ($user[$walletColumn] ?? 0), 2);
            $afterBalance  = round($beforeBalance + $win, 2);

            $pdo->prepare("UPDATE users SET {$walletColumn} = :bal WHERE id = :id")
                ->execute([':bal' => $afterBalance, ':id' => $userId]);

            $gameRow = self::findGameRow($pdo, $gameId);
            $pdo->prepare(
                "INSERT INTO drakon_transactions
                    (user_id, username, user_full_name, transaction_id, round_id, session_id,
                     game_id, game_name, provider_name, image_url,
                     txn_type, wallet_source, bet_amount, win_amount, amount, before_balance, after_balance, status)
                 VALUES (:uid, :uname, :ufull, :txn, :round, :sess,
                         :gid, :gname, :pname, :img,
                         'win', :wallet_source, :bet, :win, :win2, :before, :after, 'ok')"
            )->execute([
                ':uid'   => $userId,
                ':uname' => (string) ($user['username'] ?? ''),
                ':ufull' => self::userFullName($pdo, $userId),
                ':txn'   => $txnId,
                ':round' => $roundId,
                ':sess'  => $sessionId,
                ':gid'   => $gameId,
                ':gname' => (string) ($gameRow['game_name'] ?? $gameId),
                ':pname' => (string) ($gameRow['provider_name'] ?? ''),
                ':img'   => (string) ($gameRow['banner'] ?? ''),
                ':wallet_source' => $walletColumn,
                ':bet'   => $bet,
                ':win'   => $win,
                ':win2'  => $win,
                ':before'=> $beforeBalance,
                ':after' => $afterBalance,
            ]);

            $pdo->commit();
            return ['__http_status' => 200, '__user_id' => $userId, '__txn_id' => $txnId,
                    'status' => true, 'balance' => $afterBalance];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (str_contains($e->getMessage(), '1062') || str_contains($e->getMessage(), 'Duplicate')) {
                $balStmt = $pdo->prepare("SELECT {$walletColumn} FROM users WHERE id = :id LIMIT 1");
                $balStmt->execute([':id' => $userId]);
                return ['__http_status' => 200, '__user_id' => $userId, '__txn_id' => $txnId,
                        'status' => true, 'balance' => round((float) ($balStmt->fetchColumn() ?: 0), 2)];
            }
            throw $e;
        }
    }

    private static function webhookRefund(PDO $pdo, array $p): array
    {
        $userId    = (int) ($p['user_id'] ?? 0);
        $txnId     = trim((string) ($p['transaction_id'] ?? ''));
        $roundId   = trim((string) ($p['round_id'] ?? ''));
        $sessionId = trim((string) ($p['session_id'] ?? ''));
        $gameId    = trim((string) ($p['game'] ?? ''));
        $amount    = round((float) ($p['amount'] ?? 0), 2);

        if ($userId <= 0) {
            return ['__http_status' => 200, '__txn_id' => $txnId, 'status' => false, 'error' => 'INVALID_USER'];
        }
        if ($txnId === '' || $amount <= 0) {
            return ['__http_status' => 200, '__txn_id' => $txnId, 'status' => false, 'error' => 'NO_AMOUNT'];
        }

        self::ensureTransactionColumns($pdo);

        // Determine direction from original transaction type for this round, and
        // reverse against whichever wallet actually funded that original bet/win
        // (not necessarily the user's CURRENT wallet mode, in case it changed).
        $origStmt = $pdo->prepare(
            "SELECT txn_type, wallet_source FROM drakon_transactions
             WHERE round_id = :round AND user_id = :uid AND txn_type IN ('bet','win')
             ORDER BY id ASC LIMIT 1"
        );
        $origStmt->execute([':round' => $roundId, ':uid' => $userId]);
        $origRow  = $origStmt->fetch(PDO::FETCH_ASSOC);
        $origType = is_array($origRow) ? (string) ($origRow['txn_type'] ?? 'bet') : 'bet';
        $origWalletSource = is_array($origRow) ? (string) ($origRow['wallet_source'] ?? '') : '';
        $walletColumn = in_array($origWalletSource, ['balance', 'bonus_balance'], true)
            ? $origWalletSource
            : WageringService::walletSourceColumn($pdo, $userId);

        // Idempotency: refund transaction_id already exists?
        $existStmt = $pdo->prepare("SELECT id FROM drakon_transactions WHERE transaction_id = :txn LIMIT 1");
        $existStmt->execute([':txn' => $txnId]);
        if ($existStmt->fetch()) {
            $balStmt = $pdo->prepare("SELECT {$walletColumn} FROM users WHERE id = :id LIMIT 1");
            $balStmt->execute([':id' => $userId]);
            return ['__http_status' => 200, '__user_id' => $userId, '__txn_id' => $txnId,
                    'status' => true, 'balance' => round((float) ($balStmt->fetchColumn() ?: 0), 2)];
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT id, username, balance, bonus_balance FROM users WHERE id = :id AND banned = 0 LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($user)) {
                $pdo->rollBack();
                return ['__http_status' => 200, '__txn_id' => $txnId, 'status' => false, 'error' => 'INVALID_USER'];
            }

            $beforeBalance = round((float) ($user[$walletColumn] ?? 0), 2);
            // Bet refund → add back; Win refund → subtract
            $afterBalance = $origType === 'win'
                ? round($beforeBalance - $amount, 2)
                : round($beforeBalance + $amount, 2);

            $pdo->prepare("UPDATE users SET {$walletColumn} = :bal WHERE id = :id")
                ->execute([':bal' => $afterBalance, ':id' => $userId]);
            if ($origType === 'bet') {
                WageringService::reverseBet($pdo, $userId, $amount);
            }

            $gameRow = self::findGameRow($pdo, $gameId);
            $pdo->prepare(
                "INSERT INTO drakon_transactions
                    (user_id, username, user_full_name, transaction_id, round_id, session_id,
                     game_id, game_name, provider_name, image_url,
                     txn_type, wallet_source, bet_amount, win_amount, amount, before_balance, after_balance, status)
                 VALUES (:uid, :uname, :ufull, :txn, :round, :sess,
                         :gid, :gname, :pname, :img,
                         'refund', :wallet_source, 0, 0, :amt, :before, :after, 'ok')"
            )->execute([
                ':uid'   => $userId,
                ':uname' => (string) ($user['username'] ?? ''),
                ':ufull' => self::userFullName($pdo, $userId),
                ':txn'   => $txnId,
                ':round' => $roundId,
                ':sess'  => $sessionId,
                ':gid'   => $gameId,
                ':gname' => (string) ($gameRow['game_name'] ?? $gameId),
                ':pname' => (string) ($gameRow['provider_name'] ?? ''),
                ':img'   => (string) ($gameRow['banner'] ?? ''),
                ':wallet_source' => $walletColumn,
                ':amt'   => $amount,
                ':before'=> $beforeBalance,
                ':after' => $afterBalance,
            ]);

            $pdo->commit();
            return ['__http_status' => 200, '__user_id' => $userId, '__txn_id' => $txnId,
                    'status' => true, 'balance' => $afterBalance];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (str_contains($e->getMessage(), '1062') || str_contains($e->getMessage(), 'Duplicate')) {
                $balStmt = $pdo->prepare("SELECT {$walletColumn} FROM users WHERE id = :id LIMIT 1");
                $balStmt->execute([':id' => $userId]);
                return ['__http_status' => 200, '__user_id' => $userId, '__txn_id' => $txnId,
                        'status' => true, 'balance' => round((float) ($balStmt->fetchColumn() ?: 0), 2)];
            }
            throw $e;
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private static function webhookError(int $status, string $error): array
    {
        return ['__http_status' => $status, 'status' => false, 'error' => $error];
    }

    private static function findGameRow(PDO $pdo, string $gameId): array
    {
        if ($gameId === '') {
            return [];
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT game_name, provider_name, banner FROM drakon_games WHERE game_id = :gid LIMIT 1"
            );
            $stmt->execute([':gid' => $gameId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : [];
        } catch (Throwable) {
            return [];
        }
    }

    private static function userFullName(PDO $pdo, int $userId): string
    {
        static $cache = [];
        if (!isset($cache[$userId])) {
            try {
                $stmt = $pdo->prepare("SELECT name, surname FROM users WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $userId]);
                $row           = $stmt->fetch(PDO::FETCH_ASSOC);
                $cache[$userId] = is_array($row)
                    ? trim(trim((string) ($row['name'] ?? '')) . ' ' . trim((string) ($row['surname'] ?? '')))
                    : '';
            } catch (Throwable) {
                $cache[$userId] = '';
            }
        }
        return $cache[$userId];
    }

    /**
     * @param array<string, mixed>|null $requestPayload
     * @param array<string, mixed>|null $responsePayload
     */
    private static function logWebhook(
        PDO $pdo,
        string $method,
        ?int $userId,
        ?string $txnId,
        int $status,
        ?string $errCode,
        int $duration,
        ?array $requestPayload = null,
        ?array $responsePayload = null
    ): void {
        self::ensureWebhookLogColumns($pdo);
        $cols = self::tableColumns($pdo, 'drakon_webhook_logs');
        $hasPayloadCols = in_array('request_payload', $cols, true)
            && in_array('response_payload', $cols, true);

        if ($hasPayloadCols) {
            $pdo->prepare(
                "INSERT INTO drakon_webhook_logs
                    (method, user_id, transaction_id, http_status, error_code,
                     request_payload, response_payload, duration_ms)
                 VALUES (:m, :u, :t, :s, :e, :req, :res, :d)"
            )->execute([
                ':m'   => $method,
                ':u'   => $userId,
                ':t'   => $txnId,
                ':s'   => $status,
                ':e'   => $errCode,
                ':req' => $requestPayload !== null
                    ? json_encode(self::redactWebhookPayload($requestPayload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
                ':res' => $responsePayload !== null
                    ? json_encode(self::redactWebhookPayload($responsePayload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
                ':d'   => $duration,
            ]);
            return;
        }

        $pdo->prepare(
            "INSERT INTO drakon_webhook_logs
                (method, user_id, transaction_id, http_status, error_code, duration_ms)
             VALUES (:m, :u, :t, :s, :e, :d)"
        )->execute([
            ':m' => $method,
            ':u' => $userId,
            ':t' => $txnId,
            ':s' => $status,
            ':e' => $errCode,
            ':d' => $duration,
        ]);
    }

    private static function ensureWebhookLogColumns(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        try {
            $cols = self::tableColumns($pdo, 'drakon_webhook_logs');
            if ($cols === []) {
                return; // table not present yet; nothing to alter
            }
            if (!in_array('request_payload', $cols, true)) {
                $pdo->exec('ALTER TABLE drakon_webhook_logs ADD COLUMN request_payload MEDIUMTEXT NULL AFTER error_code');
            }
            if (!in_array('response_payload', $cols, true)) {
                $pdo->exec('ALTER TABLE drakon_webhook_logs ADD COLUMN response_payload MEDIUMTEXT NULL AFTER request_payload');
            }
            $done = true;
        } catch (Throwable $e) {
            error_log('[DrakonService] ensureWebhookLogColumns failed: ' . $e->getMessage());
        }
    }

    /**
     * Recursively mask sensitive keys before persisting webhook payloads.
     */
    private static function redactWebhookPayload(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }
        static $sensitive = ['agent_token', 'agent_secret', 'access_token', 'token', 'secret', 'authorization', 'callback_secret', 'password'];
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $sensitive, true)) {
                $out[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $out[$key] = self::redactWebhookPayload($value);
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    // ─── HTTP ─────────────────────────────────────────────────────────────────

    private static function requestContentType(array $headers): string
    {
        $contentType = 'application/json';
        foreach ($headers as $headerLine) {
            $headerLine = trim((string) $headerLine);
            if (stripos($headerLine, 'Content-Type:') === 0) {
                $contentType = strtolower(trim(substr($headerLine, strlen('Content-Type:'))));
            }
        }

        return $contentType;
    }

    private static function httpRequest(string $method, string $url, array $params = [], array $headers = [], int $timeout = 15): array
    {
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => max(15, $timeout),
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($params)) {
                $contentType = self::requestContentType($headers);
                curl_setopt($ch, CURLOPT_POSTFIELDS, str_contains($contentType, 'application/x-www-form-urlencoded')
                    ? http_build_query($params)
                    : json_encode($params));
            }
        }

        // Use project CA bundle if available
        foreach ([
            defined('ROOT_PATH') ? ROOT_PATH . '/config/cacert.pem' : '',
            defined('BASE_PATH') ? BASE_PATH . '/config/cacert.pem' : '',
        ] as $caInfo) {
            if ($caInfo !== '' && is_readable($caInfo)) {
                curl_setopt($ch, CURLOPT_CAINFO, $caInfo);
                break;
            }
        }

        $raw  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            throw new RuntimeException('Drakon API cURL hatası: ' . $err);
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException(
                'Drakon API geçersiz JSON (HTTP ' . $code . '): ' . substr((string) $raw, 0, 200)
            );
        }

        return $decoded;
    }
}
