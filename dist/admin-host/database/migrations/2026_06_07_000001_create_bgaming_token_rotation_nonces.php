<?php

declare(strict_types=1);


return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS bgaming_token_rotation_nonces (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nonce_hash CHAR(64) NOT NULL,
            nonce VARCHAR(190) NOT NULL,
            request_payload JSON NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_bgaming_token_rotation_nonce_hash (nonce_hash),
            KEY idx_bgaming_token_rotation_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
