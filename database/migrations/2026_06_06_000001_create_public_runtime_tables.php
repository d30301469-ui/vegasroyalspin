<?php

declare(strict_types=1);


return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS visitor_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ip VARCHAR(64) NULL,
            ip_address VARCHAR(64) NULL,
            country_code VARCHAR(8) NULL,
            country_name VARCHAR(100) NULL,
            region VARCHAR(120) NULL,
            city VARCHAR(120) NULL,
            lat DECIMAL(10,7) NULL,
            lon DECIMAL(10,7) NULL,
            user_agent VARCHAR(500) NULL,
            referer VARCHAR(500) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_visitor_logs_created_at (created_at),
            KEY idx_visitor_logs_country (country_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_balance_adjustments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            username VARCHAR(50) NOT NULL,
            admin_id INT NULL,
            admin_username VARCHAR(100) NULL,
            wallet ENUM('balance','bonus_balance') NOT NULL DEFAULT 'balance',
            action ENUM('add','subtract') NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            before_balance DECIMAL(12,2) NOT NULL,
            after_balance DECIMAL(12,2) NOT NULL,
            note VARCHAR(500) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_balance_adjustments_user (user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
