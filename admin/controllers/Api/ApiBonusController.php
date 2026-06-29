<?php

require_once SERVICE_PATH . '/BackendApiClient.php';

/**
 * Bonus kodu kullanım API.
 */
class ApiBonusController
{
    public function useCode(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!isset($_SESSION['username'])) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'mesaj' => 'Kullanıcı oturumu bulunamadı.']);
            return;
        }

        $kullanici_adi = $_SESSION['username'];
        $raw           = file_get_contents('php://input');
        $data          = json_decode($raw);
        $kod           = isset($data->kod) ? $data->kod : null;

        if ($kod === null || $kod === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'mesaj' => 'Geçersiz istek.']);
            return;
        }

        $res = BackendApiClient::request('POST', BackendApiClient::SVC_MAIN, '/promotions/use-code', [], [
            'username' => $kullanici_adi,
            'kod'      => $kod,
        ]);

        if ($res === null) {
            http_response_code(503);
            echo json_encode(['status' => 'error', 'mesaj' => 'Backend API yanıt vermedi.']);
            return;
        }

        if (!empty($res['status']) && $res['status'] === 'success') {
            echo json_encode(['status' => 'success', 'mesaj' => $res['mesaj'] ?? 'Promosyon kodu başarıyla kullanıldı!']);
            return;
        }

        echo json_encode(['status' => 'error', 'mesaj' => $res['mesaj'] ?? $res['message'] ?? 'İşlem başarısız.']);
    }
}
