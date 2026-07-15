<?php
/**
 * Generate favicon files from SVG
 * Usage: php generate_favicons.php
 */

$svgPath = __DIR__ . '/assets/images/favicons/favicon.svg';
$outputDir = __DIR__ . '/assets/images/favicons';

if (!file_exists($svgPath)) {
    die("SVG file not found: $svgPath\n");
}

echo "Generating favicon files from SVG...\n";

// Read SVG as base image
$svgContent = file_get_contents($svgPath);

// Define favicon configurations
$configs = [
    ['name' => 'favicon-96x96.png', 'size' => 96, 'bg' => 'transparent'],
    ['name' => 'apple-touch-icon.png', 'size' => 180, 'bg' => 'white'],
    ['name' => 'web-app-manifest-192x192.png', 'size' => 192, 'bg' => 'white'],
    ['name' => 'web-app-manifest-512x512.png', 'size' => 512, 'bg' => 'white'],
];

// Try to use Imagick if available
if (extension_loaded('imagick')) {
    echo "Using Imagick extension...\n\n";
    
    foreach ($configs as $config) {
        try {
            $img = new Imagick();
            $img->setBackgroundColor(new ImagickPixel($config['bg']));
            $img->readImageBlob($svgContent);
            $img->resizeImage($config['size'], $config['size'], Imagick::FILTER_LANCZOS, 1);
            $img->setImageFormat('png');
            
            $outputPath = $outputDir . '/' . $config['name'];
            $img->writeImage($outputPath);
            $img->destroy();
            
            $size = filesize($outputPath);
            echo "✓ {$config['name']} ({$config['size']}x{$config['size']}) - $size bytes\n";
        } catch (Exception $e) {
            echo "✗ {$config['name']} - Error: {$e->getMessage()}\n";
        }
    }
    
    echo "\nFavicon generation complete!\n";
} else if (extension_loaded('gd')) {
    echo "Warning: Imagick not available. GD extension doesn't support SVG rasterization.\n";
    echo "Please install ImageMagick or use an online SVG converter.\n";
} else {
    echo "Error: Neither Imagick nor GD extension is available.\n";
    echo "Please install ImageMagick with PHP support.\n";
}

// Display all favicon files
echo "\n--- Current Favicon Files ---\n";
$files = glob($outputDir . '/*');
foreach ($files as $file) {
    if (is_file($file)) {
        $name = basename($file);
        $size = filesize($file);
        $formatted_size = number_format($size) . ' bytes';
        echo "$name: $formatted_size\n";
    }
}
?>
