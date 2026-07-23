<?php

declare(strict_types=1);

/**
 * Add columns that were defined in 2026_06_10_000000_create_core_member_tables.php
 * but never applied to pre-existing users tables (CREATE TABLE IF NOT EXISTS
 * skips ALTER). This fills the gap so dashboard KPIs and login tracking work.
 */
return static function (PDO $pdo): void {
    $columnExists = static function (PDO $pdo, string $table, string $column): bool {
        try {
            $cols = array_column(
                $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC),
                'Field'
            );
            return in_array($column, $cols, true);
        } catch (Throwable) {
            return false;
        }
    };

    $addColumn = static function (PDO $pdo, string $table, string $column, string $definition) use ($columnExists): void {
        if ($columnExists($pdo, $table, $column)) {
            return;
        }
        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        } catch (Throwable $e) {
            error_log("[migration] Failed to add {$table}.{$column}: " . $e->getMessage());
        }
    };

    // Columns from 2026_06_10_000000_create_core_member_tables.php that may be
    // missing on pre-existing tables.
    $addColumn($pdo, 'users', 'identity_number', 'VARCHAR(32) NULL');
    $addColumn($pdo, 'users', 'gender', 'VARCHAR(16) NULL');
    $addColumn($pdo, 'users', 'dob', 'DATE NULL');
    $addColumn($pdo, 'users', 'city', 'VARCHAR(100) NULL');
    $addColumn($pdo, 'users', 'address', 'VARCHAR(500) NULL');
    $addColumn($pdo, 'users', 'bonus_code', 'VARCHAR(60) NULL');
    $addColumn($pdo, 'users', 'bonus_balance', "DECIMAL(15,2) NOT NULL DEFAULT 0.00");
    $addColumn($pdo, 'users', 'is_verified', 'TINYINT(1) NOT NULL DEFAULT 0');
    $addColumn($pdo, 'users', 'is_test', 'TINYINT(1) NOT NULL DEFAULT 0');
    $addColumn($pdo, 'users', 'verify_token', 'VARCHAR(128) NULL');
    $addColumn($pdo, 'users', 'last_login_at', 'DATETIME NULL');
    $addColumn($pdo, 'users', 'password_changed_at', 'DATETIME NULL');
    $addColumn($pdo, 'users', 'updated_at', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

    // Index that depends on last_login_at (from 2026_06_23 migration).
    $indexExists = static function (PDO $pdo, string $table, string $index): bool {
        try {
            $stmt = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $pdo->quote($index));
            return $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    };

    if ($columnExists($pdo, 'users', 'last_login_at') && !$indexExists($pdo, 'users', 'idx_users_last_login')) {
        try {
            $pdo->exec('ALTER TABLE `users` ADD INDEX `idx_users_last_login` (`last_login_at`)');
        } catch (Throwable) {
            // Index may already exist.
        }
    }
};
