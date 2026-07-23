<?php

declare(strict_types=1);

/**
 * STANDALONE: Upload to admin.vegasroyalspin.com webroot and access via browser.
 * DELETE AFTER USE!
 * 
 * Usage: https://admin.vegasroyalspin.com/cleanup-pending-tx.php?run=1
 *         https://admin.vegasroyalspin.com/cleanup-pending-tx.php (preview only)
 */

// ---- Auth: simple token check ----
$authToken = 'vegas-cleanup-2026';
$provided = trim((string) ($_GET['token'] ?? ''));
$isRun = ($_GET['run'] ?? '') === '1';

if ($provided !== $authToken) {
    http_response_code(401);
    die('Unauthorized. Add ?token=vegas-cleanup-2026 to URL.');
}

// ---- Load env ----
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    $envFile = dirname(__DIR__) . '/.env';
}
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $_ENV[trim($parts[0])] = trim($parts[1]);
            putenv(trim($parts[0]) . '=' . trim($parts[1]));
        }
    }
}

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3306';
$dbname = $_ENV['DB_DATABASE'] ?? '';
$user = $_ENV['DB_USERNAME'] ?? '';
$pass = $_ENV['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $e) {
    http_response_code(500);
    die('DB connection failed: ' . $e->getMessage());
}

$targetStatuses = "'pending','failed','rejected'";

// Preview mode
if (!$isRun) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "=== PREVIEW MODE ===\n";
    echo "Add &run=1 to execute cleanup.\n\n";
    
    echo "--- megapayz_transactions ---\n";
    $rows = $pdo->query("SELECT id, type, status, amount, created_at FROM megapayz_transactions WHERE status IN ({$targetStatuses}) ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo "  ID={$r['id']} | {$r['type']} | {$r['status']} | {$r['amount']} TRY | {$r['created_at']}\n";
    }
    
    $approved = (int) $pdo->query("SELECT COUNT(*) FROM megapayz_transactions WHERE status NOT IN ({$targetStatuses})")->fetchColumn();
    $callbacks = (int) $pdo->query("SELECT COUNT(*) FROM megapayz_callbacks")->fetchColumn();
    
    echo "\nTo delete: " . count($rows) . " transactions\n";
    echo "Callbacks to delete: {$callbacks}\n";
    echo "Will remain (approved): {$approved}\n";
    exit;
}

// ---- EXECUTE ----
try {
    $pdo->beginTransaction();
    $deletedTx = $pdo->exec("DELETE FROM megapayz_transactions WHERE status IN ({$targetStatuses})");
    $deletedCallbacks = $pdo->exec('DELETE FROM megapayz_callbacks');
    $pdo->exec('ALTER TABLE megapayz_transactions AUTO_INCREMENT = 1');
    $pdo->exec('ALTER TABLE megapayz_callbacks AUTO_INCREMENT = 1');
    $pdo->commit();
    
    header('Content-Type: text/plain; charset=utf-8');
    echo "=== CLEANUP COMPLETE ===\n";
    echo "Deleted transactions: {$deletedTx}\n";
    echo "Deleted callbacks: {$deletedCallbacks}\n";
    echo "AUTO_INCREMENT reset to 1 for both tables.\n";
    echo "\nDELETE THIS FILE after use!\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    die('Cleanup failed: ' . $e->getMessage());
}
