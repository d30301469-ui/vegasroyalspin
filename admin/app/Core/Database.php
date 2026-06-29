<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        // Admin context: all connections go through AdminDatabase (the single PDO factory).
        if (!class_exists('AdminDatabase', false)) {
            require_once __DIR__ . '/AdminDatabase.php';
        }

        self::$pdo = AdminDatabase::pdo();

        return self::$pdo;
    }
}

