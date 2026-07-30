<?php

declare(strict_types=1);

/**
 * Referans sahibini ayrıştırır:
 *   users.referred_by_affiliate_id → affiliates.id (ortaklık portalı / komisyon cron'u)
 *   users.referred_by_user_id      → users.id      (üye→üye "Referanslarım")
 *
 * Tek kolonun iki anlamda kullanılması ortaklık komisyonlarının yanlış hesaplanmasına
 * yol açtığı için üye referansları ayrı kolona taşınır.
 */
return static function (PDO $pdo): void {
    $columns = [];
    try {
        $columns = array_column($pdo->query('SHOW COLUMNS FROM `users`')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    } catch (Throwable) {
        return;
    }

    if (!in_array('referred_by_affiliate_id', $columns, true)) {
        $pdo->exec('ALTER TABLE `users` ADD COLUMN `referred_by_affiliate_id` INT UNSIGNED NULL');
    }
    if (!in_array('referred_by_user_id', $columns, true)) {
        $pdo->exec('ALTER TABLE `users` ADD COLUMN `referred_by_user_id` INT UNSIGNED NULL');
    }

    $indexes = [
        'idx_users_referred_by_affiliate' => '(`referred_by_affiliate_id`)',
        'idx_users_referred_by_user' => '(`referred_by_user_id`)',
    ];
    foreach ($indexes as $name => $definition) {
        try {
            $exists = $pdo->query('SHOW INDEX FROM `users` WHERE Key_name = ' . $pdo->quote($name))->fetchColumn();
            if ($exists === false) {
                $pdo->exec("ALTER TABLE `users` ADD INDEX `{$name}` {$definition}");
            }
        } catch (Throwable $e) {
            error_log('[migration referred_by_user_id] index ' . $name . ': ' . $e->getMessage());
        }
    }

    // Ortak tablosuyla eşleşmeyen eski değerler üye referansıdır; doğru kolona taşı.
    try {
        $pdo->exec(
            'UPDATE users u
             LEFT JOIN affiliates a ON a.id = u.referred_by_affiliate_id
             SET u.referred_by_user_id = u.referred_by_affiliate_id,
                 u.referred_by_affiliate_id = NULL
             WHERE u.referred_by_affiliate_id IS NOT NULL
               AND u.referred_by_affiliate_id <> 0
               AND a.id IS NULL
               AND EXISTS (SELECT 1 FROM (SELECT id FROM users) r WHERE r.id = u.referred_by_affiliate_id)'
        );
    } catch (Throwable $e) {
        error_log('[migration referred_by_user_id] backfill: ' . $e->getMessage());
    }
};
