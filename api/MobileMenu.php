<?php

/**
 * Mobile bottom menu / full-screen menu configuration source.
 *
 * Admin stores the whole mobile menu as a JSON payload in mobile_menu_settings.
 * Frontend renders server-side from this source, while /api/v2/content/mobile-menu
 * exposes the same payload for clients that need it.
 */
final class ApiMobileMenu
{
    public static function fetch(): array
    {
        $default = self::defaultPayload();

        if (!ApiCmsRemote::canUseLocalDatabase()) {
            $remote = ApiCmsRemote::getMainCached('mobile_menu', ['/content/mobile-menu', '/mobile-menu.php']);
            if ($remote !== null) {
                $menu = is_array($remote['menu'] ?? null)
                    ? $remote['menu']
                    : (is_array($remote['mobile_menu'] ?? null) ? $remote['mobile_menu'] : []);

                return self::normalizePayload($menu, $default);
            }

            ApiCmsRemote::recordFetch('mobile_menu', 'default');

            return $default;
        }

        try {
            $pdo = self::pdo();

            $stmt = $pdo->query(
                "SELECT payload FROM mobile_menu_settings
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

    /**
     * @return list<array<string, mixed>>
     */
    public static function defaultTabBar(): array
    {
        return [
            ['type' => 'link', 'label' => 'SPOR', 'href' => '/sportbook', 'icon' => 'bc-i-prematch', 'badge' => '', 'enabled' => true, 'aria_label' => 'SPOR'],
            ['type' => 'button', 'label' => 'KUPON', 'href' => '', 'icon' => 'bc-i-betslip', 'badge' => '', 'enabled' => true, 'id' => 'mob-bet-kupon', 'aria_label' => 'KUPON'],
            ['type' => 'link', 'label' => 'CASİNO', 'href' => '/slot', 'icon' => 'bc-i-slots', 'badge' => '', 'enabled' => true, 'aria_label' => 'CASİNO'],
            ['type' => 'menu', 'label' => 'MENÜ', 'href' => '', 'icon' => 'bc-i-burger', 'badge' => '', 'enabled' => true, 'id' => 'menu-toggle', 'aria_label' => 'MENÜ'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function defaultDesktopNav(): array
    {
        return [
            ['label' => 'SPOR', 'href' => '/sportbook', 'icon' => 'bc-i-prematch', 'enabled' => true],
            ['label' => 'SLOT', 'href' => '/slot', 'icon' => 'bc-i-slots', 'enabled' => true],
            ['label' => 'CANLI CASINO', 'href' => '/livecasino', 'icon' => 'bc-i-livecasino', 'enabled' => true],
            ['label' => 'BGAMING', 'href' => '/bgaming', 'icon' => 'bc-i-tv-games', 'enabled' => true],
            ['label' => 'TURNUVALAR', 'href' => '/turnuvalar', 'icon' => 'bc-i-tournament', 'enabled' => true],
            ['label' => 'BENİ ARA', 'href' => '/beni-ara', 'icon' => 'bc-i-call', 'enabled' => true],
            ['label' => 'PROMOSYONLAR', 'href' => '/promotions', 'icon' => 'bc-i-promotions-3', 'enabled' => true],
        ];
    }

    public static function defaultPayload(): array
    {
        $liveSupportUrl = self::envUrl('LIVE_SUPPORT_URL', 'javascript:void(0)');
        $whatsappUrl = self::envUrl('WHATSAPP_URL', 'javascript:void(0)');

        return [
            'title' => 'Menü',
            'tab_bar' => self::defaultTabBar(),
            'desktop_nav' => self::defaultDesktopNav(),
            'sections' => [
                [
                    'title' => '',
                    'layout' => 'grid',
                    'items' => [
                        ['label' => 'Spor', 'href' => '/sportbook', 'icon' => 'bc-i-prematch', 'badge' => '', 'target' => '_self', 'enabled' => true],
                        ['label' => 'Slot', 'href' => '/slot', 'icon' => 'bc-i-slots', 'badge' => '', 'target' => '_self', 'enabled' => true],
                        ['label' => 'Canlı Casino', 'href' => '/livecasino', 'icon' => 'bc-i-livecasino', 'badge' => '', 'target' => '_self', 'enabled' => true],
                        ['label' => 'BGaming', 'href' => '/bgaming', 'icon' => 'bc-i-tv-games', 'badge' => '', 'target' => '_self', 'enabled' => true],
                        ['label' => 'Turnuvalar', 'href' => '/turnuvalar', 'icon' => 'bc-i-tournament', 'badge' => '', 'target' => '_self', 'enabled' => true],
                        ['label' => 'Promosyonlar', 'href' => '/promotions', 'icon' => 'bc-i-promotions-3', 'badge' => '', 'target' => '_self', 'enabled' => true],
                    ],
                ],
                [
                    'title' => '',
                    'items' => [
                        ['label' => 'Promosyonlar', 'href' => '/promotions', 'icon' => 'bc-i-promotions-3', 'badge' => 'PROMOSYON', 'target' => '_self', 'enabled' => true],
                        ['label' => 'Bonus Talep', 'href' => '/bonustalep', 'icon' => 'bc-i-bonus-1', 'badge' => 'PROMOSYON', 'target' => '_self', 'enabled' => true],
                    ],
                ],
                [
                    'title' => 'İLETİŞİM',
                    'items' => [
                        ['label' => 'Canlı Destek', 'href' => $liveSupportUrl, 'icon' => 'bc-i-live-chat', 'badge' => 'ÖZEL', 'target' => '_blank', 'enabled' => true],
                        ['label' => 'Beni Ara', 'href' => '/beni-ara', 'icon' => 'bc-i-call', 'badge' => 'ÖZEL', 'target' => '_self', 'enabled' => true],
                        ['label' => 'Whatsapp', 'href' => $whatsappUrl, 'icon' => 'bc-i-whatsapp', 'badge' => 'ÖZEL', 'target' => '_blank', 'enabled' => true],
                    ],
                ],
                [
                    'title' => 'SPOR',
                    'items' => [
                        ['label' => 'Spor Bahisleri', 'href' => '/sportbook', 'icon' => 'bc-i-prematch', 'badge' => 'EN İYİ', 'target' => '_self', 'enabled' => true],
                    ],
                ],
            ],
            'product_banner_base' => 'assets/images/banners',
            'product_banners' => [
                ['href' => '/slot', 'aria' => 'SLOT', 'img' => 'slot.webp', 'alt' => 'Slot', 'enabled' => true],
                ['href' => '/sportbook', 'aria' => 'Spor Bahisleri', 'img' => 'spor.webp', 'alt' => 'Spor Bahisleri', 'enabled' => true],
                ['href' => '/livecasino', 'aria' => 'Canlı Casino', 'img' => 'canlicasino.webp', 'alt' => 'Canlı Casino', 'enabled' => true],
                ['href' => '/promotions', 'aria' => 'Promosyonlar', 'img' => 'arkadasbonusu.webp', 'alt' => 'Promosyonlar', 'enabled' => true],
                ['href' => '/beni-ara', 'aria' => 'Aranma Talebi', 'img' => 'cozummerkezi.webp', 'alt' => 'Aranma Talebi', 'enabled' => true],
                ['href' => '{{LIVE_SUPPORT_URL}}', 'aria' => 'Destek', 'img' => 'telegram.webp', 'alt' => 'Canlı Destek', 'enabled' => true],
            ],
        ];
    }

    private static function envUrl(string $key, string $default): string
    {
        if (defined($key)) {
            return trim((string) constant($key));
        }

        $value = getenv($key);
        if ($value === false || trim((string) $value) === '') {
            return $default;
        }

        return trim((string) $value);
    }

    public static function normalize(array $payload): array
    {
        return self::normalizePayload($payload, self::defaultPayload());
    }

    private static function pdo(): PDO
    {
        return ApiCmsRemote::pdo();
    }

    private static function ensureStorage(PDO $pdo, array $default): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

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

        $count = (int) $pdo->query('SELECT COUNT(*) FROM mobile_menu_settings')->fetchColumn();
        if ($count === 0) {
            $payload = json_encode($default, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($payload)) {
                $payload = '{}';
            }
            $stmt = $pdo->prepare('INSERT INTO mobile_menu_settings (name, payload, is_active) VALUES (:name, :payload, 1)');
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
        if (array_key_exists('title', $payload)) {
            $title = trim((string) $payload['title']);
            $normalized['title'] = $title !== '' ? $title : $default['title'];
        }

        if (isset($payload['tab_bar']) && is_array($payload['tab_bar'])) {
            $tabBar = self::normalizeTabBar($payload['tab_bar'], $default['tab_bar'] ?? self::defaultTabBar());
            if ($tabBar !== []) {
                $normalized['tab_bar'] = $tabBar;
            }
        }

        if (isset($payload['desktop_nav']) && is_array($payload['desktop_nav'])) {
            $desktopNav = self::normalizeNavItems($payload['desktop_nav'], $default['desktop_nav'] ?? self::defaultDesktopNav());
            if ($desktopNav !== []) {
                $normalized['desktop_nav'] = $desktopNav;
            }
        }

        if (isset($payload['sections']) && is_array($payload['sections'])) {
            $sections = [];
            foreach ($payload['sections'] as $section) {
                if (!is_array($section)) {
                    continue;
                }
                $sectionTitle = trim((string) ($section['title'] ?? ''));
                $items = self::normalizeMenuItems((array) ($section['items'] ?? []));
                if ($items === []) {
                    continue;
                }
                $layout = strtolower(trim((string) ($section['layout'] ?? '')));
                if (!in_array($layout, ['grid', 'list'], true)) {
                    $layout = $sectionTitle === '' ? 'grid' : 'list';
                }
                $sections[] = [
                    'title' => $sectionTitle,
                    'layout' => $layout,
                    'items' => $items,
                ];
            }
            if ($sections !== []) {
                $normalized['sections'] = $sections;
            }
        }

        if (array_key_exists('product_banner_base', $payload)) {
            $base = trim((string) $payload['product_banner_base']);
            if ($base !== '') {
                $normalized['product_banner_base'] = $base;
            }
        }

        if (isset($payload['product_banners']) && is_array($payload['product_banners'])) {
            $banners = [];
            foreach ($payload['product_banners'] as $banner) {
                if (!is_array($banner)) {
                    continue;
                }
                if (array_key_exists('enabled', $banner) && !$banner['enabled']) {
                    continue;
                }
                $aria = trim((string) ($banner['aria'] ?? $banner['aria_label'] ?? $banner['label'] ?? ''));
                $img = trim((string) ($banner['img'] ?? $banner['image'] ?? $banner['image_url'] ?? ''));
                if ($aria === '' || $img === '') {
                    continue;
                }
                $row = [
                    'aria' => $aria,
                    'img' => $img,
                    'alt' => trim((string) ($banner['alt'] ?? $aria)),
                    'enabled' => true,
                ];
                if (!empty($banner['login_gate']) || !empty($banner['requires_login'])) {
                    $row['login_gate'] = true;
                } else {
                    $href = trim((string) ($banner['href'] ?? ''));
                    if ($href === '') {
                        continue;
                    }
                    $row['href'] = $href;
                }
                $onclick = trim((string) ($banner['onclick'] ?? ''));
                if ($onclick !== '') {
                    $row['onclick'] = $onclick;
                }
                $banners[] = $row;
            }
            if ($banners !== []) {
                $normalized['product_banners'] = $banners;
            }
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<array<string, mixed>> $fallback
     * @return list<array<string, mixed>>
     */
    private static function normalizeTabBar(array $items, array $fallback): array
    {
        $allowedTypes = ['link', 'button', 'menu'];
        $normalized = [];

        foreach ($items as $item) {
            if (!is_array($item) || empty($item['enabled'])) {
                continue;
            }
            $type = strtolower(trim((string) ($item['type'] ?? 'link')));
            if (!in_array($type, $allowedTypes, true)) {
                $type = 'link';
            }
            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $row = [
                'type' => $type,
                'label' => $label,
                'href' => trim((string) ($item['href'] ?? '')),
                'icon' => trim((string) ($item['icon'] ?? '')),
                'badge' => trim((string) ($item['badge'] ?? '')),
                'enabled' => true,
                'aria_label' => trim((string) ($item['aria_label'] ?? $label)),
            ];
            $id = trim((string) ($item['id'] ?? ''));
            if ($id !== '') {
                $row['id'] = $id;
            } elseif ($type === 'menu') {
                $row['id'] = 'menu-toggle';
            } elseif ($type === 'button') {
                $row['id'] = 'mob-bet-kupon';
            }
            $normalized[] = $row;
        }

        return $normalized !== [] ? $normalized : $fallback;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private static function normalizeMenuItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = trim((string) ($item['label'] ?? ''));
            $href = trim((string) ($item['href'] ?? ''));
            if ($label === '' || $href === '') {
                continue;
            }
            $target = (string) ($item['target'] ?? '_self');
            $normalized[] = [
                'label' => $label,
                'href' => $href,
                'icon' => trim((string) ($item['icon'] ?? '')),
                'badge' => trim((string) ($item['badge'] ?? '')),
                'target' => in_array($target, ['_self', '_blank'], true) ? $target : '_self',
                'enabled' => (bool) ($item['enabled'] ?? true),
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<array<string, mixed>> $fallback
     * @return list<array<string, mixed>>
     */
    private static function normalizeNavItems(array $items, array $fallback): array
    {
        $fallbackByHref = [];
        foreach ($fallback as $fallbackItem) {
            if (!is_array($fallbackItem)) {
                continue;
            }
            $fallbackHref = trim((string) ($fallbackItem['href'] ?? ''));
            if ($fallbackHref !== '') {
                $fallbackByHref[$fallbackHref] = $fallbackItem;
            }
        }

        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['enabled'])) {
                continue;
            }
            $label = trim((string) ($item['label'] ?? ''));
            $href = trim((string) ($item['href'] ?? ''));
            if ($label === '' || $href === '') {
                continue;
            }
            $icon = trim((string) ($item['icon'] ?? ''));
            if ($icon === '' && isset($fallbackByHref[$href])) {
                $icon = trim((string) ($fallbackByHref[$href]['icon'] ?? ''));
            }
            $normalized[] = [
                'label' => $label,
                'href' => $href,
                'icon' => $icon,
                'enabled' => true,
            ];
        }

        return $normalized !== [] ? $normalized : $fallback;
    }
}
