<?php

require_once SERVICE_PATH . '/BackendApiClient.php';

/**
 * Kayıt öncesi referral tıklamalarını takip eden endpoint.
 */
class ApiSignupTrackerController
{
    public function index(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $ref = isset($_GET['ref']) ? (string) $_GET['ref'] : '';

        if ($ref !== '') {
            $clientIp = function_exists('metropol_cloudflare_client_ip')
                ? metropol_cloudflare_client_ip()
                : (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            BackendApiClient::request('POST', BackendApiClient::SVC_AFFILIATE, '/track-click', [], [
                'referral_code' => $ref,
                'ip'            => $clientIp !== '' ? $clientIp : '0.0.0.0',
            ]);
            $_SESSION['referral_code'] = $ref;
            header('Location: ' . SITE_URL . '/?ref=' . urlencode($ref));
            exit;
        }

        header('Location: ' . SITE_URL);
        exit;
    }
}
