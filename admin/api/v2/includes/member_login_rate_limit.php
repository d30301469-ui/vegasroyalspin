<?php

declare(strict_types=1);

/**
 * Login brute-force koruması — IP + kullanıcı adı bazlı.
 */
if (!function_exists('memberLoginRateLimitEnsureTable')) {
    function memberLoginRateLimitEnsureTable(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if (function_exists('frontend_runtime_migrations_allowed') && !frontend_runtime_migrations_allowed()) {
            return;
        }
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS login_rate_limits (
                    rate_key VARCHAR(190) NOT NULL PRIMARY KEY,
                    attempts INT UNSIGNED NOT NULL DEFAULT 0,
                    window_started_at DATETIME NOT NULL,
                    blocked_until DATETIME NULL,
                    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable $e) {
            error_log('[login_rate_limit] ensure table: ' . $e->getMessage());
        }
    }
}

if (!function_exists('memberLoginRateLimitClientIp')) {
    function memberLoginRateLimitClientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            $raw = trim((string) ($_SERVER[$key] ?? ''));
            if ($raw === '') {
                continue;
            }
            if (str_contains($raw, ',')) {
                $raw = trim(explode(',', $raw, 2)[0]);
            }
            if (filter_var($raw, FILTER_VALIDATE_IP) !== false) {
                return $raw;
            }
        }

        return '0.0.0.0';
    }
}

if (!function_exists('memberLoginRateLimitKeys')) {
    /** @return array{ip:string,login:string} */
    function memberLoginRateLimitKeys(string $login): array
    {
        $loginNorm = strtolower(trim($login));
        if ($loginNorm === '') {
            $loginNorm = '_empty_';
        }

        return [
            'ip' => 'ip:' . memberLoginRateLimitClientIp(),
            'login' => 'login:' . substr(hash('sha256', $loginNorm), 0, 40),
        ];
    }
}

if (!function_exists('memberLoginRateLimitWindowMinutes')) {
    function memberLoginRateLimitWindowMinutes(): int
    {
        $v = (int) (getenv('LOGIN_RATE_LIMIT_WINDOW_MIN') ?: 15);

        return max(5, min(60, $v));
    }
}

if (!function_exists('memberLoginRateLimitMaxAttempts')) {
    function memberLoginRateLimitMaxAttempts(): int
    {
        $v = (int) (getenv('LOGIN_RATE_LIMIT_MAX') ?: 8);

        return max(3, min(30, $v));
    }
}

if (!function_exists('memberLoginRateLimitBlockMinutes')) {
    function memberLoginRateLimitBlockMinutes(): int
    {
        $v = (int) (getenv('LOGIN_RATE_LIMIT_BLOCK_MIN') ?: 15);

        return max(5, min(120, $v));
    }
}

if (!function_exists('memberLoginRateLimitCheck')) {
    /**
     * @return array{allowed:bool,message:string,retryAfterSec:int}
     */
    function memberLoginRateLimitCheck(PDO $pdo, string $login): array
    {
        memberLoginRateLimitEnsureTable($pdo);
        $keys = memberLoginRateLimitKeys($login);
        $now = time();
        $blockedMessage = 'Çok fazla başarısız giriş denemesi. Lütfen bir süre sonra tekrar deneyin.';

        foreach ($keys as $rateKey) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT attempts, window_started_at, blocked_until FROM login_rate_limits WHERE rate_key = :key LIMIT 1'
                );
                $stmt->execute(['key' => $rateKey]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!is_array($row)) {
                    continue;
                }
                $blockedUntil = trim((string) ($row['blocked_until'] ?? ''));
                if ($blockedUntil !== '') {
                    $blockedTs = strtotime($blockedUntil);
                    if ($blockedTs !== false && $blockedTs > $now) {
                        return [
                            'allowed' => false,
                            'message' => $blockedMessage,
                            'retryAfterSec' => max(1, $blockedTs - $now),
                        ];
                    }
                }
            } catch (Throwable) {
                continue;
            }
        }

        return ['allowed' => true, 'message' => '', 'retryAfterSec' => 0];
    }
}

if (!function_exists('memberLoginRateLimitRecordFailure')) {
    function memberLoginRateLimitRecordFailure(PDO $pdo, string $login): void
    {
        memberLoginRateLimitEnsureTable($pdo);
        $keys = memberLoginRateLimitKeys($login);
        $windowMin = memberLoginRateLimitWindowMinutes();
        $maxAttempts = memberLoginRateLimitMaxAttempts();
        $blockMin = memberLoginRateLimitBlockMinutes();
        $now = time();

        foreach ($keys as $rateKey) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'SELECT rate_key, attempts, window_started_at FROM login_rate_limits WHERE rate_key = :key LIMIT 1 FOR UPDATE'
                );
                $stmt->execute(['key' => $rateKey]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!is_array($row)) {
                    $pdo->prepare(
                        'INSERT INTO login_rate_limits (rate_key, attempts, window_started_at, blocked_until)
                         VALUES (:key, 1, NOW(), NULL)'
                    )->execute(['key' => $rateKey]);
                    $pdo->commit();
                    continue;
                }
                $windowStart = strtotime((string) ($row['window_started_at'] ?? ''));
                $attempts = (int) ($row['attempts'] ?? 0);
                if ($windowStart === false || ($now - $windowStart) > ($windowMin * 60)) {
                    $attempts = 0;
                    $windowStart = $now;
                }
                $attempts++;
                $blockedUntil = null;
                if ($attempts >= $maxAttempts) {
                    $blockedUntil = date('Y-m-d H:i:s', $now + ($blockMin * 60));
                    $attempts = 0;
                    $windowStart = $now;
                }
                $upd = $pdo->prepare(
                    'UPDATE login_rate_limits
                     SET attempts = :attempts, window_started_at = :window_started_at, blocked_until = :blocked_until
                     WHERE rate_key = :key'
                );
                $upd->execute([
                    'attempts' => $attempts,
                    'window_started_at' => date('Y-m-d H:i:s', $windowStart),
                    'blocked_until' => $blockedUntil,
                    'key' => $rateKey,
                ]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[login_rate_limit] record failure: ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('memberLoginRateLimitClearSuccess')) {
    function memberLoginRateLimitClearSuccess(PDO $pdo, string $login): void
    {
        memberLoginRateLimitEnsureTable($pdo);
        $keys = array_values(memberLoginRateLimitKeys($login));
        if ($keys === []) {
            return;
        }
        try {
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $stmt = $pdo->prepare("DELETE FROM login_rate_limits WHERE rate_key IN ($placeholders)");
            $stmt->execute($keys);
        } catch (Throwable $e) {
            error_log('[login_rate_limit] clear success: ' . $e->getMessage());
        }
    }
}
