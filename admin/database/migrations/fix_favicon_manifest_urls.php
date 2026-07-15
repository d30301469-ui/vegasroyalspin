<?php
/**
 * Fix favicon and manifest URLs in site_ayarlar table
 * Run: php admin/database/migrations/fix_favicon_manifest_urls.php
 */

require_once __DIR__ . '/../../app/Core/AdminDatabase.php';

$migration = new class {
    public function up(): void {
        $pdo = AdminDatabase::pdo();
        
        // Fix favicon and manifest URLs to point to local assets
        $stmt = $pdo->prepare(
            'UPDATE site_ayarlar 
             SET favicon_url = ?, manifest_url = ? 
             WHERE id = 1'
        );
        
        $result = $stmt->execute([
            '/assets/images/favicons/favicon.svg',
            '/assets/images/favicons/site.webmanifest'
        ]);
        
        if ($result) {
            echo "✅ Fixed favicon_url and manifest_url\n";
            
            // Verify
            $check = $pdo->query('SELECT favicon_url, manifest_url FROM site_ayarlar WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
            echo "   favicon_url: " . $check['favicon_url'] . "\n";
            echo "   manifest_url: " . $check['manifest_url'] . "\n";
        } else {
            throw new Exception('Failed to update favicon and manifest URLs');
        }
    }
    
    public function down(): void {
        // Rollback is not applicable for this fix
    }
};

try {
    $migration->up();
    echo "\n✅ Migration completed successfully\n";
} catch (Throwable $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
