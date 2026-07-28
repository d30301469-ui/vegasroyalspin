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

    $operatorUrl = str_replace("'", "''", $envDefault('GSC_OPERATOR_URL', 'https://staging.gsimw.com'));
    // The GSC+ agent wallet is contracted per currency; the staging agent (VGY1)
    // is funded in IDR, so IDR — not the site display currency — is the default.
    $currency = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $envDefault('GSC_CURRENCY', 'IDR')) ?? 'IDR');
    if ($currency === '') {
        $currency = 'IDR';
    }

    $columnExists = static function (PDO $pdo, string $table, string $column): bool {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE :col");
            $stmt->execute([':col' => $column]);

            return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (Throwable) {
            return false;
        }
    };

    $indexExists = static function (PDO $pdo, string $table, string $index): bool {
        try {
            $stmt = $pdo->prepare("SHOW INDEX FROM `{$table}` WHERE Key_name = :idx");
            $stmt->execute([':idx' => $index]);

            return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (Throwable) {
            return false;
        }
    };

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS gsc_config (
            id                   TINYINT UNSIGNED NOT NULL DEFAULT 1,
            operator_code        VARCHAR(32) NOT NULL DEFAULT '',
            secret_key           VARCHAR(255) NOT NULL DEFAULT '',
            operator_url         VARCHAR(255) NOT NULL DEFAULT '{$operatorUrl}',
            currency             VARCHAR(16) NOT NULL DEFAULT '{$currency}',
            language_code        INT NOT NULL DEFAULT 0,
            channel_code         VARCHAR(32) NOT NULL DEFAULT 'gscp',
            operator_lobby_url   VARCHAR(255) NOT NULL DEFAULT '',
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
        "CREATE TABLE IF NOT EXISTS gsc_products (
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
            raw_payload   JSON NULL,
            synced_at     DATETIME NULL,
            created_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_gsc_product (product_code, currency, game_type),
            KEY idx_gsc_products_active (is_active),
            KEY idx_gsc_products_type (game_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // available-products lists one row per (product_code, currency, game_type):
    // e.g. 1006/IDR appears as both LIVE_CASINO and LIVE_CASINO_PREMIUM. The
    // original (product_code, currency) key collapsed them into a single row and
    // silently dropped one of the two game lists.
    if (!$indexExists($pdo, 'gsc_products', 'uniq_gsc_product')) {
        try {
            if ($indexExists($pdo, 'gsc_products', 'uniq_gsc_product_code_currency')) {
                $pdo->exec('ALTER TABLE gsc_products DROP INDEX uniq_gsc_product_code_currency');
            }
            $pdo->exec('ALTER TABLE gsc_products ADD UNIQUE KEY uniq_gsc_product (product_code, currency, game_type)');
        } catch (Throwable) {
        }
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS gsc_games (
            id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_code      INT NOT NULL,
            game_code         VARCHAR(120) NOT NULL,
            game_name         VARCHAR(255) NOT NULL DEFAULT '',
            game_type         VARCHAR(64) NOT NULL DEFAULT '',
            image_url         TEXT NULL,
            support_currency  VARCHAR(64) NOT NULL DEFAULT '',
            product_currency  VARCHAR(16) NOT NULL DEFAULT '',
            status            VARCHAR(32) NOT NULL DEFAULT '',
            allow_free_round  TINYINT(1) NOT NULL DEFAULT 0,
            entry_type        TINYINT UNSIGNED NOT NULL DEFAULT 1,
            provider          VARCHAR(120) NOT NULL DEFAULT '',
            product_name      VARCHAR(255) NOT NULL DEFAULT '',
            lang_name         JSON NULL,
            lang_icon         JSON NULL,
            provider_created_at BIGINT NULL,
            raw_payload       JSON NULL,
            is_active         TINYINT(1) NOT NULL DEFAULT 1,
            is_featured       TINYINT(1) NOT NULL DEFAULT 0,
            synced_at         DATETIME NULL,
            created_at        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_gsc_game (product_code, game_code, support_currency),
            KEY idx_gsc_games_product (product_code),
            KEY idx_gsc_games_active (is_active),
            KEY idx_gsc_games_type (game_type),
            KEY idx_gsc_games_name (game_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Games report support_currency as a list ("IDR,PHP,MYR") or "ALL", which does
    // not fit the original VARCHAR(16). product_currency holds the contracted
    // currency of the parent product — the value launch-game must send.
    if (!$columnExists($pdo, 'gsc_games', 'product_currency')) {
        try {
            $pdo->exec("ALTER TABLE gsc_games MODIFY support_currency VARCHAR(64) NOT NULL DEFAULT ''");
            $pdo->exec("ALTER TABLE gsc_games ADD COLUMN product_currency VARCHAR(16) NOT NULL DEFAULT '' AFTER support_currency");
        } catch (Throwable) {
        }
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS gsc_sessions (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id          INT UNSIGNED NULL,
            member_account   VARCHAR(50) NOT NULL,
            product_code     INT NOT NULL,
            game_code        VARCHAR(120) NULL,
            game_type        VARCHAR(64) NOT NULL DEFAULT '',
            currency         VARCHAR(16) NOT NULL DEFAULT '{$currency}',
            platform         VARCHAR(20) NOT NULL DEFAULT 'WEB',
            launch_url       TEXT NULL,
            request_payload  JSON NULL,
            response_payload JSON NULL,
            created_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_gsc_sess_user (user_id),
            KEY idx_gsc_sess_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS gsc_transactions (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id          INT UNSIGNED NOT NULL,
            member_account   VARCHAR(50) NOT NULL,
            transaction_id   VARCHAR(200) NOT NULL,
            action           VARCHAR(40) NOT NULL DEFAULT '',
            wager_code       VARCHAR(200) NULL,
            wager_status     VARCHAR(40) NULL,
            wager_type       VARCHAR(40) NULL,
            round_id         VARCHAR(200) NULL,
            product_code     INT NULL,
            game_code        VARCHAR(120) NULL,
            game_type        VARCHAR(64) NULL,
            channel_code     VARCHAR(32) NULL,
            amount           DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
            bet_amount       DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
            valid_bet_amount DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
            prize_amount     DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
            tip_amount       DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
            before_balance   DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
            after_balance    DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
            currency         VARCHAR(16) NOT NULL DEFAULT '{$currency}',
            settled_at       BIGINT NULL,
            direction        ENUM('withdraw','deposit','push') NOT NULL DEFAULT 'withdraw',
            payload          JSON NULL,
            raw_payload      JSON NULL,
            created_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_gsc_txn_id (transaction_id),
            KEY idx_gsc_tx_user (user_id),
            KEY idx_gsc_tx_wager (wager_code),
            KEY idx_gsc_tx_round (round_id),
            KEY idx_gsc_tx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS gsc_wagers (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            member_account   VARCHAR(50) NOT NULL,
            wager_code       VARCHAR(200) NOT NULL,
            wager_status     VARCHAR(40) NULL,
            wager_type       VARCHAR(40) NULL,
            round_id         VARCHAR(200) NULL,
            product_code     INT NULL,
            game_code        VARCHAR(120) NULL,
            game_type        VARCHAR(64) NULL,
            channel_code     VARCHAR(32) NULL,
            currency         VARCHAR(16) NULL,
            bet_amount       DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
            valid_bet_amount DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
            prize_amount     DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
            tip_amount       DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
            settled_at       BIGINT NULL,
            wager_created_at BIGINT NULL,
            payload          JSON NULL,
            raw_payload      JSON NULL,
            created_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_gsc_wager_code (wager_code),
            KEY idx_gsc_wager_member (member_account),
            KEY idx_gsc_wager_status (wager_status),
            KEY idx_gsc_wager_settled (settled_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS gsc_wallet_logs (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            method           VARCHAR(50) NULL,
            user_id          INT UNSIGNED NULL,
            member_account   VARCHAR(50) NULL,
            transaction_id   VARCHAR(200) NULL,
            http_status      SMALLINT NOT NULL DEFAULT 200,
            status_code      INT NULL,
            error_code       VARCHAR(50) NULL,
            duration_ms      SMALLINT UNSIGNED NULL,
            request_payload  JSON NULL,
            response_payload JSON NULL,
            created_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_gsc_wlog_method (method),
            KEY idx_gsc_wlog_user (user_id),
            KEY idx_gsc_wlog_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "INSERT IGNORE INTO gsc_config
            (id, operator_code, secret_key, operator_url, currency, language_code, channel_code, is_active)
         VALUES (1, '', '', '{$operatorUrl}', '{$currency}', 0, 'gscp', 0)"
    );

    // TRY was never a contracted GSC+ currency for this agent; it only ever came
    // from the old hardcoded default and makes launch-game and the wallet
    // callbacks disagree with the agent wallet.
    $pdo->exec("UPDATE gsc_config SET currency = '{$currency}' WHERE id = 1 AND currency = 'TRY'");
};
