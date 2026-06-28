<?php

declare(strict_types=1);

/**
 * Admin/backend host için eksiksiz services/ paketi.
 * Sadece services/ eksikse FTP ile yüklemek için: dist/admin-services/*
 * Hedef sunucu: /www/wwwroot/bo-nexthub.site/services/
 *
 * Usage: php scripts/package-admin-services.php [output-dir]
 */

$projectRoot = dirname(__DIR__);
$outputRoot = isset($argv[1]) && trim((string) $argv[1]) !== ''
    ? rtrim(str_replace('\\', '/', (string) $argv[1]), '/')
    : $projectRoot . '/dist/admin-services';

/** Zorunlu — admin bootstrap ve api/v2 bunları require eder */
$requiredFiles = [
    'MegaPayzService.php',
    'DrakonService.php',
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
    'MegaPayzService.php' => 'Ödeme entegrasyonu (MegaPayz yatırım/çekim, callback)',
    'DrakonService.php' => 'Oyun sağlayıcı (Drakon launch, webhook, katalog)',
    'BgamingService.php' => 'Oyun sağlayıcı (BGaming wallet, launch, katalog)',
    'MemberJwtService.php' => 'Üye JWT oturum tokenları',
    'BackendApiClient.php' => 'HTTP API istemcisi',
    'MemberLoginService.php' => 'Login/register API köprüsü',
    'MemberRegisterService.php' => 'Üye kayıt API köprüsü',
    'MemberRegisterPayload.php' => 'Kayıt payload yardımcısı',
    'MemberViewDataService.php' => 'Bakiye/profil (admin host)',
    'ProfileApiHelper.php' => 'Profil API köprüsü',
    'SlotGamesQuery.php' => 'Oyun katalog sorgusu',
    'ProviderDisplayBadgeMap.php' => 'Sağlayıcı rozet haritası',
    'BalanceService.php' => 'Bakiye API köprüsü',
    'PaymentCallbackService.php' => 'Ödeme callback iş mantığı',
    'PublicApiV2Dispatcher.php' => 'Public API yönlendirici',
    'BackendMemberApiProxy.php' => 'Frontend→backend API proxy',
    'TurkishNationalId.php' => 'TC kimlik doğrulama',
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
    $manifestLines[] = '  ' . $file . ($desc !== '' ? ' — ' . $desc : '');
}
$manifestLines[] = '';
$manifestLines[] = 'Verify on server:';
$manifestLines[] = '  ls /www/wwwroot/bo-nexthub.site/services/MegaPayzService.php';
file_put_contents($outputRoot . '/MANIFEST.txt', implode("\n", $manifestLines) . "\n");

file_put_contents($outputRoot . '/UPLOAD.txt', implode("\n", [
    'YUKLEME — bo-nexthub.site services/ klasörü',
    '',
    '1. Bu klasördeki TÜM .php dosyalarını seçin',
    '2. FTP / aaPanel → /www/wwwroot/bo-nexthub.site/services/ içine yükleyin',
    '3. Mevcut boş services/ varsa üzerine yazın',
    '4. Kontrol:',
    '     ls /www/wwwroot/bo-nexthub.site/services/MegaPayzService.php',
    '',
    'Tam admin kurulumu için tercihen:',
    '  php scripts/package-admin-server.php',
    '  → dist/admin-host/* tüm site köküne yüklenir',
    '',
]) . "\n");

echo "Copied " . count($copied) . " files to {$outputRoot}\n";
foreach ($copied as $file) {
    echo "  ✓ {$file}\n";
}
echo "\nUpload dist/admin-services/* → /www/wwwroot/bo-nexthub.site/services/\n";

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
