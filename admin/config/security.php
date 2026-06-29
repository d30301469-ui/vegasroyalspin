<?php



declare(strict_types=1);



$envConfig = __DIR__ . '/env.php';

if (is_file($envConfig)) {

    require_once $envConfig;

}

require_once __DIR__ . '/deploy_domains.php';



$adminHost = getenv('ADMIN_URL_HOST') ?: getenv('BACKEND_HOST') ?: (defined('BACKEND_HOST') ? BACKEND_HOST : (parse_url((string) (getenv('BACKEND_FALLBACK_URL') ?: deploy_domain('backend_url')), PHP_URL_HOST) ?: ''));

if (function_exists('frontend_app_is_production') && frontend_app_is_production()) {

    $normalizedAdminHost = strtolower(preg_replace('/:\d+$/', '', trim((string) $adminHost)) ?? '');

    if ($normalizedAdminHost === '' || $normalizedAdminHost === 'localhost' || $normalizedAdminHost === '127.0.0.1' || str_ends_with($normalizedAdminHost, '.test')) {

        throw new RuntimeException('Production admin host must be configured with a public hostname.');

    }

}



return [

    'admin_host' => $adminHost,

    'backend_hosts' => function_exists('deploy_backend_hosts') ? deploy_backend_hosts() : [],

    'csrf_key' => (string) (getenv('CSRF_TOKEN_KEY') ?: 'site_csrf_token'),

    'member_jwt_cookie' => 'member_token',

    'allowed_url_hosts' => array_filter(array_map('trim', explode(',', (string) (getenv('ALLOWED_URL_HOSTS') ?: (defined('ALLOWED_URL_HOSTS') ? ALLOWED_URL_HOSTS : (getenv('DEFAULT_ALLOWED_URL_HOSTS') ?: deploy_domain('default_allowed_url_hosts'))))))),

];


