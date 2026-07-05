<?php

declare(strict_types=1);

/**
 * Admin/backend host iÃ§in eksiksiz services/ paketi.
 * Sadece services/ eksikse FTP ile yÃ¼klemek iÃ§in: dist/admin-services/*
 * Hedef sunucu: /www/wwwroot/bo-nexthub.site/services/
 *
 * Usage: php scripts/package-admin-services.php [output-dir]
 */

$projectRoot = dirname(__DIR__);
$outputRoot = isset($argv[1]) && trim((string) $argv[1]) !== ''
    ? rtrim(str_replace('\\', '/', (string) $argv[1]), '/')
    : $projectRoot . '/dist/admin-services';

/** Zorunlu â€” admin bootstrap ve api/v2 bunlarÄ± require eder */
$requiredFiles = [
    'MegaPayzService.php',
    'BgamingService.php',
    'MemberJwtService.php',
    'BackendApiClient.php',
    'MemberLoginService.php',
    'MemberRegisterService.php',
    'MemberRegisterPayload.php',
    'MemberViewDataService.php',
    'ProfileApiHelper.php',
    'SlotGamesQuery.php',
    'ProviderDisplayBadgeMap.php',
    'BalanceService.php',
    'PaymentCallbackService.php',
    'PublicApiV2Dispatcher.php',
    'BackendMemberApiProxy.php',
    'TurkishNationalId.php',
];

$fileDescriptions = [
    'MegaPayzService.php' => 'Ã–deme entegrasyonu (MegaPayz yatÄ±rÄ±m/Ã§ekim, callback)',
    'BgamingService.php' => 'Oyun saÄŸlayÄ±cÄ± (BGaming wallet, launch, katalog)',
    'MemberJwtService.php' => 'Ãœye JWT oturum tokenlarÄ±',
    'BackendApiClient.php' => 'HTTP API istemcisi',
    'MemberLoginService.php' => 'Login/register API kÃ¶prÃ¼sÃ¼',
    'MemberRegisterService.php' => 'Ãœye kayÄ±t API kÃ¶prÃ¼sÃ¼',
    'MemberRegisterPayload.php' => 'KayÄ±t payload yardÄ±mcÄ±sÄ±',
    'MemberViewDataService.php' => 'Bakiye/profil (admin host)',
    'ProfileApiHelper.php' => 'Profil API kÃ¶prÃ¼sÃ¼',
    'SlotGamesQuery.php' => 'Oyun katalog sorgusu',
    'ProviderDisplayBadgeMap.php' => 'SaÄŸlayÄ±cÄ± rozet haritasÄ±',
    'BalanceService.php' => 'Bakiye API kÃ¶prÃ¼sÃ¼',
    'PaymentCallbackService.php' => 'Ã–deme callback iÅŸ mantÄ±ÄŸÄ±',
    'PublicApiV2Dispatcher.php' => 'Public API yÃ¶nlendirici',
    'BackendMemberApiProxy.php' => 'Frontendâ†’backend API proxy',
    'TurkishNationalId.php' => 'TC kimlik doÄŸrulama',
];

$sourceDir = $projectRoot . '/services';
if (!is_dir($sourceDir)) {
    fwrite(STDERR, "ERROR: Source services/ not found at {$sourceDir}\n");
    exit(1);
}

if (is_dir($outputRoot)) {
    removeTree($outputRoot);
}
mkdir($outputRoot, 0755, true);

$copied = [];
$missing = [];

foreach ($requiredFiles as $file) {
    $source = $sourceDir . '/' . $file;
    if (!is_file($source)) {
        $missing[] = $file;
        continue;
    }
    copy($source, $outputRoot . '/' . $file);
    $copied[] = $file;
}

if ($missing !== []) {
    fwrite(STDERR, "ERROR: Missing source files in repo services/:\n- " . implode("\n- ", $missing) . "\n");
    exit(1);
}

$manifestLines = [
    'ADMIN SERVICES BUNDLE',
    'Target: /www/wwwroot/bo-nexthub.site/services/',
    'Upload ALL files in this folder into the site services/ directory.',
    '',
    'Required files (' . count($copied) . '):',
];
foreach ($requiredFiles as $file) {
    $desc = $fileDescriptions[$file] ?? '';
    $manifestLines[] = '  ' . $file . ($desc !== '' ? ' â€” ' . $desc : '');
}
$manifestLines[] = '';
$manifestLines[] = 'Verify on server:';
$manifestLines[] = '  ls /www/wwwroot/bo-nexthub.site/services/MegaPayzService.php';
file_put_contents($outputRoot . '/MANIFEST.txt', implode("\n", $manifestLines) . "\n");

file_put_contents($outputRoot . '/UPLOAD.txt', implode("\n", [
    'YUKLEME â€” bo-nexthub.site services/ klasÃ¶rÃ¼',
    '',
    '1. Bu klasÃ¶rdeki TÃœM .php dosyalarÄ±nÄ± seÃ§in',
    '2. FTP / aaPanel â†’ /www/wwwroot/bo-nexthub.site/services/ iÃ§ine yÃ¼kleyin',
    '3. Mevcut boÅŸ services/ varsa Ã¼zerine yazÄ±n',
    '4. Kontrol:',
    '     ls /www/wwwroot/bo-nexthub.site/services/MegaPayzService.php',
    '',
    'Tam admin kurulumu iÃ§in tercihen:',
    '  php scripts/package-admin-server.php',
    '  â†’ dist/admin-host/* tÃ¼m site kÃ¶kÃ¼ne yÃ¼klenir',
    '',
]) . "\n");

echo "Copied " . count($copied) . " files to {$outputRoot}\n";
foreach ($copied as $file) {
    echo "  âœ“ {$file}\n";
}
echo "\nUpload dist/admin-services/* â†’ /www/wwwroot/bo-nexthub.site/services/\n";

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
