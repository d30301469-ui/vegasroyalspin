<?php

declare(strict_types=1);

/**
 * Builds BOTH split-host packages:
 *   dist/admin-host/     → bo-nexthub.site
 *   dist/frontend-host/  → vegasroyalspin.com
 *
 * Usage: php scripts/build-split-hosts.php
 */

$projectRoot = dirname(__DIR__);
$adminOut = $projectRoot . '/dist/admin-host';
$frontendOut = $projectRoot . '/dist/frontend-host';

$phpBinary = (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') ? PHP_BINARY : 'php';

$bundleVendorScript = $projectRoot . '/scripts/bundle-vendor-for-deploy.php';
if (is_file($bundleVendorScript)) {
    passthru($phpBinary . ' ' . escapeshellarg($bundleVendorScript) . ' --ensure-project', $ensureVendorCode);
    if ($ensureVendorCode !== 0) {
        fwrite(STDERR, "ERROR: project vendor/ required for deploy zips — run: composer install --no-dev\n");
        exit(1);
    }
}

passthru($phpBinary . ' ' . escapeshellarg($projectRoot . '/scripts/finalize-split-deploy.php'), $finalizeCode);
if ($finalizeCode !== 0) {
    exit($finalizeCode);
}

passthru($phpBinary . ' ' . escapeshellarg($projectRoot . '/scripts/sync-admin-bundle-into-admin.php'), $syncCode);
if ($syncCode !== 0) {
    exit($syncCode);
}

passthru($phpBinary . ' ' . escapeshellarg($projectRoot . '/scripts/package-admin-server.php') . ' ' . escapeshellarg($adminOut), $adminCode);
if ($adminCode !== 0) {
    exit($adminCode);
}

passthru($phpBinary . ' ' . escapeshellarg($projectRoot . '/scripts/package-admin-services.php') . ' ' . escapeshellarg($projectRoot . '/dist/admin-services'), $servicesCode);
if ($servicesCode !== 0) {
    exit($servicesCode);
}

passthru($phpBinary . ' ' . escapeshellarg($projectRoot . '/scripts/package-frontend-server.php') . ' ' . escapeshellarg($frontendOut) . ' vegasroyalspin.com', $frontendCode);
if ($frontendCode !== 0) {
    exit($frontendCode);
}

copy($projectRoot . '/deploy/env/backend.env.example', $adminOut . '/ENV.example');
copy($projectRoot . '/deploy/env/frontend.vegasroyalspin.env.example', $frontendOut . '/ENV.example');

file_put_contents($projectRoot . '/dist/SPLIT-DEPLOYMENT.txt', implode("\n", [
    'SPLIT DEPLOYMENT',
    '',
    'ADMIN  (bo-nexthub.site):     upload dist/admin-host/*',
    '  (only services/ missing?)   upload dist/admin-services/* → .../services/',
    'FRONTEND (vegasroyalspin.com): upload dist/frontend-host/*',
    '',
    'Both .env must share the same MEMBER_JWT_SECRET.',
    'Frontend has NO database. Admin has MySQL.',
    'Connection: frontend -> https://bo-nexthub.site/api/v2/* only.',
    'vendor/ is included in both zips — NO composer install on server.',
    '',
]) . "\n");

echo "\nAdmin bundle:    {$adminOut}\n";
echo "Frontend bundle: {$frontendOut}\n";
echo "Guide:           {$projectRoot}/dist/SPLIT-DEPLOYMENT.txt\n";
echo "\nCreate zips:\n";
echo "  dist/bo-nexthub-admin.zip      ← admin-host/\n";
echo "  dist/vegasroyalspin-frontend.zip ← frontend-host/\n";

$distDir = $projectRoot . '/dist';
$adminZip = $distDir . '/bo-nexthub-admin.zip';
$frontendZip = $distDir . '/vegasroyalspin-frontend.zip';

$createZip = static function (string $sourceDir, string $zipPath): bool {
    if (!is_dir($sourceDir)) {
        return false;
    }
    if (is_file($zipPath)) {
        unlink($zipPath);
    }
    if (class_exists(ZipArchive::class)) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $filePath = $file->getRealPath();
            $relative = substr((string) $filePath, strlen($sourceDir) + 1);
            $zip->addFile($filePath, str_replace('\\', '/', $relative));
        }
        $zip->close();

        return is_file($zipPath);
    }

    if (PHP_OS_FAMILY === 'Windows') {
        $sourceEsc = str_replace("'", "''", $sourceDir . DIRECTORY_SEPARATOR . '*');
        $zipEsc = str_replace("'", "''", $zipPath);
        $cmd = "powershell -NoProfile -Command \"Compress-Archive -Path '{$sourceEsc}' -DestinationPath '{$zipEsc}' -Force\"";
        exec($cmd, $out, $code);

        return $code === 0 && is_file($zipPath);
    }

    return false;
};

if ($createZip($adminOut, $adminZip)) {
    echo "\nCreated: {$adminZip}\n";
}
if ($createZip($frontendOut, $frontendZip)) {
    echo "Created: {$frontendZip}\n";
}

    passthru($phpBinary . ' ' . escapeshellarg($projectRoot . '/scripts/smoke-install-wizards.php'), $smokeCode);
    if ($smokeCode !== 0) {
        exit($smokeCode);
    }

    passthru($phpBinary . ' ' . escapeshellarg($projectRoot . '/scripts/verify-split-deploy.php'), $verifyCode);
exit($verifyCode !== 0 ? $verifyCode : 0);
