<?php

declare(strict_types=1);

/**
 * Production vendor/ klasörünü deploy paketine ekler (sunucuda composer GEREKMEZ).
 *
 * Usage:
 *   php scripts/bundle-vendor-for-deploy.php /path/to/bundle-root
 *   php scripts/bundle-vendor-for-deploy.php --frontend /path/to/bundle-root
 *   php scripts/bundle-vendor-for-deploy.php --backend /path/to/bundle-root
 *   php scripts/bundle-vendor-for-deploy.php --ensure-project
 */

$projectRoot = dirname(__DIR__);
$phpBinary = (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') ? PHP_BINARY : 'php';

$ensureProjectOnly = in_array('--ensure-project', $argv, true);
$forceFrontend = in_array('--frontend', $argv, true);
$forceBackend = in_array('--backend', $argv, true);

$bundleRoot = null;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--ensure-project' || $arg === '--frontend' || $arg === '--backend' || str_starts_with($arg, '--')) {
        continue;
    }
    $bundleRoot = rtrim(str_replace('\\', '/', (string) $arg), '/');
    break;
}

if ($ensureProjectOnly) {
    exit(ensureProjectVendor($projectRoot, $phpBinary) ? 0 : 1);
}

if ($bundleRoot === null || $bundleRoot === '') {
    fwrite(STDERR, "Usage: php scripts/bundle-vendor-for-deploy.php [--frontend|--backend] <bundle-root>\n");
    fwrite(STDERR, "       php scripts/bundle-vendor-for-deploy.php --ensure-project\n");
    exit(1);
}

if (!is_file($bundleRoot . '/composer.json')) {
    fwrite(STDERR, "ERROR: composer.json missing in {$bundleRoot}\n");
    exit(1);
}

$isFrontendBundle = $forceFrontend || (!$forceBackend && !is_file($bundleRoot . '/services/MegaPayzService.php'));

if (is_file($bundleRoot . '/vendor/autoload.php')) {
    if ($isFrontendBundle && vendorAutoloadReferencesAdmin($bundleRoot . '/vendor')) {
        echo "Removing vendor/ with admin/ classmap references\n";
        removeTree($bundleRoot . '/vendor');
    } else {
        echo "vendor/ already present in bundle\n";
        writeVendorMarker($bundleRoot);
        exit(0);
    }
}

if (!ensureProjectVendor($projectRoot, $phpBinary)) {
    fwrite(STDERR, "ERROR: Could not prepare vendor/ in project root\n");
    exit(1);
}

if (runComposerInstall($bundleRoot, $phpBinary, $projectRoot)) {
    echo "Bundled vendor/ via composer install --no-dev in {$bundleRoot}\n";
    writeVendorMarker($bundleRoot);
    exit(is_file($bundleRoot . '/vendor/autoload.php') ? 0 : 1);
}

if ($isFrontendBundle) {
    fwrite(STDERR, "ERROR: frontend vendor/ must be built with deploy/composer.frontend.json (no admin/ paths).\n");
    fwrite(STDERR, "Install Composer locally (Laragon: C:\\laragon\\bin\\composer\\composer.bat) and re-run build.\n");
    exit(1);
}

echo "composer install in bundle failed; copying production vendor/ from project (backend only)\n";
if (copyProductionVendor($projectRoot, $bundleRoot)) {
    writeVendorMarker($bundleRoot);
    echo "Copied vendor/ from project root → {$bundleRoot}/vendor\n";
    exit(0);
}

fwrite(STDERR, "ERROR: Could not bundle vendor/ for {$bundleRoot}\n");
exit(1);

function ensureProjectVendor(string $projectRoot, string $phpBinary): bool
{
    if (is_file($projectRoot . '/vendor/autoload.php')) {
        return true;
    }

    if (!is_file($projectRoot . '/composer.json')) {
        fwrite(STDERR, "ERROR: composer.json missing in project root\n");
        return false;
    }

    echo "Project vendor/ missing — running composer install --no-dev in {$projectRoot}\n";
    return runComposerInstall($projectRoot, $phpBinary, $projectRoot);
}

function runComposerInstall(string $workingDir, string $phpBinary, string $projectRoot): bool
{
    $composer = resolveComposerCommand($phpBinary, $projectRoot);
    if ($composer === null) {
        fwrite(STDERR, "WARN: composer binary not found\n");
        return false;
    }

    $cmd = $composer . ' install --no-dev --optimize-autoloader --no-interaction --working-dir='
        . escapeshellarg($workingDir);
    passthru($cmd, $code);

    return $code === 0 && is_file($workingDir . '/vendor/autoload.php');
}

function resolveComposerCommand(string $phpBinary, string $projectRoot): ?string
{
    $candidates = [];

    if (is_file($projectRoot . '/composer.phar')) {
        $candidates[] = escapeshellarg($phpBinary) . ' ' . escapeshellarg($projectRoot . '/composer.phar');
    }

    $laragonPhar = 'C:/laragon/bin/composer/composer.phar';
    if (is_file($laragonPhar)) {
        $candidates[] = escapeshellarg($phpBinary) . ' ' . escapeshellarg($laragonPhar);
    }

    foreach ([
        'C:/laragon/bin/composer/composer.bat',
        'C:/laragon/bin/composer/composer',
    ] as $path) {
        if (is_file($path)) {
            $candidates[] = $path;
        }
    }

    $programData = getenv('ProgramData');
    if (is_string($programData) && $programData !== '') {
        $programDataComposer = rtrim($programData, '\\/') . '/ComposerSetup/bin/composer.bat';
        if (is_file($programDataComposer)) {
            $candidates[] = $programDataComposer;
        }
    }

    $candidates[] = 'composer';

    foreach ($candidates as $candidate) {
        if (composerWorks($candidate, $phpBinary)) {
            return $candidate;
        }
    }

    return null;
}

function composerWorks(string $candidate, string $phpBinary): bool
{
    if (str_contains($candidate, 'composer.phar')) {
        exec($candidate . ' --version 2>&1', $output, $code);

        return $code === 0;
    }

    if ($candidate === 'composer') {
        exec('composer --version 2>&1', $output, $code);

        return $code === 0;
    }

    if (PHP_OS_FAMILY === 'Windows' && str_ends_with(strtolower($candidate), '.bat')) {
        $phpDir = dirname(str_replace('\\', '/', $phpBinary));
        $cmd = 'cmd /c "set PATH=' . $phpDir . ';%PATH%&& ' . str_replace('/', '\\', $candidate) . ' --version"';
        exec($cmd . ' 2>&1', $output, $code);

        return $code === 0;
    }

    exec(escapeshellarg($candidate) . ' --version 2>&1', $output, $code);

    return $code === 0;
}

function vendorAutoloadReferencesAdmin(string $vendorDir): bool
{
    foreach (['composer/autoload_classmap.php', 'composer/autoload_static.php'] as $rel) {
        $file = $vendorDir . '/' . $rel;
        if (is_readable($file) && str_contains((string) file_get_contents($file), '/admin/')) {
            return true;
        }
    }

    return false;
}

function copyProductionVendor(string $projectRoot, string $bundleRoot): bool
{
    $source = $projectRoot . '/vendor';
    $dest = $bundleRoot . '/vendor';

    if (!is_file($source . '/autoload.php')) {
        return false;
    }

    if (is_dir($dest)) {
        removeTree($dest);
    }

    copyTree($source, $dest);
    pruneDevPackages($dest);

    return is_file($dest . '/autoload.php');
}

function pruneDevPackages(string $vendorDir): void
{
    foreach (['phpunit', 'nikic', 'phar-io', 'theseer', 'sebastian', 'myclabs', 'doctrine/instantiator'] as $top) {
        $path = $vendorDir . '/' . $top;
        if (is_dir($path)) {
            removeTree($path);
        }
    }
}

function writeVendorMarker(string $bundleRoot): void
{
    $marker = $bundleRoot . '/VENDOR-BUNDLED.txt';
    file_put_contents($marker, implode("\n", [
        'vendor/ is pre-installed in this deploy package.',
        'Do NOT run composer install on the production server unless you know why.',
        'Bundled at: ' . gmdate('c'),
        '',
    ]));
}

function copyTree(string $source, string $destination): void
{
    mkdir($destination, 0755, true);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $target = $destination . '/' . $iterator->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0755, true);
            }
        } else {
            copy($item->getPathname(), $target);
        }
    }
}

function removeTree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($dir);
}
