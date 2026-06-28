<?php

declare(strict_types=1);

/**
 * Frontend-only bundle (public site, API proxy to backend).
 *
 * Usage:
 *   php scripts/package-frontend-server.php [output-dir] [domain]
 *   php scripts/package-frontend-server.php dist/frontend-host vegasroyalspin.com
 */

$projectRoot = dirname(__DIR__);
$outputRoot = isset($argv[1]) && trim((string) $argv[1]) !== '' && !str_starts_with((string) $argv[1], '--')
    ? rtrim(str_replace('\\', '/', (string) $argv[1]), '/')
    : $projectRoot . '/dist/frontend-host';

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
    passthru($phpBinary . ' ' . escapeshellarg($verifyApiSync), $verifyCode);
    if ($verifyCode !== 0) {
        exit($verifyCode);
    }
}
$layerTest = $projectRoot . '/scripts/test-all-layers.php';
if (is_file($layerTest)) {
    passthru($phpBinary . ' ' . escapeshellarg($layerTest), $layerCode);
    if ($layerCode !== 0) {
        exit($layerCode);
    }
}

$frontendDomain = 'vegasroyalspin.com';
foreach (array_slice($argv, 1) as $arg) {
    $arg = trim((string) $arg);
    if ($arg === '') {
        continue;
    }
    if (str_starts_with($arg, '--domain=')) {
        $frontendDomain = trim(substr($arg, 9));
        continue;
    }
    if (!str_starts_with($arg, '--') && !str_contains($arg, '/') && !str_contains($arg, '\\') && $arg !== $outputRoot) {
        $frontendDomain = $arg;
    }
}
$frontendDomain = strtolower(preg_replace('#^https?://#i', '', rtrim($frontendDomain, '/')));
$frontendDomain = preg_replace('#^www\.#i', '', $frontendDomain) ?: 'vegasroyalspin.com';
$frontendUrl = 'https://' . $frontendDomain;
$frontendHosts = implode(',', array_unique([$frontendDomain, 'www.' . $frontendDomain, 'm.' . $frontendDomain]));

$copyDirs = [
    'assets',
    'config',
    'controllers',
    'core',
    'includes',
    'mobile',
    'pages',
    'partials',
    'public',
    'services',
    'views',
    'api',
    'app',
    'repositories',
    'storage',
];

$copyFiles = [
    'index.php',
    'install.php',
    'install-status.php',
    'install-complete.html',
    'install-probe.php',
    'health.php',
    'ping.php',
    'diagnose.php',
    'router.php',
];

$forbiddenPaths = [
    'admin',
    'database',
    'admin-host',
];

$forbiddenRelativeFiles = [
    'services/MegaPayzService.php',
    'services/DrakonService.php',
    'services/BgamingService.php',
    'services/MemberJwtService.php',
    'services/PaymentCallbackService.php',
];

if (is_dir($outputRoot)) {
    echo "Removing existing output: {$outputRoot}\n";
    removeTree($outputRoot);
}

mkdir($outputRoot, 0755, true);

foreach ($copyDirs as $dir) {
    $source = $projectRoot . '/' . $dir;
    if (!is_dir($source)) {
        fwrite(STDERR, "Skip missing directory: {$dir}\n");
        continue;
    }
    copyTree($source, $outputRoot . '/' . $dir);
    echo "Copied {$dir}/\n";
}

foreach ($copyFiles as $file) {
    $source = $projectRoot . '/' . $file;
    if (!is_file($source)) {
        continue;
    }
    copy($source, $outputRoot . '/' . $file);
    echo "Copied {$file}\n";
}

$htaccessSource = $projectRoot . '/deploy/apache/vegasroyalspin.com.htaccess';
if (is_file($htaccessSource)) {
    copy($htaccessSource, $outputRoot . '/.htaccess');
    echo "Copied deploy/apache/vegasroyalspin.com.htaccess → .htaccess\n";
} elseif (is_file($projectRoot . '/.htaccess')) {
    copy($projectRoot . '/.htaccess', $outputRoot . '/.htaccess');
    echo "Copied .htaccess (fallback)\n";
}

$frontendApiHtaccess = $projectRoot . '/deploy/apache/frontend-api.htaccess';
if (is_file($frontendApiHtaccess) && is_dir($outputRoot . '/api')) {
    copy($frontendApiHtaccess, $outputRoot . '/api/.htaccess');
    echo "Copied deploy/apache/frontend-api.htaccess → api/.htaccess\n";
}

$fallbackIndex = $projectRoot . '/deploy/apache/fallback-index.html';
if (is_file($fallbackIndex)) {
    copy($fallbackIndex, $outputRoot . '/index.html');
    echo "Copied deploy/apache/fallback-index.html → index.html (index.php eksikse yedek)\n";
}

$frontendComposerTemplate = $projectRoot . '/deploy/composer.frontend.json';
if (is_file($frontendComposerTemplate)) {
    $deployDir = $outputRoot . '/deploy';
    if (!is_dir($deployDir)) {
        mkdir($deployDir, 0755, true);
    }
    copy($frontendComposerTemplate, $deployDir . '/composer.frontend.json');
    echo "Copied deploy/composer.frontend.json (sunucuda composer dГјzeltmesi iГ§in)\n";
}

$aapanelDeploy = $projectRoot . '/deploy/aapanel';
if (is_dir($aapanelDeploy)) {
    copyTree($aapanelDeploy, $outputRoot . '/deploy/aapanel');
    echo "Copied deploy/aapanel/ (aaPanel kurulum notlarД±)\n";
}

$apacheDeploy = $projectRoot . '/deploy/apache';
if (is_dir($apacheDeploy)) {
    copyTree($apacheDeploy, $outputRoot . '/deploy/apache');
    echo "Copied deploy/apache/ (Apache .htaccess + vhost Г¶rnekleri)\n";
}

$opsScripts = [
    'live-probe-checklist.php',
    'diagnose-web-stack.php',
    'bootstrap-frontend-env.php',
    'reset-for-install.php',
    'post-upload-check.php',
    'test-all-layers.php',
];
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

$binInstaller = $projectRoot . '/bin/install-frontend.php';
if (is_file($binInstaller)) {
    $binDir = $outputRoot . '/bin';
    if (!is_dir($binDir)) {
        mkdir($binDir, 0755, true);
    }
    copy($binInstaller, $binDir . '/install-frontend.php');
    echo "Copied bin/install-frontend.php\n";
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
    passthru($phpBinary . ' ' . escapeshellarg($writeComposerScript) . ' frontend ' . escapeshellarg($outputRoot), $composerJsonCode);
    if ($composerJsonCode !== 0) {
        fwrite(STDERR, "ERROR: could not write frontend composer.json\n");
        exit(1);
    }
}

$excludeRelativePaths = [
    'services/MegaPayzService.php',
    'services/DrakonService.php',
    'services/BgamingService.php',
    'services/MemberJwtService.php',
    'services/PaymentCallbackService.php',
    'controllers/Api/ApiCallbackController.php',
    'controllers/Api/ApiCasinoCallbackController.php',
    'controllers/Api/ApiBgamingWalletController.php',
    'controllers/Api/ApiDrakonController.php',
    'app/Services/Providers/DrakonService.php',
    'app/Services/Providers/BgamingService.php',
    'app/Services/Payments/MegaPayzService.php',
    'app/Http/Controllers/Callback/MegaPayzCallbackController.php',
    'app/Http/Controllers/Callback/DrakonCallbackController.php',
    'app/Http/Controllers/Callback/BgamingCallbackController.php',
    'app/Http/Controllers/Callback/CasinoCallbackController.php',
    'app/Http/Middleware/ProviderSignatureMiddleware.php',
];

$removed = removeExcludedPaths($outputRoot, $excludeRelativePaths);
if ($removed !== []) {
    echo "Excluded backend-only files:\n- " . implode("\n- ", $removed) . "\n";
}

foreach ($forbiddenPaths as $forbidden) {
    if (is_dir($outputRoot . '/' . $forbidden) || is_file($outputRoot . '/' . $forbidden)) {
        fwrite(STDERR, "ERROR: Forbidden path in frontend bundle: {$forbidden}\n");
        exit(1);
    }
}

$bundleVendorScript = $projectRoot . '/scripts/bundle-vendor-for-deploy.php';
if (is_file($bundleVendorScript)) {
    passthru(
        $phpBinary . ' ' . escapeshellarg($bundleVendorScript) . ' --frontend ' . escapeshellarg($outputRoot),
        $vendorCode
    );
    if ($vendorCode !== 0 || !is_file($outputRoot . '/vendor/autoload.php')) {
        fwrite(STDERR, "ERROR: vendor/ bundle failed вЂ” deploy zip must include vendor/autoload.php\n");
        exit(1);
    }
}

$requiredFiles = [
    'index.php',
    'install.php',
    'install-status.php',
    'install-complete.html',
    'ping.php',
    'health.php',
    'app/Core/FrontendInstallGate.php',
    'app/Services/FrontendInstaller.php',
    'app/Controllers/FrontendInstallController.php',
    'app/Views/install/wizard.php',
    'app/Views/install/complete.php',
    'config/cloudflare.php',
    'config/app.php',
    'config/bootstrap_api.php',
    'config/env.php',
    'services/BackendApiClient.php',
    'services/BackendMemberApiProxy.php',
    'services/MemberJwtVerify.php',
    'services/PublicApiV2Dispatcher.php',
    'services/frontend_api_dispatch.php',
    'services/register_ajax_check.php',
    'app/Services/Api/PublicMemberApiDispatcher.php',
    'controllers/Api/ApiMemberV2BridgeController.php',
    'core/bootstrap.php',
    'views',
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
    fwrite(STDERR, "\nERROR: Frontend bundle incomplete:\n- " . implode("\n- ", $missing) . "\n");
    exit(1);
}

$forbiddenPresent = [];
foreach ($forbiddenRelativeFiles as $relative) {
    if (is_file($outputRoot . '/' . $relative)) {
        $forbiddenPresent[] = $relative;
    }
}
if ($forbiddenPresent !== []) {
    fwrite(STDERR, "\nERROR: Backend-only files must not be in frontend bundle:\n- " . implode("\n- ", $forbiddenPresent) . "\n");
    exit(1);
}

file_put_contents($outputRoot . '/DEPLOY.txt', implode("\n", [
    'FRONTEND HOST BUNDLE вЂ” upload ALL contents to ' . $frontendDomain,
    '',
    'Domain:  ' . $frontendUrl . '/',
    'Target:  /www/wwwroot/' . $frontendDomain . '/',
    '',
    'This folder is SELF-CONTAINED for the public site.',
    'NO admin/, NO database/, NO DB credentials.',
    '',
    'Talks to backend ONLY via:',
    '  https://bo-nexthub.site/api/v2/*',
    '',
    'Provider/payment integrations are NOT included in this bundle.',
    'They run only on the backend host (dist/admin-host).',
    '',
    'MySQL/PDO is DISABLED on this host (production + FRONTEND_API_ONLY).',
    'Never set DB_HOST / DB_DATABASE in frontend .env.',
    '',
    'vendor/ is PRE-BUNDLED in this zip вЂ” do NOT run composer on the server.',
    '',
    'After upload (FRESH install вЂ” delete old .env and storage/install.lock if present):',
    '  php scripts/post-upload-check.php',
    '  Open https://' . $frontendDomain . '/install-status.php then /install',
    '  Complete the wizard (backend URL, MEMBER_JWT_SECRET from bo-nexthub.site)',
    '  Or CLI: php bin/install-frontend.php --frontend-url=' . $frontendUrl . ' \\',
    '    --backend-url=https://bo-nexthub.site --member-jwt-secret=YOUR-SECRET',
    '',
    'Manual .env: cp ENV.example .env (only if not using /install wizard)',
    '',
    'Backend .env must include:',
    '  FRONTEND_URL=' . $frontendUrl,
    '  ALLOWED_URL_HOSTS=' . $frontendHosts . ',bo-nexthub.site',
    '',
]) . "\n");

$envExample = $projectRoot . '/deploy/env/frontend.vegasroyalspin.env.example';
if ($frontendDomain !== 'vegasroyalspin.com' || !is_readable($envExample)) {
    $envExample = $projectRoot . '/ENV.example';
}
if (is_readable($envExample)) {
    $envContent = (string) file_get_contents($envExample);
    if ($frontendDomain !== 'vegasroyalspin.com') {
        $envContent = str_replace('vegasroyalspin.com', $frontendDomain, $envContent);
        $envContent = str_replace('https://vegasroyalspin.com', $frontendUrl, $envContent);
    }
    file_put_contents($outputRoot . '/ENV.example', $envContent);
    echo "Wrote ENV.example for {$frontendDomain}\n";
}

echo "\nReady: {$outputRoot}\n";

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

