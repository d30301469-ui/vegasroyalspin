#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Drakon oyun kataloğunu yeniden senkronize et (backend SSH / deploy sonrası).
 *
 * Neden: Drakon zaman zaman oyunları yeniden numaralandırıyor/emekliye ayırıyor
 * (Pragmatic Play için doğrulandı: eski "23xxx"/"24xxx" game_id'leri emekliye
 * ayrıldı, yerlerine 51096 gibi yeni çalışan id'ler geldi). `syncGames()` yalnızca
 * ekleme/güncelleme yaptığı için emekli id'ler kataloğumuzda kalıyor ve oyun
 * başlatmada Drakon'un "/game-error" sayfasına düşüp kırık görünüyordu.
 *
 * Bu script `DrakonService::syncGames()` çağırır — güncel katalog çekilir ve bu
 * sync tarafından yenilenmeyen (emekli) satırlar `is_active = 0` yapılarak
 * listeden düşürülür (geri alınabilir; yalnızca sağlıklı, kesilmemiş bir katalog
 * döndüğünde çalışır).
 *
 * Usage: php deploy/aapanel/resync-drakon-games.php [/path/to/admin.vegasroyalspin.com]
 */

$root = dirname(__DIR__, 2);
foreach (array_slice($argv, 1) as $arg) {
    if (trim($arg) !== '' && !str_starts_with($arg, '-')) {
        $root = rtrim(str_replace('\\', '/', $arg), '/');
    }
}

$bootstrapCandidates = [
    $root . '/app/Core/AdminPaths.php',
    $root . '/admin/app/Core/AdminPaths.php',
];
$bootstrapped = false;
foreach ($bootstrapCandidates as $candidate) {
    if (!is_readable($candidate)) {
        continue;
    }
    require_once $candidate;
    admin_paths_bootstrap();
    $bootstrapped = true;
    break;
}

if (!$bootstrapped) {
    fwrite(STDERR, "AdminPaths bootstrap not found under {$root}\n");
    exit(1);
}

// Resolve AdminDatabase / DrakonService across the possible monorepo layouts
// (backend-only deploy where these live at <root>/app|services, or the full
// monorepo where the admin code lives under <root>/admin/...). admin_project_path()
// can resolve to either the repo root or the admin subdir depending on install
// markers, so probe a candidate list instead of assuming a single path.
$requireFirstReadable = static function (string $class, array $candidates): void {
    if (class_exists($class, false)) {
        return;
    }
    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_readable($candidate)) {
            require_once $candidate;
            if (class_exists($class, false)) {
                return;
            }
        }
    }
    fwrite(STDERR, "Could not locate {$class} (tried: " . implode(', ', $candidates) . ")\n");
    exit(1);
};

$requireFirstReadable('AdminDatabase', [
    admin_project_path('app/Core/AdminDatabase.php'),
    admin_project_path('admin/app/Core/AdminDatabase.php'),
    $root . '/app/Core/AdminDatabase.php',
    $root . '/admin/app/Core/AdminDatabase.php',
]);
$requireFirstReadable('DrakonService', [
    admin_project_path('services/DrakonService.php'),
    admin_project_path('admin/services/DrakonService.php'),
    $root . '/services/DrakonService.php',
    $root . '/admin/services/DrakonService.php',
]);

try {
    $pdo = AdminDatabase::pdo();

    $exists = (int) $pdo->query("SHOW TABLES LIKE 'drakon_config'")->rowCount();
    if ($exists === 0) {
        echo "drakon_config table does not exist yet — skipping Drakon game resync.\n";
        exit(0);
    }

    $cfg = DrakonService::config($pdo);
    if (empty($cfg['is_active'])) {
        echo "Drakon integration is not active — skipping game resync.\n";
        exit(0);
    }

    @set_time_limit(0);
    $result = DrakonService::syncGames($pdo);
    $count  = (int) ($result['count'] ?? 0);
    $pruned = (int) ($result['pruned'] ?? 0);
    echo "Drakon games resynced: {$count} upserted, {$pruned} stale deactivated.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Drakon game resync FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
