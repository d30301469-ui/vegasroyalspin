<?php

declare(strict_types=1);

/**
 * Enforce shared/ SoT architecture: no duplicated service/api bodies.
 *
 * Usage:
 *   php scripts/check-twins.php
 *
 * Rules:
 * - Canonical logic lives in shared/services and shared/api
 * - Root services|api and admin/services|api must be thin loaders only
 * - Thin loaders must require the matching shared file
 * - config/deploy_domains.php may still exist as root ↔ admin twin (root SoT)
 */

$root = dirname(__DIR__);

$errors = [];
$ok = 0;

/**
 * @return list<string>
 */
function twin_list_php(string $dir): array
{
    if (!is_dir($dir)) {
        return [];
    }
    $out = [];
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        if (!str_ends_with(strtolower($name), '.php')) {
            continue;
        }
        $out[] = $name;
    }
    sort($out);

    return $out;
}

function is_thin_loader(string $content, string $sharedRel): bool
{
    $content = str_replace("\r\n", "\n", $content);
    if (substr_count($content, "\n") > 40) {
        return false;
    }
    if (!preg_match('/Thin loader|canonical implementation lives in shared\//', $content)) {
        return false;
    }
    $escaped = preg_quote($sharedRel, '#');
    $okRequire = preg_match('#require_once.+shared/' . $escaped . '#', $content)
        || preg_match('#require_once\s+\$__sharedRoot\s*\.\s*[\'"]/' . $escaped . '[\'"]#', $content);
    if (!$okRequire) {
        return false;
    }
    // Must not redefine a final class / large implementation
    if (preg_match('/^\s*(?:final\s+)?class\s+\w+/m', $content)) {
        return false;
    }

    return true;
}

foreach (['services', 'api'] as $tree) {
    $sharedDir = $root . '/shared/' . $tree;
    $sharedFiles = twin_list_php($sharedDir);
    if ($sharedFiles === []) {
        $errors[] = "missing shared/{$tree} canonical tree";
        continue;
    }

    foreach ($sharedFiles as $file) {
        $sharedPath = $sharedDir . '/' . $file;
        $sharedBody = (string) file_get_contents($sharedPath);
        if (preg_match('/Thin loader/', $sharedBody) && substr_count($sharedBody, "\n") < 20) {
            $errors[] = "shared/{$tree}/{$file} looks like a loader (canonical body missing)";
            continue;
        }

        foreach ([$tree . '/' . $file, 'admin/' . $tree . '/' . $file] as $loaderRel) {
            $loaderPath = $root . '/' . $loaderRel;
            if (!is_file($loaderPath)) {
                $errors[] = "missing thin loader: {$loaderRel}";
                continue;
            }
            $loaderBody = (string) file_get_contents($loaderPath);
            $sharedRel = $tree . '/' . $file;
            if (!is_thin_loader($loaderBody, $sharedRel)) {
                $errors[] = "not a thin loader (duplicate body?): {$loaderRel}";
                continue;
            }
            $ok++;
        }
    }

    // Extra loaders without shared canonical
    foreach ([$tree, 'admin/' . $tree] as $loaderTree) {
        foreach (twin_list_php($root . '/' . $loaderTree) as $file) {
            if (!is_file($sharedDir . '/' . $file)) {
                $errors[] = "loader without shared canonical: {$loaderTree}/{$file}";
            }
        }
    }
}

if (!is_file($root . '/shared/runtime.php')) {
    $errors[] = 'missing shared/runtime.php';
}

// deploy_domains still twin-synced (root SoT)
$ddRoot = $root . '/config/deploy_domains.php';
$ddAdmin = $root . '/admin/config/deploy_domains.php';
if (is_file($ddRoot) && is_file($ddAdmin)) {
    $a = str_replace("\r\n", "\n", (string) file_get_contents($ddRoot));
    $b = str_replace("\r\n", "\n", (string) file_get_contents($ddAdmin));
    if ($a !== $b) {
        $errors[] = 'config/deploy_domains.php drifted from admin twin (root is SoT)';
    } else {
        $ok++;
    }
}

echo 'Shared architecture check: ' . $ok . " thin loaders OK\n";
if ($errors === []) {
    echo "OK: no duplicate service/api bodies\n";
    exit(0);
}

echo 'FAIL (' . count($errors) . "):\n";
foreach ($errors as $e) {
    echo "  - {$e}\n";
}
exit(1);
