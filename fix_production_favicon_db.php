<?php
/**
 * Production Fix: Update favicon and manifest URLs to correct local paths
 * Usage: Access via browser at admin panel, or run via CLI
 */

require_once dirname(__DIR__) . '/admin/app/Core/AdminDatabase.php';

$pdo = AdminDatabase::pdo();

// Check current values
echo "=== Current Values ===\n";
$current = $pdo->query('SELECT favicon_url, manifest_url FROM site_ayarlar WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
echo "Current favicon_url: " . ($current['favicon_url'] ?? 'NULL') . "\n";
echo "Current manifest_url: " . ($current['manifest_url'] ?? 'NULL') . "\n\n";

// Update to correct local paths
echo "=== Updating to correct paths ===\n";
$stmt = $pdo->prepare('UPDATE site_ayarlar SET favicon_url = ?, manifest_url = ? WHERE id = 1');
$result = $stmt->execute([
    '/assets/images/favicons/favicon.svg',
    '/assets/images/favicons/site.webmanifest'
]);

if ($result) {
    echo "✅ Database updated successfully\n";
    
    // Verify
    $updated = $pdo->query('SELECT favicon_url, manifest_url FROM site_ayarlar WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    echo "\nNew favicon_url: " . $updated['favicon_url'] . "\n";
    echo "New manifest_url: " . $updated['manifest_url'] . "\n";
} else {
    echo "❌ Update failed\n";
}
?>
