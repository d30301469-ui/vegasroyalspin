<?php

declare(strict_types=1);

/**
 * Builds a flat upload bundle for bo-nexthub.site (panel at domain root).
 *
 * Usage: php scripts/package-admin-server.php [output-dir]
 */

$projectRoot = dirname(__DIR__);
$outputRoot = isset($argv[1]) && trim((string) $argv[1]) !== ''
    ? rtrim(str_replace('\\', '/', (string) $argv[1]), '/')
    : $projectRoot . '/dist/admin-host';

$adminRoot = $projectRoot . '/admin';

$phpBinary = (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') ? PHP_BINARY : 'php';
$syncScript = $projectRoot . '/scripts/sync-admin-bundle-into-admin.php';
if (is_file($syncScript)) {
    passthru($phpBinary . ' ' . escapeshellarg($syncScript), $syncCode);
    if ($syncCode !== 0) {
        exit($syncCode);
    }
}

$verifyApiSync = $projectRoot . '/scripts/verify-api-sync.php';
if (is_file($verifyApiSync)) {
    passthru($phpBinary . ' ' . escapeshellarg($verifyApiSync), $verifyApiSyncCode);
    if ($verifyApiSyncCode !== 0) {
        exit($verifyApiSyncCode);
    }
}

$layerTest = $projectRoot . '/scripts/test-all-layers.php';
if (is_file($layerTest)) {
    passthru($phpBinary . ' ' . escapeshellarg($layerTest), $layerCode);
    if ($layerCode !== 0) {
        exit($layerCode);
    }
}

$verifyScript = $projectRoot . '/scripts/verify-services-sync.php';
if (is_file($verifyScript)) {
    $phpBinary = (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') ? PHP_BINARY : 'php';
    passthru($phpBinary . ' ' . escapeshellarg($verifyScript), $verifyCode);
    if ($verifyCode !== 0) {
        exit($verifyCode);
    }
}

$backendDirs = [
    'config',
    'services',
    'controllers',
    'database',
    'repositories',
    'core',
    'storage',
];

$backendFiles = [
];

$adminRootFiles = [
    'index.php',
    'install.php',
    'health.php',
    'ping.php',
    'diagnose.php',
    'drakon_api.php',
    'admin-ui.js',
    '2026.js',
    'vendors.js',
    'vendor-fullcalendar.js',
    'vendor-chartjs.js',
    'runtime.js',
    'style.css',
    '.htaccess',
];

if (is_dir($outputRoot)) {
    echo "Removing existing output: {$outputRoot}\n";
    removeTree($outputRoot);
}

mkdir($outputRoot, 0755, true);

foreach ($backendDirs as $dir) {
    $source = is_dir($adminRoot . '/' . $dir)
        ? $adminRoot . '/' . $dir
        : $projectRoot . '/' . $dir;
    if (!is_dir($source)) {
        fwrite(STDERR, "Missing required directory: {$dir}\n");
        continue;
    }
    copyTree($source, $outputRoot . '/' . $dir);
    echo "Copied {$dir}/\n";
}

foreach ($backendFiles as $file) {
    $source = $projectRoot . '/' . $file;
    if (!is_file($source)) {
        continue;
    }
    copy($source, $outputRoot . '/' . $file);
    echo "Copied {$file}\n";
}

$projectApi = $projectRoot . '/api';
if (is_dir($projectApi)) {
    copyTree($projectApi, $outputRoot . '/api');
    echo "Copied api/ (project)\n";
}

$adminApi = $projectRoot . '/admin/api';
if (is_dir($adminApi)) {
    copyTree($adminApi, $outputRoot . '/api');
    echo "Copied api/ (admin)\n";
}

$appOutput = $outputRoot . '/app';
mkdir($appOutput, 0755, true);

$projectApp = $projectRoot . '/app';
if (is_dir($projectApp)) {
    copyTree($projectApp, $appOutput);
    echo "Copied app/ (framework)\n";
}

$frameworkBootstrap = $projectApp . '/bootstrap.php';
if (is_file($frameworkBootstrap)) {
    copy($frameworkBootstrap, $appOutput . '/framework_bootstrap.php');
    echo "Created app/framework_bootstrap.php\n";
}

$adminApp = $projectRoot . '/admin/app';
if (is_dir($adminApp)) {
    copyTree($adminApp, $appOutput);
    echo "Copied app/ (panel)\n";
}

foreach ($adminRootFiles as $file) {
    $source = $projectRoot . '/admin/' . $file;
    if (!is_file($source)) {
        continue;
    }
    copy($source, $outputRoot . '/' . $file);
    echo "Copied admin/{$file}\n";
}

$installStatus = $projectRoot . '/install-status.php';
if (is_file($installStatus)) {
    copy($installStatus, $outputRoot . '/install-status.php');
    echo "Copied install-status.php\n";
}

$adminBin = $projectRoot . '/admin/bin';
if (is_dir($adminBin)) {
    copyTree($adminBin, $outputRoot . '/bin');
    echo "Copied admin/bin/\n";
}

$adminExcludeRelativePaths = [
    'app/Controllers/FrontendInstallController.php',
    'app/Core/FrontendInstallGate.php',
    'app/Services/FrontendInstaller.php',
];
$removedAdmin = removeExcludedPaths($outputRoot, $adminExcludeRelativePaths);
if ($removedAdmin !== []) {
    echo "Excluded frontend-only install files:\n- " . implode("\n- ", $removedAdmin) . "\n";
}

$htaccessSource = $projectRoot . '/deploy/apache/bo-nexthub.site.htaccess';
if (is_file($htaccessSource)) {
    copy($htaccessSource, $outputRoot . '/.htaccess');
    echo "Copied deploy/apache/bo-nexthub.site.htaccess → .htaccess\n";
}

$aapanelDeploy = $projectRoot . '/deploy/aapanel';
if (is_dir($aapanelDeploy)) {
    copyTree($aapanelDeploy, $outputRoot . '/deploy/aapanel');
    echo "Copied deploy/aapanel/ (aaPanel kurulum notları)\n";
}

$apacheDeploy = $projectRoot . '/deploy/apache';
if (is_dir($apacheDeploy)) {
    copyTree($apacheDeploy, $outputRoot . '/deploy/apache');
    echo "Copied deploy/apache/ (Apache .htaccess + vhost örnekleri)\n";
}

$envExample = $projectRoot . '/deploy/env/backend.env.example';
if (is_readable($envExample)) {
    copy($envExample, $outputRoot . '/ENV.example');
    echo "Copied deploy/env/backend.env.example → ENV.example\n";
}

$installLock = $outputRoot . '/storage/install.lock';
if (is_file($installLock)) {
    unlink($installLock);
    echo "Removed storage/install.lock from bundle\n";
}
$bundledEnv = $outputRoot . '/.env';
if (is_file($bundledEnv)) {
    unlink($bundledEnv);
    echo "Removed .env from bundle\n";
}

foreach ([
    'storage/cache/cms_api_circuit.json',
    'storage/logs/drakon_callback_hits.log',
    'storage/install_csrf.token',
] as $devArtifact) {
    $artifactPath = $outputRoot . '/' . $devArtifact;
    if (is_file($artifactPath)) {
        unlink($artifactPath);
        echo "Removed dev artifact: {$devArtifact}\n";
    }
}

$writeComposerScript = $projectRoot . '/scripts/write-bundle-composer-json.php';
if (is_file($writeComposerScript)) {
    passthru($phpBinary . ' ' . escapeshellarg($writeComposerScript) . ' backend ' . escapeshellarg($outputRoot), $composerJsonCode);
    if ($composerJsonCode !== 0) {
        fwrite(STDERR, "ERROR: could not write backend composer.json\n");
        exit(1);
    }
}

$bundleVendorScript = $projectRoot . '/scripts/bundle-vendor-for-deploy.php';
if (is_file($bundleVendorScript)) {
    passthru(
        $phpBinary . ' ' . escapeshellarg($bundleVendorScript) . ' --backend ' . escapeshellarg($outputRoot),
        $vendorCode
    );
    if ($vendorCode !== 0 || !is_file($outputRoot . '/vendor/autoload.php')) {
        fwrite(STDERR, "ERROR: vendor/ bundle failed — deploy zip must include vendor/autoload.php\n");
        exit(1);
    }
}

$opsScripts = ['reset-for-install.php', 'post-upload-check.php', 'diagnose-web-stack.php'];
$scriptsOut = $outputRoot . '/scripts';
if (!is_dir($scriptsOut)) {
    mkdir($scriptsOut, 0755, true);
}
foreach ($opsScripts as $script) {
    $src = $projectRoot . '/scripts/' . $script;
    if (!is_file($src)) {
        continue;
    }
    copy($src, $scriptsOut . '/' . $script);
    echo "Copied scripts/{$script}\n";
}

$requiredFiles = [
    'services/MegaPayzService.php',
    'services/DrakonService.php',
    'services/BgamingService.php',
    'services/MemberJwtService.php',
    'services/BackendApiClient.php',
    'services/MemberLoginService.php',
    'services/MemberRegisterService.php',
    'services/SlotGamesQuery.php',
    'services/PaymentCallbackService.php',
    'services/ProfileApiHelper.php',
    'config/cloudflare.php',
    'config/app.php',
    'config/bootstrap_api.php',
    'config/env.php',
    'app/bootstrap.php',
    'app/bootstrap_api.php',
    'app/Config/admin.php',
    'app/Views/install/wizard.php',
    'app/Core/Migrator.php',
    'app/Services/SqlSeedImporter.php',
    'install.php',
    'index.php',
    'install-status.php',
    'ping.php',
    'api/v2/index.php',
    'api/v2/bootstrap.php',
    'database/migrations',
    'database/seed/metropolcasino.sql',
    'vendor/autoload.php',
    'VENDOR-BUNDLED.txt',
];

$missing = [];
foreach ($requiredFiles as $relative) {
    $path = $outputRoot . '/' . $relative;
    if (!file_exists($path)) {
        $missing[] = $relative;
    }
}

if ($missing !== []) {
    fwrite(STDERR, "\nERROR: Bundle is incomplete:\n- " . implode("\n- ", $missing) . "\n");
    exit(1);
}

require_once $outputRoot . '/app/Services/SqlSeedImporter.php';
$seedPath = $outputRoot . '/database/seed/metropolcasino.sql';
$seedValidation = SqlSeedImporter::validateSeedFile($seedPath);
if ($seedValidation !== null) {
    fwrite(STDERR, "\nERROR: Bundled seed SQL invalid: {$seedValidation}\n");
    exit(1);
}
echo "Validated database/seed/metropolcasino.sql\n";

file_put_contents($outputRoot . '/DEPLOY.txt', implode("\n", [
    'ADMIN HOST BUNDLE — upload ALL contents to bo-nexthub.site',
    '',
    'Domain:  https://bo-nexthub.site/',
    'API:     https://bo-nexthub.site/api/v2/',
    'Target:  /www/wwwroot/bo-nexthub.site/',
    '',
    'This folder is SELF-CONTAINED. Do not upload frontend views/pages here.',
    'Do not upload vegasroyalspin.com frontend files to this backend host.',
    '',
    'vendor/ is PRE-BUNDLED in this zip — do NOT run composer on the server.',
    '',
    'After upload (FRESH install — delete old .env and storage/install.lock if present):',
    '  php scripts/post-upload-check.php',
    '  Open https://bo-nexthub.site/install-status.php then /install',
    'Must exist on server (site root):',
    '  services/MegaPayzService.php',
    '  services/DrakonService.php',
    '  services/BgamingService.php',
    '  services/MemberJwtService.php',
    '  config/app.php',
    '  app/Config/admin.php',
    '  api/v2/index.php',
    '  database/migrations/',
    '',
    'If only services/ is missing, run locally:',
    '  php scripts/package-admin-services.php',
    '  → upload dist/admin-services/* to .../services/',
    '',
]) . "\n");

echo "\nReady: {$outputRoot}\n";
echo "Upload the entire folder contents to /www/wwwroot/bo-nexthub.site/\n";

/**
 * @param list<string> $relativePaths
 * @return list<string>
 */
function removeExcludedPaths(string $outputRoot, array $relativePaths): array
{
    $removed = [];
    foreach ($relativePaths as $relative) {
        $relative = str_replace('\\', '/', ltrim($relative, '/'));
        $target = $outputRoot . '/' . $relative;
        if (!file_exists($target)) {
            continue;
        }
        if (is_dir($target)) {
            removeTree($target);
        } else {
            unlink($target);
        }
        $removed[] = $relative;
    }

    return $removed;
}

function copyTree(string $source, string $destination): void
{
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }

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
            continue;
        }
        copy($item->getPathname(), $target);
    }
}

function removeTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
            continue;
        }
        unlink($item->getPathname());
    }

    rmdir($path);
}
