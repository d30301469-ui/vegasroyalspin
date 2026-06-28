<?php

declare(strict_types=1);


/**
 * site_ayarlar: yeni frontend sözleşmesinde (ApiSiteSettings::normalizePublicSettings)
 * kullanılmayan eski kolonları kaldırır. Bu alanların yerini şu yeni kolonlar aldı:
 *   - canli_destek_kodu -> live_support_url / live_support_title
 *   - allowed_domains   -> allowed_url_hosts
 *   - logo_assets       -> logo_url
 *   - favicon_assets    -> favicon_url
 *   - custom_css/custom_js -> (kullanım dışı; tema/asset üzerinden yönetiliyor)
 *
 * İdempotent: kolon yoksa atlar (Migrator zaten bir kez çalıştırır).
 */
return static function (PDO $pdo): void {
    $legacyColumns = [
        'canli_destek_kodu',
        'allowed_domains',
        'custom_css',
        'custom_js',
        'logo_assets',
        'favicon_assets',
    ];

    foreach ($legacyColumns as $column) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
        );
        $stmt->execute(['t' => 'site_ayarlar', 'c' => $column]);
        if ((int) $stmt->fetchColumn() > 0) {
            $pdo->exec('ALTER TABLE `site_ayarlar` DROP COLUMN `' . str_replace('`', '``', $column) . '`');
        }
    }
};
