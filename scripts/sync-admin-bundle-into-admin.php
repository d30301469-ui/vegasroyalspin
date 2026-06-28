<?php

declare(strict_types=1);

/**
 * Proje kökündeki backend dosyalarını admin/ altına kopyalar (self-contained admin deploy).
 *
 * Usage: php scripts/sync-admin-bundle-into-admin.php
 */

$projectRoot = dirname(__DIR__);
$adminRoot = $projectRoot . '/admin';

$copyDirs = [
    'services' => 'Provider + API servisleri (MegaPayz, Drakon, BGaming, …)',
    'config' => 'Uygulama yapılandırması',
    'database' => 'Migration ve şema',
    'controllers' => 'API controller köprüleri',
    'repositories' => 'Repository katmanı',
    'core' => 'Bootstrap yardımcıları',
    'storage' => 'Log / cache',
];

foreach ($copyDirs as $dir => $label) {
    $source = $projectRoot . '/' . $dir;
    $target = $adminRoot . '/' . $dir;
    if (!is_dir($source)) {
        fwrite(STDERR, "Skip missing: {$dir}/\n");
        continue;
    }
    if (!is_dir($target)) {
        mkdir($target, 0755, true);
    }
    if ($dir === 'database') {
        syncDatabaseMigrationsOnly($source, $target);
        echo "Synced {$dir}/migrations → admin/{$dir}/migrations ({$label}, seed korunur)\n";
        continue;
    }
    copyTree($source, $target);
    echo "Synced {$dir}/ → admin/{$dir}/ ({$label})\n";
}

$adminSeed = $adminRoot . '/database/seed/' . 'metropolcasino.sql';
$projectSeed = $projectRoot . '/database/seed/' . 'metropolcasino.sql';
if (is_file($adminSeed)) {
    $projectSeedDir = dirname($projectSeed);
    if (!is_dir($projectSeedDir)) {
        mkdir($projectSeedDir, 0755, true);
    }
    copy($adminSeed, $projectSeed);
    echo "Synced admin/database/seed/metropolcasino.sql → database/seed/ (kaynak: admin)\n";
}

// Admin host: database.php her zaman aktif (frontend guard yok)
$adminDatabasePhp = <<<'PHP'
<?php

declare(strict_types=1);

/**
 * Admin/backend host database config — always active (standalone admin deploy).
 */
return static function (): array {
    $env = static function (array $keys, string $default = ''): string {
        foreach ($keys as $key) {
            $value = getenv($key);
            if ($value !== false && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return $default;
    };

    $isProduction = in_array(strtolower($env(['APP_ENV'], 'development')), ['production', 'prod'], true);
    $config = [
        'host' => $env(['ADMIN_DB_HOST', 'DB_HOST', 'DATABASE_HOST'], '127.0.0.1'),
        'port' => (int) $env(['ADMIN_DB_PORT', 'DB_PORT', 'DATABASE_PORT'], '3306'),
        'database' => $env(['ADMIN_DB_DATABASE', 'DB_DATABASE', 'DATABASE_NAME', 'DATABASE_DATABASE'], 'metropol_db'),
        'username' => $env(['ADMIN_DB_USERNAME', 'DB_USERNAME', 'DATABASE_USERNAME'], 'root'),
        'password' => $env(['ADMIN_DB_PASSWORD', 'DB_PASSWORD', 'DATABASE_PASSWORD'], ''),
        'charset' => $env(['ADMIN_DB_CHARSET', 'DB_CHARSET', 'DATABASE_CHARSET'], 'utf8mb4'),
    ];

    if ($isProduction) {
        foreach (['host', 'database', 'username', 'password'] as $requiredKey) {
            if (trim((string) $config[$requiredKey]) === '') {
                throw new RuntimeException(sprintf('Production admin database config requires a non-empty "%s" value.', $requiredKey));
            }
        }

        if (strtolower((string) $config['username']) === 'root') {
            throw new RuntimeException('Production admin database config must not use the root database user.');
        }
    }

    return [
        'host' => $config['host'],
        'port' => $config['port'],
        'database' => $config['database'],
        'username' => $config['username'],
        'password' => $config['password'],
        'charset' => $config['charset'],
        'disabled' => false,
    ];
};

PHP;
file_put_contents($adminRoot . '/config/database.php', $adminDatabasePhp);
echo "Wrote admin/config/database.php (standalone admin DB)\n";

// Proje api/ → admin/api/ (admin/api/v2 korunur)
$apiSource = $projectRoot . '/api';
$apiTarget = $adminRoot . '/api';
if (is_dir($apiSource)) {
    copyTreeMerge($apiSource, $apiTarget);
    echo "Merged api/ → admin/api/ (bootstrap, SiteSettings, …)\n";
}

// Framework app/ dosyaları (panel app/ üzerine yazmadan)
$frameworkFiles = [
    'app/bootstrap.php' => 'app/framework_bootstrap.php',
    'app/Core/Database.php' => 'app/Core/Database.php',
    'app/Core/Config.php' => 'app/Core/Config.php',
    'app/Core/ErrorHandler.php' => 'app/Core/ErrorHandler.php',
    'app/Core/Response.php' => 'app/Core/Response.php',
    'app/Core/Request.php' => 'app/Core/Request.php',
    'app/Core/Router.php' => 'app/Core/Router.php',
    'app/Core/Controller.php' => 'app/Core/Controller.php',
    'app/Config/app.php' => 'app/Config/app.php',
    'app/Config/db.php' => 'app/Config/db.php',
];

foreach ($frameworkFiles as $relSource => $relTarget) {
    $source = $projectRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relSource);
    $target = $adminRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relTarget);
    if (!is_readable($source)) {
        fwrite(STDERR, "Skip missing framework file: {$relSource}\n");
        continue;
    }
    $targetDir = dirname($target);
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    copy($source, $target);
    echo "Copied {$relSource} → admin/{$relTarget}\n";
}

// Composer (standalone admin root)
$composerSource = $projectRoot . '/composer.json';
if (is_readable($composerSource)) {
    $composer = json_decode((string) file_get_contents($composerSource), true);
    if (is_array($composer)) {
        unset($composer['autoload']['classmap']);
        $composer['name'] = 'metropol/admin-backend';
        $composer['description'] = 'Metropol Casino admin/backend (standalone deploy)';
        file_put_contents(
            $adminRoot . '/composer.json',
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
        echo "Wrote admin/composer.json\n";
    }
}

if (is_readable($projectRoot . '/composer.lock')) {
    copy($projectRoot . '/composer.lock', $adminRoot . '/composer.lock');
    echo "Copied composer.lock → admin/\n";
}

// .htaccess — standalone admin host (never copy frontend root .htaccess)
$htaccessSource = $projectRoot . '/deploy/apache/bo-nexthub.site.htaccess';
if (!is_readable($htaccessSource)) {
    $htaccessSource = $adminRoot . '/.htaccess';
}
if (is_readable($htaccessSource)) {
    copy($htaccessSource, $adminRoot . '/.htaccess');
    echo "Copied standalone .htaccess → admin/\n";
}

// ENV template (admin içinde)
$envExample = $adminRoot . '/.env.example';
if (!is_readable($envExample) && is_readable($projectRoot . '/deploy/env/backend.env.example')) {
    copy($projectRoot . '/deploy/env/backend.env.example', $envExample);
    echo "Copied deploy/env/backend.env.example → admin/.env.example\n";
}

$required = [
    'admin/services/MegaPayzService.php',
    'admin/services/DrakonService.php',
    'admin/services/BgamingService.php',
    'admin/services/MemberJwtService.php',
    'admin/config/app.php',
    'admin/config/database.php',
    'admin/api/bootstrap.php',
    'admin/api/v2/index.php',
    'admin/database',
    'admin/app/framework_bootstrap.php',
    'admin/app/Core/Database.php',
    'admin/.env.example',
    'admin/composer.json',
];

$missing = [];
foreach ($required as $rel) {
    $path = $projectRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!file_exists($path)) {
        $missing[] = $rel;
    }
}

if ($missing !== []) {
    fwrite(STDERR, "ERROR: Sync incomplete:\n- " . implode("\n- ", $missing) . "\n");
    exit(1);
}

$structure = [
    'index.php',
    '.htaccess',
    '.env.example',
    'composer.json',
    'app/              — admin panel + framework (Database, Config)',
    'api/              — bootstrap + v2 member API + callbacks',
    'services/         — MegaPayz, Drakon, BGaming, MemberJwt, …',
    'config/           — app.php, database.php, env.php',
    'database/         — migrations',
    'controllers/',
    'repositories/',
    'core/',
    'storage/',
];

file_put_contents($adminRoot . '/DEPLOY-ADMIN-FOLDER.txt', implode("\n", [
    'ADMIN KLASÖRÜ — bağımsız backend (bo-nexthub.site)',
    '',
    'Bu admin/ klasörünün TAMAMINI sunucu köküne yükleyin:',
    '  Hedef: /www/wwwroot/bo-nexthub.site/',
    '',
    'Dizin yapısı:',
    ...array_map(static fn (string $line): string => '  ' . $line, $structure),
    '',
    'Kurulum:',
    '  cd /www/wwwroot/bo-nexthub.site',
    '  composer install --no-dev',
    '  cp .env.example .env',
    '  # .env içinde DB_* ve MEMBER_JWT_SECRET doldurun',
    '',
    'Doğrulama:',
    '  ls services/MegaPayzService.php',
    '  ls api/bootstrap.php',
    '  ls config/database.php',
    '',
    'Yerelde yenilemek:',
    '  php scripts/sync-admin-bundle-into-admin.php',
    '',
]) . "\n");

echo "\nReady: {$adminRoot}/\n";
echo "Upload entire admin/ folder contents to bo-nexthub.site\n";

$verifyScript = $projectRoot . '/scripts/verify-services-sync.php';
if (is_file($verifyScript)) {
    $phpBinary = (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') ? PHP_BINARY : 'php';
    passthru($phpBinary . ' ' . escapeshellarg($verifyScript), $verifyCode);
    if ($verifyCode !== 0) {
        exit($verifyCode);
    }
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
            continue;
        }
        copy($item->getPathname(), $target);
    }
}

/**
 * Project database/ → admin/database/ but never overwrite install seed SQL.
 */
function syncDatabaseMigrationsOnly(string $source, string $destination): void
{
    $migrationSource = $source . '/migrations';
    $migrationTarget = $destination . '/migrations';
    if (!is_dir($migrationSource)) {
        return;
    }
    if (is_dir($migrationTarget)) {
        removeTree($migrationTarget);
    }
    copyTree($migrationSource, $migrationTarget);
}

function copyTreeMerge(string $source, string $destination): void
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
        $targetDir = dirname($target);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
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
