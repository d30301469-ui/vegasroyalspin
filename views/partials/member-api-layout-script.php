<?php

declare(strict_types=1);

/**
 * Layout script: member API + oturum bayrakları (desktop + mobile ortak).
 */
if (!function_exists('metropol_member_api_layout_vars')) {
    require_once (defined('CONFIG_PATH') ? CONFIG_PATH : dirname(__DIR__, 2) . '/config') . '/member_api_public.php';
}
global $siteSettingsPayload, $siteContactLinks, $ayar, $siteBranding, $siteMeta;
$memberApiLayout = metropol_member_api_layout_vars();
$loggedInPhp = function_exists('metropol_frontend_member_logged_in')
    ? metropol_frontend_member_logged_in()
    : (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true);
$hasJwtPhp = !empty($_SESSION['member_jwt']);
$memberJwtBootstrap = $hasJwtPhp ? trim((string) $_SESSION['member_jwt']) : '';
$memberBootstrapState = [
    'logged_in' => $loggedInPhp,
    'has_session_jwt' => $hasJwtPhp,
    'user_id' => (int) ($_SESSION['user_id'] ?? 0),
    'username' => (string) ($_SESSION['username'] ?? ''),
    'direct_member_api' => !empty($memberApiLayout['__FRONTEND_DIRECT_MEMBER_API__']),
    'member_api_base' => (string) ($memberApiLayout['__MEMBER_API_BASE__'] ?? ''),
    'session_cookie_domain' => function_exists('deploy_session_cookie_domain_for_host')
        ? deploy_session_cookie_domain_for_host((string) ($_SERVER['HTTP_HOST'] ?? ''))
        : '',
];
?>
<script>
    window.__MEMBER_API_CONSOLE__ = true;
    window.__MEMBER_BOOTSTRAP_STATE__ = <?php echo json_encode($memberBootstrapState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    window.__APP_BASE_PATH__ = <?php echo json_encode(defined('SITE_URL') ? rtrim((string) (parse_url((string) SITE_URL, PHP_URL_PATH) ?: ''), '/') : ''); ?>;
    window.__USER_LOGGED_IN__ = <?php echo json_encode($loggedInPhp); ?>;
    window.__HAS_MEMBER_JWT__ = <?php echo json_encode($hasJwtPhp); ?>;
    window.__MEMBER_JWT_BOOTSTRAP__ = <?php echo json_encode($memberJwtBootstrap, JSON_UNESCAPED_SLASHES); ?>;
    window.__CSRF_TOKEN__ = <?php echo json_encode((string) ($_SESSION['csrf_token'] ?? '')); ?>;
    window.__MEMBER_API_BASE__ = <?php echo json_encode((string) ($memberApiLayout['__MEMBER_API_BASE__'] ?? ''), JSON_UNESCAPED_SLASHES); ?>;
    window.__FRONTEND_DIRECT_MEMBER_API__ = <?php echo json_encode(!empty($memberApiLayout['__FRONTEND_DIRECT_MEMBER_API__'])); ?>;
    window.__SITE_SETTINGS__ = <?php echo json_encode(is_array($siteSettingsPayload ?? null) ? $siteSettingsPayload : [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    window.__SITE_SETTINGS_API__ = <?php echo json_encode((string) ($memberApiLayout['__SITE_SETTINGS_API__'] ?? '/api/v2/site-settings'), JSON_UNESCAPED_SLASHES); ?>;
    window.__FRONTEND_CONNECTIONS__ = <?php echo json_encode([
        'liveSupportUrl' => (string) ($siteContactLinks['live_support_url'] ?? (defined('LIVE_SUPPORT_URL') ? LIVE_SUPPORT_URL : '')),
        'telegramUrl' => defined('TELEGRAM_URL') ? (string) TELEGRAM_URL : '',
        'megapayzLogoBaseUrl' => defined('MEGAPAYZ_LOGO_BASE_URL') ? (string) MEGAPAYZ_LOGO_BASE_URL : '',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
