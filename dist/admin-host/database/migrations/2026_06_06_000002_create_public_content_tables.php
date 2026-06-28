<?php

declare(strict_types=1);


return static function (PDO $pdo): void {
    $envDefault = static function (string $key, string $default): string {
        $value = getenv($key);
        if ($value === false || trim((string) $value) === '') {
            return $default;
        }

        return trim((string) $value);
    };
    $sqlDefault = static fn (string $value): string => str_replace("'", "''", $value);
    $deployDomains = dirname(__DIR__, 2) . '/config/deploy_domains.php';
    if (is_file($deployDomains)) {
        require_once $deployDomains;
    }
    $deployDefault = static function (string $key, string $fallback) use ($envDefault): string {
        if (function_exists('deploy_domain')) {
            $value = deploy_domain($key);
            if ($value !== '') {
                return $value;
            }
        }

        return $fallback;
    };
    $liveSupportUrl = $sqlDefault($envDefault('LIVE_SUPPORT_URL', 'https://direct.lc.chat/19301899/'));
    $whatsappUrl = $sqlDefault($envDefault('WHATSAPP_URL', ''));
    $telegramUrl = $sqlDefault($envDefault('TELEGRAM_URL', 'https://t.me'));
    $frontendUrl = $sqlDefault($envDefault('FRONTEND_FALLBACK_URL', $deployDefault('frontend_fallback_url', 'https://vegasroyalspin.com')));
    $backendUrl = $sqlDefault($envDefault('BACKEND_FALLBACK_URL', $deployDefault('backend_url', 'https://bo-nexthub.site')));
    $backendApiBaseUrl = $sqlDefault($envDefault('API_BACKEND_FALLBACK_BASE_URL', $deployDefault('backend_api_base_url', rtrim($envDefault('BACKEND_FALLBACK_URL', 'https://bo-nexthub.site'), '/') . '/api/v2')));
    $allowedUrlHosts = $sqlDefault($envDefault('DEFAULT_ALLOWED_URL_HOSTS', $deployDefault('default_allowed_url_hosts', 'vegasroyalspin.com,www.vegasroyalspin.com,m.vegasroyalspin.com,bo-nexthub.site')));

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `site_ayarlar` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `site_adi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'MaltaBet',
            `site_aciklama` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT 'Güvenilir casino ve bahis',
            `bakim_modu` tinyint(1) NOT NULL DEFAULT 0,
            `logo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `favicon_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `partnership_label` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT 'ORTAKLIK',
            `partnership_title` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT 'Ortaklık',
            `partnership_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '/ortaklik',
            `live_support_title` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT 'Canlı Destek',
            `live_support_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '{$liveSupportUrl}',
            `callback_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '/beni-ara',
            `callback_widget_text` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT 'Dolandırıcılara geçit verme! Size ulaşan numara bize mi ait tıkla!',
            `contact_phone` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `whatsapp_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT " . ($whatsappUrl !== '' ? "'{$whatsappUrl}'" : "NULL") . ",
            `telegram_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '{$telegramUrl}',
            `manifest_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '/assets/images/favicons/site.webmanifest',
            `og_image_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `site_keywords` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `robots` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT 'index, follow',
            `language` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'tr',
            `theme_color` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT '#120023',
            `frontend_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '{$frontendUrl}',
            `backend_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '{$backendUrl}',
            `backend_api_base_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '{$backendApiBaseUrl}',
            `allowed_url_hosts` varchar(700) COLLATE utf8mb4_unicode_ci DEFAULT '{$allowedUrlHosts}',
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS mobile_menu_settings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL DEFAULT 'default',
            payload JSON NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_mobile_menu_settings_active (is_active, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS homepage_sections (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            section_key VARCHAR(80) NOT NULL,
            title VARCHAR(255) NULL,
            type ENUM('games','banner') NOT NULL DEFAULT 'games',
            surface ENUM('all','desktop','mobile') NOT NULL DEFAULT 'all',
            payload JSON NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            start_date DATETIME NULL,
            end_date DATETIME NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_homepage_sections_key_surface (section_key, surface),
            KEY idx_homepage_sections_lookup (section_key, surface, is_active, sort_order),
            KEY idx_homepage_sections_dates (start_date, end_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS footer_settings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL DEFAULT 'default',
            payload JSON NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_footer_settings_active (is_active, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

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
};
