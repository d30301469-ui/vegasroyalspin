<?php

declare(strict_types=1);

/**
 * Security hardening schema — replaces runtime DDL on hot paths.
 * - login_rate_limits (brute-force protection)
 * - users wagering / wallet mode columns
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS login_rate_limits (
            rate_key VARCHAR(190) NOT NULL PRIMARY KEY,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            window_started_at DATETIME NOT NULL,
            blocked_until DATETIME NULL,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    try {
        $cols = array_column(
            $pdo->query('SHOW COLUMNS FROM `users`')->fetchAll(PDO::FETCH_ASSOC),
            'Field'
        );
        if ($cols !== []) {
            if (!in_array('wagering_required', $cols, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN wagering_required DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER bonus_balance');
            }
            if (!in_array('wagering_progress', $cols, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN wagering_progress DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER wagering_required');
            }
            if (!in_array('active_wallet_mode', $cols, true)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN active_wallet_mode ENUM('main','bonus') NOT NULL DEFAULT 'main' AFTER wagering_progress");
            }
        }
    } catch (Throwable $e) {
        error_log('[migration harden_security_schema] users wagering cols: ' . $e->getMessage());
    }

    try {
        $bonusCols = array_column(
            $pdo->query('SHOW COLUMNS FROM user_active_bonuses')->fetchAll(PDO::FETCH_ASSOC),
            'Field'
        );
        if ($bonusCols !== [] && !in_array('completed_at', $bonusCols, true)) {
            $pdo->exec('ALTER TABLE user_active_bonuses ADD COLUMN completed_at DATETIME NULL AFTER is_complete');
        }
    } catch (Throwable $e) {
        error_log('[migration harden_security_schema] user_active_bonuses completed_at: ' . $e->getMessage());
    }
};
