<?php

declare(strict_types=1);

/**
 * Casino Aggregator Operator API Appendix 4 — AgentSetting / UserSetting local mirrors.
 * (Admin mirror of database/migrations/2026_07_28_000000_casino_aggregator_settings.php)
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS casino_aggregator_agent_settings (
            setting_key   VARCHAR(64) NOT NULL,
            setting_value TEXT NOT NULL DEFAULT '',
            synced_at     DATETIME NULL,
            created_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS casino_aggregator_user_settings (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id       INT UNSIGNED NULL,
            user_code     VARCHAR(120) NOT NULL,
            setting_key   VARCHAR(64) NOT NULL,
            setting_value TEXT NOT NULL DEFAULT '',
            synced_at     DATETIME NULL,
            created_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_casino_agg_user_setting (user_code, setting_key),
            KEY idx_casino_agg_user_settings_user (user_id),
            KEY idx_casino_agg_user_settings_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $agentDefaults = [
        'RoundKey'       => '',
        'HideRoundId'    => '0',
        'HideTournament' => '0',
        'HideBadge'      => '0',
        'LowRtp'         => '',
        'HighRtp'        => '',
    ];
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO casino_aggregator_agent_settings (setting_key, setting_value)
         VALUES (:k, :v)'
    );
    foreach ($agentDefaults as $key => $value) {
        $stmt->execute([':k' => $key, ':v' => $value]);
    }
};
