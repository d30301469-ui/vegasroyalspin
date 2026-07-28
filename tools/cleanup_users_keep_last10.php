#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Canli DB: son 10 uye haric tum kullanicilari sil, ID'leri 1..N yap, AUTO_INCREMENT sifirla.
 *
 * Kullanim (VPS):
 *   cd /www/wwwroot/vegasroyalspin.com
 *   php tools/cleanup_users_keep_last10.php --dry-run
 *   php tools/cleanup_users_keep_last10.php --execute
 *
 * Alternatif root (admin panel yolu):
 *   php tools/cleanup_users_keep_last10.php --execute /www/wwwroot/admin.vegasroyalspin.com
 */

$keepCount = 10;
$execute = in_array('--execute', $argv, true);
$dryRun = !$execute;

$root = dirname(__DIR__);
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '' || str_starts_with($arg, '-')) {
        continue;
    }
    $root = rtrim(str_replace('\\', '/', $arg), '/');
}

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
if (!defined('METROPOL_ADMIN_PANEL')) {
    define('METROPOL_ADMIN_PANEL', true);
}

$envBootstrap = $root . '/config/env.php';
if (is_readable($envBootstrap)) {
    require_once $envBootstrap;
    if (function_exists('frontend_load_dotenv')) {
        frontend_load_dotenv($root);
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
    fwrite(STDERR, "AdminPaths bootstrap bulunamadi: {$root}\n");
    exit(1);
}

if (!class_exists('AdminDatabase', false)) {
    $dbPath = function_exists('admin_project_path')
        ? admin_project_path('app/Core/AdminDatabase.php')
        : ($root . '/app/Core/AdminDatabase.php');
    if (!is_readable($dbPath)) {
        $dbPath = $root . '/admin/app/Core/AdminDatabase.php';
    }
    if (!is_readable($dbPath)) {
        fwrite(STDERR, "AdminDatabase.php bulunamadi\n");
        exit(1);
    }
    require_once $dbPath;
}

try {
    $pdo = AdminDatabase::pdo();
} catch (Throwable $e) {
    fwrite(STDERR, 'DB baglanti hatasi: ' . $e->getMessage() . "\n");
    exit(1);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

echo $dryRun ? "=== DRY RUN (canli DB) ===\n\n" : "=== EXECUTE (canli DB) ===\n\n";

$dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
echo "Database: {$dbName}\n";

$total = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
echo "Total users: {$total}\n";

$keep = $pdo->query("SELECT id, username, email, created_at FROM users ORDER BY id DESC LIMIT {$keepCount}")->fetchAll();
if (!$keep) {
    fwrite(STDERR, "users tablosu bos.\n");
    exit(1);
}

echo "Keep (son {$keepCount} by id):\n";
foreach ($keep as $u) {
    echo "  #{$u['id']} {$u['username']} <{$u['email']}> {$u['created_at']}\n";
}

$keepIds = array_map(static fn(array $u): int => (int) $u['id'], $keep);
$keepList = implode(',', $keepIds);
$deleteCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE id NOT IN ({$keepList})")->fetchColumn();
echo "\nWill delete: {$deleteCount} users\n";

$cols = $pdo->query("
    SELECT TABLE_NAME, COLUMN_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND COLUMN_NAME IN ('user_id', 'member_id')
    ORDER BY TABLE_NAME, COLUMN_NAME
")->fetchAll();

echo "\nRelated columns (orphan rows to delete):\n";
foreach ($cols as $c) {
    $cnt = (int) $pdo->query(
        "SELECT COUNT(*) FROM `{$c['TABLE_NAME']}` WHERE `{$c['COLUMN_NAME']}` IS NOT NULL AND `{$c['COLUMN_NAME']}` NOT IN ({$keepList})"
    )->fetchColumn();
    if ($cnt > 0) {
        echo "  {$c['TABLE_NAME']}.{$c['COLUMN_NAME']}: {$cnt}\n";
    }
}

$status = $pdo->query("SHOW TABLE STATUS LIKE 'users'")->fetch();
echo "\nCurrent AUTO_INCREMENT: " . ($status['Auto_increment'] ?? '?') . "\n";

if ($dryRun) {
    echo "\nDry run tamam. Uygulamak icin:\n";
    echo "  php tools/cleanup_users_keep_last10.php --execute\n";
    exit(0);
}

echo "\nApplying cleanup...\n";

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    foreach ($cols as $c) {
        $n = $pdo->exec(
            "DELETE FROM `{$c['TABLE_NAME']}` WHERE `{$c['COLUMN_NAME']}` IS NOT NULL AND `{$c['COLUMN_NAME']}` NOT IN ({$keepList})"
        );
        if ($n > 0) {
            echo "  DELETE {$c['TABLE_NAME']}.{$c['COLUMN_NAME']}: {$n}\n";
        }
    }

    $n = $pdo->exec("DELETE FROM users WHERE id NOT IN ({$keepList})");
    echo "  DELETE users: {$n}\n";

    usort($keep, static fn(array $a, array $b): int => ((int) $a['id']) <=> ((int) $b['id']));

    $tempBase = 900000;
    $map = [];
    foreach ($keep as $i => $u) {
        $oldId = (int) $u['id'];
        $newId = $i + 1;
        $map[$oldId] = $newId;
        $tempId = $tempBase + $newId;

        $pdo->prepare('UPDATE users SET id = :temp WHERE id = :old')->execute([
            ':temp' => $tempId,
            ':old' => $oldId,
        ]);
        foreach ($cols as $c) {
            $pdo->prepare(
                "UPDATE `{$c['TABLE_NAME']}` SET `{$c['COLUMN_NAME']}` = :temp WHERE `{$c['COLUMN_NAME']}` = :old"
            )->execute([':temp' => $tempId, ':old' => $oldId]);
        }
        echo "  Remap {$oldId} -> temp {$tempId} (final {$newId})\n";
    }

    foreach ($map as $oldId => $newId) {
        $tempId = $tempBase + $newId;
        $pdo->prepare('UPDATE users SET id = :new WHERE id = :temp')->execute([
            ':new' => $newId,
            ':temp' => $tempId,
        ]);
        foreach ($cols as $c) {
            $pdo->prepare(
                "UPDATE `{$c['TABLE_NAME']}` SET `{$c['COLUMN_NAME']}` = :new WHERE `{$c['COLUMN_NAME']}` = :temp"
            )->execute([':new' => $newId, ':temp' => $tempId]);
        }
        echo "  Final id {$newId} (was {$oldId})\n";
    }

    // Force AUTO_INCREMENT reset (InnoDB sometimes ignores plain ALTER)
    $max = (int) $pdo->query('SELECT MAX(id) FROM users')->fetchColumn();
    $next = $max + 1;
    $pdo->exec('DROP TABLE IF EXISTS users_ai_reset');
    $pdo->exec('CREATE TABLE users_ai_reset LIKE users');
    $pdo->exec('INSERT INTO users_ai_reset SELECT * FROM users');
    $pdo->exec('DROP TABLE users');
    $pdo->exec('RENAME TABLE users_ai_reset TO users');
    $pdo->exec("ALTER TABLE users AUTO_INCREMENT = {$next}");
    echo "  AUTO_INCREMENT = {$next}\n";

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
} catch (Throwable $e) {
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    } catch (Throwable $ignored) {
    }
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "\nDONE. Remaining users:\n";
foreach ($pdo->query('SELECT id, username, email FROM users ORDER BY id') as $u) {
    echo "  #{$u['id']} {$u['username']} <{$u['email']}>\n";
}

$status = $pdo->query("SHOW TABLE STATUS LIKE 'users'")->fetch();
echo "\nAUTO_INCREMENT: " . ($status['Auto_increment'] ?? '?') . "\n";
echo "Database: {$dbName}\n";
