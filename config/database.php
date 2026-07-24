<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

$disabled = [
    'host' => '',
    'port' => 3306,
    'database' => '',
    'username' => '',
    'password' => '',
    'charset' => 'utf8mb4',
    'disabled' => true,
];

if (!frontend_database_allowed()) {
    return $disabled;
}

$env = static function (array $keys, string $default = ''): string {
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== false && trim((string) $value) !== '') {
            return trim((string) $value);
        }
        if (isset($_ENV[$key]) && trim((string) $_ENV[$key]) !== '') {
            return trim((string) $_ENV[$key]);
        }
    }

    return $default;
};

$isProduction = in_array(strtolower($env(['APP_ENV'], 'development')), ['production', 'prod'], true);
$config = [
    'host' => $env(['DB_HOST', 'DATABASE_HOST', 'ADMIN_DB_HOST'], '127.0.0.1'),
    'port' => (int) $env(['DB_PORT', 'DATABASE_PORT', 'ADMIN_DB_PORT'], '3306'),
    'database' => $env(['DB_NAME', 'DB_DATABASE', 'DATABASE_NAME', 'DATABASE_DATABASE', 'ADMIN_DB_DATABASE'], ''),
    'username' => $env(['DB_USER', 'DB_USERNAME', 'DATABASE_USERNAME', 'ADMIN_DB_USERNAME'], 'root'),
    'password' => $env(['DB_PASS', 'DB_PASSWORD', 'DATABASE_PASSWORD', 'ADMIN_DB_PASSWORD'], ''),
    'charset' => $env(['DB_CHARSET', 'DATABASE_CHARSET', 'ADMIN_DB_CHARSET'], 'utf8mb4'),
];

if ($isProduction) {
    foreach (['host', 'database', 'username', 'password'] as $requiredKey) {
        if (trim((string) $config[$requiredKey]) === '') {
            throw new RuntimeException(sprintf('Production database config requires a non-empty "%s" value.', $requiredKey));
        }
    }

    if (strtolower((string) $config['username']) === 'root') {
        throw new RuntimeException('Production database config must not use the root database user.');
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
