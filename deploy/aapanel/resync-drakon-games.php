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

    // Self-heal the schema for catalogs created by an older migration. The live
    // drakon_games table can predate the game_code/image_url columns; because
    // production disables runtime CREATE/ALTER and `CREATE TABLE IF NOT EXISTS`
    // never backfills columns, syncGames()/games() would hit "Unknown column"
    // and the admin `casino/games` route silently drops every Drakon game.
    // MySQL 8 has no `ADD COLUMN IF NOT EXISTS`, so guard via information_schema.
    $columnExists = static function (PDO $pdo, string $column): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :t
                AND COLUMN_NAME = :c'
        );
        $stmt->execute([':t' => 'drakon_games', ':c' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    };
    if (!$columnExists($pdo, 'game_code')) {
        $pdo->exec('ALTER TABLE drakon_games ADD COLUMN game_code VARCHAR(100) NULL AFTER game_id');
        echo "Added missing column drakon_games.game_code.\n";
    }
    if (!$columnExists($pdo, 'image_url')) {
        $pdo->exec('ALTER TABLE drakon_games ADD COLUMN image_url VARCHAR(500) NULL AFTER banner');
        echo "Added missing column drakon_games.image_url.\n";
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
