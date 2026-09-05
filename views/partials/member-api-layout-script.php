<?php

declare(strict_types=1);

/**
 * Layout script: member API + oturum bayrakları (desktop + mobile ortak).
 */
if (!function_exists('member_api_layout_vars')) {
    require_once (defined('CONFIG_PATH') ? CONFIG_PATH : dirname(__DIR__, 2) . '/config') . '/member_api_public.php';
}
global $siteSettingsPayload, $siteContactLinks, $ayar, $siteBranding, $siteMeta;
$memberApiLayout = member_api_layout_vars();
$loggedInPhp = function_exists('frontend_member_logged_in')
    ? frontend_member_logged_in()
    : (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true);
$hasJwtPhp = !empty($_SESSION['member_jwt']);
$memberJwtBootstrap = $hasJwtPhp ? trim((string) $_SESSION['member_jwt']) : '';
$memberBootstrapState = [
    'logged_in' => $loggedInPhp,
    'has_session_jwt' => $hasJwtPhp,
    'has_restore_cookie' => (function (): bool {
        $name = function_exists('frontend_member_restore_cookie_name')
            ? frontend_member_restore_cookie_name()
            : 'app_member_restore';
        return trim((string) ($_COOKIE[$name] ?? $_COOKIE['app_member_restore'] ?? '')) !== '';
    })(),
    'user_id' => (int) ($_SESSION['user_id'] ?? 0),
    'username' => (string) ($_SESSION['username'] ?? ''),
    'direct_member_api' => !empty($memberApiLayout['__FRONTEND_DIRECT_MEMBER_API__']),
    'member_api_base' => (string) ($memberApiLayout['__MEMBER_API_BASE__'] ?? ''),
    'session_cookie_domain' => function_exists('deploy_session_cookie_domain_for_host')
        ? deploy_session_cookie_domain_for_host((string) ($_SERVER['HTTP_HOST'] ?? ''))
        : '',
];
// API konsolu yalnızca açık debug isteğinde — production’da asla varsayılan açık olmasın.
$memberApiConsole = isset($_GET['debug']) && (string) $_GET['debug'] === '1';
?>
<script>
    window.__MEMBER_API_CONSOLE__ = false;
    window.__MEMBER_BOOTSTRAP_STATE__ = <?php echo json_encode($memberBootstrapState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    window.__APP_BASE_PATH__ = <?php echo json_encode(defined('SITE_URL') ? rtrim((string) (parse_url((string) SITE_URL, PHP_URL_PATH) ?: ''), '/') : ''); ?>;
    window.__USER_LOGGED_IN__ = <?php echo json_encode($loggedInPhp); ?>;
    window.__HAS_MEMBER_JWT__ = <?php echo json_encode($hasJwtPhp); ?>;
    window.__MEMBER_JWT_BOOTSTRAP__ = <?php echo json_encode($memberJwtBootstrap, JSON_UNESCAPED_SLASHES); ?>;
    window.__CSRF_TOKEN__ = <?php echo json_encode((string) ($_SESSION['csrf_token'] ?? '')); ?>;
    window.__MEMBER_API_BASE__ = <?php echo json_encode((string) ($memberApiLayout['__MEMBER_API_BASE__'] ?? ''), JSON_UNESCAPED_SLASHES); ?>;
    window.__FRONTEND_DIRECT_MEMBER_API__ = <?php echo json_encode(!empty($memberApiLayout['__FRONTEND_DIRECT_MEMBER_API__'])); ?>;
    window.__SITE_SETTINGS__ = <?php echo json_encode(is_array($siteSettingsPayload ?? null) ? $siteSettingsPayload : [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    window.__TURNSTILE_ENABLED__ = <?php echo json_encode(!empty((is_array($siteSettingsPayload ?? null) ? $siteSettingsPayload : [])['turnstile_enabled'])); ?>;
    window.__TURNSTILE_SITE_KEY__ = <?php echo json_encode((string) ((is_array($siteSettingsPayload ?? null) ? $siteSettingsPayload : [])['turnstile_site_key'] ?? ''), JSON_UNESCAPED_SLASHES); ?>;
    window.__SITE_SETTINGS_API__ = <?php echo json_encode((string) ($memberApiLayout['__SITE_SETTINGS_API__'] ?? '/api/v2/site-settings'), JSON_UNESCAPED_SLASHES); ?>;
    window.__LOCALE__ = <?php echo json_encode(function_exists('current_locale') ? current_locale() : 'tr'); ?>;
    window.__INTL_LOCALE__ = <?php echo json_encode(function_exists('current_intl_locale') ? current_intl_locale() : 'tr-TR'); ?>;
    window.__I18N__ = <?php
        $i18nBag = class_exists('SiteI18n', false) ? SiteI18n::jsMessages() : [];
        echo json_encode($i18nBag, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ?>;
    window.__ = function (key, fallback) {
        var bag = window.__I18N__ || {};
        if (Object.prototype.hasOwnProperty.call(bag, key) && bag[key] != null && bag[key] !== '') {
            return String(bag[key]);
        }
        return fallback != null ? String(fallback) : key;
    };
    window.__FRONTEND_CONNECTIONS__ = <?php echo json_encode([
        'liveSupportUrl' => (string) ($siteContactLinks['live_support_url'] ?? (defined('LIVE_SUPPORT_URL') ? LIVE_SUPPORT_URL : '')),
        'liveSupportTitle' => function_exists('i18n_label')
            ? i18n_label((string) ($siteContactLinks['live_support_title'] ?? 'Canlı Destek'))
            : (string) ($siteContactLinks['live_support_title'] ?? 'Canlı Destek'),
        'telegramUrl' => (string) ($siteContactLinks['telegram_url'] ?? (defined('TELEGRAM_URL') ? TELEGRAM_URL : '')),
        'whatsappUrl' => (string) ($siteContactLinks['whatsapp_url'] ?? (defined('WHATSAPP_URL') ? WHATSAPP_URL : '')),
        'contactPhone' => (string) ($siteContactLinks['contact_phone'] ?? ''),
        'callbackUrl' => (string) ($siteContactLinks['callback_url'] ?? '/beni-ara'),
        'callbackWidgetText' => (string) ($siteContactLinks['callback_widget_text'] ?? ''),
        'partnershipUrl' => (string) ($siteContactLinks['partnership_url'] ?? '/ortaklik'),
        'partnershipLabel' => function_exists('i18n_label')
            ? i18n_label((string) ($siteContactLinks['partnership_label'] ?? 'ORTAKLIK'))
            : (string) ($siteContactLinks['partnership_label'] ?? 'ORTAKLIK'),
        'partnershipTitle' => function_exists('i18n_label')
            ? i18n_label((string) ($siteContactLinks['partnership_title'] ?? 'Ortaklık'))
            : (string) ($siteContactLinks['partnership_title'] ?? 'Ortaklık'),
        'megapayzLogoBaseUrl' => defined('MEGAPAYZ_LOGO_BASE_URL') ? (string) MEGAPAYZ_LOGO_BASE_URL : '',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

    (function () {
        if (!window.__TURNSTILE_ENABLED__ || document.querySelector('script[data-turnstile-loader="1"]')) {
            return;
        }

        var existing = document.querySelector('script[src*="challenges.cloudflare.com/turnstile/v0/api.js"]');
        if (existing) {
            return;
        }

        var script = document.createElement('script');
        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
        script.async = true;
        script.defer = true;
        script.setAttribute('data-turnstile-loader', '1');
        document.head.appendChild(script);
    })();
</script>
