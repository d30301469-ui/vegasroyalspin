<?php

declare(strict_types=1);

final class MemberJwtService
{
    public const TTL = 2592000;

    public static function secret(): string
    {
        $fromEnv = self::env('MEMBER_JWT_SECRET');
        if ($fromEnv !== '') {
            if (self::isProduction() && (strlen($fromEnv) < 32 || self::isPlaceholderSecret($fromEnv))) {
                throw new RuntimeException('MEMBER_JWT_SECRET must be a strong non-placeholder value in production.');
            }
            return $fromEnv;
        }

        if (self::isProduction()) {
            throw new RuntimeException('MEMBER_JWT_SECRET must be configured in production.');
        }

        $seed = self::env('APP_KEY') ?: self::env('APP_SECRET');
        if ($seed === '' && defined('SITE_URL')) {
            $seed = (string) SITE_URL;
        }
        if ($seed === '' && defined('BASE_PATH')) {
            $seed = (string) BASE_PATH;
        }
        if ($seed === '') {
            throw new RuntimeException('MEMBER_JWT_SECRET veya APP_KEY ortam değişkeni tanımlanmalıdır.');
        }

        return hash('sha256', $seed . '|maltabet-v2-member-jwt');
    }

    public static function ensureTable(PDO $pdo): void
    {
        static $ready = [];

        $key = (string) spl_object_id($pdo);
        if (!empty($ready[$key])) {
            return;
        }

        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS member_jwt_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    jti CHAR(32) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    issued_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    last_seen_at DATETIME NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_member_jwt_jti (jti),
    KEY idx_member_jwt_user_id (user_id),
    KEY idx_member_jwt_token_hash (token_hash),
    KEY idx_member_jwt_active (revoked_at, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        try {
            $pdo->exec($sql);
            $exists = $pdo->query("SHOW TABLES LIKE 'member_jwt_tokens'")->fetchColumn();
            if ($exists === false) {
                throw new RuntimeException('member_jwt_tokens tablosu oluşturulamadı.');
            }
            $ready[$key] = true;
        } catch (Throwable $e) {
            error_log('[MemberJwtService] ensureTable failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $user
     */
    public static function issue(PDO $pdo, array $user, int $ttl = self::TTL): string
    {
        $uid = (int) ($user['id'] ?? 0);
        $uname = (string) ($user['username'] ?? '');
        $email = (string) ($user['email'] ?? '');
        $now = time();
        $exp = $now + max(300, $ttl);
        $jti = bin2hex(random_bytes(16));

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = [
            'iss' => 'maltabet-api-v2',
            'sub' => (string) $uid,
            'uid' => $uid,
            'username' => $uname,
            'email' => $email,
            'iat' => $now,
            'exp' => $exp,
            'jti' => $jti,
        ];

        $segments = [
            self::b64Enc(json_encode($header, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            self::b64Enc(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ];
        $signingInput = implode('.', $segments);
        $segments[] = self::b64Enc(hash_hmac('sha256', $signingInput, self::secret(), true));
        $jwt = implode('.', $segments);

        self::ensureTable($pdo);
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO member_jwt_tokens
                (jti, user_id, token_hash, issued_at, expires_at, ip_address, user_agent)
                VALUES
                (:jti, :user_id, :token_hash, NOW(), :expires_at, :ip_address, :user_agent)'
            );
            $stmt->execute([
                'jti' => $jti,
                'user_id' => $uid,
                'token_hash' => hash('sha256', $jwt),
                'expires_at' => date('Y-m-d H:i:s', $exp),
                'ip_address' => substr(
                    function_exists('metropol_cloudflare_client_ip')
                        ? metropol_cloudflare_client_ip()
                        : (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                    0,
                    64
                ),
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
        } catch (Throwable $e) {
            error_log('[MemberJwtService] token persist failed: ' . $e->getMessage());
            throw new RuntimeException('member_jwt_tokens kaydı başarısız: ' . $e->getMessage(), 0, $e);
        }

        if (!self::isLocallyTracked($pdo, $jwt)) {
            throw new RuntimeException('member_jwt_tokens kaydı doğrulanamadı (INSERT sonrası satır yok).');
        }

        return $jwt;
    }

    /**
     * Issues a JWT for an already logged-in local session that has no token yet.
     */
    public static function ensureSessionToken(PDO $pdo): string
    {
        if (!empty($_SESSION['member_jwt'])) {
            $current = (string) $_SESSION['member_jwt'];
            if (self::isLocallyTracked($pdo, $current) && self::hasValidSignature($current)) {
                return $current;
            }
        }
        if (empty($_SESSION['loggedin']) || (int) ($_SESSION['user_id'] ?? 0) <= 0) {
            return '';
        }

        $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($user)) {
            return '';
        }

        $jwt = self::issue($pdo, $user);
        $_SESSION['member_jwt'] = $jwt;
        return $jwt;
    }

    public static function isLocallyTracked(PDO $pdo, string $jwt): bool
    {
        if (trim($jwt) === '') {
            return false;
        }

        try {
            self::ensureTable($pdo);
            $stmt = $pdo->prepare(
                'SELECT id FROM member_jwt_tokens
                 WHERE token_hash = :token_hash
                   AND revoked_at IS NULL
                   AND expires_at >= NOW()
                 LIMIT 1'
            );
            $stmt->execute(['token_hash' => hash('sha256', $jwt)]);
        } catch (Throwable) {
            return false;
        }

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Geçerli imzalı JWT DB'de yoksa kaydı oluşturur (kısmi INSERT / migration sonrası self-heal).
     *
     * @param array<string, mixed> $payload
     */
    public static function backfillTrackedToken(PDO $pdo, string $jwt, array $payload): bool
    {
        if (trim($jwt) === '' || !self::hasValidSignature($jwt)) {
            return false;
        }

        $uid = (int) ($payload['uid'] ?? $payload['sub'] ?? 0);
        $jti = trim((string) ($payload['jti'] ?? ''));
        $exp = (int) ($payload['exp'] ?? 0);
        if ($uid <= 0 || $jti === '' || $exp < time()) {
            return false;
        }

        if (self::isLocallyTracked($pdo, $jwt)) {
            return true;
        }

        self::ensureTable($pdo);
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO member_jwt_tokens
                (jti, user_id, token_hash, issued_at, expires_at, ip_address, user_agent)
                VALUES
                (:jti, :user_id, :token_hash, FROM_UNIXTIME(:issued_at), :expires_at, :ip_address, :user_agent)
                ON DUPLICATE KEY UPDATE
                    token_hash = VALUES(token_hash),
                    expires_at = VALUES(expires_at),
                    revoked_at = NULL'
            );
            $stmt->execute([
                'jti' => $jti,
                'user_id' => $uid,
                'token_hash' => hash('sha256', $jwt),
                'issued_at' => max(0, (int) ($payload['iat'] ?? time())),
                'expires_at' => date('Y-m-d H:i:s', $exp),
                'ip_address' => substr(
                    function_exists('metropol_cloudflare_client_ip')
                        ? metropol_cloudflare_client_ip()
                        : (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                    0,
                    64
                ),
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
        } catch (Throwable $e) {
            error_log('[MemberJwtService] backfillTrackedToken failed: ' . $e->getMessage());

            return false;
        }

        return self::isLocallyTracked($pdo, $jwt);
    }

    private static function b64Enc(string|false $raw): string
    {
        return rtrim(strtr(base64_encode(is_string($raw) ? $raw : ''), '+/', '-_'), '=');
    }

    private static function b64Dec(string $raw): ?string
    {
        $padLen = 4 - (strlen($raw) % 4);
        if ($padLen > 0 && $padLen < 4) {
            $raw .= str_repeat('=', $padLen);
        }
        $decoded = base64_decode(strtr($raw, '-_', '+/'), true);
        return is_string($decoded) ? $decoded : null;
    }

    public static function signatureValid(string $jwt): bool
    {
        return self::hasValidSignature($jwt);
    }

    private static function hasValidSignature(string $jwt): bool
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return false;
        }
        [$header, $payload, $signature] = $parts;
        $rawSignature = self::b64Dec($signature);
        if (!is_string($rawSignature)) {
            return false;
        }
        try {
            $expected = hash_hmac('sha256', $header . '.' . $payload, self::secret(), true);
        } catch (Throwable) {
            return false;
        }
        return hash_equals($expected, $rawSignature);
    }

    private static function env(string $key): string
    {
        $value = getenv($key);
        return $value === false ? '' : trim((string) $value);
    }

    private static function isProduction(): bool
    {
        return in_array(strtolower(self::env('APP_ENV')), ['production', 'prod'], true);
    }

    private static function runtimeMigrationsAllowed(): bool
    {
        if (self::isProduction()) {
            return false;
        }

        $override = self::env('ALLOW_RUNTIME_MIGRATIONS');
        if ($override !== '') {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }

        return true;
    }

    private static function isPlaceholderSecret(string $value): bool
    {
        $normalized = strtolower(trim($value));
        foreach (['change-me', 'changeme', 'example', 'placeholder', 'default'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }
}
