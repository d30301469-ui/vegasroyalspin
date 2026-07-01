<?php

/**
 * Bakiye API controller – sadece HTTP/JSON ve servis çağrısı.
 */
class ApiBalanceController
{
    /**
     * Oturum veya JWT Bearer token'dan kullanıcı adını çözer.
     */
    private static function resolveUsername(): ?string
    {
        // 1. PHP session (tarayıcı istemcileri)
        $sessionUser = (string) ($_SESSION['username'] ?? '');
        if ($sessionUser !== '' && !empty($_SESSION['loggedin'])) {
            return $sessionUser;
        }

        // 2. JWT Bearer token (mobil / API istemcileri)
        $authHeader = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($authHeader === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                $authHeader = (string) ($headers['Authorization'] ?? $headers['authorization'] ?? '');
            }
        }
        if (preg_match('/^\s*Bearer\s+(.+)\s*$/i', $authHeader, $m) !== 1) {
            return null;
        }
        $jwt = trim((string) ($m[1] ?? ''));
        if ($jwt === '') {
            return null;
        }
        if (is_file(SERVICE_PATH . '/MemberJwtVerify.php')) {
            require_once SERVICE_PATH . '/MemberJwtVerify.php';
        }
        if (!class_exists('MemberJwtVerify', false) || !MemberJwtVerify::signatureValid($jwt)) {
            return null;
        }
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        $payloadRaw = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if (!is_string($payloadRaw)) {
            return null;
        }
        $payload = json_decode($payloadRaw, true);

        return is_array($payload) && isset($payload['username']) ? (string) $payload['username'] : null;
    }

    public function index(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $username = self::resolveUsername();
        if ($username === null) {
            http_response_code(401);
            echo json_encode(['success' => false, 'code' => 401, 'message' => 'Kullanıcı oturumu açmamış.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        require_once SERVICE_PATH . '/BalanceService.php';
        $result = BalanceService::getBalanceForUsername($username);
        echo json_encode($result);
    }
}
