<?php

declare(strict_types=1);

return static function (\PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS loyalty_levels (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(40) NOT NULL,
            name VARCHAR(120) NOT NULL,
            min_points INT NOT NULL DEFAULT 0,
            cashback_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            weekly_bonus_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            icon_url VARCHAR(500) NULL,
            color_hex VARCHAR(20) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_loyalty_levels_code (code),
            KEY idx_loyalty_levels_active_sort (is_active, sort_order),
            KEY idx_loyalty_levels_points (min_points)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS user_loyalty_accounts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            username VARCHAR(120) NULL,
            level_code VARCHAR(40) NOT NULL DEFAULT 'bronze',
            points INT NOT NULL DEFAULT 0,
            lifetime_points INT NOT NULL DEFAULT 0,
            redeemable_points INT NOT NULL DEFAULT 0,
            last_activity_at DATETIME NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_user_loyalty_accounts_user (user_id),
            KEY idx_user_loyalty_accounts_level (level_code),
            KEY idx_user_loyalty_accounts_points (points)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS loyalty_point_transactions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            username VARCHAR(120) NULL,
            type ENUM('earn','redeem','adjust','expire') NOT NULL DEFAULT 'earn',
            points INT NOT NULL DEFAULT 0,
            source VARCHAR(120) NULL,
            reference_id VARCHAR(120) NULL,
            note VARCHAR(500) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_loyalty_point_transactions_user (user_id, created_at),
            KEY idx_loyalty_point_transactions_type (type),
            KEY idx_loyalty_point_transactions_source (source)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $count = (int) $pdo->query('SELECT COUNT(*) FROM loyalty_levels')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO loyalty_levels
            (code, name, min_points, cashback_rate, weekly_bonus_amount, icon_url, color_hex, sort_order, is_active)
         VALUES
            (:code, :name, :min_points, :cashback_rate, :weekly_bonus_amount, :icon_url, :color_hex, :sort_order, 1)'
    );
    foreach ([
        ['bronze', 'Bronze', 0, 0.00, 0.00, '/content/images/loyalty_points/bronze.png', '#b7791f', 10],
        ['silver', 'Silver', 1000, 1.00, 100.00, '/content/images/loyalty_points/silver.png', '#94a3b8', 20],
        ['gold', 'Gold', 5000, 2.00, 250.00, '/content/images/loyalty_points/gold.png', '#f59e0b', 30],
        ['platinum', 'Platinum', 15000, 3.00, 500.00, '/content/images/loyalty_points/platinum.png', '#60a5fa', 40],
        ['diamond', 'Diamond', 50000, 5.00, 1000.00, '/content/images/loyalty_points/diamond.png', '#a78bfa', 50],
    ] as $level) {
        $insert->execute([
            'code' => $level[0],
            'name' => $level[1],
            'min_points' => $level[2],
            'cashback_rate' => $level[3],
            'weekly_bonus_amount' => $level[4],
            'icon_url' => $level[5],
            'color_hex' => $level[6],
            'sort_order' => $level[7],
        ]);
    }
};
