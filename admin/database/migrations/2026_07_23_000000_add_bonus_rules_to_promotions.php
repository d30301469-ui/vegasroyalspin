<?php

declare(strict_types=1);

/**
 * Migration: promotions tablosuna bonus_rules (JSON kurallar) kolonu ekler.
 * Admin panel ve API tarafında bonus yapılandırması için gereklidir.
 *
 * NOT: PromotionMediaGuard::ensureSchema() da bu kolonu runtime'da ekler,
 * bu migration manuel deploy senaryoları içindir.
 */
return static function (PDO $pdo): void {
    // bonus_rules kolonu zaten varsa atla
    try {
        $check = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'promotions' AND COLUMN_NAME = 'bonus_rules'"
        );
        $check->execute();
        if ((int) $check->fetchColumn() > 0) {
            return;
        }
    } catch (Throwable) {
        // Devam et - ALTER ile dene
    }

    $pdo->exec("ALTER TABLE promotions ADD COLUMN bonus_rules TEXT NULL AFTER bonus_amount");
};
