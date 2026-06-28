#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * P1 stabilite — provider callback + üye akış scriptleri.
 *
 * Usage:
 *   php scripts/stabilize-p1.php
 *   php scripts/stabilize-p1.php --live
 *   php scripts/stabilize-p1.php --live --login=USER --password=PASS
 */

$root = dirname(__DIR__);
$php = (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') ? PHP_BINARY : 'php';
$doLive = in_array('--live', $argv, true);
$login = '';
$password = '';

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--login=')) {
        $login = substr($arg, 8);
    }
    if (str_starts_with($arg, '--password=')) {
        $password = substr($arg, 11);
    }
}

$fail = 0;
$step = 0;

$run = static function (string $title, callable $fn) use (&$fail, &$step): void {
    $step++;
    echo "\n=== Step {$step}: {$title} ===\n";
    try {
        $fn();
    } catch (Throwable $e) {
        $fail++;
        fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    }
};

echo "Metropol P1 Stabilization (Oyun & Finans)\n";
echo "Root: {$root}\n";

$run('P1 script dosyaları', static function () use ($root): void {
    $files = [
        'deploy/aapanel/probe-p1-providers.php',
        'deploy/aapanel/probe-member-flow.php',
    ];
    foreach ($files as $file) {
        $path = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $file);
        if (!is_readable($path)) {
            throw new RuntimeException("Missing: {$file}");
        }
        echo "OK   {$file}\n";
    }
});

$run('PHP syntax', static function () use ($root, $php): void {
    foreach (['deploy/aapanel/probe-p1-providers.php', 'deploy/aapanel/probe-member-flow.php', 'scripts/stabilize-p1.php'] as $file) {
        $path = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $file);
        passthru(escapeshellarg($php) . ' -l ' . escapeshellarg($path), $code);
        if ($code !== 0) {
            throw new RuntimeException("Syntax error: {$file}");
        }
    }
});

if ($doLive) {
    $run('Provider callback probe (canlı)', static function () use ($root, $php): void {
        passthru(escapeshellarg($php) . ' ' . escapeshellarg($root . '/deploy/aapanel/probe-p1-providers.php'), $code);
        if ($code !== 0) {
            throw new RuntimeException('probe-p1-providers exit ' . $code);
        }
    });

    if ($login !== '' && $password !== '') {
        $run('Üye akış probe (canlı)', static function () use ($root, $php, $login, $password): void {
            $cmd = escapeshellarg($php) . ' ' . escapeshellarg($root . '/deploy/aapanel/probe-member-flow.php')
                . ' --login=' . escapeshellarg($login)
                . ' --password=' . escapeshellarg($password)
                . ' --skip-game';
            passthru($cmd, $code);
            if ($code !== 0) {
                throw new RuntimeException('probe-member-flow exit ' . $code);
            }
        });
    } else {
        echo "\n=== Step " . (++$step) . ": Üye akış probe (atlandı) ===\n";
        echo "Canlı login testi: php scripts/stabilize-p1.php --live --login=USER --password=PASS\n";
    }
} else {
    echo "\n=== Step " . (++$step) . ": Canlı probe (atlandı) ===\n";
    echo "Çalıştırın: php scripts/stabilize-p1.php --live\n";
}

echo "\n=== SUNUCU SIRASI (P1) ===\n";
echo "BACKEND (bo-nexthub.site):\n";
echo "  1. P0 zip deploy tamamlandıysa devam edin\n";
echo "  2. php deploy/aapanel/probe-p1-providers.php --backend\n";
echo "  3. Admin → BGaming/Drakon/MegaPayz ayarlarını doğrulayın\n";
echo "\nFRONTEND (vegasroyalspin.com):\n";
echo "  1. php deploy/aapanel/probe-p1-providers.php\n";
echo "  2. php deploy/aapanel/probe-member-flow.php --login USER --password PASS\n";
echo "  3. php deploy/aapanel/probe-member-flow.php --login USER --password PASS --game-id=bgaming:GAME\n";
echo "\nProvider panel URL özeti:\n";
echo "  BGaming wallet  → https://bo-nexthub.site/api/v2/bgaming-wallet\n";
echo "  Drakon webhook  → https://bo-nexthub.site/drakon_api\n";
echo "  MegaPayz callback → https://bo-nexthub.site/api/v2/megapayz-callback\n";

exit($fail > 0 ? 1 : 0);
