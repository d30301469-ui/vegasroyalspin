<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $columns = [
        'turnstile_enabled' => "tinyint(1) NOT NULL DEFAULT 0",
        'turnstile_site_key' => "varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL",
        'turnstile_secret_key' => "varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL",
    ];

    foreach ($columns as $name => $definition) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $stmt->execute(['table' => 'site_ayarlar', 'column' => $name]);
        if ((int) $stmt->fetchColumn() > 0) {
            continue;
        }

        $pdo->exec('ALTER TABLE `site_ayarlar` ADD COLUMN `' . str_replace('`', '``', $name) . '` ' . $definition);
    }
};