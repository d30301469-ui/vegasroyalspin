<?php

require_once SERVICE_PATH . '/BackendApiClient.php';

/**
 * Bonus kodu kullanım API.
 */
class ApiBonusController
{
    /**
     * Aktif oturumdaki kullanıcı adını döner; SESSION yoksa JWT Bearer'dan çözümlemeyi dener.
     * Null dönerse istemci yetkisizdir.
     */
    private static function resolveUsername(): ?string
    {
        if (!empty($_SESSION['username'])) {
            return (string) $_SESSION['username'];
        }

        // JWT Bearer token ile kimlik doğrulama (mobil / API istemcileri).
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

        // İmzayı doğrula (PDO gerekmez, salt imza kontrolü).
        if (is_file(SERVICE_PATH . '/MemberJwtVerify.php')) {
            require_once SERVICE_PATH . '/MemberJwtVerify.php';
        }
        if (!class_exists('MemberJwtVerify', false) || !MemberJwtVerify::signatureValid($jwt)) {
            return null;
        }

        // Payload'dan kullanıcı adını çıkar.
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

    public function useCode(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $kullanici_adi = self::resolveUsername();
        if ($kullanici_adi === null) {
            http_response_code(401);
            echo json_encode(['success' => false, 'status' => 'error', 'mesaj' => 'Kullanıcı oturumu bulunamadı.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $raw  = file_get_contents('php://input');
        $data = json_decode($raw);
        $kod  = isset($data->kod) ? $data->kod : null;

        if ($kod === null || $kod === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'status' => 'error', 'mesaj' => 'Geçersiz istek.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $res = BackendApiClient::request('POST', BackendApiClient::SVC_MAIN, '/promotions/use-code', [], [
            'username' => $kullanici_adi,
            'kod'      => $kod,
        ]);

        if ($res === null) {
            http_response_code(503);
            echo json_encode(['success' => false, 'status' => 'error', 'mesaj' => 'Backend API yanıt vermedi.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!empty($res['status']) && $res['status'] === 'success') {
            echo json_encode(['success' => true, 'status' => 'success', 'mesaj' => $res['mesaj'] ?? 'Promosyon kodu başarıyla kullanıldı!'], JSON_UNESCAPED_UNICODE);
            return;
        }

        http_response_code(422);
        echo json_encode(['success' => false, 'status' => 'error', 'mesaj' => $res['mesaj'] ?? $res['message'] ?? 'İşlem başarısız.'], JSON_UNESCAPED_UNICODE);
    }
}
