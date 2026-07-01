<?php
declare(strict_types=1);
define('METROPOL_DRAKON_WEBHOOK', true);
chdir(__DIR__ . '/../admin');
require __DIR__ . '/../admin/app/Core/AdminPaths.php';
admin_paths_bootstrap();
require admin_panel_paths()['panel_app'] . '/bootstrap_api.php';
require __DIR__ . '/../admin/services/DrakonService.php';

$pdo = AdminDatabase::pdo();
$config = DrakonService::config($pdo);

$agentToken = (string) ($config['agent_token'] ?? '');
$agentSecret = (string) ($config['agent_secret'] ?? '');
$auth = base64_encode($agentToken . ':' . $agentSecret);

// Test with manual redirect (new logic) to gator.drakonapi.tech directly
$testUrl = 'https://gator.drakonapi.tech/api/v1/auth/authentication';
echo "Testing direct POST to: $testUrl" . PHP_EOL;

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $testUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_POST           => true,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Authorization: Bearer ' . $auth],
]);
$raw = curl_exec($ch);
$err = curl_error($ch);
$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

$body = is_string($raw) && $headerSize > 0 ? substr($raw, $headerSize) : (string)$raw;
echo "HTTP Status: $code" . PHP_EOL;
echo "cURL error: " . ($err ?: '(none)') . PHP_EOL;
echo "Response body: " . $body . PHP_EOL;
