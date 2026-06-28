<?php

declare(strict_types=1);

final class MemberAccountService
{
    public static function ensureTables(PDO $pdo): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $migration = dirname(__DIR__) . '/database/migrations/2026_06_11_000000_create_user_member_settings.php';
        if (is_readable($migration)) {
            $runner = require $migration;
            if (is_callable($runner)) {
                $runner($pdo);
            }
        }
        $ready = true;
    }

    /**
     * @return array{preferences: array<string, mixed>, limits: array<string, mixed>}
     */
    public static function settings(PDO $pdo, int $userId): array
    {
        self::ensureTables($pdo);
        $stmt = $pdo->prepare('SELECT preferences_json, limits_json FROM user_member_settings WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'preferences' => self::decodeJson($row['preferences_json'] ?? null, self::defaultPreferences()),
            'limits' => self::decodeJson($row['limits_json'] ?? null, self::defaultLimits()),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function updatePreferences(PDO $pdo, int $userId, array $input): array
    {
        $current = self::settings($pdo, $userId);
        $prefs = $current['preferences'];
        foreach (['language', 'timezone', 'email_notifications', 'sms_notifications', 'push_notifications'] as $key) {
            if (array_key_exists($key, $input)) {
                $prefs[$key] = $input[$key];
            }
        }
        self::saveJson($pdo, $userId, 'preferences_json', $prefs);

        return $prefs;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function updateLimits(PDO $pdo, int $userId, array $input): array
    {
        $current = self::settings($pdo, $userId);
        $limits = $current['limits'];
        foreach ([
            'daily_deposit_limit', 'weekly_deposit_limit', 'daily_loss_limit',
            'session_time_limit_minutes', 'cool_off_until', 'self_exclusion_until',
        ] as $key) {
            if (array_key_exists($key, $input)) {
                $limits[$key] = $input[$key];
            }
        }
        self::saveJson($pdo, $userId, 'limits_json', $limits);

        return $limits;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function securitySessions(PDO $pdo, int $userId): array
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT id, issued_at, expires_at, revoked_at, last_seen_at, ip_address, user_agent
                 FROM member_jwt_tokens WHERE user_id = :user_id ORDER BY id DESC LIMIT 50'
            );
            $stmt->execute(['user_id' => $userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultPreferences(): array
    {
        return [
            'language' => 'tr',
            'timezone' => 'Europe/Istanbul',
            'email_notifications' => true,
            'sms_notifications' => false,
            'push_notifications' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultLimits(): array
    {
        return [
            'daily_deposit_limit' => null,
            'weekly_deposit_limit' => null,
            'daily_loss_limit' => null,
            'session_time_limit_minutes' => null,
            'cool_off_until' => null,
            'self_exclusion_until' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeJson(mixed $value, array $default): array
    {
        if (!is_string($value) || trim($value) === '') {
            return $default;
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_replace($default, $decoded) : $default;
    }

    private static function saveJson(PDO $pdo, int $userId, string $column, array $data): void
    {
        self::ensureTables($pdo);
        if (!in_array($column, ['preferences_json', 'limits_json'], true)) {
            throw new InvalidArgumentException('Invalid settings column.');
        }
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE);
        $stmt = $pdo->prepare(
            "INSERT INTO user_member_settings (user_id, {$column}) VALUES (:user_id, :json)
             ON DUPLICATE KEY UPDATE {$column} = VALUES({$column}), updated_at = NOW()"
        );
        $stmt->execute(['user_id' => $userId, 'json' => $encoded]);
    }
}
