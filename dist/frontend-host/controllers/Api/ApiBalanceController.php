<?php

/**
 * Bakiye API controller – sadece HTTP/JSON ve servis çağrısı.
 */
class ApiBalanceController
{
    public function index(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            echo json_encode(['status' => 'error', 'message' => 'Kullanıcı oturumu açmamış.']);
            return;
        }

        require_once SERVICE_PATH . '/BalanceService.php';
        $username = $_SESSION['username'] ?? '';
        $result = BalanceService::getBalanceForUsername($username);
        echo json_encode($result);
    }
}
