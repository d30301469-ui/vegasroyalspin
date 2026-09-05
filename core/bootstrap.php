<?php

if (defined('APP_CORE_BOOTSTRAP_LOADED')) {
    return;
}
define('APP_CORE_BOOTSTRAP_LOADED', true);

require_once __DIR__ . '/shell_nav.php';

if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Europe/Istanbul');
}

require_once __DIR__ . '/../config/deploy_domains.php';
$cloudflareBootstrap = __DIR__ . '/../config/cloudflare.php';
if (is_readable($cloudflareBootstrap)) {
    require_once $cloudflareBootstrap;
}

// Determine if this is an API request early so we avoid starting sessions for APIs
$requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$apiRouteParam = isset($_GET['api_route']) && is_string($_GET['api_route']) ? $_GET['api_route'] : '';
$isApiRequest = str_starts_with($requestPath, '/api/')
    || ($apiRouteParam !== '' && str_starts_with($apiRouteParam, '/api/'));

if (!$isApiRequest) {
    require_once __DIR__ . '/../config/frontend_session.php';
    frontend_session_start();
    if (is_readable(CONFIG_PATH . '/member_api_public.php')) {
        require_once CONFIG_PATH . '/member_api_public.php';
    }
    if (
        isset($_GET['logout'])
        && (string) $_GET['logout'] === '1'
    ) {
        if (function_exists('frontend_clear_member_session')) {
            frontend_clear_member_session();
        }
    }
}

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    $bootstrapPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $isTelegramMiniApp = preg_match('#(^|/)tg/?$#', $bootstrapPath) === 1;
    if (!$isTelegramMiniApp) {
        header('X-Frame-Options: SAMEORIGIN');
    }
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (function_exists('request_is_https') ? request_is_https() : (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
    )) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

if (function_exists('register_early_error_handler')) {
    register_early_error_handler();
}

try {
    require_once __DIR__ . '/../config/app.php';
} catch (Throwable $bootException) {
    if (function_exists('render_frontend_boot_error')) {
        render_frontend_boot_error($bootException);
    } elseif (class_exists(\App\Core\ErrorHandler::class, false)) {
        \App\Core\ErrorHandler::handleException($bootException);
    } else {
        http_response_code(503);
        echo 'Configuration error';
    }
    exit;
}

require_once CONFIG_PATH . '/db.php';
require_once SERVICE_PATH . '/BackendApiClient.php';
require_once SERVICE_PATH . '/MemberLoginService.php';
require_once SERVICE_PATH . '/ReferralAttribution.php';
require_once API_PATH . '/bootstrap.php';

// ?ref= ile gelen ziyaretçilerde referans kodunu oturuma/çereze al (kayıt anında kullanılır).
ReferralAttribution::captureFromRequest();
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/i18n.php';
if (class_exists('SiteI18n', false) && !$isApiRequest) {
    SiteI18n::boot();
}

// Register global error handler (also registered early before config/app.php)
if (class_exists('App\Core\ErrorHandler')) {
    \App\Core\ErrorHandler::register();
}

$host = $_SERVER['HTTP_HOST'] ?? '';
$surface = (strpos($host, 'm.') === 0) ? 'mobile' : 'desktop';
// Apex/www hosts (e.g. vegasroyalspin119.com) often receive phone traffic without the
 // m. subdomain. Treat real mobile UAs as the mobile surface so OYNA overlays,
 // play redirect, and mobile chrome stay consistent.
if ($surface === 'desktop' && !$isApiRequest) {
    $surfaceUa = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (
        $surfaceUa !== ''
        && preg_match('/android|iphone|ipad|ipod|mobile|windows phone|opera mini|iemobile/', $surfaceUa) === 1
    ) {
        $surface = 'mobile';
    }
}
if (!defined('SURFACE')) {
    define('SURFACE', $surface);
}
if (!defined('MOBILE_PATH')) {
    define('MOBILE_PATH', BASE_PATH . '/mobile');
}

if (!$isApiRequest) {
    $csrfKey = (string) (getenv('CSRF_TOKEN_KEY') ?: (defined('SITE_CSRF_KEY') ? SITE_CSRF_KEY : 'site_csrf_token'));
    if (empty($_SESSION[$csrfKey]) || !is_string($_SESSION[$csrfKey])) {
        $_SESSION[$csrfKey] = isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token'])
            ? $_SESSION['csrf_token']
            : bin2hex(random_bytes(32));
    }
    $_SESSION['csrf_token'] = $_SESSION[$csrfKey];

    if (function_exists('frontend_restore_member_session_from_request')) {
        frontend_restore_member_session_from_request();
    }
}

// Bu değişkenler view katmanında global olarak okunuyor (ör. core/Controller::view()
// `global $ayar, $loggedIn` yapıyor; head.php/partials $siteMeta, $siteBranding vb.
// kullanıyor). bootstrap yeni router üzerinden bir metot scope'unda (LegacyPublic
// controller -> legacyRequire) include edildiğinde, global bildirmeden yapılan atamalar
// o metoda lokal kalır ve view katmanına ulaşmaz. Top-level include'da bu satır no-op'tur.
global $ayar, $loggedIn, $siteMeta, $siteBranding, $siteContactLinks, $siteSettingsPayload;

$ayar = [];
$skipRemoteCms = defined('FRONTEND_SKIP_REMOTE_CMS') && FRONTEND_SKIP_REMOTE_CMS;
if (!$isApiRequest && !$skipRemoteCms) {
    try {
        $settingsTimeout = function_exists('frontend_remote_http_timeout')
            ? frontend_remote_http_timeout()
            : 8;
        $settingsCacheTtl = function_exists('frontend_cms_cache_ttl')
            ? frontend_cms_cache_ttl()
            : 120;
        $settingsEnvelope = ApiSiteSettings::fetchRawEnvelopeWithCache($settingsTimeout, $settingsCacheTtl);
        $settingsPayload = ApiSiteSettings::normalizeAyarFromEnvelope($settingsEnvelope);
        if (is_array($settingsPayload) && $settingsPayload !== []) {
            $ayar = $settingsPayload;
        }
    } catch (Throwable) {
        $ayar = [];
    }
}

if (!$isApiRequest && $ayar === [] && function_exists('frontend_database_allowed') && frontend_database_allowed()) {
    try {
        if (!is_file(BASE_PATH . '/admin/app/Core/AdminDatabase.php')) {
            throw new RuntimeException('Admin database bridge is not available on this host.');
        }
        if (!defined('ADMIN_APP_PATH')) {
            define('ADMIN_APP_PATH', BASE_PATH . '/admin/app');
        }
        if (!class_exists('AdminDatabase', false)) {
            require_once ADMIN_APP_PATH . '/Core/AdminDatabase.php';
        }
        ApiSiteSettings::ensureStorage();
        $stmt = AdminDatabase::pdo()->query('SELECT * FROM site_ayarlar ORDER BY id ASC LIMIT 1');
        $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (is_array($row)) {
            $ayar = $row;
        }
    } catch (Throwable) {
        $ayar = [];
    }
}
$defaultsAyar = [
    'site_adi'      => 'VegasRoyalSpin',
    'site_aciklama' => 'Güvenilir casino ve bahis',
];
$ayar = array_merge($defaultsAyar, is_array($ayar) ? $ayar : []);
foreach (['logo_url', 'favicon_url', 'manifest_url', 'og_image_url'] as $mediaKey) {
    if (!empty($ayar[$mediaKey]) && is_string($ayar[$mediaKey])) {
        $ayar[$mediaKey] = cms_asset_url($ayar[$mediaKey]);
    }
}

$siteSettingsPayload = ApiSiteSettings::normalizePublicSettings($ayar);
if (class_exists('ApiMediaUrl', false)) {
    $siteSettingsPayload = ApiMediaUrl::rewriteDeep($siteSettingsPayload);
}
$siteBranding = is_array($siteSettingsPayload['branding'] ?? null) ? $siteSettingsPayload['branding'] : [];
$siteMeta = is_array($siteSettingsPayload['meta'] ?? null) ? $siteSettingsPayload['meta'] : [];
$siteContactLinks = is_array($siteSettingsPayload['contact'] ?? null)
    ? $siteSettingsPayload['contact']
    : ApiSiteSettings::normalizeContactLinks($ayar);
$ayar = array_merge($ayar, is_array($siteSettingsPayload['site_settings'] ?? null) ? $siteSettingsPayload['site_settings'] : []);

if (!$isApiRequest) {
    if (is_readable(CONFIG_PATH . '/member_api_public.php')) {
        require_once CONFIG_PATH . '/member_api_public.php';
        if (function_exists('frontend_sanitize_member_session')) {
            frontend_sanitize_member_session();
        }
    }
    $loggedIn = function_exists('frontend_member_logged_in')
        ? frontend_member_logged_in()
        : (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true);
    if (
        function_exists('frontend_database_allowed')
        && frontend_database_allowed()
        && is_file(BASE_PATH . '/admin/app/Core/AdminDatabase.php')
        && $loggedIn
        && empty($_SESSION['member_jwt'])
        && (int) ($_SESSION['user_id'] ?? 0) > 0
    ) {
    try {
        if (!defined('ADMIN_APP_PATH')) {
            define('ADMIN_APP_PATH', BASE_PATH . '/admin/app');
        }
        if (!class_exists('AdminDatabase', false)) {
            require_once ADMIN_APP_PATH . '/Core/AdminDatabase.php';
        }
        if (!class_exists('MemberJwtService', false)) {
            require_once SERVICE_PATH . '/MemberJwtService.php';
        }
        MemberJwtService::ensureSessionToken(AdminDatabase::pdo());
    } catch (Throwable) {
        // Keep rendering public pages; protected API calls will reject if JWT cannot be issued.
    }
    }
}
