<?php

declare(strict_types=1);

/**
 * Rewrite admin thin loaders so shared/ resolves in both layouts:
 * - monorepo: {repo}/shared
 * - standalone admin docroot: {admin-root}/shared
 */

$root = dirname(__DIR__);

$template = <<<'PHP'
<?php

/**
 * Thin loader — canonical implementation lives in shared/__SHARED_REL__
 * Do not duplicate logic here; edit the shared file instead.
 */
declare(strict_types=1);

$__sharedRoot = null;
foreach ([dirname(__DIR__, 2) . '/shared', dirname(__DIR__) . '/shared'] as $__dir) {
    if (is_file($__dir . '/runtime.php')) {
        $__sharedRoot = $__dir;
        break;
    }
}
if ($__sharedRoot === null) {
    throw new RuntimeException('shared/ not found for __SHARED_REL__ (deploy shared/ next to admin or at monorepo root).');
}
require_once $__sharedRoot . '/runtime.php';
require_once $__sharedRoot . '/__SHARED_REL__';

PHP;

foreach (['services', 'api'] as $tree) {
    $dir = $root . '/admin/' . $tree;
    foreach (scandir($dir) ?: [] as $file) {
        if (!str_ends_with($file, '.php')) {
            continue;
        }
        $path = $dir . '/' . $file;
        $body = (string) file_get_contents($path);
        if (!str_contains($body, 'Thin loader')) {
            continue;
        }
        $rel = $tree . '/' . $file;
        $out = str_replace('__SHARED_REL__', $rel, $template);
        file_put_contents($path, $out);
        echo "admin loader {$rel}\n";
    }
}

echo "DONE\n";
