<?php

declare(strict_types=1);

$roots = [
    dirname(__DIR__) . '/admin/api/v2',
    dirname(__DIR__) . '/admin/app',
    dirname(__DIR__) . '/admin/index.php',
];

$files = [];
foreach ($roots as $root) {
    if (is_file($root)) {
        $files[] = $root;
        continue;
    }
    if (!is_dir($root)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($it as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

foreach ($files as $file) {
    $head = (string) file_get_contents($file, false, null, 0, 3);
    if ($head === "\xEF\xBB\xBF") {
        echo str_replace('\\', '/', $file) . "\n";
    }
}
