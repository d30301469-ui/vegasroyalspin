<?php

declare(strict_types=1);

/**
 * Kullanıcı tablosuna güvenli şifre sıfırlama kolonları ekler.
 * verify_token artık yalnızca e-posta doğrulaması için kullanılır;
 * şifre sıfırlama işlemleri ayrı kolon + 1 saatlik expiry ile yönetilir.
 */
return static function (PDO $pdo): void {
    $cols = array_column(
        $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC),
        'Field'
    );

    if (!in_array('password_reset_token', $cols, true)) {
        $pdo->exec(
            "ALTER TABLE users ADD COLUMN password_reset_token VARCHAR(64) NULL DEFAULT NULL AFTER verify_token"
        );
    }

    if (!in_array('password_reset_expires_at', $cols, true)) {
        $pdo->exec(
            "ALTER TABLE users ADD COLUMN password_reset_expires_at DATETIME NULL DEFAULT NULL AFTER password_reset_token"
        );
    }

    // İndeks: token ile hızlı arama.
    try {
        $pdo->exec('ALTER TABLE users ADD INDEX idx_users_reset_token (password_reset_token)');
    } catch (Throwable) {}
};
