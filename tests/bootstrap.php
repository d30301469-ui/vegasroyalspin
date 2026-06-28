<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap and Configuration
 *
 * Initializes the test environment with necessary configuration and autoloading.
 */

// Set test environment
putenv('APP_ENV=test');
putenv('APP_DEBUG=1');

// Define constants if not already defined
require_once __DIR__ . '/../config/paths.php';

// Composer autoloader
$composerAutoload = BASE_PATH . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// Ensure base test classes are available when autoload-dev isn't loaded
$baseApiTest = __DIR__ . '/ApiTestCase.php';
if (file_exists($baseApiTest)) {
    require_once $baseApiTest;
}

// Error handler (core/bootstrap loads it conditionally; tests need it explicitly)
$errorHandler = BASE_PATH . '/app/Core/ErrorHandler.php';
if (is_readable($errorHandler)) {
    require_once $errorHandler;
    \App\Core\ErrorHandler::register();
}

$publicApiDispatcher = BASE_PATH . '/app/Services/Api/PublicMemberApiDispatcher.php';
if (is_readable($publicApiDispatcher)) {
    require_once BASE_PATH . '/app/Core/Response.php';
    require_once $publicApiDispatcher;
}
