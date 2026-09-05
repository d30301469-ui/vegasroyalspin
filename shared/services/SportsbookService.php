<?php

declare(strict_types=1);

/**
 * Sportsbook (BetBy) provider — seamless wallet aggregator.
 *
 * Protocol (see integration doc):
 *   Operator API (we call provider): POST JSON with a "method" field
 *     GetGameUrl, CreateUser, Deposit, Withdraw, WithdrawAll, GetUserInfo, ...
 *   Wallet Callback API (provider calls us): GetBalance, ChangeBalance, UpdateDetail
 *     txnType: 0 = Debit (bet), 1 = Credit (win), 2 = Cancel (rollback)
 *
 * Signing: Ed25519 (libsodium). Outbound requests are signed with the 32-byte
 * private seed; inbound callbacks are verified with the 32-byte public key.
 * Signatures are base64 of the detached signature over the raw JSON body and
 * travel in the X-Signature header.
 */
final class SportsbookService
{
    private const DEFAULT_API_BASE = 'https://api.ilomhzji.win';
    public const VENDOR_CODE       = 'sports-betby';
    public const GAME_CODE         = 'sports';
    private static bool $schemaBootstrapped = false;
    /** @var array<string,mixed>|null */
    private static ?array $configCache = null;
    private const SIGN_HEADERS     = [
        'HTTP_X_SIGNATURE',
        'HTTP_X_SIGN',
        'HTTP_X_CALLBACK_SIGNATURE',
        'HTTP_X_REQUEST_SIGN',
        'HTTP_X_BETBY_SIGNATURE',
        'HTTP_SIGNATURE',
    ];

    // ─── Schema ────────────────────────────────────────────────────────────────

    public static function bootstrap(PDO $pdo): void
    {
        if (self::$schemaBootstrapped) {
            return;
        }

        self::createSchema($pdo);
        self::ensureDefaultConfig($pdo);
        self::ensureDistroColumns($pdo);
        self::$schemaBootstrapped = true;
    }

    public static function createSchema(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sportsbook_config (
                id                   TINYINT UNSIGNED NOT NULL DEFAULT 1,
                agent_code           VARCHAR(100) NOT NULL DEFAULT '',
                api_token            VARCHAR(255) NOT NULL DEFAULT '',
                api_base_url         VARCHAR(255) NOT NULL DEFAULT '" . self::DEFAULT_API_BASE . "',
                site_endpoint        VARCHAR(255) NOT NULL DEFAULT '',
                api_mode             ENUM('seamless','transfer') NOT NULL DEFAULT 'seamless',
                sign_private_key     VARCHAR(255) NOT NULL DEFAULT '',
                verify_public_key    VARCHAR(255) NOT NULL DEFAULT '',
                callback_secret      VARCHAR(128) NOT NULL DEFAULT '',
                currency             VARCHAR(8) NOT NULL DEFAULT 'TRY',
                lang                 VARCHAR(8) NOT NULL DEFAULT 'tr',
                timezone             VARCHAR(64) NOT NULL DEFAULT 'UTC',
                callback_allowed_ips TEXT NULL,
                is_active            TINYINT(1) NOT NULL DEFAULT 0,
                created_at           TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at           TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sportsbook_sessions (
                id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id       INT UNSIGNED NULL,
                username      VARCHAR(100) NULL,
                user_code     VARCHAR(120) NOT NULL,
                vendor_code   VARCHAR(100) NOT NULL DEFAULT '" . self::VENDOR_CODE . "',
                game_code     VARCHAR(100) NOT NULL DEFAULT '" . self::GAME_CODE . "',
                currency      VARCHAR(8) NOT NULL DEFAULT 'TRY',
                lang          VARCHAR(8) NOT NULL DEFAULT 'tr',
                channel       VARCHAR(20) NOT NULL DEFAULT 'desktop',
                launch_url    TEXT NULL,
                request_payload  JSON NULL,
                response_payload JSON NULL,
                created_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_sportsbook_sess_user (user_id),
                KEY idx_sportsbook_sess_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sportsbook_transactions (
                id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id        INT UNSIGNED NOT NULL,
                username       VARCHAR(100) NULL,
                user_full_name VARCHAR(255) NULL,
                txn_code       VARCHAR(200) NOT NULL,
                pair_code      VARCHAR(200) NULL,
                wager_id       VARCHAR(200) NULL,
                round_id       VARCHAR(200) NULL,
                vendor_code    VARCHAR(100) NULL,
                game_code      VARCHAR(100) NULL,
                txn_type       ENUM('bet','win','cancel') NOT NULL,
                amount         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                before_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                after_balance  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                currency       VARCHAR(8) NOT NULL DEFAULT 'TRY',
                is_free_round  TINYINT(1) NOT NULL DEFAULT 0,
                is_finished    TINYINT(1) NOT NULL DEFAULT 0,
                detail         TEXT NULL,
                raw_payload    JSON NULL,
                created_at     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_sportsbook_txn_code (txn_code),
                KEY idx_sportsbook_tx_user (user_id),
                KEY idx_sportsbook_tx_wager (wager_id),
                KEY idx_sportsbook_tx_round (round_id),
                KEY idx_sportsbook_tx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sportsbook_wallet_logs (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                method      VARCHAR(50) NULL,
                user_id     INT UNSIGNED NULL,
                txn_code    VARCHAR(200) NULL,
                http_status SMALLINT NOT NULL DEFAULT 200,
                status_code SMALLINT NULL,
                error_code  VARCHAR(50) NULL,
                duration_ms SMALLINT UNSIGNED NULL,
                request_payload  JSON NULL,
                response_payload JSON NULL,
                created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_sportsbook_wlog_method (method),
                KEY idx_sportsbook_wlog_user (user_id),
                KEY idx_sportsbook_wlog_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private static function ensureDefaultConfig(PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO sportsbook_config
                (id, agent_code, api_token, api_base_url, site_endpoint, api_mode, currency, lang, is_active)
             VALUES (1, '', '', :api, '', 'seamless', 'TRY', 'tr', 0)"
        );
        $stmt->execute([':api' => self::DEFAULT_API_BASE]);
    }

    private static function ensureDistroColumns(PDO $pdo): void
    {
        try {
            $col = $pdo->query("SHOW COLUMNS FROM sportsbook_config LIKE 'callback_secret'")?->fetch(PDO::FETCH_ASSOC);
            if (!is_array($col)) {
                $pdo->exec("ALTER TABLE sportsbook_config ADD COLUMN callback_secret VARCHAR(128) NOT NULL DEFAULT '' AFTER verify_public_key");
            }
        } catch (Throwable) {
        }
    }

    public static function isDistro(?array $cfg = null): bool
    {
        $base = strtolower(trim((string) ($cfg['api_base_url'] ?? '')));
        return $base !== '' && (str_contains($base, '/op/v1') || str_contains($base, 'operator-sportsbook'));
    }

    // ─── Config ──────────────────────────────────────────────────────────────

    public static function config(PDO $pdo): array
    {
        if (self::$configCache !== null) {
            return self::$configCache;
        }
        try {
            self::bootstrap($pdo);
            $row = $pdo->query("SELECT * FROM sportsbook_config WHERE id = 1 LIMIT 1")?->fetch(PDO::FETCH_ASSOC);
            self::$configCache = is_array($row) ? $row : [];
        } catch (Throwable) {
            self::$configCache = [];
        }

        return self::$configCache;
    }

    public static function updateConfig(PDO $pdo, array $data): void
    {
        self::bootstrap($pdo);

        $allowed = [
            'agent_code', 'api_token', 'api_base_url', 'site_endpoint', 'api_mode',
            'sign_private_key', 'verify_public_key', 'callback_secret', 'currency', 'lang', 'timezone', 'callback_allowed_ips',
        ];
        // Secret-like fields keep their stored value when submitted empty.
        $secrets = ['api_token', 'sign_private_key', 'verify_public_key', 'callback_secret'];
        $sets    = [];
        $params  = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = trim((string) $data[$key]);
            if (in_array($key, $secrets, true) && $value === '') {
                continue;
            }
            if ($key === 'api_mode') {
                $value = in_array($value, ['seamless', 'transfer'], true) ? $value : 'seamless';
            }
            if ($key === 'currency') {
                $value = strtoupper($value) ?: 'TRY';
            }
            $sets[]            = "`{$key}` = :{$key}";
            $params[":{$key}"] = $value;
        }
        $sets[]               = 'is_active = :is_active';
        $params[':is_active'] = (!empty($data['is_active']) && $data['is_active'] !== '0') ? 1 : 0;
        if ($sets === []) {
            return;
        }
        $stmt = $pdo->prepare('UPDATE sportsbook_config SET ' . implode(', ', $sets) . ' WHERE id = 1');
        $stmt->execute($params);
        self::$configCache = null;
    }

    public static function isConfigured(PDO $pdo): bool
    {
        $cfg = self::config($pdo);
        if (trim((string) ($cfg['api_token'] ?? '')) === '' || trim((string) ($cfg['api_base_url'] ?? '')) === '') {
            return false;
        }
        if (self::isDistro($cfg)) {
            return true;
        }
        return trim((string) ($cfg['agent_code'] ?? '')) !== '';
    }

    private static function activeConfig(PDO $pdo): array
    {
        $cfg = self::config($pdo);
        if ($cfg === []) {
            throw new RuntimeException('Sportsbook yapılandırması bulunamadı.');
        }
        if ((int) ($cfg['is_active'] ?? 0) !== 1) {
            throw new RuntimeException('Sportsbook entegrasyonu pasif.');
        }
        $need = self::isDistro($cfg) ? ['api_token', 'api_base_url'] : ['agent_code', 'api_token', 'api_base_url'];
        foreach ($need as $k) {
            if (trim((string) ($cfg[$k] ?? '')) === '') {
                throw new RuntimeException('Sportsbook yapılandırması eksik: ' . $k);
            }
        }
        return $cfg;
    }

    // ─── Ed25519 signing ───────────────────────────────────────────────────────

    private static function signMessage(string $message, string $privateKeyB64): string
    {
        $seed = base64_decode(trim($privateKeyB64), true);
        if (!is_string($seed) || strlen($seed) !== SODIUM_CRYPTO_SIGN_SEEDBYTES) {
            return '';
        }
        $keypair = sodium_crypto_sign_seed_keypair($seed);
        $secret  = sodium_crypto_sign_secretkey($keypair);
        return base64_encode(sodium_crypto_sign_detached($message, $secret));
    }

    private static function verifyMessage(string $message, string $signatureB64, string $publicKeyB64): bool
    {
        $public = base64_decode(trim($publicKeyB64), true);
        $sig    = base64_decode(trim($signatureB64), true);
        if (!is_string($public) || strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }
        if (!is_string($sig) || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }
        try {
            return sodium_crypto_sign_verify_detached($sig, $message, $public);
        } catch (Throwable) {
            return false;
        }
    }

    private static function callbackSignature(): string
    {
        foreach (self::SIGN_HEADERS as $key) {
            $value = trim((string) ($_SERVER[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    // ─── Operator API (we -> provider) ─────────────────────────────────────────

    private static function request(PDO $pdo, array $payload, int $timeout = 15): array
    {
        $cfg  = self::activeConfig($pdo);
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        $signature = self::signMessage($body, (string) ($cfg['sign_private_key'] ?? ''));
        if ($signature !== '') {
            $headers[] = 'X-Signature: ' . $signature;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => rtrim((string) $cfg['api_base_url'], '/'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => max(10, $timeout),
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        foreach ([
            defined('BASE_PATH') ? BASE_PATH . '/config/cacert.pem' : '',
        ] as $caInfo) {
            if ($caInfo !== '' && is_readable($caInfo)) {
                curl_setopt($ch, CURLOPT_CAINFO, $caInfo);
                break;
            }
        }

        $raw  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            throw new RuntimeException('Sportsbook API cURL hatası: ' . $err);
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Sportsbook API geçersiz JSON (HTTP ' . $code . '): ' . substr((string) $raw, 0, 200));
        }
        return $decoded;
    }

    // ─── Game Launch ───────────────────────────────────────────────────────────

    public static function launch(PDO $pdo, ?array $user, array $input): array
    {
        try {
            $cfg = self::activeConfig($pdo);
        } catch (Throwable $e) {
            return ['success' => false, 'code' => 503, 'message' => $e->getMessage()];
        }

        if (self::isDistro($cfg)) {
            return self::launchDistro($pdo, $user, $input, $cfg);
        }

        $isGuest = !is_array($user) || (int) ($user['id'] ?? 0) <= 0;
        $userId  = $isGuest ? 0 : (int) $user['id'];
        if ($isGuest) {
            $seed = session_id();
            if ($seed === '') {
                $seed = (string) ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . (string) ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . date('Ymd');
            }
            $userCode = 'guest_' . substr(hash('sha256', $seed), 0, 24);
            $nickname = 'guest';
        } else {
            $userCode = (string) $userId;
            $nickname = trim((string) ($user['username'] ?? ('user_' . $userId)));
        }
        $currency = strtoupper(trim((string) ($cfg['currency'] ?? 'TRY')));
        $lang     = strtolower(trim((string) ($input['lang'] ?? $cfg['lang'] ?? 'tr')));
        $channel  = strtolower(trim((string) ($input['channel'] ?? 'desktop')));
        $channel  = in_array($channel, ['desktop', 'mobile'], true) ? $channel : 'desktop';

        // Transfer mode: ensure the user exists on the provider before launch.
        if (!$isGuest && strtolower((string) ($cfg['api_mode'] ?? 'seamless')) === 'transfer') {
            try {
                self::request($pdo, [
                    'method'    => 'CreateUser',
                    'token'     => (string) $cfg['api_token'],
                    'agentCode' => (string) $cfg['agent_code'],
                    'userCode'  => $userCode,
                ]);
            } catch (Throwable) {
                // Duplicate user is fine; other errors surface on launch.
            }
        }

        $payload = [
            'method'       => 'GetGameUrl',
            'token'        => (string) $cfg['api_token'],
            'agentCode'    => (string) $cfg['agent_code'],
            'userCode'     => $userCode,
            'nickname'     => $nickname,
            'vendorCode'   => self::VENDOR_CODE,
            'gameCode'     => self::GAME_CODE,
            'currencyCode' => $currency,
            'language'     => $lang,
            'channel'      => $channel,
            'isDemo'       => $isGuest,
        ];
        $homeUrl = trim((string) ($input['home_url'] ?? ''));
        if ($homeUrl === '') {
            $homeUrl = defined('SITE_URL') && trim((string) SITE_URL) !== ''
                ? rtrim((string) SITE_URL, '/')
                : ('https://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        }
        $payload['homeUrl'] = $homeUrl;

        try {
            $response = self::request($pdo, $payload, 20);
        } catch (Throwable $e) {
            return ['success' => false, 'code' => 503, 'message' => 'Sportsbook API bağlantı hatası: ' . $e->getMessage()];
        }

        $status    = (int) ($response['status'] ?? -1);
        $data      = is_array($response['data'] ?? null) ? $response['data'] : [];
        $launchUrl = trim((string) (
            $response['launchUrl']
            ?? $response['launch_url']
            ?? $response['gameUrl']
            ?? $response['url']
            ?? $data['launchUrl']
            ?? $data['launch_url']
            ?? $data['gameUrl']
            ?? $data['url']
            ?? ''
        ));
        if ($launchUrl === '' || ($status !== 0 && !isset($response['launchUrl']) && !isset($data['launchUrl']))) {
            $providerMsg = (string) (
                $response['msg']
                ?? $response['message']
                ?? $response['title']
                ?? ('status ' . $status)
            );
            try {
                self::logWallet($pdo, 'GetGameUrl', $userId > 0 ? $userId : null, '', 200, $status, $providerMsg, 0, $payload, $response);
            } catch (Throwable) {
            }
            return [
                'success' => false,
                'code'    => 422,
                'message' => 'Sportsbook oyun URL döndürmedi: ' . $providerMsg,
                'raw'     => $response,
            ];
        }

        try {
            $pdo->prepare(
                "INSERT INTO sportsbook_sessions
                    (user_id, username, user_code, vendor_code, game_code, currency, lang, channel, launch_url, request_payload, response_payload)
                 VALUES (:uid, :uname, :ucode, :vendor, :game, :cur, :lang, :chan, :url, :req, :res)"
            )->execute([
                ':uid'    => $userId > 0 ? $userId : null,
                ':uname'  => $nickname,
                ':ucode'  => $userCode,
                ':vendor' => self::VENDOR_CODE,
                ':game'   => self::GAME_CODE,
                ':cur'    => $currency,
                ':lang'   => $lang,
                ':chan'   => $channel,
                ':url'    => $launchUrl,
                ':req'    => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':res'    => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable) {
            // Session logging must never block a successful launch.
        }

        return [
            'success'  => true,
            'code'     => 200,
            'message'  => 'Spor bahisleri başlatıldı.',
            'data'     => [
                'game_url'   => $launchUrl,
                'launch_url' => $launchUrl,
                'mode'       => $isGuest ? 'guest' : 'real',
            ],
            'game_url' => $launchUrl,
        ];
    }

    /**
     * Keep Distro playable balance aligned with VRS users.balance (best-effort).
     * Uses Distro /wallet/sync so site cash-in/out is not a Distro deposit/withdrawal.
     * No-ops when Distro is not the active sportsbook or the player was never launched.
     */
    public static function mirrorWallet(PDO $pdo, int $userId): void
    {
        if ($userId <= 0) {
            return;
        }
        try {
            $cfg = self::config($pdo);
            if (!self::isDistro($cfg) || (int) ($cfg['is_active'] ?? 0) !== 1 || trim((string) ($cfg['api_token'] ?? '')) === '') {
                return;
            }
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($user)) {
                return;
            }
            self::distroSyncWallet($cfg, $user);
        } catch (Throwable) {
        }
    }

    /**
     * HMAC webhook from operator-sportsbook Distro (bet/win/rollback).
     *
     * @return array{ok:bool,code:int,body:array<string,mixed>}
     */
    public static function distroHook(PDO $pdo, string $rawBody, string $signature, string $eventHeader = ''): array
    {
        self::bootstrap($pdo);
        $cfg = self::config($pdo);
        $secret = trim((string) ($cfg['callback_secret'] ?? ''));
        if ($secret === '') {
            return ['ok' => false, 'code' => 401, 'body' => ['ok' => false, 'error' => 'unauthorized']];
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        if ($signature === '' || !hash_equals($expected, $signature)) {
            return ['ok' => false, 'code' => 401, 'body' => ['ok' => false, 'error' => 'invalid_signature']];
        }
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return ['ok' => false, 'code' => 400, 'body' => ['ok' => false, 'error' => 'invalid_json']];
        }
        $event = strtolower(trim((string) ($eventHeader !== '' ? $eventHeader : ($payload['event'] ?? ''))));
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $userId = self::distroExternalUserId($data);
        if ($userId <= 0) {
            return ['ok' => true, 'code' => 200, 'body' => ['ok' => true, 'ignored' => 'no_player']];
        }

        $txnKey = 'distro:' . $event . ':' . (string) (
            $data['transaction_id']
            ?? $data['bet_id']
            ?? hash('sha256', $rawBody)
        );

        $delta = 0.0;
        $wager = 0.0;
        $type = '';
        $state = (int) ($data['bet_state'] ?? 0);
        $isVoid = !empty($data['is_void']);
        if ($event === 'bet_placed') {
            if (!empty($data['is_freebet'])) {
                return ['ok' => true, 'code' => 200, 'body' => ['ok' => true, 'ignored' => 'freebet']];
            }
            $wager = round(abs((float) ($data['amount'] ?? 0)), 2);
            $delta = -1 * $wager;
            $type = 'bet';
        } elseif ($event === 'bet_resulted') {
            $delta = round((float) ($data['delta'] ?? 0), 2);
            if ($isVoid || $state === 2) {
                $type = 'cancel';
            } elseif ($delta > 0) {
                $type = 'win';
            } else {
                $type = 'bet';
            }
        } elseif ($event === 'rollback') {
            $delta = round(abs((float) ($data['amount'] ?? 0)), 2);
            $type = 'cancel';
        } elseif ($event === 'jackpot') {
            $delta = round(abs((float) ($data['amount'] ?? 0)), 2);
            $type = 'win';
        } else {
            return ['ok' => true, 'code' => 200, 'body' => ['ok' => true, 'ignored' => $event]];
        }

        $moneyMove = round($delta, 2) != 0.0;
        $settleCoupon = $event === 'bet_resulted' || $event === 'rollback';
        if (!$moneyMove && !$settleCoupon) {
            return ['ok' => true, 'code' => 200, 'body' => ['ok' => true, 'ignored' => 'zero']];
        }

        try {
            $pdo->beginTransaction();
            $existing = $pdo->prepare('SELECT after_balance FROM sportsbook_transactions WHERE txn_code = :c LIMIT 1');
            $existing->execute([':c' => $txnKey]);
            $prev = $existing->fetchColumn();
            if ($prev !== false) {
                $wagerId = isset($data['bet_id']) ? trim((string) $data['bet_id']) : '';
                if ($settleCoupon && $wagerId !== '') {
                    self::markWagerFinished($pdo, $wagerId, $event);
                }
                $pdo->commit();
                return ['ok' => true, 'code' => 200, 'body' => ['ok' => true, 'duplicate' => true, 'balance' => round((float) $prev, 2)]];
            }

            $stmt = $pdo->prepare('SELECT id, username, balance FROM users WHERE id = :id AND banned = 0 LIMIT 1 FOR UPDATE');
            $stmt->execute([':id' => $userId]);
            $locked = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($locked)) {
                $pdo->rollBack();
                return ['ok' => false, 'code' => 404, 'body' => ['ok' => false, 'error' => 'user_not_found']];
            }

            $before = round((float) $locked['balance'], 2);
            $after = $moneyMove ? round($before + $delta, 2) : $before;
            if ($after < 0) {
                $after = 0.0;
            }
            if ($moneyMove) {
                $pdo->prepare('UPDATE users SET balance = :bal WHERE id = :id')
                    ->execute([':bal' => $after, ':id' => $userId]);
            }

            if (!class_exists('WageringService', false)) {
                $wageringPath = dirname(__DIR__) . '/services/WageringService.php';
                if (is_readable($wageringPath)) {
                    require_once $wageringPath;
                }
            }
            if ($moneyMove && class_exists('WageringService', false)) {
                if ($type === 'bet' && $wager > 0) {
                    WageringService::registerBet($pdo, $userId, $wager);
                } elseif ($type === 'cancel' && $delta > 0) {
                    WageringService::reverseBet($pdo, $userId, abs($delta));
                }
            }

            $wagerId = isset($data['bet_id']) ? trim((string) $data['bet_id']) : '';
            if ($moneyMove) {
                $pdo->prepare(
                    "INSERT INTO sportsbook_transactions
                        (user_id, username, user_full_name, txn_code, wager_id, vendor_code, game_code, txn_type,
                         amount, before_balance, after_balance, currency, is_finished, detail, raw_payload)
                     VALUES (:uid, :uname, :ufull, :txn, :wager, :vendor, :game, :type,
                             :amt, :before, :after, :cur, :fin, :detail, :raw)"
                )->execute([
                    ':uid'    => $userId,
                    ':uname'  => (string) ($locked['username'] ?? ''),
                    ':ufull'  => self::userFullName($pdo, $userId),
                    ':txn'    => $txnKey,
                    ':wager'  => $wagerId !== '' ? $wagerId : null,
                    ':vendor' => 'sports-distro',
                    ':game'   => self::GAME_CODE,
                    ':type'   => $type,
                    ':amt'    => $delta,
                    ':before' => $before,
                    ':after'  => $after,
                    ':cur'    => strtoupper((string) ($data['currency'] ?? $cfg['currency'] ?? 'TRY')),
                    ':fin'    => $event !== 'bet_placed' ? 1 : 0,
                    ':detail' => $event,
                    ':raw'    => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }
            if ($settleCoupon && $wagerId !== '') {
                self::markWagerFinished($pdo, $wagerId, $event);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (str_contains($e->getMessage(), '1062') || stripos($e->getMessage(), 'Duplicate') !== false) {
                return ['ok' => true, 'code' => 200, 'body' => ['ok' => true, 'duplicate' => true]];
            }
            error_log('Distro sportsbook hook: ' . $e->getMessage());
            return ['ok' => false, 'code' => 500, 'body' => ['ok' => false, 'error' => 'internal']];
        }

        return ['ok' => true, 'code' => 200, 'body' => ['ok' => true, 'balance' => $after]];
    }

    private static function launchDistro(PDO $pdo, ?array $user, array $input, array $cfg): array
    {
        $isGuest = !is_array($user) || (int) ($user['id'] ?? 0) <= 0;
        $userId = $isGuest ? 0 : (int) $user['id'];
        $lang = strtolower(trim((string) ($input['lang'] ?? $cfg['lang'] ?? 'tr')));
        if (strlen($lang) > 2) {
            $lang = substr($lang, 0, 2);
        }
        $channel = strtolower(trim((string) ($input['channel'] ?? 'desktop')));
        $channel = in_array($channel, ['desktop', 'mobile'], true) ? $channel : 'desktop';
        $currency = strtoupper(trim((string) ($cfg['currency'] ?? 'TRY'))) ?: 'TRY';

        try {
            if ($isGuest) {
                $response = self::distroRequest($cfg, 'POST', '/session/guest', ['language' => $lang]);
            } else {
                $first = trim((string) ($user['name'] ?? $user['first_name'] ?? ''));
                $last = trim((string) ($user['surname'] ?? $user['last_name'] ?? ''));
                $body = [
                    'player_id' => (string) $userId,
                    'login' => trim((string) ($user['username'] ?? ('user_' . $userId))),
                    'language' => $lang,
                    'currency' => $currency,
                    'email' => $user['email'] ?? null,
                    'first_name' => $first !== '' ? $first : null,
                    'last_name' => $last !== '' ? $last : null,
                    'birth_date' => $user['dob'] ?? $user['birth_date'] ?? null,
                    'phone' => $user['phone'] ?? null,
                    'country_id' => self::countryIso($user),
                    'current_ip' => self::clientIp(),
                ];
                $g = strtolower(trim((string) ($user['gender'] ?? '')));
                if ($g === '1' || $g === 'm' || $g === 'male' || $g === 'erkek') {
                    $body['gender'] = 1;
                } elseif ($g === '2' || $g === 'f' || $g === 'female' || $g === 'kadın' || $g === 'kadin') {
                    $body['gender'] = 2;
                }
                $response = self::distroRequest($cfg, 'POST', '/session', $body);
            }
        } catch (Throwable $e) {
            return ['success' => false, 'code' => 503, 'message' => 'Sportsbook API bağlantı hatası: ' . $e->getMessage()];
        }

        if (!$isGuest && !empty($response['ok'])) {
            self::deferDistroWalletSync($cfg, $user);
        }

        if (empty($response['ok'])) {
            $err = (string) ($response['error'] ?? $response['message'] ?? 'session_failed');
            return ['success' => false, 'code' => 422, 'message' => 'Sportsbook oturumu açılamadı: ' . $err];
        }

        $desktop = trim((string) ($response['iframe_url'] ?? ''));
        $mobile = trim((string) ($response['mobile_iframe_url'] ?? $desktop));
        $launchUrl = $channel === 'mobile' ? ($mobile !== '' ? $mobile : $desktop) : $desktop;
        if ($launchUrl === '' || !preg_match('#^https?://#i', $launchUrl)) {
            return ['success' => false, 'code' => 422, 'message' => 'Sportsbook oyun URL döndürmedi.'];
        }

        $nickname = $isGuest ? 'guest' : trim((string) ($user['username'] ?? ('user_' . $userId)));
        try {
            $pdo->prepare(
                "INSERT INTO sportsbook_sessions
                    (user_id, username, user_code, vendor_code, game_code, currency, lang, channel, launch_url, request_payload, response_payload)
                 VALUES (:uid, :uname, :ucode, :vendor, :game, :cur, :lang, :chan, :url, :req, :res)"
            )->execute([
                ':uid'    => $userId > 0 ? $userId : null,
                ':uname'  => $nickname,
                ':ucode'  => $isGuest ? 'guest' : (string) $userId,
                ':vendor' => 'sports-distro',
                ':game'   => self::GAME_CODE,
                ':cur'    => $currency,
                ':lang'   => $lang,
                ':chan'   => $channel,
                ':url'    => $launchUrl,
                ':req'    => json_encode(['provider' => 'distro', 'channel' => $channel, 'lang' => $lang], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':res'    => json_encode(self::redactDistroPayload($response), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable) {
        }

        $prefs = is_array($response['preferences'] ?? null) ? $response['preferences'] : [];
        $out = [
            'provider' => 'distro',
            'game_url' => $launchUrl,
            'launch_url' => $launchUrl,
            'iframe_url' => $desktop,
            'mobile_iframe_url' => $mobile,
            'live_url' => (string) ($response['live_url'] ?? ''),
            'mobile_live_url' => (string) ($response['mobile_live_url'] ?? ''),
            'prematch_url' => (string) ($response['prematch_url'] ?? ''),
            'mobile_prematch_url' => (string) ($response['mobile_prematch_url'] ?? ''),
            'embed_js' => (string) ($response['embed_js'] ?? ''),
            'user_id' => $userId > 0 ? $userId : null,
            'preferences' => $prefs,
            'mode' => $isGuest ? 'guest' : 'real',
        ];
        if (!$isGuest) {
            $out['auth_token'] = (string) ($response['auth_token'] ?? '');
        }
        return [
            'success' => true,
            'code' => 200,
            'message' => 'Spor bahisleri başlatıldı.',
            'data' => $out,
            'game_url' => $launchUrl,
        ];
    }

    private static function stripAuthQuery(string $url): string
    {
        $url = trim($url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return $url;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return $url;
        }
        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
            unset($query['AuthToken'], $query['auth_token']);
        }
        $out = strtolower((string) ($parts['scheme'] ?? 'https')) . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $out .= ':' . (int) $parts['port'];
        }
        $out .= (string) ($parts['path'] ?? '');
        $qs = http_build_query($query);
        if ($qs !== '') {
            $out .= '?' . $qs;
        }
        if (!empty($parts['fragment'])) {
            $out .= '#' . $parts['fragment'];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private static function redactDistroPayload(array $payload): array
    {
        unset($payload['auth_token'], $payload['snippet']);
        foreach (['iframe_url', 'mobile_iframe_url', 'live_url', 'mobile_live_url', 'prematch_url', 'mobile_prematch_url'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key])) {
                $payload[$key] = self::stripAuthQuery($payload[$key]);
            }
        }
        return $payload;
    }

    /**
     * @param array<string,mixed> $cfg
     * @param array<string,mixed>|null $body
     * @param array<string,string> $query
     * @return array<string,mixed>
     */
    private static function distroRequest(array $cfg, string $method, string $path, ?array $body = null, array $query = []): array
    {
        $url = rtrim((string) $cfg['api_base_url'], '/') . $path;
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }
        $targets = self::distroTargets($url);
        $last = null;
        foreach ($targets as $i => $target) {
            try {
                return self::distroCurl($method, $target['url'], $body, $cfg, $target['host'], $target['timeout']);
            } catch (Throwable $e) {
                $last = $e;
                if ($i === count($targets) - 1) {
                    throw $e;
                }
            }
        }
        throw $last ?? new RuntimeException('Distro bağlantı hatası');
    }

    /**
     * Same-VPS Distro calls skip Cloudflare so launch is not waiting on the public edge.
     *
     * @return list<array{url:string,host:?string,timeout:array{connect:int,total:int}}>
     */
    private static function distroTargets(string $url): array
    {
        $public = ['url' => $url, 'host' => null, 'timeout' => ['connect' => 8, 'total' => 20]];
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host !== 'operator-sportsbook.site' || !is_array($parts)) {
            return [$public];
        }
        $loop = 'http://127.0.0.1' . (string) ($parts['path'] ?? '');
        if (!empty($parts['query'])) {
            $loop .= '?' . $parts['query'];
        }

        return [
            ['url' => $loop, 'host' => 'operator-sportsbook.site', 'timeout' => ['connect' => 1, 'total' => 4]],
            $public,
        ];
    }

    /**
     * @param array<string,mixed> $cfg
     * @param array<string,mixed>|null $body
     * @param array{connect:int,total:int} $timeout
     * @return array<string,mixed>
     */
    private static function distroCurl(string $method, string $url, ?array $body, array $cfg, ?string $hostHeader, array $timeout): array
    {
        $headers = [
            'Accept: application/json',
            'X-Operator-Key: ' . trim((string) $cfg['api_token']),
        ];
        if ($hostHeader !== null && $hostHeader !== '') {
            $headers[] = 'Host: ' . $hostHeader;
        }
        $ch = curl_init();
        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int) $timeout['total'],
            CURLOPT_CONNECTTIMEOUT => (int) $timeout['connect'],
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => !str_starts_with($url, 'http://127.0.0.1'),
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if (strtoupper($method) !== 'GET') {
            $json = json_encode($body ?? new stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $json;
            if (strtoupper($method) !== 'POST') {
                $opts[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
            }
        }
        curl_setopt_array($ch, $opts);
        if (!str_starts_with($url, 'http://127.0.0.1')) {
            foreach ([
                defined('BASE_PATH') ? BASE_PATH . '/config/cacert.pem' : '',
            ] as $caInfo) {
                if ($caInfo !== '' && is_readable($caInfo)) {
                    curl_setopt($ch, CURLOPT_CAINFO, $caInfo);
                    break;
                }
            }
        }
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false || $err !== '' || $code <= 0) {
            throw new RuntimeException('cURL: ' . ($err !== '' ? $err : ('HTTP ' . $code)));
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('geçersiz JSON (HTTP ' . $code . '): ' . substr((string) $raw, 0, 200));
        }
        $decoded['_http'] = $code;
        return $decoded;
    }

    private static function countryIso(array $user): string
    {
        $raw = trim((string) ($user['country_id'] ?? $user['country'] ?? 'TR'));
        if ($raw === '') {
            return 'TR';
        }
        $up = strtoupper($raw);
        if (preg_match('/^[A-Z]{2}$/', $up) === 1) {
            return $up;
        }
        $fold = strtolower(strtr($raw, [
            'ı' => 'i', 'İ' => 'i', 'ş' => 's', 'Ş' => 's', 'ğ' => 'g', 'Ğ' => 'g',
            'ü' => 'u', 'Ü' => 'u', 'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c',
        ]));
        $fold = preg_replace('/[^a-z]/', '', $fold) ?? $fold;
        return match ($fold) {
            'tr', 'tur', 'turkey', 'turkiye' => 'TR',
            'de', 'germany', 'almanya', 'deutschland' => 'DE',
            'az', 'azerbaijan', 'azerbaycan' => 'AZ',
            'gb', 'uk', 'unitedkingdom', 'ingiltere' => 'GB',
            'nl', 'netherlands', 'holland', 'hollanda' => 'NL',
            'us', 'usa', 'unitedstates' => 'US',
            default => 'TR',
        };
    }

    private static function clientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            $raw = trim((string) ($_SERVER[$key] ?? ''));
            if ($key === 'HTTP_X_FORWARDED_FOR' && str_contains($raw, ',')) {
                $raw = trim(explode(',', $raw)[0]);
            }
            if ($raw !== '' && filter_var($raw, FILTER_VALIDATE_IP)) {
                return $raw;
            }
        }

        return '';
    }

    /** @param array<string,mixed> $cfg @param array<string,mixed> $user */
    private static function deferDistroWalletSync(array $cfg, array $user): void
    {
        register_shutdown_function(static function () use ($cfg, $user): void {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            try {
                self::distroSyncWallet($cfg, $user);
            } catch (Throwable) {
            }
        });
    }

    /** @param array<string,mixed> $cfg @param array<string,mixed> $user */
    private static function distroSyncWallet(array $cfg, array $user): void
    {
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return;
        }
        $got = self::distroRequest($cfg, 'GET', '/player', null, ['player_id' => (string) $userId]);
        $player = is_array($got['player'] ?? null) ? $got['player'] : [];
        if ($player === []) {
            return;
        }
        $vrs = round((float) ($user['balance'] ?? 0), 2);
        $distro = round((float) ($player['balance'] ?? 0), 2);
        $diff = round($vrs - $distro, 2);
        if (abs($diff) < 0.01) {
            return;
        }
        $from = number_format($distro, 2, '.', '');
        $to = number_format($vrs, 2, '.', '');
        $ref = 'vrs-align-' . $userId . '-' . str_replace('.', '', $from) . '-' . str_replace('.', '', $to);
        self::distroRequest($cfg, 'POST', '/wallet/sync', [
            'player_id' => (string) $userId,
            'amount' => $to,
            'ref' => $ref,
            'note' => 'vrs_wallet_sync',
        ]);
    }

    /** @param array<string,mixed> $data */
    private static function distroExternalUserId(array $data): int
    {
        $ext = trim((string) ($data['player_id'] ?? $data['player_external_id'] ?? ''));
        if ($ext === '') {
            return 0;
        }
        if (str_contains($ext, ':')) {
            $ext = substr($ext, (int) strrpos($ext, ':') + 1);
        }
        return (int) $ext;
    }

    // ─── Wallet Callback API (provider -> we) ───────────────────────────────────

    public static function wallet(PDO $pdo, array $payload, string $rawBody, string $signature = ''): array
    {
        $payload = self::normalizeWalletPayload($payload);
        $start   = microtime(true);
        $method  = self::normalizeWalletMethod(trim((string) ($payload['method'] ?? '')));
        $userId  = null;
        $txnCode = trim((string) ($payload['txnCode'] ?? ''));
        $status  = 200;
        $result  = ['status' => 2, 'msg' => 'INVALID_ACTION'];

        try {
            if (!self::verifyCallback($pdo, $rawBody, $signature)) {
                $result = ['status' => 2, 'msg' => 'INVALID_SIGNATURE'];
            } elseif (!self::verifyToken($pdo, $payload)) {
                $result = ['status' => 3, 'msg' => 'INVALID_AGENT'];
            } else {
                $result = match ($method) {
                    'GetBalance'    => self::walletGetBalance($pdo, $payload),
                    'ChangeBalance' => self::walletChangeBalance($pdo, $payload),
                    'UpdateDetail'  => self::walletUpdateDetail($pdo, $payload),
                    default         => ['status' => 2, 'msg' => 'INVALID_ACTION'],
                };
                $userId = isset($result['__user_id']) ? (int) $result['__user_id'] : null;
            }
        } catch (Throwable $e) {
            error_log('Sportsbook wallet error: ' . $e->getMessage());
            $result = ['status' => 1, 'msg' => 'INTERNAL_ERROR'];
        }

        $statusCode = isset($result['status']) ? (int) $result['status'] : null;
        unset($result['__user_id']);

        try {
            self::logWallet($pdo, $method, $userId, $txnCode, $status, $statusCode,
                is_string($result['msg'] ?? null) && ($statusCode ?? 0) !== 0 ? (string) $result['msg'] : null,
                (int) round((microtime(true) - $start) * 1000), $payload, $result);
        } catch (Throwable) {
        }

        return ['status' => $status, 'body' => $result];
    }

    private static function verifyCallback(PDO $pdo, string $rawBody, string $signature): bool
    {
        $cfg    = self::config($pdo);
        $public = trim((string) ($cfg['verify_public_key'] ?? ''));
        // If no public key is configured, skip signature enforcement.
        if ($public === '') {
            return true;
        }
        $strict = (string) getenv('SPORTSBOOK_STRICT_SIGNATURE') === '1';
        $signature = $signature !== '' ? $signature : self::callbackSignature();
        if ($signature === '') {
            return !$strict;
        }
        $verified = self::verifyMessage($rawBody, $signature, $public);
        if ($verified) {
            return true;
        }

        return !$strict;
    }

    private static function verifyToken(PDO $pdo, array $payload): bool
    {
        $cfg   = self::config($pdo);
        $token = trim((string) ($cfg['api_token'] ?? ''));
        if ($token === '') {
            return false;
        }
        $payloadToken = trim((string) ($payload['token'] ?? $payload['api_token'] ?? $payload['agentToken'] ?? $payload['agent_token'] ?? ''));
        return hash_equals($token, $payloadToken);
    }

    private static function normalizeWalletPayload(array $payload): array
    {
        $normalized = [
            'method' => $payload['method'] ?? $payload['action'] ?? $payload['methodName'] ?? '',
            'token' => $payload['token'] ?? $payload['api_token'] ?? $payload['agentToken'] ?? $payload['agent_token'] ?? '',
            'userCode' => $payload['userCode']
                ?? $payload['user_code']
                ?? $payload['usercode']
                ?? $payload['user_id']
                ?? $payload['username']
                ?? $payload['userName']
                ?? $payload['playerId']
                ?? $payload['memberCode']
                ?? '',
            'txnCode' => $payload['txnCode'] ?? $payload['txn_code'] ?? $payload['transactionId'] ?? $payload['transaction_id'] ?? '',
            'txnType' => $payload['txnType'] ?? $payload['txn_type'] ?? $payload['type'] ?? '',
            'amount' => $payload['amount'] ?? $payload['balance'] ?? $payload['delta'] ?? '',
            'pairCode' => $payload['pairCode'] ?? $payload['pair_code'] ?? '',
            'wagerId' => $payload['wagerId'] ?? $payload['wager_id'] ?? '',
            'roundId' => $payload['roundId'] ?? $payload['round_id'] ?? '',
            'vendorCode' => $payload['vendorCode'] ?? $payload['vendor_code'] ?? '',
            'gameCode' => $payload['gameCode'] ?? $payload['game_code'] ?? '',
            'currencyCode' => $payload['currencyCode'] ?? $payload['currency_code'] ?? '',
            'detail' => $payload['detail'] ?? '',
            'isFreeRound' => $payload['isFreeRound'] ?? $payload['is_free_round'] ?? 0,
            'isFinished' => $payload['isFinished'] ?? $payload['is_finished'] ?? 0,
        ];

        foreach ($payload as $key => $value) {
            if (!array_key_exists($key, $normalized)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private static function normalizeWalletMethod(string $method): string
    {
        switch (strtolower(trim($method))) {
            case 'getbalance':
            case 'get_balance':
                return 'GetBalance';
            case 'changebalance':
            case 'change_balance':
                return 'ChangeBalance';
            case 'updatedetail':
            case 'update_detail':
                return 'UpdateDetail';
            default:
                return (string) $method;
        }
    }

    private static function walletGetBalance(PDO $pdo, array $payload): array
    {
        $user = self::userByCode($pdo, (string) ($payload['userCode'] ?? ''));
        if ($user === null) {
            return ['status' => 5, 'msg' => 'INVALID_USER'];
        }
        return [
            'status'       => 0,
            'msg'          => 'SUCCESS',
            'balance'      => round((float) ($user['balance'] ?? 0), 2),
            'currencyCode' => strtoupper(trim((string) ($payload['currencyCode'] ?? $payload['currency_code'] ?? 'TRY'))),
            '__user_id'    => (int) $user['id'],
        ];
    }

    private static function walletChangeBalance(PDO $pdo, array $payload): array
    {
        $user = self::userByCode($pdo, (string) ($payload['userCode'] ?? ''));
        if ($user === null) {
            return ['status' => 5, 'msg' => 'INVALID_USER'];
        }
        $userId  = (int) $user['id'];
        $txnCode = trim((string) ($payload['txnCode'] ?? ''));
        if ($txnCode === '') {
            return ['status' => 13, 'msg' => 'INVALID_PARAMETER', '__user_id' => $userId];
        }

        $txnType = (int) ($payload['txnType'] ?? -1);        // 0 debit, 1 credit, 2 cancel
        $amount  = round((float) ($payload['amount'] ?? 0), 2); // signed delta from provider

        // Idempotency: same txnCode returns the current balance without re-applying.
        $existing = $pdo->prepare("SELECT after_balance FROM sportsbook_transactions WHERE txn_code = :c LIMIT 1");
        $existing->execute([':c' => $txnCode]);
        $prev = $existing->fetchColumn();
        if ($prev !== false) {
            return ['status' => 0, 'msg' => 'SUCCESS', 'balance' => round((float) $prev, 2), '__user_id' => $userId];
        }

        $type = match ($txnType) {
            0 => 'bet',
            1 => 'win',
            2 => 'cancel',
            default => '',
        };
        if ($type === '') {
            return ['status' => 13, 'msg' => 'INVALID_PARAMETER', '__user_id' => $userId];
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT id, username, balance FROM users WHERE id = :id AND banned = 0 LIMIT 1 FOR UPDATE");
            $stmt->execute([':id' => $userId]);
            $locked = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($locked)) {
                $pdo->rollBack();
                return ['status' => 5, 'msg' => 'INVALID_USER', '__user_id' => $userId];
            }

            $before = round((float) $locked['balance'], 2);
            $after  = round($before + $amount, 2);
            if ($after < 0) {
                $pdo->rollBack();
                return ['status' => 8, 'msg' => 'INSUFFICIENT_MONEY', 'balance' => $before, '__user_id' => $userId];
            }

            $pdo->prepare("UPDATE users SET balance = :bal WHERE id = :id")
                ->execute([':bal' => $after, ':id' => $userId]);

            if ($type === 'bet' && $amount < 0) {
                WageringService::registerBet($pdo, $userId, abs($amount));
            } elseif ($type === 'cancel' && $amount > 0) {
                // A positive delta on cancel means funds were returned, i.e. a bet was voided.
                WageringService::reverseBet($pdo, $userId, abs($amount));
            }

            $wagerId = trim((string) ($payload['wagerId'] ?? '')) ?: null;
            $detail  = trim((string) ($payload['detail'] ?? '')) ?: null;
            $finished = !empty($payload['isFinished']) || $type === 'win' || $type === 'cancel';

            $pdo->prepare(
                "INSERT INTO sportsbook_transactions
                    (user_id, username, user_full_name, txn_code, pair_code, wager_id, round_id,
                     vendor_code, game_code, txn_type, amount, before_balance, after_balance,
                     currency, is_free_round, is_finished, detail, raw_payload)
                 VALUES (:uid, :uname, :ufull, :txn, :pair, :wager, :round,
                         :vendor, :game, :type, :amt, :before, :after,
                         :cur, :free, :fin, :detail, :raw)"
            )->execute([
                ':uid'    => $userId,
                ':uname'  => (string) ($locked['username'] ?? ''),
                ':ufull'  => self::userFullName($pdo, $userId),
                ':txn'    => $txnCode,
                ':pair'   => trim((string) ($payload['pairCode'] ?? '')) ?: null,
                ':wager'  => $wagerId,
                ':round'  => trim((string) ($payload['gameRoundId'] ?? $payload['roundId'] ?? '')) ?: null,
                ':vendor' => (string) ($payload['vendorCode'] ?? self::VENDOR_CODE),
                ':game'   => (string) ($payload['gameCode'] ?? self::GAME_CODE),
                ':type'   => $type,
                ':amt'    => $amount,
                ':before' => $before,
                ':after'  => $after,
                ':cur'    => strtoupper((string) ($payload['currencyCode'] ?? 'TRY')),
                ':free'   => !empty($payload['isFreeRound']) ? 1 : 0,
                ':fin'    => $finished ? 1 : 0,
                ':detail' => $detail,
                ':raw'    => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            // Win/cancel (and explicit isFinished) must close the original bet row as well.
            // Lost coupons often arrive as ChangeBalance win amount=0 with isFinished=true and
            // never call UpdateDetail — without this the bet stays "Aktif"/pending forever.
            if ($finished && $wagerId !== null && $wagerId !== '') {
                self::markWagerFinished($pdo, $wagerId, $detail);
            }

            $pdo->commit();
            return ['status' => 0, 'msg' => 'SUCCESS', 'balance' => $after, 'currencyCode' => strtoupper(trim((string) ($payload['currencyCode'] ?? $payload['currency_code'] ?? 'TRY'))), '__user_id' => $userId];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (str_contains($e->getMessage(), '1062') || stripos($e->getMessage(), 'Duplicate') !== false) {
                $balStmt = $pdo->prepare("SELECT balance FROM users WHERE id = :id LIMIT 1");
                $balStmt->execute([':id' => $userId]);
                return ['status' => 0, 'msg' => 'SUCCESS', 'balance' => round((float) ($balStmt->fetchColumn() ?: 0), 2), '__user_id' => $userId];
            }
            throw $e;
        }
    }

    private static function walletUpdateDetail(PDO $pdo, array $payload): array
    {
        $wagerId = trim((string) ($payload['wagerId'] ?? ''));
        $detail  = (string) ($payload['detail'] ?? '');
        if ($wagerId === '') {
            return ['status' => 18, 'msg' => 'INVALID_WAGER'];
        }

        // A LOST coupon never triggers ChangeBalance (no money moves), so UpdateDetail is the
        // only signal that settles it. Persist isFinished=1 when the provider reports it so the
        // original 'bet' row stops looking permanently "Aktif"/open in member bet history. Never
        // write it back to 0 — normalizeWalletPayload() always fills the key (default 0), so a
        // falsy value here just means "not reported", not "un-finish this wager".
        // Also treat settled detail JSON (outcome/settled_at) as finished even if isFinished flag is missing.
        $finished = !empty($payload['isFinished'] ?? $payload['is_finished'] ?? 0);
        if (!$finished && $detail !== '') {
            $decoded = json_decode($detail, true);
            if (is_array($decoded)) {
                $outcome = (int) ($decoded['outcome'] ?? 0);
                $settled = $decoded['settled_at'] ?? null;
                if ($outcome > 0 || ($settled !== null && $settled !== '' && $settled !== 0 && $settled !== '0')) {
                    $finished = true;
                }
            }
        }

        $sql    = "UPDATE sportsbook_transactions SET detail = :d";
        $params = [':d' => $detail, ':w' => $wagerId];
        if ($finished) {
            $sql .= ", is_finished = 1";
        }
        $sql .= " WHERE wager_id = :w";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->rowCount() === 0) {
            return ['status' => 18, 'msg' => 'INVALID_WAGER'];
        }
        return ['status' => 0, 'msg' => 'SUCCESS'];
    }

    /**
     * Close all rows for a wager (especially the original bet) after settlement.
     */
    private static function markWagerFinished(PDO $pdo, string $wagerId, ?string $detail = null): void
    {
        $wagerId = trim($wagerId);
        if ($wagerId === '') {
            return;
        }
        $pdo->prepare('UPDATE sportsbook_transactions SET is_finished = 1 WHERE wager_id = :w')
            ->execute([':w' => $wagerId]);
        $detail = trim((string) $detail);
        if ($detail !== '') {
            $pdo->prepare(
                "UPDATE sportsbook_transactions
                 SET detail = :d
                 WHERE wager_id = :w
                   AND txn_type = 'bet'
                   AND (detail IS NULL OR detail = '')"
            )->execute([':d' => $detail, ':w' => $wagerId]);
        }
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private static function userByCode(PDO $pdo, string $userCode): ?array
    {
        $userCode = trim($userCode);
        if ($userCode === '') {
            return null;
        }
        $column = ctype_digit($userCode) ? 'id' : 'username';
        try {
            $stmt = $pdo->prepare("SELECT id, username, balance FROM users WHERE {$column} = :v AND banned = 0 LIMIT 1");
            $stmt->execute([':v' => $userCode]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function userFullName(PDO $pdo, int $userId): string
    {
        static $cache = [];
        if (!isset($cache[$userId])) {
            try {
                $stmt = $pdo->prepare("SELECT name, surname FROM users WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $userId]);
                $row            = $stmt->fetch(PDO::FETCH_ASSOC);
                $cache[$userId] = is_array($row)
                    ? trim(trim((string) ($row['name'] ?? '')) . ' ' . trim((string) ($row['surname'] ?? '')))
                    : '';
            } catch (Throwable) {
                $cache[$userId] = '';
            }
        }
        return $cache[$userId];
    }

    private static function logWallet(
        PDO $pdo,
        string $method,
        ?int $userId,
        string $txnCode,
        int $httpStatus,
        ?int $statusCode,
        ?string $errCode,
        int $duration,
        array $request,
        array $response
    ): void {
        $pdo->prepare(
            "INSERT INTO sportsbook_wallet_logs
                (method, user_id, txn_code, http_status, status_code, error_code, duration_ms, request_payload, response_payload)
             VALUES (:m, :u, :t, :h, :s, :e, :d, :req, :res)"
        )->execute([
            ':m'   => $method !== '' ? $method : null,
            ':u'   => $userId,
            ':t'   => $txnCode !== '' ? $txnCode : null,
            ':h'   => $httpStatus,
            ':s'   => $statusCode,
            ':e'   => $errCode,
            ':d'   => $duration,
            ':req' => json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':res' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    // ─── Member transaction history ─────────────────────────────────────────────

    public static function userHistory(PDO $pdo, int $userId, int $limit = 50, int $offset = 0): array
    {
        if ($userId <= 0) {
            return [];
        }
        $limit  = max(1, min(200, $limit));
        $offset = max(0, $offset);
        try {
            $stmt = $pdo->prepare(
                "SELECT id, txn_code, wager_id, round_id, vendor_code, game_code, txn_type, amount,
                        before_balance, after_balance, currency, is_finished, created_at
                 FROM sportsbook_transactions
                 WHERE user_id = :uid
                 ORDER BY id DESC
                 LIMIT {$limit} OFFSET {$offset}"
            );
            $stmt->execute([':uid' => $userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            return [];
        }
    }
}
