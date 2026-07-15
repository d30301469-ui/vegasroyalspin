<?php
require_once __DIR__ . '/admin/app/Core/AdminDatabase.php';

try {
    $pdo = AdminDatabase::pdo();
    
    echo "=== Fixing favicon and manifest URLs ===\n\n";
    
    // Update favicon_url and manifest_url to correct local paths
    $stmt = $pdo->prepare('UPDATE site_ayarlar SET favicon_url = ?, manifest_url = ? WHERE id = 1');
    $result = $stmt->execute([
        '/assets/images/favicons/favicon.svg',
        '/assets/images/favicons/site.webmanifest'
    ]);
    
    if ($result) {
        echo "✅ Updated successfully\n";
    } else {
        echo "❌ Update failed\n";
    }
    
    // Verify the update
    echo "\n=== Verification ===\n";
    $row = $pdo->query('SELECT favicon_url, manifest_url FROM site_ayarlar WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo "favicon_url: " . $row['favicon_url'] . "\n";
        echo "manifest_url: " . $row['manifest_url'] . "\n";
    }
    
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
