<?php

declare(strict_types=1);

/**
 * GSC+ (GamingSoft) Operator API v2.0.6 — seamless wallet + launch + catalog sync.
 *
 * Wallet callbacks (operator → us):
 *   POST /api/v2/gamingsoft-wallet/v1/api/seamless/balance|withdraw|deposit|pushbetdata
 *
 * Operator API (us → GSC+):
 *   launch-game, available-products, provider-games
 */
final class GscPlusService
{
    public const GAME_ID_PREFIX = 'gsc:';

    public const WALLET_CODES = [
        0    => 'success',
        999  => 'Internal Server Error',
        1000 => 'API member does not exist',
        1001 => 'API member balance is insufficient',
        1002 => 'API proxy key error',
        1003 => 'Duplicate API transactions',
        1004 => 'API signature is invalid',
        1005 => 'API not getting game list',
        1006 => 'API bet does not exist',
        2000 => 'API product is under maintenance',
    ];

    /**
     * Currency ratio vs wallet storage (GSC appendix).
     * IDR2 1:1000 ⇒ provider_balance = wallet_idr / 1000 (4 decimals).
     */
    private const CURRENCY_RATIOS = [
        'BDT2' => 1000, 'BRL2' => 1000, 'CDF2' => 1000, 'CNY2' => 1000,
        'COP2' => 1000, 'EUR2' => 1000, 'HKD2' => 1000, 'IDR2' => 1000,
        'IDR3' => 100, 'INR2' => 1000, 'IRR2' => 1000, 'JPY2' => 1000,
        'KHR2' => 1000, 'KRW2' => 1000, 'LAK2' => 1000, 'LBP2' => 1000,
        'MAD2' => 1000, 'MMK2' => 1000, 'MMK3' => 100, 'MNT2' => 1000,
        'MXN2' => 1000, 'MYR2' => 1000, 'MYR3' => 100, 'NGN2' => 1000,
        'NPR2' => 1000, 'PHP2' => 1000, 'PKR2' => 1000, 'PYG2' => 1000,
        'SGD2' => 1000, 'THB2' => 1000, 'TRY2' => 1000, 'TWD2' => 1000,
        'TWD5' => 130, 'TZS2' => 1000, 'UGX2' => 1000, 'USD2' => 1000,
        'USDT2' => 1000, 'UZS2' => 1000, 'VND2' => 1000, 'VND3' => 100,
    ];

    /** Base ISO currencies accepted by seamless wallet (plus scaled *2/*3 codes). */
    private const BASE_CURRENCIES = [
        'AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN',
        'BAM', 'BBD', 'BDT', 'BGN', 'BHD', 'BIF', 'BMD', 'BND', 'BOB', 'BRL',
        'BSD', 'BTC', 'BTN', 'BWP', 'BYN', 'BZD', 'CAD', 'CDF', 'CHF', 'CLF',
        'CLP', 'CNY', 'COP', 'CRC', 'CSD', 'CUC', 'CUP', 'CVE', 'CZK', 'DJF',
        'DKK', 'DOGE', 'DOP', 'DZD', 'EGP', 'ERN', 'ETB', 'ETH', 'EUR', 'FJD',
        'FKP', 'FRF', 'FTN', 'GBP', 'GC', 'GEL', 'GGP', 'GHS', 'GIP', 'GMD',
        'GNF', 'GTQ', 'GYD', 'HKD', 'HNL', 'HRK', 'HTG', 'HUF', 'IDR', 'ILS',
        'IMP', 'INR', 'IQD', 'IRR', 'ISK', 'JEP', 'JMD', 'JOD', 'JPY', 'KES',
        'KGS', 'KHR', 'KRW', 'KSH', 'KWD', 'KZT', 'LAK', 'LBP', 'LKR', 'LRD',
        'LSL', 'LTC', 'LYD', 'MAD', 'MBTC', 'MDL', 'METH', 'MGA', 'MKD', 'MMK',
        'MNT', 'MOP', 'MRU', 'MVR', 'MWK', 'MXBT', 'MXN', 'MYR', 'MZN', 'NAD',
        'NGN', 'NIO', 'NOK', 'NOT', 'NPR', 'NTD', 'NZD', 'OMR', 'PAB', 'PEN',
        'PGK', 'PHP', 'PKR', 'PLN', 'PTI', 'PTV', 'PYG', 'QAR', 'RON', 'RSD',
        'RUB', 'RWF', 'SAR', 'SBD', 'SC', 'SCR', 'SDG', 'SEK', 'SGD', 'SHP',
        'SLL', 'SOS', 'SRD', 'SSP', 'STD', 'STN', 'SVC', 'SYP', 'SZL', 'THB',
        'TJS', 'TMT', 'TND', 'TON', 'TOP', 'TRY', 'TTD', 'TWD', 'TZS', 'UBTC',
        'UGX', 'USD', 'USDC', 'USDT', 'UYU', 'UZS', 'VES', 'VND', 'VUV', 'WST',
        'XAF', 'XAG', 'XAU', 'XBT', 'XCD', 'XDR', 'XOF', 'XPD', 'XPF', 'XPT',
        'YER', 'ZAR', 'ZMW', 'ZWL',
    ];

    /** VGY1 staging currencies enabled with GSC+. */
    public const STAGING_CURRENCIES = ['IDR', 'IDR2', 'CNY', 'VND', 'VND2'];

    private const DEBIT_ACTIONS = [
        'BET', 'TIP', 'BET_PRESERVE',
    ];

    private const CREDIT_ACTIONS = [
        'SETTLED', 'FREEBET', 'JACKPOT', 'BONUS', 'PROMO', 'LEADERBOARD',
        'CANCEL', 'PRESERVE_REFUND',
    ];

    private const SIGNED_ACTIONS = [
        'ROLLBACK', 'ADJUSTMENT', 'RESETTLED',
    ];

    /**
     * WBET winnings are not sent via /deposit; the operator must credit the
     * player manually after the /pushbetdata SETTLED notification (doc 2.3).
     */
    private const MANUAL_PAYOUT_PRODUCTS = [1040];

    private static bool $schemaBootstrapped = false;

    public static function bootstrap(PDO $pdo): void
    {
        if (self::$schemaBootstrapped) {
            return;
        }
        self::createSchema($pdo);
        self::ensureDefaultConfig($pdo);
        self::$schemaBootstrapped = true;
    }

    private static function createSchema(PDO $pdo): void
    {
        $paths = [
            dirname(__DIR__) . '/database/migrations/2026_07_28_100000_create_gsc_plus_tables.php',
            dirname(__DIR__) . '/admin/database/migrations/2026_07_28_100000_create_gsc_plus_tables.php',
        ];
        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }
            $runner = require $path;
            if (is_callable($runner)) {
                $runner($pdo);
            }
            return;
        }
    }

    private static function ensureDefaultConfig(PDO $pdo): void
    {
        $pdo->exec(
            "INSERT IGNORE INTO gsc_config
                (id, operator_code, secret_key, operator_url, currency, language_code, channel_code, is_active)
             VALUES (1, '', '', 'https://staging.gsimw.com', 'TRY', 0, 'gscp', 0)"
        );
    }

    public static function config(PDO $pdo): array
    {
        self::bootstrap($pdo);
        $stmt = $pdo->query('SELECT * FROM gsc_config WHERE id = 1 LIMIT 1');
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return is_array($row) ? $row : [];
    }

    public static function updateConfig(PDO $pdo, array $data): void
    {
        self::bootstrap($pdo);
        $allowed = [
            'operator_code', 'secret_key', 'operator_url', 'currency',
            'language_code', 'channel_code', 'operator_lobby_url', 'callback_allowed_ips',
        ];
        $sets = [];
        $params = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = is_string($data[$key]) ? trim($data[$key]) : $data[$key];
            if ($key === 'secret_key' && ($value === null || $value === '')) {
                continue;
            }
            if ($key === 'language_code') {
                $value = (int) $value;
            }
            $sets[] = "{$key} = :{$key}";
            $params[":{$key}"] = $value;
        }
        $sets[] = 'is_active = :is_active';
        $params[':is_active'] = !empty($data['is_active']) ? 1 : 0;
        if ($sets === []) {
            return;
        }
        $sql = 'UPDATE gsc_config SET ' . implode(', ', $sets) . ' WHERE id = 1';
        $pdo->prepare($sql)->execute($params);
    }

    public static function isConfigured(PDO $pdo): bool
    {
        $cfg = self::config($pdo);
        return trim((string) ($cfg['operator_code'] ?? '')) !== ''
            && trim((string) ($cfg['secret_key'] ?? '')) !== ''
            && trim((string) ($cfg['operator_url'] ?? '')) !== ''
            && (int) ($cfg['is_active'] ?? 0) === 1;
    }

    private static function activeConfig(PDO $pdo): array
    {
        $cfg = self::config($pdo);
        if (!self::isConfigured($pdo)) {
            throw new RuntimeException('GSC+ entegrasyonu yapılandırılmamış veya pasif.');
        }
        return $cfg;
    }

    public static function ownsGameId(string $gameId): bool
    {
        return str_starts_with(strtolower(trim($gameId)), strtolower(self::GAME_ID_PREFIX));
    }

    public static function buildGameId(int $productCode, string $gameCode): string
    {
        $gameCode = trim($gameCode);
        if ($gameCode === '') {
            $gameCode = '_lobby';
        }
        return self::GAME_ID_PREFIX . $productCode . ':' . $gameCode;
    }

    /** @return array{product_code:int,game_code:string}|null */
    public static function parseGameId(string $gameId): ?array
    {
        $gameId = trim($gameId);
        if (!self::ownsGameId($gameId)) {
            return null;
        }
        $rest = substr($gameId, strlen(self::GAME_ID_PREFIX));
        $parts = explode(':', $rest, 2);
        if (count($parts) < 1 || !is_numeric($parts[0])) {
            return null;
        }
        return [
            'product_code' => (int) $parts[0],
            'game_code'    => isset($parts[1]) ? trim($parts[1]) : '_lobby',
        ];
    }

    public static function currencyRatio(string $currency): float
    {
        $currency = strtoupper(trim($currency));
        return (float) (self::CURRENCY_RATIOS[$currency] ?? 1);
    }

    /** Base currency without scale suffix (IDR2 → IDR, MMK3 → MMK). */
    public static function providerBaseCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if (preg_match('/^([A-Z]{3})\d+$/', $currency, $m) === 1) {
            return (string) $m[1];
        }

        return $currency;
    }

    /** True when currency is a known GSC appendix code (rejects e.g. "Testing"). */
    public static function isSupportedCurrency(string $currency): bool
    {
        $currency = strtoupper(trim($currency));
        if ($currency === '') {
            return false;
        }
        if (isset(self::CURRENCY_RATIOS[$currency])) {
            return true;
        }
        if (in_array($currency, self::BASE_CURRENCIES, true)) {
            return true;
        }
        if (in_array($currency, self::STAGING_CURRENCIES, true)) {
            return true;
        }

        return false;
    }

    /**
     * Provider-facing balance precision:
     * - IDR/VND/KRW/JPY → 2 dp
     * - scaled *2/*3 (IDR2, VND2, …) → 4 dp
     */
    public static function formatProviderBalance(float $amount, string $currency): float
    {
        $amount = max(0.0, $amount);
        $currency = strtoupper(trim($currency));
        if (self::currencyRatio($currency) > 1) {
            return (float) number_format($amount, 4, '.', '');
        }
        if (in_array($currency, ['IDR', 'VND', 'KRW', 'JPY'], true)) {
            return (float) number_format($amount, 2, '.', '');
        }

        return (float) number_format($amount, 4, '.', '');
    }

    /** Convert provider (GSC) amount → wallet storage amount. */
    public static function toWalletAmount(float $providerAmount, string $currency): float
    {
        return round($providerAmount * self::currencyRatio($currency), 4);
    }

    /**
     * Convert wallet storage amount → provider (GSC) amount.
     * IDR2/VND2 (1:1000): format base currency first, then ÷ ratio (4 decimals).
     * Ensures GetBalance(IDR2) === GetBalance(IDR) / 1000 at 4 dp.
     */
    public static function toProviderAmount(float $walletAmount, string $currency): float
    {
        $currency = strtoupper(trim($currency));
        $ratio = self::currencyRatio($currency);
        if ($ratio > 1) {
            $baseCurrency = self::providerBaseCurrency($currency);
            $baseAmount = self::formatProviderBalance($walletAmount, $baseCurrency);

            return self::formatProviderBalance($baseAmount / $ratio, $currency);
        }

        return self::formatProviderBalance($walletAmount, $currency);
    }

    /** @return array{code:int,message:string,data:list<array<string,mixed>>} */
    private static function batchCurrencyError(array $payload, string $message = 'Invalid currency'): array
    {
        $data = [];
        $batch = is_array($payload['batch_requests'] ?? null) ? $payload['batch_requests'] : [];
        foreach ($batch as $req) {
            if (!is_array($req)) {
                continue;
            }
            $data[] = [
                'member_account' => trim((string) ($req['member_account'] ?? '')),
                'product_code' => (int) ($req['product_code'] ?? $req['Product_code'] ?? 0),
                'balance' => 0,
                'code' => 999,
                'message' => $message,
            ];
        }
        if ($data === []) {
            $data[] = ['balance' => 0, 'code' => 999, 'message' => $message];
        }

        return ['code' => 999, 'message' => $message, 'data' => $data];
    }

    public static function operatorSign(string $requestTime, string $secretKey, string $action, string $operatorCode): string
    {
        return md5($requestTime . $secretKey . $action . $operatorCode);
    }

    public static function callbackSign(string $operatorCode, string $requestTime, string $action, string $secretKey): string
    {
        return md5($operatorCode . $requestTime . $action . $secretKey);
    }

    public static function verifyCallbackSign(array $payload, string $action, string $secretKey, string $operatorCode): bool
    {
        $sign = strtolower(trim((string) ($payload['sign'] ?? '')));
        $requestTime = (string) ($payload['request_time'] ?? '');
        if ($sign === '' || $requestTime === '') {
            return false;
        }
        $expected = self::callbackSign($operatorCode, $requestTime, $action, $secretKey);
        return hash_equals($expected, $sign);
    }

    // ─── Wallet callbacks ───────────────────────────────────────────────

    /**
     * @return array{status:int,body:array}
     */
    public static function wallet(PDO $pdo, string $endpoint, array $payload, string $rawBody = ''): array
    {
        $started = microtime(true);
        self::bootstrap($pdo);
        $endpoint = strtolower(trim($endpoint, '/'));
        $actionMap = [
            'balance' => 'getbalance',
            'withdraw' => 'withdraw',
            'deposit' => 'deposit',
            'pushbetdata' => 'pushbetdata',
        ];
        if (!isset($actionMap[$endpoint])) {
            return ['status' => 404, 'body' => ['code' => 999, 'message' => 'NOT_FOUND']];
        }
        $signAction = $actionMap[$endpoint];

        try {
            $cfg = self::config($pdo);
            if ((int) ($cfg['is_active'] ?? 0) !== 1) {
                $body = ['code' => 999, 'message' => 'Provider inactive'];
                self::logWallet($pdo, $endpoint, null, null, null, 503, 999, 'INACTIVE', $started, $payload, $body);
                return ['status' => 503, 'body' => $body];
            }
            $operatorCode = trim((string) ($cfg['operator_code'] ?? ''));
            $secretKey = (string) ($cfg['secret_key'] ?? '');
            $reqOp = trim((string) ($payload['operator_code'] ?? ''));
            if ($operatorCode === '' || $secretKey === '' || strcasecmp($reqOp, $operatorCode) !== 0) {
                $body = $endpoint === 'pushbetdata'
                    ? ['code' => 1002, 'message' => self::WALLET_CODES[1002]]
                    : ['data' => []];
                if ($endpoint !== 'pushbetdata') {
                    // Still return batch structure with error if possible
                    $body = ['code' => 1002, 'message' => self::WALLET_CODES[1002], 'data' => []];
                }
                self::logWallet($pdo, $endpoint, null, null, null, 200, 1002, 'PROXY_KEY', $started, $payload, $body);
                return ['status' => 200, 'body' => $body];
            }
            if (!self::verifyCallbackSign($payload, $signAction, $secretKey, $operatorCode)) {
                $body = ['code' => 1004, 'message' => self::WALLET_CODES[1004]];
                if ($endpoint !== 'pushbetdata') {
                    $body['data'] = [];
                }
                self::logWallet($pdo, $endpoint, null, null, null, 200, 1004, 'INVALID_SIGN', $started, $payload, $body);
                return ['status' => 200, 'body' => $body];
            }

            $body = match ($endpoint) {
                'balance' => self::walletBalance($pdo, $payload, $cfg),
                'withdraw' => self::walletWithdraw($pdo, $payload, $cfg),
                'deposit' => self::walletDeposit($pdo, $payload, $cfg),
                'pushbetdata' => self::walletPushBetData($pdo, $payload, $cfg),
                default => ['code' => 999, 'message' => 'NOT_FOUND'],
            };
            self::logWallet($pdo, $endpoint, null, null, null, 200, (int) ($body['code'] ?? 0), null, $started, $payload, $body);
            return ['status' => 200, 'body' => $body];
        } catch (Throwable $e) {
            $body = ['code' => 999, 'message' => 'Internal Server Error'];
            if ($endpoint !== 'pushbetdata') {
                $body['data'] = [];
            }
            error_log('[GSC+] wallet ' . $endpoint . ': ' . $e->getMessage());
            self::logWallet($pdo, $endpoint, null, null, null, 500, 999, 'EXCEPTION', $started, $payload, $body + ['error' => $e->getMessage()]);
            return ['status' => 200, 'body' => $body];
        }
    }

    private static function walletBalance(PDO $pdo, array $payload, array $cfg): array
    {
        $currency = strtoupper(trim((string) ($payload['currency'] ?? '')));
        if ($currency === '') {
            $currency = strtoupper(trim((string) ($cfg['currency'] ?? 'IDR')));
        }
        if (!self::isSupportedCurrency($currency)) {
            return self::batchCurrencyError($payload, 'Invalid currency');
        }

        $batch = is_array($payload['batch_requests'] ?? null) ? $payload['batch_requests'] : [];
        $data = [];
        foreach ($batch as $req) {
            if (!is_array($req)) {
                continue;
            }
            $member = trim((string) ($req['member_account'] ?? ''));
            $productCode = (int) ($req['product_code'] ?? $req['Product_code'] ?? 0);
            $user = self::userByMemberAccount($pdo, $member);
            if ($user === null) {
                $data[] = [
                    'member_account' => $member,
                    'product_code' => $productCode,
                    'balance' => 0,
                    'code' => 1000,
                    'message' => self::WALLET_CODES[1000],
                ];
                continue;
            }
            $walletBal = round((float) ($user['balance'] ?? 0), 4);
            $data[] = [
                'member_account' => $member,
                'product_code' => $productCode,
                'balance' => self::toProviderAmount($walletBal, $currency),
                'code' => 0,
                'message' => '',
            ];
        }

        return ['code' => 0, 'message' => '', 'data' => $data];
    }

    private static function walletWithdraw(PDO $pdo, array $payload, array $cfg): array
    {
        return self::walletMoneyBatch($pdo, $payload, $cfg, 'withdraw');
    }

    private static function walletDeposit(PDO $pdo, array $payload, array $cfg): array
    {
        return self::walletMoneyBatch($pdo, $payload, $cfg, 'deposit');
    }

    private static function walletMoneyBatch(PDO $pdo, array $payload, array $cfg, string $direction): array
    {
        $currency = strtoupper(trim((string) ($payload['currency'] ?? '')));
        if ($currency === '') {
            $currency = strtoupper(trim((string) ($cfg['currency'] ?? 'IDR')));
        }
        if (!self::isSupportedCurrency($currency)) {
            return self::batchCurrencyError($payload, 'Invalid currency');
        }

        $batch = is_array($payload['batch_requests'] ?? null) ? $payload['batch_requests'] : [];
        $data = [];
        foreach ($batch as $req) {
            if (!is_array($req)) {
                continue;
            }
            $member = trim((string) ($req['member_account'] ?? ''));
            $productCode = (int) ($req['product_code'] ?? $req['Product_code'] ?? 0);
            $gameType = (string) ($req['game_type'] ?? '');
            $transactions = is_array($req['transactions'] ?? null) ? $req['transactions'] : [];
            $user = self::userByMemberAccount($pdo, $member);
            if ($user === null) {
                $data[] = [
                    'member_account' => $member,
                    'product_code' => $productCode,
                    'before_balance' => 0,
                    'balance' => 0,
                    'code' => 1000,
                    'message' => self::WALLET_CODES[1000],
                ];
                continue;
            }

            $result = self::applyTransactions(
                $pdo,
                $user,
                $transactions,
                $direction,
                $currency,
                $productCode,
                $gameType
            );
            $data[] = [
                'member_account' => $member,
                'product_code' => $productCode,
                'before_balance' => self::toProviderAmount((float) $result['before_balance'], $currency),
                'balance' => self::toProviderAmount((float) $result['balance'], $currency),
                'code' => (int) ($result['code'] ?? 0),
                'message' => (string) ($result['message'] ?? ''),
            ];
        }

        return ['code' => 0, 'message' => '', 'data' => $data];
    }

    /**
     * Apply all transactions for one member in a single DB transaction.
     * Duplicate txn id → code 1003 with current balances (GSC duplicate rule).
     *
     * @param list<array<string,mixed>> $transactions
     * @return array{before_balance:float,balance:float,code:int,message:string}
     */
    private static function applyTransactions(
        PDO $pdo,
        array $user,
        array $transactions,
        string $direction,
        string $currency,
        int $productCode,
        string $gameType
    ): array {
        $userId = (int) $user['id'];
        $member = (string) ($user['username'] ?? '');

        if ($transactions === []) {
            $bal = round((float) ($user['balance'] ?? 0), 4);
            return ['before_balance' => $bal, 'balance' => $bal, 'code' => 0, 'message' => ''];
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id, username, balance, banned FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
            $stmt->execute([':id' => $userId]);
            $locked = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($locked)) {
                $pdo->rollBack();
                return ['before_balance' => 0, 'balance' => 0, 'code' => 1000, 'message' => self::WALLET_CODES[1000]];
            }
            if ((int) ($locked['banned'] ?? 0) === 1) {
                $pdo->rollBack();
                $bal = round((float) ($locked['balance'] ?? 0), 4);
                return ['before_balance' => $bal, 'balance' => $bal, 'code' => 1000, 'message' => 'Member blocked'];
            }

            $batchBefore = round((float) $locked['balance'], 4);
            $balance = $batchBefore;
            $hadDuplicate = false;

            foreach ($transactions as $tx) {
                if (!is_array($tx)) {
                    continue;
                }
                $txnId = trim((string) ($tx['id'] ?? ''));
                if ($txnId === '') {
                    $pdo->rollBack();
                    return [
                        'before_balance' => $batchBefore,
                        'balance' => $batchBefore,
                        'code' => 999,
                        'message' => 'Missing transaction id',
                    ];
                }

                $existing = $pdo->prepare(
                    'SELECT before_balance, after_balance FROM gsc_transactions WHERE transaction_id = :id LIMIT 1'
                );
                $existing->execute([':id' => $txnId]);
                $prev = $existing->fetch(PDO::FETCH_ASSOC);
                if (is_array($prev)) {
                    $hadDuplicate = true;
                    $balance = round((float) ($prev['after_balance'] ?? $balance), 4);
                    continue;
                }

                $action = strtoupper(trim((string) ($tx['action'] ?? '')));
                if (!self::isKnownAction($action)) {
                    $pdo->rollBack();
                    return [
                        'before_balance' => $batchBefore,
                        'balance' => $batchBefore,
                        'code' => 999,
                        'message' => 'Invalid action',
                    ];
                }
                // Doc: CANCEL/ROLLBACK must confirm the referenced bet exists → 1006 otherwise.
                if (in_array($action, ['CANCEL', 'ROLLBACK'], true)
                    && !self::wagerExists($pdo, trim((string) ($tx['wager_code'] ?? '')))
                ) {
                    $pdo->rollBack();
                    return [
                        'before_balance' => $batchBefore,
                        'balance' => $batchBefore,
                        'code' => 1006,
                        'message' => self::WALLET_CODES[1006],
                    ];
                }
                $providerAmount = (float) ($tx['amount'] ?? 0);
                $deltaWallet = self::resolveWalletDelta($direction, $action, $providerAmount, $currency);
                $before = $balance;
                $after = round($before + $deltaWallet, 4);
                // GSC appendix: RESETTLED may legitimately push a balance negative
                // (fractional sportsbook corrections) and must not be rejected.
                if ($after < 0 && $action !== 'RESETTLED') {
                    $pdo->rollBack();
                    return [
                        'before_balance' => $batchBefore,
                        'balance' => $batchBefore,
                        'code' => 1001,
                        'message' => self::WALLET_CODES[1001],
                    ];
                }

                $pdo->prepare('UPDATE users SET balance = :bal WHERE id = :id')
                    ->execute([':bal' => $after, ':id' => $userId]);

                if (class_exists('WageringService', false) || class_exists('WageringService')) {
                    if (!class_exists('WageringService', false)) {
                        $wageringPath = dirname(__DIR__) . '/services/WageringService.php';
                        if (is_file($wageringPath)) {
                            require_once $wageringPath;
                        }
                    }
                    if (class_exists('WageringService', false)) {
                        if ($action === 'BET' && $deltaWallet < 0) {
                            WageringService::registerBet($pdo, $userId, abs($deltaWallet));
                        } elseif (in_array($action, ['CANCEL', 'ROLLBACK'], true) && $deltaWallet > 0) {
                            WageringService::reverseBet($pdo, $userId, abs($deltaWallet));
                        }
                    }
                }

                $pdo->prepare(
                    'INSERT INTO gsc_transactions
                        (user_id, member_account, transaction_id, action, wager_code, wager_status, wager_type,
                         round_id, product_code, game_code, game_type, channel_code, amount, bet_amount,
                         valid_bet_amount, prize_amount, tip_amount, before_balance, after_balance, currency,
                         settled_at, direction, payload, raw_payload)
                     VALUES
                        (:uid, :member, :txn, :action, :wager, :wstatus, :wtype, :round, :product, :game,
                         :gtype, :channel, :amount, :bet, :vbet, :prize, :tip, :before, :after, :cur,
                         :settled, :dir, :payload, :raw)'
                )->execute([
                    ':uid'     => $userId,
                    ':member'  => $member !== '' ? $member : (string) ($locked['username'] ?? ''),
                    ':txn'     => $txnId,
                    ':action'  => $action,
                    ':wager'   => trim((string) ($tx['wager_code'] ?? '')) ?: null,
                    ':wstatus' => trim((string) ($tx['wager_status'] ?? '')) ?: null,
                    ':wtype'   => trim((string) ($tx['wager_type'] ?? '')) ?: null,
                    ':round'   => trim((string) ($tx['round_id'] ?? '')) ?: null,
                    ':product' => $productCode > 0 ? $productCode : ((int) ($tx['product_code'] ?? 0) ?: null),
                    ':game'    => trim((string) ($tx['game_code'] ?? '')) ?: null,
                    ':gtype'   => $gameType !== '' ? $gameType : (trim((string) ($tx['game_type'] ?? '')) ?: null),
                    ':channel' => trim((string) ($tx['channel_code'] ?? $tx['Channel_code'] ?? '')) ?: null,
                    ':amount'  => self::toWalletAmount($providerAmount, $currency),
                    ':bet'     => self::toWalletAmount((float) ($tx['bet_amount'] ?? 0), $currency),
                    ':vbet'    => self::toWalletAmount((float) ($tx['valid_bet_amount'] ?? 0), $currency),
                    ':prize'   => self::toWalletAmount((float) ($tx['prize_amount'] ?? $tx['prized_amount'] ?? 0), $currency),
                    ':tip'     => self::toWalletAmount((float) ($tx['tip_amount'] ?? 0), $currency),
                    ':before'  => $before,
                    ':after'   => $after,
                    ':cur'     => $currency,
                    ':settled' => isset($tx['settled_at']) ? (int) $tx['settled_at'] : null,
                    ':dir'     => $direction,
                    ':payload' => isset($tx['payload']) ? json_encode($tx['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    ':raw'     => json_encode($tx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);

                $balance = $after;
            }

            $pdo->commit();
            if ($hadDuplicate && count($transactions) === 1) {
                return [
                    'before_balance' => $batchBefore,
                    'balance' => $balance,
                    'code' => 1003,
                    'message' => self::WALLET_CODES[1003],
                ];
            }
            return [
                'before_balance' => $batchBefore,
                'balance' => $balance,
                'code' => 0,
                'message' => '',
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (str_contains($e->getMessage(), '1062') || stripos($e->getMessage(), 'Duplicate') !== false) {
                $balStmt = $pdo->prepare('SELECT balance FROM users WHERE id = :id LIMIT 1');
                $balStmt->execute([':id' => $userId]);
                $bal = round((float) ($balStmt->fetchColumn() ?: 0), 4);
                return [
                    'before_balance' => $bal,
                    'balance' => $bal,
                    'code' => 1003,
                    'message' => self::WALLET_CODES[1003],
                ];
            }
            throw $e;
        }
    }

    /** Only the appendix Transaction Action Types are accepted (e.g. rejects INVALID_ACTION). */
    private static function isKnownAction(string $action): bool
    {
        return in_array($action, self::DEBIT_ACTIONS, true)
            || in_array($action, self::CREDIT_ACTIONS, true)
            || in_array($action, self::SIGNED_ACTIONS, true);
    }

    /** Wager is known if seen in wallet transactions or pushed bet data. */
    private static function wagerExists(PDO $pdo, string $wagerCode): bool
    {
        if ($wagerCode === '') {
            return false;
        }
        $stmt = $pdo->prepare('SELECT id FROM gsc_transactions WHERE wager_code = :w LIMIT 1');
        $stmt->execute([':w' => $wagerCode]);
        if ($stmt->fetchColumn() !== false) {
            return true;
        }
        $stmt = $pdo->prepare('SELECT id FROM gsc_wagers WHERE wager_code = :w LIMIT 1');
        $stmt->execute([':w' => $wagerCode]);

        return $stmt->fetchColumn() !== false;
    }

    private static function resolveWalletDelta(string $direction, string $action, float $providerAmount, string $currency): float
    {
        $walletAbs = self::toWalletAmount(abs($providerAmount), $currency);
        if (in_array($action, self::SIGNED_ACTIONS, true)) {
            // Positive amount → credit; negative → debit (GSC appendix).
            $signed = self::toWalletAmount($providerAmount, $currency);
            return round($signed, 4);
        }
        if ($direction === 'withdraw') {
            if (in_array($action, self::CREDIT_ACTIONS, true)) {
                return round($walletAbs, 4);
            }
            return round(-$walletAbs, 4);
        }
        // deposit
        if (in_array($action, self::DEBIT_ACTIONS, true)) {
            return round(-$walletAbs, 4);
        }
        return round($walletAbs, 4);
    }

    private static function walletPushBetData(PDO $pdo, array $payload, array $cfg): array
    {
        $wagers = is_array($payload['wagers'] ?? null) ? $payload['wagers'] : [];
        foreach ($wagers as $wager) {
            if (!is_array($wager)) {
                continue;
            }
            $code = trim((string) ($wager['wager_code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $currency = strtoupper(trim((string) ($wager['currency'] ?? $cfg['currency'] ?? 'TRY')));
            $pdo->prepare(
                'INSERT INTO gsc_wagers
                    (member_account, wager_code, wager_status, wager_type, round_id, product_code, game_code,
                     game_type, channel_code, currency, bet_amount, valid_bet_amount, prize_amount, tip_amount,
                     settled_at, wager_created_at, payload, raw_payload)
                 VALUES
                    (:member, :code, :status, :type, :round, :product, :game, :gtype, :channel, :cur,
                     :bet, :vbet, :prize, :tip, :settled, :created, :payload, :raw)
                 ON DUPLICATE KEY UPDATE
                    wager_status = VALUES(wager_status),
                    wager_type = VALUES(wager_type),
                    round_id = VALUES(round_id),
                    product_code = VALUES(product_code),
                    game_code = VALUES(game_code),
                    game_type = VALUES(game_type),
                    channel_code = VALUES(channel_code),
                    currency = VALUES(currency),
                    bet_amount = VALUES(bet_amount),
                    valid_bet_amount = VALUES(valid_bet_amount),
                    prize_amount = VALUES(prize_amount),
                    tip_amount = VALUES(tip_amount),
                    settled_at = VALUES(settled_at),
                    wager_created_at = VALUES(wager_created_at),
                    payload = VALUES(payload),
                    raw_payload = VALUES(raw_payload)'
            )->execute([
                ':member'  => trim((string) ($wager['member_account'] ?? '')),
                ':code'    => $code,
                ':status'  => trim((string) ($wager['wager_status'] ?? '')) ?: null,
                ':type'    => trim((string) ($wager['wager_type'] ?? '')) ?: null,
                ':round'   => trim((string) ($wager['round_id'] ?? '')) ?: null,
                ':product' => isset($wager['product_code']) ? (int) $wager['product_code'] : null,
                ':game'    => trim((string) ($wager['game_code'] ?? '')) ?: null,
                ':gtype'   => trim((string) ($wager['game_type'] ?? '')) ?: null,
                ':channel' => trim((string) ($wager['channel_code'] ?? '')) ?: null,
                ':cur'     => $currency,
                ':bet'     => self::toWalletAmount((float) ($wager['bet_amount'] ?? 0), $currency),
                ':vbet'    => self::toWalletAmount((float) ($wager['valid_bet_amount'] ?? 0), $currency),
                ':prize'   => self::toWalletAmount((float) ($wager['prize_amount'] ?? 0), $currency),
                ':tip'     => self::toWalletAmount((float) ($wager['tip_amount'] ?? 0), $currency),
                ':settled' => isset($wager['settled_at']) ? (int) $wager['settled_at'] : null,
                ':created' => isset($wager['created_at']) ? (int) $wager['created_at'] : null,
                ':payload' => isset($wager['payload']) ? json_encode($wager['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                ':raw'     => json_encode($wager, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            self::creditManualPayoutWager($pdo, $wager, $code, $currency);
        }
        return ['code' => 0, 'message' => ''];
    }

    /**
     * WBET-style products pay winnings only via pushbetdata; credit the prize
     * once per wager_code (idempotent through gsc_transactions unique txn id).
     */
    private static function creditManualPayoutWager(PDO $pdo, array $wager, string $wagerCode, string $currency): void
    {
        $productCode = (int) ($wager['product_code'] ?? 0);
        if (!in_array($productCode, self::MANUAL_PAYOUT_PRODUCTS, true)) {
            return;
        }
        if (strtoupper(trim((string) ($wager['wager_status'] ?? ''))) !== 'SETTLED') {
            return;
        }
        $prizeWallet = self::toWalletAmount((float) ($wager['prize_amount'] ?? 0), $currency);
        if ($prizeWallet <= 0) {
            return;
        }
        $user = self::userByMemberAccount($pdo, trim((string) ($wager['member_account'] ?? '')));
        if ($user === null) {
            return;
        }
        $txnId = 'wbet:' . $wagerCode;

        $pdo->beginTransaction();
        try {
            $dup = $pdo->prepare('SELECT id FROM gsc_transactions WHERE transaction_id = :id LIMIT 1');
            $dup->execute([':id' => $txnId]);
            if ($dup->fetchColumn() !== false) {
                $pdo->rollBack();
                return;
            }
            $stmt = $pdo->prepare('SELECT id, username, balance FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
            $stmt->execute([':id' => (int) $user['id']]);
            $locked = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($locked)) {
                $pdo->rollBack();
                return;
            }
            $before = round((float) $locked['balance'], 4);
            $after = round($before + $prizeWallet, 4);
            $pdo->prepare('UPDATE users SET balance = :bal WHERE id = :id')
                ->execute([':bal' => $after, ':id' => (int) $locked['id']]);
            $pdo->prepare(
                'INSERT INTO gsc_transactions
                    (user_id, member_account, transaction_id, action, wager_code, wager_status, round_id,
                     product_code, game_code, game_type, amount, prize_amount, before_balance, after_balance,
                     currency, direction, raw_payload)
                 VALUES
                    (:uid, :member, :txn, \'SETTLED\', :wager, \'SETTLED\', :round, :product, :game, :gtype,
                     :amount, :prize, :before, :after, :cur, \'deposit\', :raw)'
            )->execute([
                ':uid'     => (int) $locked['id'],
                ':member'  => (string) ($locked['username'] ?? ''),
                ':txn'     => $txnId,
                ':wager'   => $wagerCode,
                ':round'   => trim((string) ($wager['round_id'] ?? '')) ?: null,
                ':product' => $productCode,
                ':game'    => trim((string) ($wager['game_code'] ?? '')) ?: null,
                ':gtype'   => trim((string) ($wager['game_type'] ?? '')) ?: null,
                ':amount'  => $prizeWallet,
                ':prize'   => $prizeWallet,
                ':before'  => $before,
                ':after'   => $after,
                ':cur'     => $currency,
                ':raw'     => json_encode($wager, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[GSC+] WBET payout ' . $wagerCode . ': ' . $e->getMessage());
        }
    }

    // ─── Operator API ───────────────────────────────────────────────────

    /**
     * @return array{success:bool,code:int,message:string,data?:array,game_url?:string}
     */
    public static function launch(PDO $pdo, ?array $user, array $input): array
    {
        try {
            $cfg = self::activeConfig($pdo);
        } catch (Throwable $e) {
            return ['success' => false, 'code' => 503, 'message' => $e->getMessage()];
        }

        $parsed = self::parseGameId(trim((string) ($input['game_id'] ?? $input['gameId'] ?? '')));
        if ($parsed === null) {
            return ['success' => false, 'code' => 404, 'message' => 'Geçersiz GSC+ oyun kimliği.'];
        }

        $productCode = $parsed['product_code'];
        $gameCode = $parsed['game_code'];
        $isLobby = ($gameCode === '' || $gameCode === '_lobby');

        $currency = strtoupper(trim((string) ($cfg['currency'] ?? 'TRY')));
        $gameRow = null;
        if (!$isLobby) {
            $gameStmt = $pdo->prepare(
                'SELECT * FROM gsc_games
                 WHERE product_code = :p AND game_code = :g AND is_active = 1
                   AND (support_currency = :c OR support_currency = \'\' OR support_currency IS NULL)
                 ORDER BY (support_currency = :c2) DESC
                 LIMIT 1'
            );
            $gameStmt->execute([
                ':p' => $productCode,
                ':g' => $gameCode,
                ':c' => $currency,
                ':c2' => $currency,
            ]);
            $gameRow = $gameStmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($gameRow)) {
                return ['success' => false, 'code' => 404, 'message' => 'Oyun bulunamadı veya pasif.'];
            }
            $status = strtoupper(trim((string) ($gameRow['status'] ?? '')));
            if ($status !== '' && !in_array($status, ['ACTIVATED', 'ACTIVAT'], true)) {
                return ['success' => false, 'code' => 503, 'message' => 'Oyun bakımda veya pasif.'];
            }
        } else {
            $prodStmt = $pdo->prepare(
                'SELECT * FROM gsc_products WHERE product_code = :p AND is_active = 1 LIMIT 1'
            );
            $prodStmt->execute([':p' => $productCode]);
            $prod = $prodStmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($prod)) {
                return ['success' => false, 'code' => 404, 'message' => 'Ürün bulunamadı veya pasif.'];
            }
            $gameRow = [
                'game_type' => (string) ($prod['game_type'] ?? 'SLOT'),
                'entry_type' => (int) ($prod['entry_type'] ?? 2),
                'product_name' => (string) ($prod['product_name'] ?? ''),
            ];
        }

        $isGuest = !is_array($user) || (int) ($user['id'] ?? 0) <= 0;
        if ($isGuest) {
            return ['success' => false, 'code' => 401, 'message' => 'GSC+ oyunu için giriş yapın.'];
        }

        $userId = (int) $user['id'];
        $memberAccount = self::memberAccountFromUser($user);
        $nickname = trim((string) ($user['username'] ?? ('user_' . $userId)));
        $password = self::memberPassword($cfg, $user);
        $gameType = strtoupper(trim((string) ($gameRow['game_type'] ?? 'SLOT')));
        $platform = self::resolvePlatform($input);
        $languageCode = (int) ($cfg['language_code'] ?? 0);
        $ip = trim((string) ($input['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
        $lobbyUrl = trim((string) ($cfg['operator_lobby_url'] ?? ''));
        if ($lobbyUrl === '') {
            $lobbyUrl = trim((string) ($input['home_url'] ?? ''));
        }
        if ($lobbyUrl === '') {
            $lobbyUrl = defined('SITE_URL') && trim((string) SITE_URL) !== ''
                ? rtrim((string) SITE_URL, '/')
                : ('https://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        }

        $requestTime = (string) time();
        $operatorCode = (string) $cfg['operator_code'];
        $sign = self::operatorSign($requestTime, (string) $cfg['secret_key'], 'launchgame', $operatorCode);

        $body = [
            'operator_code' => $operatorCode,
            'member_account' => $memberAccount,
            'password' => $password,
            'nickname' => $nickname,
            'currency' => $currency,
            'game_code' => $isLobby ? null : $gameCode,
            'product_code' => $productCode,
            'game_type' => $gameType,
            'language_code' => $languageCode,
            'ip' => $ip,
            'platform' => $platform,
            'sign' => $sign,
            'request_time' => (int) $requestTime,
            'operator_lobby_url' => $lobbyUrl,
        ];

        try {
            $response = self::operatorRequest($pdo, 'POST', '/api/operators/launch-game', $body);
        } catch (Throwable $e) {
            return ['success' => false, 'code' => 422, 'message' => 'GSC+ launch hatası: ' . $e->getMessage()];
        }

        $code = (int) ($response['code'] ?? 0);
        $url = trim((string) ($response['url'] ?? $response['URL'] ?? ''));
        $content = (string) ($response['content'] ?? $response['Content'] ?? '');
        if ($code !== 200 && $code !== 0) {
            $msg = trim((string) ($response['message'] ?? $response['Message'] ?? 'Launch failed'));
            return ['success' => false, 'code' => 422, 'message' => 'GSC+: ' . ($msg !== '' ? $msg : ('code ' . $code))];
        }
        if ($url === '' && $content === '') {
            return ['success' => false, 'code' => 422, 'message' => 'GSC+ launch URL dönmedi.'];
        }

        $pdo->prepare(
            'INSERT INTO gsc_sessions
                (user_id, member_account, product_code, game_code, game_type, currency, platform, launch_url, request_payload, response_payload)
             VALUES (:uid, :member, :product, :game, :gtype, :cur, :platform, :url, :req, :res)'
        )->execute([
            ':uid' => $userId,
            ':member' => $memberAccount,
            ':product' => $productCode,
            ':game' => $isLobby ? null : $gameCode,
            ':gtype' => $gameType,
            ':cur' => $currency,
            ':platform' => $platform,
            ':url' => $url !== '' ? $url : null,
            ':req' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':res' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $launchUrl = $url !== '' ? $url : ('data:text/html;charset=utf-8,' . rawurlencode($content));
        return [
            'success' => true,
            'code' => 200,
            'message' => 'Oyun başlatıldı.',
            'data' => [
                'game_url' => $launchUrl,
                'launch_url' => $launchUrl,
                'open_mode' => 'iframe',
                'mode' => 'real',
                'content' => $content !== '' && $url === '' ? $content : null,
            ],
            'game_url' => $launchUrl,
        ];
    }

    /**
     * 3.12 Wallet Balance Inquiry — agent wallet balances per contracted currency.
     *
     * @return array{is_credit:bool,currencies:list<array{currency:string,current_balance:float,updated_at:string}>}
     */
    public static function agentWalletBalance(PDO $pdo): array
    {
        $cfg = self::activeConfig($pdo);
        $requestTime = (string) (int) round(microtime(true) * 1000);
        $operatorCode = (string) $cfg['operator_code'];
        $sign = self::operatorSign($requestTime, (string) $cfg['secret_key'], 'getwalletcurrencies', $operatorCode);
        $query = http_build_query([
            'operator_code' => $operatorCode,
            'sign' => $sign,
            'request_time' => $requestTime,
        ]);
        $response = self::operatorRequest($pdo, 'GET', '/api/operators/wallet-balance?' . $query);
        $code = (int) ($response['code'] ?? -1);
        if ($code !== 0) {
            throw new RuntimeException(
                'GSC+ wallet-balance: ' . trim((string) ($response['message'] ?? ('code ' . $code)))
            );
        }
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $currencies = [];
        foreach ((is_array($data['currencies'] ?? null) ? $data['currencies'] : []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $currencies[] = [
                'currency' => strtoupper(trim((string) ($row['currency'] ?? ''))),
                'current_balance' => (float) ($row['current_balance'] ?? 0),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ];
        }

        return [
            'is_credit' => (bool) ($data['is_credit'] ?? false),
            'currencies' => $currencies,
        ];
    }

    /** @return array{count:int} */
    public static function syncProducts(PDO $pdo): array
    {
        $cfg = self::activeConfig($pdo);
        $requestTime = (string) time();
        $operatorCode = (string) $cfg['operator_code'];
        $sign = self::operatorSign($requestTime, (string) $cfg['secret_key'], 'productlist', $operatorCode);
        $query = http_build_query([
            'operator_code' => $operatorCode,
            'sign' => $sign,
            'request_time' => $requestTime,
        ]);
        $response = self::operatorRequest($pdo, 'GET', '/api/operators/available-products?' . $query);
        $list = self::extractList($response, ['data', 'products', 'available_products']);
        if ($list === [] && array_is_list($response)) {
            $list = $response;
        }

        $count = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            $productCode = (int) ($item['product_code'] ?? 0);
            if ($productCode <= 0) {
                continue;
            }
            $currency = strtoupper(trim((string) ($item['currency'] ?? $cfg['currency'] ?? '')));
            $status = strtoupper(trim((string) ($item['status'] ?? '')));
            $pdo->prepare(
                'INSERT INTO gsc_products
                    (product_code, product_id, provider_id, provider, product_name, game_type, currency, status,
                     entry_type, is_active, raw_payload, synced_at)
                 VALUES
                    (:pc, :pid, :provid, :provider, :pname, :gtype, :cur, :status, :entry, :active, :raw, :synced)
                 ON DUPLICATE KEY UPDATE
                    product_id = VALUES(product_id),
                    provider_id = VALUES(provider_id),
                    provider = VALUES(provider),
                    product_name = VALUES(product_name),
                    game_type = VALUES(game_type),
                    status = VALUES(status),
                    entry_type = VALUES(entry_type),
                    is_active = VALUES(is_active),
                    raw_payload = VALUES(raw_payload),
                    synced_at = VALUES(synced_at)'
            )->execute([
                ':pc' => $productCode,
                ':pid' => isset($item['product_id']) ? (int) $item['product_id'] : null,
                ':provid' => isset($item['provider_id']) ? (int) $item['provider_id'] : null,
                ':provider' => trim((string) ($item['provider'] ?? '')),
                ':pname' => trim((string) ($item['product_name'] ?? '')),
                ':gtype' => strtoupper(trim((string) ($item['game_type'] ?? ''))),
                ':cur' => $currency,
                ':status' => $status,
                ':entry' => (int) ($item['entry_type'] ?? 1),
                ':active' => in_array($status, ['ACTIVATED', ''], true) ? 1 : 0,
                ':raw' => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':synced' => $now,
            ]);
            $count++;
        }

        $pdo->prepare('UPDATE gsc_config SET products_synced_at = :t WHERE id = 1')->execute([':t' => $now]);
        return ['count' => $count];
    }

    /** @return array{count:int,products:int} */
    public static function syncGames(PDO $pdo, ?int $onlyProductCode = null): array
    {
        $cfg = self::activeConfig($pdo);
        $currency = strtoupper(trim((string) ($cfg['currency'] ?? 'TRY')));

        if ($onlyProductCode !== null && $onlyProductCode > 0) {
            $products = [['product_code' => $onlyProductCode, 'game_type' => '', 'entry_type' => 1, 'provider' => '', 'product_name' => '']];
        } else {
            $prodStmt = $pdo->query(
                'SELECT product_code, game_type, entry_type, provider, product_name
                 FROM gsc_products WHERE is_active = 1'
            );
            $products = $prodStmt ? $prodStmt->fetchAll(PDO::FETCH_ASSOC) : [];
            if ($products === []) {
                self::syncProducts($pdo);
                $prodStmt = $pdo->query(
                    'SELECT product_code, game_type, entry_type, provider, product_name
                     FROM gsc_products WHERE is_active = 1'
                );
                $products = $prodStmt ? $prodStmt->fetchAll(PDO::FETCH_ASSOC) : [];
            }
        }

        $count = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($products as $product) {
            $productCode = (int) ($product['product_code'] ?? 0);
            if ($productCode <= 0) {
                continue;
            }
            $gameType = strtoupper(trim((string) ($product['game_type'] ?? '')));
            $requestTime = (string) time();
            $operatorCode = (string) $cfg['operator_code'];
            $sign = self::operatorSign($requestTime, (string) $cfg['secret_key'], 'gamelist', $operatorCode);
            $params = [
                'product_code' => $productCode,
                'operator_code' => $operatorCode,
                'sign' => $sign,
                'request_time' => $requestTime,
            ];
            if ($gameType !== '') {
                $params['game_type'] = $gameType;
            }
            try {
                $response = self::operatorRequest(
                    $pdo,
                    'GET',
                    '/api/operators/provider-games?' . http_build_query($params)
                );
            } catch (Throwable $e) {
                error_log('[GSC+] syncGames product ' . $productCode . ': ' . $e->getMessage());
                continue;
            }

            $games = self::extractList($response, ['provider_games', 'data', 'games']);
            foreach ($games as $game) {
                if (!is_array($game)) {
                    continue;
                }
                $code = trim((string) ($game['game_code'] ?? ''));
                if ($code === '') {
                    continue;
                }
                $supportCurrency = strtoupper(trim((string) ($game['support_currency'] ?? '')));
                if ($supportCurrency !== '' && $supportCurrency !== $currency) {
                    // Keep other currencies but mark inactive for our primary currency lobby.
                }
                $status = strtoupper(trim((string) ($game['status'] ?? 'ACTIVATED')));
                $active = in_array($status, ['ACTIVATED', 'ACTIVAT'], true) ? 1 : 0;
                if ($supportCurrency !== '' && $supportCurrency !== $currency) {
                    $active = 0;
                }
                $pdo->prepare(
                    'INSERT INTO gsc_games
                        (product_code, game_code, game_name, game_type, image_url, support_currency, status,
                         allow_free_round, entry_type, provider, product_name, lang_name, lang_icon,
                         provider_created_at, raw_payload, is_active, synced_at)
                     VALUES
                        (:pc, :gc, :gn, :gt, :img, :cur, :status, :fr, :entry, :provider, :pname,
                         :lname, :licon, :created, :raw, :active, :synced)
                     ON DUPLICATE KEY UPDATE
                        game_name = VALUES(game_name),
                        game_type = VALUES(game_type),
                        image_url = VALUES(image_url),
                        status = VALUES(status),
                        allow_free_round = VALUES(allow_free_round),
                        entry_type = VALUES(entry_type),
                        provider = VALUES(provider),
                        product_name = VALUES(product_name),
                        lang_name = VALUES(lang_name),
                        lang_icon = VALUES(lang_icon),
                        provider_created_at = VALUES(provider_created_at),
                        raw_payload = VALUES(raw_payload),
                        is_active = VALUES(is_active),
                        synced_at = VALUES(synced_at)'
                )->execute([
                    ':pc' => (int) ($game['product_code'] ?? $productCode),
                    ':gc' => $code,
                    ':gn' => trim((string) ($game['game_name'] ?? $code)),
                    ':gt' => strtoupper(trim((string) ($game['game_type'] ?? $gameType))),
                    ':img' => trim((string) ($game['image_url'] ?? '')) ?: null,
                    ':cur' => $supportCurrency,
                    ':status' => $status,
                    ':fr' => !empty($game['allow_free_round']) ? 1 : 0,
                    ':entry' => (int) ($product['entry_type'] ?? 1),
                    ':provider' => trim((string) ($product['provider'] ?? '')),
                    ':pname' => trim((string) ($product['product_name'] ?? '')),
                    ':lname' => isset($game['lang_name']) ? json_encode($game['lang_name'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    ':licon' => isset($game['lang_icon']) ? json_encode($game['lang_icon'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    ':created' => isset($game['created_at']) ? (int) $game['created_at'] : null,
                    ':raw' => json_encode($game, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ':active' => $active,
                    ':synced' => $now,
                ]);
                $count++;
            }

            // Lobby-only products: ensure a synthetic lobby row for launch.
            if ((int) ($product['entry_type'] ?? 1) === 2) {
                $pdo->prepare(
                    'INSERT INTO gsc_games
                        (product_code, game_code, game_name, game_type, support_currency, status,
                         entry_type, provider, product_name, is_active, synced_at)
                     VALUES
                        (:pc, \'_lobby\', :gn, :gt, :cur, \'ACTIVATED\', 2, :provider, :pname, 1, :synced)
                     ON DUPLICATE KEY UPDATE
                        game_name = VALUES(game_name),
                        game_type = VALUES(game_type),
                        is_active = 1,
                        synced_at = VALUES(synced_at)'
                )->execute([
                    ':pc' => $productCode,
                    ':gn' => trim((string) ($product['product_name'] ?? ('Product ' . $productCode))) . ' Lobby',
                    ':gt' => $gameType !== '' ? $gameType : 'SLOT',
                    ':cur' => $currency,
                    ':provider' => trim((string) ($product['provider'] ?? '')),
                    ':pname' => trim((string) ($product['product_name'] ?? '')),
                    ':synced' => $now,
                ]);
                $count++;
            }
        }

        $pdo->prepare('UPDATE gsc_config SET games_synced_at = :t WHERE id = 1')->execute([':t' => $now]);
        return ['count' => $count, 'products' => count($products)];
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    public static function memberAccountFromUser(array $user): string
    {
        $username = trim((string) ($user['username'] ?? ''));
        if ($username !== '') {
            return substr($username, 0, 50);
        }
        return substr('u' . (int) ($user['id'] ?? 0), 0, 50);
    }

    private static function memberPassword(array $cfg, array $user): string
    {
        $seed = (string) ($cfg['secret_key'] ?? '') . '|' . (int) ($user['id'] ?? 0) . '|' . (string) ($user['username'] ?? '');
        return md5($seed);
    }

    private static function resolvePlatform(array $input): string
    {
        $platform = strtoupper(trim((string) ($input['platform'] ?? '')));
        if (in_array($platform, ['WEB', 'DESKTOP', 'MOBILE', 'WIDGET'], true)) {
            return $platform;
        }
        $channel = strtolower(trim((string) ($input['channel'] ?? '')));
        if ($channel === 'mobile') {
            return 'MOBILE';
        }
        $ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) {
            return 'MOBILE';
        }
        return 'WEB';
    }

    /** @return array<string,mixed>|null */
    private static function userByMemberAccount(PDO $pdo, string $member): ?array
    {
        $member = trim($member);
        if ($member === '') {
            return null;
        }
        $stmt = $pdo->prepare('SELECT id, username, balance, banned FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $member]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            return $row;
        }
        if (ctype_digit($member)) {
            $stmt = $pdo->prepare('SELECT id, username, balance, banned FROM users WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => (int) $member]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return $row;
            }
        }
        return null;
    }

    /**
     * @param list<string> $keys
     * @return list<mixed>
     */
    private static function extractList(array $response, array $keys): array
    {
        foreach ($keys as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return array_values($response[$key]);
            }
        }
        return [];
    }

    /** @return array<string,mixed> */
    private static function operatorRequest(PDO $pdo, string $method, string $path, ?array $jsonBody = null): array
    {
        $cfg = self::config($pdo);
        $base = rtrim((string) ($cfg['operator_url'] ?? ''), '/');
        if ($base === '') {
            throw new RuntimeException('GSC+ operator_url boş.');
        }
        $url = $base . (str_starts_with($path, '/') ? $path : '/' . $path);
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl_init failed');
        }
        $headers = ['Accept: application/json'];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($jsonBody !== null) {
            $encoded = json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = $encoded;
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno !== 0) {
            throw new RuntimeException('GSC+ HTTP error: ' . $error);
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            throw new RuntimeException('GSC+ invalid JSON response (HTTP ' . $http . ')');
        }
        return $decoded;
    }

    private static function logWallet(
        PDO $pdo,
        string $method,
        ?int $userId,
        ?string $memberAccount,
        ?string $transactionId,
        int $httpStatus,
        ?int $statusCode,
        ?string $errCode,
        float $started,
        array $request,
        array $response
    ): void {
        try {
            $duration = (int) max(0, round((microtime(true) - $started) * 1000));
            $pdo->prepare(
                'INSERT INTO gsc_wallet_logs
                    (method, user_id, member_account, transaction_id, http_status, status_code, error_code,
                     duration_ms, request_payload, response_payload)
                 VALUES
                    (:method, :uid, :member, :txn, :http, :status, :err, :dur, :req, :res)'
            )->execute([
                ':method' => $method,
                ':uid' => $userId,
                ':member' => $memberAccount,
                ':txn' => $transactionId,
                ':http' => $httpStatus,
                ':status' => $statusCode,
                ':err' => $errCode,
                ':dur' => min(65535, $duration),
                ':req' => json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':res' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable) {
        }
    }

    public static function isSlotGameType(string $gameType): bool
    {
        $t = strtoupper(trim($gameType));
        return in_array($t, ['SLOT', 'FISHING', 'POKER', 'OTHERS', 'QIPAI', 'P2P', 'BONUS'], true);
    }

    public static function isLiveGameType(string $gameType): bool
    {
        $t = strtoupper(trim($gameType));
        return in_array($t, ['LIVE_CASINO', 'LIVE_CASINO_PREMIUM'], true);
    }
}
