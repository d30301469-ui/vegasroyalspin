<?php

final class ApiFooterPages
{
    public static function slugFromTitle(string $title): string
    {
        $map = [
            'ı' => 'i', 'İ' => 'i', 'ğ' => 'g', 'Ğ' => 'g', 'ü' => 'u', 'Ü' => 'u',
            'ş' => 's', 'Ş' => 's', 'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c',
        ];
        $slug = strtr(trim($title), $map);
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        return trim($slug, '-') ?: 'sayfa';
    }

    public static function hrefForTitle(string $title): string
    {
        return '/footer/' . rawurlencode(self::slugFromTitle($title));
    }

    public static function findBySlug(string $slug): ?array
    {
        $slug = self::slugFromTitle($slug);
        if ($slug === '') {
            return null;
        }

        if (!ApiCmsRemote::canUseLocalDatabase()) {
            $cacheKey = ApiCmsRemote::cacheKey('footer_page', ['slug' => $slug]);
            $remote = ApiCmsRemote::getMainCached(
                $cacheKey,
                ['/content/footer-pages', '/footer_pages.php'],
                ['slug' => $slug]
            );
            $page = is_array($remote['page'] ?? null) ? $remote['page'] : null;

            return is_array($page) ? $page : null;
        }

        try {
            $pdo = self::pdo();
            $stmt = $pdo->prepare(
                'SELECT id, title, slug, content, meta_title, meta_description, is_active, sort_order, updated_at
                 FROM footer_pages
                 WHERE slug = :slug AND is_active = 1
                 LIMIT 1'
            );
            $stmt->execute(['slug' => $slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function allActive(): array
    {
        if (!ApiCmsRemote::canUseLocalDatabase()) {
            $remote = ApiCmsRemote::getMain(['/content/footer-pages', '/footer_pages.php']);
            $pages = is_array($remote['pages'] ?? null) ? $remote['pages'] : [];

            return $pages;
        }

        try {
            $pdo = self::pdo();
            $stmt = $pdo->query(
                'SELECT id, title, slug, meta_title, meta_description, sort_order, updated_at
                 FROM footer_pages
                 WHERE is_active = 1
                 ORDER BY sort_order ASC, title ASC'
            );
            $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            return [];
        }
    }

    public static function ensureStorage(?PDO $pdo = null): void
    {
        $pdo = $pdo ?: self::pdo();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS footer_pages (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                content MEDIUMTEXT NOT NULL,
                meta_title VARCHAR(255) NULL,
                meta_description VARCHAR(500) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_footer_pages_slug (slug),
                KEY idx_footer_pages_active_sort (is_active, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::seedDefaults($pdo);
    }

    private static function pdo(): PDO
    {
        return ApiCmsRemote::pdo();
    }

    private static function seedDefaults(PDO $pdo): void
    {
        $defaults = [
            'Gizlilik Politikası',
            'Firma Bilgileri',
            'AML-KYC-POLİCY',
            'Sorumlu Oyun',
            'Adalet Ve RNG Test Yöntemleri',
            'Kara Paranın Aklanmanın Önlenmesi',
            'Tartışmalı Karar',
            'Kendini Dışlama',
            'Genel Kurallar Ve Şartlar',
            'Bahislerin Kabulü',
            'Genel Bonus Kural Ve Şartları',
            'Risk Birimi',
            'Para Çekme',
            'Para Yatırma',
            'Android Google Authenticator',
            'İos Google Authenticator',
        ];

        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO footer_pages
             (title, slug, content, meta_title, meta_description, is_active, sort_order)
             VALUES
             (:title, :slug, :content, :meta_title, :meta_description, 1, :sort_order)'
        );
        foreach ($defaults as $index => $title) {
            $content = '<p>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . ' içeriğini admin panelinden düzenleyebilirsiniz.</p>';
            $stmt->execute([
                'title' => $title,
                'slug' => self::slugFromTitle($title),
                'content' => $content,
                'meta_title' => $title,
                'meta_description' => $title,
                'sort_order' => ($index + 1) * 10,
            ]);
        }
    }
}
