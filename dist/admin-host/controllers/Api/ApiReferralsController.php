<?php

require_once SERVICE_PATH . '/BackendApiClient.php';

/**
 * Referans kodu ve yönlendirilen kullanıcılar API.
 */
class ApiReferralsController
{
    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $username = $_SESSION['username'] ?? '';
        if ($username === '') {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Oturum açık değil. Lütfen giriş yapın.']);
            return;
        }

        $res = BackendApiClient::request('GET', BackendApiClient::SVC_MAIN, '/referrals', ['username' => $username]);
        if ($res === null) {
            http_response_code(503);
            echo json_encode(['status' => 'error', 'message' => 'Backend API yanıt vermedi.']);
            return;
        }

        $u = BackendApiClient::unwrap($res);
        echo json_encode([
            'status'         => 'success',
            'referral_code'  => $u['referral_code'] ?? $res['referral_code'] ?? null,
            'referred_users' => $u['referred_users'] ?? $res['referred_users'] ?? [],
        ]);
    }
}
