<?php

declare(strict_types=1);

/**
 * Write role-specific composer.json into a deploy bundle (no admin/ classmap on frontend).
 *
 * Usage:
 *   php scripts/write-bundle-composer-json.php frontend /path/to/bundle
 *   php scripts/write-bundle-composer-json.php backend /path/to/bundle
 */

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/write-bundle-composer-json.php <frontend|backend> <bundle-root>\n");
    exit(1);
}

$role = strtolower(trim((string) $argv[1]));
$bundleRoot = rtrim(str_replace('\\', '/', (string) $argv[2]), '/');
$projectRoot = dirname(__DIR__);

if (!is_dir($bundleRoot)) {
    fwrite(STDERR, "ERROR: bundle root not found: {$bundleRoot}\n");
    exit(1);
}

$written = match ($role) {
    'frontend' => writeFrontendComposerJson($projectRoot, $bundleRoot),
    'backend', 'admin' => writeBackendComposerJson($projectRoot, $bundleRoot),
    default => false,
};

if (!$written) {
    fwrite(STDERR, "ERROR: unknown role or missing template: {$role}\n");
    exit(1);
}

echo "Wrote composer.json for {$role} → {$bundleRoot}/composer.json\n";
exit(0);

function writeFrontendComposerJson(string $projectRoot, string $bundleRoot): bool
{
    $template = $projectRoot . '/deploy/composer.frontend.json';
    if (!is_readable($template)) {
        return false;
    }

    copy($template, $bundleRoot . '/composer.json');
    copyComposerLock($projectRoot, $bundleRoot);

    return is_file($bundleRoot . '/composer.json');
}

function writeBackendComposerJson(string $projectRoot, string $bundleRoot): bool
{
    $adminComposer = $projectRoot . '/admin/composer.json';
    if (is_readable($adminComposer)) {
        copy($adminComposer, $bundleRoot . '/composer.json');
    } else {
        $json = [
            'name' => 'metropol/admin-backend',
            'description' => 'Metropol Casino admin/backend (standalone deploy)',
            'type' => 'project',
            'require' => [
                'php' => '>=8.0',
                'guzzlehttp/guzzle' => '^7.0',
            ],
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'app/',
                    'Services\\' => 'services/',
                    'Controllers\\' => 'controllers/',
                ],
                'classmap' => ['api/'],
            ],
        ];
        file_put_contents(
            $bundleRoot . '/composer.json',
            json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    copyComposerLock($projectRoot, $bundleRoot);

    return is_file($bundleRoot . '/composer.json');
}

function copyComposerLock(string $projectRoot, string $bundleRoot): void
{
    foreach ([$projectRoot . '/composer.lock', $projectRoot . '/admin/composer.lock'] as $lock) {
        if (is_readable($lock)) {
            copy($lock, $bundleRoot . '/composer.lock');
            return;
        }
    }
}
