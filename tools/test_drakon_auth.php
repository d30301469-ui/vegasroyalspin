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

echo "agent_token: " . ($config['agent_token'] ?? '(empty)') . PHP_EOL;
echo "agent_secret: " . (empty($config['agent_secret']) ? '(empty)' : '(set, ' . strlen($config['agent_secret']) . ' chars)') . PHP_EOL;
echo "api_base_url: " . ($config['api_base_url'] ?? '(empty)') . PHP_EOL;
echo PHP_EOL;

// Test auth directly via cURL
$agentToken = (string) ($config['agent_token'] ?? '');
$agentSecret = (string) ($config['agent_secret'] ?? '');
$apiBase = rtrim((string) ($config['api_base_url'] ?? 'https://gator.drakon.casino/api/v1'), '/');

$auth = base64_encode($agentToken . ':' . $agentSecret);
echo "Authorization header: Bearer " . substr($auth, 0, 20) . "..." . PHP_EOL;
echo "Auth URL: " . $apiBase . '/auth/authentication' . PHP_EOL . PHP_EOL;

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $apiBase . '/auth/authentication',
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
$effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

$headers = is_string($raw) && $headerSize > 0 ? substr($raw, 0, $headerSize) : '';
$body = is_string($raw) && $headerSize > 0 ? substr($raw, $headerSize) : (string)$raw;

echo "HTTP Status: $code" . PHP_EOL;
echo "Effective URL: $effectiveUrl" . PHP_EOL;
echo "cURL error: " . ($err ?: '(none)') . PHP_EOL;

// Check for redirect
if (preg_match('/^Location:\s*(.+)$/im', $headers, $m)) {
    echo "Redirect Location: " . trim($m[1]) . PHP_EOL;
}

echo "Response body: " . $body . PHP_EOL;
