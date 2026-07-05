<?php

declare(strict_types=1);

/**
 * Header / homepage product banners — mobile_menu_settings.product_banners (CMS) + static fallback.
 */
final class ApiProductBanners
{
    public static function fetch(bool $loggedIn = false): array
    {
        $defaults = self::defaultRows($loggedIn);
        $base = 'assets/images/banners';

        if (!class_exists('ApiMobileMenu', false) && is_file(dirname(__DIR__) . '/api/MobileMenu.php')) {
            require_once dirname(__DIR__) . '/api/MobileMenu.php';
        }

        $menu = class_exists('ApiMobileMenu', false) ? ApiMobileMenu::fetch() : [];
        $rows = is_array($menu['product_banners'] ?? null) ? $menu['product_banners'] : [];

        if ($rows === []) {
            return [
                'base' => $base,
                'items' => $defaults,
            ];
        }

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (array_key_exists('enabled', $row) && !$row['enabled']) {
                continue;
            }
            $mapped = self::mapRow($row, $loggedIn);
            if ($mapped !== null) {
                $items[] = $mapped;
            }
        }

        if ($items === []) {
            $items = $defaults;
        }

        $cmsBase = trim((string) ($menu['product_banner_base'] ?? ''));
        if ($cmsBase !== '') {
            $base = $cmsBase;
        }

        return [
            'base' => $base,
            'items' => $items,
        ];
    }

    /**
     * @return list<array{href: string, aria: string, img: string, alt: string, onclick?: string|null}>
     */
    public static function defaultRows(bool $loggedIn = false): array
    {
        $supportUrl = self::liveSupportUrl();

        return [
            ['href' => '/slot', 'aria' => 'SLOT', 'img' => 'slot.webp', 'alt' => 'Slot'],
            ['href' => '/sportbook', 'aria' => 'Spor Bahisleri', 'img' => 'spor.webp', 'alt' => 'Spor Bahisleri'],
            ['href' => '/livecasino', 'aria' => 'Canlı Casino', 'img' => 'canlicasino.webp', 'alt' => 'Canlı Casino'],
            ['href' => '/promotions', 'aria' => 'Promosyonlar', 'img' => 'arkadasbonusu.webp', 'alt' => 'Promosyonlar'],
            ['href' => '/beni-ara', 'aria' => 'Aranma Talebi', 'img' => 'cozummerkezi.webp', 'alt' => 'Aranma Talebi'],
            ['href' => $supportUrl, 'aria' => 'Destek', 'img' => 'telegram.webp', 'alt' => 'Canlı Destek'],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{href: string, aria: string, img: string, alt: string, onclick?: string|null}|null
     */
    private static function mapRow(array $row, bool $loggedIn): ?array
    {
        $aria = trim((string) ($row['aria'] ?? $row['aria_label'] ?? $row['label'] ?? ''));
        $alt = trim((string) ($row['alt'] ?? $aria));
        $img = trim((string) ($row['img'] ?? $row['image'] ?? $row['image_url'] ?? ''));
        if ($aria === '' || $img === '') {
            return null;
        }

        if (!empty($row['login_gate']) || !empty($row['requires_login'])) {
            return self::depositRow($loggedIn, $aria, $alt, $img);
        }

        $href = self::resolveHref((string) ($row['href'] ?? '#'));
        if ($href === '') {
            return null;
        }

        $out = [
            'href' => $href,
            'aria' => $aria,
            'img' => self::normalizeImagePath($img),
            'alt' => $alt !== '' ? $alt : $aria,
        ];

        $onclick = trim((string) ($row['onclick'] ?? ''));
        if ($onclick !== '') {
            $out['onclick'] = $onclick;
        }

        return $out;
    }

    /**
     * @return array{href: string, aria: string, img: string, alt: string, onclick?: string|null}
     */
    private static function depositRow(
        bool $loggedIn,
        string $aria = 'Yatırım',
        string $alt = 'Yatırım',
        string $img = 'cozummerkezi.webp'
    ): array {
        return [
            'href' => $loggedIn ? '/profile/deposit-withdraw' : 'javascript:void(0);',
            'aria' => $aria,
            'img' => self::normalizeImagePath($img),
            'alt' => $alt,
            'onclick' => $loggedIn ? null : 'showLoginWarning();',
        ];
    }

    private static function resolveHref(string $href): string
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }
        if ($href === '{{LIVE_SUPPORT_URL}}') {
            return self::liveSupportUrl();
        }
        if ($href === '{{WHATSAPP_URL}}') {
            return self::envUrl('WHATSAPP_URL', 'javascript:void(0);');
        }

        return $href;
    }

    private static function normalizeImagePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path) || str_starts_with($path, '//')) {
            if (class_exists('ApiMediaUrl', false)) {
                return ApiMediaUrl::resolve($path);
            }

            return $path;
        }
        if (str_starts_with($path, '/uploads/') || str_starts_with($path, '/storage/uploads/')) {
            if (class_exists('ApiMediaUrl', false)) {
                return ApiMediaUrl::resolve($path);
            }
        }
        if (str_contains($path, '/')) {
            return ltrim($path, '/');
        }

        return basename($path);
    }

    private static function liveSupportUrl(): string
    {
        global $siteContactLinks;
        if (isset($siteContactLinks['live_support_url']) && trim((string) $siteContactLinks['live_support_url']) !== '') {
            return trim((string) $siteContactLinks['live_support_url']);
        }

        return self::envUrl('LIVE_SUPPORT_URL', '');
    }

    private static function envUrl(string $key, string $default): string
    {
        if (defined($key)) {
            $value = trim((string) constant($key));

            return $value !== '' ? $value : $default;
        }

        $value = getenv($key);
        if ($value === false || trim((string) $value) === '') {
            return $default;
        }

        return trim((string) $value);
    }
}
