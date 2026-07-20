<?php

declare(strict_types=1);

final class ApiLoyalty
{
    private const BRONZE_ICON_URL = '/assets/images/loyalty/badges/bronze.png';
    private const LOCAL_ICON_URLS = [
        'bronze' => self::BRONZE_ICON_URL,
        'silver' => '/assets/images/loyalty/badges/silver.svg',
        'gold' => '/assets/images/loyalty/badges/gold.svg',
        'platinum' => '/assets/images/loyalty/badges/platinum.svg',
        'diamond' => '/assets/images/loyalty/badges/diamond.svg',
    ];

    public static function fetchForUser(int $userId): array
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Geçerli kullanıcı bulunamadı.');
        }

        if (function_exists('frontend_database_allowed') && !frontend_database_allowed()) {
            throw new RuntimeException('Loyalty data must be loaded via backend API on this host.');
        }

        $pdo = self::pdo();
        self::ensureStorage($pdo);

        $account = self::accountForUser($pdo, $userId);
        $levels = self::levels($pdo);
        $currentLevel = self::levelForPoints($levels, (int) ($account['points'] ?? 0));
        $nextLevel = self::nextLevel($levels, (int) ($account['points'] ?? 0));
        if ((string) ($account['level_code'] ?? '') !== (string) ($currentLevel['code'] ?? 'bronze')) {
            $account['level_code'] = (string) ($currentLevel['code'] ?? 'bronze');
            $stmt = $pdo->prepare('UPDATE user_loyalty_accounts SET level_code = :level_code WHERE user_id = :user_id');
            $stmt->execute(['level_code' => $account['level_code'], 'user_id' => $userId]);
        }

        $progress = self::progress((int) ($account['points'] ?? 0), $currentLevel, $nextLevel);

        return [
            'account' => [
                'user_id' => $userId,
                'username' => (string) ($account['username'] ?? ''),
                'level_code' => (string) ($account['level_code'] ?? 'bronze'),
                'points' => (int) ($account['points'] ?? 0),
                'lifetime_points' => (int) ($account['lifetime_points'] ?? 0),
                'redeemable_points' => (int) ($account['redeemable_points'] ?? 0),
                'last_activity_at' => (string) ($account['last_activity_at'] ?? ''),
            ],
            'level' => self::publicLevel($currentLevel),
            'next_level' => $nextLevel !== null ? self::publicLevel($nextLevel) : null,
            'progress' => $progress,
            'levels' => array_map([self::class, 'publicLevel'], $levels),
        ];
    }

    public static function publicBadgeForUser(int $userId): array
    {
        if (function_exists('frontend_database_allowed') && !frontend_database_allowed()) {
            return self::publicBadgeViaApi();
        }

        try {
            $loyalty = self::fetchForUser($userId);
            $level = is_array($loyalty['level'] ?? null) ? $loyalty['level'] : self::fallbackLevel();

            return [
                'name' => (string) ($level['name'] ?? 'Bronze'),
                'code' => (string) ($level['code'] ?? 'bronze'),
                'icon_url' => self::normalizedIconUrl((string) ($level['code'] ?? 'bronze'), (string) ($level['icon_url'] ?? self::BRONZE_ICON_URL)),
                'initial' => strtoupper(substr((string) ($level['name'] ?? 'Bronze'), 0, 1)),
                'points' => (int) ($loyalty['account']['points'] ?? 0),
                'redeemable_points' => (int) ($loyalty['account']['redeemable_points'] ?? 0),
                'progress_percent' => (int) ($loyalty['progress']['percent'] ?? 0),
            ];
        } catch (Throwable) {
            return self::fallbackBadge();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function publicBadgeViaApi(): array
    {
        $jwt = trim((string) ($_SESSION['member_jwt'] ?? ''));
        if ($jwt === '') {
            return self::fallbackBadge();
        }

        try {
            $response = BackendApiClient::requestWithMemberBearer(
                'GET',
                BackendApiClient::SVC_MAIN,
                'loyalty.php',
                $jwt
            );
            $data = BackendApiClient::unwrap($response);
            if (isset($data['badge']) && is_array($data['badge'])) {
                return $data['badge'] + self::fallbackBadge();
            }

            $level = is_array($data['level'] ?? null) ? $data['level'] : self::fallbackLevel();

            return [
                'name' => (string) ($level['name'] ?? 'Bronze'),
                'code' => (string) ($level['code'] ?? 'bronze'),
                'icon_url' => self::normalizedIconUrl((string) ($level['code'] ?? 'bronze'), (string) ($level['icon_url'] ?? self::BRONZE_ICON_URL)),
                'initial' => strtoupper(substr((string) ($level['name'] ?? 'Bronze'), 0, 1)),
                'points' => (int) ($data['account']['points'] ?? 0),
                'redeemable_points' => (int) ($data['account']['redeemable_points'] ?? 0),
                'progress_percent' => (int) ($data['progress']['percent'] ?? 0),
            ];
        } catch (Throwable) {
            return self::fallbackBadge();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function fallbackBadge(): array
    {
        return [
            'name' => 'Bronze',
            'code' => 'bronze',
            'icon_url' => '/content/images/loyalty_points/bronze.png',
            'initial' => 'B',
            'points' => 0,
            'redeemable_points' => 0,
            'progress_percent' => 0,
        ];
    }

    public static function ensureStorage(PDO $pdo): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS loyalty_levels (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                code VARCHAR(40) NOT NULL,
                name VARCHAR(120) NOT NULL,
                min_points INT NOT NULL DEFAULT 0,
                cashback_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                weekly_bonus_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                icon_url VARCHAR(500) NULL,
                color_hex VARCHAR(20) NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_loyalty_levels_code (code),
                KEY idx_loyalty_levels_active_sort (is_active, sort_order),
                KEY idx_loyalty_levels_points (min_points)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS user_loyalty_accounts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT NOT NULL,
                username VARCHAR(120) NULL,
                level_code VARCHAR(40) NOT NULL DEFAULT 'bronze',
                points INT NOT NULL DEFAULT 0,
                lifetime_points INT NOT NULL DEFAULT 0,
                redeemable_points INT NOT NULL DEFAULT 0,
                last_activity_at DATETIME NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_user_loyalty_accounts_user (user_id),
                KEY idx_user_loyalty_accounts_level (level_code),
                KEY idx_user_loyalty_accounts_points (points)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS loyalty_point_transactions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT NOT NULL,
                username VARCHAR(120) NULL,
                type ENUM('earn','redeem','adjust','expire') NOT NULL DEFAULT 'earn',
                points INT NOT NULL DEFAULT 0,
                source VARCHAR(120) NULL,
                reference_id VARCHAR(120) NULL,
                note VARCHAR(500) NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_loyalty_point_transactions_user (user_id, created_at),
                KEY idx_loyalty_point_transactions_type (type),
                KEY idx_loyalty_point_transactions_source (source)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $count = (int) $pdo->query('SELECT COUNT(*) FROM loyalty_levels')->fetchColumn();
        if ($count === 0) {
            $insert = $pdo->prepare(
                'INSERT INTO loyalty_levels
                    (code, name, min_points, cashback_rate, weekly_bonus_amount, icon_url, color_hex, sort_order, is_active)
                 VALUES
                    (:code, :name, :min_points, :cashback_rate, :weekly_bonus_amount, :icon_url, :color_hex, :sort_order, 1)'
            );
            foreach (self::defaultLevels() as $level) {
                $insert->execute($level);
            }
        }

        $ready = true;
    }

    private static function accountForUser(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $username = is_array($user) ? (string) ($user['username'] ?? '') : '';

        $stmt = $pdo->prepare('SELECT * FROM user_loyalty_accounts WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($account)) {
            if ($username !== '' && (string) ($account['username'] ?? '') !== $username) {
                $update = $pdo->prepare('UPDATE user_loyalty_accounts SET username = :username WHERE user_id = :user_id');
                $update->execute(['username' => $username, 'user_id' => $userId]);
                $account['username'] = $username;
            }

            return $account;
        }

        $insert = $pdo->prepare(
            "INSERT INTO user_loyalty_accounts
                (user_id, username, level_code, points, lifetime_points, redeemable_points, last_activity_at)
             VALUES
                (:user_id, :username, 'bronze', 0, 0, 0, NULL)"
        );
        $insert->execute(['user_id' => $userId, 'username' => $username !== '' ? $username : null]);

        return [
            'user_id' => $userId,
            'username' => $username,
            'level_code' => 'bronze',
            'points' => 0,
            'lifetime_points' => 0,
            'redeemable_points' => 0,
            'last_activity_at' => '',
        ];
    }

    private static function levels(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT code, name, min_points, cashback_rate, weekly_bonus_amount, icon_url, color_hex, sort_order
             FROM loyalty_levels
             WHERE is_active = 1
             ORDER BY min_points ASC, sort_order ASC, id ASC'
        );
        $levels = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return $levels !== [] ? $levels : [self::fallbackLevel()];
    }

    private static function levelForPoints(array $levels, int $points): array
    {
        $current = $levels[0] ?? self::fallbackLevel();
        foreach ($levels as $level) {
            if ($points >= (int) ($level['min_points'] ?? 0)) {
                $current = $level;
            }
        }

        return $current;
    }

    private static function nextLevel(array $levels, int $points): ?array
    {
        foreach ($levels as $level) {
            if ((int) ($level['min_points'] ?? 0) > $points) {
                return $level;
            }
        }

        return null;
    }

    private static function progress(int $points, array $currentLevel, ?array $nextLevel): array
    {
        $currentMin = (int) ($currentLevel['min_points'] ?? 0);
        if ($nextLevel === null) {
            return [
                'percent' => 100,
                'current_points' => $points,
                'current_level_min_points' => $currentMin,
                'next_level_min_points' => null,
                'points_to_next_level' => 0,
            ];
        }

        $nextMin = max($currentMin + 1, (int) ($nextLevel['min_points'] ?? 0));
        $span = max(1, $nextMin - $currentMin);
        $earned = max(0, $points - $currentMin);

        return [
            'percent' => min(100, max(0, (int) floor(($earned / $span) * 100))),
            'current_points' => $points,
            'current_level_min_points' => $currentMin,
            'next_level_min_points' => $nextMin,
            'points_to_next_level' => max(0, $nextMin - $points),
        ];
    }

    private static function publicLevel(array $level): array
    {
        $code = (string) ($level['code'] ?? 'bronze');
        return [
            'code' => $code,
            'name' => (string) ($level['name'] ?? 'Bronze'),
            'min_points' => (int) ($level['min_points'] ?? 0),
            'cashback_rate' => (float) ($level['cashback_rate'] ?? 0),
            'weekly_bonus_amount' => (float) ($level['weekly_bonus_amount'] ?? 0),
            'icon_url' => self::normalizedIconUrl($code, (string) ($level['icon_url'] ?? self::BRONZE_ICON_URL)),
            'color_hex' => (string) ($level['color_hex'] ?? '#b7791f'),
            'sort_order' => (int) ($level['sort_order'] ?? 0),
        ];
    }

    private static function fallbackLevel(): array
    {
        return [
            'code' => 'bronze',
            'name' => 'Bronze',
            'min_points' => 0,
            'cashback_rate' => 0.0,
            'weekly_bonus_amount' => 0.0,
            'icon_url' => self::BRONZE_ICON_URL,
            'color_hex' => '#b7791f',
            'sort_order' => 10,
        ];
    }

    private static function normalizedIconUrl(string $code, string $iconUrl): string
    {
        $code = strtolower(trim($code));
        if (isset(self::LOCAL_ICON_URLS[$code])) {
            return self::LOCAL_ICON_URLS[$code];
        }

        foreach (self::LOCAL_ICON_URLS as $levelCode => $localIconUrl) {
            if (str_contains(strtolower($iconUrl), $levelCode)) {
                return $localIconUrl;
            }
        }

        return self::BRONZE_ICON_URL;
    }

    private static function defaultLevels(): array
    {
        return [
            ['code' => 'bronze', 'name' => 'Bronze', 'min_points' => 0, 'cashback_rate' => 0.00, 'weekly_bonus_amount' => 0.00, 'icon_url' => self::BRONZE_ICON_URL, 'color_hex' => '#b7791f', 'sort_order' => 10],
            ['code' => 'silver', 'name' => 'Silver', 'min_points' => 1000, 'cashback_rate' => 1.00, 'weekly_bonus_amount' => 100.00, 'icon_url' => '/content/images/loyalty_points/silver.png', 'color_hex' => '#94a3b8', 'sort_order' => 20],
            ['code' => 'gold', 'name' => 'Gold', 'min_points' => 5000, 'cashback_rate' => 2.00, 'weekly_bonus_amount' => 250.00, 'icon_url' => '/content/images/loyalty_points/gold.png', 'color_hex' => '#f59e0b', 'sort_order' => 30],
            ['code' => 'platinum', 'name' => 'Platinum', 'min_points' => 15000, 'cashback_rate' => 3.00, 'weekly_bonus_amount' => 500.00, 'icon_url' => '/content/images/loyalty_points/platinum.png', 'color_hex' => '#60a5fa', 'sort_order' => 40],
            ['code' => 'diamond', 'name' => 'Diamond', 'min_points' => 50000, 'cashback_rate' => 5.00, 'weekly_bonus_amount' => 1000.00, 'icon_url' => '/content/images/loyalty_points/diamond.png', 'color_hex' => '#a78bfa', 'sort_order' => 50],
        ];
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
