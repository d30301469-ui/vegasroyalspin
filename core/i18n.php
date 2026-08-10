<?php

/**
 * Lightweight site i18n (tr / en / de / ru).
 * Resolve: ?lang= → cookie site_lang → default tr
 */

if (!defined('SITE_I18N_LOADED')) {
    define('SITE_I18N_LOADED', true);
}

final class SiteI18n
{
    public const COOKIE = 'site_lang';
    public const DEFAULT_LOCALE = 'tr';

    /** @var list<string> */
    public const ALLOWED = ['tr', 'en', 'de', 'ru'];

    /** @var array<string, string> */
    private static array $intlLocales = [
        'tr' => 'tr-TR',
        'en' => 'en-GB',
        'de' => 'de-DE',
        'ru' => 'ru-RU',
    ];

    /** @var array<string, string> */
    private static array $langCodes = [
        'tr' => 'TUR',
        'en' => 'ENG',
        'de' => 'DEU',
        'ru' => 'RUS',
    ];

    /** @var array<string, string> */
    private static array $flagClasses = [
        'tr' => 'flag-icon-tr',
        'en' => 'flag-icon-us',
        'de' => 'flag-icon-de',
        'ru' => 'flag-icon-ru',
    ];

    private static ?string $locale = null;

    /** @var array<string, string>|null */
    private static ?array $catalog = null;

    /** @var array<string, string>|null */
    private static ?array $fallbackCatalog = null;

    public static function boot(): void
    {
        if (self::$locale !== null) {
            return;
        }
        $fromQuery = self::normalize((string) ($_GET['lang'] ?? ''));
        $fromCookie = self::normalize((string) ($_COOKIE[self::COOKIE] ?? ''));
        $locale = $fromQuery !== '' ? $fromQuery : ($fromCookie !== '' ? $fromCookie : self::DEFAULT_LOCALE);
        self::$locale = $locale;
        if ($fromQuery !== '' && $fromQuery !== $fromCookie) {
            self::persistCookie($fromQuery);
        }
        self::loadCatalogs($locale);
    }

    public static function locale(): string
    {
        self::boot();
        return self::$locale ?? self::DEFAULT_LOCALE;
    }

    public static function intlLocale(): string
    {
        $loc = self::locale();
        return self::$intlLocales[$loc] ?? self::$intlLocales[self::DEFAULT_LOCALE];
    }

    public static function langCode(): string
    {
        $loc = self::locale();
        return self::$langCodes[$loc] ?? 'TUR';
    }

    public static function flagClass(): string
    {
        $loc = self::locale();
        return self::$flagClasses[$loc] ?? 'flag-icon-tr';
    }

    /**
     * Build current-path URL with lang query (preserves other params).
     */
    public static function switchUrl(string $lang): string
    {
        $lang = self::normalize($lang);
        if ($lang === '') {
            $lang = self::DEFAULT_LOCALE;
        }
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $parts = parse_url($uri);
        $path = (string) ($parts['path'] ?? '/');
        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }
        $query['lang'] = $lang;
        $qs = http_build_query($query);
        $hash = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
        return $path . ($qs !== '' ? '?' . $qs : '') . $hash;
    }

    /**
     * Translate a UI label from CMS/menu (TR source) using known maps + href hints.
     */
    public static function label(string $text, string $href = ''): string
    {
        self::boot();
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $norm = self::normalizeLabelKey($text);
        $map = self::labelKeyMap();
        if (isset($map[$norm])) {
            return self::translate($map[$norm]);
        }
        $hrefKey = self::hrefToKey($href);
        if ($hrefKey !== '') {
            return self::translate($hrefKey);
        }
        return $text;
    }

    /**
     * Merged messages for JS (current locale with TR fallback).
     *
     * @return array<string, string>
     */
    public static function jsMessages(): array
    {
        self::boot();
        $keys = array_unique(array_merge(
            array_keys(self::$fallbackCatalog ?? []),
            array_keys(self::$catalog ?? [])
        ));
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = self::translate($key);
        }
        return $out;
    }

    /**
     * @return array<string, string>
     */
    private static function labelKeyMap(): array
    {
        return [
            'spor' => 'menu.sports',
            'sports' => 'menu.sports',
            'sport' => 'menu.sports',
            'casino' => 'menu.casino',
            'casİno' => 'menu.casino',
            'slot' => 'menu.slots',
            'slots' => 'menu.slots',
            'canli casino' => 'menu.live_casino',
            'canlı casino' => 'menu.live_casino',
            'live casino' => 'menu.live_casino',
            'livecasino' => 'menu.live_casino',
            'menu' => 'menu.menu',
            'menü' => 'menu.menu',
            'bgaming' => 'menu.bgaming',
            'turnuvalar' => 'menu.tournaments',
            'tournaments' => 'menu.tournaments',
            'beni ara' => 'menu.callback',
            'callback' => 'menu.callback',
            'promosyonlar' => 'menu.promotions',
            'promotions' => 'menu.promotions',
            'bonus talep' => 'menu.bonus_request',
            'canli destek' => 'nav.live_support',
            'canlı destek' => 'nav.live_support',
            'live support' => 'nav.live_support',
            'whatsapp' => 'menu.whatsapp',
            'spor bahisleri' => 'menu.sportsbook',
            'ortaklik' => 'nav.partnership',
            'ortaklık' => 'nav.partnership',
            'partnership' => 'nav.partnership',
            'partnerschaft' => 'nav.partnership',
            'партнёрство' => 'nav.partnership',
            'партнерство' => 'nav.partnership',
            'yeni' => 'badge.new',
            'özel' => 'badge.special',
            'ozel' => 'badge.special',
            'en iyi' => 'badge.best',
            'promosyon' => 'badge.promo',
            'giriş' => 'nav.login',
            'giris' => 'nav.login',
            'kayit' => 'nav.register',
            'kayıt' => 'nav.register',
            'para yatır' => 'nav.deposit',
            'para yatir' => 'nav.deposit',
            'ana sayfa' => 'footer.home',
            'home' => 'footer.home',
            'destek' => 'footer.support',
            'support' => 'footer.support',
        ];
    }

    private static function hrefToKey(string $href): string
    {
        $path = strtolower(trim(parse_url($href, PHP_URL_PATH) ?: $href));
        $path = rtrim($path, '/') ?: '/';
        return match ($path) {
            '/sportbook', '/sports', '/sport' => 'menu.sports',
            '/slot', '/casino', '/slots' => 'menu.casino',
            '/livecasino', '/live-casino', '/canli-casino' => 'menu.live_casino',
            '/bgaming' => 'menu.bgaming',
            '/turnuvalar', '/tournaments' => 'menu.tournaments',
            '/beni-ara', '/callback' => 'menu.callback',
            '/promotions', '/promosyonlar', '/promosyon' => 'menu.promotions',
            '/bonustalep', '/bonus-talep' => 'menu.bonus_request',
            '/ortaklik', '/partnership', '/affiliate' => 'nav.partnership',
            '/' => 'footer.home',
            default => '',
        };
    }

    private static function normalizeLabelKey(string $text): string
    {
        $text = function_exists('mb_strtolower')
            ? mb_strtolower(trim($text), 'UTF-8')
            : strtolower(trim($text));
        $text = str_replace(['İ', 'I'], ['i', 'i'], $text);
        if (function_exists('mb_strtolower')) {
            $text = mb_strtolower($text, 'UTF-8');
        }
        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }

    /**
     * @param array<string, string|int|float> $replace
     */
    public static function translate(string $key, array $replace = []): string
    {
        self::boot();
        $catalog = self::$catalog ?? [];
        $fallback = self::$fallbackCatalog ?? [];
        $text = $catalog[$key] ?? $fallback[$key] ?? $key;
        if ($replace !== []) {
            foreach ($replace as $k => $v) {
                $text = str_replace(':' . $k, (string) $v, $text);
            }
        }
        return $text;
    }

    /**
     * Flat bag for JS (selected keys or full catalog).
     *
     * @param list<string>|null $keys
     * @return array{locale: string, intl: string, code: string, flag: string, messages: array<string, string>}
     */
    public static function jsPayload(?array $keys = null): array
    {
        self::boot();
        $messages = self::$catalog ?? [];
        if ($keys !== null) {
            $filtered = [];
            foreach ($keys as $key) {
                $filtered[$key] = self::translate($key);
            }
            $messages = $filtered;
        }
        return [
            'locale' => self::locale(),
            'intl' => self::intlLocale(),
            'code' => self::langCode(),
            'flag' => self::flagClass(),
            'messages' => $messages,
        ];
    }

    public static function normalize(string $lang): string
    {
        $lang = strtolower(trim($lang));
        return in_array($lang, self::ALLOWED, true) ? $lang : '';
    }

    private static function persistCookie(string $locale): void
    {
        if (headers_sent()) {
            return;
        }
        $secure = function_exists('request_is_https')
            ? request_is_https()
            : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
        setcookie(self::COOKIE, $locale, [
            'expires' => time() + 60 * 60 * 24 * 365,
            'path' => '/',
            'secure' => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $locale;
    }

    private static function loadCatalogs(string $locale): void
    {
        $langDir = defined('BASE_PATH') ? (BASE_PATH . '/lang') : (dirname(__DIR__) . '/lang');
        $trFile = $langDir . '/tr.php';
        $localeFile = $langDir . '/' . $locale . '.php';
        self::$fallbackCatalog = is_readable($trFile) ? (require $trFile) : [];
        if (!is_array(self::$fallbackCatalog)) {
            self::$fallbackCatalog = [];
        }
        if ($locale === 'tr') {
            self::$catalog = self::$fallbackCatalog;
            return;
        }
        $loaded = is_readable($localeFile) ? (require $localeFile) : [];
        self::$catalog = is_array($loaded) ? $loaded : [];
    }
}

if (!function_exists('__')) {
    /**
     * @param array<string, string|int|float> $replace
     */
    function __(string $key, array $replace = []): string
    {
        return SiteI18n::translate($key, $replace);
    }
}

if (!function_exists('current_locale')) {
    function current_locale(): string
    {
        return SiteI18n::locale();
    }
}

if (!function_exists('current_intl_locale')) {
    function current_intl_locale(): string
    {
        return SiteI18n::intlLocale();
    }
}

if (!function_exists('i18n_switch_url')) {
    function i18n_switch_url(string $lang): string
    {
        return SiteI18n::switchUrl($lang);
    }
}

if (!function_exists('i18n_label')) {
    function i18n_label(string $text, string $href = ''): string
    {
        return SiteI18n::label($text, $href);
    }
}
