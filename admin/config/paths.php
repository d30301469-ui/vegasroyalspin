<?php

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', str_replace('\\', '/', dirname(__DIR__)));
}
if (!defined('APP_PATH')) {
    define('APP_PATH', BASE_PATH . '/app');
}
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', BASE_PATH . '/public');
}
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', BASE_PATH . '/config');
}
if (!defined('SERVICE_PATH')) {
    define('SERVICE_PATH', BASE_PATH . '/services');
}
if (!defined('API_PATH')) {
    define('API_PATH', BASE_PATH . '/api');
}
if (!defined('CORE_PATH')) {
    define('CORE_PATH', BASE_PATH . '/core');
}
if (!defined('CONTROLLER_PATH')) {
    define('CONTROLLER_PATH', BASE_PATH . '/controllers');
}
if (!defined('VIEW_PATH')) {
    define('VIEW_PATH', BASE_PATH . '/views');
}
if (!defined('REPOSITORY_PATH')) {
    define('REPOSITORY_PATH', BASE_PATH . '/repositories');
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', BASE_PATH . '/storage');
}
