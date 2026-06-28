<?php
/**
 * Ürün banner verisi — CMS (mobile_menu.product_banners) veya marka-neutral fallback.
 */
if (!isset($loggedIn)) {
    $loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}

if (!function_exists('headerBannerImageSrc')) {
    function headerBannerImageSrc(string $base, string $img): string
    {
        $img = trim($img);
        if ($img === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $img) || str_starts_with($img, '//')) {
            return $img;
        }
        if (str_starts_with($img, '/')) {
            if (class_exists('ApiMediaUrl', false)) {
                return ApiMediaUrl::resolve($img);
            }

            return $img;
        }

        return rtrim($base, '/') . '/' . ltrim($img, '/');
    }
}

if (!class_exists('ApiProductBanners', false)) {
    require_once dirname(__DIR__, 2) . '/api/ProductBanners.php';
}

$productBannerPack = ApiProductBanners::fetch((bool) $loggedIn);
$headerBannerBase = (string) ($productBannerPack['base'] ?? 'assets/images/banners');
$headerBanners = is_array($productBannerPack['items'] ?? null) ? $productBannerPack['items'] : [];
