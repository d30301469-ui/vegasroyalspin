<?php

declare(strict_types=1);

if (!function_exists('admin_paths_bootstrap')) {
    function admin_paths_bootstrap(): void
    {
        $paths = admin_panel_paths();

        if (!defined('ADMIN_BASE_PATH')) {
            define('ADMIN_BASE_PATH', $paths['install_root']);
        }
        if (!defined('ADMIN_APP_PATH')) {
            define('ADMIN_APP_PATH', $paths['panel_app']);
        }
        if (!defined('ADMIN_VIEW_PATH')) {
            define('ADMIN_VIEW_PATH', ADMIN_APP_PATH . '/Views');
        }

        admin_load_env_files(ADMIN_BASE_PATH);

        $root = admin_project_root();
        if ($root !== ADMIN_BASE_PATH) {
            admin_load_env_files($root);
        }
        if (!defined('METROPOL_ROOT')) {
            define('METROPOL_ROOT', $root);
        }

        admin_define_project_constants($root);
    }

    /**
     * @return array{panel_app: string, install_root: string}
     */
    function admin_panel_paths(): array
    {
        static $paths = null;
        if (is_array($paths)) {
            return $paths;
        }

        // This file lives at .../app/Core/AdminPaths.php
        $panelAppDir = str_replace('\\', '/', dirname(__DIR__));
        $installRoot = dirname($panelAppDir);

        return $paths = [
            'panel_app' => $panelAppDir,
            'install_root' => $installRoot,
        ];
    }

    function admin_detect_install_root(): string
    {
        return admin_panel_paths()['install_root'];
    }

    function admin_detect_app_path(): string
    {
        $paths = admin_panel_paths();

        return admin_is_readable_file($paths['panel_app'] . '/bootstrap.php')
            ? $paths['panel_app']
            : ($paths['install_root'] . '/app');
    }

    function admin_project_root(): string
    {
        static $root = null;
        if (is_string($root) && $root !== '') {
            return $root;
        }

        foreach (['METROPOL_ROOT', 'PROJECT_ROOT'] as $envKey) {
            $fromEnv = getenv($envKey);
            if (is_string($fromEnv) && trim($fromEnv) !== '') {
                return $root = rtrim(str_replace('\\', '/', trim($fromEnv)), '/');
            }
        }

        $installRoot = admin_detect_install_root();

        // Self-contained admin deploy: services/config live under admin/ (or flat site root).
        // Monorepo guard: if installRoot is named 'admin' and its parent has project markers,
        // the parent is the actual monorepo root — prefer it over the admin subdirectory.
        if (
            admin_is_readable_file($installRoot . '/services/MegaPayzService.php')
            || admin_is_readable_file($installRoot . '/config/app.php')
        ) {
            $maybeMonorepoParent = dirname($installRoot);
            if (
                basename($installRoot) === 'admin'
                && $maybeMonorepoParent !== $installRoot
                && admin_path_is_allowed($maybeMonorepoParent)
                && admin_has_project_marker($maybeMonorepoParent)
            ) {
                return $root = $maybeMonorepoParent;
            }
            return $root = $installRoot;
        }

        $adminBase = str_replace('\\', '/', defined('ADMIN_BASE_PATH') ? ADMIN_BASE_PATH : $installRoot);

        if (admin_has_project_marker($adminBase)) {
            return $root = $adminBase;
        }

        $parentRoot = dirname($adminBase);
        if (
            basename($adminBase) === 'admin'
            && $parentRoot !== $adminBase
            && admin_path_is_allowed($parentRoot)
            && admin_has_project_marker($parentRoot)
        ) {
            return $root = $parentRoot;
        }

        return $root = $adminBase;
    }

    function admin_path_is_allowed(string $path): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/') . '/';
        $openBasedir = ini_get('open_basedir');
        if (!is_string($openBasedir) || trim($openBasedir) === '') {
            return true;
        }

        foreach (explode(PATH_SEPARATOR, $openBasedir) as $allowed) {
            $allowed = rtrim(str_replace('\\', '/', trim($allowed)), '/') . '/';
            if ($allowed !== '/' && str_starts_with($path, $allowed)) {
                return true;
            }
        }

        return false;
    }

    function admin_is_readable_file(string $path): bool
    {
        return admin_path_is_allowed($path) && is_file($path) && is_readable($path);
    }

    function admin_is_readable_dir(string $path): bool
    {
        return admin_path_is_allowed($path) && is_dir($path);
    }

    function admin_has_project_marker(string $path): bool
    {
        if (!admin_path_is_allowed($path)) {
            return false;
        }

        return admin_is_readable_file($path . '/config/app.php')
            || admin_is_readable_file($path . '/config/database.php')
            || admin_is_readable_dir($path . '/services');
    }

    function admin_project_path(string $relative = ''): string
    {
        $root = admin_project_root();
        $relative = ltrim(str_replace('\\', '/', $relative), '/');

        return $relative === '' ? $root : $root . '/' . $relative;
    }

    function admin_define_project_constants(string $root): void
    {
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', $root);
        }
        if (!defined('CONFIG_PATH')) {
            define('CONFIG_PATH', $root . '/config');
        }
        if (!defined('SERVICE_PATH')) {
            define('SERVICE_PATH', $root . '/services');
        }
        if (!defined('CONTROLLER_PATH')) {
            define('CONTROLLER_PATH', $root . '/controllers');
        }
        if (!defined('API_PATH')) {
            define('API_PATH', $root . '/api');
        }
        if (!defined('APP_PATH')) {
            define('APP_PATH', $root . '/app');
        }
        if (!defined('STORAGE_PATH')) {
            define('STORAGE_PATH', $root . '/storage');
        }
        if (!defined('REPOSITORY_PATH')) {
            define('REPOSITORY_PATH', $root . '/repositories');
        }
        if (!defined('CORE_PATH')) {
            define('CORE_PATH', $root . '/core');
        }
    }

    function admin_load_env_files(string $root): void
    {
        static $loadedRoots = [];
        $root = rtrim(str_replace('\\', '/', $root), '/');
        if ($root === '' || isset($loadedRoots[$root]) || !admin_path_is_allowed($root)) {
            return;
        }
        $loadedRoots[$root] = true;

        foreach ([$root . '/.env', $root . '/env'] as $file) {
            if (!admin_is_readable_file($file)) {
                continue;
            }
            admin_parse_env_file($file);
        }
    }

    function admin_parse_env_file(string $file): void
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return;
        }

        $canPutenv = function_exists('putenv');

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
            // putenv kapalıysa getenv .env değerlerini görmez; $_ENV/$_SERVER üzerinden de kontrol et.
            if (admin_env($key, '__MISSING__') !== '__MISSING__') {
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

    /**
     * Env okuyucu — putenv/getenv kapalı sunucularda $_ENV/$_SERVER'a düşer.
     */
    function admin_env(string $key, string $default = ''): string
    {
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

        return $default;
    }

    function admin_require_project_file(string $relative): void
    {
        $path = admin_project_path($relative);
        if (!admin_is_readable_file($path)) {
            $root = admin_project_root();
            throw new RuntimeException(sprintf(
                'Required backend file not found: %s (expected at %s). admin/ klasörü eksik — yerelde: php scripts/sync-admin-bundle-into-admin.php sonra admin/ tamamını yükleyin (admin/services/, admin/config/, admin/database/).',
                $relative,
                $path,
                $root
            ));
        }

        require_once $path;
    }

    /**
     * Public URL prefix for panel routes and assets.
     * Set ADMIN_URL_PREFIX= in .env for https://bo-backoffice.site/ (no /admin segment).
     */
    function admin_url_prefix(): string
    {
        static $prefix = null;
        if (is_string($prefix)) {
            return $prefix;
        }

        $configured = getenv('ADMIN_URL_PREFIX');
        if ($configured === false) {
            if (array_key_exists('ADMIN_URL_PREFIX', $_ENV)) {
                $configured = (string) $_ENV['ADMIN_URL_PREFIX'];
            } elseif (array_key_exists('ADMIN_URL_PREFIX', $_SERVER)) {
                $configured = (string) $_SERVER['ADMIN_URL_PREFIX'];
            }
        }
        if ($configured !== false) {
            $configured = trim(str_replace('\\', '/', $configured));
            if ($configured === '' || $configured === '/') {
                return $prefix = '';
            }

            return $prefix = '/' . trim($configured, '/');
        }

        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $detected = rtrim(str_replace('/index.php', '', $scriptName), '/');
        if ($detected === '' || $detected === '.') {
            $uriPath = str_replace('\\', '/', (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
            $uriPath = '/' . trim($uriPath, '/');
            if (preg_match('#^/(admin|public)(?:/|$)#i', $uriPath, $m) === 1) {
                $detected = '/' . strtolower((string) ($m[1] ?? ''));
            } else {
                return $prefix = '';
            }
        }

        // On dedicated admin hosts (admin.example.com), Apache may still expose
        // SCRIPT_NAME as /admin/index.php due to shared docroot rewrites.
        // In that setup links must stay root-based (/dashboard), not /admin/*.
        $requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $requestHost = preg_replace('/:\\d+$/', '', $requestHost) ?? '';
        $backendHost = strtolower(admin_env('BACKEND_HOST', ''));
        $isDedicatedAdminHost = $requestHost !== ''
            && (str_starts_with($requestHost, 'admin.') || ($backendHost !== '' && $requestHost === $backendHost));
        if ($isDedicatedAdminHost && in_array($detected, ['/admin', '/public'], true)) {
            return $prefix = '';
        }

        return $prefix = $detected;
    }
}
