<?php

/**
 * Login/register modal sliderları için yerel DB kaynağı.
 */
final class ApiAuthSliders
{
    public static function ensureStorage(): void
    {
        self::pdo()->exec(
            "CREATE TABLE IF NOT EXISTS auth_sliders (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                screen ENUM('login','register') COLLATE utf8mb4_unicode_ci NOT NULL,
                surface ENUM('desktop','mobile') COLLATE utf8mb4_unicode_ci NOT NULL,
                media_path VARCHAR(700) COLLATE utf8mb4_unicode_ci NOT NULL,
                media_alt VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                link_url VARCHAR(700) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                start_date DATETIME DEFAULT NULL,
                end_date DATETIME DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_auth_sliders_lookup (screen, surface, is_active, sort_order),
                KEY idx_auth_sliders_dates (start_date, end_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function fetchFor(string $screen, ?string $surface = null): array
    {
        $screen = in_array($screen, ['login', 'register'], true) ? $screen : 'login';
        $surface = in_array($surface, ['desktop', 'mobile'], true)
            ? $surface
            : ((defined('SURFACE') && SURFACE === 'mobile') ? 'mobile' : 'desktop');

        if (!ApiCmsRemote::canUseLocalDatabase()) {
            $query = [
                'screen' => $screen,
                'surface' => $surface,
            ];
            $cacheKey = ApiCmsRemote::cacheKey('auth_sliders', $query);
            $remote = ApiCmsRemote::getMainCached(
                $cacheKey,
                ['/content/auth-sliders', '/auth_sliders.php'],
                $query
            );
            $sliders = is_array($remote['sliders'] ?? null) ? $remote['sliders'] : [];
            if ($remote !== null) {
                return array_map(
                    static fn (array $row): array => ApiMediaUrl::resolveAuthSliderRow($row),
                    self::mapRemoteRows($sliders)
                );
            }

            ApiCmsRemote::recordFetch($cacheKey, 'default');

            return [];
        }

        try {
            $today = date('Y-m-d');
            $stmt = self::pdo()->prepare(
                "SELECT id, title, screen, surface, media_path, media_alt, link_url, sort_order
                 FROM auth_sliders
                 WHERE is_active = 1
                   AND screen = :screen
                   AND surface = :surface
                   AND (start_date IS NULL OR DATE(start_date) <= :today_start)
                   AND (end_date IS NULL OR DATE(end_date) >= :today_end)
                 ORDER BY sort_order ASC, id DESC"
            );
            $stmt->execute([
                'screen' => $screen,
                'surface' => $surface,
                'today_start' => $today,
                'today_end' => $today,
            ]);

            return self::mapRows($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function mapRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $mediaPath = ApiMediaUrl::resolve(trim((string) ($row['media_path'] ?? '')));
            if ($mediaPath === '') {
                continue;
            }
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
                'screen' => (string) ($row['screen'] ?? ''),
                'surface' => (string) ($row['surface'] ?? ''),
                'mediaPath' => $mediaPath,
                'mediaAlt' => (string) ($row['media_alt'] ?? ''),
                'linkUrl' => (string) ($row['link_url'] ?? ''),
                'sortOrder' => (int) ($row['sort_order'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function mapRemoteRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $mediaPath = trim((string) ($row['mediaPath'] ?? $row['media_path'] ?? ''));
            if ($mediaPath === '') {
                continue;
            }
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
                'screen' => (string) ($row['screen'] ?? ''),
                'surface' => (string) ($row['surface'] ?? ''),
                'mediaPath' => $mediaPath,
                'mediaAlt' => (string) ($row['mediaAlt'] ?? $row['media_alt'] ?? ''),
                'linkUrl' => (string) ($row['linkUrl'] ?? $row['link_url'] ?? ''),
                'sortOrder' => (int) ($row['sortOrder'] ?? $row['sort_order'] ?? 0),
            ];
        }

        return $out;
    }

    private static function pdo(): PDO
    {
        return ApiCmsRemote::pdo();
    }
}
