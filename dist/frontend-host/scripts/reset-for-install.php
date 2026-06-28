<?php

declare(strict_types=1);

/**
 * Reset install state so /install wizard appears again.
 *
 * Usage (on server):
 *   cd /www/wwwroot/vegasroyalspin.com   # or bo-nexthub.site
 *   php scripts/reset-for-install.php
 *   php scripts/reset-for-install.php --env   # also remove .env
 */

$root = dirname(__DIR__);
$removeEnv = in_array('--env', $argv, true) || in_array('-e', $argv, true);

$isBackend = is_file($root . '/app/Core/AdminInstallGate.php');
$isFrontend = is_file($root . '/app/Core/FrontendInstallGate.php');

if ($isBackend && $isFrontend) {
    fwrite(STDERR, "Both AdminInstallGate and FrontendInstallGate found — use separate host roots.\n");
    exit(1);
}

if (!$isFrontend && !$isBackend) {
    fwrite(STDERR, "Cannot detect site type at {$root}\n");
    fwrite(STDERR, "Run from vegasroyalspin.com or bo-nexthub.site project root.\n");
    exit(1);
}

$role = $isFrontend ? 'frontend' : 'backend';
$lock = $root . '/storage/install.lock';
$csrf = $root . '/storage/install_csrf.token';
$env = $root . '/.env';

echo "=== Reset install ({$role}) ===\n";
echo "Root: {$root}\n\n";

$removed = [];
foreach ([$lock, $csrf] as $path) {
    if (is_file($path)) {
        unlink($path);
        $removed[] = basename(dirname($path)) . '/' . basename($path);
    }
}

if ($removeEnv && is_file($env)) {
    unlink($env);
    $removed[] = '.env';
}

if ($removed === []) {
    echo "Nothing to remove (install.lock / install_csrf.token already absent";
    echo $removeEnv ? ", .env absent" : '';
    echo ").\n";
} else {
    echo "Removed:\n- " . implode("\n- ", $removed) . "\n";
}

echo "\nNext steps:\n";
if ($isFrontend) {
    echo "1. aaPanel → Site directory = {$root} (NOT {$root}/public)\n";
    echo "2. systemctl restart httpd   # or: service httpd restart\n";
    echo "3. Open https://vegasroyalspin.com/install\n";
    if (!$removeEnv && is_file($env)) {
        echo "   (Existing .env kept — wizard may still skip if secrets are valid. Re-run with --env to wipe .env.)\n";
    }
} else {
    echo "1. aaPanel → Site directory = {$root}\n";
    echo "2. systemctl restart httpd\n";
    echo "3. Open https://bo-nexthub.site/install\n";
    if (!$removeEnv && is_file($env)) {
        echo "   (Existing .env kept — wizard uses DB from .env if valid. Re-run with --env for clean DB prompt.)\n";
    }
}

exit(0);
