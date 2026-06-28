<?php

declare(strict_types=1);

final class AdminRequest
{
    public static function path(): string
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';
        $path = str_replace('\\', '/', $path);

        $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
        $scriptDir = ($scriptDir === '/' || $scriptDir === '.') ? '' : '/' . trim($scriptDir, '/');
        if ($scriptDir !== '' && ($path === $scriptDir || str_starts_with($path, $scriptDir . '/'))) {
            $path = substr($path, strlen($scriptDir));
        } elseif ($path === '/admin' || str_starts_with($path, '/admin/')) {
            $path = substr($path, strlen('/admin'));
        }

        $path = '/' . trim((string) $path, '/');
        if ($path === '/index.html') {
            return '/';
        }
        if (str_ends_with($path, '.html')) {
            $path = substr($path, 0, -5);
        }

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public static function isPost(): bool
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    }
}
