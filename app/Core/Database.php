<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (function_exists('frontend_database_allowed') && !frontend_database_allowed()) {
            throw new \RuntimeException('Direct database access is disabled on this host. Use the backend HTTP API.');
        }

        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $db = Config::get('database', []);
        if (!empty($db['disabled'])) {
            throw new \RuntimeException('Database configuration is disabled on this host.');
        }
        $host = (string) ($db['host'] ?? '127.0.0.1');
        $port = (int) ($db['port'] ?? 3306);
        $database = trim((string) ($db['database'] ?? ''));
        if ($database === '') {
            throw new \RuntimeException('Database name is not configured (DB_DATABASE env var missing or empty).');
        }
        $charset = (string) ($db['charset'] ?? 'utf8mb4');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);

        $options = function_exists('metropol_pdo_options')
            ? metropol_pdo_options()
            : [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
        self::$pdo = new PDO($dsn, (string) ($db['username'] ?? 'root'), (string) ($db['password'] ?? ''), $options);

        return self::$pdo;
    }
}

