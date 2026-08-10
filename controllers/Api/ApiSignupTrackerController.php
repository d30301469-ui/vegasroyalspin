<?php

require_once SERVICE_PATH . '/BackendApiClient.php';
require_once SERVICE_PATH . '/ReferralAttribution.php';

/**
 * Kayıt öncesi referral tıklamalarını takip eden endpoint (/r/{code}).
 */
class ApiSignupTrackerController
{
    public function index(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            require_once __DIR__ . '/../../config/frontend_session.php';
            frontend_session_start();
        }

        $ref = ReferralAttribution::normalize((string) ($_GET['ref'] ?? ''));

        if ($ref === '') {
            header('Location: ' . SITE_URL);
            exit;
        }

        ReferralAttribution::remember($ref);

        $clientIp = function_exists('cloudflare_client_ip')
            ? cloudflare_client_ip()
            : (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        ReferralAttribution::trackClick($ref, $clientIp, SITE_URL . '/?ref=' . rawurlencode($ref));

        header('Location: ' . SITE_URL . '/?ref=' . rawurlencode($ref));
        exit;
    }
}
