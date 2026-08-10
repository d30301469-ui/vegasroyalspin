<?php

declare(strict_types=1);

/**
 * Telegram WebApp initData doğrulama + üye bağlama / JWT için kullanıcı çözümleme.
 *
 * @see https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
 */
final class TelegramAuthService
{
    public const MAX_AUTH_AGE_SECONDS = 86400;

    public static function ensureTable(PDO $pdo): void
    {
        static $ready = [];
        $key = (string) spl_object_id($pdo);
        if (!empty($ready[$key])) {
            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS user_telegram_links (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                telegram_id BIGINT NOT NULL,
                telegram_username VARCHAR(64) NULL,
                first_name VARCHAR(120) NULL,
                last_name VARCHAR(120) NULL,
                language_code VARCHAR(16) NULL,
                photo_url VARCHAR(500) NULL,
                linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_auth_at DATETIME NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_telegram_id (telegram_id),
                UNIQUE KEY uniq_telegram_user (user_id),
                KEY idx_telegram_username (telegram_username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $ready[$key] = true;
    }

    /**
     * @return array{ok:bool,user?:array<string,mixed>,auth_date?:int,error?:string}
     */
    public static function validateInitData(string $initData, ?string $botToken = null): array
    {
        $initData = trim($initData);
        if ($initData === '') {
            return ['ok' => false, 'error' => 'initData boş.'];
        }

        $botToken = trim((string) ($botToken ?? ''));
        if ($botToken === '') {
            if (!class_exists('TelegramBotService', false)) {
                require_once __DIR__ . '/TelegramBotService.php';
            }
            $botToken = TelegramBotService::token();
        }
        if ($botToken === '') {
            return ['ok' => false, 'error' => 'TELEGRAM_BOT_TOKEN tanımlı değil.'];
        }

        $pairs = [];
        parse_str($initData, $pairs);
        if (!is_array($pairs) || $pairs === []) {
            return ['ok' => false, 'error' => 'initData parse edilemedi.'];
        }

        $hash = trim((string) ($pairs['hash'] ?? ''));
        if ($hash === '' || !ctype_xdigit($hash)) {
            return ['ok' => false, 'error' => 'hash eksik veya geçersiz.'];
        }
        unset($pairs['hash']);

        // Telegram docs: sort keys alphabetically, join as key=value with \n
        ksort($pairs);
        $lines = [];
        foreach ($pairs as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $lines[] = $key . '=' . (string) $value;
        }
        $dataCheckString = implode("\n", $lines);
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $calculated = hash_hmac('sha256', $dataCheckString, $secretKey);
        if (!hash_equals($calculated, $hash)) {
            return ['ok' => false, 'error' => 'initData imzası geçersiz.'];
        }

        $authDate = (int) ($pairs['auth_date'] ?? 0);
        if ($authDate <= 0) {
            return ['ok' => false, 'error' => 'auth_date eksik.'];
        }
        if ((time() - $authDate) > self::MAX_AUTH_AGE_SECONDS) {
            return ['ok' => false, 'error' => 'initData süresi dolmuş.'];
        }

        $userRaw = (string) ($pairs['user'] ?? '');
        $user = json_decode($userRaw, true);
        if (!is_array($user) || (int) ($user['id'] ?? 0) <= 0) {
            return ['ok' => false, 'error' => 'Telegram user bilgisi yok.'];
        }

        return [
            'ok' => true,
            'user' => $user,
            'auth_date' => $authDate,
        ];
    }

    /**
     * Telegram kullanıcısını mevcut üyeye bağlar veya yeni üye oluşturur.
     *
     * @param array<string, mixed> $tgUser
     * @return array{user: array<string,mixed>, created: bool, linked: bool}
     */
    public static function findOrCreateMember(PDO $pdo, array $tgUser): array
    {
        self::ensureTable($pdo);

        $telegramId = (int) ($tgUser['id'] ?? 0);
        if ($telegramId <= 0) {
            throw new InvalidArgumentException('Geçersiz telegram_id.');
        }

        $username = trim((string) ($tgUser['username'] ?? ''));
        $firstName = trim((string) ($tgUser['first_name'] ?? ''));
        $lastName = trim((string) ($tgUser['last_name'] ?? ''));
        $language = trim((string) ($tgUser['language_code'] ?? ''));
        $photo = trim((string) ($tgUser['photo_url'] ?? ''));

        $linkStmt = $pdo->prepare(
            'SELECT l.user_id, u.id, u.username, u.email, u.name, u.surname, u.balance, u.bonus_balance, u.banned
             FROM user_telegram_links l
             INNER JOIN users u ON u.id = l.user_id
             WHERE l.telegram_id = :tid
             LIMIT 1'
        );
        $linkStmt->execute([':tid' => $telegramId]);
        $existing = $linkStmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($existing)) {
            if ((int) ($existing['banned'] ?? 0) === 1) {
                throw new RuntimeException('Hesabınız banlanmıştır. Giriş yapamazsınız.');
            }
            $pdo->prepare(
                'UPDATE user_telegram_links
                 SET telegram_username = :uname,
                     first_name = :fn,
                     last_name = :ln,
                     language_code = :lang,
                     photo_url = :photo,
                     last_auth_at = NOW()
                 WHERE telegram_id = :tid'
            )->execute([
                ':uname' => $username !== '' ? $username : null,
                ':fn' => $firstName !== '' ? $firstName : null,
                ':ln' => $lastName !== '' ? $lastName : null,
                ':lang' => $language !== '' ? $language : null,
                ':photo' => $photo !== '' ? $photo : null,
                ':tid' => $telegramId,
            ]);

            return [
                'user' => [
                    'id' => (int) $existing['id'],
                    'username' => (string) $existing['username'],
                    'email' => (string) $existing['email'],
                    'name' => (string) ($existing['name'] ?? ''),
                    'surname' => (string) ($existing['surname'] ?? ''),
                    'balance' => (float) ($existing['balance'] ?? 0),
                    'bonus_balance' => (float) ($existing['bonus_balance'] ?? 0),
                ],
                'created' => false,
                'linked' => true,
            ];
        }

        $loginName = self::allocateUsername($pdo, $telegramId, $username);
        $email = 'tg' . $telegramId . '@telegram.local';
        $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $displayFirst = $firstName !== '' ? $firstName : 'Telegram';
        $displayLast = $lastName !== '' ? $lastName : 'User';
        // Live users schema requires identity/gender/dob/phone/city (NO DEFAULT).
        $identity = '9' . str_pad((string) ($telegramId % 10000000000), 10, '0', STR_PAD_LEFT);
        $phone = '5' . str_pad((string) ($telegramId % 1000000000), 9, '0', STR_PAD_LEFT);

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                'INSERT INTO users
                    (name, surname, username, email, identity_number, gender, dob, phone, city, country, password, is_verified, created_at, updated_at)
                 VALUES
                    (:name, :surname, :username, :email, :identity_number, :gender, :dob, :phone, :city, :country, :password, 1, NOW(), NOW())'
            );
            $ins->execute([
                ':name' => mb_substr($displayFirst, 0, 50),
                ':surname' => mb_substr($displayLast, 0, 50),
                ':username' => $loginName,
                ':email' => $email,
                ':identity_number' => $identity,
                ':gender' => 'Diğer',
                ':dob' => '1990-01-01',
                ':phone' => $phone,
                ':city' => 'Telegram',
                ':country' => 'TR',
                ':password' => $passwordHash,
            ]);
            $userId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO user_telegram_links
                    (user_id, telegram_id, telegram_username, first_name, last_name, language_code, photo_url, linked_at, last_auth_at)
                 VALUES
                    (:uid, :tid, :uname, :fn, :ln, :lang, :photo, NOW(), NOW())'
            )->execute([
                ':uid' => $userId,
                ':tid' => $telegramId,
                ':uname' => $username !== '' ? $username : null,
                ':fn' => $firstName !== '' ? $firstName : null,
                ':ln' => $lastName !== '' ? $lastName : null,
                ':lang' => $language !== '' ? $language : null,
                ':photo' => $photo !== '' ? $photo : null,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'user' => [
                'id' => $userId,
                'username' => $loginName,
                'email' => $email,
                'name' => $displayFirst,
                'surname' => $displayLast,
                'balance' => 0.0,
                'bonus_balance' => 0.0,
            ],
            'created' => true,
            'linked' => true,
        ];
    }

    private static function allocateUsername(PDO $pdo, int $telegramId, string $tgUsername): string
    {
        $candidates = [];
        $clean = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $tgUsername) ?? '');
        if ($clean !== '' && strlen($clean) >= 3) {
            $candidates[] = 'tg_' . substr($clean, 0, 40);
        }
        $candidates[] = 'tg_' . $telegramId;
        $candidates[] = 'tg' . $telegramId;

        foreach ($candidates as $base) {
            $base = substr($base, 0, 50);
            $try = $base;
            for ($i = 0; $i < 20; $i++) {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
                $stmt->execute([':u' => $try]);
                if ($stmt->fetchColumn() === false) {
                    return $try;
                }
                $try = substr($base, 0, 45) . '_' . ($i + 1);
            }
        }

        return 'tg_' . $telegramId . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
    }
}
