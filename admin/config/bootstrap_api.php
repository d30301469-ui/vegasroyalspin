<?php

declare(strict_types=1);

/**
 * Minimal bootstrap for backend /api/v2/* — no panel controllers, no production assertion side-effects.
 */
require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/env.php';

frontend_load_dotenv(BASE_PATH);

require_once __DIR__ . '/deploy_domains.php';

if (!defined('SITE_URL')) {
    $site = trim(frontend_env_string('SITE_URL', frontend_env_string('FRONTEND_URL', '')));
    if ($site === '') {
        $site = deploy_domain('frontend_url');
    }
    define('SITE_URL', rtrim($site !== '' ? $site : deploy_domain('frontend_url'), '/'));
}

if (!defined('FRONTEND_URL')) {
    define('FRONTEND_URL', rtrim(frontend_env_string('FRONTEND_URL', SITE_URL), '/'));
}

if (!defined('BACKEND_URL')) {
    $backend = trim(frontend_env_string('BACKEND_URL', ''));
    if ($backend === '') {
        $backend = trim(frontend_env_string('BACKEND_FALLBACK_URL', deploy_domain('backend_url')));
    }
    define('BACKEND_URL', rtrim($backend !== '' ? $backend : deploy_domain('backend_url'), '/'));
}

if (!defined('BACKEND_HOST')) {
    $backendHost = parse_url((string) BACKEND_URL, PHP_URL_HOST);
    define('BACKEND_HOST', strtolower((string) ($backendHost ?: '')));
}

if (!defined('BACKEND_API_BASE_URL')) {
    define(
        'BACKEND_API_BASE_URL',
        rtrim(frontend_env_string('BACKEND_API_BASE_URL', BACKEND_URL . '/api/v2'), '/')
    );
}

try {
    require_once __DIR__ . '/backend_api.php';
} catch (Throwable $bootstrapApiConfigError) {
    error_log('[metropol] bootstrap_api config: ' . $bootstrapApiConfigError->getMessage());
    if (!defined('API_BACKEND_MAIN_BASE_URL')) {
        define('API_BACKEND_MAIN_BASE_URL', rtrim(deploy_domain('backend_api_base_url'), '/'));
    }
    if (!defined('API_BACKEND_SLIDER_BASE_URL')) {
        define('API_BACKEND_SLIDER_BASE_URL', '');
    }
    if (!defined('API_BACKEND_AFFILIATE_BASE_URL')) {
        define('API_BACKEND_AFFILIATE_BASE_URL', '');
    }
    if (!defined('API_BACKEND_CASINO_WALLET_BASE_URL')) {
        define('API_BACKEND_CASINO_WALLET_BASE_URL', '');
    }
    if (!defined('API_BACKEND_PAYMENT_CALLBACK_BASE_URL')) {
        define('API_BACKEND_PAYMENT_CALLBACK_BASE_URL', '');
    }
    if (!defined('API_BACKEND_GAMES_BASE_URL')) {
        define('API_BACKEND_GAMES_BASE_URL', '');
    }
    if (!defined('API_BACKEND_AUTH_HEADER')) {
        define('API_BACKEND_AUTH_HEADER', '');
    }
    if (!defined('API_BACKEND_CURL_CAINFO')) {
        define('API_BACKEND_CURL_CAINFO', '');
    }
    if (!defined('API_BACKEND_INTERNAL_BASE_URL')) {
        define('API_BACKEND_INTERNAL_BASE_URL', '');
    }
    if (!defined('API_BACKEND_INTERNAL_HOST')) {
        define('API_BACKEND_INTERNAL_HOST', 'bo-backoffice.site');
    }
}

if (is_readable(__DIR__ . '/member_api_public.php')) {
    require_once __DIR__ . '/member_api_public.php';
}

if (is_readable(API_PATH . '/MediaUrl.php')) {
    require_once API_PATH . '/MediaUrl.php';
}

if (!defined('FRONTEND_CMS_PURGE_SECRET')) {
    define('FRONTEND_CMS_PURGE_SECRET', function_exists('metropol_frontend_trust_secret')
        ? metropol_frontend_trust_secret()
        : frontend_env_string('FRONTEND_CMS_PURGE_SECRET', ''));
}
if (!defined('MEMBER_JWT_SECRET')) {
    define('MEMBER_JWT_SECRET', frontend_env_string('MEMBER_JWT_SECRET', ''));
}
