#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Bekleyen / failed / rejected tum yatirim-cekim islemlerini temizler.
 * Production sunucuda calistirin:
 *   php deploy/aapanel/reset-pending-transactions.php
 * veya
 *   php deploy/aapanel/reset-pending-transactions.php /www/wwwroot/admin.vegasroyalspin.com
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

if (!class_exists('AdminDatabase', false)) {
    require_once admin_project_path('app/Core/AdminDatabase.php');
}

try {
    $pdo = AdminDatabase::pdo();
    
    // Show current state
    echo "=== CURRENT STATE ===\n";
    $targetStatuses = "'pending','failed','rejected'";
    
    $depositPending = (int) $pdo->query("SELECT COUNT(*) FROM megapayz_transactions WHERE type='deposit' AND status IN ({$targetStatuses})")->fetchColumn();
    $withdrawPending = (int) $pdo->query("SELECT COUNT(*) FROM megapayz_transactions WHERE type='withdraw' AND status IN ({$targetStatuses})")->fetchColumn();
    $callbacks = (int) $pdo->query("SELECT COUNT(*) FROM megapayz_callbacks")->fetchColumn();
    $remaining = (int) $pdo->query("SELECT COUNT(*) FROM megapayz_transactions WHERE status NOT IN ({$targetStatuses})")->fetchColumn();
    
    echo "  Pending/failed/rejected deposits: {$depositPending}\n";
    echo "  Pending/failed/rejected withdrawals: {$withdrawPending}\n";
    echo "  Callbacks: {$callbacks}\n";
    echo "  Approved/confirmed (will remain): {$remaining}\n";
    
    $totalToDelete = $depositPending + $withdrawPending;
    if ($totalToDelete === 0 && $callbacks === 0) {
        echo "\nNothing to delete. Exiting.\n";
        exit(0);
    }
    
    echo "\n=== CLEANING ===\n";
    
    $pdo->beginTransaction();
    $deletedTx = $pdo->exec("DELETE FROM megapayz_transactions WHERE status IN ({$targetStatuses})");
    echo "  Deleted transactions: {$deletedTx}\n";
    
    $deletedCallbacks = $pdo->exec('DELETE FROM megapayz_callbacks');
    echo "  Deleted callbacks: {$deletedCallbacks}\n";
    
    $pdo->exec('ALTER TABLE megapayz_transactions AUTO_INCREMENT = 1');
    echo "  Reset megapayz_transactions AUTO_INCREMENT to 1\n";
    
    $pdo->exec('ALTER TABLE megapayz_callbacks AUTO_INCREMENT = 1');
    echo "  Reset megapayz_callbacks AUTO_INCREMENT to 1\n";
    
    $pdo->commit();
    
    echo "\n=== DONE ===\n";
    echo "All pending/failed/rejected transactions cleared. IDs reset to 1.\n";
    exit(0);
    
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
