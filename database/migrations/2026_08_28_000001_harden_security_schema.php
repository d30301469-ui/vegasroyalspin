<?php

declare(strict_types=1);

/**
 * Security hardening schema — replaces runtime DDL on hot paths.
 * - login_rate_limits (brute-force protection)
 * - users wagering / wallet mode columns
 */
return static function (PDO $pdo): void {
    $migration = dirname(__DIR__, 2) . '/admin/database/migrations/2026_08_28_000001_harden_security_schema.php';
    if (is_readable($migration)) {
        (require $migration)($pdo);

        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS login_rate_limits (
            rate_key VARCHAR(190) NOT NULL PRIMARY KEY,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            window_started_at DATETIME NOT NULL,
            blocked_until DATETIME NULL,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
