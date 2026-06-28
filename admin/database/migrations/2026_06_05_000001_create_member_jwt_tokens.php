<?php

declare(strict_types=1);


return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS member_jwt_tokens (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            jti CHAR(32) NOT NULL,
            token_hash CHAR(64) NOT NULL,
            issued_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            revoked_at DATETIME NULL,
            last_seen_at DATETIME NULL,
            ip_address VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_member_jwt_jti (jti),
            KEY idx_member_jwt_user_id (user_id),
            KEY idx_member_jwt_token_hash (token_hash),
            KEY idx_member_jwt_active (revoked_at, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};

