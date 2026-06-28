<?php

require_once dirname(__DIR__) . '/services/BackendApiClient.php';

/**
 * Kullanıcı verisi – backend v2 member API (JWT Bearer).
 */
class UserRepository
{
    private string $backendKey;

    public function __construct(string $backendKey = BackendApiClient::SVC_MAIN)
    {
        $this->backendKey = $backendKey;
    }

    public function getBalanceByUsername(string $username): ?float
    {
        unset($username);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $jwt = trim((string) ($_SESSION['member_jwt'] ?? ''));
        if ($jwt === '') {
            return null;
        }

        foreach (['/account/balance', '/balance.php', '/balance'] as $path) {
            $j = BackendApiClient::requestWithMemberBearer('GET', $this->backendKey, $path, $jwt);
            $row = BackendApiClient::unwrap($j);
            if ($row === [] && $j === null) {
                continue;
            }
            if (isset($row['ana_bakiye'])) {
                return (float) $row['ana_bakiye'];
            }
            if (isset($row['balance']['balance'])) {
                return (float) $row['balance']['balance'];
            }
        }

        return null;
    }

    public function findById(int $id): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $jwt = trim((string) ($_SESSION['member_jwt'] ?? ''));
        if ($jwt === '') {
            return null;
        }

        foreach (['/profile/detail', '/profile_detail.php'] as $path) {
            $j = BackendApiClient::requestWithMemberBearer('GET', $this->backendKey, $path, $jwt);
            $data = BackendApiClient::unwrap($j);
            $user = is_array($data['user'] ?? null) ? $data['user'] : $data;
            if ($user !== [] && (int) ($user['id'] ?? 0) === $id) {
                return $user;
            }
            if ($user !== [] && $id <= 0) {
                return $user;
            }
        }

        return null;
    }

    public function updateBalance(int $userId, float $amount): void
    {
        BackendApiClient::request('POST', $this->backendKey, '/users/balance-adjust', [], [
            'user_id' => $userId,
            'amount'  => $amount,
        ]);
    }
}
