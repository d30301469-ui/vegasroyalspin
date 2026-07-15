<?php
// Test API endpoints
$baseUrl = 'https://api.vegasroyalspin.com';

echo "=== Testing API Endpoints ===\n\n";

// Test 1: Root API
echo "1. Testing: GET $baseUrl/\n";
$ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
$response = @file_get_contents("$baseUrl/", false, $ctx);
if ($response !== false) {
    $json = json_decode($response, true);
    echo "Response: " . json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
} else {
    echo "Error: Could not reach API\n\n";
}

// Test 2: Site settings endpoint
echo "2. Testing: GET $baseUrl/api/v2/site-settings\n";
$response = @file_get_contents("$baseUrl/api/v2/site-settings", false, $ctx);
if ($response !== false) {
    $json = json_decode($response, true);
    echo "Response: " . json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
} else {
    echo "Error: Could not reach site-settings API\n\n";
}

// Test 3: Check with HTTP instead
echo "3. Testing HTTP (no SSL): GET http://api.vegasroyalspin.com/\n";
$response = @file_get_contents("http://api.vegasroyalspin.com/", false, stream_context_create([]));
if ($response !== false) {
    $json = json_decode($response, true);
    echo "Response: " . json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
} else {
    echo "Error: Could not reach API via HTTP\n\n";
}

echo "Done.\n";
?>
