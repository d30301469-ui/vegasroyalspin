#!/usr/bin/env php
<?php
/**
 * Fix favicon and manifest URLs in production database
 * Usage: php fix_favicon_urls_cli.php
 */

// Find admin database class
$paths = [
    __DIR__ . '/admin/app/Core/AdminDatabase.php',
    dirname(__DIR__) . '/admin/app/Core/AdminDatabase.php',
];

$found = false;
foreach ($paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $found = true;
        break;
    }
}

if (!$found) {
    echo "ERROR: Cannot find AdminDatabase class\n";
    exit(1);
}

try {
    $pdo = AdminDatabase::pdo();
    
    echo "=== Current Database Values ===\n";
    $stmt = $pdo->query('SELECT id, favicon_url, manifest_url FROM site_ayarlar WHERE id = 1');
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current) {
        echo "ERROR: No site_ayarlar record found\n";
        exit(1);
    }
    
    echo "ID: {$current['id']}\n";
    echo "Current favicon_url: {$current['favicon_url']}\n";
    echo "Current manifest_url: {$current['manifest_url']}\n\n";
    
    // Check if already correct
    if ($current['favicon_url'] === '/assets/images/favicons/favicon.svg' && 
        $current['manifest_url'] === '/assets/images/favicons/site.webmanifest') {
        echo "✅ Already correct! No update needed.\n";
        exit(0);
    }
    
    echo "=== Updating to correct local paths ===\n";
    $stmt = $pdo->prepare('UPDATE site_ayarlar SET favicon_url = :favicon, manifest_url = :manifest WHERE id = 1');
    $result = $stmt->execute([
        ':favicon' => '/assets/images/favicons/favicon.svg',
        ':manifest' => '/assets/images/favicons/site.webmanifest'
    ]);
    
    if (!$result) {
        echo "❌ Update failed: " . implode(', ', $stmt->errorInfo()) . "\n";
        exit(1);
    }
    
    echo "Rows affected: {$pdo->lastInsertId()}\n\n";
    
    echo "=== Verifying Update ===\n";
    $stmt = $pdo->query('SELECT id, favicon_url, manifest_url FROM site_ayarlar WHERE id = 1');
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "ID: {$updated['id']}\n";
    echo "✅ New favicon_url: {$updated['favicon_url']}\n";
    echo "✅ New manifest_url: {$updated['manifest_url']}\n\n";
    
    echo "SUCCESS: Database updated! Clear any site caches.\n";
    exit(0);
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
