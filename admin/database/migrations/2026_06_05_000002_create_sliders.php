<?php

declare(strict_types=1);


return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sliders (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            subtitle VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            description TEXT COLLATE utf8mb4_unicode_ci NULL,
            desktop_path VARCHAR(700) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            mobile_path VARCHAR(700) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            button_link VARCHAR(700) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `order` INT NOT NULL DEFAULT 0,
            category VARCHAR(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'home',
            status TINYINT(1) NOT NULL DEFAULT 1,
            start_date DATETIME NULL,
            end_date DATETIME NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sliders_lookup (category, status, `order`),
            KEY idx_sliders_dates (start_date, end_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};

