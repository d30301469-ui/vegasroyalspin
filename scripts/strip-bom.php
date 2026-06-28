<?php

declare(strict_types=1);

$files = [
    dirname(__DIR__) . '/admin/api/v2/index.php',
    dirname(__DIR__) . '/admin/api/v2/includes/member_api_kernel.php',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing: {$file}\n");
        continue;
    }
    $content = (string) file_get_contents($file);
    $stripped = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    if ($stripped === null || $stripped === $content) {
        echo "No BOM: {$file}\n";
        continue;
    }
    file_put_contents($file, $stripped);
    echo "Stripped BOM: {$file}\n";
}
