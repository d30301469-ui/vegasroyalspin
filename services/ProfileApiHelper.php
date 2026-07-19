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
            require_once __DIR__ . '/../config/frontend_session.php';
            metropol_frontend_session_start();
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

        // Oturum açık olduğu halde member_jwt hiç üretilmemiş/kaybolmuş olabilir (eski login akışı,
        // süresi dolmuş token vb.) — bu durumda profil verisi backend'den hiç gelmez (first_name/surname
        // boş kalır). Internal-trust ile taze bir token alıp oturuma yazarak kendiliğinden onar.
        if (!empty($_SESSION['loggedin']) && (int) ($_SESSION['user_id'] ?? 0) > 0) {
            if (is_file(__DIR__ . '/BackendMemberApiProxy.php')) {
                require_once __DIR__ . '/BackendMemberApiProxy.php';
                if (class_exists('BackendMemberApiProxy', false)) {
                    $fresh = BackendMemberApiProxy::ensureFreshMemberJwt();
                    if ($fresh !== '') {
                        return $fresh;
                    }
                }
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

    public static function profileByUsername(string $username, bool $includeBalance = true): array
    {
        unset($username);

        // DB'ye doğrudan erişim izinliyse (split-deploy olmayan/backend host) önce onu dene:
        // dış (outbound) HTTP + JWT round trip'ine göre çok daha güvenilir — details.php'nin
        // MemberViewDataService::profileForSession() ile aynı isim/soyisim verisini garanti eder.
        $direct = self::profileViaDatabase($includeBalance);
        if ($direct !== []) {
            return $direct;
        }

        foreach (['/profile/detail', '/profile_detail.php', '/account/profile', '/user/profile'] as $path) {
            $data = self::requestMember('GET', $path);
            if ($data === []) {
                continue;
            }

            $user = is_array($data['user'] ?? null) ? $data['user'] : $data;
            if (!is_array($user) || ($user['id'] ?? null) === null) {
                continue;
            }

            return self::normalizeUserRow($user, $data, $includeBalance);
        }

        return [];
    }

    /**
     * Oturumdaki kullanıcıyı, izin verilen ortamlarda (split-deploy olmayan/local backend host)
     * doğrudan veritabanından okur — MemberViewDataService::profileForSession() ile aynı yaklaşım.
     * Böylece first_name/surname gösterimi, dış HTTP+JWT çağrısının başarısız olduğu durumlarda
     * (ör. backend servisine loopback erişimi olmayan ortamlar) bile doğru kalır.
     *
     * @return array<string, mixed>
     */
    private static function profileViaDatabase(bool $includeBalance): array
    {
        if (!function_exists('frontend_database_allowed') || !frontend_database_allowed()) {
            return [];
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0 || empty($_SESSION['loggedin'])) {
            return [];
        }

        try {
            if (!defined('ADMIN_APP_PATH')) {
                define('ADMIN_APP_PATH', (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/admin/app');
            }
            if (!class_exists('AdminDatabase', false)) {
                require_once ADMIN_APP_PATH . '/Core/AdminDatabase.php';
            }
            $stmt = AdminDatabase::pdo()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }

        if (!is_array($row) || ($row['id'] ?? null) === null) {
            return [];
        }

        // Hassas/iç alanları çıktıdan ayıkla (member_api_kernel.php::$memberUserById ile aynı liste) —
        // bu satır, oturumda önbelleklenip görünüm katmanına aktarıldığı için parola/gizli alan sızıntısını önler.
        foreach ([
            'password', 'password_hash', 'pass', 'remember_token', 'verify_token',
            'reset_token', 'reset_password_token', 'email_verify_token',
            'two_factor_secret', '2fa_secret', 'totp_secret', 'api_token', 'api_key',
            'security_pin', 'pin_code',
        ] as $sensitive) {
            unset($row[$sensitive]);
        }

        return self::normalizeUserRow($row, [], $includeBalance);
    }

    /**
     * profileByUsername() sonucunu kısa süreliğine (varsayılan 45sn) oturumda önbelleğe alır.
     * Profil modal sekmeleri arasında (bonus, kyc, mesajlar, işlem geçmişi vb.) hızlı geçiş
     * yapılırken sadece sidebar'da isim/soyisim göstermek için her sayfa yüklemesinde tekrar
     * backend'e istek atılmasını önler. Sayfa sadece kimlik doğrulaması (id) gerektiriyorsa
     * ve güncel veri şart değilse bu metodu tercih edin.
     *
     * @return array<string, mixed>
     */
    public static function profileByUsernameCached(string $username, int $ttlSeconds = 45): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            require_once __DIR__ . '/../config/frontend_session.php';
            metropol_frontend_session_start();
        }

        $cache = $_SESSION['__profile_summary_cache'] ?? null;
        if (
            is_array($cache)
            && ($cache['username'] ?? null) === $username
            && (int) ($cache['expires'] ?? 0) > time()
            && is_array($cache['data'] ?? null)
        ) {
            return $cache['data'];
        }

        // Sidebar sadece id/first_name/surname gösterir; bakiye JS tarafında ayrı ve
        // önbellekli fetchBalanceData() ile geldiği için burada balance round-trip'ine gerek yok.
        $data = self::profileByUsername($username, false);
        if ($data !== []) {
            $_SESSION['__profile_summary_cache'] = [
                'username' => $username,
                'expires' => time() + max(1, $ttlSeconds),
                'data' => $data,
            ];
        }

        return $data;
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

        $sportsLimit = max(50, min(500, (int) ($query['limit'] ?? 200)));
        $sportsQuery = [
            'limit' => $sportsLimit,
            'offset' => max(0, (int) ($query['offset'] ?? 0)),
        ];
        $sportsRaw = self::requestMember('GET', '/sportsbook/history', $sportsQuery);
        $sportsItems = [];
        if (is_array($sportsRaw['items'] ?? null)) {
            $sportsItems = $sportsRaw['items'];
        } elseif (is_array($sportsRaw['data']['items'] ?? null)) {
            $sportsItems = $sportsRaw['data']['items'];
        }

        $sportsTransactions = [];
        foreach ($sportsItems as $row) {
            if (!is_array($row)) {
                continue;
            }
            $txnType = strtolower(trim((string) ($row['txn_type'] ?? 'bet')));
            $amount  = abs((float) ($row['amount'] ?? 0));
            // status: 3 = İptal Edildi/İade (cancel/void txn), 2 = Tamamlandı (finished bet/win),
            // 1 = Aktif (still open). Cancel must win over is_finished so İADE filters (which key
            // off status===3) actually match refunded/voided sportsbook coupons.
            if ($txnType === 'cancel') {
                $status = 3;
            } elseif (!empty($row['is_finished'])) {
                $status = 2;
            } else {
                $status = 1;
            }
            $sportsTransactions[] = [
                'id'             => (string) ($row['id'] ?? ''),
                'transaction_id' => (string) ($row['txn_code'] ?? ''),
                'wager_id'       => (string) ($row['wager_id'] ?? ''),
                'game_provider'  => (string) ($row['vendor_code'] ?? 'sports-betby'),
                'game_code'      => (string) ($row['game_code'] ?? 'sports'),
                'txn_type'       => $txnType,
                'status'         => $status,
                'bet_amount'     => $txnType === 'bet' ? $amount : 0,
                'get_amount'     => in_array($txnType, ['win', 'cancel'], true) ? $amount : 0,
                'amount'         => $amount,
                'created_at'     => (string) ($row['created_at'] ?? ''),
            ];
        }

        return [
            'casino_transactions' => is_array($casinoTransactions) ? $casinoTransactions : [],
            'spor_transactions' => $sportsTransactions,
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $envelope
     * @return array<string, mixed>
     */
    private static function normalizeUserRow(array $user, array $envelope = [], bool $includeBalance = true): array
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

        if ($includeBalance && (!isset($user['ana_bakiye']) || (float) $user['ana_bakiye'] <= 0)) {
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
