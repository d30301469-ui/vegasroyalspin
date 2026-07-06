<?php

declare(strict_types=1);

final class BgamingWalletException extends RuntimeException
{
    public function __construct(private readonly array $error)
    {
        parent::__construct((string) ($error['message'] ?? $error['code'] ?? 'BGaming wallet error'));
    }

    public function error(): array
    {
        return $this->error;
    }
}

final class BgamingService
{
    private const DEFAULT_API_BASE = 'https://int.bgaming-system.com';
    private const GAME_ID_PREFIX = 'bgaming:';
    private const DEFAULT_CURRENCY = 'USD';
    private const ALLOWED_CURRENCIES = ['TRY', 'USD', 'EUR', 'JPY', 'USDT', 'ETH', 'XRP', 'LTC', 'DOG', 'BTC', 'BCH'];
    private const ALLOWED_LOCALES = ['bg', 'de', 'el', 'en', 'es', 'fr', 'id', 'it', 'ko', 'pt-BR', 'ro', 'ru', 'sv', 'tr', 'uk', 'zh'];

    public static function bootstrap(PDO $pdo): void
    {
        if ((string) getenv('METROPOL_RUNTIME_PROVIDER_BOOTSTRAP') !== '1' || !self::runtimeSchemaChangesAllowed()) {
            return;
        }

        self::createSchema($pdo);
        self::ensureDefaultConfig($pdo);
    }

    public static function createSchema(PDO $pdo): void
    {
        if (!self::runtimeSchemaChangesAllowed()) {
            throw new RuntimeException('Runtime provider schema changes are disabled in production.');
        }

        $defaultApiBase = str_replace("'", "''", trim((string) (getenv('BGAMING_API_BASE_URL') ?: self::DEFAULT_API_BASE)));
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS bgaming_config (
                id TINYINT UNSIGNED NOT NULL DEFAULT 1,
                server_id VARCHAR(100) NOT NULL DEFAULT '',
                casino_id VARCHAR(100) NOT NULL DEFAULT '',
                api_base_url VARCHAR(255) NOT NULL DEFAULT '{$defaultApiBase}',
                wallet_secret VARCHAR(255) NOT NULL DEFAULT '',
                pending_wallet_secret VARCHAR(255) NOT NULL DEFAULT '',
                pending_wallet_secret_activates_at DATETIME NULL,
                currency VARCHAR(8) NOT NULL DEFAULT 'USD',
                locale VARCHAR(10) NOT NULL DEFAULT 'tr',
                country CHAR(2) NOT NULL DEFAULT 'TR',
                return_url VARCHAR(255) NOT NULL DEFAULT '',
                wallet_url VARCHAR(255) NOT NULL DEFAULT '',
                freespins_enabled TINYINT(1) NOT NULL DEFAULT 1,
                promo_enabled TINYINT(1) NOT NULL DEFAULT 1,
                token_rotation_enabled TINYINT(1) NOT NULL DEFAULT 1,
                is_active TINYINT(1) NOT NULL DEFAULT 0,
                synced_at DATETIME NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS bgaming_games (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                identifier VARCHAR(120) NOT NULL,
                title VARCHAR(255) NOT NULL,
                provider VARCHAR(100) NOT NULL DEFAULT 'bgaming',
                category VARCHAR(80) NULL,
                api_freespins TINYINT(1) NOT NULL DEFAULT 0,
                in_game_freespins TINYINT(1) NOT NULL DEFAULT 0,
                bet_type VARCHAR(100) NULL,
                api_version VARCHAR(40) NULL,
                lines_count INT NULL,
                bet_levels JSON NULL,
                default_bet_cents INT NULL,
                max_multiplier INT NULL,
                locales JSON NULL,
                rtp DECIMAL(6,2) NULL,
                thumbnail_url VARCHAR(500) NULL,
                raw_payload JSON NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                is_featured TINYINT(1) NOT NULL DEFAULT 0,
                synced_at DATETIME NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_bgaming_identifier (identifier),
                KEY idx_bgaming_games_provider (provider),
                KEY idx_bgaming_games_active (is_active),
                KEY idx_bgaming_games_title (title)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS bgaming_game_sessions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                session_id VARCHAR(190) NOT NULL,
                user_id INT NULL,
                username VARCHAR(100) NULL,
                game_identifier VARCHAR(120) NOT NULL,
                mode ENUM('real','fun') NOT NULL DEFAULT 'real',
                currency VARCHAR(8) NOT NULL DEFAULT 'USD',
                locale VARCHAR(10) NOT NULL DEFAULT 'tr',
                game_url TEXT NULL,
                request_payload JSON NULL,
                response_payload JSON NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_bgaming_session_id (session_id),
                KEY idx_bgaming_sessions_user (user_id),
                KEY idx_bgaming_sessions_game (game_identifier)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS bgaming_transactions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT NOT NULL,
                action_id VARCHAR(190) NOT NULL,
                original_action_id VARCHAR(190) NULL,
                casino_tx_id VARCHAR(190) NOT NULL,
                session_id VARCHAR(190) NULL,
                round_id VARCHAR(190) NULL,
                casino_round_id VARCHAR(190) NOT NULL,
                game_identifier VARCHAR(120) NULL,
                txn_type ENUM('bet','win','rollback','promo_bet','promo_win','freespins_win') NOT NULL,
                amount_subunits BIGINT NOT NULL DEFAULT 0,
                amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                before_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                after_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                raw_payload JSON NULL,
                processed_at DATETIME NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_bgaming_action_id (action_id),
                UNIQUE KEY uniq_bgaming_casino_tx_id (casino_tx_id),
                KEY idx_bgaming_tx_user (user_id),
                KEY idx_bgaming_tx_round (round_id),
                KEY idx_bgaming_tx_session (session_id),
                KEY idx_bgaming_tx_original (original_action_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS bgaming_wallet_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                endpoint VARCHAR(80) NOT NULL,
                http_status SMALLINT NOT NULL DEFAULT 200,
                user_id INT NULL,
                action_id VARCHAR(190) NULL,
                request_payload JSON NULL,
                response_payload JSON NULL,
                error_code VARCHAR(100) NULL,
                duration_ms INT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_bgaming_logs_endpoint (endpoint),
                KEY idx_bgaming_logs_action (action_id),
                KEY idx_bgaming_logs_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS bgaming_token_rotation_nonces (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                nonce_hash CHAR(64) NOT NULL,
                nonce VARCHAR(190) NOT NULL,
                request_payload JSON NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_bgaming_token_rotation_nonce_hash (nonce_hash),
                KEY idx_bgaming_token_rotation_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::ensureColumns($pdo);
    }

    private static function ensureColumns(PDO $pdo): void
    {
        $columns = [
            'bgaming_config.pending_wallet_secret' => "ALTER TABLE bgaming_config ADD COLUMN pending_wallet_secret VARCHAR(255) NOT NULL DEFAULT '' AFTER wallet_secret",
            'bgaming_config.pending_wallet_secret_activates_at' => "ALTER TABLE bgaming_config ADD COLUMN pending_wallet_secret_activates_at DATETIME NULL AFTER pending_wallet_secret",
            'bgaming_config.freespins_enabled' => "ALTER TABLE bgaming_config ADD COLUMN freespins_enabled TINYINT(1) NOT NULL DEFAULT 1",
            'bgaming_config.promo_enabled' => "ALTER TABLE bgaming_config ADD COLUMN promo_enabled TINYINT(1) NOT NULL DEFAULT 1",
            'bgaming_config.token_rotation_enabled' => "ALTER TABLE bgaming_config ADD COLUMN token_rotation_enabled TINYINT(1) NOT NULL DEFAULT 1",
            'bgaming_games.bet_levels' => "ALTER TABLE bgaming_games ADD COLUMN bet_levels JSON NULL AFTER lines_count",
            'bgaming_games.default_bet_cents' => "ALTER TABLE bgaming_games ADD COLUMN default_bet_cents INT NULL AFTER bet_levels",
            'bgaming_games.max_multiplier' => "ALTER TABLE bgaming_games ADD COLUMN max_multiplier INT NULL AFTER default_bet_cents",
        ];
        foreach ($columns as $key => $sql) {
            [$table, $column] = explode('.', $key, 2);
            if (!self::columnExists($pdo, $table, $column)) {
                try {
                    $pdo->exec($sql);
                } catch (Throwable) {
                }
            }
        }
        self::ensureColumnDefinitions($pdo);
    }

    private static function ensureColumnDefinitions(PDO $pdo): void
    {
        foreach ([
            "ALTER TABLE bgaming_config MODIFY currency VARCHAR(8) NOT NULL DEFAULT 'USD'",
            "ALTER TABLE bgaming_game_sessions MODIFY currency VARCHAR(8) NOT NULL DEFAULT 'USD'",
        ] as $sql) {
            try {
                $pdo->exec($sql);
            } catch (Throwable) {
            }
        }
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $stmt->execute(['table' => $table, 'column' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private static function runtimeSchemaChangesAllowed(): bool
    {
        if (in_array(strtolower(trim((string) getenv('APP_ENV'))), ['production', 'prod'], true)) {
            return false;
        }

        $override = trim((string) getenv('ALLOW_RUNTIME_MIGRATIONS'));
        if ($override !== '') {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }

        return true;
    }

    public static function ensureDefaultConfig(PDO $pdo): void
    {
        $stmt = $pdo->query('SELECT COUNT(*) FROM bgaming_config WHERE id = 1');
        if ((int) $stmt->fetchColumn() > 0) {
            self::backfillDefaultConfig($pdo);
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO bgaming_config
                (id, server_id, casino_id, api_base_url, wallet_secret, currency, locale, country, return_url, wallet_url, is_active)
             VALUES
                (1, :server_id, :casino_id, :api_base_url, :wallet_secret, :currency, :locale, :country, :return_url, :wallet_url, 0)'
        );
        $insert->execute([
            'server_id' => '',
            'casino_id' => '',
            'api_base_url' => trim((string) (getenv('BGAMING_API_BASE_URL') ?: self::DEFAULT_API_BASE)),
            'wallet_secret' => '',
            'currency' => self::DEFAULT_CURRENCY,
            'locale' => 'tr',
            'country' => 'TR',
            'return_url' => self::frontendEndpoint() . '/bgaming',
            'wallet_url' => self::backendEndpoint() . '/api/v2/bgaming-wallet',
        ]);
    }

    private static function backfillDefaultConfig(PDO $pdo): void
    {
        $stmt = $pdo->query('SELECT * FROM bgaming_config WHERE id = 1 LIMIT 1');
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($config)) {
            return;
        }

        $updates = [];
        $params = ['id' => 1];
        $defaults = [
            'api_base_url' => self::DEFAULT_API_BASE,
            'currency' => self::DEFAULT_CURRENCY,
            'locale' => 'tr',
            'country' => 'TR',
            'return_url' => self::frontendEndpoint() . '/bgaming',
            'wallet_url' => self::backendEndpoint() . '/api/v2/bgaming-wallet',
        ];

        foreach ($defaults as $column => $value) {
            $current = trim((string) ($config[$column] ?? ''));
            if ($column === 'api_base_url' && $current === self::DEFAULT_API_BASE) {
                // Keep the full BGaming-provided GCP_URL visible in admin until the user saves.
            } elseif ($column === 'currency' && !in_array(strtoupper($current), self::ALLOWED_CURRENCIES, true)) {
                // Replace unsupported values with the admin default.
            } elseif ($current !== '') {
                continue;
            }
            $updates[] = $column . ' = :' . $column;
            $params[$column] = $value;
        }

        if ($updates === []) {
            return;
        }

        $update = $pdo->prepare('UPDATE bgaming_config SET ' . implode(', ', $updates) . ' WHERE id = :id');
        $update->execute($params);
    }

    public static function config(PDO $pdo): array
    {
        self::bootstrap($pdo);
        $stmt = $pdo->query('SELECT * FROM bgaming_config WHERE id = 1 LIMIT 1');
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($config) ? $config : [];
    }

    public static function updateConfig(PDO $pdo, array $input): void
    {
        self::bootstrap($pdo);
        $stmt = $pdo->prepare(
            'UPDATE bgaming_config
             SET server_id = :server_id,
                 casino_id = :casino_id,
                 api_base_url = :api_base_url,
                 wallet_secret = :wallet_secret,
                 currency = :currency,
                 locale = :locale,
                 country = :country,
                 return_url = :return_url,
                 wallet_url = :wallet_url,
                 freespins_enabled = :freespins_enabled,
                 promo_enabled = :promo_enabled,
                 token_rotation_enabled = :token_rotation_enabled,
                 is_active = :is_active
             WHERE id = 1'
        );
        $rawGcpUrl = rtrim(trim((string) ($input['api_base_url'] ?? self::DEFAULT_API_BASE)), '/');
        $normalizedGcp = self::normalizeGcpUrl($rawGcpUrl);
        $serverId = trim((string) ($input['server_id'] ?? ''));
        if ($normalizedGcp['server_id'] !== '') {
            $serverId = $normalizedGcp['server_id'];
        }
        $casinoId = trim((string) ($input['casino_id'] ?? ''));
        $currentConfig = self::config($pdo);
        $walletSecret = trim((string) ($input['wallet_secret'] ?? ''));
        if ($walletSecret === '') {
            $walletSecret = trim((string) ($currentConfig['wallet_secret'] ?? ''));
        }
        $stmt->execute([
            'server_id' => $serverId,
            'casino_id' => $casinoId !== '' ? $casinoId : $serverId,
            'api_base_url' => $rawGcpUrl !== '' ? $rawGcpUrl : self::DEFAULT_API_BASE,
            'wallet_secret' => $walletSecret,
            'currency' => self::normalizeCurrency((string) ($input['currency'] ?? self::DEFAULT_CURRENCY)),
            'locale' => self::normalizeLocale((string) ($input['locale'] ?? 'tr')),
            'country' => strtoupper(substr(trim((string) ($input['country'] ?? 'TR')), 0, 2)) ?: 'TR',
            'return_url' => rtrim(trim((string) ($input['return_url'] ?? self::frontendEndpoint() . '/bgaming')), '/'),
            'wallet_url' => rtrim(trim((string) ($input['wallet_url'] ?? self::backendEndpoint() . '/api/v2/bgaming-wallet')), '/'),
            'freespins_enabled' => !empty($input['freespins_enabled']) ? 1 : 0,
            'promo_enabled' => !empty($input['promo_enabled']) ? 1 : 0,
            'token_rotation_enabled' => !empty($input['token_rotation_enabled']) ? 1 : 0,
            'is_active' => !empty($input['is_active']) ? 1 : 0,
        ]);

        if (trim((string) ($input['wallet_secret'] ?? '')) !== '') {
            $pdo->prepare('UPDATE bgaming_config SET pending_wallet_secret = \'\', pending_wallet_secret_activates_at = NULL WHERE id = 1')->execute();
        }
    }

    public static function campaigns(PDO $pdo): array
    {
        self::bootstrap($pdo);
        self::ensureCampaignStorage($pdo);
        $stmt = $pdo->query(
            'SELECT * FROM bgaming_campaigns ORDER BY active DESC, created_at DESC, id DESC'
        );
        return $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public static function campaignAssignments(PDO $pdo, int $limit = 100): array
    {
        self::bootstrap($pdo);
        self::ensureCampaignStorage($pdo);
        $limit = min(200, max(1, $limit));
        $stmt = $pdo->prepare(
            'SELECT cp.*, c.title, c.campaign_type, c.freespins_per_player, c.promo_amount, u.username
             FROM bgaming_campaign_players cp
             INNER JOIN bgaming_campaigns c ON c.campaign_code = cp.campaign_code
             LEFT JOIN users u ON u.id = cp.user_id
             ORDER BY cp.created_at DESC, cp.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function campaignById(PDO $pdo, int $id): ?array
    {
        self::bootstrap($pdo);
        self::ensureCampaignStorage($pdo);
        if ($id <= 0) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT * FROM bgaming_campaigns WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public static function saveCampaign(PDO $pdo, array $input): array
    {
        self::bootstrap($pdo);
        self::ensureCampaignStorage($pdo);

        $id = max(0, (int) ($input['id'] ?? 0));
        $campaignType = self::normalizeCampaignType((string) ($input['campaign_type'] ?? 'freespin'));
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new RuntimeException('Kampanya başlığı zorunludur.');
        }

        $campaignCode = trim((string) ($input['campaign_code'] ?? ''));
        if ($campaignCode === '') {
            $campaignCode = self::generateCampaignCode($campaignType);
        }

        $gameIdentifier = self::normalizeGameIdentifier(trim((string) ($input['game_identifier'] ?? '')));
        $currencyCode = self::normalizeCurrency((string) ($input['currency_code'] ?? self::config($pdo)['currency'] ?? self::DEFAULT_CURRENCY));
        $freespinsPerPlayer = max(0, (int) ($input['freespins_per_player'] ?? 0));
        $promoAmount = round((float) ($input['promo_amount'] ?? 0), 2);
        $wageringMultiplier = max(0, (float) ($input['wagering_multiplier'] ?? 0));
        $beginsAt = self::parseUnixTimestamp((string) ($input['begins_at'] ?? ''));
        $expiresAt = self::parseUnixTimestamp((string) ($input['expires_at'] ?? ''));
        $active = !empty($input['active']) ? 1 : 0;

        if ($campaignType === 'freespin' && $freespinsPerPlayer <= 0) {
            throw new RuntimeException('Freespin adedi 1 veya daha büyük olmalıdır.');
        }
        if ($campaignType === 'promo' && $promoAmount <= 0) {
            throw new RuntimeException('Promo tutarı 0 dan büyük olmalıdır.');
        }

        $payload = [
            'notes' => trim((string) ($input['notes'] ?? '')),
            'created_from_admin' => true,
        ];

        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE bgaming_campaigns
                 SET campaign_code = :campaign_code,
                     title = :title,
                     campaign_type = :campaign_type,
                     game_identifier = :game_identifier,
                     currency_code = :currency_code,
                     freespins_per_player = :freespins_per_player,
                     promo_amount = :promo_amount,
                     wagering_multiplier = :wagering_multiplier,
                     begins_at = :begins_at,
                     expires_at = :expires_at,
                     active = :active,
                     status = :status,
                     payload = :payload
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'campaign_code' => $campaignCode,
                'title' => $title,
                'campaign_type' => $campaignType,
                'game_identifier' => $gameIdentifier !== '' ? $gameIdentifier : null,
                'currency_code' => $currencyCode,
                'freespins_per_player' => $freespinsPerPlayer,
                'promo_amount' => number_format($promoAmount, 2, '.', ''),
                'wagering_multiplier' => number_format($wageringMultiplier, 2, '.', ''),
                'begins_at' => $beginsAt,
                'expires_at' => $expiresAt,
                'active' => $active,
                'status' => $active === 1 ? 'active' : 'inactive',
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO bgaming_campaigns
                    (campaign_code, title, campaign_type, game_identifier, vendor, currency_code,
                     freespins_per_player, promo_amount, wagering_multiplier, begins_at, expires_at,
                     active, status, payload)
                 VALUES
                    (:campaign_code, :title, :campaign_type, :game_identifier, :vendor, :currency_code,
                     :freespins_per_player, :promo_amount, :wagering_multiplier, :begins_at, :expires_at,
                     :active, :status, :payload)'
            );
            $stmt->execute([
                'campaign_code' => $campaignCode,
                'title' => $title,
                'campaign_type' => $campaignType,
                'game_identifier' => $gameIdentifier !== '' ? $gameIdentifier : null,
                'vendor' => 'bgaming',
                'currency_code' => $currencyCode,
                'freespins_per_player' => $freespinsPerPlayer,
                'promo_amount' => number_format($promoAmount, 2, '.', ''),
                'wagering_multiplier' => number_format($wageringMultiplier, 2, '.', ''),
                'begins_at' => $beginsAt,
                'expires_at' => $expiresAt,
                'active' => $active,
                'status' => $active === 1 ? 'active' : 'inactive',
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $id = (int) $pdo->lastInsertId();
        }

        return [
            'id' => $id,
            'campaign_code' => $campaignCode,
            'campaign_type' => $campaignType,
            'title' => $title,
        ];
    }

    public static function assignCampaign(PDO $pdo, array $input): array
    {
        self::bootstrap($pdo);
        self::ensureCampaignStorage($pdo);

        $campaignId = max(0, (int) ($input['campaign_id'] ?? 0));
        $userId = max(0, (int) ($input['user_id'] ?? 0));
        if ($campaignId <= 0 || $userId <= 0) {
            throw new RuntimeException('campaign_id ve user_id zorunludur.');
        }

        $campaign = self::campaignById($pdo, $campaignId);
        if ($campaign === null) {
            throw new RuntimeException('BGaming kampanyası bulunamadı.');
        }
        if ((int) ($campaign['active'] ?? 0) !== 1) {
            throw new RuntimeException('Pasif kampanya kullanıcıya atanamaz.');
        }

        $user = self::user($pdo, $userId);
        if ($user === null) {
            throw new RuntimeException('Kullanıcı bulunamadı.');
        }

        $campaignCode = (string) ($campaign['campaign_code'] ?? '');
        $existing = $pdo->prepare('SELECT id FROM bgaming_campaign_players WHERE campaign_code = :campaign_code AND user_id = :user_id LIMIT 1');
        $existing->execute(['campaign_code' => $campaignCode, 'user_id' => $userId]);
        if ($existing->fetchColumn()) {
            throw new RuntimeException('Kampanya bu kullanıcıya zaten atanmış.');
        }

        $bonusId = null;
        $pdo->beginTransaction();
        try {
            if ((string) ($campaign['campaign_type'] ?? '') === 'promo') {
                $promoAmount = round((float) ($campaign['promo_amount'] ?? 0), 2);
                $wageringMultiplier = max(0, (float) ($campaign['wagering_multiplier'] ?? 0));
                $wageringTarget = $promoAmount * max(1, $wageringMultiplier);
                $deadlineTs = (int) ($campaign['expires_at'] ?? 0);
                $deadline = $deadlineTs > 0 ? date('Y-m-d H:i:s', $deadlineTs) : date('Y-m-d H:i:s', strtotime('+30 days'));

                $stmt = $pdo->prepare(
                    "INSERT INTO user_active_bonuses
                        (user_id, promotion_id, name, category, initial_amount, current_bonus_balance,
                         wagering_requirement, wagering_target, total_bet_amount, status, granted_at, deadline)
                     VALUES
                        (:user_id, NULL, :name, 'bgaming_promo', :amount, :amount,
                         :wagering_requirement, :wagering_target, 0, 'active', NOW(), :deadline)"
                );
                $stmt->execute([
                    'user_id' => $userId,
                    'name' => (string) ($campaign['title'] ?? 'BGaming Promo'),
                    'amount' => number_format($promoAmount, 2, '.', ''),
                    'wagering_requirement' => number_format($wageringMultiplier, 2, '.', ''),
                    'wagering_target' => number_format($wageringTarget, 2, '.', ''),
                    'deadline' => $deadline,
                ]);
                $bonusId = (int) $pdo->lastInsertId();
            }

            $stmt = $pdo->prepare(
                'INSERT INTO bgaming_campaign_players
                    (campaign_code, user_id, bonus_id, status, payload)
                 VALUES
                    (:campaign_code, :user_id, :bonus_id, :status, :payload)'
            );
            $stmt->execute([
                'campaign_code' => $campaignCode,
                'user_id' => $userId,
                'bonus_id' => $bonusId,
                'status' => (string) ($campaign['campaign_type'] ?? '') === 'promo' ? 'bonus_assigned' : 'assigned',
                'payload' => json_encode([
                    'campaign_title' => (string) ($campaign['title'] ?? ''),
                    'campaign_type' => (string) ($campaign['campaign_type'] ?? ''),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        return [
            'campaign_code' => $campaignCode,
            'user_id' => $userId,
            'bonus_id' => $bonusId,
        ];
    }

    public static function memberFreespins(PDO $pdo, int $userId, string $tab = 'yeni'): array
    {
        self::bootstrap($pdo);
        self::ensureCampaignStorage($pdo);
        if ($userId <= 0) {
            return [];
        }

        $rows = [];
        try {
            $stmt = $pdo->prepare(
                'SELECT cp.status AS player_status, c.campaign_code, c.title, c.game_identifier, c.currency_code,
                        c.freespins_per_player, c.begins_at, c.expires_at, c.active, c.status AS campaign_status,
                        COALESCE(g.thumbnail_url, \'\') AS image_url
                 FROM bgaming_campaign_players cp
                 INNER JOIN bgaming_campaigns c ON c.campaign_code = cp.campaign_code
                 LEFT JOIN bgaming_games g ON g.identifier = c.game_identifier
                 WHERE cp.user_id = :user_id AND c.campaign_type = :campaign_type
                 ORDER BY c.created_at DESC, c.id DESC'
            );
            $stmt->execute(['user_id' => $userId, 'campaign_type' => 'freespin']);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            // Legacy kurulumlarda bgaming_games tablosu yoksa freespin listesi yine çalışsın.
            $stmt = $pdo->prepare(
                'SELECT cp.status AS player_status, c.campaign_code, c.title, c.game_identifier, c.currency_code,
                        c.freespins_per_player, c.begins_at, c.expires_at, c.active, c.status AS campaign_status,
                        \'\' AS image_url
                 FROM bgaming_campaign_players cp
                 INNER JOIN bgaming_campaigns c ON c.campaign_code = cp.campaign_code
                 WHERE cp.user_id = :user_id AND c.campaign_type = :campaign_type
                 ORDER BY c.created_at DESC, c.id DESC'
            );
            $stmt->execute(['user_id' => $userId, 'campaign_type' => 'freespin']);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $items = [];
        $now = time();
        foreach ($rows as $row) {
            $beginsAt = isset($row['begins_at']) ? (int) $row['begins_at'] : 0;
            $expiresAt = isset($row['expires_at']) ? (int) $row['expires_at'] : 0;
            $status = 'new';

            if (in_array((string) ($row['player_status'] ?? ''), ['played', 'completed'], true)) {
                $status = 'played';
            } elseif ($expiresAt > 0 && $expiresAt < $now) {
                $status = 'expired';
            } elseif ((int) ($row['active'] ?? 0) === 1 && ($beginsAt === 0 || $beginsAt <= $now) && ($expiresAt === 0 || $expiresAt >= $now)) {
                $status = 'active';
            }

            $isAktifTab = $tab === 'aktif';
            if ($isAktifTab && $status !== 'active') {
                continue;
            }
            if (!$isAktifTab && !in_array($status, ['new', 'active'], true)) {
                continue;
            }

            $items[] = [
                'campaign_code' => (string) ($row['campaign_code'] ?? ''),
                'vendor' => 'bgaming',
                'status' => $status,
                'freespins_per_player' => (int) ($row['freespins_per_player'] ?? 0),
                'begins_at' => $beginsAt,
                'expires_at' => $expiresAt,
                'currency_code' => (string) ($row['currency_code'] ?? ''),
                'game_identifier' => (string) ($row['game_identifier'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'image_url' => (string) ($row['image_url'] ?? ''),
            ];
        }

        return $items;
    }

    public static function syncGames(PDO $pdo): array
    {
        $response = self::request($pdo, 'GET', '/gamelist');
        $games = self::listFromDirectResponse($response);
        $betLevelsByGame = self::betLevelsByGame($pdo);
        $stmt = $pdo->prepare(
            "INSERT INTO bgaming_games
                (identifier, title, provider, category, api_freespins, in_game_freespins, bet_type, api_version,
                 lines_count, bet_levels, default_bet_cents, max_multiplier, locales, rtp, thumbnail_url, raw_payload, is_active, synced_at)
             VALUES
                (:identifier, :title, :provider, :category, :api_freespins, :in_game_freespins, :bet_type, :api_version,
                 :lines_count, :bet_levels, :default_bet_cents, :max_multiplier, :locales, :rtp, :thumbnail_url, :raw_payload, 1, NOW())
             ON DUPLICATE KEY UPDATE
                title = VALUES(title), provider = VALUES(provider), category = VALUES(category),
                api_freespins = VALUES(api_freespins), in_game_freespins = VALUES(in_game_freespins),
                bet_type = VALUES(bet_type), api_version = VALUES(api_version), lines_count = VALUES(lines_count),
                bet_levels = VALUES(bet_levels), default_bet_cents = VALUES(default_bet_cents),
                max_multiplier = VALUES(max_multiplier), locales = VALUES(locales), rtp = VALUES(rtp), thumbnail_url = VALUES(thumbnail_url),
                raw_payload = VALUES(raw_payload), is_active = 1, synced_at = NOW()"
        );

        foreach ($games as $game) {
            $identifier = trim((string) ($game['identifier'] ?? ''));
            if ($identifier === '') {
                continue;
            }
            $rawPayload = json_encode($game, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $locales = json_encode($game['locales'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $gameTable = self::gameTable($game);
            if ($gameTable === [] && isset($betLevelsByGame[$identifier])) {
                $gameTable = $betLevelsByGame[$identifier];
            }
            $betLevels = json_encode($gameTable['bet_levels'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $stmt->execute([
                'identifier' => $identifier,
                'title' => trim((string) ($game['title'] ?? $identifier)),
                'provider' => trim((string) ($game['provider'] ?? 'bgaming')) ?: 'bgaming',
                'category' => trim((string) ($game['category'] ?? '')),
                'api_freespins' => !empty($game['api_freespins']) ? 1 : 0,
                'in_game_freespins' => !empty($game['in_game_freespins']) ? 1 : 0,
                'bet_type' => trim((string) ($game['bet_type'] ?? '')),
                'api_version' => trim((string) ($game['api_version'] ?? '')),
                'lines_count' => isset($game['lines_count']) ? (int) $game['lines_count'] : (isset($gameTable['lines_count']) ? (int) $gameTable['lines_count'] : null),
                'bet_levels' => is_string($betLevels) ? $betLevels : null,
                'default_bet_cents' => isset($gameTable['default_bet_cents']) ? (int) $gameTable['default_bet_cents'] : null,
                'max_multiplier' => isset($gameTable['max_multiplier']) ? (int) $gameTable['max_multiplier'] : null,
                'locales' => is_string($locales) ? $locales : null,
                'rtp' => isset($game['rtp']) && is_numeric($game['rtp']) ? (float) $game['rtp'] : null,
                'thumbnail_url' => self::thumbnailUrl($game),
                'raw_payload' => is_string($rawPayload) ? $rawPayload : null,
            ]);
        }

        $pdo->prepare('UPDATE bgaming_config SET synced_at = NOW() WHERE id = 1')->execute();
        return ['success' => true, 'count' => count($games)];
    }

    public static function providers(PDO $pdo, array $query = []): array
    {
        self::bootstrap($pdo);
        $where = ['is_active = 1'];
        $params = [];
        if (self::isTvQuery($query)) {
            $where[] = "provider = 'bgaming'";
        }
        $stmt = $pdo->prepare(
            'SELECT provider AS provider_code, provider AS provider_name, MAX(rtp) AS rtp, 1 AS is_active, 0 AS game_type
             FROM bgaming_games
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY provider
             ORDER BY provider ASC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function games(PDO $pdo, array $query): array
    {
        self::bootstrap($pdo);
        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = min(200, max(1, (int) ($query['limit'] ?? $query['per_page'] ?? 100)));
        $offset = array_key_exists('offset', $query) ? max(0, (int) $query['offset']) : (($page - 1) * $limit);
        $where = ['is_active = 1'];
        $params = [];

        $search = trim((string) ($query['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(title LIKE :search_title OR identifier LIKE :search_identifier OR provider LIKE :search_provider)';
            $params['search_title'] = '%' . $search . '%';
            $params['search_identifier'] = '%' . $search . '%';
            $params['search_provider'] = '%' . $search . '%';
        }

        $provider = trim((string) ($query['provider'] ?? $query['provider_code'] ?? ''));
        if ($provider !== '' && $provider !== 'hepsi') {
            $where[] = 'provider = :provider';
            $params['provider'] = $provider;
        }

        $sort = strtolower(trim((string) ($query['sort'] ?? $query['category'] ?? '')));
        $orderSql = match ($sort) {
            'new' => 'id DESC, title ASC',
            'popular', 'liked' => 'is_featured DESC, title ASC',
            default => 'is_featured DESC, title ASC',
        };

        $whereSql = implode(' AND ', $where);
        $count = $pdo->prepare('SELECT COUNT(*) FROM bgaming_games WHERE ' . $whereSql);
        foreach ($params as $key => $value) {
            $count->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $count->execute();
        $total = (int) $count->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT id,
                    CONCAT('" . self::GAME_ID_PREFIX . "', identifier) AS game_id,
                    identifier AS game_code,
                    title AS game_name,
                    title AS name,
                    provider,
                    provider AS provider_code,
                    'tv' AS type,
                    0 AS game_type,
                    thumbnail_url AS image_url,
                    thumbnail_url,
                    thumbnail_url AS banner,
                    is_featured,
                    rtp,
                    'bgaming' AS source
             FROM bgaming_games
             WHERE " . $whereSql . '
             ORDER BY ' . $orderSql . '
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items' => $items,
            'games' => $items,
            'pagination' => [
                'page' => $page,
                'perPage' => $limit,
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'totalPages' => max(1, (int) ceil($total / $limit)),
                'hasNext' => ($offset + $limit) < $total,
                'hasPrev' => $offset > 0,
            ],
        ];
    }

    public static function ownsGameId(string $gameId): bool
    {
        return str_starts_with($gameId, self::GAME_ID_PREFIX);
    }

    public static function normalizeGameIdentifier(string $gameId): string
    {
        return self::ownsGameId($gameId) ? substr($gameId, strlen(self::GAME_ID_PREFIX)) : $gameId;
    }

    public static function launch(PDO $pdo, ?array $user, array $input): array
    {
        $mode = strtolower(trim((string) ($input['mode'] ?? 'real')));
        $mode = in_array($mode, ['fun', 'demo'], true) ? 'fun' : 'real';
        if ($mode === 'real' && !is_array($user)) {
            return ['success' => false, 'code' => 401, 'message' => 'Oyun açmak için giriş yapın.'];
        }

        $game = self::normalizeGameIdentifier(trim((string) ($input['game_id'] ?? $input['gameId'] ?? $input['gameid'] ?? '')));
        if ($game === '') {
            return ['success' => false, 'code' => 422, 'message' => 'game_id zorunludur.'];
        }

        $config = self::activeConfig($pdo);
        $currency = self::normalizeCurrency((string) ($config['currency'] ?? self::DEFAULT_CURRENCY));
        $locale = self::normalizeLocale((string) ($input['lang'] ?? $input['locale'] ?? $config['locale'] ?? 'tr'));
        $sessionId = 'bg_' . (is_array($user) ? (int) ($user['id'] ?? 0) : 0) . '_' . bin2hex(random_bytes(12));
        $returnUrl = trim((string) ($input['return_url'] ?? $config['return_url'] ?? self::siteEndpoint()));

        $payload = [
            'game' => $game,
            'locale' => $locale,
            'ip' => self::clientIp(),
            'urls' => ['return_url' => $returnUrl],
        ];

        if ($mode === 'real') {
            $payload['casino_id'] = (string) ($config['casino_id'] ?: $config['server_id']);
            $payload['currency'] = $currency;
            $payload['user'] = [
                'id' => (string) ($user['id'] ?? ''),
                'nickname' => (string) ($user['username'] ?? ('user_' . ($user['id'] ?? ''))),
                'firstname' => (string) ($user['name'] ?? ''),
                'lastname' => (string) ($user['surname'] ?? ''),
                'country' => strtoupper((string) ($config['country'] ?? 'TR')),
            ];
            $payload['session_id'] = $sessionId;
        }

        $response = self::request($pdo, 'POST', $mode === 'fun' ? '/sessions/demo' : '/sessions', $payload);
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $gameUrl = trim((string) ($data['game_url'] ?? $response['game_url'] ?? ''));
        if ($gameUrl === '') {
            return ['success' => false, 'code' => 502, 'message' => 'BGaming oyun URL dönmedi.', 'data' => ['response' => $response]];
        }

        $remoteSessionId = trim((string) ($data['session_id'] ?? $sessionId));
        $stmt = $pdo->prepare(
            'INSERT INTO bgaming_game_sessions
                (session_id, user_id, username, game_identifier, mode, currency, locale, game_url, request_payload, response_payload)
             VALUES
                (:session_id, :user_id, :username, :game_identifier, :mode, :currency, :locale, :game_url, :request_payload, :response_payload)'
        );
        $stmt->execute([
            'session_id' => $remoteSessionId,
            'user_id' => is_array($user) ? (int) ($user['id'] ?? 0) : null,
            'username' => is_array($user) ? (string) ($user['username'] ?? '') : 'Demo',
            'game_identifier' => $game,
            'mode' => $mode,
            'currency' => $currency,
            'locale' => $locale,
            'game_url' => $gameUrl,
            'request_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'response_payload' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return [
            'success' => true,
            'code' => 200,
            'message' => 'Oyun başlatıldı.',
            'data' => [
                'game_url' => $gameUrl,
                'launch_url' => $gameUrl,
                'session_id' => $remoteSessionId,
                'mode' => $mode,
            ],
        ];
    }

    public static function wallet(PDO $pdo, string $endpoint, array $payload, string $rawBody, string $signature): array
    {
        $started = microtime(true);
        self::bootstrap($pdo);
        $status = 200;
        $response = [];
        try {
            if (!self::verifyWalletSignature($pdo, $rawBody, $signature)) {
                $status = 403;
                $response = ['code' => 'FORBIDDEN', 'message' => 'Sign does not match'];
            } else {
                $response = match ($endpoint) {
                    'balance' => self::walletBalance($pdo, $payload),
                    'play' => self::walletPlay($pdo, $payload),
                    'rollback' => self::walletRollback($pdo, $payload),
                    'freespins/finish' => self::walletFreespinsFinish($pdo, $payload),
                    'promo/bet' => self::walletPromo($pdo, $payload, 'promo_bet'),
                    'promo/win' => self::walletPromo($pdo, $payload, 'promo_win'),
                    'promo/rollback' => self::walletPromoRollback($pdo, $payload),
                    'auth/token_rotation' => self::walletTokenRotation($pdo, $payload),
                    default => ['code' => 'NOT_FOUND', 'message' => 'Wallet endpoint not found'],
                };
                if (isset($response['code'], $response['message'])) {
                    $status = $response['code'] === 'NOT_FOUND' ? 404 : 412;
                }
            }
        } catch (BgamingWalletException $exception) {
            $status = 412;
            $response = $exception->error();
        } catch (Throwable $exception) {
            $status = 412;
            error_log('BGaming wallet processing error: ' . $exception->getMessage());
            $response = ['code' => 'PROCESSING_ERROR', 'message' => 'Wallet request could not be processed'];
        }

        self::logWallet($pdo, $endpoint, $payload, $response, $status, (int) round((microtime(true) - $started) * 1000));
        return ['status' => $status, 'body' => $response];
    }

    private static function walletBalance(PDO $pdo, array $payload): array
    {
        $user = self::user($pdo, (int) ($payload['user_id'] ?? 0));
        if (!$user) {
            return ['code' => 'INVALID_USER', 'message' => 'User not found'];
        }
        return ['balance' => self::toSubunits((float) ($user['balance'] ?? 0), (string) ($payload['currency'] ?? self::DEFAULT_CURRENCY))];
    }

    private static function walletPlay(PDO $pdo, array $payload): array
    {
        $actions = self::actions($payload);
        if ($actions === []) {
            return self::walletBalance($pdo, $payload);
        }
        return self::applyActions($pdo, $payload, $actions, null);
    }

    private static function walletRollback(PDO $pdo, array $payload): array
    {
        return self::applyActions($pdo, $payload, self::actions($payload), 'rollback');
    }

    private static function walletFreespinsFinish(PDO $pdo, array $payload): array
    {
        if (!self::featureEnabled($pdo, 'freespins_enabled')) {
            return ['code' => 'FREESPINS_DISABLED', 'message' => 'Freespins are disabled'];
        }
        $userId = self::extractWalletUserId($payload);
        if ($userId > 0) {
            $payload['user_id'] = $userId;
        }

        $issueId = self::extractFreespinIssueId($payload);
        if ($issueId !== '') {
            $payload['issue_id'] = $issueId;
        }

        $resolvedUserId = self::resolveFreespinUserId($pdo, $payload, $issueId);
        if ($resolvedUserId > 0) {
            $payload['user_id'] = $resolvedUserId;
        }

        $user = self::user($pdo, (int) ($payload['user_id'] ?? 0));
        if (!$user) {
            return ['code' => 'INVALID_USER', 'message' => 'User not found'];
        }
        $issueId = trim((string) ($payload['issue_id'] ?? ''));
        if ($issueId === '') {
            return ['code' => 'INVALID_REQUEST', 'message' => 'issue_id is required'];
        }
        $config = self::config($pdo);
        $currency = (string) ($payload['currency'] ?? $config['currency'] ?? self::DEFAULT_CURRENCY);
        $payload['currency'] = $currency;

        $status = self::normalizeFreespinsStatus(self::extractFreespinsStatus($payload));
        if (!in_array($status, ['active', 'played', 'canceled', 'expired'], true)) {
            return ['code' => 'INVALID_REQUEST', 'message' => 'Unsupported freespins status'];
        }

        self::syncFreespinIssueFromWallet($pdo, $payload, $status);

        if ($status === 'played') {
            $payoutSubunits = self::extractFreespinsPayoutSubunits($payload, $currency);
            self::applyActions($pdo, $payload, [[
                'action_id' => $issueId . ':freespins_finish',
                'action' => 'win',
                'amount' => $payoutSubunits,
            ]], 'freespins_win');
        }

        $fresh = self::user($pdo, (int) ($payload['user_id'] ?? 0));
        return ['balance' => self::toSubunits((float) ($fresh['balance'] ?? 0), $currency)];
    }

    private static function normalizeFreespinsStatus(string $rawStatus): string
    {
        $status = strtolower(trim($rawStatus));
        return match ($status) {
            'played', 'complete', 'completed', 'finished', 'done', 'closed', 'success' => 'played',
            'active', 'in_progress', 'processing', 'started', 'running' => 'active',
            'cancel', 'canceled', 'cancelled' => 'canceled',
            'expire', 'expired' => 'expired',
            default => $status !== '' ? $status : 'played',
        };
    }

    private static function extractWalletUserId(array $payload): int
    {
        $candidate = $payload['user_id']
            ?? $payload['userId']
            ?? $payload['uid']
            ?? ($payload['freespins']['user_id'] ?? null)
            ?? ($payload['freespins']['userId'] ?? null)
            ?? ($payload['user']['id'] ?? null)
            ?? ($payload['player']['id'] ?? null)
            ?? null;

        return max(0, (int) $candidate);
    }

    private static function extractFreespinIssueId(array $payload): string
    {
        $candidate = $payload['issue_id']
            ?? $payload['issueId']
            ?? $payload['freespin_id']
            ?? $payload['freespins_id']
            ?? $payload['bonus_id']
            ?? ($payload['freespins']['issue_id'] ?? null)
            ?? ($payload['freespins']['issueId'] ?? null)
            ?? ($payload['issue']['issue_id'] ?? null)
            ?? ($payload['issue']['id'] ?? null)
            ?? null;

        return trim((string) ($candidate ?? ''));
    }

    private static function extractFreespinsStatus(array $payload): string
    {
        $candidate = $payload['status']
            ?? $payload['state']
            ?? $payload['freespins_status']
            ?? ($payload['issue']['status'] ?? null)
            ?? null;

        return trim((string) ($candidate ?? 'played'));
    }

    private static function extractFreespinsPayoutSubunits(array $payload, string $currency): int
    {
        $candidates = [
            $payload['total_amount'] ?? null,
            $payload['totalAmount'] ?? null,
            $payload['win_amount'] ?? null,
            $payload['winAmount'] ?? null,
            $payload['amount'] ?? null,
            $payload['payout'] ?? null,
        ];

        foreach ($candidates as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (is_int($value)) {
                return max(0, $value);
            }
            if (is_float($value)) {
                return max(0, self::toSubunits($value, $currency));
            }

            $raw = trim((string) $value);
            if ($raw === '') {
                continue;
            }
            if (preg_match('/[\.,]/', $raw) === 1) {
                $normalized = str_replace(',', '.', $raw);
                if (is_numeric($normalized)) {
                    return max(0, self::toSubunits((float) $normalized, $currency));
                }
                continue;
            }
            if (is_numeric($raw)) {
                return max(0, (int) $raw);
            }
        }

        return 0;
    }

    private static function syncFreespinIssueFromWallet(PDO $pdo, array $payload, string $status): void
    {
        $issueId = self::extractFreespinIssueId($payload);
        $userId = self::resolveFreespinUserId($pdo, $payload, $issueId);
        if ($issueId === '' || $userId <= 0) {
            return;
        }

        self::ensureCampaignStorage($pdo);

        $rawGame = trim((string) ($payload['game'] ?? $payload['game_identifier'] ?? 'acceptance:test'));
        $gameIdentifier = self::normalizeGameIdentifier($rawGame);
        $currencyCode = self::normalizeCurrency((string) ($payload['currency'] ?? self::DEFAULT_CURRENCY));
        $freespinsPerPlayer = max(1, (int) (
            $payload['freespins_count']
            ?? $payload['freespins_quantity']
            ?? $payload['spins_count']
            ?? $payload['quantity']
            ?? 1
        ));

        $beginsAt = self::parseUnixTimestamp((string) ($payload['issued_at'] ?? $payload['created_at'] ?? ''));
        $expiresAt = self::parseUnixTimestamp((string) ($payload['expires_at'] ?? $payload['expired_at'] ?? $payload['expire_at'] ?? ''));
        $active = $status === 'active' ? 1 : 0;
        $campaignStatus = match ($status) {
            'played' => 'played',
            'canceled' => 'canceled',
            'expired' => 'expired',
            default => 'active',
        };

        $payloadJson = json_encode([
            'wallet_issue' => true,
            'status' => $status,
            'source' => 'wallet_freespins_finish',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $campaignStmt = $pdo->prepare(
            'INSERT INTO bgaming_campaigns
                (campaign_code, title, campaign_type, game_identifier, vendor, currency_code, freespins_per_player,
                 begins_at, expires_at, active, status, payload)
             VALUES
                (:campaign_code, :title, :campaign_type, :game_identifier, :vendor, :currency_code, :freespins_per_player,
                 :begins_at, :expires_at, :active, :status, :payload)
             ON DUPLICATE KEY UPDATE
                game_identifier = VALUES(game_identifier),
                currency_code = VALUES(currency_code),
                freespins_per_player = VALUES(freespins_per_player),
                begins_at = COALESCE(VALUES(begins_at), begins_at),
                expires_at = COALESCE(VALUES(expires_at), expires_at),
                active = VALUES(active),
                status = VALUES(status),
                payload = VALUES(payload)'
        );
        $campaignStmt->execute([
            'campaign_code' => $issueId,
            'title' => 'BGaming Freespin ' . $issueId,
            'campaign_type' => 'freespin',
            'game_identifier' => $gameIdentifier,
            'vendor' => 'bgaming',
            'currency_code' => $currencyCode,
            'freespins_per_player' => $freespinsPerPlayer,
            'begins_at' => $beginsAt,
            'expires_at' => $expiresAt,
            'active' => $active,
            'status' => $campaignStatus,
            'payload' => is_string($payloadJson) ? $payloadJson : null,
        ]);

        $playerStatus = match ($status) {
            'played' => 'played',
            'canceled' => 'canceled',
            'expired' => 'expired',
            default => 'active',
        };
        $playerStmt = $pdo->prepare(
            'INSERT INTO bgaming_campaign_players (campaign_code, user_id, bonus_id, status, payload)
             VALUES (:campaign_code, :user_id, NULL, :status, :payload)
             ON DUPLICATE KEY UPDATE status = VALUES(status), payload = VALUES(payload), updated_at = CURRENT_TIMESTAMP'
        );
        $playerStmt->execute([
            'campaign_code' => $issueId,
            'user_id' => $userId,
            'status' => $playerStatus,
            'payload' => is_string($payloadJson) ? $payloadJson : null,
        ]);
    }

    private static function resolveFreespinUserId(PDO $pdo, array $payload, string $issueId = ''): int
    {
        $userId = self::extractWalletUserId($payload);
        if ($userId > 0) {
            return $userId;
        }

        $issueId = $issueId !== '' ? $issueId : self::extractFreespinIssueId($payload);
        if ($issueId === '') {
            return 0;
        }

        if (preg_match('/(?:^|_)(\d{1,10})(?:_|$)/', $issueId, $matches) === 1) {
            $guessed = max(0, (int) ($matches[1] ?? 0));
            if ($guessed > 0 && self::user($pdo, $guessed) !== null) {
                return $guessed;
            }
        }

        $stmt = $pdo->prepare('SELECT user_id FROM bgaming_campaign_players WHERE campaign_code = :campaign_code ORDER BY id DESC LIMIT 1');
        $stmt->execute(['campaign_code' => $issueId]);
        return max(0, (int) $stmt->fetchColumn());
    }

    public static function issueRemoteFreespins(PDO $pdo, array $input): array
    {
        self::bootstrap($pdo);
        self::ensureCampaignStorage($pdo);

        $userId = max(0, (int) ($input['user_id'] ?? 0));
        if ($userId <= 0) {
            throw new RuntimeException('Kullanıcı seçimi zorunludur.');
        }
        $user = self::user($pdo, $userId);
        if ($user === null) {
            throw new RuntimeException('Kullanıcı bulunamadı.');
        }

        $issueId = trim((string) ($input['issue_id'] ?? ''));
        if ($issueId === '') {
            $issueId = 'fs_' . $userId . '_' . bin2hex(random_bytes(6));
        }

        $gamesRaw = trim((string) ($input['games'] ?? $input['game_identifier'] ?? ''));
        $games = array_values(array_unique(array_filter(array_map(
            static fn (string $game): string => trim($game),
            preg_split('/[\s,;]+/', $gamesRaw) ?: []
        ), static fn (string $game): bool => $game !== '')));
        if ($games === []) {
            throw new RuntimeException('En az bir game identifier zorunludur.');
        }

        $config = self::activeConfig($pdo);
        $currency = self::normalizeCurrency((string) ($input['currency'] ?? $config['currency'] ?? self::DEFAULT_CURRENCY));
        $count = max(1, (int) ($input['freespins_quantity'] ?? $input['freespins_count'] ?? 1));
        $betLevel = max(0, (int) ($input['bet_level'] ?? 0));

        $validUntilRaw = trim((string) ($input['valid_until'] ?? ''));
        $validUntilTs = strtotime($validUntilRaw !== '' ? $validUntilRaw : '+7 days');
        if ($validUntilTs === false || $validUntilTs <= time()) {
            throw new RuntimeException('valid_until gelecekte bir tarih olmalıdır.');
        }

        $payload = [
            'casino_id' => trim((string) ($config['casino_id'] ?? $config['server_id'] ?? '')),
            'issue_id' => $issueId,
            'currency' => $currency,
            'games' => $games,
            'freespins_quantity' => $count,
            'valid_until' => gmdate('Y-m-d\TH:i:s\Z', $validUntilTs),
            'user' => [
                'id' => (string) $userId,
                'nickname' => (string) ($user['username'] ?? ('user_' . $userId)),
                'firstname' => (string) ($user['name'] ?? ''),
                'lastname' => (string) ($user['surname'] ?? ''),
                'country' => strtoupper((string) ($config['country'] ?? 'TR')),
            ],
        ];
        if ($betLevel > 0) {
            $payload['bet_level'] = $betLevel;
        }

        $validSinceRaw = trim((string) ($input['valid_since'] ?? ''));
        if ($validSinceRaw !== '') {
            $validSinceTs = strtotime($validSinceRaw);
            if ($validSinceTs !== false) {
                $payload['valid_since'] = gmdate('Y-m-d\TH:i:s\Z', $validSinceTs);
            }
        }

        $response = self::request($pdo, 'POST', '/promo/freespins', $payload);
        self::syncFreespinIssueFromWallet($pdo, [
            'issue_id' => $issueId,
            'user_id' => $userId,
            'currency' => $currency,
            'game' => $games[0],
            'freespins_quantity' => $count,
            'issued_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'expires_at' => $payload['valid_until'],
            'status' => 'active',
        ], 'active');

        return [
            'issue_id' => $issueId,
            'response' => $response,
        ];
    }

    public static function syncRemoteFreespinStatus(PDO $pdo, string $issueId): array
    {
        self::bootstrap($pdo);
        self::ensureCampaignStorage($pdo);

        $issueId = trim($issueId);
        if ($issueId === '') {
            throw new RuntimeException('issue_id zorunludur.');
        }

        $response = self::request($pdo, 'GET', '/promo/freespins/' . rawurlencode($issueId));
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        if ($data === []) {
            throw new RuntimeException('BGaming freespin status yanıtı boş döndü.');
        }

        $status = self::normalizeFreespinsStatus((string) ($data['status'] ?? 'active'));
        self::syncFreespinIssueFromWallet($pdo, [
            'issue_id' => (string) ($data['issue_id'] ?? $issueId),
            'user_id' => (int) ($data['user_id'] ?? 0),
            'status' => $status,
            'freespins_count' => (int) ($data['freespins_count'] ?? $data['freespins_quantity'] ?? 1),
            'spins_count' => (int) ($data['freespins_done'] ?? 0),
            'win_amount' => (int) ($data['win_amount'] ?? 0),
        ], $status);

        return $data;
    }

    public static function cancelRemoteFreespins(PDO $pdo, string $issueId): array
    {
        self::bootstrap($pdo);
        self::ensureCampaignStorage($pdo);

        $issueId = trim($issueId);
        if ($issueId === '') {
            throw new RuntimeException('issue_id zorunludur.');
        }

        $response = self::request($pdo, 'DELETE', '/promo/freespins/' . rawurlencode($issueId));
        $userIdStmt = $pdo->prepare('SELECT user_id FROM bgaming_campaign_players WHERE campaign_code = :code ORDER BY id DESC LIMIT 1');
        $userIdStmt->execute(['code' => $issueId]);
        $userId = max(0, (int) $userIdStmt->fetchColumn());

        self::syncFreespinIssueFromWallet($pdo, [
            'issue_id' => $issueId,
            'user_id' => $userId,
            'status' => 'canceled',
        ], 'canceled');

        return ['issue_id' => $issueId, 'response' => $response];
    }

    public static function listRemoteFreespins(PDO $pdo, array $query = []): array
    {
        self::bootstrap($pdo);
        $params = [];

        $userId = max(0, (int) ($query['user_id'] ?? 0));
        if ($userId > 0) {
            $params['user_id'] = (string) $userId;
        }

        $status = self::normalizeFreespinsStatus((string) ($query['status'] ?? ''));
        if (in_array($status, ['active', 'played', 'canceled', 'expired'], true)) {
            $params['status'] = $status;
        }

        $page = max(1, (int) ($query['page'] ?? 1));
        $params['page'] = $page;

        $response = self::request($pdo, 'GET', '/promo/freespins', $params);
        return [
            'data' => is_array($response['data'] ?? null) ? $response['data'] : [],
            'meta' => is_array($response['meta'] ?? null) ? $response['meta'] : [],
        ];
    }

    private static function walletPromo(PDO $pdo, array $payload, string $type): array
    {
        if (!self::featureEnabled($pdo, 'promo_enabled')) {
            return ['code' => 'PROMO_DISABLED', 'message' => 'Promo is disabled'];
        }
        $actionId = trim((string) ($payload['event_id'] ?? $payload['action_id'] ?? ''));
        if ($actionId === '') {
            return ['code' => 'INVALID_REQUEST', 'message' => 'event_id is required'];
        }
        $actionId .= $type === 'promo_bet' ? ':bet' : ':win';
        $action = [
            'action_id' => $actionId,
            'action' => $type === 'promo_bet' ? 'bet' : 'win',
            'amount' => (int) ($payload['amount'] ?? 0),
        ];
        $result = self::applyActions($pdo, $payload, [$action], $type);
        return ['balance' => (int) ($result['balance'] ?? 0)];
    }

    private static function walletPromoRollback(PDO $pdo, array $payload): array
    {
        if (!self::featureEnabled($pdo, 'promo_enabled')) {
            return ['code' => 'PROMO_DISABLED', 'message' => 'Promo is disabled'];
        }
        $eventId = trim((string) ($payload['event_id'] ?? ''));
        $originalEventId = trim((string) ($payload['original_event_id'] ?? ''));
        if ($eventId === '' || $originalEventId === '') {
            return ['code' => 'INVALID_REQUEST', 'message' => 'event_id and original_event_id are required'];
        }

        $result = self::applyActions($pdo, $payload, [[
            'action_id' => $eventId . ':rollback',
            'action' => 'rollback',
            'amount' => 0,
            'original_action_id' => $originalEventId . ':bet',
        ]], 'rollback');
        return ['balance' => (int) ($result['balance'] ?? 0)];
    }

    private static function walletTokenRotation(PDO $pdo, array $payload): array
    {
        if (!self::featureEnabled($pdo, 'token_rotation_enabled')) {
            return ['code' => 'TOKEN_ROTATION_DISABLED', 'message' => 'Token rotation is disabled'];
        }
        $nonceError = self::recordTokenRotationNonce($pdo, $payload);
        if ($nonceError !== null) {
            return $nonceError;
        }
        $newToken = trim((string) ($payload['new_token'] ?? ''));
        if ($newToken === '') {
            return ['code' => 'INVALID_REQUEST', 'message' => 'new_token is required'];
        }

        $rotationDatetime = trim((string) ($payload['rotation_datetime'] ?? ''));
        $rotationAt = $rotationDatetime !== '' ? strtotime($rotationDatetime) : false;
        if ($rotationAt !== false && $rotationAt <= time()) {
            self::replaceCurrentWalletSecret($pdo, $newToken);
        } else {
            self::storePendingWalletSecret($pdo, $newToken, $rotationAt === false ? null : gmdate('Y-m-d H:i:s', $rotationAt));
        }

        return ['status' => 'success'];
    }

    /**
     * @return array{code: string, message: string}|null
     */
    private static function recordTokenRotationNonce(PDO $pdo, array $payload): ?array
    {
        $nonce = trim((string) ($payload['nonce'] ?? $payload['request_id'] ?? ''));
        if ($nonce === '') {
            return ['code' => 'INVALID_REQUEST', 'message' => 'Token rotation nonce is required'];
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO bgaming_token_rotation_nonces (nonce_hash, nonce, request_payload)
                 VALUES (:nonce_hash, :nonce, :request_payload)'
            );
            $stmt->execute([
                'nonce_hash' => hash('sha256', $nonce),
                'nonce' => substr($nonce, 0, 190),
                'request_payload' => json_encode(self::redactSensitivePayload($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable) {
            return ['code' => 'REPLAYED_REQUEST', 'message' => 'Token rotation nonce was already used or could not be recorded'];
        }

        return null;
    }

    private static function applyActions(PDO $pdo, array $payload, array $actions, ?string $forcedType): array
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        if ($userId <= 0) {
            return ['code' => 'INVALID_USER', 'message' => 'User not found'];
        }

        $casinoRoundId = self::casinoRoundId($payload);
        $currency = (string) ($payload['currency'] ?? self::DEFAULT_CURRENCY);
        $transactions = [];
        $pdo->beginTransaction();
        try {
            $user = self::lockedUser($pdo, $userId);
            if (!$user) {
                $pdo->rollBack();
                return ['code' => 'INVALID_USER', 'message' => 'User not found'];
            }
            $balance = round((float) ($user['balance'] ?? 0), 2);

            foreach ($actions as $action) {
                $actionId = trim((string) ($action['action_id'] ?? ''));
                if ($actionId === '') {
                    throw new RuntimeException('action_id is required');
                }

                $existing = self::existingTransaction($pdo, $actionId);
                if ($existing !== null) {
                    $balance = round((float) $existing['after_balance'], 2);
                    $transactions[] = self::transactionResponse($existing);
                    continue;
                }

                $actionName = strtolower(trim((string) ($action['action'] ?? '')));
                $type = $forcedType ?: ($actionName === 'rollback' ? 'rollback' : $actionName);
                if (!in_array($type, ['bet', 'win', 'rollback', 'promo_bet', 'promo_win', 'freespins_win'], true)) {
                    throw new RuntimeException('Unsupported action: ' . $type);
                }

                $amountSubunits = (int) ($action['amount'] ?? 0);
                $amount = self::fromSubunits($amountSubunits, $currency);
                $before = $balance;
                $originalActionId = trim((string) ($action['original_action_id'] ?? ''));

                if ($type === 'rollback') {
                    [$amountSubunits, $amount, $balance] = self::applyRollbackBalance($pdo, $originalActionId, $balance);
                } elseif ($type === 'bet' || $type === 'promo_bet') {
                    if ($amount < 0 || $balance < $amount) {
                        throw new BgamingWalletException([
                            'code' => 'NOT_ENOUGH_FUNDS',
                            'message' => 'Not enough funds.',
                        ]);
                    }
                    $balance = round($balance - $amount, 2);
                } elseif ($amount > 0) {
                    $balance = round($balance + $amount, 2);
                }

                $pdo->prepare('UPDATE users SET balance = :balance WHERE id = :id')->execute([
                    'balance' => number_format($balance, 2, '.', ''),
                    'id' => $userId,
                ]);

                $casinoTxId = self::newCasinoTxId();
                $processedAt = gmdate('Y-m-d H:i:s');
                $stmt = $pdo->prepare(
                    'INSERT INTO bgaming_transactions
                        (user_id, action_id, original_action_id, casino_tx_id, session_id, round_id, casino_round_id,
                         game_identifier, txn_type, amount_subunits, amount, before_balance, after_balance, raw_payload, processed_at)
                     VALUES
                        (:user_id, :action_id, :original_action_id, :casino_tx_id, :session_id, :round_id, :casino_round_id,
                         :game_identifier, :txn_type, :amount_subunits, :amount, :before_balance, :after_balance, :raw_payload, :processed_at)'
                );
                $stmt->execute([
                    'user_id' => $userId,
                    'action_id' => $actionId,
                    'original_action_id' => $originalActionId !== '' ? $originalActionId : null,
                    'casino_tx_id' => $casinoTxId,
                    'session_id' => (string) ($payload['session_id'] ?? ''),
                    'round_id' => (string) ($payload['round_id'] ?? ''),
                    'casino_round_id' => $casinoRoundId,
                    'game_identifier' => (string) ($payload['game'] ?? $action['game_id'] ?? ''),
                    'txn_type' => $type,
                    'amount_subunits' => $amountSubunits,
                    'amount' => number_format($amount, 2, '.', ''),
                    'before_balance' => number_format($before, 2, '.', ''),
                    'after_balance' => number_format($balance, 2, '.', ''),
                    'raw_payload' => json_encode($action, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'processed_at' => $processedAt,
                ]);

                $transactions[] = [
                    'action_id' => $actionId,
                    'casino_tx_id' => $casinoTxId,
                    'processed_at' => self::isoDate($processedAt),
                ];
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        return [
            'balance' => self::toSubunits($balance, $currency),
            'casino_round_id' => $casinoRoundId,
            'transactions' => $transactions,
        ];
    }

    private static function applyRollbackBalance(PDO $pdo, string $originalActionId, float $balance): array
    {
        if ($originalActionId === '') {
            return [0, 0.0, $balance];
        }
        $original = self::existingTransaction($pdo, $originalActionId);
        if ($original === null) {
            return [0, 0.0, $balance];
        }
        $amount = round((float) $original['amount'], 2);
        $amountSubunits = (int) $original['amount_subunits'];
        $type = (string) $original['txn_type'];
        if (in_array($type, ['bet', 'promo_bet'], true)) {
            return [$amountSubunits, $amount, round($balance + $amount, 2)];
        }
        if ($amount > 0 && $balance < $amount) {
            throw new RuntimeException('Insufficient funds for rollback');
        }
        return [$amountSubunits, $amount, round($balance - $amount, 2)];
    }

    private static function request(PDO $pdo, string $method, string $path, array $payload = []): array
    {
        $config = self::activeConfig($pdo);
        $serverId = rawurlencode((string) $config['server_id']);
        $basePath = '/direct/' . $serverId . $path;
        $method = strtoupper($method);
        $bodies = [''];
        $query = '';
        if (in_array($method, ['GET', 'DELETE'], true) && $payload !== []) {
            $query = '?' . http_build_query($payload);
        } elseif ($payload !== []) {
            $bodies = self::apiBodies($payload);
        } elseif (!in_array($method, ['GET', 'DELETE'], true)) {
            $bodies = ['{}'];
        }

        $lastData = null;
        $lastRaw = '';
        $lastErr = '';
        $lastCode = 0;
        $secrets = self::signingSecrets($config);
        foreach ($bodies as $body) {
            $signSource = in_array($method, ['GET', 'DELETE'], true) ? $basePath . $query : $body;
            foreach ($secrets as $secret) {
                $headers = [
                    'Accept: application/json',
                    'X-REQUEST-SIGN: ' . self::sign($signSource, $secret),
                ];
                if (!in_array($method, ['GET', 'DELETE'], true)) {
                    $headers[] = 'Content-Type: application/json';
                }

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => rtrim((string) $config['api_base_url'], '/') . $basePath . $query,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST => $method,
                    CURLOPT_CONNECTTIMEOUT => 8,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTPHEADER => $headers,
                ]);
                if (!in_array($method, ['GET', 'DELETE'], true)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                }
                $raw = curl_exec($ch);
                $err = curl_error($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
                $lastData = $data;
                $lastRaw = is_string($raw) ? $raw : '';
                $lastErr = $err;
                $lastCode = $code;
                if ($code < 400 && is_array($data)) {
                    if ($secret !== (string) ($config['wallet_secret'] ?? '')) {
                        self::promotePendingWalletSecret($pdo, $secret);
                    }
                    return $data;
                }
                $error = is_array($data['error'] ?? null) ? $data['error'] : (is_array($data) ? $data : []);
                $message = (string) ($error['message'] ?? $error['code'] ?? '');
                if ($code !== 403 || stripos($message, 'sign') === false) {
                    break 2;
                }
            }
        }

        if (!is_array($lastData)) {
            $detail = trim((string) ($lastRaw ?: $lastErr));
            throw new RuntimeException('BGaming API yanıtı okunamadı. HTTP ' . $lastCode . ($detail !== '' ? ': ' . substr($detail, 0, 300) : ''));
        }
        $error = is_array($lastData['error'] ?? null) ? $lastData['error'] : $lastData;
        $message = (string) ($error['message'] ?? $error['code'] ?? 'BGaming API hatası');
        throw new RuntimeException('BGaming API HTTP ' . $lastCode . ': ' . $message);
    }

    private static function apiBodies(array $payload): array
    {
        $variants = [
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}',
        ];
        return array_values(array_unique($variants));
    }

    private static function activeConfig(PDO $pdo): array
    {
        $config = self::config($pdo);
        $normalizedGcp = self::normalizeGcpUrl((string) ($config['api_base_url'] ?? self::DEFAULT_API_BASE));
        $config['api_base_url'] = $normalizedGcp['api_base_url'];
        if ($normalizedGcp['server_id'] !== '') {
            $config['server_id'] = $normalizedGcp['server_id'];
        }
        if (trim((string) ($config['casino_id'] ?? '')) === '') {
            $config['casino_id'] = (string) ($config['server_id'] ?? '');
        }
        $config['currency'] = self::normalizeCurrency((string) ($config['currency'] ?? self::DEFAULT_CURRENCY));
        foreach (['server_id', 'api_base_url', 'wallet_secret'] as $key) {
            if (trim((string) ($config[$key] ?? '')) === '') {
                throw new RuntimeException('BGaming yapılandırması eksik: ' . $key);
            }
        }
        if ((int) ($config['is_active'] ?? 0) !== 1) {
            throw new RuntimeException('BGaming entegrasyonu pasif.');
        }
        return $config;
    }

    /**
     * BGaming bazen GCP_URL değerini https://host/direct/{server_id} şeklinde verir.
     * Request üretirken /direct/{server_id} tekrar eklenmemesi için host ve server_id ayrıştırılır.
     *
     * @return array{api_base_url: string, server_id: string}
     */
    private static function normalizeGcpUrl(string $url): array
    {
        $url = rtrim(trim($url), '/') ?: self::DEFAULT_API_BASE;
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return ['api_base_url' => self::DEFAULT_API_BASE, 'server_id' => ''];
        }

        $base = (string) $parts['scheme'] . '://' . (string) $parts['host'];
        if (!empty($parts['port'])) {
            $base .= ':' . (string) $parts['port'];
        }

        $serverId = '';
        $path = trim((string) ($parts['path'] ?? ''), '/');
        if (preg_match('#^direct/([^/]+)$#', $path, $matches) === 1) {
            $serverId = rawurldecode((string) $matches[1]);
        }

        return ['api_base_url' => $base, 'server_id' => $serverId];
    }

    private static function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        return in_array($currency, self::ALLOWED_CURRENCIES, true) ? $currency : self::DEFAULT_CURRENCY;
    }

    private static function normalizeLocale(string $locale): string
    {
        $locale = trim($locale);
        foreach (self::ALLOWED_LOCALES as $allowed) {
            if (strcasecmp($locale, $allowed) === 0) {
                return $allowed;
            }
        }
        return 'tr';
    }

    private static function verifyWalletSignature(PDO $pdo, string $rawBody, string $signature): bool
    {
        try {
            $config = self::activeConfig($pdo);
        } catch (Throwable) {
            return false;
        }
        if ($signature === '') {
            return false;
        }

        foreach (self::signingSecrets($config) as $secret) {
            if ($secret !== '' && hash_equals(self::sign($rawBody, $secret), $signature)) {
                if ($secret !== (string) ($config['wallet_secret'] ?? '')) {
                    self::promotePendingWalletSecret($pdo, $secret);
                }
                return true;
            }
        }

        return false;
    }

    private static function sign(string $message, string $secret): string
    {
        return hash_hmac('sha256', $message, $secret);
    }

    private static function existingTransaction(PDO $pdo, string $actionId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM bgaming_transactions WHERE action_id = :action_id LIMIT 1');
        $stmt->execute(['action_id' => $actionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private static function transactionResponse(array $row): array
    {
        return [
            'action_id' => (string) $row['action_id'],
            'casino_tx_id' => (string) $row['casino_tx_id'],
            'processed_at' => self::isoDate((string) $row['processed_at']),
        ];
    }

    private static function lockedUser(PDO $pdo, int $userId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private static function user(PDO $pdo, int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private static function actions(array $payload): array
    {
        return array_values(array_filter($payload['actions'] ?? [], 'is_array'));
    }

    private static function casinoRoundId(array $payload): string
    {
        $roundId = trim((string) ($payload['round_id'] ?? ''));
        return $roundId !== '' ? 'bg_' . hash('sha256', $roundId) : 'bg_' . bin2hex(random_bytes(12));
    }

    private static function newCasinoTxId(): string
    {
        return 'bgtx_' . bin2hex(random_bytes(12));
    }

    private static function featureEnabled(PDO $pdo, string $column): bool
    {
        $config = self::config($pdo);
        return (int) ($config[$column] ?? 1) === 1;
    }

    private static function toSubunits(float $amount, string $currency): int
    {
        return (int) round($amount * self::subunitFactor($currency));
    }

    private static function fromSubunits(int $amount, string $currency): float
    {
        $scale = self::subunitScale($currency);
        return round($amount / self::subunitFactor($currency), min($scale, 8));
    }

    private static function subunitFactor(string $currency): int
    {
        return 10 ** self::subunitScale($currency);
    }

    private static function subunitScale(string $currency): int
    {
        return match (strtoupper(trim($currency))) {
            'BTC', 'BCH', 'DOG', 'LTC', 'USDT' => 8,
            'ETH' => 9,
            'XRP' => 6,
            'JPY' => 0,
            default => 2,
        };
    }

    private static function isoDate(string $date): string
    {
        $ts = strtotime($date);
        return gmdate('Y-m-d\TH:i:s.u\Z', $ts !== false ? $ts : time());
    }

    private static function listFromDirectResponse(array $response): array
    {
        return isset($response['data']) && is_array($response['data'])
            ? array_values(array_filter($response['data'], 'is_array'))
            : [];
    }

    /**
     * Bet level details are useful in admin, but they must not block catalog sync.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function betLevelsByGame(PDO $pdo): array
    {
        try {
            $response = self::request($pdo, 'GET', '/bet_levels');
        } catch (Throwable) {
            return [];
        }

        $map = [];
        foreach (self::listFromDirectResponse($response) as $row) {
            $identifier = trim((string) ($row['identifier'] ?? ''));
            if ($identifier !== '') {
                $map[$identifier] = $row;
            }
        }
        return $map;
    }

    private static function gameTable(array $game): array
    {
        $gameTables = $game['game_tables'] ?? null;
        if (!is_array($gameTables)) {
            return [];
        }
        if (array_key_exists('bet_levels', $gameTables) || array_key_exists('default_bet_cents', $gameTables)) {
            return $gameTables;
        }
        foreach ($gameTables as $table) {
            if (is_array($table)) {
                return $table;
            }
        }
        return [];
    }

    private static function thumbnailUrl(array $game): string
    {
        $thumbs = is_array($game['thumbnails'] ?? null) ? $game['thumbnails'] : [];
        foreach (['337x181', '380x380', '236x110', '190x190'] as $size) {
            if (!is_array($thumbs[$size] ?? null)) {
                continue;
            }
            $url = (string) ($thumbs[$size]['webp'] ?? $thumbs[$size]['png'] ?? '');
            if ($url !== '') {
                return $url;
            }
        }
        $identifier = (string) ($game['identifier'] ?? '');
        return $identifier !== '' ? 'https://cdn.softswiss.net/i/s2/softswiss/' . rawurlencode($identifier) . '.webp' : '';
    }

    private static function isTvQuery(array $query): bool
    {
        $type = strtolower(trim((string) ($query['type'] ?? $query['game_type'] ?? '')));
        return in_array($type, ['tv', 'tv-games', 'tv_oyunlari'], true);
    }

    private static function clientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            $value = trim((string) ($_SERVER[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $ip = trim(explode(',', $value)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return '127.0.0.1';
    }

    private static function siteEndpoint(): string
    {
        return self::frontendEndpoint();
    }

    private static function frontendEndpoint(): string
    {
        if (defined('SITE_URL')) {
            return rtrim((string) SITE_URL, '/');
        }
        $scheme = function_exists('metropol_public_url_scheme')
            ? metropol_public_url_scheme('http')
            : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
        $fallback = (string) (getenv('SITE_URL') ?: getenv('FRONTEND_URL') ?: getenv('FRONTEND_FALLBACK_URL') ?: '');
        if ($fallback === '') {
            if (!function_exists('deploy_domain')) {
                $path = dirname(__DIR__) . '/config/deploy_domains.php';
                if (is_file($path)) {
                    require_once $path;
                }
            }
            $fallback = function_exists('deploy_domain') ? deploy_domain('frontend_url') : 'https://vegasroyalspin.com';
        }
        $fallbackHost = (string) (parse_url($fallback, PHP_URL_HOST) ?: 'vegasroyalspin.com');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? $fallbackHost);
        return $scheme . '://' . $host;
    }

    private static function backendEndpoint(): string
    {
        if (defined('BACKEND_URL') && trim((string) BACKEND_URL) !== '') {
            return rtrim((string) BACKEND_URL, '/');
        }

        return rtrim((string) (getenv('BACKEND_URL') ?: getenv('BACKEND_FALLBACK_URL') ?: 'https://bo-nexthub.site'), '/');
    }

    private static function logWallet(PDO $pdo, string $endpoint, array $payload, array $response, int $status, int $durationMs): void
    {
        try {
            $firstAction = is_array(($payload['actions'] ?? [])[0] ?? null) ? $payload['actions'][0] : [];
            $stmt = $pdo->prepare(
                'INSERT INTO bgaming_wallet_logs
                    (endpoint, http_status, user_id, action_id, request_payload, response_payload, error_code, duration_ms)
                 VALUES
                    (:endpoint, :http_status, :user_id, :action_id, :request_payload, :response_payload, :error_code, :duration_ms)'
            );
            $stmt->execute([
                'endpoint' => $endpoint,
                'http_status' => $status,
                'user_id' => isset($payload['user_id']) ? (int) $payload['user_id'] : null,
                'action_id' => (string) ($payload['action_id'] ?? $payload['event_id'] ?? $firstAction['action_id'] ?? ''),
                'request_payload' => json_encode(self::redactSensitivePayload($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'response_payload' => json_encode(self::redactSensitivePayload($response), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'error_code' => (string) ($response['code'] ?? ''),
                'duration_ms' => $durationMs,
            ]);
        } catch (Throwable) {
        }
    }

    private static function redactSensitivePayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            $lowerKey = strtolower((string) $key);
            if (in_array($lowerKey, ['new_token', 'token', 'wallet_secret', 'secret', 'api_key', 'authorization'], true)) {
                $payload[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $payload[$key] = self::redactSensitivePayload($value);
            }
        }

        return $payload;
    }

    private static function normalizeCampaignType(string $campaignType): string
    {
        $campaignType = strtolower(trim($campaignType));
        return in_array($campaignType, ['freespin', 'promo'], true) ? $campaignType : 'freespin';
    }

    private static function generateCampaignCode(string $campaignType): string
    {
        return 'bg_' . $campaignType . '_' . bin2hex(random_bytes(6));
    }

    private static function parseUnixTimestamp(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? null : $timestamp;
    }

    /**
     * @return list<string>
     */
    private static function signingSecrets(array $config): array
    {
        $secrets = [];
        $current = trim((string) ($config['wallet_secret'] ?? ''));
        $pending = trim((string) ($config['pending_wallet_secret'] ?? ''));
        $env = trim((string) getenv('BGAMING_WALLET_SECRET'));

        if ($current !== '') {
            $secrets[] = $current;
        }
        if ($pending !== '' && !in_array($pending, $secrets, true)) {
            $secrets[] = $pending;
        }
        if ($env !== '' && !in_array($env, $secrets, true)) {
            $secrets[] = $env;
        }

        return $secrets;
    }

    private static function storePendingWalletSecret(PDO $pdo, string $token, ?string $activatesAt): void
    {
        if (!self::columnExists($pdo, 'bgaming_config', 'pending_wallet_secret')) {
            return;
        }
        $pdo->prepare(
            'UPDATE bgaming_config
             SET pending_wallet_secret = :token,
                 pending_wallet_secret_activates_at = :activates_at
             WHERE id = 1'
        )->execute([
            'token' => $token,
            'activates_at' => $activatesAt,
        ]);
    }

    private static function replaceCurrentWalletSecret(PDO $pdo, string $token): void
    {
        if (self::columnExists($pdo, 'bgaming_config', 'pending_wallet_secret')) {
            $pdo->prepare(
                'UPDATE bgaming_config
                 SET wallet_secret = :token,
                     pending_wallet_secret = \'\',
                     pending_wallet_secret_activates_at = NULL
                 WHERE id = 1'
            )->execute(['token' => $token]);
            return;
        }

        $pdo->prepare('UPDATE bgaming_config SET wallet_secret = :token WHERE id = 1')->execute(['token' => $token]);
    }

    private static function promotePendingWalletSecret(PDO $pdo, string $token): void
    {
        if ($token === '') {
            return;
        }

        if (!self::columnExists($pdo, 'bgaming_config', 'pending_wallet_secret')) {
            return;
        }

        $pdo->prepare(
            'UPDATE bgaming_config
             SET wallet_secret = :token,
                 pending_wallet_secret = \'\',
                 pending_wallet_secret_activates_at = NULL
             WHERE id = 1 AND pending_wallet_secret = :token'
        )->execute(['token' => $token]);
    }

    private static function ensureCampaignStorage(PDO $pdo): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS bgaming_campaigns (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                campaign_code VARCHAR(190) NOT NULL,
                title VARCHAR(190) NOT NULL,
                campaign_type VARCHAR(40) NOT NULL DEFAULT 'freespin',
                game_identifier VARCHAR(120) NULL,
                vendor VARCHAR(100) NOT NULL DEFAULT 'bgaming',
                currency_code VARCHAR(8) NULL,
                freespins_per_player INT NOT NULL DEFAULT 0,
                promo_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                wagering_multiplier DECIMAL(8,2) NOT NULL DEFAULT 0.00,
                begins_at BIGINT NULL,
                expires_at BIGINT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                status VARCHAR(40) NOT NULL DEFAULT 'active',
                payload JSON NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_bgaming_campaign_code (campaign_code),
                KEY idx_bgaming_campaign_type (campaign_type),
                KEY idx_bgaming_campaign_active (active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS bgaming_campaign_players (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                campaign_code VARCHAR(190) NOT NULL,
                user_id INT NOT NULL,
                bonus_id INT NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'assigned',
                payload JSON NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_bgaming_campaign_player (campaign_code, user_id),
                KEY idx_bgaming_campaign_player_user (user_id),
                KEY idx_bgaming_campaign_player_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $ready = true;
    }
}
