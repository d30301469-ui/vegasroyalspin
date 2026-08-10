<?php

declare(strict_types=1);

/**
 * Telegram Mini App / bot kullanıcı bağları.
 * Aynı telegram_id yalnızca bir üye hesabına bağlanabilir.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS user_telegram_links (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            telegram_id BIGINT NOT NULL,
            telegram_username VARCHAR(64) NULL,
            first_name VARCHAR(120) NULL,
            last_name VARCHAR(120) NULL,
            language_code VARCHAR(16) NULL,
            photo_url VARCHAR(500) NULL,
            linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_auth_at DATETIME NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_telegram_id (telegram_id),
            UNIQUE KEY uniq_telegram_user (user_id),
            KEY idx_telegram_username (telegram_username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
