<?php

declare(strict_types=1);

/**
 * admin_audit_logs, admin_user_notes ve eksik kolon düzeltmeleri.
 */
return static function (PDO $pdo): void {
    // Birleşik audit log tablosu.
    // Admin_routes.php'de iki farklı INSERT şeması kullanılıyordu; bu migration
    // her iki şemayı da karşılayan tek tablo oluşturur.
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_audit_logs (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            admin_id    INT UNSIGNED    NOT NULL DEFAULT 0,
            admin_username VARCHAR(100) NULL,
            action      VARCHAR(120)    NOT NULL,
            entity_type VARCHAR(80)     NULL,
            entity_id   VARCHAR(120)    NULL,
            note        VARCHAR(1000)   NULL,
            meta        JSON            NULL,
            ip_address  VARCHAR(64)     NULL,
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_aal_admin   (admin_id, created_at),
            KEY idx_aal_action  (action),
            KEY idx_aal_entity  (entity_type, entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Admin tarafından kullanıcı üzerine yazılan iç notlar.
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_user_notes (
            id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id    INT UNSIGNED NOT NULL,
            admin_id   INT UNSIGNED NOT NULL DEFAULT 0,
            content    TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_aun_user (user_id),
            KEY idx_aun_admin (admin_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // user_active_bonuses'a eksik completed_at kolonu ekle (idempotent).
    try {
        $cols = array_column(
            $pdo->query('SHOW COLUMNS FROM user_active_bonuses')->fetchAll(PDO::FETCH_ASSOC),
            'Field'
        );
        if (!in_array('completed_at', $cols, true)) {
            $pdo->exec('ALTER TABLE user_active_bonuses ADD COLUMN completed_at DATETIME NULL DEFAULT NULL AFTER deadline');
        }
    } catch (Throwable) {}
};
