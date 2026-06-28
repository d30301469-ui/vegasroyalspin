<?php

declare(strict_types=1);

/**
 * Admin/backend host database config — always active (standalone admin deploy).
 */
return static function (): array {
    $env = static function (array $keys, string $default = ''): string {
        foreach ($keys as $key) {
            $value = getenv($key);
            if ($value !== false && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return $default;
    };

    $isProduction = in_array(strtolower($env(['APP_ENV'], 'development')), ['production', 'prod'], true);
    $config = [
        'host' => $env(['ADMIN_DB_HOST', 'DB_HOST', 'DATABASE_HOST'], '127.0.0.1'),
        'port' => (int) $env(['ADMIN_DB_PORT', 'DB_PORT', 'DATABASE_PORT'], '3306'),
        'database' => $env(['ADMIN_DB_DATABASE', 'DB_DATABASE', 'DATABASE_NAME', 'DATABASE_DATABASE'], 'metropol_db'),
        'username' => $env(['ADMIN_DB_USERNAME', 'DB_USERNAME', 'DATABASE_USERNAME'], 'root'),
        'password' => $env(['ADMIN_DB_PASSWORD', 'DB_PASSWORD', 'DATABASE_PASSWORD'], ''),
        'charset' => $env(['ADMIN_DB_CHARSET', 'DB_CHARSET', 'DATABASE_CHARSET'], 'utf8mb4'),
    ];

    if ($isProduction) {
        foreach (['host', 'database', 'username', 'password'] as $requiredKey) {
            if (trim((string) $config[$requiredKey]) === '') {
                throw new RuntimeException(sprintf('Production admin database config requires a non-empty "%s" value.', $requiredKey));
            }
        }

        if (strtolower((string) $config['username']) === 'root') {
            throw new RuntimeException('Production admin database config must not use the root database user.');
        }
    }

    return [
        'host' => $config['host'],
        'port' => $config['port'],
        'database' => $config['database'],
        'username' => $config['username'],
        'password' => $config['password'],
        'charset' => $config['charset'],
        'disabled' => false,
    ];
};
