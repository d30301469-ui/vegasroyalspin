<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $envDefault = static function (string $key, string $default): string {
        $value = getenv($key);
        if ($value === false || trim((string) $value) === '') {
            return $default;
        }

        return trim((string) $value);
    };
    $apiBase = str_replace("'", "''", $envDefault('CASINO_AGGREGATOR_API_BASE_URL', ''));

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS casino_aggregator_config (
            id                   TINYINT UNSIGNED NOT NULL DEFAULT 1,
            agent_code           VARCHAR(100) NOT NULL DEFAULT '',
            api_token            VARCHAR(255) NOT NULL DEFAULT '',
            api_base_url         VARCHAR(255) NOT NULL DEFAULT '{$apiBase}',
            site_endpoint        VARCHAR(255) NOT NULL DEFAULT '',
            api_mode             ENUM('seamless','transfer') NOT NULL DEFAULT 'seamless',
            sign_private_key     VARCHAR(255) NOT NULL DEFAULT '',
            verify_public_key    VARCHAR(255) NOT NULL DEFAULT '',
            currency             VARCHAR(8) NOT NULL DEFAULT 'TRY',
            lang                 VARCHAR(8) NOT NULL DEFAULT 'tr',
            callback_allowed_ips TEXT NULL,
            is_active            TINYINT(1) NOT NULL DEFAULT 0,
            vendors_synced_at    DATETIME NULL,
            games_synced_at      DATETIME NULL,
            created_at           TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at           TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS casino_aggregator_vendors (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            vendor_code   VARCHAR(120) NOT NULL,
            vendor_name   VARCHAR(255) NOT NULL DEFAULT '',
            game_type     TINYINT UNSIGNED NOT NULL DEFAULT 1,
            is_active     TINYINT(1) NOT NULL DEFAULT 1,
            synced_at     DATETIME NULL,
            created_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_casino_agg_vendor_code (vendor_code),
            KEY idx_casino_agg_vendors_active (is_active),
            KEY idx_casino_agg_vendors_type (game_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS casino_aggregator_games (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            vendor_code   VARCHAR(120) NOT NULL,
            game_code     VARCHAR(120) NOT NULL,
            game_name     VARCHAR(255) NOT NULL DEFAULT '',
            game_type     TINYINT UNSIGNED NOT NULL DEFAULT 1,
            image_url     VARCHAR(700) NULL,
            raw_payload   JSON NULL,
            is_active     TINYINT(1) NOT NULL DEFAULT 1,
            is_featured   TINYINT(1) NOT NULL DEFAULT 0,
            synced_at     DATETIME NULL,
            created_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_casino_agg_game (vendor_code, game_code),
            KEY idx_casino_agg_games_vendor (vendor_code),
            KEY idx_casino_agg_games_active (is_active),
            KEY idx_casino_agg_games_type (game_type),
            KEY idx_casino_agg_games_name (game_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS casino_aggregator_sessions (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id          INT UNSIGNED NULL,
            username         VARCHAR(100) NULL,
            user_code        VARCHAR(120) NOT NULL,
            vendor_code      VARCHAR(120) NOT NULL,
            game_code        VARCHAR(120) NOT NULL,
            currency         VARCHAR(8) NOT NULL DEFAULT 'TRY',
            lang             VARCHAR(8) NOT NULL DEFAULT 'tr',
            channel          VARCHAR(20) NOT NULL DEFAULT 'desktop',
            launch_url       TEXT NULL,
            request_payload  JSON NULL,
            response_payload JSON NULL,
            created_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_casino_agg_sess_user (user_id),
            KEY idx_casino_agg_sess_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS casino_aggregator_transactions (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id        INT UNSIGNED NOT NULL,
            username       VARCHAR(100) NULL,
            txn_code       VARCHAR(200) NOT NULL,
            pair_code      VARCHAR(200) NULL,
            wager_id       VARCHAR(200) NULL,
            round_id       VARCHAR(200) NULL,
            vendor_code    VARCHAR(120) NULL,
            game_code      VARCHAR(120) NULL,
            txn_type       ENUM('bet','win','cancel') NOT NULL,
            amount         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            before_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            after_balance  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            currency       VARCHAR(8) NOT NULL DEFAULT 'TRY',
            is_free_round  TINYINT(1) NOT NULL DEFAULT 0,
            is_finished    TINYINT(1) NOT NULL DEFAULT 0,
            detail         TEXT NULL,
            raw_payload    JSON NULL,
            created_at     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_casino_agg_txn_code (txn_code),
            KEY idx_casino_agg_tx_user (user_id),
            KEY idx_casino_agg_tx_wager (wager_id),
            KEY idx_casino_agg_tx_round (round_id),
            KEY idx_casino_agg_tx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS casino_aggregator_wallet_logs (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            method           VARCHAR(50) NULL,
            user_id          INT UNSIGNED NULL,
            txn_code         VARCHAR(200) NULL,
            http_status      SMALLINT NOT NULL DEFAULT 200,
            status_code      SMALLINT NULL,
            error_code       VARCHAR(50) NULL,
            duration_ms      SMALLINT UNSIGNED NULL,
            request_payload  JSON NULL,
            response_payload JSON NULL,
            created_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_casino_agg_wlog_method (method),
            KEY idx_casino_agg_wlog_user (user_id),
            KEY idx_casino_agg_wlog_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "INSERT IGNORE INTO casino_aggregator_config
            (id, agent_code, api_token, api_base_url, site_endpoint, api_mode, currency, lang, is_active)
         VALUES (1, '', '', '{$apiBase}', '', 'seamless', 'TRY', 'tr', 0)"
    );
};
