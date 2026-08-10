<?php

declare(strict_types=1);

/**
 * After migrate-to-shared: fix dirname(__DIR__) assumptions and wire runtime.php into loaders.
 */

$root = dirname(__DIR__);

$runtimeRequireFront = "require_once dirname(__DIR__) . '/shared/runtime.php';\n";
$runtimeRequireAdmin = "require_once dirname(__DIR__, 2) . '/shared/runtime.php';\n";

foreach (['services', 'api'] as $tree) {
    foreach ([$tree, 'admin/' . $tree] as $relDir) {
        $dir = $root . '/' . $relDir;
        if (!is_dir($dir)) {
            continue;
        }
        $isAdmin = str_starts_with($relDir, 'admin/');
        foreach (scandir($dir) ?: [] as $file) {
            if (!str_ends_with($file, '.php')) {
                continue;
            }
            $path = $dir . '/' . $file;
            $body = (string) file_get_contents($path);
            if (!str_contains($body, 'Thin loader')) {
                continue;
            }
            if (str_contains($body, 'shared/runtime.php')) {
                continue;
            }
            $inject = $isAdmin ? $runtimeRequireAdmin : $runtimeRequireFront;
            $body = preg_replace(
                '/(declare\(strict_types=1\);\s*\n)/',
                "$1\n" . $inject,
                $body,
                1
            ) ?? $body;
            file_put_contents($path, $body);
            echo "loader+runtime {$relDir}/{$file}\n";
        }
    }
}

/** @return list<string> */
function shared_php_files(string $dir): array
{
    $out = [];
    foreach (scandir($dir) ?: [] as $f) {
        if (str_ends_with($f, '.php') && is_file($dir . '/' . $f)) {
            $out[] = $dir . '/' . $f;
        }
    }

    return $out;
}

$replacements = [
    // Package-scoped paths (admin/ or frontend root)
    "dirname(__DIR__) . '/config/" => "shared_package_root() . '/config/",
    'dirname(__DIR__) . "/config/' => 'shared_package_root() . "/config/',
    "dirname(__DIR__) . '/database/" => "shared_package_root() . '/database/",
    "dirname(__DIR__) . '/storage/" => "shared_package_root() . '/storage/",
    "dirname(__DIR__) . '/app/" => "shared_package_root() . '/app/",
    // Monorepo paths
    "dirname(__DIR__) . '/admin/" => "shared_project_root() . '/admin/",
    "dirname(__DIR__) . '/scripts/" => "shared_project_root() . '/scripts/",
    // Relative config from shared/{api,services}
    "__DIR__ . '/../config/" => "shared_package_root() . '/config/",
    // BASE_PATH fallbacks that meant package root
    "defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__)" => "defined('BASE_PATH') ? (string) BASE_PATH : shared_package_root()",
    "defined('BASE_PATH') ? rtrim((string) BASE_PATH, '/\\\\') : dirname(__DIR__)" => "defined('BASE_PATH') ? rtrim((string) BASE_PATH, '/\\\\') : shared_package_root()",
    "defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)" => "defined('BASE_PATH') ? BASE_PATH : shared_package_root()",
    "(defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__))" => "(defined('BASE_PATH') ? (string) BASE_PATH : shared_package_root())",
    "(defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__))" => "(defined('BASE_PATH') ? BASE_PATH : shared_package_root())",
];

foreach (array_merge(shared_php_files($root . '/shared/api'), shared_php_files($root . '/shared/services')) as $path) {
    $before = (string) file_get_contents($path);
    $after = $before;
    foreach ($replacements as $from => $to) {
        $after = str_replace($from, $to, $after);
    }

    // bootstrap: resolve paths.php via package root
    if (str_ends_with($path, 'bootstrap.php')) {
        $after = str_replace(
            "require_once __DIR__ . '/../config/paths.php';",
            "require_once shared_package_root() . '/config/paths.php';",
            $after
        );
        // Ensure runtime is available even if caller forgot
        if (!str_contains($after, 'shared_package_root')) {
            // still need function — loaders inject runtime; also self-load
        }
        if (!str_contains($after, "shared/runtime.php")) {
            $after = preg_replace(
                '/^<\?php\s*/',
                "<?php\n\nrequire_once dirname(__DIR__) . '/runtime.php';\n\n",
                $after,
                1
            ) ?? $after;
        }
    }

    // SiteSettings deploy_domains should use project root (exists on frontend)
    if (str_ends_with($path, 'SiteSettings.php')) {
        $after = str_replace(
            "shared_package_root() . '/config/deploy_domains.php'",
            "shared_project_root() . '/config/deploy_domains.php'",
            $after
        );
    }

    if ($after !== $before) {
        file_put_contents($path, $after);
        echo 'fixed ' . substr($path, strlen($root) + 1) . "\n";
    }
}

echo "DONE\n";
