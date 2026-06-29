<?php

declare(strict_types=1);

final class AdminDatabase
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        if (!defined('ADMIN_APP_PATH')) {
            require_once __DIR__ . '/AdminPaths.php';
            admin_paths_bootstrap();
        }

        self::$pdo = self::connectFromAdminConfig();

        return self::$pdo;
    }

    /**
     * Single PDO factory — all connections go through here.
     * Use pdo() for the cached singleton; use connectWithParams() only for
     * pre-bootstrap contexts (installer, health check, CLI scripts).
     *
     * @param array{host?:string,port?:int,database?:string,charset?:string,username?:string,password?:string} $db
     */
    public static function connectWithParams(array $db): PDO
    {
        $host     = (string) ($db['host'] ?? '127.0.0.1');
        $port     = (int)    ($db['port'] ?? 3306);
        $database = (string) ($db['database'] ?? '');
        $charset  = (string) ($db['charset'] ?? 'utf8mb4');
        $username = (string) ($db['username'] ?? 'root');
        $password = (string) ($db['password'] ?? '');

        if (trim($database) === '') {
            throw new RuntimeException('Database name is not configured.');
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);

        $options = function_exists('metropol_pdo_options')
            ? metropol_pdo_options()
            : [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

        return new PDO($dsn, $username, $password, $options);
    }

    private static function connectFromAdminConfig(): PDO
    {
        $configFile = ADMIN_APP_PATH . '/Config/admin.php';
        if (!is_readable($configFile)) {
            throw new RuntimeException('Admin database config not found: ' . $configFile);
        }

        $config = require $configFile;
        $db = is_array($config['db'] ?? null) ? $config['db'] : [];

        return self::connectWithParams($db);
    }
}
