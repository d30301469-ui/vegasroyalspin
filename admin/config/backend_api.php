<?php
/**
 * Backend REST API taban adresleri (sonunda / olmadan).
 * Varsayılan bağlantı bu repo içindeki admin üye API’sidir: {SITE_URL}/admin/api/v2.
 * Ortam bazlı ayrıştırma gerekiyorsa API_BACKEND_* env değerleri ile override edin.
 *
 * MAIN base URL: tam kök veya …/api/v2 — örn. https://site.test/admin/api/v2
 * (Sonda / yok.) Kök …/api/v2 ise slider isteği /sliders.php ile birleşir; yalnızca domain ise /api/v2/sliders.php.
 *
 * Önerilen uç nokta örnekleri (MAIN):
 *   GET  /site_settings.php | /site-settings | /site-settings.php (public; bakim_modu dahil)
 *   POST /login.php           JSON { login, password } — envelope (api.md üye API)
 *   POST /forgot_password.php JSON { email } — şifre sıfırlama isteği (public envelope)
 *   POST /reset_password.php   JSON { token, password, password_confirmation } — şifre sıfırlama onayı (public envelope)
 *   POST /password_reset.php   JSON { action: request|forgot|confirm|reset, … } — forgot + reset tek uç (public envelope)
 *   POST /password_update.php  Bearer üye JWT; JSON { current_password, password, password_confirmation } — yeni token (envelope)
 *   GET/POST /email_verification.php — action request|resend|confirm|verify; GET’te yalnızca token ise confirm (public envelope)
 *   POST /register.php        JSON üye kayıt — envelope (201, data.token; api.md üye API)
 *   POST /auth/register       (legacy; tercihen /register.php)
 *   POST /auth/check-availability { username?, email? } -> { username: bool, email: bool }
 *   GET  /users/by-username?username=
 *   GET  /users/by-id?id=
 *   GET  /users/profile?username=
 *   GET  /users/balance?username=  -> { ana_bakiye }
 *   POST /payments/megapayz/log  { user_id, username, method, amount, trx, status }
 *
 * AFFILIATE:
 *   GET /resolve-referral?ip=
 *   GET /affiliate/by-code?code=
 *   POST /registrations { affiliate_id, user_id, username, email, ip_address }
 *
 * PAYMENT_CALLBACK (yatırım callback; eski Callback DB):
 *   GET  /users/by-id?id=
 *   POST /users/balance-adjust { user_id, amount }
 *   POST /deposits/parayatir   { user_id, uye, miktar, tur, referans, tarih, durum, aciklama, token, adsoyad }
 *
 * CASINO_WALLET (ApiGate seamless – ham istek iletilebilir):
 *   POST /wallet/seamless  (ApiGate JSON gövdesi aynen; yanıt aynen döner)
 *   GET  /games/by-code?code=  -> { vendor_code, vendor_name, game_name, ... }
 *
 * GAMES (BGaming katalog, launch API'leri; boşsa MAIN kullanılır):
 *   GET /games.php?search=&page=&limit=&game_type=0&popular=… (slot listesi; envelope)
 *   GET /games_provider.php
 *   POST /game_launch.php
 *   GET /game_history.php
 *   GET /winners.php
 *
 * SSL (curl): Önce API_BACKEND_CURL_CAINFO (dosya yolu), yoksa config/cacert.pem kullanılır; doğrulama yine başarısızsa sunucunun sistem CA deposuyla otomatik ikinci deneme yapılır. Ayrıca outbound istekler IPv4 ile yapılır (bazı sunucularda kırık IPv6 bağlantı hatası önlenir).
 *
 * Slider (views/partials/slider.php → api/Sliders.php):
 *   • API_BACKEND_MAIN_BASE_URL — varsayılan {SITE_URL}/admin/api/v2; üzerinde MemberApiPaths::SLIDERS yolları denenir; gerekirse /content/sliders (legacy).
 *   • API_BACKEND_SLIDER_BASE_URL — yalnızca ayrı slider backend’i varsa doldurun.
 *   • SITE_URL (config/app.php) — ApiBases yedek adayları için kullanılır.
 *   • Göreli görsel/link yolları SITE_URL ile birleştirilir.
 */

if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', __DIR__);
}

if (!function_exists('deploy_domain') && is_file(__DIR__ . '/deploy_domains.php')) {
    require_once __DIR__ . '/deploy_domains.php';
}

if (!function_exists('frontend_env_string')) {
    function frontend_env_string(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : $default;
    }
}

if (!function_exists('frontend_backend_api_is_production')) {
    function frontend_backend_api_is_production(): bool
    {
        return in_array(strtolower(frontend_env_string('APP_ENV', 'development')), ['production', 'prod'], true);
    }
}

if (!function_exists('frontend_assert_production_backend_api_url')) {
    function frontend_assert_production_backend_api_url(string $key, string $value): void
    {
        if (!frontend_backend_api_is_production() || trim($value) === '') {
            return;
        }

        $host = strtolower((string) (parse_url($value, PHP_URL_HOST) ?: ''));
        if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || str_ends_with($host, '.test')) {
            throw new RuntimeException(sprintf('Production %s must resolve to a public host.', $key));
        }
    }
}

if (!function_exists('frontend_default_member_api_base_url')) {
    function frontend_default_member_api_base_url(): string
    {
        $fallbackBackendBase = frontend_env_string('API_BACKEND_FALLBACK_BASE_URL', defined('BACKEND_API_BASE_URL') ? (string) BACKEND_API_BASE_URL : deploy_domain('api_public_base_url'));
        if (frontend_backend_api_is_production()) {
            return rtrim($fallbackBackendBase, '/');
        }

        $publicHosts = array_filter(array_map('trim', explode(',', frontend_env_string('PUBLIC_URL_HOSTS', deploy_domain('public_url_hosts')))));
        $requestHost = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
        $requestHost = preg_replace('/:\d+$/', '', $requestHost);
        if (in_array($requestHost, $publicHosts, true)) {
            return rtrim($fallbackBackendBase, '/');
        }

        $site = defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '';
        if ($site === '') {
            return rtrim($fallbackBackendBase, '/');
        }

        $parts = parse_url($site);
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
        if (in_array($host, $publicHosts, true) || str_ends_with($host, '.test')) {
            return rtrim($fallbackBackendBase, '/');
        }

        return rtrim($fallbackBackendBase, '/');
    }
}

if (!function_exists('frontend_coerce_public_api_url')) {
    function frontend_coerce_public_api_url(string $url, ?string $fallback = null): string
    {
        $url = rtrim(trim($url), '/');
        $fallback = rtrim(trim((string) ($fallback ?? deploy_domain('backend_api_base_url'))), '/');
        if ($url === '') {
            return $fallback;
        }

        $runtimeHost = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
        $runtimeHost = preg_replace('/:\\d+$/', '', $runtimeHost) ?? '';
        $runtimeLooksPublic = $runtimeHost !== ''
            && $runtimeHost !== 'localhost'
            && $runtimeHost !== '127.0.0.1'
            && !str_ends_with($runtimeHost, '.test')
            && !str_ends_with($runtimeHost, '.local');

        if ($runtimeLooksPublic) {
            $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
            if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || str_ends_with($host, '.test') || str_ends_with($host, '.local')) {
                if ($fallback !== '' && $url !== $fallback) {
                    error_log('[metropol] Runtime host is public (' . $runtimeHost . '), coercing API backend URL "' . $url . '" to fallback ' . $fallback);
                }

                return $fallback !== '' ? $fallback : $url;
            }
        }

        // Local .test / localhost URLs are valid in non-production; only reject them in production.
        if (!frontend_backend_api_is_production()) {
            return $url;
        }

        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        if (
            $host === ''
            || $host === 'localhost'
            || $host === '127.0.0.1'
            || str_ends_with($host, '.test')
        ) {
            if ($fallback !== '' && $url !== $fallback) {
                error_log('[metropol] Invalid API backend URL "' . $url . '"; using fallback ' . $fallback);
            }

            return $fallback !== '' ? $fallback : $url;
        }

        return $url;
    }
}

/** Ana üye API: mevcut proje içindeki admin/api/v2. Env ile override edilebilir. */
if (!defined('API_BACKEND_MAIN_BASE_URL')) {
    $rawMainApi = frontend_coerce_public_api_url(
        frontend_env_string('API_BACKEND_MAIN_BASE_URL', frontend_default_member_api_base_url())
    );
    if (!function_exists('metropol_normalize_member_api_public_url') && is_readable(__DIR__ . '/member_api_public.php')) {
        require_once __DIR__ . '/member_api_public.php';
    }
    define('API_BACKEND_MAIN_BASE_URL', function_exists('metropol_normalize_member_api_public_url')
        ? metropol_normalize_member_api_public_url($rawMainApi)
        : $rawMainApi);
}

if (!defined('API_BACKEND_FALLBACK_BASE_URL')) {
    $fallbackRaw = frontend_coerce_public_api_url(
        frontend_env_string(
            'API_BACKEND_FALLBACK_BASE_URL',
            frontend_env_string('BACKEND_API_BASE_URL', deploy_domain('backend_url') . '/api/v2')
        ),
        deploy_domain('api_public_base_url')
    );
    define('API_BACKEND_FALLBACK_BASE_URL', function_exists('metropol_normalize_member_api_public_url')
        ? metropol_normalize_member_api_public_url($fallbackRaw)
        : $fallbackRaw);
}

/** Slider GET (public); ayrı backend yoksa boş kalır ve MAIN kullanılır. */
if (!defined('API_BACKEND_SLIDER_BASE_URL')) {
    define('API_BACKEND_SLIDER_BASE_URL', frontend_env_string('API_BACKEND_SLIDER_BASE_URL', ''));
}

if (!defined('API_BACKEND_AFFILIATE_BASE_URL')) {
    define('API_BACKEND_AFFILIATE_BASE_URL', frontend_env_string('API_BACKEND_AFFILIATE_BASE_URL', ''));
}

if (!defined('API_BACKEND_CASINO_WALLET_BASE_URL')) {
    define('API_BACKEND_CASINO_WALLET_BASE_URL', frontend_env_string('API_BACKEND_CASINO_WALLET_BASE_URL', ''));
}

if (!defined('API_BACKEND_PAYMENT_CALLBACK_BASE_URL')) {
    define('API_BACKEND_PAYMENT_CALLBACK_BASE_URL', frontend_env_string('API_BACKEND_PAYMENT_CALLBACK_BASE_URL', ''));
}

if (!defined('API_BACKEND_GAMES_BASE_URL')) {
    define('API_BACKEND_GAMES_BASE_URL', frontend_env_string('API_BACKEND_GAMES_BASE_URL', ''));
}

/** Örn. "Bearer xxx" veya özel başlık değeri; boşsa Authorization eklenmez */
if (!defined('API_BACKEND_AUTH_HEADER')) {
    define('API_BACKEND_AUTH_HEADER', frontend_env_string('API_BACKEND_AUTH_HEADER', ''));
}

/** İsteğe bağlı: curl için özel CA paketi yolu (boş bırakılırsa config/cacert.pem veya sistem CA) */
if (!defined('API_BACKEND_CURL_CAINFO')) {
    define('API_BACKEND_CURL_CAINFO', frontend_env_string('API_BACKEND_CURL_CAINFO', ''));
}

/**
 * Server-side curl base (Apache same-server: http://127.0.0.1/api/v2 + Host header).
 * Public API_BACKEND_MAIN_BASE_URL stays https://bo-backoffice.site/api/v2 for diagnostics.
 */
if (!defined('API_BACKEND_INTERNAL_BASE_URL')) {
    define('API_BACKEND_INTERNAL_BASE_URL', frontend_env_string('API_BACKEND_INTERNAL_BASE_URL', ''));
}

if (!defined('API_BACKEND_INTERNAL_HOST')) {
    $internalHost = frontend_env_string('API_BACKEND_INTERNAL_HOST', '');
    if ($internalHost === '' && defined('BACKEND_HOST') && BACKEND_HOST !== '') {
        $internalHost = (string) BACKEND_HOST;
    }
    if ($internalHost === '') {
        $internalHost = strtolower((string) (parse_url(
            frontend_env_string('BACKEND_URL', deploy_domain('backend_url')),
            PHP_URL_HOST
        ) ?: 'bo-backoffice.site'));
    }
    if (str_starts_with($internalHost, 'api.')) {
        $internalHost = substr($internalHost, 4);
    }
    define('API_BACKEND_INTERNAL_HOST', $internalHost);
}

frontend_assert_production_backend_api_url('API_BACKEND_MAIN_BASE_URL', (string) API_BACKEND_MAIN_BASE_URL);
frontend_assert_production_backend_api_url('API_BACKEND_SLIDER_BASE_URL', (string) API_BACKEND_SLIDER_BASE_URL);
frontend_assert_production_backend_api_url('API_BACKEND_AFFILIATE_BASE_URL', (string) API_BACKEND_AFFILIATE_BASE_URL);
frontend_assert_production_backend_api_url('API_BACKEND_CASINO_WALLET_BASE_URL', (string) API_BACKEND_CASINO_WALLET_BASE_URL);
frontend_assert_production_backend_api_url('API_BACKEND_PAYMENT_CALLBACK_BASE_URL', (string) API_BACKEND_PAYMENT_CALLBACK_BASE_URL);
frontend_assert_production_backend_api_url('API_BACKEND_GAMES_BASE_URL', (string) API_BACKEND_GAMES_BASE_URL);
