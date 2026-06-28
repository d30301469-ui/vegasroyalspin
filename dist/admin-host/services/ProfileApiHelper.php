<?php

require_once __DIR__ . '/BackendApiClient.php';

/**
 * Profil ve hesap sayfaları — v2 member API (JWT Bearer).
 */
final class ProfileApiHelper
{
    public static function resolveMemberJwt(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $jwt = trim((string) ($_SESSION['member_jwt'] ?? ''));
        if ($jwt !== '') {
            return $jwt;
        }

        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $headerKey) {
            $auth = trim((string) ($_SERVER[$headerKey] ?? ''));
            if ($auth !== '' && preg_match('/^Bearer\s+(\S+)/i', $auth, $matches) === 1) {
                return trim((string) ($matches[1] ?? ''));
            }
        }

        return '';
    }

    private static function memberJwt(): string
    {
        return self::resolveMemberJwt();
    }

    /**
     * @return array<string, mixed>
     */
    private static function requestMember(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $jwt = self::memberJwt();
        if ($jwt === '') {
            return [];
        }

        $response = BackendApiClient::requestWithMemberBearer(
            $method,
            BackendApiClient::SVC_MAIN,
            $path,
            $jwt,
            $query,
            $body
        );

        return BackendApiClient::unwrap($response);
    }

    public static function profileByUsername(string $username): array
    {
        unset($username);

        foreach (['/profile/detail', '/profile_detail.php', '/account/profile', '/user/profile'] as $path) {
            $data = self::requestMember('GET', $path);
            if ($data === []) {
                continue;
            }

            $user = is_array($data['user'] ?? null) ? $data['user'] : $data;
            if (!is_array($user) || ($user['id'] ?? null) === null) {
                continue;
            }

            return self::normalizeUserRow($user, $data);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public static function profileSection(string $path, array $query = []): array
    {
        $path = trim($path);
        if ($path === '/profile/bet-history-pack') {
            return self::betHistoryPack($query);
        }

        $mapped = self::mapLegacyPath($path);
        if ($mapped === '/profile/withdrawals') {
            $data = self::requestMember('GET', '/history/withdrawals', $query);

            return is_array($data['withdrawals'] ?? null)
                ? ['withdrawals' => $data['withdrawals']]
                : (is_array($data['items'] ?? null) ? ['withdrawals' => $data['items']] : $data);
        }

        $data = self::requestMember('GET', $mapped, $query);
        if ($mapped === '/kyc/status' && $data !== [] && !isset($data['kyc']) && !isset($data['request'])) {
            return ['kyc' => $data, 'request' => $data];
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>|null
     */
    public static function postProfile(string $path, array $body): ?array
    {
        $jwt = self::memberJwt();
        if ($jwt === '') {
            return null;
        }

        $mapped = self::mapLegacyPath($path);

        return BackendApiClient::requestWithMemberBearer(
            'POST',
            BackendApiClient::SVC_MAIN,
            $mapped,
            $jwt,
            [],
            $body
        );
    }

    private static function mapLegacyPath(string $path): string
    {
        return match ($path) {
            '/profile/kyc-status' => '/kyc/status',
            '/profile/withdrawals' => '/history/withdrawals',
            '/profile/para-transactions' => '/wallet/transactions',
            '/profile/spor-bet-detail' => '/profile/spor-bet-detail',
            '/profile/kyc/submit' => '/kyc/documents',
            '/users/profile' => '/profile/detail',
            '/users/by-id' => '/profile/detail',
            '/users/balance' => '/account/balance',
            default => $path,
        };
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private static function betHistoryPack(array $query): array
    {
        $casino = self::requestMember('GET', '/profile/casino-game-history', $query);
        if ($casino === []) {
            $casino = self::requestMember('GET', '/casino_game_history.php', $query);
        }
        if ($casino === []) {
            $casino = self::requestMember('GET', '/game_history.php', $query);
        }

        $casinoTransactions = [];
        if (is_array($casino['transactions'] ?? null)) {
            $casinoTransactions = $casino['transactions'];
        } elseif (is_array($casino['items'] ?? null)) {
            $casinoTransactions = $casino['items'];
        }

        return [
            'casino_transactions' => is_array($casinoTransactions) ? $casinoTransactions : [],
            'spor_transactions' => [],
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $envelope
     * @return array<string, mixed>
     */
    private static function normalizeUserRow(array $user, array $envelope = []): array
    {
        if (!isset($user['ana_bakiye'])) {
            if (isset($envelope['balance']['ana_bakiye'])) {
                $user['ana_bakiye'] = $envelope['balance']['ana_bakiye'];
            } elseif (isset($envelope['balance']['balance'])) {
                $user['ana_bakiye'] = $envelope['balance']['balance'];
            } elseif (isset($user['balance'])) {
                $user['ana_bakiye'] = $user['balance'];
            }
        }

        if (!isset($user['ana_bakiye']) || (float) $user['ana_bakiye'] <= 0) {
            $balance = self::requestMember('GET', '/account/balance');
            if (isset($balance['ana_bakiye'])) {
                $user['ana_bakiye'] = $balance['ana_bakiye'];
            } elseif (isset($balance['balance']['balance'])) {
                $user['ana_bakiye'] = $balance['balance']['balance'];
            }
        }

        if (!isset($user['first_name']) && isset($user['name'])) {
            $user['first_name'] = $user['name'];
        }

        return $user;
    }
}
