<?php

declare(strict_types=1);

/**
 * Global Error Handler and Exception Management
 *
 * This file provides standardized error handling across the entire application
 * with consistent JSON responses for API, HTML for web, and logging.
 */

namespace App\Core;

use Throwable;

final class ErrorHandler
{
    /**
     * HTTP status code to use for exceptions without explicit code.
     */
    private const DEFAULT_HTTP_CODE = 500;

    /**
     * Initialize global error handling.
     *
     * Call this early in bootstrap, after logging is configured.
     */
    public static function register(): void
    {
        // Set error handler for PHP errors
        set_error_handler([self::class, 'handleError']);

        // Set exception handler for uncaught exceptions
        set_exception_handler([self::class, 'handleException']);

        // Handle script shutdown errors
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * Handle PHP errors (E_WARNING, E_NOTICE, E_DEPRECATED, etc.)
     */
    public static function handleError(
        int $errno,
        string $errstr,
        string $errfile,
        int $errline
    ): bool {
        // Skip suppressed errors (@)
        if (error_reporting() === 0) {
            return false;
        }

        $isProduction = strtolower((string) getenv('APP_ENV')) === 'production';

        $errorMessage = sprintf(
            "[%s] %s in %s on line %d",
            self::getErrorTypeName($errno),
            $errstr,
            $errfile,
            $errline
        );

        self::logError($errorMessage, $errno);

        // Fatal errors should not continue
        if ($errno === E_ERROR || $errno === E_PARSE || $errno === E_CORE_ERROR || $errno === E_COMPILE_ERROR) {
            self::respond500($errorMessage, $isProduction);
            return true;
        }

        // For development, convert warnings/notices to exceptions
        if (!$isProduction) {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        }

        return true;
    }

    /**
     * Handle uncaught exceptions.
     */
    public static function handleException(Throwable $exception): void
    {
        $isProduction = strtolower((string) getenv('APP_ENV')) === 'production';
        $isDev = !$isProduction;

        // Log the exception
        self::logException($exception);

        // Determine HTTP code
        $httpCode = self::getHttpCodeFromException($exception);

        // Determine response format
        $isApi = self::isApiRequest();

        if ($isApi) {
            self::respondJsonError($exception, $httpCode, $isDev);
        } else {
            self::respondHtmlError($exception, $httpCode, $isDev);
        }

        exit(1);
    }

    /**
     * Handle fatal errors during shutdown.
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        if (!in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }

        $isProduction = strtolower((string) getenv('APP_ENV')) === 'production';
        $errorMessage = sprintf(
            "[SHUTDOWN] %s in %s on line %d",
            $error['message'],
            $error['file'],
            $error['line']
        );

        self::logError($errorMessage, $error['type']);
        self::respond500($errorMessage, $isProduction);
    }

    /**
     * Respond with JSON error (for API requests).
     */
    private static function respondJsonError(Throwable $exception, int $httpCode, bool $isDev): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code($httpCode);
        }

        $response = [
            'success' => false,
            'code' => $httpCode,
            'message' => $exception->getMessage() ?: 'An error occurred',
        ];

        if ($isDev) {
            $response['exception'] = get_class($exception);
            $response['file'] = $exception->getFile();
            $response['line'] = $exception->getLine();
            $response['trace'] = $exception->getTraceAsString();
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Respond with HTML error page (for web requests).
     */
    private static function respondHtmlError(Throwable $exception, int $httpCode, bool $isDev): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
            http_response_code($httpCode);
        }

        $title = 'Error ' . $httpCode;
        $message = $exception->getMessage() ?: 'An error occurred';

        if ($isDev) {
            $trace = htmlspecialchars($exception->getTraceAsString(), ENT_QUOTES, 'UTF-8');
            $file = htmlspecialchars($exception->getFile(), ENT_QUOTES, 'UTF-8');
            $line = $exception->getLine();
            $exceptionClass = get_class($exception);

            echo <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>{$title}</title>
                <style>
                    body { font-family: sans-serif; margin: 40px; background: #f5f5f5; }
                    .container { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,.1); }
                    h1 { color: #d9534f; margin: 0 0 20px; }
                    .meta { font-size: 12px; color: #666; margin-bottom: 15px; }
                    pre { background: #f9f9f9; border: 1px solid #ddd; padding: 10px; overflow-x: auto; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1>{$title}</h1>
                    <p>{$message}</p>
                    <div class="meta">
                        <strong>File:</strong> {$file} (line {$line})<br>
                        <strong>Exception:</strong> {$exceptionClass}
                    </div>
                    <h3>Stack Trace:</h3>
                    <pre>{$trace}</pre>
                </div>
            </body>
            </html>
            HTML;
        } else {
            echo <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>{$title}</title>
                <style>
                    body { font-family: sans-serif; margin: 40px; background: #f5f5f5; }
                    .container { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,.1); }
                    h1 { color: #d9534f; margin: 0 0 20px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1>{$title}</h1>
                    <p>An error occurred. Please try again later.</p>
                </div>
            </body>
            </html>
            HTML;
        }
    }

    /**
     * Respond with 500 error during bootstrap/shutdown.
     */
    private static function respond500(string $message, bool $isProduction): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
            http_response_code(500);
        }

        if ($isProduction) {
            echo '<h1>500 Internal Server Error</h1><p>An error occurred. Please try again later.</p>';
        } else {
            echo '<h1>500 Internal Server Error</h1><pre>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</pre>';
        }
    }

    /**
     * Get HTTP code from exception.
     *
     * If exception has a 'code' property in range 100-599, use it.
     * Otherwise, default to 500.
     */
    private static function getHttpCodeFromException(Throwable $exception): int
    {
        $code = $exception->getCode();
        if (is_int($code) && $code >= 100 && $code < 600) {
            return $code;
        }

        return self::DEFAULT_HTTP_CODE;
    }

    /**
     * Check if this is an API request.
     *
     * API requests typically:
     * - Have Accept: application/json header
     * - Start with /api/ path
     * - Are XHR requests
     */
    private static function isApiRequest(): bool
    {
        // Check Accept header
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (stripos($accept, 'application/json') !== false) {
            return true;
        }

        // Check path
        $path = $_SERVER['REQUEST_URI'] ?? '';
        if (stripos($path, '/api/') === 0) {
            return true;
        }

        // Check XHR header
        if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') {
            return true;
        }

        return false;
    }

    /**
     * Log error message.
     */
    private static function logError(string $message, int $errno): void
    {
        self::writeLog('ERROR', $message);
    }

    /**
     * Log exception.
     */
    private static function logException(Throwable $exception): void
    {
        $message = sprintf(
            "%s: %s\nFile: %s:%d\nTrace:\n%s",
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        self::writeLog('EXCEPTION', $message);
    }

    /**
     * Write to log file.
     */
    private static function writeLog(string $level, string $message): void
    {
        $logDir = defined('BASE_PATH') ? BASE_PATH . '/logs' : dirname(__DIR__) . '/logs';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        if (!is_dir($logDir) || !is_writable($logDir)) {
            return;
        }

        $logFile = $logDir . '/errors.log';
        $timestamp = date('Y-m-d H:i:s');
        $formatted = "[$timestamp] [$level] $message\n";

        @file_put_contents($logFile, $formatted, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get human-readable error type name.
     */
    private static function getErrorTypeName(int $errno): string
    {
        return match ($errno) {
            E_ERROR => 'Fatal Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated',
            default => 'Unknown Error (' . $errno . ')',
        };
    }
}
