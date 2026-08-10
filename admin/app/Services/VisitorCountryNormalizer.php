<?php

declare(strict_types=1);

/**
 * GeoIP ülke adlarını ISO koduna göre tekilleştirir (Turkey / Türkiye → TR).
 */
final class VisitorCountryNormalizer
{
    /** @var array<string, string> ISO2 => görünen ad (TR panel) */
    private const LABELS = [
        'TR' => 'Türkiye',
        'GE' => 'Gürcistan',
        'US' => 'Amerika Birleşik Devletleri',
        'DE' => 'Almanya',
        'RU' => 'Rusya',
        'GB' => 'Birleşik Krallık',
        'AZ' => 'Azerbaycan',
        'NL' => 'Hollanda',
        'FR' => 'Fransa',
        'IT' => 'İtalya',
        'ES' => 'İspanya',
        'CY' => 'Kıbrıs',
        'BG' => 'Bulgaristan',
        'RO' => 'Romanya',
        'UA' => 'Ukrayna',
        'KZ' => 'Kazakistan',
        'IQ' => 'Irak',
        'IR' => 'İran',
        'SA' => 'Suudi Arabistan',
        'AE' => 'Birleşik Arap Emirlikleri',
        'CA' => 'Kanada',
        'AU' => 'Avustralya',
        'SE' => 'İsveç',
        'NO' => 'Norveç',
        'FI' => 'Finlandiya',
        'PL' => 'Polonya',
        'AT' => 'Avusturya',
        'CH' => 'İsviçre',
        'BE' => 'Belçika',
        'GR' => 'Yunanistan',
        'PT' => 'Portekiz',
        'CN' => 'Çin',
        'JP' => 'Japonya',
        'KR' => 'Güney Kore',
        'IN' => 'Hindistan',
        'BR' => 'Brezilya',
        'MX' => 'Meksika',
        'EG' => 'Mısır',
        'PK' => 'Pakistan',
        'AF' => 'Afganistan',
        'SY' => 'Suriye',
        'LB' => 'Lübnan',
        'JO' => 'Ürdün',
        'IL' => 'İsrail',
        'MK' => 'Kuzey Makedonya',
        'AL' => 'Arnavutluk',
        'BA' => 'Bosna-Hersek',
        'RS' => 'Sırbistan',
        'XK' => 'Kosova',
        'MD' => 'Moldova',
        'BY' => 'Belarus',
        'UZ' => 'Özbekistan',
        'TM' => 'Türkmenistan',
        'KG' => 'Kırgızistan',
        'TJ' => 'Tacikistan',
    ];

    /** @var array<string, string> normalize edilmiş ad => ISO2 */
    private const NAME_ALIASES = [
        'turkey' => 'TR',
        'turkiye' => 'TR',
        'türkiye' => 'TR',
        'republic of turkey' => 'TR',
        'georgia' => 'GE',
        'gurcistan' => 'GE',
        'gürcistan' => 'GE',
        'united states' => 'US',
        'united states of america' => 'US',
        'usa' => 'US',
        'u.s.' => 'US',
        'u.s.a.' => 'US',
        'amerika birlesik devletleri' => 'US',
        'amerika birleşik devletleri' => 'US',
        'germany' => 'DE',
        'almanya' => 'DE',
        'deutschland' => 'DE',
        'russia' => 'RU',
        'russian federation' => 'RU',
        'rusya' => 'RU',
        'united kingdom' => 'GB',
        'great britain' => 'GB',
        'uk' => 'GB',
        'birlesik krallik' => 'GB',
        'birleşik krallık' => 'GB',
        'azerbaijan' => 'AZ',
        'azerbaycan' => 'AZ',
        'netherlands' => 'NL',
        'holland' => 'NL',
        'hollanda' => 'NL',
        'france' => 'FR',
        'fransa' => 'FR',
        'italy' => 'IT',
        'italya' => 'IT',
        'spain' => 'ES',
        'ispanya' => 'ES',
        'cyprus' => 'CY',
        'kibris' => 'CY',
        'kıbrıs' => 'CY',
        'bulgaria' => 'BG',
        'bulgaristan' => 'BG',
        'romania' => 'RO',
        'romanya' => 'RO',
        'ukraine' => 'UA',
        'ukrayna' => 'UA',
        'kazakhstan' => 'KZ',
        'kazakistan' => 'KZ',
        'iraq' => 'IQ',
        'irak' => 'IQ',
        'iran' => 'IR',
        'iran islamic republic of' => 'IR',
        'iran (islamic republic of)' => 'IR',
        'saudi arabia' => 'SA',
        'suudi arabistan' => 'SA',
        'united arab emirates' => 'AE',
        'uae' => 'AE',
        'birlesik arap emirlikleri' => 'AE',
        'birleşik arap emirlikleri' => 'AE',
        'canada' => 'CA',
        'kanada' => 'CA',
        'australia' => 'AU',
        'avustralya' => 'AU',
        'sweden' => 'SE',
        'isvec' => 'SE',
        'isveç' => 'SE',
        'norway' => 'NO',
        'norvec' => 'NO',
        'norveç' => 'NO',
        'finland' => 'FI',
        'finlandiya' => 'FI',
        'poland' => 'PL',
        'polonya' => 'PL',
        'austria' => 'AT',
        'avusturya' => 'AT',
        'switzerland' => 'CH',
        'isvicre' => 'CH',
        'isviçre' => 'CH',
        'belgium' => 'BE',
        'belcika' => 'BE',
        'belçika' => 'BE',
        'greece' => 'GR',
        'yunanistan' => 'GR',
        'portugal' => 'PT',
        'portekiz' => 'PT',
        'china' => 'CN',
        'cin' => 'CN',
        'çin' => 'CN',
        'japan' => 'JP',
        'japonya' => 'JP',
        'south korea' => 'KR',
        'korea republic of' => 'KR',
        'korea, republic of' => 'KR',
        'guney kore' => 'KR',
        'güney kore' => 'KR',
        'india' => 'IN',
        'hindistan' => 'IN',
        'brazil' => 'BR',
        'brezilya' => 'BR',
        'mexico' => 'MX',
        'meksika' => 'MX',
        'egypt' => 'EG',
        'misir' => 'EG',
        'mısır' => 'EG',
        'pakistan' => 'PK',
        'afghanistan' => 'AF',
        'afganistan' => 'AF',
        'syria' => 'SY',
        'syrian arab republic' => 'SY',
        'suriye' => 'SY',
        'lebanon' => 'LB',
        'lubnan' => 'LB',
        'lübnan' => 'LB',
        'jordan' => 'JO',
        'urdun' => 'JO',
        'ürdün' => 'JO',
        'israel' => 'IL',
        'israil' => 'IL',
        'israıl' => 'IL',
        'north macedonia' => 'MK',
        'macedonia' => 'MK',
        'kuzey makedonya' => 'MK',
        'albania' => 'AL',
        'arnavutluk' => 'AL',
        'bosnia and herzegovina' => 'BA',
        'bosna-hersek' => 'BA',
        'bosna hersek' => 'BA',
        'serbia' => 'RS',
        'sirbistan' => 'RS',
        'sırbistan' => 'RS',
        'kosovo' => 'XK',
        'kosova' => 'XK',
        'moldova' => 'MD',
        'moldova republic of' => 'MD',
        'belarus' => 'BY',
        'uzbekistan' => 'UZ',
        'ozbekistan' => 'UZ',
        'özbekistan' => 'UZ',
        'turkmenistan' => 'TM',
        'kyrgyzstan' => 'KG',
        'kirgizistan' => 'KG',
        'kırgızistan' => 'KG',
        'tajikistan' => 'TJ',
        'tacikistan' => 'TJ',
    ];

    /**
     * @return array{code: string, label: string, key: string}
     */
    public static function normalize(?string $code, ?string $name): array
    {
        $code = strtoupper(trim((string) $code));
        $name = trim((string) $name);

        if ($code !== '' && isset(self::LABELS[$code])) {
            return ['code' => $code, 'label' => self::LABELS[$code], 'key' => $code];
        }

        $aliasKey = self::aliasKey($name);
        if ($aliasKey !== '' && isset(self::NAME_ALIASES[$aliasKey])) {
            $resolved = self::NAME_ALIASES[$aliasKey];
            return [
                'code' => $resolved,
                'label' => self::LABELS[$resolved] ?? $name,
                'key' => $resolved,
            ];
        }

        if ($code !== '' && preg_match('/^[A-Z]{2}$/', $code) === 1) {
            $label = $name !== '' ? $name : $code;
            return ['code' => $code, 'label' => $label, 'key' => $code];
        }

        if ($name !== '') {
            return ['code' => '', 'label' => $name, 'key' => 'N:' . $aliasKey];
        }

        return ['code' => '', 'label' => 'Bilinmeyen', 'key' => 'N:bilinmeyen'];
    }

    public static function canonicalName(?string $code, ?string $name): string
    {
        return self::normalize($code, $name)['label'];
    }

    /**
     * Ham satırları ülke anahtarına göre birleştirir.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array{country: string, country_code: string, total: int, lat: float, lon: float}>
     */
    public static function mergeCountryRows(array $rows, string $countKey = 'total'): array
    {
        $merged = [];

        foreach ($rows as $row) {
            $norm = self::normalize(
                isset($row['country_code']) ? (string) $row['country_code'] : '',
                (string) ($row['country'] ?? $row['country_name'] ?? $row['label'] ?? '')
            );
            $key = $norm['key'];
            $count = (int) ($row[$countKey] ?? $row['visitors'] ?? $row['total'] ?? 0);
            $lat = (float) ($row['lat'] ?? 0);
            $lon = (float) ($row['lon'] ?? 0);

            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'country' => $norm['label'],
                    'country_code' => $norm['code'],
                    'total' => 0,
                    'visitors' => 0,
                    'lat_sum' => 0.0,
                    'lon_sum' => 0.0,
                    'geo_weight' => 0,
                ];
            }

            $merged[$key]['total'] += $count;
            $merged[$key]['visitors'] += $count;
            if (abs($lat) > 0.0001 || abs($lon) > 0.0001) {
                $merged[$key]['lat_sum'] += $lat * max(1, $count);
                $merged[$key]['lon_sum'] += $lon * max(1, $count);
                $merged[$key]['geo_weight'] += max(1, $count);
            }
        }

        $out = [];
        foreach ($merged as $row) {
            $weight = (int) $row['geo_weight'];
            $out[] = [
                'country' => (string) $row['country'],
                'country_code' => (string) $row['country_code'],
                'country_name' => (string) $row['country'],
                'total' => (int) $row['total'],
                'visitors' => (int) $row['visitors'],
                'lat' => $weight > 0 ? ((float) $row['lat_sum'] / $weight) : 0.0,
                'lon' => $weight > 0 ? ((float) $row['lon_sum'] / $weight) : 0.0,
            ];
        }

        usort($out, static fn (array $a, array $b): int => ((int) $b['total']) <=> ((int) $a['total']));

        return $out;
    }

    private static function aliasKey(string $name): string
    {
        $name = trim(mb_strtolower($name, 'UTF-8'));
        $name = str_replace(['ı', 'İ'], ['i', 'i'], $name);
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return $name;
    }
}
