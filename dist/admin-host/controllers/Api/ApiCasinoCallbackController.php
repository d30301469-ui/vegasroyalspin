<?php

require_once CONFIG_PATH . '/casino_api.php';
require_once SERVICE_PATH . '/BackendApiClient.php';

/**
 * Casino API (ApiGate) Seamless Integration Wallet Callback.
 * İstekler API_BACKEND_CASINO_WALLET_BASE_URL üzerindeki backend’e iletilir.
 */
class ApiCasinoCallbackController
{
    private const CALLBACK_LOG = 'casino_callback.log';

    public function index(): void
    {
        $corsAllowed = $this->applyCorsHeaders();
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            if (!$corsAllowed) {
                http_response_code(403);
                exit;
            }

            http_response_code(200);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['status' => 2, 'msg' => 'INVALID_ACTION']);
            exit;
        }

        if (!$this->ipAllowed()) {
            http_response_code(403);
            echo json_encode(['status' => 3, 'msg' => 'IP_NOT_ALLOWED']);
            exit;
        }

        $rawInput = file_get_contents('php://input');
        $input = is_string($rawInput) ? $rawInput : '';
        $request = json_decode($input, true);

        $logPath = dirname(__DIR__, 2) . '/logs/' . self::CALLBACK_LOG;
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($logPath, "[$timestamp] REQUEST: " . $this->redactedPayloadForLog($request, $input) . PHP_EOL, FILE_APPEND);

        if (!$request) {
            @file_put_contents($logPath, "[$timestamp] ERROR: Invalid JSON" . PHP_EOL, FILE_APPEND);
            http_response_code(400);
            echo json_encode(['status' => 13, 'msg' => 'INVALID_PARAMETER']);
            exit;
        }

        $configuredToken = defined('API_TOKEN') ? trim((string) API_TOKEN) : '';
        if ($configuredToken === '') {
            @file_put_contents($logPath, "[$timestamp] ERROR: Callback token is not configured" . PHP_EOL, FILE_APPEND);
            http_response_code(503);
            echo json_encode(['status' => 3, 'msg' => 'INVALID_AGENT']);
            exit;
        }

        $token = (string) ($request['token'] ?? '');
        if ($token === '' || !hash_equals($configuredToken, $token)) {
            @file_put_contents($logPath, "[$timestamp] ERROR: Invalid token" . PHP_EOL, FILE_APPEND);
            http_response_code(403);
            echo json_encode(['status' => 3, 'msg' => 'INVALID_AGENT']);
            exit;
        }

        $method = $request['method'] ?? '';
        @file_put_contents($logPath, "[$timestamp] METHOD: $method" . PHP_EOL, FILE_APPEND);

        $forwarded = BackendApiClient::forwardPostJson(
            BackendApiClient::SVC_CASINO_WALLET,
            '/wallet/seamless',
            $input
        );

        if ($forwarded !== null && $forwarded !== '') {
            echo $forwarded;
            return;
        }

        @file_put_contents($logPath, "[$timestamp] ERROR: Backend forward failed or API_BACKEND_CASINO_WALLET_BASE_URL boş" . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 1, 'msg' => 'INTERNAL_ERROR']);
    }

    private function applyCorsHeaders(): bool
    {
        $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        if ($origin === '') {
            return true;
        }

        $originHost = strtolower((string) (parse_url($origin, PHP_URL_HOST) ?: ''));
        if ($originHost === '' || !in_array($originHost, $this->allowedCorsHosts(), true)) {
            return false;
        }

        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin', false);
        return true;
    }

    /**
     * @return list<string>
     */
    private function allowedCorsHosts(): array
    {
        $hosts = [];
        foreach (['ALLOWED_URL_HOSTS', 'PUBLIC_URL_HOSTS'] as $key) {
            $value = (string) (getenv($key) ?: '');
            foreach (preg_split('/[\s,]+/', $value) ?: [] as $host) {
                $host = strtolower(preg_replace('/:\d+$/', '', trim((string) $host)) ?? '');
                if ($host !== '') {
                    $hosts[] = $host;
                }
            }
        }

        foreach (['SITE_URL', 'FRONTEND_URL', 'BACKEND_URL'] as $key) {
            $host = strtolower((string) (parse_url((string) (getenv($key) ?: ''), PHP_URL_HOST) ?: ''));
            if ($host !== '') {
                $hosts[] = $host;
            }
        }

        foreach (['BACKEND_HOST', 'ADMIN_URL_HOST'] as $key) {
            $host = strtolower(preg_replace('/:\d+$/', '', trim((string) (getenv($key) ?: ''))) ?? '');
            if ($host !== '') {
                $hosts[] = $host;
            }
        }

        return array_values(array_unique($hosts));
    }

    private function ipAllowed(): bool
    {
        $allowlist = trim((string) (getenv('CASINO_CALLBACK_ALLOWED_IPS') ?: ''));
        if ($allowlist === '') {
            return true;
        }

        $remoteIp = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($remoteIp === '' || filter_var($remoteIp, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        foreach (preg_split('/[\s,]+/', $allowlist) ?: [] as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            if ($item === $remoteIp) {
                return true;
            }
            if (str_ends_with($item, '.*')) {
                $prefix = substr($item, 0, -1);
                if ($prefix !== '' && str_starts_with($remoteIp, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function redactedPayloadForLog(mixed $request, string $rawInput): string
    {
        if (!is_array($request)) {
            return '[invalid-json length=' . strlen($rawInput) . ']';
        }

        $redacted = $request;
        foreach (['token', 'password', 'secret', 'api_key', 'private_key'] as $key) {
            if (array_key_exists($key, $redacted)) {
                $redacted[$key] = '[redacted]';
            }
        }

        return json_encode($redacted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[redacted-json]';
    }
}
