<?php

require_once CONFIG_PATH . '/db.php';
require_once SERVICE_PATH . '/BackendApiClient.php';
require_once REPOSITORY_PATH . '/UserRepository.php';

/**
 * Bakiye iş mantığı – UserRepository (HTTP API).
 */
class BalanceService
{
    public static function getBalanceForUsername(string $username): array
    {
        $repo    = new UserRepository(BackendApiClient::SVC_MAIN);
        $balance = $repo->getBalanceByUsername($username);

        if ($balance === null) {
            return ['status' => 'error', 'message' => 'Kullanıcı bulunamadı.'];
        }

        $formatted = number_format($balance, 2, ',', '.') . ' ₺';
        return ['status' => 'success', 'ana_bakiye' => $formatted];
    }
}
