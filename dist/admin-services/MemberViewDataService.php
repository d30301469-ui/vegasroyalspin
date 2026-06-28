<?php

declare(strict_types=1);

require_once __DIR__ . '/BackendApiClient.php';

final class MemberViewDataService
{
    public static function balanceForSession(): float
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0 || empty($_SESSION['loggedin'])) {
            return 0.0;
        }

        if (function_exists('frontend_database_allowed') && !frontend_database_allowed()) {
            $data = self::fetchViaApi('GET', 'balance.php');
            $balanceField = $data['balance'] ?? null;
            if (is_array($balanceField)) {
                $balance = $balanceField['balance'] ?? $balanceField['total_balance'] ?? null;
            } else {
                $balance = $balanceField
                    ?? $data['ana_bakiye']
                    ?? $data['amount']
                    ?? ($data['wallet']['balance'] ?? null);
            }

            return is_numeric($balance) ? (float) $balance : 0.0;
        }

        try {
            $stmt = self::pdo()->prepare('SELECT balance FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $userId]);
            return (float) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0.0;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function profileForSession(): array
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0 || empty($_SESSION['loggedin'])) {
            return [];
        }

        if (function_exists('frontend_database_allowed') && !frontend_database_allowed()) {
            $data = self::fetchViaApi('GET', 'profile_detail.php');
            if (isset($data['user']) && is_array($data['user'])) {
                return $data['user'];
            }
            if (isset($data['profile']) && is_array($data['profile'])) {
                return $data['profile'];
            }

            return $data;
        }

        try {
            $stmt = self::pdo()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function fetchViaApi(string $method, string $path): array
    {
        require_once __DIR__ . '/ProfileApiHelper.php';
        $jwt = ProfileApiHelper::resolveMemberJwt();
        if ($jwt === '') {
            return [];
        }

        try {
            $response = BackendApiClient::requestWithMemberBearer(
                $method,
                BackendApiClient::SVC_MAIN,
                $path,
                $jwt
            );
        } catch (Throwable) {
            return [];
        }

        return BackendApiClient::unwrap($response);
    }

    private static function pdo(): PDO
    {
        if (function_exists('frontend_database_allowed') && !frontend_database_allowed()) {
            throw new RuntimeException('Direct database access is disabled on API-only frontend hosts.');
        }

        if (!defined('ADMIN_APP_PATH')) {
            define('ADMIN_APP_PATH', BASE_PATH . '/admin/app');
        }
        if (!class_exists('AdminDatabase', false)) {
            require_once ADMIN_APP_PATH . '/Core/AdminDatabase.php';
        }

        return AdminDatabase::pdo();
    }
}
