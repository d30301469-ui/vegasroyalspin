<?php

declare(strict_types=1);

/**
 * Üye oyun favorileri — slot / live casino kalıcı depolama.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS member_game_favorites (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     INT UNSIGNED NOT NULL,
            kind        VARCHAR(16) NOT NULL DEFAULT 'slot',
            game_id     VARCHAR(120) NOT NULL,
            game_name   VARCHAR(255) NULL,
            image_url   VARCHAR(500) NULL,
            provider    VARCHAR(120) NULL,
            created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_member_fav_user_kind_game (user_id, kind, game_id),
            KEY idx_member_fav_user_kind (user_id, kind),
            KEY idx_member_fav_game (game_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
