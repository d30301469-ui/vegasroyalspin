<?php
// Check favicon_url in database
$env_file = __DIR__ . '/env';
if (!is_file($env_file)) {
    die("env dosyası bulunamadı\n");
}

$env_content = file_get_contents($env_file);
parse_str(str_replace(["\r\n", "\r", "\n"], "&", 
                     preg_replace('/^export\s+/m', '', 
                     preg_replace('/#.*$/m', '', $env_content))), $env);

$host = $env['DB_HOST'] ?? 'localhost';
$user = $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASS'] ?? '';
$db = $env['DB_NAME'] ?? 'vegasroyalspin';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $row = $pdo->query("SELECT id, logo_url, favicon_url, manifest_url, og_image_url FROM site_ayarlar LIMIT 1")->fetch();
    
    if ($row) {
        echo "=== Site Settings ===\n";
        echo "ID: " . ($row['id'] ?? 'N/A') . "\n";
        echo "Logo URL: " . ($row['logo_url'] ?? 'NULL') . "\n";
        echo "Favicon URL: " . ($row['favicon_url'] ?? 'NULL') . "\n";
        echo "Manifest URL: " . ($row['manifest_url'] ?? 'NULL') . "\n";
        echo "OG Image URL: " . ($row['og_image_url'] ?? 'NULL') . "\n";
    } else {
        echo "No site_ayarlar record found\n";
    }
    
} catch (Throwable $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
?>
