<?php

declare(strict_types=1);


return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admins (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            username VARCHAR(100) NOT NULL,
            email VARCHAR(190) NOT NULL,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(40) NOT NULL DEFAULT 'admin',
            twofa_enabled TINYINT(1) NOT NULL DEFAULT 0,
            twofa_secret VARCHAR(255) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_admins_email (email),
            UNIQUE KEY uniq_admins_username (username),
            KEY idx_admins_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_sessions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(128) NOT NULL,
            admin_id INT UNSIGNED NOT NULL,
            username VARCHAR(100) NOT NULL,
            email VARCHAR(190) NULL,
            role VARCHAR(40) NOT NULL DEFAULT 'admin',
            ip_address VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
            last_activity DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
            expired_at DATETIME NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_2fa_verified TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_admin_sessions_session (session_id),
            KEY idx_admin_sessions_admin (admin_id, is_active),
            KEY idx_admin_sessions_activity (last_activity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            admin_id INT UNSIGNED NULL,
            admin_username VARCHAR(100) NULL,
            action VARCHAR(120) NOT NULL,
            entity_type VARCHAR(80) NULL,
            entity_id VARCHAR(80) NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'success',
            ip_address VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            payload LONGTEXT NULL,
            created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_logs_created (created_at),
            KEY idx_admin_logs_admin (admin_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
