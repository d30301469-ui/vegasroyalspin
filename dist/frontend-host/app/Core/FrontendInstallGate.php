<?php

declare(strict_types=1);

final class FrontendInstallGate
{
    private const PLACEHOLDER_SECRETS = [
        'change-me',
        'change-me-to-a-random',
        'CHANGE-ME',
        'your-secret',
    ];

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
            if (!is_readable(self::envPath($root))) {
                return false;
            }
            self::loadEnv($root);
            $appKey = self::envValue(['APP_KEY']);
            $jwt = self::envValue(['MEMBER_JWT_SECRET']);
            if (!self::isValidSecret($appKey) || !self::isValidSecret($jwt)) {
                return false;
            }

            return true;
        }

        if (!is_readable(self::envPath($root))) {
            return false;
        }

        self::loadEnv($root);

        if (!self::envBool('FRONTEND_API_ONLY')) {
            return false;
        }

        $backend = self::envValue(['BACKEND_URL', 'BACKEND_FALLBACK_URL']);
        $apiMain = self::envValue(['API_BACKEND_MAIN_BASE_URL', 'BACKEND_API_BASE_URL']);
        $appKey = self::envValue(['APP_KEY']);
        $jwt = self::envValue(['MEMBER_JWT_SECRET']);

        if ($backend === '' || $apiMain === '') {
            return false;
        }

        if (!self::isValidSecret($appKey) || !self::isValidSecret($jwt)) {
            return false;
        }

        self::writeLock($root, ['legacy_detected' => true]);

        return true;
    }

    public static function loadEnv(string $root, bool $includeExampleFallback = false): void
    {
        $root = self::root($root);
        $canPutenv = function_exists('putenv');
        /** @var array<string, true> $loadedKeys */
        $loadedKeys = [];

        $applyPair = static function (string $key, string $value) use ($canPutenv): void {
            if ($key === '') {
                return;
            }
            if ($canPutenv) {
                @putenv($key . '=' . $value);
            }
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        };

        $parseFile = static function (string $file, bool $forceOverwrite) use (&$loadedKeys, $applyPair): void {
            if (!is_readable($file)) {
                return;
            }
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                return;
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
                if (!$forceOverwrite) {
                    if (isset($loadedKeys[$key])) {
                        continue;
                    }
                    if (self::envValue([$key], '__MISSING__') !== '__MISSING__') {
                        continue;
                    }
                }
                $value = trim($value);
                if (
                    (str_starts_with($value, '"') && str_ends_with($value, '"'))
                    || (str_starts_with($value, "'") && str_ends_with($value, "'"))
                ) {
                    $value = substr($value, 1, -1);
                }
                $applyPair($key, $value);
                $loadedKeys[$key] = true;
            }
        };

        // Production: only .env — never ENV.example (misleading health/diagnostics).
        $parseFile($root . '/.env', true);
        if ($includeExampleFallback && !isset($loadedKeys['FRONTEND_API_ONLY'])) {
            $parseFile($root . '/ENV.example', false);
        }
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
            'type' => 'frontend-api-only',
            'version' => '1.0.0',
        ], $meta);

        file_put_contents(
            self::lockPath($root),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    public static function isValidSecret(string $secret): bool
    {
        $secret = trim($secret);
        if (strlen($secret) < 32) {
            return false;
        }
        $lower = strtolower($secret);
        foreach (self::PLACEHOLDER_SECRETS as $placeholder) {
            if (str_contains($lower, strtolower($placeholder))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $keys
     */
    public static function envValue(array $keys, string $default = ''): string
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

    public static function envBool(string $key): bool
    {
        return in_array(strtolower(self::envValue([$key])), ['1', 'true', 'yes', 'on'], true);
    }
}
