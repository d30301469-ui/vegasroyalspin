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
    $sqlDefault = static fn (string $value): string => str_replace("'", "''", $value);
    $megapayzApiBase = $sqlDefault($envDefault('MEGAPAYZ_API_BASE_URL', 'https://api.megapayz.net'));
    $bgamingApiBase = $sqlDefault($envDefault('BGAMING_API_BASE_URL', 'https://int.bgaming-system.com'));
    $drakonApiBase = $sqlDefault($envDefault('DRAKON_API_BASE_URL', 'https://gator.drakon.casino/api/v1'));

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS megapayz_config (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(64) NOT NULL DEFAULT 'default',
            sid VARCHAR(128) NOT NULL,
            private_key VARCHAR(255) NOT NULL,
            api_base_url VARCHAR(255) NOT NULL DEFAULT '{$megapayzApiBase}',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_megapayz_config_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS megapayz_methods (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            method_key VARCHAR(64) NOT NULL,
            name VARCHAR(120) NOT NULL,
            type VARCHAR(64) NOT NULL,
            currency CHAR(3) NOT NULL DEFAULT 'TRY',
            deposit_enabled TINYINT(1) NOT NULL DEFAULT 0,
            withdraw_enabled TINYINT(1) NOT NULL DEFAULT 0,
            min_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            max_amount DECIMAL(18,2) NOT NULL DEFAULT 1000000.00,
            logo_url VARCHAR(700) NULL,
            input_fields LONGTEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_megapayz_method_key (method_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS megapayz_transactions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            type ENUM('deposit','withdraw') NOT NULL,
            user_id INT NOT NULL,
            username VARCHAR(120) NOT NULL,
            fullname VARCHAR(255) NOT NULL,
            method VARCHAR(64) NOT NULL,
            trx VARCHAR(64) NOT NULL,
            megapayz_transaction_id VARCHAR(120) NULL,
            amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            fee DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            currency CHAR(3) NOT NULL DEFAULT 'TRY',
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            input_fields LONGTEXT NULL,
            request_payload LONGTEXT NULL,
            response_payload LONGTEXT NULL,
            callback_payload LONGTEXT NULL,
            failure_message VARCHAR(700) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            finalized_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_megapayz_trx (trx),
            KEY idx_megapayz_user_type (user_id, type, id),
            KEY idx_megapayz_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS megapayz_callbacks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            type ENUM('deposit','withdraw') NOT NULL,
            trx VARCHAR(64) NOT NULL,
            megapayz_transaction_id VARCHAR(120) NULL,
            hash_valid TINYINT(1) NOT NULL DEFAULT 0,
            processed TINYINT(1) NOT NULL DEFAULT 0,
            payload LONGTEXT NOT NULL,
            message VARCHAR(700) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_megapayz_callback_trx (trx),
            KEY idx_megapayz_callback_tx (megapayz_transaction_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS bgaming_config (
            id TINYINT UNSIGNED NOT NULL DEFAULT 1,
            server_id VARCHAR(100) NOT NULL DEFAULT '',
            casino_id VARCHAR(100) NOT NULL DEFAULT '',
            api_base_url VARCHAR(255) NOT NULL DEFAULT '{$bgamingApiBase}',
            wallet_secret VARCHAR(255) NOT NULL DEFAULT '',
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
        "CREATE TABLE IF NOT EXISTS drakon_config (
            id TINYINT UNSIGNED NOT NULL DEFAULT 1,
            agent_code VARCHAR(100) NOT NULL DEFAULT '',
            agent_token VARCHAR(255) NOT NULL DEFAULT '',
            agent_secret VARCHAR(255) NOT NULL DEFAULT '',
            currency CHAR(3) NOT NULL DEFAULT 'TRY',
            api_base_url VARCHAR(255) NOT NULL DEFAULT '{$drakonApiBase}',
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
};
