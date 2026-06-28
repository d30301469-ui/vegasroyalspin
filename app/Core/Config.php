<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    /** @var array<string, mixed> */
    private static array $cache = [];

    /** @return mixed */
    public static function get(string $key, mixed $default = null): mixed
    {
        [$file, $item] = array_pad(explode('.', $key, 2), 2, null);
        $config = self::file($file);
        if ($item === null || $item === '') {
            return $config;
        }

        return $config[$item] ?? $default;
    }

    /** @return array<string, mixed> */
    public static function file(string $name): array
    {
        if (isset(self::$cache[$name]) && is_array(self::$cache[$name])) {
            return self::$cache[$name];
        }

        $path = CONFIG_PATH . '/' . $name . '.php';
        if (!is_file($path)) {
            return self::$cache[$name] = [];
        }
        $config = require $path;

        return self::$cache[$name] = is_array($config) ? $config : [];
    }
}

