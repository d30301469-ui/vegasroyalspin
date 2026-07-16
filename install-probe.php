<?php

declare(strict_types=1);

/**
 * /install öncesi PHP-FPM teşhisi — proxy_fcgi reset ayıklama.
 * Tarayıcı: https://vegasroyalspin.com/install-probe.php
 */
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');

$root = __DIR__;
$steps = [];

$run = static function (string $label, callable $fn) use (&$steps): void {
    try {
        $fn();
        $steps[] = '[OK] ' . $label;
    } catch (Throwable $e) {
        $steps[] = '[FAIL] ' . $label . ' — ' . $e->getMessage();
    }
};

$run('PHP ' . PHP_VERSION, static fn (): bool => version_compare(PHP_VERSION, '8.0.0', '>='));
$run('extension json', static fn (): bool => extension_loaded('json'));
$run('extension mbstring', static fn (): bool => extension_loaded('mbstring'));
$run('extension openssl', static fn (): bool => extension_loaded('openssl'));
$run('extension curl', static fn (): bool => extension_loaded('curl'));
$run('storage writable', static function () use ($root): bool {
    if (!is_dir($root . '/storage')) {
        mkdir($root . '/storage', 0755, true);
    }

    return is_writable($root . '/storage');
});
$run('vendor/autoload.php', static fn (): bool => is_file($root . '/vendor/autoload.php'));
$run('config/env.php', static fn (): bool => is_readable($root . '/config/env.php'));
$run('FrontendInstallGate.php', static fn (): bool => is_readable($root . '/app/Core/FrontendInstallGate.php'));
$run('session_start', static function (): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return session_status() === PHP_SESSION_ACTIVE;
});
$run('require FrontendInstallGate', static function () use ($root): void {
    require_once $root . '/app/Core/FrontendInstallGate.php';
});
$run('csrf token write', static function () use ($root): bool {
    require_once $root . '/app/Core/FrontendInstallGate.php';
    $token = FrontendInstallGate::csrfToken($root);

    return strlen($token) >= 32;
});

echo "install-probe " . gmdate('c') . "\n";
echo "root: {$root}\n\n";
echo implode("\n", $steps) . "\n\n";

$failed = array_values(array_filter($steps, static fn (string $s): bool => str_starts_with($s, '[FAIL]')));
if ($failed !== []) {
    echo "Sonuç: FAIL — yukarıdaki adımları düzeltin.\n";
    http_response_code(500);
} else {
    echo "Sonuç: OK — PHP-FPM /install dosyalarını yükleyebiliyor.\n";
    echo "Sırada: https://vegasroyalspin.com/install\n";
}
