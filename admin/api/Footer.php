<?php

/**
 * Footer configuration source.
 *
 * Admin panel stores the whole footer as one JSON payload in footer_settings.
 * Frontend keeps rendering server-side; only the data source is dynamic.
 */
final class ApiFooter
{
    public static function fetch(): array
    {
        $default = self::defaultPayload();

        if (!ApiCmsRemote::canUseLocalDatabase()) {
            $remote = ApiCmsRemote::getMainCached('footer', ['/content/footer', '/footer.php']);
            if ($remote !== null) {
                $footer = is_array($remote['footer'] ?? null) ? $remote['footer'] : [];

                return self::normalizePayload($footer, $default);
            }

            ApiCmsRemote::recordFetch('footer', 'default');

            return $default;
        }

        try {
            $pdo = self::pdo();
            self::ensureStorage($pdo, $default);

            $stmt = $pdo->query(
                "SELECT payload FROM footer_settings
                 WHERE is_active = 1
                 ORDER BY updated_at DESC, id DESC
                 LIMIT 1"
            );
            $payload = $stmt !== false ? $stmt->fetchColumn() : false;
            if (!is_string($payload) || trim($payload) === '') {
                return $default;
            }

            $decoded = json_decode($payload, true);
            if (!is_array($decoded)) {
                return $default;
            }

            return self::normalizePayload($decoded, $default);
        } catch (Throwable) {
            return $default;
        }
    }

    public static function defaultPayload(): array
    {
        $siteName = ApiCmsRemote::defaultSiteLabel();

        return [
            'social_icons' => [],
            'menu_columns' => [
                [
                    'title' => 'HAKKIMIZDA',
                    'icon' => 'valentine',
                    'links' => [
                        ['title' => 'Gizlilik Politikası', 'href' => 'javascript:void(0)', 'target' => '_self', 'icon' => ''],
                        ['title' => 'Firma Bilgileri', 'href' => 'javascript:void(0)', 'target' => '_self', 'icon' => ''],
                        ['title' => 'Sorumlu Oyun', 'href' => 'javascript:void(0)', 'target' => '_self', 'icon' => ''],
                    ],
                ],
                [
                    'title' => 'KURALLAR VE ŞARTLAR',
                    'icon' => 'star',
                    'links' => [
                        ['title' => 'Genel Kurallar Ve Şartlar', 'href' => 'javascript:void(0)', 'target' => '_self', 'icon' => ''],
                        ['title' => 'Genel Bonus Kural Ve Şartları', 'href' => 'javascript:void(0)', 'target' => '_self', 'icon' => ''],
                    ],
                ],
                [
                    'title' => 'CASİNO',
                    'icon' => 'casino',
                    'links' => [
                        ['title' => 'Casino', 'href' => '/slot', 'target' => '_self', 'icon' => ''],
                        ['title' => 'Canlı Casino', 'href' => '/livecasino', 'target' => '_self', 'icon' => ''],
                        ['title' => 'Promosyonlar', 'href' => '/promotions', 'target' => '_self', 'icon' => ''],
                    ],
                ],
                [
                    'title' => 'BAHİS',
                    'icon' => 'Football',
                    'links' => [
                        ['title' => 'Spor Bahisleri', 'href' => '/sportbook', 'target' => '_self', 'icon' => ''],
                    ],
                ],
            ],
            'payments' => [],
            'licence_rows' => [
                [
                    ['type' => 'text', 'html' => '<p>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . ' — lisans ve iletişim bilgileri admin panelden yönetilir.</p>'],
                ],
            ],
            'flag_image' => '/assets/images/footer/flag-tr.png',
            'copyright_since' => (int) date('Y'),
            'site_name' => $siteName,
            'show_custom_content' => false,
            'support_badge' => [
                'enabled' => false,
                'label' => '7/24',
                'text' => 'ONLINE',
                'href' => 'javascript:void(0)',
            ],
            'about' => [
                'history_title' => '',
                'history_text' => '',
                'future_title' => '',
                'future_text' => '',
                'awards_title' => '',
            ],
            'awards' => [],
            'partner_logos' => [],
            'jackpot_config' => [
                'epoch' => date('Y-m-d H:i:s'),
                'providers' => [],
            ],
        ];
    }

    public static function normalize(array $payload): array
    {
        return self::normalizePayload($payload, self::defaultPayload());
    }

    private static function pdo(): PDO
    {
        return ApiCmsRemote::pdo();
    }

    public static function ensureStorage(PDO $pdo, array $default): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

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

        $count = (int) $pdo->query('SELECT COUNT(*) FROM footer_settings')->fetchColumn();
        if ($count === 0) {
            $payload = json_encode($default, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($payload)) {
                $payload = '{}';
            }
            $stmt = $pdo->prepare('INSERT INTO footer_settings (name, payload, is_active) VALUES (:name, :payload, 1)');
            $stmt->execute([
                'name' => 'default',
                'payload' => $payload,
            ]);
        }

        $ready = true;
    }

    private static function normalizePayload(array $payload, array $default): array
    {
        $normalized = $default;
        foreach (['social_icons', 'menu_columns', 'payments', 'licence_rows', 'awards', 'partner_logos'] as $key) {
            if (array_key_exists($key, $payload) && is_array($payload[$key])) {
                $normalized[$key] = $payload[$key];
            }
        }

        if (isset($payload['jackpot_config']) && is_array($payload['jackpot_config'])) {
            $normalized['jackpot_config'] = array_replace(
                $default['jackpot_config'] ?? ['epoch' => '', 'providers' => []],
                $payload['jackpot_config']
            );
            if (!is_array($normalized['jackpot_config']['providers'] ?? null)) {
                $normalized['jackpot_config']['providers'] = [];
            }
        }

        if (isset($payload['about']) && is_array($payload['about'])) {
            $normalized['about'] = array_replace($default['about'], $payload['about']);
        }
        if (isset($payload['support_badge']) && is_array($payload['support_badge'])) {
            $normalized['support_badge'] = array_replace($default['support_badge'], $payload['support_badge']);
            $normalized['support_badge']['enabled'] = (bool) ($payload['support_badge']['enabled'] ?? $default['support_badge']['enabled']);
        }

        foreach (['flag_image', 'site_name'] as $key) {
            if (array_key_exists($key, $payload)) {
                $normalized[$key] = (string) $payload[$key];
            }
        }
        if (array_key_exists('copyright_since', $payload)) {
            $normalized['copyright_since'] = (int) $payload['copyright_since'];
        }
        if (array_key_exists('show_custom_content', $payload)) {
            $normalized['show_custom_content'] = (bool) $payload['show_custom_content'];
        }

        return $normalized;
    }
}
