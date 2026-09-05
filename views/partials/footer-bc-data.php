<?php
/** Footer data — admin/API managed payload with local defaults */

if (!defined('API_PATH')) {
    require_once dirname(__DIR__, 2) . '/api/bootstrap.php';
} elseif (!class_exists('ApiFooter', false)) {
    require_once API_PATH . '/bootstrap.php';
}

// Tek kaynak: ApiFooter::fetch(). API-only hostlarda zaten uzak /content/footer'ı
// ("footer" cache anahtarıyla) okur; DB'ye izin verilen hostlarda footer_settings
// tablosunu okur. Eskiden burada ikinci bir "footer_render" cache anahtarıyla uzak
// veri array_replace ile üste yazılıyordu; iki anahtar desenkron olduğunda taze veri
// bayat remote kopyayla eziliyordu (adminden yapılan değişiklikler frontend'e
// yansımıyordu). Bu yüzden overlay kaldırıldı — ikinci bir okuma eklemeyin.
$footerPayload = ApiFooter::fetch();

$footerSocialIcons = is_array($footerPayload['social_icons'] ?? null)
    ? $footerPayload['social_icons']
    : [];

// Site Ayarları → İletişim linklerini footer sosyal ikonlarına yedir (boş/eksik network'ler için).
$footerContactLinks = is_array($siteContactLinks ?? null)
    ? $siteContactLinks
    : (class_exists('ApiSiteSettings') ? ApiSiteSettings::normalizeContactLinks(is_array($ayar ?? null) ? $ayar : []) : []);
$footerContactSocialFallbacks = [
    'telegram' => trim((string) ($footerContactLinks['telegram_url'] ?? '')),
    'whatsapp' => trim((string) ($footerContactLinks['whatsapp_url'] ?? '')),
    'phone' => trim((string) ($footerContactLinks['contact_phone'] ?? '')),
];
if ($footerContactSocialFallbacks['phone'] !== '') {
    $footerContactSocialFallbacks['phone'] = 'tel:' . preg_replace('/[^0-9+]/', '', $footerContactSocialFallbacks['phone']);
}
$footerSocialByNetwork = [];
foreach ($footerSocialIcons as $index => $icon) {
    if (!is_array($icon)) {
        continue;
    }
    $network = strtolower(trim((string) ($icon['network'] ?? '')));
    if ($network === '') {
        continue;
    }
    $footerSocialByNetwork[$network] = $index;
    $url = trim((string) ($icon['url'] ?? ''));
    if (($url === '' || $url === '#' || str_starts_with($url, 'javascript:')) && isset($footerContactSocialFallbacks[$network]) && $footerContactSocialFallbacks[$network] !== '') {
        $footerSocialIcons[$index]['url'] = $footerContactSocialFallbacks[$network];
    }
}
foreach ($footerContactSocialFallbacks as $network => $url) {
    if ($url === '' || isset($footerSocialByNetwork[$network])) {
        continue;
    }
    $footerSocialIcons[] = [
        'network' => $network,
        'url' => $url,
    ];
}
unset($footerContactLinks, $footerContactSocialFallbacks, $footerSocialByNetwork, $index, $icon, $network, $url);
$footerMenuColumns = is_array($footerPayload['menu_columns'] ?? null)
    ? $footerPayload['menu_columns']
    : [];
$footerPayments = is_array($footerPayload['payments'] ?? null)
    ? $footerPayload['payments']
    : [];
$footerLicenceRows = is_array($footerPayload['licence_rows'] ?? null)
    ? $footerPayload['licence_rows']
    : [];

$footerLicenceItemBlocked = static function (array $item): bool {
    $type = strtolower(trim((string) ($item['type'] ?? '')));
    $blob = strtolower(trim((string) ($item['src'] ?? '')) . ' '
        . trim((string) ($item['href'] ?? '')) . ' '
        . trim((string) ($item['html'] ?? '')));
    foreach (['casinomilyon', 'deluxebahis', 'haleon', 'metropolcasino', 'maltabet'] as $needle) {
        if ($needle !== '' && str_contains($blob, $needle)) {
            return true;
        }
    }
    if ($type === 'iframe' && trim((string) ($item['src'] ?? '')) === '') {
        return true;
    }

    return false;
};

$footerSanitizeLicenceRows = static function (array $rows) use ($footerLicenceItemBlocked): array {
    return array_values(array_filter(array_map(
        static function ($row) use ($footerLicenceItemBlocked): array {
            if (!is_array($row)) {
                return [];
            }
            $clean = [];
            foreach ($row as $item) {
                if (!is_array($item) || $footerLicenceItemBlocked($item)) {
                    continue;
                }
                $clean[] = $item;
            }

            return $clean;
        },
        $rows
    ), static fn (array $row): bool => $row !== []));
};

$footerLicenceRows = $footerSanitizeLicenceRows($footerLicenceRows);

if ($footerLicenceRows === []) {
    $manifestPath = dirname(__DIR__, 2) . '/assets/images/footer/manifest.json';
    if (is_readable($manifestPath)) {
        $manifestRaw = file_get_contents($manifestPath);
        if (is_string($manifestRaw)) {
            $manifest = json_decode($manifestRaw, true);
            if (is_array($manifest['licence_rows'] ?? null)) {
                $footerLicenceRows = $footerSanitizeLicenceRows($manifest['licence_rows']);
            }
        }
    }
}

unset($footerLicenceItemBlocked, $footerSanitizeLicenceRows);

// Yerel GCB mührü: iframe widget yerine doğrudan img — boyut/radius kontrolü için.
$footerLicenceSealHref = '/cert.gcb.cw/';
$footerLicenceSealSrc = '/cert.gcb.cw/asset/1c0246df-1aa7-485a-a24c-21ae5e730000';
foreach ($footerLicenceRows as $rowIndex => $row) {
    if (!is_array($row)) {
        continue;
    }
    foreach ($row as $itemIndex => $item) {
        if (!is_array($item)) {
            continue;
        }
        $licenceType = strtolower(trim((string) ($item['type'] ?? '')));
        if ($licenceType !== 'iframe') {
            continue;
        }
        $licenceSrc = strtolower(trim((string) ($item['src'] ?? '')));
        if ($licenceSrc === '' || !str_contains($licenceSrc, 'licence-widget')) {
            continue;
        }
        $footerLicenceRows[$rowIndex][$itemIndex] = [
            'type' => 'licence_seal',
            'href' => $footerLicenceSealHref,
            'src' => $footerLicenceSealSrc,
            'alt' => 'GCB Digital Seal',
        ];
    }
}
unset($rowIndex, $row, $itemIndex, $item, $licenceType, $licenceSrc, $footerLicenceSealHref, $footerLicenceSealSrc);

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
$footerSiteName = (string) ($footerPayload['site_name'] ?? $footerBranding['site_name'] ?? $ayar['site_adi'] ?? 'VegasRoyalSpin');
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
