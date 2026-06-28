<?php

declare(strict_types=1);


return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS aml_alerts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NULL,
            rule_code VARCHAR(80) NOT NULL DEFAULT 'manual',
            severity VARCHAR(20) NOT NULL DEFAULT 'medium',
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            title VARCHAR(190) NOT NULL,
            description TEXT NULL,
            payload_json JSON NULL,
            resolved_by VARCHAR(100) NULL,
            resolved_at DATETIME NULL,
            resolution_note VARCHAR(500) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_aml_alerts_status (status, created_at),
            KEY idx_aml_alerts_user (user_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS risk_alerts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NULL,
            alert_type VARCHAR(80) NOT NULL DEFAULT 'general',
            severity VARCHAR(20) NOT NULL DEFAULT 'medium',
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            title VARCHAR(190) NOT NULL,
            description TEXT NULL,
            payload_json JSON NULL,
            resolved_by VARCHAR(100) NULL,
            resolved_at DATETIME NULL,
            resolution_note VARCHAR(500) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_risk_alerts_status (status, created_at),
            KEY idx_risk_alerts_user (user_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
