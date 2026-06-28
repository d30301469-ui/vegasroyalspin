<?php

declare(strict_types=1);

/**
 * Lightweight register username/email availability check (no full page bootstrap).
 */
function metropol_handle_register_ajax_check(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'message' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    require_once __DIR__ . '/../config/paths.php';
    require_once BASE_PATH . '/config/bootstrap_api.php';
    require_once SERVICE_PATH . '/BackendApiClient.php';

    header('Content-Type: application/json; charset=UTF-8');

    $response = ['success' => true, 'username' => true, 'email' => true];
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));

    $timeout = function_exists('frontend_api_proxy_timeout') ? frontend_api_proxy_timeout() : 20;
    $check = BackendApiClient::request(
        'POST',
        BackendApiClient::SVC_MAIN,
        '/auth/check-availability',
        [],
        ['username' => $username, 'email' => $email],
        $timeout
    );

    if ($check !== null) {
        $c = BackendApiClient::unwrap($check);
        if (isset($c['username_available'])) {
            $response['username'] = (bool) $c['username_available'];
        }
        if (isset($c['email_available'])) {
            $response['email'] = (bool) $c['email_available'];
        }
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}
