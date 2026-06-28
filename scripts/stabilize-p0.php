#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * P0 stabilite — sırayla yerel doğrulama + zip + sunucu komut listesi.
 *
 * Usage:
 *   php scripts/stabilize-p0.php
 *   php scripts/stabilize-p0.php --build
 *   php scripts/stabilize-p0.php --build --live
 */

$root = dirname(__DIR__);
$php = (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') ? PHP_BINARY : 'php';
$doBuild = in_array('--build', $argv, true);
$doLive = in_array('--live', $argv, true);

$fail = 0;
$step = 0;

$run = static function (string $title, callable $fn) use (&$fail, &$step): void {
    $step++;
    echo "\n=== Step {$step}: {$title} ===\n";
    try {
        $fn();
    } catch (Throwable $e) {
        $fail++;
        fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
    }
};

echo "Metropol P0 Stabilization\n";
echo "Root: {$root}\n";

$run('Kritik kod düzeltmeleri', static function () use ($root): void {
    $checks = [
        'admin/api/v2/includes/member_api_kernel.php' => 'memberApiUsesSessionCsrf',
        'admin/api/Sliders.php' => 'ensureCategoryColumnSupportsBgaming',
        'services/BackendMemberApiProxy.php' => 'game-launch',
        'assets/js/auth-shared.js' => "'/game-launch': true",
        'admin/database/migrations/2026_06_25_000001_widen_sliders_category.php' => "'bgaming'",
    ];
    foreach ($checks as $file => $needle) {
        $path = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $file);
        if (!is_readable($path)) {
            throw new RuntimeException("Missing file: {$file}");
        }
        $src = (string) file_get_contents($path);
        if (!str_contains($src, $needle)) {
            throw new RuntimeException("Fix marker not found in {$file}: {$needle}");
        }
        echo "OK   {$file}\n";
    }
});

$run('.env secret eşleşmesi (yerel)', static function () use ($root): void {
    $fe = $root . '/.env';
    $be = $root . '/admin/.env';
    if (!is_readable($fe) || !is_readable($be)) {
        echo "SKIP — .env dosyaları yok (sunucuda kontrol edin)\n";

        return;
    }
    $parse = static function (string $path): array {
        $out = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $out[trim($k)] = trim($v, " \t\"'");
        }

        return $out;
    };
    $feEnv = $parse($fe);
    $beEnv = $parse($be);
    foreach (['MEMBER_JWT_SECRET', 'FRONTEND_CMS_PURGE_SECRET'] as $key) {
        $a = $feEnv[$key] ?? '';
        $b = $beEnv[$key] ?? '';
        if ($a === '' || $b === '') {
            echo "WARN {$key} eksik\n";
            continue;
        }
        if ($a !== $b) {
            throw new RuntimeException("{$key} frontend ≠ backend");
        }
        if (str_contains(strtolower($a), 'change-me')) {
            throw new RuntimeException("{$key} hala CHANGE-ME");
        }
        echo "OK   {$key} eşleşiyor (" . strlen($a) . " chars)\n";
    }
});

$run('Katman testleri', static function () use ($root, $php, $doLive): void {
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($root . '/scripts/test-all-layers.php');
    if ($doLive) {
        $cmd .= ' --live';
    }
    passthru($cmd, $code);
    if ($code !== 0) {
        throw new RuntimeException('test-all-layers exit ' . $code);
    }
});

$run('Split-deploy verify', static function () use ($root, $php): void {
    passthru(escapeshellarg($php) . ' ' . escapeshellarg($root . '/scripts/verify-split-deploy.php'), $code);
    if ($code !== 0) {
        throw new RuntimeException('verify-split-deploy exit ' . $code);
    }
});

if ($doBuild) {
    $run('Zip build', static function () use ($root, $php): void {
        passthru(escapeshellarg($php) . ' ' . escapeshellarg($root . '/scripts/build-split-hosts.php'), $code);
        if ($code !== 0) {
            throw new RuntimeException('build-split-hosts exit ' . $code);
        }
        foreach (['bo-nexthub-admin.zip', 'vegasroyalspin-frontend.zip'] as $zip) {
            $path = $root . '/dist/' . $zip;
            if (!is_file($path)) {
                throw new RuntimeException("Missing {$zip}");
            }
            $mb = round(filesize($path) / 1024 / 1024, 2);
            echo "OK   dist/{$zip} ({$mb} MB)\n";
        }
    });
} else {
    echo "\n=== Step " . (++$step) . ": Zip build (atlandı) ===\n";
    echo "Çalıştırın: php scripts/stabilize-p0.php --build\n";
}

echo "\n=== SUNUCU SIRASI (P0) ===\n";
echo "BACKEND (bo-nexthub.site):\n";
echo "  1. dist/bo-nexthub-admin.zip yükle (.env KORU)\n";
echo "  2. php deploy/aapanel/fix-backend-env.php\n";
echo "  3. php deploy/aapanel/ensure-member-jwt-table.php\n";
echo "  4. php deploy/aapanel/ensure-sliders-category.php\n";
echo "  5. php deploy/aapanel/audit-all-apis.php --backend\n";
echo "\nFRONTEND (vegasroyalspin.com):\n";
echo "  6. dist/vegasroyalspin-frontend.zip yükle (.env KORU)\n";
echo "  7. php deploy/aapanel/fix-frontend-env.php\n";
echo "  8. php deploy/aapanel/audit-all-apis.php\n";
echo "  9. php deploy/aapanel/probe-login-chain.php\n";
echo " 10. php deploy/aapanel/test-member-jwt-trust.php [user_id]\n";
echo "\nTARAYICI:\n";
echo "  - Giriş → bakiye görünüyor mu\n";
echo "  - BGaming oyun launch\n";
echo "  - Admin → Slider → kategori BGaming kayıt\n";

exit($fail > 0 ? 1 : 0);
