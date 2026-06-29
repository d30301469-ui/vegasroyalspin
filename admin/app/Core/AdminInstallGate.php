<?php

declare(strict_types=1);

final class AdminInstallGate
{
    public static function root(string $hint = ''): string
    {
        if ($hint !== '') {
            return rtrim(str_replace('\\', '/', $hint), '/');
        }

        return rtrim(str_replace('\\', '/', dirname(__DIR__, 2)), '/');
    }

    public static function lockPath(string $root): string
    {
        return self::root($root) . '/storage/install.lock';
    }

    public static function envPath(string $root): string
    {
        return self::root($root) . '/.env';
    }

    public static function csrfTokenPath(string $root): string
    {
        return self::root($root) . '/storage/install_csrf.token';
    }

    public static function csrfToken(string $root): string
    {
        $root = self::root($root);
        $path = self::csrfTokenPath($root);
        $storage = dirname($path);
        if (!is_dir($storage)) {
            mkdir($storage, 0755, true);
        }

        if (is_readable($path)) {
            $existing = trim((string) file_get_contents($path));
            if (strlen($existing) >= 32) {
                return $existing;
            }
        }

        $token = bin2hex(random_bytes(32));
        file_put_contents($path, $token);

        return $token;
    }

    public static function verifyCsrfToken(string $root, ?string $token): bool
    {
        $path = self::csrfTokenPath($root);
        $known = is_readable($path) ? trim((string) file_get_contents($path)) : '';

        return $known !== '' && is_string($token) && hash_equals($known, $token);
    }

    public static function clearCsrfToken(string $root): void
    {
        $path = self::csrfTokenPath($root);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function isInstalled(string $root): bool
    {
        $root = self::root($root);
        if (is_file(self::lockPath($root))) {
            return true;
        }

        if (!is_readable(self::envPath($root))) {
            return false;
        }

        try {
            self::loadEnv($root);
            $pdo = self::connectFromEnv($root);
            if (!self::tableExists($pdo, 'admins')) {
                return false;
            }

            $count = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
            if ($count <= 0) {
                return false;
            }

            self::writeLock($root, ['legacy_detected' => true]);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function loadEnv(string $root): void
    {
        $root = self::root($root);
        $canPutenv = function_exists('putenv');
        foreach ([$root . '/.env', $root . '/env'] as $file) {
            if (!is_readable($file)) {
                continue;
            }
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                if ($key === '') {
                    continue;
                }
                // putenv kapalı olabilir; getenv yerine $_ENV/$_SERVER üzerinden de kontrol et.
                if (self::envValue([$key], '__MISSING__') !== '__MISSING__') {
                    continue;
                }
                $value = trim($value);
                if (
                    (str_starts_with($value, '"') && str_ends_with($value, '"'))
                    || (str_starts_with($value, "'") && str_ends_with($value, "'"))
                ) {
                    $value = substr($value, 1, -1);
                }
                if ($canPutenv) {
                    @putenv($key . '=' . $value);
                }
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }

    public static function connectFromEnv(string $root): PDO
    {
        self::loadEnv($root);

        $configEnv = self::root($root) . '/config/env.php';
        if (!function_exists('metropol_pdo_options') && is_readable($configEnv)) {
            require_once $configEnv;
        }

        if (!class_exists('AdminDatabase', false)) {
            require_once __DIR__ . '/AdminDatabase.php';
        }

        return AdminDatabase::connectWithParams([
            'host'     => self::envValue(['DB_HOST', 'ADMIN_DB_HOST'], '127.0.0.1'),
            'port'     => (int) self::envValue(['DB_PORT', 'ADMIN_DB_PORT'], '3306'),
            'database' => self::envValue(['DB_DATABASE', 'ADMIN_DB_DATABASE'], ''),
            'username' => self::envValue(['DB_USERNAME', 'ADMIN_DB_USERNAME'], 'root'),
            'password' => self::envValue(['DB_PASSWORD', 'ADMIN_DB_PASSWORD'], ''),
            'charset'  => self::envValue(['DB_CHARSET', 'ADMIN_DB_CHARSET'], 'utf8mb4'),
        ]);
    }

    public static function writeLock(string $root, array $meta = []): void
    {
        $root = self::root($root);
        $storage = $root . '/storage';
        if (!is_dir($storage)) {
            mkdir($storage, 0755, true);
        }

        $payload = array_merge([
            'installed_at' => gmdate('c'),
            'version' => '1.0.0',
        ], $meta);

        file_put_contents(
            self::lockPath($root),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1'
        );
        $stmt->execute(['table' => $table]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param list<string> $keys
     */
    private static function envValue(array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            if (function_exists('getenv')) {
                $value = getenv($key);
                if ($value !== false && trim((string) $value) !== '') {
                    return trim((string) $value);
                }
            }
            if (isset($_ENV[$key]) && trim((string) $_ENV[$key]) !== '') {
                return trim((string) $_ENV[$key]);
            }
            if (isset($_SERVER[$key]) && trim((string) $_SERVER[$key]) !== '') {
                return trim((string) $_SERVER[$key]);
            }
        }

        return $default;
    }
}
