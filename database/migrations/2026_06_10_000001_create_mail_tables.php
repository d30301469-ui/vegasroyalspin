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
};
