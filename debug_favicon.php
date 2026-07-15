<?php
// Check database favicon_url value and test what browser sees
require_once __DIR__ . '/core/bootstrap.php';

echo "=== Debugging Favicon Issue ===\n\n";

// 1. Check what's in $siteBranding
echo "1. \$siteBranding array:\n";
var_dump($siteBranding ?? []);

echo "\n2. \$headBranding would be:\n";
$headBranding = (isset($siteBranding) && is_array($siteBranding)) ? $siteBranding : [];
var_dump($headBranding);

echo "\n3. Favicon URL calculation:\n";
$headFaviconUrl = cms_asset_url((string) ($headBranding['favicon_url'] ?? '/assets/images/favicons/favicon.svg'));
echo "Result: " . $headFaviconUrl . "\n";

echo "\n4. File existence checks:\n";
$checkFiles = [
    '/assets/images/favicons/favicon.svg',
    '/assets/images/favicons/favicon.ico',
    '/favicon.ico'
];
foreach ($checkFiles as $file) {
    $fullPath = BASE_PATH . $file;
    $exists = is_file($fullPath);
    echo "$file: " . ($exists ? "EXISTS" : "NOT FOUND") . "\n";
}

echo "\n5. Testing ApiMediaUrl::resolve():\n";
if (class_exists('ApiMediaUrl')) {
    $testPaths = [
        '/assets/images/favicons/favicon.svg',
        'assets/images/favicons/favicon.svg',
        '/uploads/test.svg'
    ];
    foreach ($testPaths as $path) {
        $resolved = ApiMediaUrl::resolve($path);
        echo "  '$path' -> '$resolved'\n";
    }
}
?>
