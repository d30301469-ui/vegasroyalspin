<?php

declare(strict_types=1);

/**
 * Backoffice için sağlam, bağımsız sınıf yükleyici.
 *
 * Amaç: manuel require zincirine veya yükleme sırasına bağlı "Class not found"
 * fatal hatalarını tamamen ortadan kaldırmak. Composer vendor/ olmasa da çalışır.
 *
 * - App\* namespace'li sınıflar  -> {app}/<path>.php
 * - Global sınıflar (Admin*, *Service, repository vb.) -> dosya adı = sınıf adı
 *   eşlemesiyle app/, services/, repositories/, controllers/, core/ altında aranır.
 *
 * api/ altındaki ApiXxx sınıfları dosya adıyla eşleşmediğinden zaten
 * admin_require_project_file() ile açıkça yüklenir; bu yükleyici onları etkilemez.
 */
if (!function_exists('admin_register_autoloader')) {
    function admin_register_autoloader(string $appPath, string $projectRoot): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;

        $appPath = rtrim(str_replace('\\', '/', $appPath), '/');
        $projectRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');

        spl_autoload_register(static function (string $class) use ($appPath, $projectRoot): void {
            if (str_contains($class, '\\')) {
                if (str_starts_with($class, 'App\\')) {
                    $file = $appPath . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
                    if (is_file($file)) {
                        require_once $file;
                    }
                }

                return;
            }

            static $map = null;
            if ($map === null) {
                $map = admin_build_class_map($appPath, $projectRoot);
            }

            if (isset($map[$class]) && is_file($map[$class])) {
                require_once $map[$class];
            }
        });
    }
}

if (!function_exists('admin_build_class_map')) {
    /**
     * @return array<string, string> Sınıf adı (dosya adı) => mutlak yol
     */
    function admin_build_class_map(string $appPath, string $projectRoot): array
    {
        $map = [];
        $dirs = [
            $appPath,
            $projectRoot . '/services',
            $projectRoot . '/repositories',
            $projectRoot . '/controllers',
            $projectRoot . '/core',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
                );
            } catch (Throwable) {
                continue;
            }

            foreach ($iterator as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                    continue;
                }
                $name = $file->getBasename('.php');
                // İlk bulunan (app/ önceliklidir) korunur.
                if (!isset($map[$name])) {
                    $map[$name] = str_replace('\\', '/', $file->getPathname());
                }
            }
        }

        return $map;
    }
}
