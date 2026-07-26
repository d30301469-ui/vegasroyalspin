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
            require_once __DIR__ . '/../config/frontend_session.php';
            metropol_frontend_session_start();
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
            require_once __DIR__ . '/../config/frontend_session.php';
            metropol_frontend_session_start();
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

    /**
     * Backend v2 rotası `users/{id}/balance-adjust` şeklindedir ve
     * wallet/action/amount gövdesi bekler. Yanıt kontrol edilmezse bakiye
     * yüklenmediği halde callback başarılı sanılır — bu yüzden bool döner.
     */
    public function updateBalance(int $userId, float $amount): bool
    {
        if ($userId <= 0 || abs($amount) < 0.01) {
            return false;
        }

        $response = BackendApiClient::request('POST', $this->backendKey, '/users/' . $userId . '/balance-adjust', [], [
            'wallet' => 'balance',
            'action' => $amount >= 0 ? 'add' : 'subtract',
            'amount' => round(abs($amount), 2),
            'note'   => 'legacy-payment-callback',
        ]);

        return is_array($response) && (bool) ($response['success'] ?? false);
    }
}
