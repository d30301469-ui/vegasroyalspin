<?php
/** Footer data — admin/API managed payload with local defaults */

if (!defined('API_PATH')) {
    require_once dirname(__DIR__, 2) . '/api/bootstrap.php';
} elseif (!class_exists('ApiFooter', false)) {
    require_once API_PATH . '/bootstrap.php';
}

$footerPayload = ApiFooter::fetch();

// Frontend render katmanında API footer verisini source-of-truth kabul et.
// Split deploy veya local DB/env drift durumlarında admin'deki güncel footer
// değerleri (özellikle copyright/site_name) doğrudan frontend'e yansısın.
if (class_exists('ApiCmsRemote', false)) {
    $remoteEnvelope = ApiCmsRemote::getMainCached('footer_render', ['/content/footer', '/footer.php']);
    if (is_array($remoteEnvelope)) {
        $remoteFooter = is_array($remoteEnvelope['footer'] ?? null)
            ? $remoteEnvelope['footer']
            : $remoteEnvelope;
        if (is_array($remoteFooter) && $remoteFooter !== []) {
            $footerPayload = ApiFooter::normalize(array_replace($footerPayload, $remoteFooter));
        }
    }
}

$footerSocialIcons = is_array($footerPayload['social_icons'] ?? null)
    ? $footerPayload['social_icons']
    : [];
$footerMenuColumns = is_array($footerPayload['menu_columns'] ?? null)
    ? $footerPayload['menu_columns']
    : [];
$footerPayments = is_array($footerPayload['payments'] ?? null)
    ? $footerPayload['payments']
    : [];
$footerLicenceRows = is_array($footerPayload['licence_rows'] ?? null)
    ? $footerPayload['licence_rows']
    : [];

// Lisans bağlantılarını (iframe/external) tamamen kaldır.
$footerLicenceRows = array_values(array_filter(array_map(
    static function ($row): array {
        if (!is_array($row)) {
            return [];
        }

        $clean = [];
        foreach ($row as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = strtolower(trim((string) ($item['type'] ?? '')));
            $src = strtolower(trim((string) ($item['src'] ?? '')));
            $href = strtolower(trim((string) ($item['href'] ?? '')));

            $isLicenceLink = $src !== '' && (
                str_contains($src, 'casinomilyonlisans.com')
                || str_contains($src, 'licence-widget.html')
            );
            $isLicenceHref = $href !== '' && str_contains($href, 'casinomilyonlisans.com');

            if ($type === 'iframe' || $isLicenceLink || $isLicenceHref) {
                continue;
            }

            $clean[] = $item;
        }

        return $clean;
    },
    $footerLicenceRows
), static fn (array $row): bool => $row !== []));

foreach ($footerMenuColumns as $columnIndex => $column) {
    if (!is_array($column)) {
        continue;
    }
    $links = is_array($column['links'] ?? null) ? $column['links'] : [];
    foreach ($links as $linkIndex => $link) {
        if (!is_array($link)) {
            continue;
        }
        $href = trim((string) ($link['href'] ?? ''));
        if ($href === '' || str_starts_with($href, 'javascript:')) {
            $links[$linkIndex]['href'] = ApiFooterPages::hrefForTitle((string) ($link['title'] ?? ''));
        }
    }
    $footerMenuColumns[$columnIndex]['links'] = $links;
}
unset($columnIndex, $column, $linkIndex, $link, $links, $href);

$footerFlagImage = (string) ($footerPayload['flag_image'] ?? '/assets/images/footer/flag-tr.png');
$footerCopyrightSince = (int) ($footerPayload['copyright_since'] ?? 2014);
$footerBranding = is_array($siteBranding ?? null) ? $siteBranding : [];
$footerSiteName = (string) ($footerPayload['site_name'] ?? $footerBranding['site_name'] ?? $ayar['site_adi'] ?? 'MaltaBet');
$footerShowCustomContent = (bool) ($footerPayload['show_custom_content'] ?? true);
$footerSupportBadge = is_array($footerPayload['support_badge'] ?? null)
    ? $footerPayload['support_badge']
    : [];

$footerAbout = is_array($footerPayload['about'] ?? null)
    ? $footerPayload['about']
    : [];
$footerAwards = is_array($footerPayload['awards'] ?? null)
    ? $footerPayload['awards']
    : [];
