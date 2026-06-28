<?php

declare(strict_types=1);


return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_permissions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            admin_id INT UNSIGNED NOT NULL,
            page_key VARCHAR(120) NOT NULL,
            granted TINYINT(1) NOT NULL DEFAULT 0,
            granted_by INT UNSIGNED NULL,
            granted_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_admin_permissions_admin_page (admin_id, page_key),
            KEY idx_admin_permissions_admin (admin_id),
            KEY idx_admin_permissions_page (page_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
