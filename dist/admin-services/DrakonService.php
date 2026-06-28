<?php

declare(strict_types=1);

final class DrakonService
{
    private const DEFAULT_API_BASE = 'https://gator.drakon.casino/api/v1';

    public static function bootstrap(PDO $pdo, bool $dropLegacy = false): void
    {
        if ((string) getenv('METROPOL_RUNTIME_PROVIDER_BOOTSTRAP') !== '1' || !self::runtimeSchemaChangesAllowed()) {
            return;
        }

        self::createSchema($pdo);
        if ($dropLegacy) {
            self::dropLegacyTables($pdo);
        }
        self::ensureDefaultConfig($pdo);
    }

    public static function createSchema(PDO $pdo): void
    {
        if (!self::runtimeSchemaChangesAllowed()) {
            throw new RuntimeException('Runtime provider schema changes are disabled in production.');
        }

        $defaultApiBase = str_replace("'", "''", trim((string) (getenv('DRAKON_API_BASE_URL') ?: self::DEFAULT_API_BASE)));
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS drakon_config (
                id TINYINT UNSIGNED NOT NULL DEFAULT 1,
                agent_code VARCHAR(100) NOT NULL DEFAULT '',
                agent_token VARCHAR(255) NOT NULL DEFAULT '',
                agent_secret VARCHAR(255) NOT NULL DEFAULT '',
                currency CHAR(3) NOT NULL DEFAULT 'TRY',
                api_base_url VARCHAR(255) NOT NULL DEFAULT '{$defaultApiBase}',
                site_endpoint VARCHAR(255) NOT NULL DEFAULT '',
                callback_secret VARCHAR(255) NOT NULL DEFAULT '',
                callback_allowed_ips TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 0,
                last_auth_at DATETIME NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS drakon_access_tokens (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                token_hash CHAR(64) NOT NULL,
                access_token TEXT NOT NULL,
                expires_at DATETIME NULL,
                last_used_at DATETIME NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_drakon_access_tokens_expires_at (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS drakon_providers (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                provider_code VARCHAR(100) NOT NULL,
                provider_name VARCHAR(190) NOT NULL,
                rtp DECIMAL(6,2) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                synced_at DATETIME NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_drakon_provider_code (provider_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS drakon_games (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                game_id VARCHAR(100) NOT NULL,
                game_code VARCHAR(100) NULL,
                game_name VARCHAR(255) NOT NULL,
                provider_code VARCHAR(100) NULL,
                provider_name VARCHAR(190) NOT NULL,
                rtp DECIMAL(6,2) NULL,
                image_url VARCHAR(500) NULL,
                banner VARCHAR(500) NULL,
                type VARCHAR(50) NOT NULL DEFAULT 'casino',
                game_type TINYINT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                is_featured TINYINT(1) NOT NULL DEFAULT 0,
                raw_payload JSON NULL,
                synced_at DATETIME NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_drakon_game_id (game_id),
                KEY idx_drakon_games_provider (provider_code),
                KEY idx_drakon_games_name (game_name),
                KEY idx_drakon_games_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

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

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS drakon_game_sessions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                session_key CHAR(32) NOT NULL,
                user_id INT NULL,
                username VARCHAR(100) NULL,
                game_id VARCHAR(100) NOT NULL,
                mode ENUM('real','fun') NOT NULL DEFAULT 'real',
                currency CHAR(3) NOT NULL DEFAULT 'TRY',
                lang VARCHAR(10) NOT NULL DEFAULT 'tr',
                game_url TEXT NULL,
                request_payload JSON NULL,
                response_payload JSON NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_drakon_session_key (session_key),
                KEY idx_drakon_sessions_user (user_id),
                KEY idx_drakon_sessions_game (game_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS drakon_transactions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT NOT NULL,
                username VARCHAR(100) NULL,
                user_full_name VARCHAR(255) NULL,
                transaction_id VARCHAR(190) NOT NULL,
                related_transaction_id VARCHAR(190) NULL,
                session_id VARCHAR(190) NULL,
                round_id VARCHAR(190) NULL,
                game_id VARCHAR(100) NULL,
                game_name VARCHAR(255) NULL,
                provider_name VARCHAR(190) NULL,
                image_url VARCHAR(500) NULL,
                txn_type ENUM('bet','win','refund') NOT NULL,
                amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                bet_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                win_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                before_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                after_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                status VARCHAR(40) NOT NULL DEFAULT 'confirmed',
                raw_payload JSON NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_drakon_transaction_id (transaction_id),
                KEY idx_drakon_tx_user (user_id),
                KEY idx_drakon_tx_round (round_id),
                KEY idx_drakon_tx_session (session_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS drakon_webhook_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                method VARCHAR(80) NULL,
                user_id INT NULL,
                transaction_id VARCHAR(190) NULL,
                request_payload JSON NULL,
                response_payload JSON NULL,
                http_status SMALLINT NOT NULL DEFAULT 200,
                error_code VARCHAR(100) NULL,
                duration_ms INT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_drakon_webhook_method (method),
                KEY idx_drakon_webhook_tx (transaction_id),
                KEY idx_drakon_webhook_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS drakon_campaigns (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                campaign_code VARCHAR(190) NOT NULL,
                vendor VARCHAR(100) NOT NULL,
                currency_code CHAR(3) NULL,
                freespins_per_player INT NOT NULL DEFAULT 0,
                begins_at BIGINT NULL,
                expires_at BIGINT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                status VARCHAR(40) NOT NULL DEFAULT 'active',
                payload JSON NULL,
                remote_response JSON NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_drakon_campaign_code (campaign_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS drakon_campaign_players (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                campaign_code VARCHAR(190) NOT NULL,
                user_id INT NOT NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'assigned',
                remote_response JSON NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_drakon_campaign_player (campaign_code, user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS drakon_campaign_requests (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                action VARCHAR(80) NOT NULL,
                campaign_code VARCHAR(190) NULL,
                idempotency_key VARCHAR(190) NULL,
                request_payload JSON NULL,
                response_payload JSON NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'pending',
                request_id VARCHAR(190) NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_drakon_campaign_req_code (campaign_code),
                KEY idx_drakon_campaign_req_idem (idempotency_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::ensureColumns($pdo);
    }

    private static function ensureColumns(PDO $pdo): void
    {
        $defaultApiBase = str_replace("'", "''", trim((string) (getenv('DRAKON_API_BASE_URL') ?: self::DEFAULT_API_BASE)));
        $columns = [
            'drakon_config' => [
                'agent_code' => "ALTER TABLE drakon_config ADD COLUMN agent_code VARCHAR(100) NOT NULL DEFAULT ''",
                'agent_token' => "ALTER TABLE drakon_config ADD COLUMN agent_token VARCHAR(255) NOT NULL DEFAULT ''",
                'agent_secret' => "ALTER TABLE drakon_config ADD COLUMN agent_secret VARCHAR(255) NOT NULL DEFAULT ''",
                'currency' => "ALTER TABLE drakon_config ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'TRY'",
                'api_base_url' => "ALTER TABLE drakon_config ADD COLUMN api_base_url VARCHAR(255) NOT NULL DEFAULT '{$defaultApiBase}'",
                'site_endpoint' => "ALTER TABLE drakon_config ADD COLUMN site_endpoint VARCHAR(255) NOT NULL DEFAULT ''",
                'callback_secret' => "ALTER TABLE drakon_config ADD COLUMN callback_secret VARCHAR(255) NOT NULL DEFAULT ''",
                'callback_allowed_ips' => "ALTER TABLE drakon_config ADD COLUMN callback_allowed_ips TEXT NULL",
                'is_active' => "ALTER TABLE drakon_config ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 0",
                'last_auth_at' => "ALTER TABLE drakon_config ADD COLUMN last_auth_at DATETIME NULL",
            ],
            'drakon_providers' => [
                'code' => "ALTER TABLE drakon_providers ADD COLUMN code VARCHAR(100) NOT NULL DEFAULT ''",
                'name' => "ALTER TABLE drakon_providers ADD COLUMN name VARCHAR(190) NOT NULL DEFAULT ''",
                'raw_payload' => "ALTER TABLE drakon_providers ADD COLUMN raw_payload JSON NULL",
                'provider_code' => "ALTER TABLE drakon_providers ADD COLUMN provider_code VARCHAR(100) NOT NULL DEFAULT ''",
                'provider_name' => "ALTER TABLE drakon_providers ADD COLUMN provider_name VARCHAR(190) NOT NULL DEFAULT ''",
                'rtp' => "ALTER TABLE drakon_providers ADD COLUMN rtp DECIMAL(6,2) NULL",
                'is_active' => "ALTER TABLE drakon_providers ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1",
                'synced_at' => "ALTER TABLE drakon_providers ADD COLUMN synced_at DATETIME NULL",
                'created_at' => "ALTER TABLE drakon_providers ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP",
                'updated_at' => "ALTER TABLE drakon_providers ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
            ],
            'drakon_games' => [
                'game_id' => "ALTER TABLE drakon_games ADD COLUMN game_id VARCHAR(100) NOT NULL DEFAULT ''",
                'game_code' => "ALTER TABLE drakon_games ADD COLUMN game_code VARCHAR(100) NULL",
                'game_name' => "ALTER TABLE drakon_games ADD COLUMN game_name VARCHAR(255) NOT NULL DEFAULT ''",
                'provider_code' => "ALTER TABLE drakon_games ADD COLUMN provider_code VARCHAR(100) NULL",
                'provider_name' => "ALTER TABLE drakon_games ADD COLUMN provider_name VARCHAR(190) NOT NULL DEFAULT ''",
                'rtp' => "ALTER TABLE drakon_games ADD COLUMN rtp DECIMAL(6,2) NULL",
                'image_url' => "ALTER TABLE drakon_games ADD COLUMN image_url VARCHAR(500) NULL",
                'banner' => "ALTER TABLE drakon_games ADD COLUMN banner VARCHAR(500) NULL",
                'type' => "ALTER TABLE drakon_games ADD COLUMN type VARCHAR(50) NOT NULL DEFAULT 'casino'",
                'game_type' => "ALTER TABLE drakon_games ADD COLUMN game_type TINYINT NOT NULL DEFAULT 0",
                'is_active' => "ALTER TABLE drakon_games ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1",
                'is_featured' => "ALTER TABLE drakon_games ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0",
                'raw_payload' => "ALTER TABLE drakon_games ADD COLUMN raw_payload JSON NULL",
                'synced_at' => "ALTER TABLE drakon_games ADD COLUMN synced_at DATETIME NULL",
                'created_at' => "ALTER TABLE drakon_games ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP",
                'updated_at' => "ALTER TABLE drakon_games ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
            ],
            'drakon_transactions' => [
                'user_id' => "ALTER TABLE drakon_transactions ADD COLUMN user_id INT NOT NULL DEFAULT 0",
                'username' => "ALTER TABLE drakon_transactions ADD COLUMN username VARCHAR(100) NULL",
                'user_full_name' => "ALTER TABLE drakon_transactions ADD COLUMN user_full_name VARCHAR(255) NULL",
                'transaction_id' => "ALTER TABLE drakon_transactions ADD COLUMN transaction_id VARCHAR(190) NOT NULL DEFAULT ''",
                'related_transaction_id' => "ALTER TABLE drakon_transactions ADD COLUMN related_transaction_id VARCHAR(190) NULL",
                'session_id' => "ALTER TABLE drakon_transactions ADD COLUMN session_id VARCHAR(190) NULL",
                'round_id' => "ALTER TABLE drakon_transactions ADD COLUMN round_id VARCHAR(190) NULL",
                'game_id' => "ALTER TABLE drakon_transactions ADD COLUMN game_id VARCHAR(100) NULL",
                'game_name' => "ALTER TABLE drakon_transactions ADD COLUMN game_name VARCHAR(255) NULL",
                'provider_name' => "ALTER TABLE drakon_transactions ADD COLUMN provider_name VARCHAR(190) NULL",
                'image_url' => "ALTER TABLE drakon_transactions ADD COLUMN image_url VARCHAR(500) NULL",
                'txn_type' => "ALTER TABLE drakon_transactions ADD COLUMN txn_type ENUM('bet','win','refund') NOT NULL DEFAULT 'bet'",
                'amount' => "ALTER TABLE drakon_transactions ADD COLUMN amount DECIMAL(14,2) NOT NULL DEFAULT 0.00",
                'bet_amount' => "ALTER TABLE drakon_transactions ADD COLUMN bet_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00",
                'win_amount' => "ALTER TABLE drakon_transactions ADD COLUMN win_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00",
                'before_balance' => "ALTER TABLE drakon_transactions ADD COLUMN before_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00",
                'after_balance' => "ALTER TABLE drakon_transactions ADD COLUMN after_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00",
                'status' => "ALTER TABLE drakon_transactions ADD COLUMN status VARCHAR(40) NOT NULL DEFAULT 'confirmed'",
                'raw_payload' => "ALTER TABLE drakon_transactions ADD COLUMN raw_payload JSON NULL",
                'created_at' => "ALTER TABLE drakon_transactions ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP",
            ],
            'drakon_game_sessions' => [
                'session_key' => "ALTER TABLE drakon_game_sessions ADD COLUMN session_key CHAR(32) NOT NULL DEFAULT ''",
                'user_id' => "ALTER TABLE drakon_game_sessions ADD COLUMN user_id INT NULL",
                'username' => "ALTER TABLE drakon_game_sessions ADD COLUMN username VARCHAR(100) NULL",
                'game_id' => "ALTER TABLE drakon_game_sessions ADD COLUMN game_id VARCHAR(100) NOT NULL DEFAULT ''",
                'mode' => "ALTER TABLE drakon_game_sessions ADD COLUMN mode ENUM('real','fun') NOT NULL DEFAULT 'real'",
                'currency' => "ALTER TABLE drakon_game_sessions ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'TRY'",
                'lang' => "ALTER TABLE drakon_game_sessions ADD COLUMN lang VARCHAR(10) NOT NULL DEFAULT 'tr'",
                'game_url' => "ALTER TABLE drakon_game_sessions ADD COLUMN game_url TEXT NULL",
                'request_payload' => "ALTER TABLE drakon_game_sessions ADD COLUMN request_payload JSON NULL",
                'response_payload' => "ALTER TABLE drakon_game_sessions ADD COLUMN response_payload JSON NULL",
                'created_at' => "ALTER TABLE drakon_game_sessions ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP",
            ],
            'drakon_campaigns' => [
                'campaign_code' => "ALTER TABLE drakon_campaigns ADD COLUMN campaign_code VARCHAR(190) NOT NULL DEFAULT ''",
                'vendor' => "ALTER TABLE drakon_campaigns ADD COLUMN vendor VARCHAR(100) NOT NULL DEFAULT ''",
                'currency_code' => "ALTER TABLE drakon_campaigns ADD COLUMN currency_code CHAR(3) NULL",
                'freespins_per_player' => "ALTER TABLE drakon_campaigns ADD COLUMN freespins_per_player INT NOT NULL DEFAULT 0",
                'begins_at' => "ALTER TABLE drakon_campaigns ADD COLUMN begins_at BIGINT NULL",
                'expires_at' => "ALTER TABLE drakon_campaigns ADD COLUMN expires_at BIGINT NULL",
                'active' => "ALTER TABLE drakon_campaigns ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1",
                'status' => "ALTER TABLE drakon_campaigns ADD COLUMN status VARCHAR(40) NOT NULL DEFAULT 'active'",
                'payload' => "ALTER TABLE drakon_campaigns ADD COLUMN payload JSON NULL",
                'remote_response' => "ALTER TABLE drakon_campaigns ADD COLUMN remote_response JSON NULL",
                'created_at' => "ALTER TABLE drakon_campaigns ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP",
                'updated_at' => "ALTER TABLE drakon_campaigns ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
            ],
            'drakon_campaign_players' => [
                'campaign_code' => "ALTER TABLE drakon_campaign_players ADD COLUMN campaign_code VARCHAR(190) NOT NULL DEFAULT ''",
                'user_id' => "ALTER TABLE drakon_campaign_players ADD COLUMN user_id INT NOT NULL DEFAULT 0",
                'status' => "ALTER TABLE drakon_campaign_players ADD COLUMN status VARCHAR(40) NOT NULL DEFAULT 'assigned'",
                'remote_response' => "ALTER TABLE drakon_campaign_players ADD COLUMN remote_response JSON NULL",
                'created_at' => "ALTER TABLE drakon_campaign_players ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP",
                'updated_at' => "ALTER TABLE drakon_campaign_players ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
            ],
            'drakon_campaign_requests' => [
                'action' => "ALTER TABLE drakon_campaign_requests ADD COLUMN action VARCHAR(80) NOT NULL DEFAULT ''",
                'campaign_code' => "ALTER TABLE drakon_campaign_requests ADD COLUMN campaign_code VARCHAR(190) NULL",
                'idempotency_key' => "ALTER TABLE drakon_campaign_requests ADD COLUMN idempotency_key VARCHAR(190) NULL",
                'request_payload' => "ALTER TABLE drakon_campaign_requests ADD COLUMN request_payload JSON NULL",
                'response_payload' => "ALTER TABLE drakon_campaign_requests ADD COLUMN response_payload JSON NULL",
                'status' => "ALTER TABLE drakon_campaign_requests ADD COLUMN status VARCHAR(40) NOT NULL DEFAULT 'pending'",
                'request_id' => "ALTER TABLE drakon_campaign_requests ADD COLUMN request_id VARCHAR(190) NULL",
                'created_at' => "ALTER TABLE drakon_campaign_requests ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP",
            ],
        ];
        foreach ($columns as $table => $tableColumns) {
            foreach ($tableColumns as $column => $sql) {
                if (!self::columnExists($pdo, $table, $column)) {
                    try {
                        $pdo->exec($sql);
                    } catch (Throwable) {
                    }
                }
            }
        }
        if (self::columnExists($pdo, 'drakon_transactions', 'method')) {
            try {
                $pdo->exec('ALTER TABLE drakon_transactions MODIFY COLUMN method VARCHAR(80) NULL DEFAULT NULL');
            } catch (Throwable) {
            }
        }
        if (self::columnExists($pdo, 'drakon_games', 'image_url') && self::columnExists($pdo, 'drakon_games', 'banner')) {
            try {
                $pdo->exec("UPDATE drakon_games SET image_url = banner WHERE (image_url IS NULL OR image_url = '') AND banner IS NOT NULL AND banner <> ''");
            } catch (Throwable) {
            }
        }
        if (self::columnExists($pdo, 'drakon_games', 'type') && self::columnExists($pdo, 'drakon_games', 'game_type')) {
            try {
                $pdo->exec("UPDATE drakon_games
                    SET type = 'live', game_type = 1
                    WHERE " . self::liveProviderSqlCondition('provider_name'));
                $pdo->exec("UPDATE drakon_games
                    SET type = 'casino', game_type = 0
                    WHERE (type IS NULL OR type = '' OR type NOT IN ('live', 'casino'))
                      AND NOT (" . self::liveProviderSqlCondition('provider_name') . ")");
            } catch (Throwable) {
            }
        }
        if (self::columnExists($pdo, 'drakon_games', 'provider_name') && self::columnExists($pdo, 'drakon_games', 'is_active')) {
            try {
                $pdo->exec("UPDATE drakon_games
                    SET is_active = 0
                    WHERE " . self::excludedDirectProviderSqlCondition('provider_name'));
            } catch (Throwable) {
            }
        }
        if (self::columnExists($pdo, 'drakon_providers', 'provider_name') && self::columnExists($pdo, 'drakon_providers', 'is_active')) {
            try {
                $pdo->exec("UPDATE drakon_providers
                    SET is_active = 0
                    WHERE " . self::excludedDirectProviderSqlCondition('provider_name'));
            } catch (Throwable) {
            }
        }
        self::backfillDrakonProviderColumns($pdo);
        if (
            self::columnExists($pdo, 'drakon_transactions', 'game_name')
            && self::columnExists($pdo, 'drakon_transactions', 'provider_name')
            && self::columnExists($pdo, 'drakon_transactions', 'image_url')
        ) {
            try {
                $pdo->exec("UPDATE drakon_transactions t
                    LEFT JOIN drakon_games g ON g.game_id = t.game_id
                    SET
                        t.game_name = COALESCE(NULLIF(t.game_name, ''), g.game_name, t.game_id),
                        t.provider_name = COALESCE(NULLIF(t.provider_name, ''), g.provider_name, ''),
                        t.image_url = COALESCE(NULLIF(t.image_url, ''), NULLIF(g.image_url, ''), NULLIF(g.banner, ''))
                    WHERE t.game_id IS NOT NULL
                      AND t.game_id <> ''
                      AND (
                        t.game_name IS NULL OR t.game_name = ''
                        OR t.provider_name IS NULL OR t.provider_name = ''
                        OR t.image_url IS NULL OR t.image_url = ''
                      )");
            } catch (Throwable) {
            }
        }
        if (
            self::columnExists($pdo, 'drakon_transactions', 'username')
            && self::columnExists($pdo, 'drakon_transactions', 'user_full_name')
        ) {
            try {
                $pdo->exec("UPDATE drakon_transactions t
                    LEFT JOIN users u ON u.id = t.user_id
                    SET
                        t.username = COALESCE(NULLIF(t.username, ''), u.username),
                        t.user_full_name = COALESCE(
                            NULLIF(t.user_full_name, ''),
                            NULLIF(TRIM(CONCAT(COALESCE(u.name, ''), ' ', COALESCE(u.surname, ''))), ''),
                            u.username
                        )
                    WHERE t.user_id > 0
                      AND (t.username IS NULL OR t.username = '' OR t.user_full_name IS NULL OR t.user_full_name = '')");
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

    public static function dropLegacyTables(PDO $pdo): void
    {
        foreach (['provider_config', 'games_transactions', 'games', 'users_favorite_games'] as $table) {
            try {
                $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
            } catch (Throwable) {
            }
        }
    }

    public static function ensureDefaultConfig(PDO $pdo): void
    {
        $stmt = $pdo->query('SELECT COUNT(*) FROM drakon_config WHERE id = 1');
        if ((int) $stmt->fetchColumn() > 0) {
            self::normalizeStoredConfig($pdo);
            return;
        }
        $insert = $pdo->prepare(
            'INSERT INTO drakon_config (id, api_base_url, currency, site_endpoint, is_active)
             VALUES (1, :api_base_url, :currency, :site_endpoint, 0)'
        );
        $insert->execute([
            'api_base_url' => trim((string) (getenv('DRAKON_API_BASE_URL') ?: self::DEFAULT_API_BASE)),
            'currency' => 'TRY',
            'site_endpoint' => self::siteEndpoint(),
        ]);
    }

    private static function normalizeStoredConfig(PDO $pdo): void
    {
        try {
            $stmt = $pdo->query('SELECT api_base_url, site_endpoint FROM drakon_config WHERE id = 1 LIMIT 1');
            $row = is_object($stmt) ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        } catch (Throwable) {
            return;
        }
        if (!is_array($row)) {
            return;
        }

        $currentApiBase = (string) ($row['api_base_url'] ?? '');
        $normalizedApiBase = self::normalizeApiBaseUrl($currentApiBase);
        if ($normalizedApiBase !== '' && $normalizedApiBase !== $currentApiBase) {
            $update = $pdo->prepare('UPDATE drakon_config SET api_base_url = :api_base_url WHERE id = 1');
            $update->execute(['api_base_url' => $normalizedApiBase]);
            $pdo->exec('DELETE FROM drakon_access_tokens');
        }

        self::persistNormalizedSiteEndpoint($pdo, (string) ($row['site_endpoint'] ?? ''));
    }

    public static function config(PDO $pdo): array
    {
        return self::configRow($pdo, true, true);
    }

    private static function configRow(PDO $pdo, bool $ensureSchema, bool $persistNormalization = false): array
    {
        if ($ensureSchema) {
            self::bootstrap($pdo);
        }

        try {
            $stmt = $pdo->query('SELECT * FROM drakon_config WHERE id = 1 LIMIT 1');
            $config = is_object($stmt) ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        } catch (Throwable) {
            return [];
        }
        if (!is_array($config)) {
            return [];
        }

        $rawSiteEndpoint = (string) ($config['site_endpoint'] ?? '');
        $config['api_base_url'] = self::normalizeApiBaseUrl((string) ($config['api_base_url'] ?? self::DEFAULT_API_BASE));
        $config['site_endpoint'] = self::backendSiteEndpoint($rawSiteEndpoint);
        if ($persistNormalization) {
            self::persistNormalizedSiteEndpoint($pdo, $rawSiteEndpoint, (string) $config['site_endpoint']);
        }
        return $config;
    }

    public static function updateConfig(PDO $pdo, array $input): void
    {
        self::bootstrap($pdo);
        // Ensure the single config row exists regardless of env-var-gated bootstrap.
        // Migration creates the table but inserts no default row; INSERT IGNORE is a no-op when row exists.
        try {
            $pdo->exec('INSERT IGNORE INTO drakon_config (id) VALUES (1)');
        } catch (Throwable) {
        }
        $stmt = $pdo->prepare(
            'UPDATE drakon_config
             SET agent_code = :agent_code,
                 agent_token = :agent_token,
                 agent_secret = :agent_secret,
                 currency = :currency,
                 api_base_url = :api_base_url,
                 site_endpoint = :site_endpoint,
                 callback_secret = :callback_secret,
                 callback_allowed_ips = :callback_allowed_ips,
                 is_active = :is_active
             WHERE id = 1'
        );
        $current = self::config($pdo);
        $agentToken = trim((string) ($input['agent_token'] ?? ''));
        if ($agentToken === '') {
            $agentToken = trim((string) ($current['agent_token'] ?? ''));
        }
        $agentSecret = trim((string) ($input['agent_secret'] ?? ''));
        if ($agentSecret === '') {
            $agentSecret = trim((string) ($current['agent_secret'] ?? ''));
        }
        $callbackSecret = trim((string) ($input['callback_secret'] ?? ''));
        if ($callbackSecret === '') {
            $callbackSecret = trim((string) ($current['callback_secret'] ?? ''));
        }
        $agentCode = trim((string) ($input['agent_code'] ?? ''));
        $apiBaseUrl = self::normalizeApiBaseUrl((string) ($input['api_base_url'] ?? self::DEFAULT_API_BASE));
        $credentialsChanged = $agentCode !== trim((string) ($current['agent_code'] ?? ''))
            || $agentToken !== trim((string) ($current['agent_token'] ?? ''))
            || $agentSecret !== trim((string) ($current['agent_secret'] ?? ''))
            || $apiBaseUrl !== self::normalizeApiBaseUrl((string) ($current['api_base_url'] ?? self::DEFAULT_API_BASE));
        $stmt->execute([
            'agent_code' => $agentCode,
            'agent_token' => $agentToken,
            'agent_secret' => $agentSecret,
            'currency' => strtoupper(trim((string) ($input['currency'] ?? 'TRY'))) ?: 'TRY',
            'api_base_url' => $apiBaseUrl,
            'site_endpoint' => self::backendSiteEndpoint((string) ($input['site_endpoint'] ?? $current['site_endpoint'] ?? '')),
            'callback_secret' => $callbackSecret,
            'callback_allowed_ips' => trim((string) ($input['callback_allowed_ips'] ?? $current['callback_allowed_ips'] ?? '')),
            'is_active' => !empty($input['is_active']) ? 1 : 0,
        ]);
        if ($credentialsChanged) {
            $pdo->exec('DELETE FROM drakon_access_tokens');
        }
    }

    public static function verifyWebhookRequest(PDO $pdo, string $rawBody, array $server, bool $healthProbe = false): array
    {
        $config = self::configRow($pdo, false);
        if ((int) ($config['is_active'] ?? 0) !== 1) {
            return ['valid' => false, 'code' => 503, 'error' => 'DRAKON_INACTIVE'];
        }

        $allowedIps = trim((string) ($config['callback_allowed_ips'] ?? ''));
        if ($allowedIps === '') {
            $allowedIps = trim((string) (getenv('DRAKON_CALLBACK_ALLOWED_IPS') ?: ''));
        }
        // Drakon panel doğrulaması GET ile gelir; IP allowlist yalnızca POST işlemlerinde uygulanır.
        if (!$healthProbe && $allowedIps !== '' && !self::ipAllowed(self::resolveClientIp($server), $allowedIps)) {
            return ['valid' => false, 'code' => 403, 'error' => 'IP_NOT_ALLOWED'];
        }

        // Drakon API documentation does not specify any webhook auth header.
        // When no callback_secret is configured, rely on IP allowlist alone.
        $secret = trim((string) (getenv('DRAKON_CALLBACK_SECRET') ?: ($config['callback_secret'] ?? '')));
        if ($secret === '') {
            return ['valid' => true];
        }

        $signature = trim((string) (
            $server['HTTP_X_DRAKON_CALLBACK_SIGNATURE']
            ?? $server['HTTP_X_SIGNATURE']
            ?? ''
        ));
        $token = trim((string) (
            $server['HTTP_X_DRAKON_CALLBACK_TOKEN']
            ?? $server['HTTP_X_CALLBACK_TOKEN']
            ?? ''
        ));

        if ($signature === '' && $token === '') {
            // Drakon panel webhooks are unsigned; optional callback_secret only applies when a header is sent.
            return ['valid' => true];
        }
        if ($signature !== '') {
            $signature = preg_replace('/^sha256=/i', '', $signature) ?? $signature;
            $expected = hash_hmac('sha256', $rawBody, $secret);
            return hash_equals($expected, $signature)
                ? ['valid' => true]
                : ['valid' => false, 'code' => 403, 'error' => 'INVALID_SIGNATURE'];
        }

        if ($token !== '' && hash_equals($secret, $token)) {
            return ['valid' => true];
        }

        return ['valid' => false, 'code' => 401, 'error' => 'MISSING_SIGNATURE'];
    }

    public static function logWebhookVerificationFailure(array $payload, array $verification, array $server): void
    {
        self::logApiResponseIssue('Drakon webhook verification failed', [
            'uri' => (string) ($server['REQUEST_URI'] ?? ''),
            'remote_addr' => (string) ($server['REMOTE_ADDR'] ?? ''),
            'method' => (string) ($payload['method'] ?? ''),
            'user_id' => (string) ($payload['user_id'] ?? ''),
            'code' => (int) ($verification['code'] ?? 403),
            'error' => (string) ($verification['error'] ?? 'UNAUTHORIZED_WEBHOOK'),
            'has_signature' => !empty($server['HTTP_X_DRAKON_CALLBACK_SIGNATURE']) || !empty($server['HTTP_X_SIGNATURE']),
            'has_token' => !empty($server['HTTP_X_DRAKON_CALLBACK_TOKEN']) || !empty($server['HTTP_X_CALLBACK_TOKEN']),
        ]);
    }

    public static function syncProviders(PDO $pdo): array
    {
        self::backfillDrakonProviderColumns($pdo);

        $data = self::request($pdo, 'GET', '/games/provider');
        $providers = self::listFromResponse($data, 'providers');
        $stmt = self::prepareDrakonProviderUpsertStatement($pdo);
        $synced = 0;
        foreach ($providers as $provider) {
            if (!is_array($provider)) {
                continue;
            }
            $code = trim((string) ($provider['code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $providerName = trim((string) ($provider['name'] ?? $code));
            if (self::isExcludedDirectProvider($providerName) || self::isExcludedDirectProvider($code)) {
                continue;
            }
            $encoded = json_encode($provider, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $params = [
                'provider_code' => $code,
                'provider_name' => $providerName,
                'rtp' => isset($provider['rtp']) ? (float) $provider['rtp'] : null,
            ];
            if (self::columnExists($pdo, 'drakon_providers', 'code')) {
                $params['code'] = $code;
            }
            if (self::columnExists($pdo, 'drakon_providers', 'name')) {
                $params['name'] = $providerName;
            }
            if (self::columnExists($pdo, 'drakon_providers', 'raw_payload')) {
                $params['raw_payload'] = is_string($encoded) ? $encoded : null;
            }
            $stmt->execute($params);
            $synced++;
        }

        return ['success' => true, 'count' => $synced, 'providers' => $providers];
    }

    private static function backfillDrakonProviderColumns(PDO $pdo): void
    {
        if (!self::columnExists($pdo, 'drakon_providers', 'provider_code')) {
            return;
        }

        try {
            if (self::columnExists($pdo, 'drakon_providers', 'code')) {
                $pdo->exec("UPDATE drakon_providers SET provider_code = code WHERE provider_code = '' AND code <> ''");
                $pdo->exec("UPDATE drakon_providers SET code = provider_code WHERE code = '' AND provider_code <> ''");
            }
            if (self::columnExists($pdo, 'drakon_providers', 'name')) {
                $pdo->exec("UPDATE drakon_providers SET provider_name = name WHERE provider_name = '' AND name <> ''");
                $pdo->exec("UPDATE drakon_providers SET name = provider_name WHERE name = '' AND provider_name <> ''");
            }
        } catch (Throwable) {
        }
    }

    private static function prepareDrakonProviderUpsertStatement(PDO $pdo): PDOStatement
    {
        static $cache = [];

        $signature = implode('|', [
            self::columnExists($pdo, 'drakon_providers', 'code') ? '1' : '0',
            self::columnExists($pdo, 'drakon_providers', 'name') ? '1' : '0',
            self::columnExists($pdo, 'drakon_providers', 'raw_payload') ? '1' : '0',
        ]);

        if (isset($cache[$signature]) && $cache[$signature] instanceof PDOStatement) {
            return $cache[$signature];
        }

        $columns = [];
        if (self::columnExists($pdo, 'drakon_providers', 'code')) {
            $columns[] = 'code';
        }
        if (self::columnExists($pdo, 'drakon_providers', 'name')) {
            $columns[] = 'name';
        }
        $columns = array_merge($columns, ['provider_code', 'provider_name', 'rtp', 'is_active', 'synced_at']);
        if (self::columnExists($pdo, 'drakon_providers', 'raw_payload')) {
            $columns[] = 'raw_payload';
        }

        $values = [];
        foreach ($columns as $column) {
            if ($column === 'is_active') {
                $values[] = '1';
                continue;
            }
            if ($column === 'synced_at') {
                $values[] = 'NOW()';
                continue;
            }
            $values[] = ':' . $column;
        }

        $updates = [
            'provider_name = VALUES(provider_name)',
            'rtp = VALUES(rtp)',
            'is_active = 1',
            'synced_at = NOW()',
        ];
        if (self::columnExists($pdo, 'drakon_providers', 'code')) {
            $updates[] = 'code = VALUES(code)';
            $updates[] = 'provider_code = VALUES(provider_code)';
        }
        if (self::columnExists($pdo, 'drakon_providers', 'name')) {
            $updates[] = 'name = VALUES(name)';
        }
        if (self::columnExists($pdo, 'drakon_providers', 'raw_payload')) {
            $updates[] = 'raw_payload = VALUES(raw_payload)';
        }

        $sql = sprintf(
            'INSERT INTO drakon_providers (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
            implode(', ', $columns),
            implode(', ', $values),
            implode(', ', $updates)
        );

        return $cache[$signature] = $pdo->prepare($sql);
    }

    public static function syncGames(PDO $pdo): array
    {
        $data = self::request($pdo, 'GET', '/games/all');
        $games = self::listFromResponse($data, 'games');
        $stmt = $pdo->prepare(
            "INSERT INTO drakon_games
                (game_id, game_code, game_name, provider_code, provider_name, rtp, image_url, banner, type, game_type, is_active, raw_payload, synced_at)
             VALUES
                (:game_id, :game_code, :game_name, :provider_code, :provider_name, :rtp, :image_url, :banner, :type, :game_type, 1, :raw_payload, NOW())
             ON DUPLICATE KEY UPDATE
                game_code = VALUES(game_code), game_name = VALUES(game_name), provider_code = VALUES(provider_code),
                provider_name = VALUES(provider_name), rtp = VALUES(rtp), image_url = VALUES(image_url),
                banner = VALUES(banner), type = VALUES(type), game_type = VALUES(game_type), is_active = 1,
                raw_payload = VALUES(raw_payload), synced_at = NOW()"
        );
        foreach ($games as $game) {
            if (!is_array($game)) {
                continue;
            }
            $gameId = trim((string) ($game['game_id'] ?? $game['game_code'] ?? ''));
            if ($gameId === '') {
                continue;
            }
            $provider = self::resolveProviderFromGame($game);
            $providerName = $provider['name'];
            if (self::isExcludedDirectProvider($providerName) || self::isExcludedDirectProvider($provider['code'])) {
                continue;
            }
            $encoded = json_encode($game, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $stmt->execute([
                'game_id' => $gameId,
                'game_code' => trim((string) ($game['game_code'] ?? $gameId)),
                'game_name' => trim((string) ($game['game_name'] ?? $game['name'] ?? $gameId)),
                'provider_code' => $provider['code'],
                'provider_name' => $providerName,
                'rtp' => isset($game['rtp']) ? (float) $game['rtp'] : null,
                'image_url' => trim((string) ($game['banner'] ?? $game['image_url'] ?? '')),
                'banner' => trim((string) ($game['banner'] ?? '')),
                'type' => self::inferGameType($providerName),
                'game_type' => self::inferGameType($providerName) === 'live' ? 1 : 0,
                'raw_payload' => is_string($encoded) ? $encoded : null,
            ]);
        }
        return ['success' => true, 'count' => count($games)];
    }

    public static function providers(PDO $pdo, array $query = []): array
    {
        self::bootstrap($pdo);
        $gameType = trim((string) ($query['game_type'] ?? $query['type'] ?? ''));
        $where = ['is_active = 1', 'provider_name <> ""', 'NOT (' . self::excludedDirectProviderSqlCondition('provider_name') . ')'];
        if (in_array($gameType, ['0', 'casino', 'slot', 'slots'], true)) {
            $where[] = "type = 'casino'";
            $where[] = 'game_type = 0';
            $where[] = 'NOT (' . self::liveProviderSqlCondition('provider_name') . ')';
        } elseif (in_array($gameType, ['1', 'live', 'live_casino'], true)) {
            $where[] = "(type = 'live' OR game_type = 1 OR " . self::liveProviderSqlCondition('provider_name') . ')';
        }
        $sql = 'SELECT provider_code, provider_name, MAX(rtp) AS rtp, 1 AS is_active, MAX(game_type) AS game_type
                FROM drakon_games
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY provider_code, provider_name
                ORDER BY provider_name ASC';
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function games(PDO $pdo, array $query): array
    {
        self::bootstrap($pdo);
        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = min(200, max(1, (int) ($query['limit'] ?? $query['per_page'] ?? 100)));
        $offset = array_key_exists('offset', $query) ? max(0, (int) $query['offset']) : (($page - 1) * $limit);
        $where = ['is_active = 1', 'NOT (' . self::excludedDirectProviderSqlCondition('provider_name') . ')'];
        $params = [];
        $search = trim((string) ($query['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(game_name LIKE :search_game_name OR provider_name LIKE :search_provider_name OR game_id LIKE :search_game_id)';
            $likeSearch = '%' . $search . '%';
            $params['search_game_name'] = $likeSearch;
            $params['search_provider_name'] = $likeSearch;
            $params['search_game_id'] = $likeSearch;
        }
        $provider = trim((string) ($query['provider'] ?? $query['provider_code'] ?? ''));
        if ($provider !== '' && $provider !== 'hepsi') {
            $normalizedProvider = strtolower($provider);
            if (in_array($normalizedProvider, ['pragmatic-virtual', 'pragmatic-play-virtual', 'pragmaticplay-virtual'], true)) {
                $where[] = '(provider_code = :provider_code OR provider_name = :provider_name OR LOWER(provider_code) LIKE :provider_virtual OR LOWER(provider_name) LIKE :provider_virtual)';
                $params['provider_virtual'] = '%pragmatic%virtual%';
            } else {
                $where[] = '(provider_code = :provider_code OR provider_name = :provider_name)';
            }
            $params['provider_code'] = $provider;
            $params['provider_name'] = $provider;
        }
        $gameType = trim((string) ($query['game_type'] ?? $query['type'] ?? ''));
        if ($gameType !== '') {
            if (in_array($gameType, ['0', 'casino', 'slot', 'slots'], true)) {
                $where[] = "type = 'casino'";
                $where[] = 'game_type = 0';
                $where[] = 'NOT (' . self::liveProviderSqlCondition('provider_name') . ')';
            } elseif (in_array($gameType, ['1', 'live', 'live_casino'], true)) {
                $where[] = "(type = 'live' OR game_type = 1 OR " . self::liveProviderSqlCondition('provider_name') . ')';
            }
        }
        if (isset($query['is_featured']) && (string) $query['is_featured'] !== '') {
            $where[] = 'is_featured = :is_featured';
            $params['is_featured'] = (int) $query['is_featured'] === 1 ? 1 : 0;
        }
        $sort = strtolower(trim((string) ($query['sort'] ?? $query['category'] ?? '')));
        $orderSql = 'is_featured DESC, game_name ASC';
        self::applyGameCategoryFilter($sort, $where, $params, $orderSql);
        $whereSql = implode(' AND ', $where);
        $count = $pdo->prepare('SELECT COUNT(*) FROM drakon_games WHERE ' . $whereSql);
        foreach ($params as $key => $value) {
            $count->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $count->execute();
        $total = (int) $count->fetchColumn();
        $stmt = $pdo->prepare(
            'SELECT id, game_id, game_code, game_name, game_name AS name, provider_name AS provider,
                    provider_code, type, game_type, image_url, image_url AS thumbnail_url, banner,
                    is_featured, rtp
             FROM drakon_games WHERE ' . $whereSql . '
             ORDER BY ' . $orderSql . '
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
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

    public static function launch(PDO $pdo, ?array $user, array $input): array
    {
        $mode = strtolower(trim((string) ($input['mode'] ?? 'real')));
        if (!empty($input['demo']) || !empty($input['isDemo'])) {
            $mode = 'fun';
        }
        $mode = in_array($mode, ['fun', 'demo'], true) ? 'fun' : 'real';
        if ($mode === 'real' && !is_array($user)) {
            return ['success' => false, 'code' => 401, 'message' => 'Oyun açmak için giriş yapın.'];
        }
        $gameId = self::resolveLaunchGameId($pdo, trim((string) ($input['game_id'] ?? $input['gameId'] ?? $input['gameid'] ?? '')));
        if ($gameId === '') {
            return ['success' => false, 'code' => 422, 'message' => 'game_id zorunludur.'];
        }
        $config = self::activeConfig($pdo);
        if ($mode === 'fun') {
            $userId = self::demoUserId();
            $displayName = 'Demo Player';
        } else {
            $userId = (string) ($user['id'] ?? '0');
            $fullName = trim((string) (($user['name'] ?? '') . ' ' . ($user['surname'] ?? '')));
            $displayName = $fullName !== '' ? $fullName : (string) ($user['username'] ?? 'User');
        }
        $params = [
            'agent_code' => (string) $config['agent_code'],
            'agent_token' => (string) $config['agent_token'],
            'game_id' => (string) $gameId,
            'currency' => strtoupper((string) ($input['currency'] ?? $config['currency'] ?? 'TRY')),
            'lang' => self::normalizeLaunchLang((string) ($input['lang'] ?? $input['locale'] ?? 'tr')),
            'user_id' => $userId,
            'user_name' => $displayName,
            'mode' => $mode,
        ];
        $siteRoot = self::launchSiteEndpoint($config);

        $publicProbe = self::probePublicWebhook($pdo, $mode === 'fun' ? self::demoUserId() : $userId);
        if (empty($publicProbe['ok'])) {
            $allowedIps = trim((string) ($config['callback_allowed_ips'] ?? ''));
            if ($allowedIps === '') {
                $allowedIps = trim((string) (getenv('DRAKON_CALLBACK_ALLOWED_IPS') ?: ''));
            }
            $ipHint = $allowedIps !== ''
                ? ' Admin → Drakon → Callback Allowed IPs alanı dolu; Drakon sunucuları engellenmiş olabilir — BOŞ bırakın.'
                : ' Cloudflare/WAF Drakon IP\'lerini engelliyor olabilir (bo-nexthub.site için proxy gri/DNS only önerilir).';
            return [
                'success' => false,
                'code' => 422,
                'message' => 'Drakon webhook dışarıdan erişilemiyor: '
                    . (string) ($publicProbe['error'] ?? 'WEBHOOK_UNREACHABLE')
                    . $ipHint
                    . ' Drakon agent panel Site URL: '
                    . $siteRoot
                    . ' (vegasroyalspin.com veya api.bo-nexthub.site DEĞİL) — webhook: '
                    . (string) ($publicProbe['url'] ?? self::webhookPublicUrl($siteRoot)),
                'error' => (string) ($publicProbe['error'] ?? 'WEBHOOK_UNREACHABLE'),
            ];
        }

        if ($mode === 'real') {
            $webhookProbe = self::webhook($pdo, ['method' => 'user_balance', 'user_id' => $userId]);
            if ((int) ($webhookProbe['status'] ?? 0) !== 1) {
                $webhookUrl = self::webhookPublicUrl($siteRoot);
                $probeError = (string) ($webhookProbe['error'] ?? 'UNKNOWN');
                return [
                    'success' => false,
                    'code' => 422,
                    'message' => 'Webhook user_balance hazır değil (kullanıcı '
                        . $userId
                        . ', ' . $probeError . '). Drakon POST: '
                        . $webhookUrl,
                    'error' => $probeError,
                ];
            }
        }

        self::logLaunchContext($gameId, $mode, $userId, $config);
        try {
            $response = self::request($pdo, 'GET', '/games/game_launch', $params);
        } catch (RuntimeException $exception) {
            self::logApiResponseIssue('Drakon launch request failed', [
                'game_id' => $gameId,
                'mode' => $mode,
                'message' => $exception->getMessage(),
            ]);
            return ['success' => false, 'code' => 422, 'message' => $exception->getMessage()];
        }
        try {
            $gameUrl = self::extractGameLaunchUrl($response);
        } catch (RuntimeException $exception) {
            self::logApiResponseIssue('Drakon launch URL rejected', [
                'game_id' => $gameId,
                'mode' => $mode,
                'message' => $exception->getMessage(),
                'response' => self::redactSensitivePayload($response),
            ]);
            return ['success' => false, 'code' => 422, 'message' => $exception->getMessage()];
        }
        if ($gameUrl === '') {
            self::logApiResponseIssue('Drakon launch URL missing or invalid', [
                'game_id' => $gameId,
                'response' => self::redactSensitivePayload($response),
            ]);
            return ['success' => false, 'code' => 422, 'message' => 'Drakon oyun URL dönmedi.'];
        }
        if (!self::isValidLaunchUrl($gameUrl)) {
            self::logApiResponseIssue('Drakon launch URL rejected after extraction', [
                'game_id' => $gameId,
                'mode' => $mode,
                'response' => self::redactSensitivePayload($response),
            ]);
            return [
                'success' => false,
                'code' => 422,
                'message' => 'Drakon geçersiz oyun URL döndü. Agent ayarlarını ve webhook URL\'ini kontrol edin.',
            ];
        }
        $sessionKey = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare(
            'INSERT INTO drakon_game_sessions
                (session_key, user_id, username, game_id, mode, currency, lang, game_url, request_payload, response_payload)
             VALUES
                (:session_key, :user_id, :username, :game_id, :mode, :currency, :lang, :game_url, :request_payload, :response_payload)'
        );
        $stmt->execute([
            'session_key' => $sessionKey,
            'user_id' => is_array($user) ? (int) ($user['id'] ?? 0) : null,
            'username' => $displayName,
            'game_id' => $gameId,
            'mode' => $mode,
            'currency' => $params['currency'],
            'lang' => $params['lang'],
            'game_url' => $gameUrl,
            'request_payload' => json_encode(self::redactSensitivePayload($params), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'response_payload' => json_encode(self::redactSensitivePayload($response), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        return [
            'success' => true,
            'code' => 200,
            'message' => 'Oyun başlatıldı.',
            'data' => [
                'game_url' => $gameUrl,
                'launch_url' => $gameUrl,
                'session_id' => $sessionKey,
                'mode' => $mode,
                'open_mode' => self::launchOpenMode($gameUrl),
            ],
        ];
    }

    private static function extractGameLaunchUrl(array $response): string
    {
        if (array_key_exists('status', $response)) {
            $status = $response['status'];
            if ($status === false || $status === 0 || $status === '0' || $status === 'error' || $status === 'failed') {
                throw new RuntimeException(self::apiErrorMessage($response, 'Drakon oyun başlatılamadı'));
            }
        }

        $responseData = is_array($response['data'] ?? null) ? $response['data'] : [];
        $gameData = is_array($response['game'] ?? null) ? $response['game'] : [];
        $nestedGame = is_array($responseData['game'] ?? null) ? $responseData['game'] : [];
        $gameUrl = self::firstValidLaunchUrl([
            $response['game_url'] ?? null,
            $response['launch_url'] ?? null,
            $response['url'] ?? null,
            $response['link'] ?? null,
            $responseData['game_url'] ?? null,
            $responseData['launch_url'] ?? null,
            $responseData['url'] ?? null,
            $responseData['link'] ?? null,
            $gameData['game_url'] ?? null,
            $gameData['launch_url'] ?? null,
            $gameData['url'] ?? null,
            $gameData['link'] ?? null,
            $nestedGame['game_url'] ?? null,
            $nestedGame['launch_url'] ?? null,
            $nestedGame['url'] ?? null,
            $nestedGame['link'] ?? null,
        ], $response);
        if ($gameUrl === '') {
            throw new RuntimeException(self::apiErrorMessage($response, 'Drakon oyun URL dönmedi'));
        }

        return $gameUrl;
    }

    /**
     * @param array<int, mixed> $candidates
     * @param array<string, mixed> $response
     */
    private static function firstValidLaunchUrl(array $candidates, array $response = []): string
    {
        $lastInvalid = '';
        foreach ($candidates as $candidate) {
            $url = trim((string) $candidate);
            if ($url === '') {
                continue;
            }
            if (self::isValidLaunchUrl($url)) {
                return $url;
            }
            if (self::isDrakonInfrastructureLaunchUrl($url)) {
                $lastInvalid = $url;
            }
        }

        if ($lastInvalid !== '') {
            $panel = self::canonicalBackendSiteUrl();
            $webhook = self::webhookPublicUrl($panel);
            $allowedIps = trim((string) (getenv('DRAKON_CALLBACK_ALLOWED_IPS') ?: ''));
            $ipHint = $allowedIps !== ''
                ? ' Callback Allowed IPs dolu — Drakon POST engellenmiş olabilir.'
                : '';
            throw new RuntimeException(
                'Drakon game_launch altyapı URL döndü — webhook sizin sunucunuzda çalışsa bile Drakon agent panelindeki Site URL yanlış olabilir. '
                . 'gator.drakon.casino panelinde Site URL = '
                . $panel
                . ' (api.bo-nexthub.site veya vegasroyalspin.com DEĞİL). Webhook: '
                . $webhook
                . $ipHint
                . ' Drakon destek ile panel Site URL güncellemesini doğrulayın. Test: curl -X POST "'
                . $webhook
                . '" -H "Content-Type: application/json" -d \'{"method":"user_balance","user_id":"1"}\''
            );
        }

        if (($response['error'] ?? '') === 'INTEGRATION_VALIDATION_FAILED') {
            throw new RuntimeException(self::apiErrorMessage($response, 'Drakon oyun başlatılamadı'));
        }

        return '';
    }

    private static function isDrakonInfrastructureLaunchUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return false;
        }
        $host = strtolower((string) $parts['host']);
        if (self::isDrakonInfrastructureHost($host)) {
            return true;
        }
        $path = strtolower((string) ($parts['path'] ?? ''));

        return str_contains($path, 'games/game_launch');
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function logLaunchContext(string $gameId, string $mode, string $userId, array $config): void
    {
        self::logApiResponseIssue('Drakon launch attempt', [
            'game_id' => $gameId,
            'mode' => $mode,
            'user_id' => $userId,
            'site_endpoint' => (string) ($config['site_endpoint'] ?? ''),
            'api_base_url' => (string) ($config['api_base_url'] ?? ''),
        ]);
    }

    private static function launchOpenMode(string $gameUrl): string
    {
        return self::isIframeFriendlyLaunchUrl($gameUrl) ? 'iframe' : 'redirect';
    }

    private static function isIframeFriendlyLaunchUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return false;
        }

        return !self::isDrakonInfrastructureHost(strtolower((string) $parts['host']));
    }

    private static function isDrakonInfrastructureHost(string $host): bool
    {
        $host = strtolower(trim($host));
        foreach (['gator.drakon.casino', 'drakon.casino', 'gator.drakonapi.tech', 'drakonapi.tech'] as $blockedHost) {
            if ($host === $blockedHost || str_ends_with($host, '.' . $blockedHost)) {
                return true;
            }
        }

        return false;
    }

    public static function webhook(PDO $pdo, array $payload): array
    {
        $started = microtime(true);
        $method = trim((string) ($payload['method'] ?? ''));
        $response = match ($method) {
            'account_details' => self::webhookAccountDetails($pdo, $payload),
            'user_balance' => self::webhookBalance($pdo, $payload),
            'transaction_bet' => self::webhookBet($pdo, $payload),
            'transaction_win' => self::webhookWin($pdo, $payload),
            'refund' => self::webhookRefund($pdo, $payload),
            default => ['status' => false, 'error' => 'INVALID_METHOD'],
        };
        if (self::shouldLogWebhook($method, $response)) {
            self::logWebhook($pdo, $payload, $response, (int) round((microtime(true) - $started) * 1000));
        }
        return $response;
    }

    /**
     * @param array<string, mixed> $response
     */
    private static function shouldLogWebhook(string $method, array $response): bool
    {
        if ($method === 'user_balance' && (int) ($response['status'] ?? 0) === 1) {
            return false;
        }

        return true;
    }

    public static function campaignRequest(PDO $pdo, string $method, string $path, array $payload = [], array $query = [], ?string $idempotencyKey = null): array
    {
        $method = strtoupper($method);
        $path = '/' . ltrim($path, '/');
        $requestPayload = $method === 'GET'
            ? self::normalizeCampaignQuery($path, $query)
            : self::normalizeCampaignPayload($path, $payload);
        $headers = [];
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers['Idempotency-Key'] = $idempotencyKey;
            $headers['X-Request-Id'] = $idempotencyKey;
        }
        $response = self::request($pdo, $method, $path, $requestPayload, $headers);
        $stmt = $pdo->prepare(
            'INSERT INTO drakon_campaign_requests (action, campaign_code, idempotency_key, request_payload, response_payload, status, request_id)
             VALUES (:action, :campaign_code, :idempotency_key, :request_payload, :response_payload, :status, :request_id)'
        );
        $stmt->execute([
            'action' => $path,
            'campaign_code' => self::campaignCodeFromPath($path),
            'idempotency_key' => $idempotencyKey,
            'request_payload' => json_encode($requestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'response_payload' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => !empty($response['status']) ? 'success' : 'failed',
            'request_id' => (string) ($response['meta']['request_id'] ?? ''),
        ]);
        self::syncCampaignResponse($pdo, $path, $requestPayload, $response);
        return $response;
    }

    private static function normalizeCampaignQuery(string $path, array $query): array
    {
        $allowed = strpos($path, '/campaigns/vendors/limits') !== false
            ? ['vendors', 'games']
            : ['vendor', 'status', 'active', 'per_page'];
        $normalized = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $query)) {
                continue;
            }
            $value = is_array($query[$key]) ? implode(',', array_filter(array_map('strval', $query[$key]))) : trim((string) $query[$key]);
            if ($value !== '') {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private static function normalizeCampaignPayload(string $path, array $payload): array
    {
        foreach (['action', '_token', 'idempotency_key', 'currency_code', 'request_id'] as $key) {
            unset($payload[$key]);
        }

        if (strpos($path, '/players/add') !== false || strpos($path, '/players/remove') !== false) {
            return ['players' => self::normalizeCampaignPlayers($payload['players'] ?? $payload['player'] ?? $payload['user_id'] ?? $payload['user_ids'] ?? [])];
        }

        if (substr($path, -strlen('/campaigns/create')) !== '/campaigns/create') {
            return $payload;
        }

        $games = [];
        $rawGames = is_array($payload['games'] ?? null) ? $payload['games'] : [];
        foreach ($rawGames as $game) {
            if (!is_array($game)) {
                continue;
            }
            $gameId = trim((string) ($game['game_id'] ?? $game['gameId'] ?? $game['id'] ?? ''));
            $totalBet = trim((string) ($game['total_bet'] ?? $game['totalBet'] ?? ''));
            if ($gameId === '' || $totalBet === '') {
                continue;
            }
            $games[] = [
                'game_id' => is_numeric($gameId) ? (int) $gameId : $gameId,
                'total_bet' => $totalBet,
            ];
        }

        $normalized = [
            'campaign_code' => trim((string) ($payload['campaign_code'] ?? '')),
            'vendor' => trim((string) ($payload['vendor'] ?? '')),
            'freespins_per_player' => (int) ($payload['freespins_per_player'] ?? 0),
            'begins_at' => (int) ($payload['begins_at'] ?? 0),
            'expires_at' => (int) ($payload['expires_at'] ?? 0),
            'games' => $games,
        ];
        $players = self::normalizeCampaignPlayers($payload['players'] ?? []);
        if ($players !== []) {
            $normalized['players'] = $players;
        }

        return array_filter($normalized, static fn($value): bool => $value !== '' && $value !== 0 && $value !== []);
    }

    private static function normalizeCampaignPlayers($players): array
    {
        if (is_string($players)) {
            $players = strpos($players, ',') !== false ? explode(',', $players) : [$players];
        } elseif (!is_array($players)) {
            $players = [$players];
        }

        $normalized = [];
        foreach ($players as $player) {
            $player = trim((string) $player);
            if ($player !== '') {
                $normalized[] = $player;
            }
        }

        return array_values(array_unique($normalized));
    }

    private static function syncCampaignResponse(PDO $pdo, string $path, array $payload, array $response): void
    {
        if (empty($response['status'])) {
            return;
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        if (isset($data[0]) && is_array($data[0])) {
            foreach ($data as $campaign) {
                if (is_array($campaign)) {
                    self::upsertCampaign($pdo, $campaign, $response);
                }
            }
            return;
        }

        if (isset($data['campaign_code']) || isset($payload['campaign_code'])) {
            self::upsertCampaign($pdo, array_merge($payload, $data), $response);
        }

        $campaignCode = trim((string) ($data['campaign_code'] ?? $payload['campaign_code'] ?? self::campaignCodeFromPath($path) ?? ''));
        $players = $data['players'] ?? $payload['players'] ?? null;
        if ($campaignCode !== '' && is_array($players)) {
            $status = strpos($path, '/players/remove') !== false ? 'removed' : 'assigned';
            self::syncCampaignPlayers($pdo, $campaignCode, $players, $status, $response);
        }
    }

    private static function upsertCampaign(PDO $pdo, array $campaign, array $response): void
    {
        $campaignCode = trim((string) ($campaign['campaign_code'] ?? ''));
        if ($campaignCode === '') {
            return;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO drakon_campaigns
                (campaign_code, vendor, currency_code, freespins_per_player, begins_at, expires_at, active, status, payload, remote_response)
             VALUES
                (:campaign_code, :vendor, :currency_code, :freespins_per_player, :begins_at, :expires_at, :active, :status, :payload, :remote_response)
             ON DUPLICATE KEY UPDATE
                vendor = VALUES(vendor),
                currency_code = VALUES(currency_code),
                freespins_per_player = VALUES(freespins_per_player),
                begins_at = VALUES(begins_at),
                expires_at = VALUES(expires_at),
                active = VALUES(active),
                status = VALUES(status),
                payload = VALUES(payload),
                remote_response = VALUES(remote_response),
                updated_at = NOW()"
        );
        $stmt->execute([
            'campaign_code' => $campaignCode,
            'vendor' => trim((string) ($campaign['vendor'] ?? '')),
            'currency_code' => trim((string) ($campaign['currency_code'] ?? '')) ?: null,
            'freespins_per_player' => (int) ($campaign['freespins_per_player'] ?? 0),
            'begins_at' => isset($campaign['begins_at']) ? (int) $campaign['begins_at'] : null,
            'expires_at' => isset($campaign['expires_at']) ? (int) $campaign['expires_at'] : null,
            'active' => !empty($campaign['active']) ? 1 : 0,
            'status' => trim((string) ($campaign['status'] ?? 'active')) ?: 'active',
            'payload' => json_encode($campaign, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'remote_response' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private static function syncCampaignPlayers(PDO $pdo, string $campaignCode, array $players, string $status, array $response): void
    {
        $stmt = $pdo->prepare(
            "INSERT INTO drakon_campaign_players (campaign_code, user_id, status, remote_response)
             VALUES (:campaign_code, :user_id, :status, :remote_response)
             ON DUPLICATE KEY UPDATE status = VALUES(status), remote_response = VALUES(remote_response), updated_at = NOW()"
        );
        $encoded = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        foreach ($players as $player) {
            $userId = (int) $player;
            if ($userId <= 0) {
                continue;
            }
            $stmt->execute([
                'campaign_code' => $campaignCode,
                'user_id' => $userId,
                'status' => $status,
                'remote_response' => $encoded,
            ]);
        }
    }

    private static function webhookAccountDetails(PDO $pdo, array $payload): array
    {
        $userIdRaw = trim((string) ($payload['user_id'] ?? ''));
        if (self::isDemoWebhookUserId($userIdRaw)) {
            return [
                'email' => 'demo@demo.local',
                'name_jogador' => 'Demo Player',
                'date' => date('c'),
            ];
        }
        $user = self::user($pdo, (int) $userIdRaw);
        if (!$user) {
            return ['status' => false, 'error' => 'INVALID_USER'];
        }
        return [
            'email' => (string) ($user['email'] ?? ''),
            'name_jogador' => trim((string) (($user['name'] ?? '') . ' ' . ($user['surname'] ?? ''))) ?: (string) ($user['username'] ?? ''),
            'date' => date('c', strtotime((string) ($user['created_at'] ?? 'now'))),
        ];
    }

    private static function webhookBalance(PDO $pdo, array $payload): array
    {
        $userIdRaw = trim((string) ($payload['user_id'] ?? ''));
        if (self::isDemoWebhookUserId($userIdRaw)) {
            return ['status' => 1, 'balance' => self::readDemoBalance(self::demoWalletKey($payload))];
        }
        $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $userIdRaw]);
        $balance = $stmt->fetchColumn();
        if ($balance === false) {
            return ['status' => 0, 'error' => 'INVALID_USER'];
        }

        return ['status' => 1, 'balance' => round((float) $balance, 2)];
    }

    private static function webhookBet(PDO $pdo, array $payload): array
    {
        $amount = round((float) ($payload['bet'] ?? $payload['amount'] ?? 0), 2);
        if ($amount <= 0) {
            return ['status' => false, 'error' => 'NO_AMOUNT'];
        }
        if (self::isDemoWebhookUserId(trim((string) ($payload['user_id'] ?? '')))) {
            return self::applyDemoTransaction($payload, 'bet', $amount);
        }
        return self::applyTransaction($pdo, $payload, 'bet', $amount);
    }

    private static function webhookWin(PDO $pdo, array $payload): array
    {
        $amount = round((float) ($payload['win'] ?? 0), 2);
        if ($amount < 0) {
            return ['status' => false, 'error' => 'NO_AMOUNT'];
        }
        if (self::isDemoWebhookUserId(trim((string) ($payload['user_id'] ?? '')))) {
            return self::applyDemoTransaction($payload, 'win', $amount);
        }
        if (!self::hasExistingBetForWin($pdo, $payload)) {
            return ['status' => false, 'error' => 'INVALID_TRANSACTION'];
        }
        return self::applyTransaction($pdo, $payload, 'win', $amount);
    }

    private static function webhookRefund(PDO $pdo, array $payload): array
    {
        $amount = round((float) ($payload['amount'] ?? 0), 2);
        if ($amount <= 0) {
            return ['status' => false, 'error' => 'NO_AMOUNT'];
        }
        if (self::isDemoWebhookUserId(trim((string) ($payload['user_id'] ?? '')))) {
            return self::applyDemoTransaction($payload, 'refund', $amount);
        }
        return self::applyRefund($pdo, $payload, $amount);
    }

    private static function applyTransaction(PDO $pdo, array $payload, string $type, float $amount): array
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        $transactionId = trim((string) ($payload['transaction_id'] ?? ''));
        if ($userId <= 0) {
            return ['status' => false, 'error' => 'INVALID_USER'];
        }
        if ($transactionId === '') {
            return ['status' => false, 'error' => 'INVALID_TRANSACTION'];
        }
        $pdo->beginTransaction();
        try {
            $exists = $pdo->prepare('SELECT after_balance, txn_type FROM drakon_transactions WHERE transaction_id = :transaction_id LIMIT 1');
            $exists->execute(['transaction_id' => $transactionId]);
            $existingRow = $exists->fetch(PDO::FETCH_ASSOC);
            if (is_array($existingRow)) {
                $pdo->commit();
                $balance = round((float) ($existingRow['after_balance'] ?? 0), 2);
                // Idempotent: same transaction_id must not double-charge (Drakon retries on timeout).
                return ['status' => true, 'balance' => $balance];
            }
            $stmt = $pdo->prepare('SELECT id, balance FROM users WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($user)) {
                $pdo->rollBack();
                return ['status' => false, 'error' => 'INVALID_USER'];
            }
            $before = round((float) ($user['balance'] ?? 0), 2);
            $after = $type === 'bet' ? $before - $amount : $before + $amount;
            if ($after < 0) {
                $pdo->rollBack();
                return ['status' => false, 'error' => 'NO_BALANCE'];
            }
            $pdo->prepare('UPDATE users SET balance = :balance WHERE id = :id')->execute([
                'balance' => number_format($after, 2, '.', ''),
                'id' => $userId,
            ]);
            $gameId = (string) ($payload['game'] ?? '');
            $gameMeta = self::gameMeta($pdo, $gameId);
            $userMeta = self::userMetaFromRow($user);
            $insert = $pdo->prepare(
                'INSERT INTO drakon_transactions
                    (user_id, username, user_full_name, transaction_id, session_id, round_id, game_id, game_name, provider_name, image_url, txn_type, amount, bet_amount, win_amount, before_balance, after_balance, raw_payload)
                 VALUES
                    (:user_id, :username, :user_full_name, :transaction_id, :session_id, :round_id, :game_id, :game_name, :provider_name, :image_url, :txn_type, :amount, :bet_amount, :win_amount, :before_balance, :after_balance, :raw_payload)'
            );
            $insert->execute([
                'user_id' => $userId,
                'username' => $userMeta['username'],
                'user_full_name' => $userMeta['user_full_name'],
                'transaction_id' => $transactionId,
                'session_id' => (string) ($payload['session_id'] ?? ''),
                'round_id' => (string) ($payload['round_id'] ?? ''),
                'game_id' => $gameId,
                'game_name' => $gameMeta['game_name'],
                'provider_name' => $gameMeta['provider_name'],
                'image_url' => $gameMeta['image_url'],
                'txn_type' => $type,
                'amount' => number_format($amount, 2, '.', ''),
                'bet_amount' => $type === 'bet' ? number_format($amount, 2, '.', '') : number_format((float) ($payload['bet'] ?? 0), 2, '.', ''),
                'win_amount' => $type === 'win' ? number_format($amount, 2, '.', '') : '0.00',
                'before_balance' => number_format($before, 2, '.', ''),
                'after_balance' => number_format($after, 2, '.', ''),
                'raw_payload' => json_encode(self::redactSensitivePayload($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $pdo->commit();
            return ['status' => true, 'balance' => round($after, 2)];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof PDOException && strpos($exception->getMessage(), 'uniq_drakon_transaction_id') !== false) {
                $balanceStmt = $pdo->prepare('SELECT after_balance FROM drakon_transactions WHERE transaction_id = :transaction_id LIMIT 1');
                $balanceStmt->execute(['transaction_id' => $transactionId]);
                $balance = round((float) ($balanceStmt->fetchColumn() ?: 0), 2);
                return ['status' => true, 'balance' => $balance];
            }
            error_log('Drakon transaction error: ' . $exception->getMessage());
            return ['status' => false, 'error' => 'INVALID_TRANSACTION'];
        }
    }

    private static function hasExistingBetForWin(PDO $pdo, array $payload): bool
    {
        $transactionId = trim((string) ($payload['transaction_id'] ?? ''));
        if ($transactionId !== '') {
            $exists = $pdo->prepare('SELECT 1 FROM drakon_transactions WHERE transaction_id = :transaction_id AND txn_type = :txn_type LIMIT 1');
            $exists->execute(['transaction_id' => $transactionId, 'txn_type' => 'win']);
            if ($exists->fetchColumn() !== false) {
                return true;
            }
        }

        $userId = (int) ($payload['user_id'] ?? 0);
        $roundId = trim((string) ($payload['round_id'] ?? ''));
        if ($userId <= 0 || $roundId === '') {
            return false;
        }

        $stmt = $pdo->prepare(
            "SELECT 1
             FROM drakon_transactions
             WHERE user_id = :user_id
               AND round_id = :round_id
               AND txn_type = 'bet'
             LIMIT 1"
        );
        $stmt->execute([
            'user_id' => $userId,
            'round_id' => $roundId,
        ]);
        return $stmt->fetchColumn() !== false;
    }

    private static function gameMeta(PDO $pdo, string $gameId): array
    {
        $gameId = trim($gameId);
        $fallback = [
            'game_name' => $gameId,
            'provider_name' => '',
            'image_url' => '',
        ];
        if ($gameId === '') {
            return $fallback;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT game_name, provider_name, COALESCE(NULLIF(image_url, ''), NULLIF(banner, ''), '') AS image_url
                 FROM drakon_games
                 WHERE game_id = :game_id
                 LIMIT 1"
            );
            $stmt->execute(['game_id' => $gameId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return $fallback;
            }

            return [
                'game_name' => trim((string) ($row['game_name'] ?? '')) ?: $gameId,
                'provider_name' => trim((string) ($row['provider_name'] ?? '')),
                'image_url' => trim((string) ($row['image_url'] ?? '')),
            ];
        } catch (Throwable) {
            return $fallback;
        }
    }

    private static function userMetaFromRow(array $user): array
    {
        $username = trim((string) ($user['username'] ?? ''));
        $fullName = trim((string) (($user['name'] ?? '') . ' ' . ($user['surname'] ?? '')));
        return [
            'username' => $username !== '' ? $username : null,
            'user_full_name' => $fullName !== '' ? $fullName : ($username !== '' ? $username : null),
        ];
    }

    private static function applyRefund(PDO $pdo, array $payload, float $amount): array
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        $incomingTransactionId = trim((string) ($payload['transaction_id'] ?? ''));
        $refundTransactionId = trim((string) ($payload['refund_transaction_id'] ?? ''));
        $transactionId = $refundTransactionId !== '' ? $refundTransactionId : $incomingTransactionId;
        if ($userId <= 0) {
            return ['status' => false, 'error' => 'INVALID_USER'];
        }
        if ($transactionId === '') {
            return ['status' => false, 'error' => 'INVALID_TRANSACTION'];
        }
        $relatedId = trim((string) (
            $payload['related_transaction_id']
            ?? $payload['reference_transaction_id']
            ?? $payload['bet_transaction_id']
            ?? $payload['win_transaction_id']
            ?? ''
        ));

        $pdo->beginTransaction();
        try {
            if ($refundTransactionId === '' && $incomingTransactionId !== '') {
                $original = $pdo->prepare("SELECT txn_type FROM drakon_transactions WHERE transaction_id = :transaction_id AND txn_type IN ('bet', 'win') LIMIT 1");
                $original->execute(['transaction_id' => $incomingTransactionId]);
                $originalType = $original->fetchColumn();
                if ($originalType !== false) {
                    $relatedId = $relatedId !== '' ? $relatedId : $incomingTransactionId;
                    $transactionId = 'refund:' . $incomingTransactionId;
                }
            }

            $exists = $pdo->prepare('SELECT after_balance FROM drakon_transactions WHERE transaction_id = :transaction_id AND txn_type = :txn_type LIMIT 1');
            $exists->execute(['transaction_id' => $transactionId, 'txn_type' => 'refund']);
            $existingBalance = $exists->fetchColumn();
            if ($existingBalance !== false) {
                $pdo->commit();
                return ['status' => true, 'balance' => round((float) $existingBalance, 2)];
            }

            $relatedType = 'bet';
            if ($relatedId !== '') {
                $related = $pdo->prepare('SELECT txn_type, amount FROM drakon_transactions WHERE transaction_id = :transaction_id LIMIT 1');
                $related->execute(['transaction_id' => $relatedId]);
                $relatedRow = $related->fetch(PDO::FETCH_ASSOC);
                if (is_array($relatedRow) && in_array((string) ($relatedRow['txn_type'] ?? ''), ['bet', 'win'], true)) {
                    $relatedType = (string) $relatedRow['txn_type'];
                    if ($amount <= 0) {
                        $amount = round((float) ($relatedRow['amount'] ?? 0), 2);
                    }
                }
            }

            // Drakon sends its own unique transaction_id for refunds — no related_transaction_id field.
            // Fall back to round_id lookup to determine if we're reversing a bet or a win.
            $roundIdForRefund = trim((string) ($payload['round_id'] ?? ''));
            if ($relatedId === '' && $roundIdForRefund !== '' && $userId > 0) {
                $roundStmt = $pdo->prepare(
                    "SELECT transaction_id, txn_type, amount FROM drakon_transactions
                     WHERE user_id = :user_id AND round_id = :round_id
                       AND txn_type IN ('bet', 'win')
                     ORDER BY id DESC LIMIT 1"
                );
                $roundStmt->execute(['user_id' => $userId, 'round_id' => $roundIdForRefund]);
                $roundRow = $roundStmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($roundRow)) {
                    $relatedType = (string) ($roundRow['txn_type'] ?? 'bet');
                    $relatedId = (string) ($roundRow['transaction_id'] ?? '');
                    if ($amount <= 0) {
                        $amount = round((float) ($roundRow['amount'] ?? 0), 2);
                    }
                }
            }

            $stmt = $pdo->prepare('SELECT id, balance FROM users WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($user)) {
                $pdo->rollBack();
                return ['status' => false, 'error' => 'INVALID_USER'];
            }
            $before = round((float) ($user['balance'] ?? 0), 2);
            $after = $relatedType === 'win' ? $before - $amount : $before + $amount;
            if ($after < 0) {
                $pdo->rollBack();
                return ['status' => false, 'error' => 'NO_BALANCE'];
            }
            $pdo->prepare('UPDATE users SET balance = :balance WHERE id = :id')->execute([
                'balance' => number_format($after, 2, '.', ''),
                'id' => $userId,
            ]);
            $gameId = (string) ($payload['game'] ?? '');
            $gameMeta = self::gameMeta($pdo, $gameId);
            $userMeta = self::userMetaFromRow($user);
            $insert = $pdo->prepare(
                'INSERT INTO drakon_transactions
                    (user_id, username, user_full_name, transaction_id, related_transaction_id, session_id, round_id, game_id, game_name, provider_name, image_url, txn_type, amount, bet_amount, win_amount, before_balance, after_balance, raw_payload)
                 VALUES
                    (:user_id, :username, :user_full_name, :transaction_id, :related_transaction_id, :session_id, :round_id, :game_id, :game_name, :provider_name, :image_url, :txn_type, :amount, :bet_amount, :win_amount, :before_balance, :after_balance, :raw_payload)'
            );
            $insert->execute([
                'user_id' => $userId,
                'username' => $userMeta['username'],
                'user_full_name' => $userMeta['user_full_name'],
                'transaction_id' => $transactionId,
                'related_transaction_id' => $relatedId !== '' ? $relatedId : null,
                'session_id' => (string) ($payload['session_id'] ?? ''),
                'round_id' => (string) ($payload['round_id'] ?? ''),
                'game_id' => $gameId,
                'game_name' => $gameMeta['game_name'],
                'provider_name' => $gameMeta['provider_name'],
                'image_url' => $gameMeta['image_url'],
                'txn_type' => 'refund',
                'amount' => number_format($amount, 2, '.', ''),
                'bet_amount' => $relatedType === 'bet' ? number_format($amount, 2, '.', '') : '0.00',
                'win_amount' => $relatedType === 'win' ? number_format($amount, 2, '.', '') : '0.00',
                'before_balance' => number_format($before, 2, '.', ''),
                'after_balance' => number_format($after, 2, '.', ''),
                'raw_payload' => json_encode(self::redactSensitivePayload($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $pdo->commit();
            return ['status' => true, 'balance' => round($after, 2)];
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['status' => false, 'error' => 'INVALID_TRANSACTION'];
        }
    }

    private static function request(PDO $pdo, string $method, string $path, array $params = [], array $extraHeaders = [], bool $retryOnUnauthorized = true): array
    {
        $config = self::activeConfig($pdo);
        $apiBase = self::normalizeApiBaseUrl((string) ($config['api_base_url'] ?? self::DEFAULT_API_BASE));
        $token = self::accessToken($pdo, $config);
        $base = $apiBase;
        $url = $base . '/' . ltrim($path, '/');
        if ($path === '/games/game_launch') {
            $params = self::appendLaunchEndpointParams($params, $config);
        }
        $headers = array_merge([
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
        ], array_map(static fn($k, $v): string => $k . ': ' . $v, array_keys($extraHeaders), $extraHeaders));

        if (strtoupper($method) === 'GET' && $params !== []) {
            $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($params);
        }
        $timeout = $path === '/games/game_launch' ? 25 : 20;
        $requestResult = self::executeApiRequest(
            strtoupper($method),
            $url,
            $headers,
            strtoupper($method) === 'GET' ? [] : $params,
            $timeout,
            $path === '/games/game_launch' ? 1 : 3
        );
        $raw = $requestResult['raw'];
        $err = $requestResult['error'];
        $code = $requestResult['status'];
        $contentType = $requestResult['content_type'];
        $rawHeaders = $requestResult['headers'];
        $rawBody = $requestResult['body'];
        $effectiveUrl = $requestResult['effective_url'];
        if (!is_string($raw) || $raw === '') {
            throw new RuntimeException('Drakon API yanıt vermedi: ' . $err);
        }
        $data = json_decode(self::stripUtf8Bom(trim((string) $rawBody)), true);
        if (!is_array($data)) {
            if (in_array($code, [401, 403], true) && $retryOnUnauthorized) {
                $pdo->exec('DELETE FROM drakon_access_tokens');
                return self::request($pdo, $method, $path, $params, $extraHeaders, false);
            }
            if ($path === '/games/game_launch') {
                $launchUrl = self::extractLaunchUrlFromNonJsonResponse((string) $rawBody, (string) $rawHeaders);
                if ($launchUrl !== '') {
                    return ['game_url' => $launchUrl, 'launch_url' => $launchUrl, 'url' => $launchUrl];
                }
            }
            self::logApiResponseIssue('Drakon API non-json response', [
                'method' => strtoupper($method),
                'path' => $path,
                'status' => $code,
                'content_type' => $contentType,
                'effective_url' => self::redactSensitiveString($effectiveUrl),
                'location' => self::redactSensitiveString(self::redirectLocationFromHeaders((string) $rawHeaders, $effectiveUrl)),
                'body_snippet' => self::responseSnippet((string) $rawBody),
            ]);
            throw new RuntimeException('Drakon API JSON yanıtı okunamadı' . ($code > 0 ? ' (HTTP ' . $code . ')' : '') . '.');
        }
        if (in_array($code, [401, 403], true) && $retryOnUnauthorized) {
            $pdo->exec('DELETE FROM drakon_access_tokens');
            return self::request($pdo, $method, $path, $params, $extraHeaders, false);
        }
        if ($code >= 400) {
            self::logApiResponseIssue('Drakon API error response', [
                'method' => strtoupper($method),
                'path' => $path,
                'status' => $code,
                'effective_url' => self::redactSensitiveString($effectiveUrl),
                'response' => self::redactSensitivePayload($data),
            ]);
            throw new RuntimeException(self::apiErrorMessage($data, 'Drakon API hatası'));
        }
        if ($path === '/games/game_launch' && empty($data['game_url']) && empty($data['launch_url']) && empty($data['url']) && empty($data['data'])) {
            self::logApiResponseIssue('Drakon launch response without URL', [
                'method' => strtoupper($method),
                'path' => $path,
                'status' => $code,
                'effective_url' => self::redactSensitiveString($effectiveUrl),
                'response' => self::redactSensitivePayload($data),
            ]);
        }
        return $data;
    }

    private static function stripUtf8Bom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }

    /**
     * @return array{raw: string|false, headers: string, body: string|false, error: string, status: int, content_type: string, effective_url: string}
     */
    private static function executeApiRequest(string $method, string $url, array $headers, array $bodyParams = [], int $timeout = 20, int $maxAttempts = 3): array
    {
        $timeout = max(5, min(45, $timeout));
        $maxAttempts = max(1, min(4, $maxAttempts));
        $currentUrl = $url;
        $lastResult = [
            'raw' => false,
            'headers' => '',
            'body' => false,
            'error' => '',
            'status' => 0,
            'content_type' => '',
            'effective_url' => $url,
        ];

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $requestHeaders = $headers;
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $currentUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => $requestHeaders,
                CURLOPT_CUSTOMREQUEST => $method,
            ]);
            if ($method !== 'GET' && $bodyParams !== []) {
                $encodedBody = json_encode($bodyParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($encodedBody) ? $encodedBody : '{}');
                $requestHeaders[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
            }
            $raw = curl_exec($ch);
            $err = curl_error($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            curl_close($ch);

            $rawHeaders = is_string($raw) && $headerSize > 0 ? substr($raw, 0, $headerSize) : '';
            $rawBody = is_string($raw) && $headerSize > 0 ? substr($raw, $headerSize) : $raw;
            $lastResult = [
                'raw' => $raw,
                'headers' => (string) $rawHeaders,
                'body' => $rawBody,
                'error' => $err,
                'status' => $code,
                'content_type' => $contentType,
                'effective_url' => $effectiveUrl !== '' ? $effectiveUrl : $currentUrl,
            ];

            if (self::isTransientHttpStatus($code) && $attempt < ($maxAttempts - 1)) {
                usleep(100000 * ($attempt + 1));
                continue;
            }

            if (!in_array($code, [301, 302, 303, 307, 308], true)) {
                return $lastResult;
            }

            $redirectUrl = self::redirectLocationFromHeaders((string) $rawHeaders, $currentUrl);
            if ($redirectUrl === '' || $redirectUrl === $currentUrl) {
                return $lastResult;
            }
            if (self::isValidLaunchUrl($redirectUrl)) {
                $lastResult['body'] = json_encode(['game_url' => $redirectUrl], JSON_UNESCAPED_SLASHES);
                $lastResult['raw'] = $lastResult['body'];
                $lastResult['status'] = 200;
                $lastResult['content_type'] = 'application/json';
                $lastResult['effective_url'] = $redirectUrl;
                return $lastResult;
            }
            $currentUrl = $redirectUrl;
        }

        return $lastResult;
    }

    private static function extractLaunchUrlFromNonJsonResponse(string $body, string $headers): string
    {
        if (preg_match('/^Location:\s*(\S+)/im', $headers, $match)) {
            $url = trim($match[1]);
            return self::isValidLaunchUrl($url) ? $url : '';
        }

        $trimmed = trim(self::stripUtf8Bom($body));
        if (preg_match('#^https?://[^\s<>"\']+$#i', $trimmed) && self::isValidLaunchUrl($trimmed)) {
            return $trimmed;
        }

        $patterns = [
            '#(?:window\.location(?:\.href)?|location\.href)\s*=\s*[\'"]([^\'"]+)[\'"]#i',
            '#<meta[^>]+http-equiv=["\']?refresh["\']?[^>]+content=["\'][^"\']*url=([^"\']+)["\']#i',
            '#<iframe[^>]+src=["\']([^"\']+)["\']#i',
            '#<a[^>]+href=["\']([^"\']+)["\']#i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body, $match)) {
                $url = html_entity_decode(trim((string) $match[1]), ENT_QUOTES, 'UTF-8');
                if (self::isValidLaunchUrl($url)) {
                    return $url;
                }
            }
        }

        return '';
    }

    private static function isValidLaunchUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return false;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return false;
        }

        $host = strtolower((string) $parts['host']);
        if (self::isDrakonInfrastructureHost($host)) {
            return false;
        }

        $path = strtolower(trim((string) ($parts['path'] ?? ''), '/'));
        if ($path === '' || $path === 'index.php') {
            return false;
        }

        $query = strtolower((string) ($parts['query'] ?? ''));
        if (str_contains($path, 'games/game_launch') || str_contains($path, 'drakon_callback') || str_contains($path, 'drakon_api')) {
            return false;
        }
        if (preg_match('/(?:^|&)(?:agent_token|agent_secret|agent_secret_key|agent_code)=/i', $query) === 1) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private static function appendLaunchEndpointParams(array $params, array $config): array
    {
        // Resmi Drakon game_launch dokümantasyonunda site_endpoint / callback alanları yok.
        // Webhook URL yalnızca Drakon agent panelinde tanımlanır.
        return $params;
    }

    private static function responseSnippet(string $body): string
    {
        $body = preg_replace('/\s+/', ' ', trim(strip_tags($body))) ?? '';
        if (function_exists('mb_substr')) {
            return mb_substr($body, 0, 500, 'UTF-8');
        }
        return substr($body, 0, 500);
    }

    private static function logApiResponseIssue(string $message, array $context): void
    {
        $dir = dirname(__DIR__) . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        @file_put_contents($dir . '/drakon_api_responses.log', $line . PHP_EOL, FILE_APPEND);
    }

    private static function accessToken(PDO $pdo, array $config): string
    {
        $apiBase = self::normalizeApiBaseUrl((string) ($config['api_base_url'] ?? self::DEFAULT_API_BASE));
        $stmt = $pdo->query("SELECT id, access_token FROM drakon_access_tokens WHERE expires_at IS NULL OR expires_at > DATE_ADD(NOW(), INTERVAL 5 MINUTE) ORDER BY id DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row) && trim((string) ($row['access_token'] ?? '')) !== '') {
            return (string) $row['access_token'];
        }
        $base = $apiBase;
        $auth = base64_encode((string) $config['agent_token'] . ':' . (string) $config['agent_secret']);
        $authResult = self::executeAuthRequest($base . '/auth/authentication', $auth);
        $raw = $authResult['body'];
        $err = $authResult['error'];
        $code = $authResult['status'];
        $contentType = $authResult['content_type'];
        $effectiveUrl = $authResult['effective_url'];
        $data = is_string($raw) ? json_decode($raw, true) : null;
        $tokenData = is_array($data['data'] ?? null) ? $data['data'] : [];
        $token = (string) ($data['access_token'] ?? $tokenData['access_token'] ?? $data['token'] ?? $tokenData['token'] ?? '');
        if (!is_array($data) || $token === '') {
            self::logApiResponseIssue('Drakon auth failed response', [
                'status' => $code,
                'content_type' => $contentType,
                'effective_url' => $effectiveUrl,
                'body_snippet' => is_string($raw) ? self::responseSnippet($raw) : trim($err),
            ]);
            $detail = is_array($data) ? self::apiErrorMessage($data, 'Drakon auth başarısız') : trim($err);
            throw new RuntimeException('Drakon auth başarısız' . ($detail !== '' ? ': ' . $detail : '') . ($code > 0 ? ' (HTTP ' . $code . ')' : ''));
        }
        $pdo->prepare('INSERT INTO drakon_access_tokens (token_hash, access_token, expires_at, last_used_at) VALUES (:hash, :token, DATE_ADD(NOW(), INTERVAL 50 MINUTE), NOW())')
            ->execute(['hash' => hash('sha256', $token), 'token' => $token]);
        $pdo->prepare('UPDATE drakon_config SET last_auth_at = NOW() WHERE id = 1')->execute();
        return $token;
    }

    /**
     * @return array{body: string|false, error: string, status: int, content_type: string, effective_url: string}
     */
    private static function executeAuthRequest(string $url, string $auth): array
    {
        $currentUrl = $url;
        $lastResult = [
            'body' => false,
            'error' => '',
            'status' => 0,
            'content_type' => '',
            'effective_url' => $url,
        ];

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $currentUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_POST => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 4,
                CURLOPT_POSTREDIR => 7,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer ' . $auth],
            ]);
            $raw = curl_exec($ch);
            $err = curl_error($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            curl_close($ch);

            $headers = is_string($raw) && $headerSize > 0 ? substr($raw, 0, $headerSize) : '';
            $body = is_string($raw) && $headerSize > 0 ? substr($raw, $headerSize) : $raw;
            $lastResult = [
                'body' => $body,
                'error' => $err,
                'status' => $code,
                'content_type' => $contentType,
                'effective_url' => $effectiveUrl !== '' ? $effectiveUrl : $currentUrl,
            ];

            if (self::isTransientHttpStatus($code) && $attempt < 2) {
                usleep(150000 * ($attempt + 1));
                continue;
            }

            return $lastResult;
        }

        return $lastResult;
    }

    private static function redirectLocationFromHeaders(string $headers, string $baseUrl): string
    {
        if (preg_match('/^Location:\s*(.+)$/im', $headers, $match) !== 1) {
            return '';
        }
        $location = trim((string) $match[1]);
        if ($location === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $parts = parse_url($baseUrl);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }
        $scheme = (string) ($parts['scheme'] ?? 'https');
        $host = (string) $parts['host'];
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        if (substr($location, 0, 1) === '/') {
            return $scheme . '://' . $host . $port . $location;
        }
        $basePath = (string) ($parts['path'] ?? '/');
        $dir = rtrim(str_replace('\\', '/', dirname($basePath)), '/');
        return $scheme . '://' . $host . $port . ($dir !== '' ? $dir : '') . '/' . ltrim($location, '/');
    }

    private static function isTransientHttpStatus(int $code): bool
    {
        return $code === 0 || in_array($code, [408, 425, 429, 500, 502, 503, 504], true);
    }

    private static function apiErrorMessage(array $data, string $fallback): string
    {
        $message = (string) ($data['message'] ?? $data['error'] ?? $data['error_code'] ?? $fallback);
        $expectedUrl = '';
        if (!empty($data['problems']) && is_array($data['problems'])) {
            $details = [];
            foreach ($data['problems'] as $problem) {
                if (!is_array($problem)) {
                    continue;
                }
                $detail = trim((string) ($problem['problem'] ?? $problem['message'] ?? ''));
                if ($detail !== '') {
                    $details[] = $detail;
                }
                if ($expectedUrl === '') {
                    $expected = trim((string) ($problem['expected'] ?? ''));
                    if ($expected !== '' && preg_match('#https?://[^\s"\']+#', $expected, $match) === 1) {
                        $expectedUrl = rtrim((string) $match[0], '.');
                    }
                }
            }
            if ($details !== []) {
                $message = $message . ': ' . implode('; ', array_slice($details, 0, 2));
            }
        }
        if (($data['error'] ?? '') === 'INTEGRATION_VALIDATION_FAILED') {
            $backendEndpoint = self::siteEndpoint();
            $message .= ' Drakon agent panelinde site URL: '
                . $backendEndpoint
                . ' — webhook endpoint: '
                . self::webhookPublicUrl()
                . ' (POST method=user_balance).';
            if ($expectedUrl !== '' && stripos($message, $expectedUrl) === false) {
                $message .= ' Drakon şu adresi denedi: ' . $expectedUrl . '.';
            }
        }
        if (isset($data['error_code']) && strpos($message, (string) $data['error_code']) === false) {
            $message .= ' (' . (string) $data['error_code'] . ')';
        }
        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public static function integrationDiagnostics(PDO $pdo): array
    {
        $config = self::config($pdo);
        $panelUrl = rtrim(trim((string) ($config['site_endpoint'] ?? '')), '/');
        if ($panelUrl === '') {
            $panelUrl = rtrim(self::siteEndpoint(), '/');
        }
        $webhookUrl = self::webhookPublicUrl($panelUrl);
        $webhook = self::testWebhookIntegration($pdo, 1);

        return [
            'is_active' => (int) ($config['is_active'] ?? 0) === 1,
            'api_base_url' => (string) ($config['api_base_url'] ?? ''),
            'site_endpoint' => $panelUrl,
            'drakon_panel_callback_url' => $panelUrl,
            'drakon_webhook_url' => $webhookUrl,
            'drakon_webhook_probe_url' => $webhookUrl,
            'webhook_handler' => $webhook,
            'agent_code' => (string) ($config['agent_code'] ?? ''),
        ];
    }

    /**
     * Drakon sunucularının erişebildiği public webhook (HTTPS curl).
     *
     * @return array{ok: bool, url: string, error?: string, http?: int, stage?: string}
     */
    public static function probePublicWebhook(PDO $pdo, string $userId = '1'): array
    {
        $config = self::config($pdo);
        $url = self::webhookPublicUrl(self::launchSiteEndpoint($config));
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'url' => $url, 'error' => 'cURL extension missing'];
        }

        $get = self::curlWebhookProbe('GET', $url, null);
        if ($get['http'] >= 400 || $get['http'] === 0) {
            return [
                'ok' => false,
                'url' => $url,
                'http' => $get['http'],
                'stage' => 'get',
                'error' => 'GET health HTTP ' . $get['http'] . ($get['error'] !== '' ? ' (' . $get['error'] . ')' : ''),
            ];
        }

        $payload = json_encode(['method' => 'user_balance', 'user_id' => $userId], JSON_UNESCAPED_UNICODE);
        $post = self::curlWebhookProbe('POST', $url, $payload);
        if ($post['http'] >= 400 || $post['http'] === 0) {
            return [
                'ok' => false,
                'url' => $url,
                'http' => $post['http'],
                'stage' => 'post',
                'error' => 'POST user_balance HTTP ' . $post['http'] . ($post['error'] !== '' ? ' (' . $post['error'] . ')' : ''),
            ];
        }

        $decoded = json_decode((string) $post['body'], true);
        $status = is_array($decoded) ? ($decoded['status'] ?? null) : null;
        if ($status !== 1 && $status !== true && $status !== '1') {
            $snippet = self::responseSnippet((string) $post['body']);
            return [
                'ok' => false,
                'url' => $url,
                'http' => $post['http'],
                'stage' => 'post',
                'error' => 'user_balance geçersiz yanıt: ' . $snippet,
            ];
        }

        return ['ok' => true, 'url' => $url, 'http' => $post['http']];
    }

    /**
     * @return array{http: int, body: string, error: string}
     */
    private static function curlWebhookProbe(string $method, string $url, ?string $body): array
    {
        $ch = curl_init($url);
        $headers = ['Accept: application/json'];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        if (defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
        $respBody = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        return [
            'http' => $http,
            'body' => is_string($respBody) ? $respBody : '',
            'error' => $err,
        ];
    }

    /**
     * @return array{ok: bool, message: string, probe_url?: string, external?: array<string, mixed>}
     */
    public static function testWebhookIntegration(PDO $pdo, ?int $userId = null): array
    {
        $config = self::config($pdo);
        $probeUserId = $userId !== null && $userId > 0 ? (string) $userId : '1';
        $panelUrl = self::launchSiteEndpoint($config);
        $probeUrl = self::webhookPublicUrl($panelUrl);

        $external = self::probePublicWebhook($pdo, $probeUserId);
        if (empty($external['ok'])) {
            return [
                'ok' => false,
                'message' => 'Public webhook erişilemiyor: '
                    . (string) ($external['error'] ?? 'unknown')
                    . '. Drakon panel Site URL: '
                    . $panelUrl
                    . ' — webhook: '
                    . $probeUrl,
                'probe_url' => $probeUrl,
                'external' => $external,
            ];
        }

        $response = self::webhook($pdo, [
            'method' => 'user_balance',
            'user_id' => $probeUserId,
        ]);
        $status = $response['status'] ?? null;
        if ($status === 1 || $status === true || $status === '1') {
            return [
                'ok' => true,
                'message' => 'Webhook OK (public + handler). Drakon panel Site URL: ' . $panelUrl . ' — webhook: ' . $probeUrl,
                'probe_url' => $probeUrl,
                'external' => $external,
            ];
        }

        return [
            'ok' => false,
            'message' => 'Webhook handler user_balance başarısız: '
                . (string) ($response['error'] ?? 'INVALID_RESPONSE')
                . '. Drakon panel Site URL: '
                . $panelUrl
                . ' — webhook: '
                . $probeUrl,
            'probe_url' => $probeUrl,
            'external' => $external,
        ];
    }

    public static function webhookPublicUrl(string $siteEndpoint = ''): string
    {
        $base = rtrim(trim($siteEndpoint !== '' ? $siteEndpoint : self::siteEndpoint()), '/');
        if ($base === '') {
            return '/drakon_api';
        }

        // Drakon agent panel = site kökü; Drakon {site}/drakon_api çağırır
        $base = preg_replace('#/drakon_api(?:/.*)?$#i', '', $base) ?? $base;
        $base = preg_replace('#/api/v2/drakon_callback(?:/.*)?$#i', '', $base) ?? $base;
        $base = rtrim($base, '/');

        return $base . '/drakon_api';
    }

    private static function normalizeApiBaseUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return self::DEFAULT_API_BASE;
        }
        $url = str_ireplace('gator.drakonapi.tech', 'gator.drakon.casino', $url);

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return self::DEFAULT_API_BASE;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) $parts['host']);
        if ($host === 'gator.drakonapi.tech' || $host === 'drakonapi.tech' || str_ends_with($host, '.drakonapi.tech')) {
            $host = 'gator.drakon.casino';
        }
        if ($host === 'gator.drakon.casino') {
            $scheme = 'https';
        }

        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $path = '/' . trim((string) ($parts['path'] ?? ''), '/');
        if ($path === '/') {
            $path = '/api/v1';
        } elseif (preg_match('#/api/v1(?:/|$)#', $path, $match, PREG_OFFSET_CAPTURE) === 1) {
            $path = substr($path, 0, (int) $match[0][1] + strlen('/api/v1'));
        }

        return rtrim($scheme . '://' . $host . $port . $path, '/');
    }

    private static function persistNormalizedApiBaseUrl(PDO $pdo, string $url): string
    {
        $normalized = self::normalizeApiBaseUrl($url);
        if (stripos($normalized, 'drakonapi.tech') !== false || stripos($normalized, 'gator.drakon.casino') === false) {
            $normalized = self::DEFAULT_API_BASE;
        }
        if (stripos($url, $normalized) === false || $url !== $normalized) {
            try {
                $stmt = $pdo->prepare('UPDATE drakon_config SET api_base_url = :api_base_url WHERE id = 1');
                $stmt->execute(['api_base_url' => $normalized]);
                $pdo->exec('DELETE FROM drakon_access_tokens');
            } catch (Throwable) {
            }
        }
        return $normalized;
    }

    private static function persistNormalizedSiteEndpoint(PDO $pdo, string $stored, string $normalized = ''): string
    {
        $stored = trim($stored);
        $normalized = trim($normalized !== '' ? $normalized : self::backendSiteEndpoint($stored));
        if ($normalized === '' || $normalized === $stored) {
            return $normalized;
        }
        try {
            $stmt = $pdo->prepare('UPDATE drakon_config SET site_endpoint = :site_endpoint WHERE id = 1');
            $stmt->execute(['site_endpoint' => $normalized]);
        } catch (Throwable) {
        }
        return $normalized;
    }

    private static function activeConfig(PDO $pdo): array
    {
        $config = self::configRow($pdo, false, false);
        foreach (['agent_code', 'agent_token', 'agent_secret'] as $key) {
            if (trim((string) ($config[$key] ?? '')) === '') {
                throw new RuntimeException('Drakon yapılandırması eksik: ' . $key);
            }
        }
        if ((int) ($config['is_active'] ?? 0) !== 1) {
            throw new RuntimeException('Drakon entegrasyonu pasif.');
        }
        return $config;
    }

    private static function ipAllowed(string $remoteIp, string $allowlist): bool
    {
        $remoteIp = trim($remoteIp);
        if ($remoteIp === '') {
            return false;
        }

        $items = preg_split('/[\s,]+/', $allowlist) ?: [];
        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            if ($item === $remoteIp) {
                return true;
            }
            if (substr($item, -2) === '.*') {
                $prefix = substr($item, 0, -1);
                if ($prefix !== '' && strpos($remoteIp, $prefix) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $server
     */
    private static function resolveClientIp(array $server): string
    {
        $candidates = [
            (string) ($server['HTTP_CF_CONNECTING_IP'] ?? ''),
            (string) ($server['HTTP_X_REAL_IP'] ?? ''),
        ];
        $forwarded = trim((string) ($server['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwarded !== '') {
            $parts = array_map('trim', explode(',', $forwarded));
            if ($parts !== [] && $parts[0] !== '') {
                $candidates[] = $parts[0];
            }
        }
        $candidates[] = (string) ($server['REMOTE_ADDR'] ?? '');

        foreach ($candidates as $ip) {
            $ip = trim($ip);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }

        return trim((string) ($server['REMOTE_ADDR'] ?? ''));
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

    private static function logWebhook(PDO $pdo, array $payload, array $response, int $durationMs): void
    {
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        @file_put_contents(
            $logDir . '/drakon_webhook.log',
            '[' . date('Y-m-d H:i:s') . '] ' . json_encode([
                'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
                'method' => (string) ($payload['method'] ?? ''),
                'user_id' => (string) ($payload['user_id'] ?? ''),
                'request' => self::redactSensitivePayload($payload),
                'response' => $response,
                'duration_ms' => $durationMs,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND
        );

        if (!self::shouldPersistWebhookLog($payload, $response)) {
            return;
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO drakon_webhook_logs
                    (method, user_id, transaction_id, request_payload, response_payload, http_status, error_code, duration_ms)
                 VALUES
                    (:method, :user_id, :transaction_id, :request_payload, :response_payload, :http_status, :error_code, :duration_ms)'
            );
            $stmt->execute([
                'method' => (string) ($payload['method'] ?? ''),
                'user_id' => isset($payload['user_id']) ? (int) $payload['user_id'] : null,
                'transaction_id' => (string) ($payload['transaction_id'] ?? ''),
                'request_payload' => json_encode(self::redactSensitivePayload($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'response_payload' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'http_status' => 200,
                'error_code' => (string) ($response['error'] ?? ''),
                'duration_ms' => $durationMs,
            ]);
        } catch (Throwable) {
        }
    }

    private static function shouldPersistWebhookLog(array $payload, array $response): bool
    {
        if (!empty($response['error']) || (isset($response['status']) && $response['status'] === false)) {
            return true;
        }

        $method = (string) ($payload['method'] ?? '');
        return in_array($method, ['transaction_bet', 'transaction_win', 'refund'], true);
    }

    private static function redactSensitivePayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            $lowerKey = strtolower((string) $key);
            if (in_array($lowerKey, ['agent_token', 'agent_secret', 'agent_secret_key', 'callback_secret', 'access_token', 'token'], true)) {
                $payload[$key] = '[redacted]';
                continue;
            }
            if (is_array($value)) {
                $payload[$key] = self::redactSensitivePayload($value);
                continue;
            }
            if (is_string($value)) {
                $payload[$key] = self::redactSensitiveString($value);
            }
        }
        return $payload;
    }

    private static function redactSensitiveString(string $value): string
    {
        return preg_replace('/([?&](?:agent_token|agent_secret|agent_secret_key|access_token|token)=)[^&\s]+/i', '$1[redacted]', $value) ?? $value;
    }

    /**
     * Drakon agent paneline yazılacak site kökü — her zaman ana backend host (api.* alt alanı değil).
     */
    private static function canonicalBackendSiteUrl(): string
    {
        if (defined('BACKEND_URL') && trim((string) BACKEND_URL) !== '') {
            return rtrim((string) BACKEND_URL, '/');
        }
        $envBackend = trim((string) (getenv('BACKEND_URL') ?: getenv('BACKEND_FALLBACK_URL') ?: ''));
        if ($envBackend !== '') {
            return rtrim($envBackend, '/');
        }
        if (function_exists('deploy_domain')) {
            $deploy = rtrim(trim((string) deploy_domain('backend_url')), '/');
            if ($deploy !== '') {
                return $deploy;
            }
        }

        return 'https://bo-nexthub.site';
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function launchSiteEndpoint(array $config): string
    {
        $configured = rtrim(trim((string) ($config['site_endpoint'] ?? '')), '/');
        if ($configured !== '') {
            return self::normalizeSiteEndpoint($configured);
        }

        return self::canonicalBackendSiteUrl();
    }

    public static function resolveLaunchGameId(PDO $pdo, string $gameId): string
    {
        $gameId = trim($gameId);
        if ($gameId === '' || str_starts_with($gameId, 'bgaming:')) {
            return $gameId;
        }
        try {
            $byCode = $pdo->prepare('SELECT game_id FROM drakon_games WHERE game_id = :game_id LIMIT 1');
            $byCode->execute(['game_id' => $gameId]);
            $found = $byCode->fetchColumn();
            if (is_string($found) && $found !== '') {
                return $found;
            }
            if (ctype_digit($gameId)) {
                $byInternal = $pdo->prepare('SELECT game_id FROM drakon_games WHERE id = :id LIMIT 1');
                $byInternal->execute(['id' => (int) $gameId]);
                $mapped = $byInternal->fetchColumn();
                if (is_string($mapped) && $mapped !== '') {
                    return $mapped;
                }
            }
        } catch (Throwable) {
        }

        return $gameId;
    }

    private static function isBackendApiSubdomainHost(string $host): bool
    {
        $host = strtolower(trim(preg_replace('/:\d+$/', '', $host) ?? ''));
        if ($host === '') {
            return false;
        }
        $apiHost = strtolower(trim((string) (
            getenv('API_SUBDOMAIN_HOST')
            ?: (function_exists('deploy_domain') ? deploy_domain('api_subdomain_host') : 'api.bo-nexthub.site')
        )));
        if ($apiHost !== '' && $host === $apiHost) {
            return true;
        }
        $backendHost = strtolower((string) (parse_url(self::canonicalBackendSiteUrl(), PHP_URL_HOST) ?: ''));
        if ($backendHost !== '' && $host !== $backendHost && str_starts_with($host, 'api.') && str_ends_with($host, '.' . $backendHost)) {
            return true;
        }

        return false;
    }

    private static function siteEndpoint(): string
    {
        $canonical = self::canonicalBackendSiteUrl();
        $requestHost = strtolower(trim(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? ''));
        if ($requestHost === '' || self::isBackendApiSubdomainHost($requestHost) || self::shouldRewriteCallbackHost($requestHost)) {
            return $canonical;
        }
        $scheme = function_exists('metropol_public_url_scheme')
            ? metropol_public_url_scheme('http')
            : (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
                ? 'https'
                : 'http');

        return rtrim($scheme . '://' . $requestHost, '/');
    }

    private static function backendSiteEndpoint(string $configuredEndpoint = ''): string
    {
        $configuredEndpoint = trim($configuredEndpoint);
        if ($configuredEndpoint !== '') {
            return self::normalizeSiteEndpoint($configuredEndpoint);
        }

        $envEndpoint = trim((string) (getenv('DRAKON_SITE_ENDPOINT') ?: ''));
        if ($envEndpoint !== '') {
            return self::normalizeSiteEndpoint($envEndpoint);
        }

        $publicBaseUrl = trim((string) (getenv('DRAKON_PUBLIC_BASE_URL') ?: ''));
        if ($publicBaseUrl !== '') {
            return self::normalizeSiteEndpoint(rtrim($publicBaseUrl, '/'));
        }

        return self::siteEndpoint();
    }

    private static function normalizeSiteEndpoint(string $endpoint): string
    {
        $endpoint = trim($endpoint);
        if ($endpoint === '') {
            return self::siteEndpoint();
        }

        $endpoint = rtrim($endpoint, '/');
        $parts = parse_url($endpoint);
        if (!is_array($parts) || empty($parts['host'])) {
            return self::siteEndpoint();
        }

        $endpointHost = strtolower((string) ($parts['host'] ?? ''));
        if (self::shouldRewriteCallbackHost($endpointHost) || self::isBackendApiSubdomainHost($endpointHost)) {
            return self::canonicalBackendSiteUrl();
        }

        $scheme = (string) ($parts['scheme'] ?? 'https');
        $host = (string) ($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $root = rtrim($scheme . '://' . $host . $port, '/');

        $backendHost = strtolower((string) (parse_url(self::canonicalBackendSiteUrl(), PHP_URL_HOST) ?: 'bo-nexthub.site'));
        $frontendHosts = array_filter(array_map(static function (string $value): string {
            $parsedHost = parse_url($value, PHP_URL_HOST);
            return strtolower(trim(is_string($parsedHost) ? $parsedHost : $value));
        }, [
            (string) (getenv('FRONTEND_URL') ?: ''),
            (string) (getenv('FRONTEND_FALLBACK_URL') ?: ''),
            'vegasroyalspin.com',
            'www.vegasroyalspin.com',
            'm.vegasroyalspin.com',
        ]));

        if ($backendHost !== '' && ($endpointHost === $backendHost || in_array($endpointHost, $frontendHosts, true))) {
            return self::canonicalBackendSiteUrl();
        }

        // Her zaman site kökü — path (/drakon_api, /api/v2/...) atılır
        return $root;
    }

    private static function adminCallbackPath(string $host): string
    {
        return '';
    }

    private static function shouldRewriteCallbackHost(string $host): bool
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return true;
        }

        $explicitEndpoint = trim((string) (getenv('DRAKON_SITE_ENDPOINT') ?: ''));
        if ($explicitEndpoint !== '') {
            $explicitHost = strtolower((string) (parse_url($explicitEndpoint, PHP_URL_HOST) ?: ''));
            if ($explicitHost !== '' && $host === $explicitHost) {
                return false;
            }
        }

        $backendHost = strtolower((string) (parse_url((string) (getenv('BACKEND_URL') ?: getenv('BACKEND_FALLBACK_URL') ?: 'https://bo-nexthub.site'), PHP_URL_HOST) ?: 'bo-nexthub.site'));
        if ($backendHost !== '' && $host === $backendHost) {
            return false;
        }

        foreach ([
            'serveousercontent.com',
            'ngrok-free.dev',
            'ngrok.io',
            'loca.lt',
            'trycloudflare.com',
            'cloudflare.com',
        ] as $tunnelHost) {
            if ($host === $tunnelHost || str_ends_with($host, '.' . $tunnelHost)) {
                return true;
            }
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        if (str_ends_with($host, '.test') || str_ends_with($host, '.local')) {
            return true;
        }

        if (function_exists('deploy_stale_url_hosts') && in_array($host, deploy_stale_url_hosts(), true)) {
            return true;
        }

        $frontendHosts = array_filter(array_map(static function (string $value): string {
            $parsedHost = parse_url($value, PHP_URL_HOST);
            return strtolower(trim(is_string($parsedHost) ? $parsedHost : $value));
        }, [
            (string) (getenv('FRONTEND_URL') ?: ''),
            (string) (getenv('FRONTEND_FALLBACK_URL') ?: ''),
            'vegasroyalspin.com',
            'www.vegasroyalspin.com',
            'm.vegasroyalspin.com',
        ]));
        if (in_array($host, $frontendHosts, true)) {
            return true;
        }

        return false;
    }

    private static function slug(string $value): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $value) ?: 'drakon'));
        return trim($slug, '-') ?: 'drakon';
    }

    /**
     * @return array{code: string, name: string}
     */
    private static function resolveProviderFromGame(array $game): array
    {
        $provider = is_array($game['provider'] ?? null) ? $game['provider'] : [];
        $code = trim((string) ($provider['code'] ?? $game['provider_code'] ?? ''));
        $name = trim((string) ($provider['name'] ?? $game['provider_game'] ?? ''));
        if ($name === '' && is_string($game['provider'] ?? null)) {
            $name = trim((string) $game['provider']);
        }
        if ($name === '' && $code !== '') {
            $name = $code;
        }
        if ($code === '' && $name !== '') {
            $code = preg_match('/^[a-z0-9_-]+$/i', $name) === 1 ? strtolower($name) : self::slug($name);
        }
        if ($code === '' && $name === '') {
            $name = 'Drakon';
            $code = 'drakon';
        }

        return ['code' => $code, 'name' => $name];
    }

    private static function demoUserId(): string
    {
        return 'demo';
    }

    private static function isDemoWebhookUserId(string $userIdRaw): bool
    {
        $userIdRaw = trim($userIdRaw);
        return $userIdRaw === '' || $userIdRaw === 'demo' || str_starts_with($userIdRaw, 'fun_');
    }

    private static function normalizeLaunchLang(string $lang): string
    {
        $lang = strtolower(trim($lang));
        if ($lang === '') {
            return 'tr';
        }
        if (str_contains($lang, '-')) {
            $lang = explode('-', $lang, 2)[0];
        }

        return strlen($lang) >= 2 ? substr($lang, 0, 2) : 'tr';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function demoWalletKey(array $payload): string
    {
        $session = trim((string) ($payload['session_id'] ?? ''));
        $user = trim((string) ($payload['user_id'] ?? 'demo'));
        $key = $session !== '' ? $session : $user;
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) ?? 'demo';

        return $safe !== '' ? $safe : 'demo';
    }

    private static function demoWalletDir(): string
    {
        $dir = dirname(__DIR__) . '/storage/cache/drakon_demo_wallets';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir;
    }

    /**
     * @return array{balance: float, tx: array<string, float>}
     */
    private static function readDemoWalletState(string $key): array
    {
        $path = self::demoWalletDir() . '/' . $key . '.json';
        if (!is_file($path)) {
            return ['balance' => 10000.0, 'tx' => []];
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return ['balance' => 10000.0, 'tx' => []];
        }

        return [
            'balance' => round((float) ($decoded['balance'] ?? 10000.0), 2),
            'tx' => is_array($decoded['tx'] ?? null) ? $decoded['tx'] : [],
        ];
    }

    private static function writeDemoWalletState(string $key, array $state): float
    {
        $balance = max(0, round((float) ($state['balance'] ?? 0), 2));
        $path = self::demoWalletDir() . '/' . $key . '.json';
        @file_put_contents($path, json_encode([
            'balance' => $balance,
            'tx' => is_array($state['tx'] ?? null) ? $state['tx'] : [],
            'updated_at' => time(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $balance;
    }

    private static function readDemoBalance(string $key): float
    {
        return self::readDemoWalletState($key)['balance'];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: bool|int, balance?: float, error?: string}
     */
    private static function applyDemoTransaction(array $payload, string $type, float $amount): array
    {
        $key = self::demoWalletKey($payload);
        $txId = trim((string) ($payload['transaction_id'] ?? ''));
        $state = self::readDemoWalletState($key);
        if ($txId !== '' && isset($state['tx'][$txId])) {
            return ['status' => true, 'balance' => round((float) $state['tx'][$txId], 2)];
        }

        $balance = (float) $state['balance'];
        if ($type === 'bet') {
            if ($amount > $balance) {
                return ['status' => false, 'error' => 'NO_BALANCE'];
            }
            $balance -= $amount;
        } else {
            $balance += $amount;
        }

        $balance = self::writeDemoWalletState($key, [
            'balance' => $balance,
            'tx' => $txId !== ''
                ? $state['tx'] + [$txId => $balance]
                : $state['tx'],
        ]);

        return ['status' => true, 'balance' => $balance];
    }

    private static function inferGameType(string $provider): string
    {
        return self::isLiveProviderName($provider) ? 'live' : 'casino';
    }

    /**
     * Slot lobby category tabs are UI categories, while Drakon only stores a flat catalog.
     * Keep these filters conservative and based on stable catalog text fields.
     *
     * @param array<int, string> $where
     * @param array<string, int|string> $params
     */
    private static function applyGameCategoryFilter(string $sort, array &$where, array &$params, string &$orderSql): void
    {
        switch ($sort) {
            case '':
            case 'all':
            case 'slots':
            case 'video':
                return;

            case 'liked':
            case 'popular':
                $orderSql = 'is_featured DESC, game_name ASC';
                return;

            case 'new':
                $orderSql = 'id DESC, game_name ASC';
                return;

            case 'jackpots':
                $where[] = self::textMatchSql(['jackpot', 'grand', 'mega', 'fortune', 'coins'], 'jackpot');
                self::bindTextMatchParams($params, ['jackpot', 'grand', 'mega', 'fortune', 'coins'], 'jackpot');
                return;

            case 'bonus-buy':
            case 'freespin':
                $where[] = self::textMatchSql(['bonus', 'buy', 'feature', 'free spin', 'freespin'], 'bonus');
                self::bindTextMatchParams($params, ['bonus', 'buy', 'feature', 'free spin', 'freespin'], 'bonus');
                return;

            case 'crash':
                $where[] = self::textMatchSql(['crash', 'aviator', 'plane', 'rocket', 'jet', 'spaceman'], 'crash');
                self::bindTextMatchParams($params, ['crash', 'aviator', 'plane', 'rocket', 'jet', 'spaceman'], 'crash');
                return;

            case 'instant':
                $where[] = self::textMatchSql(['instant', 'scratch', 'mines', 'plinko', 'dice', 'keno'], 'instant');
                self::bindTextMatchParams($params, ['instant', 'scratch', 'mines', 'plinko', 'dice', 'keno'], 'instant');
                return;

            case 'table':
                $where[] = self::textMatchSql(['roulette', 'blackjack', 'baccarat', 'poker', 'sic bo', 'andar bahar', 'dragon tiger'], 'table');
                self::bindTextMatchParams($params, ['roulette', 'blackjack', 'baccarat', 'poker', 'sic bo', 'andar bahar', 'dragon tiger'], 'table');
                return;

            case 'special':
                $where[] = self::textMatchSql(['christmas', 'xmas', 'new year', 'halloween', 'easter', 'special'], 'special');
                self::bindTextMatchParams($params, ['christmas', 'xmas', 'new year', 'halloween', 'easter', 'special'], 'special');
                return;
        }
    }

    /**
     * @param array<int, string> $needles
     */
    private static function textMatchSql(array $needles, string $prefix): string
    {
        $parts = [];
        $fields = ['game_name', 'provider_name', 'provider_code'];
        foreach (array_values($needles) as $idx => $_needle) {
            $fieldParts = [];
            foreach ($fields as $fieldIdx => $field) {
                $param = ':' . $prefix . '_match_' . $idx . '_' . $fieldIdx;
                $fieldParts[] = "$field LIKE $param";
            }
            $parts[] = '(' . implode(' OR ', $fieldParts) . ')';
        }

        return '(' . implode(' OR ', $parts) . ')';
    }

    /**
     * @param array<string, int|string> $params
     * @param array<int, string> $needles
     */
    private static function bindTextMatchParams(array &$params, array $needles, string $prefix): void
    {
        $fieldCount = 3;
        foreach (array_values($needles) as $idx => $needle) {
            for ($fieldIdx = 0; $fieldIdx < $fieldCount; $fieldIdx++) {
                $params[$prefix . '_match_' . $idx . '_' . $fieldIdx] = '%' . $needle . '%';
            }
        }
    }

    private static function isLiveProviderName(string $provider): bool
    {
        $normalized = strtolower(trim($provider));
        if (in_array($normalized, ['7mojos', 'iconic21', 'pragmatic-bj', 'pragmatic-bj2', 'pragmatic-virtual'], true)) {
            return true;
        }

        return preg_match('/' . self::liveProviderPattern() . '/i', $provider) === 1;
    }

    private static function liveProviderSqlCondition(string $column): string
    {
        return "(LOWER($column) IN ('7mojos', 'iconic21', 'pragmatic-bj', 'pragmatic-bj2', 'pragmatic-virtual') OR LOWER($column) REGEXP '" . self::liveProviderPattern() . "')";
    }

    private static function liveProviderPattern(): string
    {
        return 'evolution|ezugi|vivo|sagaming|pragmatic[[:space:]_-]*live|live[[:space:]_-]*casino|casino[[:space:]_-]*live|(^|[^a-z])live([^a-z]|$)|tvbet|betgames|creedroomz|creed[[:space:]_-]*roomz|^creedz([[:space:]_-]|$)|xpg|vivogaming|goldsvet|luckystreak|onair|yeebet|playtech[[:space:]_-]*live|asiagaming|asia[[:space:]_-]*gaming';
    }

    private static function isExcludedDirectProvider(string $provider): bool
    {
        return preg_match('/' . self::excludedDirectProviderPattern() . '/i', $provider) === 1;
    }

    private static function excludedDirectProviderSqlCondition(string $column): string
    {
        return "LOWER($column) REGEXP '" . self::excludedDirectProviderPattern() . "'";
    }

    private static function excludedDirectProviderPattern(): string
    {
        return 'b[[:space:]_-]*gaming|bgaming';
    }

    private static function campaignCodeFromPath(string $path): ?string
    {
        if (preg_match('#/campaigns/([^/]+)#', $path, $m) === 1) {
            return (string) $m[1];
        }
        return null;
    }

    private static function listFromResponse(array $response, string $key): array
    {
        if (isset($response[$key]) && is_array($response[$key])) {
            return array_values(array_filter($response[$key], 'is_array'));
        }
        if (isset($response['data']) && is_array($response['data'])) {
            if (isset($response['data'][$key]) && is_array($response['data'][$key])) {
                return array_values(array_filter($response['data'][$key], 'is_array'));
            }
            if (self::isList($response['data'])) {
                return array_values(array_filter($response['data'], 'is_array'));
            }
        }
        if (self::isList($response)) {
            return array_values(array_filter($response, 'is_array'));
        }
        return [];
    }

    private static function isList(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }
}
