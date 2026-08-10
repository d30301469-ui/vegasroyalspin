<?php

declare(strict_types=1);

/**
 * Harden affiliate commission schema for the durable engine:
 * - nullable user_id for aggregate RevShare
 * - period run audit table
 */

return static function (PDO $pdo): void {
    try {
        $col = $pdo->query(
            "SELECT IS_NULLABLE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'affiliate_commissions'
               AND COLUMN_NAME = 'user_id'
             LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        if (is_array($col) && strtoupper((string) ($col['IS_NULLABLE'] ?? '')) === 'NO') {
            try {
                $pdo->exec('ALTER TABLE affiliate_commissions DROP FOREIGN KEY fk_aff_comm_user');
            } catch (Throwable) {
            }
            $pdo->exec('ALTER TABLE affiliate_commissions MODIFY user_id INT NULL');
            try {
                $pdo->exec(
                    'ALTER TABLE affiliate_commissions
                     ADD CONSTRAINT fk_aff_comm_user
                     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL'
                );
            } catch (Throwable) {
            }
        }
    } catch (Throwable $e) {
        error_log('[migration affiliate harden] user_id: ' . $e->getMessage());
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS affiliate_period_runs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id INT UNSIGNED NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            plan_id INT UNSIGNED NULL,
            plan_snapshot JSON NULL,
            revshare_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            cpa_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            cpa_count INT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'completed',
            message VARCHAR(500) NOT NULL DEFAULT '',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_aff_period_run (affiliate_id, period_start, period_end),
            KEY idx_aff_period_run_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
