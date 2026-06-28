<?php

declare(strict_types=1);

$root = dirname(__DIR__) . '/admin/api/v2';
$files = array_merge(
    [$root . '/bootstrap.php', $root . '/bootstrap-member-local.php'],
    glob($root . '/includes/*.php') ?: [],
    glob($root . '/routes/*.php') ?: []
);

$pattern = '/^\s*declare\(strict_types=1\);\s*\R/m';
$changed = 0;

foreach ($files as $file) {
    if (!is_file($file)) {
        continue;
    }
    $content = (string) file_get_contents($file);
    $updated = preg_replace($pattern, '', $content, -1, $count);
    if ($count > 0 && is_string($updated)) {
        file_put_contents($file, $updated);
        echo str_replace(dirname(__DIR__) . '/', '', $file) . "\n";
        $changed += $count;
    }
}

echo "Done: {$changed} declare lines removed.\n";
