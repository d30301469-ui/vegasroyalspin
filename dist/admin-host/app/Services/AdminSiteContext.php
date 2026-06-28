<?php

declare(strict_types=1);

/**
 * Admin panelinde site_ayarlar tablosundan global marka / meta metinleri.
 */
final class AdminSiteContext
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * Veritabanına hiç dokunmadan, .env / config üzerinden marka bilgisi döner.
     * Giriş (login) gibi oturum öncesi sayfalarda kullanılır.
     *
     * @return array{
     *     site_name: string,
     *     description: string,
     *     logo_url: string,
     *     favicon_url: string,
     *     meta_title: string,
     *     language: string
     * }
     */
    public static function staticContext(): array
    {
        $siteName = function_exists('admin_env')
            ? (admin_env('SITE_NAME') ?: admin_env('APP_NAME', 'MaltaBet'))
            : 'MaltaBet';
        $language = function_exists('admin_env') ? admin_env('SITE_LANG', 'tr') : 'tr';

        return [
            'site_name' => $siteName !== '' ? $siteName : 'MaltaBet',
            'description' => 'Güvenilir casino ve bahis',
            'logo_url' => '',
            'favicon_url' => '',
            'meta_title' => '',
            'language' => $language !== '' ? $language : 'tr',
        ];
    }

    /**
     * @return array{
     *     site_name: string,
     *     description: string,
     *     logo_url: string,
     *     favicon_url: string,
     *     meta_title: string,
     *     language: string
     * }
     */
    public static function globals(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $defaults = [
            'site_name' => 'MaltaBet',
            'description' => 'Güvenilir casino ve bahis',
            'logo_url' => '',
            'favicon_url' => '',
            'meta_title' => '',
            'language' => 'tr',
        ];

        try {
            admin_require_project_file('api/SiteSettings.php');
            $pdo = AdminDatabase::pdo();
            $stmt = $pdo->query('SELECT * FROM site_ayarlar ORDER BY id ASC LIMIT 1');
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $payload = ApiSiteSettings::normalizePublicSettings($row);
                $branding = is_array($payload['branding'] ?? null) ? $payload['branding'] : [];
                $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

                return self::$cache = [
                    'site_name' => trim((string) ($branding['site_name'] ?? $defaults['site_name'])) ?: $defaults['site_name'],
                    'description' => trim((string) ($branding['description'] ?? $defaults['description'])) ?: $defaults['description'],
                    'logo_url' => trim((string) ($branding['logo_url'] ?? '')),
                    'favicon_url' => trim((string) ($branding['favicon_url'] ?? '')),
                    'meta_title' => trim((string) ($meta['title'] ?? '')),
                    'language' => trim((string) ($meta['language'] ?? $defaults['language'])) ?: $defaults['language'],
                ];
            }
        } catch (Throwable) {
        }

        return self::$cache = $defaults;
    }
}
