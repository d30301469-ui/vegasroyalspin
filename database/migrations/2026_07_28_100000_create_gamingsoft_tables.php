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

    $apiBase = str_replace("'", "''", $envDefault('GAMINGSOFT_API_BASE_URL', 'https://staging.gsimw.com'));
    $operator = str_replace("'", "''", $envDefault('GAMINGSOFT_OPERATOR_CODE', 'VGY1'));
    $secret = str_replace("'", "''", $envDefault('GAMINGSOFT_SECRET_KEY', 'zS5CzH7U224nMVgMaghYsY'));
    $site = str_replace("'", "''", $envDefault('GAMINGSOFT_SITE_ENDPOINT', 'https://admin.vegasroyalspin.com'));
    $currency = str_replace("'", "''", $envDefault('GAMINGSOFT_CURRENCY', 'IDR'));

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS gamingsoft_config (
            id                   TINYINT UNSIGNED NOT NULL DEFAULT 1,
            operator_code        VARCHAR(32) NOT NULL DEFAULT '',
            secret_key           VARCHAR(255) NOT NULL DEFAULT '',
            api_base_url         VARCHAR(255) NOT NULL DEFAULT '{$apiBase}',
            site_endpoint        VARCHAR(255) NOT NULL DEFAULT '',
            currency             VARCHAR(16) NOT NULL DEFAULT 'IDR',
            language_code        INT NOT NULL DEFAULT 0,
            channel_code         VARCHAR(32) NOT NULL DEFAULT 'gscp',
            callback_allowed_ips TEXT NULL,
            is_active            TINYINT(1) NOT NULL DEFAULT 0,
            products_synced_at   DATETIME NULL,
            games_synced_at      DATETIME NULL,
            created_at           TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at           TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS gamingsoft_products (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_code  INT NOT NULL,
            product_id    INT NULL,
            provider_id   INT NULL,
            provider      VARCHAR(120) NOT NULL DEFAULT '',
            product_name  VARCHAR(255) NOT NULL DEFAULT '',
            game_type     VARCHAR(64) NOT NULL DEFAULT '',
            currency      VARCHAR(16) NOT NULL DEFAULT '',
            status        VARCHAR(32) NOT NULL DEFAULT '',
            entry_type    TINYINT UNSIGNED NOT NULL DEFAULT 1,
            is_active     TINYINT(1) NOT NULL DEFAULT 1,
            synced_at     DATETIME NULL,
            created_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_gs_product_code (product_code),
            KEY idx_gs_products_active (is_active),
            KEY idx_gs_products_type (game_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS gamingsoft_games (
            id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_code      INT NOT NULL,
            game_code         VARCHAR(120) NOT NULL,
            game_name         VARCHAR(255) NOT NULL DEFAULT '',
            game_type         VARCHAR(64) NOT NULL DEFAULT '',
            image_url         TEXT NULL,
            support_currency  VARCHAR(16) NOT NULL DEFAULT '',
            status            VARCHAR(32) NOT NULL DEFAULT '',
            allow_free_round  TINYINT(1) NOT NULL DEFAULT 0,
            entry_type        TINYINT UNSIGNED NOT NULL DEFAULT 1,
            raw_payload       JSON NULL,
            is_active         TINYINT(1) NOT NULL DEFAULT 1,
            is_featured       TINYINT(1) NOT NULL DEFAULT 0,
            synced_at         DATETIME NULL,
            created_at        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_gs_game (product_code, game_code),
            KEY idx_gs_games_product (product_code),
            KEY idx_gs_games_active (is_active),
            KEY idx_gs_games_type (game_type),
            KEY idx_gs_games_name (game_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS gamingsoft_sessions (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id          INT UNSIGNED NULL,
            username         VARCHAR(100) NULL,
            member_account   VARCHAR(50) NOT NULL,
            product_code     INT NOT NULL,
            game_code        VARCHAR(120) NULL,
            game_type        VARCHAR(64) NOT NULL DEFAULT '',
            currency         VARCHAR(16) NOT NULL DEFAULT 'TRY',
            platform         VARCHAR(20) NOT NULL DEFAULT 'WEB',
            launch_url       TEXT NULL,
            request_payload  JSON NULL,
            response_payload JSON NULL,
            created_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_gs_sess_user (user_id),
            KEY idx_gs_sess_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS gamingsoft_transactions (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id          INT UNSIGNED NOT NULL,
            username         VARCHAR(100) NULL,
            member_account   VARCHAR(50) NOT NULL,
            txn_id           VARCHAR(200) NOT NULL,
            wager_code       VARCHAR(200) NULL,
            round_id         VARCHAR(200) NULL,
            product_code     INT NULL,
            game_code        VARCHAR(120) NULL,
            game_type        VARCHAR(64) NULL,
            action           VARCHAR(40) NOT NULL DEFAULT '',
            wager_status     VARCHAR(40) NULL,
            wager_type       VARCHAR(40) NULL,
            amount           DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
            bet_amount       DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
            valid_bet_amount DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
            prize_amount     DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
            tip_amount       DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
            before_balance   DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
            after_balance    DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
            currency         VARCHAR(16) NOT NULL DEFAULT 'TRY',
            endpoint         VARCHAR(40) NOT NULL DEFAULT '',
            settled_at       BIGINT NULL,
            raw_payload      JSON NULL,
            created_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_gs_txn_id (txn_id),
            KEY idx_gs_txn_user (user_id),
            KEY idx_gs_txn_wager (wager_code),
            KEY idx_gs_txn_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS gamingsoft_wagers (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id          INT UNSIGNED NULL,
            member_account   VARCHAR(50) NOT NULL,
            wager_code       VARCHAR(200) NOT NULL,
            round_id         VARCHAR(200) NULL,
            product_code     INT NULL,
            game_code        VARCHAR(120) NULL,
            game_type        VARCHAR(64) NULL,
            wager_type       VARCHAR(40) NULL,
            wager_status     VARCHAR(40) NULL,
            bet_amount       DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
            valid_bet_amount DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
            prize_amount     DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
            tip_amount       DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
            currency         VARCHAR(16) NOT NULL DEFAULT 'TRY',
            channel_code     VARCHAR(32) NULL,
            settled_at       BIGINT NULL,
            created_at_ms    BIGINT NULL,
            payload          JSON NULL,
            raw_payload      JSON NULL,
            updated_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_gs_wager_code (wager_code),
            KEY idx_gs_wager_user (user_id),
            KEY idx_gs_wager_member (member_account)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS gamingsoft_wallet_logs (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            method           VARCHAR(40) NULL,
            user_id          INT UNSIGNED NULL,
            txn_code         VARCHAR(200) NULL,
            http_status      SMALLINT NOT NULL DEFAULT 200,
            status_code      INT NULL,
            error_code       VARCHAR(120) NULL,
            duration_ms      INT NOT NULL DEFAULT 0,
            request_payload  JSON NULL,
            response_payload JSON NULL,
            created_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_gs_wallet_logs_created (created_at),
            KEY idx_gs_wallet_logs_method (method)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "INSERT IGNORE INTO gamingsoft_config
            (id, operator_code, secret_key, api_base_url, site_endpoint, currency, language_code, channel_code, is_active)
         VALUES (1, '{$operator}', '{$secret}', '{$apiBase}', '{$site}', '{$currency}', 0, 'gscp', 1)"
    );
};
