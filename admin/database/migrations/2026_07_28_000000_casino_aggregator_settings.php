<?php

declare(strict_types=1);

/**
 * Casino Aggregator Operator API Appendix 4 — AgentSetting / UserSetting local mirrors.
 * (Admin mirror — prefer CasinoAggregatorService bootstrap inline DDL.)
 */
return static function (PDO $pdo): void {
    $exists = static function (PDO $pdo, string $table): bool {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1'
        );
        $stmt->execute([':t' => $table]);
        return (bool) $stmt->fetchColumn();
    };

    if (!$exists($pdo, 'casino_aggregator_agent_settings')) {
        $pdo->exec(
            "CREATE TABLE casino_aggregator_agent_settings (
                setting_key VARCHAR(64) NOT NULL,
                setting_value VARCHAR(512) NOT NULL DEFAULT '',
                synced_at DATETIME NULL DEFAULT NULL,
                created_at DATETIME NULL DEFAULT NULL,
                updated_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$exists($pdo, 'casino_aggregator_user_settings')) {
        $pdo->exec(
            "CREATE TABLE casino_aggregator_user_settings (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NULL DEFAULT NULL,
                user_code VARCHAR(120) NOT NULL,
                setting_key VARCHAR(64) NOT NULL,
                setting_value VARCHAR(512) NOT NULL DEFAULT '',
                synced_at DATETIME NULL DEFAULT NULL,
                created_at DATETIME NULL DEFAULT NULL,
                updated_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_casino_agg_user_setting (user_code, setting_key),
                KEY idx_casino_agg_user_settings_user (user_id),
                KEY idx_casino_agg_user_settings_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

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
