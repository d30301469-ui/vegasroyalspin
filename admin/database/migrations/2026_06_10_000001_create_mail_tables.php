<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS mail_outbound_log (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            admin_id INT UNSIGNED NULL,
            to_email VARCHAR(190) NOT NULL,
            subject VARCHAR(255) NOT NULL DEFAULT '',
            body_preview TEXT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'queued',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_mail_outbound_created (created_at),
            KEY idx_mail_outbound_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS mail_settings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            mail_enabled TINYINT(1) NOT NULL DEFAULT 0,
            from_email VARCHAR(190) NULL,
            mail_from_address VARCHAR(190) NULL,
            smtp_host VARCHAR(190) NULL,
            smtp_port INT NULL,
            smtp_user VARCHAR(190) NULL,
            smtp_password VARCHAR(255) NULL,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Existing installations may have an older mail_settings schema.
    // Keep migration idempotent by adding missing columns in place.
    try {
        $existing = [];
        $stmt = $pdo->query("SHOW COLUMNS FROM mail_settings");
        if ($stmt !== false) {
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $name = strtolower((string) ($col['Field'] ?? ''));
                if ($name !== '') {
                    $existing[$name] = true;
                }
            }
        }

        $required = [
            'enabled' => 'ALTER TABLE mail_settings ADD COLUMN enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER id',
            'mail_enabled' => 'ALTER TABLE mail_settings ADD COLUMN mail_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER enabled',
            'from_email' => 'ALTER TABLE mail_settings ADD COLUMN from_email VARCHAR(190) NULL AFTER mail_enabled',
            'mail_from_address' => 'ALTER TABLE mail_settings ADD COLUMN mail_from_address VARCHAR(190) NULL AFTER from_email',
            'smtp_host' => 'ALTER TABLE mail_settings ADD COLUMN smtp_host VARCHAR(190) NULL AFTER mail_from_address',
            'smtp_port' => 'ALTER TABLE mail_settings ADD COLUMN smtp_port INT NULL AFTER smtp_host',
            'smtp_user' => 'ALTER TABLE mail_settings ADD COLUMN smtp_user VARCHAR(190) NULL AFTER smtp_port',
            'smtp_password' => 'ALTER TABLE mail_settings ADD COLUMN smtp_password VARCHAR(255) NULL AFTER smtp_user',
            'updated_at' => 'ALTER TABLE mail_settings ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER smtp_password',
        ];

        foreach ($required as $column => $sql) {
            if (!isset($existing[$column])) {
                $pdo->exec($sql);
            }
        }
    } catch (Throwable) {
        // Best-effort schema reconciliation.
    }
};
