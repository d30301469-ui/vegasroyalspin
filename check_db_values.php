<?php
require_once __DIR__ . '/admin/app/Core/AdminDatabase.php';

try {
    $pdo = AdminDatabase::pdo();
    $row = $pdo->query('SELECT id, logo_url, favicon_url, manifest_url, og_image_url FROM site_ayarlar LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        echo "=== Database Values ===\n";
        echo "ID: " . ($row['id'] ?? 'N/A') . "\n";
        echo "logo_url: " . ($row['logo_url'] ?? 'NULL') . "\n";
        echo "favicon_url: " . ($row['favicon_url'] ?? 'NULL') . "\n";
        echo "manifest_url: " . ($row['manifest_url'] ?? 'NULL') . "\n";
        echo "og_image_url: " . ($row['og_image_url'] ?? 'NULL') . "\n";
    } else {
        echo "No records found\n";
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
