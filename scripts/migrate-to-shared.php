<?php

declare(strict_types=1);

/**
 * One-shot: move canonical services/api bodies into shared/, leave thin loaders.
 * Prefer admin as source of truth when both exist.
 */

$root = dirname(__DIR__);
$pairs = [
    ['services', 'admin/services', 'shared/services'],
    ['api', 'admin/api', 'shared/api'],
];

foreach ($pairs as [$frontRel, $adminRel, $sharedRel]) {
    $sharedDir = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sharedRel);
    if (!is_dir($sharedDir) && !mkdir($sharedDir, 0777, true) && !is_dir($sharedDir)) {
        fwrite(STDERR, "Cannot create {$sharedDir}\n");
        exit(1);
    }

    $adminDir = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $adminRel);
    $frontDir = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $frontRel);

    $listPhp = static function (string $dir): array {
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (scandir($dir) ?: [] as $f) {
            if (str_ends_with($f, '.php') && is_file($dir . DIRECTORY_SEPARATOR . $f)) {
                $out[] = $f;
            }
        }

        return $out;
    };

    $all = array_values(array_unique(array_merge($listPhp($adminDir), $listPhp($frontDir))));
    sort($all);

    foreach ($all as $file) {
        $adminPath = $adminDir . DIRECTORY_SEPARATOR . $file;
        $frontPath = $frontDir . DIRECTORY_SEPARATOR . $file;
        $sharedPath = $sharedDir . DIRECTORY_SEPARATOR . $file;

        // Skip if already a thin loader (re-run safety)
        $pickSrc = static function (string $path): ?string {
            if (!is_file($path)) {
                return null;
            }
            $c = file_get_contents($path);
            if ($c === false) {
                return null;
            }
            if (preg_match('/Thin loader|canonical implementation lives in shared\//', $c)
                && preg_match('/require_once.+shared\//', $c)
                && substr_count($c, "\n") < 20) {
                return null; // already loader
            }

            return $c;
        };

        $adminBody = $pickSrc($adminPath);
        $frontBody = $pickSrc($frontPath);

        if ($adminBody === null && $frontBody === null) {
            // Both already loaders — ensure shared exists
            if (!is_file($sharedPath)) {
                fwrite(STDERR, "SKIP empty twin (no shared yet): {$sharedRel}/{$file}\n");
            } else {
                echo "SKIP already migrated {$sharedRel}/{$file}\n";
            }
            continue;
        }

        $body = $adminBody ?? $frontBody;
        $frontRaw = is_file($frontPath) ? (string) file_get_contents($frontPath) : '';

        if ($file === 'SiteSettings.php') {
            if (!str_contains($body, 'shared_runtime_base_path')) {
                $helper = <<<'PHP'

if (!function_exists('shared_runtime_base_path')) {
    function shared_runtime_base_path(): string
    {
        if (defined('ADMIN_BASE_PATH')) {
            return (string) ADMIN_BASE_PATH;
        }
        if (defined('BASE_PATH')) {
            return (string) BASE_PATH;
        }

        return dirname(__DIR__, 2);
    }
}


PHP;
                $body = preg_replace('/^<\?php\s*/', "<?php\n" . $helper, $body, 1) ?? $body;
            }
            $body = str_replace('ADMIN_BASE_PATH', 'shared_runtime_base_path()', $body);
            $body = str_replace('BASE_PATH', 'shared_runtime_base_path()', $body);
            $body = str_replace('shared_runtime_base_path()()', 'shared_runtime_base_path()', $body);

            if ($frontRaw !== ''
                && str_contains($frontRaw, 'frontend_database_allowed')
                && !str_contains($body, 'frontend_database_allowed')
            ) {
                if (preg_match('/private static function frontend_database_allowed\(\): bool\s*\{.*?\n    \}/s', $frontRaw, $m)) {
                    $body = preg_replace('/\}\s*$/', $m[0] . "\n}\n", $body, 1) ?? $body;
                }
                if (str_contains($frontRaw, 'if (!self::frontend_database_allowed())')
                    && !str_contains($body, 'self::frontend_database_allowed()')
                ) {
                    $body = preg_replace(
                        '/(public static function ensureDefaults\(\): void\s*\{\s*)/',
                        "$1" . "        if (!self::frontend_database_allowed()) {\n            return;\n        }\n\n",
                        $body,
                        1
                    ) ?? $body;
                }
            }
        }

        if (file_put_contents($sharedPath, $body) === false) {
            fwrite(STDERR, "WRITE FAIL {$sharedPath}\n");
            exit(1);
        }

        $frontLoader = <<<PHP
<?php

/**
 * Thin loader — canonical implementation lives in {$sharedRel}/{$file}
 * Do not duplicate logic here; edit the shared file instead.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/{$sharedRel}/{$file}';

PHP;

        $adminLoader = <<<PHP
<?php

/**
 * Thin loader — canonical implementation lives in {$sharedRel}/{$file}
 * Do not duplicate logic here; edit the shared file instead.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/{$sharedRel}/{$file}';

PHP;

        if (!is_dir($frontDir)) {
            mkdir($frontDir, 0777, true);
        }
        if (!is_dir($adminDir)) {
            mkdir($adminDir, 0777, true);
        }

        file_put_contents($frontPath, $frontLoader);
        file_put_contents($adminPath, $adminLoader);
        echo "OK {$sharedRel}/{$file}\n";
    }
}

echo "DONE\n";
