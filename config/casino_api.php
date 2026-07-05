<?php
/**
 * Legacy ApiGate yapÄ±landÄ±rmasÄ± tasfiye edildi.
 * Oyun launch ve wallet callback akÄ±ÅŸÄ± BGaming Ã¼zerinden Ã§alÄ±ÅŸÄ±r.
 */

if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', dirname(__DIR__) . '/config');
}

require_once CONFIG_PATH . '/db.php';

define('CASINO_API_URL', getenv('CASINO_API_URL') ?: '');
define('CASINO_AGENT_CODE', getenv('CASINO_AGENT_CODE') ?: '');
define('CASINO_API_TOKEN', getenv('CASINO_API_TOKEN') ?: '');

// Eski sabit isimleri (geriye uyumluluk)
if (!defined('API_URL')) {
    define('API_URL', CASINO_API_URL);
}
if (!defined('AGENT_CODE')) {
    define('AGENT_CODE', CASINO_AGENT_CODE);
}
if (!defined('API_TOKEN')) {
    define('API_TOKEN', CASINO_API_TOKEN);
}

$log_dir = dirname(__DIR__) . '/logs';
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0755, true);
}

function casino_api_log(string $type, string $message, $data = null): void
{
    $log_file = dirname(__DIR__) . '/logs/casino_api.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp][$type] $message";
    if ($data !== null) {
        $log_message .= " - " . (is_array($data) || is_object($data)
            ? json_encode($data, JSON_UNESCAPED_UNICODE)
            : (string) $data);
    }
    $log_message .= PHP_EOL;
    @file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
    if ($type === 'ERROR') {
        error_log($log_message);
    }
}

function casino_api_request(array $data): array
{
    casino_api_log('ERROR', 'Legacy ApiGate request blocked after provider migration', $data);
    return ['status' => 410, 'msg' => 'LEGACY_PROVIDER_DISABLED'];
}
