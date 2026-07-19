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
        if ((string) getenv('METROPOL_RUNTIME_PROVIDER_BOOTSTRAP') !== '1'
            || !self::runtimeSchemaChangesAllowed()) {
            return;
        }
        self::createSchema($pdo);
        self::ensureDefaultConfig($pdo);
    }

    private static function runtimeSchemaChangesAllowed(): bool
    {
        $env = strtolower(trim((string) (getenv('APP_ENV') ?: 'production')));
        return in_array($env, ['development', 'local', 'dev', 'testing', 'test'], true)
            || (string) getenv('ALLOW_RUNTIME_MIGRATIONS') === '1';
    }

    public static function createSchema(PDO $pdo): void
    {
        if (!self::runtimeSchemaChangesAllowed()) {
            throw new RuntimeException('Runtime provider schema changes are disabled in production.');
        }

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

    // ─── Config ──────────────────────────────────────────────────────────────

    public static function config(PDO $pdo): array
    {
        try {
            $row = $pdo->query("SELECT * FROM sportsbook_config WHERE id = 1 LIMIT 1")?->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : [];
        } catch (Throwable) {
            return [];
        }
    }

    public static function updateConfig(PDO $pdo, array $data): void
    {
        $allowed = [
            'agent_code', 'api_token', 'api_base_url', 'site_endpoint', 'api_mode',
            'sign_private_key', 'verify_public_key', 'currency', 'lang', 'timezone', 'callback_allowed_ips',
        ];
        // Secret-like fields keep their stored value when submitted empty.
        $secrets = ['api_token', 'sign_private_key', 'verify_public_key'];
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
    }

    public static function isConfigured(PDO $pdo): bool
    {
        $cfg = self::config($pdo);
        return !empty($cfg['agent_code']) && !empty($cfg['api_token']) && !empty($cfg['api_base_url']);
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
        foreach (['agent_code', 'api_token', 'api_base_url'] as $k) {
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
                ':wager'  => trim((string) ($payload['wagerId'] ?? '')) ?: null,
                ':round'  => trim((string) ($payload['gameRoundId'] ?? '')) ?: null,
                ':vendor' => (string) ($payload['vendorCode'] ?? self::VENDOR_CODE),
                ':game'   => (string) ($payload['gameCode'] ?? self::GAME_CODE),
                ':type'   => $type,
                ':amt'    => $amount,
                ':before' => $before,
                ':after'  => $after,
                ':cur'    => strtoupper((string) ($payload['currencyCode'] ?? 'TRY')),
                ':free'   => !empty($payload['isFreeRound']) ? 1 : 0,
                ':fin'    => !empty($payload['isFinished']) ? 1 : 0,
                ':detail' => trim((string) ($payload['detail'] ?? '')) ?: null,
                ':raw'    => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

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
        $sql    = "UPDATE sportsbook_transactions SET detail = :d";
        $params = [':d' => $detail, ':w' => $wagerId];
        if (!empty($payload['isFinished'] ?? $payload['is_finished'] ?? 0)) {
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
